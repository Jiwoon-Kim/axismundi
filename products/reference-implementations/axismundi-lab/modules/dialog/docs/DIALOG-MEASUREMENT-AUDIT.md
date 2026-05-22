# Dialog Measurement Audit

## Baseline Authority

All Dialog measurements remain in `components.css §12`.

This module adds only `.lab-dialog-demo` page scaffolding:

```txt
max-inline-size
grid gaps
specimen cards
local z-index ordering for pattern-page scrim/dialog layering
```

The module does not redefine:

```txt
.dialog
.dialog--basic
.dialog--full-screen
.modal-scrim
Dialog tokens
scrim opacity
corner radius
elevation
typography
```

## Scrim / Backdrop

Baseline `dialog::backdrop` is transparent. The visible overlay is the existing
`.modal-scrim` element.

The pattern uses:

```txt
native dialog::backdrop:
  transparent, browser top-layer backdrop

.modal-scrim:
  visual scrim, data-open / is-open toggled by lab-dialog.js
```

This avoids stacked visible scrims while keeping native modal behavior.

## Phase 3 Checks

```txt
basic dialog width within baseline max
full-screen dialog fills viewport
scrim visible on open
scrim hidden on close
no horizontal overflow at 390px
focus outline uses existing tokens
```
