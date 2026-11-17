//! A JMAP client core that holds no knowledge of any particular server.
//!
//! The one rule this crate exists to enforce: every URL comes from the Session
//! resource. The caller supplies a Session URL and credentials, and nothing else
//! about the server's shape is assumed - no path suffix is appended, no account
//! identifier is guessed, and no capability is presumed present. A client that
//! builds `apiUrl` itself cannot be evidence of interoperability, because it would
//! only ever work against the server it was written for.

use std::collections::BTreeMap;
use std::fmt;

use serde::{Deserialize, Serialize};
use serde_json::{Value, json};

pub mod discovery;

pub const CORE_CAPABILITY: &str = "urn:ietf:params:jmap:core";

/// Username and secret for HTTP Basic. Never printed.
pub struct Credentials {
    pub user: String,
    pub secret: String,
}

impl fmt::Debug for Credentials {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        // The secret must not reach a log, a panic message or an error report.
        f.debug_struct("Credentials")
            .field("user", &self.user)
            .field("secret", &"<redacted>")
            .finish()
    }
}

#[derive(Debug, Deserialize)]
pub struct Account {
    #[serde(default)]
    pub name: String,
    #[serde(rename = "isPersonal", default)]
    pub is_personal: bool,
    #[serde(rename = "isReadOnly", default)]
    pub is_read_only: bool,
    #[serde(rename = "accountCapabilities", default)]
    pub account_capabilities: BTreeMap<String, Value>,
    #[serde(rename = "actorUri", default)]
    pub actor_uri: Option<String>,
}

#[derive(Debug, Deserialize, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct ActingActor {
    pub id: String,
    pub name: String,
    #[serde(rename = "type")]
    pub kind: String,
    pub outbox_submit_url: String,
}

#[derive(Debug, Deserialize)]
pub struct Session {
    #[serde(rename = "apiUrl")]
    pub api_url: String,
    #[serde(rename = "downloadUrl", default)]
    pub download_url: String,
    #[serde(rename = "uploadUrl", default)]
    pub upload_url: String,
    #[serde(default)]
    pub capabilities: BTreeMap<String, Value>,
    #[serde(default)]
    pub accounts: BTreeMap<String, Account>,
    #[serde(rename = "primaryAccounts", default)]
    pub primary_accounts: BTreeMap<String, String>,
    #[serde(default)]
    pub state: String,
}

impl Session {
    /// The accounts that advertise a capability.
    ///
    /// RFC 8621 puts `accountCapabilities` on the account, so presence is per account
    /// and is never inferred from the session-level `capabilities` map.
    pub fn accounts_with(&self, capability: &str) -> Vec<&str> {
        self.accounts
            .iter()
            .filter(|(_, account)| account.account_capabilities.contains_key(capability))
            .map(|(id, _)| id.as_str())
            .collect()
    }

    /// The server's own choice for a capability, when it states one.
    pub fn primary_account(&self, capability: &str) -> Option<&str> {
        self.primary_accounts.get(capability).map(String::as_str)
    }
}

/// One `[name, arguments, callId]` invocation.
#[derive(Debug)]
pub struct Invocation {
    pub name: String,
    pub arguments: Value,
    pub call_id: String,
}

impl Invocation {
    /// JMAP reports a method-level failure as an invocation literally named `error`.
    /// An HTTP 200 carrying one of these is a failed call, not a successful one.
    pub fn is_error(&self) -> bool {
        self.name == "error"
    }

    pub fn error_type(&self) -> Option<&str> {
        self.arguments.get("type").and_then(Value::as_str)
    }

    /// Decode a successful Set invocation. Method errors must be handled first.
    pub fn set_outcome(&self) -> Result<SetOutcome, Error> {
        if !self.name.ends_with("/set") {
            return Err(Error::Shape("expected a successful /set invocation".into()));
        }
        serde_json::from_value(self.arguments.clone())
            .map_err(|error| Error::Shape(format!("{}: {error}", self.name)))
    }
}

#[derive(Debug, Deserialize)]
pub struct SetError {
    #[serde(rename = "type")]
    pub kind: String,
    pub description: Option<String>,
    pub properties: Option<Vec<String>>,
}

