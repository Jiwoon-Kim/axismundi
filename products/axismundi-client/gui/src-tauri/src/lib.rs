mod connection;
mod mail;
mod saved;

/// The application's wiring, kept apart from `run` so a test can build the same
/// thing against a mock runtime. A test that registered its own handler would
/// only prove the command compiles; this proves the command this app actually
/// exposes is reachable.
pub fn configure<R: tauri::Runtime>(builder: tauri::Builder<R>) -> tauri::Builder<R> {
    builder
        .plugin(tauri_plugin_opener::init())
        .manage(connection::Connection::default())
        .invoke_handler(tauri::generate_handler![
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
            mail::mail_view,
            mail::mail_read,
            mail::mail_thread,
        ])
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    configure(tauri::Builder::default())
        .run(tauri::generate_context!())
        .expect("error while running tauri application");
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
