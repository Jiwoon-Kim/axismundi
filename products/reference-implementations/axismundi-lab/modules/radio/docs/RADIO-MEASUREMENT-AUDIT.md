# Radio - Measurement Audit (v3.6.10 Phase 2)

## Baseline Source

```txt
components.css §23 Radio button
selector range recorded in Phase 1: lines 3562-3695
selector count: 20
```

The module does not redefine Radio geometry, colors, state layer opacity, or
disabled opacity.

## Lab CSS Boundary

`lab-radio.css` owns:

```txt
.lab-radio-demo
.lab-radio-header
.lab-radio-section
.lab-radio-grid
.lab-radio-card
.lab-radio-stack
.lab-radio-fieldset
.lab-radio-note
```

No unscoped `.ax-radio` selectors are introduced.

## Visual Checks Expected In Phase 3

```txt
unchecked
checked
disabled
focus-visible
fieldset / legend visible to accessibility tree
same-name group one selected value
mobile overflow 0
console errors 0
```

## Token Boundary

The page consumes existing md-sys tokens for lab scaffolding only. No token
route or `components.css` baseline value changes are introduced.
