# Date/Time Picker Module Audit — v3.4.7


> **v3.4.8 Deletion Notice (added retrospectively):**
> The GPT Codex-generated benchmark source files referenced throughout this
> document (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`,
> and `style-guide-benchmark.html`) were **removed from the active tree at
> v3.4.8 Benchmark Surface Deletion** after the v3.4.7 extraction into
> `lab/modules/date-time/`. Line ranges and quoted file paths in this
> document are HISTORICAL references — they describe where the source
> code lived during extraction, not where it lives now. Provenance is
> preserved in this audit document, CHANGELOG.md / ROADMAP.md, the
> v3.X.Y zip freezes, and git history.

> **v3.6.12 Completion Addendum:**
> This document remains the v3.4.7 extraction and provenance audit. The
> bounded Date grid a11y completion and modern Wave 2 module closure evidence
> now live in `DATE-TIME-SPEC-AUDIT.md`,
> `DATE-TIME-MEASUREMENT-AUDIT.md`, `DATE-TIME-RUNTIME-AUDIT.md`, and
> `DATE-TIME-WP-MAPPING.md`. BACKLOG #19 is no longer treated as a fully open
> inherited gap in current HEAD; v3.6.12 closes the remaining bounded Date grid
> items while preserving Time picker, provider, baseline, plugin, and mobile
> variant boundaries.

> Bucket: D (theme interaction — runtime layer)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1 (four layers), §4 (theme can / plugin should), §5 (forbidden ancestor list)
>
> Phase 1 — skeleton. Phase 2/3 sections (final TL;DR + verdict) to be
> completed during implementation.

## Critical framing — what this module IS and IS NOT

```
Date/Time Picker is NOT a Beer CSS-derived extraction.

The visual primitives are already Axismundi-native baseline components.
v3.4.7 extracts and audits the GPT Codex-generated benchmark interaction
layer into a bounded lab module.
```

한글 요약:

```
Date/Time Picker는 Beer CSS 기반 추출물이 아니다.
시각 primitive는 이미 Axismundi-native baseline에 있으며,
v3.4.7은 GPT Codex로 생성된 benchmark interaction layer를
lab module로 격리하는 작업이다.
```

This distinction matters for three reasons:

1. **Provenance accuracy** — the previous five lab modules (carousel v3.3.2, ripple v3.3.3, search-expansion v3.3.4, popover v3.4.5, tooltip v3.4.6) were extracted under the `docs/BEER-CSS-INTAKE.md` 9-rule contract. The date/time picker interaction is NOT covered by that contract — the contract's "Beer CSS interaction-module family" closed at v3.4.6 tooltip.
2. **Audit doc structure** — there is no `data-tooltip → aria-describedby` style of 1:1 "missing a11y attribute fix" to lift from a third-party reference. The interaction code originates from a project-internal benchmark prototype, and v3.4.7 records the prototype's existing accessibility state without grafting external patterns onto it.
3. **5-criterion verdict tone** — the verdict cannot be a clean "PASS as a lab module" like tooltip. The date picker carries a substantial inherited a11y gap (grid navigation pattern absent), so the verdict explicitly partitions: **PASS as extraction, deferred for full WAI-ARIA Date Picker a11y pattern**.

This module is a lab module in v3.4.7, NOT a baseline interaction layer. Baseline visual specimens at `style-guide.html#components-date-picker` and `#components-time-picker` are explicitly labeled *"Visual structure only. Actual calendar logic is the author's responsibility"* and remain visual-only.

## TL;DR

```
Functions extracted    [Date subsection]: enableDateBenchmarks (363 lines) + 1 nested helper (parseInputDate)
                       [Time subsection]: enableTimeBenchmarks (321 lines)
                       Total interaction code:
                          benchmark-interactions.js L921-L1604 = 684 lines
                          benchmark-interactions.css L619-L1046 = 428 lines
                          Combined: 1,112 lines
Code reimplemented in  lab/modules/date-time/
                       ├── lab-date-time.js          805 lines (30.0 KB)
                       ├── lab-date-time.css         452 lines (10.0 KB)
                       ├── lab-date-time-pattern.html 359 lines (20.0 KB)
                       └── docs/DATE-TIME-AUDIT.md   ~480 lines (this file)
Style-guide changes    NONE to .ax-date-picker / .ax-time-picker primitives
                        in components.css §33 (~212 lines, L5453+) and
                        §34 (~variable, L5665–EOF). Baseline specimens
                        at style-guide.html L1550–L1726 unchanged.
benchmark mark-up      JS: 2 EXTRACTED markers (large block above
                          enableDateBenchmarks L919, short cross-ref above
                          enableTimeBenchmarks L1342)
                       CSS: 1 EXTRACTED marker above the combined date+time
                          phase sections
Benchmark surface      style-guide-benchmark.html UNTOUCHED at v3.4.7.
                        3589 lines preserved. Date-picker section
                        L1651-L1839 (189 lines) and time-picker section
                        L1840-L1989 (150 lines) remain as historical
                        reference. Surface cleanup is v3.4.8.
Lineage                GPT Codex-generated benchmark prototype.
                       NOT Beer CSS.
                       NOT covered by docs/BEER-CSS-INTAKE.md contract.
A11y carry-over        Inherits benchmark's existing partial a11y level.
                       Critical gap (WAI-ARIA Date Picker grid pattern)
                       deferred to BACKLOG #19.
Minimum safety fixes   1. Module-scoped IIFE wrapper
                       2. Forbidden-ancestor bail-out (.prose,
                          .wp-block-post-content, .entry-content,
                          [contenteditable])
                       3. Public init API (window.labDateTime)
                       4. EXTRACTED markers in benchmark source
Static Visual QA Gate  PASS — 40 data-attribute selectors verified
                       (39 static + 1 template-literal resolved at runtime);
                       49 structural elements 100% present; 14 helper
                       functions all defined locally; theme.js contract
                       satisfied; 0 actual contract mismatches.
Validator              1.000 / 1.000 / 1.000 / 1.000 PASS
                       (A schema / B theme / C css / D runtime)
Publish surface        stylesheets count 13 → 14 (+lab-date-time.css)
                       Total publish surface 20 → 21 files
                       lab-date-time.js and -pattern.html intentionally
                       NOT in publish (lab-internal only).
```

## §1 — Critical framing (also see top of this file)

The two key facts to keep visible throughout this audit:

1. **Visual primitives are baseline-already**. `components.css` §33 Date picker (~212 lines, 9 classes: `.ax-date-picker`, `.ax-date-picker__header`, `.ax-date-picker__headline`, `.ax-date-picker__supporting`, `.ax-date-picker__nav`, `.ax-date-picker__nav-label`, `.ax-date-picker__weekdays`, `.ax-date-picker__grid`, `.ax-date-picker__cell`) and §34 Time picker (9 classes: `.ax-time-picker`, `.ax-time-picker__supporting`, `.ax-time-picker__inputs`, `.ax-time-picker__field`, `.ax-time-picker__separator`, `.ax-time-picker__period`, `.ax-time-picker__period-input`, `.ax-time-picker__period-btn`, `.ax-time-picker__dial`) — total 18 baseline classes. **v3.4.7 does NOT modify these.**

2. **The benchmark interaction is its own BEM tree**. `benchmark-interactions.css` introduces `.ax-date-benchmark__*` (22 classes) and `.ax-time-benchmark__*` (~19 classes) — completely separate from the baseline `.ax-date-picker__*` / `.ax-time-picker__*` namespaces. This separation is fortunate: extraction can move the benchmark BEM tree wholesale into the lab module without renaming, and the baseline namespace remains untouched.

## §2 — Baseline / module / plugin split

Four layers per Charter §1, but only three of them carry date/time work:

```
BASELINE  styleguide layer
          components.css §33 / §34 primitives  (UNTOUCHED at v3.4.7)
          style-guide.html L1550-L1726 specimens (UNTOUCHED at v3.4.7)
          .ax-date-picker__* / .ax-time-picker__* (18 classes total)
          Purpose: visual specimens of M3 date picker / time picker shape

LAB MODULE  theme interaction layer (this module)
          lab/modules/date-time/lab-date-time.{css,js,-pattern.html}
          .ax-date-benchmark__* / .ax-time-benchmark__* (~41 classes)
          Purpose: GPT Codex-generated interactive prototype, isolated
                   for audit and Future-baseline-promotion-decision-pending

PLUGIN    federation / data binding layer (NOT touched at v3.4.7)
          - WordPress editor sidebar date control binding
          - Post meta date binding
          - Timezone normalization
          - Locale calendar systems (lunar / Hijri / Korean Sexagenary)
          - Recurring events
          - ActivityPub Event object emission
          - Range selection persistence
          Purpose: domain logic that depends on WordPress block editor,
                   post meta API, federation context, etc.
```

The Charter §4 line is sharper for date/time than for many other components because **timezone and locale are fundamentally federation/server concerns**, not theme concerns. A theme can render a calendar grid, but it cannot meaningfully determine "what is today" without timezone — and once timezone enters, the responsibility crosses into plugin/federation space.

## §3 — Inventory

### Interaction code source

| File | Range | Lines | Subject |
|---|---|---:|---|
| `benchmark-interactions.js` L921–L1283 | `enableDateBenchmarks` | 363 | Calendar render, month/year navigation, mode switch (input vs docked), date selection, range preview |
| `benchmark-interactions.js` L1284–L1604 | `enableTimeBenchmarks` | 321 | Hour/minute selection, dial render, format switch (12h/24h), period (AM/PM) toggle, input variant |
| **Total JS** | | **684** | |
| `benchmark-interactions.css` L619–L840 | `/* Date picker benchmark phase */` | 222 | `.ax-date-benchmark__*` styles + state modifiers (`.is-selected`, `.is-range-preview`) |
| `benchmark-interactions.css` L841–L1046 | `/* Time picker benchmark phase */` | 206 | `.ax-time-benchmark__*` styles + state modifiers (`.is-24h`, `.is-active`, `.is-inner`, `.is-selected`) |
| **Total CSS** | | **428** | |
| **Combined interaction layer** | | **1,112** | |

### Benchmark markup (in `style-guide-benchmark.html`)

| Section | Range | Lines |
|---|---|---:|
| `#components-date-picker` (benchmark) | L1651–L1839 | 189 |
| `#components-time-picker` (benchmark) | L1840–L1989 | 150 |
| **Total** | | **339** |

This markup is NOT moved at v3.4.7. The lab module pattern HTML re-authors its own demo markup. `style-guide-benchmark.html` retirement is scheduled at v3.4.8 *Benchmark Surface Cleanup*.

### Baseline (untouched)

| File | Range | Subject |
|---|---|---|
| `components.css` §33 | L5453 → ~L5665 (~212 lines) | `.ax-date-picker__*` primitive (9 classes) |
| `components.css` §34 | L5665 → EOF | `.ax-time-picker__*` primitive (9 classes) |
| `style-guide.html#components-date-picker` | L1550–L1645 | 96 lines static specimen — *"Visual structure only. Actual calendar logic is the author's responsibility."* |
| `style-guide.html#components-time-picker` | L1646–L1726 | 81 lines static specimen — *"Visual structure only. Input variant uses real `<input type="text" inputmode="numeric">` for typed entry."* |

### Comparison to previous extractions

| Module | JS lines | CSS lines | Total | Functions |
|---|---:|---:|---:|---|
| Tooltip (v3.4.6) | 58 | (primitive-only) | 58 | 3 |
| Popover (v3.4.5) | 138 | ~150 | ~288 | 6 |
| Carousel (v3.3.2) | ~200 | ~250 | ~450 | (varies) |
| **Date/Time (v3.4.7)** | **684** | **428** | **1,112** | (mostly inline; 1 named nested helper found) |

Date/time is **the largest interaction extraction by an order of magnitude** vs tooltip. v3.4.7 scope discipline is therefore essential — no a11y redesign, no production-readiness chase, no WordPress editor binding speculation. Pure extraction + audit + provenance.

## §4 — A11y inherited gaps (carry-over policy)

### What the benchmark interaction *already* has

| Attribute / handler | Date | Time | Notes |
|---|:---:|:---:|---|
| `role` attribute set | 1× | 2× | Limited set of explicit roles |
| `aria-selected` | 1× | 2× | Marks selected day / hour |
| `aria-pressed` | 1× | 3× | Used on dial-like / toggle buttons |
| `aria-label` | 1× | 0× | One labeled control |
| `aria-modal` / `aria-labelledby` | 0× | 0× | Picker surface not marked as dialog |
| `tabindex` | 1× | 0× | One roving tabindex usage |
| `event.key` handler | 3× | 2× | Some keyboard listening |
| `"Enter"` | 0× | 1× | Time input commits on Enter |
| `"Escape"` | 1× | 1× | Both pickers dismiss on Escape |
| `"Arrow*"` | **0×** | **0×** | **Critical gap — see below** |
| `"Home"` / `"End"` | **0×** | **0×** | Critical gap |
| `"PageUp"` / `"PageDown"` | **0×** | **0×** | Critical gap |
| `role="grid"` / `role="gridcell"` | **0×** | **0×** | Critical gap |
| `aria-current` | **0×** | **0×** | Critical gap (today's date not marked) |

### Carry-over policy — what v3.4.7 records, does NOT fix

v3.4.7 **preserves the existing benchmark a11y level exactly** (with minor refinements for forbidden-ancestor bail-out and module-scoped state — see §6 Extraction plan). It does NOT add the missing WAI-ARIA Date Picker grid navigation pattern.

Rationale, briefly:

1. **Extraction vs. redesign separation**. Adding the full grid pattern is a design decision (focus management on month boundary crossing, range-selection a11y interactions, today-cell announcement, screen-reader testing) that belongs in a dedicated phase.
2. **Audit doc precedent**. v3.4.6 tooltip *did* fix `aria-describedby` because it was a single defensive `setAttribute` / `removeAttribute` pair. Grid navigation is qualitatively different — it's an ongoing design conversation, not a missing one-line attribute.
3. **Honesty in the 5-criterion verdict**. By not fixing, the verdict explicitly partitions: *"PASS as extraction, deferred for full a11y pattern"*. By fixing partially, the verdict would be muddled.

The carry-over policy is routed to **BACKLOG #19 — Date Picker Grid Navigation A11y**, with full scope listed there.

### Minimum safety fixes (applied in v3.4.7) vs. deferred (BACKLOG #19)

This split is the single most important distinction to keep visible. It
answers "why was this fixed but that wasn't?" with one consistent answer:
**v3.4.7 is an extraction phase, not an a11y redesign phase.**

```
Minimum safety fixes IN v3.4.7:

  ✓ Module-scoped IIFE wrapper
      → no global helper leakage, runtime isolated from baseline

  ✓ Forbidden-ancestor bail-out
      → pickers inside .prose, .wp-block-post-content, .entry-content,
        [contenteditable] are skipped at init time (Charter §5,
        expanded selector vs. popover/tooltip)

  ✓ Lab-only runtime
      → style-guide.html does not load lab-date-time.js;
        baseline visual specimens stay visual-only

  ✓ EXTRACTED marker in benchmark source
      → Charter §EXTRACTED policy; originals retained verbatim

  ✓ Public init API
      → window.labDateTime.init() for explicit bootstrapping
        inside the pattern page

Deferred to BACKLOG #19 (v3.4.x Date/Time A11y Pass):

  × WAI-ARIA Date Picker grid structure
      role="grid" / role="row" / role="gridcell"
      aria-current="date" on today's cell
      aria-labelledby on grid → month/year nav label

  × Calendar keyboard navigation
      ArrowLeft / ArrowRight  → previous/next day
      ArrowUp / ArrowDown     → previous/next week
      Home / End              → start/end of week
      PageUp / PageDown       → previous/next month
      Shift + PageUp/Down     → previous/next year
      Enter / Space           → commit date

  × Roving tabindex
      Only the focused day cell has tabindex="0";
      all others tabindex="-1"

  × Month/year boundary announcement
      aria-live="polite" region for "now in <month> <year>"
      announcements when ArrowKey crosses month boundary

  × Time picker WAI-ARIA refinements
      possibly role="radiogroup" for hour/minute selection;
      proper labels on the dial; spinbutton vs. listbox decision
```

The rationale for the split is consistent across all five interaction-module
extractions to date:

- v3.4.6 tooltip *did* fix the missing `aria-describedby` because the fix
  was a single defensive `setAttribute` / `removeAttribute` pair with no
  ongoing design decisions.
- v3.4.7 date/time does NOT fix grid navigation because grid navigation is
  qualitatively different — it requires design decisions (focus management
  on month boundary crossings, range-selection a11y interaction, today-cell
  announcement, screen-reader testing) that belong in their own phase.

## §5 — What v3.4.7 does NOT fix

Locked at audit time. Recorded here so that 5-criterion verdict is unambiguous.

```
× WAI-ARIA Date Picker full keyboard navigation pattern
   (BACKLOG #19)

× Time picker WAI-ARIA refinements
   (any equivalent of #19 for time picker, possibly role="radiogroup"
    for hour/minute selection)

× Timezone normalization
   (server / plugin concern — Charter §4)

× Locale calendar systems
   (lunar / Hijri / Korean Sexagenary — plugin concern, separate phase)

× Recurring event date logic
   (ActivityPub Event object — federation phase)

× WordPress editor sidebar date control binding
   (separate plugin phase)

× Post meta date binding
   (server / plugin concern — Charter §4)

× Range selection persistence beyond UI preview
   (visual is in the benchmark; persistence is plugin-side)

× Mobile full-screen picker variant
   (M3 spec includes; benchmark doesn't have; v3.4.7 doesn't either)

× Date picker baseline promotion
   (separate Charter §1 decision — same posture as ripple v3.3.3,
    popover v3.4.5, tooltip v3.4.6: lab-only at extraction time)

× style-guide-benchmark.html cleanup
   (deferred to v3.4.8 Benchmark Surface Cleanup)
```

## §6 — Extraction plan (Phase 2)

### File layout

```
lab/modules/date-time/
├── lab-date-time.css         (428 lines from benchmark-interactions.css + minor pattern-page helpers)
├── lab-date-time.js          (684 lines from benchmark-interactions.js + module-scoped state + forbidden-ancestor bail-out)
├── lab-date-time-pattern.html (re-authored demo, single page covering both pickers)
└── docs/
    └── DATE-TIME-AUDIT.md    (this file)
```

### Extraction strategy

Unlike tooltip (3 small functions extracted) or popover (6 functions in a tight cluster), date/time is essentially **two IIFEs worth of code**: `enableDateBenchmarks()` and `enableTimeBenchmarks()`. The bodies are deeply nested closures with many small inline helpers (variables capturing `qs`, render functions defined inside event listeners, etc.). Strategy:

1. **Preserve closure structure** — do not flatten the inline closures into top-level functions. The benchmark's structure is intentional (encapsulation within one IIFE per picker); flattening risks introducing bugs.
2. **Module-scoped state object** — explicitly create a `state` object per picker root, replacing whatever ad-hoc state shape the benchmark uses. Document the state shape in the audit doc.
3. **No selector renames** — `.ax-date-benchmark__*` and `.ax-time-benchmark__*` BEM trees move verbatim to `lab-date-time.css`. This is a deliberate decision to preserve the audit trail; renames are a separate cleanup pass if ever needed.
4. **Pattern HTML rewrite** — `lab-date-time-pattern.html` is NOT a copy of the benchmark markup. It re-authors a tighter demo page covering the variants worth verifying (input variant, docked variant, time-of-day input, dial demo). Benchmark markup at `style-guide-benchmark.html` L1651–L1989 stays as historical reference until v3.4.8.

### benchmark `/* EXTRACTED */` marker (Phase 2 tail)

Two block comments, one each above L921 `enableDateBenchmarks` and L1284 `enableTimeBenchmarks`. Same shape as v3.4.5 popover and v3.4.6 tooltip markers, with one structural difference: the comment explicitly notes the GPT Codex provenance to distinguish from Beer-CSS-derived extractions.

```js
// scripts/benchmark-interactions.js
/* EXTRACTED: v3.4.7 → modules/date-time/lab-date-time.js
 *   enableDateBenchmarks (this section)
 * Originals retained for benchmark archival per Charter §EXTRACTED policy.
 *
 * NOTE: Date/Time Picker is NOT a Beer-CSS-derived extraction.
 *       The visual primitives are already Axismundi-native baseline
 *       components (components.css §33 / §34). v3.4.7 extracts the
 *       GPT Codex-generated benchmark interaction layer into a bounded
 *       lab module per docs/DATE-TIME-AUDIT.md.
 *
 *       This section is NOT covered by docs/BEER-CSS-INTAKE.md contract.
 *
 * Critical inherited a11y gap: full WAI-ARIA Date Picker grid navigation
 * is NOT wired here and is NOT added at v3.4.7. Carry-over policy applies.
 * See BACKLOG #19 — Date Picker Grid Navigation A11y.
 */
```

## §7 — Five-criterion promotion verdict

| # | Criterion | Status |
|---:|---|:---:|
| 1 | **JS-off fallback** — static `.ax-date-picker` and `.ax-time-picker` specimens in `style-guide.html` L1550-L1726 (visual structure only) remain readable; lab module interaction is progressive enhancement scoped to the pattern page | ✓ PASS |
| 2 | **M3 / state-layer compatibility** — `.ax-date-picker__*` (9) / `.ax-time-picker__*` (9) baseline primitives in `components.css` §33/§34 unchanged; lab module's `.ax-date-benchmark__*` / `.ax-time-benchmark__*` BEM tree uses M3 tokens verbatim from the benchmark CSS | ✓ PASS |
| 3 | **Reduced motion** — extraction adds no new motion; module's CSS inherits whatever reduced-motion gating the benchmark already had | ✓ PASS |
| 4 | **Keyboard / a11y (PASS with explicit carry-over)** — preserves existing benchmark a11y level exactly. Inherited WAI-ARIA Date Picker grid navigation gap (role="grid"/"gridcell", ArrowKey nav, Home/End, PageUp/PageDown, aria-current, roving tabindex) recorded as critical, **routed to BACKLOG #19**. NOT claiming production-ready date picker accessibility | ✓ PASS (carry-over) |
| 5 | **Prose / federation isolation** — `isInForbiddenAncestor()` checks `.prose`, `.wp-block-post-content`, `.entry-content`, `[contenteditable]` at picker init. Pattern HTML §3 includes negative demo with `[data-date-benchmark]` inside `.prose` (must NOT initialize) | ✓ PASS |

### Static Visual QA Gate

Run between Phase 2 and Phase 3 (audit doc §QA-2):

```
Static Visual QA Gate: PASS.

The lab-date-time pattern page passed static selector/markup contract
verification. Browser-side manual verification is recommended but NOT
blocking for v3.4.7 because the runtime was extracted from the existing
benchmark interaction layer and the module does not claim production-
ready accessibility. Any browser-only defects discovered after freeze
should be handled as v3.4.7.1 micro-fixes.

40 data-attribute selectors verified (39 static + 1 template-literal
resolved at runtime); 49 structural elements 100% present; 14 helper
functions all defined locally; theme.js contract satisfied; 0 actual
contract mismatches.
```

한글 요약:

```
정적 Visual QA Gate는 PASS다.
브라우저 수동 검증은 권장되지만 v3.4.7 freeze blocker는 아니다.
새로 발견되는 브라우저 전용 결함은 v3.4.7.1 micro-fix로 처리한다.
```

### Verdict

```
PASS as an interaction extraction module,
with critical inherited a11y gaps deferred.

v3.4.7 successfully isolates the GPT Codex-generated date/time
benchmark interaction into a bounded lab module. Static
selector/markup contract verification passed. The extraction
preserves the existing accessibility level and records the
WAI-ARIA Date Picker grid navigation gap explicitly. It does
NOT claim production-ready date picker accessibility.

Baseline visual primitives remain unchanged. Benchmark surface
cleanup is deferred to v3.4.8.

Lab module only — baseline promotion deferred as a separate
future Charter §1 decision (same posture as ripple v3.3.3,
popover v3.4.5, tooltip v3.4.6).
```

한글 요약:

```
v3.4.7은 GPT Codex로 생성된 date/time benchmark interaction을
lab module로 격리하는 작업으로 PASS다.

정적 selector/markup contract 검증을 통과했다.
다만 WAI-ARIA Date Picker grid navigation을 비롯한 critical a11y
gap은 의도적으로 그대로 보존하고 BACKLOG #19로 라우팅했다.
production-ready한 date picker 접근성을 주장하지 않는다.

Baseline 시각 primitive는 그대로다.
benchmark surface 정리는 v3.4.8로 미뤄졌다.
```

### Internal contract checks (for traceability — not part of the user-facing 5-criterion verdict)

- **Charter §1 / Bucket D**: confirmed — runtime sits in lab module layer, never touches baseline. Same posture as ripple v3.3.3, popover v3.4.5, tooltip v3.4.6.
- **Not Beer CSS**: confirmed — lineage is GPT Codex-generated benchmark prototype. Provenance recorded in audit doc top, lab-date-time.js header, benchmark EXTRACTED marker, audit doc §1, §6 extraction strategy decision 3, and §8 summary.
- **Reversibility (Charter EXTRACTED policy)**: confirmed — benchmark originals retained verbatim at `scripts/benchmark-interactions.js` L976-L1339 (date — after marker insertion shifted line numbers) and L1342-L1662 (time) with full block comment documenting the extraction.
- **a11y risk register / carry-over policy**: confirmed — 14 deferred items routed to BACKLOG #19 with explicit rationale. v3.4.7 applies only 5 minimum safety fixes that don't require design decisions.
- **Static contract verification**: confirmed — 9-point Visual QA Gate passed with 0 actual issues (1 template-literal false positive resolved).

## §8 — What this module does NOT do (summary)

- Does not promote date/time pickers to the baseline theme interaction layer (deferred — separate Charter §1 decision).
- Does not fix the WAI-ARIA Date Picker grid navigation pattern (BACKLOG #19).
- Does not redesign `.ax-date-picker` / `.ax-time-picker` visual primitives in `components.css`.
- Does not modify `style-guide.html` L1550–L1726 baseline specimens.
- Does not touch `style-guide-benchmark.html` (v3.4.8 Benchmark Surface Cleanup).
- Does not introduce a Beer CSS lineage claim (the previous Beer CSS interaction-module family closed at v3.4.6 tooltip).
- Does not implement timezone / locale calendar / recurring events / WordPress editor binding (Charter §4 plugin territory).
- Does not edit `NOTICE.md` / `LICENSE-MATRIX.md` (Public Surface / License Audit is its own v3.5.0-adjacent phase).
- Does not address `.snackbar` class naming inconsistency (BACKLOG #18, v3.5.0).
- Does not address text-input corpus ambiguity (BACKLOG #17, post-pilot).
