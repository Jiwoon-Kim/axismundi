//! Native commands for the JMAP FileNode account, independent of Mail and Contacts.
use std::sync::Arc;

use axismundi_client_core::jmap::{Session, blob, filenode};
use serde::{Deserialize, Serialize};
use serde_json::{Value, json};
use tauri::Manager;

use crate::{
    compose_files::{ComposeFiles, upload_picked},
    connection::{Active, Connection},
};

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn files_fixture_matches_the_ipc_contract() {
        use filenode::{Quotas, UploadResult};
        let fixture: Value =
            serde_json::from_str(include_str!("../../src/app/files/files.fixture.json")).unwrap();
        let node = json!({"@type":"File","id":"file","parentId":null,"name":"example.txt","blobId":"stored","size":8,"mediaType":"text/plain"});
        assert_eq!(
            fixture,
            json!({
                "listing": Listing { nodes: vec![node.clone()], position: 0, query_state: "q".into(), total: Some(1), limit: 200 },
                "upload": {
                    "saved": UploadResult::Saved {node},
                    "exists": UploadResult::Rejected {error: json!({"type":"alreadyExists","existingId":"file"})},
                    "overQuota": UploadResult::Rejected {error: json!({"type":"overQuota"})},
                    "unknown": UploadResult::Unknown {parent_id: None, name: "example.txt".into(), error: "No response".into()},
                    "needsRefresh": UploadResult::NeedsRefresh {id: "file".into(), error: "Could not confirm".into()},
                },
                "quota": {
                    "available": Quotas::Available {list: vec![json!({"id":"q","resourceType":"octets","scope":"account","used":8,"hardLimit":4096,"types":["FileNode","Email"]})]},
                    "notApplicable": Quotas::NotApplicable,
                    "failed": Quotas::Failed {error: "Could not load quotas".into()},
                },
                "usage": Usage {blob_id: "stored".into(), types: vec!["FileNode".into(),"Email".into()],
                    references: [("FileNode".into(), vec!["file".into()])].into()},
            })
        );
    }
}

fn context(
    state: &Connection,
    connection: u32,
    requested: Option<&str>,
) -> Result<(Arc<Active>, Session, String), String> {
    let active = state.current(connection)?;
    let session = active
        .files_session
        .clone()
        .ok_or("Files is not available on this connection.")?;
    let supported = session.accounts_with(filenode::CAPABILITY);
    let account = match requested {
        Some(id) => supported.into_iter().find(|candidate| *candidate == id),
        None => session
            .primary_account(filenode::CAPABILITY)
            .filter(|id| supported.contains(id))
            .or_else(|| {
                supported
                    .iter()
                    .copied()
                    .find(|id| session.accounts[*id].is_personal)
            })
            .or_else(|| supported.first().copied()),
    }
    .ok_or("No eligible FileNode account.")?
    .to_owned();
    Ok((active, session, account))
}

async fn run<T: Send + 'static>(
    state: &Connection,
    connection: u32,
    account: Option<&str>,
    work: impl FnOnce(&Active, &Session, &str) -> Result<T, String> + Send + 'static,
) -> Result<T, String> {
    let (active, session, account) = context(state, connection, account)?;
    let value = tauri::async_runtime::spawn_blocking(move || work(&active, &session, &account))
        .await
        .map_err(|_| "Files command did not complete.")?;
    state.current(connection)?;
    value
}

fn error(value: axismundi_client_core::Error) -> String {
    value.to_string()
}

#[derive(Deserialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct Sort {
    property: String,
    is_ascending: bool,
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct Listing {
    nodes: Vec<Value>,
    position: u64,
    query_state: String,
    total: Option<u64>,
    /// The page size this page was read with: the server's `limit` when it lowered the request
    /// (RFC 8620 §5.5), else the request clamped to maxObjectsInGet. A page shorter than this ends
    /// the folder, which is how the GUI knows when the server does not count `total`.
    limit: u64,
}

