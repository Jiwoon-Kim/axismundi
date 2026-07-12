# Axismundi Media Library — Data Model

> Status: **Living specification. Phase 0 and Phase 1a are implemented.**
> **Read this distinction first:** §2 is data the plugin actually creates in
> 0.1.0. §3–§5 are **reserved schema** for later phases — documented so the model
> is stable, but **not** table-creation or storage contracts yet. Do not create
> §4/§5 tables in 0.1.0.

## 1. Conventions

- Attachment post meta prefix: **`_ax_media_*`**
- Folder taxonomy: **`ax_media_folder`** · its term meta: **`_ax_media_folder_*`**
- One prefix family only; no mixed `_ax_folder_*` vs `_ax_media_folder_*`.
- All local queries key on **`attachment_id`** (SPEC.md §3). `object_uri` is
  identity for federation, not a local lookup key.

## 2. Created in 0.1.0 (Phase 0 + 1)

### 2.0 Subjects (owner vs rights metadata)

Ownership is the WordPress author, **`post_author`** — the single source of truth.
There is **no separate owner meta**: this reuses core capabilities and author
queries, removes owner/author drift, and needs no legacy owner fallback.

- **Ownership transfer** = change `post_author` (`wp_update_post`).
- **Permission** = core `edit_post` (author *or* an `edit_others_posts` holder).
- Admin UI may label `post_author` as "Owner".

The other subjects are rights metadata that may name an external person/org, not
a site user:

| Concept | Field | Permission effect |
|---|---|---|
| Current owner / WP author | `post_author` | **owner**; core `edit_post` / `edit_others_posts` |
| Creator | `_ax_media_creator_*` | rights/display; **not** a WP capability |
| Copyright holder | `_ax_media_copyright_holder_*` | rights/display; person, org, or outsider |
| Source | `_ax_media_source_url` | display/provenance |

> `post_author` is the current media owner and initially identifies the uploader.
> Ownership transfer changes `post_author`, so it is not an immutable uploader
> audit field. Creator and copyright-holder fields describe
> authorship and legal rights; they do not grant WordPress capabilities.

`post_author = 0` (legacy/import) means **unowned**, not "owned by user 0" — a
logged-out uid of 0 must never be treated as the owner. If an immutable uploader
record is ever needed, add `_ax_media_original_author_id` at the first transfer —
reserved, not built now. Ownership transfer itself is a later phase (PHASES.md).

### 2.1 Attachment post meta — **written & enforced** in 0.1.0

```
_ax_media_visibility          enum     inherit | public | unlisted | private
_ax_media_listed              bool     eligible for archives (gated with public)
_ax_media_searchable          bool     eligible for search (gated with public)
_ax_media_captured_at         datetime EXIF DateTimeOriginal if present
_ax_media_uploaded_at         datetime actual upload time
_ax_media_first_published_at  datetime first made public
_ax_media_date_source         enum     exif | manual | storage-context | upload-time
```

`post_status` stays **`inherit`** for all Attachments (SECURITY.md §1). Visibility
is enforced by the plugin, not by post status.

### 2.1.1 Legacy fallback (existing media has no `_ax_media_*` meta)

The resolver and every list `meta_query` MUST assume defaults when meta is absent,
or existing Attachments vanish/misresolve the instant a filter is added:

```
owner       → post_author               (permission via core edit_post; uid 0 != author 0)
visibility  → public (legacy-public)   when _ax_media_visibility absent
listed      → true                     when absent
searchable  → true                     when absent
```

List `meta_query` for visibility MUST be `( NOT EXISTS ) OR ( == public )` —
never a bare `== public` (which drops legacy rows). Independent mode creates
policy meta only when explicitly edited; legacy rows remain legacy-public.

### 2.2 Attachment post meta — **stored only** in 0.1.0 (enforced Phase 4)

Written by the editor UI so nothing is lost, but **not yet enforced**; 0.1.0 must
not *claim* protection for these (esp. GPS — SPEC.md Invariant 8):

```
# Creator / copyright are structured — a subject may be a site user OR an
# external person/org, so user_id is OPTIONAL (name + url stand alone).
_ax_media_creator_name           _ax_media_creator_user_id (opt)    _ax_media_creator_url
_ax_media_copyright_holder_name  _ax_media_copyright_holder_user_id (opt)  _ax_media_copyright_holder_url
_ax_media_copyright_notice
_ax_media_license            _ax_media_license_url        _ax_media_attribution
_ax_media_source_url         _ax_media_reuse_policy       _ax_media_download_policy
_ax_media_sensitive          _ax_media_content_warning    _ax_media_sensitivity_reason
_ax_media_geo_visibility     (public | approximate | hidden)  ← flag only; EXIF strip = Phase 4
_ax_media_federated          (bool; reserved for Phase 7)
```

