# v3.6.11 Wave 2B-2 Dialog / Sheet - Phase 1 Diagnostic Inventory

## Verdict

Route A is selected: Dialog + Sheet Module-Local Runtime.

No implementation files were edited in Phase 1.

Phase 2 may implement `modules/dialog/` and `modules/sheet/` as separate
lab-scoped modules, each consuming existing baseline CSS and `.modal-scrim`
without editing baseline, styleguide runtime, providers, WordPress/Pilot files,
or lock files.

## Lock 5 Compliance

Lock 5 is active and this report supplies the required diagnostic before any
Phase 2 implementation:

```txt
source inputs: listed below
baseline boundaries: components.css §12 Dialog / §13 Sheet / .modal-scrim
provider boundaries: popover/ripple/icon-system unchanged; no new provider
semantic boundaries: native <dialog> vs custom sheet host recorded
route buckets: A/B/C/D/E/F evaluated
selected route: Route A
rejected routes: B/C/D/E/F with evidence
write scope: Phase 2 files listed
fences: no-touch files listed
validation plan: Phase 3 portal/overlay matrix listed
```

No safe-shortcut exception was used.

## Source Inputs

Phase 1 used the Phase 0 reading set plus focused local evidence:

```txt
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-0-PLAN.md
docs/v3.6.10/WAVE-2B-FORM-PHASE-5-CLOSE.md
docs/v3.6.10/WAVE-2B-FORM-PHASE-3-VISUAL-QA.md
docs/v3.6.10/WAVE-2B-FORM-PHASE-2-REPORT.md
docs/v3.6.10/WAVE-2B-FORM-PHASE-1-REPORT.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
PROJECT-CONTEXT.md
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
products/reference-implementations/axismundi-lab/modules/popover/lab-popover.js
products/reference-implementations/axismundi-lab/modules/snackbar/lab-snackbar.js
products/reference-implementations/axismundi-lab/modules/tooltip/lab-tooltip.js
```

## Phase 0 Review P3 Absorption

### P3-1 - Stop Trigger Lock Mapping

The sixth stop trigger from Phase 0, "Need to reinterpret Dialog/Sheet as
popover consumers", maps to:

```txt
Lock 4:
  semantic mismatch handling

Lock 5:
  provider / semantic boundary risk

Provider boundary:
  popover/ is a DONE anchored-surface provider for Menu, Split button,
  FAB menu, Date+Time, and future Select. Dialog and Sheet are matrix
  Independent, not popover consumers.
```

If Phase 2 needs to reinterpret Dialog/Sheet as popover consumers, stop and
return for review.

### P3-2 - Native Dialog Host Contract

Dialog should use native `<dialog>` elements.

Decision details:

```txt
Chosen host:
  native <dialog class="dialog dialog--basic">
  native <dialog class="dialog dialog--full-screen">

Reason:
  showModal() supplies browser-owned modal semantics, focus containment,
  Escape/cancel behavior, and inertness better than a custom .is-open host.

Baseline contract:
  components.css already supports both [open] and .is-open states.
  Using native <dialog> consumes existing baseline without mutation.

Styleguide precedent:
  style-guide.js already uses showModal() for #sg-modal-basic and
  #sg-modal-full. That is precedent only, not module closure.

Focus owner:
  native dialog owns core modal focus containment.
  lab-dialog.js owns trigger wiring, initial focus target selection,
  scrim sync, close controls, and focus restoration verification.
```

Sheet should use custom `aside` hosts:

```txt
bottom:
  <aside class="sheet sheet--bottom-modal" role="dialog" aria-modal="true">

side:
  <aside class="sheet sheet--side-modal" role="dialog" aria-modal="true">

Reason:
  Sheet is not a native dialog element in the existing baseline contract.
  style-guide.html also models sheets as asides with role=dialog.
```

### P3-3 - Shared Utility Numeric Metric

Phase 1 uses this metric:

```txt
custom focus-trap code required by Dialog:
  0 lines expected, because native showModal() provides the trap

custom focus-trap code required by Sheet:
  approximately 35-55 lines expected for focusable query,
  first/last wrapping, Escape close, and restoration

shared custom focus-trap overlap:
  0% at Phase 1, because Dialog has no custom trap implementation

backdrop/scrim code:
  shared baseline primitive exists as .modal-scrim
  module runtime needs only local open/close toggling
```

