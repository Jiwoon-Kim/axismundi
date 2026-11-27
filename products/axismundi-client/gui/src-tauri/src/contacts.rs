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
            "books":[book_row(json!({"id":"test-book","name":"Test book","description":"Personal contacts",
                "sortOrder":10,"isDefault":true,"isSubscribed":true})).unwrap()],
            "rows":[row(source.clone()).unwrap(), row(json!({
                "id":"test-group","kind":"group","name":{"full":"Test Group"},
                "addressBookIds":{"test-book":true},"members":{"urn:uuid:test-member":true}
            })).unwrap()],
            "query": {
                "filter": serde_json::from_value::<core::Filter>(json!({"operator":"AND","conditions":[
                    {"operator":"OR","conditions":[{"inAddressBook":"test-book"},{"inAddressBook":"other-book"}]},
                    {"kind":"group","text":"Test"}]})).unwrap(),
                "sort": [core::Comparator { property: "name/surname".into(), is_ascending: false }]
            },
            "edit": Edit {path: vec!["phones".into(),"p1".into(),"number".into()], value:json!("123")},
            "detail":Document{row:row(source.clone()),card:source,state:Some("cards-state".into())},
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
    description: Option<String>,
    sort_order: Option<u64>,
    is_default: bool,
    is_subscribed: Option<bool>,
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct Row {
    id: String,
    kind: String,
    name: Option<String>,
    email: Option<String>,
    avatar_uri: Option<String>,
    phone: Option<String>,
    organization: Option<String>,
    sort_key: Option<String>,
}

#[derive(Serialize)]
pub(crate) struct Document {
    card: Value,
    row: Option<Row>,
    state: Option<String>,
}

#[derive(Deserialize, Serialize)]
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

fn book_row(book: Value) -> Option<Book> {
    Some(Book {
        id: string(&book["id"])?,
        name: string(&book["name"]),
        description: string(&book["description"]),
        sort_order: book["sortOrder"].as_u64(),
        is_default: book["isDefault"] == true,
        is_subscribed: book["isSubscribed"].as_bool(),
    })
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
    let name = &card["name"];
    let sort_key = ["surname", "given"].into_iter().filter_map(|kind| {
        string(&name["sortAs"][kind]).or_else(|| name["components"].as_array()?.iter()
            .find(|part| part["kind"] == kind).and_then(|part| string(&part["value"])))
    }).collect::<Vec<_>>().join(" ");
    Some(Row {
        id: string(&card["id"])?,
        kind: string(&card["kind"]).unwrap_or_else(|| "individual".to_owned()),
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
        phone: preferred(&card["phones"], "number", false),
        organization: preferred(&card["organizations"], "name", false),
        sort_key: if sort_key.is_empty() { string(&name["full"]) } else { Some(sort_key) },
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
            .filter_map(book_row)
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
    reload: Option<bool>,
) -> Result<Document, String> {
    run(
        state,
        connection_id,
        account_id,
        move |active, session, store, account| {
            if reload == Some(true) {
                core::sync_type(&active.client, session, store, account, CARD)?;
                store.discard_update_after_sync(account, &card_id)?;
            }
            let card = store
                .list(account, CARD)?
                .into_iter()
                .find(|card| card["id"] == card_id)
                .ok_or("Contact is not cached.")?;
            Ok(Document { row: row(card.clone()), card, state: store.state(account, CARD)? })
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

/// Runs the query and returns its rows in the server's order. The cache is brought up to date
/// after the query so every id it found is held; if a card is still missing (it changed between
/// the two), the query runs once more, and a second gap is an error rather than a short list
/// shown as complete.
fn query_rows(
    active: &Active,
    session: &Session,
    store: &mut core::Store,
    account: &str,
    filter: Option<&core::Filter>,
    sort: &[core::Comparator],
) -> core::Result<Vec<Row>> {
    for _ in 0..2 {
        let ids = core::query(&active.client, session, account, filter, sort)?;
        core::sync_type(&active.client, session, store, account, CARD)?;
        let mut rows: std::collections::HashMap<_, _> =
            store.list(account, CARD)?.into_iter().filter_map(row).map(|row| (row.id.clone(), row)).collect();
        let found: Vec<Row> = ids.iter().filter_map(|id| rows.remove(id)).collect();
        if found.len() == ids.len() {
            return Ok(found);
        }
    }
    Err("Contacts changed while the results were read. Try again.".into())
}

/// ContactCard/query with a JMAP filter and RFC 9610 comparators (online). Rows come back in the
/// server's order and are not sorted again here or in the GUI; an unsupported filter or sort is
/// the server's error, shown as such.
#[tauri::command]
pub(crate) async fn contacts_query(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    filter: Option<core::Filter>,
    sort: Option<Vec<core::Comparator>>,
) -> Result<Vec<Row>, String> {
    run(state, connection_id, account_id, move |active, session, store, account| {
        query_rows(active, session, store, account, filter.as_ref(), &sort.unwrap_or_default())
    })
    .await
}

/// Text search across every address book: `contacts_query` with `{text}` and no sort.
/// Kept until the GUI calls `contacts_query` directly.
#[tauri::command]
pub(crate) async fn contacts_search(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    text: String,
) -> Result<Vec<Row>, String> {
    run(state, connection_id, account_id, move |active, session, store, account| {
        let filter = core::Filter::Condition([("text".to_owned(), text)].into());
        query_rows(active, session, store, account, Some(&filter), &[])
    })
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
    base_card: Value,
) -> Result<Document, String> {
    run(
        state,
        connection_id,
        account_id,
        move |active, session, store, account| {
            if session.accounts[account].is_read_only {
                return Err("account is read-only".into());
            }
            let base = store.list(account, CARD)?.into_iter().find(|card| card["id"] == card_id)
                .ok_or("Contact is not cached.")?;
            if base != base_card {
                return Err("stateMismatch: this contact changed while editing".into());
            }
            for edit in &edits {
                let mut node = &base;
                for segment in &edit.path {
                    if node.is_array() { return Err("Replace an array as a whole.".into()); }
                    node = &node[segment];
                }
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
            Ok(Document { row: row(card.clone()), card, state: store.state(account, CARD)? })
        },
    )
    .await
}
