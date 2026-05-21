# v3.6.7 - WP Specimen Follow-On Coverage / Editor Compatibility - Phase 1 Report

Date: 2026-05-21

Phase: 1 - Editor Compatibility / Coverage Inventory

## Verdict

Phase 1 diagnostic inventory is complete. No implementation files were edited.

Selected route:

```txt
C. Split fixture strategy:
   Preserve the front-end wall and add a separate editor-valid fixture or
   reduced editor smoke fixture for editor compatibility checks.
```

Reason:

```txt
The current specimen wall's front-end value depends on stable data-ax specimen
anchors. Those same data-ax attributes are a primary source of editor block
validation mismatches because WordPress core block save output does not
preserve arbitrary data attributes on those core block wrappers.

Making the existing front-end wall fully editor-valid would either remove the
stable Playwright anchors or require changing the specimen methodology. A split
fixture keeps the proven front-end evidence surface intact and gives #44 an
editor-valid surface without pretending the two jobs are identical.
```

Recommended Phase 2:

```txt
No bridge/reset implementation.
Create a separate editor-valid specimen/smoke fixture and importer path, or
equivalent reduced editor-valid page, while preserving the current front-end
specimen wall unchanged.
```

## Phase 0 Review Carry-Forward

Opus Phase 0 review returned:

```txt
GO
P1: none
P2: none
P3: four non-blocking notes
```

P3 absorption:

```txt
P3-1 validate_theme_pilot.py fence:
  Explicitly recorded in this report. Do not edit
  tools/validators/validate_theme_pilot.py in v3.6.7.

P3-2 re-baseline 56/56 first:
  Completed as the first editor probe after rebuilding page 29.

P3-3 extension-free Phase 1 context:
  Completed with a fresh Playwright Chromium context.

P3-4 Route B fixture comment:
  Recorded as a rejected-route condition. If a later review chooses Route B,
  add a short fixture header comment stating intentional front-end-only status.
```

## Local Status

Phase 1 entry:

```txt
## main...origin/main [ahead 6]
```

No unstaged or untracked work was present before Phase 1 probes.

After rebuilding the specimen wall and running diagnostics:

```txt
git diff --name-only: empty
git status:           ## main...origin/main [ahead 6]
```

No implementation file was modified by Phase 1.

## Commands / Environment

Commands run:

```powershell
git status --short --branch
npx wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
npx wp-env run cli wp post list --post_type=page --fields=ID,post_name,post_title --format=csv
node <extension-free editor console probe>
node <extension-free validation mapping probe>
npm run validate:specimen-wall
git diff --name-only
```

Environment:

```txt
WordPress core: 7.0
Specimen page:  page 29
Front-end URL:  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
Editor URL:     http://localhost:8888/wp-admin/post.php?post=29&action=edit
Browser:        fresh Playwright Chromium context
```

Specimen page rebuild:

```txt
Updated specimen wall page 29:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
```

## Re-Baseline Result

The v3.6.6 editor warning count reproduces exactly.

```txt
editor iframe count:                    1
UI invalid-content text count:          0
UI Attempt recovery text count:         0
console/page error count:               56
block validation heuristic count:       56
unique console error prefixes:          6
```

Interpretation:

```txt
The 56/56 signal is stable in the current local WordPress 7.0 + rebuilt page 29
state. It is not a stale v3.6.6 observation and not an extension/content-script
artifact in this probe.
```

The 56 console errors represent duplicated logging of 28 unique block
validation mismatches:

```txt
raw validation error count: 56
unique mismatch count:     28
duplicate factor:           2
```

## Unique Mismatch Distribution

Unique mismatches by block type:

