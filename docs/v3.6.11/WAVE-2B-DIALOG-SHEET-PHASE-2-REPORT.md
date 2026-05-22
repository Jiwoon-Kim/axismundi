# v3.6.11 Wave 2B-2 Dialog / Sheet - Phase 2 Report

## Verdict

Route A implemented.

Added Dialog #26 and Sheet #27 as module-local Interaction Runtime lab modules.
No baseline, provider, styleguide runtime, WordPress/Pilot, validator,
generator, closed module, or lock-file surfaces were edited.

## Files Added

Dialog:

```txt
products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.css
products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.js
products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog-pattern.html
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/dialog/docs/DIALOG-WP-MAPPING.md
```

Sheet:

```txt
products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.css
products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.js
products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet-pattern.html
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-RUNTIME-AUDIT.md
products/reference-implementations/axismundi-lab/modules/sheet/docs/SHEET-WP-MAPPING.md
```

Phase report:

```txt
docs/v3.6.11/WAVE-2B-DIALOG-SHEET-PHASE-2-REPORT.md
```

Expected file count from Phase 1: 15.
Actual file count: 15.

## Dialog Implementation

Host contract:

```txt
native <dialog>
```

Implemented surfaces:

```txt
basic dialog
full-screen dialog
```

Runtime ownership:

```txt
lab-dialog.js:
  [data-dialog-trigger]
  showModal()
  close()
  .modal-scrim data-open / is-open sync
  initial focus
  close button
  basic backdrop-click close
  cancel / close event handling
  focus restoration
```

Custom focus trap:

```txt
0 lines
```

Native `showModal()` owns Dialog modal containment.

## Sheet Implementation

Host contract:

```txt
<aside class="sheet ..." role="dialog" aria-modal="true">
```

Implemented surfaces:

```txt
bottom-modal sheet
side-modal sheet
```

Runtime ownership:

```txt
lab-sheet.js:
  [data-sheet-trigger]
  .is-open state
  aria-hidden sync
  .modal-scrim data-open / is-open sync
  initial focus
  local focus containment
  Escape close
  scrim click close
  close button close
  focus restoration
```

Sheet side-modal terminology:

```txt
side-modal sheet = fully modal drawer
not a non-modal navigation drawer
```

Drag-to-dismiss:

```txt
deferred in v3.6.11
visible note included in lab-sheet-pattern.html
SHEET-SPEC-AUDIT.md and SHEET-RUNTIME-AUDIT.md record rationale
```

## Phase 1 P3 Absorption

### P3-1 - Dialog Backdrop Interaction

Documented in `DIALOG-MEASUREMENT-AUDIT.md` and `DIALOG-RUNTIME-AUDIT.md`.

Decision:

```txt
native dialog::backdrop:
  transparent in baseline CSS
  browser modal backdrop / hit area

.modal-scrim:
  visible M3 scrim primitive
  toggled by lab-dialog.js
```

For basic dialog, backdrop click is handled by the native dialog click path
where `event.target === dialog`. The `.modal-scrim` click path is also wired as
a defensive visual-scrim close path. Full-screen dialog uses close controls and
Escape/cancel rather than backdrop click.

### P3-2 - Drag-To-Dismiss Routing

Drag-to-dismiss is not implemented in Phase 2.

Decision:

```txt
Route through ROADMAP / NEXT-SESSION at Phase 5 if still needed.
Do not create a BACKLOG item unless review decides this is specific routed work.
```

### P3-3 - Sheet Focus-Trap Line Count

Phase 1 estimate:

```txt
35-55 lines
```

Phase 2 actual:

```txt
getFocusable / focusFirst / trapFocus: 31 lines
```

Result:

```txt
Below estimate, because the local trap only needs a focusable query,
first-focus helper, and Tab / Shift+Tab wrap.
No Route D threshold is approached.
```

### P3-4 - Sheet Side-Modal Terminology

Recorded in `SHEET-SPEC-AUDIT.md` and pattern copy:

```txt
side-modal = fully modal drawer
not non-modal drawer
```

## Selector Compliance

Lab CSS scopes:

```txt
.lab-dialog-demo
.lab-sheet-demo
```

Forbidden unscoped overrides:

```txt
.dialog
.sheet
.modal-scrim
[data-dialog-trigger]
[data-sheet-trigger]
[data-modal-close]
```

Phase 2 result:

```txt
No unscoped baseline selector overrides.
No provider-specific branches.
No token remapping.
```

## Runtime Checks

Syntax checks:

```txt
node --check products/reference-implementations/axismundi-lab/modules/dialog/lab-dialog.js PASS
node --check products/reference-implementations/axismundi-lab/modules/sheet/lab-sheet.js  PASS
```

Global fixture surfaces:

```txt
window.labDialog = { init, close }
window.labSheet  = { init, close }
```

These follow the v3.6.10 fixture convention. They are not providers.

## Fences Preserved

Unchanged:

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
closed Wave 2A modules
v3.6.10 form modules
form-adjacent existing modules
tools/validators/*
tools/generators/*
styleguide/*
```

## Validation

```txt
node --check lab-dialog.js                           PASS
node --check lab-sheet.js                            PASS
wp-env run cli wp core version                       PASS - 7.0
python tools/generators/build_pilot_specimen_wall.py PASS
npm run validate:specimen-wall                       PASS
php -l products/reference-implementations/axismundi-pilot/functions.php PASS
npm test                                             PASS - Axis A-G all 1.000
npm run validate:computed                            PASS
npm run publish:styleguide                           PASS, generated mirror restored
git diff --check                                     PASS
```

## Next

Proceed to Phase 3 visual / interaction QA after review.

Required Phase 3 emphasis:

```txt
Dialog:
  basic / full-screen open-close
  native backdrop click for basic dialog
  Escape/cancel close
  focus containment
  focus restoration

Sheet:
  bottom / side open-close
  local Tab / Shift+Tab wrap
  Escape close
  scrim click close
  focus restoration
  drag-to-dismiss defer note visible
```
