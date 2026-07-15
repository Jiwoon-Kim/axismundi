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
