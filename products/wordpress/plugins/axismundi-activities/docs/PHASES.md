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

## 0.0.9 — Phase 2.6: cached remote Actor Follow controls (shipped)

Add transport-neutral Follow and Undo state transitions for cached remote Actors. Public
cached profiles and the Actors administrator detail screen share one nonce-protected control.
Outbound Follow, Undo, Accept, and Reject records carry an explicit remote audience; Activities
still performs no HTTP and a transport adapter decides whether and how to deliver them. Imported
legacy relationships remain read-only when no original Follow Activity URI exists.

## 0.0.10 — Phase 2.7: inbound remote Follow acceptance (shipped)

Auto-accept a verified inbound remote Follow when the local Actor does not require approval.
Record one outbound Accept addressed to the remote Actor, derive transport direction from the
committed relation, and let real Activity evidence supersede imported legacy snapshots.

## 0.0.11 — Phase 2.8: relationship recovery and controls (shipped)

Display remote relations as `@handle@instance`, expose Follow back and Activity-backed
follower removal, and let imported outbound snapshots be replaced by an explicit Re-follow.
A distinct newly received Follow URI starts the latest relationship cycle even when the
previous cycle was accepted, so automatic approval targets the exact incoming Activity.

## Prerequisite — Object Projections lease substrate (shipped)

Before Phase 3, Object Projections ships multi-reason object leases. Activities only consumes
its feature-detected API.

## 0.0.12 — Phase 3a: Like and Undo (shipped)

URI-referenced Like state, idempotent distinct-Actor aggregate queries, Undo transitions,
nonce-protected Interactivity API block, and feature-detected `interaction` leases for remote
objects. Object Projections publishes a count-only Object `likes` collection without liker
enumeration. The internal Actor
`liked` query ships, but no public `liked` route is advertised until its privacy policy is set.

## 0.0.13 — Phase 3b: Announce and Undo (shipped)

URI-referenced personal Announce state and matching Undo transitions, a nonce-protected Boost
block, fail-closed Object Projections visibility decisions, interaction leases, and a
count-only public Object `shares` collection. Axismundi keeps `shares` as an
`OrderedCollection` for consistency with its existing `likes` representation; Mastodon uses
an unordered `Collection`, and ActivityPub permits either. Group fan-out remains Phase 5 work.

## 0.0.14–0.0.18 — Phase 3c: FEP-044f Quote consent (shipped through local revocation)

Store the general AS2 `instrument` member, issue immutable QuoteAuthorization identities,
derive a count-only accepted-follower query, and process committed inbound QuoteRequest
Activities against the quoted Post's explicit policy. Accepted requests produce one addressed
Accept with the authorization URI in `result`; denied requests produce one Reject. Replays and
later policy changes preserve the first decision. Authorization representation, revocation
of locally issued stamps, and delivery to the remote quote author are shipped. Forwarding a
remote revocation through a locally owned quote's audience requires the inbound authorization
relation index planned with observed quote indexing; that later increment must not guess from
a privacy-minimal Delete that deliberately embeds neither Object.

## Future — Phase 4: logical inbox/outbox membership

Actor-scoped inbox/outbox memberships and collection query services. Object Projections may
serialize collections; Federation serves HTTP. Notifications are explicitly excluded.

## Future — Phase 5: Add, Remove, Move, Join, Leave

Collection/community operations for saved media, shared folders, and managed Group workflows,
plus optional `collection` leases. Domain plugins continue to own collection membership data.

## Later packages

Notifications consumes post-commit Activity events, then Federation adds signed transport,
then PWA/Web Push may add an optional notification delivery channel.
