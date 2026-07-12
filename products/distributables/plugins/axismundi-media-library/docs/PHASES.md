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
one user's root, or a specific folder as separate folder and Attachment regions.
Its Query Loop-style child contract is `media-folders`, `media-post-template`,
`media-no-results`, and `media-pagination`; the post template repeats arbitrary
blocks with the current Attachment context. Folder counts use the same visibility
query as the viewer and never expose hidden descendants. Password-protected child
folders remain discoverable as protected navigation items, but expose no count.

**Admin collection UX:** the desktop Media Library and media modal expose a
FileBird-informed nested sidebar tree backed by the same taxonomy and attachment
query model; compact viewports retain the core-toolbar folder dropdown. Tree
selection also sets the current upload destination through the existing service.

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

## Phase 2c — Multi-user permission audit (Phase 2 closing gate)

**Entry:** Phase 2b. A **diagnostic**, not a feature: measure the *current* behaviour
of personal-folder isolation, admin cross-user access, and the sensitive flag before
building shared folders — so Phase 3-5 build on measured reality, not assumption.
**Method:** dev-only fixtures (Alice + Bob users) via a test script / WP-CLI fixture
that is **excluded from the distributed build** — no permanent seed UI, no hand-edited
DB rows.
**Audit matrix (record pass/fail, don't fix yet):**
- Alice's admin Media Library shows **her own** media, not the whole site's.
- Alice cannot see another user's personal folder or its media.
- Alice can move **only her own** Attachments into her folders.
- A REST move with **another user's attachment ID** is denied (IDOR).
- A folder-ID-swapped request (another owner's folder) is denied (IDOR).
- Admin *can* see other users' private folders/media — and that view does **not** leak
  into normal user queries.
- Admin sets `sensitive`; **can Alice clear it via the current UI/REST?** (Expected
  **FAIL** on today's boolean model — record as the Phase 4 authority requirement,
  §2.4/DATA-MODEL §2.3. Do not build the moderation state model here.)
**Acceptance:** every isolation/IDOR row passes or is filed as a fix; the sensitive-
clear row is recorded as a confirmed Phase 4 gap. Then proceed to Phase 3.

## Phase 3 — Protection & used-in index

**Entry:** Phase 2.
**Build:** *(folder password moved to Phase 2b.)* Per-Attachment access policy
refinements; **`wp_ax_media_relations`** (finalize provisional
schema) + block-content scan (featured/gallery/image/file/audio/video), incremental
update on save, full re-index tool; legacy_parent migration executes here (with
preview/rollback from Phase 0); "used in" panel + delete-warning.
**Model (FEDERATED-MEDIA.md §7):** used-in is the **reverse index of the
ActivityStreams `attachment`/`image`/`icon`/`schema:associatedMedia` relations** —
NOT a `post_parent` replacement, NOT "file URL in HTML". The internal Media Relation
index is a **superset** of the federated `attachment` projection (keeps local,
decorative, non-federated uses the serializer filters out); keyed on the media
**object URI** (local id = pointer); built from the same normalized object model that
feeds JSON-LD (don't re-parse JSON-LD into the DB). Three distinct relation groups on
the Attachment page: **Location** (folder) / **Used in** / **Saved in**.
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

## Phase 5a — Collection / Save (URI-first container)

**Entry:** Phase 4. Validate the URI-first container substrate on the simpler,
single-user Collection before adding shared-folder membership.
**Build:** **`wp_ax_media_references`** (finalize provisional schema); a
**MediaContainer** item with `relation = bookmark` (Collection) — distinct from a
folder's `relation = location` (FEDERATED-MEDIA.md §3). Save is the **`Add` activity,
not `Like`** (Like never lands in a Collection). Local Save by id (live read) + manual
external-URL save (`object_uri`, nullable `local_attachment_id`; Openverse adapter
later); Save gated by reuse policy (`unknown = denied`), never widens origin
visibility/rights; **license/reuse snapshot at save** (kept when the origin later
tightens — bookmark stays, new insert/reuse blocked).
**Acceptance:** Save = reference (no file copy, no new Attachment); id-keyed so an
origin folder move doesn't break it; origin visibility change respected (no permanent
grant); a later reuse-denied origin keeps the bookmark but blocks new reuse.
**Non-goals:** shared-folder membership (5b); remote/federated Save (Phase 7);
multi-folder *folder* membership (references may be multi-container).

## Phase 5b — Local Shared Folder (membership on the URI substrate)

**Entry:** Phase 5a. Add membership to the container substrate proven in 5a.
**Build:** a MediaFolder with members beyond the owner; **actor-URI-keyed** membership
(roles **owner / manager / contributor / viewer** — no single `guest`), visibility
separate from member powers. **Dev-only WP-CLI/PHP seed of already-accepted
memberships** (no Invite/Accept UI yet). A member's own upload into a shared folder
keeps *their* ownership; another user's existing Attachment is referenced as a
**container item, not force-moved** from its home. Leaving / removal / folder-delete
returns each owner's media to their **Unfiled**, never destroys it. Admin views all
private folders only via a dedicated audit capability (SECURITY.md §2.4).
**Acceptance:** roles enforce upload/move/remove/delete boundaries; leaving returns
owned media to Unfiled; ownership never transfers on placement; admin audit access is
capability-gated and logged.
**Non-goals:** remote members / replicas (Phase 7); Invite/Accept UI.

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
`Add`/`Remove`-based Save; `Invite`/`Accept`/`Leave` membership; Follow + user media
feed.
**Model (FEDERATED-MEDIA.md §8-§12):** S2S shared folder = a **per-instance folder
projection + binary replica** (identity stays the remote canonical URI; local id is a
pointer). A **Remote Attachment Replica** (`post_status=ax_remote_replica`, read-only,
excluded from default queries) is created **only** for gated shared-folder replicas,
**never** for Follow/timeline media. Reception paths (Follow / Save / shared-folder /
import) have distinct records, binaries, and lifetimes — `Remove from folder ≠ Delete
object`. WP↔WP is a **plugin extension protocol over ActivityPub** (versioned `axm:`
namespace + capability discovery + Level 1-3 graceful degradation); canonical URIs are
immutable, display permalinks are not.
**Non-goals:** standard-AP interop for shared-folder semantics; (revisit) multisite.

## Post-roadmap stabilization — reset and clean removal

**Entry:** the complete plugin-owned data inventory and all versioned migrations
through Phase 7 are stable.
**Build:** one reset service with a dry run and explicit scopes (`settings`,
`folders`, `metadata`, future plugin-owned tables, or `all`); WP-CLI first, optional
administrator Danger Zone UI later. Deactivation and uninstall remain
data-preserving by default (COMPATIBILITY.md §6).
**Acceptance:** reports exact affected counts before mutation; requires explicit
confirmation; never deletes Attachment posts or physical files; removes only
Axismundi-owned relationships/terms/meta/options/tables in deterministic order;
is safely retryable after partial failure.
**Non-goals:** guessing legacy parents without the relation index; deleting media
files; automatic purge during deactivate/uninstall.

---

### Explicitly deferred (state "deferred" in spec, don't design now)

Multi-folder Save references · shared-folder upload ownership · Like AS
representation · remote cache-expiry policy · full Storage exclusion list · FTP
date-priority rules · original-file EXIF strip specifics ·
real private storage / signed URLs · multisite · reset implementation before the
plugin-owned data inventory stabilizes.
