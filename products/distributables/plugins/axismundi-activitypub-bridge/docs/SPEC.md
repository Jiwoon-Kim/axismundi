# Axismundi ActivityPub Bridge

> Status: **0.0.16 existing Inbox action composition, Bridge-owned delivery spool, and
> provenance-aware legacy import implemented.**

## Purpose

Bridge Axismundi's authoritative URI-keyed Actor, Object, Activity, and relationship
repositories to supported network-facing extension points in the official ActivityPub plugin.

## Ownership

- **Axismundi Actors** owns Actor identities and local profile presentation.
- **Object Projections** owns Actor, object, and public collection JSON-LD representations,
  including the Actor Outbox GET route.
- **Activities** owns the immutable Activity ledger and local social relationship state.
- **This bridge** owns verified Inbox action composition, transport endpoint declarations,
  recipient selection, its private delivery spool, retry, and HTTP dispatch.
- **The official ActivityPub plugin** owns HTTP signature implementation, verified Inbox parsing,
  and protocol interoperability.
- **This bridge** translates between those APIs and owns no authoritative domain table.

## Invariants

1. This is the only Axismundi package that requires the official ActivityPub plugin.
2. Core Axismundi packages continue to function when this bridge and ActivityPub are absent.
3. A verified Inbox Activity is consumed from the controller-owned `activitypub_inbox` or
   `activitypub_inbox_shared` action after permission validation. Official type handlers remain
   dormant and `activitypub_skip_inbox_storage` prevents duplicate CPT persistence only after
   Axismundi has successfully claimed the Activity.
4. Outbound delivery stores a complete payload, URI-backed signing Actor descriptor, and explicit
   recipient inboxes in `{prefix}ax_ap_deliveries`. `ap_outbox` and the Posts state machine are never reused.
5. One post lifecycle has one publisher. Axismundi owns local lifecycle records while the
   official scheduler is dormant.
6. Upstream patches are written independently under upstream-compatible MIT/GPLv2 terms;
   GPL-3.0-only Axismundi implementation code is never copied into the upstream repository.
7. Shared Inbox delivery is recorded once from `activitypub_inbox_shared`; its deprecated
   per-recipient `activitypub_inbox` callbacks are ignored to prevent duplicate Activities.
8. Legacy migration is Bridge-owned and repository-driven. Scan, import, and purge remain
   separate operations. Import performs no fetch or source deletion; relation snapshots carry
   explicit provenance and can never override Activity-derived state.

## Runtime composition

The bridge uses the official `activitypub_register_handlers` and
`activitypub_register_schedulers` seams to remove only callbacks whose domain state Axismundi
owns. Handler, Scheduler, and Dispatcher initializers remain active. The overlapping Router has
no equivalent registration seam, so its initializer alone is disabled while Object Projections
owns public Actor and Object routes.
Axismundi Actors owns profiles, WebFinger, and NodeInfo; Object Projections owns Actor/content
negotiation; Activities owns local lifecycle records. Official signature and REST server code
verifies Inbox requests before firing its existing Inbox actions. The bridge resolves local
recipients and remote Actors, records the full Activity through Activities, and skips official
Inbox CPT storage. Unclaimed Activities retain the official Inbox snapshot as a recovery path.

The official request-signing filter signs Bridge-owned `wp_safe_remote_post()` requests carrying
transient `key_id` and `private_key` arguments. Private keys are never persisted by Bridge. The
delivery table has one status field, a unique Activity URI hash, and an atomic conditional-update
worker claim. A one-time migration copies experimental fork and provisional Bridge CPT jobs into
the table, links and preserves the source rows, clears old worker events, and removes pending fork
rows from the official Outbox scheduler's scan.

Rewrite rules are rebuilt once after the ownership change so cached official routes cannot keep
winning. Deactivation deletes only the rewrite cache, allowing the official plugin to rebuild its
routes on the next request.
