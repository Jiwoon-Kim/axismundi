//! Mailbox writes never retry an ambiguous request.
use serde_json::{Value, json};

use super::{Client, Error, Session, arguments, call, choose_account, uncertain};

#[derive(Clone, Copy)]
pub enum Change<'a> {
    Create { parent: Option<&'a str>, name: &'a str, role: Option<&'a str> },
    Rename { id: &'a str, name: &'a str },
    Destroy { id: &'a str },
}

#[derive(Debug)]
pub enum Outcome {
    Confirmed(String),
    Refused { kind: String, properties: Vec<String> },
    Unchanged,
    Unknown,
}

pub fn write(client: &Client, session: &Session, account: &str, change: Change<'_>) -> Result<Outcome, Error> {
    choose_account(session, Some(account))?;
    let args = match change {
        Change::Create { parent, name, role } =>
            json!({"accountId":account,"create":{"new":{"parentId":parent,"name":name,"role":role}}}),
        Change::Rename { id, name } => json!({"accountId":account,"update":{id:{"name":name}}}),
        Change::Destroy { id } => json!({"accountId":account,"destroy":[id],"onDestroyRemoveEmails":false}),
    };
    super::limit(session, "maxCallsInRequest")?;
    if super::request_bytes("Mailbox/set", &args)? > super::limit(session, "maxSizeRequest")? {
        return Err(Error::Shape("Mailbox write exceeds maxSizeRequest".into()));
    }
    match call(client, session, "Mailbox/set", args) {
        Ok(batch) => {
            if let [response] = batch.method_responses.as_slice()
                && response.call_id == "mail" && response.is_error()
            {
                return Ok(Outcome::Refused {
                    kind: response.error_type().unwrap_or("unknown").into(), properties: vec![],
                });
            }
            if let Ok(response) = batch.response("mail", "Mailbox/set")
                && let Ok(result) = response.set_outcome()
                && result.account_id == account
            {
                let refused = match change {
                    Change::Create { .. } => result.not_created.get("new"),
                    Change::Rename { id, .. } => result.not_updated.get(id),
                    Change::Destroy { id } => result.not_destroyed.get(id),
                };
                if let Some(error) = refused {
                    return Ok(Outcome::Refused {
                        kind: error.kind.clone(), properties: error.properties.clone().unwrap_or_default(),
                    });
                }
                let confirmed = match change {
                    Change::Create { .. } => result.created.get("new").and_then(|v| v["id"].as_str()).filter(|id| !id.is_empty()),
                    Change::Rename { id, .. } => result.updated.contains_key(id).then_some(id),
                    Change::Destroy { id } => result.destroyed.iter().any(|v| v == id).then_some(id),
                };
                if let Some(id) = confirmed { return Ok(Outcome::Confirmed(id.into())); }
            }
        }
        Err(error) if !uncertain(&error) => return Err(error),
        Err(_) => {}
    }
    let id = match change {
        Change::Create { .. } => return Ok(Outcome::Unknown),
        Change::Rename { id, .. } | Change::Destroy { id } => id,
    };
    let read = call(client, session, "Mailbox/get", json!({"accountId":account,"ids":[id],"properties":["id","name"]}))
        .and_then(|batch| arguments(&batch, "Mailbox/get", account));
    let Ok(read) = read else { return Ok(Outcome::Unknown) };
    Ok(reconcile(change, id, &read))
}

fn reconcile(change: Change<'_>, id: &str, read: &Value) -> Outcome {
    let row = read["list"].as_array().and_then(|rows| rows.iter().find(|row| row["id"].as_str() == Some(id)));
    match change {
        Change::Rename { name, .. } => match row.and_then(|row| row["name"].as_str()) {
            Some(current) if current == name => Outcome::Confirmed(id.into()),
            Some(_) => Outcome::Unchanged,
            None => Outcome::Unknown,
        },
        Change::Destroy { .. } if row.is_some() => Outcome::Unchanged,
        Change::Destroy { .. } if read["notFound"].as_array().is_some_and(|ids| ids.iter().any(|v| v == id)) =>
            Outcome::Confirmed(id.into()),
        _ => Outcome::Unknown,
    }
}
