# Sheet Spec Audit

## Scope

v3.6.11 closes Sheet #27 as an Interaction Runtime module.

Implemented variants:

```txt
bottom-modal sheet
side-modal sheet
```

Host contract:

```txt
<aside class="sheet ..." role="dialog" aria-modal="true">
```

The side-modal variant is a fully modal drawer, not a non-modal navigation
drawer.

## Baseline

The module consumes existing `components.css §13` selectors:

```txt
.modal-scrim
.sheet
.sheet--bottom-modal
.sheet--side-modal
.sheet__handle
.sheet__header
.sheet__title
.sheet__body
```

No `.ax-sheet` alias is introduced.

## Accessibility

```txt
aria-haspopup="dialog" on triggers
aria-controls links trigger to sheet host
role="dialog"
aria-modal="true"
aria-labelledby on each sheet
aria-hidden reflects open/closed state
close controls are real buttons
Escape closes
focus stays inside the sheet while open
focus restores to trigger after close
```

## Drag-To-Dismiss

Deferred in v3.6.11.

Reason:

```txt
Drag-to-dismiss requires pointer capture, threshold, velocity / distance
heuristics, scroll coordination, and mobile-specific behavior. Modal open /
close / focus closure is complete without it.
```

Route future drag work through ROADMAP / NEXT-SESSION unless it becomes a
specific backlog item.
