//! Loopback-only Mail interop. Creates and removes one synthetic Email, never submits it.
use axismundi_jmap_core::{CORE_CAPABILITY, Client, Credentials, Session, mail};
use serde_json::{Value, json};

fn call(client: &Client, session: &Session, args: Value) -> Value {
    let batch = client
        .request(
            session,
            &[CORE_CAPABILITY, mail::CAPABILITY],
            vec![("Email/set", args, "fixture")],
        )
        .unwrap();
    let response = batch.response("fixture", "Email/set").unwrap();
    assert!(
        response.set_outcome().unwrap().fully_applied(),
        "fixture Email/set rejected: {:?}",
        response.arguments
    );
    response.arguments.clone()
}

#[test]
#[ignore = "requires explicitly supplied local Stalwart credentials; writes a synthetic Email"]
fn mail_read_round_trip() {
    let url = std::env::var("AXJ_SESSION_URL").expect("AXJ_SESSION_URL");
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
    let boxes = mail::mailboxes(&client, &session, &account).unwrap();
    let inbox = boxes["list"]
        .as_array()
        .unwrap()
        .iter()
        .find(|b| b["role"] == "inbox")
        .unwrap()["id"]
        .as_str()
        .unwrap();
    let nonce = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_nanos();
    let subject = format!("Axismundi Mail E2E {nonce}");
    let created = call(
        &client,
        &session,
        json!({"accountId":account,"create":{"fixture":{
            "mailboxIds":{inbox:true},"keywords":{"$seen":true},
            "from":[{"email":"fixture@example.invalid"}],"to":[{"email":"recipient@example.invalid"}],
            "subject":subject,"textBody":[{"partId":"text","type":"text/plain"}],
            "bodyValues":{"text":{"value":"Axismundi synthetic body. No SMTP delivery.","isTruncated":false,"isEncodingProblem":false}}
        }}}),
    );
    let id = created["created"]["fixture"]["id"]
        .as_str()
        .expect("created Email id")
        .to_owned();
    let checked = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        let list = mail::inbox(&client, &session, &account).unwrap();
        assert!(
            list["query"]["ids"]
                .as_array()
                .unwrap()
                .iter()
                .any(|v| v == &id)
        );
        assert!(
            list["emails"]
                .as_array()
                .unwrap()
                .iter()
                .any(|v| v["id"] == id && v["subject"] == subject)
        );
        let detail = mail::read(&client, &session, &account, &id).unwrap();
        let email = &detail["list"][0];
        assert_eq!(email["id"], id);
        assert!(email["bodyValues"].as_object().unwrap().values().any(|v| {
            v["value"]
                .as_str()
                .is_some_and(|s| s.contains("Axismundi synthetic body"))
        }));
        let missing = mail::read(&client, &session, &account, "nonexistent-fixture-id").unwrap();
        assert_eq!(missing["notFound"], json!(["nonexistent-fixture-id"]));
    }));
    let destroyed = call(
        &client,
        &session,
        json!({"accountId":account,"destroy":[id]}),
    );
    assert_eq!(destroyed["destroyed"], json!([id]));
    println!(
        "Mail account={account}: mailbox roles, nonempty query, body, notFound and fixture cleanup verified; no submission"
    );
    if let Err(panic) = checked {
        std::panic::resume_unwind(panic);
    }
}
