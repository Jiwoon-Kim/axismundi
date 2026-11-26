//! Mail commands: thin wrappers over `axismundi_client_core::jmap::mail::cache`. The GUI names an
//! Account, a mailbox or an Email; it never builds a JMAP request or sees SQL.
use std::collections::{HashMap, HashSet};
use std::path::{Path, PathBuf};
use std::sync::{Arc, Mutex};

use axismundi_client_core::{
    Error,
    jmap::{
        Session, blob, blob_ledger::{Access, Ledger, Reference},
        mail::{Rows, cache, compose, mailbox, search},
    },
};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};

use crate::connection::{Active, Connection, failure};

/// The JMAP Session a connection signed in with, and that login's Mail cache.
pub struct Mail {
    pub session: Session,
    store: Mutex<cache::Store>,
    compose: Mutex<ComposeState>,
    ledger_root: PathBuf,
    ledger_origin: String,
    ledger_login: String,
}

#[derive(Default)]
struct ComposeState {
    identities: HashMap<String, (String, Vec<compose::Identity>)>,
    uncertain: HashSet<(String, String)>,
}

impl Mail {
    /// One cache file per (API URL, login). The store also binds itself to that pair and refuses a
    /// file that belongs to another, so a hand-copied file cannot leak one login's mail to another.
    pub fn open(data_dir: &Path, session: Session, username: &str) -> Result<Self, String> {
        let profile = json!({"apiUrl": session.api_url, "username": username}).to_string();
        let dir = data_dir.join("mail");
        std::fs::create_dir_all(&dir).map_err(|_| "Mail cache: the data folder is not writable.")?;
        let store = cache::Store::open(cache_file(&dir, &profile), &profile)
            .map_err(|error| format!("Mail cache: {error}"))?;
        let ledger_origin = url::Url::parse(&session.api_url).map_err(|_| "Invalid Mail API URL.")?
            .origin().ascii_serialization();
        Ok(Self {
            session,
            store: Mutex::new(store),
            compose: Mutex::default(),
            ledger_root: data_dir.join("mail-blobs"),
            ledger_origin,
            ledger_login: profile,
        })
    }

    fn prune_attachment_refs(&self, store: &cache::Store) -> Result<(), String> {
        let mut ledger = Ledger::open(&self.ledger_root, &self.ledger_origin, CACHE_LIMIT)?;
        ledger.retain(&self.ledger_login, |account, email, blob| {
            self.session.accounts.contains_key(account) && store.email(account, email).is_ok_and(|entry|
                entry["available"] == true && entry["document"]["attachments"].as_array()
                    .is_some_and(|parts| parts.iter().any(|part| part["blobId"] == blob)))
        })
    }

    pub(crate) fn forget_attachment_refs(&self) -> Result<(), String> {
        let _held = self.store.lock().map_err(|_| "Mail cache is unavailable.")?;
        Ledger::open(&self.ledger_root, &self.ledger_origin, CACHE_LIMIT)?
            .retain(&self.ledger_login, |_, _, _| false)
    }
}

/// Hex keeps the file name reversible and free of the URL's separators.
fn cache_file(dir: &Path, profile: &str) -> PathBuf {
    let name: String = profile.bytes().map(|byte| format!("{byte:02x}")).collect();
    dir.join(format!("{name}.sqlite"))
}

/// What a listing command returns to the GUI. The cache keeps the raw JMAP documents with their
/// coverage; the GUI gets only fields it shows, named here once. Labels for missing values
/// ("no subject") belong to the GUI, so absent values stay `null`.
#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct ListView {
    account_id: String,
    mailbox_id: String,
    mailboxes: Vec<MailboxRow>,
    /// In the server's query order.
    rows: Vec<MailRow>,
    /// `null` when the server did not calculate a total; never guessed from `rows`.
    total: Option<u64>,
    has_more: Option<bool>,
    query_stale: bool,
    partial_cache: bool,
    may_create_top_level_mailbox: Option<bool>,
    max_mailbox_depth: Option<u64>,
    max_size_mailbox_name: Option<u64>,
    #[serde(skip_serializing_if = "Option::is_none")]
    search: Option<SearchPage>,
}

#[derive(Debug, Deserialize, Serialize)]
struct SearchPage {
    state: String,
    position: usize,
    snippets: std::collections::BTreeMap<String, SearchSnippet>,
}

#[derive(Debug, Deserialize, Serialize)]
struct SearchSnippet {
    subject: Option<String>,
    preview: Option<String>,
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct MailboxRow {
    id: String,
    name: String,
    role: Option<String>,
    parent_id: Option<String>,
    sort_order: Option<u64>,
    total_emails: Option<u64>,
    unread_emails: Option<u64>,
    /// `myRights.mayAddItems`: a move may end here. `null` when the server did not say.
    may_add_items: Option<bool>,
    /// `myRights.mayRemoveItems`: a move may start here.
    may_remove_items: Option<bool>,
    /// `myRights.maySetSeen`: `$seen` may change on a message here. A message in several mailboxes
    /// needs it in all of them (RFC 8621 §2).
    may_set_seen: Option<bool>,
    may_create_child: Option<bool>,
    may_rename: Option<bool>,
    may_delete: Option<bool>,
}

#[derive(Debug, Serialize)]
#[serde(tag = "kind", rename_all = "camelCase", rename_all_fields = "camelCase")]
pub enum MailRow {
    Message {
        id: String,
        thread_id: Option<String>,
        sender_name: Option<String>,
        sender_email: Option<String>,
        subject: Option<String>,
        preview: Option<String>,
        received_at: Option<String>,
        /// `null` when keywords were never fetched or the cached copy is stale: unknown is not
        /// unread, and an old `$seen` is not today's read state.
        unread: Option<bool>,
        /// `null` on the same terms as `unread`.
        flagged: Option<bool>,
        has_attachment: Option<bool>,
        /// The mailboxes the message is in, or `null` when not fetched or stale. In a shared Account
        /// only the mailboxes this login can see.
        mailbox_ids: Option<Vec<String>>,
    },
    /// Not in the cache, and not a message with an empty subject.
    Unavailable { id: String, reason: String },
}

fn text(value: &Value, pointer: &str) -> Option<String> {
    value.pointer(pointer).and_then(Value::as_str).map(str::to_owned)
}

fn mail_row(entry: &Value) -> Result<MailRow, String> {
    let id = text(entry, "/id").ok_or("Mail: a listing entry has no id")?;
    if entry["available"] != Value::Bool(true) {
        let reason = text(entry, "/reason").unwrap_or_else(|| "notCached".into());
        return Ok(MailRow::Unavailable { id, reason });
    }
    let document = &entry["document"];
    let keywords = known_keywords(entry);
    Ok(MailRow::Message {
        id,
        thread_id: text(document, "/threadId"),
        sender_name: text(document, "/from/0/name").filter(|name| !name.is_empty()),
        sender_email: text(document, "/from/0/email"),
        subject: text(document, "/subject"),
        preview: text(document, "/preview"),
        received_at: text(document, "/receivedAt"),
        unread: keywords.map(|keywords| !has(keywords, "$seen")),
        flagged: keywords.map(|keywords| has(keywords, "$flagged")),
        has_attachment: document.get("hasAttachment").and_then(Value::as_bool),
        mailbox_ids: known(entry, "mailboxIds").map(|boxes| boxes.keys().cloned().collect()),
    })
}

/// A cached message's keywords, when they were fetched and are not stale. Unknown is not "off":
/// an old `$seen` is not today's read state.
fn known_keywords(entry: &Value) -> Option<&serde_json::Map<String, Value>> {
    known(entry, "keywords")
}

/// One of a cached message's mutable properties, when it was fetched and is not stale.
fn known<'a>(entry: &'a Value, property: &str) -> Option<&'a serde_json::Map<String, Value>> {
    let stale = entry["staleProperties"]
        .as_array()
        .is_some_and(|stale| stale.iter().any(|name| name == property));
    entry["document"].get(property).and_then(Value::as_object).filter(|_| !stale)
}

