# Omphalos — Core Theme blocks VQA route (diagnostic baseline, pre-implementation)

> **Purpose**: cut the route for the WordPress **Theme** block category (site
> identity, navigation, Query Loop + post-context blocks, post navigation, comments,
> term/archive). These blocks are CONTEXT-dependent (post / query / comment / term /
> site), so the route is decided BEFORE any pattern/CSS, per the diagnostic-first
> lock, and the VQA is split by context so invalid/fallback specimens don't mix.
> **Canonical source**: the RUNTIME registry (`WP_Block_Type_Registry`) — verified
> against this install. WordPress docs ([Blocks list](https://wordpress.org/documentation/article/blocks-list/),
> [Theme blocks category](https://wordpress.org/documentation/category/theme-blocks/))
> are SECONDARY: the category index is incomplete and uses pre-7.0 names, and the
> blocks-list is broader than what is actually registered.
> **Status**: route + registry reconciliation. No seed / pattern / CSS yet.
> **Date**: 2026-06-03 · WP 7.0 · M3 Expressive.

---

## §1 — Registry reconciliation (the load-bearing diagnostic)

This install registers **50 `category=theme` blocks**. Two traps the docs hide:

**A. The WP 7.0 "renames" are EDITOR LABELS only — the block NAME is unchanged.** VQA
markup must use the registered name or the block is invalid:

```txt
label "Title"          → core/post-title
label "Excerpt"        → core/post-excerpt
label "Featured Image" → core/post-featured-image
label "Author"         → core/post-author
label "Author Name"    → core/post-author-name
label "Author Bio"     → core/post-author-biography
label "Date"           → core/post-date
label "Tags"/"Categories" content → core/post-terms (one block, taxonomy attr)
label "Comments Form"  → core/post-comments-form
label "Time to Read"   → core/post-time-to-read
label "Comments Count" → core/post-comments-count
label "Comments Link"  → core/post-comments-link
```

**B. Several "blocks" are actually VARIATIONS of another block, or are NOT registered
at all here:**

```txt
VARIATIONS (not separate blocks):
  "Word Count"           = core/post-time-to-read variation
  "Modified Date"        = core/post-date variation (no core/post-modified-date)
  "Archive Title"        = core/query-title variation (no core/archive-title)
  "Search Results Title" = core/query-title variation (no core/search-results-title)

NOT REGISTERED here → EXCLUDE:
  core/post-types-label   (not in this WP build)
  core/posts-list         (deprecated / removed — "not replaced")
```

**Full registered theme set** (50): site-logo, site-title, site-tagline · navigation,
navigation-link, navigation-submenu, home-link, breadcrumbs · query, post-template,
query-no-results, query-pagination(+next/numbers/previous), query-title, query-total ·
post-content, post-title, post-excerpt, post-featured-image, post-date, post-terms,
post-author, post-author-name, post-author-biography, avatar, post-time-to-read,
read-more, post-navigation-link · post-comments-count, post-comments-link,
post-comments-form, post-comments(legacy) · comments, comment-template,
comment-author-name, comment-content, comment-date, comment-edit-link,
comment-reply-link, comments-title, comments-pagination(+next/numbers/previous) ·
term-name, term-count, term-description, term-template, terms-query · template-part,
loginout · pattern.

---

## §2 — Context dependency → split the VQA

A single page would mix invalid/fallback specimens, because each family needs its own
context:

```txt
Query Loop + post blocks → a query/postId context (the Post Template loop)
Comments family          → a comment context (a post WITH comments)
Term / Terms Query        → an archive / term context
Navigation / Template Part→ a site / template context
```

---

## §3 — VQA split

```txt
/vqa-theme/           Phase 1 — site identity · navigation · Query Loop + post meta
                                · post navigation · template infra
/vqa-theme-comments/  Phase 2 — Comments block family + Comments Form + the parked
                                Widgets Latest Comments
/vqa-theme-archive/   Phase 3 — Term blocks, Terms Query, query-title variations
                                (Archive/Search), + parked Widgets Archives /
                                Categories / Page List
```

---

## §4 — Phase 1 (`/vqa-theme/`) block set (registered names)

```txt
Site identity   core/site-logo · core/site-title · core/site-tagline
Navigation      core/navigation (+ core/navigation-link · core/navigation-submenu
                · core/home-link) · core/breadcrumbs
Query/Post      core/query > core/post-template > [ core/post-featured-image
                · core/post-title · core/post-excerpt · core/post-date
                · core/post-author (+ core/avatar / core/post-author-name)
                · core/post-terms · core/read-more · core/post-time-to-read
                · core/post-comments-count · core/post-comments-link ]
                + core/query-pagination · core/query-total
Post nav        core/post-navigation-link
Infra           core/template-part · core/loginout
```

Deferred out of Phase 1: comments family (§3 P2), term/terms-query + query-title
archive/search variations (§3 P3), Word Count (post-time-to-read variation, with P3),
post-content (already covered by prose/media/text VQA — minimal specimen only).
Excluded entirely: post-types-label, posts-list.

---

## §5 — Caveats (learned traps)

- **`core/navigation` is static-save + canonical-risk** (same trap as accordion /
  terms-query). Start from a MINIMAL menu and capture the canonical markup from the
  editor; do not hand-author a rich nav. Verify editor validity (no "block contains
  unexpected/invalid content") before trusting it.
- **`core/terms-query` / `core/term-template`** are also static-save containers →
  Phase 3, canonical-from-editor.
- **Query Loop is dynamic** and renders this install's real posts → the seed must
  supply enough posts (featured / no-featured), terms, authors, and prev/next context
  for the specimens to be meaningful.
- **`core/post-comments`** is the LEGACY comments block (deprecated in favour of
  `core/comments`); note it, don't specimen it.

---

## §6 — Write scope & validation plan

- **This step**: route only. Next: `seed-vqa-theme.php` (demo posts/pages/cats/tags/
  comments + featured/no-featured + prev/next), `patterns/vqa-theme.php` (Phase 1
  set), `seed.ps1` wiring. CSS-0 — observe registration / render / context / validity
  only, no theme CSS yet.
- **Validation**: `serialize_blocks(parse_blocks($body)) === $body` round-trip for the
  pattern; front-end render of every specimen (no PHP/console errors); editor opens
  the page with NO block-invalid warnings (esp. navigation). Computed CSS is a LATER
  step.

---

## §7 — CSS-0 observation → M3 component mapping (contract route)

Baseline observed on `/vqa-theme/` (current theme base, no theme-block CSS yet). The
load-bearing finding: **the Query-Loop post-meta blocks inherit the prose
always-underline link style** (they sit in `.wp-block-post-content`), so terms,
read-more, comments-link, and pagination all render as underlined primary BODY LINKS
with no structure — a wall of links, not an object card with a metadata cluster.

**Baseline computed (dark):**
```txt
nav-link    14 / on-surface / no-underline / no padding   (already text-link-ish)
breadcrumbs 16 / on-surface / "Home▮VQA Theme" no separator
terms       12 / primary / UNDERLINED                     (prose link)
read-more   16 / primary / UNDERLINED                     (prose link)
comments-lk 16 / primary / UNDERLINED                     (prose link)
pagination# 14 / primary / UNDERLINED                     (prose link)
date        12 / on-surface-variant                       (ok = body-small meta)
author-name 16 / on-surface                               (too big for meta vs date 12)
navigation  → renders the PAGE-LIST FALLBACK, not the inline menu (canonical risk).
```

**M3 component mapping (the contract route — observe-first, per priority A–F):**
```txt
A. Site identity + Navigation
   site-logo   → brand mark (size/align contract); site-title → title; tagline → body
   navigation  → Navigation Bar: items are TEXT links (NOT tabs/buttons); M3 spacing
                 + state layer; submenu → Menu SURFACE (surface-container + elevation)
   breadcrumbs → compact navigation aid: body-small links + a separator, on-surface-variant
B. Query Loop / Post Template → Card / List (reuse the collections ontology)
   list  → teaser-card stack ; grid → filled card grid ; featured-image → card media
   read-more → TEXT BUTTON (not a prose link)
C. Post-meta cluster (de-prose-ify — this is the biggest baseline gap)
   terms       → chips (assist/suggestion) OR de-underlined meta links
   date/author → body-small / on-surface-variant metadata row (one rhythm)
   comments-count/-link → text action, not a body link
D. Author identity
   avatar (size matrix) + author/author-name → compact identity row ;
   author-biography → supporting text
E. Pagination / Post navigation
   query-pagination(numbers/prev/next) → Pagination component (current-page emphasis);
   post-navigation-link → text link / nav button (mind the button-vs-link contract)
F. Comments family → separate phase (/vqa-theme-comments/), many M3 components mixed

First contracts: A (nav) + B (query-loop card/list). NOTE: navigation needs a real
`wp_navigation` menu (a bare nav falls back to the page list) before the
submenu/home-link/custom-link specimens are observable — seed one for the A contract.

---

## §8 — Theme blocks are CHROME, not prose (first contract — DONE)

The single biggest fix: theme blocks are template CHROME, so they must NOT inherit
the prose long-form link treatment (the §9 always-underline) nor the prose body size.

**Why the baseline looked wrong (the post-content-context trap):** the VQA renders
these blocks INSIDE `.wp-block-post-content`, where a real header/footer would render
them in a template part. So `core/site-title` (a `<p>`) inherited the 16px body size
(not TT5's ~22px header size, which comes from the header instance/context), and
every meta/title link inherited the prose underline+primary. This is a VQA-context
artifact AND a real leakage wherever a theme block is placed in content.

**Fix (blocks.css §18, first cut — loads after prose.css, ties+wins on order):**
- Site identity: `core/site-title` → **title-large (22/28)** GLOBALLY (a site/brand
  title, not a body line); `core/site-tagline` → body-small / on-surface-variant.
  Logo set via `-SetDemoLogo`; tagline seeded if empty (both site-owner data).
- De-prose-ify the theme-block links: post-title → on-surface HEADLINE (no resting
  underline), meta/nav links (terms, comments-count/-link, pagination,
  post-navigation-link) → on-surface-variant METADATA (no resting underline),
  read-more keeps the primary affordance; all underline on hover/focus only.

Verified computed (dark): site-title 22/28/400; tagline 12 on-surface-variant;
post-title on-surface no-underline; terms/comments/pagination on-surface-variant
no-underline; read-more primary no-underline.

**Still first-cut** (deferred to the per-family contracts §7): terms → chips,
read-more → text button, pagination component, the metadata-cluster layout, the
navigation/submenu surfaces, and bumping `core/post-title` to its card headline size
(the B contract).

---

## §9 — Navigation (A contract) — real-menu seed + CSS-0 DOM/overlay diagnosis

A bare `core/navigation` renders the **page-list fallback** (canonical risk, §5). So
the A contract first needs a real menu: `scripts/seed-vqa-theme.php` seeds an
idempotent `wp_navigation` post (slug `vqa-theme-nav`: home-link · About · Blog ·
More→[Categories · Tags] · loginout), and `patterns/vqa-theme.php` looks it up at
pattern-include time and references it via `{"ref":N}` (PHP-in-pattern runs at
registration). **Verified**: the specimen renders the ACTUAL menu (Home · About · Blog
· More · Categories · Tags · Log in), submenu present, NO page-list fallback.

**CSS-0 DOM/overlay diagnosis** (dark; wide 1000px + narrow 380px, no nav CSS yet):

```txt
DESKTOP (wide)
  nav            <nav> display:flex  (horizontal bar)
  nav item       <a> 14px / on-surface (rgb 230,224,233) / underline:NONE / pad:0
                 → already de-prosed by §18; reads as a text nav item
  submenu trig   a.wp-block-navigation-item__content + chevron SVG (has icon)
  submenu panel  position:absolute · visibility:hidden · opacity:0 (CSS hover/focus
                 toggle) · display:flex · bg rgb(20,18,24)  ← flat, NO elevation/shadow

MOBILE (narrow, overlayMenu:"mobile")
  toggle         <button> SVG-icon (hamburger) · 24×24 · color on-surface
  overlay open   position:fixed · inset:0 · bg rgb(20,18,24) OPAQUE · FULL-SCREEN
  close          <button> SVG-icon (X)
```

**Observed → M3 target (mapping notes, NOT contracts — no CSS this step):**

```txt
desktop nav item → M3 text nav item: §18 already did color/size/no-underline; the gap
                   is M3 spacing + a state layer (hover/focus/active), not type/color.
submenu panel    → M3 Menu SURFACE: surface-container + elevation (shadow) + corner +
                   padding. Currently a FLAT absolute dropdown with no elevation.
mobile toggle    → M3 ICON BUTTON, Material Symbols `menu`. Currently raw core SVG.
mobile overlay   → user wants a SIDE MODAL SHEET; core gives a FULL-SCREEN fixed
                   inset:0 modal. Gap = anchor to a side + max-inline-size + scrim
                   behind, not inset:0 fill.
close            → M3 ICON BUTTON, Material Symbols `menu_open` / `close`.
```

The three real gaps (item type/color already handled by §18): (1) submenu = flat
popover with no elevation; (2) overlay = full-screen, not a side sheet; (3)
toggle/close = raw core SVG, not M3 icon buttons. Backlog refs for the contract:
styleguide `#components-sheet` ("Static — side modal"), `lab-popover-pattern` (submenu
surface), icon-system (Material Symbols `menu`/`menu_open`).

### §9.1 — Submenu is CONTEXT-SENSITIVE (the load-bearing scoping rule)

A bare `.wp-block-navigation__submenu-container` must **never** be styled as a popover
globally — core/TT5 already lay it out two different ways (verified on this install):

```txt
header/horizontal (>=600px, hamburger hidden)
  submenu = position:ABSOLUTE detached dropdown, bg, boxShadow:NONE (reads FLAT)
  → M3 target: Menu SURFACE (tone + elevation + corner)
responsive overlay (<600px, .is-menu-open)
  submenu = position:STATIC, bg:transparent, no border/shadow, padding 19.2/32/0
  → already a COLLAPSIBLE nested nav section (NOT a popover); leave core's layout
```

**Breakpoint = exactly 600px** (sweep: hamburger <=599px, inline >=600px). So the
desktop dropdown-surface CSS lives entirely inside `@media (min-width:600px)`; below
600px no rule touches the submenu, so the overlay keeps its nested layout. NO reset
needed because no global popover rule exists. TT5 confirms the split — never collapse
the two contexts into one `.wp-block-navigation__submenu-container` popover patch.

### §9.2 — A1 + A2 implemented (blocks.css §19, DESKTOP-ONLY first cut — DONE)

- **A1 nav item** (both contexts, type only + de-underline): label-large (14/20/500,
  0.1px), resting underline removed (the post-content-scoped selector ties the §9
  prose rule and wins by order). Items are DESTINATION LINKS — no `.wp-element-button`.
- **A1 desktop state layer** (`@media min-width:600px`): item gets padding (4/8),
  corner-small radius, hover = on-surface @ 8%, focus-visible = on-surface @ 12% +
  primary outline. (Below 600px the overlay rows keep core's layout — A3.)
- **A2 desktop submenu surface** (`@media min-width:600px`): the container carries the
  `has-base-background-color` PRESET utility (`background-color: var(--wp--preset--
  color--base) !important`), so the surface tone MUST also be `!important` to win
  (specificity/order can't beat a preset important). → surface-container-high +
  outline-variant hairline + corner-small + elevation-shadow-level2 + menu-row padding.
  Dark suppresses shadow (tonal elevation), so the high surface tone + hairline
  delineate the menu; light gets the real shadow.

Verified computed: item 14/20/500 no-underline, hover on-surface@8%, radius 8px;
submenu dark bg rgb(43,41,48)=surface-container-high (vs page rgb(20,18,24)) shadow
none, light bg rgb(236,230,240) + real shadow; overlay submenu UNCHANGED
(static/transparent/no-shadow — no leak). Screenshots confirm a proper menu surface.

**Deferred (separate contracts):** A3 overlay side-sheet (full-screen `fixed inset:0`
→ side modal sheet + scrim + max-inline-size; leans on core interactivity/markup),
A4 toggle/close icon-button swap (raw core SVG → Material Symbols `menu`/`menu_open`;
needs icon-system + possibly markup/filter), and nav current/active treatment (until
the current-menu class is observed).

### §9.3 — VQA sitemap menu + overlay-visibility / nested / divider diagnosis

The 1-level "More" specimen was too shallow. The seed menu (`vqa-theme-nav`) was rebuilt
as a **VQA sitemap with 2–4 levels of nesting**, real `?page_id=` / `?attachment_id=`
permalinks (ASCII, mojibake-safe): Home · VQA → [Prose · Text · Media · Design · Widgets
· VQA Theme → [Embeds · Embed Template] · Attachments → [Images → [webp · jpeg · png ·
wide] · Audio · Video]] · Log in. Round-trip stable; nested depth verified (4 submenu
containers, max nest depth 3). The pattern now also carries three overlay-visibility
specimens (`never` / `mobile` / `always`), all `justifyContent:left` (deep submenus read
ragged when right-justified — the user's call; alignment follows the nav toolbar's
justify-items, a per-block layout attr).

**Header vs content context (resolves the "header reacts to theme color, content doesn't"
observation):** the omphalos `header` template part is just `<!-- wp:pattern
{"slug":"twentytwentyfive/header"} /-->` — so the real site-header nav is the **TT5
header pattern's** navigation, which sets overlay colors + justification, hence it reacts
to the theme. The `/vqa-theme/` nav is an in-`post-content` specimen WITHOUT those attrs,
so it looks default and — critically — its **interaction is NOT faithful**: the
hover-bridge between trigger and dropdown breaks in content (must click the
`__submenu-icon` toggle to traverse), while the header nav hovers fine. **Conclusion: the
content specimen is for DOM / structure / CSS-surface observation only; the canonical
INTERACTION + overlay-color context is the header/template-part nav.** (This is why we
never trusted the content nav for the hover path.)

**Overlay-visibility DOM/class (CSS-safety — verified):**
```txt
overlayMenu  nav class        hamburger @>=600   hamburger @<600   submenu when open
never        (no is-responsive) never            never             inline dropdown (A2 surface)
mobile       is-responsive      hidden           shown             <600 = overlay nested
always       is-responsive      SHOWN            shown             modal overlay at ALL widths
```
Visibility is baked into markup/classes (the open button gets `always-shown` for
`always`), NOT purely a media query — so `@media (min-width:600px)` is necessary but not
sufficient. **The leak the lock warned about, confirmed:** `always` opens the modal at
>=600px, where core resets the submenu `position`→static + `background`→transparent but
NOT `border-radius` / `border` / `box-shadow` / `min-width` — so A2 leaked a floating-card
look into the overlay's nested section. **Fixed (blocks.css §19 OVERLAY GUARD):**
`.wp-block-navigation__responsive-container.is-menu-open .…__submenu-container { reset
radius/border/shadow/min-width/bg }`, inside the same `@media (min-width:600px)`. Verified:
always-overlay-open @1000px submenu now radius 0 / static / transparent / no-shadow; the
inline desktop dropdown (mobile/never) keeps the surface (bg surface-container-high, radius
8, min 11rem). never-nav has no `.is-menu-open` so the guard never touches it.

**Nested flyouts:** each nested `__submenu-container` also gets the A2 surface (correct —
they're dropdown flyouts). Core positions child submenus `position:absolute` with NO
collision detection, so 3–4-deep flyouts can overflow the viewport edge — a real popover
positioning engine is out of CSS-only scope (defer; ref `lab-popover-pattern`).

**Divider probe (isolated render, menu never at risk):** `core/separator` inside a nav
submenu round-trips stable and renders an `<hr>` — but as a non-`<li>` child of the menu
`<ul>` (structurally questionable), and the editor inserter likely won't expose it
(seed-only). `core/spacer` likewise valid. For a menu divider, full-width
(`is-style-wide`) is the natural choice, but a CSS-drawn row separator is the cleaner
route than a seeded `<hr>` — **deferred** until a divider is actually needed in the menu.

**Still deferred / next:** A3 overlay = side modal sheet (now doubly motivated — `always`
makes the full-screen `fixed inset:0` overlay a desktop surface too; needs scrim +
max-inline-size + collapsible sections, leans on core interactivity), A4 icon-button
toggle, nav current/active, and the nested-flyout positioning engine.

### §9.4 — A2 cleanup (M3-faithful, core-leaning — corrected over the first cut)

Review caught over-correction + wrong targets in the first A2 cut. Reworked (blocks.css
§19 rewrite), verified dark+light:

- **Surface = `surface-container-low`, NO border** (was `surface-container-high` +
  outline-variant hairline — too bright / over-skinned; M3 standard menu is
  surface-container-low). Dark page base = rgb(20,18,24), surface-container-low =
  rgb(29,27,32) — still a tone above base, so it lifts WITHOUT shadow (dark suppresses
  shadow); light keeps elevation-shadow-level2.
- **State layer on the ROW (`li`), not the anchor** (M3 menu item: the row owns the
  state layer). on-surface @ 8% hover / 12% focus-within. **Submenu rows only** —
  top-level stays conservative.
- **Top-level typography/padding LEFT TO CORE.** The first cut forced label-large
  (line-height 20) + padding on the top-level anchor, which shifted the front-end
  baseline ~1px vs header/editor (the reported bug). Now only de-underline + a
  conservative hover underline (no box, geometry stays core's; top-level computed pad
  0/0, line-height 24 = core).
- **Overlay = fully core via a single-direction gate** (no skin+reset). Gate =
  `nav.wp-block-navigation:not(:has(.…responsive-container.is-menu-open))`. **The leading
  `nav` type selector is load-bearing**: the INNER `<ul
  class="wp-block-navigation__container wp-block-navigation">` also carries the
  `wp-block-navigation` class but sits *inside* the responsive container, so a class-only
  gate is satisfied by that inner ul (no is-menu-open descendant) and the skin leaks back
  into the open overlay — only the outer `<nav>` both carries the class and *contains* the
  responsive container. Verified: always-overlay-open @1000px submenu = static /
  transparent / radius 0 / no-shadow (fully core); this also fixes the reported
  desktop≠mobile color difference for `always`.
- **Menu item description support.** `core/navigation-link` has a `description` attr and
  renders it, but core hides it (`.…item__description { display:none }`) unless the theme
  opts in. We opt in for the INLINE menu (gated; overlay stays core) → M3 label +
  supporting text (body-small / on-surface-variant). Two seed items carry a description
  specimen.

**Sitemap shrunk** (was over-stuffed): Home · VQA → [Prose · Text · Media · Design ·
Widgets · VQA Theme → [VQA Comments · VQA Archive — the FUTURE Phase-2/3 subpages, the
real reason to nest]] · Log in. Embeds / Embed Template / Attachment links pulled (they
belong in their own lanes / a footer site-map, not the Theme nav).

### §9.5 — A2 follow-up (M3 Menu spec values + sys.state tokens + fixes)

Second review pass — measured corrections against the M3 standard Menu spec, plus a
sitemap re-expansion (the shrink over-corrected). blocks.css §19 reworked as ONE gated
nested block (`nav.…:not(:has(.is-menu-open))`, the `nav` type still load-bearing);
verified dark+light:

- **Baseline (the 1px).** Cause found: the top-level submenu trigger `li.wp-block-
  navigation-submenu` carries a stray `margin-block-start: ~4px` (not in our CSS — core/
  TT5), dropping the submenu label below its non-submenu siblings. Zeroed it (gated) →
  Home / VQA / Log in now share one top (725.6 == 725.6).
- **State layer on the ROW (`li`), top-level INCLUDED.** First cleanup removed top-level
  hover entirely; restored as a li state layer (covers label + trailing icon), NO box
  padding so top-level geometry stays core. NO hover underline. Opacities now come from
  `--md-sys-state-{hover,focus}-state-layer-opacity` (0.08 / 0.10), not hardcoded.
- **Nested trailing chevron was BLACK.** `.…__submenu-icon` in the dropdown rendered
  rgb(0,0,0) (its button color didn't inherit the theme color); top-level was fine. Set
  to on-surface-variant (M3 trailing icon) + transparent bg (so it doesn't double the row
  state layer).
- **M3 Menu spec values.** Container = surface-container-low · NO border · corner-large
  (16dp) · elevation-shadow-level2 (light) / tonal lift (dark) · group padding/gap 2 ·
  NO overflow:hidden (it would clip the nested flyouts — overflow stays visible). Rows =
  middle corner-extra-small (4dp), first/last outer corners 12dp · label-large
  (14/20/500/0.1) · min-block-size 48 · padding 8/12 · trailing icon 20 · supporting
  text body-small (12/16/400/0.4) on-surface-variant.
- **Focus indicator tokens (NEW).** Audit: `--md-sys-state-*-state-layer-opacity` already
  existed (reused, NOT reinvented); the focus-ring MECHANICS did not, so added to
  `tokens.sys.core.css` §6: `--md-sys-state-focus-indicator-{thickness:3px, outer-offset:
  2px, inner-offset:-3px}` (color stays role-chosen = secondary). §19 uses outer-offset
  on the top-level pill, inner-offset on menu rows (so the ring isn't clipped). NOTE: the
  pre-existing hardcoded focus rings (`.wp-element-button:focus-visible` 3px/+2, block
  focus rings) can adopt these tokens in a later sweep — left untouched here (no naming
  sweep this turn).

**Sitemap re-expanded with hierarchy** (the shrink was a misread — the ask was structure,
not removal): Home · VQA → [Prose · Text · Media · Design · Widgets · VQA Theme →
[Comments · Archive (future)] · VQA Embeds · VQA Embed Template · Attachment page →
[Images → [webp · jpeg · png · wide] · Audio → [ogg] · Video → [webm · caption #10 · caption #11]]] · Log in. Real `?page_id=` /
`?attachment_id=` permalinks; two description specimens.

### §9.6 — Dropdown Menu measurements vs Nav-rail-like navigation (locked)

Apply **Menu/Popover measurements only to horizontal inline dropdown submenus**. The
navigation block has other modes that are not menus:

- `overlayMenu: always` / mobile overlay: a responsive overlay with in-flow nested
  submenu sections.
- `orientation: vertical` + `overlayMenu: never` + always-visible submenus: visually
  closer to an expanded navigation rail / drawer than a popover menu.

**Class signals (verified, PHP render + DOM):** `submenuVisibility` enum = `hover` /
`click` / `always` (default hover); `always` puts `.open-always` on the submenu li (a
static, always-open nested list). `orientation: vertical` puts `.is-vertical` on the
`<nav>`. Overlay open = `.…responsive-container.is-menu-open`. hover/click both produce a
FLOATING absolute dropdown (= Menu); only always/vertical/overlay are nested sections.

Therefore the Menu contract is gated to the floating dropdown with THREE exclusions:

```css
nav.wp-block-navigation:not(.is-vertical):not(:has(.open-always)):not(:has(.…responsive-container.is-menu-open)) { … }
```

The leading `nav` type is load-bearing (the inner container ul also carries the class).
Do **not** style `.wp-block-navigation__submenu-container` globally as a menu. Verified
(2c specimens — vertical + horizontal-submenu-always): excluded submenus stay CORE
(vertical bg = core default, not surface-container-low; radius 0, not 16) — the Menu skin
does not leak. The Nav rail / drawer lane (vertical / always) is SEPARATE and must not
inherit the dropdown measurements.

Current measured dropdown values (front, dark, `/vqa-theme/`):

```txt
container: bg surface-container-low · border 0 · radius 16 · padding 2 · gap 2 · overflow visible
row:       middle radius 4 · first/last outer radius 12 · min-block-size 48 · padding-inline 12 · padding-block 8
label:     label-large 14/20/500/0.1
support:   body-small 12/16/400/0.4 · on-surface-variant · margin-top 2
trailing:  chevron 20 · on-surface-variant
overlay:   static/transparent/radius 0, anchor auto/14px (core; no Menu skin)
```
