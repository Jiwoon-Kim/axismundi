# Axismundi Actors — Data Model

> 2026-10-27 update: the shared non-Actor registry design below is superseded by
> [Identifier Separation](../../axismundi-object-projections/docs/IMPLEMENTATION-IDENTIFIER-SEPARATION.md).
> Objects now own their IDs/URI index in OP. User folders no longer have AP
> identities. [Actor Body](IMPLEMENTATION-ACTOR-BODY.md) schema 39.0 subsequently
> consolidates Actor identifiers into `ax_actors` and removes `ax_identities`.
> The historical table layout below is not the current storage contract.

> Status: **Living specification. Schema v10b (stored version 10.1) implemented
> (§2–§6, §8, §9.2–§9.6b, §9.10).** The non-actor registry API (§2.1) is implemented as of
> 0.0.31 with **no schema change** — `object_kind` and its kinds were already specified.
> Ten tables exist today: the shared identity and
> actor profile records, multilingual text, address and instance ledgers, normalized
> endpoints, remote asset mappings, keyring, fetch state, and identity relations. Local person
> profile fields remain live `WP_User` fallbacks; only remote actors snapshot. Next
> schema step: v11 managers, only when managed actors ship. WebFinger
> acct policy is fail-closed (§9.9); bounded synchronous remote Actor discovery + NodeInfo
> caching are implemented.

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
- `object_kind` is `actor` plus the non-actor kinds collection / folder / media /
  activity / **place** (SPEC §3). **`Place` and `Collection` are objects, not actors** —
  a place or a place-collection (map / geodata grouping) reuses the identity registry
  with `attributedTo` a real actor, but has **no** actor row and no inbox/outbox. They
  are created by their owning plugins (geodata / Media Library), not by Actors.
- **The two creation paths are separate and must stay so.** `axismundi_actors_create_local()`
  writes an identity **and** its actor row in one transaction; the non-actor registry API
  (`includes/identity-registry.php`, §2.1) writes identity rows only and **refuses
  `object_kind = 'actor'`** — an actor identity with no profile row is an orphan that no
  actor lookup can hydrate, so the kind allowlist is that orphan guard.
- `status` transitions: `internal → public` (admin publish), `→ disabled`
  (hidden but retained), `→ tombstone` (owner user deleted / remote Delete).
  `tombstone` is terminal for exposure; the row is never hard-deleted here.

### 2.1 The non-actor registry API

Owning plugins reach the registry only through `includes/identity-registry.php` — never
direct SQL (§1). It is a separate file from the actor-profile repository so the documented
extraction into a shared `axismundi-core` stays a file move.

```php
axismundi_actors_identity_kinds()                       // collection|folder|media|activity|place
axismundi_actors_register_identity( array $args )       // → identity array | WP_Error
axismundi_actors_get_identity( int $identity_id )       // → identity array | null
axismundi_actors_get_identity_by_uuid( string $uuid )
axismundi_actors_get_identity_by_uri( string $uri )     // hash narrows, exact URI confirms
axismundi_actors_audit_identity_uri_hashes()            // reports stored URI/hash drift
axismundi_actors_set_identity_uri( int $id, string $uri ) // domain move; UUID preserved
axismundi_actors_set_status( int $id, string $status )  // shared with actors; kind-agnostic
```

`register_identity()` rules:

- **A local caller supplies a `uri_template` containing `{uuid}`, not a finished URI.** A
  local identity's URI contains its own UUID, so the registry owns UUID generation while
  the caller owns its URI space. A remote caller supplies `canonical_uri` instead.
- **A remote `canonical_uri` is validated for shape only** — https + host, no DNS
  resolution — matching the endpoint-persistence rule (§9.4). Registering an identity is a
  *record*, not a fetch: a peer may be temporarily unresolvable or behind split DNS
  without that invalidating its canonical URI. The SSRF gate belongs at the fetch site.
- **`status` defaults to `internal`**; `tombstone` is rejected at creation because it is an
  end state reached through `set_status()`, not a birth state (SPEC §2.6).
