use std::time::{SystemTime, UNIX_EPOCH};

use axismundi_jmap_core::{Client, Invocation, Session, SetOutcome};
use serde_json::{Value, json};
use url::{Host, Origin, Url};

use crate::{CORE, TODO, explain};

pub(crate) fn loopback_origin(value: &str) -> Result<Origin, String> {
    let url = Url::parse(value).map_err(|err| format!("invalid probe URL: {err}"))?;
    let loopback = match url.host() {
        Some(Host::Domain("localhost")) => true,
        Some(Host::Ipv4(ip)) => ip.is_loopback(),
        Some(Host::Ipv6(ip)) => ip.is_loopback(),
        _ => false,
    };
    if loopback
        && matches!(url.scheme(), "http" | "https")
        && url.username().is_empty()
        && url.password().is_none()
    {
        Ok(url.origin())
    } else {
        Err("Todo write probe only accepts HTTP(S) loopback development URLs without embedded credentials".into())
    }
}

struct Probe<'a> {
    client: &'a Client,
    session: &'a Session,
    account: &'a str,
    marker: String,
    list: Option<String>,
    todo: Option<String>,
}

pub(crate) fn run(client: &Client, session: &Session, account: &str) -> Result<(), String> {
    if session.accounts[account].is_read_only {
        return Err("Todo write probe needs a writable Account".into());
    }
    let nonce = SystemTime::now()
        .duration_since(UNIX_EPOCH)
        .map_err(|err| err.to_string())?
        .as_nanos();
    let mut probe = Probe {
        client,
        session,
        account,
        marker: format!("axj-probe-{}-{nonce}", std::process::id()),
        list: None,
        todo: None,
    };
    println!("Todo/set probe marker={} account={account}", probe.marker);
    let result = probe.exercise();
    let cleanup = probe.cleanup();
    match (result, cleanup) {
        (Ok(()), Ok(())) => {
            println!("PASS Todo/set: CRUD, stateMismatch, partial success, and deltas");
            Ok(())
        }
        (Err(error), Ok(())) | (Ok(()), Err(error)) => Err(error),
        (Err(error), Err(cleanup)) => Err(format!("{error}; cleanup: {cleanup}")),
    }
}

