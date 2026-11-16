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

Enter a server address and discover its services before signing in. For a
WordPress connection, use a WordPress username and Application Password.
The shell uses the shared Rust client for discovery and sign-in; secrets are
not saved. JMAP accounts and ActivityPub actors are shown separately and
linked only when their canonical Actor URIs match.

The Tauri shell has its own Cargo workspace and lockfile, so CLI-only builds
do not pull in GUI dependencies. No drive letter or mobile SDK is required
for desktop builds. `npm run start` previews the layout in a browser;
connecting requires the Tauri app.
