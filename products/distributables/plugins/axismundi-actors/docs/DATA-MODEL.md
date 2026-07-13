# Axismundi Actors — Data Model

> Status: **Living specification. Phase 1 schema implemented.**
> Two tables: a shared **identity registry** and the **actor profile**. Local
> person profile fields are read live from `WP_User`; only remote actors snapshot.

## 1. Conventions

- Table prefix family: **`wp_ax_*`** (shared with other Axismundi plugins that
  reuse the identity registry). Option prefix: **`ax_actors_*`**.
- **Ownership:** Axismundi Actors owns and creates `wp_ax_identities` /
  `wp_ax_actors`. Other plugins (Media Library collections/folders, later
  Activities) reuse the identity layer **only through the repository API**, never
  direct SQL — so the registry can later be extracted into a shared `axismundi-core`
  without touching consumers. Consumers depend on Actors being active.
- Long URIs are never uniquely indexed directly (utf8mb4 index-length limit).
  Follow the Media Library relation lesson: store the URI as text **and** a
  `*_hash` = `CHAR(64)` ascii sha-256 hex that is `NOT NULL UNIQUE`. This avoids
  the nullable-UNIQUE pitfall.
- Distinct identifiers, never conflated (SPEC §2.4):

```
local_user_id   WP_User account key            (login; may be absent)
identity.id     local DB key (= actors PK)     (internal; never exposed; changes on re-import)
identity.uuid   immutable UUID                  (the only stable anchor; survives domain move)
actor_uri       identity.canonical_uri         (local: {home}/actors/{uuid}; remote: source URI)
profile_url     /@{preferred_username}/        (human alias; mutable)
```

## 2. `wp_ax_identities` — the identity registry

One row per identifiable object (actor now; collection/folder/media/activity
later). It answers "what UUID and canonical URI is this, and is it local, public,
alive?" — nothing domain-specific.

```
id                BIGINT UNSIGNED  PK AUTO_INCREMENT
uuid              CHAR(36)         NOT NULL   -- UUIDv4 (wp_generate_uuid4), canonical hyphenated form
canonical_uri     TEXT             NOT NULL   -- local: {home_url}/actors/{uuid}; remote: source URI
canonical_uri_hash CHAR(64)        NOT NULL   -- sha256(canonical_uri), ascii
object_kind       VARCHAR(20)      NOT NULL   -- actor | collection | folder | media | activity
origin            VARCHAR(10)      NOT NULL   -- local | remote
status            VARCHAR(12)      NOT NULL   -- internal | public | disabled | tombstone
created_at        DATETIME         NOT NULL
updated_at        DATETIME         NOT NULL

UNIQUE KEY uuid (uuid)
UNIQUE KEY canonical_uri_hash (canonical_uri_hash)
KEY kind_origin_status (object_kind, origin, status)
ENGINE=InnoDB
```

Rules:

- **`uuid` is the only immutable anchor.** Generate it once (`wp_generate_uuid4()`)
  and never change it — it survives a domain move and a re-import (where `id`
  changes). It is the identity's true name.
- **Local `canonical_uri = home_url( '/actors/' . uuid )` is a rebuildable cache**
  of the current site URL, not an eternal constant. A domain move rewrites every
  local `canonical_uri` (an explicit migration, later paired with an ActivityPub
  `Move`) while `uuid` is preserved. `/?ax_actor={uuid}` is the plain-permalink
  fallback for the same target (ROUTING §1).
- **Remote** identity: the remote `canonical_uri` is the source of truth; the local
  `uuid` is an internal record id only and is never presented as the object's
  identity, nor re-served under our `/actors/{uuid}`.
- v0.1 writes only `object_kind = 'actor'`; collection/folder/media/activity kinds
  are reserved (SPEC §3) so the registry is not prematurely generalised.
- `status` transitions: `internal → public` (admin publish), `→ disabled`
  (hidden but retained), `→ tombstone` (owner user deleted / remote Delete).
  `tombstone` is terminal for exposure; the row is never hard-deleted here.