impl Probe<'_> {
    fn call(&self, method: &str, mut args: Value) -> Result<Invocation, String> {
        args["accountId"] = json!(self.account);
        let mut replies = self
            .client
            .request(self.session, &[CORE, TODO], vec![(method, args, "probe")])
            .map_err(explain)?
            .method_responses;
        if replies.len() != 1
            || replies[0].call_id != "probe"
            || (replies[0].name != method && !replies[0].is_error())
        {
            return Err(format!("{method}: unexpected method response"));
        }
        Ok(replies.remove(0))
    }

    fn success(reply: Invocation) -> Result<Value, String> {
        if reply.is_error() {
            return Err(format!("method error: {}", reply.arguments));
        }
        Ok(reply.arguments)
    }

    fn set(&self, method: &str, args: Value) -> Result<SetOutcome, String> {
        let reply = self.call(method, args)?;
        if reply.is_error() {
            return Err(format!("{method} method error: {}", reply.arguments));
        }
        let outcome = reply.set_outcome().map_err(explain)?;
        check(
            outcome.account_id == self.account,
            "Set returned the wrong account",
        )?;
        Ok(outcome)
    }

    fn delta(&self, since: &str, next: &str, field: &str, id: &str) -> Result<(), String> {
        let args = Self::success(self.call("Todo/changes", json!({"sinceState": since}))?)?;
        check(
            args["oldState"] == since
                && args["newState"] == next
                && args["hasMoreChanges"] == false
                && args[field]
                    .as_array()
                    .is_some_and(|ids| ids.contains(&json!(id))),
            &format!("Todo/changes did not report {field} for {id}"),
        )
    }

    fn exercise(&mut self) -> Result<(), String> {
        let list = self.set(
            "TodoList/set",
            json!({"create": {"fixture": {"name": self.marker}}}),
        )?;
        self.list = created_id(&list);
        check(
            list.fully_applied(),
            &format!("list create failed: {list:?}"),
        )?;
        let list_id = self
            .list
            .clone()
            .ok_or("list create returned no fixture id")?;
        let before = Self::success(self.call("Todo/get", json!({"ids": []}))?)?;
        let before = state(&before)?;
        let created = self.set(
            "Todo/set",
            json!({"ifInState": before,
            "create": {"fixture": {"title": self.marker, "todoListId": list_id}}}),
        )?;
        self.todo = created_id(&created);
        check(
            created.fully_applied(),
            &format!("Todo create failed: {created:?}"),
        )?;
        let id = self
            .todo
            .clone()
            .ok_or("Todo create returned no fixture id")?;
        self.check_todo(&id, &list_id, &self.marker, &created.new_state)?;
        self.delta(before, &created.new_state, "created", &id)?;

        let stale = self.call(
            "Todo/set",
            json!({"ifInState": before,
            "update": {(id.clone()): {"title": "stale write"}}}),
        )?;
        check(
            stale.is_error() && stale.error_type() == Some("stateMismatch"),
            "stale Set was not a method-level stateMismatch",
        )?;

        let rejected = self.set(
            "TodoList/set",
            json!({
                "update": {(list_id.clone()): {"myRights": {"mayRead": false}}}
            }),
        )?;
        check(
            !rejected.fully_applied()
                && rejected.not_updated.get(&list_id).is_some_and(|error| {
                    error.kind == "invalidProperties"
                        && error
                            .properties
                            .as_deref()
                            .is_some_and(|keys| keys.iter().any(|key| key == "myRights"))
                }),
            &format!("myRights write did not yield an item error: {rejected:?}"),
        )?;

        // The id was just absent in this account, and is never sent to destroy.
        let missing = format!("missing-{}", self.marker);
        let absent = Self::success(self.call("Todo/get", json!({"ids": [missing]}))?)?;
        check(
            absent["notFound"] == json!([missing]),
            "negative fixture id is not absent",
        )?;
        let title = format!("{} updated", self.marker);
        let updated = self.set(
            "Todo/set",
            json!({"ifInState": created.new_state,
            "update": {(id.clone()): {"title": title}, (missing.clone()): {"title": "missing"}}}),
        )?;
        check(
            !updated.fully_applied()
                && updated.updated.contains_key(&id)
                && updated
                    .not_updated
                    .get(&missing)
                    .is_some_and(|error| error.kind == "notFound"),
            &format!("expected one update and one notFound: {updated:?}"),
        )?;
        self.check_todo(&id, &list_id, &title, &updated.new_state)?;
        self.delta(&created.new_state, &updated.new_state, "updated", &id)?;
        let destroyed = self.destroy("Todo/set", &id, Some(&updated.new_state))?;
        self.todo = None;
        self.delta(&updated.new_state, &destroyed.new_state, "destroyed", &id)
    }

    fn check_todo(
        &self,
        id: &str,
        list: &str,
        title: &str,
        expected_state: &str,
    ) -> Result<(), String> {
        let got = Self::success(self.call("Todo/get", json!({"ids": [id]}))?)?;
        let rows = got["list"].as_array().ok_or("Todo/get returned no list")?;
        check(
            got["state"] == expected_state
                && rows.len() == 1
                && rows[0]["id"] == id
                && rows[0]["todoListId"] == list
                && rows[0]["title"] == title,
            "Todo/get did not confirm the fixture and its state",
        )
    }

    fn destroy(&self, method: &str, id: &str, guard: Option<&str>) -> Result<SetOutcome, String> {
        let mut args = json!({"destroy": [id]});
        if let Some(state) = guard {
            args["ifInState"] = json!(state);
        }
        let outcome = self.set(method, args)?;
        check(
            outcome.fully_applied() && outcome.destroyed == [id],
            &format!("{method} did not confirm cleanup of {id}: {outcome:?}"),
        )?;
        Ok(outcome)
    }

    fn cleanup(&mut self) -> Result<(), String> {
        if let Some(id) = self.todo.as_deref() {
            if let Err(error) = self.destroy("Todo/set", id, None) {
                return Err(format!(
                    "{error}; unconfirmed Todo id={id}, list id={:?}",
                    self.list
                ));
            }
            self.todo = None;
        }
        if let Some(id) = self.list.as_deref() {
            self.destroy("TodoList/set", id, None)
                .map_err(|error| format!("{error}; unconfirmed list id={id}"))?;
            self.list = None;
        }
        Ok(())
    }
}

fn created_id(outcome: &SetOutcome) -> Option<String> {
    outcome
        .created
        .get("fixture")?
        .get("id")?
        .as_str()
        .filter(|id| !id.is_empty() && !id.starts_with('#'))
        .map(str::to_owned)
}

fn state(got: &Value) -> Result<&str, String> {
    got["state"]
        .as_str()
        .ok_or_else(|| "Todo/get returned no state".into())
}

fn check(condition: bool, message: &str) -> Result<(), String> {
    if condition {
        Ok(())
    } else {
        Err(message.into())
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn writes_are_limited_to_explicit_loopback_origins() {
        let origin = loopback_origin("http://localhost:8884/session").unwrap();
        assert_eq!(
            origin,
            loopback_origin("http://localhost:8884/api").unwrap()
        );
        assert_ne!(
            origin,
            loopback_origin("http://localhost:8885/api").unwrap()
        );
        assert!(loopback_origin("http://127.0.0.1/api").is_ok());
        assert!(loopback_origin("http://[::1]/api").is_ok());
        assert!(loopback_origin("https://staging.example.test/session").is_err());
        assert!(loopback_origin("http://localhost.example.test/session").is_err());
        assert!(loopback_origin("http://user:secret@localhost/api").is_err());
    }
}
