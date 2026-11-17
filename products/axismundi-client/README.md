# axismundi-client

A JMAP client that knows nothing about WordPress.

```txt
core/   the protocol.  Session, the method batch, the error model
cli/    a terminal front end over it, built as `axj`
```

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
axj discover http://localhost:8884
axj session --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session
axj echo    --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session
axj todo    --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session
axj changes --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session [--since STATE]
axj todo-set --session-url http://localhost:8884/wp-json/axismundi/v1/users/me/session [--account ID]
```

`discover` sends no credentials. It checks WordPress REST, JMAP Session
discovery, and the server's NodeInfo protocol advertisement independently.
A JMAP login challenge is not a missing JMAP endpoint. A host without a scheme
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

`axj echo` needs no account selection. It verifies exactly one response with
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

The binary is therefore at `D:\rust\target\debug\axj.exe`, not `./target/debug/`.
`CARGO_TARGET_DIR` is set in the user environment, so it applies to every Rust
build on this machine, and a Tauri shell's dependency tree will land there too
rather than filling the system drive.

The MSVC linker and the Windows SDK were already present, so the default
`x86_64-pc-windows-msvc` target links without installing the Visual Studio C++
workload.
