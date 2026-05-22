# Checkbox - Measurement Audit (v3.6.10 Phase 2)

## Baseline Source

```txt
components.css §22 Checkbox
selector range recorded in Phase 1: lines 3364-3549
selector count: 29
```

The module does not redefine Checkbox geometry, colors, state layer opacity,
or disabled opacity.

## Lab CSS Boundary

`lab-checkbox.css` owns:

```txt
.lab-checkbox-demo
.lab-checkbox-header
.lab-checkbox-section
.lab-checkbox-grid
.lab-checkbox-card
.lab-checkbox-stack
.lab-checkbox-note
```

No unscoped `.ax-checkbox` selectors are introduced.

## Visual Checks Expected In Phase 3

```txt
unchecked
checked
indeterminate
disabled unchecked
disabled checked
disabled indeterminate
error
focus-visible
mobile overflow 0
console errors 0
```

## Token Boundary

The page consumes existing md-sys tokens for lab scaffolding only. No token
route or `components.css` baseline value changes are introduced.
