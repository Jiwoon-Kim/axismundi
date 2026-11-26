//! Content-addressed local bytes. A digest alone never authorizes reuse.
use std::{
    fs,
    path::{Path, PathBuf},
};

use base64::{Engine, engine::general_purpose::STANDARD};
use ring::digest::{SHA256, digest};
use rusqlite::{Connection, OptionalExtension, params};

type Result<T> = std::result::Result<T, String>;
fn ledger_error(error: impl std::fmt::Display) -> String {
    format!("Attachment cache: {error}")
}

fn valid_hash(hash: &str) -> bool {
    hash.len() == 64 && hash.bytes().all(|byte| byte.is_ascii_hexdigit())
}

pub fn hex_digest(bytes: &[u8]) -> String {
    digest(&SHA256, bytes)
        .as_ref()
        .iter()
        .map(|byte| format!("{byte:02x}"))
        .collect()
}

pub struct Reference<'a> {
    pub login: &'a str,
    pub account: &'a str,
    pub email: &'a str,
    pub blob: &'a str,
}

/// Only an authenticated lookup may produce Verified. Unsupported permits an authenticated
/// byte transfer, but never grants access to a cached copy.
pub enum Access {
    Verified(String),
    Unsupported,
    Denied,
}

pub struct Ledger {
    db: Connection,
    dir: PathBuf,
    limit: u64,
}

impl Ledger {
    pub fn open(root: &Path, origin: &str, limit: u64) -> Result<Self> {
        let dir = root.join(hex_digest(origin.as_bytes()));
        fs::create_dir_all(&dir).map_err(ledger_error)?;
        let db = Connection::open(dir.join("index.sqlite")).map_err(ledger_error)?;
        db.busy_timeout(std::time::Duration::from_secs(5))
            .map_err(ledger_error)?;
        db.execute_batch("
            PRAGMA foreign_keys=ON;
            CREATE TABLE IF NOT EXISTS bytes(hash TEXT PRIMARY KEY, size INTEGER NOT NULL CHECK(size >= 0), used INTEGER NOT NULL);
            CREATE TABLE IF NOT EXISTS refs(login TEXT, account TEXT, email TEXT, blob TEXT,
                hash TEXT NOT NULL REFERENCES bytes(hash) ON DELETE CASCADE,
                PRIMARY KEY(login, account, email, blob));
        ").map_err(ledger_error)?;
        Ok(Self { db, dir, limit })
    }

    pub fn get(
        &mut self,
        key: &Reference<'_>,
        cap: u64,
        recheck: bool,
        check: impl FnOnce() -> Result<Access>,
        download: impl FnOnce() -> Result<Vec<u8>>,
    ) -> Result<Vec<u8>> {
        let args = params![key.login, key.account, key.email, key.blob];
        if !recheck {
            let existing: Option<String> = self
                .db
                .query_row(
                    "SELECT hash FROM refs WHERE login=? AND account=? AND email=? AND blob=?",
                    args,
                    |row| row.get(0),
                )
                .optional()
                .map_err(ledger_error)?;
            if let Some(bytes) = self.read(existing.as_deref(), cap)? {
                return Ok(bytes);
            }
        }
        let expected = match check()? {
            Access::Verified(value) => {
                let raw = STANDARD
                    .decode(value)
                    .map_err(|_| "Invalid attachment digest.")?;
                if raw.len() != 32 {
                    return Err("Invalid attachment digest.".into());
                }
                Some(
                    raw.iter()
                        .map(|byte| format!("{byte:02x}"))
                        .collect::<String>(),
                )
            }
            Access::Unsupported => None,
            Access::Denied => {
                self.db
                    .execute(
                        "DELETE FROM refs WHERE login=? AND account=? AND email=? AND blob=?",
                        args,
                    )
                    .map_err(ledger_error)?;
                return Err("Attachment is no longer accessible.".into());
            }
        };
        let cached = self.read(expected.as_deref(), cap)?;
        let hit = cached.is_some();
        let bytes = match cached {
            Some(bytes) => bytes,
            None => download()?,
        };
        if bytes.len() as u64 > cap {
            return Err("Attachment exceeds the size limit.".into());
        }
        let hash = hex_digest(&bytes);
        if expected.as_ref().is_some_and(|value| *value != hash) {
            return Err("Attachment digest did not match.".into());
        }
        let tx = self
            .db
            .transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)
            .map_err(ledger_error)?;
        let path = self.dir.join(&hash);
        if !hit || !path.exists() {
            let temp = self.dir.join(format!("{hash}.incoming"));
            fs::write(&temp, &bytes).map_err(ledger_error)?;
            fs::rename(&temp, path).map_err(ledger_error)?;
        }
        tx.execute(
            "INSERT INTO bytes VALUES(?,?,(SELECT coalesce(max(used),0)+1 FROM bytes))
            ON CONFLICT(hash) DO UPDATE SET used=excluded.used,size=excluded.size",
            params![hash, i64::try_from(bytes.len()).map_err(ledger_error)?],
        )
        .map_err(ledger_error)?;
        tx.execute(
            "INSERT OR REPLACE INTO refs VALUES(?,?,?,?,?)",
            params![key.login, key.account, key.email, key.blob, hash],
        )
        .map_err(ledger_error)?;
        let mut total: i64 = tx
            .query_row("SELECT coalesce(sum(size),0) FROM bytes", [], |row| {
                row.get(0)
            })
            .map_err(ledger_error)?;
        while total.unsigned_abs() > self.limit {
            let (victim, size): (String, i64) = tx
                .query_row(
                    "SELECT hash,size FROM bytes ORDER BY used, hash=? LIMIT 1",
                    [&hash],
                    |row| Ok((row.get(0)?, row.get(1)?)),
                )
                .map_err(ledger_error)?;
            if !valid_hash(&victim) {
                return Err("Attachment cache index is invalid.".into());
            }
            match fs::remove_file(self.dir.join(&victim)) {
                Ok(()) => (),
                Err(error) if error.kind() == std::io::ErrorKind::NotFound => (),
                Err(error) => return Err(ledger_error(error)),
            }
            tx.execute("DELETE FROM bytes WHERE hash=?", [&victim])
                .map_err(ledger_error)?;
            total -= size;
        }
        tx.commit().map_err(ledger_error)?;
        Ok(bytes)
    }

