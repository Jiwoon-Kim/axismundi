# Axismundi Actors — Data Model

> Status: **Living specification. Pre-implementation (schema lock).**
> Two tables: a shared **identity registry** and the **actor profile**. Local
> person profile fields are read live from `WP_User`; only remote actors snapshot.

## 1. Conventions

- Table prefix family: **`wp_ax_*`** (shared with other Axismundi plugins that
  reuse the identity registry). Option prefix: **`ax_actors_*`**.
- Long URIs are never uniquely indexed directly (utf8mb4 index-length limit).
  Follow the Media Library relation lesson: store the URI as text **and** a
  `*_hash` = `CHAR(64)` ascii sha-256 hex that is `NOT NULL UNIQUE`. This avoids
  the nullable-UNIQUE pitfall.
- Four distinct identifiers, never conflated (SPEC §2.4):

```
local_user_id   WP_User account key            (login; may be absent)
actor.id        local actor DB key             (internal; never exposed)
identity.uuid   immutable UUID                  (stable handle)
actor_uri       identity.canonical_uri         (/?ax_actor={uuid}; federation identity)
profile_url     /@{preferred_username}/        (human alias; mutable)
```

## 2. `wp_ax_identities` — the identity registry

One row per identifiable object (actor now; collection/folder/media/activity
later). It answers "what UUID and canonical URI is this, and is it local, public,
alive?" — nothing domain-specific.

```
id                BIGINT UNSIGNED  PK AUTO_INCREMENT
uuid              CHAR(36)         NOT NULL   -- UUIDv4, canonical hyphenated form
canonical_uri     TEXT             NOT NULL   -- local: {home_url}/?ax_actor={uuid}; remote: source URI
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

- **Local** identity: generate `uuid` (UUIDv4), then
  `canonical_uri = home_url( '/?ax_actor=' . uuid )`. Both immutable for life.
- **Remote** identity: the remote `canonical_uri` is the source of truth; a local
  `uuid` may still be assigned as an internal record id but is never presented as
  the object's identity.
- `status` transitions: `internal → public` (admin publish), `→ disabled`
  (hidden but retained), `→ tombstone` (owner user deleted / remote Delete).
  `tombstone` is terminal for exposure; the row is never hard-deleted here.

## 3. `wp_ax_actors` — the actor profile

One row per actor, attached 1:1 to an identity row. Holds *profile/federation*
fields, not identity truth (that is the identity row) and not content.

```
id                BIGINT UNSIGNED  PK AUTO_INCREMENT
identity_id       BIGINT UNSIGNED  NOT NULL   -- FK → wp_ax_identities.id
actor_type        VARCHAR(16)      NOT NULL   -- Person | Organization | Application | Service | Group
actor_scope       VARCHAR(10)      NOT NULL   -- site | user | s2s | remote
preferred_username VARCHAR(191)    NOT NULL   -- handle for /@{username}/ (mutable alias)
local_user_id     BIGINT UNSIGNED  NULL       -- set only for local Person
display_name      VARCHAR(191)     NULL       -- remote snapshot only (local reads WP_User live)
summary           TEXT             NULL       -- remote snapshot only
profile_url       TEXT             NULL       -- remote snapshot only (local is derived)
inbox_uri         TEXT             NULL       -- reserved (federation); unused in v0.1
outbox_uri        TEXT             NULL       -- reserved
payload_json      LONGTEXT         NULL       -- remote Actor JSON-LD snapshot only
created_at        DATETIME         NOT NULL
updated_at        DATETIME         NOT NULL

UNIQUE KEY identity_id (identity_id)
UNIQUE KEY local_user_id (local_user_id)   -- one local Person per user (v0.1)
KEY preferred_username (preferred_username)
KEY scope_type (actor_scope, actor_type)
ENGINE=InnoDB
```

Notes:

- `origin` and `status` live on the **identity** row, not duplicated here (single
  source of truth). `actor_scope` is a finer classification of *local* actors
  (`site` vs `user`) plus `s2s`/`remote`.
- `UNIQUE(local_user_id)` enforces one local Person per user for v0.1. When a user
  is later allowed to manage multiple actors, this unique is dropped and a
  `wp_ax_actor_managers(actor_id, user_id, role)` join table is added (reserved,
  not built now).
- **Local Person / Site actors:** `display_name`, `summary`, `profile_url` are
  left NULL and resolved live —
  `display_name → get_the_author_meta('display_name', local_user_id)`,
  `profile_url → home_url('/@' . preferred_username . '/')`. `payload_json` stays
  NULL for local rows.
- **Remote actors** populate the snapshot fields + `payload_json` and carry
  fetch/cache metadata when the federation phase adds it (reserved).

## 4. Derived / resolved values (never stored for local actors)

| Value | Local resolution | Remote |
|---|---|---|
| display name | `WP_User.display_name` | `actors.display_name` snapshot |
| summary / bio | `WP_User` description | `actors.summary` |
| avatar | `get_avatar_url( local_user_id )` | snapshot / cached |
| website | `WP_User.user_url` | from payload |
| profile_url | `home_url('/@'.preferred_username.'/')` | `actors.profile_url` |
| actor_uri | identity `canonical_uri` | identity `canonical_uri` |

**Email** appears in none of these and is never serialized.

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
- **User deleted (`deleted_user`):** set the linked identity `status = tombstone`;
  keep both rows. Do not reassign `local_user_id`.
- **Deactivate / uninstall:** tables and rows are **retained** (no destructive
  drop). A scoped reset is a post-roadmap danger-zone action, mirroring the Media
  Library contract.
