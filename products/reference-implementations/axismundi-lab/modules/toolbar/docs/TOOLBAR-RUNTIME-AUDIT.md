# Toolbar Runtime Audit

Component: Toolbar #8

Cycle: v3.6.13

## Runtime Scope

`lab-toolbar.js` is a lab-scoped runtime that toggles:

- `aria-pressed`
- `.is-selected`
- local status output

It only handles controls inside `.lab-toolbar-demo [role="toolbar"]`.

## Collision Avoidance

The pattern page intentionally does not load `scripts/theme.js`. The global
styleguide toolbar handler remains precedent only and is not a module runtime.

## Listener Boundary

The file uses one `DOMContentLoaded` setup listener with `{ once: true }`.

All click listeners are attached to toolbar controls. No persistent
`document` or `window` listener is added.
