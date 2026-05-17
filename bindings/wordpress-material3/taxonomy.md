# Block ↔ Component Binding Taxonomy

v2.1a normalization of `BLOCK-COMPONENT-MAP.md` from intuitive lookup table to 
rule-based binding ontology. Each M3 component binding is classified by:

- **binding_type** — ontology category (Direct / Compositional / Composite / OutOfScope / RuntimeOnly)
- **ontology_strength** — strong / conditional / out_of_scope / runtime_only
- **implementation_path** — concrete realization mechanism

## Distribution by binding_type

| binding_type | count |
|---|---|
| Direct.CoreBlockStyle | 14 |
| Direct.CustomBlock | 11 |
| OutOfScope.Handoff | 9 |
| Composite.TemplatePart | 7 |
| RuntimeOnly.ThemeJS | 6 |
| Compositional.BlockPattern | 1 |

## Distribution by legacy bucket

| bucket | count | meaning |
|---|---|---|
| A | 14 | Direct.CoreBlockStyle |
| C | 11 | Direct.CustomBlock |
| E | 9 | OutOfScope.Handoff |
| D | 7 | Composite.TemplatePart |
| F | 6 | RuntimeOnly.ThemeJS |
| B | 1 | Compositional.BlockPattern |

**Total rules**: 48 | **Deferred** (Tier 3+): 0

## Rule samples by type

### Button — filled (Direct.CoreBlockStyle)

- **rule_id**: `binding.button---filled`
- **bucket**: A
- **ontology_strength**: strong
- **implementation_path**: block_style_registration
- **block_refs**: `core/button`
- **style_classes**: `is-style-filled`

### Icon button (Direct.CustomBlock)

- **rule_id**: `binding.icon-button`
- **bucket**: C
- **ontology_strength**: strong
- **implementation_path**: custom_block_plugin
- **block_refs**: `m3/icon-button`

### FAB (sm/md/lg) (Composite.TemplatePart)

- **rule_id**: `binding.fab-smmdlg`
- **bucket**: D
- **ontology_strength**: strong
- **implementation_path**: fse_template_part

### FAB menu (OutOfScope.Handoff)

- **rule_id**: `binding.fab-menu`
- **bucket**: E
- **ontology_strength**: out_of_scope
- **implementation_path**: external_plugin_styling_only

### List item (with leading/trailing) (Compositional.BlockPattern)

- **rule_id**: `binding.list-item-with-leadingtrailing`
- **bucket**: B
- **ontology_strength**: conditional
- **implementation_path**: block_pattern_authoring
- **block_refs**: `core/group`, `core/group`
- **evaluation_rule**: WHEN block participates in pattern AND its BlockType.supports profile includes [] THEN this binding is applicable.

### Dialog (RuntimeOnly.ThemeJS)

- **rule_id**: `binding.dialog`
- **bucket**: F
- **ontology_strength**: runtime_only
- **implementation_path**: theme_js_api
- **block_refs**: `m3/dialog-trigger`
