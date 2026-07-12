# Axismundi Media Library — Security & Access Contract

> Status: **Living specification. Phase 1a guards are implemented.**
> This is an **acceptance contract**, not a hook checklist. The matrix in §3 is
> the source of truth; every enforcement point in §4 exists to satisfy a matrix
> cell, and §7 restates the cells as testable criteria.

## 1. Threat-model boundary (read first)

This plugin controls access to the **Attachment object, its metadata, and the
plugin's own API/archive surfaces**. It does **not** protect:

- an already-known **original file URL** under `/wp-content/uploads/…`;
- **EXIF/GPS** embedded in delivered files;
- copies replicated to a **CDN or external cache**.

> **Page/query access control ≠ file protection.** Real file protection (signed
> URLs, private object storage, server rules) is out of scope for the visibility
> model and is a separate, later capability.

When the plugin is **deactivated**, its visibility/archive/permalink behavior
stops and WordPress core Attachment behavior returns. Stored meta is retained but
not interpreted. We do **not** guarantee `private` semantics after deactivation —
and we don't need to, because core has no such feature and the file URL was
public regardless.

Boundary text delivery (a deactivated plugin's code does not run, so **no admin
notice can fire after deactivation** — do not assume one):

- **Always** in the settings screen and readme.
- On the **"disable Independent mode"** screen, with the current count of
  `private`/`protected` Attachments that will stop being guarded.
- Optionally a **pre-deactivation confirm** on the Plugins-screen Deactivate link
  (`plugin_action_links` / JS confirm) — while the plugin is still active.

> *Disabling these controls (deactivating the plugin or turning off Independent
> mode) disables Axismundi media visibility and access controls. Original file
> URLs are not protected by these controls.*

State model stays uniform: **`post_status = inherit` for all Attachments**; no
`post_status=private` hybrid. Visibility is a plugin concern via `_ax_media_visibility`.

## 2. Visibility levels

Attachment visibility is `inherit | public | unlisted | private`. `protected`
(password) is **not** an attachment level — it is a folder access gate (§2.3), so a
single item that needs a password goes in a one-item password folder. This keeps
per-item auth/cookies/forms out of the model.

| Level | Meaning |
|---|---|
| `inherit` | take the effective tier from the folder chain (§2.3); the default for uploads made **into** a folder |
| `public` | page + REST single; **eligible** for lists/search/feed (gated by `listed`/`searchable`, see predicates) |
| `unlisted` | page + REST single reachable by direct id; excluded from lists/search/feed/sitemap **regardless of `listed`/`searchable`** |
| `private` | owner + `edit_others_posts` only; excluded from every public surface |

Owner always sees their own media at every level. `edit_others_posts` holders
(editors/admins) see everything, and **bypass the password gate** (management).

### 2.3 Folder visibility — tier + gate, and processing order (Phase 2)

Folders narrow, never widen (invariant ⑥). Two orthogonal dimensions (DATA-MODEL
§3.1): a linear **tier** `public=0 < unlisted=1 < private=2` and an orthogonal
**access** gate `open | password`. Resolution walks the whole folder chain:

```
folder_chain_tier = max( rank(folder.tier), skipping inherit; empty → public )
item_tier = attachment == inherit ? folder_chain_tier : max(rank(attachment), folder_chain_tier)
gated     = OR( folder.access == password ) over the chain
```

Collection queries consume the derived term meta
`_ax_media_folder_effective_tier_rank`; folder mutations refresh the affected
subtree. This is a cache of the formula above, never a separately authored policy.

Per-surface processing order (single surfaces):

```
1. owner / edit_post holder            → allow (also bypasses the password gate)
2. item_tier == private                → not_found (404)   (private beats any password)
3. gated                               → password challenge
4. item_tier == unlisted               → single allowed; excluded from archive/search/feed
5. public                              → public
```

