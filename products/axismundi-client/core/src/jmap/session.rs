//! The Session resource (RFC 8620 §2): accounts, capabilities, and the URLs every request uses.
use std::collections::BTreeMap;

use serde::Deserialize;
use serde_json::Value;

use crate::{Error, http::Client};

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
    #[serde(default)]
    pub username: String,
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
    /// Report facts, not a guessed authentication failure: zero accounts can be valid.
    pub fn empty_account_diagnostic(&self) -> Option<&'static str> {
        if !self.accounts.is_empty() { return None; }
        Some(if self.username.trim().is_empty() {
            "Session returned no username and no accessible accounts; authentication is unconfirmed. Check credentials and the Session endpoint."
        } else {
            "Session returned a username but no accessible accounts; this may be a valid user without account access."
        })
    }
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

impl Client {
    /// Read the Session resource. This is the only URL the caller supplies.
    pub fn session(&self, session_url: &str) -> Result<Session, Error> {
        self.session_document(session_url).map(|(session, _)| session)
    }

    pub fn session_document(&self, session_url: &str) -> Result<(Session, Value), Error> {
        let (body, response_url) = self.get(session_url)?;
        let document: Value = serde_json::from_str(&body)
            .map_err(|error| Error::Shape(format!("session: {error}")))?;
        let mut session: Session = serde_json::from_value(document.clone())
            .map_err(|error| Error::Shape(format!("session: {error}")))?;
        session.api_url = resolve_api_url(&response_url, &session.api_url)?;
        Ok((session, document))
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

#[cfg(test)]
mod tests {
    use super::*;
    use serde_json::json;

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
    fn empty_accounts_do_not_prove_authentication_failure() {
        let mut session: Session=serde_json::from_value(json!({"apiUrl":"https://example.test/api","accounts":{}})).unwrap();
        assert!(session.empty_account_diagnostic().unwrap().contains("unconfirmed"));
        session.username="named-user".into();
        assert!(session.empty_account_diagnostic().unwrap().contains("valid user"));
        assert!(!session.empty_account_diagnostic().unwrap().contains("unsupported"));
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
