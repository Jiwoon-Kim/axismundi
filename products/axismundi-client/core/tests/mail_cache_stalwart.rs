//! Profile-local Mail cache and external delta interop. Only destroys its own fixtures.
use axismundi_client_core::{
    http::{Client, Credentials},
    jmap::{
        CORE_CAPABILITY, Session,
        mail::{self, Rows, cache},
    },
};
use serde_json::{Value, json};

fn call(client: &Client, session: &Session, method: &str, args: Value) -> Value {
    let batch = client
        .request(
            session,
            &[CORE_CAPABILITY, mail::CAPABILITY],
            vec![(method, args, "fixture")],
        )
        .unwrap();
    let response = batch.response("fixture", method).unwrap();
    if method.ends_with("/set") {
        assert!(
            response.set_outcome().unwrap().fully_applied(),
            "{method}: {:?}",
            response.arguments
        );
    }
    response.arguments.clone()
}

#[test]
#[ignore = "requires explicit local Stalwart credentials; creates an isolated Mailbox and Email"]
fn mail_cache_external_delta_offline_and_destruction() {
    let url = std::env::var("AXJ_SESSION_URL").unwrap();
    let parsed = url::Url::parse(&url).unwrap();
    assert!(matches!(
        parsed.host_str(),
        Some("localhost" | "127.0.0.1" | "[::1]")
    ));
    let client = Client::new(Credentials {
        user: std::env::var("AXJ_USER").unwrap(),
        secret: std::env::var("AXJ_SECRET").unwrap(),
    });
    let session = client.session(&url).unwrap();
    assert_eq!(
        url::Url::parse(&session.api_url).unwrap().origin(),
        parsed.origin()
    );
    let account = mail::choose_account(&session, None).unwrap();
    let nonce = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_nanos();
    let path = std::env::temp_dir().join(format!("ax-mail-cache-e2e-{nonce}.sqlite"));
    let boxes = call(
        &client,
        &session,
        "Mailbox/set",
        json!({"accountId":account,"create":{"box":{"name":format!("Axismundi cache E2E {nonce}")}}}),
    );
    let mailbox = boxes["created"]["box"]["id"].as_str().unwrap().to_owned();
    let mut created_email: Option<String> = None;
    let result = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        let created = call(
            &client,
            &session,
            "Email/set",
            json!({"accountId":account,"create":{"email":{
                "mailboxIds":{&mailbox:true},"keywords":{},"from":[{"email":"fixture@example.invalid"}],"subject":format!("Cache E2E {nonce}"),
                "textBody":[{"partId":"text","type":"text/plain"}],"bodyValues":{"text":{"value":"Offline cached synthetic body"}}
            }}}),
        );
        let id = created["created"]["email"]["id"]
            .as_str()
            .unwrap()
            .to_owned();
        created_email = Some(id.clone());
        let mut store = cache::Store::open(&path, "e2e-profile").unwrap();
        assert!(
            cache::sync(
                &client,
                &session,
                &mut store,
                &account,
                Some("no-such-mailbox"),
                Rows::Messages
            )
            .is_err()
        );
        let view = cache::sync(&client, &session, &mut store, &account, Some(&mailbox), Rows::Messages).unwrap();
        assert_eq!(view["emails"][0]["id"], id);
        assert_eq!(view["partialCache"], true);
        cache::read(&client, &session, &mut store, &account, &id).unwrap();
        drop(store);
        let mut store = cache::Store::open(&path, "e2e-profile").unwrap();
        assert!(store.email(&account, &id).unwrap()["document"]["bodyValues"].is_object());
        assert_eq!(
            store.view(&account, Some(&mailbox), Rows::Messages).unwrap()["emails"]
                .as_array()
                .unwrap()
                .len(),
            1
        );
        assert!(cache::Store::open(&path, "wrong-profile").is_err());
        call(
            &client,
            &session,
            "Email/set",
            json!({"accountId":account,"update":{&id:{"keywords/$seen":true}}}),
        );
        let view = cache::sync(&client, &session, &mut store, &account, Some(&mailbox), Rows::Messages).unwrap();
        assert_eq!(view["emails"][0]["document"]["keywords"]["$seen"], true);
        // Marking a message read changes its keywords and nothing else an Email can carry (RFC 8621
        // §4), so the body read before stays good offline.
        let after = store.email(&account, &id).unwrap();
        assert!(
            !after["staleProperties"]
                .as_array()
                .unwrap()
                .contains(&json!("bodyValues")),
            "{after}"
        );
        assert!(after["coverage"].get("bodyValues").is_some());
        let inbox = mail::mailboxes(&client, &session, &account).unwrap()["list"]
            .as_array()
            .unwrap()
            .iter()
            .find(|v| v["role"] == "inbox")
            .unwrap()["id"]
            .as_str()
            .unwrap()
            .to_owned();
        call(
            &client,
            &session,
            "Email/set",
            json!({"accountId":account,"update":{&id:{"mailboxIds":{inbox:true}}}}),
        );
        let view = cache::sync(&client, &session, &mut store, &account, Some(&mailbox), Rows::Messages).unwrap();
        assert!(view["emails"].as_array().unwrap().is_empty());
        assert_eq!(store.email(&account, &id).unwrap()["available"], true);
        call(
            &client,
            &session,
            "Email/set",
            json!({"accountId":account,"destroy":[id]}),
        );
        created_email = None;
        cache::sync(&client, &session, &mut store, &account, Some(&mailbox), Rows::Messages).unwrap();
        assert_eq!(store.email(&account, &id).unwrap()["reason"], "destroyed");
        drop(store);
    }));
    if let Some(id) = created_email {
        call(
            &client,
            &session,
            "Email/set",
            json!({"accountId":account,"destroy":[id]}),
        );
    }
    call(
        &client,
        &session,
        "Mailbox/set",
        json!({"accountId":account,"destroy":[mailbox],"onDestroyRemoveEmails":true}),
    );
    for suffix in ["", "-wal", "-shm"] {
        let _ = std::fs::remove_file(format!("{}{suffix}", path.display()));
    }
    if let Err(panic) = result {
        std::panic::resume_unwind(panic);
    }
    println!(
        "PASS isolated Mail cache: snapshot, detail, offline reopen, external keyword delta, move vs destroy, profile isolation and cleanup"
    );
}

