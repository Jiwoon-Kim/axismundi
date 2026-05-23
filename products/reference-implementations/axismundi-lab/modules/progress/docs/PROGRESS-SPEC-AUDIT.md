# Progress - Spec Audit (v3.6.14 Phase 2)

> **Component**: Progress #31
> **Category**: Component Full-Spec
> **Status**: Phase 2 implementation candidate
> **Companions**: `PROGRESS-MEASUREMENT-AUDIT.md`, `PROGRESS-WP-MAPPING.md`

## Scope

```txt
In scope:
  - Linear determinate progress
  - Linear indeterminate progress
  - Circular determinate progress
  - Circular indeterminate progress
  - role="progressbar"
  - aria-valuemin / aria-valuemax / aria-valuenow for determinate examples
  - Reduced-motion baseline behavior

Out of scope:
  - Wavy progress variant
  - JavaScript runtime
  - Loading spinner replacement
  - WordPress block registration
  - Baseline CSS mutation
```

## Semantics Boundary

Determinate progress uses:

```html
<div class="ax-progress-linear is-determinate"
     style="--_value: 60%;"
     role="progressbar"
     aria-valuenow="60"
     aria-valuemin="0"
     aria-valuemax="100"></div>
```

Indeterminate progress omits `aria-valuenow` because the current value is not
known.

## Baseline Contract

Baseline authority remains `components.css section 27`:

```txt
.ax-progress-linear
.ax-progress-circular
.ax-progress-circular__svg
.ax-progress-circular__track
.ax-progress-circular__active
```

## Runtime Boundary

Progress is CSS/SVG only in v3.6.14. No RUNTIME audit is required.

## Verdict

Progress can proceed as a provider-free and JS-free Full-Spec lab module.
