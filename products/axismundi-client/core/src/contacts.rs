//! Profile-isolated Contacts cache. JSON is authoritative; SQL fields are indexes.
use crate::{CORE_CAPABILITY, Client, Session};
use rusqlite::{Connection, OptionalExtension, params};
use serde_json::{Value, json};
use std::{collections::BTreeSet, path::Path};

pub const CAPABILITY: &str = "urn:ietf:params:jmap:contacts";
pub type Result<T> = std::result::Result<T, Box<dyn std::error::Error>>;

pub struct Store {
    db: Connection,
}

#[derive(Debug)]
struct MethodError(String);
impl std::fmt::Display for MethodError {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        write!(f, "JMAP method error: {}", self.0)
    }
}
impl std::error::Error for MethodError {}

impl Store {
    pub fn remember_session(
        &self,
        client: &Client,
        session: &Session,
        document: &Value,
    ) -> Result<()> {
        self.bind(client, session)?;
        self.db.execute("INSERT INTO metadata VALUES('session',?1) ON CONFLICT(key) DO UPDATE SET value=excluded.value",[document.to_string()])?;
        Ok(())
    }
    fn bind(&self, client: &Client, session: &Session) -> Result<()> {
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
            return Err(
                "cache is bound to a different service or login; use a separate profile".into(),
            );
        }
        Ok(())
    }
    /// profile is a durable local login-profile identifier, never a secret or Session state.
    pub fn open(path: impl AsRef<Path>, profile: &str) -> Result<Self> {
        let db = Connection::open(path)?;
        db.busy_timeout(std::time::Duration::from_secs(5))?;
        db.execute_batch("PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;
            CREATE TABLE IF NOT EXISTS metadata(key TEXT PRIMARY KEY,value TEXT NOT NULL);
            CREATE TABLE IF NOT EXISTS records(account TEXT NOT NULL,kind TEXT NOT NULL,id TEXT NOT NULL,
                document TEXT NOT NULL,label TEXT NOT NULL,PRIMARY KEY(account,kind,id));
            CREATE INDEX IF NOT EXISTS records_label ON records(account,kind,label,id);
            CREATE TABLE IF NOT EXISTS sync_state(account TEXT NOT NULL,kind TEXT NOT NULL,state TEXT NOT NULL,
                PRIMARY KEY(account,kind));
            CREATE TABLE IF NOT EXISTS pending_updates(account TEXT NOT NULL,id TEXT NOT NULL,
                base_state TEXT NOT NULL,patch TEXT NOT NULL,status TEXT NOT NULL DEFAULT 'pending',
                PRIMARY KEY(account,id));")?;
        db.execute(
            "INSERT OR IGNORE INTO metadata VALUES('profile',?1)",
            [profile],
        )?;
        let bound: String =
            db.query_row("SELECT value FROM metadata WHERE key='profile'", [], |r| {
                r.get(0)
            })?;
        if bound != profile {
            return Err("cache belongs to another authentication profile".into());
        }
        db.execute(
            "INSERT OR IGNORE INTO metadata VALUES('schema_version','1')",
            [],
        )?;
        let version: String = db.query_row(
            "SELECT value FROM metadata WHERE key='schema_version'",
            [],
            |r| r.get(0),
        )?;
        if version != "1" {
            return Err("unsupported Contacts cache schema version".into());
        }
        Ok(Self { db })
    }

    pub fn state(&self, account: &str, kind: &str) -> Result<Option<String>> {
        Ok(self
            .db
            .query_row(
                "SELECT state FROM sync_state WHERE account=?1 AND kind=?2",
                params![account, kind],
                |r| r.get(0),
            )
            .optional()?)
    }

    pub fn list(&self, account: &str, kind: &str) -> Result<Vec<Value>> {
        let mut stmt = self.db.prepare(
            "SELECT document FROM records WHERE account=?1 AND kind=?2 ORDER BY label,id",
        )?;
        let rows = stmt.query_map(params![account, kind], |r| r.get::<_, String>(0))?;
        rows.map(|r| Ok(serde_json::from_str(&r?)?)).collect()
    }

    fn commit(
        &mut self,
        account: &str,
        kind: &str,
        old: Option<&str>,
        state: &str,
        documents: &[Value],
        removed: &[String],
        replace: bool,
    ) -> Result<()> {
        let tx = self
            .db
            .transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)?;
        let actual: Option<String> = tx
            .query_row(
                "SELECT state FROM sync_state WHERE account=?1 AND kind=?2",
                params![account, kind],
                |r| r.get(0),
            )
            .optional()?;
        if actual.as_deref() != old {
            return Err("cache changed during synchronization; retry".into());
        }
        if replace {
            tx.execute(
                "DELETE FROM records WHERE account=?1 AND kind=?2",
                params![account, kind],
            )?;
        }
        for id in removed {
            tx.execute(
                "DELETE FROM records WHERE account=?1 AND kind=?2 AND id=?3",
                params![account, kind, id],
            )?;
        }
        for doc in documents {
            let id = string(doc, "id")?;
            let label = doc
                .pointer("/name/full")
                .and_then(Value::as_str)
                .or_else(|| doc.get("name").and_then(Value::as_str))
                .unwrap_or("");
            tx.execute(
                "INSERT INTO records VALUES(?1,?2,?3,?4,?5) ON CONFLICT(account,kind,id)
                DO UPDATE SET document=excluded.document,label=excluded.label",
                params![account, kind, id, doc.to_string(), label],
            )?;
        }
        tx.execute("INSERT INTO sync_state VALUES(?1,?2,?3) ON CONFLICT(account,kind) DO UPDATE SET state=excluded.state",
            params![account,kind,state])?;
        tx.commit()?;
        Ok(())
    }

    pub fn stage_update(&self, account: &str, id: &str, patch: &Value) -> Result<()> {
        if !patch.is_object() {
            return Err("update must be a PatchObject".into());
        }
        let state = self
            .state(account, "ContactCard")?
            .ok_or("synchronize before editing")?;
        let exists: bool = self.db.query_row("SELECT EXISTS(SELECT 1 FROM records WHERE account=?1 AND kind='ContactCard' AND id=?2)", params![account,id], |r| r.get(0))?;
        if !exists {
            return Err("contact is not cached".into());
        }
        // Do not overwrite an unresolved operation with a different base state.
        self.db.execute(
            "INSERT INTO pending_updates(account,id,base_state,patch) VALUES(?1,?2,?3,?4)",
            params![account, id, state, patch.to_string()],
        )?;
        Ok(())
    }

    pub fn pending_count(&self, account: &str) -> Result<usize> {
        let count: i64 = self.db.query_row(
            "SELECT COUNT(*) FROM pending_updates WHERE account=?1",
            [account],
            |r| r.get(0),
        )?;
        Ok(usize::try_from(count)?)
    }

    pub fn submit_update(
        &mut self,
        client: &Client,
        session: &Session,
        account: &str,
        id: &str,
    ) -> Result<()> {
        validate_account(session, account)?;
        self.bind(client, session)?;
        limit(session, "maxObjectsInSet")?;
        if session.accounts[account].is_read_only {
            return Err("account is read-only".into());
        }
        let (base, patch, status): (String, String, String) = self.db.query_row(
            "SELECT base_state,patch,status FROM pending_updates WHERE account=?1 AND id=?2",
            params![account, id],
            |r| Ok((r.get(0)?, r.get(1)?, r.get(2)?)),
        )?;
        if status == "pending" {
            let patch: Value = serde_json::from_str(&patch)?;
            let response = call(
                client,
                session,
                "ContactCard/set",
                json!({"accountId":account,"ifInState":base,"update":{id:patch}}),
            )?;
            if !response
                .get("updated")
                .and_then(Value::as_object)
                .is_some_and(|v| v.contains_key(id))
            {
                return Err("ContactCard/set rejected update; local edit retained".into());
            }
            self.db.execute(
                "UPDATE pending_updates SET status='accepted' WHERE account=?1 AND id=?2",
                params![account, id],
            )?;
        }
        sync_type(client, session, self, account, "ContactCard")?;
        self.db.execute(
            "DELETE FROM pending_updates WHERE account=?1 AND id=?2 AND status='accepted'",
            params![account, id],
        )?;
        Ok(())
    }
}

