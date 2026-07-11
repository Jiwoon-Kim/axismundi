# Axismundi Media Library — Data Model

> Status: **Design draft (pre-code).**
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

### 2.1 Attachment post meta — **written & enforced** in 0.1.0

```
_ax_media_owner_id            int      uploader (authoritative for permission)
_ax_media_visibility          enum     public | unlisted | private   (protected = Phase 3)
_ax_media_listed              bool     appears in archives
_ax_media_searchable          bool     appears in media search
_ax_media_captured_at         datetime EXIF DateTimeOriginal if present
_ax_media_uploaded_at         datetime actual upload time
_ax_media_first_published_at  datetime first made public
_ax_media_date_source         enum     exif | manual | storage-context | upload-time
```

`post_status` stays **`inherit`** for all Attachments (SECURITY.md §1). Visibility
is enforced by the plugin, not by post status.

### 2.2 Attachment post meta — **stored only** in 0.1.0 (enforced Phase 4)

Written by the editor UI so nothing is lost, but **not yet enforced**; 0.1.0 must
not *claim* protection for these (esp. GPS — SPEC.md Invariant 8):

```
_ax_media_creator            _ax_media_copyright_holder   _ax_media_copyright_notice
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
_ax_media_folder_id        term_id or 0 (root = no term)
_ax_media_folder_added_at  datetime (drives folder-feed pubDate)
```

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
store beyond the `_ax_media_folder_id` convenience mirror (kept only if a hard
single-relation guarantee needs it — decide at Phase 2).

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
resolve( attachment_id, current_user ) →
  input:  _ax_media_owner_id, _ax_media_visibility,
          current_user caps (owner? edit_others_posts?),
          (Phase 3) protected-challenge session
  output: allow | not_found(404) | challenge     (+ listed/searchable for list surfaces)
```

Rules: owner → allow (any level); `edit_others_posts` → allow; else per
SECURITY.md §3 matrix. `private` → `not_found` (existence hidden). No global
query filter; surfaces inject the resolver's `meta_query` via the shared
`Visibility_Query` service.

## 7. Identity fields (reserved formats)

```
object_uri   /?ax_media_object={id}     immutable; format reserved, NOT persisted in 0.1.0
attachment_id (DB primary)              always present
file URL     wp_get_attachment_url()    immutable after upload
permalink    /?attachment_id={id}       mutable HTML canonical (ROUTING.md)
```