Threshold for Route D in this cycle:

```txt
Route D would require both Dialog and Sheet to need materially similar custom
focus-trap or backdrop code, or a third current consumer. That threshold is not
met.
```

### P3-4 - Split Naming Convention

If Route B/C/E becomes necessary later, use:

```txt
Wave 2B-2a Dialog
Wave 2B-2b Sheet
```

This keeps the v3.6.10 Wave 2B split language stable.

## Current Local State

Module directories:

```txt
products/reference-implementations/axismundi-lab/modules/dialog/  absent
products/reference-implementations/axismundi-lab/modules/sheet/   absent
```

Existing relevant modules:

```txt
popover/   DONE provider, unchanged
snackbar/  DONE feedback runtime precedent
tooltip/   DONE feedback runtime precedent
menu/      DONE popover consumer, unchanged
checkbox/  DONE v3.6.10 input module, unchanged
radio/     DONE v3.6.10 input module, unchanged
switch/    DONE v3.6.10 input module, unchanged
```

## Canonical Classification

From `COMPONENT-COVERAGE-MAP.md`:

```txt
Dialog #26: TODO, Feedback, Focus trap + ESC + backdrop
Sheet #27:  TODO, Feedback, Drag-to-dismiss; often paired with Dialog
```

From `MODULE-STATUS-MATRIX.md`:

```txt
Dialog #26:
  Feedback
  Interaction Runtime
  TODO
  Target: dialog/
  Dependency: Independent
  Notes: Basic + full-screen; focus trap; backdrop click; ESC dismiss

Sheet #27:
  Feedback
  Interaction Runtime
  TODO
  Target: sheet/
  Dependency: Independent
  Notes: Bottom-modal + side-modal; drag-to-dismiss; often paired with Dialog
```

Independent means no declared dependency on existing infrastructure providers.
It does not mean no runtime.

## Baseline Boundary

### Dialog Baseline

`components.css §12 Dialog` existing selector families:

```txt
1846  .modal-scrim note in Dialog contract
1865  .modal-scrim
1877  .modal-scrim[data-open="true"]
1878  .modal-scrim.is-open
1884  .dialog
1893  .dialog::backdrop
1903  .dialog--basic
1931  .dialog--basic:not([open]):not(.is-open)
1937  .dialog__icon
1947  .dialog__headline
1961  .dialog__supporting
1970  .dialog__actions
1978  .dialog--full-screen
1994  .dialog--full-screen .dialog__app-bar
1997  .dialog--full-screen .dialog__body
2002  .dialog--full-screen:not([open]):not(.is-open)
```

State support:

```txt
open:
  [open] for native dialog
  .is-open for custom host fallback

closed:
  :not([open]):not(.is-open)

scrim:
  .modal-scrim[data-open="true"]
  .modal-scrim.is-open
```

### Sheet Baseline

`components.css §13 Sheet` existing selector families:

```txt
2014  Sheet uses .modal-scrim
2034  .sheet
2056  .sheet--bottom-modal
2074  .sheet__handle
2085  .sheet__header
2089  .sheet__title
2097  .sheet__body
2104  .sheet--bottom-modal:not(.is-open):not([open])
2112  .sheet--side-modal
2122  .sheet--side-modal .sheet__header
2123  .sheet--side-modal .sheet__body
2127  .sheet--side-modal:not(.is-open):not([open])
```

State support:

```txt
open:
  .is-open
  [open] fallback

closed:
  :not(.is-open):not([open])

scrim:
  shared .modal-scrim
```

### Selector Convention

Dialog and Sheet use bare baseline selector prefixes:

```txt
.dialog
.sheet
.modal-scrim
```

Do not rename to `.ax-dialog` or `.ax-sheet`.

## Styleguide Precedent Boundary

Static sections:

```txt
style-guide.html #components-dialog
style-guide.html #components-sheet
```

Static specimens:

```txt
Dialog:
  basic static specimen
  full-screen static specimen
  live portal basic dialog
  live portal full-screen dialog

Sheet:
  bottom-modal static specimen
  side-modal static specimen
  live portal bottom sheet
  live portal side sheet
```

Live styleguide runtime:

