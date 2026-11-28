use std::sync::{Arc, Mutex};

use axismundi_client_core::{
    Error,
    activitypub::pre_fep::session::ActingActor,
    discovery::{AP_SESSION_ROUTE, Discovery, DiscoveryOptions, Probe, discover_selected},
    http::{Client, Credentials},
};
use serde::{Deserialize, Serialize};
use serde_json::Value;
use url::{Host, Url};

#[derive(Default)]
pub struct Connection(Mutex<ConnectionState>);

/// Every signed-in connection, by id. The GUI chooses which one it shows; each command names its
/// connection, so an answer for one never lands in another.
#[derive(Default)]
struct ConnectionState {
    /// Ids are never reused within a run, so a late answer cannot match a newer connection.
    next_id: u32,
    /// Bumped by each discovery, so an older discovery's result does not replace a newer plan.
    discovery: u32,
    /// Bumped by signing out of everything. A sign-in or restore that started before is not
    /// admitted when it finishes: it read a secret that has since been forgotten.
    epoch: u64,
    plan: Option<LoginPlan>,
    connections: std::collections::BTreeMap<u32, Arc<Active>>,
    mail_watch: Option<(u32, crate::mail_push::Worker)>,
}

impl Connection {
    pub(crate) fn watch_mail<R: tauri::Runtime>(&self, app: tauri::AppHandle<R>, id: Option<u32>) -> Result<(), String> {
        let mut state = self.lock()?;
        if state.mail_watch.as_ref().map(|(id, _)| *id) == id { return Ok(()); }
        state.mail_watch = None;
        if let Some(id) = id {
            let active = state.connections.get(&id).ok_or("This connection has ended")?;
            if let Some(worker) = crate::mail_push::Worker::start(app, id, Arc::clone(active))? {
                state.mail_watch = Some((id, worker));
            }
        }
        Ok(())
    }

    /// The connection `connection_id` names, while it is still signed in.
    pub(crate) fn current(&self, connection_id: u32) -> Result<Arc<Active>, String> {
        self.lock()?
            .connections
            .get(&connection_id)
            .cloned()
            .ok_or_else(|| "This connection has ended".into())
    }

    fn lock(&self) -> Result<std::sync::MutexGuard<'_, ConnectionState>, String> {
        self.0
            .lock()
            .map_err(|_| "Connection state is unavailable".into())
    }
}

pub(crate) struct Active {
    summary: Connected,
    /// Origin, username and auth method: one live connection per login, whichever services it
    /// reaches. Not a credential id — the saved key is.
    login: (String, String, &'static str),
    /// The saved-connection key whose secret this connection was signed in with, if saved.
    saved: Option<String>,
    pub(crate) client: Client,
    /// The JMAP Session and Mail cache, when the connection signed in to a Mail server.
    pub(crate) mail: Option<crate::mail::Mail>,
    pub(crate) contacts: Option<crate::contacts::Contacts>,
    pub(crate) files_session: Option<axismundi_client_core::jmap::Session>,
    actors_url: Option<Url>,
    actor_ids: Vec<String>,
    outboxes: Mutex<std::collections::BTreeMap<String, String>>,
}

impl Active {
    fn remember_actor_document(&self, actor_id: &str, document: &Value) -> Result<(), String> {
        if document["id"].as_str() != Some(actor_id)
            || !matches!(
                document["type"].as_str(),
                Some("Person" | "Group" | "Organization" | "Service" | "Application")
            )
        {
            return Err("The document does not describe the selected Actor".into());
        }
        let address = document["outbox"].as_str().ok_or("Actor has no outbox")?;
        validated_outbox(
            self.actors_url.as_ref().ok_or("No approved REST root")?,
            address,
        )
        .ok_or("Actor outbox is outside the approved transport")?;
        self.outboxes
            .lock()
            .map_err(|_| "Actor state is unavailable")?
            .insert(actor_id.into(), address.into());
        Ok(())
    }
    fn actor_document_url(&self, actor_id: &str) -> Option<Url> {
        self.actor_ids.iter().find(|id| id.as_str() == actor_id)?;
        credential_url(actor_id)
    }

    fn outbox_page_url(&self, actor_id: &str) -> Option<Url> {
        let outboxes = self.outboxes.lock().ok()?;
        let address = outboxes.get(actor_id)?;
        let mut url = validated_outbox(self.actors_url.as_ref()?, address)?;
        url.query_pairs_mut().append_pair("page", "1");
        Some(url)
    }

    fn object_url(&self, actor_id: &str, object_id: &str) -> Option<Url> {
        self.actor_ids.iter().find(|id| id.as_str() == actor_id)?;
        let id = Url::parse(object_id).ok()?;
        if !matches!(id.scheme(), "http" | "https")
            || id.host().is_none()
            || !id.username().is_empty()
            || id.password().is_some()
            || object_id.len() > 6000
        {
            return None;
        }
        Some(id)
    }
}

#[derive(Clone, Debug, Default, Deserialize, PartialEq, Eq, Serialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct LoginPlan {
    pub(crate) wordpress: Option<String>,
    pub(crate) jmap: Option<String>,
    pub(crate) activitypub: Option<String>,
}

impl LoginPlan {
    /// The one origin a plan's addresses share (an address on another origin is dropped).
    pub(crate) fn origin(&self) -> String {
        self.addresses()
            .first()
            .and_then(|address| Url::parse(address).ok())
            .map(|url| url.origin().ascii_serialization())
            .unwrap_or_default()
    }

    pub(crate) fn addresses(&self) -> Vec<&str> {
        [&self.wordpress, &self.jmap, &self.activitypub]
            .into_iter()
            .filter_map(|s| s.as_deref())
            .collect()
    }
}