#[derive(Debug, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct SetOutcome {
    pub account_id: String,
    pub old_state: Option<String>,
    pub new_state: String,
    #[serde(default, deserialize_with = "null_as_default")]
    pub created: BTreeMap<String, Value>,
    #[serde(default, deserialize_with = "null_as_default")]
    pub updated: BTreeMap<String, Option<Value>>,
    #[serde(default, deserialize_with = "null_as_default")]
    pub destroyed: Vec<String>,
    #[serde(default, deserialize_with = "null_as_default")]
    pub not_created: BTreeMap<String, SetError>,
    #[serde(default, deserialize_with = "null_as_default")]
    pub not_updated: BTreeMap<String, SetError>,
    #[serde(default, deserialize_with = "null_as_default")]
    pub not_destroyed: BTreeMap<String, SetError>,
}

impl SetOutcome {
    pub fn fully_applied(&self) -> bool {
        self.not_created.is_empty() && self.not_updated.is_empty() && self.not_destroyed.is_empty()
    }
}

fn null_as_default<'de, D, T>(deserializer: D) -> Result<T, D::Error>
where
    D: serde::Deserializer<'de>,
    T: Deserialize<'de> + Default,
{
    Ok(Option::<T>::deserialize(deserializer)?.unwrap_or_default())
}

#[derive(Debug)]
pub struct Batch {
    pub method_responses: Vec<Invocation>,
    pub session_state: Option<String>,
}

impl Batch {
    pub fn first_named(&self, name: &str) -> Option<&Invocation> {
        self.method_responses.iter().find(|i| i.name == name)
    }

    /// Every method-level failure in the batch, which a successful status code hides.
    pub fn errors(&self) -> Vec<&Invocation> {
        self.method_responses
            .iter()
            .filter(|i| i.is_error())
            .collect()
    }
}

/// Failures an exchange can produce, kept apart because they mean different things.
#[derive(Debug)]
pub enum Error {
    /// The request never produced an HTTP response.
    Transport(String),
    /// A request-level JMAP error, which RFC 8620 serves as a problem document.
    Problem {
        status: u16,
        kind: String,
        detail: Option<String>,
    },
    /// A status this client cannot read as either success or a JMAP problem.
    Status { status: u16, body: String },
    /// The response was not the shape the specification describes.
    Shape(String),
}

impl fmt::Display for Error {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        match self {
            Error::Transport(message) => write!(f, "transport: {message}"),
            Error::Problem {
                status,
                kind,
                detail,
            } => match detail {
                Some(text) => write!(f, "jmap problem {status} {kind}: {text}"),
                None => write!(f, "jmap problem {status} {kind}"),
            },
            Error::Status { status, body } => write!(f, "http {status}: {body}"),
            Error::Shape(message) => write!(f, "unexpected response shape: {message}"),
        }
    }
}

impl std::error::Error for Error {}

pub struct Client {
    agent: ureq::Agent,
    credentials: Credentials,
}

impl Client {
    /// Verify a Core round trip without selecting an account.
    pub fn echo(&self, session: &Session, arguments: Value, call_id: &str) -> Result<(), Error> {
        if !session.capabilities.contains_key(CORE_CAPABILITY) {
            return Err(Error::Shape("Session does not advertise JMAP Core".into()));
        }
        let batch = self.request(
            session,
            &[CORE_CAPABILITY],
            vec![("Core/echo", arguments.clone(), call_id)],
        )?;
        verify_echo(&batch, &arguments, call_id)
    }

    pub fn new(credentials: Credentials) -> Self {
        Self::with_redirect_limit(credentials, None)
    }

    /// Keep a write probe on its explicitly selected development endpoint.
    pub fn without_redirects(credentials: Credentials) -> Self {
        Self::with_redirect_limit(credentials, Some(0))
    }

