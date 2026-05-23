# v3.6.14 Wave 3 Closure - Inputs / Feedback Final - Phase 0 Plan

Date: 2026-05-23

Phase: 0 - Plan

Status: Proposed, awaiting review

## Verdict

Recommended next diagnostic target:

```txt
v3.6.14 Wave 3 Closure - Inputs / Feedback Final
```

Candidate rows:

```txt
Slider #21
Loading #30
Progress #31
```

This is a plan-first cycle. Do not implement in Phase 0.

Phase 1 must determine whether these three remaining TODO rows can close in
one bounded Wave 3 cycle or whether the category split should produce smaller
implementation slices.

## Current Baseline

```txt
local HEAD:      ff9b0a0
origin/main:     ff9b0a0
branch:          main
push status:     pushed before Phase 0
last close:      v3.6.13 Wave 2B-4 Actions Consumers
working tree:    clean before Phase 0
```

v3.6.13 closed Wave 2B and left the matrix at:

```txt
DONE       28
PARTIAL     0
TODO        3
RECORD      3
```

The remaining TODO rows are all Wave 3 rows in the v3.5.0 coverage map. They
are category-mixed:

| Row | Component | TOC Group | Category | Status | Target module | Dependency |
|---:|---|---|---|---|---|---|
| 21 | Slider | Inputs | Component Full-Spec | TODO | `slider/` | Independent |
| 30 | Loading | Feedback | Component Full-Spec | TODO | `loading/` | Independent |
| 31 | Progress | Feedback | Component Full-Spec | TODO | `progress/` | Independent |

The cycle name uses "Inputs / Feedback Final" so the category mixture is
visible. "Wave 3 Closure" is still accurate because all three rows share Wave 3
classification.

## Phase Cadence

Use the standard v3.6.x component cadence:

```txt
Phase 0 - Plan
Phase 1 - Diagnostic inventory and route selection
Phase 2 - Implementation, if approved after Phase 1
Phase 3 - Visual / interaction QA
Phase 5 - Mechanical close
```

Phase 4 is intentionally unused in this cadence. It remains reserved for
category-specific deeper QA when a cycle needs it; this cycle currently has no
known separate Phase 4 target.

## Lock 5 Compliance

Lock 5 requires diagnosis before implementation for plan-first cycles where the
route, failure mode, or boundary risk is not already known.

This Phase 0 identifies:

```txt
source inputs:             listed below
baseline boundaries:       components.css §20 Loading, §25 Slider, §27 Progress
provider boundaries:       no provider dependency expected
semantic boundaries:       native range input vs status/progressbar feedback
route buckets:             3-in-1 / 2+1 / 1+1+1
selected route:            Phase 1 chooses
rejected routes:           Phase 1 records with evidence
write scope:               conditional Phase 2 shapes below
fences:                    Files Not Expected To Change below
validation plan:           standard commands + Phase 3 Wave 3 visual QA
```

No safe-shortcut exception is used. Phase 1 diagnostic is mandatory.

## Source Inputs

Read according to `NEXT-SESSION.md §0` current order:

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #46 / #47 / #21 / #14
7-11. docs/v3.6.13/WAVE-2B-ACTIONS-PHASE-{5,3,2,1,0}*
12-67. earlier v3.6.x and v3.6.0 lesson sources as needed
```

Focused Phase 0 / Phase 1 reads:

```txt
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/
products/reference-implementations/axismundi-lab/modules/switch/
products/reference-implementations/axismundi-lab/modules/dialog/
products/reference-implementations/axismundi-lab/modules/sheet/
products/reference-implementations/axismundi-lab/modules/snackbar/
products/reference-implementations/axismundi-lab/modules/tooltip/
```

Prior module reads are only precedent checks:

- `switch/` for native input + custom visual module pattern.
- `dialog/` and `sheet/` for Feedback module docs with runtime boundaries.
- `snackbar/` and `tooltip/` for historically DONE Feedback provenance.

## Existing Local Surface

Module directory state:

```txt
missing: slider/
missing: loading/
missing: progress/
```

Baseline CSS evidence:

```txt
Slider:
  components.css Chunk F2 / formal §25
  .ax-slider
  .ax-slider__input
  .ax-slider__value
  vendor range pseudo-elements for WebKit and Firefox

