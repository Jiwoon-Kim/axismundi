# WordPress ↔ Material Design 3 Binding

| File | Purpose |
|---|---|
| `binding_map.json` | Tier 1 (token) + Tier 2 (component) bindings |
| `block_component_rules.json` | 48 rules: BLOCK-COMPONENT-MAP → ontology grammar |
| `confidence_matrix.json` | 38 confidence-typed binding rows |
| `taxonomy.md` | 6-bucket → binding_type mapping |
| `binding_summary.md` | Human-readable summary |
| `gap_report.md` | G5.1–G5.5 + post-pilot architectural decision |
| `legitimacy_audit.json` | v2.1a-P0.5 4-axis audit (current: 1.000 PASS) |
| `pilot_validation_report.md` | Human-readable audit report |
| `_source/BLOCK-COMPONENT-MAP.md` | Original lookup table (preserved for reference) |

Tier 1 strong bindings (≥0.85):
- `wp:ThemeToken.color ↔ m3:Family.sys.color` (0.95)
- `wp:AppearanceTool ↔ M3 design-tool meta-flag` (0.90)
- `wp:ThemeToken.typography ↔ m3:Family.sys.typescale` (0.85)
