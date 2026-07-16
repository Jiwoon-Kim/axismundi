# Axismundi Media Library — Routing & Templates

> Status: **Living specification. Plain query endpoints and the pretty `/media/…`
> object/collection routes are implemented through v0.0.21.** See
> SPEC.md §3 (identity) and SECURITY.md §4.1 (the HTML `template_redirect` guard) —
> this doc defines how requests reach that guard and which template renders.

## 0. Object URLs vs Collection URLs

Two namespaces under `/media/`, kept deliberately distinct so an **object** (one
media item) can never be confused with a **collection** (a browse surface), and so
Drive-style folders and Openverse-style objects grow in the same space without
colliding.

```
OBJECT (one item — identity is attachment_id)
  /?attachment_id={id}              plain, always-canonical base
  /media/image/{id}/                pretty, type = image
  /media/video/{id}/                pretty, type = video
  /media/audio/{id}/                pretty, type = audio
  /media/file/{id}/                 pretty, type = other

COLLECTION (a browse surface)
  /media/                                              home
  /media/author/{nicename}/                            author media archive
  /media/author/{nicename}/folder/{path}/              author folder / board

FOLDER IDENTITY (federation; not a browse surface)
  /media/folder/{uuid}              canonical folder identity URI (immutable)
  /media/folder/{uuid}/page/{n}     OrderedCollectionPage
```

**A folder has two URLs and they are not interchangeable.**
`/media/author/{nicename}/folder/{path}/` is the *display* permalink: it is scoped to an
owner and it changes on every rename, move, and ownership transfer. A federated folder's
identity must not. So a folder's canonical identity is the UUID-keyed
`/media/folder/{uuid}`, minted from the Actors identity registry at folder creation and
immutable thereafter (FEDERATED-MEDIA.md §12; Actors DATA-MODEL.md §2.1). The top-level
`folder` segment was already reserved and was unused by the author-scoped display route.
`{uuid}` and `{path}` cannot collide: a path segment is a folder name, and a folder named
as a bare UUIDv4 still resolves under its owner's tree, never at the top level.

**Reserved first segments under `/media/`:** `image`, `video`, `audio`, `file`,
`author`, `folder`, `search`, `feed`. Because every user lives under
`/media/author/{nicename}/`, owner nicenames no longer collide with these reserved
words or with folder paths — the earlier owner-segment collision guard is gone.

The parallel is WordPress Photo Directory (`/photos/photo/{object}`,
`/photos/author/{user}`, `/photos/`).

## 1. Object (single) — identity is `attachment_id`

**The identity is always the numeric `attachment_id`.** `image|video|audio|file` is
only a **display / routing hint** derived from the file's MIME family:

```
image/*  → image      video/*  → video      audio/*  → audio      (anything else) → file
```

Two forms, same object:

```
pretty permalinks ON   → /media/{type}/{id}/
pretty permalinks OFF  → /?attachment_id={id}    (the WordPress-native single request)
```

- Use **`attachment_id`** (numeric), never **`attachment`** (a mutable, collision-
  prone `post_name` slug).
- `attachment_link` is filtered (Independent mode): pretty → `/media/{type}/{id}/`,
  plain → `/?attachment_id={id}`. On plain-permalink sites the query URL stays
  canonical; wp-env (no `.htaccess`) exercises exactly that path.

### 1.1 Canonical redirects

- **Wrong type in the URL** (`/media/video/192/` for an image) → **301** to the
  correct `/media/image/192/`. The id is authoritative; the type segment is
  corrected, never trusted.
- **MIME family changed** (a file replaced so image→video) → the old type URL
  **301**s to the new canonical type URL. The `attachment_id` — and therefore the
  object's identity, owner, and folder — is unchanged; only the presentational type
  segment moves.
- Old/previous attachment permalinks (`?attachment=slug`, legacy pretty) → **301**
  to the current canonical (pretty `/media/{type}/{id}/` or plain
  `/?attachment_id={id}`).
- On pretty sites `redirect_canonical()` derives its target from
  `get_attachment_link()`, so forcing the `attachment_link` filter is sufficient —
  do not add a competing manual redirect.

### 1.2 `wp_attachment_pages_enabled` ownership

Modern core can route attachment requests to the **file URL** when attachment pages
are disabled. Independent mode requires attachment pages **on**.

Policy (**A**) — owned change with a restore guard. When Independent mode is
enabled, store three things:

```
ax_media_attach_pages_prev   the value before we changed it
ax_media_attach_pages_set    the value we wrote (so we can detect our own value)
ax_media_attach_pages_owned  true while we hold the option
```

- **Restore only if we still own it**: on Independent-mode-off / deactivation,
  restore `prev` **only when the current option value equals `set`**. If another
  admin/plugin changed it (current ≠ set), leave it and drop ownership.