Loading:
  components.css formal §20
  .ax-loading
  .ax-loading__svg
  .ax-loading__circle
  .ax-loading.is-contained
  .ax-loading.is-small

Progress:
  components.css Chunk G1 / formal §27
  .ax-progress-linear
  .ax-progress-circular
  .ax-progress-circular__svg
  .ax-progress-circular__track
  .ax-progress-circular__active
```

Styleguide anchors exist:

```txt
#components-slider
#components-loading
#components-progress
```

Styleguide specimens already exercise:

```txt
Slider:
  enabled native range input
  disabled native range input
  inline --_value examples

Loading:
  default spinner
  contained spinner
  small inline spinner in button context

Progress:
  linear determinate
  linear indeterminate
  circular determinate
  circular indeterminate
```

These are source evidence only. v3.6.14 must not edit `style-guide.html`.

## Baseline Primitive Mapping

Phase 1 must inventory whether the existing baseline primitives are sufficient
for lab module extraction:

| Component | Baseline primitives | Phase 1 question |
|---|---|---|
| Slider | `ax-slider`, `ax-slider__input`, `ax-slider__value` | Is lab-only JS needed to keep active fill / value output in sync, or is authored `--_value` sufficient for closure? |
| Loading | `ax-loading`, `ax-loading__svg`, `ax-loading__circle` | Are default / contained / small variants enough, and is reduced-motion evidence sufficient? |
| Progress | `ax-progress-linear`, `ax-progress-circular` | Are determinate and indeterminate linear/circular patterns enough, and is JS needed for determinate value sync? |

Do not add new baseline tokens or baseline selectors in Phase 2 unless Phase 1
returns for review. If token CSS or baseline CSS must change, Lock 1 / Lock 2
impact must be evaluated before implementation.

## Route Candidates

### Route A - 3-in-1 Wave 3 Closure

Close Slider #21, Loading #30, and Progress #31 in one cycle.

Benefits:

- Directly moves matrix TODO 3 -> 0.
- Reuses the v3.6.13 pattern of three fresh module directories in one cycle.
- Keeps Wave 3 closure atomic.

Risks:

- Category mixture can obscure Slider's native input concerns.
- Loading and Progress share visual indicator concepts, while Slider does not.
- Phase 3 matrix expands across three different semantic surfaces.

### Route B - 2+1 Split

Close Loading #30 and Progress #31 together, then Slider #21 separately.

Benefits:

- Feedback indicator pair shares motion / status / progressbar QA concerns.
- Slider receives a separate native range-input diagnostic.

Risks:

- Adds cadence overhead for only three remaining rows.
- Leaves the matrix with one TODO after the first slice.

### Route C - 1+1+1 Split

Close each row independently.

Benefits:

- Smallest implementation blast radius per cycle.
- Cleanest per-row Phase 3 evidence.

Risks:

- Highest process overhead.
- Likely unnecessary if Phase 1 confirms baseline primitives are sufficient.

Phase 0 does not select a route. Phase 1 must recommend Route A, B, or C with
specific evidence from baseline primitives, semantics, and QA scope.

## Preliminary Recommendation

Route A is the default candidate because all three rows are Independent
Component Full-Spec rows with existing baseline primitives and no provider
coupling. Route B remains the main fallback if Phase 1 finds Slider requires
distinct runtime or cross-browser range-input QA beyond the Feedback indicator
surface.

## Expected Phase 2 Shape

If Phase 1 approves Route A, expected new files are:

```txt
products/reference-implementations/axismundi-lab/modules/slider/
  lab-slider.css
  lab-slider.js              optional, only if Phase 1 proves value sync is needed
  lab-slider-pattern.html
  docs/SLIDER-SPEC-AUDIT.md
  docs/SLIDER-MEASUREMENT-AUDIT.md
  docs/SLIDER-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/loading/
  lab-loading.css
  lab-loading-pattern.html
  docs/LOADING-SPEC-AUDIT.md
  docs/LOADING-MEASUREMENT-AUDIT.md
  docs/LOADING-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/progress/
  lab-progress.css
  lab-progress.js            optional, only if Phase 1 proves value sync is needed
  lab-progress-pattern.html
  docs/PROGRESS-SPEC-AUDIT.md
  docs/PROGRESS-MEASUREMENT-AUDIT.md
  docs/PROGRESS-WP-MAPPING.md
