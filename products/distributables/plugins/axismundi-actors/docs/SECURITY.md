# Axismundi Actors — Security

> Status: **Living specification. Pre-implementation.**
> Actors is an identity registry. Its risks are exposure (publishing an identity
> or PII prematurely), impersonation (binding federation identity to a mutable
> handle), and IDOR (reading/mutating another identity).

## 1. Exposure model — record vs publication

Creating an actor record and exposing its public profile are **separate** (SPEC
§2.6). `status` gates exposure:

| status | `/@handle/` & identity URI (public viewer) | usable as membership key |
|---|---|---|
| `internal` | 404 | yes |
| `public` | 200 | yes |
| `disabled` | 404 | no (hidden) |
| `tombstone` | 410/404 | no |

- Activation always seeds the site actor as `internal`, and the site-owner Person
  actor as `internal` **only when the activating user is a valid admin** (skipped on
  CLI). Nothing is world-visible until an admin publishes it.
- Existing users are **never bulk-published**. A user's actor is created lazily and
  stays `internal` until the user (or an admin) opts in.
- Owner and `manage_options` may **preview** their own non-public actor; everyone
  else gets the same 404 as a nonexistent handle (no existence oracle).

## 2. PII

- **Email is never serialized** into any HTML or REST projection, JSON-LD (later),
  or count callback. It is not a field on the actor row.
- Local profile fields are read live from `WP_User`; the plugin exposes only what
  the user's public author profile already exposes (name, bio, URL, avatar).
- `payload_json` is remote-only and must be sanitized/escaped on output like any
  untrusted federation data (relevant only once the federation phase lands).

## 3. Identity integrity

- `identity.uuid` and `canonical_uri` are immutable. No code path updates them
  after creation; a username change touches only `preferred_username` /
  `profile_url` alias.
- Binding federation identity to the mutable `/@handle/` is forbidden — the
  identity URI (`/actors/{uuid}`, plain fallback `/?ax_actor={uuid}`) is the only
  stable id.
- Reserved-handle guard prevents a username from shadowing routing or another
  actor's handle.

## 4. Access control

- **Capabilities:** view public actor = anyone; publish/unpublish or edit an
  actor's identity settings = the linked user for their own Person actor, and
  `manage_options` for the site actor and any actor. v0.1 uses core caps +
  the `ax_actors_site_owner_user_id` option rather than a custom cap set or a
  managers table (both reserved).
- **IDOR:** repository lookups are by `uuid` / `canonical_uri_hash` / `user_id`;
  every mutation re-checks the caller against the target actor. A handle or UUID in
  a request never implies authority over that actor.
- **Nonces / referers** on all admin toggles and any future REST mutation.

## 5. Lifecycle safety

- **User deletion → tombstone**, never cascade-delete the actor (SPEC §2.9), so
  federation back-references and audit history survive.
- **Deactivate / uninstall retains data** — no destructive table drop. A scoped
  reset is a deliberate post-roadmap danger-zone action.
- Reactivation is idempotent: seeds are keyed so no duplicate site/owner actor is
  created, and retained rows keep their UUID / URI / status.
