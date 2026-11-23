//! Partial Mail cache. Network exchanges are staged outside SQLite transactions.
use super::{
    LIST_PROPERTIES, MAILBOX_PROPERTIES, MUTABLE_PROPERTIES, MAX_BODY_VALUE_BYTES, Rows, Window, apply_query_delta,
    arguments, call, choose_account, limit, mailboxes, query_args, query_changes, query_ids,
    query_key, validate_get, window, window_size,
};

/// v2 keys a listing by its query, not its mailbox.
const SCHEMA_VERSION: &str = "2";
use crate::{Client, Session};
use rusqlite::{Connection, OptionalExtension, Transaction, TransactionBehavior, params};
use serde_json::{Value, json};
use std::{collections::BTreeSet, path::Path};

pub type Result<T> = std::result::Result<T, Box<dyn std::error::Error>>;

pub struct Store {
    db: Connection,
}

fn string<'a>(value: &'a Value, key: &str) -> Result<&'a str> {
    value
        .get(key)
        .and_then(Value::as_str)
        .ok_or_else(|| format!("missing string {key}").into())
}

impl Store {
    /// Shares only profile/service metadata with Contacts; owns its own schema version.
    pub fn open(path: impl AsRef<Path>, profile: &str) -> Result<Self> {
        if profile.trim().is_empty() {
            return Err("profile must not be empty".into());
        }
        let mut db = Connection::open(path)?;
        db.busy_timeout(std::time::Duration::from_secs(5))?;
        db.execute_batch("PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;")?;
        let tx = db.transaction_with_behavior(TransactionBehavior::Immediate)?;
        tx.execute_batch(
            "CREATE TABLE IF NOT EXISTS metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);",
        )?;
        tx.execute(
            "INSERT OR IGNORE INTO metadata VALUES('profile',?1)",
            [profile],
        )?;
        let actual: String =
            tx.query_row("SELECT value FROM metadata WHERE key='profile'", [], |r| {
                r.get(0)
            })?;
        if actual != profile {
            return Err("cache belongs to another authentication profile".into());
        }
        let version: Option<String> = tx
            .query_row(
                "SELECT value FROM metadata WHERE key='mail_schema_version'",
                [],
                |r| r.get(0),
            )
            .optional()?;
        match version.as_deref() {
            None | Some(SCHEMA_VERSION) => {}
            // v1 keyed a listing by its mailbox alone, so a message listing and a thread listing of
            // the same mailbox overwrote each other. The two tables hold only what a query returned,
            // with no user intent in them, so they are dropped and refilled by the next sync rather
            // than translated; CREATE ... IF NOT EXISTS below would otherwise keep the old shape.
            Some("1") => tx.execute_batch(
                "DROP TABLE IF EXISTS mail_query_items; DROP TABLE IF EXISTS mail_queries;",
            )?,
            Some(_) => return Err("unsupported Mail cache schema version".into()),
        }
        tx.execute_batch("CREATE TABLE IF NOT EXISTS mail_accounts(account TEXT PRIMARY KEY, revision INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE IF NOT EXISTS mail_mailboxes(account TEXT NOT NULL,id TEXT NOT NULL,document TEXT NOT NULL,role TEXT,PRIMARY KEY(account,id));
            CREATE TABLE IF NOT EXISTS mail_emails(account TEXT NOT NULL,id TEXT NOT NULL,document TEXT NOT NULL,coverage TEXT NOT NULL,subject TEXT,received_at TEXT,PRIMARY KEY(account,id));
            CREATE TABLE IF NOT EXISTS mail_email_mailboxes(account TEXT NOT NULL,email_id TEXT NOT NULL,mailbox_id TEXT NOT NULL,PRIMARY KEY(account,email_id,mailbox_id),FOREIGN KEY(account,email_id) REFERENCES mail_emails(account,id) ON DELETE CASCADE);
            CREATE INDEX IF NOT EXISTS mail_membership ON mail_email_mailboxes(account,mailbox_id,email_id);
            CREATE TABLE IF NOT EXISTS mail_sync_state(account TEXT NOT NULL,kind TEXT NOT NULL,state TEXT NOT NULL,PRIMARY KEY(account,kind));
            CREATE TABLE IF NOT EXISTS mail_queries(account TEXT NOT NULL,query_key TEXT NOT NULL,mailbox_id TEXT NOT NULL,arguments TEXT NOT NULL,document TEXT NOT NULL,stale INTEGER NOT NULL DEFAULT 1,PRIMARY KEY(account,query_key));
            CREATE TABLE IF NOT EXISTS mail_query_items(account TEXT NOT NULL,query_key TEXT NOT NULL,position INTEGER NOT NULL,email_id TEXT NOT NULL,PRIMARY KEY(account,query_key,position),UNIQUE(account,query_key,email_id),FOREIGN KEY(account,query_key) REFERENCES mail_queries(account,query_key) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS mail_unavailable(account TEXT NOT NULL,id TEXT NOT NULL,reason TEXT NOT NULL,PRIMARY KEY(account,id));")?;
        tx.execute(
            "INSERT INTO metadata VALUES('mail_schema_version',?1) ON CONFLICT(key) DO UPDATE SET value=excluded.value",
            [SCHEMA_VERSION],
        )?;
        tx.commit()?;
        Ok(Self { db })
    }

    pub fn bind(&self, client: &Client, session: &Session) -> Result<()> {
        let binding =
            json!({"apiUrl":session.api_url,"username":client.credentials.user}).to_string();
        self.db.execute(
            "INSERT OR IGNORE INTO metadata VALUES('service',?1)",
            [&binding],
        )?;
        let actual: String =
            self.db
                .query_row("SELECT value FROM metadata WHERE key='service'", [], |r| {
                    r.get(0)
                })?;
        if actual != binding {
            return Err("cache is bound to a different service or login".into());
        }
        Ok(())
    }

    fn revision(&self, account: &str) -> Result<i64> {
        self.db.execute(
            "INSERT OR IGNORE INTO mail_accounts(account) VALUES(?1)",
            [account],
        )?;
        Ok(self.db.query_row(
            "SELECT revision FROM mail_accounts WHERE account=?1",
            [account],
            |r| r.get(0),
        )?)
    }

    fn write(&mut self, account: &str, revision: i64) -> Result<Transaction<'_>> {
        let tx = self
            .db
            .transaction_with_behavior(TransactionBehavior::Immediate)?;
        if tx.execute(
            "UPDATE mail_accounts SET revision=revision+1 WHERE account=?1 AND revision=?2",
            params![account, revision],
        )? != 1
        {
            return Err("Mail cache changed during request; retry".into());
        }
        Ok(tx)
    }

    pub fn state(&self, account: &str, kind: &str) -> Result<Option<String>> {
        Ok(self
            .db
            .query_row(
                "SELECT state FROM mail_sync_state WHERE account=?1 AND kind=?2",
                params![account, kind],
                |r| r.get(0),
            )
            .optional()?)
    }

    pub fn email(&self, account: &str, id: &str) -> Result<Value> {
        let row: Option<(String, String)> = self
            .db
            .query_row(
                "SELECT document,coverage FROM mail_emails WHERE account=?1 AND id=?2",
                params![account, id],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .optional()?;
        if let Some((doc, coverage)) = row {
            let document: Value = serde_json::from_str(&doc)?;
            let coverage: Value = serde_json::from_str(&coverage)?;
            let stale: Vec<_> = document
                .as_object()
                .ok_or("invalid cached Email")?
                .keys()
                .filter(|k| !coverage.as_object().is_some_and(|v| v.contains_key(*k)))
                .cloned()
                .collect();
            return Ok(
                json!({"id":id,"available":true,"document":document,"coverage":coverage,"staleProperties":stale}),
            );
        }
        let reason: Option<String> = self
            .db
            .query_row(
                "SELECT reason FROM mail_unavailable WHERE account=?1 AND id=?2",
                params![account, id],
                |r| r.get(0),
            )
            .optional()?;
        Ok(json!({"id":id,"available":false,"reason":reason.unwrap_or("notCached".into())}))
    }

    pub fn mailbox_id(&self, account: &str, requested: Option<&str>) -> Result<String> {
        if let Some(id) = requested {
            return Ok(id.to_owned());
        }
        let mut stmt = self
            .db
            .prepare("SELECT id FROM mail_mailboxes WHERE account=?1 AND role='inbox'")?;
        let ids: Vec<String> = stmt
            .query_map([account], |r| r.get(0))?
            .collect::<std::result::Result<_, _>>()?;
        match ids.as_slice() {
            [id] => Ok(id.clone()),
            _ => Err("no unambiguous cached Inbox; supply --mailbox".into()),
        }
    }

    /// Every cached mailbox of the Account that this login may remove items from (`myRights`
    /// unstated counts as allowed: the server still decides).
    fn removable_mailboxes(&self, account: &str) -> Result<Vec<String>> {
        let mut stmt = self.db.prepare("SELECT id, document FROM mail_mailboxes WHERE account=?1 ORDER BY id")?;
        let rows = stmt.query_map([account], |r| Ok((r.get::<_, String>(0)?, r.get::<_, String>(1)?)))?;
        let mut ids = Vec::new();
        for row in rows {
            let (id, document) = row?;
            let document: Value = serde_json::from_str(&document)?;
            if document.pointer("/myRights/mayRemoveItems") != Some(&Value::Bool(false)) {
                ids.push(id);
            }
        }
        Ok(ids)
    }

    fn mailbox(&self, account: &str, id: &str) -> Result<Option<Value>> {
        let raw: Option<String> = self
            .db
            .query_row(
                "SELECT document FROM mail_mailboxes WHERE account=?1 AND id=?2",
                params![account, id],
                |r| r.get(0),
            )
            .optional()?;
        Ok(raw.map(|raw| serde_json::from_str(&raw)).transpose()?)
    }

    pub fn view(&self, account: &str, mailbox: Option<&str>, rows: Rows) -> Result<Value> {
        let mailbox = self.mailbox_id(account, mailbox)?;
        let key = query_key(&mailbox, rows);
        let mut stmt = self
            .db
            .prepare("SELECT document FROM mail_mailboxes WHERE account=?1 ORDER BY id")?;
        let boxes: Vec<String> = stmt
            .query_map([account], |r| r.get(0))?
            .collect::<std::result::Result<_, _>>()?;
        let boxes: Vec<Value> = boxes
            .iter()
            .map(|s| serde_json::from_str(s))
            .collect::<std::result::Result<_, _>>()?;
        let query: Option<(String, i64)> = self
            .db
            .query_row(
                "SELECT document,stale FROM mail_queries WHERE account=?1 AND query_key=?2",
                params![account, key],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .optional()?;
        let mut stmt = self.db.prepare("SELECT email_id FROM mail_query_items WHERE account=?1 AND query_key=?2 ORDER BY position")?;
        let ids: Vec<String> = stmt
            .query_map(params![account, key], |r| r.get(0))?
            .collect::<std::result::Result<_, _>>()?;
        let emails: Vec<Value> = ids
            .iter()
            .map(|id| self.email(account, id))
            .collect::<Result<_>>()?;
        let (query, stale) = match query {
            Some((raw, stale)) => (serde_json::from_str::<Value>(&raw)?, stale != 0),
            None => (Value::Null, true),
        };
        // Unknown when the server did not calculate a total; never guessed from the rows held.
        let total = query.get("total").and_then(Value::as_u64);
        let more = total.map(|total| (ids.len() as u64) < total);
        Ok(
            json!({"accountId":account,"mailboxId":mailbox,"mailboxes":boxes,"query":query,"queryStale":stale,"total":total,"hasMore":more,"emails":emails,"emailChangesState":self.state(account,"Email")?,"partialCache":true}),
        )
    }
}

fn merge_email(tx: &Transaction<'_>, account: &str, incoming: &Value, detail: bool) -> Result<()> {
    let id = string(incoming, "id")?;
    let old: Option<(String, String)> = tx
        .query_row(
            "SELECT document,coverage FROM mail_emails WHERE account=?1 AND id=?2",
            params![account, id],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )
        .optional()?;
    let (mut doc, mut coverage) = if let Some((d, c)) = old {
        (
            serde_json::from_str::<Value>(&d)?,
            serde_json::from_str::<Value>(&c)?,
        )
    } else {
        (json!({}), json!({}))
    };
    // Preserve omitted properties. Replace fetched bodyValues as a unit; never imply full-body coverage.
    for (key, value) in incoming.as_object().ok_or("Email is not an object")? {
        doc[key] = value.clone();
        coverage[key] = if key == "bodyValues" {
            let mut parts = serde_json::Map::new();
            for (part, body) in value.as_object().ok_or("bodyValues must be an object")? {
                parts.insert(part.clone(),json!({"isTruncated":body.get("isTruncated"),"isEncodingProblem":body.get("isEncodingProblem"),"cachedBytes":body["value"].as_str().map(str::len)}));
            }
            json!({"fetchTextBodyValues":detail,"fetchHTMLBodyValues":detail,"maxBodyValueBytes":MAX_BODY_VALUE_BYTES,"parts":parts})
        } else {
            json!(true)
        };
    }
    tx.execute("INSERT INTO mail_emails VALUES(?1,?2,?3,?4,?5,?6) ON CONFLICT(account,id) DO UPDATE SET document=excluded.document,coverage=excluded.coverage,subject=excluded.subject,received_at=excluded.received_at",
        params![account,id,doc.to_string(),coverage.to_string(),doc["subject"].as_str(),doc["receivedAt"].as_str()])?;
    if let Some(members) = incoming.get("mailboxIds") {
        let members = members.as_object().ok_or("mailboxIds must be an object")?;
        tx.execute(
            "DELETE FROM mail_email_mailboxes WHERE account=?1 AND email_id=?2",
            params![account, id],
        )?;
        for (mailbox, present) in members {
            if present == true {
                tx.execute(
                    "INSERT INTO mail_email_mailboxes VALUES(?1,?2,?3)",
                    params![account, id, mailbox],
                )?;
            }
        }
    }
    tx.execute(
        "DELETE FROM mail_unavailable WHERE account=?1 AND id=?2",
        params![account, id],
    )?;
    Ok(())
}

fn unavailable(tx: &Transaction<'_>, account: &str, id: &str, reason: &str) -> Result<()> {
    tx.execute(
        "DELETE FROM mail_emails WHERE account=?1 AND id=?2",
        params![account, id],
    )?;
    tx.execute("INSERT INTO mail_unavailable VALUES(?1,?2,?3) ON CONFLICT(account,id) DO UPDATE SET reason=excluded.reason", params![account,id,reason])?;
    tx.execute(
        "UPDATE mail_queries SET stale=1 WHERE account=?1",
        [account],
    )?;
    Ok(())
}

fn save_get(tx: &Transaction<'_>, account: &str, response: &Value, detail: bool) -> Result<()> {
    for doc in response["list"].as_array().ok_or("get has no list")? {
        merge_email(tx, account, doc, detail)?;
    }
    for id in response["notFound"]
        .as_array()
        .ok_or("get has no notFound")?
    {
        unavailable(
            tx,
            account,
            id.as_str().ok_or("invalid notFound")?,
            "notFound",
        )?;
    }
    Ok(())
}

pub fn read(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    id: &str,
) -> Result<Value> {
    store.bind(client, session)?;
    let revision = store.revision(account)?;
    let response = super::read(client, session, account, id)?;
    let tx = store.write(account, revision)?;
    save_get(&tx, account, &response, true)?;
    tx.commit()?;
    store.email(account, id)
}

/// Sets or clears a keyword on many messages and keeps what the server shows afterwards.
pub fn set_keywords(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    ids: &[String],
    keyword: super::Keyword,
    on: bool,
) -> Result<super::UpdateResults> {
    update(client, session, store, account, ids, super::Change::Keyword { keyword, on })
}

/// Moves many messages from one mailbox of an Account to another and keeps what the server shows
/// afterwards. The caller says which move it means; nothing here looks at roles:
///
/// - `exclusive: false` — `Change::Move`: only `from` is left and only `to` joined; other
///   memberships stay.
/// - `exclusive: true` — `Change::OnlyIn`: taken out of every mailbox of the Account this login sees
///   in the cache (and may remove items from), then put in `to`. Mailboxes a shared Account does not
///   show this login are not in the request, so they are never touched. The GUI asks for this when
///   moving to Trash or Junk (see `mail-moves.ts`).
///
/// An exclusive move with no cached mailbox list is an error: "every visible mailbox" would be a guess.
pub fn move_emails(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    ids: &[String],
    from: &str,
    to: &str,
    exclusive: bool,
) -> Result<super::UpdateResults> {
    if !exclusive {
        return update(client, session, store, account, ids, super::Change::Move { from, to });
    }
    let out_of = store.removable_mailboxes(account)?;
    if out_of.is_empty() {
        return Err("no cached mailbox list for this Account; sync it first".into());
    }
    update(client, session, store, account, ids, super::Change::OnlyIn { to, out_of: &out_of })
}

/// Applies a change on the server, then keeps what the server shows afterwards.
///
/// Only the read-back (see `mail::update`) reaches the cache, never the request itself, so nothing
/// changes here unless the server shows it. If the cache cannot take it, the outcomes still stand
/// (they describe the server, not this file) and every confirmed id joins `not_read_back`, so the
/// caller syncs instead of trusting the cache. No write is ever resent from here.
fn update(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    ids: &[String],
    change: super::Change<'_>,
) -> Result<super::UpdateResults> {
    store.bind(client, session)?;
    let revision = store.revision(account)?;
    let mut results = super::update(client, session, account, ids, change)?;
    let saved = (|| -> Result<()> {
        let tx = store.write(account, revision)?;
        save_get(&tx, account, &results.read_back, false)?;
        tx.commit()?;
        Ok(())
    })();
    if saved.is_err() {
        results.not_read_back = results
            .outcomes
            .iter()
            .filter(|(_, outcome)| matches!(outcome, super::UpdateOutcome::Confirmed))
            .map(|(id, _)| id.clone())
            .collect();
    }
    Ok(results)
}

/// Reads the given messages' keywords from the server and keeps them; returns each message's cache
/// entry afterwards, in the order asked. Read-only on the server (see `mail::read_keywords`).
pub fn read_keywords(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    ids: &[String],
) -> Result<Vec<Value>> {
    store.bind(client, session)?;
    let revision = store.revision(account)?;
    let result = super::read_keywords(client, session, account, ids)?;
    let tx = store.write(account, revision)?;
    save_get(&tx, account, &result, false)?;
    tx.commit()?;
    ids.iter().map(|id| store.email(account, id)).collect()
}

/// Expands a thread and keeps what it learned about each message.
///
/// The messages merge into the cache as listing-coverage rows, exactly as a listing's would, so a
/// later offline read of any of them works. The thread's own membership is not stored: nothing here
/// tracks Thread/changes yet, and a remembered member list would silently go stale when a reply
/// arrives. Until that exists, a thread view is network-only.
pub fn thread(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    id: &str,
) -> Result<Value> {
    store.bind(client, session)?;
    let revision = store.revision(account)?;
    let result = super::thread(client, session, account, id)?;
    if result["found"] == true {
        let tx = store.write(account, revision)?;
        save_get(
            &tx,
            account,
            &json!({"list":result["emails"],"notFound":result["notFound"]}),
            false,
        )?;
        tx.commit()?;
    }
    Ok(result)
}

fn checkpoint(client: &Client, session: &Session, account: &str) -> Result<String> {
    let batch = call(
        client,
        session,
        "Email/get",
        json!({"accountId":account,"ids":[],"properties":["id"]}),
    )?;
    let result = arguments(&batch, "Email/get", account)?;
    validate_get(&result, &[])?;
    Ok(string(&result, "state")?.to_owned())
}

fn set_state(tx: &Transaction<'_>, account: &str, kind: &str, state: &str) -> Result<()> {
    tx.execute("INSERT INTO mail_sync_state VALUES(?1,?2,?3) ON CONFLICT(account,kind) DO UPDATE SET state=excluded.state",params![account,kind,state])?;
    Ok(())
}

fn invalidate(store: &mut Store, account: &str, revision: i64, state: &str) -> Result<()> {
    let tx = store.write(account, revision)?;
    // The server cannot say what changed since our state. What can have changed is still only
    // what can change at all; subjects, bodies and headers fetched before remain true.
    tx.execute(
        "UPDATE mail_emails SET coverage=json_remove(coverage,'$.keywords','$.mailboxIds') WHERE account=?1",
        [account],
    )?;
    tx.execute(
        "UPDATE mail_queries SET stale=1 WHERE account=?1",
        [account],
    )?;
    set_state(&tx, account, "Email", state)?;
    tx.commit()?;
    Ok(())
}

fn changes(client: &Client, session: &Session, store: &mut Store, account: &str) -> Result<()> {
    if store.state(account, "Email")?.is_none() {
        let revision = store.revision(account)?;
        let state = checkpoint(client, session, account)?;
        invalidate(store, account, revision, &state)?;
        return Ok(());
    }
    for _ in 0..32 {
        let revision = store.revision(account)?;
        let since = store.state(account, "Email")?.ok_or("missing checkpoint")?;
        let batch = call(
            client,
            session,
            "Email/changes",
            json!({"accountId":account,"sinceState":since,"maxChanges":256}),
        )?;
        if batch.method_responses.len() == 1
            && batch.method_responses[0].call_id == "mail"
            && batch.method_responses[0].error_type() == Some("cannotCalculateChanges")
        {
            let state = checkpoint(client, session, account)?;
            invalidate(store, account, revision, &state)?;
            return Ok(());
        }
        let result = arguments(&batch, "Email/changes", account)?;
        if string(&result, "oldState")? != since {
            return Err("changes oldState mismatch".into());
        }
        let state = string(&result, "newState")?;
        let more = result["hasMoreChanges"]
            .as_bool()
            .ok_or("missing hasMoreChanges")?;
        if more && state == since {
            return Err("changes made no progress".into());
        }
        let mut changed = BTreeSet::new();
        let mut destroyed = BTreeSet::new();
        for key in ["created", "updated", "destroyed"] {
            for id in result[key].as_array().ok_or("invalid changes ids")? {
                let id = id.as_str().ok_or("invalid changed id")?.to_owned();
                if key == "destroyed" {
                    destroyed.insert(id);
                } else {
                    changed.insert(id);
                }
            }
        }
        let tx = store.write(account, revision)?;
        for id in changed {
            tx.execute(
                "UPDATE mail_emails SET coverage=json_remove(coverage,'$.keywords','$.mailboxIds') WHERE account=?1 AND id=?2",
                params![account, id],
            )?;
        }
        for id in destroyed {
            let known: bool = tx.query_row("SELECT EXISTS(SELECT 1 FROM mail_emails WHERE account=?1 AND id=?2) OR EXISTS(SELECT 1 FROM mail_query_items WHERE account=?1 AND email_id=?2)",params![account,id],|r|r.get(0))?;
            if known {
                unavailable(&tx, account, &id, "destroyed")?;
            }
        }
        // The visible window is refreshed independently even when the object delta is empty.
        tx.execute(
            "UPDATE mail_queries SET stale=1 WHERE account=?1",
            [account],
        )?;
        set_state(&tx, account, "Email", state)?;
        tx.commit()?;
        if !more {
            return Ok(());
        }
    }
    Err("changes backlog checkpointed; rerun to continue".into())
}

fn put_mailbox(tx: &Transaction<'_>, account: &str, doc: &Value) -> Result<()> {
    tx.execute(
        "INSERT INTO mail_mailboxes VALUES(?1,?2,?3,?4) ON CONFLICT(account,id) DO UPDATE SET document=excluded.document,role=excluded.role",
        params![account, string(doc, "id")?, doc.to_string(), doc["role"].as_str()],
    )?;
    Ok(())
}

/// A mailbox that is gone takes its listings with it. The messages stay: whether they were moved,
/// deleted with it, or never in it is Email/changes' to say, not this.
fn drop_mailbox(tx: &Transaction<'_>, account: &str, id: &str) -> Result<()> {
    tx.execute(
        "DELETE FROM mail_mailboxes WHERE account=?1 AND id=?2",
        params![account, id],
    )?;
    tx.execute(
        "DELETE FROM mail_queries WHERE account=?1 AND mailbox_id=?2",
        params![account, id],
    )?;
    Ok(())
}

/// Replaces every cached mailbox with what the server has now. Used for the first sync and whenever
/// the server cannot say what changed since our state.
fn mailbox_snapshot(client: &Client, session: &Session, store: &mut Store, account: &str) -> Result<()> {
    let revision = store.revision(account)?;
    let boxes = mailboxes(client, session, account)?;
    let rows = boxes["list"].as_array().ok_or("Mailbox/get has no list")?;
    if !boxes["notFound"].as_array().is_some_and(Vec::is_empty) {
        return Err("incomplete Mailbox snapshot".into());
    }
    let mut seen = BTreeSet::new();
    for doc in rows {
        if !seen.insert(string(doc, "id")?) {
            return Err("duplicate Mailbox id".into());
        }
    }
    let tx = store.write(account, revision)?;
    let cached: Vec<String> = tx
        .prepare("SELECT id FROM mail_mailboxes WHERE account=?1")?
        .query_map([account], |r| r.get(0))?
        .collect::<std::result::Result<_, _>>()?;
    for id in cached.iter().filter(|id| !seen.contains(id.as_str())) {
        drop_mailbox(&tx, account, id)?;
    }
    for doc in rows {
        put_mailbox(&tx, account, doc)?;
    }
    set_state(&tx, account, "Mailbox", string(&boxes, "state")?)?;
    tx.commit()?;
    Ok(())
}

fn change_ids(result: &Value, key: &str) -> Result<Vec<String>> {
    result[key]
        .as_array()
        .ok_or_else(|| format!("Mailbox/changes has no {key}"))?
        .iter()
        .map(|id| Ok(id.as_str().ok_or("invalid Mailbox/changes id")?.to_owned()))
        .collect()
}

/// Brings the cached mailboxes up to the server's Mailbox state.
///
/// Mailbox/changes names what was created, updated and destroyed; only those are fetched. When a
/// message arrives or is read, the only thing that changes on its mailbox is a count, and the server
/// may say so with updatedProperties (RFC 8621 §2.2). Then only those properties are asked for and
/// merged into the cached document — but only for mailboxes already cached; one we have never seen
/// is fetched whole, since there is nothing to merge into.
///
/// Each page commits on its own with its newState, so a long backlog advances in steps and a failure
/// leaves the last committed state, never a state ahead of the rows. A mailbox that disappears
/// between Mailbox/changes and Mailbox/get is treated as destroyed.
fn mailbox_changes(client: &Client, session: &Session, store: &mut Store, account: &str) -> Result<()> {
    if store.state(account, "Mailbox")?.is_none() {
        return mailbox_snapshot(client, session, store, account);
    }
    let size = limit(session, "maxObjectsInGet")?;
    for _ in 0..32 {
        let revision = store.revision(account)?;
        let since = store.state(account, "Mailbox")?.ok_or("missing Mailbox checkpoint")?;
        let batch = call(
            client,
            session,
            "Mailbox/changes",
            json!({"accountId":account,"sinceState":since,"maxChanges":256}),
        )?;
        if batch.method_responses.len() == 1
            && batch.method_responses[0].call_id == "mail"
            && batch.method_responses[0].error_type() == Some("cannotCalculateChanges")
        {
            return mailbox_snapshot(client, session, store, account);
        }
        let result = arguments(&batch, "Mailbox/changes", account)?;
        if string(&result, "oldState")? != since {
            return Err("Mailbox/changes oldState mismatch".into());
        }
        let state = string(&result, "newState")?.to_owned();
        let more = result["hasMoreChanges"]
            .as_bool()
            .ok_or("Mailbox/changes has no hasMoreChanges")?;
        if more && state == since {
            return Err("Mailbox/changes made no progress".into());
        }
        let only: Option<Vec<String>> = match result.get("updatedProperties") {
            None | Some(Value::Null) => None,
            Some(Value::Array(names)) => Some(
                names
                    .iter()
                    .map(|n| Ok(n.as_str().ok_or("invalid updatedProperties")?.to_owned()))
                    .collect::<Result<_>>()?,
            ),
            Some(_) => return Err("invalid updatedProperties".into()),
        };
        let mut whole = change_ids(&result, "created")?;
        let mut partial = Vec::new();
        for id in change_ids(&result, "updated")? {
            if only.is_some() && store.mailbox(account, &id)?.is_some() {
                partial.push(id);
            } else {
                whole.push(id);
            }
        }
        let mut gone: BTreeSet<String> = change_ids(&result, "destroyed")?.into_iter().collect();
        let mut fetch = |ids: &[String], properties: Vec<&str>| -> Result<Vec<Value>> {
            let mut docs = Vec::new();
            for chunk in ids.chunks(size) {
                let batch = call(
                    client,
                    session,
                    "Mailbox/get",
                    json!({"accountId":account,"ids":chunk,"properties":properties}),
                )?;
                let got = arguments(&batch, "Mailbox/get", account)?;
                validate_get(&got, chunk)?;
                docs.extend(got["list"].as_array().into_iter().flatten().cloned());
                for id in got["notFound"].as_array().into_iter().flatten() {
                    gone.insert(id.as_str().ok_or("invalid notFound")?.to_owned());
                }
            }
            Ok(docs)
        };
        let full = fetch(&whole, MAILBOX_PROPERTIES.to_vec())?;
        // Only what the cache keeps. Stalwart names totalThreads and unreadThreads here too; merging
        // those would leave a delta-updated mailbox shaped differently from a snapshot one. When
        // nothing the cache keeps changed, there is nothing to fetch.
        let mut properties = vec!["id"];
        properties.extend(
            only.iter()
                .flatten()
                .map(String::as_str)
                .filter(|name| *name != "id" && MAILBOX_PROPERTIES.contains(name)),
        );
        let counts = if properties.len() > 1 {
            fetch(&partial, properties)?
        } else {
            Vec::new()
        };
        let tx = store.write(account, revision)?;
        for doc in &full {
            put_mailbox(&tx, account, doc)?;
        }
        for update in &counts {
            let id = string(update, "id")?;
            let raw: String = tx.query_row(
                "SELECT document FROM mail_mailboxes WHERE account=?1 AND id=?2",
                params![account, id],
                |r| r.get(0),
            )?;
            let mut doc: Value = serde_json::from_str(&raw)?;
            for (key, value) in update.as_object().ok_or("invalid Mailbox")? {
                doc[key] = value.clone();
            }
            put_mailbox(&tx, account, &doc)?;
        }
        for id in &gone {
            drop_mailbox(&tx, account, id)?;
        }
        set_state(&tx, account, "Mailbox", &state)?;
        tx.commit()?;
        if !more {
            return Ok(());
        }
    }
    Err("Mailbox/changes backlog checkpointed; rerun to continue".into())
}

/// Which listing properties of a message the cache does not hold — every one of them for a message
/// it has never seen. Email/changes clears only the mutable ones of a message it reports updated, so
/// for a message that was merely read or flagged this is just keywords and mailboxIds.
fn lacking(store: &Store, account: &str, id: &str) -> Result<Vec<&'static str>> {
    let coverage: Option<String> = store
        .db
        .query_row(
            "SELECT coverage FROM mail_emails WHERE account=?1 AND id=?2",
            params![account, id],
            |r| r.get(0),
        )
        .optional()?;
    let Some(coverage) = coverage else {
        return Ok(LIST_PROPERTIES.to_vec());
    };
    let coverage: Value = serde_json::from_str(&coverage)?;
    Ok(LIST_PROPERTIES
        .iter()
        .copied()
        .filter(|key| coverage.get(*key).is_none())
        .collect())
}

/// Fetches what the cache lacks for these messages, and no more.
///
/// A message lacking only its mutable properties — one that was read, flagged or moved — is asked for
/// those alone; anything else is fetched with every listing property. The two kinds go in separate
/// Email/get calls, each chunked by maxObjectsInGet.
fn fetch_lacking(
    client: &Client,
    session: &Session,
    store: &Store,
    account: &str,
    ids: &[String],
) -> Result<Vec<Value>> {
    let mut whole = Vec::new();
    let mut flags = Vec::new();
    for id in ids {
        let gaps = lacking(store, account, id)?;
        if gaps.is_empty() {
            continue;
        }
        if gaps.iter().all(|key| MUTABLE_PROPERTIES.contains(key)) {
            flags.push(id.clone());
        } else {
            whole.push(id.clone());
        }
    }
    let mut mutable = vec!["id"];
    mutable.extend_from_slice(MUTABLE_PROPERTIES);
    let mut gets = Vec::new();
    for (ids, properties) in [(&whole, LIST_PROPERTIES), (&flags, mutable.as_slice())] {
        for chunk in ids.chunks(limit(session, "maxObjectsInGet")?) {
            let batch = call(
                client,
                session,
                "Email/get",
                json!({"accountId":account,"ids":chunk,"properties":properties}),
            )?;
            let get = arguments(&batch, "Email/get", account)?;
            validate_get(&get, chunk)?;
            gets.push(get);
        }
    }
    Ok(gets)
}

/// Brings a cached listing forward with Email/queryChanges instead of reading the window again.
///
/// Membership and order come from the query delta; message contents come from Email/changes, which
/// already ran. So only members whose listing properties the cache does not hold are fetched — a new
/// arrival, or a message Email/changes marked as changed.
///
/// `None` sends the caller back to reading the whole window: no cached listing, different arguments
/// (the server's maxObjectsInGet moved, say), a query the server said it cannot track, a delta it
/// cannot compute, or a top-up page that does not line up with the delta's state. Each of those is
/// an ordinary reason to start over, not an error.
fn advance(
    client: &Client,
    session: &Session,
    store: &Store,
    account: &str,
    mailbox: &str,
    listing: Rows,
) -> Result<Option<Window>> {
    let size = window_size(session)?;
    let key = query_key(mailbox, listing);
    let args = query_args(account, mailbox, size, listing);
    let cached: Option<(String, String)> = store
        .db
        .query_row(
            "SELECT arguments,document FROM mail_queries WHERE account=?1 AND query_key=?2",
            params![account, key],
            |r| Ok((r.get(0)?, r.get(1)?)),
        )
        .optional()?;
    let Some((old_args, document)) = cached else {
        return Ok(None);
    };
    let document: Value = serde_json::from_str(&document)?;
    if serde_json::from_str::<Value>(&old_args)? != args || document["canCalculateChanges"] != true {
        return Ok(None);
    }
    let Some(since) = document["queryState"].as_str() else {
        return Ok(None);
    };
    let held: Vec<String> = store
        .db
        .prepare("SELECT email_id FROM mail_query_items WHERE account=?1 AND query_key=?2 ORDER BY position")?
        .query_map(params![account, key], |r| r.get(0))?
        .collect::<std::result::Result<_, _>>()?;
    let Some(delta) = query_changes(client, session, &args, since)? else {
        return Ok(None);
    };
    // A listing scrolled past its first window keeps the length it has: the next delta must not cut
    // the pages a reader already loaded back to one window.
    let length = size.max(held.len());
    let mut rows = apply_query_delta(&held, &delta, length);
    if rows.len() < length {
        // Rows above the end were removed, so rows from past the old window slid up into it. The delta
        // does not name them; ask for exactly that stretch, at the delta's own state.
        let mut page = args.clone();
        page["position"] = json!(rows.len());
        page["limit"] = json!(length - rows.len());
        let batch = call(client, session, "Email/query", page)?;
        let page = arguments(&batch, "Email/query", account)?;
        if page["queryState"].as_str() != Some(delta.new_state.as_str())
            || page["position"].as_u64() != Some(rows.len() as u64)
        {
            return Ok(None);
        }
        let more = query_ids(&page, length - rows.len())?;
        if more.iter().any(|id| rows.contains(id)) {
            return Ok(None);
        }
        rows.extend(more);
    }
    let gets = fetch_lacking(client, session, store, account, &rows)?;
    let query = json!({"accountId":account,"queryState":delta.new_state,"position":0,"ids":rows,"canCalculateChanges":true,"total":delta.total});
    Ok(Some(Window {
        key,
        args,
        query,
        gets,
    }))
}

/// Whether the Email/get answers for a listing contradict the query that listed them: a message the
/// query named is gone, or is no longer in the listing's mailbox. Query and get are two steps, and a
/// message can be deleted or moved between them; the listing then holds a row it cannot show and
/// must be marked stale rather than presented as current. Every path that writes listing rows uses
/// this one test.
fn contradicted(gets: &[Value], mailbox: &str) -> bool {
    gets.iter().any(|result| {
        result["notFound"].as_array().is_some_and(|v| !v.is_empty())
            || result["list"].as_array().is_some_and(|rows| {
                rows.iter().any(|doc| {
                    doc["mailboxIds"]
                        .as_object()
                        .is_some_and(|ids| ids.get(mailbox) != Some(&Value::Bool(true)))
                })
            })
    })
}

/// Loads the next page of a cached listing and appends it.
///
/// The page is asked for by position at the listing's own queryState. If the server answers at
/// another state, the listing changed since it was read; the delta sync runs first and the page is
/// asked for again, once. A second mismatch is reported rather than chased, since appending rows from
/// one state to rows from another would put a message in the wrong place or show it twice.
pub fn more(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    requested: Option<&str>,
    listing: Rows,
) -> Result<Value> {
    choose_account(session, Some(account))?;
    store.bind(client, session)?;
    let mailbox = store.mailbox_id(account, requested)?;
    let key = query_key(&mailbox, listing);
    let size = window_size(session)?;
    for attempt in 0..2 {
        let revision = store.revision(account)?;
        let cached: Option<(String, String)> = store
            .db
            .query_row(
                "SELECT arguments,document FROM mail_queries WHERE account=?1 AND query_key=?2",
                params![account, key],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .optional()?;
        let Some((args, document)) = cached else {
            return Err("no cached listing to extend; sync it first".into());
        };
        let (mut page, mut document): (Value, Value) =
            (serde_json::from_str(&args)?, serde_json::from_str(&document)?);
        let state = string(&document, "queryState")?.to_owned();
        let held: Vec<String> = store
            .db
            .prepare("SELECT email_id FROM mail_query_items WHERE account=?1 AND query_key=?2 ORDER BY position")?
            .query_map(params![account, key], |r| r.get(0))?
            .collect::<std::result::Result<_, _>>()?;
        page["position"] = json!(held.len());
        page["limit"] = json!(size);
        page["calculateTotal"] = json!(true);
        let batch = call(client, session, "Email/query", page)?;
        let answer = arguments(&batch, "Email/query", account)?;
        if answer["queryState"].as_str() != Some(state.as_str()) {
            if attempt == 0 {
                sync(client, session, store, account, Some(&mailbox), listing)?;
                continue;
            }
            return Err("listing keeps changing while paging; sync and try again".into());
        }
        if answer["position"].as_u64() != Some(held.len() as u64) {
            return Err("Email/query answered a different position than asked".into());
        }
        let ids = query_ids(&answer, size)?;
        if ids.iter().any(|id| held.contains(id)) {
            return Err("next page repeats a message already listed".into());
        }
        let gets = fetch_lacking(client, session, store, account, &ids)?;
        let stale = contradicted(&gets, &mailbox);
        let mut all = held.clone();
        all.extend(ids.iter().cloned());
        document["ids"] = json!(all);
        if let Some(total) = answer.get("total").and_then(Value::as_u64) {
            document["total"] = json!(total);
        }
        let tx = store.write(account, revision)?;
        for result in gets {
            save_get(&tx, account, &result, false)?;
        }
        for (offset, id) in ids.iter().enumerate() {
            tx.execute(
                "INSERT INTO mail_query_items VALUES(?1,?2,?3,?4)",
                params![account, key, (held.len() + offset) as i64, id],
            )?;
        }
        // Stale stays set once set: a page that is consistent does not make earlier rows current.
        tx.execute(
            "UPDATE mail_queries SET document=?3, stale=MAX(stale,?4) WHERE account=?1 AND query_key=?2",
            params![account, key, document.to_string(), stale],
        )?;
        tx.commit()?;
        return store.view(account, Some(&mailbox), listing);
    }
    unreachable!()
}

pub fn sync(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    requested: Option<&str>,
    listing: Rows,
) -> Result<Value> {
    choose_account(session, Some(account))?;
    store.bind(client, session)?;
    changes(client, session, store, account)?;
    mailbox_changes(client, session, store, account)?;
    let revision = store.revision(account)?;
    let mailbox = store.mailbox_id(account, requested)?;
    let selected = store
        .mailbox(account, &mailbox)?
        .ok_or("mailbox is not accessible")?;
    if selected.pointer("/myRights/mayReadItems") == Some(&Value::Bool(false)) {
        return Err("mailbox does not permit reading".into());
    }
    let Window {
        key,
        args,
        query,
        gets,
    } = match advance(client, session, store, account, &mailbox, listing)? {
        Some(advanced) => advanced,
        None => window(client, session, account, &mailbox, listing)?,
    };
    string(&query, "queryState")?;
    if query["position"].as_u64() != Some(0) {
        return Err("unexpected query position".into());
    }
    // Already bounded where it was read: a window by its limit, an advanced listing by its length.
    let ids = query_ids(&query, usize::MAX)?;
    let missing = contradicted(&gets, &mailbox);
    let tx = store.write(account, revision)?;
    for result in gets {
        save_get(&tx, account, &result, false)?;
    }
    tx.execute(
        "UPDATE mail_queries SET stale=1 WHERE account=?1",
        [account],
    )?;
    tx.execute("INSERT INTO mail_queries VALUES(?1,?2,?3,?4,?5,?6) ON CONFLICT(account,query_key) DO UPDATE SET mailbox_id=excluded.mailbox_id,arguments=excluded.arguments,document=excluded.document,stale=excluded.stale",params![account,key,mailbox,args.to_string(),query.to_string(),missing])?;
    tx.execute(
        "DELETE FROM mail_query_items WHERE account=?1 AND query_key=?2",
        params![account, key],
    )?;
    for (position, id) in ids.iter().enumerate() {
        tx.execute(
            "INSERT INTO mail_query_items VALUES(?1,?2,?3,?4)",
            params![account, key, position as i64, id],
        )?;
    }
    tx.commit()?;
    store.view(account, Some(&mailbox), listing)
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::{CORE_CAPABILITY, Credentials, mail::CAPABILITY};

    fn session(api: &str) -> Session {
        serde_json::from_value(json!({"apiUrl":api,"capabilities":{CORE_CAPABILITY:{"maxCallsInRequest":1,"maxObjectsInGet":2,"maxSizeRequest":100000},CAPABILITY:{}},"accounts":{"a":{"accountCapabilities":{CAPABILITY:{}}}}})).unwrap()
    }

    fn client() -> Client {
        Client::new(Credentials {
            user: "fixture".into(),
            secret: "test-only".into(),
        })
    }

    fn temp() -> std::path::PathBuf {
        std::env::temp_dir().join(format!(
            "ax-mail-cache-{}-{}.sqlite",
            std::process::id(),
            std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)
                .unwrap()
                .as_nanos()
        ))
    }

    fn remove(path: &Path) {
        for suffix in ["", "-wal", "-shm"] {
            let _ = std::fs::remove_file(format!("{}{suffix}", path.display()));
        }
    }

    #[test]
    fn contacts_coexistence_reopen_profile_binding_and_unknown_version() {
        let path = temp();
        {
            let _contacts = crate::contacts::Store::open(&path, "p").unwrap();
            let raw = Connection::open(&path).unwrap();
            raw.execute(
                "INSERT INTO records VALUES('a','ContactCard','c','{\"id\":\"c\"}','C')",
                [],
            )
            .unwrap();
            raw.execute(
                "INSERT INTO pending_updates VALUES('a','c','old','{}','pending')",
                [],
            )
            .unwrap();
            let store = Store::open(&path, "p").unwrap();
            store
                .bind(&client(), &session("https://one.test/jmap"))
                .unwrap();
            let other = Client::new(Credentials {
                user: "another-user".into(),
                secret: "test-only".into(),
            });
            assert!(
                store
                    .bind(&other, &session("https://one.test/jmap"))
                    .is_err()
            );
            assert!(
                store
                    .bind(&client(), &session("https://two.test/jmap"))
                    .is_err()
            );
            assert!(Store::open(&path, "other").is_err());
        }
        {
            let contacts = crate::contacts::Store::open(&path, "p").unwrap();
            assert_eq!(contacts.list("a", "ContactCard").unwrap().len(), 1);
            assert_eq!(contacts.pending_count("a").unwrap(), 1);
            let store = Store::open(&path, "p").unwrap();
            assert_eq!(store.email("a", "none").unwrap()["reason"], "notCached");
        }
        {
            let raw = Connection::open(&path).unwrap();
            raw.execute(
                "UPDATE metadata SET value='999' WHERE key='mail_schema_version'",
                [],
            )
            .unwrap();
        }
        assert!(Store::open(&path, "p").is_err());
        remove(&path);
    }

    #[test]
    fn partial_merge_coverage_null_and_account_isolation() {
        let mut store = Store::open(":memory:", "p").unwrap();
        let revision = store.revision("a").unwrap();
        let tx = store.write("a", revision).unwrap();
        merge_email(&tx,"a",&json!({"id":"e","subject":"Old","vendor":{"x":1},"mailboxIds":{"in":true,"sent":true},"bodyValues":{"part":{"value":"abc","isTruncated":true}}}),true).unwrap();
        merge_email(&tx, "a", &json!({"id":"e","subject":null}), false).unwrap();
        tx.commit().unwrap();
        let got = store.email("a", "e").unwrap();
        assert!(got["document"]["subject"].is_null());
        assert_eq!(got["document"]["vendor"]["x"], 1);
        assert_eq!(
            got["coverage"]["bodyValues"]["parts"]["part"]["isTruncated"],
            true
        );
        assert!(
            got["coverage"]["bodyValues"]["parts"]["part"]
                .get("value")
                .is_none()
        );
        assert_eq!(store.email("b", "e").unwrap()["available"], false);
        let n: i64 = store
            .db
            .query_row("SELECT COUNT(*) FROM mail_email_mailboxes", [], |r| {
                r.get(0)
            })
            .unwrap();
        assert_eq!(n, 2);
        let revision = store.revision("a").unwrap();
        invalidate(&mut store, "a", revision, "s1").unwrap();
        let got = store.email("a", "e").unwrap();
        // Only what an Email can change goes stale; the subject and body fetched before stay true.
        assert_eq!(got["staleProperties"], json!(["mailboxIds"]));
        assert!(got["coverage"].get("bodyValues").is_some());
        assert!(got["coverage"].get("subject").is_some());
    }

    #[test]
    fn transaction_rollback_revision_guard_and_destroyed_membership() {
        let mut store = Store::open(":memory:", "p").unwrap();
        let revision = store.revision("a").unwrap();
        {
            let tx = store.write("a", revision).unwrap();
            set_state(&tx, "a", "Email", "bad").unwrap();
            merge_email(&tx, "a", &json!({"id":"e","mailboxIds":{"in":true}}), false).unwrap();
            assert!(merge_email(&tx, "a", &json!({"missing":"id"}), false).is_err());
        }
        assert!(store.state("a", "Email").unwrap().is_none());
        assert_eq!(store.email("a", "e").unwrap()["available"], false);
        let tx = store.write("a", revision).unwrap();
        merge_email(&tx, "a", &json!({"id":"e","mailboxIds":{"in":true}}), false).unwrap();
        tx.commit().unwrap();
        assert!(store.write("a", revision).is_err());
        let revision = store.revision("a").unwrap();
        let tx = store.write("a", revision).unwrap();
        unavailable(&tx, "a", "e", "destroyed").unwrap();
        tx.commit().unwrap();
        assert_eq!(store.email("a", "e").unwrap()["reason"], "destroyed");
        let n: i64 = store
            .db
            .query_row("SELECT COUNT(*) FROM mail_email_mailboxes", [], |r| {
                r.get(0)
            })
            .unwrap();
        assert_eq!(n, 0);
    }

    fn mock(responses: Vec<(&'static str, Value)>) -> (Session, std::thread::JoinHandle<()>) {
        let (session, server, _) = mock_recorded(responses);
        (session, server)
    }

    type Recorded = std::sync::Arc<std::sync::Mutex<Vec<Value>>>;

    /// Like `mock`, and also keeps the arguments of every call it answered, so a test can check what
    /// was sent and not only what came back.
    fn mock_recorded(
        responses: Vec<(&'static str, Value)>,
    ) -> (Session, std::thread::JoinHandle<()>, Recorded) {
        use std::{
            io::{BufRead, BufReader, Read, Write},
            net::TcpListener,
            thread,
            time::{Duration, Instant},
        };
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        listener.set_nonblocking(true).unwrap();
        let session = session(&format!("http://{}", listener.local_addr().unwrap()));
        let recorded: Recorded = Default::default();
        let sent = recorded.clone();
        let server = thread::spawn(move || {
            for (method, response) in responses {
                let deadline = Instant::now() + Duration::from_secs(5);
                let mut stream = loop {
                    match listener.accept() {
                        Ok((stream, _)) => break stream,
                        Err(e) if e.kind() == std::io::ErrorKind::WouldBlock => {
                            assert!(Instant::now() < deadline, "missing request {method}");
                            thread::sleep(Duration::from_millis(10));
                        }
                        Err(e) => panic!("{e}"),
                    }
                };
                // On Windows an accepted socket inherits the listener's non-blocking mode, and a read
                // that runs ahead of the request's bytes then fails with WouldBlock instead of waiting.
                stream.set_nonblocking(false).unwrap();
                stream
                    .set_read_timeout(Some(Duration::from_secs(5)))
                    .unwrap();
                let mut reader = BufReader::new(&mut stream);
                let mut len = 0;
                loop {
                    let mut line = String::new();
                    reader.read_line(&mut line).unwrap();
                    assert!(!line.is_empty());
                    if line == "\r\n" {
                        break;
                    }
                    if let Some(v) = line.to_ascii_lowercase().strip_prefix("content-length:") {
                        len = v.trim().parse().unwrap();
                    }
                }
                let mut bytes = vec![0; len];
                reader.read_exact(&mut bytes).unwrap();
                let request: Value = serde_json::from_slice(&bytes).unwrap();
                sent.lock()
                    .unwrap()
                    .push(request["methodCalls"][0][1].clone());
                assert_eq!(request["methodCalls"][0][0], method);
                // A window asks for the whole window; a top-up page asks for the stretch it lacks.
                if method == "Email/query" && request["methodCalls"][0][1]["position"] == 0 {
                    assert_eq!(request["methodCalls"][0][1]["limit"], 2);
                }
                if method == "Email/get" {
                    assert!(
                        request["methodCalls"][0][1]["ids"]
                            .as_array()
                            .unwrap()
                            .len()
                            <= 2
                    );
                }
                let name = if response.get("type").is_some() {
                    "error"
                } else {
                    method
                };
                let body = json!({"methodResponses":[[name,response,"mail"]]}).to_string();
                write!(stream,"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}",body.len(),body).unwrap();
            }
        });
        (session, server, recorded)
    }

    fn boxes() -> Value {
        json!({"accountId":"a","state":"boxes","list":[{"id":"in","name":"Localized Inbox","role":"inbox","myRights":{"mayReadItems":true}}],"notFound":[]})
    }
    fn unchanged() -> Value {
        json!({"accountId":"a","oldState":"boxes","newState":"boxes","hasMoreChanges":false,"created":[],"updated":[],"destroyed":[]})
    }
    fn query(ids: Value, state: &str) -> Value {
        json!({"accountId":"a","position":0,"queryState":state,"ids":ids})
    }
    fn get(list: Value, state: &str) -> Value {
        json!({"accountId":"a","state":state,"list":list,"notFound":[]})
    }

    #[test]
    fn delta_pages_query_order_stale_body_expiry_and_query_exit() {
        let (session, server) = mock(vec![
            ("Email/get", get(json!([]), "seed")),
            ("Mailbox/get", boxes()),
            ("Email/query", query(json!(["2", "1"]), "q1")),
            (
                "Email/get",
                get(
                    json!([{"id":"1","subject":"one"},{"id":"2","subject":"two"}]),
                    "get1",
                ),
            ),
            (
                "Email/get",
                get(
                    json!([{"id":"2","bodyValues":{"text":{"value":"body","isTruncated":false}}}]),
                    "get1",
                ),
            ),
            (
                "Email/changes",
                json!({"accountId":"a","oldState":"seed","newState":"s1","hasMoreChanges":true,"created":[],"updated":["2"],"destroyed":["1"]}),
            ),
            (
                "Email/changes",
                json!({"accountId":"a","oldState":"s1","newState":"s2","hasMoreChanges":false,"created":["3"],"updated":[],"destroyed":[]}),
            ),
            ("Mailbox/changes", unchanged()),
            ("Email/query", query(json!(["3", "2"]), "q2")),
            (
                "Email/get",
                get(
                    json!([{"id":"2","subject":"updated"},{"id":"3","subject":"new"}]),
                    "unrelated-get-state",
                ),
            ),
            (
                "Email/changes",
                json!({"accountId":"a","oldState":"s2","newState":"s3","hasMoreChanges":false,"created":[],"updated":["2","3"],"destroyed":[]}),
            ),
            ("Mailbox/changes", unchanged()),
            ("Email/query", query(json!([]), "q3")),
            ("Email/changes", json!({"type":"cannotCalculateChanges"})),
            ("Email/get", get(json!([]), "reset")),
            ("Mailbox/changes", unchanged()),
            ("Email/query", query(json!(["2"]), "q4")),
            (
                "Email/get",
                get(json!([{"id":"2","subject":"refetched"}]), "later"),
            ),
        ]);
        let path = temp();
        let mut store = Store::open(&path, "p").unwrap();
        let c = client();
        let first = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(first["emails"][0]["id"], "2");
        read(&c, &session, &mut store, "a", "2").unwrap();
        let second = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(second["emails"][0]["id"], "3");
        assert_eq!(store.state("a", "Email").unwrap().as_deref(), Some("s2"));
        assert_eq!(store.email("a", "1").unwrap()["reason"], "destroyed");
        // Message 2 was updated, and an Email update can only touch keywords and mailboxIds: the body
        // read before it is still the body.
        assert!(
            !store.email("a", "2").unwrap()["staleProperties"]
                .as_array()
                .unwrap()
                .contains(&json!("bodyValues"))
        );
        let third = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert!(third["emails"].as_array().unwrap().is_empty());
        assert_eq!(store.email("a", "2").unwrap()["available"], true);
        sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(store.state("a", "Email").unwrap().as_deref(), Some("reset"));
        drop(store);
        let store = Store::open(&path, "p").unwrap();
        assert_eq!(
            store.view("a", None, Rows::Messages).unwrap()["emails"][0]["document"]["subject"],
            "refetched"
        );
        drop(store);
        remove(&path);
        server.join().unwrap();
    }

    #[test]
    fn incomplete_fetch_preserves_dirty_checkpoint_and_old_query() {
        let (session, server) = mock(vec![
            ("Email/get", get(json!([]), "seed")),
            ("Mailbox/get", boxes()),
            ("Email/query", query(json!(["1"]), "q1")),
            (
                "Email/get",
                get(json!([{"id":"1","subject":"original"}]), "s1"),
            ),
            (
                "Email/changes",
                json!({"accountId":"a","oldState":"seed","newState":"s2","hasMoreChanges":false,"created":[],"updated":["1"],"destroyed":[]}),
            ),
            ("Mailbox/changes", unchanged()),
            ("Email/query", query(json!(["1", "2"]), "q2")),
            (
                "Email/get",
                get(json!([{"id":"1","subject":"do-not-commit"}]), "s3"),
            ),
        ]);
        let c = client();
        let mut store = Store::open(":memory:", "p").unwrap();
        sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert!(sync(&c, &session, &mut store, "a", None, Rows::Messages).is_err());
        assert_eq!(store.state("a", "Email").unwrap().as_deref(), Some("s2"));
        let view = store.view("a", None, Rows::Messages).unwrap();
        assert_eq!(view["query"]["queryState"], "q1");
        assert_eq!(view["queryStale"], true);
        assert_eq!(view["emails"][0]["document"]["subject"], "original");
        // The refetch failed, so nothing from it was kept: the coverage is what the first sync had,
        // which here never included the mutable properties.
        assert_eq!(view["emails"][0]["coverage"], json!({"id":true,"subject":true}));
        server.join().unwrap();
    }

    /// A message listing and a thread listing of one mailbox are two queries. Under v1 they shared a
    /// key, so the second sync replaced the first and the message listing came back with the thread
    /// listing's rows in it.
    #[test]
    fn message_and_thread_listings_of_one_mailbox_are_kept_apart() {
        let (session, server, sent) = mock_recorded(vec![
            ("Email/get", get(json!([]), "seed")),
            ("Mailbox/get", boxes()),
            ("Email/query", query(json!(["m1", "m2"]), "q-messages")),
            (
                "Email/get",
                get(
                    json!([{"id":"m1","subject":"one","threadId":"t1"},{"id":"m2","subject":"two","threadId":"t1"}]),
                    "s1",
                ),
            ),
            (
                "Email/changes",
                json!({"accountId":"a","oldState":"seed","newState":"seed","hasMoreChanges":false,"created":[],"updated":[],"destroyed":[]}),
            ),
            ("Mailbox/changes", unchanged()),
            ("Email/query", query(json!(["m2"]), "q-threads")),
            (
                "Email/get",
                get(json!([{"id":"m2","subject":"two","threadId":"t1"}]), "s1"),
            ),
        ]);
        let c = client();
        let mut store = Store::open(":memory:", "p").unwrap();
        sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        sync(&c, &session, &mut store, "a", None, Rows::Threads).unwrap();
        server.join().unwrap();

        let ids = |view: &Value| -> Vec<String> {
            view["emails"]
                .as_array()
                .unwrap()
                .iter()
                .map(|e| e["id"].as_str().unwrap().to_owned())
                .collect()
        };
        let messages = store.view("a", None, Rows::Messages).unwrap();
        let threads = store.view("a", None, Rows::Threads).unwrap();
        assert_eq!(ids(&messages), ["m1", "m2"]);
        assert_eq!(ids(&threads), ["m2"]);
        assert_eq!(messages["query"]["queryState"], "q-messages");
        assert_eq!(threads["query"]["queryState"], "q-threads");

        // The thread listing really asked the server to collapse; the message listing did not.
        let collapse: Vec<Value> = sent
            .lock()
            .unwrap()
            .iter()
            .filter(|args| args.get("filter").is_some())
            .map(|args| args["collapseThreads"].clone())
            .collect();
        assert_eq!(collapse, [json!(false), json!(true)]);
    }

    #[test]
    fn mailbox_delta_merges_counts_drops_the_destroyed_and_falls_back_to_a_snapshot() {
        let mailbox = |id: &str, name: &str, role: Value| {
            json!({"id":id,"name":name,"role":role,"myRights":{"mayReadItems":true},"totalEmails":1,"unreadEmails":1})
        };
        let quiet = json!({"accountId":"a","oldState":"seed","newState":"seed","hasMoreChanges":false,"created":[],"updated":[],"destroyed":[]});
        let (session, server, sent) = mock_recorded(vec![
            // 1: snapshot, listing of "old".
            ("Email/get", get(json!([]), "seed")),
            (
                "Mailbox/get",
                json!({"accountId":"a","state":"b0","list":[mailbox("in","Localized Inbox",json!("inbox")),mailbox("arch","Archive",json!("archive")),mailbox("old","Old",Value::Null)],"notFound":[]}),
            ),
            ("Email/query", query(json!(["1"]), "q-old")),
            ("Email/get", get(json!([{"id":"1","subject":"kept"}]), "s")),
            // 2: two pages of Mailbox changes.
            ("Email/changes", quiet.clone()),
            (
                "Mailbox/changes",
                json!({"accountId":"a","oldState":"b0","newState":"b1","hasMoreChanges":true,"created":["new"],"updated":["in"],"destroyed":["old"],"updatedProperties":["totalEmails","unreadEmails","totalThreads","unreadThreads"]}),
            ),
            (
                "Mailbox/get",
                json!({"accountId":"a","state":"b1","list":[mailbox("new","New",Value::Null)],"notFound":[]}),
            ),
            (
                "Mailbox/get",
                json!({"accountId":"a","state":"b1","list":[{"id":"in","totalEmails":5,"unreadEmails":2}],"notFound":[]}),
            ),
            (
                "Mailbox/changes",
                json!({"accountId":"a","oldState":"b1","newState":"b2","hasMoreChanges":false,"created":[],"updated":["arch"],"destroyed":[],"updatedProperties":null}),
            ),
            // Gone between Mailbox/changes and Mailbox/get.
            (
                "Mailbox/get",
                json!({"accountId":"a","state":"b2","list":[],"notFound":["arch"]}),
            ),
            ("Email/query", query(json!([]), "q-in")),
            // 3: the server cannot calculate the delta; a snapshot replaces everything.
            ("Email/changes", quiet.clone()),
            ("Mailbox/changes", json!({"type":"cannotCalculateChanges"})),
            (
                "Mailbox/get",
                json!({"accountId":"a","state":"b9","list":[mailbox("in","Renamed Inbox",json!("inbox"))],"notFound":[]}),
            ),
            ("Email/query", query(json!([]), "q-in2")),
            // 4: a fetch fails mid-page; nothing of that page is kept.
            ("Email/changes", quiet),
            (
                "Mailbox/changes",
                json!({"accountId":"a","oldState":"b9","newState":"b10","hasMoreChanges":false,"created":[],"updated":["in"],"destroyed":[]}),
            ),
            (
                "Mailbox/get",
                json!({"accountId":"other","state":"b10","list":[mailbox("in","Wrong",json!("inbox"))],"notFound":[]}),
            ),
        ]);
        let c = client();
        let mut store = Store::open(":memory:", "p").unwrap();
        sync(&c, &session, &mut store, "a", Some("old"), Rows::Messages).unwrap();
        assert_eq!(store.view("a", Some("old"), Rows::Messages).unwrap()["query"]["queryState"], "q-old");

        sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(store.state("a", "Mailbox").unwrap().as_deref(), Some("b2"));
        let inbox = store.mailbox("a", "in").unwrap().unwrap();
        assert_eq!(inbox["totalEmails"], 5);
        assert_eq!(inbox["unreadEmails"], 2);
        assert_eq!(inbox["name"], "Localized Inbox", "a count-only update keeps the rest");
        assert_eq!(store.mailbox("a", "new").unwrap().unwrap()["name"], "New");
        assert!(store.mailbox("a", "old").unwrap().is_none());
        assert!(store.mailbox("a", "arch").unwrap().is_none(), "vanished before get");
        assert_eq!(store.view("a", Some("old"), Rows::Messages).unwrap()["query"], Value::Null);
        assert_eq!(store.email("a", "1").unwrap()["available"], true, "messages outlive their mailbox");

        sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(store.state("a", "Mailbox").unwrap().as_deref(), Some("b9"));
        assert!(store.mailbox("a", "new").unwrap().is_none());
        assert_eq!(store.mailbox("a", "in").unwrap().unwrap()["name"], "Renamed Inbox");

        assert!(sync(&c, &session, &mut store, "a", None, Rows::Messages).is_err());
        assert_eq!(store.state("a", "Mailbox").unwrap().as_deref(), Some("b9"));
        assert_eq!(store.mailbox("a", "in").unwrap().unwrap()["name"], "Renamed Inbox");
        server.join().unwrap();

        // Created mailboxes are fetched whole; count-only updates ask for the counts the cache keeps,
        // not every count the server named (Stalwart names the thread counts too).
        let gets: Vec<Value> = sent
            .lock()
            .unwrap()
            .iter()
            .filter(|args| args.get("ids").is_some_and(|ids| ids.as_array().is_some_and(|ids| ids.iter().any(|id| id == "new" || id == "in"))))
            .map(|args| args["properties"].clone())
            .collect();
        assert_eq!(gets[0], json!(MAILBOX_PROPERTIES));
        assert_eq!(gets[1], json!(["id", "totalEmails", "unreadEmails"]));
    }

    #[test]
    fn query_changes_moves_a_listing_and_fetches_only_what_it_lacks() {
        // A server answers every property asked for, null when unset; the cache's coverage relies on it.
        let row = |id: &str, subject: &str| {
            let mut doc = json!({});
            for key in LIST_PROPERTIES {
                doc[*key] = Value::Null;
            }
            doc["id"] = json!(id);
            doc["subject"] = json!(subject);
            doc["mailboxIds"] = json!({"in":true});
            doc["keywords"] = json!({});
            doc
        };
        let tracked = |ids: Value, state: &str| {
            json!({"accountId":"a","position":0,"queryState":state,"canCalculateChanges":true,"ids":ids})
        };
        let email_changes = |old: &str, new: &str, created: Value, destroyed: Value| {
            json!({"accountId":"a","oldState":old,"newState":new,"hasMoreChanges":false,"created":created,"updated":[],"destroyed":destroyed})
        };
        let query_delta = |old: &str, new: &str, removed: Value, added: Value| {
            json!({"accountId":"a","oldQueryState":old,"newQueryState":new,"removed":removed,"added":added})
        };
        let (session, server, sent) = mock_recorded(vec![
            // 1: a window of two, tracked by the server.
            ("Email/get", get(json!([]), "seed")),
            ("Mailbox/get", boxes()),
            ("Email/query", tracked(json!(["2", "1"]), "q1")),
            ("Email/get", get(Value::Array(vec![row("2","two"),row("1","one")]), "s")),
            // 2: a new arrival on top. Only it is fetched; "1" slides out of the window.
            ("Email/changes", email_changes("seed", "s1", json!(["3"]), json!([]))),
            ("Mailbox/changes", unchanged()),
            ("Email/queryChanges", query_delta("q1", "q2", json!([]), json!([{"id":"3","index":0}]))),
            ("Email/get", get(Value::Array(vec![row("3","three")]), "s")),
            // 3: the top row goes; the row below the window slides up and is asked for by position.
            ("Email/changes", email_changes("s1", "s2", json!([]), json!(["3"]))),
            ("Mailbox/changes", unchanged()),
            ("Email/queryChanges", query_delta("q2", "q3", json!(["3"]), json!([]))),
            ("Email/query", json!({"accountId":"a","position":1,"queryState":"q3","ids":["1"]})),
            // 4: the top-up page answers at another state, so the window is read again whole.
            ("Email/changes", email_changes("s2", "s2", json!([]), json!([]))),
            ("Mailbox/changes", unchanged()),
            ("Email/queryChanges", query_delta("q3", "q4", json!(["2"]), json!([]))),
            ("Email/query", json!({"accountId":"a","position":1,"queryState":"q5","ids":["4"]})),
            ("Email/query", tracked(json!(["1", "4"]), "q5")),
            ("Email/get", get(Value::Array(vec![row("1","one"),row("4","four")]), "s")),
            // 5: the server cannot compute the delta; the window is read again whole.
            ("Email/changes", email_changes("s2", "s2", json!([]), json!([]))),
            ("Mailbox/changes", unchanged()),
            ("Email/queryChanges", json!({"type":"cannotCalculateChanges"})),
            ("Email/query", tracked(json!(["4"]), "q6")),
            ("Email/get", get(Value::Array(vec![row("4","four")]), "s")),
        ]);
        let c = client();
        let mut store = Store::open(":memory:", "p").unwrap();
        let ids = |view: &Value| -> Vec<String> {
            view["emails"].as_array().unwrap().iter().map(|e| e["id"].as_str().unwrap().to_owned()).collect()
        };
        sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        let second = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&second), ["3", "2"]);
        assert_eq!(second["query"]["queryState"], "q2");
        let third = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&third), ["2", "1"]);
        assert_eq!(third["query"]["queryState"], "q3");
        assert_eq!(third["emails"][1]["document"]["subject"], "one", "kept from the first sync");
        let fourth = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&fourth), ["1", "4"]);
        assert_eq!(fourth["query"]["queryState"], "q5");
        let fifth = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&fifth), ["4"]);
        assert_eq!(fifth["queryStale"], false);
        server.join().unwrap();

        // The delta was asked for with the cached query's own arguments.
        let sent = sent.lock().unwrap();
        let delta = sent.iter().find(|args| args.get("sinceQueryState").is_some()).unwrap();
        assert_eq!(delta["sinceQueryState"], "q1");
        assert_eq!(delta["filter"], json!({"inMailbox":"in"}));
        assert_eq!(delta["collapseThreads"], false);
        assert!(delta.get("position").is_none() && delta.get("limit").is_none());
        // Sync 2 fetched only the arrival; sync 3 fetched nothing.
        let fetched: Vec<&Value> = sent
            .iter()
            .filter(|args| args.get("properties").is_some() && args["ids"].as_array().is_some_and(|ids| !ids.is_empty()))
            .map(|args| &args["ids"])
            .collect();
        assert_eq!(fetched, [&json!(["2", "1"]), &json!(["3"]), &json!(["1", "4"]), &json!(["4"])]);
    }

    #[test]
    fn pages_append_survive_a_delta_and_resync_when_the_listing_moved() {
        let row = |id: &str| {
            let mut doc = json!({});
            for key in LIST_PROPERTIES {
                doc[*key] = Value::Null;
            }
            doc["id"] = json!(id);
            doc["mailboxIds"] = json!({"in":true});
            doc["keywords"] = json!({});
            doc
        };
        let rows = |ids: &[&str]| Value::Array(ids.iter().map(|id| row(id)).collect());
        let page = |position: u64, state: &str, ids: Value, total: u64| {
            json!({"accountId":"a","position":position,"queryState":state,"canCalculateChanges":true,"ids":ids,"total":total})
        };
        let quiet = |state: &str| json!({"accountId":"a","oldState":state,"newState":state,"hasMoreChanges":false,"created":[],"updated":[],"destroyed":[]});
        let delta = |old: &str, new: &str, removed: Value, added: Value, total: u64| {
            json!({"accountId":"a","oldQueryState":old,"newQueryState":new,"removed":removed,"added":added,"total":total})
        };
        let (session, server, sent) = mock_recorded(vec![
            // sync: the first window.
            ("Email/get", get(json!([]), "seed")),
            ("Mailbox/get", boxes()),
            ("Email/query", page(0, "q1", json!(["5", "4"]), 5)),
            ("Email/get", get(rows(&["5", "4"]), "s")),
            // more: the next window, at the same state.
            ("Email/query", page(2, "q1", json!(["3", "2"]), 5)),
            ("Email/get", get(rows(&["3", "2"]), "s")),
            // sync: one arrival on top. Four rows stay four.
            ("Email/changes", quiet("seed")),
            ("Mailbox/changes", unchanged()),
            ("Email/queryChanges", delta("q1", "q2", json!([]), json!([{"id":"6","index":0}]), 6)),
            ("Email/get", get(rows(&["6"]), "s")),
            // more: answered at another state, so the listing is brought forward first.
            ("Email/query", page(4, "q3", json!(["2"]), 5)),
            ("Email/changes", quiet("seed")),
            ("Mailbox/changes", unchanged()),
            ("Email/queryChanges", delta("q2", "q3", json!(["6"]), json!([]), 5)),
            ("Email/query", page(3, "q3", json!(["2"]), 5)),
            ("Email/query", page(4, "q3", json!(["1"]), 5)),
            ("Email/get", get(rows(&["1"]), "s")),
            // more: past the end.
            ("Email/query", page(5, "q3", json!([]), 5)),
        ]);
        let c = client();
        let mut store = Store::open(":memory:", "p").unwrap();
        let ids = |view: &Value| -> Vec<String> {
            view["emails"].as_array().unwrap().iter().map(|e| e["id"].as_str().unwrap().to_owned()).collect()
        };
        let first = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!((ids(&first), &first["total"], &first["hasMore"]), (vec!["5".into(), "4".into()], &json!(5), &json!(true)));
        let paged = more(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&paged), ["5", "4", "3", "2"]);
        let moved = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&moved), ["6", "5", "4", "3"], "a delta keeps the pages already loaded");
        assert_eq!(moved["total"], 6);
        let last = more(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&last), ["5", "4", "3", "2", "1"]);
        assert_eq!((&last["total"], &last["hasMore"]), (&json!(5), &json!(false)));
        let end = more(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        assert_eq!(ids(&end).len(), 5);
        server.join().unwrap();
        // Every query asked for a total, and every page asked for one window.
        let sent = sent.lock().unwrap();
        for args in sent.iter().filter(|a| a.get("filter").is_some() && a.get("sinceQueryState").is_none()) {
            assert_eq!(args["calculateTotal"], true);
            if args["position"] != 0 && args["position"] != 3 {
                assert_eq!(args["limit"], 2, "{args}");
            }
        }
    }

    /// A page is read in two steps, Email/query then Email/get. A message that is deleted, or moved
    /// out of the mailbox, between them is listed by the query and contradicted by the get; the
    /// listing must then say it is stale, as a full sync does.
    #[test]
    fn a_page_that_lost_a_message_between_query_and_get_marks_the_listing_stale() {
        let row = |id: &str, mailbox: &str| {
            let mut doc = json!({});
            for key in LIST_PROPERTIES {
                doc[*key] = Value::Null;
            }
            doc["id"] = json!(id);
            doc["mailboxIds"] = json!({mailbox: true});
            doc["keywords"] = json!({});
            doc
        };
        let first = |state: &str| json!({"accountId":"a","position":0,"queryState":state,"canCalculateChanges":true,"ids":["2","1"],"total":3});
        for (case, got) in [
            ("deleted", json!({"accountId":"a","state":"s","list":[],"notFound":["x"]})),
            ("moved", json!({"accountId":"a","state":"s","list":[row("x", "elsewhere")],"notFound":[]})),
        ] {
            let (session, server) = mock(vec![
                ("Email/get", get(json!([]), "seed")),
                ("Mailbox/get", boxes()),
                ("Email/query", first("q1")),
                ("Email/get", get(Value::Array(vec![row("2", "in"), row("1", "in")]), "s")),
                ("Email/query", json!({"accountId":"a","position":2,"queryState":"q1","ids":["x"],"total":3})),
                ("Email/get", got),
            ]);
            let c = client();
            let mut store = Store::open(":memory:", "p").unwrap();
            let synced = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
            assert_eq!(synced["queryStale"], false, "{case}: fresh before paging");
            let paged = more(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
            server.join().unwrap();
            assert_eq!(paged["queryStale"], true, "{case}");
        }
    }

    #[test]
    fn a_flag_change_refetches_only_what_an_email_can_change() {
        let row = |id: &str, seen: bool| {
            let mut doc = json!({});
            for key in LIST_PROPERTIES {
                doc[*key] = Value::Null;
            }
            doc["id"] = json!(id);
            doc["subject"] = json!(format!("subject {id}"));
            doc["mailboxIds"] = json!({"in":true});
            doc["keywords"] = if seen { json!({"$seen":true}) } else { json!({}) };
            doc
        };
        let tracked = json!({"accountId":"a","position":0,"queryState":"q1","canCalculateChanges":true,"ids":["2","1"]});
        let (session, server, sent) = mock_recorded(vec![
            ("Email/get", get(json!([]), "seed")),
            ("Mailbox/get", boxes()),
            ("Email/query", tracked),
            ("Email/get", get(Value::Array(vec![row("2", false), row("1", false)]), "s")),
            // Message 2 was read; a new message 3 arrived on top.
            ("Email/changes", json!({"accountId":"a","oldState":"seed","newState":"s1","hasMoreChanges":false,"created":["3"],"updated":["2"],"destroyed":[]})),
            ("Mailbox/changes", unchanged()),
            ("Email/queryChanges", json!({"accountId":"a","oldQueryState":"q1","newQueryState":"q2","removed":[],"added":[{"id":"3","index":0}]})),
            ("Email/get", get(Value::Array(vec![row("3", false)]), "s")),
            ("Email/get", get(json!([{"id":"2","keywords":{"$seen":true},"mailboxIds":{"in":true}}]), "s")),
        ]);
        let c = client();
        let mut store = Store::open(":memory:", "p").unwrap();
        sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        let view = sync(&c, &session, &mut store, "a", None, Rows::Messages).unwrap();
        server.join().unwrap();
        let sent = sent.lock().unwrap();
        let gets: Vec<(&Value, &Value)> = sent
            .iter()
            .filter(|args| args["ids"].as_array().is_some_and(|ids| !ids.is_empty()) && args.get("properties").is_some())
            .map(|args| (&args["ids"], &args["properties"]))
            .collect();
        // The new arrival whole, the read message for its two mutable properties only.
        assert_eq!(gets[1], (&json!(["3"]), &json!(LIST_PROPERTIES)));
        assert_eq!(gets[2], (&json!(["2"]), &json!(["id", "keywords", "mailboxIds"])));
        let two = &view["emails"][1];
        assert_eq!(two["document"]["keywords"], json!({"$seen":true}));
        assert_eq!(two["document"]["subject"], "subject 2", "kept from the first fetch");
        assert_eq!(two["staleProperties"], json!([]));
    }

    #[test]
    fn a_query_delta_proves_only_the_prefix_it_reaches() {
        use crate::mail::QueryDelta;
        let held: Vec<String> = ["a", "b", "c", "d"].map(String::from).to_vec();
        let delta = |removed: &[&str], added: &[(&str, usize)]| QueryDelta {
            new_state: "n".into(),
            total: None,
            removed: removed.iter().map(|s| s.to_string()).collect(),
            added: added.iter().map(|(id, i)| (id.to_string(), *i)).collect(),
        };
        // Inserted at its new index, pushed past the window's end.
        assert_eq!(apply_query_delta(&held, &delta(&[], &[("x", 1)]), 4), ["a", "x", "b", "c"]);
        // A row that moved is listed in both and lands once, at its new place.
        assert_eq!(apply_query_delta(&held, &delta(&["d"], &[("d", 0)]), 4), ["d", "a", "b", "c"]);
        // An added id already held but not listed as removed (a server outside RFC 8620 §5.6) still
        // appears once.
        assert_eq!(apply_query_delta(&held, &delta(&[], &[("b", 0)]), 4), ["b", "a", "c", "d"]);
        // Two removed above the end: what slid up from beyond is unknown, so the prefix is shorter.
        assert_eq!(apply_query_delta(&held, &delta(&["a", "b"], &[]), 4), ["c", "d"]);
        // An index past what is held is after a gap; it and everything after it are dropped.
        assert_eq!(apply_query_delta(&held, &delta(&["d"], &[("x", 3), ("y", 9)]), 4), ["a", "b", "c", "x"]);
        assert_eq!(apply_query_delta(&held, &delta(&["c", "d"], &[("x", 3)]), 4), ["a", "b"]);
    }

    /// v1 listings are a cache of what a query returned and hold no user intent, so they are dropped
    /// and refilled rather than translated. Messages already fetched are kept.
    #[test]
    fn v1_cache_drops_its_listings_and_keeps_its_messages() {
        let path = temp();
        {
            let raw = Connection::open(&path).unwrap();
            raw.execute_batch(
                "CREATE TABLE metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);
                 INSERT INTO metadata VALUES('profile','p'),('mail_schema_version','1');
                 CREATE TABLE mail_emails(account TEXT NOT NULL,id TEXT NOT NULL,document TEXT NOT NULL,coverage TEXT NOT NULL,subject TEXT,received_at TEXT,PRIMARY KEY(account,id));
                 INSERT INTO mail_emails VALUES('a','kept','{\"id\":\"kept\",\"subject\":\"kept\"}','{\"subject\":true}','kept',NULL);
                 CREATE TABLE mail_queries(account TEXT NOT NULL,mailbox_id TEXT NOT NULL,arguments TEXT NOT NULL,document TEXT NOT NULL,stale INTEGER NOT NULL DEFAULT 1,PRIMARY KEY(account,mailbox_id));
                 CREATE TABLE mail_query_items(account TEXT NOT NULL,mailbox_id TEXT NOT NULL,position INTEGER NOT NULL,email_id TEXT NOT NULL,PRIMARY KEY(account,mailbox_id,position));
                 INSERT INTO mail_queries VALUES('a','in','{}','{\"queryState\":\"old\"}',0);
                 INSERT INTO mail_query_items VALUES('a','in',0,'kept');
                 CREATE TABLE mail_mailboxes(account TEXT NOT NULL,id TEXT NOT NULL,document TEXT NOT NULL,role TEXT,PRIMARY KEY(account,id));
                 INSERT INTO mail_mailboxes VALUES('a','in','{\"id\":\"in\",\"role\":\"inbox\"}','inbox');",
            )
            .unwrap();
        }
        let store = Store::open(&path, "p").unwrap();
        let version: String = store
            .db
            .query_row(
                "SELECT value FROM metadata WHERE key='mail_schema_version'",
                [],
                |r| r.get(0),
            )
            .unwrap();
        assert_eq!(version, SCHEMA_VERSION);
        assert_eq!(store.email("a", "kept").unwrap()["available"], true);
        let view = store.view("a", None, Rows::Messages).unwrap();
        assert_eq!(view["query"], Value::Null);
        assert_eq!(view["queryStale"], true);
        assert!(view["emails"].as_array().unwrap().is_empty());
        drop(store);
        // Opening again does not migrate twice or lose what the first open kept.
        let store = Store::open(&path, "p").unwrap();
        assert_eq!(store.email("a", "kept").unwrap()["available"], true);
        drop(store);
        remove(&path);
    }
}