- A duplicate `canonical_uri` is refused across every kind (the hash column is UNIQUE).

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
personal_owner_user_id BIGINT UNSIGNED NULL  -- personal attribution, not transport
transport_kind    VARCHAR(24)      NULL       -- upstream_user | upstream_application | actor
transport_user_id BIGINT UNSIGNED  NULL       -- reserved legacy per-user route, not ownership
local_signing_key_id BIGINT UNSIGNED NULL    -- active local signing key (S3b)
display_name      VARCHAR(191)     NULL       -- remote snapshot only (local reads WP_User / bloginfo live)
summary           TEXT             NULL       -- remote snapshot only
profile_url       TEXT             NULL       -- remote snapshot only (local is derived)
payload_json      LONGTEXT         NULL       -- remote Actor JSON-LD snapshot only
created_at        DATETIME         NOT NULL
updated_at        DATETIME         NOT NULL

UNIQUE KEY local_handle_key (local_handle_key)       -- one handle per LOCAL actor (NULLs allowed → remote dupes OK)
KEY local_user_lookup (local_user_id)                -- compatibility links may repeat (S4)
UNIQUE KEY legacy_transport_user (transport_user_id) -- one legacy route per user; multiple NULLs
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
- S4(DB 37.0)는 local_user_id의 UNIQUE를 transport_user_id로 옮긴다. 추가 Person은 같은
  User 링크를 가질 수 있으나 구 per-user 라우트를 중복 예약하지 않는다. 접근 관계는 기존
  `wp_ax_actor_managers(identity_id, principal_kind, principal_id, role)`가 관리한다.
  생성 UI·REST gate 및 S2의 이전·삭제 정책은 별개다.
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
ax_actors_site_actor_disabled     one-time retirement marker for the reserved Site Actor
```

## 6. Seeding & lifecycle

- **Activation:** always create the disabled **site** actor (idempotent — keyed on
  `actor_scope='site'`, re-activation never duplicates). Create the **site-owner
  Person** actor **only if the current user is a valid administrator**; on CLI / no
  current user, skip it (activation still succeeds). The Site Actor is `disabled`; the
  Person is `internal`. Never depend on
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
- `default_language` = the **site language** at creation and is mutable later. The
  user's profile language (`profile.php`) is offered as a secondary editing tab. For
  a local Person's **human-facing HTML profile**, a matching explicitly authored
  translation in that user language is preferred; this does not mutate
  `default_language` or change the scalar language serialized to peers.
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
  (see §9.7). URI is the canonical identifier; DB id / blog id are internal
  acceleration pointers. `blog_id` is **not** added to the current tables — the
  per-site `$wpdb->prefix` is the tenancy (§9.8).

### 9.1 DB v4 — multilingual profile *(shipped)*
`wp_ax_actors += default_language`; `wp_ax_actor_texts` (§8.2).

### 9.2 DB v5 — addresses & handle history (realizes §7) *(shipped)*
```
wp_ax_actor_addresses
- id, identity_id
- address_type   local_handle | acct | former_handle
- address, address_hash   -- hash = sha256( namespace ':' address ); namespace='handle' for
                          --   local_handle+former_handle (shared), else the type (e.g. 'acct')
- status         primary | redirect | reserved | alias
- verified_at, created_at, retired_at
UNIQUE(address_hash)   -- shared 'handle' namespace ⇒ a reserved former handle blocks a new
                       --   handle of the same string; acct addresses never collide with handles
KEY(identity_id, status)
```
- Install **backfills** the current handle as `local_handle` / `primary`.
  `register_handle()` writes the primary row and rejects a handle **reserved to a
  different actor** (`axismundi_actors_handle_owner`); `reserve_former_handle()` is the
  infrastructure for a future site-actor rename / admin recovery (a retired handle is
  never recycled). This table is the **routing + history ledger**; `preferred_username`
  on the actor row stays the Actor-JSON display value — **source of truth = addresses
  for routing, `preferred_username` for display**.
- Local `acct:` rows (e.g. `alice@example.test`) are written by the WebFinger
  increment after the site's authority and public Actor are validated; the local
  server is authoritative for them. Future **remote** `acct:` rows are verified only
  after a successful HTTPS WebFinger lookup (RFC 7033); remote
  `preferredUsername` alone is never trusted.

#### 출하된 UNIQUE 는 의도된 일탈이다 (2026-11-01 정정)

위 블록은 `UNIQUE(address_hash)` 라고 적었으나 출하된 스키마는 복합키다. **문서가 틀렸고
스키마가 맞다.**

```txt
문서(위)   UNIQUE(address_hash)
출하       UNIQUE KEY address_kind (address_hash, actor_kind)
           actor_kind 는 AS2 타입 -- Person | Group | Organization | Application | ''
