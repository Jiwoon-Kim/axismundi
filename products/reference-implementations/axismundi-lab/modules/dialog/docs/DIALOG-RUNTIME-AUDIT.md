# Dialog Runtime Audit

## Runtime Boundary

`lab-dialog.js` is module-local. It is not a provider.

Public fixture surface:

```txt
window.labDialog.init(root?)
window.labDialog.close(dialog?)
```

The window surface follows the v3.6.10 fixture convention: small, explicit
re-initialization / inspection support for lab pages only.

## Responsibilities

Owned by native `<dialog>`:

```txt
modal top layer
core focus containment
Escape/cancel event
browser modal semantics
```

Owned by `lab-dialog.js`:

```txt
[data-dialog-trigger] wiring
showModal() call
close() call
.modal-scrim data-open / is-open sync
initial focus target
button close
basic backdrop-click close via event.target === dialog
focus restoration to trigger
```

## Backdrop Interaction

Native `::backdrop` and `.modal-scrim` coexist deliberately:

```txt
dialog::backdrop:
  transparent in baseline CSS
  provides browser modal backdrop hit area

.modal-scrim:
  visible M3 scrim primitive
  shared visual overlay used by Dialog and Sheet
```

For basic dialog, clicking the native backdrop produces a click event where
`event.target === dialog`; the runtime closes the dialog. The `.modal-scrim`
click path is also wired as a defensive visual-scrim close path.

For full-screen dialog, backdrop click is not a target close path because the
surface fills the viewport. Close controls and Escape/cancel are the intended
paths.

## Focus Trap

Custom focus-trap code:

```txt
0 lines
```

Native `showModal()` owns Dialog focus containment in v3.6.11. This preserves
the Phase 1 Route D rejection rationale.

## Stop Conditions

If future Dialog work requires any of the following, return to a Phase 1 route
review:

```txt
custom focus-trap
new modal provider
components.css mutation
style-guide.js mutation
popover/ripple/icon-system mutation
```
