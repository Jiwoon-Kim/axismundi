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
#[ignore = "requires explicit loopback Stalwart credentials; creates and removes a search fixture"]
fn account_search_and_snippets_stalwart() {
    use mail::search::{Cursor, search};
    let url = std::env::var("AXJ_SESSION_URL").unwrap();
    let source = url::Url::parse(&url).unwrap();
    assert!(matches!(source.host_str(), Some("localhost" | "127.0.0.1" | "[::1]")));
    let client = Client::new(Credentials {
        user: std::env::var("AXJ_USER").unwrap(),
        secret: std::env::var("AXJ_SECRET").unwrap(),
    });
    let session = client.session(&url).unwrap();
    assert_eq!(url::Url::parse(&session.api_url).unwrap().origin(), source.origin());
    let account = mail::choose_account(&session, None).unwrap();
    let nonce = std::time::SystemTime::now().duration_since(std::time::UNIX_EPOCH).unwrap().as_nanos();
    let term = format!("axsearch{nonce}");
    let folder = call(&client, &session, "Mailbox/set", json!({"accountId":account,"create":{"m":{"name":&term}}}));
    let folder = folder["created"]["m"]["id"].as_str().unwrap();
    let boxes = mail::mailboxes(&client, &session, &account).unwrap();
    let trash = boxes["list"].as_array().unwrap().iter().find(|row| row["role"] == "trash").unwrap()["id"].as_str().unwrap();
    let window = session.capabilities[CORE_CAPABILITY]["maxObjectsInGet"].as_u64().unwrap().min(50) as usize;
    let mut ids = Vec::new();
    let mut create = |from: usize, count: usize, memberships: Value| {
        let create: serde_json::Map<_, _> = (from..from+count).map(|i| (format!("e{i}"), json!({
            "mailboxIds":memberships, "subject":format!("<b> {term} {i}"), "from":[{"email":"fixture@example.invalid"}],
            "textBody":[{"partId":"t","type":"text/plain"}],"bodyValues":{"t":{"value":"Search fixture body"}}
        }))).collect();
        let answer = call(&client, &session, "Email/set", json!({"accountId":account,"create":create}));
        let created: Vec<String> = answer["created"].as_object().unwrap().values()
            .map(|row| row["id"].as_str().unwrap().to_owned()).collect();
        ids.extend(created.iter().cloned());
        created
    };
    let indexed = |expected: usize, retained: usize| {
        let deadline = std::time::Instant::now() + std::time::Duration::from_secs(15);
        loop {
            let view = search(&client, &session, &account, &term, retained, None).unwrap();
            if view["total"] == json!(expected) || std::time::Instant::now() >= deadline { break view; }
            std::thread::sleep(std::time::Duration::from_millis(300));
        }
    };
    let result = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        for start in (0..window).step_by(10) {
            create(start, 10.min(window - start), json!({folder:true}));
        }
        let mixed = create(window, 1, json!({folder:true,trash:true})).pop().unwrap();
        let excluded = create(window + 1, 1, json!({trash:true})).pop().unwrap();
        let first = indexed(window + 1, 0);
        assert_eq!(first["total"], json!(window + 1));
        assert_eq!(first["emails"].as_array().unwrap().len(), window);
        assert!(first["search"]["snippets"].as_object().unwrap().values().any(|snippet| {
            let subject = snippet["subject"].as_str().unwrap_or_default();
            subject.contains("&lt;b&gt;") && subject.contains("<mark>")
        }));
        let cursor = Cursor {position: window, state: first["search"]["state"].as_str().unwrap()};
        let next = search(&client, &session, &account, &term, window, Some(cursor)).unwrap();
        assert_eq!(next["search"]["position"], window);
        assert_eq!(next["emails"].as_array().unwrap().len(), 1);
        let combined: Vec<_> = first["emails"].as_array().unwrap().iter()
            .chain(next["emails"].as_array().unwrap()).map(|row| row["id"].as_str().unwrap()).collect();
        assert!(combined.contains(&mixed.as_str()));
        assert!(!combined.contains(&excluded.as_str()));
        create(window + 2, 1, json!({folder:true}));
        let refreshed = indexed(window + 2, window + 1);
        assert_eq!(refreshed["total"], json!(window + 2));
        assert_eq!(refreshed["emails"].as_array().unwrap().len(), window + 1);
    }));
    for batch in ids.chunks(10) {
        call(&client, &session, "Email/set", json!({"accountId":account,"destroy":batch}));
    }
    call(&client, &session, "Mailbox/set", json!({"accountId":account,"destroy":[folder],"onDestroyRemoveEmails":false}));
    if let Err(panic) = result { std::panic::resume_unwind(panic); }
}

