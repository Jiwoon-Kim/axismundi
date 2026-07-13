# Axismundi Actors — Data Model

> Status: **Living specification. Schema v4 implemented (§2–§6 + §8 profile
> presentation); §7 and DB v5+ in §9 are provisional.** Three tables exist today: a
> shared **identity registry**, the **actor profile**, and explicitly authored
> multilingual Actor text. Local person profile fields remain live `WP_User`
> fallbacks; only remote actors snapshot. The next schema steps are v5 addresses →
> v6 endpoints/policy → v7 keys/fetch.

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
object_kind       VARCHAR(20)      NOT NULL   -- actor | collection | folder | media | activity | place
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
- v0.1 writes only `object_kind = 'actor'`; collection / folder / media / activity /
  **place** kinds are reserved (SPEC §3) so the registry is not prematurely
  generalised. **`Place` and `Collection` are objects, not actors** — a place or a
  place-collection (map / geodata grouping) reuses the identity registry with
  `attributedTo` a real actor, but has **no** actor row and no inbox/outbox. They are
  created by their owning plugins (geodata / Media Library), not by Actors.
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
  source of truth. `actor_scope` sub-classifies *local* actors: v0.1 uses `site`
  (the one site actor) and `user` (a 1:1 WP-user Person). **`managed` is reserved**
  for admin-created actors that are neither the site nor tied to a WP user — a
  `Group` (forum / Lemmy community / subreddit-like space) or a `Service` /
  `Organization` publisher (e.g. a geodata service). A `managed` actor has
  `local_user_id = NULL` and is administered through the reserved
  `wp_ax_actor_managers` table. `actor_scope` is `NULL` for remote actors, where
  `origin = remote` is the discriminator. (There is no `s2s` scope — federation is a
  delivery concern, not an identity scope.)
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
  `user_login` (which may be an email or login id).
- **Local handle character rule (mention interop).** A local handle must match
  `^[a-z0-9](?:[a-z0-9_]{0,28}[a-z0-9])?$` — **lowercase letters, digits, and
  underscores only** (1–30 chars, no leading/trailing underscore). This is stricter
  than ActivityPub `preferredUsername` (which has *no* character rule) on purpose:
  many servers/apps parse mentions as a bare `@\w+`, so a hyphen would split
  `@kim-jiwoon` into `@kim`. Underscore is inside `\w`, so it is the widest-compatible
  separator. The transform (lowercase; hyphen/dot/space → `_`; collapse/trim `_`) runs
  only when generating **candidates** (`suggest_handle`); at registration the value is
  case-folded and then **validated without silent rewriting** — since the handle is
  immutable, the user must confirm the exact value. Collision suffixing uses `_N`, not
  `-N`. **Remote** actors keep their `preferredUsername` verbatim (dots/hyphens/
  unicode allowed) in `payload_json`; this rule is a *local* mention-interop policy.
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

## 7. Reserved: handle alias-history (future phase, not built in v0.1)

The handle is a **reservable routing alias**, not the identity (the identity is the
UUID / `actor_uri`). v0.1 keeps the handle on the actor row and immutable-once-set.
When change/recovery is opened, it moves to a dedicated table so old handles are
retained as reservations and can never be silently reused:

```
wp_ax_actor_handles
- id
- identity_id
- handle
- handle_key   UNIQUE      -- normalized; the routing key
- status       primary | redirect | reserved
- created_at
- retired_at
```

Rules (frozen intent; schema is actor-kind-neutral so Person change can open later):

- One `primary` per actor; superseded handles become `redirect`; a tombstoned
  actor's handles become `reserved`.
- **Only the same actor** may reclaim its own `redirect` / `reserved` handle; another
  actor can never occupy it (anti-impersonation). Any change checks reserved/dup and
  swaps primary↔redirect **in one transaction**; the canonical id stays `/actors/{uuid}`.
- **Site actor:** change is appropriate (e.g. `@blog` → `@designbusan`), with the old
  handle permanently reserved to the same site actor and a `redirect`; the site actor
  may revert.
