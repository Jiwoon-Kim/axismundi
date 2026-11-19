//! Read-only Mail operations. No persistence or submission side effects.
use crate::{Batch, CORE_CAPABILITY, Client, Error, Session};
use serde_json::{Value, json};

pub const CAPABILITY: &str = "urn:ietf:params:jmap:mail";

pub fn choose_account(session: &Session, requested: Option<&str>) -> Result<String, Error> {
    if !session.capabilities.contains_key(CORE_CAPABILITY)
        || !session.capabilities.contains_key(CAPABILITY)
    {
        return Err(Error::Shape(
            "Session does not advertise Core and Mail".into(),
        ));
    }
    let accounts = session.accounts_with(CAPABILITY);
    if let Some(id) = requested {
        return accounts
            .contains(&id)
            .then(|| id.to_owned())
            .ok_or_else(|| Error::Shape("requested account does not advertise Mail".into()));
    }
    if let Some(id) = session.primary_account(CAPABILITY) {
        if accounts.contains(&id) {
            return Ok(id.to_owned());
        }
    }
    match accounts.as_slice() {
        [id] => Ok((*id).to_owned()),
        _ => Err(Error::Shape(
            session
                .empty_account_diagnostic()
                .unwrap_or("no unambiguous Mail account; specify --account")
                .into(),
        )),
    }
}

fn limit(session: &Session, key: &str) -> Result<usize, Error> {
    session
        .capabilities
        .get(CORE_CAPABILITY)
        .and_then(|v| v.get(key))
        .and_then(Value::as_u64)
        .and_then(|v| usize::try_from(v).ok())
        .filter(|v| *v > 0)
        .ok_or_else(|| Error::Shape(format!("invalid Core limit {key}")))
}

fn call(client: &Client, session: &Session, name: &str, args: Value) -> Result<Batch, Error> {
    limit(session, "maxCallsInRequest")?;
    let bytes = serde_json::to_vec(
        &json!({"using":[CORE_CAPABILITY,CAPABILITY],"methodCalls":[[name,args,"mail"]]}),
    )
    .map_err(|e| Error::Shape(e.to_string()))?
    .len();
    if bytes > limit(session, "maxSizeRequest")? {
        return Err(Error::Shape("Mail request exceeds maxSizeRequest".into()));
    }
    client.request(
        session,
        &[CORE_CAPABILITY, CAPABILITY],
        vec![(name, args, "mail")],
    )
}

fn arguments(batch: &Batch, name: &str, account: &str) -> Result<Value, Error> {
    let value = &batch.response("mail", name)?.arguments;
    if value.get("accountId").and_then(Value::as_str) != Some(account) {
        return Err(Error::Shape("Mail response accountId mismatch".into()));
    }
    Ok(value.clone())
}

pub fn mailboxes(client: &Client, session: &Session, account: &str) -> Result<Value, Error> {
    choose_account(session, Some(account))?;
    let batch = call(
        client,
        session,
        "Mailbox/get",
        json!({"accountId":account,"properties":["id","name","parentId","role","sortOrder","myRights","totalEmails","unreadEmails"]}),
    )?;
    let result = arguments(&batch, "Mailbox/get", account)?;
    if !result.get("list").is_some_and(Value::is_array) {
        return Err(Error::Shape("Mailbox/get has no list".into()));
    }
    Ok(result)
}

pub fn read(client: &Client, session: &Session, account: &str, id: &str) -> Result<Value, Error> {
    choose_account(session, Some(account))?;
    limit(session, "maxObjectsInGet")?;
    let batch = call(
        client,
        session,
        "Email/get",
        json!({"accountId":account,"ids":[id],"properties":["id","blobId","threadId","mailboxIds","keywords","subject","from","to","cc","receivedAt","sentAt","bodyStructure","textBody","htmlBody","attachments","bodyValues"],"fetchTextBodyValues":true,"fetchHTMLBodyValues":true,"maxBodyValueBytes":65536}),
    )?;
    arguments(&batch, "Email/get", account)
}