#[derive(Serialize)]
pub struct DiscoveryResult {
    discovery: Discovery,
    login: LoginPlan,
    notices: Vec<String>,
}

#[derive(Clone, Serialize)]
#[serde(rename_all = "camelCase")]
struct AccountSummary {
    id: String,
    name: String,
    is_personal: bool,
    is_read_only: bool,
    capabilities: Vec<String>,
}

#[derive(Clone, Default, Serialize)]
pub struct Connected {
    id: u32,
    /// The saved-connection key, when the sign-in is kept in the credential store.
    saved_key: Option<String>,
    /// Who signed in, and where: the label of this connection in the GUI.
    username: String,
    server: String,
    wordpress: Option<String>,
    wordpress_document: Option<Value>,
    jmap: Option<Vec<AccountSummary>>,
    /// Session.primaryAccounts: capability URI → the Account the server suggests for it.
    jmap_primary_accounts: Option<std::collections::BTreeMap<String, String>>,
    jmap_document: Option<Value>,
    activitypub: Option<Vec<ActingActor>>,
    activitypub_document: Option<Value>,
    errors: Vec<String>,
}

#[tauri::command]
pub async fn discover_server(
    input: String,
    options: Option<DiscoveryOptions>,
    state: tauri::State<'_, Connection>,
) -> Result<DiscoveryResult, String> {
    // Discovering another server leaves the signed-in connections alone: it may be adding one.
    let revision = {
        let mut state = state.lock()?;
        state.discovery += 1;
        state.plan = None;
        state.discovery
    };
    let discovery = tauri::async_runtime::spawn_blocking(move || discover_selected(&input, options.unwrap_or_default()))
        .await
        .map_err(|_| "Discovery did not complete")??;
    let (login, notices) = login_plan(&discovery);
    let mut state = state.lock()?;
    if state.discovery != revision {
        return Err("Discovery was superseded".into());
    }
    state.plan = Some(login.clone());
    Ok(DiscoveryResult {
        discovery,
        login,
        notices,
    })
}

/// What a finished sign-in should keep on this device, if anything.
struct Persist {
    dir: std::path::PathBuf,
    entry: crate::saved::Saved,
    secret: String,
}

/// Signs in over the network without holding any lock, then hands the result to
/// [`finish_sign_in`], the one place a sign-in becomes a connection and a saved entry.
async fn add_connection(
    state: &Connection,
    plan: LoginPlan,
    credentials: Credentials,
    data_dir: Option<std::path::PathBuf>,
    persist: Option<Persist>,
    still_saved: Option<(std::path::PathBuf, String)>,
    secrets: &dyn crate::saved::Secrets,
) -> Result<Connected, String> {
    let login = (plan.origin(), credentials.user.clone(), crate::saved::PASSWORD);
    let epoch = {
        let state = state.lock()?;
        if state.connections.values().any(|active| active.login == login) {
            return Err("This login is already connected. Switch to it instead.".into());
        }
        state.epoch
    };
    let (connected, active) = tauri::async_runtime::spawn_blocking(move || connect(plan, credentials, data_dir))
        .await
        .map_err(|_| "Sign-in did not complete")??;
    finish_sign_in(state, epoch, active, connected, persist, still_saved, secrets)
}

/// Decides a finished sign-in under the connection lock, which every sign-out also holds, so the
/// two cannot interleave: a sign-out that came first leaves nothing to save or admit, and one that
/// comes after finds and forgets what was just saved.
///
/// In order: refused if everything was signed out since it began (`epoch`), if a restored entry
/// was removed meanwhile (`still_saved`), or if the same login finished first; then it saves the
/// secret and list entry when asked, and admits the connection. A failure to save keeps the
/// sign-in in memory and says so.
fn finish_sign_in(
    state: &Connection,
    epoch: u64,
    mut active: Active,
    mut connected: Connected,
    persist: Option<Persist>,
    still_saved: Option<(std::path::PathBuf, String)>,
    secrets: &dyn crate::saved::Secrets,
) -> Result<Connected, String> {
    let mut state = state.lock()?;
    if state.epoch != epoch {
        return Err("Signed out while signing in".into());
    }
    if let Some((dir, key)) = &still_saved {
        if !crate::saved::list(dir)?.iter().any(|item| &item.key == key) {
            return Err("This saved connection was removed while it was signing in".into());
        }
        active.saved = Some(key.clone());
        connected.saved_key = Some(key.clone());
    }
    if state.connections.values().any(|other| other.login == active.login) {
        return Err("This login is already connected. Switch to it instead.".into());
    }
    if let Some(Persist { dir, entry, secret }) = persist {
        let key = entry.key.clone();
        let saved = secrets.put(&key, &secret).and_then(|()| {
            crate::saved::upsert(&dir, entry).inspect_err(|_| {
                let _ = secrets.delete(&key); // no secret without its list entry
            })
        });
        match saved {
            Ok(()) => {
                active.saved = Some(key.clone());
                connected.saved_key = Some(key);
            }
            Err(reason) => connected.errors.push(format!("Not saved on this device: {reason}.")),
        }
    }
    state.next_id += 1;
    connected.id = state.next_id;
    active.summary = connected.clone();
    state.connections.insert(connected.id, Arc::new(active));
    Ok(connected)
}

fn data_dir<R: tauri::Runtime>(app: &tauri::AppHandle<R>) -> Option<std::path::PathBuf> {
    use tauri::Manager;
    app.path().app_data_dir().ok()
}