pub fn choose_account(session: &Session, requested: Option<&str>) -> Result<String> {
    if let Some(id) = requested {
        validate_account(session, id)?;
        return Ok(id.into());
    }
    if let Some(id) = session.primary_account(CAPABILITY) {
        validate_account(session, id)?;
        return Ok(id.into());
    }
    match session.accounts_with(CAPABILITY).as_slice() {
        [id] => Ok((*id).into()),
        _ => Err("select a Contacts account explicitly; no unambiguous primary".into()),
    }
}

fn validate_account(session: &Session, account: &str) -> Result<()> {
    if !session.capabilities.contains_key(CAPABILITY)
        || !session.accounts_with(CAPABILITY).contains(&account)
    {
        return Err("Session/account does not advertise Contacts".into());
    }
    Ok(())
}

fn limit(session: &Session, name: &str) -> Result<usize> {
    let n = session
        .capabilities
        .get(CORE_CAPABILITY)
        .and_then(|v| v.get(name))
        .and_then(Value::as_u64)
        .filter(|n| *n > 0)
        .ok_or_else(|| format!("Session has no valid {name}"))?;
    Ok(usize::try_from(n)?.min(500)) // A local memory bound, never a replacement for the server limit.
}

fn string<'a>(value: &'a Value, key: &str) -> Result<&'a str> {
    value
        .get(key)
        .and_then(Value::as_str)
        .ok_or_else(|| format!("response has no {key}").into())
}

