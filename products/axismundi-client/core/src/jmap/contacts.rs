//! Profile-isolated Contacts cache. JSON is authoritative; SQL fields are indexes.
use crate::{
    http::Client,
    jmap::{CORE_CAPABILITY, Session},
};
use rusqlite::{Connection, OptionalExtension, params};
use serde_json::{Value, json};
use std::{collections::BTreeSet, path::Path};

pub const CAPABILITY: &str = "urn:ietf:params:jmap:contacts";
pub type Result<T> = std::result::Result<T, Box<dyn std::error::Error>>;

/// RFC 9610 §3.3.1: every FilterCondition property. Values are all strings on the wire (Id,
/// String or UTCDate).
pub const FILTER_PROPERTIES: [&str; 20] = [
    "inAddressBook", "uid", "hasMember", "kind", "createdBefore", "createdAfter", "updatedBefore",
    "updatedAfter", "text", "name", "name/given", "name/surname", "name/surname2", "nickname",
    "organization", "email", "phone", "onlineService", "address", "note",
];

/// RFC 9610 §3.3.2: `created` and `updated` MUST be supported, the three name parts SHOULD.
pub const SORT_PROPERTIES: [&str; 5] = ["created", "updated", "name/given", "name/surname", "name/surname2"];

/// A JMAP filter (RFC 8620 §5.5): an operator over child filters, or one FilterCondition.
#[derive(Clone, Debug, PartialEq, serde::Deserialize, serde::Serialize)]
#[serde(untagged)]
pub enum Filter {
    Operator(FilterOperator),
    Condition(std::collections::BTreeMap<String, String>),
}

/// An operator object carries `operator` and `conditions` only. A condition property mixed into
/// it is refused rather than dropped, so the query sent is the one written.
#[derive(Clone, Debug, PartialEq, serde::Deserialize, serde::Serialize)]
#[serde(deny_unknown_fields)]
pub struct FilterOperator {
    pub operator: Operator,
    pub conditions: Vec<Filter>,
}

#[derive(Clone, Copy, Debug, PartialEq, serde::Deserialize, serde::Serialize)]
#[serde(rename_all = "UPPERCASE")]
pub enum Operator { And, Or, Not }

/// A JMAP Comparator (RFC 8620 §5.5) limited to the RFC 9610 sort properties.
#[derive(Clone, Debug, PartialEq, serde::Deserialize, serde::Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Comparator {
    pub property: String,
    #[serde(default = "ascending")]
    pub is_ascending: bool,
}

fn ascending() -> bool { true }

impl Filter {
    /// Refuses what RFC 9610 does not define, so a typo never reaches the server as a silently
    /// different query. An empty condition is valid and matches every card (§3.3.1).
    fn check(&self) -> Result<()> {
        match self {
            Filter::Operator(FilterOperator { conditions, .. }) => conditions.iter().try_for_each(Filter::check),
            Filter::Condition(properties) => match properties.keys().find(|key| !FILTER_PROPERTIES.contains(&key.as_str())) {
                Some(key) => Err(format!("not a ContactCard filter property: {key}").into()),
                None => Ok(()),
            },
        }
    }
}

/// ContactCard/query with the server's filter and order (RFC 9610 §3.3). Pages are read until the
/// end, and the ids come back in the server's order: the caller shows them as they are. A filter or
/// sort the server does not support fails with its error (`unsupportedFilter`, `unsupportedSort`)
/// instead of being dropped or replaced locally.
pub fn query(
    client: &Client,
    session: &Session,
    account: &str,
    filter: Option<&Filter>,
    sort: &[Comparator],
) -> Result<Vec<String>> {
    validate_account(session, account)?;
    if let Some(filter) = filter {
        filter.check()?;
    }
    if let Some(comparator) = sort.iter().find(|c| !SORT_PROPERTIES.contains(&c.property.as_str())) {
        return Err(format!("not a ContactCard sort property: {}", comparator.property).into());
    }
    let page_size = limit(session, "maxObjectsInGet")?;
    let (mut found, mut position, mut query_state) = (Vec::new(), 0u64, None::<String>);
    loop {
        let mut args = json!({"accountId": account, "position": position, "limit": page_size});
        if let Some(filter) = filter {
            args["filter"] = serde_json::to_value(filter)?;
        }
        if !sort.is_empty() {
            args["sort"] = serde_json::to_value(sort)?;
        }
        let page = call(client, session, "ContactCard/query", args)?;
        if string(&page, "accountId")? != account || page.get("position").and_then(Value::as_u64) != Some(position) {
            return Err("Contacts query position mismatch".into());
        }
        let state = string(&page, "queryState")?.to_owned();
        if query_state.as_ref().is_some_and(|previous| *previous != state) {
            return Err("Contacts changed while the query was read; retry".into());
        }
        query_state = Some(state);
        let page_ids = ids(&page, "ids")?;
        if page_ids.is_empty() {
            return Ok(found);
        }
        position += page_ids.len() as u64;
        found.extend(page_ids);
        if page.get("total").and_then(Value::as_u64).is_some_and(|total| position >= total) {
            return Ok(found);
        }
    }
}

