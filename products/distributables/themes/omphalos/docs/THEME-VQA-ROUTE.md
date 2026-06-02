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
