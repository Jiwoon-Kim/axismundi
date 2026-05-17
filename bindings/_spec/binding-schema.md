# Binding Schema — D-layer grammar specification

> Every binding entry in `bindings/*/` must conform to this schema. This is the type-system of the binding layer itself.

A binding is **not** an equivalence. It is a typed translation between two ontology axes with explicit confidence, pattern, and provenance.

---

## 1. Binding entry — required fields

```yaml
# Required fields (all bindings)
binding_id:           # globally unique, kebab-case (e.g., "wp-color-m3-sys-color")
binding_type:         # ontology category (see §3)
source_axis:          # ontology axis A (e.g., "wp", "ap")
target_axis:          # ontology axis B (e.g., "m3", "fluent", "ap")
source_anchor:        # entity ID in axis A (e.g., "wp:ThemeToken.color")
target_anchor:        # entity ID in axis B (e.g., "m3:Family.sys.color")
confidence:           # 0.0–1.0 (see §4)
binding_pattern:      # translation strategy (see §5)
translation_rule:     # prose explanation of the mapping
source_minimum:       # number of independent sources agreeing (≥3 for confidence ≥0.85)
```

```yaml
# Optional fields
provenance:           # where the binding came from (atlas rule, manual curation, derived)
atlas_anchor_source:  # if grounded in atlas rule
atlas_anchor_target:  # if target side has atlas equivalent
binding_gap:          # known limitations or scope mismatches
render_mode:          # how the binding manifests at runtime (css_var_chain, php_filter, js_hook, ...)
fallback_mode:        # what happens when binding can't apply (default_to_source, error, no_op)
deferred:             # boolean — postpone to a future version
v_introduced:         # version the binding was added
```

---

## 2. Binding entry — required fields per binding_type

Different binding types require different additional fields:

### Direct.* bindings (1:1 mapping)

```yaml
binding_type: Direct.CoreBlockStyle  # or Direct.CustomBlock, Direct.SchemaField
implementation_path:  # "block_style_registration", "custom_block_plugin", "schema_property"
operational_artifact: # path to the file that operationalizes the binding
```

### Compositional.* bindings (composition of multiple source entities → one target)

```yaml
binding_type: Compositional.BlockPattern  # or Compositional.AttributeProfile
composition_requires:           # list of source entity IDs that must co-occur
composition_requires_supports:  # for WP block patterns: BlockSupport list
evaluation_rule:                # WHEN/THEN style rule
```

### Composite.* bindings (single source → composition of target entities)

```yaml
binding_type: Composite.TemplatePart  # or Composite.ComponentTree
target_components:    # list of target entity IDs that the source maps to (collectively)
composition_strategy: # "wraps_all", "delegates_subset", "alternates"
```

### OutOfScope.* bindings (intentional non-mapping)

```yaml
binding_type: OutOfScope.Handoff  # or OutOfScope.Discard
out_of_scope_reason:  # "form_plugin_handoff", "deferred_for_v1", "platform_paradigm_conflict"
handoff_target:       # what the actual implementation should be (e.g., "CF7 / WPForms / Gravity")
```

### RuntimeOnly.* bindings (no static binding, runtime-only)

```yaml
binding_type: RuntimeOnly.ThemeJS  # or RuntimeOnly.WebComponent
runtime_kind:         # "imperative_api", "directive", "event_listener"
no_static_artifact:   # boolean — true means binding has no file representation
```

---

## 3. binding_type enumeration

The current set of valid binding types. New types require updating this schema first, then the binding layer.

| Family | Members | Strength |
|---|---|---|
| `Direct.*` | `Direct.CoreBlockStyle`, `Direct.CustomBlock`, `Direct.SchemaField` | strong |
| `Compositional.*` | `Compositional.BlockPattern`, `Compositional.AttributeProfile` | conditional |
| `Composite.*` | `Composite.TemplatePart`, `Composite.ComponentTree` | strong |
| `OutOfScope.*` | `OutOfScope.Handoff`, `OutOfScope.Discard` | out_of_scope |
| `RuntimeOnly.*` | `RuntimeOnly.ThemeJS`, `RuntimeOnly.WebComponent` | runtime_only |

