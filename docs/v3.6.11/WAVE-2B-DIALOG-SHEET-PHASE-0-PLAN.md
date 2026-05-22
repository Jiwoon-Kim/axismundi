# v3.6.11 Wave 2B-2 Dialog / Sheet - Phase 0 Plan

## Verdict

Selected candidate: Wave 2B-2 Dialog / Sheet.

This is the first post-Lock-5 cycle. Diagnostic-first is now mandatory because
the route and boundary risks are not fully known:

```txt
Dialog #26: Interaction Runtime, TODO, independent
Sheet #27:  Interaction Runtime, TODO, independent
Shared risk: focus-trap and backdrop utility extraction may be warranted
```

Do not edit implementation files before Phase 1 review.

## Current Baseline

```txt
origin/main: 2baecbb
local HEAD at Phase 0 entry: 28c979e
local status at Phase 0 entry: clean, ahead 25
WordPress core in wp-env: 7.0
v3.6.0 through v3.6.10: closed
Current release state: v3.6.10 closed, Wave 2B-1 complete
Lock 5: promoted in v3.6.10
Cadence: Phase 0 / 1 / 2 / 3 / 5; Phase 4 intentionally unused
```

## Phase 0 Actually Read

Read according to `NEXT-SESSION.md §0` order:

```txt
1. AGENTS.md or CLAUDE.md
2. CURRENT-STATE.md
3. PROJECT-CONTEXT.md
4. CHANGELOG.md latest entry
5. ROADMAP.md current tail
6. BACKLOG.md #41 / #44 / #46 / #47 / #21 / #14
7. docs/v3.6.10/WAVE-2B-FORM-PHASE-5-CLOSE.md
8. docs/v3.6.10/WAVE-2B-FORM-PHASE-3-VISUAL-QA.md
9. docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
10. docs/v3.6.10/WAVE-2B-FORM-PHASE-1-REPORT.md
11. docs/v3.6.10/WAVE-2B-FORM-PHASE-0-PLAN.md
12. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-5-CLOSE.md
13. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-3-VISUAL-QA.md
14. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-2-REPORT.md
15. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-1-REPORT.md
16. docs/v3.6.9/WAVE-2A-MENU-POPOVER-CONSUMER-PHASE-0-PLAN.md
17. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-5-CLOSE.md
18. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-3-VISUAL-QA.md
19. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-2-REPORT.md
20. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-1-REPORT.md
21. docs/v3.6.8/WAVE-2A-NAVIGATION-PHASE-0-PLAN.md
22. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
23. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
24. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
25. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
26. docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
27. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
28. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
29. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
30. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
31. docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
32. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
33. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
34. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
35. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
36. docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
37. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
38. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
39. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
40. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
41. docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
42. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
43. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
44. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
45. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
46. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
47. docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
48. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
49. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
50. docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
51. bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
52. docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Additional focused Phase 0 reads:

```txt
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
PROJECT-CONTEXT.md DISTINCT but COUPLED section
products/reference-implementations/axismundi-lab/stylesheets/components.css §12 / §13
products/reference-implementations/axismundi-lab/style-guide.html Dialog / Sheet static and portal specimens
products/reference-implementations/axismundi-lab/scripts/style-guide.js modal runtime
products/reference-implementations/axismundi-lab/modules/popover/lab-popover.js
products/reference-implementations/axismundi-lab/modules/tooltip/lab-tooltip.js
products/reference-implementations/axismundi-lab/modules/snackbar/lab-snackbar.js
```

## Source Findings

`COMPONENT-COVERAGE-MAP.md` says Wave 2 Dialog + Sheet naturally extends the
transient-surface pattern already represented by popover / tooltip / snackbar.
It also names Dialog + Sheet as a natural Wave 2 sub-group.

Canonical rows:

```txt
Dialog #26: Feedback, Interaction Runtime, TODO
  Notes: Focus trap + ESC + backdrop

Sheet #27: Feedback, Interaction Runtime, TODO
  Notes: Drag-to-dismiss; often paired with Dialog