```

이유는 `tests/audit-addresses.php` 가 기록해 두었다 -- **Lemmy 는 한 호스트에서 Person 과
Group 이 같은 `acct:` 를 갖는 것을 허용하고, 전역 unique 키 아래에서는 나중에 캐시된 쪽이
주소를 거부당해 핸들로 도달 불가능해졌다.** 그 감사는 복합키의 존재와 **옛 전역
`address_hash` 인덱스의 부재**를 함께 단언한다. 2026-11-01 실데이터에도 그 사례가 있다:
`acct` 하나를 remote Group 과 remote Person 이 공유한다.

따라서 **이 키를 단일 컬럼으로 "고치지" 말 것.** 측정된 버그를 되살리고 감사를 깨뜨린다.
(2026-11-01 리뷰에서 Opus 가 이것을 드리프트로 판정하고 조이기를 권고했다. 오너가
Lemmy 사유를 기억해 지적했고, 데이터와 감사 주석이 확인했다. AGENTS.md 가 경고하는
"cleanup 처럼 보이지만 의도된 일탈을 제거하는 변경" 의 사례로 남긴다.)

#### 로컬 핸들 유일성은 코드가 집행한다

복합키가 remote 사례를 위한 모양이므로, **로컬 핸들의 유일성은 스키마가 주지 않는다.**
로컬에서는 WebFinger 가 `acct:` 하나에 한 액터로 답해야 하는데, 복합키는 로컬 Person 과
로컬 Group 이 같은 `local_handle` 을 갖는 것을 막지 않는다.

막는 것은 `axismundi_actors_handle_owner()` 다. 그 함수는 `address_hash` 만으로 조회하고
`actor_kind` 를 보지 않으므로 `register_handle()` 경로에서 교차 타입까지 거부한다.
**즉 의도적으로 코드에 있는 집행이다** -- 스키마로 올리려면 remote 를 깨뜨리지 않도록
로컬 행에만 적용되는 형태(원격이면 NULL 인 컬럼에 UNIQUE)여야 한다.

남는 위험은 검사-후-쓰기 경합이다. 로컬 Person 과 로컬 Group 이 동시에 같은 핸들을
등록하면 둘 다 `handle_owner()` 를 통과하고 복합키가 교차 타입을 막지 않는다. 그 상태에서
`get_by_handle()` 은 `query_one` 이므로 **둘 중 하나를 조용히** 돌려준다. 등록은 드문
동작이라 확률은 낮지만, 핸들 변경이 열리면 빈도가 오르고 rename 경로가 추가된다.

#### 오너 결정 (2026-11-01): 핸들 변경을 연다

§7 과 `PHASES.md` Phase 2.1 의 "immutable-once-set" 전제를 완화한다. 근거는 이 모델이
이미 적어 둔 것이다 -- **핸들은 routing alias 이고 정체성은 UUID/`actor_uri`** 다.

```txt
깨지지 않는 것   기존 팔로우 -- 원격은 Actor URI 와 inbox 를 저장하고 재해소하지 않는다
                 기존 글의 멘션 -- 작성 시점에 해소된 링크
                 self 링크 -- /actors/{uuid}
깨지는 것        원격이 @old@host 로 검색하는 경로.  새로 쓰는 옛 핸들 멘션
남는 것          원격이 캐시한 옛 핸들.  액터 문서를 다시 받을 때까지 옛 이름으로 표시된다
```

즉 **발견(discovery)이 깨지고 전달(delivery)은 깨지지 않는다.** 이메일·전화번호 변경이
어려운 이유(인증과 관계가 그 값으로 키잉됨)는 여기 적용되지 않는다.

정책 통제는 네 가지가 거론되었고 **셋이 이미 있는 것으로 접힌다.**

```txt
운영자 해제        만든다 -- capability 검사 + 플래그.  유일하게 새 코드다
강제 개명          이미 가능 -- manage_options 가 can_manage() 를 통과한다
                   (PRINCIPAL-BOUNDARIES.md 2026-10-30 완화 규칙)
"연합활동 없음" 자동 해제   만들지 않는다 -- 모집단 0
                   handle_locked_at 이 이미 "외부에 노출했는가" 를 기록하고
                   repository.php:494 백필이 핸들 보유 액터 전부를 잠갔다
                   2026-11-01 측정: 핸들 보유 255, 그중 lock NULL 0