#[tauri::command]
pub async fn connect_server<R: tauri::Runtime>(
    approved: Vec<String>,
    username: String,
    password: String,
    remember_sign_in: Option<bool>,
    app: tauri::AppHandle<R>,
    state: tauri::State<'_, Connection>,
) -> Result<Connected, String> {
    let data_dir = data_dir(&app);
    if username.trim().is_empty() || username.contains(':') || password.is_empty() {
        return Err("Enter a valid username and password".into());
    }
    let plan = {
        let state = state.lock()?;
        let plan = state
            .plan
            .clone()
            .ok_or("Discover the server before signing in")?;
        if approved.is_empty() || plan.addresses() != approved {
            return Err("The approved addresses changed. Discover the server again.".into());
        }
        plan
    };
    let mut cannot_save = None;
    let persist = if remember_sign_in.unwrap_or(true) {
        match &data_dir {
            Some(dir) => Some(Persist {
                dir: dir.clone(),
                entry: crate::saved::Saved {
                    key: crate::saved::key_for(Some(dir), &plan.origin(), &username, crate::saved::PASSWORD),
                    username: username.clone(),
                    server: plan.origin(),
                    auth: crate::saved::PASSWORD.into(),
                    plan: plan.clone(),
                    forget_failed: false,
                },
                secret: password.clone(),
            }),
            None => {
                cannot_save = Some("Not saved on this device: no app data folder.");
                None
            }
        }
    } else {
        None
    };
    let credentials = Credentials { user: username, secret: password };
    let vault = crate::saved::Vault::platform();
    let mut connected = add_connection(&state, plan, credentials, data_dir, persist, None, &vault).await?;
    connected.errors.extend(cannot_save.map(String::from));
    Ok(connected)
}

/// What the app can restore at start: the saved list, and whether secrets can be stored here.
#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct SavedConnections {
    can_store_secrets: bool,
    items: Vec<crate::saved::Saved>,
    active: Vec<Connected>,
}

#[tauri::command]
pub fn saved_connections<R: tauri::Runtime>(app: tauri::AppHandle<R>, state: tauri::State<'_, Connection>) -> Result<SavedConnections, String> {
    let state = state.lock()?;
    let items = match data_dir(&app) {
        Some(dir) => crate::saved::list(&dir)?,
        None => Vec::new(),
    };
    let active = state.connections.values().map(|entry| entry.summary.clone()).collect();
    Ok(SavedConnections { can_store_secrets: crate::saved::Vault::platform().available(), items, active })
}

/// Signs a saved connection in. With no `password` the stored secret is used, and a missing one
/// answers "Sign-in required"; a password typed again replaces the stored secret once it works.
#[tauri::command]
pub async fn restore_connection<R: tauri::Runtime>(
    key: String,
    password: Option<String>,
    app: tauri::AppHandle<R>,
    state: tauri::State<'_, Connection>,
) -> Result<Connected, String> {
    let dir = data_dir(&app).ok_or("No app data folder")?;
    let saved = crate::saved::list(&dir)?
        .into_iter()
        .find(|item| item.key == key)
        .ok_or("This saved connection no longer exists")?;
    saved.restorable()?;
    let vault = crate::saved::Vault::platform();
    let typed = password.filter(|value| !value.is_empty());
    let secret = match &typed {
        Some(value) => value.clone(),
        None => vault.get(&key)?.ok_or("Sign-in required")?,
    };
    let persist = typed.map(|secret| Persist { dir: dir.clone(), entry: saved.clone(), secret });
    let credentials = Credentials { user: saved.username.clone(), secret };
    add_connection(&state, saved.plan.clone(), credentials, Some(dir.clone()), persist, Some((dir, key)), &vault).await
}

/// Signing out always ends the connection; forgetting the saved sign-in can fail on its own and
/// is reported apart, so the GUI never keeps showing a connection that is gone.
#[derive(Default, Serialize)]
pub struct SignedOut {
    warnings: Vec<String>,
}

/// Forgets one saved sign-in: the secret first, and the list entry only once the secret is gone,
/// so a failure leaves an entry the person can see and retry rather than an orphaned secret.
/// Callers hold the connection lock, which a finishing sign-in also holds.
fn forget(dir: &std::path::Path, key: &str, secrets: &dyn crate::saved::Secrets) -> Result<(), String> {
    if let Err(reason) = secrets.delete(key) {
        // The secret is still there: keep the entry for another try, but never restore it.
        let _ = crate::saved::mark_forget_failed(dir, key);
        return Err(reason);
    }
    crate::saved::remove(dir, key)
}

/// Removes a saved connection and its secret. Nothing is revoked on the server.
#[tauri::command]
pub fn forget_connection<R: tauri::Runtime>(
    key: String,
    app: tauri::AppHandle<R>,
    state: tauri::State<'_, Connection>,
) -> Result<(), String> {
    let dir = data_dir(&app).ok_or("No app data folder")?;
    let _held = state.lock()?;
    forget(&dir, &key, &crate::saved::Vault::platform())
}

fn sign_out_one(state: &Connection, connection_id: u32, dir: Option<&std::path::Path>, secrets: &dyn crate::saved::Secrets) -> Result<SignedOut, String> {
    let mut state = state.lock()?;
    let ended = state.connections.remove(&connection_id);
    if state.mail_watch.as_ref().is_some_and(|(id, _)| *id == connection_id) {
        state.mail_watch = None;
    }
    let mut result = SignedOut::default();
    if let Some(mail) = ended.as_ref().and_then(|active| active.mail.as_ref()) {
        if let Err(error) = mail.forget_attachment_refs() { result.warnings.push(error); }
    }
    if let Some(key) = ended.and_then(|active| active.saved.clone()) {
        match dir {
            Some(dir) => {
                if let Err(reason) = forget(dir, &key, secrets) {
                    result.warnings.push(format!("Signed out, but the saved sign-in was not removed: {reason}"));
                }
            }
            None => result.warnings.push("Signed out, but the saved sign-in could not be reached".into()),
        }
    }
    Ok(result)
}

