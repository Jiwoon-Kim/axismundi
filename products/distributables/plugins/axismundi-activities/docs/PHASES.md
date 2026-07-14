# Implementation phases

## 0.0.1 — Phase 0: contract and scaffold (shipped)

Ownership, URI references, immutable Activity lifecycle, relation state, media no-Create,
lease ownership, prefix tenancy, and package boundaries. No runtime state.

## 0.0.2 — Phase 1: Activity repository

Create `wp_ax_activities` with verified DB-version gate; immutable value object; bounded,
idempotent `record_activity()`; URI/actor/object lookup; valid Undo effectiveness; post-commit
`axismundi_act_activity_recorded`. Network requests remain forbidden.

## 0.0.3 — Phase 2: Follow, Accept, Reject, Block

Create `wp_ax_activity_relations`; Follow pending state, Accept/Reject transition, Undo Follow,
directional Block, and followers/following projections. Tests use local payload fixtures only.

## Prerequisite — Object Projections lease substrate

Before Phase 3, Object Projections ships multi-reason object leases. Activities only consumes
its feature-detected API.

## 0.0.4 — Phase 3: Like, Announce, Undo

URI-referenced reactions, idempotent aggregate queries, Undo transitions, and optional
`interaction` lease declarations for remote objects.

## 0.0.5 — Phase 4: logical inbox/outbox membership

Actor-scoped inbox/outbox memberships and collection query services. Object Projections may
serialize collections; Federation serves HTTP. Notifications are explicitly excluded.

## 0.0.6 — Phase 5: Add, Remove, Move, Join, Leave

Collection/community operations for saved media, shared folders, and managed Group workflows,
plus optional `collection` leases. Domain plugins continue to own collection membership data.

## Later packages

Notifications consumes post-commit Activity events, then Federation adds signed transport,
then PWA/Web Push may add an optional notification delivery channel.
