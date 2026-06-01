# Omphalos — content-collection list/grid route (decision, pre-implementation)

> **Purpose**: cut the route for the **list / grid duality** of the content-
> collection blocks (`core/latest-posts`, `core/rss`) BEFORE any CSS, per the
> diagnostic-first lock. List view → M3 List + Divider; Grid view → a FILLED
> (non-elevated) Card grid.
> **References**: Google Drive list/grid browser (read via the user's Chrome
> session — see §1 measurements); the Card / Divider / List M3 components; the lab
> card contract (`blocks.css §8`, `--comp-card-*`).
> **Status**: route + VQA specimens only. No CSS, no `register_block_style`, no
> view-switcher block. Implementation is the NEXT step.
> **Date**: 2026-06-02 · WP 7.0 · M3 Expressive.

---

## §1 — Diagnostic: rendered DOM + reference measurements

**core/latest-posts** (dynamic):

```txt
ul.wp-block-latest-posts__list[.is-grid.columns-N][.has-dates][.has-author]
  li
    a.wp-block-latest-posts__post-title      (title link)
    div.wp-block-latest-posts__post-author   (when displayAuthor)  "by admin"
    time.wp-block-latest-posts__post-date     (when displayPostDate)
    div.wp-block-latest-posts__post-excerpt   (when displayPostContent=excerpt)
    div.wp-block-latest-posts__featured-image (when displayFeaturedImage + image)
view toggle:  postLayout = list | grid  ->  adds .is-grid.columns-N
```

**core/rss** (dynamic, external fetch):

```txt
ul.wp-block-rss[.is-grid.columns-N][.has-dates][.has-authors][.has-excerpts]
  li.wp-block-rss__item
    div.wp-block-rss__item-title > a          (title link)
    time.wp-block-rss__item-publish-date
    span.wp-block-rss__item-author            "by <name>"
    div.wp-block-rss__item-excerpt
view toggle:  blockLayout = list | grid  ->  adds .is-grid.columns-N
```