변경요청 + 승인     나중 -- 요청 저장소 · 알림 · 승인 화면 · 상태기계.  플래그가 아니라 기능
```

`handle_locked_at` 의 의미도 함께 재정의한다. "불변" 이 아니라 **"한 번이라도 외부에
노출되었다"** 다. 그 플래그를 보는 곳이 멘션 검색 · NodeInfo 공개 액터 수 · 공개 Group
디렉터리 · 원격 팔로우 대상이므로, 의미를 바꿀 때 그 네 게이트의 뜻도 함께 읽어야 한다.

**해제가 바꾸지 못하는 것**: 옛 핸들은 여전히 `former_handle` 로 예약되고 handle 네임스페이스를
공유한다. 해제는 "누가 개명할 수 있는가" 만 바꾸고 "옛 이름이 풀리는가" 는 바꾸지 않는다.
사칭 위험은 정책과 무관하게 닫혀 있다 -- 단, 위의 교차 타입 공백을 먼저 닫은 뒤에.

#### 미결 (2026-11-01)

```txt
옛 핸들 해소        get_by_handle() 은 a.local_handle_key 만 본다.  former_handle 을
                    해소하지 않으므로 예약은 되고 조회는 404 다.  해소하면 옛 링크가
                    살고, WebFinger 의 subject 로 현재 이름을 돌려주어 원격이 갱신할
                    단서를 얻는다.  메일 주소를 핸들에서 파생하면 같은 결정이 배달에도
                    적용되고, 거기서는 근거가 더 강하다 -- 조용한 반송이 404 보다 나쁘다
reserve_former_handle()   실행 이력 없음(행 0).  §9.2 가 "future rename 을 위한
                    인프라" 라고 적었으므로 예정된 상태이지, 결함의 증거가 아니다.
                    핸들 변경을 열기 전에 픽스처로 예약·해소·충돌 거부를 검증할 것

### 9.3 DB v7 — Actor endpoints *(implemented)*
```
wp_ax_actor_endpoints
- identity_id, endpoint_type, endpoint_uri, endpoint_uri_hash, updated_at
- endpoint_type: inbox|outbox|followers|following|featured|shared_inbox
UNIQUE(identity_id, endpoint_type)
KEY(endpoint_uri_hash)   -- NOT unique: a sharedInbox is shared across many actors
```
DB v7 backfills `inbox_uri` / `outbox_uri` plus followers/following/featured/sharedInbox
from the bounded payload snapshot, verifies all non-empty legacy values, then drops
the two old actor columns. Remote refresh atomically replaces the endpoint set with
the Actor snapshot, so removed optional roles do not remain stale.

### 9.4 DB v8 — follower/discovery policy axes
```
wp_ax_actors += published_at                     -- remote declaration (≠ created_at)
              + manually_approves_followers      -- Follow approval (lock)
              + discoverable                     -- recommendations / directory
              + indexable                        -- public posts in search
              + follow_collections_visibility    -- public | followers | private
```
These are separate axes, not one enum: `internal|public` is lifecycle (§2.3), while
follow approval, discovery/indexing, and collection visibility are independent.
The default posting audience belongs to the Activity plugin. Remote declarations
must preserve NULL (unreported) separately from explicit false.

### 9.5 DB v9 — remote asset cache *(implemented; REMOTE-ASSET-CACHE.md)*
```
wp_ax_actor_asset_cache    identity_id, asset_role avatar|header, source_uri(+hash),
                           content_hash (NULL until ready), source_etag/last_modified/mime/w/h/byte_size,
                           variants_json, processor_version, fetch_status pending|ready|stale|error,
                           fetched_at, expires_at, next_refresh_at, last_accessed_at, failure_count, last_error_code
                           UNIQUE(identity_id, asset_role)
                           KEY(source_uri_hash) KEY(content_hash, processor_version)
                           KEY(next_refresh_at, fetch_status) KEY(last_accessed_at)
```
A **single** map/cache table; physical dedup is handled by the content-addressed file
path (`…/actors/v{processor}/{ab}/{cd}/{content_hash}/{role}-{size}.{ext}`), so GC =
*no row references this `(content_hash, processor_version)`* → sweep after a grace
period. Never a `WP_User` attachment (no Media Library pollution). Originals are not
retained — validate + derive, then delete. `content_hash` is `NULL` in `pending`/`error`
and **required once `ready`**. Downloads are asynchronous, stale-while-revalidate; a
successful row has no timer refresh and is queued again only when a verified Actor
snapshot changes its image source (or an administrator explicitly rebuilds it). The
render path never blocks on a fetch and falls back to the default avatar / no header.
Full contract (path, refresh, SSRF/MIME/pixel limits, derivative sizes, purge/uninstall)
in **REMOTE-ASSET-CACHE.md**. The administrator-only remote profile preview consumes
these local derivatives and never hotlinks the remote source.

