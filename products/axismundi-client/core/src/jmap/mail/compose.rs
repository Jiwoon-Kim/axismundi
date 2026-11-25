//! Plain-text drafts and submission. A lost write response is never replayed.
use super::CAPABILITY;
use crate::{
    Error,
    http::Client,
    jmap::{Batch, CORE_CAPABILITY, Session},
};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};

pub const SUBMISSION: &str = "urn:ietf:params:jmap:submission";

#[derive(Debug, Deserialize, Serialize)]
pub struct Address {
    pub name: Option<String>,
    pub email: String,
}

#[derive(Debug, Deserialize, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Identity {
    pub id: String,
    pub name: String,
    pub email: String,
    pub reply_to: Option<Vec<Address>>,
    pub bcc: Option<Vec<Address>>,
}

#[derive(Debug, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct Draft {
    pub identity_id: String,
    pub wildcard_address: Option<String>,
    pub to: Vec<Address>,
    pub cc: Vec<Address>,
    pub bcc: Vec<Address>,
    pub subject: String,
    pub body: String,
    pub in_reply_to: Vec<String>,
    pub references: Vec<String>,
}

#[derive(Debug, PartialEq, Eq)]
pub enum Write<T> {
    Applied(T),
    Rejected(String),
    Unknown,
}

#[derive(Debug, PartialEq, Eq)]
pub struct SavedDraft {
    pub id: String,
    pub pending_cleanup: Vec<String>,
}

pub fn identities(
    client: &Client,
    session: &Session,
    account: &str,
) -> Result<(String, Vec<Identity>), Error> {
    let batch = client.request(
        session,
        &[CORE_CAPABILITY, CAPABILITY, SUBMISSION],
        vec![("Identity/get", json!({"accountId": account}), "identity")],
    )?;
    let reply = &batch.response("identity", "Identity/get")?.arguments;
    if reply["accountId"] != account {
        return Err(Error::Shape("Identity/get account mismatch".into()));
    }
    let state = reply["state"]
        .as_str()
        .ok_or_else(|| Error::Shape("missing Identity state".into()))?;
    let list = serde_json::from_value(reply["list"].clone())
        .map_err(|_| Error::Shape("invalid Identity list".into()))?;
    Ok((state.to_owned(), list))
}

#[derive(Debug, Deserialize, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct ComposeSource {
    pub id: String,
    pub from: Option<Vec<Address>>,
    pub reply_to: Option<Vec<Address>>,
    pub to: Option<Vec<Address>>,
    pub cc: Option<Vec<Address>>,
    pub bcc: Option<Vec<Address>>,
    pub subject: Option<String>,
    pub message_id: Option<Vec<String>>,
    pub in_reply_to: Option<Vec<String>>,
    pub references: Option<Vec<String>>,
    #[serde(default)]
    pub body: String,
    #[serde(default)]
    pub is_draft: bool,
}

#[derive(Debug)]
pub enum SourceError {
    Request(Error),
    UnsupportedDraft,
}

impl From<Error> for SourceError {
    fn from(error: Error) -> Self {
        Self::Request(error)
    }
}

pub fn source(
    client: &Client,
    session: &Session,
    account: &str,
    email: &str,
) -> Result<ComposeSource, SourceError> {
    let batch = client.request(session, &[CORE_CAPABILITY, CAPABILITY], vec![("Email/get", json!({
        "accountId":account, "ids":[email], "fetchTextBodyValues":true, "maxBodyValueBytes":1048576,
        "properties":["id","from","replyTo","to","cc","bcc","subject","messageId",
            "inReplyTo","references","keywords","textBody","htmlBody","attachments","bodyValues"]
    }), "source")])?;
    let reply = &batch.response("source", "Email/get")?.arguments;
    let row = &reply["list"][0];
    if reply["accountId"] != account || row["id"] != email {
        return Err(Error::Shape("message is unavailable".into()).into());
    }
    decode_source(row)
}

