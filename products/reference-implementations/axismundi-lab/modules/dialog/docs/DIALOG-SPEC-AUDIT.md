# Dialog Spec Audit

## Scope

v3.6.11 closes Dialog #26 as an Interaction Runtime module.

Implemented variants:

```txt
basic dialog
full-screen dialog
```

Host contract:

```txt
native <dialog>
```

Reason:

```txt
Native showModal() owns modal semantics, Escape/cancel behavior, and focus
containment. The lab module owns trigger wiring, scrim sync, initial focus,
close controls, and focus restoration.
```

## Baseline

The module consumes existing `components.css §12` selectors:

```txt
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
```

No `.ax-dialog` alias is introduced.

## Accessibility

```txt
aria-haspopup="dialog" on triggers
aria-controls links trigger to dialog host
aria-labelledby on each dialog
aria-describedby on basic dialog with supporting text
close controls are real buttons
Escape/cancel closes through native dialog event path
focus restores to trigger after close
```

## Out Of Scope

```txt
modeless dialog
nested dialogs
dialog stacking
custom .is-open host runtime
new focus-trap infrastructure
new backdrop infrastructure
```
