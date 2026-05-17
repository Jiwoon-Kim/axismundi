# Axismundi — Block ↔ Component Map

Maps Material 3 components to Gutenberg / FSE blocks. Source-of-truth for **Phase 2 — Block Theme**.

> Reading order: §0 (rules) → §1 (component → block) → §2 (FSE/templates)
> → §3 (gaps: core blocks needing M3 design) → §4 (gaps: custom blocks to ship)
> → §5 (discard / replace) → §6 (proposed work order) → §7 (open decisions).

---

## 0. Rules of thumb

### 0.1 Component → block bucket

Every M3 component falls into exactly one of six buckets. When a new component is added, classify first; implementation path follows:

| Bucket | Means | Example |
|---|---|---|
| **A. Core block + Block Style** | Block exists; M3 variant = registered Block Style | Button → filled / tonal / elevated / outlined / text |
| **B. Block Pattern** | Composition of existing core blocks | Post Card = Group + Image + Heading + Paragraph |
| **C. Custom block (plugin)** | No reasonable core equivalent | Chip, Tabs, Icon button |
| **D. Template-part / FSE** | Lives at site-chrome level, not in content flow | App bar, Navigation rail |
| **E. Discard / replace** | Doesn't fit WP paradigm — drop, or hand off to a forms/3rd-party plugin | Form text fields, FAB menu |
| **F. Theme JS only** | Pure interaction, no block | Tooltip, Snackbar, ripple/state-layer |

### 0.2 Naming — three-layer separation

| Layer | What | Naming |
|---|---|---|
| **Theme** | Brand wrapper, FSE templates, theme.json | `axismundi` (theme slug) |
| **Generic blocks plugin** | M3-spec components, reusable across themes | `m3-blocks` (plugin), `m3/*` (block namespace) |
| **Domain blocks plugin** | SNS-specific composites (Post Card, Composer, etc.) | `axismundi-blocks` (plugin), `axismundi/*` (block namespace) |

**Why split:** generic M3 components (chip, tabs, icon-button) have value to other themes / projects. Locking them in `axismundi/` namespace prevents reuse. Domain blocks (post-card, composer) are inherently Axismundi-specific and stay in `axismundi/`.

**`axismundi-blocks` depends on `m3-blocks`** — domain blocks reuse generic blocks where possible (e.g. post card composes m3/icon-button for actions).

### 0.3 Block Style naming

Use **block-scoped descriptors** to avoid collision and keep intent visible:

```
core/button:    is-style-filled, is-style-tonal, is-style-elevated, is-style-outlined, is-style-text
core/group:     is-style-card-filled, is-style-card-elevated, is-style-card-outlined
core/search:    is-style-filled-search
core/separator: is-style-divider-inset, is-style-divider-middle-inset
core/list:      is-style-list-segmented
```

Prefix card variants with `card-` (since `core/group` has many uses and `is-style-filled` is too generic). Other blocks reuse the variant name directly.

---

## 1. M3 component → Gutenberg block

### 1.1 Buttons

| M3 Component | Bucket | Mapping |
|---|---|---|
| Button — filled | A | `core/button` + `is-style-filled` (default) |
| Button — tonal | A | `core/button` + `is-style-tonal` |
| Button — elevated | A | `core/button` + `is-style-elevated` |
| Button — outlined | A | `core/button` + `is-style-outlined` |
| Button — text | A | `core/button` + `is-style-text` |
| Button group | A | `core/buttons` (parent block) |
| Icon button | C | `m3/icon-button` — supports `filled` / `filled-tonal` / `outlined` / `standard` + `toggled` |
| FAB (sm/md/lg) | D | Theme-level — fixed-position element in `templates/*.html` |
| Extended FAB | D | Same — template-part overlay |
| FAB menu | E | **Drop for v1.** Replace with bottom-sheet menu |
| Split button | C | `m3/split-button` — Tier 3, low priority |

### 1.2 Containers

