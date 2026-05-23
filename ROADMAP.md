# Roadmap

> **Two roadmaps coexist:**
> - **This file (`ROADMAP.md`)** — monorepo / architectural roadmap (v3.x → v4.0)
> - **`products/reference-implementations/axismundi-v1.0.0-rc1/docs/ROADMAP.md`** — product roadmap (Phase 9 MVP build, user-authored)
>
> They are intentionally separate. Architectural changes (Constitution, layer structure, new design systems) live here. Product feature decisions (MVP scope, federation features, identity layer) live in the product's own ROADMAP.

---

## Commercial Sustainability

Axismundi's public theme and repository path stay GPL-compatible for WordPress.org
submission. Long-term sustainability is handled through compatible models rather
than proprietary theme-code licensing: paid support, hosted services,
GPL-compatible premium plugins, template packs, consulting, and sponsorship.
A dedicated commercial strategy document is deferred to a future v4.0 public
release planning cycle.

---

## v3.0.x — Structural hardening

- v3.0.0 ← Monorepo normalization (corpus/atlas/core/bindings/products/tools)
- v3.0.1 ← Design Doctrine, binding schema spec, products 2-track split, projections placeholder
- v3.1.0 ← RC integration + publishing surface (Axismundi v1.0.0-rc1 imported, /styleguide/ publish mirror, atlas/material/ added, Article 12)
- **v3.2.0** ← **License, Font, Icon Foundation** (LICENSE-MATRIX, NOTICE, self-hosted variable fonts, Material Symbols + scope policy, rc1 → prototype rename)

## v3.2.x — Asset integration

- **v3.2.1** ← Font Runtime Integration (fonts.css, icons.css, tokens.css --md-grade, prose.css icon scope enforcement, first icon-button pattern)
- **v3.2.2** ← Interaction Lab Audit (benchmark → lab rename, INTERACTION-AUDIT.md, .nojekyll, typography-axis.html, slider color refinement, Korean typography audit)
- **v3.2.3** ← **Font Coverage Fix** (Roboto full no-subset re-conversion; `-latin` suffix dropped; Noto stays Korean-fallback via unicode-range; future-CJK-expansion-ready)
- v3.2.4 (optional) — Promoted CSS/JS merge into lab/components; styleguide GRAD sync demo page

## v3.3 — Lab Promotion + Legacy Split

- **v3.3.0** ← **Lab Promotion + Legacy Split** (this release: prototype → `_archive/`; fonts/icons → `core/design-systems/material3/assets/`; lab as active visual authority; publish source → lab; Constitution Article 12 Rule 5 added)
- **v3.3.1** ← **Lab Cleanup / Style Guide Regression Fix** ✓
  - Lab inherited pre-lab-era contamination from prototype styleguide. User identified after v3.3.0 freeze.
  - Action taken: surgical rollback of two contaminated sections (text-field Korean form in `style-guide.html`, Time picker in `components.css`) to local clean backup; restored the v3.2.1 prose §12 icon scope policy that was missing from lab; restored the suffix right-align rule that the Korean 가격/KRW form depends on.
  - Visual-QA supplement: `pre code` background rolled back to `transparent`; table `is-style-stripes` + `.prose th` moved to `surface-container-high` to avoid `.sg-demo` backdrop collision.
  - Publish surface (`/styleguide/`) regenerated from clean lab via `tools/generators/publish_styleguide.py`.
  - Pilot validation: 1.000 / 1.000.
- **v3.3.2** ← **Carousel Lab Module Extraction** ✓
  - First module extraction following the v3.3.1 cleanup. Material You morph slider/carousel moved out of shared `benchmark-interactions.{css,js}` into self-contained lab module: `stylesheets/lab-carousel.css` + `scripts/lab-carousel.js` + `lab-carousel-pattern.html` + `docs/CAROUSEL-{AUDIT,ONTOLOGY-CHECK,VISUAL-QA}.md`.
  - Flat structure (option B); modular `lab/modules/<name>/` folder layout deferred to v3.4.x Lab Module Restructure.
  - NOT promoted to main `components.css §G3 Carousel` — module is lab-internal pending five-criterion audit (known blocker: `prefers-reduced-motion: reduce` rule not yet added).
  - Closes the `compare/Material You Slider.html` reference category (no further patterns to lift from that reference).
  - Pilot validation: still 1.000 / 1.000.
- **v3.3.3** ← **Beer CSS Intake Contract + Ripple Module Extraction** ✓
  - Established the cross-module `docs/BEER-CSS-INTAKE.md` contract (9 rules: component scope, naming, tokenization, reduced-motion, JS-disabled fallback, no listener proliferation, state-layer awareness, federation portability, audit-trail policy). This contract governs every Beer-CSS-derived module going forward.
  - Extracted ripple as the first module under the contract: `lab-ripple.{css,js}` + `lab-ripple-pattern.html` + `docs/RIPPLE-AUDIT.md`. Extract-with-refinement approach (7 specific changes vs benchmark original, not a fresh reimplementation). Explicit `.prose` / `.wp-block-post-content` / `[contenteditable]` bail-out is the new normative pattern for all subsequent modules.
  - Ripple audit verdict: PASS on all five lab promotion criteria. Manual visual QA pending on `lab-ripple-pattern.html` before promotion to `components.css`.
  - Pilot validation: still 1.000 / 1.000.
- **v3.3.4** ← **Search Expansion Module Extraction** ✓
  - Third module extraction, second Beer-CSS-derived module under the intake contract. `.search-bar` focus expansion + suggestions popup → `lab-search-expansion.{css,js}` + pattern + audit.
  - Seven refinements vs benchmark original: tokenization (6 hardcoded values), state-layer compliance on suggestion items (Pattern A `::before` instead of hand-rolled `color-mix`), explicit reduced-motion block, `.prose`/`contenteditable` bail-out, two-step Escape policy (clear-then-collapse — addresses GPT-flagged "검색어 입력 중 collapse 금지" risk), full ARIA combobox wiring (`role=combobox` + `aria-controls` + `aria-expanded` + `aria-autocomplete`), keyboard arrow-nav between input and suggestion options.
  - Validated that the intake contract works for **per-instance / stateful** patterns (search) in addition to **delegated / stateless** patterns (ripple). The §6 per-instance-listener nuance is now documented in both `SEARCH-EXPANSION-AUDIT.md §Module-comparison notes` and `BEER-CSS-INTAKE.md` change log.
  - Verdict: PASS on all five lab promotion criteria.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.0** ← **Lab Module Restructure** ✓
  - Purely structural release. Three lab modules (`carousel`, `ripple`, `search-expansion`) accumulated as flat files in v3.3.2–v3.3.4 consolidated into per-module folder layout under `lab/modules/<name>/`. Each module folder bundles its CSS, JS, pattern HTML, and per-module audit docs. Cross-module docs (`BEER-CSS-INTAKE.md`, `INTERACTION-AUDIT.md`) stay at `lab/docs/`.
  - `lab-<name>` file-prefix retained inside each module folder to preserve identifiability after publish-surface flattening (the publish generator flattens module CSS into `styleguide/stylesheets/` next to design-system files). Rationale documented in new `lab/modules/README.md`.
  - `publish_styleguide.py` becomes module-aware: discovers `lab/modules/*/lab-*.css` in addition to `lab/stylesheets/*.css`, both flattened to the publish surface. JS / pattern HTML / per-module docs intentionally not copied — lab-internal asymmetry preserved.
  - 36 relative-path references in pattern HTML files rewritten for the new depth. 15 `EXTRACTED:` marker paths in benchmark CSS/JS updated. All cross-references in module audit docs updated.
  - Validator: 1.000 / 1.000.
  - Triggered by v3.4.1 popover — popover's structural complexity (focus trap, anchor positioning, outside-click dismiss, ARIA `aria-haspopup` triad) would have made the flat layout unwieldy. Better to consolidate at three modules than at four.
- **v3.4.1** ← **Architecture Boundaries Charter** ✓
  - Pure governance release, zero code changes. Establishes `lab/docs/ARCHITECTURE-BOUNDARIES.md` as the project-wide charter that subsequent v3.4.x modules cite instead of re-deriving boundary decisions.
  - Nine sections: (§1) four-layer model (baseline / module / theme interaction / plugin); (§2) theme state cascades into prose, theme control stays in chrome — the most important new rule; (§3) seven-bucket reclassification (A core direct, B core+variation, C core+pattern, D theme interaction, E lab/plugin candidate, F plugin, G excluded), with 19-item working classification table and a mandatory `Bucket:` field for all future module audits; (§4) theme-can / plugin-should split (codifies that `axismundi-pilot` will register zero custom blocks); (§5) forbidden-ancestor list locked in lockstep with `BEER-CSS-INTAKE.md` §1; (§6) federation portability rule (no theme JS, ligature icon fonts, or private CSS class semantics inside content that may be syndicated); (§7) frontier-theme failure-mode policy (visual enhancement missing = allowed; content inaccessible / controls unusable / focus lost / federation breaks = forbidden); (§8) pattern documentation UX (canonical styleguide = final demos; lab modules = rationale + QA); (§9) living-document policy.
  - `BEER-CSS-INTAKE.md` and `modules/README.md` aligned to cite the charter.