    fn with_redirect_limit(credentials: Credentials, limit: Option<u32>) -> Self {
        // Status codes carry meaning here: a 4xx may be a JMAP problem document that has
        // to be read, so the agent must not turn a status into an error and drop the body.
        let mut config = ureq::Agent::config_builder()
            .http_status_as_error(false)
            .user_agent("axismundi-jmap-client/0.1");
        if let Some(limit) = limit {
            config = config
                .max_redirects(limit)
                .timeout_global(Some(std::time::Duration::from_secs(30)));
        }
        let agent = config.build().into();
        Self { agent, credentials }
    }

    /// Axismundi's authenticated AP discovery projection.
    pub fn acting_actors(&self, session_url: &str) -> Result<Vec<ActingActor>, Error> {
        #[derive(Deserialize)]
        struct ActingSession {
            actors: Vec<ActingActor>,
        }
        let (body, _) = self.get_with_accept(
            session_url,
            r#"application/ld+json; profile="https://www.w3.org/ns/activitystreams""#,
        )?;
        serde_json::from_str::<ActingSession>(&body)
            .map(|session| session.actors)
            .map_err(|error| Error::Shape(format!("AP Session: {error}")))
    }

    /// Verify a WordPress login at the discovered users/me endpoint.
    pub fn wordpress_user(&self, user_url: &str) -> Result<String, Error> {
        #[derive(Deserialize)]
        struct User {
            name: String,
        }
        let (body, _) = self.get(user_url)?;
        serde_json::from_str::<User>(&body)
            .map(|user| user.name)
            .map_err(|error| Error::Shape(format!("WordPress user: {error}")))
    }

    fn authorization(&self) -> String {
        use base64::Engine as _;
        let raw = format!("{}:{}", self.credentials.user, self.credentials.secret);
        format!(
            "Basic {}",
            base64::engine::general_purpose::STANDARD.encode(raw)
        )
    }

    /// Read the Session resource. This is the only URL the caller supplies.
    pub fn session(&self, session_url: &str) -> Result<Session, Error> {
        let (body, response_url) = self.get(session_url)?;
        let mut session: Session = serde_json::from_str(&body)
            .map_err(|error| Error::Shape(format!("session: {error}")))?;
        session.api_url = resolve_api_url(&response_url, &session.api_url)?;
        Ok(session)
    }

    /// Send a method batch to the `apiUrl` the Session advertised.
    pub fn request(
        &self,
        session: &Session,
        using: &[&str],
        calls: Vec<(&str, Value, &str)>,
    ) -> Result<Batch, Error> {
        let method_calls: Vec<Value> = calls
            .into_iter()
            .map(|(name, arguments, id)| json!([name, arguments, id]))
            .collect();
        let envelope = json!({ "using": using, "methodCalls": method_calls });
        let body = self.post(&session.api_url, envelope.to_string())?;
        let parsed: Value =
            serde_json::from_str(&body).map_err(|error| Error::Shape(format!("batch: {error}")))?;
        let rows = parsed
            .get("methodResponses")
            .and_then(Value::as_array)
            .ok_or_else(|| Error::Shape("batch has no methodResponses array".into()))?;
        let mut responses = Vec::with_capacity(rows.len());
        for row in rows {
            let triple = row
                .as_array()
                .filter(|entries| entries.len() == 3)
                .ok_or_else(|| {
                    Error::Shape("a method response must be a three-element array".into())
                })?;
            responses.push(Invocation {
                name: triple[0].as_str().unwrap_or_default().to_string(),
                arguments: triple[1].clone(),
                call_id: triple[2].as_str().unwrap_or_default().to_string(),
            });
        }
        Ok(Batch {
            method_responses: responses,
            session_state: parsed
                .get("sessionState")
                .and_then(Value::as_str)
                .map(str::to_string),
        })
    }

    fn get(&self, url: &str) -> Result<(String, String), Error> {
        self.get_with_accept(url, "application/json")
    }

    fn get_with_accept(&self, url: &str, accept: &str) -> Result<(String, String), Error> {
        use ureq::ResponseExt as _;
        let response = self
            .agent
            .get(url)
            .header("Authorization", self.authorization())
            .header("Accept", accept)
            .call()
            .map_err(|error| Error::Transport(error.to_string()))?;
        let response_url = response.get_uri().to_string();
        Ok((Self::read(response)?, response_url))
    }

