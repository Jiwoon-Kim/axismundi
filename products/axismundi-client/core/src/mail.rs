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

/// The only Email properties that can change (RFC 8621 §4: an Email is immutable except for its
/// mailboxIds and keywords). Email/changes says only that a message was updated, never what — unlike
/// Mailbox/changes it has no updatedProperties — so these two are what an update can have touched,
/// and everything else the cache holds about the message stays true.
pub(crate) const MUTABLE_PROPERTIES: &[&str] = &["keywords", "mailboxIds"];

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

/// The size of the request `call` would send for this one method call, as maxSizeRequest counts it.
fn request_bytes(name: &str, args: &Value) -> Result<usize, Error> {
    Ok(serde_json::to_vec(&json!({"using":[CORE_CAPABILITY,CAPABILITY],"methodCalls":[[name,args,"mail"]]}))
        .map_err(|e| Error::Shape(e.to_string()))?
        .len())
}

/// Splits ids into requests that respect both a count limit and maxSizeRequest, in order. `build`
/// makes the arguments for one request. An id that does not fit even alone is returned apart, never
/// sent: no request can carry it to this server.
fn pack<'a>(
    session: &Session,
    ids: &[&'a str],
    count: usize,
    name: &str,
    build: impl Fn(&[&str]) -> Value,
) -> Result<(Vec<Vec<&'a str>>, Vec<&'a str>), Error> {
    let max = limit(session, "maxSizeRequest")?;
    let fits = |chunk: &[&str]| -> Result<bool, Error> { Ok(request_bytes(name, &build(chunk))? <= max) };
    let mut chunks: Vec<Vec<&'a str>> = Vec::new();
    let mut oversize = Vec::new();
    let mut current: Vec<&'a str> = Vec::new();
    for &id in ids {
        current.push(id);
        if current.len() <= count && fits(&current)? {
            continue;
        }
        current.pop();
        if !current.is_empty() {
            chunks.push(std::mem::take(&mut current));
        }
        if fits(&[id])? {
            current.push(id);
        } else {
            oversize.push(id);
        }
    }
    if !current.is_empty() {
        chunks.push(current);
    }
    Ok((chunks, oversize))
}

fn call(client: &Client, session: &Session, name: &str, args: Value) -> Result<Batch, Error> {
    limit(session, "maxCallsInRequest")?;
    let bytes = request_bytes(name, &args)?;
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

/// The keywords this client changes. Others on a message are left exactly as they are.
#[derive(Clone, Copy, Debug, PartialEq, Eq)]
pub enum Keyword {
    Seen,
    Flagged,
}

impl Keyword {
    pub fn as_str(self) -> &'static str {
        match self {
            Keyword::Seen => "$seen",
            Keyword::Flagged => "$flagged",
        }
    }
}

/// Whether a failed exchange may still have reached the server, so its answer is unknown: no
/// response, a 5xx, or a response that could not be read. A request-level JMAP problem or another
/// 4xx means the server processed nothing (RFC 8620 §3.6.1). A Shape error raised before sending
/// (maxSizeRequest) also lands here; reading back then shows the change absent, which is still right.
fn uncertain(error: &Error) -> bool {
    match error {
        Error::Transport(_) | Error::Shape(_) => true,
        Error::Status { status, .. } => *status >= 500,
        Error::Problem { .. } => false,
    }
}

/// How one message came out of a keyword change.
#[derive(Debug)]
pub enum KeywordOutcome {
    /// Applied: the server said so in `updated`, or a read after a lost answer shows it.
    Confirmed,
    /// The server refused it, by a `notUpdated` SetError or a method-level error for its request,
    /// and named the type. Nothing was applied (RFC 8620 §3.6.2, §5.3).
    Refused(String),
    /// The request failed before the server applied anything (a request-level problem or 4xx).
    Failed(Error),
    /// Not sent: even alone, the request for this id exceeds the server's maxSizeRequest.
    TooLarge,
    /// The answer was lost, and reading back shows the keyword as it was. Safe to send again.
    Unchanged,
    /// The answer was lost and reading back did not settle it: the read failed (`Some`), or the
    /// message did not come back with keywords (`None`, notFound included). Read before resending.
    Unknown(Option<Error>),
}

