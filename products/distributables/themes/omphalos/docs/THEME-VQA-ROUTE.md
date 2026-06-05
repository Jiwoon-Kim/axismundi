# Omphalos — Core Theme blocks VQA route (token-binding phase closed)

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
> **Status**: CLOSED for the Theme-block **token-binding** lane. VQA harnesses,
> seeds, route docs, and class-based M3 token bindings are implemented for
> `/vqa-theme/`, `/vqa-theme-comments/`, and `/vqa-theme-archive/`. Remaining work is
> intentionally deferred into component/template/plugin lanes, not this baseline
> binding lane.
> **Date**: 2026-06-05 · WP 7.0 · M3 Expressive.

---

## §0 — Final State (2026-06-05)

The Theme block category baseline is complete at the **token-binding** layer:

```txt
Phase 1 /vqa-theme/          site identity · navigation · breadcrumbs · Query Loop/post
                             · post-meta · pagination · parked infra
Phase 2 /vqa-theme-comments/ comments family · comment-template bubble thread
                             · comments form text-field/checkbox lite
Phase 3 /vqa-theme-archive/  query-title archive/search · terms-query/term blocks
                             · archives/categories/page-list widgets
```

Implementation principle:

```txt
Core/TT5 owns context, layout primitives, and template composition.
Omphalos owns token binding, de-prose chrome fixes, and small opt-in surfaces.
```

**Done families.**

```txt
Site identity                 §18
Navigation + submenu contexts §19–§21
Breadcrumbs                   §18e2
Query Loop / post-context     §18b + §18c opt-in cards
Comments + form controls      §21 + §22 + comments pattern/template
Archive / terms / query-title §18d
Parked widgets                §18e
```

**Deferred lanes (not Theme-token baseline):**

```txt
Template design       archive.html / search.html / home.html / single.html
Component Phase 2     full buttons, chips, full text-field/checkbox, nav overlay sheet
Pattern/style opt-ins cards media, term chips/cards, expanded rail/sidebar contexts
Plugin/form lane      inline reply composer, richer comment form markup
AP/object lane        microblog NOTE renderer / custom post type templates
```

Everything below remains as the diagnostic history and implementation record.

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

## §6 — Write scope & validation plan (closed)

- **Closed scope**: route + seed + VQA pages + token binding. Theme VQA is split into
  three live pattern-reference pages wired through `seed.ps1`: `/vqa-theme/`,
  `/vqa-theme-comments/`, and `/vqa-theme-archive/`.
- **Validation used**: runtime registry reconciliation; seeded dynamic context
  (posts/terms/comments/navigation); front-end computed CSS checks in light/dark;
  editor/static-save traps documented for navigation and terms-query; PHP lint and
  `git diff --check` before commits.
- **Current boundary**: do not continue broadening this lane with template redesign or
  rich component behaviour. Those are deferred lanes listed in §0.

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
                 [DONE — blocks.css §18e2: global type/colour + post-content-scoped prose-indent
                 + de-prose link resets; current (aria-current) crumb = on-surface; core owns the
                 "/" separator. With this every registered Theme block carries a token binding.]
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
FLOATING absolute dropdown (= Menu), including vertical `submenuVisibility:click`.
Only always-expanded and overlay states are nested sections.

Therefore the Menu contract is gated to the floating dropdown with TWO exclusions:

```css
nav.wp-block-navigation:not(:has(.open-always)):not(:has(.…responsive-container.is-menu-open)) { … }
```

The leading `nav` type is load-bearing (the inner container ul also carries the class).
Do **not** style `.wp-block-navigation__submenu-container` globally as a menu. Verified
(2c specimens): vertical `submenuVisibility:click` gets the Menu popover surface
(surface-container-low, radius 16, 48dp rows), while `submenuVisibility:always` stays a
transparent always-expanded tree and does not receive the Menu skin. **NOTE:**
`submenuVisibility:always` is only coherent for a VERTICAL nav (an always-expanded
rail/tree); on a horizontal nav the submenu is an absolute dropdown, so "always-open"
has no meaningful state — the editor does not honor horizontal+always (front renders the
`.open-always` class but it is not an editor-faithful config). The Nav rail / drawer lane
(vertical / always) is SEPARATE and must not inherit the dropdown measurements.

**Row ownership = the `li` (M3 menu item).** The first cut put the row box (min-height +
padding) on the ANCHOR, which is wrong — the row, state layer, hit area, height and item
padding belong to the `li`; the anchor (label) and the toggle button are content/action
SLOTS inside it. Corrected:

```txt
ul  …__submenu-container   padding 2 / 4 (block/inline) · gap 2 · surface-container-low · radius 16 · overflow visible
li  …navigation-item       min-block-size 48 · padding-inline 12 · gap 8 · shape 4 (first/last outer 12)
                           → state layer + hit area + height live here
a   …__content             padding 0 · min-block-size auto · flex-grow 1 · label/supporting typography only
button …__submenu-icon     20px slot · margin 0 (core gives ~3.5px → zeroed) · Material Symbols arrow_right
effective leading/trailing = ul 4 + li 12 = 16 ; a↔button gap = 8 (verified exact)
```

