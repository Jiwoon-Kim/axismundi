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
_ax_media_visibility          enum     public | unlisted | private   (protected = Phase 3)
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

### 2.3 Options

```
ax_media_relationship_mode    core | independent
ax_media_prev_attachment_pages (stored prior wp_attachment_pages_enabled value)
ax_media_reserved_slugs        (owner-slug collision guard; ROUTING.md §3.1)
ax_media_delete_data_on_uninstall (bool, default false)
```

## 3. Virtual folders — **Phase 2** (taxonomy exists then, not 0.1.0)

```
taxonomy: ax_media_folder   hierarchical: true   object_type: attachment
```

Term meta:

```
_ax_media_folder_owner_id        _ax_media_folder_visibility     (public|unlisted|protected|private)
_ax_media_folder_password_hash   _ax_media_folder_cover_id       _ax_media_folder_sort_mode
_ax_media_folder_default_license _ax_media_folder_default_reuse_policy
_ax_media_folder_default_sensitive  _ax_media_folder_feed_enabled
```

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

## 4. Used-in relations — **Phase 3, PROVISIONAL schema (not created in 0.1.0)**

Replaces `post_parent` with a many-to-many usage index. **Schema reserved, not a
0.1.0 table contract; columns may change before Phase 3.**

```
wp_ax_media_relations   (provisional)
  id · attachment_id · object_id · object_type · relation_type
  · block_client_id · created_at · updated_at
relation_type: legacy_parent | featured_image | block_reference | gallery_item | embed_reference
```

`legacy_parent` (origin) and the live `used_in` set are never merged.

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
          (Phase 3) protected-challenge session
  output: allow | not_found(404) | challenge     (+ listed/searchable for list surfaces)
```

Rules: `user_id > 0 && user_can( user_id, 'edit_post', id )` → allow (owner or
`edit_others_posts`, via core cap mapping); else per SECURITY.md §3 matrix + §2.1
predicates. Permission keys on **post_author**, not creator/copyright. `private` →
`not_found` (existence hidden). `user_can( 0, … )` is false, so uid 0 never matches
author 0. Absent meta → legacy defaults (§2.1.1). No global query filter; list
surfaces inject the `meta_query` (with the `NOT EXISTS OR == value` legacy form)
via the shared `Visibility_Query` service, and "mine" is `post_author = uid`
(uid > 0).

## 7. Identity fields (reserved formats)

```
object_uri   /?ax_media_object={id}     immutable; format reserved, NOT persisted in 0.1.0
attachment_id (DB primary)              always present
file URL     wp_get_attachment_url()    immutable after upload
permalink    /?attachment_id={id}       mutable HTML canonical (ROUTING.md)
```
