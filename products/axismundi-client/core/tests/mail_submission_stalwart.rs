//! Explicit local-server interop for the product composer.
use axismundi_client_core::jmap::mail::compose::{self, Draft, Write};
use axismundi_client_core::{
    http::{Client, Credentials},
    jmap::{CORE_CAPABILITY, Session, mail},
};
use serde_json::{Value, json};
use std::{thread, time::Duration};

const SUBMISSION: &str = "urn:ietf:params:jmap:submission";

fn call(client: &Client, session: &Session, name: &str, args: Value) -> Value {
    let batch = client
        .request(
            session,
            &[CORE_CAPABILITY, mail::CAPABILITY, SUBMISSION],
            vec![(name, args, "test")],
        )
        .unwrap();
    let response = batch.response("test", name).unwrap();
    if name.ends_with("/set") {
        assert!(
            response.set_outcome().unwrap().fully_applied(),
            "{name}: {:?}",
            response.arguments
        );
    }
    response.arguments.clone()
}

fn local_session(url: &str, user: String, secret: String) -> (Client, Session, String) {
    let parsed = url::Url::parse(url).unwrap();
    assert!(matches!(
        parsed.host_str(),
        Some("localhost" | "127.0.0.1" | "[::1]")
    ));
    let client = Client::new(Credentials { user, secret });
    let session = client.session(url).unwrap();
    assert_eq!(
        url::Url::parse(&session.api_url).unwrap().origin(),
        parsed.origin()
    );
    let account = mail::choose_account(&session, None).unwrap();
    assert!(
        session.accounts[&account]
            .account_capabilities
            .contains_key(SUBMISSION)
    );
    (client, session, account)
}

fn role(client: &Client, session: &Session, account: &str, role: &str) -> String {
    mail::mailboxes(client, session, account).unwrap()["list"]
        .as_array()
        .unwrap()
        .iter()
        .find(|v| v["role"] == role)
        .expect("required mailbox role")["id"]
        .as_str()
        .unwrap()
        .to_owned()
}

fn send(
    sender: &(Client, Session, String),
    receiver: &(Client, Session, String),
    to: &str,
    subject: &str,
) {
    let (client, session, account) = sender;
    let (other, other_session, other_account) = receiver;
    let identities = call(
        client,
        session,
        "Identity/get",
        json!({"accountId":account}),
    );
    let identity = identities["list"]
        .as_array()
        .unwrap()
        .iter()
        .find(|i| {
            i["email"]
                .as_str()
                .is_some_and(|s| s.ends_with("@example.org"))
        })
        .expect("local sender identity");
    let from = identity["email"].as_str().unwrap();
    assert!(matches!(to, "admin@example.org" | "alice@example.org"));
    assert!(matches!(from, "admin@example.org" | "alice@example.org"));
    let draft = role(client, session, account, "drafts");
    let sent = role(client, session, account, "sent");
    let inbox = role(other, other_session, other_account, "inbox");
    let identity = serde_json::from_value(identity.clone()).unwrap();
    let mut fields: Draft = serde_json::from_value(json!({
        "identityId":identities["list"].as_array().unwrap().iter()
            .find(|i| i["email"] == from).unwrap()["id"],
        "to":[{"email":to}],"cc":[],"bcc":[],"subject":subject,
        "body":"Axismundi local Submission interop. No external recipients.",
        "inReplyTo":[],"references":[]
    }))
    .unwrap();
    let Write::Applied(first) =
        compose::save_draft(client, session, account, &identity, &draft, &fields, &[])
    else {
        panic!("first draft was not saved")
    };
    fields.body.push_str(" Second save.");
    let Write::Applied(saved) = compose::save_draft(
        client,
        session,
        account,
        &identity,
        &draft,
        &fields,
        &[first.id.clone()],
    ) else {
        panic!("replacement draft was not saved")
    };
    assert!(saved.pending_cleanup.is_empty());
    assert_eq!(
        compose::submit(
            client,
            session,
            account,
            &identity,
            &draft,
            Some(&sent),
            &saved
        ),
        Write::Applied(())
    );
    let email = &saved.id;
    let mut received = None;
    for _ in 0..30 {
        let query = call(
            other,
            other_session,
            "Email/query",
            json!({"accountId":other_account,"filter":{"inMailbox":inbox,"subject":subject},"limit":10}),
        );
        if let Some(id) = query["ids"]
            .as_array()
            .unwrap()
            .first()
            .and_then(Value::as_str)
        {
            let read = mail::read(other, other_session, other_account, id).unwrap();
            if read["list"][0]["subject"] == subject {
                assert!(
                    read["list"][0]["bodyValues"]
                        .as_object()
                        .unwrap()
                        .values()
                        .any(|v| v["value"]
                            .as_str()
                            .is_some_and(|s| s.contains("Axismundi local Submission")))
                );
                received = Some(id.to_owned());
                break;
            }
        }
        thread::sleep(Duration::from_millis(500));
    }
    let received = received.expect("recipient Inbox delivery within 15 seconds");
    let filed = call(
        client,
        session,
        "Email/get",
        json!({"accountId":account,"ids":[email,&first.id],"properties":["id","mailboxIds","keywords"]}),
    );
    assert_eq!(filed["notFound"], json!([first.id]));
    assert_eq!(filed["list"][0]["mailboxIds"], json!({&sent:true}));
    assert!(filed["list"][0]["keywords"].get("$draft").is_none());
    let submissions = call(
        client,
        session,
        "EmailSubmission/query",
        json!({"accountId":account,"filter":{"emailIds":[email]}}),
    );
    println!("{from} -> {to}: sender Email={email}, recipient Email={received}");
    if std::env::var("AXJ_KEEP_MAIL_FIXTURES").as_deref() != Ok("1") {
        if submissions["ids"]
            .as_array()
            .is_some_and(|ids| !ids.is_empty())
        {
            call(
                client,
                session,
                "EmailSubmission/set",
                json!({"accountId":account,"destroy":submissions["ids"]}),
            );
        }
        call(
            client,
            session,
            "Email/set",
            json!({"accountId":account,"destroy":[email]}),
        );
        call(
            other,
            other_session,
            "Email/set",
            json!({"accountId":other_account,"destroy":[received]}),
        );
    }
}

#[test]
#[ignore = "requires two explicit local Stalwart accounts; sends real local mail"]
fn bidirectional_local_delivery() {
    let url = std::env::var("AXJ_SESSION_URL").unwrap();
    let admin = local_session(
        &url,
        std::env::var("AXJ_USER").unwrap(),
        std::env::var("AXJ_SECRET").unwrap(),
    );
    let alice = local_session(
        &url,
        std::env::var("AXJ_ALICE_USER").unwrap(),
        std::env::var("AXJ_ALICE_SECRET").unwrap(),
    );
    assert_ne!(admin.2, alice.2);
    let nonce = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_nanos();
    send(
        &admin,
        &alice,
        "alice@example.org",
        &format!("Axismundi admin to Alice {nonce}"),
    );
    send(
        &alice,
        &admin,
        "admin@example.org",
        &format!("Axismundi Alice to admin {nonce}"),
    );
}