- **v3.4.2** ← **Icon System Scope Audit** ✓
  - First cross-cutting module under the v3.4.1 charter. Scope audit only — minimal implementation, mostly inventory + policy.
  - Dual-engine codified: Material Symbols icon font for M3 chrome glyphs (Bucket D), SVG icons for WordPress / editor / social / brand / portable content (Bucket F). Not alternatives — both must coexist.
  - Five policy documents: `ICON-SYSTEM-AUDIT.md` (umbrella), `ICON-FONT-POLICY.md` (hardening + axes contract), `SVG-ICON-POLICY.md` (when SVG is required + WP integration), `INLINE-SVG-INVENTORY.md` (146-SVG inventory with per-category conversion advice), `ICON-PICKER-UX.md` (future plugin UX sketch).
  - Pattern page demonstrates hardening rules inline (move to `icons.css` in v3.4.3). `--md-grade` confirmed theme-scope (not block-scope per charter §2). Korean-first authoring requirements specified for picker registry.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.3** ← **Icon Button Runtime Prototype** ✓
  - First end-to-end run of the inline-SVG → Material Symbols conversion pattern. Scope: 40 `ax-icon-button` instances in `style-guide.html` + 4 hardening CSS rules in `lab/stylesheets/icons.css §1`.
  - 30-entry mapping table (aria-label → Material Symbols glyph) hand-curated as audit trail. Korean aria-labels preserved (`이전 달` → `chevron_left`, `다음 달` → `chevron_right`). All 40 buttons covered; 0 orphans.
  - File size: `style-guide.html` 186,659 B → 182,483 B (−2.2%). Inline SVG count: 146 → 106 (40 removed). `ax-icon-button` blocks with inline SVG: 40 → 0; with Material Symbols span: 0 → 40.
  - `ICON-BUTTON-RUNTIME-AUDIT.md` documents the prototype with 29-check manual visual QA checklist (size/alignment, weight/stroke, filled state, dark+GRAD, hardening proof, disabled, accessibility). Five-criterion promotion verdict: PASS on all five (construction-level; visual QA pending).
  - FAB, chip, search-bar, button leading icons, list, menu, text-field, checkbox, progress: NOT touched. Each has independent size/motion/layout constraints per `INLINE-SVG-INVENTORY.md §Conversion ordering`.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.3.1** ← **Visual QA Patch** ✓
  - Two blockers from user visual QA: (1) `style-guide.html` `<head>` was missing `fonts.css` + `icons.css` loads so v3.4.3 Material Symbols rendered as ligature text; fixed by adding both `<link>` tags. (2) `theme.js` looks for `[role="radiogroup"]` but all 4 module pattern HTMLs (carousel, ripple, search-expansion, icon-system) used `role="group"` — same typo cohort, all 4 fixed in this patch.
  - User-uploaded WordPress logo SVG stored in **`compare/brand-assets-research/`** (frozen reference workspace) with policy README. Theme/styleguide do NOT embed it. 14-URL inventory of un-mirrored brand sources captured for future fetch-from-source workflow.
  - **`BACKLOG.md`** (new repo-root file, 7.1 KB) — explicit deferral of 8 visual-QA findings (inline code font-size, avatar token consistency, floating-toolbar selected color, chip M3 measurement audit, WordPress logo styleguide specimen, monotone SVG theming plugin, search-bar leading icon known-delta, module pattern cohort fix already resolved). Establishes scope-creep routing convention: future visual QA observations go to BACKLOG.md unless they fit the release-in-flight's commitment.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.4** ← **Icon Migration Pass 2 + WordPress SVG Specimen** ✓
  - 19 SVGs converted across five host components: `ax-split-button__trailing-icon` × 4 (`arrow_drop_down`) + `ax-button` snackbar close × 1 + `search-bar__leading-icon` × 1 + `nav-bar__icon` × 4 (home/search/notifications/person) + `nav-rail__icon` × 9 (across two variants: home/search/notifications/chat/person). Inventory drift note: v3.4.2 estimate was chip 4 + ax-button 10 + search-bar 1 = 15. Strict-slot audit at v3.4.4 entry found chip 0 (nested cases were converted at v3.4.3), ax-button 5, search-bar 1. Cohort expanded to include same-surface-family chrome (nav-bar 4 + nav-rail 9, one pre-converted). Executed scope = 19.
  - Three CSS patches in `components.css` (`.search-bar__leading-icon > .material-symbols-rounded`, `.nav-bar__icon > .material-symbols-rounded` with z-index 1 preserving active-pill stacking, `.material-symbols-rounded.ax-split-button__trailing-icon` overriding default 24px with 20px per M3 spec).
  - WordPress wmark added as reference specimen in `icon-system-pattern.html §SVG icons` — currentColor normalized (original `<style>.cls-1{fill:#32373c;}</style>` stripped), trademark caption, source-link footer. NOT a theme primitive (Bucket F, styleguide-only). Caption phrasing deliberately scoped: "official-source styleguide-only specimen with mandatory trademark caption" — NOT "trademark policy compliant".
  - Total inline SVG in `style-guide.html`: 96 → 77 (19 removed). 36 inline SVGs remain by structural intent (FAB 35, ax-list 8, ax-menu 7, text-field 7, ax-checkbox 7, ax-progress 5, ax-loading 4, sg-* 4 — all explicitly deferred per audit doc §What does NOT change).
  - BACKLOG items 5 (WordPress logo specimen) and 7 (search-bar leading icon known-delta) closed.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.4.1** ← **Remaining Chrome SVG Sweep** ✓
  - Close-out patch finishing the 24px chrome inline-SVG family. 5 SVGs converted in v3.4.3/v3.4.4 conversion shape: `tabs__tab` × 3 (`home` / `explore` / `person`, with Korean sibling labels 홈 / 탐색 / 프로필) + `dialog__icon` × 2 (`error` × 2, both confirm-dialog instances at L2838 inline-static and L3166 portal-modal — identical SVG content, single replace() handled both).
  - Originally planned as BACKLOG item under "v3.4.x Styleguide Chrome Icon Sweep"; promoted to same-day patch because the 5 SVGs are the same migration family as v3.4.4's commitment (same Bucket D, same 24px surface, same conversion shape). Strict guardrail: 5 SVGs only, no other family touched.
  - Two intentional notes documented in audit: `탐색` placeholder bare-circle SVG → semantic upgrade to `explore` (compass); `dialog__icon` shape (circle+!) → glyph `error` (M3-canonical "attention required" indicator in dialog patterns).
  - `material-symbols-rounded` count 69 → 74; inline `<svg>` count 77 → 72.
  - v3.4.4 `ICON-MIGRATION-PASS-2-AUDIT.md` deliberately NOT edited; v3.4.4.1 records its own deltas in `ICON-MIGRATION-PASS-2.1-AUDIT.md`.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.5** ← **Popover/Menu Module Extraction + Theme Switcher Cohort Fix** ✓
  - Fourth Beer-CSS-derived lab module under `docs/BEER-CSS-INTAKE.md` contract (after carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4). Extracts 6 benchmark functions (`makeMenu`, `positionMenu`, `openBenchmarkMenu`, `closeBenchmarkMenu`, `enableAnchoredMenuDemos`, `enableSplitButtonMenus`) into `lab/modules/popover/` with Axismundi-native reimplementation.
  - Four artifacts in new module: `lab-popover.js` (12 KB, ~290 lines), `lab-popover.css` (4.7 KB, 6 sections), `lab-popover-pattern.html` (12 KB, 3 demo sections), `docs/POPOVER-AUDIT.md` (13 KB, ~330 lines).
  - **Lab module only** — `lab-popover.js` is NOT loaded from `style-guide.html`. Baseline keeps `.ax-menu.is-open` as static visual specimen. Same posture as ripple v3.3.3 — baseline promotion deferred as separate Charter §1 decision. Verdict: PASS as a lab module.
  - Nine issues fixed vs. benchmark originals: always-on listeners → open-scoped attach/detach, missing forbidden-ancestor bail-out → explicit `.prose`/`[contenteditable]` check, incomplete focus restoration → universal restoration on all close paths, `click` → `pointerdown` for outside dismiss, `stopPropagation` reliance → `requestAnimationFrame`-deferred attach, inline SVG chevron → Material Symbols `arrow_drop_down`, styleguide-specific selectors → declarative `[data-popover-trigger]` hook, missing `activeElement` capture → `previousFocus` stored at open, inconsistent ARIA triad → enforced and auto-filled at init time.
  - Phase 4b cohort fix: `data-theme-button` → `data-theme-set` rename across 4 existing module pattern HTMLs (carousel, ripple, search-expansion, icon-system) — 3 occurrences each, 12 total. New popover pattern uses `data-theme-set` from authoring. Resolves BACKLOG #9.
  - benchmark-interactions.js gains 50-line `/* EXTRACTED */` block comment above L96 `makeMenu`. Originals retained verbatim per Charter EXTRACTED policy.
  - BACKLOG #10 (lab ripple verification) re-enabled now that palette toggling works in module pattern pages.
  - BACKLOG #11 added (v3.5.0 candidate, Bucket E): Public Surface Reframe — first BACKLOG entry in Bucket E.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.5.1** ← **Theme Switcher Sync Selector Fix** ✓
  - Visual QA Gate follow-up. One-line `theme.js` patch (with doc comment update) resolving the last symptom in the theme-switcher cohort-fix family started at v3.4.3.1.
  - Root cause: `syncSwitchers()` selector was `.ax-theme-switcher` (only used in archive); active codebase uses `.sg-theme`. Result: on reload the hardcoded `aria-checked="true"` on the `auto` button stayed regardless of stored mode. Palette toggled correctly (separate code path) but selection indicator drifted.
  - `style-guide.html` masked the bug — `style-guide.js` has its own theme handler that takes precedence; module patterns don't load `style-guide.js` so they had no fallback.
  - Fix: `theme.js` syncSwitchers selector → `.sg-theme, .ax-theme-switcher` (defensive — both accepted). No markup changes anywhere.
  - Cohort-fix family completed: v3.4.3.1 role → v3.4.5 attribute → v3.4.5.1 class. Each fix touched a different layer of the same handshake.
  - BACKLOG #12 added and immediately closed.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.6** ← **Tooltip Module Extraction** ✓
  - Fifth and final Beer-CSS-derived lab module under `docs/BEER-CSS-INTAKE.md` contract. After v3.4.6 the Beer CSS interaction-module family is closed: carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4, popover v3.4.5, tooltip v3.4.6.
  - Phase 0 retirement audit confirmed `compare/beer-css/` is already absent from the active tree. Attribution preserved in `NOTICE.md` and `LICENSE-MATRIX.md`; "Code audit pending before public release" language intentionally NOT touched at v3.4.6 (separate Public Surface / License Audit phase tied to v3.5.0).
  - Four artifacts: `lab-tooltip.js` (10 KB, ~280 lines), `lab-tooltip.css` (3.5 KB, runtime-only), `lab-tooltip-pattern.html` (15 KB, 5 demo sections), `docs/TOOLTIP-AUDIT.md` (~22 KB). `components.css` `.ax-tooltip` primitive UNCHANGED.
  - **Lab module only** — same posture as popover v3.4.5 and ripple v3.3.3. Baseline promotion deferred. Verdict: PASS as a lab module.
  - Three decisions captured: **Decision B** — trigger selector narrowed to `[data-tooltip], .ax-icon-button[aria-label]` (`.ax-button[aria-label]` removed — text buttons have visible labels); **Decision Y** — show delay / touch long-press deferred to BACKLOG #16; **Phase 2 implementation decision** — rich tooltip is visual-specimen-only at v3.4.6, interactive wiring rolled into BACKLOG #16.
  - Five issues fixed vs. benchmark originals: missing `aria-describedby` (critical a11y) → wired with defensive value checks; missing forbidden-ancestor bail-out → `.prose` / `[contenteditable]` check on `pointerover` + `focusin`; global always-on listeners → **Tier A / Tier B split** (Tier A: pointer/focus enter/leave always-on; Tier B: scroll/resize/Escape visible-scoped — BEER-CSS-INTAKE rule-4 partial-compliance documented); `pointerout` collision with rich self-hover → `relatedTarget` check; trigger selector narrowed per Decision B.
  - benchmark-interactions.js gains ~50-line `/* EXTRACTED */` block comment above L473 `createTooltip`. Originals retained verbatim per Charter EXTRACTED policy.
  - BEER-CSS-INTAKE.md table updated: tooltip item 4 "held" → "Done — v3.4.6"; module audit table popover corrected from stale "planned v3.4.1" → "Done — v3.4.5"; tooltip row added "Done — v3.4.6".
  - BACKLOG #16 re-scoped to include rich tooltip interactive wiring alongside show delay / touch long-press.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.7** ← **Date/Time Picker Interaction Extraction** ✓
  - First interaction-module extraction outside the Beer CSS lineage. Beer CSS interaction-module family closed at v3.4.6 tooltip (carousel + ripple + search-expansion + popover + tooltip = 5). v3.4.7 is the first lab module with GPT Codex-generated benchmark prototype provenance, NOT Beer CSS.
  - Largest extraction by an order of magnitude: 1,112 lines combined (684 JS + 428 CSS) vs. previous largest (popover) ~288 lines. Scope discipline: extraction + audit + provenance, NOT a date-picker production-readiness pass.
  - Four artifacts: `lab-date-time.js` (805 lines, 30 KB), `lab-date-time.css` (452 lines, 10 KB), `lab-date-time-pattern.html` (359 lines, 20 KB), `docs/DATE-TIME-AUDIT.md` (~480 lines). `components.css` §33 Date picker + §34 Time picker primitives UNCHANGED. `style-guide.html` L1550-L1726 baseline specimens UNCHANGED. `style-guide-benchmark.html` UNCHANGED (cleanup deferred to v3.4.8).
  - **Lab module only** — same posture as ripple v3.3.3, popover v3.4.5, tooltip v3.4.6. Baseline promotion deferred. Verdict: **PASS as an interaction extraction module, with critical inherited a11y gaps deferred**.
  - Three decisions captured: **Decision B** — name is "Interaction Extraction" not "Module Extraction" (lineage explicit); **Decision (Phase 0 #2)** — `style-guide-benchmark.html` NOT touched (v3.4.8 cleanup); **Decision A** — single `lab/modules/date-time/` folder, not split.
  - Five minimum safety fixes applied (audit doc §4): module-scoped IIFE, forbidden-ancestor bail-out (extended selector: `.prose`, `.wp-block-post-content`, `.entry-content`, `[contenteditable]`), lab-only runtime, EXTRACTED markers, public init API. Carry-over policy: WAI-ARIA Date Picker grid navigation pattern (14 deferred items) routed to BACKLOG #19 — v3.4.7 does NOT claim production-ready date picker accessibility.
  - 2 JS EXTRACTED markers + 1 CSS EXTRACTED marker in benchmark sources. Originals retained verbatim per Charter §EXTRACTED policy.
  - BEER-CSS-INTAKE.md module audit table: unchanged for this release (date/time is NOT covered by that contract — first extraction outside it).
  - Static Visual QA Gate: PASS (40 selectors verified, 49 structural elements 100% present, 14 helpers defined, theme.js contract satisfied, 0 actual contract mismatches). Browser-side manual verification recommended but not blocking — micro-fixes routed to v3.4.7.1 if needed.
  - Pilot validation: still 1.000 / 1.000.
- **v3.4.8** ← **Benchmark Surface Deletion** ✓
  - Three benchmark source files removed from active tree: `scripts/benchmark-interactions.js` (64,962 B), `stylesheets/benchmark-interactions.css` (29,451 B), `style-guide-benchmark.html` (212,359 B). Total deletion: **306,772 B (300 KB)**.
  - Phase 0 pre-flight verified: zero lab module runtime dependencies (all `benchmark-interactions` mentions in lab/modules are audit-trail comments only); zero publish_styleguide.py references; zero style-guide.html references; zero Charter "retain forever" clauses.
  - 7 audit documents amended with retroactive "v3.4.8 Deletion Notice" blockquote at top: `BEER-CSS-INTAKE.md` (intake-specific notice that also retires intake rule 4), `CAROUSEL-AUDIT.md`, `RIPPLE-AUDIT.md`, `SEARCH-EXPANSION-AUDIT.md`, `POPOVER-AUDIT.md`, `TOOLTIP-AUDIT.md` (Beer-CSS-derived standard notice), `DATE-TIME-AUDIT.md` (GPT Codex-specific notice). Body text NOT mechanically edited — line ranges remain as historical record, protected by the Deletion Notice.
  - Provenance redundancy: 6 module audit docs + intake contract + zip freezes (v3.3.2 – v3.4.7) + CHANGELOG + ROADMAP + git history = **11 independent provenance surfaces**. `_archive/` move was considered and rejected — would invite confusion in active tree.
  - Publish surface mirror cleared: previously had stale `stylesheets/benchmark-interactions.css` (29 KB mirrored). v3.4.8 publish run automatically removed it. Mirror file count 21 → 18.
  - Pilot validation: still 1.000 / 1.000 PASS across A/B/C/D axes.
- **v3.4.9** ← **Chip Full Spec Module** ✓
  - First **Component Full-Spec Module**. Not an interaction module — no benchmark runtime to extract. The chip baseline (`components.css §11 Chip`, L1626–L1743, 118 lines, 7 rule blocks) was already mature and remains **UNCHANGED**. The module expands it into full M3 §14 spec coverage, measurement audit (closing BACKLOG #4), and WordPress mapping audit.
  - Five artifacts: `lab-chip.css` (307 lines, 10.5 KB) with 4 sections (native filter form mapping / input close button + hit-area expansion / disabled state-layer suppression / pattern-page demo helpers), `lab-chip-pattern.html` (369 lines, 18.6 KB) with 5 demo sections, `docs/CHIP-SPEC-AUDIT.md` (332 lines), `docs/CHIP-MEASUREMENT-AUDIT.md` (188 lines, closes BACKLOG #4), `docs/CHIP-WP-MAPPING.md` (205 lines, first WP mapping audit).
  - **Module taxonomy formalized** in `lab/modules/README.md` — Interaction modules validate behavior; Component modules expand baseline components into full-spec / measurement / variant / WordPress mapping surfaces. Sets the template for future Component modules (text-field, future FAB-full-spec).
  - **"Visible control must map to real runtime behavior"** adopted as cross-module design principle. Sourced from WordPress/M3 binding feedback memo (`bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md`, also new). Applies to every module's pattern HTML and audit demo sections.
  - Decision: B-2 + C-1 scope (3-doc full set + JS deferred). Filter chip state via real `:checked`, not JS-emulated. Input chip close via real `<button class="chip__close">` + `aria-label` (Option B + Option A-lite hit-area expansion). Backspace/Delete dismiss honestly deferred.
  - Decision: 4 baseline variants only (assist/filter/input/suggestion). Elevated variants → BACKLOG #23.
  - WCAG SC 2.5.8 (AA, 24×24) and SC 2.5.5 (AAA, 44×44) cited correctly throughout audit; Material touch convention noted as separate design convention.
  - Static Visual QA Gate: PASS (0 actual issues; 8 chip__control inputs ↔ 8 labels 100% matched; 4 input chips have 4 close buttons each with aria-label; 16 CSS classes all defined). Browser-side manual verification recommended but NOT blocking — native HTML semantics provide higher static-QA confidence than benchmark-extracted JS. Defects discovered after freeze are handled as v3.4.9.1 micro-fixes.
  - BACKLOG #4 (Chip Measurement Audit) CLOSED. BACKLOG #20/#21/#22/#23 ADDED.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- **v3.4.10** ← **Snackbar Runtime Module** ✓
  - **Second Interaction module outside the Beer CSS lineage** (date-time v3.4.7 was the first). Unlike a benchmark-extraction interaction module, this release fills a runtime layer that the baseline explicitly carved out — `components.css §14` L2041 comment: *"positioning + queue management live in prototype JS. This stylesheet defines visual chrome only."* Module fits the bounded scope precisely.
  - The `components.css §14 Snackbar` baseline (5 base selectors, full state-layer Pattern A on `.snackbar__action`, 24×24 container on `.snackbar__close`, 11 total rule blocks including state-layer modifiers) remains **UNCHANGED**.
  - Four artifacts: `lab-snackbar.css` (~155 lines, 5 sections — close hit-area expansion / positioning / open/leaving states / reduced motion / live region utility), `lab-snackbar.js` (~250 lines IIFE — LiveRegion singleton + TimeoutController + SnackbarQueue + public `window.labSnackbar.{show, dismiss, dismissAll}` + forbidden-ancestor trigger check), `lab-snackbar-pattern.html` (~250 lines, 5 demo sections), `docs/SNACKBAR-RUNTIME-AUDIT.md` (~470 lines, 10 sections, 5 hard rules).
  - **Phase 0 inventory correction recorded explicitly** in audit §3 (bilingual). Static Visual QA Gate caught the mismatch during Phase 2: baseline §14 actually styles `.snackbar__label`, `.snackbar__action` (full state-layer Pattern A), `.snackbar__close` — not the 2-rule subset originally reported. Module scope narrowed from 6 → 5 CSS sections; `.snackbar__action` override removed, close hit-area expansion uses `::after` to avoid colliding with baseline `::before` state-layer.
  - **5 Hard rules** locked: (1) visible snackbar root never `aria-hidden`, (2) timeout MUST pause on hover/focus (WCAG 2.2 SC 2.2.1), (3) action/close real `<button>`, (4) `role="alert"` not default, (5) live region announces text-only.
  - **Closes the transient/feedback surface trio**: popover-as-menu (v3.4.5) + tooltip-as-description (v3.4.6) + snackbar-as-feedback (v3.4.10). Three transient surfaces complete.
  - Decisions: Single live region + visible interactive surface separated · Single-at-a-time FIFO queue (no stacking) · Configurable timeout with 5000/7000/0 defaults (web-safe) · Module CSS = runtime-only (positioning + states + motion + live region + hit-area) · Single audit doc (Interaction module pattern).
  - Static Visual QA Gate: PASS (10 user-specified checks + 6 post-correction checks; 0 actual issues; 2 earlier false positives manually confirmed safe).
  - BACKLOG #15 (Snackbar Runtime) CLOSED. BACKLOG #18 (.snackbar naming) carried forward to v3.5.0.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS.
- **v3.5.0** ← **Public Surface Reframe** ✓
  - **Policy / ontology / public-surface reframe release** — NOT an implementation release. Closes v3.4.x interaction-module cycle and opens Wave 1+ component module track.
  - Five policy documents under `docs/v3.5.0/` (~105 KB total): `33-COMPONENT-INVENTORY.md` (Phase 0A+0B, 26 KB), `MODULE-STATUS-MATRIX.md` (Phase 1A, 17 KB), `COMPONENT-COVERAGE-MAP.md` (Phase 1A, 19 KB), `PROMOTION-CRITERIA.md` (Phase 1B, 22 KB), `PUBLIC-SURFACE-CHARTER.md` (Phase 1B, 21 KB).
  - **37-entry canonical matrix** = 34 TOC component rows + 3 infrastructure provider rows. Status: 3 DONE (Chip/Snackbar/Tooltip) + 4 PARTIAL (Icon button/Search bar/Date+Time/Carousel) + 24 TODO + 3 RECORD (Avatar/Divider/Badge); 3 infrastructure DONE (popover/ v3.4.5, ripple/ v3.3.3, icon-system/).
  - **3-axis ontology** locked: TOC Group × Category × Dependency. The Dependency axis is the central new finding — surfaced by Phase 0B Menu/Popover refinement.
  - **DISTINCT but COUPLED principle** formalized (EN + KO + WAI-ARIA APG + M3 spec alignment). Infrastructure modules may be public dependencies without becoming public components; consumers depend on infrastructure runtime; infrastructure MUST NOT absorb consumer semantics.
  - **Two new categories introduced**: Baseline-only Module Record (used by Avatar/Divider/Badge — the honest "no module needed" outcome) and Plugin-territory Mapping (0 cases inside baseline scope, reserved for federation/data items).
  - **4-tier architecture** (Public / Lab / Baseline / Plugin) defined with surface meanings: style-guide.html = baseline catalog (NOT final app), components.css = primitive source (NOT runtime), lab/modules/* = validation surface (NOT public contract), bindings/ = plugin-territory mapping.
  - **Validation gates G1–G26** locked (universal + category-specific + infrastructure).
  - **Wave grouping confirmed**: Wave 1 = 9 entries (Button family + Card + Text field + Search bar + List + Carousel), Wave 2 = 14 entries (largest; navigation + form + transient), Wave 3 = 3 entries (visualization).
  - **Phase 0B reconciliation decisions captured (7 items)** — Avatar standalone / FAB+Extended merge / Date+Time merge / Menu/popover DISTINCT but COUPLED / Tabs dual-category / Slider no separate Interaction / Search bar distinct from Text field.
  - **No implementation in this release**: no rename execution, no data-theme="auto" code, no theme.json modification, no new modules, no pilot theme, no RC declaration. Phase 1B OUT items all respected.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained throughout Phase 0 → 1A → 1B.
  - BACKLOG: NO closes (this is a policy release). Schedule pointers added for #18 / #20 / #22.
- **v3.5.x mini-releases** (parallel to Wave 1+, order TBD)
  - **Naming sweep** (BACKLOG #18) — `.snackbar → .ax-snackbar` + any other prefix inconsistencies surfaced during Wave 1 authoring. Single coordinated release per CHARTER §5.2. Phase 0/1/2/3/5 structure.
  - **Theme policy** (BACKLOG #20 + #22) — `data-theme="auto"` 3-state implementation + theme-only color customization policy enforcement. Per CHARTER §6.1 + §6.2.
  - **Baseline-only Record sweep** — Avatar/Divider/Badge record-only audit docs (1-2 pages each) under `lab/modules/_records/` (path decision in Phase 2). Independent of Wave 1+ work.
- **v3.5.1** ← **Wave 1 — Button #1** ✓ DONE (2026-05-16)
  - First component module authored under v3.5.0 framework. Component Full-Spec Module. Baseline `components.css §2 Button` (L122–L234) + `§0 State-layer foundation` (L22–L79) UNCHANGED.
  - Phase 0 → 0.5 → 1 → 1.5 (C3) → 2 → 3 → 5 all closed.
  - **Deliverables**: `docs/v3.5.1/BUTTON-PHASE-0-REPORT.md` (876 lines), root context pack (CLAUDE / AGENTS / PROJECT-CONTEXT / CURRENT-STATE / NEXT-SESSION), 3-doc audit trio (`BUTTON-{SPEC,MEASUREMENT,WP-MAPPING}-AUDIT.md`, ~1450 lines total at lab/modules/button/docs/), Phase 2 plan (`BUTTON-PHASE-2-PLAN.md` v1.1, 511 lines), lab module artifacts (`lab-button.css` 174 lines + `lab-button-pattern.html` 330 lines).
  - **Phase 2 pre-entry decision SETTLED**: Option (b) — defer animated ripple to Ripple v2 release (BACKLOG #25). Consensus: User + GPT + Claude Opus. lab-button.js NOT created.
  - **5 SPEC §11 verdict criteria PASS**: M3 §4 spec coverage / token-driven implementation (100%) / Pattern HTML completeness / Audit doc completeness / Dependency declarations (3 deps × consumer-state).
  - **G1–G10 applicable gates cleared**; G11–G26 correctly N/A (Button is Consumer, not Provider/Runtime/Record/Plugin-territory).
  - **Phase 3 Visual QA**: PASS (user-verified, 10-point gate, 0 actual issues).
  - **Multi-agent orchestration validated**: User direction + GPT review + Claude Opus execution + Codex tooling cleanup all coordinated via documented context plane (CURRENT-STATE.md / NEXT-SESSION.md / phase docs).
  - **Tooling cleanup (Codex)**: `validate_theme_pilot.py` UTF-8 encoding fix + `publish_styleguide.py` script-relative ROOT + UTF-8 stdout reconfigure. Both scripts now portable across Windows + Linux.
  - **Phase 0 risks routed to BACKLOG**: #24 matrix consumer-state column / #25 Ripple v2 contract / #26 matrix row #36 allowlist correction / #27 data-ax-ripple opt-in. All scheduled for v3.5.x amendment releases.
  - **MODULE-STATUS-MATRIX row #1 (Button)**: TODO → DONE.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained throughout. Publish: 23 files in styleguide/ mirror.
- **v3.5.2** ← **Wave 1 — Icon button #2** ✓ DONE (2026-05-16)
  - Second component module authored under v3.5.0 framework. Component Full-Spec Module. Baseline `components.css §3 Icon button`, `components.css §0 State-layer foundation`, `icons.css §1 + §5`, and `style-guide.html #components-icon-button` all UNCHANGED.
  - Phase 0 → 1 plan → 1 → 2 plan → 2 → 3 → 5 closed.
  - **Deliverables**: `docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md`, `ICON-BUTTON-PHASE-1-PLAN.md`, `ICON-BUTTON-PHASE-2-PLAN.md`, 3-doc audit trio under `lab/modules/icon-button/docs/`, and lab artifacts `lab-icon-button.css` + `lab-icon-button-pattern.html`.
  - **Ontology finding**: `icon-system/` is CURRENT unconditional for Icon button (the icon is the component body), not CURRENT-conditional like Button's optional icon slot.
  - **Positive a11y finding**: SC 2.5.8 AA and SC 2.5.5 AAA both met via 48px touch target.
  - **Phase 2 pre-entry decision inherited**: Option (b) — defer animated ripple to Ripple v2. `lab-icon-button.js` NOT created.
  - **Phase 3 Visual QA**: PASS (user-verified).
  - **BACKLOG #28 added**: Icon button public specimen SVG wording cleanup.
  - **MODULE-STATUS-MATRIX row #2 (Icon button)**: PARTIAL → DONE.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained.
- **v3.5.3** ← **Wave 1 — Card #9** ✓ DONE (2026-05-16)
  - Third component module authored under v3.5.0 framework. Component Full-Spec Module. Baseline `components.css §5 Card`, `components.css §0 State-layer foundation`, `blocks.css §8 core/group card bridge`, and `style-guide.html #components-card` all UNCHANGED.
  - Phase 0 plan → Phase 0 report → Phase 1 plan → Phase 1 audit trio → Phase 2 plan → Phase 2 artifacts → Phase 3 visual QA → Phase 5 closed.
  - **Deliverables**: `docs/v3.5.3/CARD-PHASE-0-PLAN.md`, `CARD-PHASE-0-REPORT.md`, `CARD-PHASE-1-PLAN.md`, `CARD-PHASE-2-PLAN.md`, 3-doc audit trio under `lab/modules/card/docs/`, and lab artifacts `lab-card.css` + `lab-card-pattern.html`.
  - **Ontology finding**: Card is a container primitive first. Static Card uses article/section/div. Whole-card action uses native button. Whole-card navigation uses anchor. Fake clickable article/div is forbidden.
  - **WordPress bridge finding**: `blocks.css` already contains CURRENT partial `core/group` card bridge (`.wp-block-group.is-style-card-*`). Audited as current behavior, not future proposal.
  - **Ripple decision**: base Card = NONE; interactive/action Card = CANDIDATE, deferred to Ripple v2 / matrix consumer-state amendments.
  - **Phase 3 Visual QA**: PASS (user-verified).
  - **BACKLOG #29 added**: Card behavior patterns (expanding / swipe / pickup / reorder / scrolling) deferred from static primitive release.
  - **MODULE-STATUS-MATRIX row #9 (Card)**: TODO → DONE.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained.
- **v3.5.4** ← **Matrix Consumer-State Amendment** ✓ DONE (2026-05-16)
  - Small foundation-cleanup release after the first three Wave 1 cycles.
  - **Closes BACKLOG #24 + #26**: consumer-state vocabulary and row #36 `ripple/` allowlist correction.
  - `MODULE-STATUS-MATRIX.md` now has explicit v3.5.4 amendment notice, consumer-state vocabulary, state-aware ripple buckets, and updated Button/Icon button/Card DONE statuses.
  - Ripple row #36 now distinguishes CURRENT none / TARGET 7 allowlist consumers / CANDIDATE 8 inferred consumers / NONE base Card + non-interactive surfaces.
  - Button, Icon button, and Card SPEC docs gained short matrix-alignment notes. Chip v3.4.9 intentionally remains legacy audit text; matrix carries its TARGET ripple state.
  - **Not closed at v3.5.4**: #25 Ripple v2, #27 data-ax-ripple, #28 Icon button SVG cleanup, #29 Card behavior patterns.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained.
- **v3.5.5** ← **Wave 1 — FAB Family #3 + #4** ✓ DONE (2026-05-16)
  - Fourth Wave 1 component cycle and first family-merge module under the v3.5.0 framework. FAB #3 + Extended FAB #4 close together as `lab/modules/fab/`.
  - Phase 0 plan → Phase 0 report → Phase 1 plan → Phase 1 audit trio → Phase 2 plan → Phase 2 artifacts → Phase 3 visual QA → Phase 5 closed.
  - **Deliverables**: `docs/v3.5.5/FAB-PHASE-0-PLAN.md`, `FAB-PHASE-0-REPORT.md`, `FAB-PHASE-1-PLAN.md`, `FAB-PHASE-2-PLAN.md`, 3-doc audit trio under `lab/modules/fab/docs/`, and lab artifacts `lab-fab.css` + `lab-fab-pattern.html`.
  - **Family-merge finding**: FAB and Extended FAB share one module and one audit trio; Extended FAB is static label-bearing primitive in this release.
  - **Dependency profile**: state-layer CURRENT; icon-system CURRENT unconditional; ripple CANDIDATE; elevation tokens remain baseline token graph dependency.
  - **Phase 3 Visual QA**: PASS (user-verified after lab-scoped Material Symbols glyph-size bridge fix).
  - **BACKLOG #30 added**: Extended FAB behavior patterns deferred from static primitive release.
  - **MODULE-STATUS-MATRIX rows #3 + #4**: TODO → DONE.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained.
- **v3.5.6** ← **Ripple v2 Contract + `data-ax-ripple` Opt-In** ✓ DONE (2026-05-16)
  - First Interaction Runtime Infrastructure amendment cycle after Wave 1 component closures.
  - Closed BACKLOG #25 + #27 with `RIPPLE-V2-AUDIT.md`, v2 `lab-ripple.css`, v2 `lab-ripple.js`, and v2 `lab-ripple-pattern.html`.
  - Stable contract: `[data-ax-ripple]`, bounded/unbounded variants, `--md-ripple-*` bridge tokens, `window.axRipple.attach/detach/refresh`, reduced-motion path, and transitional HOST_SELECTOR compatibility.
  - Phase 3 Visual QA correction: Nav bar/Nav rail verified as bounded TARGET, not unbounded; pattern markup realigned to baseline wrappers.
  - MODULE-STATUS-MATRIX row #36 amended again: FAB family + Card action surfaces promoted from CANDIDATE to TARGET; base Card remains NONE.
  - Baseline and state/handoff files untouched; validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS.
- **v3.5.7** ← **Wave 1 — Text field #16** ✓ DONE (2026-05-16)
  - Fifth Wave 1 component cycle and first Inputs family entry under the v3.5.0 framework. First dual-category Component Full-Spec + Interaction closure.
  - Phase 0 plan → Phase 0 report → Phase 1 plan → Phase 1 audit trio → Phase 2 plan → Phase 2 artifacts → Phase 3 Playwright + user visual QA → Phase 5 closed.
  - **Deliverables**: `docs/v3.5.7/TEXT-FIELD-PHASE-0-PLAN.md`, `TEXT-FIELD-PHASE-0-REPORT.md`, `TEXT-FIELD-PHASE-1-PLAN.md`, `TEXT-FIELD-PHASE-2-PLAN.md`, 3-doc audit trio under `lab/modules/text-field/docs/`, and lab artifacts `lab-text-field.css` + `lab-text-field-pattern.html`.
  - **Dual-category discipline validated**: native/CSS state behavior does not require a fourth runtime audit. G11-G16 are handled inside SPEC/WP-MAPPING; extracted JS runtime remains the threshold for a standalone runtime audit.
  - **Scope decisions**: clear button static-only, counter static-only, native validation in scope, custom/async validation plugin territory, textarea as Text field variant, Date/Time picker owns calendar/clock behavior while Text field owns only the input shell.
  - **Phase 3 Visual QA**: PASS after three in-cycle corrections — filled leading-icon specimen added, outlined Price affix label stabilized, and Slots Amount example replaced with M3-aligned Weight/kg suffix specimen.
  - **Playwright QA**: dimension checks, label transition traces, native `:user-invalid`, composed trailing icon-button geometry, and no field-host ripple/no JS assertions used during Phase 2/3.
  - **MODULE-STATUS-MATRIX row #16 (Text field)**: TODO → DONE. Component distribution now DONE 9 / PARTIAL 4 / TODO 18 / RECORD 3.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained.
- **v3.5.8** ← **Wave 1 — Search bar #17** ✓ DONE (2026-05-17)
  - Sixth Wave 1 closure and second Inputs family entry.
  - Search bar validates the dual-category split rule opposite Text field: native/CSS interaction stays 3-doc, while extracted JS runtime requires a 4-doc audit shape.
  - **Deliverables**: `docs/v3.5.8/SEARCH-BAR-PHASE-0-PLAN.md`, `SEARCH-BAR-PHASE-0-REPORT.md`, `SEARCH-BAR-PHASE-1-PLAN.md`, `SEARCH-BAR-PHASE-2-PLAN.md`, 4-doc audit set under `lab/modules/search-bar/docs/`, and lab artifacts `lab-search-bar.css` + `lab-search-bar.js` + `lab-search-bar-pattern.html`.
  - **Runtime disposition**: `search-expansion/` remains untouched as v3.3.4 historical runtime evidence; Search bar owns the new v3.5.8 lab implementation.
  - **Dependency profile**: Search field host ripple NONE; trailing icon-button consumes Ripple v2 via own route; `popover/` remains CANDIDATE future alignment.
  - **Phase 3 Visual QA**: PASS after three in-cycle corrections — mobile overflow guard, `data-search-value` separation from Material Symbols ligature text, and native search clear pseudo-element suppression.
  - **MODULE-STATUS-MATRIX row #17 (Search bar)**: PARTIAL → DONE. Component distribution now DONE 10 / PARTIAL 3 / TODO 18 / RECORD 3.
  - Pilot validation: 1.000 / 1.000 / 1.000 / 1.000 PASS maintained.
- **v3.5.9** ← **Baseline Correction — Pill Radius Interpolation (#31)** ✓ DONE (2026-05-17)
  - Foundation cleanup release, not a Wave 1 component closure. Wave 1 remains 6/9.
  - Closed BACKLOG #31 by preserving static `--md-sys-shape-corner-full: 9999px`, adding `--md-sys-shape-corner-pill-stable: 50%`, migrating Button to `calc(var(--comp-button-height) / 2)`, and migrating Button group connected/selected morph sources to a local finite pill radius.
  - Phase 3 Playwright + user visual QA confirmed Button active morph now traces `20px -> 8px` and Button group selected morph traces `20px -> 4px` while outer connected pill corners stay `20px`.
  - Edited files: `tokens.css` + `components.css` only. `style-guide.html`, `theme.json`, `blocks.css`, `search-expansion/`, lab artifacts, and JS untouched.
  - Validator preserved: 1.000 / 1.000 / 1.000 / 1.000 PASS.

- **v3.5.10** ← **Wave 1 — Button group #6** ✓ DONE (2026-05-17)
  - Seventh Wave 1 component cycle and second Actions family entry (after Button #1 + Icon button #2 + FAB family). Component Full-Spec, 3-doc trio (no RUNTIME). Baseline `components.css §28 Button group`, `tokens.css`, `style-guide.html`, `blocks.css`, and `theme.json` all UNCHANGED — Button group inherits v3.5.9 finite pill baseline verbatim.
  - **Deliverables**: `docs/v3.5.10/BUTTON-GROUP-PHASE-0-PLAN.md`, `BUTTON-GROUP-PHASE-0-REPORT.md`, `BUTTON-GROUP-PHASE-2-PLAN.md`, 3-doc audit trio under `lab/modules/button-group/docs/`, and lab artifacts `lab-button-group.css` + `lab-button-group.js` (Pattern B aria-pressed demo only, not a public runtime extraction) + `lab-button-group-pattern.html`.
  - **Pattern A** native radio + label single-select; **Pattern B** button + aria-pressed multi-toggle. M3 web Tab/Space/Enter guidance vs native radio arrow-key semantics surfaced as compatible tensions; Pattern A intentionally does NOT polyfill native browser behavior.
  - **Ripple #36 row promotion**: Button group CANDIDATE → TARGET bounded per segment. Group container has no ripple (Phase 3 Playwright count 0).
  - **Phase 3 visual QA**: 390 / 768 / 1280 viewport overflowX 0; 56 ripple hosts (28 Pattern A + 28 Pattern B); connected morph outer 20px / inner 4px verified; Pattern A ArrowLeft/Right PASS; Pattern B Space/Enter toggle PASS; reduced motion stable; Wave 1 smoke clean.
  - **Honest findings**: M3 XS/S/M/L/XL size variants are scaffolded as is-size-* hooks but do NOT actually change segment height — Button family ships only default M (40px). Pattern HTML §6 specimen labelled "Size hooks — partial baseline". SC 2.5.8 AA PASS; SC 2.5.5 AAA honest NOT PASS for default M (40 < 44), matching Button #1 precedent. Pattern A Home/End does not change checked state in Chrome native radio (acceptable: native/no-JS lock).
  - **BACKLOG #32 added** — Button family size variants — XS/S/M/L/XL coverage cycle. Cross-cutting cycle affecting Button #1, Icon button #2, Button group #6. Shape similar to v3.5.9 pill-radius correction.
  - Matrix: 10 DONE / 18 TODO → 11 DONE / 17 TODO. Wave 1: 6/9 → 7/9.
  - Pilot validation: still 1.000 / 1.000 / 1.000 / 1.000.
- **v3.5.11** ← **Wave 1 — List #33** ✓ DONE (2026-05-17)
  - Eighth Wave 1 component cycle and first Display-family Full-Spec closure after Card. Component Full-Spec, 3-doc trio (no RUNTIME). Baseline `components.css §26 List` received a small in-cycle List-only color alignment patch; `tokens.css`, `style-guide.html`, `blocks.css`, and `theme.json` were unchanged.
  - **Deliverables**: `docs/v3.5.11/LIST-PHASE-0-PLAN.md`, `LIST-PHASE-0-REPORT.md`, `LIST-PHASE-2-PLAN.md`, `LIST-PHASE-5-PLAN.md`, 3-doc audit trio under `lab/modules/list/docs/`, and lab artifacts `lab-list.css` + `lab-list-pattern.html`.
  - **Semantics**: static informational rows, action rows, navigation rows, and selectable-row guidance are split explicitly. `button role=listitem` remains a documented baseline risk and is not canonicalized.
  - **Ripple #36 row promotion**: interactive/action/navigation List rows CANDIDATE → TARGET bounded per item. List container and static informational rows remain ripple NONE.
  - **M3 token-level color patch**: segmented container `transparent` → `surface`; direct unselected trailing icons → `on-surface`; selected/disabled direct icon overrides preserve existing state colors. No BACKLOG #33 opened.
  - **Phase 3 visual QA**: 390 / 768 / 1280 viewport overflowX 0; row heights 56/72/88; segmented gap 2px; trailing time text no-wrap; 24px icons; 56px leading image; item-only ripple hosts with container/static count 0.
  - Matrix: 11 DONE / 17 TODO → 12 DONE / 16 TODO. Wave 1: 7/9 → 8/9.
  - Pilot validation: still 1.000 / 1.000 / 1.000 / 1.000.
- **v3.5.12** ← **Wave 1 — Carousel #34** ✓ DONE (2026-05-17)
  - Ninth and final Wave 1 closure. Existing `carousel/` PARTIAL module from v3.3.2 promoted to Full-Spec with a 4-doc audit shape (`SPEC` / `MEASUREMENT` / `WP-MAPPING` / `RUNTIME-AUDIT`) because Carousel owns extracted JS runtime.
  - Phase 2 closed the two known blockers: reduced-motion handling and Home/End keyboard navigation. No-JS scroll-snap fallback remains available through the `.is-enhanced` runtime marker split.
  - `lab-carousel.css`, `lab-carousel.js`, and `lab-carousel-pattern.html` updated in place; baseline `components.css`, `tokens.css`, `style-guide.html`, `blocks.css`, and `theme.json` preserved.
  - Gallery remains DISTINCT but COUPLED, not folded into Carousel.
  - Wave 1 complete: 9 / 9. Matrix distribution: 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.
- **v3.5.13** ← **Wave 1 Closure Cleanup** ✓ DONE (2026-05-17)
  - Cleanup release, not a new component row closure. Wave 1 remains 9 / 9. Matrix remains 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.
  - **Closed BACKLOG #32**: Button family XS/S/M/L/XL size variants. `tokens.css` adds the size matrix; `components.css §2/§3/§28` maps Button, Icon button, and Button group opt-in `is-size-*` hooks. Default no-size Button remains 40px.
  - **Closed BACKLOG #33**: List full-token coverage extension. `LIST-SPEC-AUDIT.md` + `LIST-MEASUREMENT-AUDIT.md` gained full-token extension notes; `components.css §26` received narrow patches for 3px focus indicator, selected-disabled 38% on-surface mix, transparent segmented wrapper with surface item containers, Expand trailing icon container mapping, and no-wrap trailing supporting text.
  - **Records sweep**: Avatar / Divider / Badge record-only audit docs added under `lab/modules/_records/`. Rows retain RECORD status.
  - Follow-up QA: Card composition surfaced the Icon button finite-radius tail from #31; v3.5.13 removes the `9999px -> 8px` interpolation path for composed Icon buttons.
  - Pilot validation: still 1.000 / 1.000 / 1.000 / 1.000.
- **v3.5.14** ← **Publish Prep** ✓ DONE (2026-05-17)
  - Public metadata is aligned: README.md, README.ko.md, root index.html, package metadata, author string (`KIM JIWOON (designbusan.ai.kr) — Busan, Korea`), and project description.
  - License surface finalized for publish prep: root GPL-3.0 text, CC BY-SA 4.0 legalcode, multi-license LICENSE-MATRIX, NOTICE asset-path correction, and WordPress.org compatibility framing.
  - `.gitignore` now covers Node / Playwright / temp / editor / OS artifacts while validator evidence remains tracked.
  - GitHub Actions validator-only workflow added; Playwright CI remains deferred.
  - `publish_styleguide.py` regenerated the styleguide mirror (30 files / 16 module CSS files) and now references Axismundi lab source wording.
  - `docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md` defines `/templates/` as the future lab composition / page-layout preview category.
  - Directory rename, GitHub repository creation, and Pages activation remain deferred to v3.5.15.
- **v3.5.15** ← **GitHub Repository + Pages Publish** ✓ DONE (2026-05-17)
  - Local directory renamed to `C:\Users\thaum\dev\axismundi`.
  - Git initialized with root commit `e22b9e5 Initial Axismundi public release`.
  - Repository published at `https://github.com/Jiwoon-Kim/axismundi`.
  - GitHub Pages enabled from `main` branch root:
    `https://jiwoon-kim.github.io/axismundi/`.
  - Public navigation verified: root, styleguide, README, README.ko, lab overview,
    lab module index, templates note, LICENSE-MATRIX, and NOTICE return 200.
- **v3.5.16** ← **Styleguide Modernization + Module Workspace Framing** ✓ DONE (2026-05-18)
  - Amended `PUBLIC-SURFACE-CHARTER.md §3.3`: `lab/modules/*` is a module workspace + validation specimen surface; `/styleguide/` remains canonical public visual demo.
  - Added 18 styleguide actions (15 validation specimen links + 3 record audit links), with Material Symbols icon+label treatment.
  - Added mobile-first public shell guardrails to root `index.html` and the styleguide.
  - Updated `publish_styleguide.py`: copies `theme.js` and rewrites generated lab links for repository-root GitHub Pages.
  - Added validation-specimen banners to all 16 current `lab-*-pattern.html` pages.
  - Closed hygiene items #1, #10, #13, #17, #28; #11 framework portion remains resolved and UX is superseded by #34.
  - Phase 3 lesson locked: sidebar nav is the canonical source of section order; body sections follow nav order.
  - Matrix distribution unchanged: 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.
- **v3.5.17** ← **Styleguide Shell Rebuild + Mobile Reading Polish** ✓ DONE (2026-05-18)
  - Rebuilt the styleguide mobile shell as styleguide-local `.sg-*` chrome: top app bar, menu icon button, and Sheet-style side drawer without claiming App bar / Nav drawer / Sheet component completion.
  - Converted the theme switcher to icon buttons while preserving the shared `data-theme-button` contract.
  - Added mobile reading polish: compact palette chips, native read-more disclosure, and styleguide version `v0.3.0` with monorepo cycle metadata.
  - Linked `typography-axis.html` from Foundation > Typography and optimized the specimen for mobile with collapsible sticky axis controls.
  - BACKLOG #34 is partially resolved; N3 module picker/dialog UX remains deferred. BACKLOG #37 still owns full docs-site dogfooding after Wave 2 navigation closure.
  - Matrix distribution unchanged: 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.
- **v3.5.18** ← **Pre-Pilot Cleanup + Carousel Reroute** ✓ DONE (2026-05-18)
  - Small docs/spec release before v3.6.0. Carousel #34 keeps its v3.5.12 DONE closure but is amended as Pilot-excluded / plugin-routed; `lab/modules/carousel/` remains the extraction seed and BACKLOG #38 owns future plugin work.
  - Added process lessons to AGENTS.md / CLAUDE.md / `PRE-ENTRY-ONTOLOGY-GROUNDING.md`: User Request Log discipline and global portal/overlay smoke testing.
  - Verified `blocks.html` and `prose.html` as Pilot specification surfaces. Fixed a `prose.html` 390px overflow issue in-cycle; routed remaining blocks/prose shell consistency to BACKLOG #39.
  - Added `docs/v3.6.0/ONTOLOGY-THEME-PILOT-HANDOFF.md`; v3.6.0 Pilot consumes Wave 1 minus Carousel plus popover/ripple/icon-system, blocks.css, prose.css, tokens.css, and components.css.
- **v3.6.0** ← **Ontology Theme Pilot v0** ✓ DONE (2026-05-19)
  - Created `products/reference-implementations/axismundi-pilot/` as a theme-only WordPress block theme Pilot, separate from the historical `ontology-theme-pilot/`.
  - Validated scaffold, wp-env activation, asset bridge, templates, patterns, Font Library registration, and a minimum WordPress core block -> M3 reverse mapping bridge.
  - Locked the Pilot narrative as "Pilot v0 — scaffold + Wave 1 reverse mapping + block bridge MVP"; not a complete distributable theme.
  - Confirmed Carousel remains plugin territory (BACKLOG #38) and custom blocks remain out of scope.
  - Added computed-style QA and documented the reset-first reverse mapping lesson for WordPress core blocks.
- **v3.6.1** ← **Token Architecture Refactor** ✓ DONE (2026-05-20)
  - Split/refined the token layers (`md-ref`, `md-sys.light`, `md-sys.dark`, `wp-preset.bridge`, `wp-custom.bridge`, `comp`) across lab + Pilot.
  - Added Pilot dark-mode infrastructure as sys-layer remapping only.
  - Closed BACKLOG #20 and BACKLOG #42 with validator-backed Axis E/F/G locks.
  - Phase 3 visual QA passed with table-footer and core/button semantic findings routed to BACKLOG #43 / #41.
- **v3.6.2** ← **WP Core Block Specimen Wall** ✓ DONE (2026-05-20)
  - Closed BACKLOG #43 as a Tier 1 evidence collection / classification cycle, not an implementation cycle.
  - Added the WordPress-rendered specimen fixture, idempotent importer, and `npm run validate:specimen-wall` render gate.
  - Verified 11 / 11 Tier 1 block families, 26 / 26 classified entries, and 0 unclassified entries.
  - Routed reset / bridge / semantic-decision inputs to BACKLOG #41 and specimen follow-on coverage/editor compatibility to BACKLOG #44.
- **v3.6.3** ← **WP Block Bridge Expansion** ✓ DONE (2026-05-20)
  - Consumed BACKLOG #41's v3.6.2 evidence slice: 1 reset patch, 3 bridge patches, and 2 semantic decisions.
  - Reset table footer native border leakage; bridged core/search, core/code/preformatted/pre overflow, and core/separator variants.
  - Routed core/button anchors and quote/pullquote semantics without custom block implementation.
  - Added line-ending policy and corrected the #41/#44/#14 separator-to-Material-Symbols cross-link.
- **v3.6.4** ← **WP Block Bridge Residual Cleanup** ✓ DONE (2026-05-21)
  - Enforced v3.6.3 Lock 3/4 routes as mechanical cleanup, without reopening semantic decisions.
  - Cleaned up core/button link affordances: underline leakage, user-select, and state behavior checks.
  - Split core/quote and core/pullquote bridge surfaces so pullquote no longer absorbs quote blockquote styling.
  - Added light/dark visual QA plus editor canvas and drag console smoke routing.
- **v3.6.5** ← **WP Block Bridge Editor Token Parity** ✓ DONE (2026-05-21)
  - Diagnosed WordPress 7.0 editor iframe md-sys light token loss as a malformed `tokens.sys.light.css` trailing comment.
  - Repaired the lab, Pilot, and styleguide token copies in lockstep.
  - Restored editor canvas pullquote color/divider resolution while preserving front-end light/dark values.
  - Kept TT5 as future selector/schema reference only; no TT5-derived implementation.
- **v3.6.6** ← **WP Block Bridge Ripple / Editor State Parity** ✓ DONE (2026-05-21)
  - Confirmed the Pilot front-end core/button ripple bridge remains Pilot-only and does not graduate into shared Ripple v2 / WordPress binding runtime in this theme cycle.
  - Closed current editor-canvas state parity for core/button: focus-visible and disabled pass; hover, pressed, and selected are not exposed theme targets in the real editor canvas.
  - Narrowed BACKLOG #41 to a future shared WordPress ripple runtime packaging decision with five preserved sub-decisions: post-content anchors, editor-owned surfaces, forbidden ancestor policy, attach/detach lifecycle, and shared token alias location.
  - Routed editor block-validation console errors to BACKLOG #44 editor-valid fixture / editor compatibility work.
- **v3.6.7** ← **WP Specimen Follow-On Editor Compatibility** ✓ DONE (2026-05-21)
  - Implemented BACKLOG #44 Route C: preserved the original front-end specimen wall and added a separate editor-valid smoke fixture.
  - Extended the specimen importer and existing `validate:specimen-wall` gate to cover both fixture pages.
  - Confirmed editor smoke evidence at iframe 1 / console 0 / block validation 0 / invalid UI 0 / recovery UI 0 while the original wall's editor reference remains intentionally isolated at 56 / 56.
  - Narrowed BACKLOG #44 to remaining coverage follow-ons plus validator hardening polish; BACKLOG #41 shared ripple runtime packaging remains unchanged.
- **v3.6.8** ← **Wave 2A Navigation Core** ✓ DONE (2026-05-22)
  - Implemented Route B: App bar, Nav bar, Nav rail, and Tabs as lab-scoped component modules with baseline `components.css` unchanged.
  - Added Tabs local runtime for click, ArrowLeft/ArrowRight, Home/End, disabled-skip, roving `tabindex`, `aria-selected`, and panel visibility.
  - Verified 4 modules x desktop/mobile x light/dark with console 0 / overflow 0, plus live bounded ripple attachment for Nav bar, Nav rail, and Tabs.
  - Deferred Menu to Wave 2A-2 (BACKLOG #45) to preserve the Menu/popover DISTINCT but COUPLED boundary; BACKLOG #46 tracks disabled ripple host authoring hygiene.
- **v3.6.9** ← **Wave 2A-2 Menu / Popover Consumer** ✓ DONE (2026-05-22)
  - Implemented Route A: Menu as a lab-scoped consumer of the existing `popover/` and `ripple/` providers, with no `lab-menu.js`.
  - Verified 4 visual cells with console 0 / overflow 0, 3 live popover surfaces, 1 static structure specimen, forbidden `.prose` non-open, and keyboard interaction PASS.
  - Confirmed 10 enabled bounded ripple hosts, 2 disabled hosts with no ripple attribute, and interactive submenu deferred.
  - Closed BACKLOG #45 and completed Wave 2A; routed BACKLOG #47 for future popover provider menu-item-class logic extraction hygiene.
- **v3.6.10** ← **Wave 2B-1 Form Controls** ✓ DONE (2026-05-22)
  - Implemented Route B: Checkbox, Radio, and Switch as lab-scoped input modules with baseline `components.css` unchanged.
  - Verified 12 visual cells with console 0 / overflow 0, Checkbox indeterminate native transition, Radio native same-name navigation, and Switch FormData participation.
  - Accepted `window.labCheckbox = { init }` as fixture-only indeterminate re-initialization convention.
  - Promoted diagnostic-first to Lock 5 after six clean cycles spanning WP bridge, component-lab, provider-consumer, and input-control domains.
- **v3.6.11** ← **Wave 2B-2 Dialog / Sheet** ✓ DONE (2026-05-22)
  - Implemented Route A: Dialog and Sheet as lab-scoped interaction-runtime modules with baseline `components.css`, `scripts/style-guide.js`, and provider modules unchanged.
  - Verified 8 visual cells with console 0 / overflow 0, native Dialog backdrop behavior, Sheet focus containment, and scrim/focus restoration.
  - Preserved Lock 5 in its first post-promotion self-application cycle.
  - Routed Sheet drag-to-dismiss as a Wave 2B-2 follow-on note, not a new BACKLOG item; Wave 2B-3 Date+Time and Wave 2B-4 Actions remain.
- **v3.6.12** ← **Wave 2B-3 DateTime** ✓ DONE (2026-05-22)
  - Implemented Route A inside existing `modules/date-time/`, moving DateTime #22+#23 from PARTIAL to DONE.
  - Closed BACKLOG #19 Date Picker Grid Navigation A11y with CDP accessibility tree evidence (`grid: 1`, `row: 6`, `gridcell: 42`) plus Date keyboard and Time non-regression QA.
  - Preserved Lock 5 in its second post-promotion self-application cycle and first PARTIAL-to-DONE completion cycle.
  - Kept DateTime's stale/aspirational `popover/` matrix relationship as light documentation cleanup; no provider migration or BACKLOG fragmentation.
- **v3.6.13** ← **Wave 2B-4 Actions Consumers** ✓ DONE (2026-05-22)
  - Implemented Route A with `modules/fab-menu/`, `modules/split-button/`, and `modules/toolbar/`, closing FAB menu #5, Split button #7, and Toolbar #8.
  - Verified 12 visual cells with console 0 / 4xx 0 / overflow 0, `theme.js` no-load, FAB menu intentional outside-click absence, Split button primary/chevron separation, and Toolbar lab-scoped state toggles.
  - Preserved BACKLOG #46 separation by counting enabled ripple hosts separately from total controls: Toolbar has 7 icon buttons total, 6 enabled unbounded ripple hosts, and 1 disabled no-ripple host.
  - Preserved Lock 5 in its third post-promotion self-application cycle and completed Wave 2B.
- **v3.6.14** ← **Wave 3 Closure - Inputs / Feedback Final** ✓ DONE (2026-05-23)
  - Implemented Route A with `modules/slider/`, `modules/loading/`, and `modules/progress/`, closing Slider #21, Loading #30, and Progress #31.
  - Verified 12 visual cells with console 0 / 4xx 0 / overflow 0 and `theme.js` no-load across all three module pages.
  - Confirmed Slider native range labels/value sync, Loading `role=status` and reduced-motion fallback, and Progress determinate/indeterminate `role=progressbar` semantics.
  - Preserved Lock 5 in its fourth post-promotion self-application cycle and brought the component matrix to DONE 31 / PARTIAL 0 / TODO 0 / RECORD 3.
- **v3.6.15** ← **VS Code Diagnostics Sweep** ✓ DONE (2026-05-23)
  - Closed a diagnostic-only cycle after correcting scope from repo-level parser checks to user-captured VS Code Problems panel diagnostics.
  - Confirmed the v3.6.14 Wave 3 priority slice has 0 source errors in VS Code Problems; no-inline-styles warnings are shared pattern-page critical style signals.
  - Resolved v3.6.14 Docker-dependent validation debt: `build_pilot_specimen_wall`, `validate:specimen-wall`, and `validate:computed` all PASS.
  - Routed four existing lab module a11y/CSS diagnostics to v3.6.16 and counted Lock 5 fifth clean self-application as a diagnostic-only variant.
- **v3.6.16** ← **Lab A11y Diagnostics Fix Sweep** ✓ DONE (2026-05-23)
  - Closed BACKLOG #48, the v3.6.15 VS Code Problems panel follow-on.
  - Fixed DateTime CSS parser hygiene, Menu checkable item semantics, Nav Bar current destination semantics, and Ripple menuitem parent context.
  - Verified by user-side VS Code Problems panel re-sweep: the four target severity-8 diagnostics are absent; remaining warnings are routed policy/compatibility signals.
  - Preserved Lock 1/2 with `npm test` Axis A-G all 1.000 and counted Lock 5 sixth clean self-application (fifth implementation-cycle application).
- **v3.6.17** ← **WP Ripple Runtime Packaging Decision** ✓ DONE (2026-05-23)
  - Closed the remaining BACKLOG #41 shared WordPress ripple runtime packaging decision as a no-code architecture decision.
  - Recorded the layered route: Route D split CSS state-layer parity from animated JS ripple; Route C classifies shared animated WordPress ripple runtime as future plugin/custom-binding or dedicated WordPress runtime package territory; Route A was the no-code execution shape.
  - Preserved lab Ripple v2 forbidden ancestors and kept current Pilot front-end button ripple Pilot-only, not shared runtime authority.
  - Verified `php -l`, `npm test` Axis A-G all 1.000, `build_pilot_specimen_wall`, `validate:specimen-wall`, `validate:computed`, front-end no-code smoke, and `git diff --check`; validator-generated report churn was restored.
- **v3.6.18** ← **Core Block Mapping Audit** ✓ DONE (2026-05-23)
  - Closed a no-code core-block mapping audit after v3.6.2-v3.6.7 and v3.6.17 by recording a five-layer decision: Tier 1 closed/routed status, WordPress category ownership, lab catalog route, prose route, and D-layer read-only route.
  - Routed `style-guide-blocks.html` to a future category-aware lab catalog split across Text, Media, Design, Widgets, and Theme, with Embeds excluded pending explicit source/privacy/provider policy.
  - Preserved `style-guide-prose.html` as the Markdown / Custom HTML prose inheritance surface.
  - Recorded out-of-cycle asset commits `1eed48a`, `6a6d27b`, and `4bec70d` as brand-slot / placeholder-media lineage outside the mapping audit.
  - Verified `php -l`, `npm test` Axis A-G all 1.000, `build_pilot_specimen_wall`, `validate:specimen-wall`, `validate:computed`, and `git diff --check`; validator-generated report churn was restored.
- **v3.6.x — v3.7.x (NEXT)** — Post-component-closure hygiene + pilot feedback iteration. Primary next candidate should be selected plan-first from Pilot vs distributable theme bootstrap, brand asset migration follow-on, lab catalog split, Theme / FSE template work, Media catalog implementation, BACKLOG #21 Interpreter Plugin strategy, remaining BACKLOG #44 specimen coverage follow-ons, BACKLOG #46 disabled ripple host hygiene, BACKLOG #47 popover provider menu-item-class logic extraction hygiene, Sheet drag-to-dismiss follow-on, styleguide integration for Slider/Loading/Progress, VS Code workspace diagnostics config policy, Microsoft Edge Tools / webhint policy decisions, no-inline-styles policy, broad compat-api/css policy, or button-group `inline-size: fit-content` compatibility.
- **v3.7.x → v1.0 RC** — Component audit coverage complete for DONE + RECORD rows (31 DONE + 3 RECORD out of 34 TOC components); pilot theme stable; M3 Interpreter Plugin (BACKLOG #21) authoring in parallel as plugin-tier artifact.
- v3.4.x — **Promotion Criteria / Public Surface Prep** — `lab/docs/PROMOTION-CRITERIA.md` author the rules for "what gets promoted from lab to baseline styleguide" before v3.5.0 trims the styleguide. Also: publish-doc "generated artifact, not authoring surface" note, link `typography-axis.html` from Typography section. No interaction-module work. (Was tagged v3.4.8 — bumped because Date/Time and Benchmark Surface Cleanup took those slots.)
- v3.4.x — **FAB / Extended FAB / FAB menu conversion** — 35 SVGs at 56px context. Independent visual rhythm pass; conversion-shape variants A/B already documented in v3.4.4 audit. (Originally tagged v3.4.6, then v3.4.7, then v3.4.9 — now bumped to v3.4.x to make room for Date/Time extraction and benchmark cleanup.)
- v3.4.x — **Chip Measurement Audit** (BACKLOG item 4) — triggered now that real Material Symbols chip rendering exists for comparison against M3's 32dp/8dp/18dp spec. Best timing: after popover landed (popover may surface additional chip-vs-menu measurement questions).
- v3.4.x — **`components.css` future-split map** — annotation only, no split yet.
- v3.4.x — **TOC Module Scope Audit** — slot in theme, generation/parser in plugin (charter §4 textbook).
- v3.4.x — **Styleguide chrome conversion (sg-* icons)** — 21 SVGs.
- v3.4.x — **Pilot Block Theme Probe** — pilot registers zero custom blocks (charter §4). Demonstrates A/B/C/D/E composition without F dependencies. *(Sequencing note: BACKLOG #17 Text Input Corpus / Ontology Audit is best preceded by this probe — pilot surfaces actual placement contexts that inform textbox bucket decisions.)*

Pilot scope (deferred to v3.4.x):
```
products/reference-implementations/axismundi-pilot/
├── style.css
├── theme.json
├── functions.php
├── templates/
│   ├── index.html
│   └── single.html
├── parts/
│   ├── header.html
│   └── footer.html
└── patterns/
    ├── hero.html
    ├── prose-sample.html
    ├── gallery-fallback.html
    └── icon-button-search.html
```

Pilot is NOT a distributable — it's a probe for core block mapping validation. After QA stabilizes, a separate distributable theme will be constructed from lab + pilot findings.

## v3.2 — HCT plugin

First distributable plugin. Replaces WP's hex color picker with M3 HCT (Hue/Chroma/Tone) panel.

- `products/distributables/plugins/hct-color-panel/`
- WordPress block editor sidebar/inspector integration
- Demonstrates: "WP inspector GUI limit → plugin layer replacement" doctrine in practice

## v3.3 — ActivityPub ontology alpha

First federation ontology + binding.

- `core/federation/activitypub/`
- `bindings/wordpress-activitypub/`
- Note: product-level ActivityPub work (microblog feed UI, Korean federation patterns) belongs to the **product roadmap**. The ontology + binding work belongs here.

## v3.4 — Secondary design system (replaceability proof)

Demonstrate that the architecture supports interchangeable design systems by adding one alternative:

- `core/design-systems/<choice>/` (likely Fluent 2 or Carbon — TBD)
- `bindings/wordpress-<choice>/`
- Validates Constitution Article 3 (design systems are replaceable)

## v4.0 — Axismundi public release

After v3.1/v3.2/v3.3/v3.4 prove the architecture:

- `products/distributables/themes/axismundi/` promoted from RC
- Plugin suite: HCT panel + typography inspector + dynamic palette + theme switcher
- ActivityPub federation working end-to-end
- Public documentation and license finalized
- Korean + English documentation parity

---

## Principles for ordering

1. **Structure before features** — finalize layer architecture before adding new content
2. **Replaceability before features** — prove design system swap works before locking M3 deeper
3. **Binding before product** — formalize the binding layer before consuming products are built
4. **Plugin before doctrine deviation** — when WP limits are hit, write a plugin, not a workaround in `tokens.css`
5. **Mirror before edit** — publishing surfaces (Article 12) are derived; never edit them directly

These principles are why HCT plugin (v3.2) comes before more ontology content. The plugin proves the doctrine; further ontology is cheaper after that proof.
