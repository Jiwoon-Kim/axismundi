# Slider - Runtime Audit (v3.6.14 Phase 2)

Component: Slider #21

## Runtime Scope

`lab-slider.js` is a lab-scoped fixture runtime. It synchronizes native range
input values to:

- the input's `--_value` custom property;
- the nearest `[data-slider-output]` text node.

It does not own native keyboard behavior or value semantics.

## Listener Boundary

The file uses one `DOMContentLoaded` setup listener with `{ once: true }`.

After setup, listeners are attached directly to `.lab-slider-demo
.ax-slider__input` controls for `input` and `change` events. No persistent
`document` or `window` listener is added.

## Collision Avoidance

The runtime is exposed as `window.labSlider = { init }` for fixture reuse only.
It does not use `scripts/theme.js`, does not alter global theme state, and does
not touch provider modules.

## Verdict

The runtime is bounded enough for a Component Full-Spec module. It exists only
to keep lab specimen active fill and visible value output synchronized.
