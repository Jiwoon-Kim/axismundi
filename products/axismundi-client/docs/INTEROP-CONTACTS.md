# Contacts Storage and Stalwart E2E

2026-11-18. Implemented in `core/src/jmap/contacts.rs` (then `core/src/contacts.rs`); the WordPress Contacts server
was not changed. Stalwart runs in `axismundi-jmap-interop` on loopback port 18880.

## Storage Boundary

SQLite through bundled rusqlite lives in Rust, not the web view. This first
implementation uses one explicitly supplied file per durable authentication profile.
The file records the profile ID and binds to the resolved JMAP API URL and login
name; accidental reuse by another service/login is rejected. An endpoint or login
rename currently requires a new cache, not silent rebinding. No secrets are stored.
This is an implementation choice, not a prohibition on future combined views.

Schema version 1:

- `metadata`: profile binding, schema version, last explicitly saved Session JSON.
- `records`: `(account,kind,id)`, received JSON and derived display-name index.
- `sync_state`: `(account,kind)` and opaque state string.
- `pending_updates`: `(account,id)`, base state, PatchObject and pending/accepted status.

JSON values, including unknown members, are preserved semantically; JSON lexical
whitespace/key ordering is not promised. Received documents are never HTML-rendered
here. The cache is unencrypted: suitable for this development slice, not a decision
to ship personal data without an encryption/key-management policy. Logout does not
delete the cache. No general-purpose SQL IPC or AP cache has been introduced.

## Synchronization

Account selection validates the explicit account or advertised Contacts primary;
without one it selects only an unambiguous sole Contacts account. No WP user,
Actor or account ID is guessed. Existing Session code resolves relative `apiUrl`.

AddressBooks initially use `/get` with null ids. ContactCards use paginated `/query`
and chunked `/get`, retaining queryState only to detect changes during that scan;
it is not used as object state. Requests obey advertised Core limits, one call per
request, with a local Get chunk ceiling of 500. The server limit always wins if
smaller. AddressBook/get may report tooManyObjects; there is no invented
AddressBook/query fallback.

Incremental sync drains `/changes` pages, folds created/updated/destroyed IDs, then
gets surviving changed objects in chunks. All retrieved chunks must have the final
changes state. Concurrent changes, incomplete gets and malformed responses cause
failure without advancing the checkpoint. Retry is explicit; no infinite retry loop.
`cannotCalculateChanges` stages a complete replacement before committing. It never
clears the existing cache before network work succeeds.

Data and checkpoint commit in one SQLite transaction, guarded by comparing the
previous checkpoint again inside the write transaction. Pending edits are separate
and survive replacement, conflicts and server deletion. Updates submit with
`ifInState`; rejected writes remain pending. Successful writes are marked accepted,
then synchronized before removing the queue row. No background retry scheduler,
merge UI, create/delete queue or push connection is implemented yet.

## Repeatable Verification

```powershell
cargo test --workspace
# Explicit opt-in: supply loopback AXJ_SESSION_URL, AXJ_USER, AXJ_SECRET in the environment.
cargo test -p axismundi-client-core --test contacts_stalwart -- --ignored --nocapture
```

The Stalwart test creates its own marked AddressBook and ContactCard. It verifies
initial sync, file reopen/offline reads, external update delta, queued local update,
stale-write rejection retaining the edit, and remote deletion. It destroys only its
own AddressBook with onDestroyRemoveContents, and removes its temporary SQLite files.
No existing AddressBook/Card is edited. The cache can read existing contacts under
the supplied test login, so use a development account, not personal credentials.

Mock tests independently cover a maxObjectsInGet of 2, multiple changes pages,
incomplete fetch rollback, expired-state full rebuild and pending-edit preservation.
The ignored real-server test is not silently counted by ordinary cargo test.

## Input to the WordPress Refactor

Use these same method contracts and tests against the future server implementation:
AddressBook/get/set, ContactCard/query/get/changes/set, object state versus queryState,
partial Set results, ifInState rejection, full-refetch fallback and unknown JSON
members. Passing Stalwart E2E is client evidence, not evidence that the existing
WordPress Contacts plugin already satisfies these methods.

Protocol reference: [RFC 9610](https://www.rfc-editor.org/rfc/rfc9610.html).
Clippy was not available in the installed Rust toolchain during this run; it was
not counted as a passing check. GUI integration/build is outside this slice.
