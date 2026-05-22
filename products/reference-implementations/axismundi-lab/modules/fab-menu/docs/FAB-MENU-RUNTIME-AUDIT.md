# FAB Menu Runtime Audit

Component: FAB menu #5

Cycle: v3.6.13

## Runtime Scope

`lab-fab-menu.js` is a component-local validation runtime.

It owns:

- `.is-open` toggle
- `aria-expanded` sync
- `aria-hidden` sync on the action list
- Escape close while focus is inside the FAB menu
- enabled item activation output
- trigger focus restoration after item activation

It does not own:

- anchored-surface positioning
- global outside-click dismissal
- shared provider behavior
- menuitem keyboard model
- plugin/editor commands

## Listener Boundary

The file uses one `DOMContentLoaded` setup listener with `{ once: true }`.

Event listeners are attached to module hosts and controls only. No persistent
`document` or `window` listener is added.

## Provider Boundary

`popover/` is not loaded or consumed by FAB menu in v3.6.13.
