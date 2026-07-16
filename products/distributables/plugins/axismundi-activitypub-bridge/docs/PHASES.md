# Implementation phases

## 0.0.1 — Compatibility scaffold

Declare dependencies, verify runtime surfaces, preserve official lifecycle ownership, and
lock package/license boundaries. No persistence, route, network request, or transport.

## 0.0.2 — Conflict-safe dormant transport

Suppress official presentation, lifecycle, default domain handlers, and delivery callbacks.
Restore Axismundi content negotiation and lifecycle ownership. Return an explicit 503 from
official Inbox write routes until verified handoff can claim requests without duplicate state.
Document the active owner for every overlapping runtime surface in `OWNERSHIP.md`.

## 0.0.3 — Upstream module gate

Use the patched official plugin's `activitypub_module_enabled` filter to retain only Signature,
REST Server, and Inbox routes. Keep callback removal as a stock-version fallback. Move the
dormant Inbox guard before signature lookup so no official remote-Actor cache write occurs.

## 0.0.4 — Verified Inbox handoff

Consume the supported upstream verified-envelope hook. Claim only Activities whose Actor,
object, and authority checks pass; record through Axismundi Activities and suppress official
domain handlers/persistence exactly once.

## 0.0.5 — Actor transport and external delivery

Keep Actor JSON-LD in Object Projections and inject only Inbox, sharedInbox, and publicKey
fields. Submit complete Axismundi payloads, URI-backed signing
Actors, and explicit recipient inboxes through the supported official delivery API. The
official plugin remains queue/retry owner and its spool is never authoritative domain state.

## 0.0.6 — Representation ownership correction

Move the public Actor Outbox collection and GET route to Object Projections. Bridge retains
only surfaces that require the official plugin: verified Inbox handoff, transport endpoints,
signing identity resolution, queue handoff, retry, and HTTP delivery.

## 0.0.7 — Legacy storage scan and dry-run

Inspect official `ap_actor`, `ap_post`, `ap_inbox`, `ap_outbox`, follower snapshots,
`activitypub_status`, profile fields, comments, and signing-key custody. Report import and
purge decisions independently without writes, network requests, payload rendering, import,
or deletion. Keep `ap_actor` runtime-required while official signature verification resolves
public keys through that cache. Import follows in 0.0.8; fail-closed purge follows separately.

## 0.0.8 — Legacy import and verification

Require an explicit administrator confirmation and a complete bounded preflight. Import remote
Actors, remote Objects, and verified Inbox history in dependency order through their public
repository APIs, then read each identity back immediately. Preserve existing Axismundi caches,
derive Follow state only by replaying Inbox Activities, and keep every official source row.
Network requests, key copying, follower-snapshot writes, lifecycle synthesis, and purge remain
disabled. The operation is retryable and URI-keyed.

## 0.0.9 — Legacy follower/following provenance

After Inbox replay, import accepted inbound followers, accepted outbound following, and
pending outbound following from official Actor postmeta. Use Activities DB v4 provenance,
create no synthetic Activity, never retransmit imported pending state, and let real Activities
take precedence. Purge remains fail-closed.

## 0.0.10 — Official WebFinger interoperability

Supply public Axismundi Actor descriptors through the official plugin's `webfinger_data`
surface after its pseudo-user resolver runs. Add the canonical ActivityStreams Actor `self`
link while leaving every non-Axismundi resource untouched. Destructive purge remains deferred.

## 0.0.11 — WebFinger route gate correction

Keep the official `rest.webfinger` module enabled alongside the verified Inbox transport
surface. This makes the 0.0.10 descriptor adapter reachable regardless of which compatible
well-known rewrite currently wins. Destructive purge remains deferred.

## 0.0.12 — Existing Inbox action composition

Replace the provisional verified-envelope handoff and temporary 503 guard with the official
controllers' existing `activitypub_inbox` and `activitypub_inbox_shared` actions. Keep type
handlers dormant, skip official Inbox CPT persistence only for successfully claimed Activities,
record shared delivery once, and preserve URI-keyed idempotency in Activities. Unclaimed
Activities retain the official snapshot fallback. Destructive purge remains deferred.

## 0.0.13 — Inbox observability and Mention target supplement

Record a bounded administrator diagnostic ring containing only UTC time, route, Activity type,
Activity URI hash, outcome, and error code. Never copy payload content or recipients. Permit a
verified shared Inbox delivery to use `object.tag[].type=Mention` href/id as a supplemental local
Actor target when Activity/Object audience fields omit it. Every unclaimed result still falls back
to official Inbox snapshot storage. Legacy purge remains disabled until old local Actor documents,
`alsoKnownAs`, `movedTo`, and Move delivery have an explicit migration contract.

## 0.0.14 — Outbound Activity JSON-LD finalization

Pass every outbound ledger payload through Object Projections' Activity finalizer before
queueing it with the official transport. The ledger remains representation-neutral while the
wire payload receives the canonical ActivityStreams `@context` required by remote processors.

## 0.0.15 — Remote Actor cache maintenance at the verified Inbox boundary

Queue a first-time NodeInfo fill when verified traffic references a cached remote Actor whose
host ledger is absent. Ordinary Follow/Accept traffic does not refresh Actor snapshots. A
complete `Update(Actor)` may refresh an existing canonical Actor only when Activity.actor equals
object.id and Actors' normal discovery validator accepts the whole object. Unknown or partial
Actor Updates remain unclaimed and cannot create or erase cached identity state.
