//! Saved connections: the list in the app data folder, the secrets in the OS credential store.
//!
//! The list holds what is needed to sign in again — username, the approved addresses — and never
//! a secret. Secrets live only in the platform store (Windows Credential Manager today). Where no
//! store is available yet (Android until its Keystore-backed store lands), sign-ins stay in memory
//! and nothing is written in their place: no plain file, no webview storage.
use std::path::{Path, PathBuf};
use std::sync::{Arc, Mutex};

use keyring_core::api::CredentialStore;
use serde::{Deserialize, Serialize};

use crate::connection::LoginPlan;

const SERVICE: &str = "axismundi-client";
const FILE: &str = "connections.json";

/// How a connection authenticates. Only a password (WordPress Application Password, JMAP Basic)
/// exists today; an OAuth connection later is a different credential even for the same username
/// on the same origin — a User-wide Session and a token bound to one Actor are not interchangeable.
pub const PASSWORD: &str = "password";

/// One saved sign-in. `key` is an id this app issues and names the secret in the credential store;
/// the username and server are for people. The secret goes only to the approved `plan` addresses.
#[derive(Clone, Debug, Deserialize, PartialEq, Eq, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct Saved {
    pub key: String,
    pub username: String,
    pub server: String,
    pub auth: String,
    pub plan: LoginPlan,
    /// Signing out could not delete the secret. The entry stays so the person can remove it
    /// again, and it is never restored automatically: they signed out.
    #[serde(default, skip_serializing_if = "std::ops::Not::not")]
    pub forget_failed: bool,
}

impl Saved {
    /// Whether a stored secret may sign this entry in without the person asking.
    pub fn restorable(&self) -> Result<(), String> {
        if self.forget_failed {
            return Err("Removing this saved sign-in failed. Remove it again.".into());
        }
        Ok(())
    }
}

/// A fresh connection id. Unique enough for one person's list: time, process and a counter.
pub fn new_key() -> String {
    use std::sync::atomic::{AtomicU64, Ordering};
    static COUNTER: AtomicU64 = AtomicU64::new(0);
    let nanos = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .map(|elapsed| elapsed.as_nanos())
        .unwrap_or_default();
    format!("conn-{nanos:x}-{:x}-{:x}", std::process::id(), COUNTER.fetch_add(1, Ordering::Relaxed))
}

/// The key of a saved entry for the same login — origin, username and auth method — or a new one.
/// Signing in again with more services then replaces that entry instead of adding a second.
pub fn key_for(dir: Option<&Path>, origin: &str, username: &str, auth: &str) -> String {
    dir.and_then(|dir| list(dir).ok())
        .and_then(|items| {
            items.into_iter().find(|item| item.server == origin && item.username == username && item.auth == auth)
        })
        .map(|item| item.key)
        .unwrap_or_else(new_key)
}

/// Where secrets go. The platform vault in the app; an in-memory fake in tests.
pub trait Secrets: Send + Sync {
    fn put(&self, key: &str, secret: &str) -> Result<(), String>;
    /// `Ok(None)` when nothing is stored: the connection needs a sign-in, which is not an error.
    fn get(&self, key: &str) -> Result<Option<String>, String>;
    /// Deleting a key with nothing stored succeeds.
    fn delete(&self, key: &str) -> Result<(), String>;
}

impl Secrets for Vault {
    fn put(&self, key: &str, secret: &str) -> Result<(), String> { Vault::put(self, key, secret) }
    fn get(&self, key: &str) -> Result<Option<String>, String> { Vault::get(self, key) }
    fn delete(&self, key: &str) -> Result<(), String> { Vault::delete(self, key) }
}

/// The platform credential store, if this build has one.
pub struct Vault {
    store: Option<Arc<CredentialStore>>,
}

impl Vault {
    pub fn platform() -> Self {
        #[cfg(windows)]
        let store = windows_native_keyring_store::Store::new()
            .ok()
            .map(|store| store as Arc<CredentialStore>);
        #[cfg(not(windows))]
        let store = None;
        Self { store }
    }

    pub fn available(&self) -> bool {
        self.store.is_some()
    }

    fn entry(&self, key: &str) -> Result<keyring_core::Entry, String> {
        let store = self.store.as_ref().ok_or("This platform cannot store sign-ins yet")?;
        store.build(SERVICE, key, None).map_err(|error| format!("Credential store: {error}"))
    }

    pub fn put(&self, key: &str, secret: &str) -> Result<(), String> {
        self.entry(key)?.set_password(secret).map_err(|error| format!("Credential store: {error}"))
    }

    /// `Ok(None)` when nothing is stored: the connection needs a sign-in, which is not an error.
    pub fn get(&self, key: &str) -> Result<Option<String>, String> {
        match self.entry(key)?.get_password() {
            Ok(secret) => Ok(Some(secret)),
            Err(keyring_core::Error::NoEntry) => Ok(None),
            Err(error) => Err(format!("Credential store: {error}")),
        }
    }

    pub fn delete(&self, key: &str) -> Result<(), String> {
        match self.entry(key)?.delete_credential() {
            Ok(()) | Err(keyring_core::Error::NoEntry) => Ok(()),
            Err(error) => Err(format!("Credential store: {error}")),
        }
    }
}