#[tauri::command]
pub(crate) async fn files_list(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    parent_id: Option<String>,
    sort: Vec<Sort>,
    position: u64,
    limit: u64,
) -> Result<Listing, String> {
    run(
        &state,
        connection_id,
        account_id.as_deref(),
        move |a, s, id| {
            let sort = sort
                .iter()
                .map(|sort| filenode::Comparator {
                    property: &sort.property,
                    is_ascending: sort.is_ascending,
                })
                .collect::<Vec<_>>();
            let max =
                s.capabilities[axismundi_client_core::jmap::CORE_CAPABILITY]["maxObjectsInGet"]
                    .as_u64()
                    .unwrap_or(100)
                    .clamp(1, 500);
            let asked = limit.clamp(1, max);
            let page = filenode::query(
                &a.client,
                s,
                id,
                parent_id.as_deref(),
                &sort,
                position,
                asked,
            )
            .map_err(error)?;
            let ids = page["ids"]
                .as_array()
                .ok_or("FileNode/query omitted ids.")?
                .iter()
                .map(|id| id.as_str().ok_or("Invalid FileNode id."))
                .collect::<Result<Vec<_>, _>>()?;
            let mut nodes = Vec::new();
            if !ids.is_empty() {
                let answer = filenode::get(&a.client, s, id, &ids, false).map_err(error)?;
                let list = answer["list"]
                    .as_array()
                    .ok_or("FileNode/get omitted the list.")?;
                for id in ids {
                    nodes.push(
                        list.iter()
                            .find(|node| node["id"] == id)
                            .ok_or("Files changed during listing. Retry.")?
                            .clone(),
                    );
                }
            }
            Ok(Listing {
                nodes,
                position: page["position"]
                    .as_u64()
                    .ok_or("FileNode/query omitted position.")?,
                query_state: page["queryState"]
                    .as_str()
                    .ok_or("FileNode/query omitted queryState.")?
                    .into(),
                total: page["total"].as_u64(),
                limit: page["limit"].as_u64().unwrap_or(asked).min(asked),
            })
        },
    )
    .await
}

#[tauri::command]
pub(crate) async fn files_get(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    node_id: String,
    fetch_parents: bool,
) -> Result<Value, String> {
    run(
        &state,
        connection_id,
        account_id.as_deref(),
        move |a, s, id| filenode::get(&a.client, s, id, &[&node_id], fetch_parents).map_err(error),
    )
    .await
}

#[derive(Deserialize)]
#[serde(
    tag = "kind",
    rename_all = "camelCase",
    rename_all_fields = "camelCase"
)]
pub(crate) enum Mutation {
    CreateFolder {
        parent_id: Option<String>,
        name: String,
    },
    Rename {
        node_id: String,
        name: String,
    },
    Move {
        node_id: String,
        parent_id: Option<String>,
    },
    Destroy {
        node_id: String,
        remove_children: bool,
    },
}

#[tauri::command]
pub(crate) async fn files_mutate(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    mutation: Mutation,
) -> Result<Value, String> {
    let arguments = match mutation {
        Mutation::CreateFolder { parent_id, name } => {
            json!({"create":{"folder":{"@type":"Directory","name":name,"parentId":parent_id}}})
        }
        Mutation::Rename { node_id, name } => json!({"update":{(node_id):{"name":name}}}),
        Mutation::Move { node_id, parent_id } => {
            json!({"update":{(node_id):{"parentId":parent_id}}})
        }
        Mutation::Destroy {
            node_id,
            remove_children,
        } => json!({"destroy":[node_id],"onDestroyRemoveChildren":remove_children}),
    };
    run(
        &state,
        connection_id,
        account_id.as_deref(),
        move |a, s, id| filenode::set(&a.client, s, id, arguments).map_err(error),
    )
    .await
}

#[derive(Clone, Copy, Deserialize)]
#[serde(rename_all = "camelCase")]
pub(crate) enum Collision {
    Rename,
    Replace,
}

#[tauri::command]
pub(crate) async fn files_upload(
    state: tauri::State<'_, Connection>,
    files: tauri::State<'_, ComposeFiles>,
    connection_id: u32,
    account_id: Option<String>,
    handle: String,
    parent_id: Option<String>,
    on_exists: Option<Collision>,
) -> Result<filenode::UploadResult, String> {
    let (active, session, account) = context(&state, connection_id, account_id.as_deref())?;
    if session.accounts[&account].is_read_only {
        return Err("This account is read only.".into());
    }
    let upload = upload_picked(
        &files,
        active.clone(),
        session.clone(),
        account.clone(),
        handle,
        |name| filenode::validate_name(&session, &account, name).map_err(error),
    )
    .await?;
    state.current(connection_id)?;
    let on_exists = on_exists.map(|policy| match policy {
        Collision::Rename => filenode::OnExists::Rename,
        Collision::Replace => filenode::OnExists::Replace,
    });
    let result = tauri::async_runtime::spawn_blocking(move || {
        filenode::create_file(
            &active.client,
            &session,
            &account,
            parent_id.as_deref(),
            &upload.name,
            &upload.media_type,
            &upload.blob_id,
            upload.size,
            on_exists,
        )
        .map_err(error)
    })
    .await
    .map_err(|_| "FileNode save did not complete.")?;
    state.current(connection_id)?;
    result
}

