# Changelog

> Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
> Versioning: Phase-based semver
>   - Major (`x.0.0`) — Phase 전환 (1 → 2 → 3)
>   - Minor (`0.x.0`) — 컴포넌트 마일스톤 추가
>   - Patch (`0.0.x`) — fix, doc, 작은 추가

---

## [Unreleased]

### Added — Phase 2B δ-2 — v1 prototype absorption (separate variation page) (2026-05-08)

> **v1 reference (`Axismundi_depreciated_.zip`) integration — pass 1, REVISED.** First attempt overwrote `front-page.html` with the v1-derived microblog feed; this approach loses the original Korean local-instance design that was already a deliberate visual identity. **Final approach: keep `front-page.html` as-is (Korean local feed), add `front-page-microblog.html` as a separate federated variation.** Both pages share the same template chrome (header / nav drawer / footer / sidebar nav) — they differ only in the main+aside content area, mirroring how WordPress `theme.json` `styleVariations` work.
>
> Rationale: the v1 prototype had genuine design value (federated bilingual feed, Trends rail, follow suggestions, theme switcher, inline composer) but was **a different mental model** (federated-first vs local-instance-first). Both should be available. Phase 3 PHP integration registers them as alternate front-page templates the user can pick via Site Editor.

#### theme.js — §8 Theme switcher (light / dark / auto)

- New IIFE absorbed from v1 prototype's React `[data-theme]` toggle, rebuilt as static-HTML-friendly delegated handler.
- Pattern: `<fieldset class="ax-theme-switcher" role="radiogroup">` containing `<button data-theme-set="light|dark|auto" role="radio">`.
- Persists to `localStorage("ax-theme")`; survives reloads + per-page nav. Phase 3 PHP integration can layer on WP user meta via `ax_theme_preference` filter.
- Cross-tab sync via `storage` event listener.
- Default: `auto` (follows `prefers-color-scheme`). Existing OS-only behavior preserved when no switcher exists / no storage value.
- Updated theme.js section header to include §7 + §8.
- **System-level addition — applies to all pages**, not tied to either variation.

#### base.css — §6.8 `.visually-hidden` utility

- New accessible-only label utility (clip-path + size 1px). Used by composer markup where placeholder is sufficient for sighted users but `<label>` text is needed for AT.
- Pattern: NOT `display: none` (removes from AT tree), NOT `visibility: hidden` (same), but clip-path + 1px positioning that preserves AT visibility while taking zero layout space.
- **System-level addition — utility available across the codebase.**

#### front-page.html — UNCHANGED

- Original Korean local-instance feed preserved verbatim: 3 posts (김민수 / 이서연 / 박지훈), 인기 주제 widget (3 trending tags), 팔로우 추천 widget. Sidebar nav pattern with 6 items (Home / Blog / Explore submenu / Notifications / Messages / Profile / Settings) intact.
- Added one cross-link at end of trends rail: `Variation: Federated microblog →` (links to new variation page).

#### front-page-microblog.html — NEW variation page

- Built from `front-page.html` as base (same header / nav drawer / sidebar nav / footer chrome). Only the `<main class="ax-feed">` + `<aside class="ax-trends-rail">` content swapped.
- **Tabs row** (Home / Following / Federated) — sticky below app-bar, uses existing `.tabs--primary` component.
- **Inline composer** — collapsed-affordance pattern. `<textarea>` + action row (image / emoji / visibility icons + counter + post button).
- **7 bilingual posts** (KR/EN mix) absorbed from v1 `FEED_DATA`:
  - p1 Hana Park (한·영, morning light) · p2 Mira Sato (EN, typography) · p3 Jiwoo Kim (한·영, design system) · p4 Forest Lab (EN, audio media placeholder) · p5 Eun Lee (한·영, image media placeholder) · p6 Alex Renn (EN, fediverse) · p7 도서관 봇 (KR, book recommendation)
- **Trends rail** with theme switcher card + Trending now (5 tags) + Who to follow (3 suggestions) + footer mini-info + cross-link back to original variation.
- v1-derived inline CSS scoped to this file: `.ax-feed__tabs`, `.ax-composer*`, `.ax-post__tags`, `.ax-post__media-placeholder`, `.ax-post__instance`, `.ax-trend`, `.ax-suggest`, `.ax-theme-switcher`. (Could be promoted to `components.css` if either variation pattern is reused beyond the front-page level.)

### Notes

- v1 prototype was a **React SPA** with 7 views (feed / profile / reading / explore / saved / settings / notifications). Per the C-track integration decision, views are remapped to WordPress template hierarchy:
  - feed → `front-page-microblog.html` (NEW separate variation, this pass) ✅
  - explore → `archive.html` enhancement (next pass — δ-3)
  - reading → either `home.html` enhancement OR new `single-axismundi_article.html` (next pass)
  - saved → new `single-axismundi_collection.html` CPT (next pass — δ-4)
  - profile → existing `single-axismundi_profile.html` data enrichment (next pass)
  - notifications → drawer/sheet UI pattern integration (next pass)
  - settings → Phase 3 admin options page (deferred)
- **Variation pattern** maps to Phase 3 `theme.json` `styleVariations`. Same PHP template, different content/styling registrations. The static HTML pair (`front-page.html` + `front-page-microblog.html`) is the design-time analog; PHP integration registers both via `register_block_template()` or `theme.json` variants.
- **Tokens / components stay ours.** v1 used `rgb(var(--md-sys-color-primary))` syntax with raw RGB triples; we did NOT adopt that. Our `--md-sys-color-*` tokens are direct hex values consumed by `var()` directly. v1 patterns rebuilt against our token API.
- **Material Symbols icon font dependency** from v1 NOT adopted — wp.org §14 (no external dependencies). Each new icon added manually in this pass continues that pattern.
- **Seed swap (plum/teal/rose/forest/amber)** from v1 NOT yet integrated. Better as `theme.json` `styleVariations` in Phase 3, not as runtime token swap.

---

### Added — Phase 2B δ — static prototype pages (2026-05-08)

> **Block-theme template hierarchy expansion.** Adds 4 new static prototype pages covering search, archive, attachment, and a custom post type (axismundi_profile). All built with internal WP knowledge — no `<!-- wp:* -->` block markup (Phase 3 PHP integration territory).

#### New pages
- **`search.html`** — search results template. Page heading + meta (count + duration), full-width refinement search bar (text-field--with-leading + clear button), result cards with `<mark>` highlighted query terms, sidebar (result-type filter chips as radiogroup + recent searches list).
- **`archive.html`** — generic archive template (fallback for category / tag / date / author / taxonomy archives). Hero band (eyebrow + title + description + count), 2-column post grid + sidebar (category list + monthly archive + tag chips), pagination at bottom row spanning full grid.
- **`attachment.html`** — single attachment / image detail template. Breadcrumb, large media banner (placeholder gradient stand-in for `<img>`), title + caption, 2-column body (prose description + meta panel with file info + action buttons), prev/next attachment navigation.
- **`single-axismundi_profile.html`** — CPT single template for the `axismundi_profile` content type. Hero banner with avatar overlap, identity row (name + handle + follow button), stats row (articles / notes / followers / following), tabs (overview / articles / notes / collections), 2-column body (bio prose + sidebar with details + interest tags), recent posts grid (3 cards).

All pages share the canonical header / nav-drawer / footer pattern from existing pages (home / front-page / single / 404 / index).

### Fixed — Phase 2B γ-3-vqa1.2 — visual QA pass 3 (2026-05-08)

> **VQA pass 3** — 4 issues from Ji-woon's review of vqa1.1 zip. Slider color was reported as still broken from vqa1; root cause turned out to be wrong inactive token, not the gradient direction (which was the vqa1 hypothesis). Plus 3 new findings.

#### Slider — color truly fixed this time

- **Root cause was the inactive token, not the gradient direction.** vqa1 had changed `secondary-container` (low-contrast surface tone) → `secondary-container` (no change in name); the actual M3 spec slider inactive track uses `on-secondary-container` (foreground role, higher contrast against page surface). Without enough contrast against the page background, the inactive track was technically rendering but visually invisible. Changed both `::-webkit-slider-runnable-track` gradient stop and `::-moz-range-track` background to `var(--md-sys-color-on-secondary-container)`.
- Also reverted the gradient direction back from `to inline-end` (logical) to `to right` (physical) for older Safari (≤16) compatibility — logical gradient directions are dropped as invalid in some Safari versions, which would silently wipe the entire background.

#### Prose ul / ol — top-level margin restored

- vqa1's §2.2 (`{block} { margin-block: 16px }`) was being silently overridden at runtime by an older `.prose ul, .prose ol { margin-block: 0 }` rule lower in `prose.css`. Both rules had identical specificity (0,1,1) so source order won — the override was the loser. Removed the override entirely; nested-list tightening is now scoped to `.prose li > ul/ol` (extra specificity, no conflict with §2.2). Added explanatory comment so future readers don't reintroduce the override.

#### Table stripes — header-aligned rhythm