```

If any module owns extracted local JS, add a RUNTIME audit doc for that module.
Do not create a RUNTIME audit doc for CSS-only specimens.

## Files Not Expected To Change

Phase 2 should not edit these without returning for review:

```txt
AGENTS.md
CLAUDE.md
NEXT-SESSION.md
CURRENT-STATE.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/modules/ripple/
products/reference-implementations/axismundi-lab/modules/popover/
products/reference-implementations/axismundi-lab/modules/icon-system/
products/reference-implementations/axismundi-pilot/
styleguide/
tools/validators/
tools/generators/
```

Phase 5 may update release mechanics docs after implementation and review:

```txt
BACKLOG.md
CHANGELOG.md
CURRENT-STATE.md
ROADMAP.md
NEXT-SESSION.md
docs/v3.6.14/*-PHASE-5-CLOSE.md
```

## Tracked-Copy Impact

Expected Phase 2 has no tracked-copy impact because it should add lab module
files only and leave token CSS unchanged.

If Phase 1 discovers a need to modify token CSS, generated mirror CSS, Pilot
asset bridge copies, or styleguide mirror artifacts, stop and return for review
before implementation. That would trigger tracked-copy coordination and Lock 1
/ Lock 2 evaluation.

## Lock Impact

| Lock | Expected impact |
|---|---|
| Lock 1 - Axis G theme.json custom guard | No impact if token / Pilot files remain unchanged. |
| Lock 2 - Axis E md-sys color guard | No impact if token files remain unchanged. |
| Lock 3 - core/button semantic routing | No impact; WordPress bridge work is out of scope. |
| Lock 4 - semantic row distinction | Preserved by treating Slider, Loading, and Progress as distinct rows even if Route A closes them together. |
| Lock 5 - diagnostic-first | Active; Phase 1 diagnostic required before implementation. |

v3.6.14 would be the fourth clean post-promotion Lock 5 self-application if it
closes without safe-shortcut exception.

## Phase 1 Questions

Phase 1 must answer:

1. Are `Slider #21`, `Loading #30`, and `Progress #31` still the only remaining
   TODO component rows after v3.6.13?
2. Are the existing baseline primitives sufficient for lab module extraction?
3. Does Slider require `lab-slider.js` for active fill / value label sync, or
   can the module remain authored-CSS-only?
4. Does Progress require `lab-progress.js` for determinate value sync, or can
   examples use authored `--_value` / ARIA attributes?
5. Does Loading require a RUNTIME audit, or is reduced-motion CSS evidence
   enough for a Component Full-Spec close?
6. Which route should be selected: 3-in-1, 2+1, or 1+1+1?
7. Which validation and visual QA matrix is required for the selected route?

## Non-Goals

- Do not edit baseline `components.css`.
- Do not edit `style-guide.html` or public styleguide mirror files.
- Do not change token architecture, token files, or tracked copies.
- Do not edit Pilot theme files or WordPress bridge files.
- Do not edit provider modules.
- Do not reopen v3.6.13 Actions Consumers.
- Do not start VS Code diagnostics sweep in this cycle unless explicitly
  re-routed after v3.6.14 close.
- Do not start BACKLOG #21, #41, #44, #46, or #47 implementation.

## Validation Plan

Phase 1 is read-only and should need only:

```txt
git status --short --branch
git diff --check
```

Phase 2 / Phase 3 should run the standard validation set, adjusted for any JS
files actually created:

```txt
node --check products/reference-implementations/axismundi-lab/modules/slider/lab-slider.js
node --check products/reference-implementations/axismundi-lab/modules/progress/lab-progress.js
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

Skip `node --check` for optional JS files that Phase 1 rejects.

Phase 3 should use a repository-root localhost server for module pattern pages,
matching the v3.6.13 convention, and should verify desktop / mobile x light /
dark cells for each implemented module, with:

```txt
console errors 0
4xx assets 0
horizontal overflow 0
theme switch path works
reduced-motion evidence for Loading / Progress
native range keyboard / value evidence for Slider
progressbar ARIA evidence for Progress
status semantics evidence for Loading
```

## Review Gate

Submit this Phase 0 plan for Opus review before Phase 1.

Expected review outputs:

```txt
P1 / P2 / P3 findings
GO / NO-GO / APPROVE WITH NOTES
route-split concerns, if any
```

Implementation is blocked until Phase 1 selects a route and the user approves
the transition to Phase 2.
