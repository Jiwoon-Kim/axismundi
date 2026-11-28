//! JMAP File Storage (draft-ietf-jmap-filenode-22).
use serde::Serialize;
use serde_json::{Value, json};
use unicode_normalization::UnicodeNormalization;

use super::{CORE_CAPABILITY, Session};
use crate::{Error, http::Client};

pub const CAPABILITY: &str = "urn:ietf:params:jmap:filenode";

#[derive(Debug, Serialize)]
#[serde(
    tag = "kind",
    rename_all = "camelCase",
    rename_all_fields = "camelCase"
)]
pub enum UploadResult {
    Unknown {
        parent_id: Option<String>,
        name: String,
        error: String,
    },
    Saved {
        node: Value,
    },
    Rejected {
        error: Value,
    },
    NeedsRefresh {
        id: String,
        error: String,
    },
}

#[derive(Clone, Copy, Serialize)]
#[serde(rename_all = "lowercase")]
pub enum OnExists {
    Rename,
    Replace,
}

#[derive(Debug, Serialize)]
#[serde(tag = "kind", rename_all = "camelCase")]
pub enum Quotas {
    Available { list: Vec<Value> },
    NotApplicable,
    Failed { error: String },
}

/// RFC 9425 §4. A quota shared with other types retains its full types list.
pub fn quotas(client: &Client, session: &Session, account: &str) -> Quotas {
    const QUOTA: &str = "urn:ietf:params:jmap:quota";
    if !session
        .accounts
        .get(account)
        .is_some_and(|a| a.account_capabilities.contains_key(QUOTA))
    {
        return Quotas::NotApplicable;
    }
    let result = (|| {
        let batch = client.request(
            session,
            &[CORE_CAPABILITY, QUOTA, CAPABILITY],
            vec![(
                "Quota/get",
                json!({"accountId": account, "ids": null}),
                "quota",
            )],
        )?;
        let answer = &batch.response("quota", "Quota/get")?.arguments;
        if answer["accountId"].as_str() != Some(account) {
            return Err(Error::Shape("Quota/get: account mismatch".into()));
        }
        let list = answer["list"]
            .as_array()
            .ok_or_else(|| Error::Shape("Quota/get: missing list".into()))?;
        Ok(list
            .iter()
            .filter(|quota| {
                quota["types"]
                    .as_array()
                    .is_some_and(|types| types.iter().any(|kind| kind == "FileNode"))
            })
            .cloned()
            .collect::<Vec<_>>())
    })();
    match result {
        Ok(list) if list.is_empty() => Quotas::NotApplicable,
        Ok(list) => Quotas::Available { list },
        Err(error) => Quotas::Failed {
            error: error.to_string(),
        },
    }
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Comparator<'a> {
    pub property: &'a str,
    pub is_ascending: bool,
}

fn capability<'a>(session: &'a Session, account: &str) -> Result<&'a Value, Error> {
    session
        .accounts
        .get(account)
        .and_then(|a| a.account_capabilities.get(CAPABILITY))
        .ok_or_else(|| Error::Shape("account does not advertise JMAP FileNode".into()))
}

pub fn validate_name(session: &Session, account: &str, name: &str) -> Result<(), Error> {
    let name: String = name.nfc().collect();
    let name = name.as_str();
    let cap = capability(session, account)?;
    let invalid = name.is_empty() || matches!(name, "." | "..") || name.contains('/')
        || cap["maxSizeFileNodeName"].as_u64().is_some_and(|max| name.len() as u64 > max)
        || cap["forbiddenNameChars"].as_str().is_some_and(|chars| name.chars().any(|c| chars.contains(c)))
        // NON-STANDARD: Stalwart advertises forbiddenNodeNames in addition to the draft limits.
        || cap["forbiddenNodeNames"].as_array().is_some_and(|names| names.iter().any(|value| value == name));
    if invalid {
        Err(Error::Shape(
            "name violates the server's FileNode naming limits".into(),
        ))
    } else {
        Ok(())
    }
}

fn call(
    client: &Client,
    session: &Session,
    account: &str,
    method: &str,
    args: Value,
) -> Result<Value, Error> {
    capability(session, account)?;
    let batch = client.request(
        session,
        &[CORE_CAPABILITY, CAPABILITY],
        vec![(method, args, "files")],
    )?;
    let answer = &batch.response("files", method)?.arguments;
    if answer["accountId"].as_str() != Some(account) {
        return Err(Error::Shape(format!("{method}: account mismatch")));
    }
    Ok(answer.clone())
}

