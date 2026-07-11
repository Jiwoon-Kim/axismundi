# Axismundi Media Library — Specification

> Status: **Design draft (pre-code).** No implementation exists yet.
> Plugin brand: **Axismundi Media Library** · Admin app: **Media Drive**
> This document defines the product, its invariants, the 0.1.0 release scope, and
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

1. The direct **file URL never changes** after upload.
2. Moving an Attachment between virtual folders changes **neither** the file URL
   **nor** the Attachment page URL.
3. An Attachment has exactly **one owner** and **zero-or-one current virtual
   folder** (OS "single location" affordance, enforced in the service layer even
   though the taxonomy is many-to-many).
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
8. GPS defaults to **hidden**; the Attachment page defaults to **public**. Note
   that "GPS hidden" is only real once EXIF is stripped from delivered files
   (Phase 4) — 0.1.0 stores the flag but does **not** claim file-level GPS
   protection.

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

## 4. 0.1.0 release scope (Phase 0 + trimmed Phase 1)

**Phase 0 — compatibility boundary (non-destructive):**
- Plugin scaffold; `Core attached-to` ⇄ `Independent media` mode toggle.
- Activation alone changes **no** existing data, parent, status, or permalink.
- `post_parent` scan + migration **preview** only.
- Separate "new-upload policy" from "existing-media migration."
- **No bulk existing-parent mutation ships in 0.1.0** (preview/scan only; the
  destructive removal is a later, tested step).

**Phase 1 — independent Attachment publishing:**
- Independent mode: **new** uploads get `post_parent = 0` (regardless of upload
  path); insertions are recorded as used-in relations (index lands in Phase 3).
- Canonical single page `/?attachment_id={id}` with the visibility guard.
- Archives via rewrite: `/media/`, `/media/{owner}/`.
- Visibility: `public | unlisted | private` (`protected` lands in Phase 3).
- `listed` / `searchable` toggles.
- Rights + sensitivity + GPS fields **stored** (enforcement of GPS/sensitivity is
  Phase 4; see Invariant 8).
- File URL immutable; old attachment permalink → canonical redirect.
- Full visibility enforcement across surfaces per SECURITY.md (HTML, REST
  single + collection, plugin archives/search, media modal).

## 5. Non-goals for v0.1 (explicitly deferred)

Physical file move/rename · original file-URL protection · FTP/storage file
manager · shared folders & invitations · remote ActivityPub Save · Like ·
file copy / hotlink proxy · advanced EXIF/color search · simultaneous RSS+Atom ·
multisite integration · `protected` visibility (Phase 3) · GPS/EXIF stripping
(Phase 4).

## 6. Phase gates (summary; full criteria in PHASES.md)

```
Phase 0  Compatibility boundary        ← first release gate (with Phase 1)
Phase 1  Independent Attachment pages  ← first public MVP
Phase 2  Single virtual folder (ax_media_folder)
Phase 3  Protection (protected, folder password, used-in index)
Phase 4  Rights, sensitivity, GPS/EXIF enforcement, download policy
Phase 5  Saved Reference (object_uri + local_attachment_id)
Phase 6  Storage Browser (filesystem mirror; the four Storage contracts)
Phase 7  Federation (ActivityStreams objects/collections)
```

First release = **Phase 0 + Phase 1** only.