`ontology_strength` is derived from the binding_type family (don't duplicate it as a separate field unless it diverges from the family default).

---

## 4. Confidence taxonomy

Confidence must be earned through source agreement. Inflated confidence is the worst failure mode of D layer.

| Range | Meaning | Source minimum | Action |
|---|---|---|---|
| 0.85–1.0 | strong | 3+ independent sources agree | proceed with implementation |
| 0.70–0.85 | moderate | 2 sources agree + 1 partial | proceed with documented binding pattern |
| 0.50–0.70 | weak | 1 source + heuristic | design decision required, document gap |
| <0.50 | speculative | exploratory | NOT for production binding layer |

Sources for WP↔M3 bindings include:
1. WordPress dev handbook (corpus)
2. Gutenberg block API docs (corpus)
3. Gutenberg schema files (block.json, theme.json)
4. WP/Gutenberg PHP/JS runtime (default values, register_block_type calls)
5. Atlas rules (knowledge layer)

5/5 agreement → confidence 0.90+. 3/5 with no atlas conflict → 0.85.

---

## 5. binding_pattern enumeration

The *how* of translation. New patterns require schema update.

| Pattern | Meaning | Example |
|---|---|---|
| `role_to_slug` | Source role name becomes target slug | M3 sys-color role `primary` → WP palette slug `primary` |
| `role_to_slug_plus_utility_class` | Slug + accompanying CSS class for missing properties | M3 typescale role → WP fontSize + `.t-{role}` utility |
| `level_to_preset` | Stepped/numbered values map to preset list | M3 elevation level0..5 → WP shadow presets |
| `meta_flag_to_capability_bundle` | Single boolean enables a bundle of capabilities | `appearanceTools=true` → 12 BlockSupport sub-properties |
| `child_whitelist` | Containment constraint with explicit allow-list | core/buttons.allowedBlocks: [core/button] |
| `descendant_constraint` | Transitive (any-depth) containment | block.ancestor: [core/query] |
| `radius_token_subset` | Subset of properties from one ontology projects to another | M3 sys-shape corners → WP border.radius (excludes style/width) |
| `wp_authoritative_with_recommendation` | One side has the canonical spec; the other recommends compatibility | WP spacingScale (M3 has no spacing baseline) |
| `composition_with_supports_profile` | Block Pattern requires specific BlockSupport profile | List item: core/group + supports.spacing + supports.color |

---

## 6. Versioning + lifecycle

A binding entry may be in one of these lifecycle states:

```yaml
state: stable      # default, no further changes expected
state: evolving    # may change, treat as provisional
state: deprecated  # superseded; do not use in new products
state: experimental # under active investigation
state: archived    # historical record, no current use
```

If `state == deprecated`:

```yaml
deprecated_in:    # version that deprecated this binding
deprecated_by:    # binding_id that replaces this
deprecation_rationale: # prose
```

---

## 7. Gap declarations

Every binding directory must include a `gap_report.md` documenting:

- Unbound source entities (in source ontology, not yet bound to target)
- Unbound target entities (in target ontology, no source counterpart)
- Confidence-limited bindings (why not stronger)
- Coverage statistics

This is how the binding layer stays honest. Gaps are not failures; they are scope statements.

---

## 8. Validation

A binding layer is well-formed if:

1. Every entry conforms to §1 + applicable §2 fields
2. Every `binding_type` is in the §3 enumeration
3. Every `binding_pattern` is in the §5 enumeration
4. Confidence is consistent with source_minimum (§4)
5. Atlas anchors (if present) resolve to actual atlas rule IDs
6. Source anchors and target anchors resolve to actual entities in their respective ontologies

The validator (`tools/validators/validate_binding_layer.py` — planned) checks all six.

---

## 9. Example — well-formed binding

```yaml
binding_id: wp-color-palette-m3-sys-color
binding_type: Direct.SchemaField
source_axis: wp
target_axis: m3
source_anchor: wp:ThemeToken.color
target_anchor: m3:Family.sys.color
confidence: 0.95
binding_pattern: role_to_slug
source_minimum: 5
translation_rule: |
  WP theme.json settings.color.palette[].slug binds to M3 sys-color role.
  Each M3 role becomes a palette entry whose color value is var(--md-sys-color-{role}).
  Lock-down: color.custom=false enforces M3 role set as the only available palette.
provenance: P4-pilot-5-way-agreement
atlas_anchor_source: theme-config.json-settings-color
render_mode: css_var_chain
fallback_mode: default_to_source
v_introduced: v2.1a-P0
state: stable
```
