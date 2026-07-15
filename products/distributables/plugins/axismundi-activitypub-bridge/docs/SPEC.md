# Axismundi ActivityPub Bridge

> Status: **0.0.1 scaffold. No Inbox claim or outbound delivery is implemented.**

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
5. One post lifecycle has one publisher. Version 0.0.1 leaves ownership with ActivityPub.
6. Upstream patches are written independently under upstream-compatible MIT/GPLv2 terms;
   GPL-3.0-only Axismundi implementation code is never copied into the upstream repository.

