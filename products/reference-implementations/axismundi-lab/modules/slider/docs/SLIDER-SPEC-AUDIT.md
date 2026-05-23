# Slider - Spec Audit (v3.6.14 Phase 2)

> **Component**: Slider #21
> **Category**: Component Full-Spec
> **Status**: Phase 2 implementation candidate
> **Companions**: `SLIDER-MEASUREMENT-AUDIT.md`, `SLIDER-RUNTIME-AUDIT.md`, `SLIDER-WP-MAPPING.md`

## Scope

```txt
In scope:
  - Native input[type="range"]
  - Continuous value examples
  - Step / bounded range example
  - Disabled state
  - Visible labels with aria-labelledby
  - Lab-local --_value and output synchronization

Out of scope:
  - Range slider with two thumbs
  - Vertical slider
  - Tick marks and stops
  - WordPress block registration
  - Baseline CSS mutation
```

## Native / ARIA Boundary

The implementation uses native range inputs:

```html
<label id="slider-volume-label" for="slider-volume">Volume</label>
<input id="slider-volume"
       class="ax-slider__input"
       type="range"
       min="0"
       max="100"
       value="40"
       aria-labelledby="slider-volume-label">
```

The native control owns keyboard behavior and value semantics. The visible
output text is marked `aria-hidden="true"` because it mirrors the native input
value rather than adding a second announcement target.

## Baseline Contract

Baseline authority remains `components.css section 25`:

```txt
.ax-slider
.ax-slider__input
.ax-slider__value
```

The baseline already anticipates author or JS-driven active fill through the
`--_value` custom property. The lab runtime uses that hook without changing the
baseline selector contract.

## Runtime Boundary

`lab-slider.js` is included, so Slider uses the modern 4-doc shape. The runtime
only updates:

```txt
input.style --_value
[data-slider-output] text
```

It does not polyfill native range behavior, does not create a provider, and
does not attach document/window listeners beyond `DOMContentLoaded`.

## Verdict

Slider can proceed as a native-input Full-Spec lab module with a bounded
fixture runtime and no baseline or provider edits.