| Block type | Unique mismatches | Primary cause |
|---|---:|---|
| `core/group` | 14 | `data-ax-specimen-id` / `data-ax-specimen-variant`; some direct inner HTML |
| `core/list` | 1 | `data-ax-specimen-variant` on list wrapper |
| `core/table` | 3 | `data-ax-specimen-variant` plus WordPress expected `has-fixed-layout` table class |
| `core/button` | 5 | `data-ax-specimen-variant` on button wrapper |
| `core/separator` | 3 | `data-ax-specimen-variant` on separator element |
| `core/column` | 2 | direct paragraph HTML inside column instead of serialized inner paragraph block |
| **Total** | **28** | duplicated to 56 console errors |

Unique mismatches by specimen anchor or variant:

| Anchor / Variant | Count |
|---|---:|
| `core-paragraph` | 1 |
| `core-heading` | 1 |
| `list-segmented` | 1 |
| `core-list` | 1 |
| `core-quote` | 1 |
| `core-code` | 1 |
| `table-default` | 1 |
| `table-stripes` | 1 |
| `table-footer` | 1 |
| `core-table` | 1 |
| `button-fill` | 1 |
| `button-outline` | 1 |
| `button-tonal` | 1 |
| `button-elevated` | 1 |
| `button-text` | 1 |
| `core-buttons` | 1 |
| `core-search` | 1 |
| `separator-default` | 1 |
| `separator-inset` | 1 |
| `separator-middle-inset` | 1 |
| `core-separator` | 1 |
| `group-card-filled` | 1 |
| `group-card-elevated` | 1 |
| `group-card-outlined` | 1 |
| `core-group` | 1 |
| unanchored `core/column` x2 | 2 |
| `core-columns` | 1 |
| **Total** | **28** |

## Error Shape Evidence

Representative `core/group` mismatch:

```txt
Generated:
  <div class="wp-block-group ax-specimen-wall__item"></div>

Retrieved:
  <div class="wp-block-group ax-specimen-wall__item"
       data-ax-specimen-id="core-paragraph">...</div>
```

Representative `core/button` mismatch:

```txt
Generated:
  <div class="wp-block-button is-style-fill">
    <a class="wp-block-button__link wp-element-button" href="#button-fill">Filled</a>
  </div>

Retrieved:
  <div class="wp-block-button is-style-fill"
       data-ax-specimen-variant="button-fill">
    <a class="wp-block-button__link wp-element-button" href="#button-fill">Filled</a>
  </div>
```

Representative `core/separator` mismatch:

```txt
Generated:
  <hr class="wp-block-separator has-alpha-channel-opacity"/>

Retrieved:
  <hr class="wp-block-separator has-alpha-channel-opacity"
      data-ax-specimen-variant="separator-default"/>
```

Representative `core/table` mismatch:

```txt
Generated:
  <figure class="wp-block-table">
    <table class="has-fixed-layout">...</table>
  </figure>

Retrieved:
  <figure class="wp-block-table" data-ax-specimen-variant="table-default">
    <table>...</table>
  </figure>
```

Representative `core/column` mismatch:

```txt
Generated:
  <div class="wp-block-column"></div>

Retrieved:
  <div class="wp-block-column"><p>Column one.</p></div>
```

## Root Cause Classification

The validation errors are fixture-authorship errors relative to the editor
save contract, not front-end rendering errors.

Root causes:

```txt
1. Stable Playwright anchors are stored as data-ax-* attributes on core block
   wrappers that WordPress does not serialize from the block save function.

2. Some handcrafted inner HTML is nested directly inside container blocks
   instead of being represented as valid serialized inner blocks.

3. Table markup lacks the editor save output's has-fixed-layout table class.
```

What this is not:

```txt
Not a token enqueue failure:
  v3.6.5 already restored editor md-sys token landing.

Not a #41 bridge/reset failure:
  The front-end specimen render gate still passes and bridge/reset visual
  behavior is not the source of the editor validation messages.

Not a Material Symbols font issue:
  No captured validation message points to icon font loading, ligature layout,
  or Material Symbols markup.

Not plugin/custom-block territory:
  The mismatch is between a committed core block fixture and the WordPress core
  block editor save contract.
```

