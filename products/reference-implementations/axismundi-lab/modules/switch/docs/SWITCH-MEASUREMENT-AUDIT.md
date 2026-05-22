# Switch - Measurement Audit (v3.6.10 Phase 2)

## Baseline Source

```txt
components.css §24 Switch
selector range recorded in Phase 1: lines 3732-3950
selector count: 22
```

The module does not redefine Switch track, thumb, colors, state layer opacity,
or disabled opacity.

## Lab CSS Boundary

`lab-switch.css` owns:

```txt
.lab-switch-demo
.lab-switch-header
.lab-switch-section
.lab-switch-grid
.lab-switch-card
.lab-switch-stack
.lab-switch-note
```

No unscoped `.ax-switch` selectors are introduced.

## Visual Checks Expected In Phase 3

```txt
off
on
disabled off
disabled on
focus-visible
Space key toggle
FormData participation
mobile overflow 0
console errors 0
```

## Token Boundary

The page consumes existing md-sys tokens for lab scaffolding only. No token
route or `components.css` baseline value changes are introduced.
