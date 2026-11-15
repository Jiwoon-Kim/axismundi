# JMAP Core/echo interoperability

On 2026-11-15, the Rust `axj` binary authenticated to Stalwart Server v1.1.0
using the container's temporary bootstrap administrator and HTTP Basic.
The server ran in a local Docker container with only port
`127.0.0.1:18880` published. No external account or personal data was used.

The supplied Session URL was `http://127.0.0.1:18880/jmap/session`.
Stalwart advertised JMAP Core and the relative `apiUrl` `/jmap/`; the
client resolved that reference against the Session response URL. It sent
only the Core capability and no account ID. The invocation matched the
sent method name, call ID, and nested JSON value exactly. Exit status: 0.

The same binary also passed against the Axismundi wp-env Session on
`http://localhost:8884`. It followed that server's absolute `apiUrl`
`http://localhost:8884/wp-json/axismundi/jmap` with no path substitution.
This second run is the Axismundi baseline, not an additional independent
implementation.

This is Session discovery, Basic authentication, and Core method
interoperability with an independent server implementation. The local
fixture used loopback HTTP, not production HTTPS, and does not measure
TLS setup, Mail, Contacts, blobs, Todo, or incremental synchronization.

The container's configuration/data bind mounts and the Cargo build
output were placed in `D:\axismundi-interop`. Image layers use Docker's
existing storage configuration.

## Independent re-verification, 2026-11-15

Both legs were re-run by a second party rather than taken from the first
report. Three corrections came out of it.

The container was still running, not stopped: `Up (healthy)` with
`127.0.0.1:18880` published to loopback only. It is left running because the
Contacts leg below will want it.

Stalwart issues a new bootstrap password on every restart and prints each one,
so the credential is the **last** `password:` line in `docker logs`, not the
first. Taking the first produced a 401 that looked like an expired account.

The first two re-runs failed because the compiled binary predated the `echo`
subcommand: `cargo test` rebuilds the library and the test harness, not the
`axj` executable. After `cargo build`, both legs pass.

```txt
Stalwart v1   apiUrl /jmap/ resolved against the Session URL, authenticated,
              Core/echo arguments match exactly, exit 0
Axismundi     apiUrl absolute, authenticated, arguments match exactly, exit 0
```

## What Stalwart advertises, and why it matters next

Its Session lists `urn:ietf:params:jmap:contacts` and
`urn:ietf:params:jmap:calendars` alongside Core, Mail, blob and submission.
So the capability that could widen this proof beyond Core is already
implemented on the independent side: Contacts is RFC 9610, which our Contacts
plugin registers the type for. That is a concrete reason for the recorded
ordering of Todo before Contacts, rather than a preference.

It does not change what this document measures. A shared capability has to be
exercised to be claimed, and neither side's Contacts methods have been called
from this client.