- **Re-acquire on reactivation**: reactivating with Independent mode still on
  re-reads the current value into `prev`, re-enables pages, and re-takes ownership.

## 2. `object_uri` endpoint (reserved, not Phase 1)

```
object_uri (federation/identity)  /?ax_media_object={id}    ← Phase 7, reserved
HTML canonical (human)            /media/{type}/{id}/  |  /?attachment_id={id}
```

`ax_media_object` is kept **distinct** from `attachment_id` so a future
ActivityPub/JSON-LD/Tombstone endpoint (content negotiation) never collides with the
human HTML request. Per SPEC.md §3, 0.1.0 **reserves the format but does not
persist** `object_uri`.

## 3. Collection archives — plain query endpoints are the base

The **always-working base is a query string** (it survives when pretty permalinks
are off, e.g. no `.htaccess`); the pretty `/media/…` URLs and the optional Media
Page are aliases over it. Only collections use custom rewrite; the single object
inherits the core attachment query (§1).

```
Base endpoints (always available in Independent mode)
/?ax_media_archive=landing
/?ax_media_archive=owner&ax_media_owner={USER_ID}
/?ax_media_archive=folder&ax_media_owner={USER_ID}&ax_media_folder={TERM_ID}

Pretty aliases (when a permalink structure is set)
/media/                                      → landing
/media/author/{nicename}/                    → owner   (nicename resolves to the user)
/media/author/{nicename}/folder/{path}/      → folder  (path resolves within the owner's tree)
```

- **`ax_media_owner` is a user ID** in the canonical endpoint (nicename changes /
  collisions never break it); the pretty alias passes a nicename which the plugin
  resolves (`ctype_digit → by id, else by slug`). Unknown owner → **404**.
- **`ax_media_folder` is a folder term ID** in the canonical endpoint; the pretty
  alias resolves the `{path}` within the owner's folder tree (by name segment, not
  global slug — two users may both have `travel/`). Unknown folder → **404**.
- Helpers: `axismundi_media_landing_url()`, `axismundi_media_author_url( $user_id )`,
  `axismundi_media_folder_url( $user_id, $term_id )`.
- Folder path segments `page` and `feed` are reserved for pagination/feed
  routing and cannot be used as folder names.

### 3.0 Optional Media Page (Settings > Reading)

A `Media page` selector (option `ax_media_page_id`) lets the site pick an existing
**published Page** as the media home. When set, that Page **is** the landing: its
`/?page_id={id}` URL and pretty permalink render the media grid (the plugin reshapes
the page request into the landing archive and applies the `media-home` template).
This gives an editable, stable entry point that works without pretty permalinks. The
plugin never auto-creates the page; if it is unset, unpublished, or deleted, the
base/pretty routes are used. `author.html` and normal author queries are never
touched.

### 3.1 `/media/` base collision

The `/media/` base can still collide with a same-named page/category/tag → reserve
on plugin setup (or make the base filterable if `media` is already taken). Per-user
segments no longer need guarding — they live under `/media/author/`.

## 4. Template selection

The plugin provides working defaults for its routes; the theme may override them.
Never hard-depend on the Axismundi theme.

**Object (attachment single)** — extend (do not replace) the core hierarchy. The
type segment does not need a bespoke single template; use the core MIME hierarchy
for type-specific design:

```
{mime-subtype}.php → {mime-type}.php → attachment.php →
single-attachment.php → single.php → singular.php → index.php
```

So `image.html` / `video.html` / `audio.html` cover per-type presentation while the
identity/URL stays `attachment_id`. The plugin does not register a duplicate
attachment single template; Axismundi ships `attachment.html` and enables attachment
pages. A different theme may provide its own or use the core fallback.

**Collection routes** resolution order:

```
1. Site Editor DB customization (wp_template)          ← user edits win
2. Active theme block template of the same slug         ← theme override
3. Plugin default block template (registered)           ← ships working
4. Core fallback (index.html / archive.html)            ← safety net
```

Mechanism: register the plugin's block templates so the Site Editor can edit them;
select via `template_include` gated on the `ax_media_*` query vars. Plugin defaults
render standalone (token fallbacks), independent of the theme.

Template set (block templates, theme-overridable by slug) — **custom plugin
templates**, not core-hierarchy names:

```
media-home    (landing / all public media)
media-author  (one user's media; owner = post_author)
media-folder  (one folder / board)
media-search · (later) media-explore
```

`media-author` deliberately mirrors `post_author` (the owner model). It is a *plugin*
template selected only for the `ax_media_archive=owner|folder` routes — it never
intercepts core `author.html` or a normal author query.
