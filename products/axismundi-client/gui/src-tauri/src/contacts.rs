use std::{path::Path, sync::Mutex};

use axismundi_client_core::jmap::{Session, contacts as core};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};

use crate::connection::{Active, Connection};

pub(crate) struct Contacts {
    session: Session,
    store: Mutex<core::Store>,
}

impl Contacts {
    pub(crate) fn open(dir: &Path, session: Session, username: &str) -> Result<Self, String> {
        let profile = json!({"apiUrl": session.api_url, "username": username}).to_string();
        let dir = dir.join("contacts");
        std::fs::create_dir_all(&dir).map_err(|_| "Contacts cache folder is not writable.")?;
        let store = core::Store::open(crate::mail::cache_file(&dir, &profile), &profile)
            .map_err(|error| error.to_string())?;
        Ok(Self {
            session,
            store: Mutex::new(store),
        })
    }
}

const BOOK: &str = "AddressBook";
const CARD: &str = "ContactCard";

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn contacts_fixture_matches_ipc_serialization() {
        let source = json!({
            "id":"test-card","version":"1.0","name":{"full":"Test Contact"},
            "emails":{"e1":{"address":"test@example.org"}},
            "media":{"m1":{"kind":"photo","uri":"https://example.org/avatar.png"}},
            "localizations":{"en":{"name/full":"Example"}}
        });
        let actual = json!({
            "books":[Book{id:"test-book".into(), name:Some("Test book".into()), is_default:true, is_subscribed:Some(true)}],
            "rows":[row(source.clone()).unwrap()],
            "detail":Document{card:source},
            "created":Created{id:"test-card".into()},
            "synced":Synced{address_book_state:Some("books-state".into()),contact_card_state:Some("cards-state".into())}
        });
        let path =
            Path::new(env!("CARGO_MANIFEST_DIR")).join("../src/app/contacts/contacts.fixture.json");
        if std::env::var_os("AXISMUNDI_UPDATE_FIXTURES").is_some() {
            std::fs::write(&path, serde_json::to_string_pretty(&actual).unwrap() + "\n").unwrap();
        }
        let expected: Value =
            serde_json::from_str(&std::fs::read_to_string(path).unwrap()).unwrap();
        assert_eq!(actual, expected);
    }
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct Book {
    id: String,
    name: Option<String>,
    is_default: bool,
    is_subscribed: Option<bool>,
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct Row {
    id: String,
    name: Option<String>,
    email: Option<String>,
    avatar_uri: Option<String>,
}

#[derive(Serialize)]
pub(crate) struct Document {
    card: Value,
}

#[derive(Deserialize)]
pub(crate) struct Edit {
    path: Vec<String>,
    value: Value,
}

#[derive(Serialize)]
pub(crate) struct Created {
    id: String,
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct Synced {
    address_book_state: Option<String>,
    contact_card_state: Option<String>,
}

fn string(value: &Value) -> Option<String> {
    value.as_str().filter(|s| !s.is_empty()).map(str::to_owned)
}

fn preferred(items: &Value, field: &str, photos: bool) -> Option<String> {
    let items = items.as_object()?;
    items
        .values()
        .filter_map(|item| {
            if photos && item["kind"] != "photo" {
                return None;
            }
            let value = string(&item[field])?;
            if photos {
                let url = url::Url::parse(&value).ok()?;
                if url.scheme() != "https" || !url.username().is_empty() || url.password().is_some() {
                    return None;
                }
            }
            Some((item["pref"].as_u64().unwrap_or(u64::MAX), value))
        })
        .min_by_key(|(pref, _)| *pref)
        .map(|(_, value)| value)
}

fn row(card: Value) -> Option<Row> {
    Some(Row {
        id: string(&card["id"])?,
        name: string(&card["name"]["full"]).or_else(|| {
            let parts = card["name"]["components"].as_array()?;
            let name = parts
                .iter()
                .filter_map(|p| p["value"].as_str())
                .collect::<Vec<_>>()
                .join(card["name"]["defaultSeparator"].as_str().unwrap_or(" "));
            (!name.is_empty()).then_some(name)
        }),
        email: preferred(&card["emails"], "address", false),
        avatar_uri: preferred(&card["media"], "uri", true),
    })
}

async fn run<T: Send + 'static>(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    action: impl FnOnce(&Active, &Session, &mut core::Store, &str) -> core::Result<T> + Send + 'static,
) -> Result<T, String> {
    let active = state.current(connection_id)?;
    let result = tauri::async_runtime::spawn_blocking(move || {
        let contacts = active
            .contacts
            .as_ref()
            .ok_or("Contacts is not available on this connection.")?;
        let account = core::choose_account(&contacts.session, account_id.as_deref())
            .map_err(|err| err.to_string())?;
        let mut store = contacts
            .store
            .lock()
            .map_err(|_| "Contacts cache is unavailable.")?;
        action(&active, &contacts.session, &mut store, &account).map_err(|err| err.to_string())
    })
    .await
    .map_err(|_| "Contacts command did not complete.")?;
    state.current(connection_id)?;
    result
}

#[tauri::command]
pub(crate) async fn contacts_books(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
) -> Result<Vec<Book>, String> {
    run(state, connection_id, account_id, |_, _, store, account| {
        Ok(store
            .list(account, BOOK)?
            .into_iter()
            .filter_map(|book| {
                Some(Book {
                    id: string(&book["id"])?,
                    name: string(&book["name"]),
                    is_default: book["isDefault"] == true,
                    is_subscribed: book["isSubscribed"].as_bool(),
                })
            })
            .collect())
    })
    .await
}

#[tauri::command]
pub(crate) async fn contacts_list(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    book_id: Option<String>,
) -> Result<Vec<Row>, String> {
    run(
        state,
        connection_id,
        account_id,
        move |_, _, store, account| {
            Ok(store
                .list(account, CARD)?
                .into_iter()
                .filter(|card| {
                    book_id
                        .as_ref()
                        .is_none_or(|book| card["addressBookIds"][book] == true)
                })
                .filter_map(row)
                .collect())
        },
    )
    .await
}

#[tauri::command]
pub(crate) async fn contacts_card(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    card_id: String,
) -> Result<Document, String> {
    run(
        state,
        connection_id,
        account_id,
        move |_, _, store, account| {
            let card = store
                .list(account, CARD)?
                .into_iter()
                .find(|card| card["id"] == card_id)
                .ok_or("Contact is not cached.")?;
            Ok(Document { card })
        },
    )
    .await
}

#[tauri::command]
pub(crate) async fn contacts_sync(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
) -> Result<Synced, String> {
    run(
        state,
        connection_id,
        account_id,
        |active, session, store, account| {
            for kind in [BOOK, CARD] {
                core::sync_type(&active.client, session, store, account, kind)?;
            }
            Ok(Synced {
                address_book_state: store.state(account, BOOK)?,
                contact_card_state: store.state(account, CARD)?,
            })
        },
    )
    .await
}

#[tauri::command]
pub(crate) async fn contacts_search(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    text: String,
) -> Result<Vec<Row>, String> {
    run(
        state,
        connection_id,
        account_id,
        move |active, session, store, account| {
            let ids = core::search(&active.client, session, account, &text)?;
            core::sync_type(&active.client, session, store, account, CARD)?;
            let mut rows: std::collections::HashMap<_, _> = store
                .list(account, CARD)?
                .into_iter()
                .filter_map(row)
                .map(|row| (row.id.clone(), row))
                .collect();
            Ok(ids.iter().filter_map(|id| rows.remove(id)).collect())
        },
    )
    .await
}

#[tauri::command]
pub(crate) async fn contacts_create(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    book_id: Option<String>,
    card: Value,
) -> Result<Created, String> {
    run(
        state,
        connection_id,
        account_id,
        move |active, session, store, account| {
            let book_id = match book_id {
                Some(id) => id,
                None => {
                    core::sync_type(&active.client, session, store, account, BOOK)?;
                    store
                        .list(account, BOOK)?
                        .iter()
                        .find(|book| book["isDefault"] == true)
                        .and_then(|book| string(&book["id"]))
                        .ok_or("Choose an address book.")?
                }
            };
            let id = core::create(&active.client, session, account, &book_id, card)?;
            Ok(Created { id })
        },
    )
    .await
}

#[tauri::command]
pub(crate) async fn contacts_update(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    card_id: String,
    edits: Vec<Edit>,
) -> Result<Document, String> {
    run(
        state,
        connection_id,
        account_id,
        move |active, session, store, account| {
            if session.accounts[account].is_read_only {
                return Err("account is read-only".into());
            }
            let patch = core::edit_patch(
                edits
                    .into_iter()
                    .map(|edit| (edit.path, edit.value))
                    .collect(),
            )?;
            store.stage_update(account, &card_id, &patch)?;
            store.submit_update(&active.client, session, account, &card_id)?;
            let card = store
                .list(account, CARD)?
                .into_iter()
                .find(|card| card["id"] == card_id)
                .ok_or("Updated contact is no longer available.")?;
            Ok(Document { card })
        },
    )
    .await
}