fn file_node(active: &Active, session: &Session, account: &str, id: &str) -> Result<Value, String> {
    let answer = filenode::get(&active.client, session, account, &[id], false).map_err(error)?;
    answer["list"]
        .as_array()
        .and_then(|nodes| {
            nodes
                .iter()
                .find(|node| node["id"] == id && node["@type"] == "File")
        })
        .cloned()
        .ok_or_else(|| "File not found.".into())
}

#[tauri::command]
pub(crate) async fn files_download<R: tauri::Runtime>(
    app: tauri::AppHandle<R>,
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    node_id: String,
) -> Result<bool, String> {
    let (_, _, selected_account) = context(&state, connection_id, account_id.as_deref())?;
    let account_id = Some(selected_account);
    let id = node_id.clone();
    let file = run(
        &state,
        connection_id,
        account_id.as_deref(),
        move |a, s, account| file_node(a, s, account, &id),
    )
    .await?;
    let name = crate::mail::attachment_name(file["name"].as_str().unwrap_or("file"));
    let parent = app.get_webview_window("main");
    let path = tauri::async_runtime::spawn_blocking(move || {
        let dialog = rfd::FileDialog::new().set_file_name(name);
        match parent {
            Some(window) => dialog.set_parent(&window).save_file(),
            None => dialog.save_file(),
        }
    })
    .await
    .map_err(|_| "Save dialog did not complete.")?;
    let Some(path) = path else {
        return Ok(false);
    };
    let directory = path
        .parent()
        .filter(|parent| !parent.as_os_str().is_empty())
        .ok_or("Save destination has no parent directory.")?
        .to_path_buf();
    let staged = run(
        &state,
        connection_id,
        account_id.as_deref(),
        move |a, s, account| {
            let file = file_node(a, s, account, &node_id)?;
            let blob = file["blobId"]
                .as_str()
                .ok_or("File has no stored blobId.")?;
            let size = file["size"].as_u64().ok_or("File has no valid size.")?;
            let mut staged =
                tempfile::NamedTempFile::new_in(directory).map_err(|e| e.to_string())?;
            a.client
                .download_to(
                    s,
                    account,
                    blob,
                    file["mediaType"]
                        .as_str()
                        .unwrap_or("application/octet-stream"),
                    file["name"].as_str().unwrap_or("file"),
                    size,
                    staged.as_file_mut(),
                )
                .map_err(error)?;
            staged.as_file().sync_all().map_err(|e| e.to_string())?;
            Ok(staged)
        },
    )
    .await?;
    tauri::async_runtime::spawn_blocking(move || staged.persist(path))
        .await
        .map_err(|_| "Save did not complete.")?
        .map_err(|e| e.to_string())?;
    Ok(true)
}

#[tauri::command]
pub(crate) async fn files_quotas(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
) -> Result<filenode::Quotas, String> {
    run(&state, connection_id, account_id.as_deref(), |a, s, id| {
        Ok(filenode::quotas(&a.client, s, id))
    })
    .await
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub(crate) struct Usage {
    blob_id: String,
    types: Vec<String>,
    references: std::collections::BTreeMap<String, Vec<String>>,
}

#[tauri::command]
pub(crate) async fn files_usage(
    state: tauri::State<'_, Connection>,
    connection_id: u32,
    account_id: Option<String>,
    node_id: String,
) -> Result<Usage, String> {
    run(
        &state,
        connection_id,
        account_id.as_deref(),
        move |a, s, id| {
            let file = file_node(a, s, id, &node_id)?;
            let blob_id = file["blobId"]
                .as_str()
                .ok_or("File has no stored blobId.")?;
            let advertised = s.accounts[id]
                .account_capabilities
                .get(blob::CAPABILITY)
                .and_then(|cap| cap["supportedTypeNames"].as_array())
                .ok_or("Blob lookup is unavailable.")?;
            let types = ["FileNode", "Email"]
                .into_iter()
                .filter(|kind| advertised.iter().any(|name| name == *kind))
                .filter(|kind| {
                    *kind != "Email"
                        || s.accounts[id]
                            .account_capabilities
                            .contains_key(axismundi_client_core::jmap::mail::CAPABILITY)
                })
                .collect::<Vec<_>>();
            if types.is_empty() {
                return Err("No supported file usage lookup types.".into());
            }
            let mut found = a
                .client
                .blob_lookup(s, id, &types, &[blob_id])
                .map_err(error)?;
            Ok(Usage {
                blob_id: blob_id.into(),
                types: types.into_iter().map(str::to_owned).collect(),
                references: found.remove(blob_id).unwrap_or_default(),
            })
        },
    )
    .await
}