## Route Decision

### Route A - Editor-Valid Fixture Repair

```txt
The 56 console errors are caused by invalid committed block fixture markup.
Patch the fixture to match current WordPress 7.0 save output while preserving
the front-end specimen anchors and coverage.
```

Assessment:

```txt
Not selected as a single-fixture route.
```

Why:

```txt
The current front-end wall uses data-ax-specimen-id and
data-ax-specimen-variant as stable Playwright anchors. Those attributes are
central to validate:specimen-wall and to the v3.6.2 evidence method.

Removing or relocating them inside the same fixture would change the evidence
surface. Keeping them on core block wrappers keeps the editor validation
mismatch.
```

Route A remains possible only if review explicitly allows a breaking update to
the specimen anchoring method. Phase 1 does not recommend that.

### Route B - Front-End-Only Fixture Method

```txt
The current fixture is intentionally a front-end evidence surface and editor
validity would require distorting the specimen or losing coverage. Document the
method, keep the warning routed, and avoid a fake repair.
```

Assessment:

```txt
Not selected as the preferred final state.
```

Why:

```txt
It is true that the current wall is a strong front-end evidence surface.
However, #44 explicitly owns editor compatibility. Leaving the only specimen
surface front-end-only would preserve the problem rather than create a
repeatable editor-valid lane.
```

If Route B is later chosen:

```txt
Add a short HTML comment at the top of
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html:

  This fixture is intentionally front-end-only; see the v3.6.7 close doc.

This prevents future maintainers from misreading the editor warning as a
forgotten repair.
```

### Route C - Split Fixture Strategy

```txt
Preserve the front-end wall and add a separate editor-valid fixture or reduced
editor smoke fixture for editor compatibility checks.
```

Assessment:

```txt
Selected.
```

Why:

```txt
The current wall has two jobs that conflict:
  - front-end computed evidence needs stable data-ax anchors;
  - editor validation needs markup that matches core block save output.

Splitting the fixtures preserves both:
  - the existing wall remains the v3.6.2 front-end evidence surface;
  - a new editor-valid fixture can use WordPress-save-compatible markup and
    avoid data-ax attributes on core block wrappers.
```

Implementation implication:

```txt
Phase 2 should add a separate editor-valid specimen/smoke fixture and importer
or validator path. It should not change bridge CSS, theme.json, functions.php,
or the current front-end wall unless the reviewed Phase 2 route explicitly
needs a small generator/validator extension.
```

### Route D - Validator / Probe-Only Route

```txt
No fixture content patch yet. Add diagnostics that map validation errors by
block/selector first, then defer actual repair to a later #44 sub-cycle.
```

Assessment:

```txt
Not selected as final route; partially completed by Phase 1.
```

Why:

```txt
Phase 1 already maps the errors enough to choose a route:
  56 raw console errors
  28 unique mismatches
  root causes classified by block type and anchor shape
```

Phase 2 may still add reusable validator diagnostics as part of Route C, but
Route D alone would unnecessarily defer a tractable #44 improvement.

### Route E - Coverage-Only Route

```txt
Editor validation errors prove unrelated or already acceptable; use this cycle
for mark/highlight / long-line / pullquote fixture coverage only.
```

Assessment:

```txt
Not selected.
```

Why:

```txt
The 56/56 validation signal is real and fixture-derived. It should be handled
before broadening the wall with additional coverage that could add more
invalid editor markup.
```

### Route F - Other

```txt
Other, with evidence.
```

Assessment:

```txt
Not selected.
```

## Coverage Follow-On Status

### mark/highlight

Status:

```txt
Open under #44; do not add to the current front-end wall before editor-valid
strategy lands.
```

Reason:

```txt
The current fixture has no mark/highlight case. Phase 2 Route C should decide
whether mark/highlight belongs in the new editor-valid smoke fixture, the
existing front-end wall after route stabilization, or a later coverage patch.
```

