# axismundi-client

A Rust client core with CLI and Tauri front ends for JMAP and ActivityPub,
with optional WordPress integration. JMAP Contacts does not depend on WordPress.

The CLI package and executable are named `axismundi` (formerly `axj`). Build/run
with `cargo run -p axismundi -- ...`. Existing `AXJ_*` environment variables remain
unchanged for compatibility with local credentials and interop scripts.

```txt
core/   the protocol.  Session, the method batch, the error model
cli/    a terminal front end over it, built as `axismundi`
```

## JMAP Mail Drafts

GUI language support direction (not implemented): [i18n design](docs/DESIGN-I18N.md).

Initial read-only CLI commands: `mailboxes`, `mail-inbox`, and `mail-read --id ID`,
using the same Session/credential flags as `session`. `mail-inbox` returns a bounded
window (at most 50 messages); `query.ids` defines the display order, not the order
of the `emails` array. `mail-read` fetches body values on demand with a 64 KiB
per-value limit; inspect truncation flags before treating a body as complete.
These commands do not modify server data or create a Mail database.

Target contracts and remaining work: [spec](docs/SPEC-JMAP-CLIENT.md),
[design and review questions](docs/DESIGN-JMAP-CLIENT.md), and
[CLI / persistence / GUI plan](docs/PLAN-JMAP-MAIL.md).
Core and Mail are the proposed product baseline; Contacts/Calendar storage remains
separate. The current Contacts tables are not a generic Mail store.

The split exists so a desktop front end can reuse `core` instead of writing the
exchange a second time behind a window. Nothing in `core` prints, and nothing in
`cli` speaks HTTP.

## The rule this client is built to keep

Every URL comes from the Session resource. The caller supplies a Session URL and
credentials; `apiUrl`, the accounts and the capabilities are read from what the
server advertises. The client appends no path, guesses no account identifier and
presumes no capability. An advertised relative `apiUrl` is resolved against
the URL of the Session response. A client that guesses `apiUrl` itself proves nothing,
because it would only ever work against the server it was written for.

## Running it

```bash
axismundi discover http://localhost:8884
axismundi discover https://localhost:18881 --jmap
axismundi session --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session
axismundi echo    --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session
axismundi todo    --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session
axismundi changes --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session [--since STATE]
axismundi todo-set --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session [--account ID]
axismundi contacts --session-url http://127.0.0.1:18880/jmap/session --db contacts.sqlite --profile local-stalwart
axismundi contacts-offline --db contacts.sqlite --profile local-stalwart --account ACCOUNT_ID
```

`discover` sends no credentials. It checks WordPress REST, JMAP Session
discovery, and the server's NodeInfo protocol advertisement independently.
A JMAP login challenge is not a missing JMAP endpoint. Discovery flags select
only the requested services; without flags the CLI retains the all-service probe.
The GUI starts with only JMAP selected and exposes independent checkboxes.
WordPress discovery reads the site's advertised HTML-head REST link rather than
guessing `/wp-json/` on unrelated servers. A host without a scheme
defaults to HTTPS; use an explicit HTTP URL for local development. NodeInfo
does not establish permission to submit ActivityPub activities.

`todo-set` is a write probe for loopback development servers only. It creates
its own TodoList and Todo, checks optimistic concurrency and partial failures,
and cleans up only the IDs returned by its own creates. If cleanup cannot be
confirmed, it reports the known IDs and the fixture marker for inspection.

Credentials come from `AXJ_USER` and `AXJ_SECRET` so the secret stays out of the
shell history and the process list; `--user` and `--secret` also work. Against a
WordPress server these are a username and an Application Password, which is that
server's authentication setting rather than an assumption of this client.

## What a run does and does not prove

`axismundi echo` needs no account selection. It verifies exactly one response with
the same method name, call ID, and JSON arguments. See the
[Stalwart Core smoke test](docs/INTEROP-CORE-ECHO.md) for the measured result.

```txt
localhost, staging     the deployment works.  Both are the same server implementation
a third-party server   protocol interoperability, on whatever both sides support
Todo                   our own engine and this client agreeing
```

The third line is not the second. The Todo capability is `urn:com.example:jmap:todo`,
the example string from RFC 8620, so no independent server will ever implement it:
Todo can show that this client and our server agree, never that either interoperates.
The ground actually shared with a third-party server is Session discovery,
authentication, `Core/echo` and the blob endpoints. A capability real servers also
implement, such as Contacts (RFC 9610), is where a shared-capability claim could be
earned.

Desktop connections use platform certificate verification and manually follow
authenticated GET redirects only within the same origin. POST redirects are not
followed. See [network trust and Session diagnostics](docs/IMPLEMENTATION-NETWORK-TRUST.md).

Contacts now has an independent-server round trip: see
[Contacts storage and Stalwart E2E](docs/INTEROP-CONTACTS.md). The read commands
above synchronize and display AddressBooks and ContactCards. Local queued update
submission is a core API exercised by the opt-in integration test, not yet a CLI
editing command or GUI. A profile ID is a persistent local identifier, not a
domain or a server-provided account ID. Use a distinct profile/cache for each login.

## GUI document reads

The GUI's Actor and Object document panels dereference the selected canonical
URI with an ActivityStreams Accept header, without sending connection credentials.
They do not substitute an Axismundi CMS collection or automatically retry through
its authenticated registry reader. A denied canonical read remains a denied read;
private retrieval through a home-server delegation is a separate feature.
The outbox address comes from the retrieved Actor document, not a Session copy.

## Setting this up on another machine

Nothing here is bound to this computer. The repository carries the workspace,
the committed `Cargo.lock` and a minimum Rust version, so a clone builds with

```bash
cargo build
```

and the binary lands in `./target/debug/`. The `D:` paths in the next section
are a local answer to a full system drive, not a requirement; leave those
variables unset and Cargo uses its defaults.

Two things are worth knowing because this machine cannot see them. Its
`CARGO_TARGET_DIR` moves build output off the project, so a build directory
that would appear under a shell never appears here, which is why `.gitignore`
matches `target/` at any depth rather than only at the root. And
`rust-version` is declared because edition 2024 needs Rust 1.85 or newer, which
fails on an older toolchain with an error that does not say so.

## Toolchain on this machine

Rust and its build output live on `D:`, a 2.8 TB spinning disk, while the
project and its npm dependencies stay on the NVMe system drive. The split is by
size and access pattern rather than by necessity: a Tauri build tree runs to
several gigabytes and is only touched when Rust changes, whereas node_modules
and the Angular cache are tens of thousands of small files that a dev server
reads continuously, which is the worst case for a hard disk.

```txt
RUSTUP_HOME       D:\rust\rustup
CARGO_HOME        D:\rust\cargo
CARGO_TARGET_DIR  D:\rust\target      ← so `target/` is NOT under this directory
```

The binary is therefore at `D:\rust\target\debug\axismundi.exe`, not `./target/debug/`.
`CARGO_TARGET_DIR` is set in the user environment, so it applies to every Rust
build on this machine, and a Tauri shell's dependency tree will land there too
rather than filling the system drive.

The MSVC linker and the Windows SDK were already present, so the default
`x86_64-pc-windows-msvc` target links without installing the Visual Studio C++
workload.