fn has(keywords: &serde_json::Map<String, Value>, keyword: &str) -> bool {
    keywords.get(keyword) == Some(&Value::Bool(true))
}

fn mailbox_row(mailbox: &Value) -> Result<MailboxRow, String> {
    Ok(MailboxRow {
        id: text(mailbox, "/id").ok_or("Mail: a mailbox has no id")?,
        name: text(mailbox, "/name").unwrap_or_default(),
        role: text(mailbox, "/role"),
        parent_id: text(mailbox, "/parentId"),
        sort_order: mailbox.get("sortOrder").and_then(Value::as_u64),
        total_emails: mailbox.get("totalEmails").and_then(Value::as_u64),
        unread_emails: mailbox.get("unreadEmails").and_then(Value::as_u64),
        may_add_items: mailbox.pointer("/myRights/mayAddItems").and_then(Value::as_bool),
        may_remove_items: mailbox.pointer("/myRights/mayRemoveItems").and_then(Value::as_bool),
        may_set_seen: mailbox.pointer("/myRights/maySetSeen").and_then(Value::as_bool),
        may_create_child: mailbox.pointer("/myRights/mayCreateChild").and_then(Value::as_bool),
        may_rename: mailbox.pointer("/myRights/mayRename").and_then(Value::as_bool),
        may_delete: mailbox.pointer("/myRights/mayDelete").and_then(Value::as_bool),
    })
}

/// `Store::view` → the GUI listing.
fn list_view(view: &Value) -> Result<ListView, String> {
    let list = |key: &str| view[key].as_array().ok_or(format!("Mail: the view has no {key}"));
    Ok(ListView {
        account_id: text(view, "/accountId").ok_or("Mail: the view has no accountId")?,
        mailbox_id: text(view, "/mailboxId").ok_or("Mail: the view has no mailboxId")?,
        mailboxes: list("mailboxes")?.iter().map(mailbox_row).collect::<Result<_, _>>()?,
        rows: list("emails")?.iter().map(mail_row).collect::<Result<_, _>>()?,
        total: view["total"].as_u64(),
        has_more: view["hasMore"].as_bool(),
        query_stale: view["queryStale"].as_bool().unwrap_or(true),
        partial_cache: view["partialCache"].as_bool().unwrap_or(true),
        may_create_top_level_mailbox: None,
        max_mailbox_depth: None,
        max_size_mailbox_name: None,
        search: view.get("search").map(|page| serde_json::from_value(page.clone()))
            .transpose().map_err(|_| "Invalid search result")?,
    })
}

fn account_list_view(view: &Value, session: &Session, account: &str) -> Result<ListView, String> {
    let mut view = list_view(view)?;
    if let Some(mail) = session.accounts.get(account).and_then(|a| a.account_capabilities.get(axismundi_client_core::jmap::mail::CAPABILITY)) {
        view.may_create_top_level_mailbox = mail["mayCreateTopLevelMailbox"].as_bool();
        view.max_mailbox_depth = mail["maxMailboxDepth"].as_u64();
        view.max_size_mailbox_name = mail["maxSizeMailboxName"].as_u64();
    }
    Ok(view)
}

#[derive(Deserialize)]
#[serde(tag = "kind", rename_all = "camelCase")]
pub enum MailboxWrite {
    Create { parent: Option<String>, name: String, role: Option<String> },
    Rename { id: String, name: String },
    Destroy { id: String },
}