- vqa1 had stripes at `surface-container` (one step darker than the prose `th` header band's `surface-container-low`) on the assumption that "different from header = visible stripe." Ji-woon's correction: the stripe should match the header tone so the alternation reads as a continuous rhythm starting FROM the header (header [stripe] → row 1 [base] → row 2 [stripe] → row 3 [base]). Changed stripe `background-color` to `surface-container-low` to match header tone.

#### Table vertical borders — new rule

- `is-style-vertical-borders` had no CSS rule, leaving the WP block style toolbar option non-functional. Added `border-inline-end: 1px outline-variant` on `th/td` with `:last-child` exclusion to avoid doubling the wrapper's outer outline.

#### Style guide text-field demo — 7 configurations matrix

- Replaced ad-hoc subsections (Filled states / Outlined states / Prefix-Suffix-Counter / Leading-Trailing / Textarea) with a structured 2 × 7 matrix that mirrors the M3 spec configuration list per Ji-woon's request: each variant (Filled / Outlined) gets both a "5 states" sub-section (rest / focused / populated / error / disabled) AND a "7 configurations" sub-section (1 supporting text · 2 trailing · 3 leading · 4 leading+trailing · 5 prefix · 6 suffix · 7 multi-line). Numbered to match the M3 reference matrix for direct cross-referencing.

### Fixed — Phase 2B γ-3-vqa1.1 — visual QA pass 2 spec audit (2026-05-08)

> **17-image M3 text-field spec audit** ([m3.material.io/components/text-fields/specs](https://m3.material.io/components/text-fields/specs)). Direct spec measurement comparison; one minor parity fix applied, one deferred item closed.

#### Outlined focus outline thickness — 3px → 2px

- M3 spec consistently shows 2dp focused outline thickness across all reference images (light/dark, with/without icons, error states). Our implementation used 3px which is functionally safe (passes WCAG 1.4.11 with margin) but parity-incorrect. Fix: `box-shadow: inset 0 0 0 2px` on `.text-field--outlined .text-field__container:focus-within` (normal + error variant). Total 2 sites updated.

#### Suffix → input text-align (deferred from γ-3 ultrareview): CLOSED

- M3 spec image 6 (configuration list) directly shows: when a suffix is present (e.g. `25 lbs`), input value `25` stays **left-aligned**, suffix `lbs` is **right-aligned in its own grid column**. Input value text-align does NOT change. Our existing layout (`.text-field__input { grid-column: 3 }` + `.text-field__suffix { grid-column: 4 }`) already matches this exactly. No code change. The original VQA hypothesis ("input should right-align when suffix present") was incorrect; the visual right-alignment in design references comes from suffix being in its own cell, not from input changing alignment.

### Notes

- 11 other audit points checked against spec measurements, all compliant: 56dp container height, 16dp / 12+24+16 padding decomposition, filled bottom-border 1→2dp active indicator, outlined `with-leading` floated label notch (label ignores leading icon offset, sits at outline start), outlined notch backdrop (label breaks outline), prefix/suffix grid placement, prefix/suffix rest hide pattern, error caret + value + password mask color cascade, disabled `-webkit-text-fill-color` UA override, focus caret primary.
- Deferred items closed: 1 (suffix). Open questions resolved: 1 (outline thickness).
- See `vqa2-spec-audit.md` for full 17-image audit detail with all measured spec values.

---

### Fixed — Phase 2B γ-3-vqa1 — visual QA pass 1 (2026-05-08)

> **Visual QA after γ-3 ultrareview** — 7 issues found across components and prose typography. Includes one revert of an incorrect ultrareview patch (C-2) where the M3 spec was misread.

#### Reverts (incorrect γ-3 ultrareview decisions)

- **C-2 revert** — outlined component border color restored from `outline` back to `outline-variant` at 4 sites (`.ax-button.is-outlined`, `.ax-icon-button.is-outlined`, `.card--outlined`, `.chip--{assist,filter,input}`). M3 spec verification (Buttons spec → Color → Outlined → Enabled → "outline color = `md.sys.color.outline-variant`"; Chips spec same): `outline-variant` is the correct token. The γ-3 audit's contrast argument (1.62/1.99 vs WCAG 1.4.11 3:1) was correct as a contrast measurement but invalid as a fix justification — the M3 spec deliberately uses outline-variant for these components, and "fixing" it broke spec conformance. Lesson: cite spec URL + quoted text, do not rely on memorized M3 token role.

#### Time picker (components.css §32)

- **Field input vertical+horizontal centering** — `<input type="text">` value was rendering at the lower-left of the 96×72 field box. Root cause: UA default `text-align: left` for inputs + `line-height` from display-large (~64px) inside a 72px box putting the baseline below center. Fix: explicit `text-align: center` and `line-height: 72px` (matches box height for vertical centering).
- **Separator vertical alignment** — `<span>:</span>` was sitting at parent baseline, glyph dropping below the field's vertical center. Fix: `display: inline-flex; align-items: center; height: 72px; line-height: 1`.
- **Period selector — native radio exposed** — markup uses CSS-only `<input type="radio"> + <label>` toggle pattern but components.css had no rule for `.ax-time-picker__period-input`, leaving the native radio circle visible. Fix: visually-hide the input (clip-path inset 50%) and drive selected state via `:checked + .ax-time-picker__period-btn`. Selected state via `.is-selected` / `[aria-selected]` retained for the `<button>` markup variant; both patterns now produce identical visuals.
- **AM/PM divider** — period selector container had a 1px outline-variant border but no divider between AM and PM halves. Fix: `.ax-time-picker__period-btn:not(:last-of-type) { border-block-end: 1px solid var(--md-sys-color-outline) }`.

#### Text field (components.css §10)

- **Default caret color** — added `caret-color: var(--md-sys-color-primary)` to `.text-field__input` so the cursor matches the focus indicator (active indicator + label color all turn primary on focus; the caret was rendering as on-surface, breaking the visual binding).
- **Error state — caret + value + password mask** — `is-error` and `:user-invalid` states now apply `color: error` + `caret-color: error` + `-webkit-text-fill-color: error` to the input itself. Reasons: password-type input renders the mask glyph (•••) using `currentColor`, so the dots only turn red when input `color` is error; `-webkit-text-fill-color` is needed because Safari/iOS UA styles override plain `color` in some states.
- **Disabled value color — WebKit override** — added explicit `-webkit-text-fill-color` on `:disabled` input to defeat the UA `color: GrayText` that was overriding our `color: inherit` cascade (especially aggressive on iOS Safari).
- **Suffix → input text-align** — DEFERRED. M3 guideline review needed (filled-leading-icon + suffix combination may be a style-guide example error rather than a spec defect). Awaiting Ji-woon's spec re-check with reference images.

#### Search text field — prototype markup

- **`text-field--with-leading` modifier missing** in `home.html` (sidebar search) and `404.html` (page search). Without the modifier, the floating label was not shifted to clear the leading search icon, and the two visually overlapped. Fix: add modifier, plus migrate the markup to the caveat 9.10–compliant pattern (outer `<div class="text-field__container">`, inner `<label class="text-field__label" for="…">` rather than `<span>`, removed unused `data-mode="rest"` attribute).

#### Table stripes (blocks.css §6)

- **Stripes color collision with prose `th` header band** — `.wp-block-table.is-style-stripes tbody tr:nth-child(even)` was using `surface-container-low`, identical to the `prose th` header background. In `.prose` contexts the header and the even-row stripe rendered at the same tint, making the zebra pattern invisible. Fix: bump stripes to `surface-container` (one step darker), preserving the header band at `surface-container-low`.

#### home.html — markup order

- **Layout intent vs implementation mismatch.** `.ax-blog` grid was designed as `[posts | sidebar]` 2-column on desktop with `pagination grid-column: 1/-1` (full width below). But the markup ordered `[posts → pagination → sidebar]`, so on mobile the stack was `[posts → pagination → sidebar]` (sidebar appearing AFTER pagination, defeating its purpose) and on desktop the grid auto-placement put pagination on row 2 and sidebar on row 3, breaking the sticky-sidebar contract. Fix: swap the two blocks so markup reads `[posts → sidebar → pagination]`. CSS unchanged; both desktop 2-column and mobile stack now match design intent.

#### Prose vertical rhythm (prose.css §2.1, §2.2 — new)

- **§2.1 Paragraph rhythm** — body-large internal line-height (~24px) was absorbing the 16px owl-default gap between paragraphs, visually erasing the boundary. New rule: `.prose > p + p { margin-block-start: 1.25em }` (≈20px at body-large) per WP / Tailwind Typography / Notion convention. `em` (not `space-md`) keeps the rhythm proportional to local font-size for nested prose.
- **§2.2 Block-punctuation rhythm** — `blockquote`, `pre`, `ol`, `ul`, `hr`, `table`, `figure` are themselves visual units that need vertical breathing room on BOTH sides. The §2 owl-default only sets `margin-block-start` (top), letting the next paragraph cling to the block's bottom edge. New rule: `.prose > {block} { margin-block: var(--space-md) }` — direct top+bottom 16px margin on the punctuation element itself. Margin-collapsing handles interaction with §2 owl: when the next sibling's owl `margin-top` (16px) meets this element's `margin-bottom` (16px), the gap collapses to 16px, balanced. Specificity (0,1,1) beats §2 owl (0,1,0); loses to §2.1 `p + p` (0,1,2) which is fine — `p + p` is for paragraph rhythm only.

- **Slider — color rendering** — theme.js had no slider IIFE, so `.ax-slider__input { --_value }` defaulted to 0%, rendering the active-track gradient as 100% secondary-container. On a light surface this read as "no fill color." Added `§7 Slider` IIFE in `theme.js` that sets `--_value` to the input's percentage on load + on `input`/`change` events. Static markup in `style-guide.html` also got inline `style="--_value: NN%"` fallback so the prototype renders correctly before JS executes.

### Notes

- C-2 revert is the second revert of a γ-3 ultrareview decision (after Ji-woon's earlier rejection of C-1, C-4.6, B-5, B-3-partial reverts). Pattern: ultrareview's quantitative axes (token resolution, contrast ratios, ARIA roles) catch real defects but its M3-spec assertions need URL + quoted text, not memorized role mapping.
- Time picker was the most visually broken component pre-fix; all four issues (field, separator, period, divider) are corrected in a single pass.
- Suffix → input text-align is the only deferred item from this VQA pass; awaits Ji-woon's M3 guideline image review.

---

### Fixed — Phase 2B γ-3 — ultrareview (2026-05-08)

> **6-axis sequential audit** — token consistency, logical properties, a11y (ARIA + WCAG AA), cross-page sync, theme.js wiring, wp.org compliance. 36 issues found, 26 fixed in this pass, 10 deferred to Phase 3 (PHP integration territory) or future review passes.

#### Axis A — Token consistency
- `--space-xxs` undefined token replaced at use site (`components.css:746` `.nav-rail.is-expanded { gap }` → `var(--space-xs)`). Token scale unchanged; M3 sys/ref layers stay scheme-driven, project-local `--space-*` stays at xs/sm/md/lg/xl.
- `--space-2xl` undefined token (4 uses with hex fallback in `home.html` + `index.html`) → `var(--space-xl)` at all sites. The 2xl tier belongs to WP `theme.json` `spacingSizes` in Phase 3, not M3 scale.
- Defensive hex fallback `var(--md-sys-color-scrim, #000)` removed from inline `<style>` drawer scrim in 4 pages (`404`, `front-page`, `home`, `single`). Token always defined; matches `components.css .dialog::backdrop` standard pattern.
- After Axis A: 4 component CSS files + HTML inline styles hold 0 hex literals; 188/188 used tokens resolve cleanly.

#### Axis B — Logical properties (RTL safety)
- 35 declaration lines in `components.css` migrated from physical to logical inline-axis properties (zero visual change in LTR, culturally-correct flip in RTL):
  - 6 lines top-corner radii on `.card__media`, `.tabs--primary .is-active::after`, `.sheet--bottom-modal` → `border-start-{start,end}-radius`
  - 7 → 4 lines full-axis spans → `inset-inline:0` / `inset-block:0` shorthands
  - 15 lines centering pairs/standalones → `inset-block-start: 50%` / `inset-inline-start: 50%`
  - 12 lines `@keyframes ax-progress-linear-1/-2` → `inset-inline-start/inset-inline-end` (RTL: bar slides end-to-start, matching reading direction)
  - 2 lines `linear-gradient(to right, …)` → `to inline-end` on `.ax-progress-linear.is-determinate` + slider track (Chrome 120+ / Safari 16+ / Firefox 121+ baseline)
- WP `.has-text-align-{left,right}` in `blocks.css`: kept physical (intentional, comment strengthened — author's "right align" mental model is visual right, not directional end).
- Slider `::-moz-range-progress` physical radius kept (Firefox slider doesn't honor `dir=rtl` for its progress fill).

#### Axis C — A11y (ARIA + WCAG AA)
- 6 `<a aria-hidden="true">` blog post media links in `home.html` → added `tabindex="-1"` so keyboard users skip past the empty duplicates (AT was hidden, keyboard wasn't).
- 4 interactive `is-outlined` components (`.ax-button`, `.ax-icon-button`, `.card--outlined`, `.chip--{assist,filter,input}`) migrated `outline-variant` (1.62/1.99 contrast, fails WCAG 1.4.11) → `outline` (4.33/5.87, AA pass + M3-spec correct). The 4 remaining `outline-variant` uses are decorative dividers (correct location).
- New `§6.7 Skip link` block in `base.css` (~75 LOC). Markup `<a href="#main" class="skip-link">메인 콘텐츠로 건너뛰기</a>` placed first-after-`<body>` on all 5 user-facing pages.
- `single.html` `<div class="ax-single">` → `<main class="ax-single" id="main">` (was missing main landmark entirely).
- 13 redundant landmark roles removed per W3C ARIA-in-HTML §3.3: `role="banner"` × 6 `<header>`, `role="contentinfo"` × 6 `<footer>`, `role="navigation"` × 1 `<nav>`. Plus `role="main"` on 4 `<main>` elements.
- Heading hierarchy fixed across all 5 pages (WP TT5 pattern): homepage keeps `<h1>` site title, non-homepage demotes site title to `<p class="app-bar__title">` and uses `<h1>` for page-specific content. After: every user-facing page has exactly 1 `<h1>`.
- WCAG AA contrast verified on all M3 sys-color text pairs (most AAA, lowest 5.48 dark `surface-variant`).

#### Axis D — Cross-page sync
- Drawer content drift across 4 pages (front-page 6+ items vs home/single/404 2-3 items) — **deferred to Phase 3 PHP** (`wp_nav_menu()` becomes source of truth).
- `.ax-nav-list__label` markup convention drift — **deferred** (initial dead-class diagnosis was incorrect; class IS defined inline in front-page + sidebar; needs `.ax-nav-list` BEM family review next pass).
- Whitespace formatting drift in drawer/header markup — **deferred** (Phase 3 partial extraction normalizes).
- Header action-button divergence (front-page 알림+계정 vs home 검색+계정 vs single/404 검색만) — **kept as-is** pending intent confirmation.

#### Axis E — theme.js wiring
- `§5 TOC scroll-spy` promoted from STUB to real implementation. Removed inline `<script>` duplicates from `single.html` + `style-guide-prose.html` (-42 LOC duplicates, single source of truth).
- `home.html` filter chips converted from `[role="toolbar"][aria-pressed]` to `[role="radiogroup"][role="radio"][aria-checked]` (M3 spec: single-select chips use radiogroup semantics, not toolbar).
- `style-guide.html` theme switcher (Light/Dark/Auto) converted to same radiogroup pattern. `style-guide.js` `apply()` extended to set the 3-marker pattern (`aria-checked` + `aria-pressed` + `.is-selected`).
- New `§6 Radiogroup keyboard nav` IIFE in `theme.js` (~50 LOC). Per-instance click handlers + container-delegated keyboard nav (←↑ prev, →↓ next, Home / End). Sets `aria-checked` + `.is-selected` on click; CSS multi-marker selector handles the rest.
- `components.css` `.chip--filter` selected-state extended to match `[aria-checked="true"]` alongside the existing `[aria-pressed="true"]` and `.is-selected`.
- `theme.js` LOC: 311 → 403.

#### Axis F — wp.org compliance
- `LICENSE` file added to theme root (canonical FSF GPL-3.0 text, SPDX-recognized — 34,673 bytes).
- License declarations in `style.css` + `readme.txt` harmonized to SPDX-standard `GPL-3.0-or-later` identifier with `LICENSE` file reference. Preserves wp.org automated license check parity.
- §13 single front-facing credit verified compliant (`<a rel="author" href="…">designbusan.ai.kr</a>` exactly once per footer).
- No external CDN/font dependencies (Google Fonts, FontAwesome): self-contained.
- `index.php`, `theme.json`, `templates/index.html`, `screenshot.png` — Phase 3 deliverables, not Phase 2A scope.
- `본인 실명` placeholder in 5 footers + readme — open task for Ji-woon, not a Phase 2A defect.
- 16 inline `style=""` attributes in `front-page.html` (all token-based, zero hex) — Phase 3 cleanup target.

### Notes
- Patch deltas re-validated against `Axismundi-phase-2B-gamma-2-final.zip` baseline. 24 files, no inventory drift.
- 4 deferred items in Known issues below are awaiting either explicit decision (header action drift, B-axis block-axis orphans) or Phase 3 deliverables (drawer content sync via `wp_nav_menu`, markup convention via `.ax-nav-list` family review).

---

### Known issues — deferred to v1.5+

#### Date picker (components.css §31, ~lines 5210–5500)
시각 문제 4건이 있으나 v1.0.0-rc1에 *시각 구조만* 제공하기로 결정한 컴포넌트 (실제 month/day 로직, 키보드 navigation, range selection 모두 author/JS 영역). Phase 3에서 picker JS interaction layer 작성 시 함께 정리 예정.

1. **`is-in-range` 셀이 시각적으로 끊어져 보임** — `.ax-date-picker__grid`에 grid `gap`이 있어 cell 사이 빈 공간이 생김. range fill을 끊김 없이 잇으려면 gap=0 + cell 자체에 padding으로 hit area 확보, 또는 ::before pseudo로 가로 fill 그려서 gap 가로지르는 방식 필요.
2. **`is-range-start` + `is-selected` 둘 다 둥근 모양 → 충돌** — range 양 끝점은 *반원 + 사각형 합성* 모양이어야 (안쪽으로 range fill 이어지고, 바깥쪽 끝만 둥글게). 현재 둘 다 full circle.
3. **range color가 두 cell 중간까지 와야** — start cell 오른쪽 절반 + end cell 왼쪽 절반에만 range bg, 가운데 cell들은 풀 fill. ::before pseudo로 cell의 절반 너비만 칠하는 패턴 필요.
4. **주(week) 넘어가는 range가 padding까지 닿아야** — 첫 row 마지막 cell의 fill이 grid 오른쪽 padding을 가로지르고, 다음 row 첫 cell의 fill이 왼쪽 padding을 가로지름. CSS-only로는 row 단위 ::before가 grid padding 영역을 침범하기 어려움 — JS로 first/last visible cell에 동적 클래스 inject하는 방식이 더 깔끔. 현재 구조에선 CSS-only로 *근사치*만 가능.

→ Phase 3 picker interaction wiring 시 (1)+(2)+(3) CSS 정리, (4) JS 동적 클래스로 처리. 그 전까지는 시각적으로 끊김이 보일 수 있음.

---

### Planned — Phase 2B — Feed prototype + theme JS
- Static HTML page templates — front-page (microblog feed), single (article), archive, search, 404
- Off-canvas mobile nav drawer (`<dialog>` element + scrim, no JS framework)
- Theme JS — toolbar `aria-pressed` toggle, TOC scroll-spy, off-canvas nav, carousel
- Article view (.prose) integration test with Korean + English long-form content
- Visual QA pass on dark scheme, 360px mobile, 200% zoom

### Planned — Phase 3 — WordPress integration
- `theme.json` finalize — color palette, typography roles, layout `wideSize` / `contentSize`, block style hooks
- `register_block_style()` PHP wiring — Button (5), Group as Card (3), Separator (5), List (1), Search
- Block patterns — Post Card, Profile Head, Composer, Filter Chips Row, Suggestions, Trends
- Template parts — `header.html`, `footer.html`, `sidebar.html`
- Templates — `front-page.html`, `single.html`, `archive.html`, `404.html`, `single-axismundi_profile.html`
- `m3-blocks` plugin — Tier 1 (icon-button, chip)
- `axismundi-blocks` plugin — Tier 1 (post-card, composer)

---

## [0.6.1] — 2026-05-08 — Phase 2B γ-2 — 페이지 보강 (home + single + index)

> **마일스톤:** γ-1 (parts + front-page + 404) 완료 후 페이지 templates 3개 추가. WordPress template hierarchy 완전 cover (front-page → home → index fallback chain).

### Added — page templates

- **`home.html`** (677 lines) — 블로그 인덱스, full features.
  - Layout: 2-column main (posts grid + sidebar), 모바일에서 sidebar가 main 아래로 stack
  - Category filter chips row (`role="toolbar"`, multi-select aria-pressed) — 전체/디자인 시스템/WordPress/ActivityPub/A11y
  - 6 sample blog posts in 2-col grid (`.ax-blog-post.card.card--outlined.card--interactive`) — gradient placeholder media (3 variants), category chip, title, excerpt, meta (date/author/read time)
  - Pagination component (`.ax-pagination__btn`) — primary fill on current, disabled state, ellipsis spacer
  - Sidebar widgets (sticky on desktop ≥900px): search field (outlined text-field), categories list with counts, recent posts, tags cloud (chip)
  - Header / drawer / footer markup synced from front-page.html

- **`single.html`** (923 lines) — 단일 article view.
  - Layout: minmax(0, 1fr) 240px (article + TOC rail) at ≥1100px, single column 이하
  - Breadcrumb (`<nav class="ax-breadcrumb">` + `<ol>`)
  - Article hero gradient placeholder (border-radius: large), category chip, display-small title, meta row (avatar + author + date + read time)
  - `.prose` body — full long-form Korean+English content about M3 token system (h2: why-tokens, three-tiers, state-layers, korean-typography, conclusion; h3: ref-layer, sys-layer)
  - Article footer: tags row + share buttons (link copy + share icons)
  - Author bio card (`.ax-author-bio.card.card--filled`) — avatar, name, handle, bio, "전체 글 보기" tonal button
  - Related posts grid (3 outlined cards)
  - Comments section: form with outlined textarea + filled "댓글 등록" button, comment list with nested replies (`.ax-comment-list .ax-comment-list` with `border-inline-start: 2px solid outline-variant`)
  - TOC rail with IntersectionObserver scroll-spy (inline script, mirrors style-guide-prose.html pattern). Tablet/mobile에선 article 위로 stack.

- **`index.html`** (348 lines) — TT5-style minimal fallback.
  - Role: WordPress template hierarchy ultimate fallback. ActivityPub 플러그인 비활성 시 front-page.html에서 자동으로 fallback. wp.org §11 필수.
  - Concept: WordPress Twenty Twenty-Five (TT5) 미니멀 차용 — 단일 컬럼 max-inline-size 65ch, 큰 whitespace, serif typography (`--md-ref-typeface-serif` = Roboto Serif + Noto Serif KR + Georgia fallback)
  - Minimal header: brand name (serif clamp 2-3rem, letter-spacing -0.02em) + serif italic tagline + 4 nav links (Home/Blog/About/Archive). 액션 아이콘 없음.
  - Recent posts list (5 sample): meta (date · category · read time, uppercase letter-spacing) + serif italic title + body excerpt + "Read more →" primary link. 각 항목 사이 space-2xl (48px) vertical rhythm.
  - Section title `— Recent Notes —` serif italic centered
  - Pagination: minimal "← Newer" / "Older →" 양 끝 정렬, italic serif
  - Single-line footer credit (serif italic)
  - Why this design? Plugin 없는 vanilla 환경에서도 site가 *읽기 좋게* 작동. Microblog UI 기능 (FAB, 카드 그리드)이 없어도 *깨진 느낌* 없도록.

### Changed — docs/CONTEXT.md
- §3.1 — γ-1 완료 + γ-2 진입 + γ-3 후보 명시
- §3.5 — `index.html` ✅ 표시 (wp.org §11 필수 4 파일 모두 충족)

### Notes
- **Template hierarchy 완성** — `front-page.html` (microblog) > `home.html` (블로그 인덱스) > `index.html` (TT5 fallback). WP가 자동으로 가장 specific한 template을 골라 렌더.
- **`index.html`이 곧 wp.org §11 필수 파일 4개 중 마지막 누락분** (`style.css`, `readme.txt`, `theme.json`은 이전 phase에서 처리). 이로써 기술적으로 wp.org 디렉토리 제출 가능 상태.
- **Inline duplication** — 5 page (front-page, home, single, 404, index 중 index 제외) 모두 header/drawer/footer markup 인라인 복사. Phase 3 PHP `template-part` include로 dedupe 예정. index는 자체 minimal layout이라 dedupe 대상 아님.
- **TT5 차용 결정 rationale** — TT5 자체 미니멀리즘 (whitespace, serif, 단일 컬럼)을 차용하되 *완전 흉내는 X*. M3 토큰 그대로 + serif typeface만 추가. 결과 — light/dark 자동 sync + 본인 디자인 언어 유지.

### Pending — γ-3 또는 δ
- γ-3 후보: `archive.html` (date/category/tag), `search.html`, `attachment.html`, `single-axismundi_profile.html` (CPT)
- δ 직전: `themes/` knowledge prep chat (1회) — Theme Handbook 청크화

---

## [0.6.0] — 2026-05-08 — Phase 2B γ-1 — 정적 페이지 1차 (parts + front-page + 404)

> **마일스톤:** Phase 2B β style guide 분리 마무리 후, 본격 page template 빌드 진입. *옵션 A* 합의 — parts 3 + front-page + 404, 5 파일.

### Added — template parts (standalone preview pages)

각 part는 *standalone preview HTML*로 작동. templates에 inline 복사. Phase 3 PHP 통합 시 server-render로 dedupe.

- `parts/header.html` — App bar `.app-bar--small` + hamburger (`data-toggle-nav` + `aria-controls="nav-drawer"` + `aria-expanded="false"`) + 검색/알림/계정 actions. Material icons (menu, menu_open, search, notifications, account_circle) inline SVG. Desktop 1024px+ 에서 hamburger 숨김 (nav rail이 항상 보임). `.ax-site-header[data-scrolled="true"]` 시 outline-variant border-block-end.
- `parts/footer.html` — 3-column grid (about / site nav / legal nav) + bottom row (copyright + GPL credit + WordPress credit). 720px 이하에서 single column stack. wp.org §13 single front-facing credit link 충족.
- `parts/sidebar.html` — Nav rail expanded 패턴 (custom `.ax-nav-list` markup — components.css `.nav-rail`은 icon-only, prose 길이 nav가 필요해 새 마크업). Depth-2 expandable submenu (`<button class="ax-nav-list__expand">` + `aria-expanded` + `aria-controls`) — theme.js §2 accordion 트리거. Depth-3 재귀 (Explore → Fediverse → 로컬/연합) 시연.

### Added — page templates

- `front-page.html` — microblog feed.
  - **Layout matrix**: mobile single column (sidebar=drawer), tablet 600+ 2-col (sidebar+feed), desktop 1024+ 3-col (sidebar+feed+trends rail).
  - **Post card** (`.ax-post.card.card--outlined`) — avatar (gradient placeholder) + author name/handle + relative time + body + optional media + actions (like/reply/share with counts). 3 sample posts (한글 + 영문 mixed, ActivityPub-style handle).
  - **FAB compose** — `.ax-fab.is-large` fixed bottom-right, edit icon, "새 글 작성" aria-label.
  - **Trends rail** (desktop only) — segmented list (`.wp-block-list.is-style-list-segmented`) for hot topics + 팔로우 추천 카드.
  - **Nav drawer** (`<dialog id="nav-drawer">`) — 모바일 햄버거 클릭 시 modal mode open. Sidebar 마크업 일부 복사 (homepage/blog/explore/notifications/profile/settings). Phase 3에서 PHP template part로 dedupe.

- `404.html` — App bar + 큰 "404" hero (display-large, primary→tertiary linear-gradient via `-webkit-background-clip: text`) + 한글 메시지 + 액션 버튼 2개 (홈으로 / 블로그 둘러보기) + outlined search field (form action="/search"). 동일 nav drawer + footer.

### Changed — docs/CONTEXT.md

- §2 — *Phase 2B β 진행 중*으로 갱신, Phase 1B + 2A α + 2B β + β-fix1/2/2b 누적 완성품 목록
- §3 — Phase 2B γ 진입 가이드 (옵션 A 채택 명시, static HTML 형식 결정, parts 포함 패턴, theme.js wiring 본 작업의 일부 명시)
- **§4 NEW — Knowledge base 점진 정리 plan** — 작업 영역과 동기화된 just-in-time 정리. 각 단계별 정리 시점 + 트리거 + 본인이 받은 5 핸드북 목차 ground truth 기록 (Theme / Block Editor / Plugin / Common APIs / REST API). δ 직전 themes/ 정리 chat의 raw 자료 첨부 식별 기준으로 작동.
- §5 — Archive (이전 §3 Phase 2A 진입 가이드, rationale 보존)
- §6–§9 — 번호 시프트

### Notes
- Static HTML 형식 — `<!-- wp:* -->` 코멘트 *없음*. Phase 3 PHP `render_block` 필터로 inject 예정.
- `index.html` (template hierarchy 루트) — 이번 chat 미포함, δ 진입 시 가장 먼저 정리 (또는 front-page.html 별칭).
- Sidebar inline 중복 — front-page.html 안 inline sidebar + nav-drawer 안 sidebar (mobile용) 두 곳에 markup 복사. Phase 3에 server-side dedupe.
- theme.js §1/§2 실전 wiring 검증 — front-page에서 햄버거 → drawer open + accordion 작동, 404에서도 drawer 작동.
- 모든 sample image = gradient placeholder (primary-container → tertiary-container 또는 secondary-container → tertiary-container). 외부 이미지 요청 0, dark scheme 자동 sync.

---

## [0.5.2] — 2026-05-07 — Phase 2B β-fix2 — Time picker + visual rhythm

### Changed — prose.css paragraph spacing
- §4 — `.prose p + p { margin-block-start: 1.25em }` (이전 `space-lg` 24px가 너무 넓고, `space-md` 16px는 `<br>`처럼 보였음). 1.25em (~20px at body-large) = Tailwind Typography 표준값. em 단위로 fontsize 변경 시 자동 비례.

### Fixed — Time picker (components.css §34) 전면 재작성
- **Markup**: `<button>` → `<input type="text" inputmode="numeric" maxlength="2">` (M3 §16.3 input variant — 직접 타이핑 가능). Period selector도 `<button>` → `<fieldset>` + `<input type="radio">` + `<label>` 패턴 (button group §28과 동일 — single-select mutually-exclusive, CSS-only, native keyboard arrow).
- **AM/PM divider**: 첫 `__period-btn`에 `border-block-end: 1px solid outline` 추가. Border가 background-color 위에 그려지므로 selected fill이 덮지 않음 — "selected covers outline" 이슈 동시 해결.
- **State layer**: `<button>` 마크업엔 기존 ::before tint, `<label>`-based period btn은 `background-color: color-mix()`로 직접 tint (parent overflow:hidden이 ::before를 클립해서). selected + hover 콤보는 tertiary-container 위에 on-tertiary-container 8% blend.
- **Focus**: `<input>` 시 inset 2px primary outline. radio:focus-visible 시 sibling label에 secondary outline + z-index 1 (parent overflow:hidden 클립 회피).
- **Backward-compat**: 기존 `<button>` 마크업 + `.is-selected` / `[aria-selected="true"]` 셀렉터 모두 유지.

### Fixed — Time picker visual alignment + period frame (β-fix2 후속)
- `__inputs { line-height: 0 }` — 콜론 span의 inline line-box (display-large 64px line-height)가 `align-items: center`의 box-center를 왜곡해서 콜론 글리프가 input 숫자 optical center 대비 ~4px 아래로 드리프트. line-height 0으로 inline 부모의 line-box 차단, flex 자식들은 자기 line-height 유지.
- `__separator { line-height: 1 }` — 콜론 글리프 자체에 tight box. typescale의 default line-height 무시.
- `__period`: `outline + offset:-1px` → `border: 1px solid outline`. Outline은 paint 마지막에 그려지지만 인접 selected fill의 채도가 강하면 1px 라인이 시각적으로 묻힘. border는 box geometry 자체라 자식 bg가 *물리적으로* border 영역 침범 불가 → selected 상태에서도 frame 명확.

### Fixed — Wide table 데모 (style-guide-blocks.html)
- 이전 데모 콘텐츠가 짧아서 720px sg-demo 안에 우연히 맞아 가로 스크롤 안 트리거. 셀에 `style="white-space: nowrap"` + 콘텐츠 더 길게 (8 컬럼, 풀네임 + 이메일 전체 + 타임스탬프 + IP) → 어떤 viewport에서도 강제 overflow.

### Documented — Date picker known issues
- v1.0.0-rc1 시점에서 4건 visual bug 인지 (range 끊김, range start/end shape 충돌, range color 절반 fill, week 넘어가는 padding fill). 코드 변경 X — 이번 릴리즈는 *시각 구조만* 제공하는 컴포넌트. Phase 3 picker interaction JS 작성 시 함께 처리. 위 [Unreleased] § Known issues 섹션 참조.

### Notes
- M3 spec의 caret-thickness는 비표준 — `caret-color` (색)와 `caret-shape` (Safari 16.4+ block/bar/underscore)만 표준이라 두께 변경은 무시 권장. spec 이미지의 두꺼운 caret은 mockup 시각효과.

---

## [0.5.1] — 2026-05-07 — Phase 2B β-fix1 — Visual QA round 1

### Fixed — prose.css
- §4 — `<p> + <p>` 사이 명시적 margin (`space-lg`로 처음 시도 — `<br>`과 시각 구분 위해. **β-fix2에서 1.25em으로 재조정**)
- §6 — blockquote `margin-block: var(--space-md)` 명시 (위/아래 균형)
- §11.6 — figure-as-wrapper 안의 figcaption 스타일 (margin-block-start, padding-inline)
- §11.7 — `is-style-vertical-borders` 변형 (셀 병합 표용)

### Fixed — blocks.css
- §6 — `is-style-vertical-borders` mirror (prose 밖 작동), `is-style-wrap` (가로 스크롤 opt-out + word-break: keep-all), figcaption 스타일 layer

### Fixed — components.css text-field
- disabled value 색 — `-webkit-text-fill-color` + `opacity: 1` (iOS Safari UA 강제 회색 override)
- error 시 input value/caret/password mask color → error token
- suffix 있을 때 input `text-align: end` (`:has(~ .text-field__suffix)` 셀렉터)

### Fixed — Style guide horizontal scroll
- `.sg-main` `min-inline-size: 0` 추가 (CSS Grid 1fr child의 implicit minimum이 `auto` = `min-content`라 긴 `<pre>` 콘텐츠가 track 확장)
- prose tablet 분기 `minmax(0, 1fr)` 명시

### Added — themed thin scrollbars (3 가이드)
- `.sg-sidebar`, `.sg-toc-rail`에 `scrollbar-width: thin` + `outline-variant` thumb + transparent track + webkit button 숨김. 시스템/페이지 외부 스크롤바는 그대로.

### Added — Style guide blocks samples
- Wide table 3개 변형 — wrap, vertical-borders, with figcaption

---

## [0.5.0] — 2026-05-04 — Phase 2A: Style foundation 확장 ✅

> **마일스톤:** Phase 1B 33 컴포넌트 위에 *콘텐츠 출력 레이어* 3개 시트 추가 + button group 디버그 패스. WordPress 블록 테마 진입점 (`style.css`, `readme.txt`) 정리.

### Added — base.css 확장 (v0.2.0 → v0.3.0, +331 lines)
- §6.6 A11y utility — `.u-vh` (visually hidden, clip-path inset 패턴)
- §8 Block elements — blockquote (border-inline-start primary-바깥 outline-variant), hr, figure, figcaption
- §9 Lists — ul/ol/li, ::marker subtle, task list (`:has(> input[type="checkbox"]:first-child)`)
- §10 Tables baseline — border-collapse, caption, th font-weight (시각 스타일은 prose.css가 담당)
- §11 Code — inline `<code>` pill, `<pre>` block padding + overflow-x, `<kbd>` key cap
- §12 Inline rich text — mark / del / s / ins / abbr
- §13 Native `<select>` — outlined-text-field 시각 (1px → 3px primary focus), CSS-only chevron via twin linear-gradient stems with currentColor (no SVG data URL, token 100%, dark scheme 자동 sync)
- §14 Heading anchor — `.heading-anchor` opacity 0 → 1 on heading hover or anchor focus
- §15 `::selection` — secondary-container fill

### Added — prose.css 신설 (352 lines)
- §1 `.prose` baseline — body-large 명시 (parent context 무관)
- §2 Vertical rhythm — lobotomized owl (`> * + *`) + first/last child reset
- §3 Headings — asymmetric content margin (h1/h2 큰 top space-xl, h6 작은 top space-md)
- §4–5 Paragraphs / lists — owl 패턴에 위임, 간격만 조정
- §6 Blockquote — primary border + 큰 padding (base의 outline-variant 위에 강조 layer)
- §7 HR — space-xl 마진 (섹션 break)
- §8 Code — `<pre>` space-lg padding (인라인은 base 그대로)
- §9 Images / figures — radius-medium + figcaption center
- §10 Links — always-underline (WCAG 1.4.1 색상 단독 의존 회피), `.heading-anchor` 만 예외
- §11 Tables 풀 스타일 — `.table-wrapper` + `figure:has(> table)` 양쪽 wrapper 패턴 통합 (border + radius + overflow-x), 셀 padding, header surface-container-low + outline 강조선, last-row no-border, body-medium typography

### Added — blocks.css 신설 (436 lines)
- §1 WP alignment — `.alignleft / .alignright / .aligncenter / .alignwide / .alignfull` + `.has-text-align-*` (alignfull 주석에 *bounded parent required* 명시)
- §2 paragraph drop-cap, list margin reset
- §3 quote pass-through (prose에 위임), pullquote 전용 (headline-medium serif italic, top/bottom dividers)
- §4 code / preformatted / verse (verse만 serif)
- §5 Separator + 5 variants — default(25%), wide(100%), dots(`···`), divider-inset, divider-middle-inset
- §6 Table — wrapper containment (prose 밖에서도 작동, idempotent with prose.css), `.has-fixed-layout`, `.is-style-stripes`
- §7 Image radius-medium, gallery 1–8 column grid
- §8 Columns (mobile stack @ 600px), Group as Card 3 variants (filled / elevated / outlined)
- §9 Button + 5 style variants fallback (PHP `register_block_style()` + render filter wiring 후 components.css §3 takeover, 주석에 명시)
- §10 List `is-style-list-segmented` — components.css `.ax-list--segmented` 미러

### Fixed — Button group (components.css §28)
- **BUG #1**: `flex: 1 1 auto` → `flex: 1 1 0` 변경. `auto` basis였을 때 selected의 `flex-grow: 1.15`가 *남은 공간만* 1.15:1:1로 분배하다 보니 콘텐츠 너비 차이에 묻혀 시각 변화가 거의 안 보임. basis 0으로 가서 grow 비율이 *절대 너비*를 결정하게.
- **BUG #2**: `:active` (pressed) vs `.is-selected` (selected) 동일 specificity로 source order에 의존하던 충돌 — selected+pressed 조합 명시 룰 추가, M3 spec대로 pressed가 우선.
- **BUG #3 (근본)**: `<button>` 단독 마크업으로는 *클릭 → selected 토글*이 JS 없이 불가. CSS-only single-select 패턴 필요.

### Changed — Button group 마크업 패턴 도입 (components.css §28 전면 재작성)
- **Pattern A (PRIMARY)**: `<fieldset>` + visually-hidden `<input type="radio">` + `<label class="ax-button">`. Single-select에 의미적으로 정확 (`role="radiogroup"` 자동), 키보드 arrow 네이티브, JS 0 lines. `:checked + .ax-button` 셀렉터로 selected 스타일.
- **Pattern B**: `<button aria-pressed>` + JS toggle (toolbar/multi-select 전용 — B/I/U/S처럼 mutually-exclusive 아닌 케이스).
- 기존 `<button class="is-selected">` 마크업도 backward-compat 유지 (스타일 가이드 정적 데모용).
- `:first-of-type / :last-of-type` 셀렉터로 `<label>` + `<button>` 양쪽 패턴 동시 지원.
- Style guide section 마크업 교체, toolbar JS 데모 인라인 추가.

### Added — wp.org submission groundwork
- `style.css` (테마 root) — 표준 헤더 메타데이터 (Theme Name, URI, Author, License GPL-2.0-or-later, Tags 11개, Description)
- `readme.txt` — wp.org 디렉토리 표준 형식 (Description, Installation, FAQ, Changelog, Copyright)
- `style-guide.html` footer — 저작자 + 라이선스 표기 (실명, designbusan.ai.kr, GPL-2.0-or-later, docs CC-BY-4.0)

### Notes
- Phase 2A 시트 (base / prose / blocks) 합 1,469 lines, 전체 token-driven, hex literal 0개.
- WP 핸드북 §11 (필수 파일 4개) 진척: `style.css` ✓ / `readme.txt` ✓ / `theme.json` ✓ (Phase 1B 잔존) / `templates/index.html` 대기 (Phase 2B).
- Button group radio + label 패턴은 *단일 선택 + 콘텐츠 패널 전환 없는* 경우 한정 사용. 탭(`role="tablist"`)은 별도 컴포넌트로 분리 예정.
- Swiper Material You Slider 무료 코어 채택 + M3 비주얼 자체 구현 결정 (premium 의존 회피). Phase 3 carousel 블록에서 토큰 매핑.

---

## [0.4.0] — 2026-05-03 — Tier 3 + Tier 4 (시스템 완성 9개) — 33/33 컴포넌트 ✅

> **마일스톤:** Phase 1B 종료. 33 spec 컴포넌트 *전부* 빌드. Tier 3 (시스템 완전성 5개) + Tier 4 (deferred 4개) 묶어서 한 단계로. Style guide JS 외부 분리.

### Added — Tier 3 (5 components, `components.css` §26-§30)

#### §26 List (M3 §19)
- Container — surface, level0, corner-large.
- Item base — 56 (1-line) / 72 (2-line) / 88 (3-line) tall, slot-based:
  - Leading: icon 24 / image 56 / avatar (composes `.ax-avatar`)
  - Content: overline + label + supporting (1-line truncate, 3-line clamps to 2)
  - Trailing: icon 24 / label-small text
- **Expressive shape state machine (M3 §19.6)** — corner morph by state: rest extra-small → hover medium → focus/pressed/selected large → disabled extra-small
- Selected — secondary-container fill + on-secondary-container content
- Pattern A state layer + focus indicator inner -3px
- Segmented variant (`.ax-list--segmented`) — 2px gap + per-item full corner-large
- Divider helper (`.ax-list__divider`)
- **Deferred to v1.5+:** video slot, multi-select differentiated visuals, drag handle slot

#### §27 Progress indicator (M3 §21)
- Linear — 4px track + corner-full
  - Determinate: linear-gradient + `--_value` custom property (slider pattern)
  - Indeterminate: 2-bar M3 keyframe animation
- Circular — 40px size + 4px stroke (r=18)
  - Determinate: stroke-dashoffset + `--_value` 0-100 + default-spatial transition
  - Indeterminate: rotation + dasharray morph
- **`prefers-reduced-motion` fallback** — both variants degrade to opacity pulse
- **Deferred to v1.5+:** wavy variant (M3 expressive — complex SVG path animation)

#### §28 Button group (M3 §5)
- Standard variant — selected button widens 1.15× (M3 §5.6 spring) + secondary-container fill (toggle visual unified across base variants — filled/outlined/tonal)
- Outlined selected → outline-color: transparent (clean fill)
- Connected variant — 2px gap, full-pill outer corners, per-size inner corners
  - Inner radius rest: XS 4dp / S/M 8dp / L 16dp / XL 20dp (`large-increased`, not `extra-large`)
  - Inner radius pressed: M → 4dp / L → 12dp / XL → 16dp
  - Selected → 50% (full pill — middle button morphs to capsule)
- Border-radius transition included for state-driven shape morph
- **Deferred to v1.5+:** toggle button shape morph (square ↔ round) — requires shape-toggle on `.ax-button` itself; square-shape variant for connected group

#### §29 Toolbar (M3 §33)
- Docked variant — 64px height, full-width, 16px padding, corner-none
- Floating variant — 64px height, corner-full, level3, 8px inside padding
- Standard color (surface-container) + Vibrant color (primary-container) schemes
- Slot composition — `.ax-icon-button.is-standard` + currentColor cascade
- Selected button — secondary-container (Standard) / surface-container (Vibrant)
- `.ax-toolbar__spacer` flex helper
- **Deferred to v1.5+:** floating-with-FAB variant (toolbar-bound FAB sizes diverge from standalone), vertical orientation

#### §30 Carousel (M3 §12)
- Native horizontal scroll + `scroll-snap-type: x mandatory`
- Custom property layout pattern (`--_carousel-leading/trailing/block/gap`)
- 3 layouts: multi-browse (default), hero (large + small queue, 16:9 aspect), uncontained (trailing 0 = bleed past edge)
- Item — corner-extra-large (28dp), aspect-ratio fallback, sized variants `--large/--medium/--small`
- Item label — inverse-surface theme-aware contrast pair (light/dark both readable)
- Optional nav buttons (`.ax-carousel__nav--prev/--next`)
- iOS smooth scroll + reduced-motion fallback
- **Deferred to v1.5+:** center-aligned hero, multi-aspect-ratio uncontained, full-screen, item morph animation

### Added — Tier 4 (4 components, `components.css` §31-§34)

#### §31 FAB menu (M3 §9)
- Close button (anchor) — 56×56 corner-full, level3 (rest) → level4 (hover), icon swap (rest ↔ X with rotate 90° transition)
- List items stack vertically *위로* (column-reverse + flex-end)
- Item button — 56px height, corner-full, 24px L/R padding, 8px gap, title-medium typography
- 3 color sets via `.is-color-{secondary,tertiary}` modifier — Custom property pattern (close-bg/fg + item-bg/fg)
- Toggle pattern: `.is-open` + visibility/opacity/transform transition
- 10-line inline JS for `[data-fab-menu-toggle]` (now in external `scripts/style-guide.js`)
- **Deferred to v1.5+:** item enter/exit stagger animation (50ms cascade)

#### §32 Split button (M3 §10)
- Composes two `.ax-button` instances + 2px gap
- Inner corner per size: M=4dp, L=8dp, XL=12dp
- Hover → inner morph 8dp; selected/`aria-expanded="true"` → 50% full pill
- Trailing icon helper (`.ax-split-button__trailing-icon` 20×20)
- v0.4.0 scope: M (default) + S sizes shown in style guide

#### §33 Date picker (M3 §15) — visual structure only
- Docked variant: 360×456, surface-container-high + level3 + corner-large
- Header (supporting + headline-large), nav row, weekdays grid, 7-col date grid
- Date cell 40×40 corner-full + state layer Pattern A
- Variants: `.is-today` (primary outline), `.is-selected` (primary fill), `.is-outside` (38% opacity), `.is-in-range` (secondary-container, range-start/range-end with primary fill — Compose DateRangePicker pattern)
- Modal + modal-input variants reuse internals + author wraps in `.dialog`
- Calendar logic = author/library responsibility
- **Usage patterns documented** (Compose-derived): Docked (text-field + popup) / Modal (dialog wrap) / Modal-input (dialog with text-field only)

#### §34 Time picker (M3 §16) — visual structure only
- Container — surface-container-high + corner-extra-large + 24px padding + level3
- Input variant: time field 96×72 (display-large in surface-container-highest box), separator, period selector vertical 52px (AM/PM with tertiary-container selected)
- Selected field = primary-container
- Dial variant: 256×256 surface-container-highest circle scaffold (geometry/labels/selector handle = JS-driven)
- **24-hour mode** — `.is-24h` modifier hides AM/PM period selector (Compose `is24Hour=true` pattern)
- Clock dial drag interaction = author/library

### Changed — Style guide
- **Sidebar updates** — Actions: + FAB menu, Button group, Split button, Toolbar. Inputs: + Date picker, Time picker. Display: + List, Carousel. Feedback: + Progress.
- **JS extracted to external file** — 5 inline `<script>` blocks (~330 lines) → single `scripts/style-guide.js` (FAB menu toggle / text field counter / checkbox indeterminate / slider gradient / main runtime). Loaded via `<script defer>`.
- **34 component sections** — full sample markup, KR localization throughout.

### Fixed — accumulated visual issues (Phase 1B QA round)
- **Connected first/last child border-radius** — kept logical properties + 8dp inner per spec (rejected Gemini's "logical + transition browser bug" diagnosis; sheet uses same pattern with no issue)
- **BEM convention oversight** — 14 instances of `ax-button--{filled,outlined,tonal}` corrected to `ax-button is-{filled,outlined,tonal}` per system convention (G2 button-group demos used wrong pattern)
- **Button group selected state** — added secondary-container fill + on-secondary-container content (was missing — selected state had only widen+morph cue, no color change). Outlined variant outline-color: transparent on selected.
- **Button group transition flicker** — expanded transition list to include `color`, `box-shadow`, `outline-color` (was only `flex-grow`/`border-radius`/`background-color`, causing color jumps while bg eased)
- **Carousel label dark-mode legibility** — switched from theme-invariant scrim gradient to inverse-surface contrast pair (light bg in dark, dark bg in light — both readable)
- **Inline badge alignment** — added `vertical-align: middle` + `margin-block-start: -0.1em` optical adjustment (was sitting on baseline, looked low next to cap-height text)
- **Search bar input** — added `name="q"` + `id` + `type="search"` (browser autofill warning, mobile search keyboard)
- **FAB menu demo padding** — replaced `padding-block: 200px/240px` overhead with `min-height` (cleaner demo geometry)

### Caveats added — `OVERVIEW.md`
- **§9.10 Text field markup A11y refactor** — `<label>` container → `<div>`, `<span class="text-field__label">` → real `<label for="...">`. HTML5 forbids labelable elements (`<button>`) inside `<label>` other than the controlled input. Plus DOM-order grid auto-placement gotcha — every slot has explicit `grid-row: 1`.

### Notes
- `components.css` 3,966 → 5,636 lines (+1,670, 33 components total)
- `style-guide.html` 2,541 → 3,046 lines (after JS extraction; was 3,377 with inline scripts)
- `scripts/style-guide.js` new — 348 lines
- Hex literals: 0 across entire codebase
- All M3 spec literals justified with inline comments citing M3 spec sections
- 33/33 spec components built (Tier 4 Date/Time picker = visual structure only — interaction is author/library territory)

### Deferred to v1.5+ (catalogued for prototype scope decisions)
🟢 Required for prototype completeness:
- Toolbar floating-with-FAB variant (Feed compose pattern)

🟡 Optional for prototype:
- Slider value indicator
- Switch icon-on-handle
- Carousel center-aligned hero, item morph animation

🔴 Defer indefinitely (not blocking prototype):
- Menu horizontal orientation, Vibrant color, position-aware corners, submenu
- Loading indicator true M3 Expressive morph
- Slider S/M/L/XL sizes, vertical, centered, range, stops
- List video slot, multi-select differentiated visuals, drag handle
- Progress wavy variant
- Button group toggle button shape morph (square ↔ round), square-shape variant

---

## [0.3.0] — 2026-05-02 — Tier 2 (form 4개) + Text field 구조 리팩토링

> **마일스톤:** Form 컴포넌트 4개 추가 (E bucket — 외형만, Phase 2 form plugin과 통합). Text field 구조적 리팩토링 (M3 spec 정합성 + 누락 슬롯 추가). 21 → 25 컴포넌트.

### Added — 4 form components (`components.css` §22–§25)
- **§22 Checkbox** (M3 §13) — 18×18 box + 40×40 state layer halo. Hide-native-input + custom visual span 패턴. Selected (primary fill + on-primary check), indeterminate (horizontal bar), error variant (`.is-error`). Pattern C disabled.
- **§23 Radio** (M3 §25) — 20×20 outer ring + 10×10 inner dot (10/20 ratio). Same hide-native + custom visual pattern, 40×40 state layer. Selected dot scales in via spring transition. Pattern C disabled.
- **§24 Switch** (M3 §30) — Track 52×32 pill (`box-sizing: border-box` + 2px outline). Handle morph (M3 §0.9 pattern 4): 16 unselected → 24 selected → 28 pressed. Geometry calc per CSS spec (containing block = padding edge). State layer 40×40 follows handle position. Pattern C disabled.
- **§25 Slider** (M3 §27 XS standard horizontal) — Native `<input type="range">` + vendor pseudo-elements (`::-webkit-slider-{runnable-track,thumb}` + `::-moz-range-{track,thumb,progress}`). Handle morph 4→2px on press. JS-driven gradient via `--_value` custom property (5-line inline JS in style guide). Pattern C disabled.

### Cross-color pressed quirk (Checkbox + Radio, M3 §13.3 / §25.3)
Implemented per spec:
- Unselected hover/focus state layer = `on-surface`
- Unselected **pressed** = `primary` (preview of selected)
- Selected hover/focus = `primary`
- Selected **pressed** = `on-surface` (preview of unselected)

### Refactored — Text field structural rewrite (`components.css` §9)
**v0.2.x markup (single `<label>` wrapper) → v0.3.0+ markup:**
```
<div class="text-field text-field--filled">              ← outer wrapper
  <label class="text-field__container">                  ← visual container
    <input ... />                                        ← input first in DOM
    <span class="text-field__label">…</span>
    <span class="text-field__leading-icon">…</span>
    <span class="text-field__prefix">₩</span>            ← visual col 2
    <span class="text-field__suffix">KRW</span>          ← visual col 4
    <span class="text-field__trailing-icon">…</span>
    <span class="text-field__error-icon">…</span>
  </label>
  <div class="text-field__bottom">                       ← OUTSIDE container
    <span class="text-field__supporting">…</span>
    <span class="text-field__counter">0 / 50</span>
  </div>
</div>
```

**Why the refactor:**
- Container background no longer leaks onto supporting text / counter (those sit in `__bottom`, outside visual container).
- 5-column grid (leading | prefix | input+label | suffix | trailing+error) with prefix/suffix slots added.
- Native `:user-invalid` validation (post-interaction) drives `.is-error` styles without JS — both paths share styles.
- Counter slot with `[data-tf-counter]` JS auto-update (5-line script in style guide).
- Pattern D disabled now extends to `__bottom` row text + prefix/suffix.
- DOM order = input first (slot positions via `grid-column`) — required for `:placeholder-shown ~ .__prefix` selector.

**M3 §32 icons & images:**
- Leading + trailing slots (24px), error icon slot (auto-shown via `.is-error` / `:user-invalid`, hides trailing icon at same grid col).
- Interactive trailing buttons compose with `.ax-icon-button.is-standard.has-state-layer` (system reuse vs parallel impl).
- Clear button uses `.is-clear` modifier — auto-hides when input empty (M3 §32 "appear only when input text is present"), CSS-only.

**Other text field fixes in this release:**
- Outlined input padding-block reset to 0 (input centers vertically since label notches outline).
- Prefix/suffix hidden at rest (placeholder-shown + not focused), shown when label floats up.
- Outlined `.text-field--with-leading` floating label notch sits at outline corner (not above leading icon).
- Error label color preserved on focus (specificity fix).
- Textarea container = block (not 5-col grid); container padding-top reserves rest-label line-height.

### Added — caveats (`OVERVIEW.md` §9.9)
- **§9.9 Token naming divergence** — Axismundi follows M3 sys-tier convention (`var(--md-sys-color-*)`), not Material Web's `--md-{component}-{prop}` flat naming. Rationale: Axismundi is system-builder (sys-layer theming) not component library; ~330 component-flat tokens would duplicate sys layer for marginal flexibility. Documented to prevent re-questioning.

### Style guide
- **Sidebar — Inputs category** — added Checkbox, Radio, Switch, Slider entries.
- **Component categorization** — Switch + Slider placed in Inputs (form-input role) rather than Selection (chip + badge stay there).
- **Color palette grid simplified** — removed dual `data-theme="light"` + `data-theme="dark"` grids (CSS scoping doesn't propagate from inner element to css custom properties — both showed identical colors). Replaced with single grid that follows root theme switcher. Dead CSS (`.sg-scheme-pair`, `.sg-scheme`, `.sg-scheme__label`) and dead JS (`darkScope` query) removed.
- **Text field demos** — 14 demos rewritten in new markup (5 filled + 5 outlined + 2 prefix/suffix/counter + 2 leading/trailing + 1 textarea + 1 dialog leftover). New "Leading · Trailing icons" subsection with search field (leading + clear button) + email field (leading + auto error icon).

### Fixed
- **Switch geometry** — handle leading position calc accounts for `box-sizing: border-box` + abspos containing block = padding edge (CSS spec). Final values: unselected rest 6px, unselected pressed 0, selected rest 22px, selected pressed 20px, state layer unselected -6px, state layer selected 14px. Track has `overflow: visible` so pressed 28×28 handle can extend outside per M3 pattern.
- **Slider** — focus ring on thumb only (`::-webkit-slider-thumb`, `::-moz-range-thumb`), not on the 44px-tall input box. Disabled gradient bleed fixed by switching `background-color` → `background` shorthand. Firefox `::-moz-range-progress { background: transparent }` added for disabled state.
- **Badge `right: auto`** — removed (was canceling `inset-inline-end` due to physical/logical property override).

### Notes
- `components.css` 2,944 → 3,966 lines (+1,022).
- `style-guide.html` 2,151 → 2,541 lines (+390).
- All 4 form components: hex 0개, var() 100%, M3 spec literals 인라인 코멘트로 정당화.
- Slider scope = XS standard horizontal only. S/M/L/XL sizes, vertical, centered, range, stops, value indicator deferred to v1.5+.
- Switch optional icon-on-handle deferred to v1.5+ (~80% of use cases don't need it).

---

## [0.2.5] — 2026-05-02 — Tier 1 (마이크로블로그 핵심 7개)

> **마일스톤:** 마이크로블로그 prototype에 필요한 핵심 컴포넌트 7개 추가. 14 → 21 컴포넌트.

### Added — 7 components (`components.css` §15–§21)
- **§15 FAB** (M3 §7) — 3 sizes (default 56 / medium 80 / large 96), 6 color styles (3 tonal + 3 high-emphasis), Pattern A state layer + disabled, M3 §7.4 elevation states (level3 rest → level4 hover).
- **§16 Extended FAB** (M3 §8) — 56px height, min-width 80px, leading icon + title-medium label, same 6 color styles.
- **§17 Nav bar** (M3 §23) — mobile bottom navigation, 64px height, 4-item layout, active indicator pill 56×32 (§0.13), state layer Pattern A, focus indicator inner -3px, RTL-safe via inset-inline, iOS safe-area-inset-bottom respected.
- **§18 Badge** (M3 §2) — 2 variants: small (6×6 dot, 3dp radius) + large (16×16 numeric, 8dp radius). Width-aware positioning via `transform: translate(50%, -50%)` — handles "3", "12", "99+" without icon overlap. `.has-badge` host helper. RTL-safe.
- **§19 Menu** (M3 §22) — vertical orientation (Standard color). Slots: leading icon, label, supporting text, trailing supporting (e.g. ⌘K), trailing icon. Section label + section divider. Selected state via tertiary-container (§22.7). Pattern A state layer + disabled. `.is-open` toggle pattern.
- **§20 Loading indicator** (M3 §20) — 2 variants: default 48px (uncontained, primary) + contained 38×48 (primary-container alveus). Small 24px variant for inline use. SVG-based rotating arc with stroke-dashoffset morph. `prefers-reduced-motion` fallback (rotation → opacity pulse).
- **§21 Tooltip** (M3 §34) — plain (inverse-surface, body-small, max 240px) + rich (surface-container + level2, subhead + supporting + 1-2 actions). Action button = text-button-like, primary color. State layer Pattern A.

### Style guide
- **Sidebar reorganization** — 14 components 평면 → 8 categories: Foundation / Actions / Containers / Navigation / Inputs / Selection / Feedback / Display.
- **7 new sections** — FAB / Extended FAB (Actions), Nav bar (Navigation), Badge (Selection), Menu (Navigation), Tooltip / Loading (Feedback). Korean samples included where relevant ("새 글 쓰기", "Notifications + 3", "내 프로필 / @dohyun", "키보드 단축키", "저장 중...").

### Fixed
- **Auto-scroll on page load** — removed `autofocus` from text-field demo input that caused page to scroll to text-field section on every refresh.
- **Native scrollbar mismatch** — `color-scheme` now syncs with `data-theme` attribute (and follows `prefers-color-scheme: dark` in auto mode). Light Axismundi mode no longer shows OS-dark scrollbar.

### Project structure
- Folder structure introduced (option 3 — partial classification):
  ```
  Axismundi/
    style-guide.html
    theme.json
    stylesheets/{tokens.css, base.css, components.css}
    docs/{OVERVIEW.md, CONTEXT.md, CHANGELOG.md}
    uploads/...
  ```
- `style-guide.html` `<link>` paths updated to `stylesheets/{file}.css`.

### Notes
- `components.css` 1,888 → 2,944 lines (+1,056).
- `style-guide.html` 1,667 → 2,151 lines (+484).
- All 7 new components: hex 0개, var() 100%, M3 spec literals 인라인 코멘트로 정당화.
- Badge/Loading/Tooltip 모두 M3 spec deviations 명시 (Menu position-aware corners deferred, Loading morph animation 단순화, Tooltip width bounds sensible defaults).

---

## [0.2.0] — 2026-05-01 — v2 클린 리빌드

> **마일스톤:** Phase-1 폐기 후 M3 baseline 기반 클린 리빌드. 14 컴포넌트 + 토큰 시스템 안정 상태 확보.

### Added — 시스템 기반
- **`tokens.css`** — Reference layer (6 tonal palettes, 88 tones) + System layer (38 sys-color × 2 schemes) + Typescale (15 roles × 5 properties) + Spacing (5 tier) + Shape (10 tier + 3 directional) + State opacity + Motion (12 spring + 12 cubic-bezier) + Elevation (6 level) + Component tokens + Semantic aliases + Layout tokens
- **`base.css`** — Modern reset + heading→typescale 매핑 + KR-first 처리 (`word-break: keep-all` + `overflow-wrap: anywhere` + `line-height: 1.6`) + focus-visible 글로벌 + reduced-motion + `.t-{role}` 유틸리티 클래스 15개
- **`theme.json`** — 토큰 단일 source-of-truth manifest (ref / sys / comp / modes 3-tier)

### Added — 컴포넌트 (14개)
- **§0 Foundation** — `.has-state-layer` mixin (Pattern A, currentColor + opacity 토큰)
- **§1 Avatar** — sm 32 / default 40 / lg 56 / xs 24 (chip leading slot용)
- **§2 Button** — 5 variants (filled / tonal / elevated / outlined / text), S size, pressed shape morph
- **§3 Icon button** — 4 variants (filled / tonal / outlined / standard) + toggle 모드
- **§4 Divider** — full-width / inset / middle-inset
- **§5 Card** — 3 variants (filled / elevated / outlined) + interactive state-layer
- **§6 App bar** — 3 variants (small / medium-flexible / large-flexible) + scrolled state
- **§7 Nav rail** — collapsed (96px) + expanded (.is-expanded modifier, 220-360px)
- **§8 Tabs** — primary + secondary, `::after` indicator + `.is-active` (CSS-only)
- **§9 Text field** — filled + outlined, floating label (`:placeholder-shown`), error state, textarea variant
- **§10 Search bar** — rest state (full search view는 dialog로 위임)
- **§11 Chip** — 4 variants (assist / filter / input / suggestion), filter selected state, chip-only icon size 18px
- **§12 Dialog** — basic + full-screen, `<dialog>` element, manual scrim (native `::backdrop` 무효화)
- **§13 Sheet** — bottom-modal + side-modal (RTL safe with logical properties)
- **§14 Snackbar** — single/two-line, with/without action, inverse-surface 색상

### Added — Style guide (`style-guide.html`)
- Sidebar anchored navigation + theme switcher (light/dark/auto + localStorage)
- Color tokens swatch (38 sys-color × light + dark, side-by-side)
- Typography specimen (15 typescale role, 영문 + 한글 sample)
- 14 컴포넌트 시각 카탈로그 (variants + states + code snippets)
- Live modal triggers (dialog + sheet) — body scroll lock 포함

### Added — 결정 / 정책
- M3 official baseline 채택 (Phase-1 Plum seed 폐기)
- Token format: hex (`#RRGGBB`) — `color-mix()` 호환
- State layer: Pattern A (`currentColor` + opacity 토큰)
- Motion: M3 Expressive spring physics (12 spring + 12 cubic-bezier)
- KR-first typography (`word-break: keep-all` + `overflow-wrap: anywhere`)
- Auto + manual theme override (`prefers-color-scheme` + `[data-theme]`)

### Changed — Phase-1 → v2
- Color source: Material Theme Builder (Plum seed) → M3 baseline raw spec
- Token format: RGB channels (`106 84 141`) → Hex (`#6750A4`)
- Shape scale: 5 tier (xs–xl) → 10 tier + 3 directional
- State opacity: 0.08/0.12/0.12 → 0.08/0.10/0.10/0.16 (M3 Expressive correct)
- State token name: `-hover-opacity` → `-state-layer-opacity` (full M3 form)
- State implementation: 8% overlay → `color-mix()` Pattern A
- Motion: duration aliases (4 values) → spring physics + cubic-bezier (24 tokens)
- Sys color tokens: 14개 → 38개 (inverse, surface containers, on-error-container 추가)

### Removed — Phase-1 폐기
- `surface-tint` 토큰 (M3 Expressive에서 deprecated)
- 5-seed swap 시스템 (over-engineering)
- Material Theme Builder export 의존성

### Fixed — Visual QA round
- **Issue 1:** `--space-2xl` 미정의 토큰 사용 → `--space-xl`로 변경
- **Issue 2:** Avatar 섹션 `sg-row--baseline` 정렬 깨짐 → `sg-row` (center)
- **Issue 3:** Static full-screen dialog가 frame 안에서 깨짐 → viewport 단위 (`100vw`/`100vh`) 제거 (스크롤바 간섭 회피)
- **Issue 4:** Live modal `.modal-scrim` ↔ `<dialog>::backdrop` 이중 darkening → `::backdrop` transparent 처리
- **Issue 5:** Chip leading/trailing icon size selector mismatch (`<svg class="chip__leading-icon">`이 `.chip__leading-icon > svg` selector에 매칭 안 됨) → `svg.chip__leading-icon` 추가 selector
- **Issue 6:** Static demo (sg-frame--sheet/--dialog)가 modal-scrim 위에 떠오름 (z-index 1001 누수) → `isolation: isolate`로 stacking context 가둠
- **Issue 7:** Modal 열릴 때 body 스크롤 가능 → `html.has-modal-open { overflow: hidden }` + JS class toggle
- **Issue 8:** Avatar `is-size-xs` (24px) 누락 → components.css §1에 추가, chip-input leading slot 표준 사이즈

### Notes
- Type utility classes (`.t-{role}`)는 base.css에 *retroactively* 추가됨 (style-guide.html에서 사용 시 발견). 새 토큰은 도입 안 됨.
- `:has()` 의존: Modern Safari 15.4+, Chrome 105+, Firefox 121+. Older browser fallback은 v1.5에서 검토.
- Outlined text-field notch backdrop은 `surface` 위 가정. `surface-container-highest` 위에선 mismatch — v1.5에서 `--text-field-notch-bg` 토큰 도입 검토.

---

## [0.1.0] — Phase-1 (폐기됨)

> **상태:** Audit에서 다수 spec 위반 발견 후 v2로 클린 리빌드. 산출물 미보존.

### Identified issues (audit-en.md 참조)
- State opacity 잘못된 값 (0.12)
- Shape scale 5 tier만
- Inverse 색상 토큰 누락
- Token name 단축형 (`-hover-opacity`)
- Plum seed 임의 채택 (Material Theme Builder 추측)
- Motion에 spring physics 미사용

### Lessons applied to v2
모든 issue가 `prompt-v2.md` §0.1 변경 표 + Appendix A self-check로 흡수됨.