/// The outcome of one keyword change over many messages.
#[derive(Debug)]
pub struct KeywordResults {
    /// One per distinct id asked for, in the order first asked.
    pub outcomes: Vec<(String, KeywordOutcome)>,
    /// What was read after the write (id, keywords, mailboxIds), as one Email/get-shaped
    /// `{list, notFound}` for the caller to merge. It covers only what could be read.
    pub read_back: Value,
    /// Confirmed ids that could not be read back. Their writes stand; only the read is due again.
    pub not_read_back: Vec<String>,
}

/// Sets or clears one keyword on many messages of one Account.
///
/// Each message gets a patch of `keywords/<keyword>` alone (RFC 8621 §4.6), so keywords another
/// client set survive. The ids go out in `Email/set` requests that respect both `maxObjectsInSet`
/// and `maxSizeRequest` (see `pack`), and each message's result is read from `updated` /
/// `notUpdated` on its own, so one refusal does not fail the rest. Afterwards every confirmed
/// message, and every message whose answer was lost, is read back in `Email/get` requests packed
/// the same way against `maxObjectsInGet`. An id too large for any request is `TooLarge`, unsent.
///
/// A lost answer (no response, a 5xx, an unreadable response, or a message missing from both
/// `updated` and `notUpdated`) is never answered by sending again. It counts as applied only if
/// the message comes back with a `keywords` object showing the asked-for state; notFound or a
/// failed read leaves it Unknown. A confirmed write whose read fails stays Confirmed and is listed
/// in `not_read_back`. Only a precondition (unknown Account, missing Core limits) is an `Err`.
pub fn set_keywords(
    client: &Client,
    session: &Session,
    account: &str,
    ids: &[String],
    keyword: Keyword,
    on: bool,
) -> Result<KeywordResults, Error> {
    use std::collections::{BTreeMap, BTreeSet};
    choose_account(session, Some(account))?;
    let set_size = limit(session, "maxObjectsInSet")?;
    let get_size = limit(session, "maxObjectsInGet")?;
    let mut seen = BTreeSet::new();
    let ids: Vec<&String> = ids.iter().filter(|id| seen.insert(id.as_str())).collect();
    let patch = format!("keywords/{}", keyword.as_str());
    let value = if on { Value::Bool(true) } else { Value::Null };
    let set_args = |chunk: &[&str]| {
        let update: serde_json::Map<String, Value> =
            chunk.iter().map(|id| ((*id).to_owned(), json!({ patch.as_str(): value.clone() }))).collect();
        json!({"accountId":account,"update":update})
    };
    let get_args = |chunk: &[&str]| json!({"accountId":account,"ids":chunk,"properties":["id","keywords","mailboxIds"]});

    let mut outcomes: BTreeMap<&str, KeywordOutcome> = BTreeMap::new();
    let mut lost: BTreeSet<&str> = BTreeSet::new();
    let all: Vec<&str> = ids.iter().map(|id| id.as_str()).collect();
    let (set_chunks, oversize) = pack(session, &all, set_size, "Email/set", set_args)?;
    for id in oversize {
        outcomes.insert(id, KeywordOutcome::TooLarge);
    }
    for chunk in &set_chunks {
        let sent = call(client, session, "Email/set", set_args(chunk));
        let batch = match sent {
            Ok(batch) => batch,
            Err(error) if uncertain(&error) => {
                lost.extend(chunk.iter().copied());
                continue;
            }
            Err(error) => {
                // A request-level failure processed nothing; each id gets its own copy of the reason.
                for &id in chunk {
                    outcomes.insert(id, KeywordOutcome::Failed(copy_error(&error)));
                }
                continue;
            }
        };
        if let Some(refusal) = batch.errors().first() {
            let kind = refusal.error_type().unwrap_or("unknown").to_owned();
            for &id in chunk {
                outcomes.insert(id, KeywordOutcome::Refused(kind.clone()));
            }
            continue;
        }
        let Ok(result) = arguments(&batch, "Email/set", account) else {
            lost.extend(chunk.iter().copied());
            continue;
        };
        for &id in chunk {
            if let Some(refused) = result.get("notUpdated").and_then(|map| map.get(id)) {
                let kind = refused.get("type").and_then(Value::as_str).unwrap_or("unknown");
                outcomes.insert(id, KeywordOutcome::Refused(kind.to_owned()));
            } else if result.get("updated").and_then(|map| map.get(id)).is_some() {
                outcomes.insert(id, KeywordOutcome::Confirmed);
            } else {
                lost.insert(id);
            }
        }
    }

    let to_read: Vec<&str> = ids
        .iter()
        .map(|id| id.as_str())
        .filter(|id| lost.contains(id) || matches!(outcomes.get(id), Some(KeywordOutcome::Confirmed)))
        .collect();
    let mut list = Vec::new();
    let mut not_found = Vec::new();
    let mut read_errors: BTreeMap<&str, Error> = BTreeMap::new();
    let (get_chunks, unreadable) = pack(session, &to_read, get_size, "Email/get", get_args)?;
    for id in unreadable {
        read_errors.insert(id, Error::Shape("Email/get for this id exceeds maxSizeRequest".into()));
    }
    for chunk in &get_chunks {
        let wanted: Vec<String> = chunk.iter().map(|id| (*id).to_owned()).collect();
        let read = (|| {
            let batch = call(client, session, "Email/get", get_args(chunk))?;
            let result = arguments(&batch, "Email/get", account)?;
            validate_get(&result, &wanted)?;
            Ok::<Value, Error>(result)
        })();
        match read {
            Ok(result) => {
                list.extend(result["list"].as_array().into_iter().flatten().cloned());
                not_found.extend(result["notFound"].as_array().into_iter().flatten().cloned());
            }
            Err(error) => {
                for id in chunk {
                    read_errors.insert(id, copy_error(&error));
                }
            }
        }
    }

    for id in &lost {
        let outcome = if let Some(error) = read_errors.remove(id) {
            KeywordOutcome::Unknown(Some(error))
        } else {
            let keywords = list
                .iter()
                .find(|row| row.get("id").and_then(Value::as_str) == Some(*id))
                .and_then(|row| row.get("keywords"))
                .and_then(Value::as_object);
            match keywords {
                // Only a returned message with a keywords object counts; notFound proves nothing.
                None => KeywordOutcome::Unknown(None),
                Some(keywords) if (keywords.get(keyword.as_str()) == Some(&Value::Bool(true))) == on => {
                    KeywordOutcome::Confirmed
                }
                Some(_) => KeywordOutcome::Unchanged,
            }
        };
        outcomes.insert(id, outcome);
    }
    let not_read_back = ids
        .iter()
        .filter(|id| read_errors.contains_key(id.as_str()))
        .map(|id| (*id).clone())
        .collect();
    let outcomes = ids
        .iter()
        .map(|id| {
            let outcome = outcomes.remove(id.as_str()).expect("every id has an outcome");
            ((*id).clone(), outcome)
        })
        .collect();
    Ok(KeywordResults {
        outcomes,
        read_back: json!({"list": list, "notFound": not_found}),
        not_read_back,
    })
}