fn ids(value: &Value, key: &str) -> Result<Vec<String>> {
    value
        .get(key)
        .and_then(Value::as_array)
        .ok_or_else(|| format!("response has no {key}"))?
        .iter()
        .map(|v| {
            v.as_str()
                .map(str::to_owned)
                .ok_or_else(|| "invalid id".into())
        })
        .collect()
}

fn call(client: &Client, session: &Session, method: &str, args: Value) -> Result<Value> {
    limit(session, "maxCallsInRequest")?;
    let batch = client.request(
        session,
        &[CORE_CAPABILITY, CAPABILITY],
        vec![(method, args, "contacts")],
    )?;
    if let Some(error) = batch.errors().first() {
        return Err(Box::new(MethodError(
            error.error_type().unwrap_or("unknown").into(),
        )));
    }
    let response = batch
        .method_responses
        .iter()
        .find(|r| r.name == method && r.call_id == "contacts")
        .ok_or("missing Contacts method response")?;
    Ok(response.arguments.clone())
}

fn fetch(
    client: &Client,
    session: &Session,
    account: &str,
    kind: &str,
    wanted: &[String],
    state: &str,
) -> Result<Vec<Value>> {
    let mut documents = Vec::new();
    for chunk in wanted.chunks(limit(session, "maxObjectsInGet")?) {
        let response = call(
            client,
            session,
            &format!("{kind}/get"),
            json!({"accountId":account,"ids":chunk}),
        )?;
        if string(&response, "accountId")? != account || string(&response, "state")? != state {
            return Err("data changed while fetching; retry synchronization".into());
        }
        let list = response
            .get("list")
            .and_then(Value::as_array)
            .ok_or("get has no list")?;
        let returned: BTreeSet<_> = list
            .iter()
            .map(|v| string(v, "id").map(str::to_owned))
            .collect::<Result<_>>()?;
        if returned != chunk.iter().cloned().collect() || !ids(&response, "notFound")?.is_empty() {
            return Err("get was incomplete; cache checkpoint not advanced".into());
        }
        documents.extend(list.iter().cloned());
    }
    Ok(documents)
}