```

`MODULE-STATUS-MATRIX.md` classifies both as Independent:

```txt
Dialog #26: target dialog/, independent, basic + full-screen, focus trap, backdrop click, ESC dismiss
Sheet #27:  target sheet/, independent, bottom-modal + side-modal, drag-to-dismiss, often paired with Dialog
```

Important nuance: Independent does not mean "no runtime." It means no declared
dependency on existing infrastructure providers.

`PROMOTION-CRITERIA.md` explicitly defers the shared utility decision:

```txt
focus-trap utility:
  Dialog + Sheet both need focus-trap behavior.
  If both implement focus-trap independently, consider extracting to
  focus-trap/ infrastructure.
  Decision: defer to Wave 2 authoring; decide based on actual code overlap.

backdrop utility:
  Dialog + Sheet both render backdrop overlays.
  Same deferral path as focus-trap.

dismissible/closable:
  Dialog / Sheet / Snackbar / Tooltip all have dismiss semantics.
  Decision: NO extraction. Dismiss is too surface-specific.
```

This cycle is the moment where that deferred decision becomes real. Lock 5
requires Phase 1 to diagnose the route before any implementation.

## Existing Local Surface

Baseline CSS exists:

```txt
components.css §12 Dialog:
  .modal-scrim
  .dialog
  .dialog--basic
  .dialog--full-screen
  .dialog__icon
  .dialog__headline
  .dialog__supporting
  .dialog__actions
  .dialog__app-bar
  .dialog__body

components.css §13 Sheet:
  .sheet
  .sheet--bottom-modal
  .sheet--side-modal
  .sheet__handle
  .sheet__header
  .sheet__title
  .sheet__body
```

Selector convention note:

```txt
Dialog and Sheet use bare baseline selector prefixes:
  .dialog
  .sheet
  .modal-scrim