Recommendation:

```txt
Keep as a Route C follow-on candidate, not Phase 2's first required case.
```

### long-line code

Status:

```txt
Open as fixture coverage, but #41 bridge behavior is already closed for the
current bridge implementation.
```

Evidence:

```txt
v3.6.3 Phase 2 confirmed long-line overflow with a temporary DOM probe:
  overflow-x: auto
  width/clientWidth: 390
  scrollWidth: 3680
  code white-space: pre
```

Recommendation:

```txt
Do not patch code bridge in #44. Add long-line code only as specimen coverage
after the editor-valid fixture strategy is established.
```

### pullquote

Status:

```txt
Open as possible fixture coverage; not needed to re-prove #41 semantics.
```

Evidence:

```txt
v3.6.4 Phase 3 proved quote/pullquote distinct surfaces using temporary DOM
probes.
v3.6.5 Phase 3 proved editor md-sys token parity for pullquote color/divider.
```

Recommendation:

```txt
Do not patch pullquote bridge in #44. Add committed pullquote coverage only if
the Route C editor-valid fixture needs a representative semantic surface.
```

### Material Symbols

Status:

```txt
No active v3.6.7 defect found.
```

Evidence:

```txt
No Phase 1 validation error points to Material Symbols font loading, icon
ligature layout, or separator visibility.
```

Recommendation:

```txt
Keep the #14 cross-reference only. Do not route separator visibility through
Material Symbols in v3.6.7.
```

## Lock / Scope Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; no theme.json or wp-custom source was edited.

Lock 2 - md-sys color maps to md-ref:
  preserved; no token file was edited.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; core/button errors are fixture data-attribute mismatches, not a
  semantic route reopening.

Lock 4 - semantic mismatch handling rule:
  preserved; direct container inner HTML is classified as fixture/editor-save
  mismatch, not hidden behind a visual patch.
```

Explicit file fences:

```txt
tools/validators/validate_theme_pilot.py: do not edit in v3.6.7
theme.json: do not edit
functions.php: do not edit unless Phase 2 review explicitly expands scope
pilot-block-bridge.css/js: do not edit
components.css §0: do not edit
styleguide: do not edit
lab ripple: do not edit
```

## Validation

Phase 1 validation:

```txt
wp-env run cli wp core version:
  PASS - 7.0

python tools\generators\build_pilot_specimen_wall.py:
  PASS - updated specimen wall page 29

npm run validate:specimen-wall:
  PASS

git diff --name-only after probes:
  empty
```

Full validation is deferred until Phase 2 if a patch is approved. Required
Phase 2 / Phase 5 validation remains:

```powershell
wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
git diff --check
```

## Recommended Phase 2 Route

Proceed with Route C after review GO:

```txt
Add a separate editor-valid specimen/smoke fixture.
Keep the current front-end specimen wall intact.
Add or extend importer/validator support only as needed for the second
surface.
Record before/after editor validation counts.
Do not implement #41 bridge/reset fixes.
```

Phase 2 likely write scope:

```txt
products/reference-implementations/axismundi-pilot/fixtures/<editor-valid-fixture>.html
tools/generators/build_pilot_specimen_wall.py
tools/validators/<editor fixture validator or validate_pilot_specimen_wall.js extension>
package.json only if a new npm script is needed
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
```

Files still not expected to change:

```txt
tools/validators/validate_theme_pilot.py
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-lab/stylesheets/components.css
styleguide/*
```

## Phase 1 Exit Criteria

```txt
Editor validation error count recorded: PASS - 56 raw / 28 unique duplicated
Block/fixture source of errors mapped: PASS
Route A/B/C/D/E/F selected: PASS - Route C
Coverage items classified: PASS
Implementation files edited: no
P3 review notes absorbed: PASS
```

## Next

Submit this Phase 1 report for Opus review.

Do not edit implementation files until Phase 1 review gives Phase 2 GO.