- **Person actor:** immutable in v0.1, and its handle stays **reserved even after the
  user is deleted**. A returning person is handled as **tombstone-identity recovery**
  (admin re-links the tombstoned actor to a new `WP_User`), *not* by letting a new
  user grab `@alice` — which would mis-attribute old posts and read as account
  takeover to remote servers. A genuinely different person gets `alice-2`.
- A remote actor's `preferredUsername` is **never** entered in this local table.
- When §9.1 `wp_ax_actor_addresses` lands it **realizes** this alias-history (adding
  the WebFinger `acct:` address); the two are one table, not two.

## 8. Profile presentation

Avatar/header and multilingual profiles. Names and bios are still read live from
`WP_User` (§4); only these presentation extras are Actor-owned. §8.1 is **shipped
(DB v3)**; §8.2 multilingual is **DB v4** (Phase 4d).

### 8.1 avatar / header — two columns on `wp_ax_actors` *(shipped, DB v3)*

Avatar and header are fixed **0..1 slots per actor**, so a relation table would be
over-modelling. Two columns are exact:

```
wp_ax_actors += avatar_attachment_id BIGINT UNSIGNED NULL   -- a CORE attachment id
                header_attachment_id BIGINT UNSIGNED NULL   -- a CORE attachment id
```

- A core Media-picker attachment id (not a Media Library object), resolved to a URL
  at render; works without the Media Library plugin. Columns (not usermeta) because
  the **site / Group / Service actors have no `WP_User`** — Actor-owned values live on
  the actor row so Person and non-Person share one path.
- Serialization: `avatar_attachment_id → icon`, `header_attachment_id → image`.
- Resolution extends §4: **avatar** → `avatar_attachment_id` → `get_avatar_url(user)`;
  **header** → `header_attachment_id` → none. Name / bio / website stay live from
  `WP_User`.
- **Remote** actors read `icon` / `image` from `payload_json` (no URI column, no
  shadow attachment) until real caching is needed.
- On save: verify it is an attachment, an image MIME, and the current user
  `edit_post`s it. On `delete_attachment`, null any `avatar_attachment_id` /
  `header_attachment_id` that referenced it (logical cleanup, no physical FK).
- A dedicated media table is revisited **only** when one of these appears: multiple
  avatar/header variants, change history, remote-image local cache, per-language
  header, crop/focal-point metadata, or media roles beyond avatar/header. None exist
  now, so `avatar_attachment_id` / `header_attachment_id` it is.
- Optional (default **on** for local Person): mirror the Actor avatar into WordPress
  via the `get_avatar_data` filter so comments / admin show it; the header is
  Actor-only.

### 8.2 `wp_ax_actor_texts` + `default_language` — multilingual *(shipped, DB v4, Phase 4d)*

```
wp_ax_actors  += default_language VARCHAR(35) NULL   -- BCP 47

wp_ax_actor_texts
- identity_id
- field_name     name | summary | content
- language_tag   BCP 47 (ko-KR, en-US, und)
- value          LONGTEXT
- media_type     NULL | text/html
- updated_at
UNIQUE(identity_id, field_name, language_tag)
```

- Maps to AS `nameMap` / `summaryMap` / `contentMap` (name = plain text; summary /
  content = sanitized HTML; `content` is the optional long "About"). A table, not
  three JSON columns, so languages add/edit without rewriting a blob.
- `default_language` = the **site language** at creation; the user's own profile
  language (`profile.php`) is offered as a **secondary** tab, never auto-applied
  (`get_user_locale()` is a candidate, not a decision — an admin-UI locale is not a
  public-profile language). Unlike the handle, `default_language` is mutable later.
- Viewer resolution: request-lang exact → request-lang base → `default_language` →
  site language → user profile language → `und` → first remaining → `WP_User`
  display_name / description. The **serialized scalar** `name` / `summary` /
  `content` always use `default_language`; every translation also goes in the `*Map`
  (some peers ignore Maps). WP locales are normalized to BCP 47 (`ko_KR` → `ko-KR`).
