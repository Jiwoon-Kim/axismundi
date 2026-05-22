# Menu Measurement Audit

Status: v3.6.9 Phase 2 lab implementation.

## Baseline Reuse

The Menu module does not define Menu dimensions. The baseline primitive in
`stylesheets/components.css` remains authoritative for:

- menu min/max width
- menu padding
- menu surface color
- menu shape
- menu elevation
- item density
- item slot spacing
- state-layer opacity
- focus indicator
- selected state
- disabled state

## Lab Layout

`lab-menu.css` only defines validation-page layout under `.lab-menu-demo`:

- page padding
- grid cards
- captions
- static structure reference layout
- deferred-note layout
- forbidden-ancestor demo layout

## Destructive Item

The destructive specimen uses existing system color tokens:

```txt
--md-sys-color-error
```

This is lab-scoped to `.lab-menu-demo .lab-menu-item--danger` and does not add
or alter baseline Menu color routes.

## Mobile Constraint

The pattern page is expected to fit a 390px-wide viewport without horizontal
overflow. The menu surface itself continues to use the baseline `max-width`
contract.