```txt
style-guide.js lines 171-257:
  #sg-portal
  [data-portal-scrim]
  modal registry for dialog:basic, dialog:full, sheet:bottom, sheet:side
  open() syncs scrim, calls showModal() for dialogs, adds .is-open for sheets
  close() closes native dialogs or removes .is-open
  [data-open-dialog] triggers
  [data-open-sheet] triggers
  [data-close-modal] controls
  scrim click close
  native dialog click/cancel/close hooks
  document Escape close
```

This is a factual precedent only. It is not module closure and is not a Phase 2
edit surface.

Observed gaps in styleguide runtime for module closure:

```txt
No reusable module namespace
No per-module pattern page
No explicit focus trap for custom Sheet aside
No initial-focus contract
No focus restore assertion beyond close mechanics
No runtime audit docs
```

## Provider Boundary

Existing providers remain unchanged:

```txt
popover/      DONE anchored surface provider
ripple/       DONE state-layer / ripple provider
icon-system/  DONE icon provider
```

Dialog and Sheet do not become popover consumers in v3.6.11.

Reason:

```txt
popover/ owns anchor positioning and viewport collision for anchored surfaces.
Dialog / Sheet own modal surfaces, scrim, focus containment, and modal
dismissal.
```

Creating `focus-trap/` or `backdrop/` infrastructure is not selected in Phase 1.

## Runtime Requirement Map

### Dialog

Required:

```txt
native <dialog> host
basic variant
full-screen variant
trigger button
showModal()
close()
cancel event / Escape close
scrim sync with .modal-scrim
backdrop click close for basic dialog only
close buttons
aria-labelledby
aria-describedby where supporting text exists
initial focus target
focus restoration to trigger
console/page errors 0
```

Deferred / not required:

```txt
custom dialog host
dialog stacking
nested dialogs
modeless dialog
provider extraction
```

### Sheet

Required:

```txt
custom <aside> host with role="dialog" and aria-modal="true"
bottom-modal variant
side-modal variant
trigger button
.is-open state
scrim sync with .modal-scrim
scrim click close
Escape close
close buttons
aria-labelledby
initial focus target
focus containment
focus restoration to trigger
console/page errors 0
```

Drag-to-dismiss:

```txt
defer in v3.6.11
```

Reason:

```txt
Drag-to-dismiss requires pointer capture, threshold, velocity / distance
heuristics, scroll coordination, and mobile-specific behavior. It can be routed
as a future Sheet runtime enhancement after modal open/close/focus closure.
```

## Focus Trap / Backdrop Decision

Decision: no new infrastructure provider in v3.6.11.

Focus trap:

```txt
Dialog:
  native showModal() owns containment.
  custom focus trap: 0 lines expected.

Sheet:
  custom aside host needs local Tab / Shift+Tab wrap.
  expected local code: about 35-55 lines.

Overlap:
  0% shared custom focus-trap code in this cycle.
```

Backdrop / scrim:

```txt
Baseline already provides .modal-scrim.
Each module may toggle its own local scrim in its pattern page.
No new backdrop class or provider is required.
```

Route D rejection threshold:

```txt
Route D would become active if both components required materially similar
custom focus-trap or backdrop logic, or if a third active consumer needed the
same neutral runtime. Phase 1 evidence does not meet that threshold.
```

## Route Selection

### Route A - Selected

Dialog + Sheet Module-Local Runtime.

Why:

```txt
Both rows are paired in Wave 2B-2.
Both have baseline CSS already present.
Both module directories are absent and cleanly addable.
Dialog can use native <dialog>.showModal().
Sheet can use a custom aside with local focus containment.
Shared .modal-scrim is already baseline.
No provider, baseline, WordPress, styleguide, or lock-file edits are needed.
Drag-to-dismiss can be explicitly deferred without blocking modal Sheet closure.
```

### Route B - Rejected

Dialog only.

Reason:

```txt
Sheet's hard parts are known and bounded after Phase 1:
custom host, local focus loop, .is-open state, existing .modal-scrim.
No evidence requires splitting Sheet out before implementation.
```

### Route C - Rejected

Sheet only.

Reason:

```txt
Dialog is the simpler and more canonical modal surface because native <dialog>
owns much of the accessibility contract. Implementing Sheet first would defer
the surface that supplies the clearest model.
```

### Route D - Rejected

Shared utility / infrastructure decision.

Reason:

```txt
focus-trap custom overlap is 0% in this cycle
backdrop primitive already exists as .modal-scrim
dismiss semantics remain surface-specific by PROMOTION-CRITERIA.md
```

