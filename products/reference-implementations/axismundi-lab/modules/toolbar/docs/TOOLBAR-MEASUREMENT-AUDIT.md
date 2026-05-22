# Toolbar Measurement Audit

Component: Toolbar #8

Cycle: v3.6.13

## Baseline Reuse

The module consumes existing `components.css §29` measurements:

- docked height: 64px
- docked inline padding: 16px
- floating height: 64px
- floating inline padding: 8px
- action gap: 4px
- floating radius: full
- floating elevation: level3

## Lab CSS Boundary

`lab-toolbar.css` provides validation layout only and does not redefine
unscoped `.ax-toolbar` or slot control selectors.

## Ripple Geometry

- icon-button slots: unbounded ripple
- text button slots: bounded ripple
- spacers/separators: no ripple host