They do not use .ax-dialog / .ax-sheet.
```

Styleguide static sections exist:

```txt
#components-dialog
#components-sheet
```

Styleguide runtime also exists in `style-guide.js`, but it is styleguide chrome /
demo runtime, not a module closure:

```txt
portal: #sg-portal
scrim: [data-portal-scrim]
dialog keys: dialog:basic, dialog:full
sheet keys: sheet:bottom, sheet:side
open: showModal() for dialogs, .is-open for sheets
close: close button, scrim click, native cancel / close, document Escape
```

This runtime is source evidence only. v3.6.11 must not edit
`style-guide.html` or `scripts/style-guide.js`.

Current module state:

```txt
modules/dialog/ absent
modules/sheet/ absent
modules/popover/ DONE, unchanged provider
modules/snackbar/ DONE
modules/tooltip/ DONE
```

## Selected Candidate

Primary candidate:

```txt
Wave 2B-2 Dialog / Sheet
```

Reason:

```txt
1. NEXT-SESSION.md lists it as the primary next route.
2. v3.6.10 close explicitly routed remaining Wave 2B into 2B-2 / 2B-3 / 2B-4.
3. Dialog and Sheet are the paired Interaction Runtime surfaces in the coverage map.
4. This cycle is the first hard test of Lock 5 after promotion.
5. The focus-trap / backdrop extraction question is explicitly deferred to Wave 2 authoring.
```

## In Scope

1. Diagnose Dialog #26 and Sheet #27 as a paired Wave 2B-2 slice.
2. Inventory baseline CSS selectors, states, and variant contracts.
3. Inventory existing styleguide static and live modal examples as precedent,
   not closure.
4. Decide whether Phase 2 should implement:
   - Dialog only,
   - Sheet only,
   - both with module-local runtimes,
   - both plus a shared local helper,
   - a new infrastructure provider,
   - or audit/split only.
5. Decide whether focus trap / backdrop should remain module-local or route to
   a future infrastructure cycle.
6. Define Dialog runtime requirements:
   - trigger,
   - open / close,
   - Escape,
   - backdrop click,
   - focus entry,
   - focus containment,
   - focus restoration,
   - labelled / described relationship,
   - basic and full-screen variants.
7. Define Sheet runtime requirements:
   - trigger,
   - open / close,
   - Escape,
   - scrim click,
   - focus entry,
   - focus containment,
   - focus restoration,
   - bottom-modal and side-modal variants,
   - drag-to-dismiss decision or explicit defer.
8. Confirm portal / overlay smoke test requirements from `AGENTS.md`.
9. Preserve styleguide runtime as precedent only.
10. Preserve Lock 1-5.

## Out Of Scope

1. No implementation before Phase 1 review.
2. No `components.css` edits.
3. No `blocks.css` edits.
4. No `style-guide.html` edits.
5. No `scripts/style-guide.js` edits.
6. No `styleguide/*` manual edits.
7. No `popover/*`, `ripple/*`, or `icon-system/*` edits.
8. No WordPress/Pilot edits:
   - `theme.json`
   - `functions.php`
   - bridge source / asset pairs
   - fixtures
9. No validators or generator edits unless Phase 1 selects a route that
   explicitly requires them and review approves.
10. No BACKLOG #21 plugin strategy work.
11. No BACKLOG #41 ripple runtime packaging work.
12. No BACKLOG #44 specimen coverage work.
13. No BACKLOG #46 disabled ripple hygiene work.
14. No BACKLOG #47 popover hygiene work.
15. No Date+Time #22+#23 work.
16. No Actions consumers #5/#7/#8 work.
17. No baseline selector rename from `.dialog` / `.sheet` to `.ax-*`.
18. No drag-to-dismiss implementation unless Phase 1 proves it can be done
   without provider/baseline drift and review approves the route.

## Active Locks

Lock 1 - wp-custom downstream-only:

```txt
Every settings.custom.axismundi.* entry MUST be defined as:
  var(--comp-*) or var(--md-sys-*) or var(--md-ref-*)

Literal hex / rgb / px / number values are forbidden in this namespace.
Validator: tools/validators/validate_theme_pilot.py Axis G.
```

Lock 2 - md-sys color maps to md-ref:

```txt
Every --md-sys-color-* entry MUST be defined as:
  var(--md-ref-palette-*)

Literal hex / rgb / hsl values are forbidden in the md-sys color layer.
Validator: tools/validators/validate_theme_pilot.py Axis E.
```

Lock 3 - core/button semantic route before visual cleanup:

```txt
core/button anchor navigation can receive visual bridge.
real actions / form / AJAX / federation / durable schema route to plugin/custom-block territory.
```

Lock 4 - semantic mismatch handling rule:

```txt
When a component visually maps to M3 but carries divergent markup,
interaction, or accessibility semantics, route the mismatch before accepting
a visual fix.
```

Lock 5 - diagnostic-first before implementation:

```txt
For plan-first cycles where the route, failure mode, or boundary risk is not
already known, Phase 1 diagnostic inventory is mandatory before Phase 2
implementation.

Do not patch first and backfill the route later.
```

This cycle is explicitly under Lock 5. Phase 1 must produce route evidence
before Phase 2 can patch.

## Route Buckets

### Route A - Dialog + Sheet Module-Local Runtime

Implement both `dialog/` and `sheet/` in one cycle with separate module-local
runtimes. Shared ideas may be duplicated only if the code remains small and the
Phase 1 report explains why no infrastructure extraction is needed.

Expected Phase 2 shape if selected:

```txt
modules/dialog/lab-dialog.css
modules/dialog/lab-dialog.js
modules/dialog/lab-dialog-pattern.html
modules/dialog/docs/DIALOG-SPEC-AUDIT.md
modules/dialog/docs/DIALOG-MEASUREMENT-AUDIT.md
modules/dialog/docs/DIALOG-RUNTIME-AUDIT.md
modules/dialog/docs/DIALOG-WP-MAPPING.md

modules/sheet/lab-sheet.css
modules/sheet/lab-sheet.js
modules/sheet/lab-sheet-pattern.html
modules/sheet/docs/SHEET-SPEC-AUDIT.md
modules/sheet/docs/SHEET-MEASUREMENT-AUDIT.md
modules/sheet/docs/SHEET-RUNTIME-AUDIT.md
modules/sheet/docs/SHEET-WP-MAPPING.md
```

This is the preferred hypothesis only if Phase 1 proves focus trap and backdrop
logic can remain component-local without risky duplication.

### Route B - Dialog First

Implement Dialog #26 only and defer Sheet #27. This may be safer if Sheet
introduces drag-to-dismiss or side-modal behavior that changes the runtime
shape.

Expected forward routing:

```txt
Wave 2B-2a: Dialog
Wave 2B-2b: Sheet
```

### Route C - Sheet First

Implement Sheet #27 only and defer Dialog #26. This is less likely because
Dialog is the simpler focus-trap surface and uses native `<dialog>` affordances,
but Phase 1 may choose it if Sheet's baseline contract is more complete and
Dialog requires unresolved native/custom dialog decisions.

### Route D - Shared Utility / Infrastructure Decision

No component implementation yet. Phase 1 may recommend a separate
focus-trap/backdrop utility or provider cycle if actual overlap is high enough
to satisfy the infrastructure criteria.

This route requires review before any Phase 2 implementation because creating a
new provider or shared utility is a boundary decision.

### Route E - Audit / Split Only

No implementation. Produce a no-code split report that defines:

```txt
Dialog runtime contract
Sheet runtime contract
focus-trap extraction decision
backdrop extraction decision
drag-to-dismiss defer or implementation criteria
```

Use this route if Phase 1 discovers unresolved boundary questions.

### Route F - Other

Escape hatch. Requires concrete evidence and explicit review.

## Phase Plan

### Phase 0 - Plan

This document.

Exit criteria:

```txt
Plan reviewed
Lock 5 compliance acknowledged
No implementation files edited
```

### Phase 1 - Diagnostic Inventory

Required report:

```txt
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-1-REPORT.md
```

Inventory tasks:

1. Confirm `modules/dialog/` and `modules/sheet/` are absent.
2. Map `components.css §12 Dialog` selector families, variants, states, and
   line ranges.
3. Map `components.css §13 Sheet` selector families, variants, states, and
   line ranges.
4. Map `.modal-scrim` as the existing shared baseline primitive.
5. Inventory `style-guide.html #components-dialog` and `#components-sheet`
   static specimens.
6. Inventory `style-guide.js` modal runtime as factual precedent, not module
   closure.
7. Map Dialog runtime requirements:
   native `<dialog>` vs custom host,
   showModal / close,
   Escape / cancel,
   backdrop click,
   focus entry,
   focus trap,
   focus restore,
   labelled / described wiring.
8. Map Sheet runtime requirements:
   `aside[role="dialog"][aria-modal="true"]`,
   open / close,
   Escape,
   scrim click,
   focus entry,
   focus trap,
   focus restore,
   bottom-modal,
   side-modal,
   drag-to-dismiss decision.
9. Compare module-local vs shared focus-trap / backdrop overlap.
10. Decide whether a shared helper is allowed inside one module, duplicated
    locally, or must become a separate provider route.
11. Confirm no need to edit `components.css`, `style-guide.js`, providers,
    WordPress/Pilot, validators, or closed modules.
12. Select Route A/B/C/D/E/F with rejected-bucket evidence.
13. Define Phase 2 write scope and no-touch fences.
14. Define Phase 3 portal / overlay smoke matrix.

Phase 1 stop conditions:

```txt
Need to edit components.css
Need to edit style-guide.js
Need to edit popover/ripple/icon-system
Need to create focus-trap/backdrop infrastructure without prior review
Need to alter AGENTS.md / CLAUDE.md / Lock 5
Need to reinterpret Dialog/Sheet as popover consumers
```

### Phase 2 - Implementation or No-Code Route Report

Phase 2 is conditional on Phase 1 review.

Allowed write scope depends on the selected route. Route A expected max scope:

```txt
products/reference-implementations/axismundi-lab/modules/dialog/*
products/reference-implementations/axismundi-lab/modules/sheet/*
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-2-REPORT.md
```

Optional only if Phase 1 and review approve:

```txt
tools/validators/validate_wave2b_dialog_sheet.js
package.json
```

Not expected in Phase 2:

```txt
components.css
blocks.css
style-guide.html
scripts/style-guide.js
styleguide/*
popover/*
ripple/*
icon-system/*
WordPress/Pilot files
closed Wave 2A modules
v3.6.10 form modules
form-adjacent existing modules
```

### Phase 3 - Visual / Interaction QA

Required report:

```txt
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-3-VISUAL-QA.md
```

Expected QA if Route A/B/C implementation occurs:

```txt
desktop/light
desktop/dark
mobile/light
mobile/dark
console errors 0
horizontal overflow 0 at 390px
trigger exists
runtime handler attaches
host / portal element exists
open visible state works
close / dismiss path works
Escape works
scrim/backdrop click works where intended
focus enters initial target
Tab / Shift+Tab containment works
focus restores to trigger
body/background inertness or scroll-lock behavior is recorded
```

Dialog-specific:

```txt
basic dialog open / close
full-screen dialog open / close
native cancel handling
labelledby / describedby relationships
```

Sheet-specific:

```txt
bottom-modal open / close
side-modal open / close
scrim open state
drag-to-dismiss implemented or explicitly deferred
```

Test target convention:

```txt
Use repo-root localhost server unless Phase 1 defines a better target:
http://127.0.0.1:<port>/products/reference-implementations/axismundi-lab/modules/<module>/<pattern>.html
```

### Phase 5 - Close

Expected if cycle closes:

```txt
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-5-CLOSE.md
BACKLOG.md update only if a new routed item or closed backlog item exists
CHANGELOG.md v3.6.11 entry
ROADMAP.md tail update
CURRENT-STATE.md update
NEXT-SESSION.md handoff update
```

Phase 5 must record:

```txt
Dialog / Sheet close or narrowed-open state
focus-trap / backdrop extraction decision
drag-to-dismiss decision
Lock 5 compliance as first post-promotion cycle
next route ordering
```

`AGENTS.md` / `CLAUDE.md` are not expected to change.

## Risk Register

### R1 - Lock 5 First-Test Slippage

Risk: because Lock 5 is new, Phase 2 might start from the obvious styleguide
runtime and patch first.

Mitigation: Phase 1 must select a route before implementation. Any shortcut
must be recorded as safe. No shortcut is expected here.

### R2 - Styleguide Runtime Mistaken For Module Closure

Risk: `style-guide.js` already opens dialogs and sheets, so implementers may
treat the components as already done.

Mitigation: Phase 1 must separate factual precedent from module closure.
`style-guide.js` remains fenced.

### R3 - Premature Focus-Trap Infrastructure

Risk: Dialog and Sheet share focus containment needs; creating `focus-trap/`
too early could overfit this pair or become a provider without enough contract
evidence.

Mitigation: Phase 1 compares overlap. Provider extraction requires Route D and
review before implementation.

### R4 - Duplicated Focus-Trap Logic

Risk: implementing two module-local runtimes may duplicate fragile focus code.

Mitigation: Phase 1 must decide duplication vs helper vs provider, with line-of
responsibility and future extraction criteria.

### R5 - Backdrop / Scrim Drift

Risk: `.modal-scrim` is already a shared baseline primitive; implementing
another scrim class or changing opacity can create token drift.

Mitigation: consume `.modal-scrim` as-is unless Phase 1 proves otherwise.
`components.css` stays fenced.

### R6 - Drag-To-Dismiss Scope Explosion

Risk: Sheet's drag-to-dismiss can require pointer capture, velocity thresholds,
touch heuristics, scroll coordination, and mobile-only behavior.

Mitigation: Phase 1 must choose implement or explicit defer. Defer is valid if
bottom/side modal open-close closure is otherwise complete.

### R7 - Native `<dialog>` vs Custom Host Mismatch

Risk: Dialog baseline supports `<dialog>` and custom `.is-open` hosts; runtime
choices can diverge from accessibility expectations.

Mitigation: Phase 1 records chosen host contract. Phase 3 tests keyboard,
cancel, focus, labelledby, and close paths.

### R8 - Existing Provider Bleed

Risk: because popover already handles anchored transient surfaces, Dialog/Sheet
runtime might accidentally import popover semantics or alter `popover/`.

Mitigation: Dialog and Sheet are matrix-independent, not popover consumers.
`popover/*` is fenced.

### R9 - Baseline Mutation Temptation

Risk: runtime implementation may expose missing baseline hooks and tempt
`components.css` edits.

Mitigation: If baseline edits are needed, stop and return to Phase 1 review.

### R10 - Portal / Overlay Smoke Under-Tested

Risk: visual-only QA can miss body scroll, focus restoration, or invisible host
errors.

Mitigation: Phase 3 must use the AGENTS portal / overlay smoke checklist.

### R11 - Publish / Validator Artifact Churn

Risk: `publish:styleguide` and validators write generated files.

Mitigation: restore generated mirror / validator reports after validation.

### R12 - Next-Route Fragmentation

Risk: unresolved decisions could create unnecessary backlog items.

Mitigation: only create BACKLOG items for real routed work; otherwise use
ROADMAP / CURRENT-STATE / NEXT-SESSION for Wave 2B split routing.

## Files Not Expected To Change

Hard fences:

```txt
AGENTS.md
CLAUDE.md
theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/fixtures/*
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-lab/modules/popover/*
products/reference-implementations/axismundi-lab/modules/ripple/*
products/reference-implementations/axismundi-lab/modules/icon-system/*
products/reference-implementations/axismundi-lab/modules/app-bar/*
products/reference-implementations/axismundi-lab/modules/nav-bar/*
products/reference-implementations/axismundi-lab/modules/nav-rail/*
products/reference-implementations/axismundi-lab/modules/tabs/*
products/reference-implementations/axismundi-lab/modules/menu/*
products/reference-implementations/axismundi-lab/modules/checkbox/*
products/reference-implementations/axismundi-lab/modules/radio/*
products/reference-implementations/axismundi-lab/modules/switch/*
products/reference-implementations/axismundi-lab/modules/date-time/*
products/reference-implementations/axismundi-lab/modules/text-field/*
products/reference-implementations/axismundi-lab/modules/search-bar/*
products/reference-implementations/axismundi-lab/modules/button/*
products/reference-implementations/axismundi-lab/modules/button-group/*
products/reference-implementations/axismundi-lab/modules/fab/*
tools/validators/validate_theme_pilot.py
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
styleguide/*
```

Phase 0 changes only:

```txt
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-0-PLAN.md
```

## Validation Strategy

Standard gates:

```txt
wp-env run cli wp core version
python tools/generators/build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products/reference-implementations/axismundi-pilot/functions.php
npm test
npm run validate:computed
npm run publish:styleguide
git diff --check
```

Phase 3 additional gates if implemented:

```txt
Dialog / Sheet pattern pages:
  console errors 0
  page errors 0
  horizontal overflow 0 at 390px
  trigger count expected
  host / portal count expected
  scrim open/closed states expected
  open state visible
  close state hidden / inert
  Escape close
  backdrop/scrim close
  focus entry
  focus trap Tab / Shift+Tab
  focus restore
  body scroll-lock or non-lock behavior recorded
```

## Expected Phase 0 Review Questions

1. Is Wave 2B-2 Dialog / Sheet the right candidate after v3.6.10?
2. Does the plan treat Lock 5 as policy, not preference?
3. Are focus-trap and backdrop extraction framed as Phase 1 decisions rather
   than Phase 0 assumptions?
4. Are styleguide modal runtime and static specimens fenced as precedent only?
5. Are Dialog/Sheet bare selector conventions (`.dialog`, `.sheet`) correctly
   preserved?
6. Are provider / baseline / WordPress / closed module fences complete?

## Next

Submit this Phase 0 plan for review.

After Phase 0 GO, produce the Phase 1 diagnostic inventory report before any
implementation.
