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
presumes no capability. A client that builds `apiUrl` itself proves nothing,
because it would only ever work against the server it was written for.

## Running it

```bash
axj session --session-url http://localhost:8884/wp-json/axismundi/jmap/session
axj todo    --session-url http://localhost:8884/wp-json/axismundi/jmap/session
```

Credentials come from `AXJ_USER` and `AXJ_SECRET` so the secret stays out of the
shell history and the process list; `--user` and `--secret` also work. Against a
WordPress server these are a username and an Application Password, which is that
server's authentication setting rather than an assumption of this client.

## What a run does and does not prove

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

## Toolchain on this machine

Rust lives on `D:` because the system drive is nearly full:
`RUSTUP_HOME=D:\rust\rustup`, `CARGO_HOME=D:\rust\cargo`. The MSVC linker and the
Windows SDK were already present, so the default `x86_64-pc-windows-msvc` target
links without installing the Visual Studio C++ workload.
