# Axismundi Media Library — Routing & Templates

> Status: **Design draft (pre-code).** See SPEC.md §3 (identity) and SECURITY.md
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

## 3. Archive & folder rewrites

Only archives/folders use custom rewrite; the single page does not.

```
/media/                         media landing              (Phase 1)
/media/{owner}/                 user media archive         (Phase 1)
/media/{owner}/folder/{path}/   virtual folder / board     (Phase 2)
/media/search/                  media search               (Phase 2+)
/media/explore/                 explore feed               (later)
```

- The literal **`folder`** segment prevents `{attachment-id}` / `{folder-slug}`
  ambiguity under `/media/{owner}/…`.
- Query vars: `ax_media_archive` (landing|owner|folder|search|explore),
  `ax_media_owner`, `ax_media_folder_path`.
- A **pretty alias** `/media/{owner}/{id}/` for the single page is **deferred**;
  0.1.0 ships the query URL only, removing owner-slug/rewrite risk. If added
  later, decide the canonical direction (alias→query or query→alias) then.

### 3.1 Reserved slugs (owner-segment collision guard)

Owner nicenames MUST NOT equal a reserved segment; validate on profile
save / block the media profile for reserved names:

```
media, explore, search, o, feed, folder, objects, ax_media_object, wp-admin
```

`/media/` base also collides with a same-named page/category/tag → reserve on
plugin setup (or make the base filterable if `media` is already taken).

## 4. Template selection

The plugin provides working defaults; the theme may override. Never hard-depend
on the Axismundi theme.

**Attachment single** — extend (do not replace) the core hierarchy:

```
{mime-subtype}.php → {mime-type}.php → attachment.php →
single-attachment.php → single.php → singular.php → index.php
```

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

Template set (block templates, theme-overridable by slug):

```
Media Archive · Media User Archive · Media Folder · Media Search ·
Media Attachment · (later) Media Explore
```