/// Move to Trash and back out, through the cache, against the server's own rules: an exclusive move
/// of a message in two mailboxes leaves Trash alone (Stalwart later destroys Trash contents outright),
/// a plain move keeps any other membership, and a message cannot be left in no mailbox.
#[test]
#[ignore = "requires explicit local Stalwart credentials; creates two isolated Mailboxes and one Email"]
fn mail_cache_move_to_trash_and_back() {
    let url = std::env::var("AXJ_SESSION_URL").unwrap();
    let parsed = url::Url::parse(&url).unwrap();
    assert!(matches!(parsed.host_str(), Some("localhost" | "127.0.0.1" | "[::1]")));
    let client = Client::new(Credentials {
        user: std::env::var("AXJ_USER").unwrap(),
        secret: std::env::var("AXJ_SECRET").unwrap(),
    });
    let session = client.session(&url).unwrap();
    let account = mail::choose_account(&session, None).unwrap();
    let nonce = std::time::SystemTime::now().duration_since(std::time::UNIX_EPOCH).unwrap().as_nanos();
    let path = std::env::temp_dir().join(format!("ax-mail-move-e2e-{nonce}.sqlite"));
    let boxes = call(
        &client,
        &session,
        "Mailbox/set",
        json!({"accountId":account,"create":{
            "one":{"name":format!("Axismundi move E2E one {nonce}")},
            "two":{"name":format!("Axismundi move E2E two {nonce}")}
        }}),
    );
    let one = boxes["created"]["one"]["id"].as_str().unwrap().to_owned();
    let two = boxes["created"]["two"]["id"].as_str().unwrap().to_owned();
    let mut created_email: Option<String> = None;
    let result = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        let created = call(
            &client,
            &session,
            "Email/set",
            json!({"accountId":account,"create":{"email":{
                "mailboxIds":{&one:true,&two:true},"keywords":{"$seen":true},"from":[{"email":"fixture@example.invalid"}],
                "subject":format!("Move E2E {nonce}"),
                "textBody":[{"partId":"text","type":"text/plain"}],"bodyValues":{"text":{"value":"Move synthetic body"}}
            }}}),
        );
        let id = created["created"]["email"]["id"].as_str().unwrap().to_owned();
        created_email = Some(id.clone());
        let trash = mail::mailboxes(&client, &session, &account).unwrap()["list"]
            .as_array()
            .unwrap()
            .iter()
            .find(|v| v["role"] == "trash")
            .expect("the account has a Trash mailbox")["id"]
            .as_str()
            .unwrap()
            .to_owned();
        let mut store = cache::Store::open(&path, "e2e-profile").unwrap();
        cache::sync(&client, &session, &mut store, &account, Some(&one), Rows::Messages).unwrap();
        let boxes_of = |store: &cache::Store| store.email(&account, &id).unwrap()["document"]["mailboxIds"].clone();
        let confirmed = |results: &mail::UpdateResults| {
            assert!(matches!(results.outcomes.as_slice(), [(_, mail::UpdateOutcome::Confirmed)]), "{:?}", results.outcomes);
            assert!(results.not_read_back.is_empty());
        };

        // Into Trash from `one`, as the GUI's Move to Trash asks: out of every mailbox this login sees.
        let results = cache::move_emails(&client, &session, &mut store, &account, &[id.clone()], &one, &trash, true).unwrap();
        confirmed(&results);
        assert_eq!(boxes_of(&store), json!({&trash: true}), "Trash is the only mailbox");

        // Out of Trash into `two`: a plain move.
        let results = cache::move_emails(&client, &session, &mut store, &account, &[id.clone()], &trash, &two, false).unwrap();
        confirmed(&results);
        assert_eq!(boxes_of(&store), json!({&two: true}));

        // A plain move leaves other memberships alone: add `one` back, then move `two` → Inbox.
        call(&client, &session, "Email/set", json!({"accountId":account,"update":{&id:{format!("mailboxIds/{one}"):true}}}));
        cache::sync(&client, &session, &mut store, &account, Some(&one), Rows::Messages).unwrap();
        let inbox = mail::mailboxes(&client, &session, &account).unwrap()["list"]
            .as_array()
            .unwrap()
            .iter()
            .find(|v| v["role"] == "inbox")
            .unwrap()["id"]
            .as_str()
            .unwrap()
            .to_owned();
        let results = cache::move_emails(&client, &session, &mut store, &account, &[id.clone()], &two, &inbox, false).unwrap();
        confirmed(&results);
        assert_eq!(boxes_of(&store), json!({&one: true, &inbox: true}), "the other membership stays");

        // The server, not the client, refuses an empty membership: a raw patch removing both is refused.
        let refused = client
            .request(
                &session,
                &[axismundi_client_core::jmap::CORE_CAPABILITY, mail::CAPABILITY],
                vec![("Email/set", json!({"accountId":account,"update":{&id:{"mailboxIds":{}}}}), "fixture")],
            )
            .unwrap();
        let answer = refused.response("fixture", "Email/set").unwrap().arguments.clone();
        assert_eq!(answer["notUpdated"][&id]["type"], "invalidProperties", "{answer}");
        drop(store);
    }));
    if let Some(id) = created_email {
        call(&client, &session, "Email/set", json!({"accountId":account,"destroy":[id]}));
    }
    call(
        &client,
        &session,
        "Mailbox/set",
        json!({"accountId":account,"destroy":[one, two],"onDestroyRemoveEmails":true}),
    );
    for suffix in ["", "-wal", "-shm"] {
        let _ = std::fs::remove_file(format!("{}{suffix}", path.display()));
    }
    if let Err(panic) = result {
        std::panic::resume_unwind(panic);
    }
    println!("PASS exclusive move to Trash leaves Trash alone, plain move keeps other memberships, empty membership refused");
}