fn decode_source(row: &Value) -> Result<ComposeSource, SourceError> {
    let mut source: ComposeSource = serde_json::from_value(row.clone())
        .map_err(|_| Error::Shape("invalid message source".into()))?;
    source.is_draft = row["keywords"]["$draft"] == true;
    if source.is_draft {
        let invalid = || Error::Shape("missing draft body metadata".into());
        let html = row["htmlBody"].as_array().ok_or_else(invalid)?;
        let attachments = row["attachments"].as_array().ok_or_else(invalid)?;
        // htmlBody can contain the same text/plain parts as textBody.
        if !attachments.is_empty()
            || html.iter().any(|part| {
                !part["type"]
                    .as_str()
                    .is_some_and(|kind| kind.eq_ignore_ascii_case("text/plain"))
            })
        {
            return Err(SourceError::UnsupportedDraft);
        }
    }
    let mut parts = Vec::new();
    for part in row["textBody"].as_array().into_iter().flatten() {
        if part["type"] != "text/plain" {
            continue;
        }
        let value = &row["bodyValues"][part["partId"].as_str().unwrap_or_default()];
        if source.is_draft && (value["isTruncated"] == true || !value["value"].is_string()) {
            return Err(Error::Shape("draft body is incomplete".into()).into());
        }
        if let Some(text) = value["value"].as_str() {
            parts.push(text);
        }
    }
    if source.is_draft && parts.is_empty() {
        return Err(SourceError::UnsupportedDraft);
    }
    source.body = parts.join("\n\n");
    Ok(source)
}

fn draft_email(draft: &Draft, identity: &Identity, mailbox: &str) -> Result<Value, String> {
    if draft.identity_id != identity.id {
        return Err("forbiddenFrom".into());
    }
    let address = if let Some(domain) = identity.email.strip_prefix("*@") {
        draft
            .wildcard_address
            .as_deref()
            .filter(|address| {
                address.split_once('@').is_some_and(|(local, rhs)| {
                    !local.is_empty()
                        && !local.contains(['*', '<', '>'])
                        && !address.chars().any(|c| c.is_whitespace() || c.is_control())
                        && rhs.eq_ignore_ascii_case(domain)
                })
            })
            .ok_or_else(|| "forbiddenFrom".to_owned())?
    } else {
        if draft.wildcard_address.is_some() {
            return Err("forbiddenFrom".into());
        }
        &identity.email
    };
    let mut bcc: Vec<&Address> = draft.bcc.iter().collect();
    for entry in identity.bcc.iter().flatten() {
        if !bcc.iter().any(|a| a.email == entry.email) {
            bcc.push(entry);
        }
    }
    let mut from = json!({"email": address});
    if !identity.name.is_empty() {
        from["name"] = json!(identity.name);
    }
    let mut email = json!({
        "from": [from],
        "to": draft.to, "cc": draft.cc, "bcc": bcc, "subject": draft.subject,
        "mailboxIds": {mailbox: true}, "keywords": {"$draft": true, "$seen": true},
        "textBody": [{"partId": "text", "type": "text/plain"}],
        "bodyValues": {"text": {"value": draft.body}},
    });
    if let Some(reply_to) = &identity.reply_to {
        email["replyTo"] = json!(reply_to);
    }
    if !draft.in_reply_to.is_empty() {
        email["inReplyTo"] = json!(draft.in_reply_to);
    }
    if !draft.references.is_empty() {
        email["references"] = json!(draft.references);
    }
    Ok(email)
}

