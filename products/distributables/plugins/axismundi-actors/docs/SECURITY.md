# Axismundi Actors — Security

> Status: **Living specification. Phase 2 profile exposure gate implemented.**
> Actors is an identity registry. Its risks are exposure (publishing an identity
> or PII prematurely), impersonation (binding federation identity to a mutable
> handle), and IDOR (reading/mutating another identity).

## 1. Exposure model — record vs publication

Creating an actor record and exposing its public profile are **separate** (SPEC
§2.6). `status` gates exposure:

| status | `/@handle/` & identity URI (public viewer) | usable as membership key |
|---|---|---|
| `internal` | 404 | yes |
| `public` **+ registered handle** | 200 | yes |
| `public` **without a handle** | 404 (hidden) | yes |
| `disabled` | 404 | no (hidden) |
| `tombstone` | 404 (410 is reserved for the federation phase) | no |

- Public exposure requires **both** `status = public` **and** a registered, locked
  handle (`preferred_username` + `handle_locked_at` set). A handle-less `public`
  actor is still 404 to anonymous viewers — it cannot leak before activation.
- **Actor activation is opt-in and separate from WordPress account creation.** The
  WordPress *"Membership: Anyone can register"* setting only governs WP accounts;
  creating a WP account never mints a public actor. A user's actor stays handle-less
  and `internal` until they explicitly activate it (register a handle, then publish).
- Activation always seeds the site actor as `internal`, and the site-owner Person
  actor as `internal` **only when the activating user is a valid admin** (skipped on
  CLI). Nothing is world-visible until it is published.
- Existing users are **never bulk-published**. A user's actor is created lazily and
  stays `internal` until the user (or an admin) opts in.
- Owner and `manage_options` may **preview** their own non-public actor; everyone
  else gets the same 404 as a nonexistent handle (no existence oracle).
- **`status` is lifecycle, not a privacy dial — do not add a `followers` status.**
  A federated *locked* account is still publicly fetchable as an Actor document, so
  "followers-only" is never an actor status. Reach/privacy are **separate axes**
  (DATA-MODEL §9.3), each its own field: **follow approval**
  (`manually_approves_followers` — the Mastodon lock), **discovery** (`discoverable`),
  **search indexing** (`indexable`), and **followers/following list visibility**
  (`follow_collections_visibility`). The **default posting audience**
  (`public|unlisted|followers|mentioned`) is a per-activity policy owned by the
  Activity plugin, not an actor field. Collapsing these into one switch is a bug.

## 2. PII

- **Email is never serialized** into any HTML or REST projection, JSON-LD (later),
  or count callback. It is not a field on the actor row.
- Local profile fields are read live from `WP_User`; the plugin exposes only what
  the user's public author profile already exposes (name, bio, URL, avatar).
- `payload_json` is remote-only and must be sanitized/escaped on output like any
  untrusted federation data (relevant only once the federation phase lands).

## 3. Identity integrity

- `identity.uuid` and `canonical_uri` are immutable. No code path updates them
  after creation.
- **The local handle is immutable once registered.** `register_handle()` sets it a
  single time and stamps `handle_locked_at`; there is no public rename path. An
  exceptional change is a future **admin recovery tool + alias-history/`Move`** flow
  (DATA-MODEL §7), not a normal-UI feature — so a compromised or careless rename
  cannot silently hijack a `/@handle/` that others already reference.
- **A retired handle is reserved to its actor, never recycled.** After a user is
  deleted, `@alice` stays reserved to the tombstoned Person actor; a *new* user does
  not get to claim it (that would mis-attribute old posts and read as account
  takeover to remote servers). A returning person is handled as **tombstone-identity
  recovery** — an admin re-links the tombstoned actor to a new `WP_User` — while a
  genuinely different person receives a distinct handle (`alice-2`). The Actor handle
  is also independent of `user_nicename` / the author archive URL (ROUTING §0.1), so
  neither can silently reassign the other.
- Binding federation identity to the mutable-looking `/@handle/` is forbidden — the
  identity URI (`/actors/{uuid}`, plain fallback `/?ax_actor={uuid}`) is the only
  stable id.
- Reserved-handle guard prevents a handle from shadowing routing or another actor.

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
