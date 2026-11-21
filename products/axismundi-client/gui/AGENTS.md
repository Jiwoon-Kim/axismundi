# AGENTS.md — axismundi client GUI

Rules for any agent (Claude, Codex, other) working in `products/axismundi-client/gui/`.
The repository root `AGENTS.md` still applies. `CLAUDE.md` here only imports this file.

## Angular rules come from the installed version

Read the best-practices file that ships with the installed framework, not a copy:

```txt
node_modules/@angular/core/resources/best-practices.md
```

It is the same text as https://angular.dev/assets/context/best-practices.md for the version in
`package-lock.json` (Angular 22.2 at the time of writing), and it moves with upgrades. Do not paste
it here: a copy goes stale the day the framework is upgraded.

The `angular-cli` MCP server, when the agent has it, serves the same file through
`get_best_practices`, documentation for this version through `search_documentation`, and the
workspace through `list_projects`. It is registered read-only: it does not start `ng serve` or run
targets (see "Verify" below for why).

Where the upstream file is ambiguous, this project decides:

- **Component files.** The upstream file says both "prefer inline templates for small
  components" and "put the template in the html file". Here: a routed or stateful component has
  separate `.ts`, `.html` and `.css` files; a presentational component under ~20 template lines
  may be inline.
- **Services.** Use `@Service` for new singletons (exists in the installed `@angular/core`).
- **Forms.** Signal Forms (`@angular/forms/signals`, present in the installed version) for new
  forms.

## Architecture

- **Protocols live in Rust.** JMAP, ActivityPub, caching and sync are in `core/` and reached
  through named Tauri commands in `src-tauri/`. The GUI never builds a JMAP request, never runs
  SQL, and is never given a generic authenticated fetch.
- **Keep the route shell thin.** The shared rail links to available domain routes, with Settings
  at the bottom. The app bar's account popover keeps JMAP Account and ActivityPub Actor
  selections independent, even when they share authentication. Domain screens own their pane
  layout and use their protocol-specific identifiers and permissions.
- **Every state is visible.** Distinguish empty, loading, error, not cached and offline. A view
  built from the cache says so (`partialCache`, `queryStale`, `staleProperties`); never present a
  partial cache as the whole mailbox, or a truncated body as complete.
- **Text before rich content.** Plain-text bodies first. HTML mail and remote images need their
  own policy before they render.
- **Material 3** through `src/material-theme.scss`. Accessibility is part of done: AXE clean and
  WCAG AA, as the upstream rules require.

## Verify

`ng build` passing is not a working screen. A GUI change is done when it has been exercised in the
running Tauri binary — scrolled, clicked, restarted offline — and seen. `ng serve` alone runs
without the Tauri backend, so every `invoke` fails there; that is why the MCP server is read-only.

```txt
npm --prefix gui run build                                         Angular compiles
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib          IPC layer
node --test "gui/src/**/*.spec.ts"                                IPC response shapes → labels
```

`invoke<T>()` only casts: a wrong `T` compiles and renders blanks. A command returns a shape
declared in `src-tauri` (a `Serialize` struct), not the cache's raw documents. Its Rust test
serializes a real core response and compares it with a fixture file under `gui/src/`; the
`*.spec.ts` beside the TypeScript type reads that same file. Change the shape on one side only and
one of the two tests fails. Regenerate with `AXISMUNDI_UPDATE_FIXTURES=1 cargo test ...`.
Node 24 runs the spec with no test framework; `*.spec.ts` stays out of the app build.

Run from `products/axismundi-client`. Code comments are English; design notes may be Korean,
as everywhere in this repository.
