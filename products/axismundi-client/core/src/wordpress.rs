//! WordPress REST: the signed-in user (`users/me`) that a WordPress connection is checked against.
use serde_json::Value;

use crate::{Error, http::Client};

impl Client {
    /// Read and validate the discovered WordPress users/me response.
    pub fn wordpress_user_document(&self, user_url: &str) -> Result<Value, Error> {
        let (body, _) = self.get(user_url)?;
        let value: Value = serde_json::from_str(&body)
            .map_err(|error| Error::Shape(format!("WordPress user: {error}")))?;
        if value.get("name").and_then(Value::as_str).is_none() {
            return Err(Error::Shape("WordPress user has no name".into()));
        }
        Ok(value)
    }

    pub fn wordpress_user(&self, user_url: &str) -> Result<String, Error> {
        self.wordpress_user_document(user_url)
            .map(|value| value["name"].as_str().unwrap_or_default().to_owned())
    }
}
