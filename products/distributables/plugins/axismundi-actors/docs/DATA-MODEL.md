# Axismundi Actors — Data Model

> Status: **Living specification. Pre-implementation (schema lock).**
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
preferred_username VARCHAR(191)    NOT NULL   -- handle for /@{username}/ (mutable alias)
local_user_id     BIGINT UNSIGNED  NULL       -- set only for local Person
display_name      VARCHAR(191)     NULL       -- remote snapshot only (local reads WP_User / bloginfo live)
summary           TEXT             NULL       -- remote snapshot only
profile_url       TEXT             NULL       -- remote snapshot only (local is derived)
inbox_uri         TEXT             NULL       -- reserved (federation); unused in v0.1
outbox_uri        TEXT             NULL       -- reserved
payload_json      LONGTEXT         NULL       -- remote Actor JSON-LD snapshot only
created_at        DATETIME         NOT NULL
updated_at        DATETIME         NOT NULL

UNIQUE KEY preferred_username (preferred_username)   -- one handle per local actor
UNIQUE KEY local_user_id (local_user_id)             -- one local Person per user (v0.1)
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
- `UNIQUE(preferred_username)` makes `/@handle/` resolution unambiguous; a
  reserved-handle guard (ROUTING §2) additionally blocks routing collisions.
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

- **Activation:** ensure the two identity+actor pairs of SPEC §4 exist (idempotent;
  re-activation never duplicates — keyed on scope/uuid). Both `internal`.
- **`ensure_for_user( user_id )`:** return the user's Person actor, creating the
  identity+actor pair (`internal`) if absent. Never publishes.
- **Handle policy:** a local Person's `preferred_username` defaults to the user's
  `user_nicename` and **tracks it** on change, *unless* an admin has set an explicit
  handle override (then it is independent). The site actor's handle comes from the
  option / site slug. A handle change moves the `/@handle/` alias only — `uuid` and
  `actor_uri` never change (SPEC §2.3). Multisite network-wide actors are out of
  scope for v0.1 (per-site actors only).
- **User deleted (`deleted_user`):** set the linked identity `status = tombstone`;
  keep both rows. Do not reassign `local_user_id`.
- **Deactivate / uninstall:** tables and rows are **retained** (no destructive
  drop). A scoped reset is a post-roadmap danger-zone action, mirroring the Media
  Library contract.
