mod compose_files;
mod contacts;
mod connection;
mod mail;
mod mail_push;
mod saved;
use tauri::Manager;

/// The application's wiring, kept apart from `run` so a test can build the same
/// thing against a mock runtime. A test that registered its own handler would
/// only prove the command compiles; this proves the command this app actually
/// exposes is reachable.
pub fn configure<R: tauri::Runtime>(builder: tauri::Builder<R>) -> tauri::Builder<R> {
    builder
        .plugin(tauri_plugin_opener::init())
        .manage(connection::Connection::default())
        .manage(mail::OpenedAttachments::default())
        .manage(compose_files::ComposeFiles::default())
        // A native file drop is the other trusted source of attachment files: register them here
        // and hand the webview handles, never paths.
        .on_window_event(|window, event| {
            use tauri::Emitter;
            if let tauri::WindowEvent::DragDrop(tauri::DragDropEvent::Drop { paths, .. }) = event {
                let views = window.state::<compose_files::ComposeFiles>().register(paths.clone());
                if !views.is_empty() {
                    let _ = window.emit("compose-files-dropped", views);
                }
            }
        })
        .invoke_handler(tauri::generate_handler![
            contacts::contacts_books,
            contacts::contacts_list,
            contacts::contacts_card,
            contacts::contacts_sync,
            contacts::contacts_search,
            contacts::contacts_create,
            contacts::contacts_update,
            connection::discover_server,
            connection::connect_server,
            connection::disconnect_server,
            connection::disconnect_all,
            connection::saved_connections,
            connection::restore_connection,
            connection::forget_connection,
            connection::actor_document,
            connection::outbox_page,
            connection::object_by_id,
            mail::mail_sync,
            mail::mail_more,
            mail::mail_search,
            mail::mail_view,
            mail::mail_read,
            mail::mail_part_bytes,
            mail::mail_attachment_export,
            mail::mail_identities,
            mail::mail_compose_source,
            mail::mail_compose_save,
            mail::mail_compose_discard,
            compose_files::mail_attachment_pick,
            compose_files::mail_attachment_upload,
            compose_files::mail_attachment_release,
            compose_files::mail_attachment_limit,
            mail::mail_thread,
            mail::mail_set_keywords,
            mail::mail_move,
            mail::mail_write_mailbox,
            mail::mail_check,
            mail_push::mail_watch,
        ])
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    configure(tauri::Builder::default())
        .build(tauri::generate_context!())
        .expect("error while building tauri application")
        .run(|app, event| {
            if matches!(event, tauri::RunEvent::Exit) {
                let _ = app.state::<connection::Connection>().watch_mail(app.clone(), None);
            }
        });
}

#[cfg(test)]
mod tests {
    use tauri::test::{mock_context, noop_assets};

    /// A request reaches the real Rust command without opening a network socket.
    #[test]
    fn the_registered_command_answers_an_ipc_request() {
        let app = super::configure(tauri::test::mock_builder())
            .build(mock_context(noop_assets()))
            .expect("the app builds against the mock runtime");
        let webview = tauri::WebviewWindowBuilder::new(&app, "main", Default::default())
            .build()
            .expect("the mock webview builds");

        let response = tauri::test::get_ipc_response(
            &webview,
            tauri::webview::InvokeRequest {
                cmd: "discover_server".into(),
                callback: tauri::ipc::CallbackFn(0),
                error: tauri::ipc::CallbackFn(1),
                url: "http://tauri.localhost".parse().unwrap(),
                body: serde_json::json!({ "input": "ftp://example.test/" }).into(),
                headers: Default::default(),
                invoke_key: tauri::test::INVOKE_KEY.to_string(),
            },
        );

        assert_eq!(
            response.unwrap_err(),
            "discovery requires HTTP(S) URLs without embedded credentials or fragments"
        );
    }

    /// A command the app does not register must not answer, or the test above
    /// would pass against any handler at all.
    #[test]
    fn an_unregistered_command_is_refused() {
        let app = super::configure(tauri::test::mock_builder())
            .build(mock_context(noop_assets()))
            .expect("the app builds against the mock runtime");
        let webview = tauri::WebviewWindowBuilder::new(&app, "main", Default::default())
            .build()
            .expect("the mock webview builds");

        let response = tauri::test::get_ipc_response(
            &webview,
            tauri::webview::InvokeRequest {
                cmd: "not_a_command".into(),
                callback: tauri::ipc::CallbackFn(0),
                error: tauri::ipc::CallbackFn(1),
                url: "http://tauri.localhost".parse().unwrap(),
                body: serde_json::json!({}).into(),
                headers: Default::default(),
                invoke_key: tauri::test::INVOKE_KEY.to_string(),
            },
        );

        assert!(response.is_err());
    }
}
