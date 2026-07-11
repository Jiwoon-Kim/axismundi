# Axismundi Media Library — Security & Access Contract

> Status: **Design draft (pre-code).**
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
public regardless. The deactivation screen and docs must carry:

> *Deactivating the plugin disables its media visibility and access controls.
> Original file URLs are not protected by these controls.*

State model stays uniform: **`post_status = inherit` for all Attachments**; no
`post_status=private` hybrid. Visibility is a plugin concern via `_ax_media_visibility`.

## 2. Visibility levels

| Level | Meaning |
|---|---|
| `public` | listed, searchable, page + REST single, feed/sitemap-eligible |
| `unlisted` | page + REST single reachable by direct id; excluded from lists/search/feed/sitemap |
| `protected` | password/permission challenge before the page; excluded from public lists & oEmbed (**Phase 3**) |
| `private` | owner + `edit_others_posts` only; excluded from every public surface |

Owner always sees their own media at every level. `edit_others_posts` holders
(editors/admins) see everything.

## 3. Access matrix (acceptance contract)

Result for a **non-owner, non-editor** requester unless noted. "single by id"
means a direct `/?attachment_id={id}` or `/wp/v2/media/{id}` request.

| Surface | public | unlisted | protected (P3) | private |
|---|---|---|---|---|
| HTML single (`/?attachment_id={id}`) | 200 | 200 | challenge → 200/401 | **404** |
| REST single (`GET /wp/v2/media/{id}`) | 200 | 200 | 401/403 | **404** |
| REST collection (`GET /wp/v2/media`) | listed | **omitted** | omitted | **omitted** |
| Plugin archive `/media/`, `/media/{owner}/`, folder | listed | omitted | omitted | omitted |
| Plugin media search | listed | omitted | omitted | omitted |
| Feed (Phase 2) | included | omitted | omitted | omitted |
| Admin media modal (other user) | shown | shown | **omitted** | **omitted** |
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
requester is the owner              → see all of their own, any visibility
requester has edit_others_posts     → see everything
other upload_files users            → see only public/unlisted owned by others
                                    → private/protected of others: OMITTED
```

Minimum non-negotiable: **another author never sees someone else's `private`
(or `protected`) media in the picker.** Finer product tuning (e.g. hide others'
`unlisted` too) may be decided later, but the private exclusion is a 0.1.0 gate.

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
- [ ] `public`: present on all public surfaces.
- [ ] Owner sees own media at every level on every surface.
- [ ] `edit_others_posts` holder sees everything.
- [ ] Deactivation: no data mutation; controls cease; boundary notice shown; count
      of `private`/`protected` Attachments reported to admin.
- [ ] No global `pre_get_posts`; admin/other-plugin attachment queries unaffected.

`protected` rows are validated when Phase 3 lands.
