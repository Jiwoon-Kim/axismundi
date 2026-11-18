# Stored Actor Scope Retirement

2026-11-18. Completes the physical cleanup described in
[the brief](BRIEF-REMOVE-ACTOR-SCOPE.md), not a new authorization model.

`actor_scope` and `scope_type` are absent from new schemas and removed from
upgraded schemas. `scope-retirement.php` runs at init priority 60 under a database
advisory lock, after Account access readiness and legacy-system retirement receipts.
The separate `ax_actors_scope_retired=1` receipt prevents routine repeat DDL.
The migration does not reclassify, delete or modify Actor rows, tombstones,
unclassified Actors, personal attribution, UUIDs, URIs or roles.

Creation rejects the retired argument. Personal attribution must be explicit;
`ensure_for_user()` supplies it. Fixture writers follow the same rule. Historical
installation fixtures retain the old column to describe old schemas. `get_scope()`
and `is_managed()` remain derived compatibility adapters, never stored authority.
Inventory and legacy-system retirement no longer read stored scope.

Upgrades require the prior Account attribution migration and legacy-system
retirement to have completed. An older populated installation without the Account
receipt fails closed: run the preceding release's migration first. This release
does not infer personal ownership from legacy `local_user_id` or scope. A database
backup was made before applying the local development migration.

The isolated schema audit checks a scope-free table, old-column upgrade,
byte-identical remaining rows and replay. This is not a full fresh WordPress
installation test. Existing audit fixture Actors are preserved, not cleaned up.

## Actor Directory

`GET /wp-json/axismundi/activitypub/actors` lists public local Actors by default.
`type=Person,Group,Organization,Service,Application` accepts exact names;
`origin=remote` explicitly selects cached public remote Actors without fetching.
The system Actor is included, just like any other public Actor. Directory presence
does not grant Account access, session membership or outbox operating authority.

Responses are `OrderedCollection` with `url`, `orderedItems`, no top-level `id`
and optional cursor `next`. Up to 20 candidates are scanned per page, descending
registry ID; unavailable documents are skipped and continuation advances over
scanned candidates. Remote documents retain their cached representation.
The authenticated `ids` lookup is unchanged and cannot be combined with directory
filters. `scope` is not a filter. No total count is computed.

The development query `origin=local AND status=public AND actor_type IN ('Person')
ORDER BY id DESC LIMIT 21` uses `origin_status` (`range`); no speculative index
was added. Future installations should assess their actual query plans.

## Verification

- Scope retirement: 8 checks, including isolated upgrade and replay.
- Actor directory: 33 checks, including cursor traversal, system inclusion,
  remote opt-in, invalid types and no network requests.
- System Actor: 48 checks; JMAP Session: 51; vocabulary: 33;
  explicit local reads: 46; Account ownership: 29.
- PHP lint passed for all 43 changed/new PHP files; `git diff --check` passed.
- Anonymous HTTP: Person/Application return 200, lowercase person returns 400,
  explicit remote Application returns 200. The local Application response includes
  the bound system Actor, named Axismundi Workspace in this installation.

No full fresh-site install or every cross-plugin audit was run. Tests establish
current invariants, not byte-identical pre/post HTTP responses captured in advance.