    fn read(&self, hash: Option<&str>, cap: u64) -> Result<Option<Vec<u8>>> {
        let Some(hash) = hash else { return Ok(None) };
        if !valid_hash(hash) {
            return Err("Attachment cache index is invalid.".into());
        }
        let path = self.dir.join(hash);
        let file = match fs::File::open(path) {
            Ok(file) => file,
            Err(error) if error.kind() == std::io::ErrorKind::NotFound => return Ok(None),
            Err(error) => return Err(ledger_error(error)),
        };
        use std::io::Read;
        let mut bytes = Vec::new();
        file.take(cap.saturating_add(1))
            .read_to_end(&mut bytes)
            .map_err(ledger_error)?;
        if bytes.len() as u64 > cap {
            return Err("Attachment exceeds the size limit.".into());
        }
        if hex_digest(&bytes) != hash {
            return Ok(None);
        }
        self.db
            .execute(
                "UPDATE bytes SET used=(SELECT coalesce(max(used),0)+1 FROM bytes) WHERE hash=?",
                [hash],
            )
            .map_err(ledger_error)?;
        Ok(Some(bytes))
    }

    pub fn retain(
        &mut self,
        login: &str,
        mut keep: impl FnMut(&str, &str, &str) -> bool,
    ) -> Result<()> {
        let tx = self
            .db
            .transaction_with_behavior(rusqlite::TransactionBehavior::Immediate)
            .map_err(ledger_error)?;
        let rows = {
            let mut query = tx
                .prepare("SELECT account,email,blob FROM refs WHERE login=?")
                .map_err(ledger_error)?;
            query
                .query_map([login], |r| {
                    Ok((
                        r.get::<_, String>(0)?,
                        r.get::<_, String>(1)?,
                        r.get::<_, String>(2)?,
                    ))
                })
                .map_err(ledger_error)?
                .collect::<std::result::Result<Vec<_>, _>>()
                .map_err(ledger_error)?
        };
        for (account, email, blob) in rows {
            if !keep(&account, &email, &blob) {
                tx.execute(
                    "DELETE FROM refs WHERE login=? AND account=? AND email=? AND blob=?",
                    params![login, account, email, blob],
                )
                .map_err(ledger_error)?;
            }
        }
        tx.commit().map_err(ledger_error)
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn cache_hit_needs_a_login_reference_and_export_rechecks_access() {
        let stamp = std::time::SystemTime::now()
            .duration_since(std::time::UNIX_EPOCH)
            .unwrap()
            .as_nanos();
        let root =
            std::env::temp_dir().join(format!("axismundi-ledger-{}-{stamp}", std::process::id()));
        let mut ledger = Ledger::open(&root, "https://test.invalid", 100).unwrap();
        let alice = Reference {
            login: "alice",
            account: "c",
            email: "e",
            blob: "b",
        };
        let bob = Reference {
            login: "bob",
            ..alice
        };
        let bytes = b"attachment";
        let sha = || Access::Verified(STANDARD.encode(digest(&SHA256, bytes).as_ref()));
        assert_eq!(
            ledger
                .get(&alice, 100, false, || Ok(sha()), || Ok(bytes.to_vec()))
                .unwrap(),
            bytes
        );
        assert_eq!(
            ledger
                .get(
                    &alice,
                    100,
                    false,
                    || panic!("cache hit"),
                    || panic!("cache hit")
                )
                .unwrap(),
            bytes
        );
        assert!(
            ledger
                .get(&bob, 100, false, || Ok(Access::Denied), || panic!("denied"))
                .is_err()
        );
        assert_eq!(
            ledger
                .get(&bob, 100, false, || Ok(sha()), || panic!("already cached"))
                .unwrap(),
            bytes
        );
        assert!(
            ledger
                .get(
                    &alice,
                    100,
                    true,
                    || Ok(Access::Denied),
                    || panic!("denied")
                )
                .is_err()
        );
        ledger.retain("bob", |_, _, _| false).unwrap();
        assert!(
            ledger
                .get(&bob, 100, false, || Ok(Access::Denied), || panic!("denied"))
                .is_err()
        );
        let other = Reference {
            blob: "different",
            ..alice
        };
        let mismatch = || Access::Verified(STANDARD.encode(digest(&SHA256, b"expected").as_ref()));
        assert!(
            ledger
                .get(&other, 100, false, || Ok(mismatch()), || Ok(bytes.to_vec()))
                .is_err()
        );
        drop(ledger);
        let mut ledger = Ledger::open(&root, "https://test.invalid", bytes.len() as u64).unwrap();
        ledger
            .get(
                &other,
                100,
                false,
                || Ok(Access::Unsupported),
                || Ok(b"second-one".to_vec()),
            )
            .unwrap();
        assert!(!ledger.dir.join(hex_digest(bytes)).exists());
        drop(ledger);
        fs::remove_dir_all(root).unwrap();
    }
}
