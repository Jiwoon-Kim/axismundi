//! Files a person picked or dropped for a draft's attachments.
//!
//! Only the OS file picker and the window's native drop register a file. The webview never sends a
//! path: it gets an opaque handle back and asks to upload or release by that handle, so a page
//! script cannot make the app read an arbitrary file.
use std::collections::HashMap;
use std::hash::BuildHasher;
use std::path::PathBuf;
use std::sync::{Arc, Condvar, Mutex};

use axismundi_client_core::jmap::CORE_CAPABILITY;
use serde::Serialize;

use crate::connection::{Connection, failure};

struct Picked {
    path: PathBuf,
    name: String,
    size: u64,
    media_type: String,
}

#[derive(Default)]
pub struct ComposeFiles {
    files: Arc<Mutex<HashMap<String, Picked>>>,
    /// Uploads in flight, held to the server's maxConcurrentUpload.
    running: Arc<(Mutex<usize>, Condvar)>,
}

#[derive(Clone, Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct FileView {
    handle: String,
    name: String,
    size: u64,
    #[serde(rename = "type")]
    media_type: String,
}

#[derive(Serialize)]
#[serde(rename_all = "camelCase")]
pub struct UploadView {
    blob_id: String,
    name: String,
    size: u64,
    #[serde(rename = "type")]
    media_type: String,
}

/// A content type from the file name. The server keeps what it is told, so an unknown kind is
/// sent as opaque bytes rather than guessed.
fn media_type(name: &str) -> &'static str {
    let ext = name.rsplit_once('.').map(|(_, ext)| ext.to_ascii_lowercase());
    match ext.as_deref() {
        Some("pdf") => "application/pdf",
        Some("png") => "image/png",
        Some("jpg" | "jpeg") => "image/jpeg",
        Some("gif") => "image/gif",
        Some("webp") => "image/webp",
        Some("avif") => "image/avif",
        Some("bmp") => "image/bmp",
        Some("svg") => "image/svg+xml",
        Some("txt") => "text/plain",
        Some("csv") => "text/csv",
        Some("md") => "text/markdown",
        Some("json") => "application/json",
        Some("zip") => "application/zip",
        Some("doc") => "application/msword",
        Some("docx") => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        Some("xls") => "application/vnd.ms-excel",
        Some("xlsx") => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        Some("ppt") => "application/vnd.ms-powerpoint",
        Some("pptx") => "application/vnd.openxmlformats-officedocument.presentationml.presentation",
        Some("mp3") => "audio/mpeg",
        Some("mp4") => "video/mp4",
        Some("ics") => "text/calendar",
        Some("vcf") => "text/vcard",
        Some("eml") => "message/rfc822",
        _ => "application/octet-stream",
    }
}

impl ComposeFiles {
    /// Registers regular files from a trusted native source; anything else is skipped.
    pub fn register(&self, paths: Vec<PathBuf>) -> Vec<FileView> {
        let Ok(mut files) = self.files.lock() else {
            return Vec::new();
        };
        let random = std::collections::hash_map::RandomState::new();
        let mut views = Vec::new();
        for path in paths {
            let Ok(meta) = std::fs::metadata(&path) else { continue };
            if !meta.is_file() {
                continue;
            }
            let name = path
                .file_name()
                .map(|name| name.to_string_lossy().into_owned())
                .unwrap_or_else(|| "attachment".into());
            let handle = format!("f{:016x}{:x}", random.hash_one(&path), files.len());
            let media_type = media_type(&name).to_owned();
            views.push(FileView {
                handle: handle.clone(),
                name: name.clone(),
                size: meta.len(),
                media_type: media_type.clone(),
            });
            files.insert(handle, Picked { path, name, size: meta.len(), media_type });
        }
        views
    }

    fn get(&self, handle: &str) -> Result<(PathBuf, String, u64, String), String> {
        lookup(&self.files, handle)
    }
}

fn lookup(files: &Mutex<HashMap<String, Picked>>, handle: &str) -> Result<(PathBuf, String, u64, String), String> {
    let files = files.lock().map_err(|_| "Attachment files are unavailable.")?;
    let file = files.get(handle).ok_or("This file is no longer available. Attach it again.")?;
    Ok((file.path.clone(), file.name.clone(), file.size, file.media_type.clone()))
}

/// The Account's maxSizeAttachmentsPerEmail, so the composer can refuse a file before uploading it.
#[tauri::command]
pub fn mail_attachment_limit(
    connection_id: u32,
    account_id: String,
    state: tauri::State<'_, Connection>,
) -> Result<Option<u64>, String> {
    let active = state.current(connection_id)?;
    let mail = active.mail.as_ref().ok_or("Mail is not available on this connection")?;
    Ok(mail.session.accounts.get(&account_id)
        .and_then(|account| account.account_capabilities.get(axismundi_client_core::jmap::mail::CAPABILITY))
        .and_then(|caps| caps["maxSizeAttachmentsPerEmail"].as_u64()))
}

