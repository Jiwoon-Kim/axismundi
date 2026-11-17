use std::sync::{Arc, Mutex};

use axismundi_jmap_core::{
    ActingActor, Client, Credentials, Error,
    discovery::{AP_SESSION_ROUTE, Discovery, Probe, discover},
};
use serde::Serialize;
use serde_json::Value;
use url::{Host, Url};

#[derive(Default)]
pub struct Connection(Mutex<ConnectionState>);

#[derive(Default)]
struct ConnectionState {
    revision: u32,
    plan: Option<LoginPlan>,
    active: Option<Arc<Active>>,
}

impl Connection {
    fn lock(&self) -> Result<std::sync::MutexGuard<'_, ConnectionState>, String> {
        self.0
            .lock()
            .map_err(|_| "Connection state is unavailable".into())
    }
}

struct Active {
    client: Client,
    actors_url: Option<Url>,
    outboxes: Vec<(String, String)>,
}

impl Active {
    fn actor_document_url(&self, actor_id: &str) -> Option<Url> {
        self.outboxes.iter().find(|(id, _)| id == actor_id)?;
        let mut url = self.actors_url.clone()?;
        url.query_pairs_mut().append_pair("ids", actor_id);
        Some(url)
    }

    fn outbox_page_url(&self, actor_id: &str) -> Option<Url> {
        let (_, address) = self.outboxes.iter().find(|(id, _)| id == actor_id)?;
        let mut url = validated_outbox(self.actors_url.as_ref()?, address)?;
        url.query_pairs_mut().append_pair("page", "1");
        Some(url)
    }

    fn object_url(&self, actor_id: &str, object_id: &str) -> Option<Url> {
        self.outboxes.iter().find(|(id, _)| id == actor_id)?;
        let id = Url::parse(object_id).ok()?;
        if !matches!(id.scheme(), "http" | "https")
            || id.host().is_none()
            || !id.username().is_empty()
            || id.password().is_some()
            || object_id.len() > 6000
            || object_id.contains(',')
        {
            return None;
        }
        let mut url = self.actors_url.as_ref()?.join("objects").ok()?;
        url.query_pairs_mut()
            .append_pair("ids", object_id)
            .append_pair("actor", actor_id);
        Some(url)
    }
}

#[derive(Clone, Debug, Default, PartialEq, Eq, Serialize)]
#[serde(rename_all = "camelCase")]
struct LoginPlan {
    wordpress: Option<String>,
    jmap: Option<String>,
    activitypub: Option<String>,
}