/// Reads the keywords (and mailboxes) of the given messages, nothing else, in `Email/get` requests of
/// at most `maxObjectsInGet`. This is how a caller settles an Unknown outcome: a read, never a
/// resend. Any failed request fails the whole check, so nothing half-checked is reported as checked.
pub fn read_keywords(client: &Client, session: &Session, account: &str, ids: &[String]) -> Result<Value, Error> {
    choose_account(session, Some(account))?;
    let args = |chunk: &[&str]| json!({"accountId":account,"ids":chunk,"properties":["id","keywords","mailboxIds"]});
    let all: Vec<&str> = ids.iter().map(String::as_str).collect();
    let (chunks, oversize) = pack(session, &all, limit(session, "maxObjectsInGet")?, "Email/get", args)?;
    if !oversize.is_empty() {
        return Err(Error::Shape("Email/get for an id exceeds maxSizeRequest".into()));
    }
    let mut list = Vec::new();
    let mut not_found = Vec::new();
    for chunk in &chunks {
        let batch = call(client, session, "Email/get", args(chunk))?;
        let result = arguments(&batch, "Email/get", account)?;
        let wanted: Vec<String> = chunk.iter().map(|id| (*id).to_owned()).collect();
        validate_get(&result, &wanted)?;
        list.extend(result["list"].as_array().into_iter().flatten().cloned());
        not_found.extend(result["notFound"].as_array().into_iter().flatten().cloned());
    }
    Ok(json!({"list": list, "notFound": not_found}))
}