fn sign_out_all(state: &Connection, dir: Option<&std::path::Path>, secrets: &dyn crate::saved::Secrets) -> Result<SignedOut, String> {
    let mut state = state.lock()?;
    state.epoch += 1;
    let ended = std::mem::take(&mut state.connections);
    state.mail_watch = None;
    state.plan = None;
    let mut result = SignedOut::default();
    for mail in ended.values().filter_map(|active| active.mail.as_ref()) {
        if let Err(error) = mail.forget_attachment_refs() { result.warnings.push(error); }
    }
    if let Some(dir) = dir {
        match crate::saved::list(dir) {
            Ok(items) => {
                for item in items {
                    if let Err(reason) = forget(dir, &item.key, secrets) {
                        result.warnings.push(format!("{} @ {}: the saved sign-in was not removed: {reason}", item.username, item.server));
                    }
                }
            }
            Err(reason) => result.warnings.push(format!("Signed out, but saved sign-ins were not removed: {reason}")),
        }
    }
    Ok(result)
}

/// Ends one connection and forgets its saved sign-in. Its Mail cache stays on disk, and nothing
/// is revoked on the server.
#[tauri::command]
pub fn disconnect_server<R: tauri::Runtime>(
    connection_id: u32,
    app: tauri::AppHandle<R>,
    state: tauri::State<'_, Connection>,
) -> Result<SignedOut, String> {
    sign_out_one(&state, connection_id, data_dir(&app).as_deref(), &crate::saved::Vault::platform())
}

/// Ends every connection, cancels sign-ins in flight, and forgets every saved sign-in it can.
#[tauri::command]
pub fn disconnect_all<R: tauri::Runtime>(app: tauri::AppHandle<R>, state: tauri::State<'_, Connection>) -> Result<SignedOut, String> {
    sign_out_all(&state, data_dir(&app).as_deref(), &crate::saved::Vault::platform())
}

async fn read_ap(
    state: &Connection,
    connection_id: u32,
    address: impl FnOnce(&Active) -> Option<Url>,
) -> Result<Value, String> {
    let active = state.current(connection_id)?;
    let url =
        address(&active).ok_or("This Actor or endpoint is not available on the connection")?;
    let document = tauri::async_runtime::spawn_blocking(move || {
        active.client.activitypub_document(url.as_str())
    })
    .await
    .map_err(|_| "ActivityPub request did not complete")?
    .map_err(|error| failure("ActivityPub", &error))?;
    state.current(connection_id)?;
    Ok(document)
}

