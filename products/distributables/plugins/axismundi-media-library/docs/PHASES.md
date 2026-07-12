# Axismundi Media Library — Phases

> Status: **Living implementation plan. Phase 0 and Phase 1a are complete.** Entry condition · acceptance · non-goals
> per phase. **First release = Phase 0 + Phase 1.** Cross-refs: SPEC.md (scope),
> SECURITY.md (matrix), ROUTING.md, COMPATIBILITY.md, DATA-MODEL.md.

## Phase 0 — Compatibility boundary (first release, non-destructive)

**Entry:** none (foundation).
**Build:** plugin scaffold; Core/Independent mode toggle; option storage;
`post_parent` scan + migration **preview**; `legacy_parent` capture design;
`wp_attachment_pages_enabled` ownership; permalink flush + collision check.
**Acceptance:**
- Install + activate changes **no** existing Attachment data/parent/status/permalink.
- Admin can see the count of Attachments/relations a transition *would* change.
- Existing parents are recoverable (preserved, not removed).
**Non-goals:** any bulk parent mutation; visibility features.

## Phase 1 — Independent Attachment publishing (first public MVP)

**Entry:** Phase 0.
**Build:** Independent-mode new uploads `post_parent=0` (path-independent, set
pre-INSERT); `/?attachment_id={id}` canonical + `attachment_link` filter +
old-permalink 301; media archives via **plain query endpoints**
(`?ax_media_archive=landing` / `owner&ax_media_owner={USER_ID}`, always work
without pretty permalinks) with `/media/`, `/media/{nicename}/` as pretty aliases;
optional **Media Page** (Settings > Reading) as an editable stable landing;
`media-home` / `media-author` plugin block templates; core/theme Attachment single
hierarchy (theme `attachment.html`, not duplicated); `public|unlisted|private`;
`listed`/`searchable`; rights/sensitivity/GPS fields **stored**; GPS default hidden
(flag only); file URL immutable.
**Acceptance (= SECURITY.md §7):**
- Visibility matrix holds for non-owner/non-editor across HTML single, REST
  single, REST collection, plugin archives, plugin search, media modal.
- `private` → 404 (HTML + REST single); `unlisted` → 200 single, absent from lists.
- Owner sees all own; `edit_others_posts` sees all.
- Deactivation contract (COMPATIBILITY.md §5) holds; no global `pre_get_posts`.
**Non-goals:** folders; `protected`; GPS/EXIF stripping; used-in index; pretty
`/media/{owner}/{id}/` alias; Save; Storage Browser.

## Phase 2 — Single virtual folder

**Collection UI:** `axismundi/media-collection` renders the current media archive,
one user's root, or a specific folder as a mixed grid of parent navigation,
visible direct child folders, and direct Attachments. Folder counts use the same
visibility query as the viewer and never expose hidden descendants.

> **UX benchmark: FileBird** (feel like FileBird, do **not** store like FileBird).
> FileBird uses two custom tables (`wp_fbv`, `wp_fbv_attachment_folder` with a
> composite PK) and gates every REST route on `upload_files` only. Axismundi keeps
> the `ax_media_folder` **taxonomy** (core term relationships) and gates moves on
> each attachment's own `edit_post`. Borrow FileBird's interaction model, not its
> schema or permissions.

**Entry:** Phase 1 stable.
**Prereq (decided):** per-user top-level slug collisions are resolved with a
**hidden per-user root term** (owner-namespaced internal slug); the clean
`/media/author/{owner}/folder/{path}/` is shown in the URL (ROUTING.md §0).

**Phase 2a — structure + tier resolver.** `ax_media_folder` taxonomy + term meta
(`_ax_media_folder_owner`, `_ax_media_folder_root`, `_ax_media_folder_tier`);
**single-relation** enforcement (root = no term; move = one
`wp_set_object_terms( id, [term|none], append=false )`); create / rename / move /
delete-to-root; direct vs recursive counts; service + REST — **moving an attachment
requires `edit_post` on that attachment** (not just `upload_files`, the FileBird gap
we avoid). Then the **folder-aware visibility resolver**: tier `public=0 < unlisted=1
< private=2`, `item_tier = max(chain)` with attachment `inherit` deferring to the
chain (SECURITY.md §2.3, DATA-MODEL §3.1) — wired into the archive / REST collection
/ media-modal queries. `/media/author/{owner}/folder/{path}/` (admin "Folder", front
"Board"). *(taxonomy + CRUD service + REST shipped in v0.0.5.)*

