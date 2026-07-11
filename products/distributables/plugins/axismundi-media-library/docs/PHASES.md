# Axismundi Media Library — Phases

> Status: **Design draft (pre-code).** Entry condition · acceptance · non-goals
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
**Build:** Independent-mode new uploads `post_parent=0` (path-independent);
`/?attachment_id={id}` canonical + `attachment_link` filter + old-permalink 301;
`/media/`, `/media/{owner}/` rewrites; Attachment single + user archive templates;
`public|unlisted|private`; `listed`/`searchable`; rights/sensitivity/GPS fields
**stored**; GPS default hidden (flag only); file URL immutable.
**Acceptance (= SECURITY.md §7):**
- Visibility matrix holds for non-owner/non-editor across HTML single, REST
  single, REST collection, plugin archives, plugin search, media modal.
- `private` → 404 (HTML + REST single); `unlisted` → 200 single, absent from lists.
- Owner sees all own; `edit_others_posts` sees all.
- Deactivation contract (COMPATIBILITY.md §5) holds; no global `pre_get_posts`.
**Non-goals:** folders; `protected`; GPS/EXIF stripping; used-in index; pretty
`/media/{owner}/{id}/` alias; Save; Storage Browser.

## Phase 2 — Single virtual folder

**Entry:** Phase 1 stable. **Prereq:** resolve per-user top-level folder slug
collision (two users' sibling folders can share a slug) — via a hidden per-user
root term **or** an owner-namespaced internal slug, with the clean `/media/{owner}/
folder/{path}/` shown in the URL. Decide before building the taxonomy.
**Build:** `ax_media_folder` taxonomy + term meta; single-relation enforcement
(root = no term); move/rename/delete; folder `public|unlisted|protected|private`;
`/media/{owner}/folder/{path}/` (admin "Folder", front "Board"); default
license/sensitivity inheritance; Atom folder feed.
**Acceptance:** an Attachment is in ≤1 folder; folder visibility is independent of
Attachment visibility (widen never); folder move changes no file/attachment URL;
folder feed pubDate = `_ax_media_folder_added_at`.
**Non-goals:** Save/shared; storage mirror.

## Phase 3 — Protection & used-in index

**Entry:** Phase 2.
**Build:** `protected` visibility + folder password (hashed, `wp_hash_password`);
per-Attachment access policy; **`wp_ax_media_relations`** (finalize provisional
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
