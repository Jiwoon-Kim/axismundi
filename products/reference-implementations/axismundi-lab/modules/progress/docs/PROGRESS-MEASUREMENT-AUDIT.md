# Progress - Measurement Audit (v3.6.14 Phase 2)

Component: Progress #31

## Baseline Reference

Baseline authority remains `components.css section 27`.

Key measured primitives:

```txt
Linear track height: 4px
Linear determinate value: --_value percentage
Circular box: 40px
Circular radius: 18
Circular stroke width: 4
```

## Motion

The baseline uses:

```txt
ax-progress-linear-1 / ax-progress-linear-2
ax-progress-circular-rotate
ax-progress-circular-dash
prefers-reduced-motion fallback pulse
```

Phase 3 should verify reduced-motion behavior for indeterminate linear and
circular surfaces.

## Specimen Coverage

The pattern page covers:

- linear determinate values at 25, 60, and 90;
- linear indeterminate;
- circular determinate values at 25, 60, and 90;
- circular indeterminate.

## Verdict

The existing measurements are sufficient for module extraction. No new
measurement token or baseline selector is required in v3.6.14.