/// Serializes read-modify-write of the list file within this process.
static LIST: Mutex<()> = Mutex::new(());

fn path(dir: &Path) -> PathBuf {
    dir.join(FILE)
}

pub fn list(dir: &Path) -> Result<Vec<Saved>, String> {
    let _guard = LIST.lock().map_err(|_| "Saved connections are unavailable")?;
    read(dir)
}

fn read(dir: &Path) -> Result<Vec<Saved>, String> {
    match std::fs::read_to_string(path(dir)) {
        Ok(text) => serde_json::from_str(&text).map_err(|_| "The saved connection list is unreadable".into()),
        Err(error) if error.kind() == std::io::ErrorKind::NotFound => Ok(Vec::new()),
        Err(_) => Err("The saved connection list is unreadable".into()),
    }
}

fn write(dir: &Path, list: &[Saved]) -> Result<(), String> {
    std::fs::create_dir_all(dir).map_err(|_| "The data folder is not writable")?;
    let text = serde_json::to_string_pretty(list).map_err(|_| "Could not encode the saved connections")?;
    let temporary = dir.join(format!("{FILE}.tmp"));
    std::fs::write(&temporary, text).map_err(|_| "Could not save the connection list")?;
    std::fs::rename(&temporary, path(dir)).map_err(|_| "Could not save the connection list".into())
}

/// Adds or replaces the entry with the same key.
pub fn upsert(dir: &Path, saved: Saved) -> Result<(), String> {
    let _guard = LIST.lock().map_err(|_| "Saved connections are unavailable")?;
    let mut list = read(dir)?;
    list.retain(|item| item.key != saved.key);
    list.push(saved);
    write(dir, &list)
}

/// Marks an entry whose secret could not be deleted, so it is not restored next time.
pub fn mark_forget_failed(dir: &Path, key: &str) -> Result<(), String> {
    let _guard = LIST.lock().map_err(|_| "Saved connections are unavailable")?;
    let mut list = read(dir)?;
    for item in list.iter_mut().filter(|item| item.key == key) {
        item.forget_failed = true;
    }
    write(dir, &list)
}

pub fn remove(dir: &Path, key: &str) -> Result<(), String> {
    let _guard = LIST.lock().map_err(|_| "Saved connections are unavailable")?;
    let mut list = read(dir)?;
    list.retain(|item| item.key != key);
    write(dir, &list)
}

#[cfg(test)]
mod tests {
    use super::*;

    fn plan() -> LoginPlan {
        LoginPlan { jmap: Some("https://mail.test/jmap/session".into()), ..LoginPlan::default() }
    }

    #[test]
    fn the_list_round_trips_without_secrets_and_replaces_by_key() {
        let dir = std::env::temp_dir().join(format!("axismundi-saved-{}", std::process::id()));
        let _ = std::fs::remove_dir_all(&dir);
        assert!(list(&dir).unwrap().is_empty(), "a missing file is an empty list");
        let key = key_for(Some(&dir), "https://mail.test", "alice", PASSWORD);
        assert!(key.starts_with("conn-"));
        let alice = Saved { key: key.clone(), username: "alice".into(), server: "https://mail.test".into(), auth: PASSWORD.into(), plan: plan(), forget_failed: false };
        upsert(&dir, alice.clone()).unwrap();
        upsert(&dir, alice.clone()).unwrap();
        assert_eq!(list(&dir).unwrap(), vec![alice.clone()]);
        assert_eq!(key_for(Some(&dir), "https://mail.test", "alice", PASSWORD), key, "the same login reuses its entry");
        assert_ne!(key_for(Some(&dir), "https://mail.test", "alice", "oauth"), key, "another auth method is another credential");
        assert_ne!(key_for(Some(&dir), "https://mail.test", "team", PASSWORD), key);
        // The file carries exactly these fields: no slot a secret could land in.
        let text = std::fs::read_to_string(path(&dir)).unwrap();
        let entry: serde_json::Value = serde_json::from_str::<serde_json::Value>(&text).unwrap()[0].clone();
        let mut fields: Vec<&str> = entry.as_object().unwrap().keys().map(String::as_str).collect();
        fields.sort();
        assert_eq!(fields, ["auth", "key", "plan", "server", "username"]);
        remove(&dir, &alice.key).unwrap();
        assert!(list(&dir).unwrap().is_empty());
        std::fs::remove_dir_all(&dir).unwrap();
    }

    /// Touches the real Windows Credential Manager under a throwaway key.
    #[test]
    #[cfg(windows)]
    #[ignore = "writes to the Windows Credential Manager"]
    fn windows_credential_manager_round_trip() {
        let vault = Vault::platform();
        assert!(vault.available());
        let key = format!("axismundi-test-{}", std::process::id());
        assert_eq!(vault.get(&key).unwrap(), None);
        vault.put(&key, "test-only-secret").unwrap();
        assert_eq!(vault.get(&key).unwrap().as_deref(), Some("test-only-secret"));
        vault.delete(&key).unwrap();
        assert_eq!(vault.get(&key).unwrap(), None);
        vault.delete(&key).unwrap();
    }
}
