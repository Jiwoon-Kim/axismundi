//! Read-only Mail operations and a partial local cache. Never submits messages.
use crate::{Batch, CORE_CAPABILITY, Client, Error, Session};
use serde_json::{Value, json};

pub mod cache;

const LIST_PROPERTIES: &[&str] = &[
    "id",
    "threadId",
    "mailboxIds",
    "keywords",
    "size",
    "receivedAt",
    "from",
    "subject",
    "preview",
    "hasAttachment",
];
const DETAIL_PROPERTIES: &[&str] = &[
    "id",
    "blobId",
    "threadId",
    "mailboxIds",
    "keywords",
    "subject",
    "from",
    "to",
    "cc",
    "receivedAt",
    "sentAt",
    "bodyStructure",
    "textBody",
    "htmlBody",
    "attachments",
    "bodyValues",
];

/// The Mailbox properties the cache keeps. A snapshot and a delta fetch must ask for the same set,
/// or a mailbox refreshed by a delta would silently lose a property the snapshot had.
pub(crate) const MAILBOX_PROPERTIES: &[&str] = &[
    "id",
    "name",
    "parentId",
    "role",
    "sortOrder",
    "myRights",
    "totalEmails",
    "unreadEmails",
];

pub const CAPABILITY: &str = "urn:ietf:params:jmap:mail";

/// The body size a detail read asks for. The cache records this same value as the coverage of the
/// bodyValues it stored, so the two must never be written as separate literals: if the request
/// changed and the record did not, the cache would claim bytes it never fetched.
pub(crate) const MAX_BODY_VALUE_BYTES: u64 = 65536;

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
    arguments_for(batch, name, account, "mail")
}

fn arguments_for(batch: &Batch, name: &str, account: &str, call_id: &str) -> Result<Value, Error> {
    let value = &batch.response(call_id, name)?.arguments;
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
        json!({"accountId":account,"properties":MAILBOX_PROPERTIES}),
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
        json!({"accountId":account,"ids":[id],"properties":DETAIL_PROPERTIES,"fetchTextBodyValues":true,"fetchHTMLBodyValues":true,"maxBodyValueBytes":MAX_BODY_VALUE_BYTES}),
    )?;
    let result = arguments(&batch, "Email/get", account)?;
    validate_get(&result, &[id.to_owned()])?;
    Ok(result)
}

/// Checks a Foo/get answer of any type: every id asked for comes back exactly once, in `list` or
/// `notFound`, and nothing else does.
pub(crate) fn validate_get(result: &Value, ids: &[String]) -> Result<(), Error> {
    use std::collections::BTreeSet;
    let rows = result
        .get("list")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("/get has no list".into()))?;
    let missing = result
        .get("notFound")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("/get has no notFound".into()))?;
    let expected: BTreeSet<_> = ids.iter().map(String::as_str).collect();
    let mut actual = BTreeSet::new();
    for id in rows
        .iter()
        .map(|row| row.get("id"))
        .chain(missing.iter().map(Some))
    {
        let id = id
            .and_then(Value::as_str)
            .ok_or_else(|| Error::Shape("invalid /get id".into()))?;
        if !expected.contains(id) || !actual.insert(id) {
            return Err(Error::Shape("unexpected or duplicate /get id".into()));
        }
    }
    if actual != expected {
        return Err(Error::Shape("incomplete /get response".into()));
    }
    Ok(())
}

/// What one row of a mailbox listing is: a message, or a thread shown by one of its messages.
#[derive(Clone, Copy, Debug, PartialEq, Eq)]
pub enum Rows {
    Messages,
    Threads,
}

impl Rows {
    fn collapse_threads(self) -> bool {
        matches!(self, Rows::Threads)
    }
}

/// The receivedAt-descending order every listing uses today. When sort becomes a caller's choice it
/// has to join both the arguments and the key below.
const SORT_PROPERTY: &str = "receivedAt";

fn query_args(account: &str, mailbox: &str, count: usize, rows: Rows) -> Value {
    json!({"accountId":account,"filter":{"inMailbox":mailbox},"sort":[{"property":SORT_PROPERTY,"isAscending":false}],"collapseThreads":rows.collapse_threads(),"position":0,"limit":count})
}