**Phase 2b — password gate + UX.** Folder `access = password` (hashed
`wp_hash_password`), challenge cookie, folder-archive + attachment-single gate
(owner/`edit_post` bypass; `private` beats any password). FileBird-informed
interaction: sidebar tree + breadcrumb, search/sort/drag-and-drop, right-click
create/rename/delete, remembered folder, upload-into-selected-folder, edited images
inherit the source folder; UI states `All media = -1`, `Unfiled = 0`; default
license/sensitivity inheritance; Atom folder feed; the move-confirm warning when an
`inherit` item's effective visibility changes (e.g. Public → Private).

**Acceptance:** an Attachment is in ≤1 folder; folders **narrow never widen** the
effective visibility (invariant ⑥); `private` is not unlocked by a folder password;
folder move changes no file/attachment URL; deleting a folder **moves its media to
the root/unfiled**, never deletes media; folder feed pubDate =
`_ax_media_folder_added_at`.
**Non-goals:** Save/shared; storage mirror; FileBird importer (compatibility item,
COMPATIBILITY.md §7); per-attachment password (use a one-item password folder).

## Phase 3 — Protection & used-in index

**Entry:** Phase 2.
**Build:** *(folder password moved to Phase 2b.)* Per-Attachment access policy
refinements; **`wp_ax_media_relations`** (finalize provisional
schema) + block-content scan (featured/gallery/image/file/audio/video), incremental
update on save, full re-index tool; legacy_parent migration executes here (with
preview/rollback from Phase 0); "used in" panel + delete-warning.
**Acceptance:** protected page requires challenge; public folder can't widen a
private Attachment; used-in list is accurate; existing-parent removal is reversible.
**Non-goals:** original-file protection (signed URLs) beyond page/query.

## Phase 4 — Rights, sensitivity, GPS/EXIF, download

**Entry:** Phase 3.
**Build:** enforce license/attribution/reuse/save policy; sensitive/content-warning
UI (blur/warn); **GPS enforcement = actual EXIF strip** of delivered files
(derivatives + original re-save) — only now may "GPS hidden" be *claimed*; download
policy (original/derivative-only/disabled); folder default inheritance.
**Acceptance:** `geo_visibility=hidden` yields no GPS in any delivered file;
reuse/save flags gate the (Phase 5) Save button; sensitive media is blurred +
excluded from OG preview.
**Non-goals:** federation rights re-check (Phase 7).

## Phase 5 — Saved Reference

**Entry:** Phase 4.
**Build:** **`wp_ax_media_references`** (finalize provisional schema); local Save
by id (live read); Like separated from Save (private default); shared/reference
badges; Save gated by reuse policy (`unknown = denied`); Save never widens origin
visibility/rights; license/reuse snapshot at save.
**Acceptance:** Save = reference (no file copy, no new Attachment); origin folder
move doesn't break the reference (id-keyed); origin visibility change is respected
(no permanent access grant).
**Non-goals:** remote/federated Save (Phase 7); multi-folder references.

## Phase 6 — Storage Browser (filesystem mirror, read-only)

**Entry:** Phase 5.
**Contracts (mandatory, from the four Storage rules):**
1. **Path sealing** — `realpath()` inside `wp_upload_dir()['basedir']` on every
   request (normalize separators/case before prefix compare); else 403.
2. **Protected/plugin-folder isolation** — dirs with `.htaccess`/`.user.ini`/
   guard `index.php` or known private/plugin paths → hidden + read-only; never
   re-register to public.
3. **Indexed reconciliation** — build/cache a `_wp_attached_file → id` map;
   never call `attachment_url_to_postid()` per file; incremental invalidation.
4. **Flat-uploads support** — `uploads_use_yearmonth_folders = false` is normal;
   `YYYY/MM` is one folder *type*, not required structure.
**Build:** read-only mirror; state = registered-original | unregistered |
derivative | missing-original | damaged-derivatives | plugin-managed | protected |
excluded | unknown; derivative detection prefers `wp_get_attachment_metadata()['sizes']`,
basename heuristic only as fallback; FTP file → Attachment registration; upload
into a chosen storage path; EXIF date suggestion; exclusion filter.
**Non-goals:** moving/renaming existing physical files.

## Phase 7 — Federation

**Entry:** Phase 6.
**Build:** Attachment → `Image`/`Video`/`Audio`; folder/board →
`Collection`/`OrderedCollection`; `object_uri` becomes the live identity; remote
Save + metadata cache + tombstone; rights re-validation at federation boundary;
`Add`/`Remove`-based Save; Follow + user media feed.
**Non-goals:** (revisit) multisite.

---

### Explicitly deferred (state "deferred" in spec, don't design now)

Multi-folder Save references · shared-folder upload ownership · Like AS
representation · simultaneous Atom+RSS · remote cache-expiry policy · full Storage
exclusion list · FTP date-priority rules · original-file EXIF strip specifics ·
real private storage / signed URLs · multisite.