fn created(batch: Result<Batch, Error>, method: &str, account: &str, key: &str) -> Write<String> {
    let Ok(batch) = batch else {
        return Write::Unknown;
    };
    let mut matches = batch
        .method_responses
        .iter()
        .filter(|reply| reply.call_id == key && (reply.name == method || reply.is_error()));
    let Some(reply) = matches.next() else {
        return Write::Unknown;
    };
    if matches.next().is_some() {
        return Write::Unknown;
    }
    if reply.is_error() {
        return reply
            .error_type()
            .map_or(Write::Unknown, |kind| Write::Rejected(kind.to_owned()));
    }
    let Ok(outcome) = reply.set_outcome() else {
        return Write::Unknown;
    };
    if outcome.account_id != account {
        return Write::Unknown;
    }
    match (outcome.created.get(key), outcome.not_created.get(key)) {
        (Some(value), None) => value["id"]
            .as_str()
            .filter(|id| !id.is_empty())
            .map_or(Write::Unknown, |id| Write::Applied(id.to_owned())),
        (None, Some(error)) => Write::Rejected(error.kind.clone()),
        _ => Write::Unknown,
    }
}

fn save(
    account: &str,
    mailbox: &str,
    email: Value,
    previous: &[String],
    mut call: impl FnMut(&str, Value, &str) -> Result<Batch, Error>,
) -> Write<SavedDraft> {
    let id = match created(
        call(
            "Email/set",
            json!({"accountId": account, "create": {"draft": email}}),
            "draft",
        ),
        "Email/set",
        account,
        "draft",
    ) {
        Write::Applied(id) => id,
        Write::Rejected(kind) => return Write::Rejected(kind),
        Write::Unknown => return Write::Unknown,
    };
    let mut pending_cleanup: Vec<_> = previous.iter().filter(|old| **old != id).cloned().collect();
    pending_cleanup.sort_unstable();
    pending_cleanup.dedup();
    let pending_cleanup = cleanup(account, mailbox, pending_cleanup, call);
    Write::Applied(SavedDraft {
        id,
        pending_cleanup,
    })
}

fn cleanup(
    account: &str,
    mailbox: &str,
    mut pending_cleanup: Vec<String>,
    mut call: impl FnMut(&str, Value, &str) -> Result<Batch, Error>,
) -> Vec<String> {
    if !pending_cleanup.is_empty()
        && let Ok(batch) = call(
            "Email/get",
            json!({
                "accountId": account, "ids": pending_cleanup, "properties": ["id", "mailboxIds", "keywords"]
            }),
            "check",
        )
        && let Ok(reply) = batch.response("check", "Email/get")
        && reply.arguments["accountId"] == account
        && let Some(state) = reply.arguments["state"].as_str()
        && let Some(rows) = reply.arguments["list"].as_array()
    {
        let mut safe = Vec::new();
        pending_cleanup.retain(|old| {
            let mut matching = rows.iter().filter(|row| row["id"] == *old);
            let row = matching.next();
            let missing = reply.arguments["notFound"]
                .as_array()
                .is_some_and(|ids| ids.iter().any(|id| id == old));
            if matching.next().is_some() || (row.is_some() && missing) {
                return true;
            }
            let Some(row) = row else { return !missing };
            let (Some(keywords), Some(mailboxes)) =
                (row["keywords"].as_object(), row["mailboxIds"].as_object())
            else {
                return true;
            };
            if keywords.get("$draft") != Some(&Value::Bool(true))
                || mailboxes.get(mailbox) != Some(&Value::Bool(true))
                || mailboxes.len() != 1
            {
                return false;
            }
            safe.push(old.clone());
            true
        });
        if !safe.is_empty()
            && let Ok(outcome) = call(
                "Email/set",
                json!({
                    "accountId": account, "destroy": safe, "ifInState": state
                }),
                "cleanup",
            )
            .and_then(|batch| batch.response("cleanup", "Email/set")?.set_outcome())
            && outcome.account_id == account
        {
            pending_cleanup.retain(|old| {
                !safe.contains(old)
                    || (!outcome.destroyed.contains(old)
                        && !outcome
                            .not_destroyed
                            .get(old)
                            .is_some_and(|err| err.kind == "notFound"))
            });
        }
    }
    pending_cleanup
}

