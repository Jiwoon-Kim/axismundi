// Learn more about Tauri commands at https://tauri.app/develop/calling-rust/
#[tauri::command]
fn greet(name: &str) -> String {
    format!("Hello, {}! You've been greeted from Rust!", name)
}

/// The application's wiring, kept apart from `run` so a test can build the same
/// thing against a mock runtime. A test that registered its own handler would
/// only prove the command compiles; this proves the command this app actually
/// exposes is reachable.
pub fn configure<R: tauri::Runtime>(builder: tauri::Builder<R>) -> tauri::Builder<R> {
    builder
        .plugin(tauri_plugin_opener::init())
        .invoke_handler(tauri::generate_handler![greet])
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

    /// The front end calls `invoke("greet", { name })`. This drives the same
    /// request through the registered handler, so the scaffold's claim that the
    /// webview can reach Rust rests on something repeatable rather than on one
    /// person having clicked a button once.
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
                cmd: "greet".into(),
                callback: tauri::ipc::CallbackFn(0),
                error: tauri::ipc::CallbackFn(1),
                url: "http://tauri.localhost".parse().unwrap(),
                body: serde_json::json!({ "name": "Axismundi" }).into(),
                headers: Default::default(),
                invoke_key: tauri::test::INVOKE_KEY.to_string(),
            },
        );

        let body = response.expect("the command answers rather than erroring");
        assert_eq!(
            body.deserialize::<String>().unwrap(),
            "Hello, Axismundi! You've been greeted from Rust!"
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