| M3 Component | Bucket | Mapping |
|---|---|---|
| Card — filled | A | `core/group` + `is-style-card-filled` |
| Card — elevated | A | `core/group` + `is-style-card-elevated` |
| Card — outlined | A | `core/group` + `is-style-card-outlined` |
| List (plain) | A | `core/list` |
| List — segmented | A | `core/list` + `is-style-list-segmented` |
| List item (with leading/trailing) | B | Pattern: `core/group` (row) → Image + `core/group` (stack: Heading+Paragraph) + Icon button |
| Divider | A | `core/separator` (variants: `is-style-divider-inset`, `is-style-divider-middle-inset`) |

### 1.3 Inputs (mostly hand-off territory)

| M3 Component | Bucket | Mapping |
|---|---|---|
| Text field — filled | E | Forms plugin (CF7 / WPForms / Gravity). Theme ships **CSS** for their output |
| Text field — outlined | E | Same |
| Search field | A | `core/search` + `is-style-filled-search` |
| Checkbox | E | Forms plugin |
| Radio button | E | Forms plugin |
| Switch | E | Forms plugin |
| Slider | E | Forms plugin |
| Date picker | E | Forms plugin |
| Time picker | E | Forms plugin |

> **Why E for form controls?** A theme that owns text fields locks you out of every real form ecosystem. Style their output instead — much higher ROI.

### 1.4 Navigation / Chrome

| M3 Component | Bucket | Mapping |
|---|---|---|
| App bar (top) | D | `parts/header.html` + `core/navigation` |
| Toolbar (docked / floating) | D | Same surface, different content variant |
| Navigation bar (mobile bottom) | D | `parts/footer.html` (mobile only via container query) + `core/navigation` |
| Navigation rail — collapsed | D | `parts/sidebar.html` + vertical `core/navigation` |
| Navigation rail — expanded modal | D | Same `parts/sidebar.html` + theme JS for toggle/scrim (replaces deprecated drawer) |
| Tabs | C | `m3/tabs` (parent) + `m3/tab-panel` (children) |
| Menu (dropdown) | C | `m3/menu`. `core/navigation` submenus aren't enough |
| Search | A | `core/search` (already covered) |

### 1.5 Selection / Status

| M3 Component | Bucket | Mapping |
|---|---|---|
| Chip — assist | C | `m3/chip` (variant attr) |
| Chip — filter | C | Same — `filter` variant, supports `selected` |
| Chip — input | C | Same — `input` variant with close icon |
| Chip — suggestion | C | Same — `suggestion` variant |
| Badge | C | `m3/badge` — also exposed as a custom **rich-text format** for inline use |

### 1.6 Feedback / Overlays

| M3 Component | Bucket | Mapping |
|---|---|---|
| Dialog | F + C | Theme JS for behavior + `m3/dialog-trigger` block to open |
| Sheet (bottom) | F + C | Same model — trigger block + JS |
| Sheet (side) | F + C | Same |
| Snackbar | F | Pure theme JS — no block. Imperative `m3.snackbar(msg)` API |
| Tooltip | F | Pure JS/CSS — `data-m3-tooltip="..."` on any element |
| Loading indicator | F | Pure JS — only used inside interactive blocks |
| Progress indicator | C | `m3/progress` (linear/circular + determinate/indeterminate) |

### 1.7 Display

| M3 Component | Bucket | Mapping |
|---|---|---|
| Carousel | C | `m3/carousel` — `core/gallery` is grid-only |

---

## 2. FSE / template-part level

| Surface | WP construct | Composed of |
|---|---|---|
| Site header | `parts/header.html` | Site Logo + Navigation (M3 App bar) |
| Site footer | `parts/footer.html` | Navigation + Site Title (M3 Nav bar on mobile via CQ) |
| Sidebar (rail) | `parts/sidebar.html` | vertical Navigation + Suggestions pattern |
| Front page (feed) | `templates/front-page.html` | Header + Sidebar + Query Loop + FAB (theme overlay) |
| Single (article) | `templates/single.html` | Header + Title + Featured Image + Post Content (serif) + Comments |
| Profile | `templates/single-axismundi_profile.html` | Header + ProfileHead pattern + Query Loop |
| Archive (tag/explore) | `templates/archive.html` | Header + Sidebar + Filter Chips row + Query Loop |
| Search | `templates/search.html` | Header + Search Results Title + Query Loop |
| 404 | `templates/404.html` | Header + Group + Search |