- Does **not** duplicate `WP_User` name/bio — a translation is stored only when the
  user adds it; the fallback chain still ends at `WP_User`. If site and user language
  match, only one tab shows.

## 9. DB version roadmap (v5–v8, provisional)

Extend **v4** (current) step by step; the schema-version option is written **only
after** the tables / columns / required indexes are confirmed to exist (install()
self-check, §6). Design rules that gate every step:

- Fixed **0..1** value → a `wp_ax_actors` column; a value with **rows that grow**
  (translations, addresses, keys) → a child table.
- **Activity / Follow / Like / Delivery / Notification never live in the Actors DB**
  (see §9.6). URI is the canonical identifier; DB id / blog id are internal
  acceleration pointers. `blog_id` is **not** added to the current tables — the
  per-site `$wpdb->prefix` is the tenancy (§9.7).

### 9.1 DB v4 — multilingual profile *(shipped)*
`wp_ax_actors += default_language`; `wp_ax_actor_texts` (§8.2).

### 9.2 DB v5 — addresses & handle history (realizes §7)
```
wp_ax_actor_addresses
- id, identity_id
- address_type   local_handle | acct | former_handle
- address, address_hash
- status         primary | redirect | reserved | alias
- verified_at, created_at, retired_at
UNIQUE(address_type, address_hash)
```
- Backfill the current handle as `local_handle` / `primary`. This table is the
  **routing + history ledger**; `preferred_username` on the actor row stays the
  Actor-JSON display value. **Pick one source of truth before the first federation
  release** — recommended: addresses = routing ledger, `preferred_username` = display.
- `acct:` (e.g. `alice@example.test`) is verified **only after a successful WebFinger
  HTTPS lookup** (RFC 7033); `preferredUsername` alone is never trusted. Old handles
  are `reserved` to the same actor, never recycled.

### 9.3 DB v6 — endpoints & follower/discovery policy
```
wp_ax_actors += published_at                    -- remote actor's declared creation (≠ created_at)
              + manually_approves_followers      -- Follow approval (lock)
              + discoverable                      -- recommendations / directory
              + indexable                         -- public posts in search
              + follow_collections_visibility     -- public | followers | private

wp_ax_actor_endpoints
- identity_id, endpoint_type, endpoint_uri, endpoint_uri_hash, updated_at
- endpoint_type: inbox|outbox|followers|following|featured|shared_inbox
UNIQUE(identity_id, endpoint_type)
KEY(endpoint_uri_hash)   -- NOT unique: a sharedInbox is shared across many actors
```
Copy `inbox_uri` / `outbox_uri` into the table, switch reads over, then (pre-release)
drop the old columns. **These are separate axes, not one enum:** `internal|public`
is *lifecycle* (§2.3); follow-approval, discovery/indexing, and collection visibility
are independent policies; the **default posting audience** (`public|unlisted|followers|
mentioned`) is **not here — it belongs to the Activity plugin**. `discoverable` /
`indexable` on a *remote* actor are observed declarations, kept separate from any
local policy flag of the same name.

### 9.4 DB v7 — keys, remote cache, moves
```
wp_ax_actor_keys           key_uri(+hash), key_type, public_key_pem, fingerprint,
                           private_key_ref, status active|retired|revoked, valid_from/until
wp_ax_actor_fetch_state    identity_id PK, payload_hash, etag, last_modified,
                           fetched_at, last_success_at, next_refresh_at, failure_count, last_error_code
wp_ax_identity_relations   relation_type also_known_as|moved_to, target_uri(+hash),
                           verification_state, verified_at   UNIQUE(identity_id, relation_type, target_uri_hash)
```
A **local private key is never stored in plaintext** — `private_key_ref` points at
separate secret storage. `alsoKnownAs` / `movedTo` are **never trusted on inbound
JSON alone** (need cross-reference / a Move verification flow).