**Prose-list leak (all nav contexts).** prose.css indents every post-content
`:is(ul,ol)` (`padding-inline-start: --space-xl = 32px`); that leaks onto the nav
container + submenu uls, pushing the depth-1 items (Home / submenu) 32px right of
`loginout` (which core renders OUTSIDE the container ul) — visible only on the front
(prose.css doesn't reach the editor canvas the same way). Reset in blocks.css §19
(un-gated, post-content-scoped): nav uls get `padding-inline-start:0` + `list-style:none`,
so depth-1 items share one line in EVERY orientation. The gated Menu submenu padding
(2/4) still wins by specificity for the horizontal dropdown.

`a` is `flex-grow:1` on EVERY submenu row, leaf items included — a leaf anchor grows to
fill the remaining row width, so its click/hit area becomes the FULL row (a deliberate
change from core's content-width hit area; a full-row target is the menu norm). State
layer is on the `li`; focus targets stay the real focusables (`a` / `button`).

**Navigation taxonomy (corrected — Tabs removed):**
```txt
horizontal WP navigation        ≈ M3 Navigation BAR (role: horizontal destination switch).
                                  WP nav is text-only by default; M3 nav bar is icon-led →
                                  not a 1:1 map, but closer to Nav bar than Tabs.
vertical / submenuVisibility:always / overlay nested
                                ≈ Navigation RAIL (expanded) / drawer-like nested nav.
                                  NOT a Menu/Popover — the dropdown measurements are forbidden.
collapsed rail                  = a SEPARATE later lane (the earlier "overlaps Tabs" framing
                                  was inaccurate; it is Nav rail collapsed, not Tabs).
```

### §9.6b — A2b horizontal nav root = "Nav bar-LITE" (header-nav bridge, NOT full adoption)

Closed so far is ONLY A2a (horizontal submenu dropdown = Menu/Popover). The horizontal
nav ROOT (A2b) and the vertical root (A2c, nav-rail) and overlay (A3) are still open.

A2b is NOT a full M3 Nav bar component port — the WP horizontal nav root is usually
HEADER-TEMPLATE CHROME, so forcing the standalone bottom-nav geometry (64dp container,
item gap 0, 40/16 pills, 12dp label) would over-occupy the header. Split of ownership:

```txt
theme owns          item SHAPE (corner-full pill) · state layer (hover/focus/pressed) ·
                    current-page active INDICATOR (secondary-container pill + secondary label)
header/template owns container height · gap · justify · item external spacing · row rhythm
deferred / review   inactive label color (on-surface-variant candidate) · 12dp label-medium
                    (lab reference; header nav keeps label-large 14) · container height /
                    gap / justify (left to header/template — NO 64dp container, NO gap)
```

Implemented (blocks.css §19, inside the horizontal gate): top-level item → the M3 nav-bar
horizontal ACTIVE-INDICATOR geometry — `min-block-size: 40` (indicator height 40dp) ·
`padding-inline: 16` (leading/trailing 16dp) · `corner-full` (pill) · flex/align-center;
current item (`:has(> a[aria-current="page"])`) → secondary-container bg + secondary label.
The 40/16 is the INDICATOR (item) spec, not the CONTAINER (the 64dp container / gap /
justify stay with the header/template). Verified (front, dark+light): item 40px tall, pad
16, radius corner-full; rest = transparent; current = secondary-container pill (#4A4458) +
secondary label; non-current hover = on-surface @ 0.08 pill; label stays 14. NOTE: the
current-item pill currently suppresses the hover state layer (both set `background-color`);
a ::before state-layer overlay (the lab `.nav-bar__item::before` pattern) is the refinement
for active+hover layering — deferred. (loginout renders OUTSIDE the container ul, so the
pill treatment doesn't reach "Log in" yet — a separate structural item.)

### §9.6c — A2c open-always vertical = light BASELINE SKIN (blocks.css §20) + RAIL deferred

**ROLLED BACK from a full expanded-rail to a light skin.** An earlier §20 implemented the
M3 EXPANDED RAIL on `nav.is-vertical:has(.open-always)` — forced rail width (220),
re-implemented `justifyContent` with margin-auto (incl. a loginout-must-match-the-container
symmetry), `flex-wrap:nowrap`, `align-items:stretch`, full-width 56dp pills, box-sizing,
nested-padding `!important`, a content-width-block-vs-rail-width split. That **OWNED layout
that core/navigation already does** (orientation · flexWrap · justifyContent/alignment ·
container width · static nested layout · depth indent) → it LOWERED core dependency and
FOUGHT the editor toolbar (align/justify doing two conflicting things on two layers). It
also wrongly equated `open-always` with a 220px rail: an always-open vertical nav is a TREE
/ expanded-section candidate, not necessarily an M3 rail, and deep nesting (depth 3-4 →
label clamped to ~105px) is a tree/flyout problem, not an in-place-rail one.

**Now core OWNS** orientation · flexWrap · justifyContent/alignment · container width ·
static nested layout · depth indent. The **theme keeps a light skin only**, on
`nav.is-vertical:has(.open-always)`:
```txt
item ROW capsule        on the LI (per review — per-anchor read messy). `li.…-item`:
                        `min-block-size:56` · logical `padding-inline:16` · `position:
                        relative`. The hover/current capsule is a `::before` (corner-full),
                        full-width by inset, painted by STATE: `:hover::before` →
                        on-surface 0.08; `:has(> a[aria-current="page"])::before` →
                        secondary-container. The `::before` is CLAMPED to `block-size:56`
                        (the trigger ROW) so a vertical PARENT li — which wraps its whole
                        nested subtree — is NOT tinted/rounded as one giant box, only its
                        top row; a LEAF li override gives `::before{block-size:100%}`.
                        focus ring stays on the focusable anchor (a11y).
current-page indicator  `li:has(> a[aria-current="page"])::before` → filled secondary-
                        container capsule; `> a[aria-current]` → secondary label.
supporting text         core hides description (display:none) → opt in (display:block,
                        body-small/on-surface-variant); the content stacks label + supporting
                        text via flex-column `:has(.description)`.
submenu (parent) row     the parent li wraps its subtree, but it still owns the trigger
                        row geometry: `li.wp-block-navigation-submenu` gets
                        `min-block-size:56` + logical padding-inline 16. The first
                        `.…__content` (anchor/button) and trailing submenu icon are slots
                        inside that row: content grows, has 56px block-size, and keeps
                        padding-inline 0; open-always nested content overrides core's
                        `flex-grow:0`, and open-always parent triggers use `inline-size:100%`
                        so leaf + submenu-heading rows both fill their row. The following
                        submenu `ul` wraps to the next row.
gap / margin            core's vertical submenu gap is `gap: var(--wp--style--block-gap)`
                        (1.2rem ≈ 19.2px) — too large on the BLOCK axis. Do NOT zero the
                        variable: core also uses it for the INLINE nested indent. Override
                        only `row-gap:0` on the nav/submenu containers (with enough priority
                        to beat the generated layout rule), leaving `column-gap` / inline
                        padding / depth indent core-owned. Drop the stray 4px
                        `margin-block-start` on the submenu li.
justification edge      for vertical submenu children, remove padding on the opposite edge
                        only: left-justified → `padding-inline-end:0`; right-justified →
                        `padding-inline-start:0`. This keeps the needed hierarchy
                        indent while letting the active edge align cleanly. Description
                        anchors follow the same justification (`text-align:start/end`,
                        `align-items:flex-start/end`), so label + supporting text align
                        together.
core still owns          width · flexWrap · justifyContent · alignment · flow · inline
                        depth indent (no nav-level width / justify re-implementation /
                        block-gap variable override).
```
prose ul-indent leak removal + link de-underline already live in §19 (un-gated). Applies to
BOTH 2c vertical variants (click = core submenu behavior; always = open tree). Verified
(front, dark): leaf content 56 / padding-inline 16 / corner-full / full-width; the VQA
submenu toggle is a full-width 56 capsule trigger (no subtree tint / no giant rounded box);
current = filled capsule. = core tree nav + full-row capsule affordance.

**The real M3 EXPANDED RAIL is DEFERRED to an explicit opt-in** — a block style variation
`.is-style-expanded-rail` or a template/sidebar context class (e.g. `.ax-navigation-rail`)
— where a defined width (220, ceiling 360) + single-column + full-width pill rows +
loginout-merge + indent clamp / deep-level right-popover become justified. `:has(.open-
always)` alone is not "a rail"; the rail needs a real header/sidebar template context.

**M3 Nav rail spec (reference for the opt-in)** — the EXPANDED rail item is the M3 *Horizontal*
item variant (icon+label ROW; WP is label-only); the COLLAPSED rail is the *Vertical*
item (icon-over-label, icon-first) → EXCLUDED for text-only WP nav.
```txt
container (standalone)  width clamp(220, 360) · top-space 44 · elevation 0 · color surface ·
                        shape 0   (NOT 16 — 16 is the MODAL variant only)
container (modal sheet) elevation 3 · color surface-container-high · shape 16   (→ A3 territory)
item                    height 56 (short) / 64 · container shape 0 · vertical gap 6
  label                 label-large 14/20/500/0.1   (NB: nav BAR item is 12dp; rail item is 14dp)
  active indicator      FULL-WIDTH · height 56 · circular (corner-full) · leading 16 / trailing 16
                        · icon-label 8 · icon 24
states                  active indicator = secondary-container · active label = secondary ·
                        inactive label = on-surface-variant · hover 0.08 / focus 0.10 / pressed 0.10
collapsed (excluded)    container 96 (narrow 80) · vertical item · label-medium 12/16/500/0.5 ·
                        indicator 56×32 around the icon · top-space 44 · item gap 4
```
So the rolled-back §20 rail was ~correct EXCEPT: container shape should be 0 (not 16; 16 = modal
only) + color surface + top-space 44 + item gap 6 + width clamp(220,360).

**RAIL OPT-IN ATTEMPT (§21) — BUILT then FULLY ROLLED BACK.** A first cut put the rail behind
`.is-style-expanded-rail` with the M3 values, but it re-introduced everything the theme should
NOT own: fixed/clamped width, `align-items:stretch`, `inline-size:100%` full-width rows, AND a
re-interpretation of `justifyContent` as the rail BLOCK position via `margin-inline-*
!important` (to beat the post-content centering). That violates the project's logical-property +
RTL/LTR-first + **core-owns-layout** principles: `items-justified-right` is not necessarily the
visual right (it flips under RTL, and WP's mapping of justify on a vertical nav is core's to
own), and `!important` margin overrides + a fixed component width are exactly the core-fight we
keep removing. So §21 was **deleted in full** (CSS + the pattern specimen).

**A2c is CLOSED at the §20 capsule skin** — core owns ALL layout (width / flexWrap /
justifyContent / alignment / indent / flow), the theme adds only: de-underline (§19) ·
capsule SHAPE (corner-full) + logical `padding-inline` · hover/focus/current STATE · supporting
text. No width / margin / justify re-implementation / `!important` / fixed rail. RTL/LTR flow
stays core-native. The real EXPANDED RAIL is deferred to a SEPARATE component/template lane and
must be re-specced with a full RTL audit (the M3 Nav rail values above are the reference) before
any rebuild — `:has(.open-always)` + a content nav is not a rail.

### §9.6d — vertical + submenuVisibility:click accordion = PLUGIN / render lane (rolled back)

A vertical click-to-open submenu would read better as an in-flow ACCORDION / disclosure than
a floating popover. Verified DOM: `li.open-on-click > button.…__toggle[aria-expanded]` + a
SIBLING `ul.…__submenu-container` (core's collapsed state = `position:absolute;
visibility:hidden`). A CSS-only prototype re-flowed it with `flex-wrap`, `position:static`,
`max-block-size`, and sibling selectors, but it kept fighting core/navigation's saved markup
and interaction model (submenu placement, nested flow, height semantics, and no real
`<details>` semantics). **That prototype was removed from theme CSS.**

Decision: **do not implement accordion behavior in the theme baseline.** Keep the current
core `submenuVisibility:"click"` behavior as core-owned. If accordion/details behavior is
wanted, build it as a plugin / block extension / `render_block` lane that can own markup and
semantics (`details/summary` or a proper disclosure controller), then test editor + front +
RTL together. Theme CSS may keep the neutral vertical item capsule skin (§20), but it must
not re-flow click submenus.

Current measured dropdown values (front, dark, `/vqa-theme/`):

```txt
container: bg surface-container-low · border 0 · radius 16 · padding 2/4 · gap 2 · overflow visible
row(li):   middle radius 4 · first/last outer radius 12 · min-block-size 48 · padding-inline 12 · gap 8
anchor(a): padding 0 · min-block-size auto · flex-grow 1 · label-large 14/20/500/0.1
support:   body-small 12/16/400/0.4 · on-surface-variant · margin-top 2
trailing:  Material Symbols arrow_right · 20 · on-surface-variant · margin 0 ; gap 8 / trailing inset 16 (exact)
overlay/open-always: core / tree (no Menu skin) — verified excluded
vertical click: Menu popover applies (same surface/48dp rows as horizontal)
```

### §9.6e — Submenu trailing affordance ontology (where the icon spec comes from)

A `core/navigation` submenu chevron is a TRAILING AFFORDANCE, and its measurement must be
sourced from the right component ontology — NOT from Navigation itself. **Nav bar / Nav rail
carry no trailing-icon spec**: their icon slots are destination PRIMARIES (the leading symbol
of a destination), so they are not a basis for a submenu indicator. The two valid sources are
**Menu** (cascading-submenu indicator: trailing icon 20dp, on-surface-variant) and **List**
(row-level disclosure / expandable parent: trailing icon 24dp, on-surface-variant; a future
stronger target is the List expanded-parent 40dp corner-full container). **Chip**'s icon
(18dp) is component-private and must NOT be pulled into navigation. Mapping applied:

```txt
nested popover submenu  = Menu cascading indicator   → arrow_right        / 20 / on-surface-variant   (blocks.css §19b)
top-level submenu        = List row disclosure         → arrow_drop_down/up / 24 / on-surface-variant   (blocks.css §19c)
open-always tree         = Nav-rail baseline           → no trailing icon (showSubmenuIcon:false)
chip                     = chip-private (18)           → NOT used in navigation
```

The top-level disclosure replaces core's caret `<svg>` for both core markups — a `<span>`
(`submenuVisibility:"click"`) and a `<button>` toggle (hover) — with the expanded state
swapping to `arrow_drop_up` (click: `button[aria-expanded] ~ icon`; hover: `icon[aria-expanded]`).
Source measures: Menu / List / Chip rows in `axismundi-lab/stylesheets/components.css`
(Menu ~3001, List ~4300, Chip ~1733).

## §10 — Query Loop + post-context (B contract)

Diagnostic (CSS-0, `/vqa-theme/` §3): `.wp-block-query.is-layout-flow` → `ul.wp-block-post-template`
(flow LIST, `list-style:none`, no marker) → `li.wp-block-post` (display:list-item). Per-item
blocks in order: featured-image · title(h2) · author+author-name+avatar · date · terms×2 ·
excerpt · time-to-read · read-more · comments-count · comments-link · author-biography. Then
`query-pagination` (flex/wrap, numbers+next) + `query-total`. **Confirmed prose leak**: the
post-template `ul` inherits prose `:is(ul,ol)` `padding-inline-start:32` → whole loop shifted in.

**Decision — flat by default, card opt-in deferred.** core/query is a layout PRIMITIVE reused
across home / archive / search / category / related; a card baseline over-applies. So the
post item is a **FLAT ARTICLE** by default. The card form is a DEFERRED opt-in block style
(`.is-style-*` list/grid) that may reuse the §16 Latest Posts/RSS card ontology. This keeps
the Navigation lesson: don't over-componentise a core layout primitive.

**Phase 1 (DONE — blocks.css §18b), flat baseline only:**

```txt
post-template     prose indent reset (padding-inline-start:0, list-style:none)
post-title        title-large / on-surface           (overrides prose h2 28/36)
featured-image    global media corner binding        (img radius 12; core owns aspect/object-fit)
post-excerpt      body-large / on-surface
meta cluster      body-small / on-surface-variant     (date·terms·time-to-read·comments·total·byline)
author-biography  body-medium / on-surface-variant
read-more         label-large / primary  (TEXT-button-LITE)
pagination        label-large + pagination-lite
                  (32px page-number pills; current=secondary-container,
                   links=on-surface-variant; full button module deferred)
layout            CORE-owned (flow/gap/columns untouched)
link colour/underline = §18 de-prose block (title on-surface, meta on-surface-variant, read-more primary)
```

**Card opt-in (DONE — surface rail open; blocks.css §18c + functions.php):**
`.is-style-cards` is a single `core/query` block style — **surface ONLY**. Name decided as
`cards` (NOT `card-list` / `card-grid`): the name must mean *surface treatment*, so layout
stays core. Reuses the §16 card ontology (filled, shadow-less, surface-container / corner /
`--space-md` padding + `--space-xs` interior rhythm). Never touches `display` / `width` /
`grid` → works unchanged in core's List **and** Grid view; inter-card spacing = core flow/grid
gap. Avoids the §16 grid-override breakage and the Navigation layout-ownership trap.

```txt
Query Loop card opt-in = .is-style-cards   (core/query)
Meaning: card SURFACE only. Does NOT imply list/grid.
Post Template layout (list/grid/columns/responsive) remains CORE-owned.
```

> NOTE — page 68 (`/vqa-theme/`) content is a LIVE pattern reference
> (`<!-- wp:pattern {"slug":"omphalos/vqa-theme"} /-->`, seed-vqa-theme.php), so editing
> `patterns/vqa-theme.php` markup reflects on the page (front + editor) immediately — no
> reseed. (The nav specimen is the exception: its menu comes from the seeded `wp_navigation`
> `ref`, so nav structure changes need the seed, not the pattern.)

> DEPRECATION — `core/post-author` is deprecated by WP (use Avatar + Author Name + Author
> Biography). The pattern's combined `post-author` block was removed; the trio (`avatar` +
> `post-author-name` + `post-author-biography`) was already present and stays. blocks.css
> §18b meta cluster dropped the dead `.wp-block-post-author` selector.

**Meta-row composition (DONE — pattern-owned):**
`patterns/vqa-theme.php` groups Avatar + Author Name + Date + Categories + Tags +
Time-to-read into `.omph-query-meta-row` inside the post-template. This is deliberately
MARKUP-owned: CSS does not synthesize a row from loose sibling dynamic blocks. blocks.css
§18b owns only row alignment/gap/margin cleanup (`flex-wrap`, center alignment, `4px/8px`
gap). Avatar remains the existing 40px specimen for now; if it reads too heavy, a later
small pass can test 32/24 without changing the row ontology.

**Deferred buckets (NOT done):**
- **Card media + specimen** — featured-image full-bleed/corner treatment inside the card, and
  a `.is-style-cards` specimen in the VQA pattern (renders live via the pattern reference).
- **Featured image treatment** — corner-large / aspect / cover → media route (with card).
- **read-more / pagination as full M3 buttons** — button module CSS, Phase 2 (forbidden in
  Phase 1 without authorization); Phase 1 stops at read-more text-button-lite and compact
  pagination-lite.
- **terms as chips** (Filter/Assist chip ontology) vs plain meta text — open decision.
- **spacing rhythm** — title keeps prose ~23px block margin; left to core block-gap for now.

### §10.1 — B contract Phase 1 CLOSE (binding-only; lean on TT5 patterns)

Final small gaps closed: `query-pagination` current page = secondary-container pill + 32dp
corner-full number affordance (hover state layer); `query-no-results` empty state =
body-medium / on-surface-variant (observable via the §3b forced-empty `offset` specimen).
Pagination-lite was later promoted from `.wp-block-post-content` scope to **global**
`.wp-block-query-pagination` because Blog Home / archive template pagination renders outside
post-content. This is still a token/affordance binding only (32px pills, current state,
label-large); the full button module remains deferred.

**Strategic close (Omphalos = TT5 child).** The DURABLE Query-Loop work is the per-block
**component binding** in §18b — each core post block (title/excerpt/date/terms/read-more/
pagination/no-results) mapped to an M3 typescale + role colour **by CLASS**. So any in-content
Query Loop — whether hand-authored or inserted from a **TT5/core starter pattern** — picks up
the M3 binding for free; we do NOT hand-build layout per pattern. `.omph-query-meta-row`
(pattern group) and `.is-style-cards` (surface opt-in) are observation specimens / opt-ins,
NOT required bindings — they stay small and are not the product baseline.

**Scope boundary (load-bearing).** The whole block bridge (§16/§18) is `.wp-block-post-content`-
scoped, so it binds IN-CONTENT loops only. **Template-context loops** (`archive.html` /
`home.html` / `index.html` — the real blog/archive product surface) sit OUTSIDE post-content
and are NOT reached here; they are theme.json global styles + TT5 templates = a **separate
template lane** (future), not the block bridge. This is why "just bind components and move on"
is correct for Phase 1: the in-content binding is done, and template styling is a different
contract. Query Loop B Phase 1 is CLOSED.

---

## §11 — Comments (P2 contract — global token binding, DONE)

Diagnostic (real single post `?p=65`, the seeded post with 2 comments): the comments family
renders in the SINGLE-POST TEMPLATE (TT5 `single.html` → `main.wp-block-group`),
**`insidePostContent: false`** — the SAME template-context boundary as the Query Loop. So the
BINDING is verified against the real template render, not invented in a page.

**Decision — bind GLOBALLY (nav model), not post-content-scoped.** Comments are unambiguous
CHROME (never prose), so the post-content prose-leak guard doesn't apply; like §19 navigation
they're bound by their own block classes globally, which reaches the real TT5 template safely.
Token binding only; thread / list LAYOUT stays core/TT5-owned.

**Observation page (per §3 convention).** Phase 2 gets its own VQA page like every other phase:
`patterns/vqa-theme-comments.php` + `scripts/seed-vqa-theme-comments.php` (page 86,
`/vqa-theme-comments/`; live `wp:pattern` ref; wired into `seed.ps1`). Final shape:

- **Page hierarchy** — `post_parent` = the VQA Theme page (68); Comments is its child.
- **Nav** — the seeded nav "VQA Comments" link → `?pagename=vqa-theme-comments` (resolves
  regardless of seed order), replacing the `#vqa-comments-future` placeholder.
- **Depth-5 thread** — comments OPEN; a single 5-deep reply chain (depth 1→5, the
  `thread_comments_depth` limit, set defensively) + one more top-level = 6 comments, so
  comment-template nesting is observable at every level.
- **Real TEMPLATE render** — page 86 uses a custom template `page-with-comments`
  (`templates/page-with-comments.html`, registered in `theme.json` `customTemplates`) = TT5's
  `page.html` + the `twentytwentyfive/comments` pattern AFTER `post-content`. So `core/comments`
  renders in TEMPLATE context (`insidePostContent:false`), identical to the real single-post
  case (`?p=65`) — NOT in-content. The in-content `core/comments` was therefore REMOVED from the
  pattern (no double render; the pattern keeps the intro + comments count/link specimens).

Because the comments render in the template (outside `.wp-block-post-content`), prose does not
leak and the §21 global binding applies cleanly. The §21 specificity lifts (`.wp-block-comments`
prefix on the title + comment links, (0,2,0)/(0,2,1)) are KEPT as a harmless guard for any
future in-content placement, but are not needed in this template-context render.

**Done (blocks.css §21):**

```txt
comments-title                         title-large / on-surface
comment meta (author/date/reply/edit)  body-small / on-surface-variant
comment-content (+ inner p)            body-medium / on-surface
comment avatar                         M3 List leading avatar primitive: 40dp / circular
comment links (author/reply/edit)      de-prose: no resting underline, hover/focus underline
comment-template                       list-style:none (thread indent stays core)
comment form label/helper              body-small / on-surface-variant
```

CSS-0 was TT5 light defaults (weight 300, mixed greys); binding normalises to M3 typescale
(weight 400) + role colours. Verified: title 22/28 on-surface, meta 12/16 on-surface-variant,
content 14/20 on-surface, reply link de-underlined, form label 12 on-surface-variant.

**Avatar ontology note.** M3 does not currently expose Avatar as a normal standalone public
component page in the same way as Button/List/Menu. The stable contract used here is **List >
leading media > avatar**: 40dp, circular, avatar colour `primary-container`, label colour
`on-primary-container`, label type 16/24/500. The local `ax-avatar` styleguide section is a
record/composition primitive extracted from that leading-slot contract, not proof of a separate
Material component. For WordPress comments we therefore bind only size/shape + fallback colour;
real Gravatar images keep their pixels and are clipped circularly.

**Comment form inputs = M3 Text Field, OUTLINED (DONE — text-field-lite).** Ontology: comment
inputs are M3 §32 Text Field — a first-class component (lab `.text-field`,
style-guide#components-text-field), grounded (unlike the List-derived avatar). But core renders
PLAIN `<input>`/`<textarea>` with no `.text-field` structure, so the FULL component (floating
label / `__container` / prefix-suffix slots / supporting + counter / error icon) needs
`comment_form` MARKUP ownership and stays a form/plugin lane (deferred). The theme binds the
OUTLINED SURFACE tokens only on the raw controls (blocks.css §21): 56dp · 16dp padding ·
corner-extra-small · transparent fill · body-large. The OUTLINED active indicator follows the
lab spec as an INSET BOX-SHADOW (not `border`) — 1px `--md-sys-color-outline` (rest) → 1px
on-surface (hover) → 2px primary (focus) → 2px error (`:user-invalid`). box-shadow (which core
does not set) is load-bearing: an earlier `border` version was overridden by core's form
border-color (rendered a faint near-white #E7E0EC outline — the reported "thin line"); the
shadow lets the true #79747E outline through. Rest stays 1px (M3 spec; the lab confirms — not
thicker). Also FIXES core's hardcoded white field bg (broke dark mode). Submit stays the M3
button element.

**Cookies-consent CHECKBOX = M3 §13 Checkbox, OUTLINED-lite (DONE).** core renders a bare native
`<input type="checkbox">` (no `.ax-checkbox__visual` markup), so bind the visual via
`appearance:none` on the input (blocks.css §21): 18dp box · 2dp corner · 2px on-surface-variant
outline (inset shadow, so core can't override the colour) · checked = primary fill + on-primary
CSS-border checkmark (token-coloured → dark-safe) · focus 2px secondary. The full `.ax-checkbox`
(40dp state-layer ripple + `__visual`/`__check` structure) needs markup = form/plugin lane.

**Deferred:**
- **full Text Field component** — floating label / container / prefix-suffix / supporting +
  counter / interactive trailing icons → needs `comment_form` markup ownership (form/plugin lane).
- **comments-pagination / comment-edit-link** — absent in this specimen (2 comments, no
  pagination; edit-link needs the cap); bind by class when a richer comment context exists.
- **thread/nesting layout** — core/TT5-owned (layout primitive).

Note: like the Query Loop, the real comments DESIGN beyond token binding (spacing, nesting
affordance, form field components) belongs to the **template lane** — Omphalos binds tokens,
TT5/core own the template + layout.

### §11.1 — comment-TEMPLATE ontology: a FACEBOOK comment BUBBLE thread (source-grounded)

`core/comment-template` (the displayed comment row — avatar / author / date / content / reply,
nested) is NOT a "traditional blog comment" form-field problem, and — after weighing two real
models — it is NOT a microblog NOTE either. It is a **comment BUBBLE thread** (Facebook model).
The comment FORM (text-field / checkbox) and the comment ITEM are different ontologies — the item
is a feed/thread object, not a form. Sources read directly (not browser-scraped — SPA wrappers +
virtualization are too noisy):

- **Microblog NOTE** — Mastodon `status.jsx` + `status/header.tsx` + `status_action_bar/` and
  Misskey `MkNote.vue`: leading avatar + main column { header/meta (author · handle · date) →
  body → media → reactions → action bar (reply / boost / favourite / renote / …) } + thread
  connector. This is RICH — handles, reactions/boosts, relative timestamps, media, polls. That
  richness belongs to a **custom post type / ActivityPub OBJECT renderer**, NOT
  `core/comment-template` (WP comments have no handle / reaction / boost). The note model
  OVER-FITS core comments.
- **Facebook comment BUBBLE thread** — the right fit for WP core comments: leading avatar + a
  rounded BUBBLE { author (top) + comment text } + a small action STRIP (date · reply · edit)
  BELOW the bubble + a thread connector for nesting. Minimal — matches exactly what WP comments
  carry.

```txt
ActivityPub / Mastodon / Misskey NOTE  → custom post type / object template / AP renderer (lane)
WordPress core comments                → Facebook-style comment BUBBLE thread
```

**WP mapping (DONE — Facebook bubble).** `patterns/comments.php` (`omphalos/comments`) re-authors
`core/comments`: per-comment = **leading avatar** (`flex-shrink:0`) + **main column** {
`omph-comment-bubble` (rounded `surface-container` · `corner-large` · `fit-content` so it HUGS the
text) holding `comment-author-name` (bold, label-large, top) + `comment-content`; then an
`omph-comment-actions` STRIP below the bubble = `comment-date` (body-small, muted) ·
`comment-reply-link` / `comment-edit-link` (label-medium action, muted, underline on hover) }.
The note-style inline meta row is dropped — the date moved into the strip.
`templates/page-with-comments.html` references it instead
of `twentytwentyfive/comments`. Nested replies keep the core thread chain; connector lines are
drawn by `assets/scripts/comment-thread-connectors.js` from measured avatar positions (parent avatar
bottom → reply avatar inline-start) instead of fixed CSS offsets. The PATTERN owns structure;
blocks.css §22 = bubble surface + strip + SVG line styling (no CSS stack reorder — the Navigation
trap). The §21 token binding (avatar 40 List-leading, role colours, de-prose) is unchanged.
Verified light/dark on page 86: author/content in the bubble (surface-container · r16 ·
fit-content), date/reply in the strip below (mt 4; date = body-small 400/0.4; reply/edit =
label-medium 500/0.5), bold name, dynamic connector paths, depth 1→5 plus depth 2/3 sibling
branches — reads as a Facebook comment thread. Connector affordance: hovering/focusing a comment
does not highlight the tree; clicking Reply toggles a persistent reply-target state instead. While
active, the Reply action turns primary and the full ancestor connector chain to that target is
raised and highlighted (primary stroke), without moving the root composer. Clicking the same Reply
again cancels the state.

History: an earlier pass built the microblog NOTE layout (flat meta row + body + action row +
per-level rail) and was reshaped into the bubble model once Facebook was used as the reference.
The NOTE layout stays the reference for the future custom-post-type / ActivityPub object lane.

Still deferred: action-row ICONS / a Like affordance (needs markup); an inline reply composer; and
the AP-object / note renderer (its own lane, not core comments).

**Composer decision (baseline).** Keep the core single `post-comments-form` at the bottom of the
comments block and keep it there: Omphalos dequeues core `comment-reply.js` and prevents reply-link
clicks from moving `#respond` under a comment. Do NOT add a second `Comments Form` block for inline
replies: core comment forms emit singleton IDs/fields (`respond`, `commentform`, `comment_parent`,
cancel-reply affordance), so duplicating the block would create ID/state collisions.
Facebook-style behaviour (bottom composer remains for root comments while reply clicks spawn
per-comment inline composers) is a custom form/plugin lane: render a separate inline reply form
with the right `comment_parent`, and handle guest fields/nonces safely. Until that lane exists,
Omphalos keeps the root composer fixed at the bottom; reply links are visual/actions-only in the
baseline.

## §12 — Phase 3 Archive / Term blocks (DONE — token binding + VQA harness)

Phase 3 follows the same boundary learned in Query Loop and Comments:

```txt
archive/search query-title chrome  → TEMPLATE context → global token binding
terms-query / term-template blocks  → in-content harness → core layout + token binding
```

**Harness.** `patterns/vqa-theme-archive.php` + `scripts/seed-vqa-theme-archive.php` create
`/vqa-theme-archive/` (page 93, child of `/vqa-theme/`). The page is a live pattern reference and
contains:

- links to real template contexts: category archive, tag archive, search results
- category `core/terms-query` specimen
- tag `core/terms-query` specimen

The seed also gives the VQA category/tag real descriptions so `core/term-description` renders.
The `termQuery` serialization was verified against runtime rendering; the valid attrs are
`taxonomy`, `perPage`, `orderBy`, `order`, `hideEmpty` (using `number`/`orderby` produced core
warnings).

**Binding (blocks.css §18d).**

```txt
core/query-title       title-large / on-surface (GLOBAL; archive/search template context)
term-template          list-style none + prose indent reset (in-content only)
term-name              title-medium / on-surface, link de-prosed
term-description       body-medium / on-surface-variant
term-count             body-small / on-surface-variant
```

Layout remains core-owned. No term card/chip/list component is introduced in Phase 3; this is the
same "component binding first, surface opt-in later" policy used for Query Loop.

**Verification.**

- `/vqa-theme-archive/`: term list renders, `wp-block-term-template` padding-inline-start = 0,
  term-name = 16/24/500 on-surface, description = 14/20 variant, count = 12/16 variant.
- `?cat=3`: `core/query-title` renders in template context (`insidePostContent:false`) and is
  bound to 22/28 title-large.
- seeded navigation now links "VQA Archive" to `?pagename=vqa-theme-archive`.

**Parked Widgets (DONE — blocks.css §18e).** `core/archives` · `core/categories` · `core/page-list`
— the list widgets parked in the Widgets lane, rejoined into the Theme/Archive family. Each is an
in-content list of links with the SAME prose leak as the term/nav blocks (32px `:is(ul,ol)` indent
+ disc markers + always-underline PRIMARY link). Bound post-content-scoped: strip the indent +
markers, de-prose the links (on-surface, hover/focus underline only), body-medium typography; the
post-count text stays muted (on-surface-variant); nested children (categories hierarchy, page-list)
keep a `--space-md` depth indent so depth still reads. Added as §4 of
`patterns/vqa-theme-archive.php`. Verified light/dark on page 93: indent 0, markers none, links
14/20 on-surface de-underlined, counts on-surface-variant, page-list hierarchy preserved. With this,
the Phase-3 Archive/Term family — query-title (Archive + Search variations), terms-query + term
blocks, and the parked widgets — is CLOSED.

**Deferred.**

- Archive/search template design (`archive.html`, `search.html`, `home.html`) — template lane.
- Term list/card/chip surface variants — opt-in style/pattern lane after real use cases.

## §13 — Global Styles promotion (IN PROGRESS)

After the Theme VQA token-binding pass, selected stable defaults are promoted from scoped CSS into
`theme.json` so the Site Editor / Style Book exposes them as native Global Styles controls.

**Text (DONE).** Global body text lives in `styles.typography` and binds to the M3 body-large
tokens. This is the source for the Site Editor Typography → Text panel; prose.css still scopes
long-form surface rhythm and rich-text details to `.wp-block-post-content`.

**Headings (DONE).** `styles.elements.heading` clears the TT5 aggregate heading defaults
(especially uppercase/letter-spacing/line-height residue), while `styles.elements.h1`…`h6` bind
the actual level scale:

```txt
h1 = headline-large   32/40/400
h2 = headline-medium  28/36/400
h3 = headline-small   24/32/400
h4 = title-large      22/28/400
h5 = title-medium     16/24/500
h6 = title-small      14/20/500
```

The Style Book Typography → Headings and Blocks → Heading panels now read from `theme.json`.
`prose.css` keeps only the long-form margin/rhythm layer around those headings.

**Links + captions (DONE for typography).** Links deliberately get **no typography binding** in
`theme.json`: Material links inherit the surrounding text style, and a global `a` font-size or
line-height would incorrectly shrink/grow heading, navigation, and metadata links. Link colour
belongs to a separate Global Styles colour lane, not this typography pass. `styles.elements.caption`
binds WordPress captions (`.wp-element-caption` + media/table figcaptions) to M3 `body-small`.
The muted caption colour (`on-surface-variant`) is also owned by the colour lane below because WP
groups caption colour and typography under the same element control.

**Buttons (DONE).** `styles.elements.button` binds the FSE Buttons element to M3 label-large
typography, primary/on-primary colour, full-radius shape, and horizontal padding. Richer button
variants (elevated/outlined/tonal/text/icon) remain a Component Phase 2 lane.

**Colour Elements (DONE, first pass).** Site Editor → Styles → Colours exposes the stable global
element roles in `theme.json`:

```txt
text       = on-background
background = background
heading    = on-surface
link       = primary
caption    = on-surface-variant
button     = primary / on-primary
```

This is intentionally separate from the typography lane: `styles.elements.link` contains colour
only, not font-size/line-height/weight/tracking. Component/context links that are not normal prose
links (navigation, meta, comments, pagination) still keep their scoped de-prose role-colour
bindings and may override the global link colour by class.

**Palette UI token bridge (DONE).** `settings.color.palette` uses `var(--md-sys-color-*)` entries so
the palette tracks the same light/dark system tokens as the front end. WordPress renders those
palette swatches in the **editor parent document**, not inside the stylebook/canvas iframe. Therefore
`add_editor_style()` is not enough: without token CSS in the parent document, swatches exist in the
DOM but compute to transparent. `assets/scripts/editor-theme-scheme.js` now injects only the **ref +
sys token** stylesheets (`tokens.ref`, `tokens.sys.light`, `tokens.sys.core`, `tokens.sys.dark`)
into the parent editor document, leaving component/layout/prose/block CSS out so admin UI chrome is
not polluted. `tokens.comp.css` is deliberately excluded: component tokens are not needed for palette
swatch resolution. The script is versioned with `filemtime()` so palette fixes are not hidden behind
the fixed theme version. Verified on Styles → Colours → Palette: `Primary` computes to
`rgb(103, 80, 164)` in light mode instead of transparent.

**Theme Switcher (DONE).** `styles.blocks.omphalos/theme-switcher` exposes track background,
padding, and radius in Global Styles. The custom block declares an example + editorStyle so its
Style Book preview renders the segmented control instead of raw fallback text. Because Style Book
uses editor-owned `blob:` iframes, `assets/scripts/editor-theme-scheme.js` rewrites those preview
blobs with the current `data-theme`, Omphalos style cascade, Material Symbols font-face, and the
current `aria-pressed` active state.

**Current FSE Typography Elements coverage.** Text, Headings, Captions, and Buttons are mapped in
`theme.json`; Links intentionally inherit typography. Continue promotion block-by-block only where a
stable global control exists (e.g. image rounding, featured image radius, group card variations);
keep layout/template-specific decisions in the template or opt-in style lanes.
