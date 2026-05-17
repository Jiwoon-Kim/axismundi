---
rule_id: block.supports.layout
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#layout
    section: "Supports — layout (default + 9 allow* flags)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # layout config injects into attribute schema
  - block.wrapper-attributes             # layout types attach via class concatenation on wrapper
  - block.inner-blocks                   # PREREQUISITE: layout only applies to container blocks
  - block.supports.spacing               # blockGap is consumed by layout (flex/grid gap)
  - block.supports.dimensions            # flex layout's allowSizingOnChildren controls child sizing
  - block.json-hierarchy-constraints     # adjacent composition concern (insertion topology)
  - theme-config.json-layout-settings    # cross-context: theme.json settings.layout (contentSize, wideSize)
---

# RULE — `supports.layout` capability flag

## WHEN

Defining a block that is a **container for inner blocks** (uses
`<InnerBlocks />` or `useInnerBlocksProps()`) and needs to declare
how those children should be laid out — flow, flex, constrained, or
grid.

This is the **first capability that is NOT a styling-emission flag** —
it is a **structural governance subsystem**. Most of its
sub-properties (9 of 10) are `allow*` flags that scope editor
controls, not values that emit CSS. Layout sits at the boundary
between supports cascade (per-block opt-in) and Gutenberg's
composition runtime (InnerBlocks orchestration, child positioning,
flow inheritance).

**Layout is fundamentally different from prior supports families:**
- color / typography / spacing / dimensions / shadow / background all
  converge to ONE execution surface (CSS emission via wrapper).
- Layout includes **governance BEFORE emission**: child controls,
  insertion topology, inheritance policy, switcher exposure.

## SHAPE

### Boolean shorthand vs object form

```json
{ "supports": { "layout": true } }
```

Boolean `true` enables layout with `flow` type as default. Use the
object form for any other configuration.

```json
{
  "supports": {
    "layout": {
      "default":           { "type": "flex", "flexWrap": "nowrap" },
      "allowSwitching":    false,
      "allowEditing":      true,
      "allowInheriting":   true,
      "allowSizingOnChildren": false,
      "allowVerticalAlignment": true,
      "allowJustification": true,
      "allowOrientation":  true,
      "allowWrap":         true,
      "allowCustomContentAndWideSize": true
    }
  }
}
```

### Sub-property matrix (10 total)

| Sub-property | Type | Default | Applies to layout type | Role |
|---|---|---|---|---|
| `default` | `Object` | `null` | (all) | Sets layout `type` + initial values for layout-type-inherent props (e.g., `flexWrap` for flex). |
| `allowSwitching` | `boolean` | `false` | (all) | Exposes a switcher control toggling between layout types in the editor. |
| `allowEditing` | `boolean` | `true` | (all) | Shows/hides the entire layout controls block in the inspector. |
| `allowInheriting` | `boolean` | `true` | **flow only** | Shows the "Inner blocks use content width" toggle. |
| `allowSizingOnChildren` | `boolean` | `false` | **flex only** | Shows Fit/Fill/Fixed sizing controls on child blocks. |
| `allowVerticalAlignment` | `boolean` | `true` | **flex only** | Shows vertical-alignment control in block toolbar. |
| `allowJustification` | `boolean` | `true` | **flex + constrained** | Shows justification control (toolbar for flex, sidebar for constrained). |
| `allowOrientation` | `boolean` | `true` | **flex only** | Shows orientation control in block toolbar. |
| `allowWrap` | `boolean` | `true` | **flex only** | Shows "Allow to wrap to multiple lines" toggle. When `false`, wrap is fixed via `layout.default.flexWrap`. |
| `allowCustomContentAndWideSize` | `boolean` | `true` | **constrained only** | Shows custom content / wide size controls. |

### Layout types referenced in source

| Type | Mentioned in |
|---|---|
| `flow` | Default for boolean form; `allowInheriting` applies |
| `flex` | 5 allow* flags scope flex-specific controls |
| `constrained` | 2 allow* flags scope constrained-specific controls |
| `grid` | Implicit in `allowSwitching` ("toggling between all existing layout types"); not explicitly described in this source section |

⚠ **Layout type inventory is partially documented.** Source
explicitly names flow, flex, constrained via allow* flag contexts.
Other types (grid, etc.) exist in WordPress but are not enumerated
in the supports.layout reference. Verify the full type list per WP
version when relying on layout switching.

## REQUIRES

- **Block MUST be a container** for inner blocks (uses
  `<InnerBlocks />`, `useInnerBlocksProps()`, or
  `<InnerBlocks.Content />`). Source: *"This value only applies to
  blocks that are containers for inner blocks."* See
  `block.inner-blocks`.