### 9.5 DB v8 — managed actors
`wp_ax_actor_managers(identity_id, user_id, role owner|manager|editor,
UNIQUE(identity_id, user_id))`, added **only when** Group / Service / Organization
actors are actually created; `actor_scope='managed'` activates then (SPEC §4.1). The
current Site/User actors do not need it. **Version numbers are implementation order,
not schema dependencies** — if a Lemmy-style community (`Group`) ships before
federation, pull this forward to just before that managed-Group work; nothing in
v5–v7 depends on it. (A `Group` community is `Application` site actor's sibling: its
posts/comments live in the object/activity store and its Follow / Accept / Announce /
moderation in the social layer — §9.6 — never on the actor row; a shared folder stays
an `OrderedCollection` + ACL and is *not* promoted to a `Group`.)

### 9.6 Owned by OTHER stores, never by Actors
`wp_ax_activities`, `wp_ax_deliveries`, `wp_ax_follows`, `wp_ax_reactions`,
`wp_ax_notifications` → a separate **Activity / social-relation** store. Collections,
memberships, and saved references → **Media Library**. Actors provides identity
lookup + the actor profile only; the followers / following endpoint *content* is
**projected** from the relation store, never stored on the actor row (counts included).

### 9.7 Multisite scope is a runtime decision, not a stored column
`actor_scope` stays `site | user | managed`. **`site-local` / `network-local` /
`remote` are computed at request time** (they depend on *which* site is asking) from
the actor URI + a network index — do **not** freeze them onto the actor row, and do
**not** add `blog_id` to the current per-site tables (the table prefix is the
tenancy). A global routing index is added **only** when a real cross-site optimisation
needs it:
```
wp_ax_actor_network_index   blog_id, identity_uuid, canonical_uri_hash, acct_uri_hash, status
```
It indexes location only — profile bodies and follow state are never globalised.
`wp_ax_identities` is **per-site**, so a UUID alone cannot be looked up network-wide —
that is fine because the **canonical URI is the identifier**; the network index (a
routing cache) is what enables cross-site lookup when it is actually needed. The
minimum multisite-safe contract to hold now: never assume `user_id` is small / 1 /
sequential; a WP user may own **many** actors (one per site), each site an independent
federation boundary; URI is the identity; cache keys include site context or hash the
URI; network-wide activation must not break.

### 9.8 WebFinger `acct:` handle policy — a pre-v5 PRODUCT decision (not a column)

`acct:` collisions across a network are resolved by **policy, not schema**, and the
policy must be fixed **before v5** (addresses):

- **Subdomain** (`site-a.example`, `site-b.example`) and **mapped-domain** subsites:
  `alice@site-a.example` vs `alice@site-b.example` resolve naturally — the authority
  differs. No extra decision needed.
- **Subdirectory** (`example.com/site-a`, `example.com/site-b`): both would be
  `alice@example.com`, which **collides** — one `acct:` cannot resolve to two actors.
  Pick one:
  1. reserve `acct:` handles **network-wide** (a global handle namespace),
  2. require subsites to use a **separate domain / subdomain** before federation is
     enabled, or
  3. let the **network routing index** designate one representative actor that owns
     that `acct:`.

This is a WebFinger *product* policy; the `wp_ax_actor_addresses` table (v5) records
the outcome but does not decide it.

### Stays in `payload_json` (resolver-read, never columnized)
`memorial`, `showFeatured` / `showMedia` / `showRepliesInMedia`, `interactionPolicy`,
`attributionDomains`, all `misskey:*` and `vcard:*`, `tag` (Emoji / Hashtag are
polymorphic), `attachment` (Mastodon profile `PropertyValue` links — unrelated to
Media Library attachments), and object-or-array `icon` / `image`. A remote profile's
`sensitive` flag is its own thing, **not** the Media Library sensitive-authority.

**vCard** is not a schema concern: a future `/@handle/contact.vcf` can map
already-public Actor fields to vCard 4.0 (birthday / address only behind an explicit
opt-in field). No `vcard_*` columns.
