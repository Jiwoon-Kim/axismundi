# Split Button Measurement Audit

Component: Split button #7

Cycle: v3.6.13

## Baseline Reuse

The module consumes existing `components.css` Chunk H2 measurements:

- segment gap: 2px
- default inner corner: extra-small
- large inner corner: small
- extra-large inner corner: medium
- outer corners: full pill
- hover inner corner morph: small
- open trailing segment: full pill
- trailing icon: 20px

## Lab CSS Boundary

`lab-split-button.css` only defines validation-page layout:

```txt
.lab-split-button-demo ...
.lab-split-button-card ...
.lab-split-button-row ...
```

It does not redefine unscoped `.ax-split-button`, `.ax-button`,
`[data-popover-trigger]`, or provider selectors.

## Ripple Geometry

Each enabled segment uses bounded ripple. Disabled menu items do not receive
animated ripple.