### 9.6 DB v10a — keys + remote fetch state *(implemented)*
```
wp_ax_actor_keys           key_uri(+hash UNIQUE), key_type, public_key_pem, fingerprint,
                           private_key_ref, status active|retired|revoked, valid_from/until
wp_ax_actor_fetch_state    identity_id PK, payload_hash, etag, last_modified,
                           fetched_at, last_success_at, next_refresh_at, failure_count, last_error_code
```
Keys are **multiple rows keyed by key URI**, so rotation retires the prior key as
history (never an overwrite); a keyless refresh is a no-op (a partial fetch must not
wipe a known key). Discovery owner-checks each declared `publicKey` (a payload cannot
smuggle a foreign key) and writes it inside the snapshot transaction. A **local private
key is never stored in plaintext** — `private_key_ref` points at separate secret
storage. Fetch-state is best-effort substrate for a future background refresher
(payload hash, HTTP validators, one-day horizon, capped exponential backoff); Actors
does no scheduling, signature verification, or delivery.

### 9.6b DB v10b — identity relations *(shipped; stored schema version 10.1)*
```
wp_ax_identity_relations   relation_type also_known_as|moved_to, target_uri(+hash),
                           verification_state, verified_at   UNIQUE(identity_id, relation_type, target_uri_hash)
```
`alsoKnownAs` / `movedTo` are stored **observed/unverified** first and **never trusted
on inbound JSON alone** — cross-reference / a Move verification flow (owned by
Federation) promotes or rejects them. Missing claims on a later/partial fetch do not
erase evidence, and another inbound snapshot cannot downgrade a verified decision.

#### 9.6.1 DB v11 — managed actors
`wp_ax_actor_managers(identity_id, user_id, role owner|manager|editor, created_at,
updated_at, PRIMARY KEY (identity_id, user_id), KEY (user_id, role))`, added **only
when** Group / Service / Organization actors are actually created; `actor_scope=
'managed'` activates then (SPEC §4.1). The current Site/User actors do not need it.

The table holds **only active** management relations: `revoke = row delete`, so every
authority read is a plain existence/role check — no `revoked_at IS NULL` to remember,
no re-grant collision. `granted_by` and grant/revoke history are deliberately **not**
columns here; that audit belongs to a later Forum moderation/admin event log, never
half-carried on the relation row. `created_at` / `updated_at` alone keep "manager
since" and role-change recency. Invariants (locked at F0): every managed `Group`
always has **≥1 `owner`**; an owner may not remove or demote the last owner; owner
transfer UI is out of F0 scope. **Version numbers are implementation order,
not schema dependencies** — if a Lemmy-style community (`Group`) ships before
federation, pull this forward to just before that managed-Group work; nothing in
v5–v10 depends on it. (A `Group` community is `Application` site actor's sibling: its
posts/comments live in the object/activity store and its Follow / Accept / Announce /
moderation in the social layer — §9.7 — never on the actor row; a shared folder stays
an `OrderedCollection` + ACL and is *not* promoted to a `Group`.)

### 9.7 Owned by OTHER stores, never by Actors
`wp_ax_activities`, `wp_ax_deliveries`, `wp_ax_follows`, `wp_ax_reactions`,
`wp_ax_notifications` → a separate **Activity / social-relation** store. Collections,
memberships, and saved references → **Media Library**. Actors provides identity
lookup + the actor profile only; the followers / following endpoint *content* is
**projected** from the relation store, never stored on the actor row (counts included).

### 9.8 Multisite scope is a runtime decision, not a stored column
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

### 9.9 WebFinger `acct:` handle policy — DECIDED: fail-closed

`acct:` collisions across a network are resolved by **policy, not schema**. The policy
is **fail-closed**: WebFinger is enabled only where each site owns a distinct
authority, and is *disabled* (not guessed) where it would collide.