#[derive(Serialize)]
#[serde(tag = "status", rename_all = "camelCase")]
pub enum MailboxWriteResult {
    Confirmed { id: String },
    /// Our wording, ending with the SetError type the server sent (as the keyword results do). The
    /// server's own description never reaches the webview.
    Refused { message: String, properties: Vec<&'static str> },
    Unchanged,
    Unknown,
}

fn mailbox_write_result(result: mailbox::Outcome) -> MailboxWriteResult {
    match result {
        mailbox::Outcome::Confirmed(id) => MailboxWriteResult::Confirmed { id },
        mailbox::Outcome::Unknown => MailboxWriteResult::Unknown,
        mailbox::Outcome::Unchanged => MailboxWriteResult::Unchanged,
        mailbox::Outcome::Refused { kind, properties } => {
            let properties: Vec<_> = ["name", "parentId", "role"].into_iter()
                .filter(|field| properties.iter().any(|name| name == field)).collect();
            let message = match (kind.as_str(), properties.as_slice()) {
                ("mailboxHasEmail", _) => "Move the messages out before deleting this mailbox.",
                ("mailboxHasChild", _) => "Remove the child mailboxes before deleting this mailbox.",
                // forbidden covers both missing rights and server policy: Stalwart refuses to delete
                // Inbox, Trash and Junk with forbidden while reporting mayDelete true for them (measured
                // 2026-11-24, HTTP 200, notDestroyed). So the wording must fit both.
                ("forbidden" | "accountReadOnly", _) => "The server does not allow this change.",
                ("invalidProperties", p) if p.contains(&"role") => "This role is already in use or is not supported by the server.",
                ("invalidProperties", p) if p.contains(&"name") => "The server did not accept this mailbox name.",
                ("invalidProperties", p) if p.contains(&"parentId") => "The server did not accept this parent mailbox.",
                _ => "The server refused the mailbox change.",
            };
            // The type is server-sent too: show it only when it looks like a SetError name.
            let token = kind.len() <= 40 && !kind.is_empty() && kind.chars().all(|c| c.is_ascii_alphanumeric());
            let message = if token { format!("{message} ({kind})") } else { message.to_owned() };
            MailboxWriteResult::Refused { message, properties }
        }
    }
}

#[tauri::command]
pub async fn mail_write_mailbox(
    connection_id: u32,
    account_id: String,
    change: MailboxWrite,
    state: tauri::State<'_, Connection>,
) -> Result<MailboxWriteResult, String> {
    with_mail(&state, connection_id, move |active, mail, _| {
        let change = match &change {
            MailboxWrite::Create { parent, name, role } =>
                mailbox::Change::Create { parent: parent.as_deref(), name, role: role.as_deref() },
            MailboxWrite::Rename { id, name } => mailbox::Change::Rename { id, name },
            MailboxWrite::Destroy { id } => mailbox::Change::Destroy { id },
        };
        mailbox::write(&active.client, &mail.session, &account_id, change)
            .map(mailbox_write_result).map_err(|_| "Could not change the mailbox.".into())
    }).await
}

/// What mail_read returns: one message for the reading pane.
#[derive(Debug, Serialize)]
#[serde(tag = "kind", rename_all = "camelCase", rename_all_fields = "camelCase")]
pub enum DetailView {
    Message {
        id: String,
        thread_id: Option<String>,
        subject: Option<String>,
        from: Vec<Address>,
        to: Vec<Address>,
        cc: Vec<Address>,
        received_at: Option<String>,
        sent_at: Option<String>,
        /// `null` when keywords are unknown, as in the listing.
        unread: Option<bool>,
        flagged: Option<bool>,
        /// As in the listing: the mailboxes it is in, or `null` when unknown or stale.
        mailbox_ids: Option<Vec<String>>,
        body: Body,
        attachments: Vec<Attachment>,
    },
    Unavailable { id: String, reason: String },
}

#[derive(Debug, Serialize)]
pub struct Address {
    name: Option<String>,
    email: Option<String>,
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Body {
    parts: Vec<BodyPart>,
    not_fetched: bool,
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct BodyPart {
    kind: &'static str,
    part_id: String,
    value: String,
    truncated: bool,
    encoding_problem: bool,
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Attachment {
    part_id: Option<String>,
    name: Option<String>,
    r#type: Option<String>,
    size: Option<u64>,
    cid: Option<String>,
}

fn addresses(document: &Value, key: &str) -> Vec<Address> {
    document[key]
        .as_array()
        .map(|list| {
            list.iter()
                .map(|address| Address {
                    name: text(address, "/name").filter(|name| !name.is_empty()),
                    email: text(address, "/email"),
                })
                .collect()
        })
        .unwrap_or_default()
}

fn body(document: &Value) -> Body {
    let mut body = Body { parts: Vec::new(), not_fetched: false };
    for (field, mime, kind) in [("textBody", "text/plain", "text"), ("htmlBody", "text/html", "html")] {
        for part in document[field].as_array().into_iter().flatten() {
            if !part["type"].as_str().is_some_and(|value| value.eq_ignore_ascii_case(mime)) { continue; }
            let Some(id) = part["partId"].as_str() else { body.not_fetched = true; continue };
            let value = &document["bodyValues"][id];
            let Some(content) = value["value"].as_str() else { body.not_fetched = true; continue };
            body.parts.push(BodyPart {
                kind, part_id: id.into(), value: content.into(),
                truncated: value["isTruncated"] == true, encoding_problem: value["isEncodingProblem"] == true,
            });
        }
    }
    body
}

/// `Store::email` after a detail fetch → the reading pane.
fn detail_view(entry: &Value) -> Result<DetailView, String> {
    let id = text(entry, "/id").ok_or("Mail: the message has no id")?;
    if entry["available"] != Value::Bool(true) {
        let reason = text(entry, "/reason").unwrap_or_else(|| "notCached".into());
        return Ok(DetailView::Unavailable { id, reason });
    }
    let document = &entry["document"];
    Ok(DetailView::Message {
        id,
        thread_id: text(document, "/threadId"),
        subject: text(document, "/subject"),
        from: addresses(document, "from"),
        to: addresses(document, "to"),
        cc: addresses(document, "cc"),
        received_at: text(document, "/receivedAt"),
        sent_at: text(document, "/sentAt"),
        unread: known_keywords(entry).map(|keywords| !has(keywords, "$seen")),
        flagged: known_keywords(entry).map(|keywords| has(keywords, "$flagged")),
        mailbox_ids: known(entry, "mailboxIds").map(|boxes| boxes.keys().cloned().collect()),
        body: body(document),
        attachments: document["attachments"]
            .as_array()
            .map(|list| {
                list.iter()
                    .map(|part| Attachment {
                        part_id: text(part, "/partId"),
                        name: text(part, "/name"),
                        r#type: text(part, "/type"),
                        size: part["size"].as_u64(),
                        cid: text(part, "/cid"),
                    })
                    .collect()
            })
            .unwrap_or_default(),
    })
}

/// What mail_thread returns. Messages carry listing properties only; a body is a mail_read.
#[derive(Debug, Serialize)]
#[serde(tag = "kind", rename_all = "camelCase", rename_all_fields = "camelCase")]
pub enum ThreadView {
    Thread {
        thread_id: String,
        /// In the thread's own order (`Thread.emailIds`), not the order Email/get answered in.
        messages: Vec<MailRow>,
    },
    NotFound { thread_id: String },
}

fn thread_view(result: &Value) -> Result<ThreadView, String> {
    let thread_id = text(result, "/threadId").ok_or("Mail: the thread has no id")?;
    if result["found"] != Value::Bool(true) {
        return Ok(ThreadView::NotFound { thread_id });
    }
    let ids = result["emailIds"].as_array().ok_or("Mail: the thread has no emailIds")?;
    let fetched = result["emails"].as_array().map(Vec::as_slice).unwrap_or_default();
    let messages = ids
        .iter()
        .map(|id| {
            let id = id.as_str().ok_or("Mail: a thread member id is not a string")?;
            // Fresh from the server, so every listing property is current.
            let entry = match fetched.iter().find(|email| email["id"] == id) {
                Some(document) => json!({"id": id, "available": true, "document": document, "staleProperties": []}),
                None => json!({"id": id, "available": false, "reason": "notFound"}),
            };
            mail_row(&entry)
        })
        .collect::<Result<_, _>>()?;
    Ok(ThreadView::Thread { thread_id, messages })
}

fn rows(value: &str) -> Result<Rows, String> {
    match value {
        "messages" => Ok(Rows::Messages),
        "threads" => Ok(Rows::Threads),
        _ => Err("rows takes messages or threads".into()),
    }
}

/// Remote failures keep only their status, as everywhere in this app; local cache errors are ours.
fn explain(error: Box<dyn std::error::Error>) -> String {
    match error.downcast_ref::<Error>() {
        Some(remote) => failure("Mail", remote),
        None => format!("Mail: {error}"),
    }
}

/// Runs `work` against the connection's Mail state off the async runtime, and drops the answer if
/// the user signed out or in again meanwhile.
async fn with_mail<T, F>(state: &Connection, connection_id: u32, work: F) -> Result<T, String>
where
    T: Send + 'static,
    F: FnOnce(&Active, &Mail, &mut cache::Store) -> Result<T, String> + Send + 'static,
{
    let active: Arc<Active> = state.current(connection_id)?;
    let value = tauri::async_runtime::spawn_blocking(move || {
        let mail = active.mail.as_ref().ok_or("Mail is not available on this connection")?;
        let mut store = mail.store.lock().map_err(|_| "Mail cache is unavailable")?;
        work(&active, mail, &mut store)
    })
    .await
    .map_err(|_| "Mail request did not complete")??;
    state.current(connection_id)?;
    Ok(value)
}

#[derive(Serialize)]
pub struct ComposeIdentity {
    id: String,
    name: String,
    email: String,
}

#[tauri::command]
pub async fn mail_identities(
    connection_id: u32,
    account_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<Vec<ComposeIdentity>, String> {
    with_mail(&state, connection_id, move |active, mail, _| {
        let (identity_state, identities) = compose::identities(&active.client, &mail.session, &account_id)
            .map_err(|error| failure("Mail identities", &error))?;
        let choices = identities.iter().map(|identity| ComposeIdentity {
            id: identity.id.clone(), name: identity.name.clone(), email: identity.email.clone(),
        }).collect();
        mail.compose.lock().map_err(|_| "Compose state is unavailable")?
            .identities.insert(account_id, (identity_state, identities));
        Ok(choices)
    }).await
}

#[tauri::command]
pub async fn mail_compose_source(
    connection_id: u32,
    account_id: String,
    email_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<compose::ComposeSource, String> {
    with_mail(&state, connection_id, move |active, mail, _| {
        compose::source(&active.client, &mail.session, &account_id, &email_id)
            .map_err(|error| match error {
                compose::SourceError::UnsupportedDraft => "unsupportedDraft".into(),
                compose::SourceError::Request(error) => failure("Mail source", &error),
            })
    }).await
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct ComposeResult {
    outcome: &'static str,
    draft_id: Option<String>,
    pending_cleanup: Vec<String>,
    reason: Option<&'static str>,
}

fn compose_reason(kind: &str) -> &'static str {
    match kind {
        "forbiddenFrom" => "The selected identity cannot send from this address.",
        "noRecipients" => "Add at least one recipient.",
        "invalidRecipients" => "The server rejected a recipient address.",
        "tooManyRecipients" => "The server allows fewer recipients.",
        "overQuota" => "The account is over quota.",
        _ => "The server did not allow this change.",
    }
}

#[tauri::command]
pub async fn mail_compose_discard(
    connection_id: u32,
    account_id: String,
    ids: Vec<String>,
    state: tauri::State<'_, Connection>,
) -> Result<bool, String> {
    with_mail(&state, connection_id, move |active, mail, store| {
        let compose_state = mail.compose.lock().map_err(|_| "Compose state is unavailable")?;
        if ids.iter().any(|id| compose_state.uncertain.contains(&(account_id.clone(), format!("draft:{id}")))) {
            return Err("Cannot discard a draft whose send could not be confirmed.".into());
        }
        let view = store.view(&account_id, None, Rows::Messages).map_err(explain)?;
        let drafts = view["mailboxes"].as_array().into_iter().flatten()
            .find(|row| row["role"] == "drafts").and_then(|row| row["id"].as_str())
            .ok_or("The account has no Drafts mailbox.")?;
        Ok(compose::discard_drafts(&active.client, &mail.session, &account_id, drafts, ids))
    }).await
}

#[tauri::command]
pub async fn mail_compose_save(
    connection_id: u32,
    account_id: String,
    compose_id: String,
    draft: compose::Draft,
    previous: Vec<String>,
    send: bool,
    state: tauri::State<'_, Connection>,
) -> Result<ComposeResult, String> {
    with_mail(&state, connection_id, move |active, mail, store| {
        let mut compose_state = mail.compose.lock().map_err(|_| "Compose state is unavailable")?;
        if compose_state.uncertain.contains(&(account_id.clone(), compose_id.clone()))
            || previous.iter().any(|id| compose_state.uncertain.contains(&(account_id.clone(), format!("draft:{id}"))))
        {
            return Err("This draft may already have been sent. Check Sent before trying again.".into());
        }
        let identity = compose_state.identities.get(&account_id)
            .and_then(|(_, list)| list.iter().find(|i| i.id == draft.identity_id))
            .ok_or("Refresh the sending identities.")?;
        let view = store.view(&account_id, None, Rows::Messages).map_err(explain)?;
        let boxes = view["mailboxes"].as_array().ok_or("Mailboxes are unavailable.")?;
        let role = |role: &str| boxes.iter().find(|box_| box_["role"] == role)
            .and_then(|box_| box_["id"].as_str());
        let drafts = role("drafts").ok_or("The account has no Drafts mailbox.")?;
        let mut result = ComposeResult {outcome: "saveUnknown", draft_id: None, pending_cleanup: previous,
            reason: None};
        match compose::save_draft(&active.client, &mail.session, &account_id, identity, drafts, &draft, &result.pending_cleanup) {
            compose::Write::Rejected(kind) => {
                result.outcome = "saveRejected";
                result.reason = Some(compose_reason(&kind));
            }
            compose::Write::Unknown => {}
            compose::Write::Applied(saved) => {
                let submission = send.then(|| compose::submit(&active.client, &mail.session, &account_id,
                    identity, drafts, role("sent"), &saved));
                result.draft_id = Some(saved.id);
                result.pending_cleanup = saved.pending_cleanup;
                match submission {
                    None => result.outcome = "saved",
                    Some(compose::Write::Applied(())) => {
                        // Submission can succeed even if the implicit filing update fails.
                        if let Some(id) = &result.draft_id {
                            compose_state.uncertain.insert((account_id, format!("draft:{id}")));
                        }
                        result.outcome = "sent";
                    }
                    Some(compose::Write::Rejected(kind)) => {
                        result.outcome = "sendRejected";
                        result.reason = Some(compose_reason(&kind));
                    }
                    Some(compose::Write::Unknown) => {
                        compose_state.uncertain.insert((account_id.clone(), compose_id));
                        if let Some(id) = &result.draft_id {
                            compose_state.uncertain.insert((account_id, format!("draft:{id}")));
                        }
                        result.outcome = "sendUnknown";
                    }
                }
            }
        }
        Ok(result)
    }).await
}

/// Fetches deltas and the mailbox's first window, then answers from the cache.
#[tauri::command]
pub async fn mail_sync(
    connection_id: u32,
    account_id: String,
    mailbox_id: Option<String>,
    rows: String,
    state: tauri::State<'_, Connection>,
) -> Result<ListView, String> {
    let rows = self::rows(&rows)?;
    with_mail(&state, connection_id, move |active, mail, store| {
        let view = cache::sync(&active.client, &mail.session, store, &account_id, mailbox_id.as_deref(), rows)
            .map_err(explain)?;
        let _ = mail.prune_attachment_refs(store);
        account_list_view(&view, &mail.session, &account_id)
    })
    .await
}

/// Searches one Account without saving the query or its listing in the cache.
#[tauri::command]
pub async fn mail_search(
    connection_id: u32,
    account_id: String,
    text: String,
    retained: usize,
    position: Option<usize>,
    query_state: Option<String>,
    state: tauri::State<'_, Connection>,
) -> Result<ListView, String> {
    let cursor = match (position, query_state) {
        (Some(position), Some(state)) => Some((position, state)),
        (None, None) => None,
        _ => return Err("Search paging requires a position and query state.".into()),
    };
    with_mail(&state, connection_id, move |active, mail, _| {
        let cursor = cursor.as_ref().map(|(position, state)| search::Cursor { position: *position, state });
        let view = search::search(&active.client, &mail.session, &account_id, &text, retained, cursor)
            .map_err(|error| match error {
                Error::Shape(kind) if kind == "unsupportedFilter" => "This server does not support this search filter.",
                Error::Shape(kind) if kind == "Search changed while paging" => "Search results changed. Try again.",
                _ => "Could not search messages.",
            })?;
        account_list_view(&view, &mail.session, &account_id)
    }).await
}

/// Extends the cached listing by the next window.
#[tauri::command]
pub async fn mail_more(
    connection_id: u32,
    account_id: String,
    mailbox_id: Option<String>,
    rows: String,
    state: tauri::State<'_, Connection>,
) -> Result<ListView, String> {
    let rows = self::rows(&rows)?;
    with_mail(&state, connection_id, move |active, mail, store| {
        let view = cache::more(&active.client, &mail.session, store, &account_id, mailbox_id.as_deref(), rows)
            .map_err(explain)?;
        account_list_view(&view, &mail.session, &account_id)
    })
    .await
}

/// The cached listing only: no network, so it answers while offline and says what it lacks.
#[tauri::command]
pub async fn mail_view(
    connection_id: u32,
    account_id: String,
    mailbox_id: Option<String>,
    rows: String,
    state: tauri::State<'_, Connection>,
) -> Result<ListView, String> {
    let rows = self::rows(&rows)?;
    with_mail(&state, connection_id, move |_, mail, store| {
        let view = store
            .view(&account_id, mailbox_id.as_deref(), rows)
            .map_err(explain)?;
        account_list_view(&view, &mail.session, &account_id)
    })
    .await
}

/// One message with its text body, fetched when the cache does not cover it.
#[tauri::command]
pub async fn mail_read(
    connection_id: u32,
    account_id: String,
    email_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<DetailView, String> {
    with_mail(&state, connection_id, move |active, mail, store| {
        let entry = cache::read(&active.client, &mail.session, store, &account_id, &email_id)
            .map_err(explain)?;
        detail_view(&entry)
    })
    .await
}

const INLINE_IMAGE_LIMIT: u64 = 2 * 1024 * 1024;
const PREVIEW_LIMIT: u64 = 10_000_000;
const EXPORT_LIMIT: u64 = 50 * 1024 * 1024;
const CACHE_LIMIT: u64 = 1024 * 1024 * 1024;

#[derive(Default)]
pub struct OpenedAttachments(Mutex<Vec<tempfile::TempDir>>);

fn raster(mime: &str) -> bool {
    matches!(mime, "image/png" | "image/jpeg" | "image/gif" | "image/webp" | "image/avif" | "image/bmp")
}

fn attachment_part(store: &cache::Store, account: &str, email: &str, part_id: &str) -> Result<Value, String> {
    let entry = store.email(account, email).map_err(explain)?;
    let mut parts = entry["document"]["attachments"].as_array().into_iter().flatten()
        .filter(|part| part["partId"] == part_id);
    let part = parts.next().ok_or("Attachment is unavailable.")?;
    if parts.next().is_some() { return Err("Attachment is ambiguous.".into()); }
    Ok(part.clone())
}

fn attachment_bytes(active: &Active, mail: &Mail, account: &str, email: &str, part: &Value,
    cap: u64, recheck: bool) -> Result<Vec<u8>, String> {
    if part["size"].as_u64().is_some_and(|size| size > cap) {
        return Err("Attachment exceeds the size limit.".into());
    }
    let id = part["blobId"].as_str().ok_or("Attachment has no blob id.")?;
    let key = Reference { login: &mail.ledger_login, account, email, blob: id };
    let mut ledger = Ledger::open(&mail.ledger_root, &mail.ledger_origin, CACHE_LIMIT)?;
    ledger.get(&key, cap, recheck, || {
        let account_info = mail.session.accounts.get(account).ok_or("Mail account is unavailable.")?;
        if !account_info.account_capabilities.contains_key(blob::CAPABILITY) { return Ok(Access::Unsupported); }
        let response = match active.client.blob_get(&mail.session, account, &[id], &["id", "digest:sha-256"], None, None) {
            Ok(response) => response,
            Err(Error::Status { status: 401 | 403 | 404, .. } | Error::Problem { status: 401 | 403 | 404, .. }) =>
                return Ok(Access::Denied),
            Err(error) => return Err(failure("Attachment access", &error)),
        };
        if let Some(item) = response["list"].as_array().and_then(|items| items.first()) {
            return Ok(item["digest:sha-256"].as_str().map_or(Access::Unsupported, |hash| Access::Verified(hash.into())));
        }
        Ok(Access::Denied)
    }, || active.client.download(&mail.session, account, id, "application/octet-stream", "attachment", cap)
        .map_err(|error| failure("Attachment download", &error)))
}

fn attachment_name(raw: &str) -> String {
    let name: String = raw.chars().map(|c| if c.is_control() || "\\/:*?\"<>|".contains(c) { '_' } else { c })
        .take(100).collect();
    let name = name.trim_matches([' ', '.']);
    let stem = name.split('.').next().unwrap_or("").trim_end().to_ascii_uppercase();
    let reserved = matches!(stem.as_str(), "CON" | "PRN" | "AUX" | "NUL" | "CONIN$" | "CONOUT$")
        || ["COM", "LPT"].iter().any(|prefix| stem.strip_prefix(prefix)
            .is_some_and(|n| matches!(n, "1"|"2"|"3"|"4"|"5"|"6"|"7"|"8"|"9"|"\u{b9}"|"\u{b2}"|"\u{b3}")));
    if name.is_empty() { "attachment".into() }
    else if reserved { format!("_{name}") }
    else { name.into() }
}

fn may_open(mime: &str, name: &str) -> bool {
    let ext = name.rsplit_once('.').map(|(_, ext)| ext.to_ascii_lowercase());
    matches!((mime, ext.as_deref()),
        ("application/pdf", Some("pdf")) | ("image/png", Some("png")) |
        ("image/jpeg", Some("jpg" | "jpeg")) | ("image/gif", Some("gif")) |
        ("image/webp", Some("webp")) | ("image/avif", Some("avif")) |
        ("image/bmp", Some("bmp")) | ("text/plain", Some("txt")))
}

#[tauri::command]
pub async fn mail_part_bytes(
    connection_id: u32,
    account_id: String,
    email_id: String,
    part_id: String,
    preview: Option<bool>,
    state: tauri::State<'_, Connection>,
) -> Result<tauri::ipc::Response, String> {
    with_mail(&state, connection_id, move |active, mail, store| {
        let part = attachment_part(store, &account_id, &email_id, &part_id)?;
        let preview = preview.unwrap_or_default();
        if !preview && !part["cid"].as_str().is_some_and(|cid| !cid.is_empty()) {
            return Err("Inline image is unavailable.".into());
        }
        let mime = part["type"].as_str().ok_or("Inline image has no media type.")?;
        let cap = if preview { PREVIEW_LIMIT } else { INLINE_IMAGE_LIMIT };
        if !raster(mime) || part["size"].as_u64().is_none_or(|size| size > cap)
        {
            return Err("Inline image type or size is not supported.".into());
        }
        attachment_bytes(active, mail, &account_id, &email_id, &part, cap, false)
            .map(tauri::ipc::Response::new)
    }).await
}

#[tauri::command]
pub async fn mail_attachment_export<R: tauri::Runtime>(
    app: tauri::AppHandle<R>, connection_id: u32, account_id: String, email_id: String,
    part_id: String, open: bool, state: tauri::State<'_, Connection>,
    opened: tauri::State<'_, OpenedAttachments>,
) -> Result<bool, String> {
    use tauri::Manager;
    use tauri_plugin_opener::OpenerExt;
    let path = if open {
        None
    } else {
        let (account, email, part) = (account_id.clone(), email_id.clone(), part_id.clone());
        let name = with_mail(&state, connection_id, move |_, _, store| {
            let part = attachment_part(store, &account, &email, &part)?;
            Ok(attachment_name(part["name"].as_str().unwrap_or("attachment")))
        }).await?;
        let parent = app.get_webview_window("main");
        let path = tauri::async_runtime::spawn_blocking(move || {
            let dialog = rfd::FileDialog::new().set_file_name(name);
            match parent {
                Some(window) => dialog.set_parent(&window).save_file(),
                None => dialog.save_file(),
            }
        }).await.map_err(|_| "Save dialog did not complete.")?;
        let Some(path) = path else { return Ok(false); };
        Some(path)
    };
    let (name, bytes) = with_mail(&state, connection_id, move |active, mail, store| {
        let part = attachment_part(store, &account_id, &email_id, &part_id)?;
        let name = attachment_name(part["name"].as_str().unwrap_or("attachment"));
        if open && !may_open(part["type"].as_str().unwrap_or_default(), &name) {
            return Err("This file type can only be saved.".into());
        }
        let bytes = attachment_bytes(active, mail, &account_id, &email_id, &part, EXPORT_LIMIT, true)?;
        Ok((name, bytes))
    }).await?;
    if let Some(path) = path {
        state.current(connection_id)?;
        tauri::async_runtime::spawn_blocking(move || std::fs::write(path, bytes)).await
            .map_err(|_| "Save did not complete.")?.map_err(|e| e.to_string())?;
    } else {
        let temp = tempfile::Builder::new().prefix("axismundi-attachment-").tempdir().map_err(|e| e.to_string())?;
        let path = temp.path().join(name);
        std::fs::write(&path, bytes).map_err(|e| e.to_string())?;
        state.current(connection_id)?;
        app.opener().open_path(path.to_string_lossy(), None::<&str>).map_err(|e| e.to_string())?;
        opened.0.lock().map_err(|_| "Temporary attachments unavailable")?.push(temp);
    }
    Ok(true)
}

/// How one message came out of a change (keyword or move), as the GUI acts on it. Core's five outcomes fold
/// into three because the GUI's choice is three-way: done; known not applied, so it may be tried
/// again; or not known, so it has to be read before anything is sent again.
#[derive(Debug, Serialize)]
#[serde(tag = "outcome", rename_all = "camelCase")]
pub enum UpdateResult {
    Confirmed { id: String },
    NotApplied { id: String, reason: String },
    Unknown { id: String, reason: String },
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct UpdateView {
    /// One per distinct id asked for, in the order first asked.
    results: Vec<UpdateResult>,
    /// False when a confirmed change could not be read back into the cache: the write stands, and
    /// the views have to sync to show it.
    read_back_complete: bool,
}

fn update_view(results: axismundi_client_core::jmap::mail::UpdateResults) -> UpdateView {
    use axismundi_client_core::jmap::mail::UpdateOutcome as Outcome;
    let read_back_complete = results.not_read_back.is_empty();
    let results = results
        .outcomes
        .into_iter()
        .map(|(id, outcome)| match outcome {
            Outcome::Confirmed => UpdateResult::Confirmed { id },
            Outcome::Refused(kind) => UpdateResult::NotApplied { id, reason: format!("The server refused it ({kind}).") },
            Outcome::Failed(error) => UpdateResult::NotApplied { id, reason: failure("Mail", &error) },
            Outcome::TooLarge => UpdateResult::NotApplied {
                id,
                reason: "Not sent: the request for this message is larger than the server accepts.".into(),
            },
            Outcome::Unchanged => UpdateResult::NotApplied {
                id,
                reason: "The answer was lost, and the server shows it unchanged.".into(),
            },
            Outcome::Unknown(None) => UpdateResult::Unknown {
                id,
                reason: "The answer was lost, and the message did not come back to check.".into(),
            },
            Outcome::Unknown(Some(error)) => UpdateResult::Unknown {
                id,
                reason: format!("The answer was lost, and reading it back failed. {}", failure("Mail", &error)),
            },
        })
        .collect();
    UpdateView { results, read_back_complete }
}

/// Sets or clears `$seen` or `$flagged` on the given messages of one Account (see core
/// `mail::set_keywords`). Each id settles on its own; nothing is resent, and nothing on screen should
/// change except what the server confirmed. One message is the one-id case.
#[tauri::command]
pub async fn mail_set_keywords(
    connection_id: u32,
    account_id: String,
    email_ids: Vec<String>,
    keyword: String,
    on: bool,
    state: tauri::State<'_, Connection>,
) -> Result<UpdateView, String> {
    let keyword = match keyword.as_str() {
        "seen" => axismundi_client_core::jmap::mail::Keyword::Seen,
        "flagged" => axismundi_client_core::jmap::mail::Keyword::Flagged,
        _ => return Err("keyword takes seen or flagged".into()),
    };
    with_mail(&state, connection_id, move |active, mail, store| {
        let results = cache::set_keywords(&active.client, &mail.session, store, &account_id, &email_ids, keyword, on)
            .map_err(explain)?;
        Ok(update_view(results))
    })
    .await
}

/// Moves messages of one Account from the mailbox they are listed in to another (see core
/// `cache::move_emails`). `exclusive` takes them out of every mailbox this login sees, not just
/// `from`; the GUI asks for it for Trash and Junk. Results as for keywords; nothing is resent.
#[tauri::command]
pub async fn mail_move(
    connection_id: u32,
    account_id: String,
    email_ids: Vec<String>,
    from: Option<String>,
    to: String,
    exclusive: bool,
    state: tauri::State<'_, Connection>,
) -> Result<UpdateView, String> {
    if !exclusive && from.as_deref().is_none_or(str::is_empty) {
        return Err("Move: open the source mailbox first.".into());
    }
    if from.as_deref() == Some(to.as_str()) {
        return Err("Move: the message is already in that mailbox.".into());
    }
    with_mail(&state, connection_id, move |active, mail, store| {
        let results = if let Some(from) = from {
            cache::move_emails(&active.client, &mail.session, store, &account_id, &email_ids, &from, &to, exclusive)
        } else {
            cache::trash_search_results(&active.client, &mail.session, store, &account_id, &email_ids, &to)
        }.map_err(explain)?;
        Ok(update_view(results))
    })
    .await
}

/// Reads the given messages' current keywords and mailboxes from the server (no write) and returns
/// their rows. The GUI uses it to settle an `unknown` outcome before it allows another write to those
/// messages.
#[tauri::command]
pub async fn mail_check(
    connection_id: u32,
    account_id: String,
    email_ids: Vec<String>,
    state: tauri::State<'_, Connection>,
) -> Result<Vec<MailRow>, String> {
    with_mail(&state, connection_id, move |active, mail, store| {
        let entries = cache::read_keywords(&active.client, &mail.session, store, &account_id, &email_ids)
            .map_err(explain)?;
        entries.iter().map(mail_row).collect()
    })
    .await
}

/// The thread a message belongs to, as the server groups it.
#[tauri::command]
pub async fn mail_thread(
    connection_id: u32,
    account_id: String,
    thread_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<ThreadView, String> {
    with_mail(&state, connection_id, move |active, mail, store| {
        let result = cache::thread(&active.client, &mail.session, store, &account_id, &thread_id)
            .map_err(explain)?;
        thread_view(&result)
    })
    .await
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn cache_file_names_differ_per_login_and_carry_no_separators() {
        let dir = Path::new("data");
        let a = cache_file(dir, r#"{"apiUrl":"https://a.test/jmap/","username":"alice"}"#);
        let b = cache_file(dir, r#"{"apiUrl":"https://a.test/jmap/","username":"alice2"}"#);
        assert_ne!(a, b);
        let name = a.file_name().unwrap().to_str().unwrap();
        assert!(name.trim_end_matches(".sqlite").bytes().all(|byte| byte.is_ascii_hexdigit()));
    }

    const CORE_VIEW: &str = include_str!("../fixtures/mail-view.core.json");
    const GUI_FIXTURE: &str = "../src/app/mail/mail-list.fixture.json";

    /// The input is a real `mail-sync` view from the local Stalwart fixture (2026-11-21), with
    /// edits to cover every row state: `$seen` added to the second message, keywords removed
    /// from the third (never fetched), a fourth, unavailable entry appended, and a fifth whose
    /// cached `$seen` is no longer covered (`staleProperties: ["keywords"]`).
    /// The output is the file the GUI spec reads, so a change to this shape fails one side.
    /// Regenerate it with AXISMUNDI_UPDATE_FIXTURES=1.
    #[test]
    fn listing_serializes_to_the_shape_the_gui_reads() {
        let view: Value = serde_json::from_str(CORE_VIEW).unwrap();
        let actual = serde_json::to_value(list_view(&view).unwrap()).unwrap();
        let path = Path::new(env!("CARGO_MANIFEST_DIR")).join(GUI_FIXTURE);
        if std::env::var_os("AXISMUNDI_UPDATE_FIXTURES").is_some() {
            let text = serde_json::to_string_pretty(&actual).unwrap() + "
";
            std::fs::write(&path, text).unwrap();
        }
        let expected: Value = serde_json::from_str(&std::fs::read_to_string(&path).unwrap()).unwrap();
        assert_eq!(actual, expected, "GUI fixture is stale; rerun with AXISMUNDI_UPDATE_FIXTURES=1");
        let rows = actual["rows"].as_array().unwrap();
        assert_eq!(rows.len(), 5, "server order and count are kept");
        assert_eq!(rows[0]["subject"], "Re: [Axismundi probe v1] Conversation");
        assert_eq!(rows[0]["senderEmail"], "admin@example.org");
        assert_eq!(rows[0]["unread"], true);
        assert_eq!(rows[1]["unread"], false);
        assert_eq!(rows[2]["unread"], Value::Null);
        assert_eq!(rows[3], json!({"kind": "unavailable", "id": "zzzzzzzz", "reason": "notFound"}));
        assert_eq!(rows[4]["unread"], Value::Null, "a stale $seen is not a read state");
        assert!(rows[0]["mailboxIds"].as_array().is_some_and(|boxes| !boxes.is_empty()), "moves need memberships");
        let trash = actual["mailboxes"].as_array().unwrap().iter().find(|b| b["role"] == "trash").unwrap();
        assert_eq!((trash["mayAddItems"].clone(), trash["mayRemoveItems"].clone()), (json!(true), json!(true)));
        assert_eq!(trash["mayCreateChild"], true);
        assert_eq!(trash["mayDelete"], true);
        assert_eq!(trash["mayRename"], true);
    }

    #[test]
    fn mailbox_management_limits_are_account_specific_and_refusals_are_local() {
        let view: Value = serde_json::from_str(CORE_VIEW).unwrap();
        let session: Session = serde_json::from_value(json!({"apiUrl":"https://example.org/jmap","accounts":{
            "b":{"accountCapabilities":{"urn:ietf:params:jmap:mail":{
                "mayCreateTopLevelMailbox":false,"maxMailboxDepth":10,"maxSizeMailboxName":255
            }}}
        }})).unwrap();
        let listing = account_list_view(&view, &session, "b").unwrap();
        assert_eq!((listing.may_create_top_level_mailbox, listing.max_mailbox_depth, listing.max_size_mailbox_name),
                   (Some(false), Some(10), Some(255)));
        let listing = account_list_view(&view, &session, "other").unwrap();
        assert_eq!(listing.may_create_top_level_mailbox, None);
        let result = mailbox_write_result(mailbox::Outcome::Refused {
            kind: "invalidProperties".into(), properties: vec!["role".into(), "remote text".into()],
        });
        let result = serde_json::to_value(result).unwrap();
        assert_eq!(result["properties"], json!(["role"]));
        assert_eq!(result["message"], "This role is already in use or is not supported by the server. (invalidProperties)");
        let forbidden = serde_json::to_value(mailbox_write_result(mailbox::Outcome::Refused {
            kind: "forbidden".into(), properties: vec![],
        })).unwrap();
        assert_eq!(forbidden["message"], "The server does not allow this change. (forbidden)");
        let odd = serde_json::to_value(mailbox_write_result(mailbox::Outcome::Refused {
            kind: "<b>click here</b>".into(), properties: vec![],
        })).unwrap();
        assert_eq!(odd["message"], "The server refused the mailbox change.", "a type that is not a name is not shown");
    }

    /// A real `mail-read` response (2026-11-21) and three edits of it: HTML-only parts, a
    /// truncated body with one attachment, and an unavailable entry.
    #[test]
    fn detail_serializes_to_the_shape_the_gui_reads() {
        let entries: Vec<Value> = serde_json::from_str(include_str!("../fixtures/mail-read.core.json")).unwrap();
        let actual = Value::Array(
            entries.iter().map(|entry| serde_json::to_value(detail_view(entry).unwrap()).unwrap()).collect(),
        );
        let path = Path::new(env!("CARGO_MANIFEST_DIR")).join("../src/app/mail/mail-detail.fixture.json");
        if std::env::var_os("AXISMUNDI_UPDATE_FIXTURES").is_some() {
            std::fs::write(&path, serde_json::to_string_pretty(&actual).unwrap() + "
").unwrap();
        }
        let expected: Value = serde_json::from_str(&std::fs::read_to_string(&path).unwrap()).unwrap();
        assert_eq!(actual, expected, "GUI fixture is stale; rerun with AXISMUNDI_UPDATE_FIXTURES=1");
        assert_eq!(actual[0]["body"]["parts"][0]["kind"], "text");
        assert!(actual[0]["body"]["parts"][0]["value"].as_str().unwrap().starts_with("Probe conversation, message 3 of 4."));
        assert_eq!(actual[0]["from"], json!([{"name": null, "email": "admin@example.org"}]));
        assert_eq!(actual[1]["body"]["parts"][0]["kind"], "html");
        assert_eq!(actual[1]["body"]["parts"][0]["value"], "<p>Probe</p>", "untrusted markup is explicitly typed for the isolated renderer");
        assert_eq!(actual[2]["body"]["parts"][0]["truncated"], true);
        assert_eq!(actual[2]["attachments"][0]["name"], "probe.txt");
        assert_eq!(actual[3]["kind"], "unavailable");
    }

    #[test]
    fn detail_keeps_text_parts_separate() {
        let body = body(&json!({
            "textBody":[{"type":"text/plain","partId":"first"},{"type":"text/plain","partId":"second"}],
            "bodyValues":{"first":{"value":"One"},"second":{"value":"Two"}}
        }));
        assert_eq!(body.parts.iter().map(|part| part.value.as_str()).collect::<Vec<_>>(), ["One", "Two"]);
    }

    /// Every core outcome, as `update` can return them, folded into the three the GUI acts on. The
    /// output is the file the GUI spec reads (`mail-updates.fixture.json`).
    #[test]
    fn update_results_serialize_to_the_shape_the_gui_reads() {
        use axismundi_client_core::jmap::mail::{UpdateOutcome as Outcome, UpdateResults};
        let results = UpdateResults {
            outcomes: vec![
                ("m1".into(), Outcome::Confirmed),
                ("m2".into(), Outcome::Refused("forbidden".into())),
                ("m3".into(), Outcome::Failed(Error::Problem { status: 400, kind: "urn:ietf:params:jmap:error:limit".into(), detail: Some("remote text".into()) })),
                ("m4".into(), Outcome::Unchanged),
                ("m5".into(), Outcome::Unknown(None)),
                ("m6".into(), Outcome::Unknown(Some(Error::Transport("connection reset".into())))),
                ("m7".into(), Outcome::Confirmed),
                ("m8".into(), Outcome::TooLarge),
            ],
            read_back: json!({"list": [], "notFound": []}),
            not_read_back: vec!["m7".into()],
        };
        let actual = serde_json::to_value(update_view(results)).unwrap();
        let path = Path::new(env!("CARGO_MANIFEST_DIR")).join("../src/app/mail/mail-updates.fixture.json");
        if std::env::var_os("AXISMUNDI_UPDATE_FIXTURES").is_some() {
            std::fs::write(&path, serde_json::to_string_pretty(&actual).unwrap() + "\n").unwrap();
        }
        let expected: Value = serde_json::from_str(&std::fs::read_to_string(&path).unwrap()).unwrap();
        assert_eq!(actual, expected, "GUI fixture is stale; rerun with AXISMUNDI_UPDATE_FIXTURES=1");
        let outcomes: Vec<&str> = actual["results"].as_array().unwrap().iter().map(|r| r["outcome"].as_str().unwrap()).collect();
        assert_eq!(outcomes, ["confirmed", "notApplied", "notApplied", "notApplied", "unknown", "unknown", "confirmed", "notApplied"]);
        assert!(!actual.to_string().contains("remote text"), "remote response text never reaches the webview");
        assert_eq!(actual["readBackComplete"], false);
    }

    /// A real `mail-thread` response for the four-message probe conversation (2026-11-21), the
    /// same with one member gone and the get order reversed, and a thread that no longer exists.
    #[test]
    fn thread_serializes_to_the_shape_the_gui_reads() {
        let results: Vec<Value> = serde_json::from_str(include_str!("../fixtures/mail-thread.core.json")).unwrap();
        let actual = Value::Array(
            results.iter().map(|result| serde_json::to_value(thread_view(result).unwrap()).unwrap()).collect(),
        );
        let path = Path::new(env!("CARGO_MANIFEST_DIR")).join("../src/app/mail/mail-thread.fixture.json");
        if std::env::var_os("AXISMUNDI_UPDATE_FIXTURES").is_some() {
            std::fs::write(&path, serde_json::to_string_pretty(&actual).unwrap() + "
").unwrap();
        }
        let expected: Value = serde_json::from_str(&std::fs::read_to_string(&path).unwrap()).unwrap();
        assert_eq!(actual, expected, "GUI fixture is stale; rerun with AXISMUNDI_UPDATE_FIXTURES=1");
        let order = |thread: &Value| -> Vec<String> {
            thread["messages"].as_array().unwrap().iter().map(|m| m["id"].as_str().unwrap().to_owned()).collect()
        };
        assert_eq!(order(&actual[0]), ["beaaaaaj", "beaaaaak", "beaaaaal", "beaaaaam"]);
        assert_eq!(order(&actual[1])[..4], order(&actual[0])[..], "thread order, not get order");
        assert_eq!(actual[1]["messages"][4]["kind"], "unavailable");
        assert_eq!(actual[2], json!({"kind": "notFound", "threadId": "nothread"}));
    }

    #[test]
    fn rows_accepts_only_the_two_listings() {
        assert_eq!(rows("messages"), Ok(Rows::Messages));
        assert_eq!(rows("threads"), Ok(Rows::Threads));
        assert!(rows("all").is_err());
    }
}