pub fn inbox(client: &Client, session: &Session, account: &str) -> Result<Value, Error> {
    let boxes = mailboxes(client, session, account)?;
    let ids: Vec<_> = boxes["list"]
        .as_array()
        .unwrap()
        .iter()
        .filter(|v| v["role"] == "inbox")
        .filter_map(|v| v["id"].as_str())
        .collect();
    let [mailbox] = ids.as_slice() else {
        return Err(Error::Shape("no unambiguous role=inbox mailbox".into()));
    };
    let count = limit(session, "maxObjectsInGet")?.min(50);
    let batch = call(
        client,
        session,
        "Email/query",
        json!({"accountId":account,"filter":{"inMailbox":mailbox},"sort":[{"property":"receivedAt","isAscending":false}],"collapseThreads":false,"position":0,"limit":count}),
    )?;
    let query = arguments(&batch, "Email/query", account)?;
    let ids = query
        .get("ids")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("Email/query has no ids".into()))?;
    if ids.len() > count || ids.iter().any(|id| !id.is_string()) {
        return Err(Error::Shape(
            "Email/query returned invalid or excessive ids".into(),
        ));
    }
    let mut emails = Vec::new();
    let mut not_found = Vec::new();
    let mut get_states = Vec::new();
    for chunk in ids.chunks(limit(session, "maxObjectsInGet")?) {
        let batch = call(
            client,
            session,
            "Email/get",
            json!({"accountId":account,"ids":chunk,"properties":["id","threadId","mailboxIds","keywords","size","receivedAt","from","subject","preview","hasAttachment"]}),
        )?;
        let result = arguments(&batch, "Email/get", account)?;
        get_states.push(result.get("state").cloned().unwrap_or(Value::Null));
        let rows = result
            .get("list")
            .and_then(Value::as_array)
            .ok_or_else(|| Error::Shape("Email/get has no list".into()))?;
        emails.extend(rows.iter().cloned());
        if let Some(missing) = result.get("notFound").and_then(Value::as_array) {
            not_found.extend(missing.iter().cloned());
        }
    }
    Ok(
        json!({"accountId":account,"query":query,"emails":emails,"notFound":not_found,"getStates":get_states}),
    )
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::Invocation;
    #[test]
    fn matching_uses_call_id_and_rejects_errors_and_duplicates() {
        let mut batch = Batch {
            session_state: None,
            method_responses: vec![Invocation {
                name: "Email/get".into(),
                call_id: "other".into(),
                arguments: json!({}),
            }],
        };
        assert!(batch.response("mail", "Email/get").is_err());
        batch.method_responses.push(Invocation {
            name: "Email/get".into(),
            call_id: "mail".into(),
            arguments: json!({"accountId":"a"}),
        });
        assert!(arguments(&batch, "Email/get", "a").is_ok());
        assert!(arguments(&batch, "Email/get", "b").is_err());
        batch.method_responses.push(Invocation {
            name: "error".into(),
            call_id: "mail".into(),
            arguments: json!({"type":"serverFail"}),
        });
        assert!(batch.response("mail", "Email/get").is_err());
    }
    #[test]
    fn account_selection_checks_capabilities_and_ambiguity() {
        let mut session: Session = serde_json::from_value(json!({"apiUrl":"https://example.test/jmap","capabilities":{CORE_CAPABILITY:{"maxObjectsInGet":2},CAPABILITY:{}},"accounts":{"a":{"accountCapabilities":{CAPABILITY:{}}},"b":{"accountCapabilities":{CAPABILITY:{}}}}})).unwrap();
        assert!(choose_account(&session, None).is_err());
        assert_eq!(choose_account(&session, Some("a")).unwrap(), "a");
        assert!(choose_account(&session, Some("missing")).is_err());
        session
            .primary_accounts
            .insert(CAPABILITY.into(), "b".into());
        assert_eq!(choose_account(&session, None).unwrap(), "b");
        assert_eq!(limit(&session, "maxObjectsInGet").unwrap(), 2);
        assert!(limit(&session, "maxSizeRequest").is_err());
    }

    #[test]
    fn inbox_uses_role_and_advertised_limit_without_fetching_bodies() {
        use std::{
            io::{BufRead, BufReader, Read, Write},
            net::TcpListener,
            thread,
            time::Duration,
        };
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let api = format!("http://{}", listener.local_addr().unwrap());
        let server = thread::spawn(move || {
            for step in 0..3 {
                let (mut stream, _) = listener.accept().unwrap();
                stream
                    .set_read_timeout(Some(Duration::from_secs(5)))
                    .unwrap();
                let mut reader = BufReader::new(&mut stream);
                let mut length = 0;
                loop {
                    let mut line = String::new();
                    reader.read_line(&mut line).unwrap();
                    assert!(!line.is_empty());
                    if line == "\r\n" {
                        break;
                    }
                    if let Some(value) = line.to_ascii_lowercase().strip_prefix("content-length:") {
                        length = value.trim().parse().unwrap();
                    }
                }
                let mut bytes = vec![0; length];
                reader.read_exact(&mut bytes).unwrap();
                let request: Value = serde_json::from_slice(&bytes).unwrap();
                let call = &request["methodCalls"][0];
                let value = match step {
                    0 => {
                        assert_eq!(call[0], "Mailbox/get");
                        json!({"accountId":"a","list":[{"id":"in","name":"Localized","role":"inbox"}]})
                    }
                    1 => {
                        assert_eq!(call[0], "Email/query");
                        assert_eq!(call[1]["limit"], 2);
                        assert_eq!(call[1]["filter"]["inMailbox"], "in");
                        assert_eq!(call[1]["collapseThreads"], false);
                        json!({"accountId":"a","queryState":"opaque","position":0,"ids":["2","1"]})
                    }
                    _ => {
                        assert_eq!(call[0], "Email/get");
                        assert_eq!(call[1]["ids"], json!(["2", "1"]));
                        assert!(call[1].get("fetchHTMLBodyValues").is_none());
                        json!({"accountId":"a","state":"different-opaque","list":[{"id":"1"},{"id":"2"}],"notFound":[]})
                    }
                };
                let body = json!({"methodResponses":[[call[0],value,"mail"]],"sessionState":"s"})
                    .to_string();
                write!(
                    stream,
                    "HTTP/1.1 200 OK\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}",
                    body.len(),
                    body
                )
                .unwrap();
            }
        });
        let session: Session = serde_json::from_value(json!({"apiUrl":api,"capabilities":{CORE_CAPABILITY:{"maxObjectsInGet":2,"maxCallsInRequest":1,"maxSizeRequest":10000},CAPABILITY:{}},"accounts":{"a":{"accountCapabilities":{CAPABILITY:{}}}}})).unwrap();
        let client = Client::new(crate::Credentials {
            user: "fixture".into(),
            secret: "fixture-only".into(),
        });
        let result = inbox(&client, &session, "a").unwrap();
        assert_eq!(result["query"]["ids"], json!(["2", "1"]));
        assert_eq!(result["emails"].as_array().unwrap().len(), 2);
        server.join().unwrap();
    }
}
