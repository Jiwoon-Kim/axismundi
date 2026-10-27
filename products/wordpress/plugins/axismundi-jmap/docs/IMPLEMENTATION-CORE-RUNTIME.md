# Core Runtime and Account Policy

Implemented 2026-10-28. In-process backend only, not a public RFC-complete server.

## Ownership

- Actors owns local/remote Actor identities, lifecycle and operating principal grants.
- A local Account uses `a<Actor id>` and the same lifecycle. Remote Actors have no local Account configuration.
- JMAP owns Session assembly, registered methods, Account administrative entitlements and limits.
- WordPress selection UI and signed session-bound cookies belong to Actors. Explicit JMAP `accountId` never falls back to a cookie or primary Account.
- Contacts/AddressBook, profile cards, Note storage and ActivityPub behavior are unchanged by this slice.

2026-10-28 소유권 정정: AddressBook·ContactCard DB와 RFC 9610 메서드/provider 등록은
Contacts가 소유한다. JMAP 코어로 DB를 이전하지 않는다. 독립 Profile Card는 주소록의
ContactCard와 구분하며, Contacts에 Account별 Profile 저장소를 두는 안은 아직 후보이다.
[결정과 미정 항목](REVIEW-DEPENDENCY-BOUNDARIES.md#2026-10-28-결정-정정)을 참고한다.

## Storage

`ax_jmap_accounts(actor_id, revision, updated_at)` has an Actor foreign key; no independent UUID or status.
`ax_jmap_account_capabilities(account_id, capability_uri, enabled, limits_json, updated_by, updated_at)`
stores administrative decisions. URI keys are ASCII and case-sensitive. Physical Actor deletion cascades;
tombstoning retains configuration but denies access. Activation backfills local Actors, including those
created while JMAP was inactive; the post-commit Actor creation hook provisions new configurations.

`set_account_policy()` requires the authenticated administrator's `manage_options`, validates the
registered policy schema, locks the current Actor and Account revision and supports optimistic CAS.
Assignment does not grant the administrator Actor access, and an Actor owner cannot self-assign entitlements.
Missing policies deny capability availability. Core is server-level, not an Account entitlement.

## Shared PHP Service

Providers register via `axismundi_jmap_register` during plugin bootstrap. The runtime assembles Core,
invokes registration once after plugin loading, then freezes the registry. Future Contacts providers must
register their actual methods and policy validator before advertising support; no production data provider
is registered in this slice. Synthetic test providers are not product capabilities.

`server()->accounts()`, `session($transport)`, `authorize($accountId,$capability,$writes)` and
`execute($json,$transport)` share the same service. WordPress does not call itself through HTTP.
Authorization re-resolves Actor access and policy on every method, including between calls in one batch.
A writable capability cannot authorize writes through another read-only capability.

`check_account_limit()` is the shared enforcement helper. Resource backends must call it while holding
their own transaction/lock and supplying their authoritative current usage; it is not a usage counter or
an atomic resource reservation. No real Contacts/Calendar/upload writers are claimed to enforce quotas yet.
Limit enforcement only takes effect when provider-backed resource writers call `check_account_limit()`.
There are currently zero production providers and zero production callers; enforcement has only been
verified through synthetic audit consumers. Registering or advertising a capability alone is not enforcement.

## Protocol Limits and Remaining Transport

`CoreConfiguration` supplies the same size/call/get/set limits that the Engine enforces. Unexpected
handler exceptions become sanitized `serverFail` responses; logs contain the exception class, not payloads.
Session state is a hash of effective Session content, not a data-type `/changes` journal.

Session construction still requires an explicit validated transport URL contract. Tests use synthetic
URLs; the plugin exposes no discovery, HTTP API, upload/download or SSE endpoints. Authentication,
HTTP admission/concurrency control, duplicate JSON member validation and Blob/Push transport remain
release requirements. Core upload defaults are zero; configuration values do not create an upload service.
Do not publish these in-process Sessions as a complete public server before the transport contract exists.

## Browser Adapter

The Actors-owned adapter preserves existing `axismundi-jmap-accounts` URLs, POST action, nonce and CSS
selectors for compatibility. Its list contains Actor display names, not fabricated JMAP rights. JMAP's old
PHP entry points forward to Actors. `ax_actors_account_selection_enabled` defaults to false and can be
explicitly enabled without an MU plugin; existing rollout filters still apply. No environment option was
enabled by this implementation, and WP User Gravatar synchronization was not changed here.

## Verification

Development audits cover the pure Engine, Session projection, Account access, durable policy/CAS,
per-capability write rights, shared limits, same-batch revocation and fixture row preservation.
The browser adapter audit also runs with `--skip-plugins=axismundi-jmap`; immediate and scheduled
publication audits remain independent of the environment's rollout setting. See the completion report
for actual counts and mutation results; these tests are not a public transport certification.

Observed in wp-env: Engine 71, runtime 34, Session 50, Account access 23, Actor browser adapter 47,
immediate publication 27 and scheduled publication 41 checks passed. The 47 adapter checks also passed
with JMAP skipped. Four in-memory mutants were caught at their intended assertions: bypassed policy,
bypassed per-capability writable rights, bypassed resource limit and cached authorization after revocation.
`audit-runtime-fixtures.php` ran five focused audits twice (10/10), preserving complete row contents in
Actor, principal, Actor detail, JMAP configuration/policy and OP Object tables. A stale `internal` fixture
transition and the scheduled audit's independent OP Object cleanup were repaired during verification.