    fn post(&self, url: &str, payload: String) -> Result<String, Error> {
        let response = self
            .agent
            .post(url)
            .header("Authorization", self.authorization())
            .header("Accept", "application/json")
            .header("Content-Type", "application/json")
            .send(payload)
            .map_err(|error| Error::Transport(error.to_string()))?;
        Self::read(response)
    }

    /// Turn one HTTP response into either a body or the failure it actually represents.
    fn read(mut response: ureq::http::Response<ureq::Body>) -> Result<String, Error> {
        let status = response.status().as_u16();
        let content_type = response
            .headers()
            .get("content-type")
            .and_then(|value| value.to_str().ok())
            .unwrap_or_default()
            .to_string();
        let text = response
            .body_mut()
            .read_to_string()
            .map_err(|error| Error::Transport(format!("reading body: {error}")))?;
        if (200..300).contains(&status) {
            return Ok(text);
        }
        // A problem document is an answer from the protocol, not a transport failure,
        // so it keeps a variant of its own rather than collapsing into a status code.
        if content_type.starts_with("application/problem+json") {
            if let Ok(problem) = serde_json::from_str::<Value>(&text) {
                return Err(Error::Problem {
                    status,
                    kind: problem
                        .get("type")
                        .and_then(Value::as_str)
                        .unwrap_or("about:blank")
                        .to_string(),
                    detail: problem
                        .get("detail")
                        .and_then(Value::as_str)
                        .map(str::to_string),
                });
            }
        }
        Err(Error::Status { status, body: text })
    }
}

fn resolve_api_url(session_url: &str, advertised: &str) -> Result<String, Error> {
    if advertised.trim().is_empty() {
        return Err(Error::Shape("Session apiUrl is empty".into()));
    }
    let base =
        url::Url::parse(session_url).map_err(|e| Error::Shape(format!("Session URL: {e}")))?;
    let api = base
        .join(advertised)
        .map_err(|e| Error::Shape(format!("Session apiUrl: {e}")))?;
    if !matches!(api.scheme(), "https" | "http")
        || !api.username().is_empty()
        || api.password().is_some()
    {
        return Err(Error::Shape(
            "Session apiUrl must be HTTP(S) without embedded credentials".into(),
        ));
    }
    if base.scheme() == "https" && api.scheme() != "https" {
        return Err(Error::Shape("Session apiUrl downgrades HTTPS".into()));
    }
    Ok(api.into())
}

