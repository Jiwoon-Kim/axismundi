use std::{
    sync::{Arc, atomic::{AtomicBool, Ordering}},
    thread::{self, Thread},
    time::Duration,
};

use axismundi_client_core::jmap::{mail, websocket::{self, Wake, watch_loop}};
use serde::Serialize;
use tauri::Emitter;

use crate::connection::{Active, Connection};

#[derive(Clone, Serialize)]
#[serde(rename_all = "camelCase")]
struct MailChanged {
    connection_id: u32,
    account_ids: Vec<String>,
    reason: &'static str,
}

pub(crate) struct Worker {
    cancelled: Arc<AtomicBool>,
    thread: Thread,
}

impl Drop for Worker {
    fn drop(&mut self) {
        self.cancelled.store(true, Ordering::Relaxed);
        self.thread.unpark();
    }
}

impl Worker {
    pub(crate) fn start<R: tauri::Runtime>(app: tauri::AppHandle<R>, id: u32, active: Arc<Active>) -> Result<Option<Self>, String> {
        let Some(mail) = &active.mail else { return Ok(None); };
        if websocket::endpoint(&mail.session).is_err() { return Ok(None); }
        let cancelled = Arc::new(AtomicBool::new(false));
        let stop = Arc::clone(&cancelled);
        let worker = thread::Builder::new().name("mail-push".into()).spawn(move || {
            let mail = active.mail.as_ref().expect("checked before spawning");
            let accounts = mail.session.accounts_with(mail::CAPABILITY);
            let result = watch_loop(
                &accounts,
                None,
                |state| active.client.push_channel(&mail.session, Some(&["Email", "Mailbox"]), state, Duration::from_secs(1)),
                |wake| {
                    let (reason, account_ids) = match wake {
                        Wake::Initial => return Ok(()),
                        Wake::CatchUp => ("catch-up", accounts.iter().map(|id| (*id).to_owned()).collect()),
                        Wake::Push(changed) => ("push", changed.into_keys().collect()),
                    };
                    app.emit("mail-changed", MailChanged { connection_id: id, account_ids, reason })
                        .map_err(|_| "could not emit mail change".to_owned())
                },
                thread::park_timeout,
                || stop.load(Ordering::Relaxed),
                |_| {},
                |error| match error {
                    axismundi_client_core::Error::Status { status, .. }
                    | axismundi_client_core::Error::Problem { status, .. } => format!("server rejected push (HTTP {status})"),
                    axismundi_client_core::Error::Shape(_) => "unexpected WebSocket protocol response".into(),
                    _ => "WebSocket setup failed".into(),
                },
            );
            if let Err(reason) = result {
                if !stop.load(Ordering::Relaxed) {
                    eprintln!("mail push stopped for connection {id}: {reason}");
                }
            }
        }).map_err(|error| error.to_string())?;
        Ok(Some(Self { cancelled, thread: worker.thread().clone() }))
    }
}

#[tauri::command]
pub fn mail_watch<R: tauri::Runtime>(
    app: tauri::AppHandle<R>, state: tauri::State<'_, Connection>, connection_id: Option<u32>,
) -> Result<(), String> {
    state.watch_mail(app, connection_id)
}
