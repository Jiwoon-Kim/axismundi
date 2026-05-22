# Sheet Runtime Audit

## Runtime Boundary

`lab-sheet.js` is module-local. It is not a provider.

Public fixture surface:

```txt
window.labSheet.init(root?)
window.labSheet.close(sheet?)
```

The window surface follows the v3.6.10 fixture convention: small, explicit
re-initialization / inspection support for lab pages only.

## Responsibilities

Owned by baseline CSS:

```txt
.sheet layout
.sheet--bottom-modal open / closed transform
.sheet--side-modal open / closed transform
.modal-scrim visual layer
```

Owned by `lab-sheet.js`:

```txt
[data-sheet-trigger] wiring
.is-open state
aria-hidden sync
.modal-scrim data-open / is-open sync
initial focus
local focus containment
Escape close
scrim click close
close-button close
focus restoration to trigger
```

## Focus Trap

Custom focus-trap code is local to `lab-sheet.js`.

Phase 2 line count:

```txt
getFocusable / focusFirst / trapFocus: 31 lines
Phase 1 expected range: 35-55 lines
```

This is below the Phase 1 estimate because the local trap only needs a
focusable query, first-focus helper, and Tab / Shift+Tab wrap. The lower count
does not trigger Route D because the code is smaller than expected and remains
Sheet-local.

## Drag-To-Dismiss

Deferred in v3.6.11.

Future implementation must define:

```txt
pointer capture
distance threshold
velocity threshold
scroll interaction
coarse-pointer behavior
keyboard equivalent or explicit non-keyboard rationale
```

## Stop Conditions

If future Sheet work requires any of the following, return to a Phase 1 route
review:

```txt
new shared focus-trap provider
new backdrop provider
components.css mutation
style-guide.js mutation
popover/ripple/icon-system mutation
```
