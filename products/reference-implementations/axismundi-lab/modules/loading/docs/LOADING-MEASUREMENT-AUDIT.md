# Loading - Measurement Audit (v3.6.14 Phase 2)

Component: Loading #30

## Baseline Reference

Baseline authority remains `components.css section 20`.

Key measured primitives:

```txt
Default box: 48px
Contained box: 38px x 48px
Contained inner SVG: 24px
Small box: 24px
Default stroke width: 4
Small stroke width: 3
```

## Motion

The baseline uses:

```txt
ax-loading-rotate
ax-loading-dash
prefers-reduced-motion fallback pulse
```

Phase 3 should verify that reduced-motion mode does not rotate the SVG and
uses the low-motion pulse fallback.

## Specimen Coverage

The pattern page covers:

- default standalone loading;
- contained loading;
- small inline loading inside a button.

## Verdict

The existing measurements are sufficient for module extraction. No new
measurement token or baseline selector is required in v3.6.14.