#[test]
#[ignore = "requires explicit loopback Stalwart credentials; writes and removes only its own fixtures"]
fn mailbox_management_stalwart() {
    use mail::mailbox::{Change, Outcome, write};
    let url = std::env::var("AXJ_SESSION_URL").unwrap();
    let source = url::Url::parse(&url).unwrap();
    assert!(matches!(source.host_str(), Some("localhost" | "127.0.0.1" | "[::1]")));
    let client = Client::new(Credentials {
        user: std::env::var("AXJ_USER").unwrap(),
        secret: std::env::var("AXJ_SECRET").unwrap(),
    });
    let session = client.session(&url).unwrap();
    assert_eq!(url::Url::parse(&session.api_url).unwrap().origin(), source.origin());
    let account = mail::choose_account(&session, None).unwrap();
    let nonce = std::time::SystemTime::now().duration_since(std::time::UNIX_EPOCH).unwrap().as_nanos();
    let name = format!("ax mailbox e2e {nonce}");
    let set = |change: Change<'_>| write(&client, &session, &account, change).unwrap();
    let mut boxes: Vec<(String, String)> = vec![];
    let mut email = None;
    let result = std::panic::catch_unwind(std::panic::AssertUnwindSafe(|| {
        let confirmed = |outcome| match outcome {
            Outcome::Confirmed(id) => id,
            other => panic!("expected confirmation: {other:?}"),
        };
        let parent = confirmed(set(Change::Create { parent: None, name: &name, role: None }));
        boxes.push((account.clone(), parent.clone()));
        let child = confirmed(set(Change::Create { parent: Some(&parent), name: "Child", role: None }));
        boxes.push((account.clone(), child.clone()));
        assert_eq!(confirmed(set(Change::Rename { id: &child, name: "Renamed" })), child);
        assert!(matches!(set(Change::Destroy { id: &parent }), Outcome::Refused { kind, .. } if kind == "mailboxHasChild"));
        let created = call(&client, &session, "Email/set", json!({"accountId":account,"create":{"email":{
            "mailboxIds":{&child:true},"keywords":{},"from":[{"email":"fixture@example.invalid"}],"subject":&name,
            "textBody":[{"partId":"t","type":"text/plain"}],"bodyValues":{"t":{"value":"Mailbox E2E fixture"}}
        }}}));
        email = Some(created["created"]["email"]["id"].as_str().unwrap().to_owned());
        assert!(matches!(set(Change::Destroy { id: &child }), Outcome::Refused { kind, .. } if kind == "mailboxHasEmail"));
        call(&client, &session, "Email/set", json!({"accountId":account,"destroy":[email.as_ref().unwrap()]}));
        email = None;
        assert_eq!(confirmed(set(Change::Destroy { id: &child })), child);
        boxes.pop();
        assert_eq!(confirmed(set(Change::Destroy { id: &parent })), parent);
        boxes.pop();

        let existing = mail::mailboxes(&client, &session, &account).unwrap();
        for mailbox in existing["list"].as_array().unwrap() {
            if matches!(mailbox["role"].as_str(), Some("inbox" | "trash" | "junk")) {
                println!("{} mayDelete={}", mailbox["role"], mailbox["myRights"]["mayDelete"]);
            }
        }
        for role in ["inbox", "all", "flagged", "archive"] {
            if role == "archive" && existing["list"].as_array().unwrap().iter().any(|item| item["role"] == role) { continue; }
            let role_name = format!("{name} {role}");
            let outcome = set(Change::Create { parent: None, name: &role_name, role: Some(role) });
            println!("role {role}: {outcome:?}");
            if let Outcome::Confirmed(id) = &outcome {
                boxes.push((account.clone(), id.clone()));
            }
            if role == "inbox" {
                assert!(matches!(outcome, Outcome::Refused { .. }));
            } else if role == "archive" {
                if let Outcome::Confirmed(id) = outcome {
                    assert_eq!(confirmed(set(Change::Destroy { id: &id })), id);
                    boxes.pop();
                }
            }
        }
        if let Some(shared) = session.accounts.get("b").filter(|_| account != "b")
            && shared.account_capabilities.contains_key(mail::CAPABILITY)
        {
            let outcome = write(&client, &session, "b", Change::Create { parent: None, name: &name, role: None }).unwrap();
            println!("shared b top-level create: {outcome:?}");
            if let Outcome::Confirmed(id) = outcome {
                boxes.push(("b".into(), id));
            }
        }
    }));
    if let Some(id) = email {
        call(&client, &session, "Email/set", json!({"accountId":account,"destroy":[id]}));
    }
    for (account, id) in boxes.into_iter().rev() {
        let cleanup = write(&client, &session, &account, Change::Destroy { id: &id });
        assert!(matches!(cleanup, Ok(Outcome::Confirmed(_))), "fixture cleanup: {cleanup:?}");
    }
    if let Err(panic) = result { std::panic::resume_unwind(panic); }
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