**Sensitive is state + authority (shape locked now, enforced Phase 4).**
`_ax_media_sensitive` is a **derived, read-only effective/compat boolean** that
serializers + UI read; the *authority* record decides who may change it:

```
_ax_media_sensitive_state   none | self_marked | automated_flagged | moderator_marked | confirmed
_ax_media_sensitive_set_by  _ax_media_sensitive_set_at   (_ax_media_sensitivity_reason above)
_ax_media_sensitive_locked  derived: moderator_marked/confirmed ⇒ locked (user cannot clear)
```

self_marked → user may clear; automated_flagged → appeal only (pending moderation),
no self-clear; moderator_marked/confirmed → user cannot clear. Caps
`mark_own_media_sensitive` / `moderate_media_sensitivity` / `override_media_sensitivity`.
This keeps `_ax_media_sensitive` as a compat value so migration is incremental. See
SECURITY.md §2.4 and FEDERATED-MEDIA.md §6.

### 2.4 Container & membership schema — **provisional (Phase 5/7)**

Keyed on local IDs + canonical URIs (URIs as optimization/federation pointers, mirrors
§2.0). Not a 0.1.x table contract — see FEDERATED-MEDIA.md §3. The **relation index**
lives in §4 (`wp_ax_media_relations`, dual-key); it is not URI-only.

```
container item   container_id · container_kind(personal_folder|shared_folder|collection)
                 · relation(location|bookmark) · object_uri · local_attachment_id?
                 · added_by_actor_uri · added_at · sort · license_at_save/reuse_at_save
folder member    folder_id · principal(local_user|remote_actor) · actor_uri · role · status
```

### 2.3 Options

```
ax_media_relationship_mode    core | independent
ax_media_prev_attachment_pages (stored prior wp_attachment_pages_enabled value)
ax_media_reserved_slugs        (owner-slug collision guard; ROUTING.md §3.1)
```

No delete-on-uninstall option is reserved. Automatic purge is forbidden; the
explicit post-roadmap reset contract is defined in COMPATIBILITY.md §6.1.

## 3. Virtual folders — **Phase 2** (taxonomy exists then, not 0.1.0)

```
taxonomy: ax_media_folder   hierarchical: true   object_type: attachment
```

Term meta (implemented names; Phase 2a marks the structural ones):

```
_ax_media_folder_owner        owner user ID                       (Phase 2a)
_ax_media_folder_root         hidden per-user root marker = uid   (Phase 2a)
_ax_media_folder_tier         inherit | public | unlisted | private   (Phase 2a resolver)
_ax_media_folder_effective_tier_rank  0 | 1 | 2 (derived chain cache; Phase 2a)
_ax_media_folder_access       open | password                         (Phase 2b gate)
_ax_media_folder_password_hash                                        (Phase 2b)
_ax_media_folder_effective_gated  0 | 1 (derived chain cache; Phase 2b)
_ax_media_folder_cover_id     _ax_media_folder_sort_mode              (later)
_ax_media_folder_default_license _ax_media_folder_default_reuse_policy (later)
_ax_media_folder_default_sensitive  _ax_media_folder_feed_enabled     (later)
```

**Per-user hidden root** — each user's top-level folders are parented to a hidden
root term (`_ax_media_folder_root = uid`) so two users can both have a top-level
`Travel` (WordPress term names collide only within the same parent). The root is
never shown or assignable.

### 3.1 Folder visibility — tier + gate (narrow-only)

Two orthogonal dimensions (do **not** collapse into one linear scale):

```
tier   (discovery/reach, linear):   public = 0  <  unlisted = 1  <  private = 2
access (authentication, orthogonal): open | password
```

Attachment visibility gains an explicit `inherit` value; `protected` is **folder-
only** (an attachment that needs a password goes in a one-item password folder —
no per-item password/cookie/form). Effective resolution walks the whole folder
chain root→assigned:

