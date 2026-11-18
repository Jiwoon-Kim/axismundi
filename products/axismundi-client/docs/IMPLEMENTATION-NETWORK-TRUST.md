# Desktop Network Trust and Session Redirects

2026-11-19. Builds on the Contacts client slice; no WordPress server changes.

## TLS

Pinned ureq 3.5.2 enables `platform-verifier` and explicitly configures
`TlsConfig` with `RootCerts::PlatformVerifier`. Both the authenticated Client
and anonymous discovery use it. Public AS2 reads use the same Client configuration
without credentials. Certificate verification is not disabled and no development
accept-all switch exists. The local Windows user trusts the development CA;
the Rust process now uses platform verification instead of only bundled roots.
This run verifies Windows desktop, not Android/iOS platform-verifier setup.

## Redirects

All agents disable automatic redirects. Authenticated GETs follow up to eight
301/302/303/307/308 responses manually, resolving Location against the current URL
and validating each hop before sending it. Authorization is sent only within the
same origin: scheme, hostname and effective port. Different ports, HTTPS downgrade,
different hosts, embedded credentials and fragments are rejected without contacting
the destination. Default-port equivalents remain the same origin. Relative apiUrl
uses the final Session URL as its base.

`Client::without_redirects` remains for write probes. POST is never automatically
redirected, including in the default Client. Anonymous discovery retains its
existing independent redirect checks; it never sends credentials. The GUI now uses
the same-origin GET follower, retaining its separate approval checks on API origins.

## Empty Session Diagnostics

Session retains the server's username. Empty accounts are not authentication
failure: they may describe a legitimate user without accessible accounts. If both
username and accounts are empty, authentication is reported as unconfirmed, not
as absent Contacts support. CLI account selection checks this before choosing an
account; the Session command prints the diagnostic. GUI keeps the Session snapshot
and adds a diagnostic rather than discarding a valid empty Session.

## Local HTTPS Alignment

Use `https://localhost:18881/.well-known/jmap` and a new authentication profile
for the permanent administrator. Caddy accepts localhost with the existing SAN;
Stalwart advertises `https://localhost:18881/jmap/` through STALWART_PUBLIC_URL.
No hosts-file change, custom DNS mapping or certificate-validation bypass is needed.
The previous stopped containers and unchanged config/data mounts remain available
for rollback; never run two Stalwart processes against the same data directory.
Local proxy settings are recorded in D:/axismundi-interop/stalwart/https/.

## Verification

- `cargo test --workspace`: includes same-origin credential retention, no request
  to a cross-port redirect target, URL-policy negatives and both empty-account cases.
- Opt-in Stalwart Contacts test starts at the HTTPS well-known URL using the new
  administrator. It exercises redirects, platform trust, relative Location, advertised
  API, local SQLite snapshot, reopen, external update, queued update, conflict and deletion.
  Only its temporary AddressBook/Card are destroyed.
- GUI library regression tests are run separately from the CLI/core workspace.

Measured final result: workspace tests 22 passed; GUI library tests 5 passed
(its existing real-Axismundi test remains opt-in). The separate Stalwart test
passed with account `b`, Session entry `https://localhost:18881/.well-known/jmap`
and resolved API `https://localhost:18881/jmap/`. The actual Contacts CLI also
returned account `b`, one AddressBook and zero pre-existing ContactCards after
the integration test cleaned up its fixtures. GUI Contacts screens were not tested.

No claim of all-platform TLS validation, GUI Contacts screens or push sync is made.
Reference: ureq 3.5.2's bundled src/lib.rs platform-verifier example and config.rs
RedirectAuthHeaders documentation; SameHost is not used as a same-origin substitute.

## Selective Discovery Follow-up

The GUI subsequently showed all services as unconfirmed. This was reproduced as
HTTP 502 in the CLI, not assumed to be a stale GUI build. Stalwart's management
BlockedIp list identified the host gateway and then the dedicated proxy address
as `portScanning`. Broad discovery was requesting a guessed WordPress path on
a JMAP server. Changing proxy routing alone did not prevent the ban recurring.

The client now exposes selected service probes in core, CLI and GUI. GUI defaults
to JMAP only. Skipped services serialize as `skipped`, not absent/failed services.
WordPress discovery only follows an advertised `https://api.w.org/` link in the
homepage HTML head, parsed with scraper; it no longer guesses a wp-json path.
Anonymous NodeInfo remains separately selectable, not an AP-session prerequisite.
HTTP failures are displayed with their reason instead of just Could not confirm.

Only the two development portScanning entries observed during this investigation
were removed and the blocked-IP list reloaded. No scan-ban setting, global
allowlist or authentication control was disabled. Proxy and server communicate
on the dedicated Docker network axismundi-stalwart-local without a host-port loop.
Tests check that JMAP-only makes no other service requests and a homepage without
the WordPress advertisement causes no guessed API request.

Follow-up verification: workspace tests 24 passed; GUI library tests 5 passed.
Angular production build passed with its initial bundle-size warning (625.89 kB
against a 500 kB warning budget). The running desktop GUI, with only JMAP checked,
found https://localhost:18881/jmap/session and showed Sign in; other services were
Not requested. The server's blocked-IP list remained empty after this GUI run.
