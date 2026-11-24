//! Opt-in, loopback-only interop test. Removes only the book/card it creates.
use axismundi_client_core::{
    http::{Client, Credentials},
    jmap::{
        CORE_CAPABILITY, Session,
        contacts::{self, CAPABILITY, Store},
    },
};
use serde_json::{Value, json};

fn call(client: &Client, session: &Session, name: &str, args: Value) -> Value {
    let batch = client
        .request(
            session,
            &[CORE_CAPABILITY, CAPABILITY],
            vec![(name, args, "test")],
        )
        .unwrap();
    assert!(
        batch.errors().is_empty(),
        "method errors: {:?}",
        batch.errors()
    );
    batch
        .first_named(name)
        .expect("method response")
        .arguments
        .clone()
}

#[test]
#[ignore = "requires explicitly supplied local Stalwart credentials"]
fn contacts_round_trip() {
    let url = std::env::var("AXJ_SESSION_URL").expect("AXJ_SESSION_URL");
    let parsed = url::Url::parse(&url).unwrap();
    assert!(matches!(
        parsed.host_str(),
        Some("127.0.0.1" | "localhost" | "[::1]")
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
    let account = contacts::choose_account(&session, None).unwrap();
    println!("Contacts account={account}; Session entry={url}; resolved API={}", session.api_url);
    let nonce = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_nanos();
    let path = std::env::temp_dir().join(format!("ax-contacts-{nonce}.sqlite"));
    let mut store = Store::open(&path, "stalwart-test").unwrap();
    assert!(Store::open(&path, "another-profile").is_err());
    let book_response = call(
        &client,
        &session,
        "AddressBook/set",
        json!({"accountId":account,"create":{"fixture":{"name":format!("Axismundi E2E {nonce}")}}}),
    );
    let book = book_response["created"]["fixture"]["id"]
        .as_str()
        .expect("book created")
        .to_owned();
    let result = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        let created = call(
            &client,
            &session,
            "ContactCard/set",
            json!({"accountId":account,"create":{"fixture":{
                "@type":"Card","version":"1.0","uid":format!("urn:uuid:00000000-0000-4000-8000-{:012x}",nonce%0xffffffffffffu128),
                "name":{"full":"Axismundi E2E Original"},"addressBookIds":{&book:true}
            }}}),
        );
        let card = created["created"]["fixture"]["id"]
            .as_str()
            .expect("contact created")
            .to_owned();
        for kind in ["AddressBook", "ContactCard"] {
            contacts::sync_type(&client, &session, &mut store, &account, kind).unwrap();
        }
        assert!(
            store
                .list(&account, "AddressBook")
                .unwrap()
                .iter()
                .any(|v| v["id"] == book)
        );
        assert!(
            store
                .list(&account, "ContactCard")
                .unwrap()
                .iter()
                .any(|v| v["id"] == card)
        );
        drop(store);
        let mut store = Store::open(&path, "stalwart-test").unwrap();
        assert!(
            store
                .list(&account, "ContactCard")
                .unwrap()
                .iter()
                .any(|v| v["id"] == card)
        );
        let updated = call(
            &client,
            &session,
            "ContactCard/set",
            json!({"accountId":account,"update":{&card:{"name/full":"External edit"}}}),
        );
        assert!(updated["updated"].get(&card).is_some());
        contacts::sync_type(&client, &session, &mut store, &account, "ContactCard").unwrap();
        assert!(
            store
                .list(&account, "ContactCard")
                .unwrap()
                .iter()
                .any(|v| v["id"] == card && v["name"]["full"] == "External edit")
        );
        store
            .stage_update(&account, &card, &json!({"name/full":"Local edit"}))
            .unwrap();
        assert_eq!(store.pending_count(&account).unwrap(), 1);
        store
            .submit_update(&client, &session, &account, &card)
            .unwrap();
        assert_eq!(store.pending_count(&account).unwrap(), 0);
        assert!(
            store
                .list(&account, "ContactCard")
                .unwrap()
                .iter()
                .any(|v| v["id"] == card && v["name"]["full"] == "Local edit")
        );
        // A stale edit must remain pending after ifInState rejects it.
        store
            .stage_update(&account, &card, &json!({"name/full":"Conflicting edit"}))
            .unwrap();
        call(
            &client,
            &session,
            "ContactCard/set",
            json!({"accountId":account,"update":{&card:{"name/full":"Concurrent edit"}}}),
        );
        assert!(
            store
                .submit_update(&client, &session, &account, &card)
                .is_err()
        );
        assert_eq!(store.pending_count(&account).unwrap(), 1);
        let deleted = call(
            &client,
            &session,
            "ContactCard/set",
            json!({"accountId":account,"destroy":[card]}),
        );
        assert_eq!(deleted["destroyed"], json!([card]));
        contacts::sync_type(&client, &session, &mut store, &account, "ContactCard").unwrap();
        assert!(
            !store
                .list(&account, "ContactCard")
                .unwrap()
                .iter()
                .any(|v| v["id"] == card)
        );
        assert_eq!(store.pending_count(&account).unwrap(), 1);
        // Invalid checkpoints fall back to a staged full snapshot, not destructive clearing.
        let mut empty = Store::open(":memory:", "fallback").unwrap();
        contacts::sync_type(&client, &session, &mut empty, &account, "ContactCard").unwrap();
        println!(
            "PASS Contacts snapshot, offline reopen, external delta, queued update, conflict retention, deletion"
        );
    }));
    let cleanup = call(
        &client,
        &session,
        "AddressBook/set",
        json!({"accountId":account,"destroy":[book],"onDestroyRemoveContents":true}),
    );
    assert_eq!(
        cleanup["destroyed"],
        json!([book]),
        "fixture cleanup failed"
    );
    for suffix in ["", "-wal", "-shm"] {
        let _ = std::fs::remove_file(format!("{}{suffix}", path.display()));
    }
    if let Err(error) = result {
        std::panic::resume_unwind(error);
    }
}
