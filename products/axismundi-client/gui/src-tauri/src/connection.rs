use std::sync::Mutex;

use axismundi_jmap_core::{
    ActingActor, Client, Credentials, Error,
    discovery::{AP_SESSION_ROUTE, Discovery, Probe, discover},
};
use serde::Serialize;
use url::{Host, Url};

#[derive(Default)]
pub struct Connection(Mutex<Option<LoginPlan>>);

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
    wordpress: Option<String>,
    jmap: Option<Vec<AccountSummary>>,
    activitypub: Option<Vec<ActingActor>>,
    errors: Vec<String>,
}

#[tauri::command]
pub async fn discover_server(
    input: String,
    state: tauri::State<'_, Connection>,
) -> Result<DiscoveryResult, String> {
    *state
        .0
        .lock()
        .map_err(|_| "Connection state is unavailable")? = None;
    let discovery = tauri::async_runtime::spawn_blocking(move || discover(&input))
        .await
        .map_err(|_| "Discovery did not complete")??;
    let (login, notices) = login_plan(&discovery);
    *state
        .0
        .lock()
        .map_err(|_| "Connection state is unavailable")? = Some(login.clone());
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
    let plan = state
        .0
        .lock()
        .map_err(|_| "Connection state is unavailable")?
        .clone()
        .ok_or("Discover the server before signing in")?;
    if approved.is_empty() || plan.addresses() != approved {
        return Err("The approved addresses changed. Discover the server again.".into());
    }
    if username.trim().is_empty() || username.contains(':') || password.is_empty() {
        return Err("Enter a valid username and password".into());
    }
    tauri::async_runtime::spawn_blocking(move || {
        connect(
            plan,
            Credentials {
                user: username,
                secret: password,
            },
        )
    })
    .await
    .map_err(|_| "Sign-in did not complete")?
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

fn connect(plan: LoginPlan, credentials: Credentials) -> Result<Connected, String> {
    let client = Client::without_redirects(credentials);
    let mut connected = Connected::default();
    if let Some(address) = &plan.wordpress {
        match client.wordpress_user(address) {
            Ok(name) => connected.wordpress = Some(name),
            Err(error) => connected.errors.push(failure("WordPress", &error)),
        }
    }
    if let Some(address) = &plan.jmap {
        let session = client.session(address).and_then(|session| {
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
            Ok(session)
        });
        match session {
            Ok(session) => {
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
        match client.acting_actors(address) {
            Ok(actors) => connected.activitypub = Some(actors),
            Err(error) => connected.errors.push(failure("ActivityPub", &error)),
        }
    }
    if connected.wordpress.is_none() && connected.jmap.is_none() && connected.activitypub.is_none()
    {
        return Err(connected.errors.join(" "));
    }
    Ok(connected)
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
        let result = connect(
            plan,
            Credentials {
                user: std::env::var("AX_GUI_TEST_USER").expect("test username"),
                secret: std::env::var("AX_GUI_TEST_SECRET").expect("test Application Password"),
            },
        )
        .expect("sign in to local server");
        assert!(result.errors.is_empty(), "{:?}", result.errors);
        assert!(result.wordpress.is_some());
        let accounts = result.jmap.expect("JMAP Accounts");
        let actors = result.activitypub.expect("ActivityPub Actors");
        assert!(
            accounts.iter().any(|account| actors
                .iter()
                .any(|actor| { account.actor_uri.as_deref() == Some(actor.id.as_str()) })),
            "the fixture login needs an Actor present on both surfaces"
        );
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
                "https://blog.test/sub/wp-json/axismundi/activitypub/session",
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
