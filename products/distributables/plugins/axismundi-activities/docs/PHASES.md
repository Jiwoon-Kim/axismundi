# Implementation phases

## 0.0.1 — Phase 0: contract and scaffold (shipped)

Ownership, URI references, immutable Activity lifecycle, relation state, media no-Create,
lease ownership, prefix tenancy, and package boundaries. No runtime state.

## 0.0.2 — Phase 1: Activity repository (shipped)

`wp_ax_activities` with verified DB-version gate; immutable value object; bounded,
idempotent `axismundi_act_record_activity()`; URI/actor/object lookup; valid Undo
effectiveness; post-commit `axismundi_act_activity_recorded`. Network requests remain forbidden.

## 0.0.3 — Phase 2: Follow, Accept, Reject, Block (shipped)

Create `wp_ax_activity_relations`; Follow pending state, Accept/Reject transition, Undo Follow,
directional Block, followers/following projections, out-of-order transition reconciliation,
and a read-only `Tools > Activity Log` inspector. Tests use stored local/remote Actor fixtures
and perform no HTTP.

## 0.0.4 — Phase 2.1: local Follow product surface (shipped)

Local public Person-to-Person Follow/Unfollow controls, automatic or approval-required
acceptance from the Actors-owned policy axis, pending request decisions, and self-service
followers/following lists. Remote Actors are rejected and no transport is attempted.

## 0.0.5 — Phase 2.2: contributor access and Users integration (shipped)

Require `edit_posts` for Actor social actions, keep Subscribers read-only, and expose
nonce-protected local Follow state/actions on the administrator Users table. Cached remote
Actors remain display-only pending an official ActivityPub transport adapter. The Follows
screen also lists pending outbound requests with cancellation controls.

## 0.0.6 — Phase 2.3: Core Post Create lifecycle (shipped)

Consume Object Projections post-commit publish candidates and record one outbound Create
per object lifecycle generation. DB v3 adds a verified source-event identity for retry and
concurrency idempotency. Draft/password/media writes remain silent; no HTTP or delivery.
Reply is deferred until the Notes CPT defines canonical local Note objects.

## 0.0.7 — Phase 2.4: public Outbox query contract (shipped)

Expose public-safe effective outbound Activity payloads by Actor URI. Preserve the lossless
ledger while stripping `bto`/`bcc` from projection copies and recognizing both the full Public
IRI and `as:Public`. Activities owns no HTTP route; Object Projections owns serialization.

## 0.0.8 — Phase 2.5: legacy relation provenance (shipped)

DB v4 adds `legacy_snapshot` evidence for accepted followers/following and non-delivering
`legacy_pending` outbound requests. Compatibility imports create no synthetic Activity.
Actual Follow/Accept/Reject/Undo replay always takes ownership from snapshot evidence.

## Prerequisite — Object Projections lease substrate

Before Phase 3, Object Projections ships multi-reason object leases. Activities only consumes
its feature-detected API.

## 0.0.9 — Phase 3: Like, Announce, Undo

URI-referenced reactions, idempotent aggregate queries, Undo transitions, and optional
`interaction` lease declarations for remote objects.

## 0.0.10 — Phase 4: logical inbox/outbox membership

Actor-scoped inbox/outbox memberships and collection query services. Object Projections may
serialize collections; Federation serves HTTP. Notifications are explicitly excluded.

## 0.0.11 — Phase 5: Add, Remove, Move, Join, Leave

Collection/community operations for saved media, shared folders, and managed Group workflows,
plus optional `collection` leases. Domain plugins continue to own collection membership data.

## Later packages

Notifications consumes post-commit Activity events, then Federation adds signed transport,
then PWA/Web Push may add an optional notification delivery channel.
