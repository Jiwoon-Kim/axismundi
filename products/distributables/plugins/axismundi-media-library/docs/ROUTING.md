# Axismundi Media Library — Routing & Templates

> Status: **Living specification. Attachment single routing is implemented; archives remain Phase 1b.** See SPEC.md §3 (identity) and SECURITY.md
> §4.1 (the HTML `template_redirect` guard) — this doc defines how requests reach
> that guard and which template renders.

## 1. Attachment single: use the core query URL

**Phase 1 canonical Attachment page = `/?attachment_id={id}`.** This is already a
WordPress-understood single-attachment request, so Phase 1 needs **no bespoke
rewrite** for the single page and inherits `is_attachment()`,
`get_queried_object_id()`, and the MIME template hierarchy.

- Use **`attachment_id`** (numeric), never **`attachment`** (which resolves a
  mutable, collision-prone `post_name` slug).
- `get_attachment_link()` already returns `/?attachment_id=n` on plain-permalink
  sites; we make it the canonical everywhere.

### 1.1 Canonical redirect handling

On pretty-permalink sites, `redirect_canonical()` may 301 `/?attachment_id=123`
to `get_attachment_link(123)`. To keep the query URL canonical:

1. Filter **`attachment_link`** to always return `/?attachment_id={id}` (in
   Independent mode).
2. Verify `redirect_canonical` then targets that same URL (it derives its target
   from `get_attachment_link`, so forcing the filter is sufficient — do not add a
   competing manual redirect).

Old/previous attachment permalinks → **301 to the canonical** `/?attachment_id={id}`.

### 1.2 `wp_attachment_pages_enabled` ownership

Modern core can route attachment requests to the **file URL** when attachment
pages are disabled. Independent mode requires attachment pages **on**.

Policy (**A**) — owned change with a restore guard. When Independent mode is
enabled, store three things:

```
ax_media_attach_pages_prev   the value before we changed it
ax_media_attach_pages_set    the value we wrote (so we can detect our own value)
ax_media_attach_pages_owned  true while we hold the option
```

- **Restore only if we still own it**: on Independent-mode-off / deactivation,
  restore `prev` **only when the current option value equals `set`**. If another
  admin/plugin changed it in the meantime (current ≠ set), leave it and drop
  ownership — never clobber their change.
- **Re-acquire on reactivation**: reactivating with Independent mode still on
  re-reads the current value into `prev`, re-enables pages, and re-takes
  ownership (deactivation may have restored it to off).

The activation UI states this explicitly; the plugin never silently overrides a
site/plugin policy.

## 2. `object_uri` endpoint (reserved, not Phase 1)

```
object_uri (federation/identity)  /?ax_media_object={id}    ← Phase 7, reserved
HTML canonical (human)            /?attachment_id={id}      ← Phase 1
```

`ax_media_object` is kept **distinct** from `attachment_id` so a future
ActivityPub/JSON-LD/Tombstone endpoint (content negotiation) never collides with
the human HTML request. Per SPEC.md §3, 0.1.0 **reserves the format but does not
persist** `object_uri`.

## 3. Media archives — plain query endpoints are the base

The **always-working base is a query string** (it survives when pretty permalinks
are off, e.g. no `.htaccess`); pretty URLs and an optional Media Page are aliases
over it. Only archives use custom rewrite; the single page does not (§1).

```
Base endpoints (always available in Independent mode)
/?ax_media_archive=landing
/?ax_media_archive=owner&ax_media_owner={USER_ID}     ← ID (robust); nicename also accepted

Pretty aliases (when a permalink structure is set)
/media/                    → landing
/media/{nicename}/         → owner   (nicename resolves to the user)
/media/{owner}/folder/{path}/   virtual folder / board   (Phase 2)
```

- **`ax_media_owner` is a user ID** in the canonical endpoint (nicename changes /
  collisions never break it); the pretty alias passes a nicename which the plugin
  resolves (`ctype_digit → by id, else by slug`). Unknown owner → **404**.
- The pretty single-page alias `/media/{owner}/{id}/` stays deferred; the single
  canonical remains `/?attachment_id={id}` (§1).
- Helpers: `axismundi_media_landing_url()`, `axismundi_media_author_url( $user_id )`.

### 3.0 Optional Media Page (Settings > Reading)

A `Media page` selector (option `ax_media_page_id`) lets the site pick an existing
**published Page** as the media hub. When set, that Page **is** the landing: its
`/?page_id={id}` URL and pretty permalink render the media grid (the plugin
reshapes the page request into the landing archive and applies the `media-home`
template). This gives an editable, stable entry point that works without pretty
permalinks. The plugin never auto-creates the page; if it is unset, unpublished,
or deleted, the base/pretty routes are used. Query vars use the user **ID**, not a
slug, so nothing breaks on rename. `author.html` and normal author queries are
never touched.

### 3.1 Reserved slugs (owner-segment collision guard)

Owner nicenames MUST NOT equal a reserved segment; validate on profile
save / block the media profile for reserved names:

```
media, explore, search, o, feed, folder, objects, ax_media_object, wp-admin
```

`/media/` base also collides with a same-named page/category/tag → reserve on
plugin setup (or make the base filterable if `media` is already taken).

## 4. Template selection

The plugin provides working defaults for its archive routes; the theme may
override them. Never hard-depend on the Axismundi theme.

**Attachment single** — extend (do not replace) the core hierarchy:

```
{mime-subtype}.php → {mime-type}.php → attachment.php →
single-attachment.php → single.php → singular.php → index.php
```

The plugin does not register a duplicate Attachment single template. The active
theme owns that presentation through the normal hierarchy above. Axismundi ships
an `attachment.html` block template and enables Attachment pages; another theme
may provide its own template or use the core fallback.

**Plugin routes (archive/owner/folder/search)** resolution order:

```
1. Site Editor DB customization (wp_template)         ← user edits win
2. Active theme block template of the same slug        ← theme override
3. Plugin default block template (registered)          ← ships working
4. Core fallback (index.html / archive.html)           ← safety net
```

Mechanism: register the plugin's block templates so the Site Editor can edit
them; select via `template_include` gated on the `ax_media_*` query vars. Plugin
default templates render standalone (token fallbacks), independent of the theme.

Template set (block templates, theme-overridable by slug). These are **custom
plugin templates**, not core-hierarchy names:

```
media-home    (landing / all public media)     ← was media-archive
media-author  (one user's media; owner = post_author)   ← was media-user
media-folder · media-search · (later) media-explore
```

`media-author` deliberately mirrors `post_author` (the owner model). It is a
*plugin* template selected only for the `ax_media_archive=owner` route — it never
intercepts core `author.html` or a normal author query.