- **Enabled — single-site, subdomain, or mapped-domain.** `alice@example.com`,
  `alice@site-a.example`, `alice@mapped-domain.com` each resolve to exactly one actor
  because the authority differs.
- **Disabled — subdirectory multisite** (`example.com/site-a`, `example.com/site-b`).
  Both handles would be `alice@example.com`, which cannot resolve to two actors, so the
  WebFinger endpoint is **explicitly off** for such subsites (a tested behaviour, not a
  silent misroute). The **Actor URI (`/actors/{uuid}`) and the profile keep working** —
  only WebFinger discovery by `acct:` is withheld.
- A **network-wide handle table** + representative-owner routing (`wp_ax_actor_network_index`
  §9.8) is added **only when a real subdirectory-federation need appears** — not
  pre-built. Until then, a subdirectory network that wants federation gives each subsite
  its own domain / subdomain (which flips it to *enabled*).

`wp_ax_actor_addresses` (v5) records the `acct:` addresses this policy allows; it does
not decide the policy. The endpoint enabling/disabling and the subdirectory-off test
are the **WebFinger increment** after v5.

### 9.10 Instance / NodeInfo host ledger — `wp_ax_instances` *(shipped, DB v6)*

Three discovery roles are **distinct**: **WebFinger** (`@user@host` → an Actor URI),
**Actor JSON-LD** (a remote actor → `wp_ax_identities` / `wp_ax_actors`), and
**NodeInfo** (`/.well-known/nodeinfo` → a *host's* software / version / registration
policy / usage). NodeInfo is **not required** to store a remote actor, but it drives
the instance-info screen and compatibility decisions (e.g. Misskey's instance-info
shows `softwareName` / `softwareVersion` / description / open-registrations).