/// draft-ietf-jmap-filenode-22 §3.2.
pub fn get(
    client: &Client,
    session: &Session,
    account: &str,
    ids: &[&str],
    fetch_parents: bool,
) -> Result<Value, Error> {
    call(
        client,
        session,
        account,
        "FileNode/get",
        json!({"accountId": account, "ids": ids, "fetchParents": fetch_parents}),
    )
}

/// draft-ietf-jmap-filenode-22 §3.6.
pub fn query(
    client: &Client,
    session: &Session,
    account: &str,
    parent: Option<&str>,
    sort: &[Comparator<'_>],
    position: u64,
    limit: u64,
) -> Result<Value, Error> {
    let cap = capability(session, account)?;
    let supported = cap["fileNodeQuerySortOptions"].as_array();
    if let Some(sort) = sort
        .iter()
        .find(|sort| !supported.is_some_and(|options| options.iter().any(|v| v == sort.property)))
    {
        return Err(Error::Shape(format!(
            "FileNode sort not advertised: {}",
            sort.property
        )));
    }
    let filter = match parent {
        Some(parent) => json!({"parentId": parent}),
        None => json!({"isTopLevel": true}),
    };
    let mut args =
        json!({"accountId": account, "filter": filter, "position": position, "limit": limit});
    if !sort.is_empty() {
        args["sort"] = json!(sort);
    }
    call(client, session, account, "FileNode/query", args)
}

/// draft-ietf-jmap-filenode-22 §3.4. Preserve vendor details such as existingId.
pub fn set(
    client: &Client,
    session: &Session,
    account: &str,
    mut args: Value,
) -> Result<Value, Error> {
    let object = args
        .as_object_mut()
        .ok_or_else(|| Error::Shape("FileNode/set arguments must be an object".into()))?;
    for field in ["create", "update"] {
        if let Some(nodes) = object.get_mut(field).and_then(Value::as_object_mut) {
            for node in nodes.values_mut() {
                if let Some(name) = node.get_mut("name") {
                    if let Some(value) = name.as_str() {
                        let normalized: String = value.nfc().collect();
                        validate_name(session, account, &normalized)?;
                        *name = json!(normalized);
                    }
                }
            }
        }
    }
    object.insert("accountId".into(), json!(account));
    call(client, session, account, "FileNode/set", args)
}

/// draft-ietf-jmap-filenode-22 §3.3.
pub fn changes(
    client: &Client,
    session: &Session,
    account: &str,
    since_state: &str,
) -> Result<Value, Error> {
    call(
        client,
        session,
        account,
        "FileNode/changes",
        json!({"accountId": account, "sinceState": since_state}),
    )
}

pub fn create_file(
    client: &Client,
    session: &Session,
    account: &str,
    parent: Option<&str>,
    name: &str,
    media_type: &str,
    uploaded_blob: &str,
    size: u64,
    on_exists: Option<OnExists>,
) -> Result<UploadResult, Error> {
    let name: String = name.nfc().collect();
    validate_name(session, account, &name)?;
    let unknown = |error| UploadResult::Unknown {
        parent_id: parent.map(str::to_owned),
        name: name.clone(),
        error,
    };
    let answer = match set(
        client,
        session,
        account,
        json!({
            "create": {"file": {"@type": "File", "parentId": parent, "name": name, "mediaType": media_type, "blobId": uploaded_blob}},
            "onExists": on_exists,
        }),
    ) {
        Ok(answer) => answer,
        Err(error @ Error::Transport(_)) => return Ok(unknown(error.to_string())),
        Err(
            error @ Error::Status {
                status: 500..=599, ..
            },
        ) => return Ok(unknown(error.to_string())),
        Err(ref error @ Error::Shape(ref message)) if !message.starts_with("files: ") => {
            return Ok(unknown(error.to_string()));
        }
        Err(error) => return Err(error),
    };
    if let Some(error) = answer["notCreated"].get("file") {
        return Ok(UploadResult::Rejected {
            error: error.clone(),
        });
    }
    let Some(id) = answer["created"]["file"]["id"]
        .as_str()
        .filter(|id| !id.is_empty())
    else {
        return Ok(unknown(
            "FileNode/set did not report the created node id".into(),
        ));
    };
    let confirmed = get(client, session, account, &[id], false).and_then(|answer| {
        answer["list"]
            .as_array()
            .and_then(|nodes| nodes.iter().find(|node| node["id"] == id))
            .filter(|node| {
                node["blobId"].as_str().is_some_and(|blob| !blob.is_empty())
                    && node["size"].as_u64() == Some(size)
            })
            .cloned()
            .ok_or_else(|| {
                Error::Shape("FileNode/get did not confirm the saved blob and size".into())
            })
    });
    Ok(match confirmed {
        Ok(node) => UploadResult::Saved { node },
        Err(error) => UploadResult::NeedsRefresh {
            id: id.to_owned(),
            error: error.to_string(),
        },
    })
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    #[ignore = "requires the local Stalwart interop instance"]
    fn stalwart_file_round_trip() -> Result<(), Box<dyn std::error::Error>> {
        let client = Client::without_redirects(crate::http::Credentials {
            user: "admin@example.org".into(),
            secret: std::env::var("AXISMUNDI_FILES_TEST_PASSWORD")?,
        });
        let session = client.session("https://localhost:18881/jmap/session")?;
        assert_eq!(
            url::Url::parse(&session.api_url)?
                .origin()
                .ascii_serialization(),
            "https://localhost:18881"
        );
        let account = session
            .primary_account(CAPABILITY)
            .ok_or("No Files primary account")?;
        let name = format!(
            "axismundi-files-probe-{}",
            std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)?
                .as_nanos()
        );
        let root = query(&client, &session, account, None, &[], 0, 20)?;
        assert!(root["ids"].is_array());
        let folder = set(
            &client,
            &session,
            account,
            json!({"create":{"probe":{"@type":"Directory","name":name,"parentId":null}}}),
        )?;
        let id = folder["created"]["probe"]["id"]
            .as_str()
            .ok_or_else(|| folder.to_string())?;
        let result = (|| -> Result<(), Box<dyn std::error::Error>> {
            let bytes = b"axismundi filenode interop\n";
            let uploaded = client.upload(&session, account, "text/plain", bytes)?;
            let result = create_file(
                &client,
                &session,
                account,
                Some(id),
                "probe.txt",
                "text/plain",
                &uploaded.blob_id,
                uploaded.size,
                None,
            )?;
            let UploadResult::Saved { node } = result else {
                return Err(format!("{result:?}").into());
            };
            let node_id = node["id"].as_str().ok_or("File has no id")?;
            let stored = node["blobId"].as_str().ok_or("File has no stored blobId")?;
            let listing = query(&client, &session, account, Some(id), &[], 0, 20)?;
            if !listing["ids"]
                .as_array()
                .is_some_and(|ids| ids.iter().any(|id| id == node_id))
            {
                return Err("Saved file is missing from the folder query.".into());
            }
            let mut download = Vec::new();
            let size = node["size"].as_u64().ok_or("File has no size")?;
            client.download_to(
                &session,
                account,
                stored,
                "text/plain",
                "probe.txt",
                size,
                &mut download,
            )?;
            if download != bytes {
                return Err("Downloaded bytes differ.".into());
            }
            Ok(())
        })();
        let cleanup = set(
            &client,
            &session,
            account,
            json!({"destroy":[id],"onDestroyRemoveChildren":true}),
        )?;
        if !cleanup["destroyed"]
            .as_array()
            .is_some_and(|values| values.iter().any(|value| value == id))
        {
            return Err(format!("Could not clean up test folder {id}: {cleanup}").into());
        }
        result
    }

    fn mock(replies: Vec<Option<Value>>) -> (Client, Session, std::thread::JoinHandle<Vec<Value>>) {
        use std::io::{BufRead, Read, Write};
        let listener = std::net::TcpListener::bind("127.0.0.1:0").unwrap();
        let session: Session = serde_json::from_value(json!({
            "apiUrl": format!("http://{}/", listener.local_addr().unwrap()),
            "accounts": {"a": {"accountCapabilities": {(CAPABILITY): {}, "urn:ietf:params:jmap:quota": {}}}}
        })).unwrap();
        let thread = std::thread::spawn(move || {
            replies.into_iter().map(|reply| {
            let (mut socket, _) = listener.accept().unwrap();
            socket.set_read_timeout(Some(std::time::Duration::from_secs(5))).unwrap();
            let mut reader = std::io::BufReader::new(&mut socket);
            let mut length = 0;
            loop {
                let mut line = String::new();
                assert_ne!(reader.read_line(&mut line).unwrap(), 0);
                if line == "\r\n" { break; }
                if let Some((key, value)) = line.split_once(':') {
                    if key.eq_ignore_ascii_case("content-length") { length = value.trim().parse().unwrap(); }
                }
            }
            let mut body = vec![0; length];
            reader.read_exact(&mut body).unwrap();
            let request: Value = serde_json::from_slice(&body).unwrap();
            if let Some(reply) = reply {
                let call = &request["methodCalls"][0];
                let body = json!({"methodResponses": [[call[0], reply, call[2]]]}).to_string();
                write!(socket, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}", body.len(), body).unwrap();
            }
            request
        }).collect()
        });
        (
            Client::without_redirects(crate::http::Credentials {
                user: "u".into(),
                secret: "p".into(),
            }),
            session,
            thread,
        )
    }

    #[test]
    fn saves_with_the_storage_blob_and_normalizes_the_name() {
        let (client, session, log) = mock(vec![
            Some(json!({"accountId":"a","created":{"file":{"id":"f"}}})),
            Some(json!({"accountId":"a","list":[{"id":"f","blobId":"stored","size":2}]})),
        ]);
        let result = create_file(
            &client,
            &session,
            "a",
            None,
            "e\u{301}",
            "text/plain",
            "uploaded",
            2,
            None,
        )
        .unwrap();
        assert!(matches!(result, UploadResult::Saved { node } if node["blobId"] == "stored"));
        let calls = log.join().unwrap();
        let file = &calls[0]["methodCalls"][0][1]["create"]["file"];
        assert_eq!(file["name"], "é");
        assert_eq!(file["mediaType"], "text/plain");
        assert_eq!(file["blobId"], "uploaded");
    }

    #[test]
    fn separates_rejection_unknown_create_and_failed_confirmation() {
        for error in ["alreadyExists", "overQuota"] {
            let (client, session, log) = mock(vec![Some(
                json!({"accountId":"a","notCreated":{"file":{"type":error,"existingId":"old"}}}),
            )]);
            assert!(
                matches!(create_file(&client,&session,"a",None,"x","text/plain","up",2,None).unwrap(),
                UploadResult::Rejected { error: value } if value["type"] == error)
            );
            log.join().unwrap();
        }
        let (client, session, log) = mock(vec![None]);
        assert!(
            matches!(create_file(&client,&session,"a",Some("folder"),"x","text/plain","up",2,None).unwrap(),
            UploadResult::Unknown { parent_id: Some(parent), name, .. } if parent == "folder" && name == "x")
        );
        log.join().unwrap();
        let (client, session, log) = mock(vec![
            Some(json!({"accountId":"a","created":{"file":{"id":"f"}}})),
            Some(json!({"accountId":"a","list":[]})),
        ]);
        assert!(
            matches!(create_file(&client,&session,"a",None,"x","text/plain","up",2,None).unwrap(),
            UploadResult::NeedsRefresh { id, .. } if id == "f")
        );
        log.join().unwrap();
    }

    #[test]
    fn uses_top_level_filter_and_keeps_only_applicable_quotas() {
        let (client, session, log) = mock(vec![
            Some(json!({"accountId":"a","ids":[]})),
            Some(json!({"accountId":"a","list":[
                {"types":["FileNode"]},{"types":["Email","FileNode"]},{"types":["Email"]}
            ]})),
            Some(json!({"accountId":"a","list":[]})),
        ]);
        query(&client, &session, "a", None, &[], 0, 20).unwrap();
        assert!(
            matches!(quotas(&client, &session, "a"), Quotas::Available { list } if list.len()==2 && list[1]["types"][0]=="Email")
        );
        assert!(matches!(
            quotas(&client, &session, "a"),
            Quotas::NotApplicable
        ));
        let calls = log.join().unwrap();
        assert_eq!(
            calls[0]["methodCalls"][0][1]["filter"],
            json!({"isTopLevel":true})
        );
        assert_eq!(calls[1]["methodCalls"][0][1].get("ids"), Some(&Value::Null));
    }

    #[test]
    fn file_names_follow_utf8_byte_limit_and_server_restrictions() {
        let session: Session = serde_json::from_value(json!({
            "apiUrl": "https://example.org/jmap", "accounts": {"a": {
                "accountCapabilities": {(CAPABILITY): {
                    "maxSizeFileNodeName": 4, "forbiddenNameChars": ":", "forbiddenNodeNames": ["CON"]
                }}
            }}
        })).unwrap();
        assert!(validate_name(&session, "a", "éé").is_ok());
        for name in ["ééx", "a:b", "a/b", "..", "", "CON"] {
            assert!(validate_name(&session, "a", name).is_err(), "{name}");
        }
    }
}
