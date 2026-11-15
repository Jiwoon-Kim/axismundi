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

use serde::Deserialize;
use serde_json::{json, Value};

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
        self.method_responses.iter().filter(|i| i.is_error()).collect()
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
    pub fn new(credentials: Credentials) -> Self {
        // Status codes carry meaning here: a 4xx may be a JMAP problem document that has
        // to be read, so the agent must not turn a status into an error and drop the body.
        let agent: ureq::Agent = ureq::Agent::config_builder()
            .http_status_as_error(false)
            .user_agent("axismundi-jmap-client/0.1")
            .build()
            .into();
        Self { agent, credentials }
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
        let body = self.get(session_url)?;
        serde_json::from_str(&body).map_err(|error| Error::Shape(format!("session: {error}")))
    }

    /// Send a method batch to the `apiUrl` the Session advertised, used verbatim.
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

    fn get(&self, url: &str) -> Result<String, Error> {
        let response = self
            .agent
            .get(url)
            .header("Authorization", self.authorization())
            .header("Accept", "application/json")
            .call()
            .map_err(|error| Error::Transport(error.to_string()))?;
        Self::read(response)
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

#[cfg(test)]
mod tests {
    use super::*;

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