**CPT (Custom Post Type) decision:** keep `axismundi_profile` per Phase-1 `CONTEXT.md`. CPT slug uses theme namespace because profile semantics are Axismundi-specific (ActivityPub Actor mapping, federation metadata). Generic block plugin doesn't define CPTs.

---

## 3. Gaps — core blocks that need M3 design *(Step 4)*

These Gutenberg core blocks are **not mapped from any M3 component** but ship in WordPress and people will use them. Each needs an M3-flavored treatment so it doesn't fall out of the system.

### Text *(most inherit type roles automatically)*
- Paragraph → `body-large`
- Heading h1–h6 → `display-small` / `headline-large` / `headline-medium` / `headline-small` / `title-large` / `title-medium`
- List → `body-large`, list-style outside, `gap-10`
- Quote → indent + 4px left border in `outline-variant`, italic body
- Pullquote → `headline-medium`, serif, centered, top/bottom dividers
- Code / Preformatted → `mono` family, `surface-container-low` bg, `radius-sm`
- Verse → `serif` family
- Table → outlined borders, header in `surface-container-high`, body in `surface-container-low`
- Details → `outline-variant` border + state layer on summary, chevron
- Math → preserve KaTeX/MathJax styles, body color

### Media
- Image / Cover / Gallery / Video → `radius-md` + `surface-container` placeholder
- File → outlined card layout, mono filename, trailing icon-button (download)
- Audio → `surface-container-low` bg, custom player control colors
- Media & Text → Group pattern with `gap-30`

### Design
- Columns / Row / Stack / Grid / Group → no visible chrome, just consume surface
- Spacer / Separator → already mapped to Divider
- More / Page Break → minimal styling
- Accordion → state layer on summary, chevron rotates with `default-spatial` spring on toggle, `outline-variant` divider between items
- Buttons (parent) → `gap-20`

### Widgets *(lower priority for v1)*
- Latest Posts / Latest Comments / RSS → render each item as `axismundi/post-card` pattern
- Calendar → `surface-container` bg, mono digits, `radius-sm`
- Page List / Categories / Tag Cloud → `chip-suggestion` row layout (Tag Cloud gets Chip styling)
- Search → already mapped
- Social Icons → `m3/icon-button` style with `filled-tonal`
- Custom HTML / Shortcode → no styling — passthrough

### Theme *(critical — feed depends on these)*
- Query Loop → no chrome, just spacing
- Post Template → wraps each post as a Card (filled or outlined per variant)
- Post Title / Excerpt / Featured Image / Date / Author / Avatar / Categories / Tags
  → all become fields inside the Post Card pattern
- Pagination → `text` button style + `outlined` for current page
- Read More / Next-Previous → `outlined` button
- Comments + sub-blocks → ProfileHead-mini pattern (avatar + name + date) + body + reply icon-button

### Embeds
All embeds go inside an outlined card with `radius-md`, lazy-load with `surface-dim` placeholder, `4:5`/`16:9` aspect-ratio default.

---

## 4. Gaps — custom blocks to ship *(Step 5)*

Two plugins, dependency direction: **`axismundi-blocks` → depends on → `m3-blocks`**.

### 4.1 `m3-blocks` plugin — generic M3 components

Reusable across any theme. M3-spec aligned, no Axismundi domain logic.

**Tier 1 — feed surface essentials:**
- `m3/icon-button`
- `m3/chip` (variant: assist / filter / input / suggestion)

**Tier 2 — navigation / UX completeness:**
- `m3/tabs` + `m3/tab-panel`
- `m3/menu`
- `m3/dialog-trigger`
- `m3/sheet-trigger`

**Tier 3 — nice to have:**
- `m3/badge` (or rich-text format)
- `m3/progress`
- `m3/carousel`
- `m3/split-button`

### 4.2 `axismundi-blocks` plugin — SNS domain composites

Axismundi-specific. Depends on `m3-blocks` for primitives.

