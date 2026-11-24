//! Method calls and their responses (RFC 8620 §3), and the /set outcome every write reads (§5.3).
use std::collections::BTreeMap;

use serde::Deserialize;
use serde_json::{Value, json};

use super::{CORE_CAPABILITY, Session};
use crate::{Error, http::Client};

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
    /// Match a response by both request identity and expected method.
    pub fn response(&self, call_id: &str, name: &str) -> Result<&Invocation, Error> {
        let mut matches = self.method_responses.iter().filter(|i| i.call_id == call_id && (i.name == name || i.is_error()));
        let result = matches.next().ok_or_else(|| Error::Shape(format!("missing response for {call_id}: {name}")))?;
        if matches.next().is_some() {
            return Err(Error::Shape(format!("ambiguous response for {call_id}: {name}")));
        }
        if result.is_error() {
            return Err(Error::Shape(format!("{call_id}: {}", result.arguments)));
        }
        Ok(result)
    }

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
}