- **Block MUST have a className as its selector.** Source: *"for
  layout to work correctly, the block it applies to should have a
  classname as its selector. That classname will be concatenated
  with a layout type string to form the layout selector."* This is
  typically the auto-generated `wp-block-{namespace}-{slug}` class
  from `useBlockProps`.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element. Without
  this, the layout class cannot attach.
- Theme support is implied — `theme.json` `settings.layout` provides
  `contentSize` / `wideSize` defaults that the constrained layout
  type consumes; flex layout consumes `settings.spacing.blockGap`
  for child gaps.

## INVARIANTS

### Editor effects

The dominant H3 section for this capability — most sub-properties
exist purely to scope editor UI exposure. **Governance over styling.**

- `allowEditing: true` (default): the layout controls panel renders
  in the block sidebar. Setting `false` hides the entire panel, even
  if other allow* flags are on (overrides them).
- `allowSwitching: true`: a layout-type switcher control appears,
  letting the user toggle between flow / flex / constrained / etc.
  Default is `false` (most blocks fix their layout type via
  `default.type`).
- `allowInheriting`: ONLY for flow — toggles "Inner blocks use
  content width" (whether children inherit theme.json contentSize
  / wideSize).
- `allowSizingOnChildren`: ONLY for flex — when true, every child
  block of this flex container gets Fit / Fill / Fixed sizing
  controls (a child-side UI exposed by the parent's flag).
- `allowVerticalAlignment`: ONLY for flex — vertical align control
  appears in the block toolbar.
- `allowJustification`: flex AND constrained — justification control
  appears (toolbar for flex, sidebar for constrained).
- `allowOrientation`: ONLY for flex — orientation control (typically
  horizontal/vertical) appears in toolbar.
- `allowWrap`: ONLY for flex — when true, the wrap toggle appears.
  When false, wrap behavior is fixed by `default.flexWrap`.
- `allowCustomContentAndWideSize`: ONLY for constrained — content /
  wide size custom inputs appear.

**Net effect:** the allow* cluster is essentially a per-layout-type
**editor capability matrix**. The block author opts into controls
the user can manipulate.

### Attribute effects

- ⚠ Source does NOT explicitly document the attribute storage path
  for layout configuration. Based on the standard pattern used by
  other supports flags, layout values are likely stored at
  `style.layout` or similar, with the user-set type / per-type
  values nested. **Verify storage path empirically before relying.**
- The `default` Object provides INITIAL values when a block is first
  inserted — these are not the user's modifications but the block
  type's declared starting state.
- User modifications via the controls exposed by allow* flags would
  populate per-instance attribute values overriding `default`.

### Wrapper effects

- A **layout class is concatenated** onto the wrapper from the
  block's own className: per source, *"That classname will be
  concatenated with a layout type string to form the layout
  selector."* Inferred form: `wp-block-{name}__layout-{type}` or
  similar — exact concatenation pattern not literally specified.
- ⚠ The exact layout-class naming convention and whether per-instance
  attribute values produce additional inline CSS is not documented
  in this source section. Treat the wrapper class concatenation as
  the verified mechanism; inline-style emission is a likely
  follow-on but unverified here.
- Layout type drives core's layout-engine CSS rules (flex container
  rules for `flex` type, max-width constraints for `constrained`,
  etc.). These rules are NOT specific to this block — they're
  emitted by the global style engine based on the layout type
  associated with the wrapper class.

### Serialization effects

- ⚠ Source does not show a full serialized example of a layout-using
  block delimiter. The standard pattern (per other capabilities)
  would store user-modified layout values under `style.layout.*`
  in the comment delimiter. **Verify with actual block output.**
- The `default` configuration in supports does NOT serialize
  per-block — it's the registration-time declaration of starting
  state; only user modifications serialize.

### theme.json interaction

- `theme.json` `settings.layout.contentSize` and
  `settings.layout.wideSize` provide defaults consumed by the
  **constrained** layout type. The "constrained" type's content
  width and wide-alignment max-widths come from these settings
  unless overridden per-block via the
  `allowCustomContentAndWideSize` controls.
- For the **flex** layout type, `settings.spacing.blockGap` is
  consumed as the gap between children — this is the operational
  link between `block.supports.spacing.blockGap` (declares the
  CONTROL) and `block.supports.layout.flex` (consumes the VALUE).
- ⚠ Source does not enumerate all theme.json layout settings keys.
  Cross-context: see `theme-config.json-layout-settings` (planned)
  for the full reference.

### General invariants

- **Governance dominates styling.** 9 of 10 sub-properties are
  allow* flags whose effect is editor-UI scoping; only `default`
  carries actual styling configuration. This is qualitatively
  different from all other supports flags, where most sub-properties
  inject attributes / emit styles.
