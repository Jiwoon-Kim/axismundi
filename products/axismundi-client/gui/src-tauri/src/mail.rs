//! Mail commands: thin wrappers over `axismundi_jmap_core::mail::cache`. The GUI names an
//! Account, a mailbox or an Email; it never builds a JMAP request or sees SQL.
use std::path::{Path, PathBuf};
use std::sync::{Arc, Mutex};

use axismundi_jmap_core::{
    Error, Session,
    mail::{Rows, cache},
};
use serde::Serialize;
use serde_json::{Value, json};

use crate::connection::{Active, Connection, failure};

/// The JMAP Session a connection signed in with, and that login's Mail cache.
pub struct Mail {
    pub session: Session,
    store: Mutex<cache::Store>,
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
        Ok(Self {
            session,
            store: Mutex::new(store),
        })
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
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
struct MailboxRow {
    id: String,
    name: String,
    role: Option<String>,
    parent_id: Option<String>,
    sort_order: Option<u64>,
    total_emails: Option<u64>,
    unread_emails: Option<u64>,
}

#[derive(Debug, Serialize)]
#[serde(tag = "kind", rename_all = "camelCase", rename_all_fields = "camelCase")]
enum MailRow {
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
        has_attachment: Option<bool>,
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
    let stale = |property: &str| {
        entry["staleProperties"]
            .as_array()
            .is_some_and(|stale| stale.iter().any(|name| name == property))
    };
    let keywords = document
        .get("keywords")
        .and_then(Value::as_object)
        .filter(|_| !stale("keywords"));
    Ok(MailRow::Message {
        id,
        thread_id: text(document, "/threadId"),
        sender_name: text(document, "/from/0/name").filter(|name| !name.is_empty()),
        sender_email: text(document, "/from/0/email"),
        subject: text(document, "/subject"),
        preview: text(document, "/preview"),
        received_at: text(document, "/receivedAt"),
        unread: keywords.map(|keywords| keywords.get("$seen") != Some(&Value::Bool(true))),
        has_attachment: document.get("hasAttachment").and_then(Value::as_bool),
    })
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
    })
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

/// Plain text first: HTML is never handed to the webview until it has its own policy.
#[derive(Debug, Serialize)]
#[serde(tag = "kind", rename_all = "camelCase", rename_all_fields = "camelCase")]
pub enum Body {
    Text { text: String, truncated: bool, encoding_problem: bool },
    /// The message offers no text/plain part; its HTML is not shown yet.
    HtmlOnly,
    /// A text part exists but its value was not fetched.
    NotFetched,
    Empty,
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Attachment {
    part_id: Option<String>,
    name: Option<String>,
    r#type: Option<String>,
    size: Option<u64>,
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
    let parts = document["textBody"].as_array().map(Vec::as_slice).unwrap_or_default();
    if parts.is_empty() {
        return Body::Empty;
    }
    let plain: Vec<&Value> = parts
        .iter()
        .filter(|part| part["type"].as_str().is_some_and(|kind| kind.eq_ignore_ascii_case("text/plain")))
        .collect();
    if plain.is_empty() {
        return Body::HtmlOnly;
    }
    let mut texts = Vec::new();
    let (mut truncated, mut encoding_problem) = (false, false);
    for part in plain {
        let Some(value) = part["partId"].as_str().and_then(|id| document["bodyValues"].get(id)) else {
            return Body::NotFetched;
        };
        let Some(text) = value["value"].as_str() else { return Body::NotFetched };
        texts.push(text);
        truncated |= value["isTruncated"] == Value::Bool(true);
        encoding_problem |= value["isEncodingProblem"] == Value::Bool(true);
    }
    Body::Text { text: texts.join("\n\n"), truncated, encoding_problem }
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
                    })
                    .collect()
            })
            .unwrap_or_default(),
    })
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
        list_view(&view)
    })
    .await
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
        list_view(&view)
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
    with_mail(&state, connection_id, move |_, _, store| {
        let view = store
            .view(&account_id, mailbox_id.as_deref(), rows)
            .map_err(explain)?;
        list_view(&view)
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

/// The thread the message belongs to, as the server groups it.
#[tauri::command]
pub async fn mail_thread(
    connection_id: u32,
    account_id: String,
    email_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<Value, String> {
    with_mail(&state, connection_id, move |active, mail, store| {
        cache::thread(&active.client, &mail.session, store, &account_id, &email_id)
            .map_err(explain)
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
        assert_eq!(actual[0]["body"]["kind"], "text");
        assert!(actual[0]["body"]["text"].as_str().unwrap().starts_with("Probe conversation, message 3 of 4."));
        assert_eq!(actual[0]["from"], json!([{"name": null, "email": "admin@example.org"}]));
        assert_eq!(actual[1]["body"], json!({"kind": "htmlOnly"}), "HTML is never handed to the webview");
        assert_eq!(actual[2]["body"]["truncated"], true);
        assert_eq!(actual[2]["attachments"][0]["name"], "probe.txt");
        assert_eq!(actual[3]["kind"], "unavailable");
    }

    #[test]
    fn rows_accepts_only_the_two_listings() {
        assert_eq!(rows("messages"), Ok(Rows::Messages));
        assert_eq!(rows("threads"), Ok(Rows::Threads));
        assert!(rows("all").is_err());
    }
}