/// Dispose of a known draft only while it still belongs exclusively to Drafts.
pub fn discard_drafts(
    client: &Client,
    session: &Session,
    account: &str,
    mailbox: &str,
    ids: Vec<String>,
) -> bool {
    cleanup(account, mailbox, ids, |method, args, tag| {
        client.request(
            session,
            &[CORE_CAPABILITY, CAPABILITY],
            vec![(method, args, tag)],
        )
    })
    .is_empty()
}

/// The caller supplies a server-read Identity and Drafts id from its current account cache.
/// Do not populate either from webview form fields.
pub fn save_draft(
    client: &Client,
    session: &Session,
    account: &str,
    identity: &Identity,
    drafts_mailbox: &str,
    draft: &Draft,
    previous: &[String],
) -> Write<SavedDraft> {
    let email = match draft_email(draft, identity, drafts_mailbox) {
        Ok(email) => email,
        Err(kind) => return Write::Rejected(kind),
    };
    save(
        account,
        drafts_mailbox,
        email,
        previous,
        |method, args, tag| {
            client.request(
                session,
                &[CORE_CAPABILITY, CAPABILITY],
                vec![(method, args, tag)],
            )
        },
    )
}

/// Submit the exact draft just saved with this Identity. Accepted is not a delivery receipt.
pub fn submit(
    client: &Client,
    session: &Session,
    account: &str,
    identity: &Identity,
    drafts_mailbox: &str,
    sent_mailbox: Option<&str>,
    draft: &SavedDraft,
) -> Write<()> {
    submit_once(
        account,
        &identity.id,
        drafts_mailbox,
        sent_mailbox,
        &draft.id,
        |method, args, tag| {
            client.request(
                session,
                &[CORE_CAPABILITY, CAPABILITY, SUBMISSION],
                vec![(method, args, tag)],
            )
        },
    )
}