/// A random (v4) UUID as a `urn:uuid:` URI, the form RFC 9553 recommends for uid.
fn new_uid() -> Result<String> {
    use ring::rand::SecureRandom;
    let mut bytes = [0u8; 16];
    ring::rand::SystemRandom::new()
        .fill(&mut bytes)
        .map_err(|_| "could not generate contact UID")?;
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    let hex = bytes.iter().map(|b| format!("{b:02x}")).collect::<String>();
    Ok(format!("urn:uuid:{}-{}-{}-{}-{}", &hex[..8], &hex[8..12], &hex[12..16], &hex[16..20], &hex[20..]))
}

pub fn create(
    client: &Client,
    session: &Session,
    account: &str,
    book: &str,
    mut card: Value,
) -> Result<String> {
    validate_account(session, account)?;
    limit(session, "maxObjectsInSet")?;
    if session.accounts[account].is_read_only {
        return Err("account is read-only".into());
    }
    let object = card.as_object_mut().ok_or("contact must be an object")?;
    for key in ["id", "blobId", "size"] {
        object.remove(key);
    }
    object.insert("@type".into(), json!("Card"));
    object.insert("version".into(), json!("2.0"));
    // A uid the caller brought (an import, a card written elsewhere) names that card and is kept.
    // Otherwise the author mints one: a group's `members` can only reference a card by uid, and a
    // server need not mint it (Stalwart stores the card without one).
    if !object.get("uid").and_then(Value::as_str).is_some_and(|uid| !uid.is_empty()) {
        object.insert("uid".into(), json!(new_uid()?));
    }
    object.insert("addressBookIds".into(), json!({book:true}));
    let response = call(
        client,
        session,
        "ContactCard/set",
        json!({"accountId":account,"create":{"card":card}}),
    )?;
    if string(&response, "accountId")? != account {
        return Err("Contacts create account mismatch".into());
    }
    if let Some(kind) = response["notCreated"]["card"]["type"].as_str() {
        return Err(format!("ContactCard/set rejected create: {kind}").into());
    }
    Ok(string(&response["created"]["card"], "id")?.to_owned())
}

/// Convert explicit path segments to JMAP patch keys without diffing a partial form.
pub fn edit_patch(edits: Vec<(Vec<String>, Value)>) -> Result<Value> {
    let mut patch = serde_json::Map::new();
    for (path, value) in edits {
        let root = path.first().ok_or("missing contact edit path")?;
        if matches!(
            root.as_str(),
            "" | "version" | "id" | "@type" | "uid" | "blobId" | "size"
        ) {
            return Err("contact property is not editable".into());
        }
        let key = path
            .iter()
            .map(|part| part.replace('~', "~0").replace('/', "~1"))
            .collect::<Vec<_>>()
            .join("/");
        if patch.insert(key, value).is_some() {
            return Err("duplicate contact edit path".into());
        }
    }
    if patch.is_empty()
        || patch.keys().any(|key| {
            patch.keys().any(|parent| {
                key.strip_prefix(parent.as_str())
                    .is_some_and(|tail| tail.starts_with('/'))
            })
        })
    {
        return Err("empty or overlapping contact edits".into());
    }
    Ok(Value::Object(patch))
}

pub struct Store {
    db: Connection,
}

#[cfg(test)]
#[test]
fn minted_uids_are_random_v4_urns() {
    let (a, b) = (new_uid().unwrap(), new_uid().unwrap());
    assert_ne!(a, b);
    assert_eq!(a.len(), "urn:uuid:".len() + 36);
    assert!(a.starts_with("urn:uuid:") && &a[23..24] == "4");
}