Route D remains a stop-and-return trigger if Phase 2 discovers real overlap or
new provider need.

### Route E - Rejected

Audit / split only.

Reason:

```txt
Phase 1 resolved the major boundary questions enough to implement:
native Dialog, custom Sheet, drag defer, no new provider.
```

### Route F - Rejected

No alternate evidence.

## Phase 2 Write Scope

Expected files:

```txt
products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.css
products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.js
products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog-pattern.html
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.css
products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.js
products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet-pattern.html
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-WP-MAPPING.md

docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-2-REPORT.md
```

Expected file count:

```txt
15 files
```

Optional validator is not recommended in Phase 2. Use Playwright probes for
Phase 3 unless a repeated manual check proves brittle.

## Phase 2 Constraints

CSS:

```txt
lab CSS must be scoped under:
  .lab-dialog-demo
  .lab-sheet-demo

Do not write unscoped:
  .dialog
  .sheet
  .modal-scrim
  [data-dialog-trigger]
  [data-sheet-trigger]
  [data-modal-close]
```

Runtime:

```txt
lab-dialog.js:
  owns Dialog pattern page only
  native <dialog>.showModal() / close()
  initial focus and focus restoration
  scrim sync

lab-sheet.js:
  owns Sheet pattern page only
  .is-open state
  local focus containment
  Escape / scrim / close button close
  focus restoration

No global provider namespace beyond optional small module fixture namespace:
  window.labDialog
  window.labSheet
```

If a shared helper is introduced inside each file, it must remain module-local.
Do not create `focus-trap/`, `backdrop/`, or shared `modal/` infrastructure in
Phase 2.

Drag-to-dismiss:

```txt
defer with visible note and audit doc rationale
```

## Files Still Not Expected To Change

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

## Stop-And-Return Triggers

Return to review before Phase 2 implementation if any of these become true:

```txt
Need to edit components.css
  Lock 5 baseline boundary

Need to edit style-guide.js
  cycle-specific baseline / styleguide runtime boundary

Need to edit popover/ripple/icon-system
  Lock 5 provider boundary

Need to create focus-trap/backdrop infrastructure
  Lock 5 provider / infrastructure boundary

Need to alter AGENTS.md / CLAUDE.md / Lock 5
  Lock 5 lock-file boundary

Need to reinterpret Dialog/Sheet as popover consumers
  Lock 4 semantic mismatch + Lock 5 provider/semantic boundary
```

## Phase 3 QA Matrix

Use repo-root localhost server.

Visual cells:

```txt
Dialog desktop/light
Dialog desktop/dark
Dialog mobile/light
Dialog mobile/dark
Sheet desktop/light
Sheet desktop/dark
Sheet mobile/light
Sheet mobile/dark

Expected:
  console errors 0
  page errors 0
  horizontal overflow 0 at 390px
```

Dialog interaction checks:

```txt
basic trigger exists
full-screen trigger exists
basic open visible
full-screen open visible
scrim opens
close button closes
Escape/cancel closes
basic backdrop click closes
initial focus enters dialog
Tab / Shift+Tab stay contained by native modal behavior
focus restores to trigger
aria-labelledby present
aria-describedby present where supporting text exists
```

Sheet interaction checks:

```txt
bottom trigger exists
side trigger exists
bottom open visible
side open visible
scrim opens
close button closes
Escape closes
scrim click closes
initial focus enters sheet
Tab / Shift+Tab wrap within sheet
focus restores to trigger
role="dialog"
aria-modal="true"
aria-labelledby present
drag-to-dismiss defer note visible
```

Lock / fence checks:

```txt
components.css unchanged
style-guide.js unchanged
providers unchanged
WordPress/Pilot unchanged
closed modules unchanged
AGENTS.md / CLAUDE.md unchanged
```

Standard validation:

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

## Locks

```txt
Lock 1: preserved; no WordPress theme custom token work
Lock 2: preserved; no token file or md-sys/md-ref mapping work
Lock 3: preserved; core/button not reopened
Lock 4: preserved; Dialog/Sheet kept independent, not collapsed into popover
Lock 5: applied; diagnostic inventory completed before implementation
```

## Next

Submit this Phase 1 report for review.

If approved, proceed to Phase 2 Route A implementation.