#[tauri::command]
pub async fn actor_document(
    connection_id: u32,
    actor_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<Value, String> {
    let active = state.current(connection_id)?;
    let url = active
        .actor_document_url(&actor_id)
        .ok_or("Actor is not available on this connection")?;
    let expected = actor_id.clone();
    let document =
        tauri::async_runtime::spawn_blocking(move || Client::public_actor_document(url.as_str()))
            .await
            .map_err(|_| "Actor request did not complete")?
            .map_err(|error| failure("Actor", &error))?;
    state.current(connection_id)?;
    active.remember_actor_document(&expected, &document)?;
    Ok(document)
}

#[tauri::command]
pub async fn outbox_page(
    connection_id: u32,
    actor_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<Value, String> {
    read_ap(&state, connection_id, |active| {
        active.outbox_page_url(&actor_id)
    })
    .await
}

#[tauri::command]
pub async fn object_by_id(
    connection_id: u32,
    actor_id: String,
    object_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<Value, String> {
    let active = state.current(connection_id)?;
    let url = active
        .object_url(&actor_id, &object_id)
        .ok_or("Invalid Object URI or Actor")?;
    let expected = object_id.clone();
    let document =
        tauri::async_runtime::spawn_blocking(move || Client::public_as2_document(url.as_str()))
            .await
            .map_err(|_| "Object request did not complete")?
            .map_err(|error| failure("Object", &error))?;
    state.current(connection_id)?;
    if document["id"].as_str() != Some(expected.as_str()) {
        return Err("The response does not identify the requested Object".into());
    }
    Ok(document)
}

fn validated_outbox(actors_url: &Url, address: &str) -> Option<Url> {
    let url = credential_url(address)?;
    let prefix = format!("{}/", actors_url.path());
    let actor_uuid = url.path().strip_prefix(&prefix)?.strip_suffix("/outbox")?;
    (url.origin() == actors_url.origin()
        && url.query().is_none()
        && actor_uuid.len() == 36
        && actor_uuid
            .bytes()
            .all(|ch| ch.is_ascii_hexdigit() || ch == b'-'))
    .then_some(url)
}

fn login_plan(discovery: &Discovery) -> (LoginPlan, Vec<String>) {
    let mut plan = LoginPlan::default();
    let mut notices = Vec::new();
    if let Probe::Found(info) | Probe::LoginRequired(info) = &discovery.jmap {
        plan.jmap = credential_url(&info.session_url).map(|url| url.to_string());
        if plan.jmap.is_none() {
            notices.push("JMAP requires HTTPS outside loopback development.".into());
        }
    }
    if let Probe::Found(info) = &discovery.wordpress {
        if let Some(index) = credential_url(&info.index_url) {
            if plan
                .jmap
                .as_deref()
                .is_some_and(|jmap| !same_origin(jmap, index.as_str()))
            {
                notices.push("WordPress and JMAP are on different origins. Only JMAP will receive these credentials.".into());
            } else if index.path().ends_with("/wp-json/") && index.query().is_none() {
                if info.namespaces.iter().any(|name| name == "wp/v2") {
                    plan.wordpress = index.join("wp/v2/users/me").ok().map(|url| url.to_string());
                }
                if info.ap_session_advertised {
                    plan.activitypub = index
                        .join(AP_SESSION_ROUTE.trim_start_matches('/'))
                        .ok()
                        .map(|url| url.to_string());
                }
            } else {
                // Found through ?rest_route=/ (or another non-path root). Discovery can use it;
                // signing in cannot yet, because the Actor and outbox routes are derived by path.
                notices.push(
                    "WordPress was found at a ?rest_route= address. Signing in through it is not supported yet."
                        .into(),
                );
            }
        } else {
            notices.push("WordPress requires HTTPS outside loopback development.".into());
        }
    }
    // The Pre-FEP discovery document names the Session directly and wins over the WordPress route.
    if let Probe::Found(info) = &discovery.activitypub_session {
        match credential_url(&info.session_url) {
            Some(url) if plan.jmap.as_deref().is_none_or(|jmap| same_origin(jmap, url.as_str())) => {
                plan.activitypub = Some(url.to_string());
            }
            Some(_) => notices.push("ActivityPub and JMAP are on different origins. Only JMAP will receive these credentials.".into()),
            None => notices.push("ActivityPub requires HTTPS outside loopback development.".into()),
        }
    }
    (plan, notices)
}

fn credential_url(value: &str) -> Option<Url> {
    let url = Url::parse(value).ok()?;
    let loopback = match url.host() {
        Some(Host::Domain("localhost")) => true,
        Some(Host::Ipv4(ip)) => ip.is_loopback(),
        Some(Host::Ipv6(ip)) => ip.is_loopback(),
        _ => false,
    };
    (url.host().is_some()
        && url.username().is_empty()
        && url.password().is_none()
        && url.fragment().is_none()
        && (url.scheme() == "https" || (url.scheme() == "http" && loopback)))
        .then_some(url)
}

fn same_origin(a: &str, b: &str) -> bool {
    matches!((credential_url(a), credential_url(b)), (Some(a), Some(b)) if a.origin() == b.origin())
}

fn connect(
    plan: LoginPlan,
    credentials: Credentials,
    data_dir: Option<std::path::PathBuf>,
) -> Result<(Connected, Active), String> {
    let username = credentials.user.clone();
    let login = (plan.origin(), username.clone(), crate::saved::PASSWORD);
    let server = plan.origin();
    let client = Client::new(credentials);
    let mut mail = None;
    let mut contacts = None;
    let mut files_session = None;
    let mut connected = Connected {
        username: username.clone(),
        server,
        ..Connected::default()
    };
    if let Some(address) = &plan.wordpress {
        match client.wordpress_user_document(address) {
            Ok(document) => {
                connected.wordpress = document["name"].as_str().map(str::to_owned);
                connected.wordpress_document = Some(document);
            }
            Err(error) => connected.errors.push(failure("WordPress", &error)),
        }
    }
    if let Some(address) = &plan.jmap {
        let session = client
            .session_document(address)
            .and_then(|(session, document)| {
                if !same_origin(address, &session.api_url) {
                    return Err(Error::Shape(
                        "JMAP API origin differs from the approved Session origin".into(),
                    ));
                }
                // A server may return its Session document to anonymous visitors.
                client.echo(
                    &session,
                    serde_json::json!({"connectionProbe": true}),
                    "connect",
                )?;
                Ok((session, document))
            });
        match session {
            Ok((session, document)) => {
                if let Some(reason) = session.empty_account_diagnostic() {
                    connected.errors.push(format!("JMAP: {reason}"));
                }
                connected.jmap_document = Some(document);
                if !session.accounts_with(axismundi_client_core::jmap::filenode::CAPABILITY).is_empty() {
                    files_session = Some(session.clone());
                }
                connected.jmap_primary_accounts = Some(session.primary_accounts.clone());
                connected.jmap = Some(
                    session
                        .accounts
                        .iter()
                        .map(|(id, account)| AccountSummary {
                            id: id.clone(),
                            name: account.name.clone(),
                            is_personal: account.is_personal,
                            is_read_only: account.is_read_only,
                            capabilities: account.account_capabilities.keys().cloned().collect(),
                        })
                        .collect(),
                );
                if session.capabilities.contains_key(axismundi_client_core::jmap::contacts::CAPABILITY) {
                    match data_dir.as_deref() {
                        Some(dir) => match crate::contacts::Contacts::open(dir, session.clone(), &username) {
                            Ok(opened) => contacts = Some(opened),
                            Err(error) => connected.errors.push(error),
                        },
                        None => connected.errors.push("Contacts cache: no app data folder.".into()),
                    }
                }
                if session.capabilities.contains_key(axismundi_client_core::jmap::mail::CAPABILITY) {
                    match data_dir.as_deref() {
                        Some(dir) => match crate::mail::Mail::open(dir, session, &username) {
                            Ok(opened) => mail = Some(opened),
                            Err(error) => connected.errors.push(error),
                        },
                        None => connected.errors.push("Mail cache: no app data folder.".into()),
                    }
                }
            }
            Err(error) => connected.errors.push(failure("JMAP", &error)),
        }
    }
    if let Some(address) = &plan.activitypub {
        match client.acting_session(address) {
            Ok((actors, document)) => {
                connected.activitypub = Some(actors);
                connected.activitypub_document = Some(document);
            }
            Err(error) => connected.errors.push(failure("ActivityPub", &error)),
        }
    }
    if connected.wordpress.is_none() && connected.jmap.is_none() && connected.activitypub.is_none()
    {
        return Err(connected.errors.join(" "));
    }
    let actors_url = plan
        .activitypub
        .as_deref()
        .and_then(credential_url)
        .and_then(|url| url.join("../../activitypub/actors").ok());
    let actor_ids = connected
        .activitypub
        .iter()
        .flatten()
        .map(|actor| actor.id.clone())
        .collect();
    Ok((
        connected,
        Active {
            summary: Connected::default(),
            login,
            saved: None,
            client,
            mail,
            contacts,
            files_session,
            actors_url,
            actor_ids,
            outboxes: Mutex::new(Default::default()),
        },
    ))
}

pub(crate) fn failure(service: &str, error: &Error) -> String {
    // Never send arbitrary remote response bodies back to the webview.
    match error {
        Error::Problem { status, .. } | Error::Status { status, .. } => {
            format!("{service}: HTTP {status}.")
        }
        Error::Transport(_) => format!("{service}: request failed."),
        Error::Shape(_) => format!("{service}: unexpected response or unapproved API origin."),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::saved::Secrets;
    use axismundi_client_core::discovery::{Jmap, WordPress, discover};

    #[test]
    #[ignore = "requires a local Axismundi instance and a temporary Application Password"]
    fn real_axismundi_connection() {
        let origin = std::env::var("AX_GUI_TEST_ORIGIN").expect("local server URL");
        let origin = credential_url(&origin).expect("valid test URL");
        assert!(matches!(origin.host_str(), Some("localhost" | "127.0.0.1")));
        let (plan, _) = login_plan(&discover(origin.as_str()).expect("discover local server"));
        assert_eq!(plan.addresses().len(), 3);
        let (result, active) = connect(
            plan,
            Credentials {
                user: std::env::var("AX_GUI_TEST_USER").expect("test username"),
                secret: std::env::var("AX_GUI_TEST_SECRET").expect("test Application Password"),
            },
            None,
        )
        .expect("sign in to local server");
        assert!(result.errors.is_empty(), "{:?}", result.errors);
        assert!(result.wordpress.is_some());
        assert!(result.wordpress_document.unwrap()["id"].is_number());
        assert!(result.jmap_document.unwrap()["accounts"].is_object());
        assert_eq!(result.activitypub_document.unwrap()["type"], "c2s:Session");
        let accounts = result.jmap.expect("JMAP Accounts");
        let actors = result.activitypub.expect("ActivityPub Actors");
        // JMAP Accounts and ActivityPub Actors are separate sessions sharing only
        // authentication; the fixture needs both, never a pairing between them.
        assert!(!accounts.is_empty(), "the fixture login needs a JMAP Account");
        assert!(!actors.is_empty(), "the fixture login needs an ActivityPub Actor");
        let mut resolved = false;
        for actor in actors {
            let actor_url = active.actor_document_url(&actor.id).unwrap();
            let document = Client::public_actor_document(actor_url.as_str()).unwrap();
            assert_eq!(document["id"], actor.id);
            active
                .remember_actor_document(&actor.id, &document)
                .unwrap();
            let outbox_url = active.outbox_page_url(&actor.id).unwrap();
            let page = active
                .client
                .activitypub_document(outbox_url.as_str())
                .unwrap();
            for item in page["orderedItems"]
                .as_array()
                .into_iter()
                .flatten()
                .take(8)
            {
                let Some(id) = item["object"]
                    .as_str()
                    .or_else(|| item["object"]["id"].as_str())
                else {
                    continue;
                };
                let Some(object_url) = active.object_url(&actor.id, id) else {
                    continue;
                };
                let Ok(object) = Client::public_as2_document(object_url.as_str()) else {
                    continue;
                };
                if object["id"] == id {
                    resolved = true;
                    break;
                }
            }
            if resolved {
                break;
            }
        }
        assert!(
            resolved,
            "the fixture login needs an outbox Object available in the registry"
        );
    }

    #[test]
    fn outbox_credentials_are_bound_to_the_approved_rest_origin_and_route() {
        let actors =
            Url::parse("https://blog.test/sub/wp-json/axismundi/activitypub/actors").unwrap();
        let uuid = "12345678-1234-1234-1234-123456789abc";
        let address = format!("{actors}/{uuid}/outbox");
        assert!(validated_outbox(&actors, &address).is_some());
        assert!(validated_outbox(&actors, &address.replace("blog.test", "other.test")).is_none());
        assert!(validated_outbox(&actors, &address.replace("/outbox", "/inbox")).is_none());
        assert!(validated_outbox(&actors, &format!("{address}?rest_route=/wp/v2/users")).is_none());
    }

    fn offline(user: &str) -> Arc<Active> {
        Arc::new(Active {
            summary: Connected::default(),
            login: ("https://mail.test".into(), user.into(), crate::saved::PASSWORD),
            saved: None,
            client: Client::without_redirects(Credentials { user: user.into(), secret: "secret".into() }),
            mail: None,
            contacts: None,
            files_session: None,
            actors_url: None,
            actor_ids: vec![],
            outboxes: Mutex::new(Default::default()),
        })
    }

    /// An in-memory credential store, so the ordering tests touch no real vault. `refuse_delete`
    /// stands in for a vault that will not let a secret go.
    #[derive(Default)]
    struct FakeSecrets(Mutex<std::collections::BTreeMap<String, String>>, std::sync::atomic::AtomicBool);
    impl FakeSecrets {
        fn refuse_delete(&self) {
            self.1.store(true, std::sync::atomic::Ordering::SeqCst);
        }
    }
    impl crate::saved::Secrets for FakeSecrets {
        fn put(&self, key: &str, secret: &str) -> Result<(), String> {
            self.0.lock().unwrap().insert(key.into(), secret.into());
            Ok(())
        }
        fn get(&self, key: &str) -> Result<Option<String>, String> {
            Ok(self.0.lock().unwrap().get(key).cloned())
        }
        fn delete(&self, key: &str) -> Result<(), String> {
            if self.1.load(std::sync::atomic::Ordering::SeqCst) {
                return Err("Credential store: access denied".into());
            }
            self.0.lock().unwrap().remove(key);
            Ok(())
        }
    }

    /// Signing out when the vault will not delete: the connection ends, the entry stays for another
    /// try, and it is not restorable — the person signed out.
    #[test]
    fn a_secret_that_cannot_be_deleted_is_not_restored_next_time() {
        let (state, dir, secrets) = (Connection::default(), scratch("refused"), FakeSecrets::default());
        let epoch = state.lock().unwrap().epoch;
        let connected = finish_sign_in(&state, epoch, unwrap(offline("alice")), Connected::default(), Some(persist(&dir, "conn-a")), None, &secrets).unwrap();
        secrets.refuse_delete();
        let out = sign_out_one(&state, connected.id, Some(&dir), &secrets).unwrap();
        assert_eq!(out.warnings.len(), 1);
        assert!(state.lock().unwrap().connections.is_empty());
        let left = crate::saved::list(&dir).unwrap();
        assert_eq!(left.len(), 1);
        assert!(left[0].forget_failed);
        assert!(left[0].restorable().is_err());
        std::fs::remove_dir_all(&dir).unwrap();
    }

    fn scratch(name: &str) -> std::path::PathBuf {
        let dir = std::env::temp_dir().join(format!("axismundi-{name}-{}", std::process::id()));
        let _ = std::fs::remove_dir_all(&dir);
        std::fs::create_dir_all(&dir).unwrap();
        dir
    }

    fn persist(dir: &std::path::Path, key: &str) -> Persist {
        Persist {
            dir: dir.into(),
            entry: crate::saved::Saved {
                key: key.into(),
                username: "alice".into(),
                server: "https://mail.test".into(),
                auth: crate::saved::PASSWORD.into(),
                plan: LoginPlan { jmap: Some("https://mail.test/session".into()), ..LoginPlan::default() },
                forget_failed: false,
            },
            secret: "test-only".into(),
        }
    }

    fn unwrap(active: Arc<Active>) -> Active {
        Arc::try_unwrap(active).ok().unwrap()
    }

    /// Sign-out of everything lands while the network sign-in is in flight: nothing is admitted,
    /// and nothing is saved — no list entry, no secret.
    #[test]
    fn sign_out_first_leaves_nothing_saved_or_connected() {
        let (state, dir, secrets) = (Connection::default(), scratch("out-first"), FakeSecrets::default());
        let epoch = state.lock().unwrap().epoch;
        sign_out_all(&state, Some(&dir), &secrets).unwrap();
        let late = finish_sign_in(&state, epoch, unwrap(offline("alice")), Connected::default(), Some(persist(&dir, "conn-a")), None, &secrets);
        assert_eq!(late.err().as_deref(), Some("Signed out while signing in"));
        assert!(state.lock().unwrap().connections.is_empty());
        assert!(crate::saved::list(&dir).unwrap().is_empty());
        assert_eq!(secrets.get("conn-a").unwrap(), None);
        std::fs::remove_dir_all(&dir).unwrap();
    }

    /// The sign-in finishes and saves first; signing out after forgets what it saved.
    #[test]
    fn save_first_then_sign_out_forgets_what_was_saved() {
        let (state, dir, secrets) = (Connection::default(), scratch("save-first"), FakeSecrets::default());
        let epoch = state.lock().unwrap().epoch;
        let connected = finish_sign_in(&state, epoch, unwrap(offline("alice")), Connected::default(), Some(persist(&dir, "conn-a")), None, &secrets).unwrap();
        assert_eq!(connected.saved_key.as_deref(), Some("conn-a"));
        let attached = state.current(connected.id).unwrap();
        assert_eq!(attached.summary.id, connected.id);
        assert_eq!(attached.summary.saved_key.as_deref(), Some("conn-a"));
        assert_eq!(secrets.get("conn-a").unwrap().as_deref(), Some("test-only"));
        assert_eq!(crate::saved::list(&dir).unwrap().len(), 1);
        let out = sign_out_one(&state, connected.id, Some(&dir), &secrets).unwrap();
        assert!(out.warnings.is_empty());
        assert!(state.lock().unwrap().connections.is_empty());
        assert!(crate::saved::list(&dir).unwrap().is_empty());
        assert_eq!(secrets.get("conn-a").unwrap(), None);
        std::fs::remove_dir_all(&dir).unwrap();
    }

    /// A restore whose saved entry is removed while it signs in is neither admitted nor re-saved,
    /// even when a password was typed again.
    #[test]
    fn a_restore_whose_entry_was_removed_does_not_come_back() {
        let (state, dir, secrets) = (Connection::default(), scratch("removed"), FakeSecrets::default());
        let epoch = state.lock().unwrap().epoch;
        // The entry existed when the restore began, and was then removed.
        crate::saved::upsert(&dir, persist(&dir, "conn-a").entry).unwrap();
        forget(&dir, "conn-a", &secrets).unwrap();
        let late = finish_sign_in(&state, epoch, unwrap(offline("alice")), Connected::default(),
            Some(persist(&dir, "conn-a")), Some((dir.clone(), "conn-a".into())), &secrets);
        assert!(late.is_err());
        assert!(state.lock().unwrap().connections.is_empty());
        assert!(crate::saved::list(&dir).unwrap().is_empty());
        assert_eq!(secrets.get("conn-a").unwrap(), None);
        std::fs::remove_dir_all(&dir).unwrap();
    }

    /// Two logins live side by side; ending one leaves the other, and its id never comes back.
    #[test]
    fn connections_are_independent_and_an_ended_one_stays_ended() {
        let state = Connection::default();
        {
            let mut inner = state.lock().unwrap();
            inner.next_id = 2;
            inner.connections.insert(1, offline("alice"));
            inner.connections.insert(2, offline("team"));
        }
        assert!(state.current(1).is_ok() && state.current(2).is_ok());
        state.lock().unwrap().connections.remove(&1);
        assert_eq!(state.current(1).err().as_deref(), Some("This connection has ended"));
        assert_eq!(state.current(2).unwrap().login.1, "team");
        state.lock().unwrap().connections.clear();
        assert!(state.current(2).is_err());
    }

    #[test]
    fn actor_reference_is_not_a_collection_query_and_outbox_comes_from_document() {
        let id = "https://blog.test/actors/12345678-1234-1234-1234-123456789abc";
        let root = Url::parse("https://blog.test/wp-json/axismundi/activitypub/actors").unwrap();
        let address = format!("{root}/12345678-1234-1234-1234-123456789abc/outbox");
        let active = Active {
            summary: Connected::default(),
            login: (String::new(), "user".into(), crate::saved::PASSWORD),
            saved: None,
            client: Client::without_redirects(Credentials {
                user: "user".into(),
                secret: "secret".into(),
            }),
            mail: None,
            contacts: None,
            files_session: None,
            actors_url: Some(root),
            actor_ids: vec![id.into()],
            outboxes: Mutex::new(Default::default()),
        };
        assert_eq!(active.actor_document_url(id).unwrap().as_str(), id);
        assert!(
            active
                .actor_document_url("https://other.test/actor")
                .is_none()
        );
        assert!(active.outbox_page_url(id).is_none());
        let object_id = "https://other.test/?p=123&example=2";
        assert_eq!(
            active.object_url(id, object_id).unwrap().as_str(),
            object_id
        );
        assert!(
            active
                .remember_actor_document(
                    id,
                    &serde_json::json!({
                        "id": "https://other.test/actor", "type": "Person", "outbox": address
                    })
                )
                .is_err()
        );
        active
            .remember_actor_document(
                id,
                &serde_json::json!({
                    "id": id, "type": "Person", "outbox": address
                }),
            )
            .unwrap();
        assert_eq!(
            active.outbox_page_url(id).unwrap().as_str(),
            format!("{address}?page=1")
        );
    }

    #[test]
    fn the_well_known_activitypub_session_wins_over_the_wordpress_route() {
        let discovery = Discovery {
            input: "blog.test".into(),
            base: "https://blog.test".into(),
            wordpress: Probe::Found(WordPress {
                index_url: "https://blog.test/wp-json/".into(),
                found_by: "link_header",
                name: "Blog".into(),
                namespaces: vec!["wp/v2".into()],
                ap_session_advertised: true,
            }),
            jmap: Probe::Skipped,
            activitypub: Probe::Skipped,
            activitypub_session: Probe::Found(axismundi_client_core::discovery::ActivityPubSession {
                session_url: "https://blog.test/wp-json/axismundi/activitypub/session".into(),
            }),
        };
        let (plan, _) = login_plan(&discovery);
        assert_eq!(plan.activitypub.as_deref(), Some("https://blog.test/wp-json/axismundi/activitypub/session"));
    }

    #[test]
    fn credentials_never_share_a_plan_across_origins() {
        let mut discovery = Discovery {
            input: "blog.test".into(),
            base: "https://blog.test".into(),
            wordpress: Probe::Found(WordPress {
                index_url: "https://blog.test/sub/wp-json/".into(),
                found_by: "html_link",
                name: "Blog".into(),
                namespaces: vec!["wp/v2".into(), "axismundi/activitypub".into()],
                ap_session_advertised: true,
            }),
            jmap: Probe::LoginRequired(Jmap {
                session_url: "https://jmap.test/session".into(),
                origin_changed: true,
                srv: None,
            }),
            activitypub: Probe::NotFound("not advertised".into()),
            activitypub_session: Probe::Skipped,
        };
        let (plan, _) = login_plan(&discovery);
        assert_eq!(plan.addresses(), ["https://jmap.test/session"]);
        discovery.jmap = Probe::LoginRequired(Jmap {
            session_url: "https://blog.test/session".into(),
            origin_changed: false,
            srv: None,
        });
        let (plan, _) = login_plan(&discovery);
        assert_eq!(
            plan.addresses(),
            [
                "https://blog.test/sub/wp-json/wp/v2/users/me",
                "https://blog.test/session",
                "https://blog.test/sub/wp-json/axismundi/v1/users/me",
            ]
        );
        assert!(credential_url("http://blog.test/session").is_none());
        assert!(credential_url("http://localhost:8884/session").is_some());
        if let Probe::Found(info) = &mut discovery.wordpress {
            info.ap_session_advertised = false;
        }
        assert!(login_plan(&discovery).0.activitypub.is_none());
    }
}