#[cfg(test)]
#[test]
fn explicit_contact_edits_preserve_unmentioned_fields_and_escape_keys() {
    let path = |parts: &[&str]| parts.iter().map(|part| part.to_string()).collect();
    assert_eq!(
        edit_patch(vec![(path(&["phones", "a/b~c", "number"]), json!("123"))]).unwrap(),
        json!({"phones/a~1b~0c/number":"123"})
    );
    assert!(edit_patch(vec![(path(&["version"]), json!("2.0"))]).is_err());
    assert!(
        edit_patch(vec![
            (path(&["phones"]), Value::Null),
            (path(&["phones", "p1"]), Value::Null)
        ])
        .is_err()
    );
}

#[cfg(test)]
#[test]
#[ignore = "requires the local Stalwart interop instance"]
fn stalwart_contact_create_update_and_search() -> Result<()> {
    let client = Client::without_redirects(crate::http::Credentials {
        user: "admin@example.org".into(),
        secret: std::env::var("AXISMUNDI_CONTACTS_TEST_PASSWORD")?,
    });
    let session = client.session("https://localhost:18881/jmap/session")?;
    if url::Url::parse(&session.api_url)?
        .origin()
        .ascii_serialization()
        != "https://localhost:18881"
    {
        return Err("unexpected test API origin".into());
    }
    let account = choose_account(&session, None)?;
    let mut store = Store::open(":memory:", "interop")?;
    sync_type(&client, &session, &mut store, &account, "AddressBook")?;
    let books = store.list(&account, "AddressBook")?;
    let book = books
        .iter()
        .find(|book| book["isDefault"] == true)
        .ok_or("no default address book")?;
    let id = create(
        &client,
        &session,
        &account,
        string(book, "id")?,
        json!({
            "name":{"full":"Codex Contacts interop"},
            "emails":{"e1":{"address":"contacts-test@example.org"}},
            "phones":{"p1":{"number":"+12025550100"}}
        }),
    )?;
    if id == "g" {
        return Err("refusing to touch the seed card".into());
    }
    let checked = (|| -> Result<()> {
        sync_type(&client, &session, &mut store, &account, "ContactCard")?;
        let read = |store: &Store| -> Result<Value> {
            store
                .list(&account, "ContactCard")?
                .into_iter()
                .find(|card| card["id"] == id)
                .ok_or_else(|| "test contact missing".into())
        };
        let before = read(&store)?;
        let patch = edit_patch(vec![(
            vec!["phones".into(), "p1".into(), "number".into()],
            json!("+12025550101"),
        )])?;
        println!("patch: {patch}");
        store.stage_update(&account, &id, &patch)?;
        store.submit_update(&client, &session, &account, &id)?;
        let after = read(&store)?;
        if after["phones"]["p1"]["number"] != "+12025550101" {
            return Err("updated phone is missing".into());
        }
        for field in ["name", "emails", "uid", "addressBookIds", "version"] {
            if before[field] != after[field] {
                return Err(format!("unexpected change to {field}").into());
            }
        }
        let text = Filter::Condition([("text".to_owned(), "Codex Contacts interop".to_owned())].into());
        if !query(&client, &session, &account, Some(&text), &[])?.contains(&id) {
            return Err("server query did not find the test card".into());
        }
        println!("server version: {}", after["version"]);
        Ok(())
    })();
    let removed = call(
        &client,
        &session,
        "ContactCard/set",
        json!({"accountId":account,"destroy":[id]}),
    )?;
    if !ids(&removed, "destroyed")?.contains(&id) {
        return Err(format!("could not remove test card {id}").into());
    }
    checked
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
        let existing: Option<(String, String)> = self.db.query_row(
            "SELECT patch,status FROM pending_updates WHERE account=?1 AND id=?2",
            params![account,id], |r| Ok((r.get(0)?, r.get(1)?)),
        ).optional()?;
        if let Some((saved, status)) = existing {
            if status == "conflict" {
                return Err("stateMismatch: reload the contact before retrying".into());
            }
            if serde_json::from_str::<Value>(&saved)? == *patch {
                return Ok(());
            }
            return Err("An earlier save is unresolved. Retry the original changes before making new ones.".into());
        }
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

    pub fn discard_update_after_sync(&self, account: &str, id: &str) -> Result<()> {
        self.db.execute(
            "DELETE FROM pending_updates WHERE account=?1 AND id=?2",
            params![account, id],
        )?;
        Ok(())
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
        if status == "conflict" {
            return Err("stateMismatch: contact changed on the server".into());
        }
        if status == "pending" {
            let patch: Value = serde_json::from_str(&patch)?;
            let response = call(
                client,
                session,
                "ContactCard/set",
                json!({"accountId":account,"ifInState":base,"update":{id:patch}}),
            );
            if response.as_ref().is_err_and(|error| error.downcast_ref::<MethodError>()
                .is_some_and(|error| error.0 == "stateMismatch")) {
                self.db.execute(
                    "UPDATE pending_updates SET status='conflict' WHERE account=?1 AND id=?2",
                    params![account, id],
                )?;
            }
            let response = response?;
            if !response
                .get("updated")
                .and_then(Value::as_object)
                .is_some_and(|v| v.contains_key(id))
            {
                if let Some(kind) = response["notUpdated"][id]["type"].as_str() {
                    self.db.execute("DELETE FROM pending_updates WHERE account=?1 AND id=?2", params![account,id])?;
                    return Err(format!("ContactCard/set rejected update: {kind}").into());
                }
                return Err("Could not confirm the contact update. Retry the same changes.".into());
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
    if let Some(reason) = session.empty_account_diagnostic() {
        return Err(reason.into());
    }
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
    if let Some(reason) = session.empty_account_diagnostic() {
        return Err(reason.into());
    }
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
        let other = Client::without_redirects(crate::http::Credentials {
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
        store
            .stage_update("a", "one", &json!({"name/full":"Edited"}))
            .unwrap();
        assert!(store.stage_update("a", "one", &json!({"name/full":"Different"})).is_err());
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
            Client::without_redirects(crate::http::Credentials {
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
    fn a_query_sends_the_rfc_filter_and_sort_and_keeps_the_server_order() {
        let (client, session, thread) = mock(vec![
            json!({"accountId":"a","queryState":"q","position":0,"ids":["z","a"],"total":3}),
            json!({"accountId":"a","queryState":"q","position":2,"ids":["m"],"total":3}),
        ]);
        let filter: Filter = serde_json::from_value(json!({"operator":"AND","conditions":[
            {"operator":"OR","conditions":[{"inAddressBook":"b1"},{"inAddressBook":"b2"}]},
            {"kind":"group","text":"kim"}]})).unwrap();
        let sort = [Comparator { property: "name/surname".into(), is_ascending: false }];
        assert_eq!(query(&client, &session, "a", Some(&filter), &sort).unwrap(), ["z", "a", "m"]);
        let calls = thread.join().unwrap();
        assert_eq!(calls.len(), 2);
        let args = |n: usize| &calls[n]["methodCalls"][0][1];
        assert_eq!(args(0)["filter"], serde_json::to_value(&filter).unwrap());
        assert_eq!(args(0)["sort"], json!([{"property":"name/surname","isAscending":false}]));
        assert_eq!((args(0)["position"].as_u64(), args(1)["position"].as_u64()), (Some(0), Some(2)));
    }

    #[test]
    fn a_query_refuses_what_rfc_9610_does_not_define_before_sending() {
        let (client, session, thread) = mock(vec![]);
        let typo = Filter::Condition([("surname".to_owned(), "Kim".to_owned())].into());
        assert!(query(&client, &session, "a", Some(&typo), &[]).is_err());
        let full_name = [Comparator { property: "name".into(), is_ascending: true }];
        assert!(query(&client, &session, "a", None, &full_name).is_err());
        assert!(thread.join().unwrap().is_empty());
        assert_eq!(serde_json::from_value::<Comparator>(json!({"property":"created"})).unwrap().is_ascending, true);
        // An operator with a condition property mixed in is not a filter: nothing is dropped.
        let mixed = json!({"operator":"OR","conditions":[{"kind":"group"}],"inAddressBook":"b"});
        assert!(serde_json::from_value::<Filter>(mixed).is_err());
    }

    #[test]
    fn an_unsupported_sort_is_the_servers_error_not_a_local_fallback() {
        let (client, session, thread) = mock(vec![json!({"error":"unsupportedSort"})]);
        let sort = [Comparator { property: "name/surname".into(), is_ascending: true }];
        let error = query(&client, &session, "a", None, &sort).unwrap_err();
        assert!(error.to_string().contains("unsupportedSort"));
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