fn verify_echo(batch: &Batch, expected: &Value, call_id: &str) -> Result<(), Error> {
    match batch.method_responses.as_slice() {
        [reply]
            if reply.name == "Core/echo"
                && reply.call_id == call_id
                && &reply.arguments == expected =>
        {
            Ok(())
        }
        _ => Err(Error::Shape(
            "Core/echo response did not match the method, call ID, and arguments sent".into(),
        )),
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn set_outcome_preserves_partial_success_and_null_update_values() {
        let reply = Invocation {
            name: "Todo/set".into(),
            call_id: "probe".into(),
            arguments: json!({
                "accountId": "a1", "oldState": null, "newState": "s2",
                "created": {"local": {"id": "server-id"}},
                "updated": {"object-id": null},
                "destroyed": null,
                "notCreated": {"bad-local": {"type": "invalidProperties", "properties": ["title"]}},
                "notUpdated": {"bad-object": {"type": "forbidden"}},
                "notDestroyed": {"missing": {"type": "notFound"}}
            }),
        };
        let result = reply.set_outcome().unwrap();
        assert_eq!(result.old_state, None);
        assert_eq!(result.created["local"]["id"], "server-id");
        assert_eq!(result.updated["object-id"], None);
        assert!(result.destroyed.is_empty());
        assert_eq!(
            result.not_created["bad-local"]
                .properties
                .as_deref()
                .unwrap(),
            ["title"]
        );
        assert_eq!(result.not_updated["bad-object"].kind, "forbidden");
        assert_eq!(result.not_destroyed["missing"].kind, "notFound");
        assert!(!result.fully_applied());
    }

    /// The fixture above carries all three rejection maps at once, so it cannot
    /// say which of them `fully_applied` actually reads: dropping one from the
    /// check leaves it passing. These populate exactly one map at a time.
    #[test]
    fn each_rejection_map_alone_makes_the_outcome_partial() {
        let clean = json!({
            "accountId": "a1", "oldState": null, "newState": "s2",
            "created": null, "updated": null, "destroyed": null,
            "notCreated": null, "notUpdated": null, "notDestroyed": null
        });
        let invoke = |arguments: Value| Invocation {
            name: "Todo/set".into(),
            call_id: "probe".into(),
            arguments,
        };

        assert!(
            invoke(clean.clone()).set_outcome().unwrap().fully_applied(),
            "nothing rejected means the set applied in full"
        );

        for field in ["notCreated", "notUpdated", "notDestroyed"] {
            let mut arguments = clean.clone();
            arguments[field] = json!({ "x": { "type": "forbidden" } });
            assert!(
                !invoke(arguments).set_outcome().unwrap().fully_applied(),
                "{field} alone must make the outcome partial"
            );
        }
    }

    #[test]
    fn set_method_errors_are_not_set_outcomes() {
        let reply = Invocation {
            name: "error".into(),
            call_id: "probe".into(),
            arguments: json!({"type": "stateMismatch"}),
        };
        assert!(reply.is_error());
        assert_eq!(reply.error_type(), Some("stateMismatch"));
        assert!(reply.set_outcome().is_err());
    }

    #[test]
    fn session_resolves_the_advertised_api_reference() {
        let base = "http://127.0.0.1:18880/jmap/session";
        assert_eq!(
            resolve_api_url(base, "/jmap/").unwrap(),
            "http://127.0.0.1:18880/jmap/"
        );
        assert_eq!(
            resolve_api_url(base, "api").unwrap(),
            "http://127.0.0.1:18880/jmap/api"
        );
        assert_eq!(
            resolve_api_url(base, "https://example.test/elsewhere").unwrap(),
            "https://example.test/elsewhere"
        );
        assert!(
            resolve_api_url("https://example.test/session", "http://example.test/api").is_err()
        );
    }

    #[test]
    fn echo_requires_exactly_one_matching_invocation() {
        let arguments = json!({"nested": ["\u{d55c}\u{ae00}", 42, null, {"ok": true}]});
        let mut batch = Batch {
            method_responses: vec![Invocation {
                name: "Core/echo".into(),
                arguments: arguments.clone(),
                call_id: "probe".into(),
            }],
            session_state: None,
        };
        assert!(verify_echo(&batch, &arguments, "probe").is_ok());
        assert!(verify_echo(&batch, &arguments, "wrong").is_err());
        assert!(verify_echo(&batch, &json!({}), "probe").is_err());
        batch.method_responses[0].name = "error".into();
        assert!(verify_echo(&batch, &arguments, "probe").is_err());
        batch.method_responses.clear();
        assert!(verify_echo(&batch, &arguments, "probe").is_err());
    }

    #[test]
    fn credentials_never_print_the_secret() {
        let rendered = format!(
            "{:?}",
            Credentials {
                user: "admin".into(),
                secret: "abcd efgh ijkl".into(),
            }
        );
        assert!(rendered.contains("admin"));
        assert!(!rendered.contains("abcd"));
    }

    #[test]
    fn capability_presence_is_per_account() {
        let session: Session = serde_json::from_value(json!({
            "apiUrl": "https://example.test/jmap",
            "state": "s1",
            "capabilities": { "urn:example:todo": {} },
            "accounts": {
                "a1": { "name": "with", "accountCapabilities": { "urn:example:todo": {} } },
                "a2": { "name": "without", "accountCapabilities": {} }
            }
        }))
        .expect("session parses");
        // The session-level capability map must not make a2 look capable.
        assert_eq!(session.accounts_with("urn:example:todo"), vec!["a1"]);
        assert_eq!(session.primary_account("urn:example:todo"), None);
    }
}