- **Type-specificity matrix** (which allow* flag applies to which
  layout type) is a documentation pattern unique to layout. Authors
  must check per-layout-type compatibility before declaring an
  allow* flag — declaring `allowOrientation: true` on a constrained
  layout has no effect (flex-only flag).
- **`default.type` is the canonical layout-type declaration.**
  Boolean `layout: true` = flow; otherwise `default.type` (within
  the object form) sets it.
- **Layout couples to InnerBlocks AND to other supports.** It is
  the only capability that REQUIRES InnerBlocks to be meaningful;
  it CONSUMES values declared by spacing (blockGap) and dimensions
  (sizing on children); and it INFLUENCES wrapper class
  concatenation that other supports (color/spacing/etc.) emit
  alongside.
- **Layout type also affects which OTHER supports flags do anything
  practical** — alignWide / alignFull controls only matter when
  layout type permits (constrained typically). The layout type is
  effectively a **runtime context** that other supports flags read
  from.
- ⚠ **Minimum WP version unknown.** Layout system has evolved
  significantly across WP 5.9 → 6.x; specific allow* flag
  introductions are version-dependent. Source does not provide
  per-flag `Since:` markers. Frontmatter `wp_min` is
  `"verification-needed"` — feature-detect or test per WP release
  if relying on specific allow* flags.

## ANTIPATTERNS

- ❌ Declaring `supports.layout: true` on a block that does NOT use
  InnerBlocks. The capability is meaningless — flow layout has no
  children to flow.
- ❌ Forgetting that the block needs a className selector. Without
  `useBlockProps()` (which auto-generates the
  `wp-block-{name}` class), the layout class concatenation has no
  base to attach to.
- ❌ Declaring `allowOrientation: true` on a constrained-type
  layout. The flag is flex-only; setting it has no effect on
  constrained / flow / grid types.
- ❌ Mismatching allow* flags to layout type more broadly:
  - flex-only: allowSizingOnChildren, allowVerticalAlignment,
    allowOrientation, allowWrap
  - flex+constrained: allowJustification
  - constrained-only: allowCustomContentAndWideSize, allowJustification
    (sidebar form)
  - flow-only: allowInheriting

  Declaring a flag on the wrong type silently does nothing.
- ❌ Setting `allowEditing: false` while expecting other allow*
  flags to still expose controls. allowEditing acts as a master
  override — false hides the entire layout panel regardless of
  other flags.
- ❌ Hardcoding layout-engine CSS rules for the block's children
  in your own stylesheets. The core style engine emits these based
  on layout type; manual overrides drift on WP version updates.
- ❌ Treating layout as a styling capability. It is primarily a
  governance capability that scopes editor controls; styling
  emission happens elsewhere (style engine, theme.json layout
  settings, blockGap consumption from spacing).
- ❌ Declaring `allowSwitching: true` for a block whose business
  logic depends on a specific layout type (e.g., a Columns block
  that breaks if switched to flow). The switcher is a user
  affordance; the block should fix its type via `default.type` and
  set `allowSwitching: false` if switching would break.
- ❌ Forgetting that `blockGap` value is consumed by layout. A flex
  block declaring `supports.layout.default.type: "flex"` will
  consume `style.spacing.blockGap` for the CSS `gap` property
  emission — the spacing supports flag declares the control, layout
  is the runtime authority that uses it.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags.

## RELATED

- `block.json-supports-field` — parent rule explaining the supports
  mechanism in general; this layout flag is an instance.
- `block.json-attributes-core` — layout values inject into the
  block's attribute schema (path verification needed).
- `block.wrapper-attributes` — layout class concatenation depends
  on the auto-generated `wp-block-{name}` class produced by
  `useBlockProps()`.
- `block.inner-blocks` — **PREREQUISITE.** Layout only applies to
  container blocks. Layout type orchestrates how inner blocks flow,
  align, and size. This is the strongest capability/composition
  coupling in the supports family.
- `block.supports.spacing` — `blockGap` value declared via spacing
  is consumed by layout (flex/grid gap CSS). This is the clearest
  case of one supports flag declaring a control whose runtime
  authority lives in another supports flag.
- `block.supports.dimensions` — flex layout's
  `allowSizingOnChildren` exposes Fit/Fill/Fixed controls on
  children — operationally adjacent to dimensions semantics.
- `block.json-hierarchy-constraints` — adjacent composition concern
  (insertion topology). Hierarchy declares WHICH children are
  allowed; layout declares HOW they are positioned.
- `theme-config.json-layout-settings` (cross-context, planned) —
  full theme.json `settings.layout` reference: contentSize,
  wideSize, allowEditing (theme-side), allowCustomContentAndWideSize
  (theme-side), and other layout-type-specific theme settings.