**Tier 1 — feed surface (build first):**
- `axismundi/post-card` (registered as a single block — wraps the pattern for editor ergonomics)
- `axismundi/composer` (textarea + visibility selector + char counter)

**Tier 2 — additional surfaces:**
- `axismundi/profile-head` (banner + avatar + stats + follow button)
- `axismundi/thread-view` (post + composer + reply chain)

> Why split this way: `m3/icon-button` could be used by anyone building a Material-style WP theme. `axismundi/post-card` only makes sense in an ActivityPub-style microblog. Lock-in matches usage scope.

---

## 5. Components to discard or replace *(Step 6)*

| Component | Decision | Reason | Replacement |
|---|---|---|---|
| Text field / checkbox / radio / switch / slider | **Drop from theme** | These belong to forms plugins, not a content-layout theme | Ship CSS that themes CF7 / WPForms / Gravity output |
| Date picker / Time picker | **Drop** | Same — form territory | Same |
| FAB menu | **Drop for v1** | Complex interaction, low ROI for SNS surface | Bottom-sheet menu |
| Bottom sheet (modal) | **Defer to v1.5** | Heavy a11y work; tab-trap, focus, scroll-lock | Trigger block ships; full impl in v1.5 |
| Side sheet | **Keep** | Maps cleanly to expanded nav rail (modal layout) | — |
| Navigation drawer | **Drop as separate construct** | Merged into Nav rail expanded modal in M3 Expressive | Use Nav rail expanded |
| Tooltip | **Keep as JS only** | Not block-paradigm but trivial in JS/CSS | `data-m3-tooltip` attribute |
| Loading indicator (full-screen) | **Drop** | Server-rendered theme has no use | Restrict to interactive blocks only |

---

## 6. Proposed order of work

**Theme work** (first — uses only core blocks + Block Styles):

1. **`theme.json` finalize** — surface containers, type scale, spacing, all Block Style hooks
2. **Block Styles registration** — Button (5 styles), Group as Card (3 styles), Search, Separator, List
3. **Block Patterns** — Post Card (initial pattern version, before block plugin upgrade), Profile Head, Composer, Filter Chips Row, Suggestions, Trends
4. **Template parts** — `header.html`, `footer.html`, `sidebar.html`
5. **Templates** — `front-page.html`, `single.html`, `archive.html`, `404.html`, `single-axismundi_profile.html`
6. **Theme JS** — snackbar, tooltip, dialog, sheet, ripple/state-layer enhancement (no block deps yet)

**`m3-blocks` plugin** (build before `axismundi-blocks`):

7. **Tier 1** — `m3/icon-button`, `m3/chip`
8. **Tier 2** — `m3/tabs`, `m3/menu`, `m3/dialog-trigger`, `m3/sheet-trigger`
9. **Tier 3** (post-launch) — `m3/badge`, `m3/progress`, `m3/carousel`, `m3/split-button`

**`axismundi-blocks` plugin** (depends on m3-blocks):

10. **Tier 1** — `axismundi/post-card`, `axismundi/composer` (replaces Pattern versions from step 3)
11. **Tier 2** — `axismundi/profile-head`, `axismundi/thread-view`

---

## 7. Decisions still open *(needs user input)*

1. **Forms scope.** Confirmed E-bucket for all form controls — themed via plugin output, not owned. ✅
2. **FAB menu.** Drop for v1 — confirm? -> [ ]
3. **Tier-2 priority.** Tabs first (article surface needs them) or Dialog/Sheet first (compose & reply need them)? -> [ ]
4. **Profile as CPT vs page-template.** `axismundi_profile` custom post type per `CONTEXT.md` — keep? ✅ (per §2 decision)
5. **Block Style naming.** Use `is-style-card-*` (descriptive prefix) for Group variants, plain `is-style-{variant}` for Button — confirm? ✅ (per §0.3 decision)
6. **`m3-blocks` plugin distribution.** Bundle inside theme for v1 (single zip) or ship as separate plugin from start? -> [ ]
   - Bundle: simpler ship, but blocks die if user switches theme
   - Separate: proper architecture, but two installs for end user
   - Recommendation: bundle for v1 (theme-required-plugin pattern via TGM), split when reaching critical mass