fn submit_once(
    account: &str,
    identity: &str,
    drafts: &str,
    sent: Option<&str>,
    email: &str,
    mut call: impl FnMut(&str, Value, &str) -> Result<Batch, Error>,
) -> Write<()> {
    let mut patch = json!({"keywords/$draft": null});
    let path = |id: &str| format!("mailboxIds/{}", id.replace('~', "~0").replace('/', "~1"));
    if let Some(sent) = sent.filter(|sent| *sent != drafts) {
        patch[path(drafts)] = Value::Null;
        patch[path(sent)] = json!(true);
    }
    let response = call(
        "EmailSubmission/set",
        json!({
            "accountId": account,
            "create": {"send": {"emailId": email, "identityId": identity}},
            "onSuccessUpdateEmail": {"#send": patch}
        }),
        "send",
    );
    // A method-level error need not establish that the server never started sending.
    let response = response.and_then(|batch| {
        batch.response("send", "EmailSubmission/set")?;
        Ok(batch)
    });
    match created(response, "EmailSubmission/set", account, "send") {
        Write::Applied(_) => return Write::Applied(()),
        Write::Rejected(kind) => return Write::Rejected(kind),
        Write::Unknown => {}
    }
    let mut read = |method, args, tag| -> Option<Value> {
        let batch = call(method, args, tag).ok()?;
        let reply = batch.response(tag, method).ok()?;
        (reply.arguments["accountId"] == account).then(|| reply.arguments.clone())
    };
    if let Some(reply) = read(
        "Email/get",
        json!({
            "accountId": account, "ids": [email], "properties": ["id", "mailboxIds", "keywords"]
        }),
        "check",
    ) && let Some(sent) = sent.filter(|sent| *sent != drafts)
        && let Some([row]) = reply["list"].as_array().map(Vec::as_slice)
        && row["id"] == email
        && row["mailboxIds"][sent] == true
        && row["keywords"]
            .as_object()
            .is_some_and(|keys| !keys.contains_key("$draft"))
    {
        return Write::Applied(());
    }
    if let Some(query) = read(
        "EmailSubmission/query",
        json!({
            "accountId": account, "filter": {"emailIds": [email]}, "limit": 2
        }),
        "find",
    ) && let Some([id]) = query["ids"].as_array().map(Vec::as_slice)
        && id.as_str().is_some_and(|id| !id.is_empty())
        && let Some(reply) = read(
            "EmailSubmission/get",
            json!({
                "accountId": account, "ids": [id], "properties": ["id", "emailId", "identityId", "undoStatus"]
            }),
            "verify",
        )
        && let Some([row]) = reply["list"].as_array().map(Vec::as_slice)
        && row["id"] == *id
        && row["emailId"] == email
        && row["identityId"] == identity
        && row["undoStatus"] == "final"
    {
        return Write::Applied(());
    }
    Write::Unknown
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::jmap::Invocation;

    #[test]
    fn draft_accepts_plain_fallback_in_html_body_but_not_rich_parts_or_attachments() {
        let mut row = json!({
            "id":"draft","keywords":{"$draft":true},"attachments":[],
            "textBody":[{"partId":"0","type":"text/plain"}],
            "htmlBody":[{"partId":"0","type":"text/plain"}],
            "bodyValues":{"0":{"value":"Keep this text","isTruncated":false}}
        });
        assert_eq!(decode_source(&row).unwrap().body, "Keep this text");
        row["htmlBody"] = json!([{"partId":"1","type":"text/html"}]);
        assert!(matches!(
            decode_source(&row),
            Err(SourceError::UnsupportedDraft)
        ));
        row["htmlBody"] = row["textBody"].clone();
        row["attachments"] = json!([{"partId":"2","type":"application/pdf"}]);
        assert!(matches!(
            decode_source(&row),
            Err(SourceError::UnsupportedDraft)
        ));
    }

    #[test]
    fn lost_submission_is_never_replayed() {
        for evidence in ["sent", "submission", "none"] {
            let mut calls = Vec::new();
            let result = submit_once("a", "i", "drafts", Some("s/~"), "e", |method, args, tag| {
                calls.push(method.to_owned());
                let arguments = match method {
                    "EmailSubmission/set" => {
                        assert_eq!(
                            args["create"]["send"],
                            json!({"emailId":"e","identityId":"i"})
                        );
                        assert_eq!(
                            args["onSuccessUpdateEmail"]["#send"],
                            json!({
                                "keywords/$draft":null,"mailboxIds/drafts":null,"mailboxIds/s~1~0":true
                            })
                        );
                        return Err(Error::Transport("lost".into()));
                    }
                    "Email/get" if evidence == "sent" => json!({"accountId":"a","list":[
                        {"id":"e","mailboxIds":{"s/~":true},"keywords":{}}
                    ]}),
                    "Email/get" => json!({"accountId":"a","list":[]}),
                    "EmailSubmission/query" if evidence == "submission" => {
                        json!({"accountId":"a","ids":["sub"]})
                    }
                    "EmailSubmission/query" => json!({"accountId":"a","ids":[]}),
                    "EmailSubmission/get" => json!({"accountId":"a","list":[
                        {"id":"sub","emailId":"e","identityId":"i","undoStatus":"final"}
                    ]}),
                    _ => panic!("unexpected method"),
                };
                Ok(Batch {
                    method_responses: vec![Invocation {
                        name: method.into(),
                        call_id: tag.into(),
                        arguments,
                    }],
                    session_state: None,
                })
            });
            assert_eq!(
                result,
                if evidence == "none" {
                    Write::Unknown
                } else {
                    Write::Applied(())
                }
            );
            assert_eq!(
                calls
                    .iter()
                    .filter(|method| *method == "EmailSubmission/set")
                    .count(),
                1
            );
        }
    }

    #[test]
    fn from_comes_from_identity_and_message_id_is_omitted() {
        let mut identity: Identity = serde_json::from_value(json!({
            "id": "i", "name": "Alice", "email": "alice@example.org"
        }))
        .unwrap();
        let mut draft: Draft = serde_json::from_value(json!({
            "identityId": "i", "to": [], "cc": [], "bcc": [], "subject": "Hello",
            "body": "Plain text", "inReplyTo": ["parent@example.org"],
            "references": ["first@example.org", "parent@example.org"]
        }))
        .unwrap();
        let email = draft_email(&draft, &identity, "drafts").unwrap();
        assert_eq!(
            email["from"],
            json!([{"name":"Alice","email":"alice@example.org"}])
        );
        assert!(email.get("messageId").is_none());
        assert!(email.get("sender").is_none());
        assert_eq!(email["inReplyTo"], json!(["parent@example.org"]));
        assert_eq!(
            email["references"],
            json!(["first@example.org", "parent@example.org"])
        );
        draft.wildcard_address = Some("custom@example.org".into());
        assert_eq!(
            draft_email(&draft, &identity, "drafts"),
            Err("forbiddenFrom".into())
        );
        identity.email = "*@example.org".into();
        assert_eq!(
            draft_email(&draft, &identity, "drafts").unwrap()["from"][0]["email"],
            "custom@example.org"
        );
        draft.wildcard_address = Some("custom@elsewhere.example".into());
        assert_eq!(
            draft_email(&draft, &identity, "drafts"),
            Err("forbiddenFrom".into())
        );
    }

    fn response(tag: &str, value: Value) -> Batch {
        Batch {
            method_responses: vec![Invocation {
                name: if tag == "check" {
                    "Email/get"
                } else {
                    "Email/set"
                }
                .into(),
                arguments: value,
                call_id: tag.into(),
            }],
            session_state: None,
        }
    }

    #[test]
    fn rejected_or_lost_create_never_destroys_old_draft() {
        for rejected in [true, false] {
            let mut calls = 0;
            let result = save("a", "d", json!({}), &["old".into()], |_, args, tag| {
                calls += 1;
                assert_eq!(tag, "draft");
                assert!(args.get("destroy").is_none());
                if rejected {
                    Ok(response(
                        tag,
                        json!({"accountId":"a","newState":"s","notCreated":{"draft":{"type":"overQuota"}}}),
                    ))
                } else {
                    Err(Error::Transport("lost".into()))
                }
            });
            let expected = if rejected {
                Write::Rejected("overQuota".into())
            } else {
                Write::Unknown
            };
            assert_eq!(result, expected);
            assert_eq!(calls, 1);
        }
    }

    #[test]
    fn confirmed_draft_survives_lost_cleanup_response() {
        let mut calls = vec![];
        let result = save(
            "a",
            "d",
            json!({}),
            &["old".into(), "gone".into(), "sent".into(), "omitted".into()],
            |_, args, tag| {
                calls.push(tag.to_owned());
                if tag == "draft" {
                    Ok(response(
                        tag,
                        json!({"accountId":"a","newState":"s","created":{"draft":{"id":"new"}}}),
                    ))
                } else if tag == "check" {
                    Ok(response(
                        tag,
                        json!({"accountId":"a","state":"checked","notFound":["gone"],"list":[
                            {"id":"old","keywords":{"$draft":true},"mailboxIds":{"d":true}},
                            {"id":"sent","keywords":{},"mailboxIds":{"s":true}}
                        ]}),
                    ))
                } else {
                    assert_eq!(args["destroy"], json!(["old"]));
                    assert_eq!(args["ifInState"], "checked");
                    Err(Error::Transport("lost".into()))
                }
            },
        );
        assert_eq!(calls, ["draft", "check", "cleanup"]);
        assert_eq!(
            result,
            Write::Applied(SavedDraft {
                id: "new".into(),
                pending_cleanup: vec!["old".into(), "omitted".into()]
            })
        );
    }
}
