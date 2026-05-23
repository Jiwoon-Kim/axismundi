# Slider - Measurement Audit (v3.6.14 Phase 2)

Component: Slider #21

## Baseline Reference

Baseline authority remains `components.css section 25`.

Key measured primitives:

```txt
Track height: 16px
Track radius: 8px
Thumb width: 4px at rest / hover
Thumb width: 2px when active
Thumb height: 44px
Focus outline: thumb-level outline
```

## Specimen Coverage

The pattern page covers:

- Continuous 0-100 range.
- Step range from 1-5.
- Disabled range.
- Visible label and mirrored output value.
- Runtime-updated active fill via `--_value`.

## Phase 3 Checks

Phase 3 should verify:

```txt
visible label or aria-labelledby exists for each input
keyboard value changes with Arrow keys
PageUp / PageDown / Home / End native behavior remains available where supported
disabled input does not change
--_value updates after input
no horizontal overflow at desktop or mobile widths
```

## Verdict

The baseline measurements are sufficient for module extraction. No new
measurement token or baseline selector is required in v3.6.14.