```
folder_chain_tier = max( rank(folder.tier) for folder in chain, skipping inherit )
                    ( empty / all-inherit chain → public/0 )
item_tier = (attachment.visibility === 'inherit')
              ? folder_chain_tier
              : max( rank(attachment.visibility), folder_chain_tier )   // narrow only
gated     = OR( folder.access === 'password' for folder in chain )
```

`_ax_media_folder_effective_tier_rank` is a derived cache of
`folder_chain_tier`, not an authoring field. Folder create, tier change, move, or
reparent MUST refresh the affected subtree. Single-item PHP resolution may repair
a missing cache lazily; collection SQL reads the cache so it never performs a
recursive parent walk per Attachment.

`_ax_media_folder_effective_gated` is the equivalent derived OR-cache for
collection SQL. Unlock state is not stored in the database: each authored
password folder has its own signed HttpOnly cookie, and nested password folders
are unlocked in root-to-leaf order.

A folder can only **narrow** an item, never widen it (invariant ⑥). `private` beats
any password (a password never unlocks `private`). See SECURITY.md §2.3 for the
per-surface processing order.

Attachment↔folder relation on the Attachment (Phase 2):

```
_ax_media_folder_added_at  datetime (drives folder-feed pubDate; the term
                           relation itself carries no timestamp)
```

The **current folder is the `ax_media_folder` term relation** (single, enforced
below) — the sole source of truth. No `_ax_media_folder_id` mirror in Phase 2;
add one only if profiling proves the term join is a bottleneck.

**Single-relation enforcement** (taxonomy is many-to-many; the service layer
forces one):

```php
wp_set_object_terms(
    $attachment_id,
    $folder_term_id ? [ $folder_term_id ] : [],   // root = none
    'ax_media_folder',
    false                                          // replace, never append
);
```

Taxonomy is the source of truth; do **not** duplicate the relation into a second
store. `_ax_media_folder_added_at` is a timestamp, not a copy of the relation.

## 4. Used-in relations — `wp_ax_media_relations` (Phase 3, dual-key)

Reverse index of the ActivityStreams `attachment`/`image`/`icon`/`schema:associatedMedia`
relations (FEDERATED-MEDIA.md §7) — **not** a `post_parent` replacement, **not** "file
URL appears in HTML". **Dual key**: local IDs are the source of truth in the local
phase; canonical URIs are stored **only when derivable**, so Phase 7 federation reads
the same rows without minting `object_uri`s now (URI-only would over-couple to the
not-yet-active identity endpoint — SPEC §3).

```
wp_ax_media_relations                         ENGINE=InnoDB
  id
  relation_kind         usage | legacy_parent          (never merged in query/UI)
  subject_type          post | remote_object
  subject_post_id       nullable   local source of truth
  subject_uri           nullable   federation identity
  subject_uri_hash      BINARY(32) nullable   sha256(subject_uri) — remote reverse lookup
  subject_key           BINARY(32) NOT NULL   sha256("post:{id}" | "uri:{uri}") — dedup identity
  predicate             as:attachment | as:image | as:icon | schema:associatedMedia
  object_attachment_id  nullable   local media pointer
  object_uri            nullable   canonical identity
  object_uri_hash       BINARY(32) nullable   sha256(object_uri) — remote reverse lookup
  object_key            BINARY(32) NOT NULL   sha256("attachment:{id}" | "uri:{uri}") — dedup identity
  role                  featured | content | gallery | cover | media_text | file
                        | audio | video | poster | decorative
  provider              featured_image | block_content | shortcode | integration
  source_key            first occurrence's provider-stable key (block path / meta key)
  occurrence_count      UNSIGNED NOT NULL DEFAULT 1   ("used 3× in this post")
  origin                local | remote
  status                active | inactive
  created_at · updated_at
```

**Why `*_key`, not the nullable local-id + URI-hash, for dedup:** MySQL UNIQUE treats
`NULL` as distinct, so a unique index over nullable columns would let the *same*
relation insert repeatedly. `subject_key`/`object_key` are **NOT NULL** normalized
identities (local `post:`/`attachment:` **or** remote `uri:`), computed in PHP, so one
UNIQUE index dedups local and remote uniformly. `*_uri_hash` stay only for remote
reverse lookup.

```
UNIQUE  relation_identity (relation_kind, subject_key, object_key, predicate, role, provider)
KEY     subject_provider  (relation_kind, subject_key, provider)
KEY     reverse_local     (relation_kind, status, object_attachment_id)
KEY     reverse_uri       (relation_kind, status, object_uri_hash)
KEY     subject_post      (subject_post_id)
```

