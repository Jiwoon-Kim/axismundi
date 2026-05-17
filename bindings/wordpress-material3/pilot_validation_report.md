# v2.1a-P0.5 — Binding Legitimacy Audit Report

**Pilot target**: `axismundi-theme-pilot-v0.1`
**Baseline**: v2.1a-P0 binding map (6 token bindings, 48 binding rules)
**Audit date**: 2026-05-12

## Overall verdict

- **Score**: 1.000 / 1.000
- **Threshold (≥0.85)**: PASS ✓
- **Verdict**: **PASS**

## 4-Axis breakdown

| Axis | Description | Score | Weight |
|---|---|---|---|
| A — Schema | theme.json slugs ↔ M3 ontology roles | **1.000** | 0.30 |
| B — Theme | appearanceTools + lock-down flags | **1.000** | 0.20 |
| C — CSS | tokens.css + base.css + block-styles.css | **1.000** | 0.20 |
| D — Runtime | block style registrations ↔ binding rules | **1.000** | 0.30 |

## Axis A — Schema (theme.json ↔ M3 ontology)

### A1_color_palette

- `m3_roles_total`: 36
- `pilot_slugs_total`: 36
- `matching`: 36
- `missing_in_pilot`: []
- `extra_in_pilot`: []
- `score`: 1.0

### A2_typography_fontSizes

- `m3_roles_total`: 15
- `pilot_slugs_total`: 15
- `matching`: 15
- `missing_in_pilot`: []
- `score`: 1.0

### A3_shadow_presets

- `m3_levels_total`: 6
- `pilot_slugs_total`: 6
- `matching`: 6
- `score`: 1.0

## Axis B — Theme (appearanceTools + lock-down)

- **B1_appearanceTools**: expected=True, actual=True, score=1.0
- **B2_color_custom_lockdown**: expected=False, actual=False, score=1.0
- **B3_customFontSize_lockdown**: expected=False, actual=False, score=1.0
- **B4_useRootPaddingAwareAlignments**: expected=True, actual=True, score=1.0

## Axis C — CSS (asset presence + token usage)

- **C_assets/css/tokens.css**: {'exists': True, 'size_bytes': 40360, 'score': 1.0}
- **C_assets/css/base.css**: {'exists': True, 'size_bytes': 29433, 'score': 1.0}
- **C_assets/css/block-styles.css**: {'exists': True, 'size_bytes': 7113, 'score': 1.0}
- **C4_block_styles_token_usage**: {'m3_var_references': 45, 'hex_literal_count': 0, 'score': 1.0}

## Axis D — Runtime (block style registrations)

### D1_registered_count

- `count`: 12
- `registrations`: ['core/button::elevated', 'core/button::filled', 'core/button::outlined', 'core/button::text', 'core/button::tonal', 'core/group::card-elevated', 'core/group::card-filled', 'core/group::card-outlined', '...']

### D2_binding_coverage

- `expected_from_binding_rules`: 12
- `registered_in_pilot`: 12
- `matching`: 12
- `missing_in_pilot`: []
- `extra_in_pilot`: []
- `score`: 1.0

### D3_enqueue_order

- `enqueues`: ['axismundi-tokens', 'axismundi-base', 'axismundi-block-styles']
- `expected`: ['axismundi-tokens', 'axismundi-base', 'axismundi-block-styles']
- `score`: 1.0

## Decision

✓ **PASS — pilot binding legitimacy verified.**

Strong bindings (color/typography/appearanceTools) are operationalized in code.
Proceed to **v2.1a-P1** (M3-COMPONENT-SPECS Tier 1 component ontology).