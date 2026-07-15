# Implementation phases

## 0.0.1 — Compatibility scaffold

Declare dependencies, verify runtime surfaces, preserve official lifecycle ownership, and
lock package/license boundaries. No persistence, route, network request, or transport.

## 0.0.2 — Conflict-safe dormant transport

Suppress official presentation, lifecycle, default domain handlers, and delivery callbacks.
Restore Axismundi content negotiation and lifecycle ownership. Return an explicit 503 from
official Inbox write routes until verified handoff can claim requests without duplicate state.
Document the active owner for every overlapping runtime surface in `OWNERSHIP.md`.

## 0.0.3 — Virtual Actor mapping experiment

Adapt Axismundi local Actors through existing official virtual-Actor and WebFinger filters.
Remain read-only and fail closed for signing/delivery paths that require a WP user id.

## 0.0.4 — Verified Inbox handoff

Consume the supported upstream verified-envelope hook. Claim only Activities whose Actor,
object, and authority checks pass; record through Axismundi Activities and suppress official
domain handlers/persistence exactly once.

## 0.0.5 — External Actor delivery

Submit complete Axismundi payloads, URI-backed signing Actors, and explicit recipient inboxes
through the supported official delivery API. The official plugin remains queue/retry owner.