**Reference (Drive, measured via the user's session)** — the model to adapt:

```txt
view switcher = 2-segment segmented control (radiogroup; ~110px, ~55x32 segments)
list view  = role=grid rows ~48px: name / date / owner / location / trailing action ~36px
grid view  = cells ~248x248, column gap ~18px
             card inner: radius 12, background #F0F4F9, NO shadow (FILLED surface)
             header 48 (16px icon, title 14/20, trailing 32 action)
             preview pane 232x152, inset 8, radius 4
             footer/meta 48: 24px source/avatar icon, metadata 12/16
```

**The load-bearing takeaway**: the grid card is a **filled, shadow-less surface**,
not an elevated card. The list is a dense, scan-first row rhythm with a trailing
action slot.

---

## §2 — The duality is FRONT-END output, not an editor-only toggle

`postLayout` / `blockLayout` are saved block attributes that change the **rendered**
class (`.is-grid.columns-N`). So the theme styles the *output*, not an editor
control. Both blocks are **content-collection** surfaces:

```txt
list view  ->  M3 List + Divider   (dense, scan-first; title + metadata + excerpt)
grid view  ->  Filled Card grid     (item = card; title/excerpt/metadata footer)
```

---

## §3 — List view contract = M3 List + Divider  (scope: `:not(.is-grid)`)

- Item rhythm: vertical padding per item (no card chrome); the `<li>` is a list row.
- Title: `a.__post-title` / `__item-title a` → `title-small` (or `body-large`
  label weight); keeps the link affordance (prose-link excluded — it is a title).
- Metadata: date / author → `body-small` (or `label-small`) in `on-surface-variant`.
- Excerpt: `body-medium`, on-surface-variant.
- **Divider between items**: 1px `outline-variant` (the M3 list divider) — between
  `<li>`s, not after the last.
- Optional trailing action slot only if/when needed (none in core output today).
- No surface fill, no radius, no shadow — list rhythm only.

---

## §4 — Grid view contract = FILLED Card grid  (scope: `.is-grid` ONLY)

- **Scope to `.is-grid`** — never card-ify the bare list (that would break list
  semantics and "card-ify everything"). The card chrome lives behind `.is-grid`.
- Grid: the `ul.is-grid.columns-N` is the track; gap = `--space-md` (~18px ≈ md).
- Each `<li>` = a **filled card**:
  - background `surface-container` (or `-low`); **NO elevation shadow** (Drive: a
    filled surface card, not elevated — important).
  - radius `--comp-card-radius` (12); padding `--comp-card-padding`.
  - content: title (title-small), excerpt (body-medium), metadata footer
    (date/author/source → body-small / on-surface-variant).
  - `__featured-image` (latest-posts) → the optional media/preview area (radius
    smaller, inset); core/rss has no media → text-only card.
  - a Divider INSIDE the card only when the metadata footer needs separation.
- Outlined variant (outline-variant hairline) is optional/future, not the default.

---

## §5 — View switcher = editor toolbar, NOT a theme surface

The list/grid switch is a **block-toolbar** control in the editor; it is not in the
front-end output. So the theme writes **no** view-switcher CSS here, and the VQA
marks the two modes by **heading** only ("… — List view" / "… — Grid view"). If a
theme-owned, front-end "view switcher" is ever wanted, it is a separate block
(like the theme switcher), out of this route. (The Drive 2-segment radiogroup is
recorded in §1 only as the reference shape, should that block ever be built.)

---

## §6 — Token map (prefer existing; minimise new)

```txt
Layout      --space-xs/sm/md/lg ; grid gap = --space-md ; card padding = --comp-card-padding
Card (grid) --comp-card-radius (12) ; background surface-container / -low ; NO shadow
            outline-variant only for an (optional) outlined style
List        divider 1px outline-variant ; row padding-block --space-sm/md
            title  = title-small (or body-large) ; metadata = body-small / on-surface-variant
            excerpt = body-medium / on-surface-variant
Icon/action trailing action = icon-button XS (32/20) [Drive action ~32] ; source/avatar 24
View switch 2-segment group, 32px — DEFERRED (editor-only; no theme CSS now)
```

No new tokens required for the baseline list/grid contracts — they compose the
existing `--space-*`, `--comp-card-*`, `--md-sys-color-*`, and typescale tokens.

---

## §7 — Caveats

- **Dynamic + data-dependent.** Both blocks render the install's data; the seed
  posts are sparse (title + date), so the grid card looks thin without excerpt /
  featured image. core/rss depends on an **external fetch** (wordpress.org/news),
  which is unstable offline — the VQA baseline documents "render may fail"; a local
  fixture feed / proxy is a future option.
- **`.is-grid` scoping is mandatory.** Card chrome only in grid; the list keeps
  list rhythm + divider. Do not style `.wp-block-latest-posts li` / `__item`
  globally with a surface.
- **Filled, not elevated.** Per the Drive reference, the grid card is a filled
  shadow-less surface. Do not give these cards an elevation shadow.

---

## §8 — Explicitly NOT in this step

- No CSS / `register_block_style` for latest-posts or rss; no view-switcher block.
- No global `ul`/`li` styling; scope to `.wp-block-latest-posts` / `.wp-block-rss`.
- No new tokens (compose existing).

---

## §9 — Next (after review)

1. Confirm the VQA specimens: Latest Posts — List / Grid, RSS — List / Grid
   (RSS list added; latest-posts grid enriched with content).
2. **List collection contract** (blocks.css): list rhythm + divider + typescale,
   scoped to `:not(.is-grid)`.
3. **Grid collection / card contract** (blocks.css): filled card grid, scoped to
   `.is-grid` only; verify computed both schemes (filled surface, no shadow,
   radius 12, gap md).
