# Axismundi Backlog

> Tracked work items that are **deliberately out of scope** for the
> current and previous releases. Each entry was flagged during a
> review or visual-QA pass, evaluated against the release-in-flight's
> scope, and either dispatched to a future release or left as a
> known-delta with rationale. Entries here are not bugs — they are
> documented decisions to defer.
>
> When an item is ready to be addressed, it gets a `target: vX.Y.Z`
> field; until then, the field is `target: TBD`.

## How items get on this list

1. A visual QA pass, review session, or external consultation surfaces
   an issue or improvement opportunity.
2. The issue is evaluated against the release-in-flight's scope
   (per the release's CHANGELOG entry and ROADMAP target).
3. If the issue would expand scope past the release's commitment, it
   is added here with a `source` field naming where it was discovered.
4. The CHANGELOG of the release notes that the issue was found and
   deferred, with a link to this file.

This ROUTING prevents scope creep on releases-in-flight without
losing the observations.

## Open items

### 1. Inline code font-size inheritance in helper text

- **Bucket**: D (theme baseline typography)
- **Status**: **Resolved at v3.5.16.** `style-guide.html` now includes `.sg-helper code, .t-body-small code { font-size: inherit; }` as part of the styleguide modernization side-fix lane.
- **Source**: v3.4.3 visual QA — found while reviewing Material Symbols conversion in `style-guide.html`.
- **Issue**: `.sg-helper` is `font-size: 12px` (t-body-small range) but inline `<code>` defaults to 14px, creating a visible size jump inside helper sentences. Pattern repeats throughout the styleguide.
- **Likely fix**: `.sg-helper code, .t-body-small code { font-size: inherit; }` (and possibly extend to all small-typography variants).
- **Target**: ~~TBD — candidate for `v3.4.4 Styleguide Typography QA` or as a side-fix in any future styleguide-touching release~~ — **RESOLVED at v3.5.16.**
- **Scope risk if forced into v3.4.3.1**: small CSS change, but would expand the patch's stated scope (icon-button visual QA fixes) — better held separately.

### 2. Avatar size token consistency across components

- **Bucket**: B / D (token system + component composition)
- **Source**: v3.4.3 visual QA — sections `#components-avatar` and `#components-search-bar` reference different avatar sizes.
- **Issue**: `#components-avatar` shows `is-size-sm`, default, `is-size-lg`. `#components-search-bar` references `is-size-xs`. The full size scale (xs / sm / default / lg / possibly xl) is not laid out together for visual comparison, and the relationship to icon sizes (24px chrome, 18px chip, 16px small-target) is undocumented.
- **Likely fix**: A short audit producing a single avatar/icon/chip size token map, plus visual comparison row in `#components-avatar`.
- **Target**: `v3.4.x — Avatar / Icon / Chip Size Token Alignment` (separate release).
- **Note**: Related to Chip Measurement Audit (item 4) — both touch size-token consistency. May merge into one release if scheduled together.

### 3. Floating toolbar — "is-selected" color should align with primary palette

- **Bucket**: B / D (component variant + state token)
- **Source**: v3.4.3 visual QA — `Components → App bar → Large-flexible → Floating, vibrant color` section.
- **Issue**: `is-selected` state uses a color that does not feel aligned with the M3 primary palette for the component. User suggested it should be a primary-family tone to match the M3 reference.
- **Likely fix**: Review the `is-selected` selector for the component and adjust its color token from whatever neutral it currently is to a primary-family token (e.g. `primary-container` → `on-primary-container`).
- **Target**: TBD — likely `v3.4.x Component State Token Audit`.

### 5. WordPress logo styleguide specimen

- **Bucket**: F (reference specimen) — NOT a theme icon primitive
- **Source**: v3.4.3 visual QA — user asked whether WordPress logo SVG could appear in the styleguide as a reference. GPT consultation concluded: yes, as a clearly-labeled `reference specimen` (NOT as a theme-shipped icon).
- **Issue**: The SVG track (per `SVG-ICON-POLICY.md`) handles WordPress / social / brand / portable content. There is currently no styleguide demonstration of the SVG track — the icon-system-pattern.html mentions it but does not show a recognized brand glyph.
- **Likely fix**: Add a `figure.sg-brand-specimen` block to `style-guide.html` (or to `icon-system-pattern.html`) embedding the WordPress wmark SVG with: trademark notice, link to https://wordpress.org/about/logos/, explicit "reference only — not a theme primitive" caption. Sourced fresh from `compare/brand-assets-research/WordPress-logotype-wmark.svg` (which itself was fetched from the official source).
- **Target**: ~~`v3.4.4` candidate~~ — **RESOLVED at v3.4.4.** Embedded in `lab/modules/icon-system/icon-system-pattern.html §SVG icons` as a currentColor-normalized inline figure with trademark caption + source link. Seed file at `compare/brand-assets-research/WordPress-logotype-wmark.svg` unchanged. Caption phrasing deliberately scoped: "official-source styleguide-only specimen, manageable when scoped per these rules" — NOT "trademark policy compliant".
- **Constraints**: No other brand logos enter the styleguide as a pack. WordPress is permitted because the project is a WordPress theme; other brands (Mastodon, etc.) come via plugin or user-provided assets per `SVG-ICON-POLICY.md`.

### 6. Monotone SVG theming plugin concept

- **Bucket**: F (plugin)
- **Source**: v3.4.3 visual QA discussion — user observation: "monotone SVG icon이 theme palette에 맞춰 자동전환 plugin은 메리트 있겠음". GPT consultation refined: this is a credible plugin concept — `Monotone SVG Theming Engine` / `Token-bound SVG Icon System`.
- **Scope**: A plugin (`axismundi-svg-icons` or similar) that:
  - Accepts SVG upload / paste with sanitization
  - Auto-converts simple monochrome SVGs to `fill="currentColor"`
  - Binds the resulting glyph to a theme token via sidebar inspector
  - Toggles between "official brand color" and "monotone adaptive" modes
  - Provides an icon-registry block toolbar item
  - Pairs with Material Symbols picker for complete dual-engine UX
- **Performance trade-off documented**: inline SVG is most flexible for currentColor / token binding, but increases DOM size; for repeated UI glyphs, sprite + `<use>` or CSS mask is preferred. The plugin should be an adaptive renderer that picks the right strategy per icon usage.
- **Target**: `axismundi-icons` plugin extraction (post-pilot, post-v3.4.x).
- **Note**: This is a multi-release vision, not a single backlog item. Listed here to preserve the architectural decision that the project agrees monotone SVG theming is a credible plugin direction; specific work items will be created when the plugin extraction starts.

### 7. Search bar leading icon — known delta from v3.4.3

- **Bucket**: D (theme interaction, deferred conversion)
- **Source**: v3.4.3 visual QA — user noted that `search-bar__leading-icon` remains inline SVG while `ax-icon-button` was converted to Material Symbols in the same page.
- **Status**: **Known delta, not a bug.** v3.4.3 was scoped to `ax-icon-button` only (40 instances) per `INLINE-SVG-INVENTORY.md §Conversion ordering`. The single search-bar leading icon is queued for v3.4.4 alongside chip (4) and button leading icons (10).
- **Likely fix**: v3.4.4 conversion pass, same shape as v3.4.3.
- **Target**: ~~`v3.4.4`~~ — **RESOLVED at v3.4.4.** `search-bar__leading-icon` SVG replaced with Material Symbols `search` glyph in `style-guide.html` L2563. Conversion-shape variant B (slot class lives on the wrapping span; only the inner SVG is replaced; the wrapping span keeps its layout role) — documented in `ICON-MIGRATION-PASS-2-AUDIT.md §Conversion shape`.

### 8. Module pattern HTML `role="group"` → `role="radiogroup"` (cohort fix)

- **Bucket**: D (theme runtime — accessibility role lookup)
- **Source**: v3.4.3 visual QA — `icon-system-pattern.html` theme switcher did not toggle. Root cause: `theme.js` (`L357 const groups = document.querySelectorAll('[role="radiogroup"]');`) finds only `[role="radiogroup"]`, but all four module pattern HTMLs used `role="group"` on the sg-theme container.
- **Fixed in v3.4.3.1**: All 4 module pattern HTMLs (carousel, ripple, search-expansion, icon-system) updated to `role="radiogroup"` in this patch. The fix was originally only required for icon-system, but applying it across the cohort prevents the same typo from re-surfacing during visual QA on the other modules. This is *consistency-maintenance*, not scope creep — same typo, same fix, four files.
- **Status**: **Resolved at v3.4.3.1.** Listed here for traceability.

### 10. Lab ripple module — runtime verification in pattern page

- **Bucket**: D (lab module runtime, scoped to `lab-ripple-pattern.html` only)
- **Status**: **Resolved at v3.5.6.** Ripple v2 rewrote the runtime contract and pattern page with `data-ax-ripple`, bounded/unbounded variants, reduced-motion behavior, and Playwright-backed QA. The original v3.4.4.1 visual-verification concern is absorbed by the v3.5.6 Ripple v2 cycle.
- **Source**: v3.4.4.1 visual QA follow-up. User noticed ripple effect not visible in module pattern pages. Initial concern was that ripple was never wired anywhere; on reflection user confirmed ripple is intentionally a lab module (not promoted to baseline theme interaction).
- **Likely cause**: Visual confusion compounded by what was then item 9 — when the theme palette did not toggle, state-layer color tokens also appeared unchanged, making any state-layer / ripple effect harder to perceive. Verification under correct palette toggling was needed before declaring ripple itself broken.
- **Status update at v3.4.5**: item 9 is resolved (cohort-fix bundled with v3.4.5). The verification path is now unblocked.
- **Action when triggered**:
  1. ~~Resolve item 9 first.~~ ✓ Done at v3.4.5.
  2. Re-verify ripple in `lab-ripple-pattern.html` (the only place it should run): hover + press on `ax-button` / `ax-icon-button` / `ax-chip` demo instances under both light and dark themes.
  3. If ripple still does not fire, inspect `lab-ripple.js` event binding scope vs. `.prose` ancestor bail-out.
- **NOT in scope**: promoting ripple to baseline theme interaction (`style-guide.html` global). That is a separate phase under Charter §1 (theme interaction layer) and intentionally not part of this BACKLOG item.
- **Target**: ~~post-v3.4.5 visual-QA pass~~ — **RESOLVED at v3.5.6.**

### 13. `publish_styleguide.py` does not copy `theme.js` to publish surface

- **Bucket**: D / build pipeline
- **Status**: **Resolved at v3.5.16.** Option A adopted: `publish_styleguide.py` copies `theme.js` to `styleguide/scripts/theme.js` when present. This removes the publish-surface 404 while preserving `/styleguide/` as a generated mirror.
- **Source**: v3.4.5.1 incidental discovery. After patching `theme.js syncSwitchers` selector, verification revealed that `styleguide/scripts/` only contains `style-guide.js`. `theme.js` is referenced from `styleguide/index.html` (and `blocks.html`, `prose.html`) but is not present, producing a 404 on the published mirror.
- **Why it has been invisible**: `style-guide.js` carries its own theme handler with self-sync, which covers the styleguide UX. The 404 surfaces only in the browser console; functionally the styleguide theme switcher works because `style-guide.js`'s handler runs first and theme.js fails silently when not present. Module pattern pages are deliberately NOT published (per `modules/README.md` policy), so the theme.js requirement on the publish surface was never enforced.
- **Scope decision pending**:
  - **Option A** — copy `theme.js` to publish (small `publish_styleguide.py` patch): aligns publish mirror with lab, eliminates 404s, makes module-pattern-style theme contracts work if a module pattern is ever individually shared from the publish surface.
  - **Option B** — remove the `<script src="scripts/theme.js">` reference from `style-guide.html` / `blocks.html` / `prose.html` since `style-guide.js` is the actual handler there: tightens the publish contract, makes the styleguide explicit about which JS it depends on, but means `theme.js` is genuinely lab-only.
  - **Option C** — leave as-is and document: the 404 is harmless given current architecture. Document the asymmetry in `tools/generators/publish_styleguide.py` and accept that module pattern pages are local-only.
- **Decision criteria**: tied to BACKLOG #11 (Public Surface Reframe). Option choice should happen alongside v3.5.0's broader publish-policy decisions, not as a one-off patch.
- **Target**: ~~v3.4.x or v3.5.0 depending on whether #11 is in progress when this is addressed~~ — **RESOLVED at v3.5.16.**
- **Constraints**: this BACKLOG entry is recorded for traceability; the issue does not break currently-shipped behavior.

### 14. Material Symbols ligature layout shift on first load

- **Bucket**: D — Theme interaction / icon runtime
- **Status**: Deferred
- **Target**: v3.4.x Icon Runtime Polish, or rolled into v3.5.0 Public Surface Reframe if the publish/icon-runtime contract is being touched together
- **Source**: v3.4.5.1 Visual QA Gate

**Symptom**: On reload, controls using Material Symbols ligatures (theme toggle buttons, anchored menu trigger, split-button chevron, dialog/tab icons, etc.) briefly expand before contracting to icon-glyph width once the Material Symbols Rounded font finishes loading. This is FOUT/FOIT-class icon ligature layout shift, not a functional bug — once the font is cached the shift disappears.

**Root cause**: Fallback text content such as `dark_mode`, `light_mode`, `arrow_drop_down`, `more_vert` occupies inline layout space in the fallback font (typically a generic sans-serif metric) for a few hundred milliseconds before the ligature substitution resolves. Without a fixed inline-size constraint on `.material-symbols-rounded`, those characters dictate width during the FOUT window.

**Potential fix** (do NOT apply without full visual QA):

```css
.material-symbols-rounded {
  display: inline-block;
  inline-size: 1em;
  block-size: 1em;
  line-height: 1;
  overflow: hidden;
  white-space: nowrap;
  flex: 0 0 auto;
}
```

Key choice: `inline-size: 1em` (not a fixed `24px`) so per-size icon rhythm is preserved:

```
font-size: 18px  → inline-size 18px
font-size: 24px  → inline-size 24px
font-size: 40px  → inline-size 40px  (FAB context)
font-size: 48px  → inline-size 48px  (large surfaces)
```

**Why this needs a dedicated visual-QA pass**: `.material-symbols-rounded` is a global selector. The rule above changes the box contract for *every* Material Symbols surface shipped so far — icon buttons (40 v3.4.3) + ax-button-icon (5 v3.4.4) + chip (4 v3.4.4) + search-bar (1 v3.4.4) + nav-bar (4 v3.4.4) + nav-rail (9 v3.4.4) + tabs (3 v3.4.4.1) + dialog (2 v3.4.4.1) + theme switcher chips (3 × 5 module patterns) + popover trigger chevrons (2 v3.4.5) + popover menu-item leadings (5+ v3.4.5) = 70+ touchpoints. Future FAB conversion (35 SVGs at 56px context) and remaining `sg-*` chrome (~21 SVGs) will compound this.

**Constraints when patching**:
- Per-size rhythm must hold (18/20/24/40/48 contexts).
- Variable-font axes (`FILL`, `wght`, `GRAD`, `opsz`) must not be regressed — Charter §v3.2.1 `--md-grade` theme-scope sync must continue to apply.
- Material Symbols Rounded family is the only Material Symbols font shipped with the theme (per `ICON-FONT-POLICY.md`); Outlined/Sharp arrive via opt-in plugin — the box rule must not assume Rounded-only metrics.
- `font-feature-settings: 'liga'` and the `notranslate` / `translate="no"` attributes already on every span must be preserved.

**Not in scope** of this entry:
- Changing icon sizes or per-context rhythm.
- Switching from ligature-based Material Symbols to glyph-codepoint Material Symbols (a separate runtime decision).
- Preloading the icon font earlier in the document (`<link rel="preload">`) — that is a different mitigation that does not need the global box contract change but adds bytes to first paint.

**Decision criteria**: visual-only flash on first load, no functional impact, no broken contract. Patch when (a) global icon box contract is up for refactor anyway, or (b) accumulated user reports make the flash a priority.

### 11. Public surface reframe — styleguide ⇄ module lab UX

- **Bucket**: E (documentation UX) — first BACKLOG item in Bucket E so far
- **Status**: **Resolved / superseded.** The framework portion landed in v3.5.0 Public Surface Reframe. Typography-axis adjunct linking and concrete styleguide-to-module navigation landed across v3.5.16 and v3.5.17. Any remaining module picker/dialog UX belongs to BACKLOG #34, not this original framing item.
- **Target**: v3.5.0 candidate (not v3.4.x). v3.4.x is internal-structure stabilization; v3.5.0 is public-surface reframe.
- **Source**: v3.4.5 mid-discussion. After v3.4.0–v3.4.4.1 the boundary between baseline (styleguide) and extension (lab modules) has crystallized: the styleguide became an uncontaminated baseline catalog, lab modules became extension/interaction labs with their own pattern pages, and the icon system introduced a separate ship-track for chrome glyphs and brand specimens. The current public surface (GitHub Pages entry, mirror layout) still reflects the older "styleguide = mirror of everything" assumption and needs reframing to match the new structure.

**User-stated vision**:

```
스타일가이드에는 승격된 컴포넌트 모듈의 결과만을 보여주고,
설명은 lab 모듈 페이지로 링크.
승격되지 않은 모듈은 실험실 아이콘 팝업 메뉴로 리스트를 보여주면 될 것 같음.
```

Translated / expanded:

- Styleguide = baseline catalog showing only **promoted** module results (post-promotion baseline form). No rationale, no a11y narrative, no Beer-CSS-intake details on the baseline page itself.
- Each component links out to its lab module page where the audit, rationale, intake notes, and visual QA narrative live.
- Modules that are not promoted to baseline appear via a **lab/flask icon** in the styleguide chrome that opens a popup menu listing them — discoverable but not visually mixed into the baseline catalog.

**Principles (consolidating user vision + GPT consultation)**:

1. **Styleguide is the baseline catalog**, not a mirror of every experiment. Once a module is promoted, its visual primitive appears in the styleguide and a "See module" link sits next to it. Unpromoted modules are accessed from a dedicated lab-flask popup, not inline.
2. **Lab modules are extension labs**. They retain their own pattern page (rationale + a11y narrative + visual QA + audit doc + 5-criterion verdict). Detailed prose moves from baseline to module pages.
3. **The public `styleguide/` mirror is a generated artifact, not an authoring surface.** Canonical source is `products/reference-implementations/axismundi-lab/`. This already matches `publish_styleguide.py` behavior — the policy just becomes explicit in docs.
4. **`typography-axis.html` is a typography adjunct, not a module.** It belongs as a sub-link from the Typography section ("Open typography axis specimen"), not as a separate module entry and not as a top-level GitHub Pages entry.

**Proposed GitHub Pages entry layout (sketch — to be designed at v3.5.0)**:

```
GitHub Pages entry
├─ Baseline Styleguide              ← promoted module results, no narrative
│  ├─ Tokens
│  ├─ Typography  (└─ link: typography-axis specimen)
│  ├─ Components
│  ├─ Blocks
│  └─ Prose
├─ Module Lab                       ← reachable also via the lab-flask popup
│  ├─ Carousel
│  ├─ Ripple
│  ├─ Search Expansion
│  ├─ Icon System
│  └─ Popover  (and any v3.4.x+ additions)
├─ Experimental / Reference
│  ├─ benchmark-interactions (frozen Beer CSS lineage snapshot)
│  └─ compare/ references (frozen, never authority — see Charter)
└─ Docs
   ├─ Architecture Boundaries
   ├─ Beer CSS Intake
   ├─ Icon System Audit
   ├─ Roadmap
   └─ Backlog
```

**Concrete change set (high-level, to be detailed at v3.5.0)**:

- New GitHub Pages entry HTML (currently no entry — styleguide IS the entry).
- Add lab-flask icon component to the styleguide chrome with popup menu listing unpromoted modules. *Note*: this depends on v3.4.5 popover landing; cannot start before that.
- Move detailed rationale prose out of `style-guide.html` and into module pages where it doesn't already live there.
- Add publish doc line: *"The public styleguide mirror is a generated artifact, not an authoring surface. Canonical edits belong in axismundi-lab."*
- Link `typography-axis.html` from the Typography section.

**Constraints**:

- Promotion criteria for "promoted module result vs. lab-only" must be written down before any styleguide trimming happens — otherwise the styleguide becomes inconsistent. Likely sits in a new Charter clause or in `lab/docs/PROMOTION-CRITERIA.md`.
- Mirror generation must not break (`publish_styleguide.py` continues to work; output paths remain stable for any existing GitHub Pages links).
- The "lab-flask popup" UX depends on v3.4.5 popover being available — sequencing constraint.

**NOT in scope** of this BACKLOG item: deciding *which* current lab modules count as "promoted" vs "lab-only". That call happens at v3.5.0 entry, informed by where each module stands at that point.

### 18. Snackbar class naming inconsistency

- **Bucket**: E — Documentation / naming convention housekeeping
- **Status**: Deferred
- **Target**: v3.5.0 Public Surface Reframe (BACKLOG #11) — natural fit for that phase's broader baseline naming convention review
- **Source**: v3.4.7 Phase 0 inventory. While verifying snackbar's actual scaffolding, discovered that snackbar uses bare BEM (`.snackbar`, `.snackbar__label`, `.snackbar__action`, `.snackbar__close`, `.snackbar--two-line`) — no `ax-` prefix — while every other component uses the `ax-` prefix (`.ax-button`, `.ax-icon-button`, `.ax-tooltip`, `.ax-date-picker`, `.ax-time-picker`, `.ax-menu`, `.ax-card`, `.ax-app-bar`, etc.).

**Why this matters**:

1. **Public surface predictability**: Once Axismundi is published, third-party themes / plugins may shadow / extend `.snackbar` (a very common BEM root used by many other design systems). The `ax-` prefix exists precisely to avoid such collisions.
2. **Documentation consistency**: When documenting "all baseline components use the `ax-` prefix", snackbar is the lone exception — generating ongoing friction in docs, tutorials, and search/replace operations.
3. **Federation / WordPress integration**: A theme installing into a WordPress site already loaded with another plugin's `.snackbar` styles risks visual collision. The `ax-` prefix isolates Axismundi.

**Why this is NOT urgent**:

- The current snackbar is baseline-only with no third-party consumers (yet). Renaming after a public release would be a breaking change.
- The renaming touches: `components.css` L2042–L2156 (primitive), `style-guide.html#components-snackbar` (4 specimens at L2941–L2973), `style-guide-benchmark.html` (snackbar section, line range to be re-checked at execution time), any future `lab/modules/snackbar/` files. Mechanical but cross-file.
- Best timing: paired with **v3.5.0 Public Surface Reframe** (BACKLOG #11), which already plans to formalize the baseline-vs-lab boundary. Naming conventions belong in the same conversation.

**Scope when triggered**:

```
1. Rename .snackbar*  →  .ax-snackbar*  in components.css §14
2. Update style-guide.html#components-snackbar markup + code snippet
3. Update style-guide-benchmark.html snackbar section (if still present after v3.4.8)
4. Update any lab-snackbar.* references (depends on BACKLOG #15 having landed first or not)
5. Verify no internal docs or tutorials reference the bare .snackbar selector
```

**NOT in scope**:

- Reviewing every other component naming for consistency (separate question, larger).
- Changing BEM convention (we keep BEM; we just add the `ax-` prefix to the root).

**Sequencing**:

- Best executed alongside v3.5.0 Public Surface Reframe.
- If BACKLOG #15 (Snackbar Runtime Module) lands first, the runtime module should be authored against the `.snackbar` name as it currently exists, and renamed at v3.5.0. This avoids two passes of the lab module.
- Alternative: rename can happen before v3.5.0 as a "v3.4.x naming convention hardening" patch, if surface stability becomes a concern earlier.

- **v3.5.0 schedule pointer (Phase 1B charter, 2026-05-15)**: Recorded in `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §5.2`. Execution scheduled for a v3.5.x mini-release ("naming sweep") with its own Phase 0 → 5 cycle. NOT closed until that mini-release lands.

### 16. Tooltip delay and touch long-press refinement

- **Bucket**: D/E — Theme interaction / lab module refinement
- **Status**: Deferred
- **Target**: v3.4.x Tooltip Interaction Polish (post-v3.4.6, before v3.5.0)
- **Source**: v3.4.6 Tooltip Module Extraction Phase 0 audit. M3 spec recommends ~500ms hover delay for plain tooltips and long-press triggering on touch devices. The benchmark `enableTooltips()` does neither — show is immediate on `pointerover` / `focusin`, and touch devices inherit pointerover's sticky behavior.

**Rationale for deferral**:

```
v3.4.6 goal
  = extract Beer-CSS-derived tooltip into an Axismundi-native lab module
  = minimal accessible hover/focus runtime

v3.4.x Tooltip Interaction Polish goal
  = refinements that depend on running the v3.4.6 extraction first
```

v3.4.6 intentionally implements only the minimal accessible hover/focus runtime. Mixing refinements into the extraction phase risks scope creep and obscures the extraction's audit trail.

**Deferred refinements**:

- **Hover show delay** — ~500ms `setTimeout` before show; cleared if pointer leaves before delay elapses. M3 spec value, but exact tuning under reduced-motion / a11y settings is open.
- **Touch long-press trigger** — Android pattern; touch device should require long-press to show, not immediate `pointerover` (which fires on every tap).
- **Rich tooltip timing** — rich variant is dismissible and may need its own show/hide model independent of plain (e.g., manual close button + auto-dismiss timeout combination).
- **Mobile dismissal behavior** — outside-tap dismiss on touch surfaces, different from desktop's mouseleave.
- **Pointer coarse/fine distinction** — `@media (pointer: coarse)` vs `(pointer: fine)` switching between long-press and hover trigger automatically.
- **Reduced-motion show delay adjustment** — `(prefers-reduced-motion: reduce)` may want zero delay (since motion is already collapsed) or, conversely, longer delay (less twitchy).

**NOT a v3.4.6 blocker**: v3.4.6 extraction does not need any of these refinements to satisfy its core a11y contract (`aria-describedby`, hover/focus show, mouseleave/blur hide, Escape for rich, forbidden-ancestor bail-out).

**Sequencing**:

- This entry activates only after `lab-tooltip` runtime stabilizes through v3.4.6 visual QA.
- Touch long-press has UA / device fragmentation concerns — likely needs a dedicated test pass.
- May get bundled with v3.4.x Icon Runtime Polish (BACKLOG #14) if both end up under the same release.

### 17. Text Input Corpus / Ontology Audit

- **Bucket**: E/F — Lab module / plugin candidate (deliberately bucket-ambiguous — that ambiguity is the reason this entry exists)
- **Status**: **Resolved by v3.5.7 Text field + v3.5.8 Search bar.** The text-input corpus split into Text field (native form-control shell, textarea, validation states, WP form boundary) and Search bar (distinct search affordance + extracted runtime audit). Remaining form/editor surfaces should be opened as new targeted items rather than keeping this broad corpus audit open.
- **Target**: v3.4.x or post-pilot gap audit (likely paired with or following Pilot Block Theme Probe)
- **Source**: v3.4.6 / v3.4.7 mid-discussion. After tooltip extraction closed the Beer-CSS interaction-module family, attention turned to text-input components — and immediately surfaced that they don't cleanly map to a single WordPress core block, editor component, or HTML form control.

**The mapping ambiguity**:

```
M3 Text Field visual spec
   ≠ WordPress frontend block
   ≠ WordPress editor component (TextControl / InputControl / TextareaControl)
   ≠ native HTML form control
```

Four overlapping but non-identical reference points. A "textbox module" cut from any one of them without first mapping the corpus risks shipping something that fits the M3 spec but breaks WordPress integration, or fits a WordPress editor control but doesn't represent the frontend authoring surface.

**Connected surfaces** (all currently touched by some flavor of "text input"):

```
Text field (M3)            comment form
InputControl (Gutenberg)   login form
TextControl                settings field
TextareaControl            post meta field
Search bar                 block sidebar control
Select                     ActivityPub note composer (future)
Combobox
```

These are not separable trivially — a Search bar overlaps with `<input type="search">` and with the search lab module already in `lab/modules/search-expansion/`. A Combobox overlaps with the popover lab module's anchored-listbox pattern.

**Why not bypass the audit and build the module directly**:

> *"이 input은 post content 안에 들어가는가? theme chrome인가? comment form인가? search form인가? editor sidebar control인가? plugin setting field인가?"*

These questions cannot be answered from the static styleguide alone. They surface only when the components are placed into a real WordPress block theme. Therefore: **build the pilot probe first, then audit, then decide module shape**. Skipping straight to "lab-textbox" module would likely require redesign once the pilot surfaces actual usage contexts.

**Scope when triggered**:

1. M3 Text Field spec inventory (filled, outlined, with-leading, with-trailing, error, disabled, focused, supporting-text, character-counter, prefix, suffix).
2. WordPress `TextControl` / `InputControl` / `TextareaControl` mapping — which Axismundi tokens / states / variants align to which WordPress component prop.
3. Boundary between frontend form input (post content / comment form / search form) and editor sidebar control (block sidebar / settings / inspector controls).
4. Overlap with existing lab modules — particularly `search-expansion` (Search bar already extracted) and `popover` (Combobox / Select would need anchored listbox).
5. Decision tree for: baseline visual specimen vs. lab module vs. plugin territory.
6. Audit doc records the corpus + gaps discovered during the pilot probe, not implementation.

**Provisional classification** (revisable at audit time):

```
Baseline styleguide:
- text field visual specimen (filled / outlined)
- textarea visual specimen
- search field visual specimen
- disabled / error / focus state primitives

Lab module candidates:
- floating label runtime behavior
- prefix / suffix / counter
- validation message lifecycle
- combobox-like behavior (overlaps with popover module)
- password visibility toggle
- textarea autosize
- input + helper / error linked-text lifecycle

Plugin territory:
- block editor sidebar controls
- custom form block
- post meta binding
- user settings forms
- ActivityPub composer
```

**NOT in scope of this BACKLOG item**:

- Implementing any of the lab module candidates listed above. This entry covers audit / classification only.
- Touching the existing `.text-field` primitive in `components.css` (it stays in baseline as visual specimen at minimum).
- Deciding the WordPress editor integration story (separate plugin phase).
- ActivityPub composer surface (federation-side decision, not theme-side).

**Sequencing dependency**:

This audit is best preceded by the Pilot Block Theme Probe (already on ROADMAP as `v3.4.x`). The pilot surfaces actual placement contexts (header chrome vs. post-content vs. comment form vs. editor sidebar), which directly informs the audit's bucket decisions.

If the pilot probe is delayed, the audit can still run on the static styleguide corpus alone — but its decisions will need a follow-up pass after pilot exists.

### 19. Date Picker Grid Navigation A11y

- **Bucket**: D/E — Theme interaction / lab module a11y refinement
- **Status**: Deferred
- **Target**: v3.4.x Date/Time A11y Pass (post-v3.4.7 extraction, before v3.5.0 if feasible)
- **Source**: v3.4.7 Date/Time Picker Interaction Extraction Phase 0 inventory. The GPT Codex-generated benchmark date picker interaction lacks the WAI-ARIA calendar grid navigation pattern. v3.4.7 explicitly carries this gap over rather than fixing it in-flight, because a complete WAI-ARIA Date Picker pattern is its own design decision and would balloon the extraction's scope.

**The carry-over policy in v3.4.7**:

```
v3.4.7 = benchmark interaction layer를 date-time module로 추출
       = 기존 a11y 수준을 정확히 기록
       = critical gap은 숨기지 않고 BACKLOG #19로 라우팅

v3.4.x later = Date Picker Grid Navigation a11y pass
```

**Missing a11y patterns** (date picker only — time picker has its own partial patterns):

The benchmark date interaction code (`benchmark-interactions.js` L921–L1283) has only `role` 1× / `aria-selected` 1× / `aria-pressed` 1× / `aria-label` 1× / `tabindex` 1× / `event.key` 3× / `"Escape"` 1×. None of the WAI-ARIA Date Picker grid pattern is wired:

```
ARIA structure:
  role="grid" on the calendar table
  role="row" on each week row
  role="gridcell" on each day cell
  aria-current="date" on today's cell
  aria-selected on the active selection
  aria-readonly / aria-multiselectable as appropriate
  aria-labelledby pointing to the month/year nav label

Keyboard navigation:
  ArrowLeft / ArrowRight  → previous/next day
  ArrowUp / ArrowDown     → previous/next week
  Home / End              → start/end of week
  PageUp / PageDown       → previous/next month (Shift +PageUp/Down = year)
  Enter / Space           → select date
  Escape                  → close picker

Roving tabindex:
  Only one cell has tabindex="0" at a time (the focused date)
  All other cells have tabindex="-1"
  Focus follows arrow-key navigation

Announcements:
  Month/year changes announced when ArrowKey crosses month boundary
  aria-live="polite" region for nav announcements
```

**Why this is a separate phase, not v3.4.7 scope**:

1. **Scope honesty** — v3.4.7 is already 684 lines JS + 428 lines CSS extraction. Adding the full WAI-ARIA pattern would double the audit doc and require new design decisions (focus management on month boundary crossings, range-selection a11y interaction with single-date a11y, etc.).
2. **Carry-over policy precedent** — v3.4.6 tooltip *did* fix the missing `aria-describedby` because it was a single defensive setAttribute / removeAttribute pair. The date picker grid pattern is qualitatively different — it's an ongoing design conversation, not a missing one-line attribute.
3. **Audit doc transparency** — DATE-TIME-AUDIT.md records the a11y gap explicitly in its 5-criterion verdict as a known limitation: *"PASS as an interaction extraction module, with critical inherited a11y gaps deferred."*

**Scope when triggered**:

- `lab-date-time.js` — wire ArrowKey navigation, roving tabindex, Home/End/PageUp/PageDown, aria-live announcements
- `lab-date-time.css` — `:focus-visible` ring on gridcell (currently relies on browser default)
- `lab-date-time-pattern.html` — add keyboard-navigation demo section with instructions
- `DATE-TIME-AUDIT.md` 5-criterion verdict — upgrade row 4 (keyboard/a11y) from "PASS (carry-over, partial)" to "PASS (full WAI-ARIA Date Picker pattern)"
- Verify with at least one screen reader (NVDA or VoiceOver)

**NOT in scope** of this BACKLOG entry:

- Time picker a11y refinements (separate consideration — time picker uses different ARIA patterns, possibly `role="radiogroup"` for hour/minute selection)
- Locale calendar systems (lunar / Hijri / Korean Sexagenary) — separate locale phase
- Range selection a11y (BACKLOG could be split further if range selection is needed)
- ActivityPub Event object integration
- WordPress editor date binding

**Sequencing**:

- Best executed after v3.4.7 freeze stabilizes and at least one external pilot using the module surfaces real usage patterns.
- May be bundled with the v3.4.x Pilot Block Theme Probe if the probe surfaces date-picker usage that needs a11y.
- Independent of BACKLOG #15 (Snackbar Runtime) and #17 (Text Input Audit) — different surface, different a11y model.

### 20. Theme-only color customization policy

- **Bucket**: F — Plugin / theme binding policy
- **Status**: **Resolved / closed at v3.6.1.**
- **Target**: v3.6.1 Token Architecture Refactor — **DONE**
- **Source**: `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` §1 (Color picker concern and bridge strategy)

**The concern**:

The WordPress `theme.json` color palette UI / Global Styles inspector / color swatch preview is built around a single-color-per-slug assumption. Axismundi's M3 token graph (`ref → sys → comp`) does not fit that assumption — a user who opens Gutenberg's color picker and changes a swatch sees a visible-but-non-functional control because the change does not propagate to `--md-sys-color-*`. This is a UX anti-pattern (user trust erosion) AND a review risk (WordPress.org theme reviews routinely test color controls).

**Resolution proposal**:

```
Theme-only mode (no Interpreter Plugin installed):
  theme.json settings.color.custom = false
  Lock Gutenberg color customization UI.
  Document the lock as protecting the M3 token graph.
  Direct users to the M3 Interpreter Plugin (BACKLOG #21) for full
  color customization.
```

This is the **honest default** — visible controls behave because non-functional controls are not rendered. Compare to the alternative ("custom=true with default Gutenberg color UI that doesn't propagate") which violates the "Visible control must map to real runtime behavior" design principle.

**Scope when triggered**:

1. Verify `ontology-theme-pilot/theme.json` current `settings.color.custom` value.
2. If currently `true` (or unset, which defaults to `true`), update to `false`.
3. Add documentation note in pilot README explaining the policy.
4. Confirm that the lock applies in both site editor and post editor contexts.
5. Record decision in `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` §6 status table.

**NOT in scope**:

- Implementing the Interpreter Plugin that *would* re-enable color customization with M3 bridging (separate BACKLOG #21).
- Renaming or restructuring existing palette slugs (separate task).
- Block-supports-level color overrides on individual blocks (per-block decision, not theme-level policy).

**Sequencing**:

- This is a small mechanical change to `ontology-theme-pilot/theme.json` plus a documentation note.
- Can be bundled with v3.5.0 Public Surface Reframe or executed standalone as a quick patch.
- Independent of all other v3.4.x items.

- **v3.5.0 Phase 1B charter pointer (2026-05-15)**: Recorded in `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §6.2`. Execution scheduled for a v3.5.x mini-release ("theme policy") alongside BACKLOG #22 implementation. NOT closed until that mini-release lands.
- **v3.6.0 Pilot validation (2026-05-19)**: `axismundi-pilot/theme.json`
  confirms `settings.color.custom = false`, `settings.color.defaultPalette =
  false`, and 24 editor-facing M3 semantic color slugs. This validates the
  theme-only default. Final close is deferred to v3.6.1 because the token layer
  still needs the ref/sys/preset/custom bridge split and dark-mode sys-layer
  swap.
- **v3.6.1 final close (2026-05-19)**: closed by the Token Architecture
  Refactor Phase 1 implementation:
  1. `axismundi-pilot/theme.json` still has `settings.color.custom = false`
     and `settings.color.defaultPalette = false`.
  2. `wp-preset.bridge.css` and `wp-custom.bridge.css` exist in lab, Pilot
     assets, and the published styleguide mirror.
  3. `theme.json settings.custom.axismundi.*` has 26 downstream-only `var(...)`
     leaves; Axis G validates every leaf against the real upstream token graph.
  4. `npm run validate:computed` validates light/dark sys-layer swaps through
     both forced matrix entries and the real Pilot `Light / Dark / Auto`
     switcher click path.
  5. Axis E/F/G now permanently guard the md-ref -> md-sys -> WP bridge ->
     theme.json chain.

### 21. M3 Interpreter Plugin separation

- **Bucket**: F — Plugin
- **Status**: Deferred (milestone candidate; scope refined by v3.6.0)
- **Target**: v3.6.x+ after v3.6.1 Token Architecture Refactor
- **Source**: `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` §1 (Bridge stages) and §7 (Interpreter Plugin scope preview)

**Goal**:

Create a separate WordPress plugin (`axismundi-m3-interpreter` or similar) that bridges Gutenberg's color/style UI and Axismundi's M3 token graph. The plugin is NOT a fork of Gutenberg — it extends Gutenberg's official extension surface (block filters, block supports, SlotFill, PluginSidebar, theme.json contract, `@wordpress/global-styles-engine`) without modifying core.

The plugin sits at the binding boundary:

```
WordPress (Gutenberg core + theme.json) ←→ Interpreter Plugin ←→ Axismundi M3 token graph
```

**Three-stage phasing** (per FEEDBACK-AND-STRATEGY.md §1 "Bridge stages"):

```
Stage 1 — Static bridge (no plugin, theme-only mode — covered by BACKLOG #20)
Stage 2 — Preset bridge (v1 plugin):
   theme.json palette slugs registered;
   --md-sys-color-primary: var(--wp--preset--color--primary);
   Changing the WP preset propagates to M3 sys token.
   Limitation: changing one preset does not regenerate the M3 role family.

Stage 3 — Semantic M3 bridge (v2 plugin):
   User chooses a seed color or role color;
   Plugin generates M3 tonal palette (ref tier);
   Plugin computes light/dark role sets (sys tier);
   Plugin synchronizes Global Styles / editor CSS / frontend CSS.
   Cost: depends on Material Color Utilities (HCT color space) —
   non-trivial to ship in either JS or PHP.
```

**Implementation surface** (preview, not committed):

```
axismundi-m3-interpreter/
├── axismundi-m3-interpreter.php       (plugin bootstrap)
├── block.json
├── src/
│   ├── editor/
│   │   ├── index.js                   (editor bootstrap)
│   │   ├── plugin-sidebar.js          (M3 role panel via PluginSidebar)
│   │   ├── block-filters.js           (blocks.registerBlockType extensions)
│   │   ├── m3-controls.js             (InspectorControls for M3 role/variant)
│   │   └── preview-sync.js            (editor canvas / frontend sync)
│   ├── runtime/
│   │   ├── theme-mode.js              (data-theme auto/light/dark — paired with BACKLOG #22)
│   │   ├── token-resolver.js          (HCT → sys token computation)
│   │   └── block-role-map.js          (core block → M3 role mapping table)
│   └── styles/
│       ├── editor.css                 (editor canvas runtime)
│       ├── frontend.css               (frontend runtime)
│       ├── tokens-ref.css             (ref tier)
│       ├── tokens-sys.css             (sys tier)
│       └── tokens-comp.css            (comp tier)
├── tokens/
│   ├── m3-baseline.tokens.json        (M3 baseline schemes)
│   ├── axismundi.tokens.json          (Axismundi specialization)
│   └── role-map.json                  (block-to-role mapping ontology)
└── build/
```

The `role-map.json` is the ontology bridge:

```json
{
  "core/button": {
    "m3Roles": ["filled-button", "outlined-button", "text-button", "tonal-button", "elevated-button"],
    "defaultRole": "filled-button"
  },
  "core/group": {
    "m3Roles": ["surface", "card", "section", "navigation-container"],
    "defaultRole": "surface"
  },
  "core/navigation": {
    "m3Roles": ["top-app-bar", "navigation-bar", "navigation-rail", "navigation-drawer"],
    "defaultRole": "top-app-bar"
  }
}
```

**NOT in scope** of this BACKLOG item:

- Modifying Gutenberg core or Site Editor source.
- Replacing the entire Gutenberg color UI (the plugin extends, it does not replace).
- Implementation in current v3.4.x cycle — this is firmly a v3.5.x+ milestone.

**Sequencing dependencies**:

- BACKLOG #20 (theme-only color policy) should land first to establish the "no plugin = no customization" honest default.
- BACKLOG #22 (explicit `data-theme="auto"` 3-state) should land in the plugin's runtime module (`src/runtime/theme-mode.js`).
- The plugin's `role-map.json` becomes a downstream consumer of completed Component modules (BACKLOG #17 text input mapping, future text-field-WP-mapping, etc.).

**Sub-decisions at execution time**:

- Stage 2 OR Stage 3 first? Stage 2 is shippable and useful; Stage 3 needs Material Color Utilities. Probably Stage 2 first, Stage 3 as a follow-up release.
- Material Color Utilities: JS (client-side, larger bundle) or PHP port (server-side, requires implementing HCT)? Open question.
- Plugin name and slug: TBD.
- Block-attribute strategy: `className` only (safest, no save-markup validation risk) vs. custom block attributes like `m3Role` (more semantic, but core block save markup may reject). FEEDBACK-AND-STRATEGY.md §6 notes this is speculative until validated.
- **v3.6.0 refinement**: In theme-only mode, bridge direction is M3 -> WP
  projection (`md-ref -> md-sys -> wp-preset/wp-custom`). The Interpreter
  Plugin owns the reverse/customizable direction where user-selected WP values
  or HCT inputs regenerate the M3 graph. Do not blur these modes inside the
  theme.

### 22. Explicit `data-theme="auto"` 3-state model

- **Bucket**: D/E — Theme runtime / lab module candidate
- **Status**: Deferred
- **Target**: v3.5.0 Public Surface Reframe (paired with BACKLOG #11)
- **Source**: `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` §6 (Impact on current v3.4.x work)

**The current state**:

The ontology-theme-pilot uses the pattern:

```css
:root:not([data-theme="light"]) {
  /* dark scheme overrides */
}

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    /* dark scheme overrides */
  }
}
```

This works functionally — a missing `data-theme` attribute, `data-theme="auto"`, or `data-theme="dark"` all resolve to dark when the OS prefers dark, while `data-theme="light"` forces light. But the state model is implicit. There is no canonical "auto" state visible in the DOM, and the `:not()` pattern would silently absorb hypothetical future variants like `data-theme="sepia"` or `data-theme="dim"` or `data-theme="high-contrast"`.

**The proposed model**:

Three explicit states:

```
data-theme="auto"   →  follow OS preference (default)
data-theme="light"  →  force light
data-theme="dark"   →  force dark
```

CSS layered explicitly:

```css
/* Light is the baseline */
:root,
:root[data-theme="light"] {
  color-scheme: light;
  /* light token values */
}

/* Auto + OS dark */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme]),
  :root[data-theme="auto"] {
    color-scheme: dark;
    /* dark token values */
  }
}

/* Force dark */
:root[data-theme="dark"] {
  color-scheme: dark;
  /* dark token values */
}
```

The `<html>` element is set by PHP `language_attributes` filter (server-side default) and overridden by JS `document.documentElement.dataset.theme` (user choice from localStorage).

**Why this matters**:

1. **Future variants**: when a high-contrast or sepia variant is added later, the `:not([data-theme="light"])` pattern would incorrectly absorb it into the dark branch. The explicit 3-state model makes the variant addition mechanical.
2. **Debuggability**: `<html data-theme="auto">` makes the current state visible in DevTools. The implicit "no attribute = auto" model hides state.
3. **Pairing with BACKLOG #21**: the Interpreter Plugin's `src/runtime/theme-mode.js` should expose `auto / light / dark` as an explicit enum, not an implicit "anything-but-light = dark" computation.

**Scope when triggered**:

1. Audit current `ontology-theme-pilot/stylesheets/tokens.css` for `:not([data-theme="light"])` patterns.
2. Rewrite each to the explicit 3-state model.
3. Confirm `color-scheme` is set explicitly per state.
4. Add PHP `language_attributes` filter to set `data-theme="auto"` by default at the HTML root.
5. Add small inline `<head>` script for JS override from localStorage (runs before render to avoid FOUC).
6. Verify both site editor canvas and frontend render correctly under each state.

**NOT in scope**:

- Adding high-contrast or sepia variants — those are separate decisions; this entry just makes future addition mechanical.
- Implementing the full theme-mode JS module — that lives in the Interpreter Plugin (BACKLOG #21).
- Changing the WordPress.org Customizer integration — separate consideration.

**Sequencing**:

- Best executed alongside v3.5.0 Public Surface Reframe (BACKLOG #11), since both touch ontology-theme-pilot's public surface and benefit from a single coordinated audit.
- Independent of BACKLOG #15 (Snackbar) and #17 (Text Input).
- BACKLOG #21 (Interpreter Plugin) consumes the output of this entry.

- **v3.5.0 Phase 1B charter pointer (2026-05-15)**: Recorded in `docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §6.1` (3-state model designed: light / dark / auto). Execution scheduled for a v3.5.x mini-release ("theme policy"). NOT closed until that mini-release lands.

### 23. Elevated Chip Variants

- **Bucket**: E — Lab module variant expansion
- **Status**: Deferred
- **Target**: v3.4.10+ or later, after v3.4.9 first-Component-Module pattern stabilizes
- **Source**: v3.4.9 Phase 1 scope decision. Material Design 3 §14 spec defines optional elevated variants for assist, filter, and suggestion chips (input chip is always outlined per M3 spec since it represents user-entered data). The v3.4.9 Chip Full Spec Module focuses on the four baseline variants already present in `components.css §11`. Elevated variants are valid M3 options but expanding into them would dilute the **first Component Full-Spec Module** as a pattern template.

**Why deferred**:

v3.4.9 is the first Axismundi Component Full-Spec Module. Its purpose is to establish the *pattern* for component modules (full-spec audit + measurement audit + WordPress mapping audit), not to expand chip into every M3 optional variant. If elevated variants were folded into v3.4.9:

1. The audit doc would double in size and lose its focus as a "first-of-class" template.
2. The variant matrix would mix "baseline coverage" with "module extension", obscuring the cleanest demonstration of what a Component Module *is*.
3. Subsequent Component Modules (text-field, future FAB-full-spec) would have a less clear template to follow.

After v3.4.9 stabilizes, elevated variants become a straightforward extension within the established pattern.

**Scope when triggered**:

- `lab-chip.css` extends with elevated variant selectors for assist / filter / suggestion (naming convention TBD by v3.4.9 audit).
- Each variant: M3 elevation token (typically level1 at rest, level2 on hover/focus, dropped on press).
- Visual specimens in `lab-chip-pattern.html`.
- `CHIP-SPEC-AUDIT.md` v2 update with elevated variant matrix rows.
- Token alignment check: which `--md-sys-elevation-level*` tokens are used.

**NOT in scope**:

- Input chip elevated variant — M3 spec does NOT define elevated input chip (input chip represents user-entered data, always outlined).
- Promoting elevated variants to `components.css` baseline. Separate Charter §1 decision after the variant pattern stabilizes in lab.
- Native form wrapping changes (filter chip `<input type="checkbox">`) — handled in v3.4.9.

**Sequencing**:

- Best executed AFTER v3.4.9 completes AND at least one external use of `lab/modules/chip/` surfaces real elevated-variant demand.
- Independent of BACKLOG #15, #17, #21.

### 28. Icon button public specimen SVG wording cleanup (v3.5.2 Phase 0 Risk 4)

- **Bucket**: E — Documentation / public specimen cleanup
- **Status**: **Resolved at v3.5.16.** `style-guide.html #components-icon-button` public snippet/helper wording now teaches Material Symbols markup instead of stale inline SVG examples.
- **Target**: v3.5.x public-surface cleanup release (NOT v3.5.2 close)
- **Source**: `docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md` Risk 4 + `ICON-BUTTON-SPEC-AUDIT.md` §2.3 / §7

**The concern**:

`style-guide.html #components-icon-button` now renders real Material Symbols glyph spans inside `.ax-icon-button`, but the public snippet/helper wording still contains SVG-era examples. This is a documentation/public specimen mismatch, not a runtime bug.

**Why it matters**:

Icon button is the first Wave 1 component with `icon-system/` as a CURRENT unconditional dependency. Public examples must not teach downstream authors to bypass `icon-system/` by copying stale inline SVG snippets.

**Proposed cleanup**:

```txt
Update style-guide.html #components-icon-button snippet/helper copy:
  before: <svg ...></svg> / update SVG fill/d wording
  after:  <span class="material-symbols-rounded notranslate" ...>...</span>

Keep:
  - native <button type="button">
  - aria-label on the host button
  - aria-hidden glyph
  - translate="no"
  - draggable="false"
```

**NOT in scope**:

- Changing baseline `.ax-icon-button` styles.
- Moving `ICON-BUTTON-RUNTIME-AUDIT.md`.
- Implementing Icon button Phase 2 artifacts (done in v3.5.2).
- Implementing Ripple v2 or current ripple wiring.

### 29. Card behavior patterns (v3.5.3 M3 guideline cross-check)

- **Bucket**: E — Lab module behavior pattern expansion
- **Status**: Open
- **Target**: v3.5.x mini-release or Wave 2 candidate (NOT v3.5.3 Card primitive close)
- **Source**: v3.5.3 Card Phase 2 M3 guideline cross-check against https://m3.material.io/components/cards/guidelines §Behavior

**The concern**:

v3.5.3 closes Card as a static Component Full-Spec primitive: variants, slots, native action/navigation semantics, disabled Pattern B, and current `core/group` bridge. Material Design 3 Card guidelines also include behavior-heavy patterns that are valid Card guidance but intentionally outside the primitive release.

**Deferred behavior patterns**:

```txt
expanding cards / container transform
navigation transition patterns
swipe actions (one swipe action per card)
pickup / move / reorder in collections
scrolling behavior for expanded cards
```

**Why deferred**:

These patterns require runtime behavior, motion policy, collection semantics, and often plugin/editor integration. Folding them into v3.5.3 would blur the boundary between Card primitive and Card behavior patterns.

**Proposed future scope**:

- Create a Card behavior pattern mini-release or Wave 2 item.
- Decide whether behavior patterns live under `modules/card/` or a separate interaction/pattern module.
- Audit M3 behavior rules:
  - expanding only for important expressive moments
  - one swipe action per card
  - no swipeable/paginated content inside swipeable cards
  - pickup/reorder raises the card without pushing other elements
  - mobile expanded cards scroll in-screen, not internally nested
  - desktop expanded card content may expand/scroll
- Keep v3.5.3 `lab-card.css` / `lab-card-pattern.html` as the static primitive reference.

**NOT in scope**:

- Reopening v3.5.3 Card primitive release.
- Adding `lab-card.js` retroactively to v3.5.3.
- Wiring current ripple.
- Implementing Ripple v2.
- Editing `components.css` baseline without a separate Charter decision.

### 30. Extended FAB behavior patterns (v3.5.5 FAB family close)

- **Bucket**: E — Lab module behavior pattern expansion
- **Status**: Open
- **Target**: v3.5.x mini-release or Wave 2 candidate (NOT v3.5.5 FAB primitive close)
- **Source**: v3.5.5 FAB Phase 0/1/2 findings; M3 Extended FAB behavior concerns.

**The concern**:

v3.5.5 closes FAB #3 + Extended FAB #4 as one static Component Full-Spec family primitive: size variants, surface variants, static Extended FAB, disabled Pattern A, icon-system CURRENT unconditional, and ripple CANDIDATE. Extended FAB guidance can also involve behavior-heavy patterns that are intentionally outside the primitive release.

**Deferred behavior patterns**:

```txt
Extended FAB collapse/expand
auto-hide on scroll
FAB-to-FAB-menu transition
toolbar floating-with-FAB choreography
modal/sheet morph behavior
```

**Why deferred**:

These patterns require runtime behavior, scroll policy, motion policy, toolbar/menu coordination, and often editor or plugin integration. Folding them into v3.5.5 would blur the boundary between the static FAB family primitive and behavior-pattern work.

**Proposed future scope**:

- Decide whether behavior patterns live under `modules/fab/`, `modules/fab-menu/`, or a separate interaction/pattern release.
- Audit Extended FAB collapse/expand and auto-hide behavior against M3 guidance.
- Coordinate with Toolbar #8 and FAB menu #5 before implementing cross-component choreography.
- Preserve v3.5.5 `lab-fab.css` / `lab-fab-pattern.html` as the static primitive reference.

**NOT in scope**:

- Reopening v3.5.5 FAB family primitive release.
- Adding `lab-fab.js` retroactively to v3.5.5.
- Promoting FAB to ripple TARGET before Ripple v2.
- Implementing Ripple v2.
- Implementing FAB menu or Toolbar integration.
- Editing `components.css` baseline without a separate Charter decision.

### 31. Pill radius interpolation — morphing-safe corner-full token

- **Bucket**: C / D — Token graph + baseline correction
- **Status**: **RESOLVED at v3.5.9**
- **Target**: ~~v3.5.x baseline correction release~~ — **v3.5.9 baseline correction**
- **Source**: v3.5.7 Playwright Button `:active` morph QA finding

**Resolution at v3.5.9**:

```txt
tokens.css:
  --md-sys-shape-corner-full remains 9999px.
  --md-sys-shape-corner-pill-stable added as 50%.
  --comp-button-radius now resolves to calc(var(--comp-button-height) / 2).

components.css:
  Button rest radius now resolves to finite 20px.
  Button group connected / selected morph sources now use a local
  --_button-group-pill-radius based on Button's 40px height.
```

Visual QA result:

```txt
Button variants:       20px -> 8px active morph, no 9999px interpolation flicker.
Button group selected: 20px -> 4px active morph.
Outer group corners:   20px preserved while inner pressed corners shrink.
```

Split button remains a future component-cycle concern; it was explicitly
excluded from v3.5.9 rather than silently migrated before its Full-Spec audit.

**The concern**:

Current `--md-sys-shape-corner-full = 9999px` works correctly for static pill contexts such as Chip at rest, Badge dot, and other non-morphing surfaces. It becomes visually unstable when a component transitions from a full pill to a smaller pressed shape.

Confirmed example:

```txt
Button #1 (v3.5.1):
  rest      -> border-radius: 9999px
  pressed   -> border-radius: 8px

Observed Playwright trace on lab-button-pattern.html:
  rest      -> 9999px
  80ms      -> 2608px
  transient -> 59px / 0px class of intermediate computed values
  pressed   -> 8px
```

The final pressed value is correct, but CSS interpolates the very large `9999px` value linearly during the transition. That produces visible flicker during the morph.

**Affected closed components**:

```txt
Button #1 (v3.5.1):
  confirmed via Playwright on lab-button-pattern.html; fixed at v3.5.9

FAB #3 + Extended FAB #4 (v3.5.5):
  verified not affected in v3.5.9 Phase 0; no corner-full morph source

Future morphing components:
  sheet drag surfaces, card expand behavior, Split button, and other
  full-pill -> smaller-corner transitions should be checked during their own
  component cycles
```

**Recommended fix direction**:

Option (d): introduce a dedicated morphing-safe pill token instead of changing static `corner-full` semantics globally.

```txt
Static logical pill:
  --md-sys-shape-corner-full: 9999px

Morphing-safe pill:
  --md-sys-shape-corner-pill-stable:
    calc(var(--comp-X-height) / 2)
    OR a per-component height-based calc through component tokens
```

Rationale:

```txt
- Static contexts continue to use corner-full.
- Morphing transition sources use a height-based pill radius.
- M3 compliant: M3 defines fully-rounded shape semantics; the concrete
  interpolation-safe implementation is the design system's responsibility.
- Avoids ad-hoc per-component fixes when the same interpolation problem
  appears in FAB, Button group, or future behavior modules.
```

**Cycle shape when scheduled**:

```txt
Phase 0:
  Token graph design. Decide option (b) component-token-only vs option (d)
  dedicated morphing-safe token.

Phase 1:
  Baseline correction audit + affected consumer alignment plan.

Phase 2:
  tokens.css + components.css edit for Button and any confirmed affected
  morphing components.

Phase 3:
  Playwright re-QA for Button + FAB morph stability.

Phase 5:
  BACKLOG #31 close + CHANGELOG/ROADMAP close. Button group #6 inherits the
  fixed baseline when its Full-Spec cycle starts.
```

**Cross-references**:

```txt
v3.5.7 Playwright Button :active morph QA finding
components.css §2 Button:
  .ax-button { border-radius: var(--comp-button-radius); }
  .ax-button:active { border-radius: var(--md-sys-shape-corner-small); }
tokens.css:
  --md-sys-shape-corner-full definition
components.css §15 + §16 FAB:
  verify active/full-pill morph behavior during correction cycle Phase 0
```

**NOT in scope**:

- Editing baseline files during v3.5.7 Text field Phase 0.
- Changing `--md-sys-shape-corner-full` globally without a token graph decision.
- Reopening v3.5.1 Button or v3.5.5 FAB release closure.
- Adding JavaScript to solve a CSS interpolation issue.

### 32. Button family size variants — XS/S/M/L/XL coverage cycle

- **Bucket**: A / B — Token graph + component family
- **Status**: Resolved
- **Target**: Closed in v3.5.13 Wave 1 closure cleanup
- **Source**: v3.5.10 Button group Phase 3 Playwright finding

**The concern**:

M3 Button group specs declare five size variants (XS / S / M / L / XL) with
distinct heights, font-sizes, and touch targets. Axismundi's Button family
currently ships only the default M size (40px height, 14px font, 16px
horizontal padding). Button group #6 inherits this limitation:

```txt
Phase 3 Playwright measurement on lab-button-group-pattern.html:
  XS height:  40px  (M3 spec expects smaller)
  M height:   40px  (default)
  L height:   40px  (M3 spec expects larger)
  XL height:  40px  (M3 spec expects largest)
  font-size:  14px  (constant across is-size-* hooks)
```

Root cause:

```txt
baseline components.css §28 is-size-xs/s/l/xl hooks only adjust:
  - gap / min-inline-size (XS / S)
  - connected inner corner radius (L / XL)
They do not touch height / font / padding because those properties cascade
from .ax-button at the default 40px / 14px / 16px values shipped by Button #1.
```

**Affected components**:

```txt
Button #1 (v3.5.1):          default M only; no XS/S/L/XL variants.
Icon button #2 (v3.5.2):     default size only; XS/S/L/XL not implemented.
Button group #6 (v3.5.10):   size hooks scaffolded but not functional.
```

**Recommended cycle shape**:

```txt
Phase 0:
  Audit M3 spec for Button + Icon button + Button group size matrices.
  Decide token surface: per-size --comp-button-{xs,s,m,l,xl}-height
                       vs --comp-button-height-{xs,s,m,l,xl}
                       vs density-style global modifier.

Phase 1:
  3-component coordinated audit. Spec, measurement, and WP-mapping
  updates for all three components.

Phase 2:
  tokens.css + components.css patch covering Button, Icon button, and
  Button group is-size-* hooks. Lab pattern HTML updates per component.

Phase 3:
  Playwright re-measure XS / S / M / L / XL heights, font sizes, padding,
  and touch targets across all three components.
  Re-evaluate SC 2.5.5 AAA for any size with rendered hit target >= 44x44.

Phase 5:
  CHANGELOG / ROADMAP / MATRIX bookkeeping.
  Update Button #1, Icon button #2, Button group #6 audit docs in-place
  (no audit re-open; mechanical size finding update only).
```

**Cycle interaction**:

This is a cross-cutting size cycle, similar in shape to v3.5.9 pill-radius
correction. It is NOT a Wave 1 component cycle; it is a foundation cycle
that touches three already-closed components mechanically.

**NOT in scope**:

- Reopening v3.5.1 Button, v3.5.2 Icon button, or v3.5.10 Button group
  closure verdicts.
- Adding new Wave 1 components.
- Changing `--md-sys-shape-corner-full` or `--md-sys-shape-corner-pill-stable`
  values (v3.5.9 baseline correction stays in place).
- Implementing FAB size variants (FAB has independent size system).

**Cross-references**:

```txt
v3.5.10 Phase 3 Playwright finding (lab-button-group-pattern.html §6 specimen
  labelled "Size hooks — partial baseline")
products/reference-implementations/axismundi-lab/modules/button-group/docs/
  BUTTON-GROUP-MEASUREMENT-AUDIT.md §9 Phase 5 close findings
M3 Button groups spec page (XS/S/M/L/XL size table)
M3 Buttons spec page (XS/S/M/L/XL size table)
```

**Resolution (v3.5.13)**:

```txt
Closed by the Wave 1 closure cleanup Lane A.

Implemented:
  - tokens.css Button family size matrix tokens;
  - components.css §2 Button is-size-xs/s/m/l/xl hooks;
  - components.css §3 Icon button is-size-xs/s/m/l/xl hooks;
  - components.css §28 Button group size hooks and connected geometry.

Verified:
  - Button XS/S/M/L/XL = 32 / 40 / 56 / 96 / 136;
  - Icon button XS/S/M/L/XL = 32 / 40 / 56 / 96 / 136;
  - Button group spacing = 18 / 12 / 8 / 8 / 8;
  - connected gap = 2px;
  - XS/S connected min width = 48px;
  - default no-size Button remains 40px.

Follow-up:
  Card composition QA surfaced the Icon button corner-full interpolation tail
  from BACKLOG #31. v3.5.13 replaced the composed Icon button rest radius with
  finite box-derived radius while preserving the existing pressed corner.
```

### 33. List M3 full token coverage extension

- **Bucket**: B — Component token alignment / audit extension
- **Status**: Resolved
- **Target**: Closed in v3.5.13 Wave 1 closure cleanup
- **Source**: v3.5.11 Phase 5 close post-mortem; user supplied the full M3 List token dump after close

**The concern**:

v3.5.11 closed List #33 with Common / Enabled and Selected token rows mapped,
and resolved the two small in-cycle color mismatches (`segmented container` and
`unselected trailing icon`) inside `components.css` §26. The post-close token
dump shows the audit still lacks explicit full-table coverage for additional
M3 List token categories:

```txt
Color:
  Disabled
  Disabled - Selected
  Hovered
  Hovered - Selected
  Focused + focus indicator
  Focused - Selected
  Pressed (ripple)
  Pressed - Selected
  Dragged

Spacing:
  leading/trailing 16dp
  top/bottom 10dp
  between 12dp
  divider spaces
  segment gap 2dp

Shape:
  container corner-large
  item corner-none
  expressive state shapes
  avatar/image/video shapes

Size and typography:
  avatar 40dp
  icon 24dp / expressive 20dp
  image 56dp
  video 100dp / 56dp / 114dp / 64dp
  56/72/88 row heights
  label/supporting/overline/trailing typography tables
```

**Important framing**:

```txt
Hovered / Focused / Pressed rows partly map to Axismundi's generic
components.css §0 state-layer Pattern A foundation. That may be an intentional
design-system difference, not a component bug.

Dragged rows include elevation level4 and state-layer opacity 0.16; v3.5.11
explicitly deferred drag/reorder behavior, so this needs a future decision
before baseline edits.

Expressive shape rows are M3 Expressive theming dimensions. Axismundi currently
uses expressive state shapes in List §26, but the full table should be audited
before claiming exhaustive coverage.
```

**Recommended cycle shape**:

```txt
Phase 0:
  Compare the full M3 List token table against components.css §26 and lab-list
  specimens. Classify each mismatch as:
    - already covered by §26,
    - covered by generic §0 state-layer foundation,
    - composition-owned (Avatar / icon-system),
    - behavior-deferred (dragged / expand / reorder),
    - real List baseline mismatch.

Phase 1:
  Audit extension for LIST-SPEC-AUDIT.md + LIST-MEASUREMENT-AUDIT.md.

Phase 2:
  Minimal baseline edit only if Phase 0/1 identify a narrow List-specific
  mismatch. Otherwise documentation-only.

Phase 3:
  Playwright re-check for disabled, selected-disabled, hover/focus/pressed,
  dragged specimen if introduced, spacing, and typography measurements.

Phase 5:
  CHANGELOG / ROADMAP / MATRIX bookkeeping.
```

**NOT in scope**:

- Reopening v3.5.11 List release closure.
- Adding BACKLOG #33 during v3.5.11 Phase 5 after close.
- Implementing drag/reorder runtime.
- Treating generic §0 state-layer differences as bugs before an explicit
  framework decision.

**Cross-references**:

```txt
v3.5.11 List #33 Phase 5 close
LIST-SPEC-AUDIT.md §10 Token-Level M3 Spec Map
LIST-MEASUREMENT-AUDIT.md §7 Token Comparison / Phase 3 Finding
components.css §0 State-layer foundation
components.css §26 List
```

**Resolution (v3.5.13)**:

```txt
Closed by the Wave 1 closure cleanup Lane B.

Implemented / recorded:
  - LIST-SPEC-AUDIT.md and LIST-MEASUREMENT-AUDIT.md full-token extensions;
  - components.css §26 focus indicator 3px / -3px;
  - selected-disabled container resolves to 38% on-surface mix;
  - segmented wrapper is transparent / radius 0 / padding 0;
  - segmented item containers own surface color and corner-large radius;
  - List Expand trailing icon container maps to surface-container (#211f26
    in dark scheme);
  - trailing supporting time text no-wraps.

Deferred by design:
  - drag/reorder runtime;
  - expand/collapse runtime;
  - video slot implementation;
  - generic §0 state-layer rewrite.
```

### 34. Styleguide modernization + lab module navigation UX

- **Bucket**: E — Public surface / publish UX
- **Status**: Partially resolved at v3.5.16 / v3.5.17; residual N3 module picker/dialog UX remains open.
- **Priority**: Medium
- **Target**: v3.5.x after GitHub Pages publish, before v3.6.0 Ontology Theme Pilot if possible
- **Source**: v3.5.14 publish prep Phase 3 / user navigation review

**The concern**:

Wave 1 component modules are now organized under `lab/modules/*`, but the
canonical `style-guide.html` still behaves like an older monolithic surface.
The publish flow is valid, yet the navigation story is incomplete:

```txt
index.html          -> styleguide/
styleguide/         -> component visual surface
lab/modules/*       -> validation surface, pattern HTML, audit docs
```

Users can reach the styleguide from the root index, but a reader inspecting a
component in the styleguide cannot easily jump to that component's lab module,
audit docs, or validation pattern. This is a publish UX gap, not a component
correctness blocker.

**Recommended direction**:

Add module-aware navigation to the styleguide without turning lab module
pattern HTML into the canonical publish surface.

Candidate UX:

```txt
Styleguide header or component section header:
  lab icon button
    -> dialog / sheet / menu listing relevant lab modules
       - module overview
       - pattern HTML
       - SPEC / MEASUREMENT / WP-MAPPING / RUNTIME docs as available
```

This matches the existing ontology:

- `styleguide/` = canonical public visual mirror,
- `lab/modules/*` = validation and audit surface,
- lab links are available for inspection but do not replace canonical demos.

**Progress**:

```txt
v3.5.16:
  - Charter/module-workspace framing amended.
  - 15 validation specimen links + 3 record audit links added to styleguide.
  - 16 lab pattern pages received validation-specimen banners.

v3.5.17:
  - Mobile shell rebuilt with styleguide-local top bar + .sg-drawer.
  - Icon theme switcher added while preserving data-theme-button.
  - Body mobile polish added with compact palettes + native read-more.
  - Foundation > Typography now links typography-axis.html as an adjunct.
  - typography-axis.html received mobile-friendly collapsible controls.

Remaining:
  - N3 module picker/dialog UX, if still desired after the direct-link shell.
```

**Likely scope**:

```txt
Phase 0:
  Read PUBLIC-SURFACE-CHARTER, Architecture Boundaries, Article 12, and
  publish_styleguide.py. Decide exact UX: global lab index dialog, per-section
  lab affordance, or both.

Phase 1:
  Navigation map: styleguide section -> lab module(s) -> docs/pattern files.

Phase 2:
  Minimal styleguide/source edit + publish mirror regeneration.

Phase 3:
  Browser/Playwright visual QA for dialog, focus management, links, and mobile.

Phase 5:
  CHANGELOG / ROADMAP / CURRENT-STATE / NEXT-SESSION bookkeeping.
```

**Out of scope**:

- Rebuilding the whole styleguide as a multi-page app.
- Publishing every lab pattern HTML as canonical styleguide content.
- Changing component baseline behavior.
- Implementing templates/page-layout previews; that belongs to the `/templates/`
  category.

**Cross-references**:

```txt
CONSTITUTION.md Article 12
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
products/reference-implementations/axismundi-lab/docs/ARCHITECTURE-BOUNDARIES.md
tools/generators/publish_styleguide.py
docs/v3.5.14/TEMPLATES-PUBLISH-CATEGORY-NOTE.md
```

### 35. Root index Korean version and language toggle

- **Bucket**: E — Public surface / i18n
- **Status**: Open
- **Priority**: Low
- **Target**: v3.5.x after GitHub Pages publish, or later public-site polish
- **Source**: v3.5.14 publish prep Phase 3 / user i18n review

**The concern**:

The root `index.html` now works as the English public entry point, and the repo
has both `README.md` and `README.ko.md`. A Korean public entry page would improve
the Korean developer audience story, but it is not a publish blocker.

**Recommended direction**:

Add a Korean root entry and a small language switcher after the first GitHub
Pages publish proves the English route is stable.

Candidate shape:

```txt
index.html     English entry
index.ko.html  Korean entry

Language switch:
  English <-> 한국어
```

Implementation options to decide in Phase 0:

- static reciprocal links only,
- tiny no-dependency JS toggle,
- URL-param or localStorage preference.

Static reciprocal links are preferred unless there is a clear reason to add
client-side state.

**Out of scope**:

- Translating every styleguide component section.
- Translating all audit docs.
- Adding a site-wide i18n framework.
- Blocking v3.5.15 GitHub repo + Pages creation.

**Cross-references**:

```txt
README.md
README.ko.md
index.html
docs/v3.5.14/PUBLISH-PREP-PHASE-0-REPORT.md
```

### 36. v4.0 directory restructure — retire `lab` naming

- **Bucket**: A — Constitution / architecture / repository structure
- **Status**: Open
- **Priority**: Future architecture freeze
- **Target**: v4.0 public release planning
- **Source**: v3.5.16 framing decision

**The concern**:

`products/reference-implementations/axismundi-lab/` was named when modules were
primarily validation / experimentation surfaces designed to protect baseline
CSS from contamination. After Wave 1 closure and public GitHub Pages publish,
many modules are now canonical validated implementation workspaces.

The current naming still works, but it carries legacy semantics:

```txt
lab = validation-only / internal / experimental
```

The intended future framing is closer to:

```txt
modules = canonical implementation workspace
styleguide = public preview mirror
templates = page-layout / composition preview layer
```

**Recommended v4.0 scope**:

```txt
- Decide final module-first product name.
- Rename axismundi-lab/ to module-first name.
- Consider hoisting modules to product root level.
- Retire lab-* file prefixes where safe.
- Rewrite publish_styleguide.py around the new source structure.
- Update 50+ audit doc cross-references.
- Update README / README.ko / index / Pages navigation.
```

**Why not v3.5.16**:

- v3.5.15 just published the repo and Pages.
- A structural rename would invalidate many freshly verified paths.
- v3.5.16 can solve the immediate user-facing problem with framing and
  navigation changes.
- v4.0 is the correct architecture-freeze moment for a repository-wide
  structure migration.

**Cross-references**:

```txt
docs/v3.5.16/MODERNIZATION-AUDIT.md
docs/v3.5.16/STALE-STATE-AUDIT.md
docs/v3.5.16/STYLEGUIDE-MODERNIZATION-PHASE-0-PLAN.md
```

### 37. GitHub Pages dogfooding — rebuild docs shell with Axismundi navigation components

- **Bucket**: E — Public surface / showcase UX
- **Status**: Open
- **Priority**: Medium after Wave 2 navigation closure
- **Target**: v3.7.x+ after App bar / Nav bar / Nav rail / Tabs are closed
- **Source**: v3.5.16 mobile-first Pages discussion

**The opportunity**:

Axismundi's public documentation should eventually use Axismundi components as
its own docs shell:

```txt
Mobile:   Nav bar / bottom navigation
Tablet:   Nav rail
Desktop:  App bar + side navigation / tabs
```

That would make GitHub Pages a strong showcase: "this documentation site is
built out of the design system it documents."

**Why deferred**:

The strongest dogfooding path depends on Wave 2 navigation components:

```txt
App bar
Nav bar
Nav rail
Tabs
Menu
Toolbar
```

These are not closed yet. Implementing them ad hoc inside the docs shell during
v3.5.16 would bypass the component audit discipline.

**Allowed before Wave 2 closure**:

- mobile-first responsive improvements to `index.html` and styleguide
  navigation,
- modest use of already-closed Wave 1 components (Button / Card / List),
- no dependency on unclosed Wave 2 navigation primitives.

v3.5.17 used this carve-out for styleguide-local `.sg-*` chrome only: a mobile
top bar, menu icon button, and Sheet-style drawer without claiming App bar /
Nav drawer / Sheet completion. Richer motion and true component dogfooding stay
deferred to this item.

**Future cycle shape**:

```txt
Phase 0:
  Verify Wave 2 navigation component closure and choose docs shell architecture.

Phase 1:
  UX plan for mobile / tablet / desktop shell.

Phase 2:
  Implement docs shell dogfooding.

Phase 3:
  GitHub Pages visual QA across 390 / 768 / 1280+.

Phase 5:
  Public surface release close.
```

**Cross-references**:

```txt
docs/v3.5.16/STYLEGUIDE-MODERNIZATION-PHASE-0-PLAN.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

### 38. Carousel plugin extraction

- **Bucket**: F — Plugin/runtime extraction
- **Status**: Open
- **Priority**: Medium after v3.6.0 Pilot entry
- **Target**: v3.6.x parallel or post-Pilot
- **Source**: v3.5.18 Carousel reroute
- **Depends on**: BACKLOG #40 for long-term theme asset slicing policy.

**Scope**:

- Extract Carousel into a WordPress plugin/block lifecycle.
- Use `lab/modules/carousel/` as seed evidence, not as final plugin code.
- Improve responsive behavior, runtime navigation, ARIA, reduced motion, and
  block integration.
- Keep v3.6.0 Ontology Theme Pilot independent from Carousel.

**Non-goals**:

- Do not change v3.5.12 Carousel audit history.
- Do not include Carousel in the theme-only Pilot.
- Do not implement the plugin in v3.5.18.

**Cross-references**:

```txt
docs/v3.5.12/
docs/v3.5.18/PRE-PILOT-CLEANUP-PHASE-0-REPORT.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md row #34
```

### 39. Styleguide shell consistency — blocks.html + prose.html

- **Bucket**: E — Public surface / docs maintenance
- **Status**: Open
- **Priority**: Low, post-Pilot unless a broken interaction is found
- **Target**: Ongoing maintenance
- **Source**: v3.5.18 pre-pilot smoke scope

**Scope**:

- Consider applying the v3.5.17 styleguide-local shell pattern to
  `blocks.html` and `prose.html`.
- Align mobile top bar, theme switcher, and reading polish if/when those pages
  become public showcase surfaces.
- Fix the `sg-sidebar` responsive layout on `blocks.html` / `prose.html`;
  v3.5.18 verified spec correctness but left the shell inconsistency as
  cosmetic post-Pilot work.

**Non-goals**:

- Does not own spec correctness for blocks/prose; v3.5.18 verifies that before
  Pilot.
- Not required before v3.6.0 Pilot.
- Do not block theme implementation.
- Do not introduce unclosed Wave 2 navigation components.

**Cross-references**:

```txt
docs/v3.5.18/BLOCKS-PROSE-PILOT-SPEC-VERIFY.md
docs/v3.5.18/PRE-PILOT-SMOKE-CHECKLIST.md
```

### 40. Modularized component CSS separation

- **Bucket**: A — Architecture
- **Status**: Open
- **Priority**: Medium before v4.0 public release
- **Target**: v4.0 architecture freeze window
- **Source**: v3.6.0 Pilot Phase 3 Carousel CSS leakage finding

**Scope**:

- Treat validated module CSS as the canonical component implementation surface.
- Reduce `components.css` to foundation / baseline / non-modularized surfaces, or
  otherwise define a clear build-time split between theme-owned component CSS and
  plugin-routed component CSS.
- Update the Pilot asset bridge so plugin-routed surfaces such as Carousel do not
  silently ship inside the theme bundle unless explicitly allowed.
- Coordinate with BACKLOG #36 directory restructure and the v4.0 public release
  architecture freeze.

**Non-goals**:

- Do not rewrite `components.css` during v3.6.0.
- Do not remove historical Carousel audit artifacts.
- Do not block the v3.6.0 Pilot close; this is an architecture cleanup item.

**Cross-references**:

```txt
docs/v3.5.16/MODERNIZATION-AUDIT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
BACKLOG #36
BACKLOG #38
```

### 41. WordPress block bridge state and ripple enhancement

- **Bucket**: B — WordPress binding / block bridge
- **Status**: Open - narrowed by v3.6.6 to shared WordPress ripple runtime packaging decision
- **Priority**: Medium post-Pilot
- **Target**: v3.6.x or v3.7.x
- **Source**: v3.6.0 Pilot Phase 3 visual QA / Phase 2E minimum bridge

**Scope**:

- Extend the minimum Phase 2E block bridge into full WordPress block coverage.
- Expand beyond the v3.6.0 proof set (`core/post-content`, prose blocks, and
  `core/button`) into `core/search`, `core/group`, `core/list`, and other Pilot
  surfaces.
- Verify editor-canvas parity for hover/focus/pressed/disabled/selected state
  mapping where WordPress exposes equivalent editor states.
- Decide whether the Pilot-specific ripple bridge should graduate into a shared
  WordPress binding runtime or remain Pilot-only.
- Preserve Charter §4: no custom block registration.

**Non-goals**:

- Do not introduce custom blocks.
- Do not include Carousel; Carousel remains BACKLOG #38.
- Do not solve modular CSS slicing; that belongs to BACKLOG #40.

**v3.6.2 specimen wall inputs**:

BACKLOG #43 closed the Tier 1 evidence/classification cycle and hands these
inputs to #41:

```txt
Reset candidate:
  table-footer-contrast
    core/table tfoot keeps a too-strong 3px currentColor rule in light and dark.

Bridge candidates:
  search-styleguide-delta
    core/search differs from the validated lab Search bar implementation.

  code-long-line-overflow
    core/code needs the long-line overflow behavior proven in prose.html.

  separator-variant-visibility
    core/separator style variants need visible M3 mapping, especially dots and
    inset variants. Treat this as bridge CSS/style-variation work, not a
    Material Symbols font issue.

Semantic-decision candidates:
  button-anchor-semantics
    core/button renders anchor markup while lab Button specimens use button
    elements. Underline/user-select fixes are mechanical; the architectural
    decision is whether to extend core/buttons styles, keep an anchor bridge,
    or introduce a semantic exception.

  quote-pullquote-semantics
    core/quote and core/pullquote render different blockquote/figure structures
    that can mix styling and semantics.
```

v3.6.2 makes no custom-block decision and applies no bridge/reset patch. #41
must consume this evidence plan-first before implementation.

**v3.6.3 close evidence (2026-05-20)**:

v3.6.3 consumed the v3.6.2 evidence slice and closed these items:

```txt
Reset:
  table-footer-contrast

Bridge:
  search-styleguide-delta
  code-long-line-overflow
  separator-variant-visibility

Semantic routes:
  button-anchor-semantics
  quote-pullquote-semantics
```

The cycle did not implement custom blocks, plugin behavior, `theme.json`
changes, or `functions.php` registration changes beyond the existing block
style inventory. Separator visibility remains bridge CSS/style-variation work,
not a Material Symbols font issue.

Residual #41 scope after v3.6.3:

```txt
button mechanical cleanup after route:
  text-decoration, user-select, and state styling checks for .wp-block-button__link

quote/pullquote implementation after route:
  selector narrowing and distinct .wp-block-pullquote bridge styling

broader original #41 questions:
  ripple bridge graduation
  editor-canvas parity for hover/focus/pressed/disabled/selected states
```

**v3.6.4 close evidence (2026-05-21)**:

v3.6.4 consumed the route-unblocked residual cleanup from v3.6.3 and closed
these items:

```txt
Button mechanical cleanup after route:
  .wp-block-button__link underline leakage removed
  .wp-block-button__link user-select disabled
  focus-visible outline preserved
  hover/pressed state layers preserved
  href semantics preserved

Quote/pullquote implementation after route:
  quote selectors narrowed away from pullquote's inner blockquote
  pullquote distinct bridge surface added
  pullquote paragraph/citation typography routed through lab §3 treatment
  pullquote cite prefix leak removed

Visual QA:
  light/dark button state and quote/pullquote distinct-surface sweep
  editor canvas smoke
  front-end drag console smoke
```

The cycle did not reopen the v3.6.3 semantic decisions, introduce custom
blocks, change plugin behavior, edit `theme.json`, edit `functions.php`, or
expand the committed specimen fixture.

Remaining #41 scope after v3.6.4:

```txt
ripple bridge graduation:
  decide whether the Pilot-specific ripple bridge graduates into shared
  WordPress binding runtime or remains Pilot-only

editor-canvas parity:
  verify hover/focus/pressed/disabled/selected state mapping where WordPress
  exposes equivalent editor states

editor token enqueue parity:
  v3.6.4 Phase 3 found the editor iframe does not expose
  --md-sys-color-on-surface or --md-sys-color-outline-variant.
  Bridge selectors apply structurally, but color tokens do not resolve in the
  editor canvas. This is pre-existing token enqueue plumbing, not a v3.6.4
  regression.
```

**v3.6.5 close evidence (2026-05-21)**:

v3.6.5 consumed the editor token enqueue parity item and closed it:

```txt
Root cause:
  tokens.sys.light.css ended with a dangling opening comment.
  WordPress 7.0's editor-style transform turned that malformed light sys file
  into an empty editor iframe inline style.

Patch:
  closed the malformed trailing comment across the lab, Pilot, and styleguide
  tracked copies.

Editor result:
  --md-sys-color-on-surface:         #1D1B20
  --md-sys-color-outline-variant:    #CAC4D0
  --md-sys-color-on-surface-variant: #49454F
  pullquote divider:                 1px solid rgb(202, 196, 208)
```

The cycle did not change `theme.json`, `functions.php`, plugin behavior,
fixtures, TT5 reference files, ripple runtime, or broader editor state parity.

Remaining #41 scope after v3.6.5:

```txt
ripple bridge graduation:
  decide whether the Pilot-specific ripple bridge graduates into shared
  WordPress binding runtime or remains Pilot-only

broader editor-canvas state parity:
  verify hover/focus/pressed/disabled/selected state mapping where WordPress
  exposes equivalent editor states
```

**v3.6.6 close evidence (2026-05-21)**:

v3.6.6 consumed the remaining ripple/editor parity question for the current
v3.6.x theme bridge and narrowed #41 to a future packaging decision:

```txt
Pilot ripple bridge graduation:
  does not graduate in v3.6.6
  remains Pilot-only for the front-end core/button bridge

Editor-canvas state parity for core/button:
  focus-visible: PASS
  disabled:      PASS
  hover:         not exposed / no theme target
  pressed:       not exposed / no theme target
  selected:      not exposed / no theme target
```

Why no shared runtime graduation:

```txt
Pilot front-end runtime currently attaches to .wp-block-button__link rendered
inside .wp-block-post-content. Ripple v2's FORBIDDEN_ANCESTORS policy
(closest('.prose, .wp-block-post-content, .entry-content, [contenteditable]'))
would refuse provider-runtime attachment on that surface.
```

Remaining #41 scope after v3.6.6:

```txt
shared WordPress ripple runtime packaging decision:
  decide whether a future v3.7.x WordPress binding / plugin-custom track
  packages the Ripple v2 provider for WordPress surfaces.

Sub-decisions:
  1. post-content front-end anchors
  2. editor-owned content surfaces
  3. forbidden ancestor policy
  4. attach/detach lifecycle
  5. shared token alias location
```

v3.6.6 did not edit implementation files, did not add plugin/custom-block
behavior, did not change `theme.json`, did not edit lab ripple files, and did
not expand fixtures.

TT5 note:

```txt
Twenty Twenty-Five 1.5 is available locally at:
  C:\Users\thaum\dev\twentytwentyfive.1.5\twentytwentyfive

Use TT5 as a future core block selector / theme.json structure reference, not
as a token or visual-style source. Axismundi keeps the M3 token architecture
and Lock 1/2 downstream-only constraints.
```

Drag console note:

```txt
The user-observed ?p=36 drag errors referenced content.js and were not
reproduced in an extension-free Playwright Chromium run. content.js is not a
Pilot theme or repository file. Re-investigate only if the error reproduces in
an extension-free browser or in the tracked Pilot script bundle.
```

**Cross-references**:

```txt
docs/v3.5.6/
docs/v3.5.9/
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-2E-REPORT.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
```

### 42. Token Architecture Refactor

- **Bucket**: A — Architecture / token system
- **Status**: **Resolved / closed at v3.6.1.**
- **Priority**: High post-Pilot
- **Target**: v3.6.1 — **DONE**
- **Source**: v3.6.0 Pilot Phase 3 architectural lessons and token-layering consultation

**Scope**:

- Split the current token architecture into explicit layers:
  `md-ref`, `md-sys.light`, `md-sys.dark`, `wp-preset.bridge`,
  `wp-custom.bridge`, and `comp` consumption.
- Keep `md-ref` as primitive source and `md-sys` as runtime semantic source.
  Do not let `theme.json` hex values become the real design-system source.
- Project M3 sys tokens into WordPress preset variables for editor-facing
  semantic values.
- Add a `wp-custom` bridge for theme-managed non-picker values such as
  state-layer opacity, shape, motion, and elevation only where WordPress-managed
  override is useful.
- Add dark-mode infrastructure through sys-layer remapping, not ref rewriting.
- Apply the refactor across both `axismundi-lab` and `axismundi-pilot`.
- Update `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` after the
  implementation validates the model.

**Non-goals**:

- Do not implement the Interpreter Plugin in this cycle.
- Do not add HCT generation; that remains BACKLOG #21.
- Do not perform v4.0 directory restructure; that remains BACKLOG #36.

**Cross-references**:

```txt
docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-1-CLOSE.md
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-3-VISUAL-QA.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
BACKLOG #20
BACKLOG #21
```

**v3.6.1 close evidence (2026-05-20)**:

- Token layers split across lab, Pilot assets, and published styleguide mirror:
  `tokens.ref.css`, `tokens.sys.light.css`, `tokens.sys.dark.css`,
  `tokens.comp.css`, plus empty `tokens.css` shim.
- `wp-preset.bridge.css` and `wp-custom.bridge.css` added as downstream
  WordPress projections.
- `theme.json settings.custom.axismundi.*` contains 26 downstream-only
  `var(...)` leaves.
- Dark mode implemented as sys-layer swap; Pilot Light / Dark / Auto runtime
  validates through the real click path.
- Axis E/F/G permanently guard md-sys -> md-ref, bridge downstream refs, and
  theme.json custom downstream refs.
- BACKLOG #20 closed as part of this cycle.

### 43. WP core block specimen wall / full variation audit

- **Bucket**: B / D — WordPress binding QA
- **Status**: **Resolved / closed at v3.6.2.**
- **Priority**: Medium post-Pilot
- **Target**: v3.6.2 — **DONE**
- **Source**: v3.6.0 Pilot Phase 3 visual QA; user noted that listing all
  core blocks and style variations at once is faster than finding residual WP
  defaults one by one.

**Scope**:

- Create or generate a specimen surface containing the WordPress core blocks and
  block style variations the Pilot/theme is expected to style.
- Compare rendered computed values against M3 tokens and flag raw core defaults
  (`#f0f0f0`, `#ccc`, `rgb(50, 55, 60)`, native table borders, etc.).
- Route each finding to one of three places: core reset, M3 bridge mapping, or
  backlog/deferred.
- Use the specimen wall as an input to BACKLOG #41 full block bridge expansion.

**Known evidence**:

- v3.6.1 Phase 3 visual QA surfaced the table footer option on the Pilot
  pattern QA page (`http://localhost:8888/?page_id=10`): `.wp-block-table tfoot`
  keeps a native-looking `border-top: 3px solid currentcolor`, which reads too
  strong against the current M3 surface treatment. Route through the specimen
  wall before deciding whether the final fix belongs in a core reset, table
  block bridge, or broader BACKLOG #41 expansion.
- v3.6.1 Phase 3 visual QA also surfaced the core/button semantic boundary:
  Axismundi styleguide button specimens use `<button>`, while WordPress
  core/button commonly renders link-based markup. Underline leakage and button
  text drag/selection suppression can be fixed mechanically, but the larger
  decision is whether M3 button variants should be added as core/buttons styles,
  require a custom block for some semantics, or remain a bridge-layer mapping.
  Audit this with the full core block option/specimen wall before changing the
  Pilot button contract.

**Non-goals**:

- Do not claim full WordPress core block coverage until the audit is complete.
- Do not add custom blocks.
- Do not solve token architecture here; that is BACKLOG #42.

**v3.6.2 close evidence (2026-05-20)**:

- Added a version-controlled Tier 1 fixture:
  `products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html`.
- Added an idempotent importer:
  `tools/generators/build_pilot_specimen_wall.py`.
- Added a render gate:
  `tools/validators/validate_pilot_specimen_wall.js` and
  `npm run validate:specimen-wall`.
- Verified actual WordPress front-end rendering at:
  `http://localhost:8888/?pagename=axismundi-core-block-specimen-wall`.
- Covered the declared Tier 1 scope:
  11 / 11 block families, 26 / 26 classified entries, 0 unclassified.
- Phase 2 computed classification:
  no-action 20, reset 1, bridge 0, semantic-decision 5, backlog 0.
- Phase 3 visual QA catalog:
  backlog 3, reset 1, semantic-decision 2, bridge 3, no-action 1.
- Routed implementation inputs to BACKLOG #41 and follow-on coverage/editor
  compatibility to BACKLOG #44.
- Froze the current Tier 1 fixture as v3.6.2 coverage; Tier 2/3 and coverage
  gaps are not claimed by this close.

**v3.6.2 evidence docs**:

```txt
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-0-PLAN.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-1-REPORT.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
```

### 44. Specimen wall follow-on coverage + editor compatibility

- **Bucket**: B / D — WordPress binding QA / specimen methodology
- **Status**: Open - narrowed by v3.6.7 to mark/highlight, long-line code, deep pullquote, Material Symbols follow-on coverage, and validator hardening polish
- **Priority**: Low to medium post-v3.6.2
- **Target**: post-v3.6.7 follow-on coverage, or before the next specimen expansion
- **Source**: v3.6.2 Phase 3 visual QA

**Scope after v3.6.7**:

- Editor compatibility decision is closed by v3.6.7 Route C: keep the original
  front-end wall front-end-only and add `core-block-editor-smoke.html` as the
  editor-valid surface.
- Add mark/highlight coverage once the authoring path is chosen.
- Add long-line code coverage, or coordinate it with the #41 code bridge input.
- Add deeper quote/pullquote fixture coverage if #41 needs more semantic
  evidence.
- Track the Material Symbols font constraint surfaced during specimen QA as a
  separate icon-font/layout constraint, cross-referencing existing BACKLOG #14.
  Do not route separator variant visibility through the Material Symbols font
  issue unless later evidence proves a real font dependency.
- Keep validator hardening polish in this item while it remains tied to the
  split fixture validator surface.

**Non-goals**:

- Do not reopen the v3.6.2 Tier 1 #43 close.
- Do not implement #41 bridge/reset fixes.
- Do not decide the core/button semantic boundary here.

**Cross-references**:

```txt
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
BACKLOG #14
BACKLOG #41
BACKLOG #43
```

**v3.6.6 forward evidence (2026-05-21)**:

v3.6.6 Phase 1 and Phase 3 editor probes observed:

```txt
editor open console errors:           56
block validation console error count: 56
```

This remains #44 editor-valid fixture / editor compatibility work. v3.6.6 did
not repair fixture validity and did not claim an invalid-content fix.

**v3.6.7 close evidence (2026-05-21)**:

v3.6.7 closed the editor compatibility question by implementing Route C:
split the front-end computed evidence fixture from a new editor-valid smoke
fixture.

```txt
Front-end evidence fixture:
  products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
  slug: axismundi-core-block-specimen-wall
  local page: 29
  purpose: stable data-ax anchors for computed-style evidence

Editor-valid smoke fixture:
  products/reference-implementations/axismundi-pilot/fixtures/core-block-editor-smoke.html
  slug: axismundi-core-block-editor-smoke
  local page: 41
  purpose: WordPress-save-compatible core block editor smoke
```

Phase 3 close evidence:

```txt
Front-end wall:        HTTP 200 / console 0 / overflow 0 / Tier 1 11/11 / findings 0
Editor smoke FE:      HTTP 200 / console 0 / overflow 0 / sections 6 / buttons 5 / searches 2
Editor smoke editor:  iframe 1 / console 0 / block validation 0 / invalid UI 0 / recovery UI 0
Existing wall editor: iframe 1 / console 56 / block validation 56 / invalid UI 0 / recovery UI 0
```

The unchanged existing-wall `56 / 56` editor signal is intentionally retained
on the front-end-only evidence surface. The new editor smoke fixture supplies
the editor-valid `0 / 0 / 0 / 0` surface.

Remaining #44 scope after v3.6.7:

```txt
1. mark/highlight coverage
2. long-line code coverage
3. deep pullquote coverage
4. Material Symbols follow-on coverage / BACKLOG #14 cross-reference
5. validator hardening polish:
   - WP_ADMIN_USER / WP_ADMIN_PASS fallback for Playwright login
   - strict section count if the smoke fixture contract should freeze
   - less timing-sensitive editor settle wait if flakiness appears
   - generic tmp output directory name instead of phase1-specific naming
```

v3.6.7 did not enter BACKLOG #41's narrowed shared WordPress ripple runtime
packaging decision.

**v3.6.7 evidence docs**:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
```

### 46. Disabled ripple host authoring hygiene

- **Bucket**: D — Theme interaction / ripple authoring contract
- **Status**: Open - routed by v3.6.8 Phase 3 review
- **Priority**: Low
- **Target**: future ripple hygiene cycle
- **Source**: v3.6.8 Phase 3 review of Nav bar disabled destination

**Issue**:

`lab-nav-bar-pattern.html` includes a disabled button with
`data-ax-ripple="bounded"`. Phase 3 verified that the disabled host does not
create a ripple because disabled/pointer-event handling blocks activation, so
this is not a v3.6.8 defect.

**Decision pending**:

- Option A: remove `data-ax-ripple` from disabled hosts as an authoring hygiene
  rule.
- Option B: document provider tolerance of disabled hosts as an explicit
  Ripple v2 contract.

**Non-goals**:

- Do not patch v3.6.8 Navigation Core for this.
- Do not change `modules/ripple/*` without a dedicated Phase 0/1 route.
- Do not reinterpret the Nav bar component close evidence.

### 47. Popover provider menu-item-class logic extraction hygiene

- **Bucket**: D — Theme interaction / provider hygiene
- **Status**: Open - routed by v3.6.9 Phase 3 review
- **Priority**: Low
- **Target**: future provider hygiene cycle
- **Source**: v3.6.9 Phase 1/2/3 Menu / popover consumer cycle

**Issue**:

v3.6.9 closed Menu as a consumer of the existing `popover/` provider without
editing the provider. During that cycle, review confirmed that `popover/`
already contains menu-item-class logic as a pre-existing condition:

- `lab-popover.js` uses `.ax-menu__item` / `[role="menuitem"]` selectors for
  first-item focus, ArrowUp / ArrowDown / Home / End navigation, Tab dismiss,
  and item-click close.
- `lab-popover.css §3` overrides `.ax-menu__item:focus-visible` with a 3px
  outline for menu items focused by `lab-popover.js`.

This is not a v3.6.9 defect because v3.6.9 intentionally accepted the factual
provider contract and did not reopen provider implementation. It is a future
hygiene question because PROMOTION-CRITERIA §5.2 says infrastructure providers
must not absorb consumer-specific semantics.

**Decision pending**:

- Option A: leave the existing provider contract documented as-is, because
  `popover/` is an anchored Menu provider in practice.
- Option B: extract menu-item selectors / keyboard logic / focus outline into
  a clearer Menu-owned helper while preserving `popover/` for anchor,
  position, dismiss, focus restore, and viewport collision.

**Non-goals**:

- Do not reopen v3.6.9 Menu close evidence.
- Do not edit `modules/popover/*` without a fresh Phase 0/1 route.
- Do not fold this into BACKLOG #46 disabled ripple hygiene.

### v3.6.10 Wave 2B-1 Form close evidence

v3.6.10 closed the first Wave 2B slice by implementing Route B, Form Controls
Core:

```txt
Checkbox #18: DONE
Radio #19:    DONE
Switch #20:   DONE
```

Close evidence:

```txt
Modules added:
  modules/checkbox/
  modules/radio/
  modules/switch/

Visual QA:
  3 modules x desktop/mobile x light/dark = 12 cells
  console errors 0 in all cells
  horizontal overflow 0 in all cells

Interactions:
  Checkbox: 10 inputs, 2 error specimens, indeterminate initial state and native click transition verified
  Radio: 2 fieldsets, 2 legends, 6 inputs, native same-name selection and arrow navigation verified
  Switch: 6 role=switch checkbox inputs, FormData participation and Space toggle verified

Validation:
  npm test PASS, Axis A-G all 1.000
```

`window.labCheckbox = { init }` is accepted as a small fixture
re-initialization convention for indeterminate examples. No BACKLOG item was
created for it because it remains fixture setup, not component runtime or a
provider surface.

Wave 2B remaining scope after v3.6.10:

```txt
Wave 2B-2: Dialog #26 / Sheet #27 runtime
Wave 2B-3: Date+Time #22+#23 PARTIAL completion
Wave 2B-4: Actions consumers #5 / #7 / #8
```

Lock 5 was promoted in v3.6.10 after six diagnostic-first cycles with no P1/P2
close defects, fence violations, lock violations, or provider/baseline drift.

### v3.6.11 Wave 2B-2 Dialog / Sheet close evidence

v3.6.11 closed the second Wave 2B slice by implementing Route A, Dialog + Sheet
module-local runtime:

```txt
Dialog #26: DONE
Sheet #27: DONE
```

Close evidence:

```txt
Dialog module:
  native <dialog>.showModal() owns modal semantics and focus containment
  lab-dialog.js owns trigger wiring, scrim sync, close paths, initial focus,
  and focus restoration
  real pointer backdrop click -> native dialog backdrop path
  programmatic .modal-scrim click -> defensive scrim path
  no double-fire observed

Sheet module:
  custom .sheet host with role=dialog and aria-modal=true
  lab-sheet.js owns .is-open state, local focus containment, Escape, scrim,
  close-button dismissal, and focus restoration
  bottom and side modal variants verified
  drag-to-dismiss intentionally deferred

Visual / interaction QA:
  2 modules x desktop/mobile x light/dark = 8 clean cells
  console errors: 0
  horizontal overflow at 390px: 0
  Dialog basic + full-screen interactions PASS
  Sheet bottom + side interactions PASS
```

Files added:

```txt
products/reference-implementations/axismundi-lab/modules/dialog/
products/reference-implementations/axismundi-lab/modules/sheet/
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-0-PLAN.md
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-1-REPORT.md
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-2-REPORT.md
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-3-VISUAL-QA.md
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-5-CLOSE.md
```

Wave 2B remaining scope after v3.6.11:

```txt
Wave 2B-3: Date+Time #22+#23 PARTIAL completion
Wave 2B-4: Actions consumers #5 / #7 / #8
Wave 2B-2 follow-on: Sheet drag-to-dismiss enhancement note, routed through
  ROADMAP / CURRENT-STATE / NEXT-SESSION rather than a new BACKLOG item
```

Backdrop follow-on note:

```txt
If a future baseline cycle changes .dialog::backdrop from transparent to a
visually styled layer, revisit native backdrop / external .modal-scrim layering.
```

## Pre-Pilot classification snapshot (v3.5.18)

This snapshot classifies open items before v3.6.0 Pilot entry. It is routing
metadata, not closure.

| Bucket | Items |
|---|---|
| Pilot-before | None currently. If `blocks.html` / `prose.html` verification surfaces a blocker, update this row before v3.6.0. |
| Post-Pilot | #2 Avatar size tokens; #3 Floating toolbar selected color; #19 Date Picker Grid Navigation A11y; #29 Card behavior patterns; #30 Extended FAB behavior patterns; #34 residual module picker/dialog UX; #35 root index Korean version and language toggle; #39 blocks/prose shell consistency; #41 WordPress block bridge state and ripple enhancement; #42 Token Architecture Refactor; #43 WP core block specimen wall / full variation audit |
| Plugin territory | #6 Monotone SVG theming plugin concept; #21 M3 Interpreter Plugin separation; #38 Carousel plugin extraction |
| Deferred / ongoing | #5 WordPress logo styleguide specimen; #7 Search bar leading icon known delta; #14 Material Symbols ligature layout shift; #16 Tooltip delay / touch long-press; #18 Snackbar class naming; #20 Theme-only color customization policy; #22 `data-theme="auto"` model; #23 Elevated Chip variants; #36 v4.0 directory restructure; #37 GitHub Pages dogfooding; #40 Modularized component CSS separation |


## Closed items

| # | Title | Closed at | Resolution summary |
|---:|---|---|---|
| 45 | Wave 2A-2 Menu / popover consumer closure | v3.6.9 | Closed by Route A. Added `modules/menu/` with lab CSS, pattern HTML, and SPEC/MEASUREMENT/RUNTIME/WP docs. Menu consumes existing `popover/` and `ripple/` providers unchanged; no `lab-menu.js`; `components.css`, provider modules, WordPress/Pilot files, and prior Wave 2A modules unchanged. Phase 3 verified 4 visual cells console 0 / overflow 0, 3 live popover surfaces, forbidden-ancestor non-open, 10 enabled bounded ripple hosts, 2 disabled no-ripple hosts, and submenu deferred. |
| 33 | List M3 full token coverage extension | v3.5.13 | Closed by Wave 1 cleanup Lane B. LIST-SPEC / MEASUREMENT gained the full-token extension; `components.css §26` now covers 3px focus indicator, selected-disabled 38% on-surface mix, transparent segmented wrapper with surface item containers, expand trailing icon container surface-container mapping, and no-wrap trailing supporting time. Drag/reorder and expand runtime remain deferred. |
| 32 | Button family size variants — XS/S/M/L/XL coverage cycle | v3.5.13 | Closed by Wave 1 cleanup Lane A. `tokens.css` gained Button family size tokens; `components.css §2/§3/§28` now maps Button, Icon button, and Button group XS/S/M/L/XL variants. Playwright verified 32/40/56/96/136 size matrix and default no-size Button remains 40px. |
| 27 | data-ax-ripple opt-in introduction | v3.5.6 | Closed by the Ripple v2 stable declarative contract. `[data-ax-ripple]` is now the public authoring path, with bounded/unbounded values and `window.axRipple.attach/detach/refresh` for imperative attachment. The previous HOST_SELECTOR allowlist remains transitional compatibility only. |
| 25 | Ripple v2 contract — Material Web alignment | v3.5.6 | Closed by `lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md` plus v2 `lab-ripple.css`, `lab-ripple.js`, and `lab-ripple-pattern.html`. The contract aligns with Material Web concepts without importing `<md-ripple>`: bounded/unbounded variants, `--md-ripple-*` bridge tokens, `data-ax-ripple`, pointer-only activation, reduced-motion behavior, and `window.axRipple.attach/detach/refresh`. `components.css §0` state-layer foundation remains unchanged. |
| 26 | Matrix row #36 allowlist correction | v3.5.4 | Closed by `docs/v3.5.0/MODULE-STATUS-MATRIX.md` v3.5.4 amendment. Row #36 `ripple/` now uses state-aware buckets. v3.5.6 later refined those buckets after Ripple v2: FAB family and Card action surfaces promoted to TARGET, Nav bar/Nav rail verified as bounded TARGET, and the remaining inferred surfaces stay CANDIDATE. |
| 24 | Matrix consumer-state column | v3.5.4 | Closed by adding consumer-state vocabulary and provider-specific sub-table strategy to `MODULE-STATUS-MATRIX.md`. States are CURRENT / TARGET / CANDIDATE / NONE plus conditional variants. Button, Icon button, and Card SPEC docs gained short v3.5.4 alignment notes. Chip v3.4.9 remains a legacy audit predating the vocabulary; its ripple TARGET state is recorded in the canonical matrix only. |
| 15 | Snackbar Runtime Module | v3.4.10 | Closed by `lab/modules/snackbar/`. Runtime layer added (queue, timeout, hover/focus pause, separated live announcement via single role=status region, fixed positioning, reduced motion, coarse-pointer close hit-area expansion). Baseline `components.css §14 Snackbar` UNCHANGED. 5 hard rules locked (visible root never aria-hidden, hover/focus pause, real buttons, no role=alert default, text-only live region). Phase 0 inventory correction explicitly recorded — baseline §14 actually styles 5 base selectors with full state-layer Pattern A. Closes the transient/feedback surface trio: popover (v3.4.5) + tooltip (v3.4.6) + snackbar (v3.4.10). |
| 4 | Chip Measurement Audit | v3.4.9 | Closed by `lab/modules/chip/docs/CHIP-MEASUREMENT-AUDIT.md`. M3 §14 spec table compared against Axismundi baseline; 19 of 20 measurement properties token-driven; 2 private literals (`--_chip-h: 32px`, `--_chip-icon: 18px`) with documented rationale; Phase 2 input chip close affordance dimensions recorded (24×24 button + ::before pseudo-element 44×44 hit area on coarse pointer). |
| 5 | WordPress logo styleguide specimen | v3.4.4 | Embedded in `icon-system-pattern.html §SVG icons` as currentColor-normalized reference specimen. Trademark caption + source-link required. Seed at `compare/brand-assets-research/`. |
| 7 | Search bar leading icon (known delta from v3.4.3) | v3.4.4 | Converted to Material Symbols `search` glyph via conversion-shape variant B (slot class on wrapping span). |
| 8 | Module pattern `role="group"` → `role="radiogroup"` cohort fix | v3.4.3.1 | Resolved as part of the v3.4.3.1 visual QA patch across all 4 module patterns. |
| 9 | Module pattern theme switcher — `data-theme-button` → `data-theme-set` cohort fix | v3.4.5 | Renamed across 4 existing module pattern HTMLs (carousel / ripple / search-expansion / icon-system), 3 occurrences each. New `lab-popover-pattern.html` uses `data-theme-set` from authoring. Cohort-fix sibling of item 8 — same shape, same release pattern. |
| 12 | Theme switcher `syncSwitchers()` selector mismatch — `.ax-theme-switcher` only finds archive markup | v3.4.5.1 | One-line `theme.js` fix: selector changed to `.sg-theme, .ax-theme-switcher` (defensive — both accepted). Third entry in the cohort-fix family (8 role → 9 attribute → 12 class). Visual QA Gate follow-up to v3.4.5. |

(Open items above retain their full entries until resolved. When an
item is closed, only the summary row is preserved here.)

## See also

- `CHANGELOG.md` — per-release record
- `ROADMAP.md` — sequence of planned releases
- `lab/docs/ARCHITECTURE-BOUNDARIES.md` — charter clauses cited above
- `lab/modules/icon-system/docs/INLINE-SVG-INVENTORY.md` — pre-conversion SVG inventory
- `compare/brand-assets-research/README.md` — brand asset policy + URL inventory