List/archive/search/feed surfaces use `item_tier` in the same way (only tier 0 =
public is listed, gated by `listed`/`searchable`); a gated folder's items are never
listed publicly. **Phase split:** Phase 2a implements the tier resolver
(public/unlisted/private, chain `max`) across the archive/REST/modal queries; Phase
2b adds the password hash, signed HttpOnly challenge cookie, and the folder +
attachment-single gate. Nested password folders require each gate in root-to-leaf
order; changing a password invalidates its existing cookie token.

### 2.1 Predicates (fix `public` vs `listed`/`searchable`)

`public` is **necessary but not sufficient** to be listed:

```
single_access = visibility permits (public | unlisted | owner/editor)
archive       = visibility == public AND listed == true
REST list     = visibility == public AND listed == true
search        = visibility == public AND searchable == true
feed          = visibility == public AND listed == true
unlisted      = excluded from every list regardless of listed/searchable
```

### 2.2 Legacy Attachments (no `_ax_media_*` meta)

Existing/pre-plugin Attachments have no visibility meta. The resolver and every
`Visibility_Query` MUST treat missing meta as legacy defaults, or existing media
vanishes from lists / fails owner checks the moment a `meta_query` is added:

```
owner       → post_author               (permission via core edit_post; uid 0 != author 0)
visibility  → public   (legacy-public)  when _ax_media_visibility is absent
listed      → true                      when absent
searchable  → true                      when absent
```

Query implication: list `meta_query` for visibility MUST be
`( key NOT EXISTS ) OR ( key == public )` — never a bare `== public`, which drops
legacy rows. Independent mode governs **new uploads**; it does not silently
re-classify existing media (migration is explicit).

### 2.4 Sensitive authority + folder-membership re-check (forward, Phase 4/5/7)

Load-bearing contracts folded from FEDERATED-MEDIA.md (§6, §4, §12); enforced in later
phases but binding on their design:

- **Sensitive is state + authority, not a free boolean.** A user may clear only
  `self_marked`; `automated_flagged` is appeal-only; `moderator_marked`/`confirmed`
  cannot be self-cleared (caps `moderate_media_sensitivity` / `override_media_sensitivity`).
  DATA-MODEL §2.3. A REST write MUST re-check the authority state, never trust the
  submitted boolean.
- **Every folder/media request re-checks membership + capability per request** — never
  trust a folder/attachment ID alone (the IDOR class that has produced real
  media-folder-plugin CVEs). Shared-folder ops verify the actor's active membership,
  role power, and per-attachment `edit_post`.
- **Admin cross-user access is a separate capability**, not `manage_options`:
  `view_all_media_folders` / `manage_all_media_folders` / `moderate_media`. Viewing
  another user's private folder shows an explicit badge and writes an audit-log entry
  (`actor · folder/object · action · timestamp`).
- **Federated inputs are untrusted**: a received `attachment`/replica is honored only
  when the remote object declares it and HTTP-signature/actor/membership verify;
  shadow attachments are gated (FEDERATED-MEDIA.md §9).

## 3. Access matrix (acceptance contract)

Result for a **non-owner, non-editor** requester unless noted. "single by id"
means a direct `/?attachment_id={id}` or `/wp/v2/media/{id}` request.

"gated (P2b)" = a `password` folder in the chain (an orthogonal gate on top of the
tier, §2.3), not a fourth tier. `private` is unaffected by the gate.

| Surface | public | unlisted | gated (P2b) | private |
|---|---|---|---|---|
| HTML single (`/?attachment_id={id}`) | 200 | 200 | challenge → 200/401 | **404** |
| REST single (`GET /wp/v2/media/{id}`) | 200 | 200 | 401/403 | **404** |
| REST collection (`GET /wp/v2/media`) | listed | **omitted** | omitted | **omitted** |
| Plugin archive `/media/`, `/media/author/{owner}/`, `.../folder/{path}/` | listed | omitted | omitted | omitted |
| Plugin media search | listed | omitted | omitted | omitted |
| Feed (Phase 2) | included | omitted | omitted | omitted |
| Admin media modal (other user) | shown | **omitted** | **omitted** | **omitted** |
| Sitemap | included¹ | omitted | omitted | omitted |
| oEmbed | allowed | omitted² | omitted | omitted |