## 3. `wp_ax_actors` — the actor profile

One row per actor, attached 1:1 to an identity row. Holds *profile/federation*
fields, not identity truth (that is the identity row) and not content.

```
identity_id       BIGINT UNSIGNED  PK         -- = wp_ax_identities.id (1:1; NO separate actor id)
actor_type        VARCHAR(16)      NOT NULL   -- Person | Organization | Application | Service | Group
actor_scope       VARCHAR(8)       NULL       -- site | user for local; NULL for remote (origin is the truth)
preferred_username VARCHAR(191)    NULL       -- real handle (NOT unique; NULL until a local actor registers one; remote actors on different domains share handles)
local_handle_key  VARCHAR(191)     NULL       -- normalized handle for LOCAL actors only; NULL for remote and until registration
handle_locked_at  DATETIME         NULL       -- set once when a local handle is registered; then immutable
local_user_id     BIGINT UNSIGNED  NULL       -- set only for local Person
display_name      VARCHAR(191)     NULL       -- remote snapshot only (local reads WP_User / bloginfo live)
summary           TEXT             NULL       -- remote snapshot only
profile_url       TEXT             NULL       -- remote snapshot only (local is derived)
inbox_uri         TEXT             NULL       -- reserved (federation); unused in v0.1
outbox_uri        TEXT             NULL       -- reserved
payload_json      LONGTEXT         NULL       -- remote Actor JSON-LD snapshot only
created_at        DATETIME         NOT NULL
updated_at        DATETIME         NOT NULL

UNIQUE KEY local_handle_key (local_handle_key)       -- one handle per LOCAL actor (NULLs allowed → remote dupes OK)
UNIQUE KEY local_user_id (local_user_id)             -- one local Person per user (v0.1)
KEY preferred_username (preferred_username)          -- lookup only (non-unique)
KEY scope_type (actor_scope, actor_type)
ENGINE=InnoDB
```

Notes:

- **The actor row is a 1:1 specialization of its identity — keyed by `identity_id`,
  with no separate `actor.id`.** One object, one DB key; `identity_id` is both the
  primary key here and the foreign key into `wp_ax_identities`. `actor_uri` is the
  identity's `canonical_uri`, never a column here.
- `origin` (local|remote) and `status` live on the **identity** row, the single
  source of truth. `actor_scope` only sub-classifies *local* actors (`site` vs
  `user`); it is `NULL` for remote actors, where `origin = remote` is the
  discriminator. (There is no `s2s` scope — federation is a delivery concern, not an
  identity scope.)
- **`preferred_username` is NOT globally unique** — the actor table also holds
  remote actors, and `alice@example.com`, `alice@remote.example`,
  `alice@another.example` legitimately share the handle `alice`. Uniqueness applies
  only to **local** actors, via a separate `local_handle_key` (the normalized handle
  for local site/user actors; `NULL` for remote). MySQL allows many `NULL`s in a
  unique index, so remote duplicates are permitted while local collisions are
  blocked at the DB. `/@handle/` resolution matches on `local_handle_key`; a
  reserved-handle guard (ROUTING §2) additionally blocks routing collisions.
  - **Per-domain remote uniqueness is a Federation concern, not a DB constraint here.**
    In ActivityPub `preferredUsername` is only a display hint; the identity is the
    `canonical_uri`. A Mastodon-style `alice@example.com` is unique within a domain
    *only after WebFinger verifies the account address* — so rejecting a second
    remote actor merely because it reuses a `preferredUsername` on the same domain
    would break interoperability. When Federation lands, a separate verified
    `acct_uri` + `acct_uri_hash UNIQUE` (written only after WebFinger success)
    enforces per-domain uniqueness; the current model stays as-is.