impl LoginPlan {
    fn addresses(&self) -> Vec<&str> {
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

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
struct AccountSummary {
    id: String,
    name: String,
    actor_uri: Option<String>,
    is_personal: bool,
    is_read_only: bool,
    capabilities: Vec<String>,
}

#[derive(Default, Serialize)]
pub struct Connected {
    id: u32,
    wordpress: Option<String>,
    wordpress_document: Option<Value>,
    jmap: Option<Vec<AccountSummary>>,
    jmap_document: Option<Value>,
    activitypub: Option<Vec<ActingActor>>,
    activitypub_document: Option<Value>,
    errors: Vec<String>,
}

#[tauri::command]
pub async fn discover_server(
    input: String,
    state: tauri::State<'_, Connection>,
) -> Result<DiscoveryResult, String> {
    let revision = {
        let mut state = state.lock()?;
        state.revision += 1;
        state.plan = None;
        state.active = None;
        state.revision
    };
    let discovery = tauri::async_runtime::spawn_blocking(move || discover(&input))
        .await
        .map_err(|_| "Discovery did not complete")??;
    let (login, notices) = login_plan(&discovery);
    let mut state = state.lock()?;
    if state.revision != revision {
        return Err("Discovery was superseded".into());
    }
    state.plan = Some(login.clone());
    Ok(DiscoveryResult {
        discovery,
        login,
        notices,
    })
}

#[tauri::command]
pub async fn connect_server(
    approved: Vec<String>,
    username: String,
    password: String,
    state: tauri::State<'_, Connection>,
) -> Result<Connected, String> {
    if username.trim().is_empty() || username.contains(':') || password.is_empty() {
        return Err("Enter a valid username and password".into());
    }
    let (plan, revision) = {
        let mut state = state.lock()?;
        let plan = state
            .plan
            .clone()
            .ok_or("Discover the server before signing in")?;
        if approved.is_empty() || plan.addresses() != approved {
            return Err("The approved addresses changed. Discover the server again.".into());
        }
        state.revision += 1;
        state.active = None;
        (plan, state.revision)
    };
    let (mut connected, active) = tauri::async_runtime::spawn_blocking(move || {
        connect(
            plan,
            Credentials {
                user: username,
                secret: password,
            },
        )
    })
    .await
    .map_err(|_| "Sign-in did not complete")??;
    let mut state = state.lock()?;
    if state.revision != revision {
        return Err("Sign-in was superseded".into());
    }
    connected.id = revision;
    state.active = Some(Arc::new(active));
    Ok(connected)
}

#[tauri::command]
pub fn disconnect_server(
    connection_id: u32,
    state: tauri::State<'_, Connection>,
) -> Result<(), String> {
    let mut state = state.lock()?;
    if state.revision == connection_id {
        state.revision += 1;
        state.active = None;
    }
    Ok(())
}

async fn read_ap(
    state: &Connection,
    connection_id: u32,
    address: impl FnOnce(&Active) -> Option<Url>,
) -> Result<Value, String> {
    let active = {
        let state = state.lock()?;
        if state.revision != connection_id {
            return Err("This connection has ended".into());
        }
        state
            .active
            .clone()
            .ok_or("Connect before reading documents")?
    };
    let url =
        address(&active).ok_or("This Actor or endpoint is not available on the connection")?;
    let document = tauri::async_runtime::spawn_blocking(move || {
        active.client.activitypub_document(url.as_str())
    })
    .await
    .map_err(|_| "ActivityPub request did not complete")?
    .map_err(|error| failure("ActivityPub", &error))?;
    if state.lock()?.revision != connection_id {
        return Err("This connection has ended".into());
    }
    Ok(document)
}

#[tauri::command]
pub async fn actor_document(
    connection_id: u32,
    actor_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<Value, String> {
    read_ap(&state, connection_id, |active| {
        active.actor_document_url(&actor_id)
    })
    .await
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
    read_ap(&state, connection_id, |active| {
        active.object_url(&actor_id, &object_id)
    })
    .await
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
                notices.push(
                    "This WordPress REST index does not use a supported pretty-permalink URL."
                        .into(),
                );
            }
        } else {
            notices.push("WordPress requires HTTPS outside loopback development.".into());
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

fn connect(plan: LoginPlan, credentials: Credentials) -> Result<(Connected, Active), String> {
    let client = Client::without_redirects(credentials);
    let mut connected = Connected::default();
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
                connected.jmap_document = Some(document);
                connected.jmap = Some(
                    session
                        .accounts
                        .into_iter()
                        .map(|(id, account)| AccountSummary {
                            id,
                            name: account.name,
                            actor_uri: account.actor_uri,
                            is_personal: account.is_personal,
                            is_read_only: account.is_read_only,
                            capabilities: account.account_capabilities.into_keys().collect(),
                        })
                        .collect(),
                )
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
    let outboxes = connected
        .activitypub
        .iter()
        .flatten()
        .map(|actor| (actor.id.clone(), actor.outbox_submit_url.clone()))
        .collect();
    Ok((
        connected,
        Active {
            client,
            actors_url,
            outboxes,
        },
    ))
}

fn failure(service: &str, error: &Error) -> String {
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
    use axismundi_jmap_core::discovery::{Jmap, WordPress};

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
        )
        .expect("sign in to local server");
        assert!(result.errors.is_empty(), "{:?}", result.errors);
        assert!(result.wordpress.is_some());
        assert!(result.wordpress_document.unwrap()["id"].is_number());
        assert!(result.jmap_document.unwrap()["accounts"].is_object());
        assert_eq!(result.activitypub_document.unwrap()["type"], "c2s:Session");
        let accounts = result.jmap.expect("JMAP Accounts");
        let actors = result.activitypub.expect("ActivityPub Actors");
        assert!(
            accounts.iter().any(|account| actors
                .iter()
                .any(|actor| { account.actor_uri.as_deref() == Some(actor.id.as_str()) })),
            "the fixture login needs an Actor present on both surfaces"
        );
        let mut resolved = false;
        for actor in actors {
            let actor_url = active.actor_document_url(&actor.id).unwrap();
            let document = active
                .client
                .activitypub_document(actor_url.as_str())
                .unwrap();
            assert_eq!(document["orderedItems"][0]["id"], actor.id);
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
                let object = active
                    .client
                    .activitypub_document(object_url.as_str())
                    .unwrap();
                if object["orderedItems"][0]["id"] == id {
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

    #[test]
    fn credentials_never_share_a_plan_across_origins() {
        let mut discovery = Discovery {
            input: "blog.test".into(),
            base: "https://blog.test".into(),
            wordpress: Probe::Found(WordPress {
                index_url: "https://blog.test/sub/wp-json/".into(),
                name: "Blog".into(),
                namespaces: vec!["wp/v2".into(), "axismundi/activitypub".into()],
                ap_session_advertised: true,
            }),
            jmap: Probe::LoginRequired(Jmap {
                session_url: "https://jmap.test/session".into(),
                origin_changed: true,
            }),
            activitypub: Probe::NotFound("not advertised".into()),
        };
        let (plan, _) = login_plan(&discovery);
        assert_eq!(plan.addresses(), ["https://jmap.test/session"]);
        discovery.jmap = Probe::LoginRequired(Jmap {
            session_url: "https://blog.test/session".into(),
            origin_changed: false,
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