/// Identity of a query: every argument that changes which ids come back or their order, and
/// nothing else. position and limit are the window onto the query, not the query, and the account is
/// already the row's own key. Two listings of one mailbox that differ only in collapseThreads are two
/// queries, so a message listing and a thread listing are cached side by side rather than one
/// overwriting the other.
///
/// Assembled from primitives in a fixed order rather than by serialising a map. The CLI and the GUI
/// open the same profile file, and the GUI build unifies serde_json features with Tauri's graph: if
/// anything there ever enabled preserve_order, a serialised map would key differently in the two
/// builds and each would miss the other's rows. JMAP ids are base64url characters, so `|` cannot occur
/// inside a mailbox id.
pub(crate) fn query_key(mailbox: &str, rows: Rows) -> String {
    format!(
        "v1|inMailbox={mailbox}|sort={SORT_PROPERTY}:desc|collapseThreads={}",
        rows.collapse_threads()
    )
}

fn query_ids(query: &Value, count: usize) -> Result<Vec<String>, Error> {
    let ids = query
        .get("ids")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("Email/query has no ids".into()))?;
    let mut seen = std::collections::BTreeSet::new();
    let result: Vec<String> = ids
        .iter()
        .map(|id| {
            let id = id
                .as_str()
                .ok_or_else(|| Error::Shape("invalid query id".into()))?;
            if !seen.insert(id) {
                return Err(Error::Shape("duplicate query id".into()));
            }
            Ok(id.to_owned())
        })
        .collect::<Result<_, _>>()?;
    if result.len() > count {
        return Err(Error::Shape("query exceeds requested limit".into()));
    }
    Ok(result)
}

/// How many rows a listing window holds: never more than one Email/get may carry.
pub(crate) fn window_size(session: &Session) -> Result<usize, Error> {
    Ok(limit(session, "maxObjectsInGet")?.min(50))
}

/// What Email/queryChanges said happened to a query since a queryState.
#[derive(Debug, PartialEq)]
pub(crate) struct QueryDelta {
    pub new_state: String,
    pub removed: Vec<String>,
    /// (id, index in the new results), in ascending index order.
    pub added: Vec<(String, usize)>,
}

/// Asks what changed in a cached query. `None` means the server cannot say (cannotCalculateChanges
/// or tooManyChanges) and the window has to be read again from the top; that is an ordinary answer,
/// not a failure.
pub(crate) fn query_changes(
    client: &Client,
    session: &Session,
    args: &Value,
    since: &str,
) -> Result<Option<QueryDelta>, Error> {
    let account = args["accountId"]
        .as_str()
        .ok_or_else(|| Error::Shape("query arguments have no accountId".into()))?;
    let mut request = json!({"accountId":account,"sinceQueryState":since,"maxChanges":256});
    for key in ["filter", "sort", "collapseThreads"] {
        if let Some(value) = args.get(key) {
            request[key] = value.clone();
        }
    }
    let batch = call(client, session, "Email/queryChanges", request)?;
    if let [only] = batch.method_responses.as_slice() {
        if only.call_id == "mail"
            && matches!(only.error_type(), Some("cannotCalculateChanges" | "tooManyChanges"))
        {
            return Ok(None);
        }
    }
    let result = arguments(&batch, "Email/queryChanges", account)?;
    let text = |key: &str| {
        result
            .get(key)
            .and_then(Value::as_str)
            .ok_or_else(|| Error::Shape(format!("Email/queryChanges has no {key}")))
    };
    if text("oldQueryState")? != since {
        return Err(Error::Shape("Email/queryChanges oldQueryState mismatch".into()));
    }
    let new_state = text("newQueryState")?.to_owned();
    let mut removed = Vec::new();
    let mut seen = std::collections::BTreeSet::new();
    for id in result
        .get("removed")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("Email/queryChanges has no removed".into()))?
    {
        let id = id
            .as_str()
            .ok_or_else(|| Error::Shape("invalid removed id".into()))?;
        if !seen.insert(id) {
            return Err(Error::Shape("duplicate removed id".into()));
        }
        removed.push(id.to_owned());
    }
    let mut added: Vec<(String, usize)> = Vec::new();
    let mut ids = std::collections::BTreeSet::new();
    for item in result
        .get("added")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("Email/queryChanges has no added".into()))?
    {
        let id = item.get("id").and_then(Value::as_str);
        let index = item
            .get("index")
            .and_then(Value::as_u64)
            .and_then(|i| usize::try_from(i).ok());
        let (Some(id), Some(index)) = (id, index) else {
            return Err(Error::Shape("invalid added item".into()));
        };
        if !ids.insert(id) || added.last().is_some_and(|(_, last)| *last >= index) {
            return Err(Error::Shape("added items must be unique and in index order".into()));
        }
        added.push((id.to_owned(), index));
    }
    Ok(Some(QueryDelta {
        new_state,
        removed,
        added,
    }))
}