#[tauri::command]
pub async fn mail_attachment_pick<R: tauri::Runtime>(
    app: tauri::AppHandle<R>,
    files: tauri::State<'_, ComposeFiles>,
) -> Result<Vec<FileView>, String> {
    use tauri::Manager;
    let parent = app.get_webview_window("main");
    let paths = tauri::async_runtime::spawn_blocking(move || {
        let dialog = rfd::FileDialog::new();
        match parent {
            Some(window) => dialog.set_parent(&window).pick_files(),
            None => dialog.pick_files(),
        }
    })
    .await
    .map_err(|_| "The file picker did not complete.")?;
    Ok(files.register(paths.unwrap_or_default()))
}

#[tauri::command]
pub fn mail_attachment_release(handles: Vec<String>, files: tauri::State<'_, ComposeFiles>) -> Result<(), String> {
    let mut map = files.files.lock().map_err(|_| "Attachment files are unavailable.")?;
    for handle in handles {
        map.remove(&handle);
    }
    Ok(())
}

/// Uploads a registered file to the Account's uploadUrl. A removed attachment cannot abort the
/// transfer under way; the caller drops its answer, and the server cleans up the unreferenced blob.
#[tauri::command]
pub async fn mail_attachment_upload(
    connection_id: u32,
    account_id: String,
    handle: String,
    state: tauri::State<'_, Connection>,
    files: tauri::State<'_, ComposeFiles>,
) -> Result<UploadView, String> {
    let (_, name, size, media_type) = files.get(&handle)?;
    let active = state.current(connection_id)?;
    let running = files.running.clone();
    let registry = files.files.clone();
    let view = tauri::async_runtime::spawn_blocking(move || {
        let mail = active.mail.as_ref().ok_or("Mail is not available on this connection")?;
        let session = &mail.session;
        let limit = |key: &str| session.capabilities.get(CORE_CAPABILITY)?.get(key)?.as_u64();
        if limit("maxSizeUpload").is_some_and(|max| size > max) {
            return Err("This file is larger than the server accepts.".to_owned());
        }
        let most = limit("maxConcurrentUpload").unwrap_or(4).max(1) as usize;
        {
            let (count, wake) = &*running;
            let mut count = count.lock().map_err(|_| "Uploads are unavailable.")?;
            while *count >= most {
                count = wake.wait(count).map_err(|_| "Uploads are unavailable.")?;
            }
            *count += 1;
        }
        // The attachment may have been removed while this upload waited for a slot: then it must
        // not start. Look the handle up again only now.
        let result = lookup(&registry, &handle)
            .and_then(|(path, ..)| std::fs::read(path).map_err(|_| "Could not read the file.".to_owned()))
            .and_then(|bytes| {
                if bytes.len() as u64 != size {
                    return Err("The file changed after it was attached. Attach it again.".into());
                }
                active.client.upload(session, &account_id, &media_type, &bytes)
                    .map_err(|error| failure("Attachment upload", &error))
            });
        let (count, wake) = &*running;
        if let Ok(mut count) = count.lock() {
            *count -= 1;
            wake.notify_one();
        }
        let uploaded = result?;
        Ok::<_, String>(UploadView { blob_id: uploaded.blob_id, name, size: uploaded.size, media_type })
    })
    .await
    .map_err(|_| "The upload did not complete.")??;
    state.current(connection_id)?;
    Ok(view)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn only_registered_files_have_handles_and_directories_are_skipped() {
        let dir = tempfile::tempdir().unwrap();
        let file = dir.path().join("Report.PDF");
        std::fs::write(&file, b"%PDF").unwrap();
        let files = ComposeFiles::default();
        let views = files.register(vec![file.clone(), dir.path().to_path_buf()]);
        assert_eq!(views.len(), 1);
        assert_eq!(views[0].name, "Report.PDF");
        assert_eq!(views[0].media_type, "application/pdf");
        assert_eq!(views[0].size, 4);
        assert!(files.get(&views[0].handle).is_ok());
        assert!(files.get("not-a-handle").is_err());
        // A released handle cannot start a queued upload: the second lookup fails.
        files.files.lock().unwrap().remove(&views[0].handle);
        assert!(lookup(&files.files, &views[0].handle).is_err());
        assert_eq!(media_type("noext"), "application/octet-stream");
    }
}