Reverse lookup ("used in") = `object_attachment_id = ? AND relation_kind='usage' AND
status='active'` (local) or `object_uri_hash = ?` (remote). Never index the long raw
URI.

**Atomic replace** (`axismundi_media_relations_replace(subject, provider, relations[])`):
InnoDB transaction — dedup+aggregate the input by `(object_key, predicate, role)`
(summing `occurrence_count`), `DELETE` the existing `(subject_key, provider,
relation_kind='usage')` rows, `INSERT` the fresh set, rollback on any error. An **empty**
input is valid — it removes that provider's rows for the subject.

**Locked contracts:**
- `usage` and `legacy_parent` may share the table but are **never merged** in query or
  UI (`relation_kind` separates them).
- **No relation from a bare file URL in HTML** — only real object references (block
  attrs, `_thumbnail_id`, shortcode ids, provider filters); URL→id scanning is
  excluded from the default indexer (slow, false-positive-prone).
- **Dedup per subject**: the same media reused many times in one post yields one entry
  per `(subject, object, predicate, role, provider)`; different roles stay distinct.
- **Atomic per `(subject, provider)` replace**: reindexing a subject deletes its
  existing `(subject, provider)` rows and inserts the fresh set in a transaction —
  idempotent.
- **Read security**: Used-in filters subjects by the viewer's read access; an owner
  never sees the title/URL of a source they cannot read (admins may).
- Providers return a **pure normalized relation array**; the same model feeds the index
  and the (Phase 7) JSON-LD serializer, which additionally filters by public/rights/
  decorative policy (internal index **⊋** federated projection).

Phase 3 sub-split: **3a** table + store + fixture · **3b** providers + incremental
hooks · **3c** reindex CLI + Attachment-Details `Location`/`Used in`/`Saved in` · **3d**
`legacy_parent` snapshot + `post_parent` removal preview/rollback (a **separate**
execution from index build). See PHASES.md Phase 3.

## 5. Saved references — **Phase 5, PROVISIONAL schema (not created in 0.1.0)**

Bookmark/shortcut to a local or remote object. **Schema reserved, not a 0.1.0
table contract.**

```
wp_ax_media_references  (provisional)
  id · object_uri (NOT NULL) · local_attachment_id (nullable)
  · saved_by_user_id · destination_folder_term_id
  · license_at_save · reuse_policy_at_save
  · cached_metadata · saved_at · last_checked_at
UNIQUE(saved_by_user_id, object_uri)
```

Identity = `object_uri`; `local_attachment_id` is a local optimization pointer.
Local objects are read live by id; only remote objects cache metadata.

## 6. Visibility resolver (contract used by SECURITY.md)

Pure function; inputs → decision. Same result on every surface (each surface owns
where it calls this, per SECURITY.md §4):

```
resolve( attachment_id, user_id ) →
  input:  owner = post_author,
          visibility = _ax_media_visibility ?? public (legacy default),
          (Phase 2) folder chain → folder_chain_tier + gated  (§3.1),
          (Phase 2b) password-challenge session
  output: allow | not_found(404) | challenge     (+ listed/searchable for list surfaces)
```

Rules: `user_id > 0 && user_can( user_id, 'edit_post', id )` → allow (owner or
`edit_others_posts`, via core cap mapping — this also bypasses the password gate for
management); else fold the folder chain (§3.1: `item_tier = max(...)`, `gated =
OR(...)`) and apply SECURITY.md §2.3 order, then the §3 matrix + §2.1 predicates. Permission keys on **post_author**, not creator/copyright. `private` →
`not_found` (existence hidden). `user_can( 0, … )` is false, so uid 0 never matches
author 0. Absent meta → legacy defaults (§2.1.1). No global query filter; each
owned list surface opts into the shared visibility SQL, which excludes explicit
unlisted/private items, derived folder ranks above 0, and `listed=0` while
preserving rows with absent legacy meta. "Mine" is `post_author = uid` (uid > 0).

## 7. Identity fields (reserved formats)

```
object_uri   /?ax_media_object={id}     immutable; format reserved, NOT persisted in 0.1.0
attachment_id (DB primary)              always present
file URL     wp_get_attachment_url()    immutable after upload
permalink    /?attachment_id={id}       mutable HTML canonical (ROUTING.md)
```