/// Applies a query delta to the first rows of a query (RFC 8620 §5.6): drop every removed id, then
/// insert every added id at its index, lowest index first.
///
/// A window holds only the start of the results, and the delta does not mention rows that merely
/// slid up from beyond it when rows above were removed. So the result is the prefix this delta
/// proves, nothing more: an added index past the end of what we hold is a row after a gap we cannot
/// fill, and so is everything after it. The caller tops the window up from the server when the
/// prefix comes back shorter than the window.
///
/// An added id is removed first wherever it is. For a row that moved (a mutable sort or filter) the
/// server lists it in removed as well; for any other row this keeps one id from appearing twice.
pub(crate) fn apply_query_delta(cached: &[String], delta: &QueryDelta, size: usize) -> Vec<String> {
    let gone: std::collections::BTreeSet<&str> = delta
        .removed
        .iter()
        .chain(delta.added.iter().map(|(id, _)| id))
        .map(String::as_str)
        .collect();
    let mut rows: Vec<String> = cached
        .iter()
        .filter(|id| !gone.contains(id.as_str()))
        .cloned()
        .collect();
    for (id, index) in &delta.added {
        if *index > rows.len() {
            break;
        }
        rows.insert(*index, id.clone());
    }
    rows.truncate(size);
    rows
}

struct Window {
    key: String,
    args: Value,
    query: Value,
    gets: Vec<Value>,
}

fn window(
    client: &Client,
    session: &Session,
    account: &str,
    mailbox: &str,
    rows: Rows,
) -> Result<Window, Error> {
    let count = window_size(session)?;
    let key = query_key(mailbox, rows);
    let args = query_args(account, mailbox, count, rows);
    let referenced = json!({"accountId":account,"#ids":{"resultOf":"query","name":"Email/query","path":"/ids"},"properties":LIST_PROPERTIES});
    let payload = json!({"using":[CORE_CAPABILITY,CAPABILITY],"methodCalls":[["Email/query",args,"query"],["Email/get",referenced,"get"]]});
    if limit(session, "maxCallsInRequest")? >= 2
        && payload.to_string().len() <= limit(session, "maxSizeRequest")?
    {
        let batch = client.request(
            session,
            &[CORE_CAPABILITY, CAPABILITY],
            vec![
                ("Email/query", args.clone(), "query"),
                ("Email/get", referenced, "get"),
            ],
        )?;
        let query = arguments_for(&batch, "Email/query", account, "query")?;
        let ids = query_ids(&query, count)?;
        let get = arguments_for(&batch, "Email/get", account, "get")?;
        validate_get(&get, &ids)?;
        return Ok(Window {
            key,
            args,
            query,
            gets: vec![get],
        });
    }
    // Splitting is safe here whichever Rows was asked for: the query returns at most `count` ids,
    // one per thread when collapsed, and `count` never exceeds maxObjectsInGet. What fans out past
    // that bound is expanding a thread into all of its messages, which this listing never does.
    let batch = call(client, session, "Email/query", args.clone())?;
    let query = arguments(&batch, "Email/query", account)?;
    let ids = query_ids(&query, count)?;
    let mut gets = Vec::new();
    for chunk in ids.chunks(limit(session, "maxObjectsInGet")?) {
        let batch = call(
            client,
            session,
            "Email/get",
            json!({"accountId":account,"ids":chunk,"properties":LIST_PROPERTIES}),
        )?;
        let get = arguments(&batch, "Email/get", account)?;
        validate_get(&get, chunk)?;
        gets.push(get);
    }
    Ok(Window {
        key,
        args,
        query,
        gets,
    })
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
    let Window { query, gets, .. } = window(client, session, account, mailbox, Rows::Messages)?;
    let mut emails = Vec::new();
    let mut not_found = Vec::new();
    let mut get_states = Vec::new();
    for result in gets {
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

/// The ids a Thread/get returned for the one thread asked for, in the server's order.
///
/// A thread nobody can see is answered with `notFound`, and that is an ordinary outcome rather than
/// a malformed response, so it comes back as `None`.
fn thread_email_ids(result: &Value, thread: &str) -> Result<Option<Vec<String>>, Error> {
    let list = result
        .get("list")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("Thread/get has no list".into()))?;
    let missing = result
        .get("notFound")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("Thread/get has no notFound".into()))?;
    let row = match (list.as_slice(), missing.as_slice()) {
        ([row], []) if row.get("id").and_then(Value::as_str) == Some(thread) => row,
        ([], [id]) if id.as_str() == Some(thread) => return Ok(None),
        _ => return Err(Error::Shape("Thread/get did not answer for the thread asked".into())),
    };
    let ids = row
        .get("emailIds")
        .and_then(Value::as_array)
        .ok_or_else(|| Error::Shape("Thread has no emailIds".into()))?;
    let mut seen = std::collections::BTreeSet::new();
    ids.iter()
        .map(|id| {
            let id = id
                .as_str()
                .ok_or_else(|| Error::Shape("invalid Thread emailId".into()))?;
            if !seen.insert(id) {
                return Err(Error::Shape("duplicate Thread emailId".into()));
            }
            Ok(id.to_owned())
        })
        .collect::<Result<_, _>>()
        .map(Some)
}