/// `Error` holds no clonable payload by design elsewhere; per-id outcomes need their own copy.
fn copy_error(error: &Error) -> Error {
    match error {
        Error::Transport(message) => Error::Transport(message.clone()),
        Error::Problem { status, kind, detail } => Error::Problem { status: *status, kind: kind.clone(), detail: detail.clone() },
        Error::Status { status, body } => Error::Status { status: *status, body: body.clone() },
        Error::Shape(message) => Error::Shape(message.clone()),
    }
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
    json!({"accountId":account,"filter":{"inMailbox":mailbox},"sort":[{"property":SORT_PROPERTY,"isAscending":false}],"collapseThreads":rows.collapse_threads(),"position":0,"limit":count,"calculateTotal":true})
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
    /// The result count now, when the server calculated it.
    pub total: Option<u64>,
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
    let mut request = json!({"accountId":account,"sinceQueryState":since,"maxChanges":256,"calculateTotal":true});
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
        total: result.get("total").and_then(Value::as_u64),
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

    /// Answers each request in turn: `Some(body)` is a 200 with that JMAP response, `None` drops the
    /// connection after reading the request, as a lost answer looks to the client. Returns the
    /// method names each request carried.
    fn scripted(replies: Vec<Option<Value>>) -> (String, std::thread::JoinHandle<Vec<Vec<String>>>) {
        use std::io::{BufRead, BufReader, Read, Write};
        let listener = std::net::TcpListener::bind("127.0.0.1:0").unwrap();
        let api = format!("http://{}", listener.local_addr().unwrap());
        let server = std::thread::spawn(move || {
            let mut seen = Vec::new();
            for reply in replies {
                let (mut stream, _) = listener.accept().unwrap();
                stream.set_read_timeout(Some(std::time::Duration::from_secs(5))).unwrap();
                let mut reader = BufReader::new(&mut stream);
                let mut length = 0;
                loop {
                    let mut line = String::new();
                    reader.read_line(&mut line).unwrap();
                    if line == "\r\n" || line.is_empty() {
                        break;
                    }
                    if let Some(v) = line.to_ascii_lowercase().strip_prefix("content-length:") {
                        length = v.trim().parse().unwrap();
                    }
                }
                let mut bytes = vec![0; length];
                reader.read_exact(&mut bytes).unwrap();
                let request: Value = serde_json::from_slice(&bytes).unwrap();
                seen.push(request["methodCalls"].as_array().unwrap().iter().map(|call| call[0].as_str().unwrap().to_owned()).collect());
                if let Some(body) = reply {
                    let body = body.to_string();
                    write!(stream, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}", body.len(), body).unwrap();
                }
            }
            seen
        });
        (api, server)
    }

    fn keyword_session(api: &str) -> (Client, Session) {
        sized_session(api, 10000)
    }

    fn sized_session(api: &str, max_size: usize) -> (Client, Session) {
        let session: Session = serde_json::from_value(json!({"apiUrl":api,"capabilities":{CORE_CAPABILITY:{"maxCallsInRequest":1,"maxObjectsInGet":2,"maxObjectsInSet":2,"maxSizeRequest":max_size},CAPABILITY:{}},"accounts":{"a":{"accountCapabilities":{CAPABILITY:{}}}}})).unwrap();
        let client = Client::new(crate::Credentials { user: "u".into(), secret: "test-only".into() });
        (client, session)
    }

    fn ids(list: &[&str]) -> Vec<String> {
        list.iter().map(|id| (*id).to_owned()).collect()
    }

    fn set_reply(body: Value) -> Option<Value> {
        let mut args = json!({"accountId":"a","oldState":"s1","newState":"s2"});
        args.as_object_mut().unwrap().extend(body.as_object().unwrap().clone());
        Some(json!({"methodResponses":[["Email/set",args,"mail"]]}))
    }

    fn get_reply(rows: Value, not_found: Value) -> Option<Value> {
        Some(json!({"methodResponses":[["Email/get",{"accountId":"a","state":"s2","list":rows,"notFound":not_found},"mail"]]}))
    }

    fn read_back(keywords: Value) -> Option<Value> {
        get_reply(json!([{"id":"m1","keywords":keywords,"mailboxIds":{"in":true}}]), json!([]))
    }

    fn one(results: &KeywordResults) -> &KeywordOutcome {
        assert_eq!(results.outcomes.len(), 1);
        &results.outcomes[0].1
    }

    #[test]
    fn a_keyword_is_patched_alone_and_read_back() {
        let (api, server) = scripted(vec![set_reply(json!({"updated":{"m1":null}})), read_back(json!({"$seen":true,"$label":true}))]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Seen, true).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Confirmed));
        assert_eq!(results.read_back["list"][0]["keywords"]["$label"], true, "other keywords survive");
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }

    #[test]
    fn not_updated_inside_a_200_is_a_refusal_and_is_not_read_back() {
        let (api, server) = scripted(vec![set_reply(json!({"notUpdated":{"m1":{"type":"forbidden"}}}))]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Flagged, true).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Refused(kind) if kind == "forbidden"));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"]], "nothing is read back after a refusal");
    }

    /// The answer is lost and the server did apply it: the read-back confirms, nothing is resent.
    #[test]
    fn a_lost_answer_is_confirmed_by_reading_back_not_by_resending() {
        let (api, server) = scripted(vec![None, read_back(json!({"$flagged":true}))]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Flagged, true).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Confirmed));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }

    /// The answer is lost and the server did not apply it: known unchanged, still without resending.
    #[test]
    fn a_lost_answer_the_server_does_not_show_is_unchanged() {
        let (api, server) = scripted(vec![None, read_back(json!({}))]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Flagged, true).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Unchanged));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }

    /// The server confirmed the write and only the read-back was lost: the write stands, and the
    /// caller learns that only the read is due.
    #[test]
    fn a_confirmed_write_survives_a_failed_read_back() {
        let (api, server) = scripted(vec![set_reply(json!({"updated":{"m1":null}})), None]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Flagged, true).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Confirmed));
        assert_eq!(results.not_read_back, ["m1"]);
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]], "the write is not resent");
    }

    /// Clearing with a lost answer, then the message is gone: an absent keyword on a missing message
    /// is not proof the clear applied.
    #[test]
    fn a_lost_clear_is_not_confirmed_by_not_found() {
        let (api, server) = scripted(vec![None, get_reply(json!([]), json!(["m1"]))]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Flagged, false).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Unknown(None)));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }

    /// A lost answer and then a failed read: unknown, and still nothing resent.
    #[test]
    fn a_lost_answer_and_a_failed_read_is_unknown() {
        let (api, server) = scripted(vec![None, None]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Seen, false).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Unknown(Some(_))));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }

    /// Clearing with a lost answer, and the message comes back without the keyword: confirmed.
    #[test]
    fn a_lost_clear_is_confirmed_by_the_keyword_being_absent() {
        let (api, server) = scripted(vec![None, read_back(json!({"$label":true}))]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1"]), Keyword::Flagged, false).unwrap();
        assert!(matches!(one(&results), KeywordOutcome::Confirmed));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }

    /// Five ids, one repeated: four distinct ones go out in maxObjectsInSet (2) chunks, each id
    /// settles on its own, and the read-back follows maxObjectsInGet (2).
    #[test]
    fn many_ids_are_chunked_and_settle_one_by_one() {
        let (api, server) = scripted(vec![
            set_reply(json!({"updated":{"m1":null},"notUpdated":{"m2":{"type":"notFound"}}})),
            None,
            get_reply(json!([{"id":"m1","keywords":{"$seen":true},"mailboxIds":{"in":true}},{"id":"m3","keywords":{"$seen":true},"mailboxIds":{"in":true}}]), json!([])),
            get_reply(json!([{"id":"m4","keywords":{},"mailboxIds":{"in":true}}]), json!([])),
        ]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1", "m2", "m3", "m1", "m4"]), Keyword::Seen, true).unwrap();
        let order: Vec<&str> = results.outcomes.iter().map(|(id, _)| id.as_str()).collect();
        assert_eq!(order, ["m1", "m2", "m3", "m4"], "first-asked order, duplicates once");
        assert!(matches!(results.outcomes[0].1, KeywordOutcome::Confirmed));
        assert!(matches!(&results.outcomes[1].1, KeywordOutcome::Refused(kind) if kind == "notFound"));
        assert!(matches!(results.outcomes[2].1, KeywordOutcome::Confirmed), "lost, then shown");
        assert!(matches!(results.outcomes[3].1, KeywordOutcome::Unchanged), "lost, then not shown");
        assert!(results.not_read_back.is_empty());
        assert_eq!(
            server.join().unwrap(),
            vec![vec!["Email/set"], vec!["Email/set"], vec!["Email/get"], vec!["Email/get"]],
            "each id is sent once"
        );
    }

    /// Two ids fit maxObjectsInSet but not maxSizeRequest: they go out one per request instead of
    /// failing together, and the read-back splits the same way.
    #[test]
    fn requests_split_by_bytes_as_well_as_by_count() {
        let set = |ids: &[&str]| {
            let update: serde_json::Map<String, Value> = ids.iter().map(|id| ((*id).to_owned(), json!({"keywords/$seen": true}))).collect();
            request_bytes("Email/set", &json!({"accountId":"a","update":update})).unwrap()
        };
        let get = |ids: &[&str]| request_bytes("Email/get", &json!({"accountId":"a","ids":ids,"properties":["id","keywords","mailboxIds"]})).unwrap();
        let max = set(&["m1"]).max(get(&["m1"]));
        assert!(max < set(&["m1", "m2"]).min(get(&["m1", "m2"])), "the limit admits one id per request, not two");
        let (api, server) = scripted(vec![
            set_reply(json!({"updated":{"m1":null}})),
            set_reply(json!({"updated":{"m2":null}})),
            get_reply(json!([{"id":"m1","keywords":{"$seen":true},"mailboxIds":{"in":true}}]), json!([])),
            get_reply(json!([{"id":"m2","keywords":{"$seen":true},"mailboxIds":{"in":true}}]), json!([])),
        ]);
        let (client, session) = sized_session(&api, max);
        let results = set_keywords(&client, &session, "a", &ids(&["m1", "m2"]), Keyword::Seen, true).unwrap();
        assert!(results.outcomes.iter().all(|(_, outcome)| matches!(outcome, KeywordOutcome::Confirmed)));
        assert!(results.not_read_back.is_empty());
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/set"], vec!["Email/get"], vec!["Email/get"]]);
    }

    /// An id whose request is too large even alone is not sent and is known not applied; the rest go.
    #[test]
    fn an_id_too_large_for_any_request_is_not_sent() {
        let long = "x".repeat(400);
        let max = request_bytes("Email/get", &json!({"accountId":"a","ids":["m1"],"properties":["id","keywords","mailboxIds"]})).unwrap() + 40;
        let (api, server) = scripted(vec![
            set_reply(json!({"updated":{"m1":null}})),
            get_reply(json!([{"id":"m1","keywords":{"$seen":true},"mailboxIds":{"in":true}}]), json!([])),
        ]);
        let (client, session) = sized_session(&api, max);
        let results = set_keywords(&client, &session, "a", &[long.clone(), "m1".into()], Keyword::Seen, true).unwrap();
        assert!(matches!(results.outcomes[0].1, KeywordOutcome::TooLarge));
        assert!(matches!(results.outcomes[1].1, KeywordOutcome::Confirmed));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }

    /// A method-level error refuses its whole request, and nothing in it is read back.
    #[test]
    fn a_method_error_refuses_its_chunk() {
        let (api, server) = scripted(vec![Some(json!({"methodResponses":[["error",{"type":"stateMismatch"},"mail"]]}))]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1", "m2"]), Keyword::Seen, true).unwrap();
        assert!(results.outcomes.iter().all(|(_, outcome)| matches!(outcome, KeywordOutcome::Refused(kind) if kind == "stateMismatch")));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"]]);
    }

    /// A check is a read only, chunked by maxObjectsInGet, and any failed chunk fails it.
    #[test]
    fn a_keyword_check_reads_and_never_writes() {
        let (api, server) = scripted(vec![
            get_reply(json!([{"id":"m1","keywords":{},"mailboxIds":{"in":true}}]), json!(["m2"])),
            None,
        ]);
        let (client, session) = keyword_session(&api);
        let error = read_keywords(&client, &session, "a", &ids(&["m1", "m2", "m3"])).unwrap_err();
        assert!(matches!(error, Error::Transport(_)), "{error:?}");
        assert_eq!(server.join().unwrap(), vec![vec!["Email/get"], vec!["Email/get"]]);
    }

    /// An id missing from both updated and notUpdated is a lost answer for that id alone.
    #[test]
    fn an_unreported_id_is_read_back_not_trusted() {
        let (api, server) = scripted(vec![set_reply(json!({"updated":{"m2":null}})), get_reply(
            json!([{"id":"m1","keywords":{},"mailboxIds":{"in":true}},{"id":"m2","keywords":{"$flagged":true},"mailboxIds":{"in":true}}]),
            json!([]),
        )]);
        let (client, session) = keyword_session(&api);
        let results = set_keywords(&client, &session, "a", &ids(&["m1", "m2"]), Keyword::Flagged, true).unwrap();
        assert!(matches!(results.outcomes[0].1, KeywordOutcome::Unchanged), "unreported, and the read shows it did not apply");
        assert!(matches!(results.outcomes[1].1, KeywordOutcome::Confirmed));
        assert_eq!(server.join().unwrap(), vec![vec!["Email/set"], vec!["Email/get"]]);
    }
}