- **A local handle is registered once, then immutable.** `ensure_for_user()` creates
  a **handle-less** internal actor (`preferred_username` / `local_handle_key` /
  `handle_locked_at` all `NULL`). The handle is set exactly once, at account
  activation, via `register_handle()`, which stamps `handle_locked_at`; a second
  registration on a locked actor is refused. This is deliberately not a rename API —
  an exceptional change is a future admin-recovery + alias/`Move` concern (SECURITY
  §3). Handle **candidates** come from `user_nicename` and the nickname, never
  `user_login` (which may be an email or login id); the final handle is normalized
  and dup/reserved-checked before locking, with the DB `UNIQUE` as the race backstop.
- `UNIQUE(local_user_id)` enforces one local Person per user for v0.1. When a user
  is later allowed to manage multiple actors, this unique is dropped and a
  `wp_ax_actor_managers(identity_id, user_id, role)` join table is added (reserved,
  not built now).
- **Local Person / Site actors:** `display_name`, `summary`, `profile_url` are
  left NULL and resolved live —
  `display_name → get_the_author_meta('display_name', local_user_id)`,
  `profile_url → home_url('/@' . preferred_username . '/')`. `payload_json` stays
  NULL for local rows.
- **Remote actors** populate the snapshot fields + `payload_json` and carry
  fetch/cache metadata when the federation phase adds it (reserved).

## 4. Derived / resolved values (never stored for local actors)

| Value | Local Person (`scope=user`) | Local Site (`scope=site`) | Remote |
|---|---|---|---|
| display name | `WP_User.display_name` | `get_bloginfo('name')` | `actors.display_name` snapshot |
| summary / bio | `WP_User` description | `get_bloginfo('description')` | `actors.summary` |
| avatar | `get_avatar_url( local_user_id )` | `get_site_icon_url()` | snapshot / cached |
| website | `WP_User.user_url` | `home_url('/')` | from payload |
| profile_url | `home_url('/@'.preferred_username.'/')` | same | `actors.profile_url` |
| actor_uri | identity `canonical_uri` | identity `canonical_uri` | identity `canonical_uri` |

The **Site actor has no `local_user_id`**, so its profile is read from the site
(`bloginfo` / site icon), not from any user. **Email** appears in none of these
columns and is never serialized (SECURITY §2).

## 5. Options

```
ax_actors_db_version              schema version for dbDelta upgrades
ax_actors_site_owner_user_id      the WP user linked to the site-owner Person actor
ax_actors_site_actor_type         Application (default) | Organization
```

## 6. Seeding & lifecycle

- **Activation:** always create the **site** actor (idempotent — keyed on
  `actor_scope='site'`, re-activation never duplicates). Create the **site-owner
  Person** actor **only if the current user is a valid administrator**; on CLI / no
  current user, skip it (activation still succeeds). Both `internal`. Never depend on
  a specific account (`user_id=1` / first admin / `admin_email`).
- **`ensure_for_user( user_id )`:** return the user's Person actor, creating a
  **handle-less** internal pair if absent (no `preferred_username`). Never registers
  a handle and never publishes — both are the user's explicit activation step.
- **Handle policy:** a handle is **registered once and then immutable** (see the
  actor-table notes above). `register_handle()` normalizes the chosen candidate,
  checks reserved/duplicate, writes `preferred_username = local_handle_key = key` and
  stamps `handle_locked_at`. The site actor's handle (`blog` / site slug) is assigned
  and locked at seed. `uuid` and `actor_uri` never change (SPEC §2.3). Multisite
  network-wide actors are out of scope for v0.1 (per-site actors only).
- **Public-exposure condition.** A profile is exposed publicly only when
  `status = public AND preferred_username IS NOT NULL AND handle_locked_at IS NOT
  NULL`. A `public` actor with no registered handle is still hidden from anonymous
  viewers (owner / admin preview still applies). Activation is therefore two acts —
  register the handle, then publish — and both are required before `/@handle/` or the
  identity URI render to the public (SECURITY §1).
- **User deleted (`deleted_user`):** set the linked identity `status = tombstone`;
  keep both rows. Do not reassign `local_user_id`.
- **Deactivate / uninstall:** tables and rows are **retained** (no destructive
  drop). A scoped reset is a post-roadmap danger-zone action, mirroring the Media
  Library contract.
