# Sheet Measurement Audit

## Baseline Authority

All Sheet measurements remain in `components.css §13`.

This module adds only `.lab-sheet-demo` page scaffolding:

```txt
max-inline-size
grid gaps
specimen cards
local z-index ordering for pattern-page scrim/sheet layering
```

The module does not redefine:

```txt
.sheet
.sheet--bottom-modal
.sheet--side-modal
.modal-scrim
Sheet tokens
scrim opacity
corner radius
bottom-modal dimensions
side-modal dimensions
```

## Variant Notes

```txt
bottom-modal:
  baseline controls width, max-height, bottom edge, and closed transform

side-modal:
  baseline controls width, height, edge alignment, RTL closed transform, and
  modal corner behavior
```

## Phase 3 Checks

```txt
bottom-modal visible and within viewport
side-modal visible and within viewport
scrim visible on open
scrim hidden on close
no horizontal overflow at 390px
focus outline uses existing tokens
```