¹ Core `wp-sitemap` **excludes attachments by default** — this row only applies
  if the plugin opts media into a sitemap provider. Otherwise: N/A.
² Attachments are a low oEmbed surface by default; enforced only where the plugin
  exposes an embeddable media page.

`private` returns **404 (not 403)** on both HTML single and REST single so
existence is not disclosed.

## 4. Enforcement points (each maps to matrix cells)

Do **not** use a global `pre_get_posts` — it pollutes admin/internal/other-plugin
queries. Each surface **owns** its visibility condition through a shared
`Visibility_Query` service that emits the `meta_query` fragment.

| # | Surface | Hook / mechanism | Notes |
|---|---|---|---|
| 1 | HTML single | `template_redirect` guard on `is_attachment()` | resolve visibility + `current_user_can`; allow / 404 / (P3) challenge |
| 2 | REST single | `rest_pre_dispatch` route guard | inspect `/wp/v2/media/{id}`; return `WP_Error(404)` before the callback. **Not** `rest_prepare_attachment` (post-fetch = too late) |
| 3 | REST collection | `rest_attachment_query` | inject `Visibility_Query` meta_query into collection args |
| 4 | Media modal | `ajax_query_attachments_args` | inject owner/visibility filter (see §5) |
| 5 | Plugin archives/search/feed | `Visibility_Query` in the plugin's own `WP_Query` calls | never global; each query opts in |
| 6 | Front main query (only if needed) | narrowly-scoped `pre_get_posts` guarded to the plugin's front routes | last resort, route-gated |
| 7 | Sitemap (if opted in) | `wp_sitemaps_posts_query_args` | per-provider, no global filter |

`rest_prepare_attachment` may shape the response **after** access is granted, but
is never the gate.

## 5. Media-modal capability policy (§4.4 detail)

`ajax_query_attachments_args` result:

```
requester is the owner (post_author) → see all of their own, any visibility
requester has edit_others_posts      → see everything
other upload_files users             → see only OTHERS' public (+ listed) media
                                     → others' unlisted / private / protected: OMITTED
```

The media picker is a discovery surface, so **unlisted is excluded there** (its
whole point is non-discoverability) — not just private/protected. This is the
deliberate "strong privacy" choice; the matrix modal row reflects it.

## 6. What is / isn't guaranteed

**Guaranteed (plugin active):** Attachment page access; title/description/metadata
exposure; plugin archive & REST responses obey the matrix.

**Not guaranteed:** an already-known original file URL; EXIF/GPS embedded in the
file; CDN/external cache copies; any behavior after deactivation.

## 7. Acceptance criteria (0.1.0)

For each visibility level, automated/manual checks assert the §3 matrix as a
non-owner, non-editor:

- [ ] `private`: HTML single **404**, REST single **404**, absent from REST
      collection, plugin archives, plugin search, and the media modal.
- [ ] `unlisted`: HTML single **200**, REST single **200**, **absent** from REST
      collection, archives, search, feed, sitemap.
- [ ] `public`: present on public surfaces; `public` + `listed=false` is **absent**
      from archives/REST list; `public` + `searchable=false` is absent from search.
- [ ] **Legacy Attachment** (no `_ax_media_*` meta): owner = `post_author`,
      visibility = legacy-public, and appears in lists (not dropped by `meta_query`).
- [ ] Owner sees own media at every level on every surface.
- [ ] `edit_others_posts` holder sees everything.
- [ ] Deactivation: no data mutation; controls cease. Boundary text + count shown
      **on the disable/settings screen while active** (no post-deactivation notice
      is possible).
- [ ] No global `pre_get_posts`; admin/other-plugin attachment queries unaffected.

`protected` rows are validated when Phase 3 lands.