Host metadata is **per host, not per actor** — never put it on an actor row (N actors
would duplicate one host's data). It goes in a dedicated ledger, added with **remote
discovery/fetch** (around v7):

```
wp_ax_instances
- id
- host, host_hash UNIQUE
- base_uri
- software_name, software_version, nodeinfo_schema
- name, description, icon_uri
- open_registrations
- fetched_at, fetch_status
- payload_json
```

- **Local NodeInfo** (our site advertising itself at `/.well-known/nodeinfo` +
  NodeInfo 2.1) needs **no table** — build it from WP options + live counts.
- **Moderation is a separate local layer, not NodeInfo.** Misskey's `isBlocked` /
  `isSilenced` / `moderationNote` are *local policy about* a host, not host metadata —
  they belong to a moderation store, never in `wp_ax_instances` (which is a fetched
  cache of the remote's own declarations).

**Order:** WebFinger endpoint (+ subdirectory fail-closed test §9.9) → local NodeInfo
2.1 → bounded remote Actor discovery/fetch *(shipped)* → then `wp_ax_instances` +
its NodeInfo cache. Background refresh/backoff remains a Federation/fetch-state phase.

### 9.10.1 Instance availability — a separate operational axis *(contract locked; NOT built)*

A host going away is an **operational** fact about *our* reach, not a statement the
remote made about its objects. It therefore gets its own axis on the instance ledger:

```
instance availability:  active | degraded | unreachable | archived
```

- **Never conflate availability with `tombstone`.** `tombstone` is a *per-object*
  lifecycle state declared by **remote authority** — an HTTP `410 Gone` or an explicit
  AS2 `Tombstone` for **that** object (§2 `status`, remote-object `object_status`). An
  instance outage must **never** tombstone its actors: the host may return, and the
  actor never declared itself gone. Marking actors dead from our own infrastructure's
  view would fabricate remote intent.
- **NodeInfo failure is a hint, never a verdict.** Many live fediverse servers never
  serve NodeInfo at all, and a failure is equally explained by a transient outage, a
  CDN/WAF block, a DNS blip, or rate limiting. A verdict requires **sustained,
  multi-signal** evidence (long-running DNS/HTTP failure, repeated delivery failure)
  **plus an explicit administrator decision**. The default is always preserve.
- **What survives an `unreachable` / `archived` host:** actors, relations, and the
  **Activity ledger are kept**. The ledger is *our own* audit history (what we sent,
  what they did to us); and because every reference is a URI rather than a physical FK,
  it stays intact even without an actor row — that is the design's intent, not a gap.
- **Remote objects are cleaned by the existing lease model, not by a host purge.**
  Unreferenced observations expire out on their own; objects held by an
  `interaction` / `collection` / `shared_shadow` lease are retained because a local
  user cared about them. A host-wide bulk delete would delete exactly those protected
  rows, so it is **not** the mechanism.
- **`archived` stops network retries only.** It is not deletion and changes no object's
  lifecycle state. Returning to `active` is an explicit administrator action.
- **Space reclamation order, only when it is a real problem:** asset binaries first
  (genuinely disposable), then **unreferenced objects only** (lease-checked,
  fail-closed). Never an indiscriminate per-host delete.
- **Remember the inversion:** a cache is normally disposable *because it is
  re-fetchable*. Once a host is gone, our cached objects are the **only remaining
  copy** — deletion becomes permanent. That argues for archive-by-default, not purge.

**Not implemented, deliberately.** When a real shutdown case appears, add to
`wp_ax_instances` an availability column **plus last-success / last-failure evidence**
(what failed, when, for how long) — evidence first, verdict second. Availability is
also distinct from moderation (`isBlocked` / `isSilenced`, above): one is "can we reach
this host", the other is "do we choose to talk to it".

### Stays in `payload_json` (resolver-read, never columnized)
`memorial`, `showFeatured` / `showMedia` / `showRepliesInMedia`, `interactionPolicy`,
`attributionDomains`, all `misskey:*` and `vcard:*`, `tag` (Emoji / Hashtag are
polymorphic), `attachment` (Mastodon profile `PropertyValue` links — unrelated to
Media Library attachments), and object-or-array `icon` / `image`. A remote profile's
`sensitive` flag is its own thing, **not** the Media Library sensitive-authority.

**vCard** is not a schema concern: a future `/@handle/contact.vcf` can map
already-public Actor fields to vCard 4.0 (birthday / address only behind an explicit
opt-in field). No `vcard_*` columns.

## 아이덴티티가 컨테이너다 (오너 결정, 2026-11-01)

JMAP Account 관점에서는 **무성적 Account 컨테이너가 먼저이고, 액터 메타데이터가 거기에
얹힌다.** 우리는 Actor 가 primary model 이라 Account 가 Actor 에 매달려 있고, 그것이 우리에게
맞다 -- Actors 가 로컬과 리모트를 한 아이덴티티 공간에 쥐고, **리모트는 Actor facet 만 있고
Account 가 없다.** account-first 로 뒤집으면 그 경우가 들어갈 자리가 없다. 아이덴티티를
컨테이너로 두는 지금 모양이 더 일반적이다.

### 측정 (2026-11-01)

```txt
ax_jmap_accounts 890 행  =  로컬 액터 890        Account facet 은 로컬에 보편적이다 (선택 아님)
                                                 Add Local Actor 와 Add Account 는 이미 같은 동작
핸들 facet    로컬 840 중 594 가 핸들 없음        추가적이다.  없는 것이 정상 상태
타입 facet    actor_type varchar(16) NOT NULL     필수다.  기본값 없음, 빈 값 0건
              Person 652 · Organization 157 · Group 79 · Application 2
```

### 결정 (2026-11-01, 수정): 타입은 필수, 핸들은 나중

같은 날 앞서 "생성은 아무것도 묻지 않는다 / 타입은 Person 기본값" 으로 적었던 것을 **대체한다.**
오너 결정은 그 반대다 -- **타입에는 `*` 를 붙여 필수로 하고, 핸들은 나중에 추가할 수 있게 한다.**

```txt
필수   actor_type   화면에 * 로 명시한다.  핸들러는 이미 in_array 로 검증하고 있다
       name         현재 필수 유지
나중   handle       생성 시점에 받지 않는다
       summary · status  이미 선택이다
```

근거는 **핸들이 WebFinger 를 켜는 스위치**라는 것이다. 핸들이 붙어야 `acct:` 가 성립하고 외부
인스턴스에서 발견된다. 그래서 "계정을 만드는 일" 과 "외부에 드러내는 일" 이 두 단계로 갈린다.

측정이 이 모양을 이미 뒷받침한다.

```txt
로컬 840 중 594 가 핸들 없음        핸들 없는 Account 는 표현 가능하고 다수다
handle_locked_at 이 외부 노출을 쥔다  멘션 검색 · NodeInfo 공개 액터 수 ·
                                     공개 Group 디렉터리 · 원격 팔로우 대상
admin.php:1229 이 register_handle    나중에 붙이는 경로가 이미 있다
```

#### 이 수정이 보증을 약하게 만든다

앞 판본은 "생성 경로가 타입을 묻지 않으므로 타입으로 분기할 수 없다" 는 **구조적 불가능**을
근거로 삼았다. 타입을 필수로 받으면 그 보호가 사라진다. 개인 귀속이 타입과 무관하다는 것은
이제 **규칙**이지 불가능이 아니다.

```txt
규칙   personal_owner_user_id 는 actor_type 과 무관하게 생성자로 배정한다
집행   감사가 단언한다.  변이로 검증한다 -- UI 로 Group 을 만들면 생성자 세션에서
       isPersonal: true 여야 하고, 타입 조건부 배정으로 되돌리면 실패해야 한다
```

거래는 명시적이다. 타입을 생성 시점에 **의도적으로 고르게** 하는 값과, 그 자리를 버그에
열어 두는 비용을 맞바꾼다. 기본값 Person 은 대부분의 Account 가 우연히 Person 이 되게 하므로
오너가 그쪽을 택하지 않았다.

#### 타입은 Account 의 운용 목적이다 (2026-11-01)

타입을 생성 시점 필수로 두는 이유는 제품 판단만이 아니다. **타입별로 운용 정책이 정해지면
전환에 부작용이 생기므로, 타입은 사실상 고정이다.** Account 를 만들 때 그 운용 목적을
정하는 것으로 본다.

규칙을 목록이 아니라 **판정식**으로 둔다. 목록은 정책이 하나 추가되는 순간 낡는다.

```txt
판정   이 타입에 걸린 운용 정책이 있는가?
       있다  -> 생성 후 고정.  전환은 그 정책이 전제한 관계를 소급해서 바꾼다
       없다  -> 지금은 전환 가능.  정책이 생기면 그때 고정된다
의무   타입에 운용 정책을 추가하는 변경은 아래 스냅샷을 함께 갱신한다
       (갱신하지 않으면 이 문서가 거짓이 된다 -- 2026-11-01 에 그런 주석·라벨을 네 건 고쳤다)
```

2026-11-01 스냅샷. **파생된 것이고 권위가 아니다** -- 위 판정식이 권위다.

```txt
고정   Group        수락된 Follow 이 곧 멤버십이다 -- Lemmy 에 Join 이 없어서 그렇게 정했다
                    (axismundi_act_local_follow_target_is_group).  Forum 은 managed Group
                    에만 바인딩한다.  떠나면 전제가 사라지고 저장된 행만 남는다.
                    들어가는 방향도 같다 -- 팔로워를 가진 Person 이 Group 이 되면 수락된
                    Follow 들이 소급해서 멤버십이 된다
       Application  인스턴스 역할을 나른다 (SYSTEM-ACTOR.md)
전환 가능
       Person <-> Organization   구분하는 운용 정책이 아직 없다
       Service                   분기하는 코드 0건, 인스턴스 0개(로컬·원격).  UI 라벨만 있다
```

**되는 것도 그만두는 것도 안 되면 처음에 정해야 한다.** 그것이 타입을 필수로 두는 이유이고,
기본값 Person 이 택해지지 않은 이유다.

연합 쪽 결과는 핸들 변경과 같은 모양이다 -- 전달은 Actor URI 로 유지되고 원격이 캐시한
`type` 이 재조회까지 어긋난다. `former_handle` 같은 예약 장치는 필요하지 않다. 핸들에 그것이
필요했던 이유는 **핸들이 주소**였기 때문이고, 타입은 주소가 아니다.

`axismundi_actors_set_actor_type()` 은 현재 5개 타입 검증 외에 가드가 없다. 위 판정식과
시스템 Actor 거부가 거기 들어가야 한다.

### 미결: 무성적 first 는 정합성 있으나 지금은 아니다

타입을 **아예 없는 상태로** 두는 쪽이 더 정합적이다 -- 그러면 위 버그가 표현 불가능해진다.
비용은 `actor_type` nullable(또는 센티넬) + 타입으로 분기하는 소비자 전수다(예: 포럼의
`'Person' !== get_type()` 사이트들). 기본값 Person 은 **그 성질을 스키마 변경 없이 얻는
버전**이고, 무성적 first 는 비용을 볼 수 있을 때 판단한다.
