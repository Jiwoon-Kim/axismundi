# Axismundi ActivityPub Bridge

> Status: **0.0.6 verified Inbox handoff, Actor transport fields, and
> official external delivery spool integration implemented.**

## Purpose

Bridge Axismundi's authoritative URI-keyed Actor, Object, Activity, and relationship
repositories to supported network-facing extension points in the official ActivityPub plugin.

## Ownership

- **Axismundi Actors** owns Actor identities and local profile presentation.
- **Object Projections** owns Actor, object, and public collection JSON-LD representations,
  including the Actor Outbox GET route.
- **Activities** owns the immutable Activity ledger and local social relationship state.
- **This bridge** owns verified Inbox handoff, transport endpoint declarations, signing
  identity mapping, and outbound queue handoff.
- **The official ActivityPub plugin** owns HTTP signatures, verified Inbox parsing, shared
  inbox delivery, queues, retry, and interoperability.
- **This bridge** translates between those APIs and owns no authoritative domain table.

## Invariants

1. This is the only Axismundi package that requires the official ActivityPub plugin.
2. Core Axismundi packages continue to function when this bridge and ActivityPub are absent.
3. A verified Inbox Activity is claimed only through the upstream handoff after signature
   validation and before default handlers or persistence.
4. Outbound delivery submits a complete payload, URI-backed signing Actor descriptor, and
   explicit recipient inboxes. The official spool never becomes an authoritative Activity row.
5. One post lifecycle has one publisher. Axismundi owns local lifecycle records while the
   official scheduler is dormant.
6. Upstream patches are written independently under upstream-compatible MIT/GPLv2 terms;
   GPL-3.0-only Axismundi implementation code is never copied into the upstream repository.
7. Upstream versions without the verified Inbox handoff fail inbound writes with 503 before
   signature lookup rather than silently discarding or duplicating state.

## Dormant transport mode

On the patched official plugin, the bridge uses `activitypub_module_enabled` to retain only
Signature, the external-delivery worker, REST Server, and the two Inbox controllers. Stock versions fall back to suppressing
the official Router, Scheduler, Handler, and Dispatcher initializers.
Axismundi Actors owns profiles, WebFinger, and NodeInfo; Object Projections owns Actor/content
negotiation; Activities owns local lifecycle records. Official signature and REST server code
verifies Inbox requests without mutating domain state. The bridge
resolves local recipients and remote Actors, then records the full Activity through Activities.

This mode removes callbacks only. It never deletes official CPT rows, options, cron events, or
queues. A later transport phase may re-enable the minimum required callbacks after an
external-sender API is available.

Rewrite rules are rebuilt once after the ownership change so cached official routes cannot keep
winning. Deactivation deletes only the rewrite cache, allowing the official plugin to rebuild its
routes on the next request.
