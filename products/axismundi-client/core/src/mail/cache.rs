//! Partial Mail cache. Network exchanges are staged outside SQLite transactions.
use super::{
    MAX_BODY_VALUE_BYTES, Window, arguments, call, choose_account, mailboxes, query_ids,
    validate_get, window,
};
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
        if version.as_deref().is_some_and(|v| v != "1") {
            return Err("unsupported Mail cache schema version".into());
        }
        tx.execute_batch("CREATE TABLE IF NOT EXISTS mail_accounts(account TEXT PRIMARY KEY, revision INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE IF NOT EXISTS mail_mailboxes(account TEXT NOT NULL,id TEXT NOT NULL,document TEXT NOT NULL,role TEXT,PRIMARY KEY(account,id));
            CREATE TABLE IF NOT EXISTS mail_emails(account TEXT NOT NULL,id TEXT NOT NULL,document TEXT NOT NULL,coverage TEXT NOT NULL,subject TEXT,received_at TEXT,PRIMARY KEY(account,id));
            CREATE TABLE IF NOT EXISTS mail_email_mailboxes(account TEXT NOT NULL,email_id TEXT NOT NULL,mailbox_id TEXT NOT NULL,PRIMARY KEY(account,email_id,mailbox_id),FOREIGN KEY(account,email_id) REFERENCES mail_emails(account,id) ON DELETE CASCADE);
            CREATE INDEX IF NOT EXISTS mail_membership ON mail_email_mailboxes(account,mailbox_id,email_id);
            CREATE TABLE IF NOT EXISTS mail_sync_state(account TEXT NOT NULL,kind TEXT NOT NULL,state TEXT NOT NULL,PRIMARY KEY(account,kind));
            CREATE TABLE IF NOT EXISTS mail_queries(account TEXT NOT NULL,mailbox_id TEXT NOT NULL,arguments TEXT NOT NULL,document TEXT NOT NULL,stale INTEGER NOT NULL DEFAULT 1,PRIMARY KEY(account,mailbox_id));
            CREATE TABLE IF NOT EXISTS mail_query_items(account TEXT NOT NULL,mailbox_id TEXT NOT NULL,position INTEGER NOT NULL,email_id TEXT NOT NULL,PRIMARY KEY(account,mailbox_id,position),UNIQUE(account,mailbox_id,email_id),FOREIGN KEY(account,mailbox_id) REFERENCES mail_queries(account,mailbox_id) ON DELETE CASCADE);
            CREATE TABLE IF NOT EXISTS mail_unavailable(account TEXT NOT NULL,id TEXT NOT NULL,reason TEXT NOT NULL,PRIMARY KEY(account,id));")?;
        tx.execute(
            "INSERT OR IGNORE INTO metadata VALUES('mail_schema_version','1')",
            [],
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

    pub fn view(&self, account: &str, mailbox: Option<&str>) -> Result<Value> {
        let mailbox = self.mailbox_id(account, mailbox)?;
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
                "SELECT document,stale FROM mail_queries WHERE account=?1 AND mailbox_id=?2",
                params![account, mailbox],
                |r| Ok((r.get(0)?, r.get(1)?)),
            )
            .optional()?;
        let mut stmt = self.db.prepare("SELECT email_id FROM mail_query_items WHERE account=?1 AND mailbox_id=?2 ORDER BY position")?;
        let ids: Vec<String> = stmt
            .query_map(params![account, mailbox], |r| r.get(0))?
            .collect::<std::result::Result<_, _>>()?;
        let emails: Vec<Value> = ids
            .iter()
            .map(|id| self.email(account, id))
            .collect::<Result<_>>()?;
        let (query, stale) = match query {
            Some((raw, stale)) => (serde_json::from_str::<Value>(&raw)?, stale != 0),
            None => (Value::Null, true),
        };
        Ok(
            json!({"accountId":account,"mailboxId":mailbox,"mailboxes":boxes,"query":query,"queryStale":stale,"emails":emails,"emailChangesState":self.state(account,"Email")?,"partialCache":true}),
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
    tx.execute(
        "UPDATE mail_emails SET coverage='{}' WHERE account=?1",
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
                "UPDATE mail_emails SET coverage='{}' WHERE account=?1 AND id=?2",
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

pub fn sync(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    requested: Option<&str>,
) -> Result<Value> {
    choose_account(session, Some(account))?;
    store.bind(client, session)?;
    changes(client, session, store, account)?;
    let revision = store.revision(account)?;
    let boxes = mailboxes(client, session, account)?;
    let rows = boxes["list"].as_array().ok_or("Mailbox/get has no list")?;
    if !boxes["notFound"].as_array().is_some_and(Vec::is_empty) {
        return Err("incomplete Mailbox snapshot".into());
    }
    let mailbox = if let Some(id) = requested {
        id.to_owned()
    } else {
        let ids: Vec<_> = rows
            .iter()
            .filter(|v| v["role"] == "inbox")
            .map(|v| string(v, "id"))
            .collect::<Result<_>>()?;
        match ids.as_slice() {
            [id] => id.to_string(),
            _ => return Err("no unambiguous Inbox; supply --mailbox".into()),
        }
    };
    let selected = rows
        .iter()
        .find(|v| v["id"] == mailbox)
        .ok_or("mailbox is not accessible")?;
    if selected.pointer("/myRights/mayReadItems") == Some(&Value::Bool(false)) {
        return Err("mailbox does not permit reading".into());
    }
    let Window { args, query, gets } = window(client, session, account, &mailbox)?;
    string(&query, "queryState")?;
    if query["position"].as_u64() != Some(0) {
        return Err("unexpected query position".into());
    }
    let ids = query_ids(&query, 50)?;
    let missing = gets.iter().any(|result| {
        result["notFound"].as_array().is_some_and(|v| !v.is_empty())
            || result["list"].as_array().is_some_and(|rows| {
                rows.iter().any(|doc| {
                    doc["mailboxIds"]
                        .as_object()
                        .is_some_and(|ids| ids.get(&mailbox) != Some(&Value::Bool(true)))
                })
            })
    });
    let tx = store.write(account, revision)?;
    tx.execute("DELETE FROM mail_mailboxes WHERE account=?1", [account])?;
    let mut seen = BTreeSet::new();
    for doc in rows {
        let id = string(doc, "id")?;
        if !seen.insert(id) {
            return Err("duplicate Mailbox id".into());
        }
        tx.execute(
            "INSERT INTO mail_mailboxes VALUES(?1,?2,?3,?4)",
            params![account, id, doc.to_string(), doc["role"].as_str()],
        )?;
    }
    for result in gets {
        save_get(&tx, account, &result, false)?;
    }
    tx.execute(
        "UPDATE mail_queries SET stale=1 WHERE account=?1",
        [account],
    )?;
    tx.execute("INSERT INTO mail_queries VALUES(?1,?2,?3,?4,?5) ON CONFLICT(account,mailbox_id) DO UPDATE SET arguments=excluded.arguments,document=excluded.document,stale=excluded.stale",params![account,mailbox,args.to_string(),query.to_string(),missing])?;
    tx.execute(
        "DELETE FROM mail_query_items WHERE account=?1 AND mailbox_id=?2",
        params![account, mailbox],
    )?;
    for (position, id) in ids.iter().enumerate() {
        tx.execute(
            "INSERT INTO mail_query_items VALUES(?1,?2,?3,?4)",
            params![account, mailbox, position as i64, id],
        )?;
    }
    set_state(&tx, account, "Mailbox", string(&boxes, "state")?)?;
    tx.commit()?;
    store.view(account, Some(&mailbox))
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
        assert!(
            got["staleProperties"]
                .as_array()
                .unwrap()
                .contains(&json!("bodyValues"))
        );
        assert_eq!(got["coverage"], json!({}));
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
        use std::{
            io::{BufRead, BufReader, Read, Write},
            net::TcpListener,
            thread,
            time::{Duration, Instant},
        };
        let listener = TcpListener::bind("127.0.0.1:0").unwrap();
        listener.set_nonblocking(true).unwrap();
        let session = session(&format!("http://{}", listener.local_addr().unwrap()));
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
                assert_eq!(request["methodCalls"][0][0], method);
                if method == "Email/query" {
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
        (session, server)
    }

    fn boxes() -> Value {
        json!({"accountId":"a","state":"boxes","list":[{"id":"in","name":"Localized Inbox","role":"inbox","myRights":{"mayReadItems":true}}],"notFound":[]})
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
            ("Mailbox/get", boxes()),
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
            ("Mailbox/get", boxes()),
            ("Email/query", query(json!([]), "q3")),
            ("Email/changes", json!({"type":"cannotCalculateChanges"})),
            ("Email/get", get(json!([]), "reset")),
            ("Mailbox/get", boxes()),
            ("Email/query", query(json!(["2"]), "q4")),
            (
                "Email/get",
                get(json!([{"id":"2","subject":"refetched"}]), "later"),
            ),
        ]);
        let path = temp();
        let mut store = Store::open(&path, "p").unwrap();
        let c = client();
        let first = sync(&c, &session, &mut store, "a", None).unwrap();
        assert_eq!(first["emails"][0]["id"], "2");
        read(&c, &session, &mut store, "a", "2").unwrap();
        let second = sync(&c, &session, &mut store, "a", None).unwrap();
        assert_eq!(second["emails"][0]["id"], "3");
        assert_eq!(store.state("a", "Email").unwrap().as_deref(), Some("s2"));
        assert_eq!(store.email("a", "1").unwrap()["reason"], "destroyed");
        assert!(
            store.email("a", "2").unwrap()["staleProperties"]
                .as_array()
                .unwrap()
                .contains(&json!("bodyValues"))
        );
        let third = sync(&c, &session, &mut store, "a", None).unwrap();
        assert!(third["emails"].as_array().unwrap().is_empty());
        assert_eq!(store.email("a", "2").unwrap()["available"], true);
        sync(&c, &session, &mut store, "a", None).unwrap();
        assert_eq!(store.state("a", "Email").unwrap().as_deref(), Some("reset"));
        drop(store);
        let store = Store::open(&path, "p").unwrap();
        assert_eq!(
            store.view("a", None).unwrap()["emails"][0]["document"]["subject"],
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
            ("Mailbox/get", boxes()),
            ("Email/query", query(json!(["1", "2"]), "q2")),
            (
                "Email/get",
                get(json!([{"id":"1","subject":"do-not-commit"}]), "s3"),
            ),
        ]);
        let c = client();
        let mut store = Store::open(":memory:", "p").unwrap();
        sync(&c, &session, &mut store, "a", None).unwrap();
        assert!(sync(&c, &session, &mut store, "a", None).is_err());
        assert_eq!(store.state("a", "Email").unwrap().as_deref(), Some("s2"));
        let view = store.view("a", None).unwrap();
        assert_eq!(view["query"]["queryState"], "q1");
        assert_eq!(view["queryStale"], true);
        assert_eq!(view["emails"][0]["document"]["subject"], "original");
        assert_eq!(view["emails"][0]["coverage"], json!({}));
        server.join().unwrap();
    }
}