/// Expands one thread into every message in it.
///
/// A thread has no upper size: one conversation can hold thousands of messages, and nothing about
/// maxObjectsInGet bounds it. So the ids come first, from Thread/get on its own, and the messages
/// follow in Email/get requests of at most maxObjectsInGet ids each — as many requests as it takes.
/// Nothing is cut off to make it fit. Chaining Thread/get into Email/get by result reference would
/// save a round trip only for threads small enough not to need it, and fail with requestTooLarge for
/// exactly the ones that matter, so it is not attempted.
///
/// The messages are fetched with the listing's properties, not bodies; opening one is `read`.
/// A message deleted between the two steps lands in `notFound`, not in an error: the thread was real
/// when it was asked for, and the caller sees which of its messages are gone.
pub fn thread(client: &Client, session: &Session, account: &str, id: &str) -> Result<Value, Error> {
    choose_account(session, Some(account))?;
    let size = limit(session, "maxObjectsInGet")?;
    let batch = call(
        client,
        session,
        "Thread/get",
        json!({"accountId":account,"ids":[id]}),
    )?;
    let result = arguments(&batch, "Thread/get", account)?;
    let state = result.get("state").cloned().unwrap_or(Value::Null);
    let Some(email_ids) = thread_email_ids(&result, id)? else {
        return Ok(json!({"accountId":account,"threadId":id,"state":state,"found":false}));
    };
    let mut emails = Vec::new();
    let mut not_found = Vec::new();
    let mut get_states = Vec::new();
    for chunk in email_ids.chunks(size) {
        let batch = call(
            client,
            session,
            "Email/get",
            json!({"accountId":account,"ids":chunk,"properties":LIST_PROPERTIES}),
        )?;
        let get = arguments(&batch, "Email/get", account)?;
        validate_get(&get, chunk)?;
        get_states.push(get.get("state").cloned().unwrap_or(Value::Null));
        emails.extend(get["list"].as_array().into_iter().flatten().cloned());
        not_found.extend(get["notFound"].as_array().into_iter().flatten().cloned());
    }
    // Email/get answers in whatever order it likes; the thread's order is the one Thread/get gave.
    let position: std::collections::HashMap<&str, usize> = email_ids
        .iter()
        .enumerate()
        .map(|(i, id)| (id.as_str(), i))
        .collect();
    emails.sort_by_key(|email| position.get(email["id"].as_str().unwrap_or_default()).copied());
    Ok(json!({
        "accountId":account,"threadId":id,"state":state,"found":true,
        "emailIds":email_ids,"emails":emails,"notFound":not_found,"getStates":get_states
    }))
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
    fn incomplete_duplicate_and_unrequested_get_ids_are_rejected() {
        let ids = vec!["1".to_owned(), "2".to_owned()];
        assert!(validate_get(&json!({"list":[{"id":"1"}],"notFound":[]}), &ids).is_err());
        assert!(
            validate_get(
                &json!({"list":[{"id":"1"},{"id":"1"}],"notFound":["2"]}),
                &ids
            )
            .is_err()
        );
        assert!(validate_get(&json!({"list":[{"id":"3"}],"notFound":["2"]}), &ids).is_err());
        assert!(validate_get(&json!({"list":[{"id":"1"}],"notFound":["2"]}), &ids).is_ok());
        assert!(query_ids(&json!({"ids":["1","1"]}), 2).is_err());
    }

    #[test]
    fn invalid_call_and_byte_limits_fail_before_network() {
        let mut session: Session = serde_json::from_value(json!({"apiUrl":"http://127.0.0.1:1/never-contact","capabilities":{CORE_CAPABILITY:{"maxCallsInRequest":1,"maxSizeRequest":1}}})).unwrap();
        let client = Client::new(crate::Credentials {
            user: "u".into(),
            secret: "test-only".into(),
        });
        assert!(matches!(
            call(&client, &session, "Mailbox/get", json!({"accountId":"a"})),
            Err(Error::Shape(_))
        ));
        session.capabilities.get_mut(CORE_CAPABILITY).unwrap()["maxCallsInRequest"] = json!(0);
        assert!(matches!(
            call(&client, &session, "Mailbox/get", json!({"accountId":"a"})),
            Err(Error::Shape(_))
        ));
    }

    #[test]
    fn bounded_result_reference_matches_call_ids_not_response_positions() {
        use std::{
            io::{BufRead, BufReader, Read, Write},
            net::TcpListener,
            thread,
            time::Duration,
        };
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let api = format!("http://{}", listener.local_addr().unwrap());
        let server = thread::spawn(move || {
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
                if let Some(v) = line.to_ascii_lowercase().strip_prefix("content-length:") {
                    length = v.trim().parse().unwrap();
                }
            }
            let mut bytes = vec![0; length];
            reader.read_exact(&mut bytes).unwrap();
            let request: Value = serde_json::from_slice(&bytes).unwrap();
            assert_eq!(request["methodCalls"].as_array().unwrap().len(), 2);
            assert_eq!(request["methodCalls"][0][1]["limit"], 2);
            assert_eq!(
                request["methodCalls"][1][1]["#ids"],
                json!({"resultOf":"query","name":"Email/query","path":"/ids"})
            );
            let body=json!({"methodResponses":[
                ["Email/get",{"accountId":"a","state":"g","list":[{"id":"1"},{"id":"2"}],"notFound":[]},"get"],
                ["Email/query",{"accountId":"a","queryState":"q","position":0,"ids":["2","1"]},"query"]
            ]}).to_string();
            write!(stream,"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}",body.len(),body).unwrap();
        });
        let session:Session=serde_json::from_value(json!({"apiUrl":api,"capabilities":{CORE_CAPABILITY:{"maxCallsInRequest":2,"maxObjectsInGet":2,"maxSizeRequest":10000}}})).unwrap();
        let client = Client::new(crate::Credentials {
            user: "u".into(),
            secret: "test-only".into(),
        });
        let Window { query, gets, .. } =
            window(&client, &session, "a", "in", Rows::Messages).unwrap();
        assert_eq!(query["ids"], json!(["2", "1"]));
        assert_eq!(gets.len(), 1);
        server.join().unwrap();
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

    #[test]
    fn thread_get_answers_are_checked_against_the_thread_asked() {
        let ok = json!({"list":[{"id":"t","emailIds":["1","2"]}],"notFound":[]});
        assert_eq!(thread_email_ids(&ok, "t").unwrap(), Some(vec!["1".into(), "2".into()]));
        assert_eq!(thread_email_ids(&json!({"list":[],"notFound":["t"]}), "t").unwrap(), None);
        for bad in [
            json!({"list":[{"id":"other","emailIds":[]}],"notFound":[]}),
            json!({"list":[],"notFound":["other"]}),
            json!({"list":[{"id":"t","emailIds":["1"]}],"notFound":["t"]}),
            json!({"list":[{"id":"t","emailIds":["1","1"]}],"notFound":[]}),
            json!({"list":[{"id":"t"}],"notFound":[]}),
            json!({"list":[]}),
        ] {
            assert!(thread_email_ids(&bad, "t").is_err(), "{bad}");
        }
    }

    /// Serves one canned answer per request, handing each request body to `answer`.
    ///
    /// Waits a bounded time for each request. A client that stops early — truncating a thread, say —
    /// must fail the test, not hang it on a request that never comes.
    fn serve(
        requests: usize,
        answer: impl Fn(usize, &Value) -> Value + Send + 'static,
    ) -> (String, std::thread::JoinHandle<Vec<Value>>) {
        use std::{
            io::{BufRead, BufReader, Read, Write},
            net::TcpListener,
            time::Duration,
        };
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        let api = format!("http://{}", listener.local_addr().unwrap());
        listener.set_nonblocking(true).unwrap();
        let server = std::thread::spawn(move || {
            let mut seen = Vec::new();
            for step in 0..requests {
                let deadline = std::time::Instant::now() + Duration::from_secs(5);
                let mut stream = loop {
                    match listener.accept() {
                        Ok((stream, _)) => break stream,
                        Err(e) if e.kind() == std::io::ErrorKind::WouldBlock => {
                            assert!(
                                std::time::Instant::now() < deadline,
                                "request {step} of {requests} never came"
                            );
                            std::thread::sleep(Duration::from_millis(10));
                        }
                        Err(e) => panic!("{e}"),
                    }
                };
                stream.set_nonblocking(false).unwrap();
                stream.set_read_timeout(Some(Duration::from_secs(5))).unwrap();
                let mut reader = BufReader::new(&mut stream);
                let mut length = 0;
                loop {
                    let mut line = String::new();
                    reader.read_line(&mut line).unwrap();
                    assert!(!line.is_empty());
                    if line == "\r\n" {
                        break;
                    }
                    if let Some(v) = line.to_ascii_lowercase().strip_prefix("content-length:") {
                        length = v.trim().parse().unwrap();
                    }
                }
                let mut bytes = vec![0; length];
                reader.read_exact(&mut bytes).unwrap();
                let request: Value = serde_json::from_slice(&bytes).unwrap();
                let call = request["methodCalls"][0].clone();
                let body = json!({"methodResponses":[[call[0],answer(step,&call),"mail"]],"sessionState":"s"}).to_string();
                write!(stream,"HTTP/1.1 200 OK\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}",body.len(),body).unwrap();
                seen.push(call);
            }
            seen
        });
        (api, server)
    }

    #[test]
    fn a_thread_larger_than_one_get_is_fetched_whole_in_bounded_chunks() {
        // Five messages, two per Email/get: three gets, none cut off. The server answers out of
        // order and has lost message 4 since Thread/get named it.
        let (api, server) = serve(4, |step, call| match step {
            0 => {
                assert_eq!(call[0], "Thread/get");
                assert_eq!(call[1]["ids"], json!(["t"]));
                json!({"accountId":"a","state":"ts","list":[{"id":"t","emailIds":["1","2","3","4","5"]}],"notFound":[]})
            }
            _ => {
                assert_eq!(call[0], "Email/get");
                assert!(call[1].get("fetchTextBodyValues").is_none());
                let ids: Vec<&str> = call[1]["ids"].as_array().unwrap().iter().map(|v| v.as_str().unwrap()).collect();
                assert!(ids.len() <= 2);
                let list: Vec<Value> = ids.iter().rev().filter(|id| **id != "4").map(|id| json!({"id":id})).collect();
                let missing: Vec<&str> = ids.iter().copied().filter(|id| *id == "4").collect();
                json!({"accountId":"a","state":"es","list":list,"notFound":missing})
            }
        });
        let session: Session = serde_json::from_value(json!({"apiUrl":api,"capabilities":{CORE_CAPABILITY:{"maxObjectsInGet":2,"maxCallsInRequest":1,"maxSizeRequest":10000},CAPABILITY:{}},"accounts":{"a":{"accountCapabilities":{CAPABILITY:{}}}}})).unwrap();
        let client = Client::new(crate::Credentials { user: "u".into(), secret: "test-only".into() });
        let result = thread(&client, &session, "a", "t").unwrap();
        let calls = server.join().unwrap();
        let chunks: Vec<&Value> = calls[1..].iter().map(|c| &c[1]["ids"]).collect();
        assert_eq!(chunks, [&json!(["1", "2"]), &json!(["3", "4"]), &json!(["5"])]);
        let order: Vec<&str> = result["emails"].as_array().unwrap().iter().map(|e| e["id"].as_str().unwrap()).collect();
        assert_eq!(order, ["1", "2", "3", "5"]);
        assert_eq!(result["notFound"], json!(["4"]));
        assert_eq!(result["emailIds"].as_array().unwrap().len(), 5);
    }

    #[test]
    fn a_thread_nobody_can_see_is_not_found_and_fetches_nothing() {
        let (api, server) = serve(1, |_, _| json!({"accountId":"a","state":"ts","list":[],"notFound":["t"]}));
        let session: Session = serde_json::from_value(json!({"apiUrl":api,"capabilities":{CORE_CAPABILITY:{"maxObjectsInGet":2,"maxCallsInRequest":1,"maxSizeRequest":10000},CAPABILITY:{}},"accounts":{"a":{"accountCapabilities":{CAPABILITY:{}}}}})).unwrap();
        let client = Client::new(crate::Credentials { user: "u".into(), secret: "test-only".into() });
        let result = thread(&client, &session, "a", "t").unwrap();
        assert_eq!(server.join().unwrap().len(), 1);
        assert_eq!(result["found"], false);
    }
}
