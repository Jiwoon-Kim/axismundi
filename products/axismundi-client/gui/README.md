# Axismundi GUI

The Tauri shell uses Angular and Angular Material's own Material 3 theme. It
does not import the WordPress frontend's styles or tokens.

Install Node.js compatible with Angular 22 and a current stable Rust toolchain.
See the [Tauri prerequisites](https://v2.tauri.app/start/prerequisites/) for
platform dependencies. Run these commands from this directory:

```sh
npm ci
npm run tauri dev
```

The starter's Greet button tests the WebView-to-Rust IPC path. It does not
connect to a server or depend on the protocol core yet. The Tauri shell has its
own Cargo workspace and lockfile, so CLI-only builds do not pull in GUI
dependencies. No drive letter or mobile SDK is required for desktop builds.
