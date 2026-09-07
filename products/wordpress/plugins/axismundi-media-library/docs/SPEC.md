# Axismundi Media Library — Specification

> Status: **Living specification. Phases 0–4 are implemented through v0.0.21.**
> Plugin brand: **Axismundi Media Library** · Admin app: **Media Drive**
> This document defines the product, its invariants, the current pre-release scope, and
> the non-goals. Security, routing, compatibility, data model, and phases live in
> the sibling docs.

## 1. Purpose

Promote the WordPress **Attachment** from a post-bound file into an independent,
publishable media object with its own page, owner, visibility, rights, and
(eventually) federation identity — while never treating the plugin as a
filesystem manager.

The product is one entity seen through context-specific projections:

| Layer | What it is | Mutable? |
|---|---|---|
| **File URL** | immutable binary address (`/wp-content/uploads/…`) | no (fixed at upload) |
| **Attachment** | independent media publishing object | metadata mutable |
| **Virtual Folder** | a user's single-location logical directory (`ax_media_folder`) | movable |
| **Storage Browser** | read-only admin projection of the real `uploads` tree | read-only |

Later layers (documented as deferred): **Saved Reference**, **Shared folders**,
**ActivityPub**.

## 2. Invariants (do not break across phases)

1. **Axismundi never changes the file URL.** Folder moves, meta edits, and
   visibility changes leave the file path and URL untouched. External changes
   (core image edit, media offload, optimizers rewriting `_wp_attached_file` or
   the delivery URL) are **detected and reconciled** (Phase 6), not prevented —
   this is a plugin-behavior guarantee, not a global one.
2. Moving an Attachment between virtual folders changes **neither** the file URL
   **nor** the Attachment page URL.
3. An Attachment has exactly **one owner** and **one current virtual folder** (OS
   "single location" affordance; `Unfiled` is a real system folder). This holds at
   the **canonical owning-instance record** — shared folders are **no exception**,
   and a federated **replica on another instance is a folder *item*, not a second
   folder *location*** (see FEDERATED-MEDIA.md §2). Attachment owner / folder owner /
   uploader stay distinct subjects; a shared folder never transfers ownership.
4. Saving another user's media creates a **reference (shortcut)**, never a file
   copy and never a new Attachment.
5. `post_parent` removal is a **user-chosen migration**, never an activation
   side effect.
6. Folder visibility and Attachment visibility are **independent**; a folder (or
   collection) can never **widen** an Attachment's exposure.
7. **Page/query access control ≠ file protection.** The controls in this plugin
   protect the Attachment object, its metadata, and plugin API/archive surfaces —
   **not** an already-known original file URL, its embedded EXIF/GPS, or copies
   in a CDN/cache. This boundary is stated wherever visibility is configured.
8. Location metadata defaults to **hidden in plugin output**; the Attachment page
   defaults to **public**. This policy never rewrites original or derivative files,
   and does **not** claim file-level EXIF/GPS protection.

## 3. Identity model

Four distinct identifiers, never conflated:

```
Database primary identity   attachment_id                  (all local queries use this)
Public / federated identity object_uri  = /?ax_media_object={id}   (immutable, owner/folder-independent)
Human-facing URL            canonical permalink            (mutable; see ROUTING.md)
Binary resource             file URL                       (immutable)
```

- `object_uri` format is **locked now** and MUST be independent of owner slug,
  folder, and permalink so federation identity survives renames. It is **not**
  the HTML permalink.
- v0.1 MAY reserve the `object_uri` format in spec and **not persist** it yet,
  rather than persist a value that could later prove wrong. (Decision recorded in
  ROUTING.md.)
- Phase 1 canonical Attachment page uses the core query URL **`/?attachment_id={id}`**
  (never `/?attachment=`slug), avoiding a bespoke rewrite for the single page.

## 4. Implemented baseline (Phases 0–4)

**Phase 0 — compatibility boundary (non-destructive):**
- Plugin scaffold; `Core attached-to` ⇄ `Independent media` mode toggle.
- Activation alone changes **no** existing data, parent, status, or permalink.
- `post_parent` scan + migration **preview** only.
- Separate "new-upload policy" from "existing-media migration."
- Bulk existing-parent mutation remains an explicit, previewed CLI operation with
  immutable snapshots and rollback; activation never runs it automatically.

**Phase 1 — independent Attachment publishing:**
- Independent mode: **new** uploads get `post_parent = 0` (regardless of upload
  path). Used-in tracking does **not** run in Phase 1 — the relation store and
  its initial scan are Phase 3.
- Existing (pre-plugin) Attachments are **legacy**: read via fallback
  (owner = `post_author`, visibility = legacy-public, listed/searchable = true)
  and left untouched until an explicit migration. Independent mode governs **new
  uploads**, not existing media.
- Canonical single page `/?attachment_id={id}` with the visibility guard.
- Archives via rewrite: `/media/`, `/media/author/{nicename}/`, and owner-scoped
  folder paths.
- Visibility: `public | unlisted | private` (`protected` lands in Phase 3).
- `listed` / `searchable` toggles — a `public` item still requires
  `listed` / `searchable` to appear in archives / search (predicate in
  SECURITY.md §2).
- **Ownership = `post_author`** (single source of truth; DATA-MODEL.md §2.0). No
  separate owner meta: ownership transfer = change `post_author`, permission =
  core `edit_post` / `edit_others_posts`. Creator and copyright-holder are rights
  metadata, not capabilities. `post_author = 0` = unowned (uid 0 ≠ author 0).
- Rights + sensitivity + location-output fields are stored; Phase 4 enforces
  sensitivity authority and click-to-reveal output while location policy remains
  subject to Invariant 8.
- File URL immutable; old attachment permalink → canonical redirect.
- Full visibility enforcement across surfaces per SECURITY.md (HTML, REST
  single + collection, plugin archives/search, media modal).

Phases 2–4 add single-location virtual folders and password gates, the used-in
relation index and migration CLI, Atom/Media RSS feeds, rights/license output, and
sensitive-media authority plus click-to-reveal rendering. PHASES.md is the acceptance
contract for each delivered increment.

## 5. Deferred beyond v0.0.21

Physical file move/rename · original file-URL protection · FTP/storage file
manager · Collections/Save · shared folders & invitations · Actors/Like ·
file copy / hotlink proxy · advanced EXIF/color search · simultaneous RSS+Atom ·
multisite integration · destructive GPS/EXIF stripping (not planned).

## 6. Phase gates (summary; full criteria in PHASES.md)

```
Phase 0  Compatibility boundary        ✓
Phase 1  Independent Attachment pages  ✓
Phase 2  Virtual folders + password    ✓
Phase 3  Used-in index + migration     ✓
Phase 4  Rights, sensitivity, output   ✓
Phase 5  Saved Reference (object_uri + local_attachment_id)
Phase 6  Storage Browser (filesystem mirror; the four Storage contracts)
Phase 7  Federation (ActivityStreams objects/collections)
```

GitHub pre-release v0.0.21 is the **dependency-free Phase 0–4 checkpoint**. Actor-
keyed Collections and shared folders begin after the separate Actors substrate.
