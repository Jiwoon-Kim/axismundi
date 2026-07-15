# Axismundi ActivityPub Bridge

> Status: **0.0.2 conflict-safe dormant transport. No Inbox claim or outbound delivery is implemented.**

## Purpose

Bridge Axismundi's authoritative URI-keyed Actor, Object, Activity, and relationship
repositories to supported network-facing extension points in the official ActivityPub plugin.

## Ownership

- **Axismundi Actors** owns Actor identities and local profile presentation.
- **Object Projections** owns local JSON-LD representations and remote object observations.
- **Activities** owns the immutable Activity ledger and local social relationship state.
- **The official ActivityPub plugin** owns HTTP signatures, verified Inbox parsing, shared
  inbox delivery, queues, retry, and interoperability.
- **This bridge** translates between those APIs and owns no authoritative domain table.

## Invariants

1. This is the only Axismundi package that requires the official ActivityPub plugin.
2. Core Axismundi packages continue to function when this bridge and ActivityPub are absent.
3. No verified Inbox Activity is claimed until upstream provides a supported handoff that
   suppresses both default handlers and default domain persistence.
4. No outbound Activity is submitted until upstream accepts a complete payload, a URI-backed
   signing Actor, and explicit recipient inboxes through a supported API.
5. One post lifecycle has one publisher. Axismundi owns local lifecycle records while the
   official scheduler is dormant.
6. Upstream patches are written independently under upstream-compatible MIT/GPLv2 terms;
   GPL-3.0-only Axismundi implementation code is never copied into the upstream repository.
7. Until verified Inbox handoff exists, inbound writes fail with 503 rather than being
   silently discarded or persisted by both systems.

## Dormant transport mode

The bridge suppresses the official Router, Scheduler, Handler, and Dispatcher initializers.
Axismundi Actors owns profiles, WebFinger, and NodeInfo; Object Projections owns content
negotiation; Activities owns local lifecycle records. Official signature and REST server code
remains installed but does not mutate domain state or deliver Activities.

This mode removes callbacks only. It never deletes official CPT rows, options, cron events, or
queues. A later transport phase may re-enable the minimum required callbacks after the upstream
handoff and external-sender APIs are available.

Rewrite rules are rebuilt once after the ownership change so cached official routes cannot keep
winning. Deactivation deletes only the rewrite cache, allowing the official plugin to rebuild its
routes on the next request.