/// Network work is staged in memory; records and checkpoint commit atomically.
pub fn sync_type(
    client: &Client,
    session: &Session,
    store: &mut Store,
    account: &str,
    kind: &str,
) -> Result<()> {
    validate_account(session, account)?;
    store.bind(client, session)?;
    if !matches!(kind, "AddressBook" | "ContactCard") {
        return Err("unsupported Contacts type".into());
    }
    let old = store.state(account, kind)?;
    let mut state = old.clone();
    let mut changed = BTreeSet::new();
    let mut removed = BTreeSet::new();
    let mut replace = old.is_none();
    if let Some(mut since) = old.clone() {
        loop {
            let response = match call(
                client,
                session,
                &format!("{kind}/changes"),
                json!({"accountId":account,"sinceState":since}),
            ) {
                Ok(v) => v,
                Err(e)
                    if e.downcast_ref::<MethodError>()
                        .is_some_and(|e| e.0 == "cannotCalculateChanges") =>
                {
                    replace = true;
                    break;
                }
                Err(e) => return Err(e),
            };
            if string(&response, "accountId")? != account || string(&response, "oldState")? != since
            {
                return Err("changes checkpoint mismatch".into());
            }
            for id in ids(&response, "created")?
                .into_iter()
                .chain(ids(&response, "updated")?)
            {
                removed.remove(&id);
                changed.insert(id);
            }
            for id in ids(&response, "destroyed")? {
                changed.remove(&id);
                removed.insert(id);
            }
            let next = string(&response, "newState")?.to_owned();
            let more = response
                .get("hasMoreChanges")
                .and_then(Value::as_bool)
                .ok_or("changes has no hasMoreChanges")?;
            if more && next == since {
                return Err("changes cursor did not advance".into());
            }
            since = next;
            if !more {
                state = Some(since);
                break;
            }
        }
    }
    let documents;
    if replace {
        let baseline = call(
            client,
            session,
            &format!("{kind}/get"),
            json!({"accountId":account,"ids":[]}),
        )?;
        if string(&baseline, "accountId")? != account {
            return Err("get account mismatch".into());
        }
        state = Some(string(&baseline, "state")?.into());
        if kind == "AddressBook" {
            let all = call(
                client,
                session,
                "AddressBook/get",
                json!({"accountId":account,"ids":null}),
            )?;
            if string(&all, "accountId")? != account
                || string(&all, "state")? != state.as_deref().unwrap()
            {
                return Err("address books changed; retry".into());
            }
            documents = all
                .get("list")
                .and_then(Value::as_array)
                .ok_or("get has no list")?
                .clone();
        } else {
            let mut position = 0;
            let mut query_state = None;
            loop {
                let page = call(
                    client,
                    session,
                    "ContactCard/query",
                    json!({"accountId":account,"position":position,"limit":limit(session,"maxObjectsInGet")?}),
                )?;
                if string(&page, "accountId")? != account
                    || page.get("position").and_then(Value::as_u64) != Some(position)
                {
                    return Err("query position mismatch".into());
                }
                let qs = string(&page, "queryState")?.to_owned();
                if query_state.as_ref().is_some_and(|s| *s != qs) {
                    return Err("query changed during pagination; retry".into());
                }
                query_state = Some(qs);
                let page_ids = ids(&page, "ids")?;
                if page_ids.is_empty() {
                    break;
                }
                position += page_ids.len() as u64;
                let before = changed.len();
                changed.extend(page_ids);
                if before == changed.len() {
                    return Err("query did not advance".into());
                }
            }
            documents = fetch(
                client,
                session,
                account,
                kind,
                &changed.into_iter().collect::<Vec<_>>(),
                state.as_deref().unwrap(),
            )?;
        }
        // Even an empty query must not commit an obsolete state after a concurrent write.
        let end = call(
            client,
            session,
            &format!("{kind}/get"),
            json!({"accountId":account,"ids":[]}),
        )?;
        if string(&end, "accountId")? != account
            || string(&end, "state")? != state.as_deref().unwrap()
        {
            return Err("data changed during snapshot; retry".into());
        }
    } else {
        documents = fetch(
            client,
            session,
            account,
            kind,
            &changed.into_iter().collect::<Vec<_>>(),
            state.as_deref().unwrap(),
        )?;
    }
    store.commit(
        account,
        kind,
        old.as_deref(),
        state.as_deref().ok_or("missing sync state")?,
        &documents,
        &removed.into_iter().collect::<Vec<_>>(),
        replace,
    )
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn service_binding_and_account_selection_are_explicit() {
        let (client, mut session, thread) = mock(vec![]);
        thread.join().unwrap();
        let store = Store::open(":memory:", "p").unwrap();
        store.bind(&client, &session).unwrap();
        assert_eq!(choose_account(&session, None).unwrap(), "a");
        assert!(choose_account(&session, Some("unknown")).is_err());
        let other = Client::without_redirects(crate::Credentials {
            user: "other".into(),
            secret: "different".into(),
        });
        assert!(store.bind(&other, &session).is_err());
        session.api_url = "https://another.example/api".into();
        assert!(store.bind(&client, &session).is_err());
    }
    #[test]
    fn profile_isolation_and_atomic_checkpoint() {
        let mut store = Store::open(":memory:", "profile-a").unwrap();
        let doc = json!({"id":"one","name":{"full":"Alice"},"vendor:unknown":{"x":1}});
        store
            .commit("a", "ContactCard", None, "s1", &[doc.clone()], &[], true)
            .unwrap();
        assert!(
            store
                .commit(
                    "a",
                    "ContactCard",
                    None,
                    "wrong",
                    &[],
                    &["one".into()],
                    true
                )
                .is_err()
        );
        assert_eq!(store.list("a", "ContactCard").unwrap(), vec![doc]);
        assert!(store.list("b", "ContactCard").unwrap().is_empty());
        store
            .stage_update("a", "one", &json!({"name/full":"Edited"}))
            .unwrap();
        assert_eq!(store.pending_count("a").unwrap(), 1);
        assert_eq!(
            store.list("a", "ContactCard").unwrap()[0]["name"]["full"],
            "Alice"
        );
        store
            .commit(
                "a",
                "ContactCard",
                Some("s1"),
                "s2",
                &[],
                &["one".into()],
                false,
            )
            .unwrap();
        assert!(store.list("a", "ContactCard").unwrap().is_empty());
        assert_eq!(store.pending_count("a").unwrap(), 1);
    }

    fn mock(replies: Vec<Value>) -> (Client, Session, std::thread::JoinHandle<Vec<Value>>) {
        use std::io::{BufRead, Read, Write};
        let listener = std::net::TcpListener::bind("127.0.0.1:0").unwrap();
        let endpoint = format!("http://{}/", listener.local_addr().unwrap());
        let session: Session=serde_json::from_value(json!({"apiUrl":endpoint,
            "capabilities":{CORE_CAPABILITY:{"maxObjectsInGet":2,"maxCallsInRequest":1,"maxObjectsInSet":1},CAPABILITY:{}},
            "accounts":{"a":{"accountCapabilities":{CAPABILITY:{}}}},"primaryAccounts":{CAPABILITY:"a"}})).unwrap();
        let thread = std::thread::spawn(move || {
            let mut calls = Vec::new();
            for reply in replies {
                let (mut socket, _) = listener.accept().unwrap();
                socket
                    .set_read_timeout(Some(std::time::Duration::from_secs(5)))
                    .unwrap();
                let mut reader = std::io::BufReader::new(&mut socket);
                let mut length = 0;
                loop {
                    let mut line = String::new();
                    reader.read_line(&mut line).unwrap();
                    if line == "\r\n" {
                        break;
                    }
                    if let Some((name, value)) = line.split_once(':') {
                        if name.eq_ignore_ascii_case("content-length") {
                            length = value.trim().parse().unwrap();
                        }
                    }
                }
                let mut body = vec![0; length];
                reader.read_exact(&mut body).unwrap();
                let request: Value = serde_json::from_slice(&body).unwrap();
                let method = request["methodCalls"][0][0].as_str().unwrap();
                let response = if reply.get("error").is_some() {
                    json!(["error",{"type":reply["error"]},"contacts"])
                } else {
                    json!([method, reply, "contacts"])
                };
                calls.push(request);
                let body = json!({"methodResponses":[response]}).to_string();
                write!(socket,"HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: {}\r\nConnection: close\r\n\r\n{}",body.len(),body).unwrap();
            }
            calls
        });
        (
            Client::without_redirects(crate::Credentials {
                user: "u".into(),
                secret: "secret".into(),
            }),
            session,
            thread,
        )
    }

    #[test]
    fn changes_are_chunked_and_checkpoint_is_atomic() {
        let (client, session, thread) = mock(vec![
            json!({"accountId":"a","oldState":"s1","newState":"s2","created":["1","2"],"updated":[],"destroyed":[],"hasMoreChanges":true}),
            json!({"accountId":"a","oldState":"s2","newState":"s3","created":["3"],"updated":[],"destroyed":["old"],"hasMoreChanges":false}),
            json!({"accountId":"a","state":"s3","list":[{"id":"1"},{"id":"2"}],"notFound":[]}),
            json!({"accountId":"a","state":"s3","list":[{"id":"3","vendor:x":[1,2]}],"notFound":[]}),
        ]);
        let mut store = Store::open(":memory:", "p").unwrap();
        store
            .commit(
                "a",
                "ContactCard",
                None,
                "s1",
                &[json!({"id":"old"})],
                &[],
                true,
            )
            .unwrap();
        sync_type(&client, &session, &mut store, "a", "ContactCard").unwrap();
        assert_eq!(
            store.state("a", "ContactCard").unwrap().as_deref(),
            Some("s3")
        );
        assert_eq!(store.list("a", "ContactCard").unwrap().len(), 3);
        let calls = thread.join().unwrap();
        assert_eq!(calls[2]["methodCalls"][0][1]["ids"], json!(["1", "2"]));
        assert_eq!(calls[3]["methodCalls"][0][1]["ids"], json!(["3"]));
    }

    #[test]
    fn incomplete_get_does_not_advance_or_delete() {
        let (client, session, thread) = mock(vec![
            json!({"accountId":"a","oldState":"s1","newState":"s2","created":["new"],"updated":[],"destroyed":["old"],"hasMoreChanges":false}),
            json!({"accountId":"a","state":"s2","list":[],"notFound":["new"]}),
        ]);
        let mut store = Store::open(":memory:", "p").unwrap();
        store
            .commit(
                "a",
                "ContactCard",
                None,
                "s1",
                &[json!({"id":"old"})],
                &[],
                true,
            )
            .unwrap();
        assert!(sync_type(&client, &session, &mut store, "a", "ContactCard").is_err());
        assert_eq!(
            store.state("a", "ContactCard").unwrap().as_deref(),
            Some("s1")
        );
        assert_eq!(store.list("a", "ContactCard").unwrap()[0]["id"], "old");
        thread.join().unwrap();
    }

    #[test]
    fn expired_state_rebuilds_without_losing_pending_edits() {
        let empty = json!({"accountId":"a","state":"fresh","list":[],"notFound":[]});
        let (client, session, thread) = mock(vec![
            json!({"error":"cannotCalculateChanges"}),
            empty.clone(),
            json!({"accountId":"a","queryState":"q1","position":0,"ids":[]}),
            empty,
        ]);
        let mut store = Store::open(":memory:", "p").unwrap();
        store
            .commit(
                "a",
                "ContactCard",
                None,
                "expired",
                &[json!({"id":"old"})],
                &[],
                true,
            )
            .unwrap();
        store
            .stage_update("a", "old", &json!({"name/full":"Local"}))
            .unwrap();
        sync_type(&client, &session, &mut store, "a", "ContactCard").unwrap();
        assert_eq!(
            store.state("a", "ContactCard").unwrap().as_deref(),
            Some("fresh")
        );
        assert!(store.list("a", "ContactCard").unwrap().is_empty());
        assert_eq!(store.pending_count("a").unwrap(), 1);
        thread.join().unwrap();
    }
}
