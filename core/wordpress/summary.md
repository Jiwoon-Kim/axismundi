# WordPress Ontology Core v0.2

Generated: 2026-05-12T14:15:36Z
Baseline: WP 6.9.4 @ 97b7f62a (2026-03-11), Gutenberg v23.1.1 @ 12c6c76e (2026-05-08)

## 2-Axis Architecture

| Axis | Root | Entities |
|---|---|---|
| Block | wp:BlockType | 84 |
| Theme | wp:ThemeJSON | 22 |
| Bridges | wp:BlockThemeBridge | 8 |
| **Total** | | **114** |

## @type distribution
- wp:BlockTypeProperty: 31
- wp:BlockSupportInstance: 29
- wp:GlobalSetting: 8
- wp:BlockThemeBridge: 8
- wp:Enumeration: 6
- wp:AttributeFacet: 6
- wp:TokenSubclass: 5
- wp:DerivedSlot: 4
- wp:ContainmentRule: 3
- wp:ThemeTokenInstance: 3
- wp:RootEntity: 2
- wp:ContextIO: 2
- wp:Identifier: 1
- wp:HookSurface: 1
- wp:CapabilityClass: 1
- wp:DataModelClass: 1
- wp:TokenClass: 1
- wp:Bridge: 1
- wp:CustomToken: 1

## Tier distribution
- Tier3_DataModel: 43
- Tier1_Identity: 28
- Tier2_Composition: 9
- Tier_Bridge: 9
- Tier4_Runtime: 8
- Tier5_Context: 6
- Tier2_TokenSubclass: 5
- Root: 2
- Tier3_DataModel_Derived: 2
- Tier3_Derived: 2

## Provenance distribution
- schema+instance: 64
- schema_only: 5
- schema+php_runtime: 5
- 3/5_partial: 5
- schema+instance+atlas+runtime+docs: 4
- schema+runtime+docs+corpus: 3
- instance_derived_enum: 2
- schema+js_runtime: 1
- instance_only: 1
- schema+instance_partial: 1
- schema+instance+atlas (no runtime default): 1

## Atlas + Binding readiness
- Entities with `atlas_rule_id`: 54
- Entities with `binding_candidates`: 17

## v0.2 milestone
- v0.1 (Block) + v0.1 (Theme) → v0.2 unified
- WordPress ontology has 2 axes + Block↔Theme bridges
- Material binding readiness: appearanceTools + spacing + typography + color confirmed perfect (5/5 source agreement)
- Next: P5 data-core-block-editor (Store ontology) → v0.3
