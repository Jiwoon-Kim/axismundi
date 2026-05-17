---
rule_id: block.supports.spacing
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#spacing
    section: "Supports — spacing (margin / padding / blockGap)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # spacing flags inject the style attribute
  - block.wrapper-attributes             # generated styles flow through useBlockProps
  - block.supports.color                 # adjacent capability with shared style.* namespace
  - block.supports.typography            # adjacent capability — content-side counterpart
  - block.inner-blocks                   # blockGap propagates to child layout — coupling point
  - theme-config.json-spacing-settings   # cross-context: settings.spacing presets / spacingSizes / spacingScale
  - theme-config.json-layout-settings    # cross-context: blockGap's layout coupling
---

# RULE — `supports.spacing` capability flag

## WHEN

Defining a block that should expose **spacing controls** (margin,
padding, blockGap) in the block inspector. This is the **first
container-semantic capability** in the supports family — unlike color
and typography (content-semantic), spacing affects how the block sits
in its parent flow and (for blockGap) how its own children flow.

The 3 subproperties have **distinct ownership semantics**:

- `margin` — self-owned outer space (the block pushes away from
  surrounding siblings).
- `padding` — self-owned inner space (the block holds children away
  from its own edges).
- `blockGap` — **parent-controlled inter-child spacing** (the block,
  acting as a parent, dictates the gap between its own InnerBlocks
  children). Operationally distinct from margin/padding — couples to
  layout primitives, not to content positioning.

## SHAPE

```json
{
  "supports": {
    "spacing": {
      "margin":   true,
      "padding":  true,
      "blockGap": true
    }
  }
}
```

### Sub-property matrix

| Sub-property | Type | Default | Ownership | Side spec |
|---|---|---|---|---|
| `margin` | `boolean` \| `string[]` | `false` | self-owned (outer) | arbitrary OR axial sides |
| `padding` | `boolean` \| `string[]` | `false` | self-owned (inner) | arbitrary OR axial sides |
| `blockGap` | `boolean` \| `string[]` | `false` | **parent-controlled** (inter-child) | **axial sides only** |

### Side specification

Two mutually exclusive forms:

- **Arbitrary sides:** `['top', 'right', 'bottom', 'left']` — pick
  any subset; only those sides get UI controls.
- **Axial sides:** `['vertical', 'horizontal']` — `vertical` controls
  both top + bottom together, `horizontal` controls both left + right.

Per source: *"A spacing property may support arbitrary individual
sides OR axial sides, but not a mix of both."*

### `blockGap` constraints

```json
{
  "supports": {
    "spacing": {
      "blockGap": [ "vertical", "horizontal" ]
    }
  }
}
```

`blockGap` accepts ONLY axial sides — controls the column-gap
(`horizontal`) and row-gap (`vertical`) of children. **Arbitrary
sides on blockGap are not supported.**

### Mixed example

```json
{
  "supports": {
    "spacing": {
      "margin":   [ "top", "bottom" ],     // arbitrary
      "padding":  true,                     // all 4 sides
      "blockGap": [ "horizontal", "vertical" ]  // axial only — required for blockGap
    }
  }
}
```

## REQUIRES

- Block MUST be registered server-side. Spacing controls and
  theme.json preset integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element.
  (See `block.wrapper-attributes`.)
- For UI to render at all: theme MUST declare spacing support per
  source: *"the block editor will show UI controls for the user to
  set their values if the theme declares support."* In modern
  themes, this is via `theme.json` `settings.spacing.padding`,
  `settings.spacing.margin`, `settings.spacing.blockGap` (each a
  boolean enabling the respective UI).
- For preset values to appear in the picker, theme.json MUST
  provide a spacing scale via either:
  - `settings.spacing.spacingSizes` (explicit array of
    `{ name, slug, size }`), OR
  - `settings.spacing.spacingScale` (algorithmic generator
    spec — operator/increment/steps).
- For `blockGap` to take effect on the rendered output, the block
  ALSO needs a `layout` declaration (in `block.json` `supports.layout`
  or via theme.json `settings.layout`). Per source comment in the
  example: *"Enables block spacing UI control for blocks that also
  use `layout`."*

## INVARIANTS

### Editor effects

- A **Spacing** panel appears in the block inspector when any
  `spacing.*` subproperty is set to `true`, AND the theme declares
  matching support.
- `padding` and `margin` render **dimension controls** with
  per-side inputs (top / right / bottom / left, OR vertical /
  horizontal axial pairs based on the array spec).
- `blockGap` renders a single gap control (or two if both axial
  sides are enabled separately).
- The picker shows preset values from
  `theme.json` `settings.spacing.spacingSizes` (or generated from
  `spacingScale`). A custom-value input is also shown when the
  custom-spacing setting is enabled.

### Attribute effects

| Sub-property | Attributes added |
|---|---|
| `margin` | `style` (object — `style.spacing.margin` for custom values, OR preset slug stored as object form) |
| `padding` | `style` (object — `style.spacing.padding` for custom values) |
| `blockGap` | `style` (object — `style.spacing.blockGap` for the gap value) |

The `style` attribute is **shared** across capability flags; spacing
adds the `style.spacing.*` sub-namespace.

Per-side custom values use object form:

```json
{
  "style": {
    "spacing": {
      "margin":  { "top": "20px", "bottom": "16px" },
      "padding": "16px",
      "blockGap": { "top": "1rem", "left": "2rem" }
    }
  }
}
```

A string value (e.g., `"16px"` for padding) means "all 4 sides equal".

Preset selection serializes as a `var:preset|spacing|{slug}` reference
string in the same `style.spacing.*` paths — the editor stores the
preset reference, not the resolved value.

### Wrapper effects

- Custom spacing values emit inline `style="margin: ..; padding: ..;
  --wp--style--block-gap: .."` on the wrapper.
- Preset selections emit CSS custom property references — the wrapper's
  inline style uses `var(--wp--preset--spacing--{slug})`.
- `blockGap` translates to the `--wp--style--block-gap` CSS custom
  property. This variable is consumed by the block's `layout` styles
  (typically through core layout flex/grid styles).
- The "missing" portion: the layout selector that ACTUALLY APPLIES
  `--wp--style--block-gap` to children (via `gap` CSS property) lives
  in the block's layout output, not in the wrapper class itself.
  blockGap's UI is in this rule; its rendering effect is layout-rule
  territory.
- All effects flow through `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` —
  required spreading per `block.wrapper-attributes`.

### Serialization effects

Block delimiter stores values as JSON under `style.spacing.*`:

```html
<!-- wp:my-plugin/foo {"style":{"spacing":{"padding":"16px","blockGap":"1rem"}}} -->
```

Preset references serialize as the `var:preset|spacing|{slug}` form:

```html
<!-- wp:my-plugin/foo {"style":{"spacing":{"padding":"var:preset|spacing|40"}}} -->
```

The preset slug round-trips intact; resolution to actual CSS values
happens at render time via core's style engine.

### theme.json interaction

- **Theme MUST opt in for spacing controls to appear.** Source explicitly
  gates UI rendering on theme support — the supports flag alone is not
  enough. theme.json keys (each a boolean):
  - `settings.spacing.padding`
  - `settings.spacing.margin`
  - `settings.spacing.blockGap`
- Preset values come from `settings.spacing.spacingSizes` (explicit
  list) or `settings.spacing.spacingScale` (formula-generated).
- `settings.spacing.units` (theme.json) restricts which CSS units
  appear in the custom-value input (e.g., `["px", "em", "rem", "%"]`).
- `settings.spacing.customSpacing` (theme.json, default behavior
  varies by API version) gates whether the custom-pixel input
  appears alongside the preset picker.
- `blockGap` resolution depends on `settings.layout` cooperation —
  the layout primitives (flex / grid / flow) consume the
  `--wp--style--block-gap` variable to apply child spacing.
- ⚠ The exact layered cascade between theme.json `styles.spacing.*`
  (theme-wide spacing defaults), block-level `theme.json`
  `styles.blocks.{name}.spacing.*` (per-block-type defaults), and
  per-instance attribute `style.spacing.*` (user choice) is the
  three-level cascade pattern shared with color/typography. Verify
  precedence per implementation if relying on it.

### General invariants

- **Ownership distinction is critical:**
  - `margin` / `padding` modify the BLOCK's own outer/inner space.
    They are properties of the rendered wrapper element.
  - `blockGap` modifies the SPACING BETWEEN THE BLOCK'S CHILDREN.
    It only takes effect if the block has layout support and contains
    inner blocks (typically via `<InnerBlocks />`).
- **`blockGap` is NOT a margin substitute.** A block with InnerBlocks
  using blockGap is dictating how its children space themselves
  internally. The block's own margin/padding still control its own
  position relative to its parent.
- **Side spec is mutually exclusive.** Within a single subproperty,
  arbitrary sides (`top` / `right` / `bottom` / `left`) and axial
  sides (`vertical` / `horizontal`) cannot be combined. Mixing
  produces undefined behavior.
- **`blockGap` requires axial-only sides.** Arbitrary side names on
  blockGap are not supported.
- **Per-side custom values use object form;** all-sides-equal uses
  string form. The serialized representation distinguishes by JSON
  shape: `"16px"` (string) vs `{ "top": "16px" }` (object).
- **Preset references are deferred resolution.** The serialized
  block carries the preset slug; the actual CSS value is resolved
  at render time. Changing the preset's value in theme.json
  immediately propagates to all blocks using that preset slug — no
  re-save required.
- ⚠ **Minimum WP version unknown.** spacing is part of original
  Block API. The `blockGap`-with-layout integration likely arrived
  with the layout API (later than original). Frontmatter `wp_min`
  is `"verification-needed"`; specific layout coupling may need
  WP 5.9+.

## ANTIPATTERNS

- ❌ Declaring `supports.spacing.padding: true` without ensuring
  the theme declares `settings.spacing.padding: true`. UI does not
  render — control is silently absent.
- ❌ Declaring `supports.spacing.blockGap: true` on a block that
  does NOT use `<InnerBlocks />` and has no `layout` declaration.
  The control may render but has nothing to act upon — child
  spacing requires children + layout.
- ❌ Mixing arbitrary and axial sides in one subproperty
  (e.g., `padding: ["top", "vertical"]`). Per source: *"a spacing
  property may support arbitrary individual sides OR axial sides,
  but not a mix of both."*
- ❌ Declaring `blockGap: ["top", "bottom"]` (arbitrary sides on
  blockGap). Not supported. Use `["vertical", "horizontal"]` axial
  form.
- ❌ Treating `blockGap` as equivalent to `margin` between siblings.
  blockGap is INTERNALLY emitted by the parent's layout style;
  margin is OUTWARDLY emitted by each child. Different ownership
  → different runtime behavior (margin collapsing, etc.).
- ❌ Hardcoding spacing values in the block's `save()` / render
  output. Bypasses both the supports cascade and theme.json preset
  resolution — user can't change values; theme spacing scale has
  no effect.
- ❌ Storing margin/padding values directly at `style.margin` /
  `style.padding` (top-level of `style`). The correct path is
  `style.spacing.margin` / `style.spacing.padding`. The top-level
  paths exist in the source's brief example (line "style: { margin:
  'value', padding: { top: 'value' } }") but the full ontology
  with `style.spacing.*` namespace is the canonical form (consistent
  with theme.json styles.spacing.* cascade). Verify with
  serialized output of an actual block before relying on either
  path.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags.
- ❌ Setting `customSpacing: false` in theme.json AND providing no
  `spacingSizes` / `spacingScale`. Leaves the picker entirely
  empty — controls render but have no values.

## RELATED

- `block.json-supports-field` — parent rule explaining the supports
  mechanism in general; this spacing flag is an instance.
- `block.json-attributes-core` — spacing flags inject the shared
  `style` attribute (under `style.spacing.*` namespace).
- `block.wrapper-attributes` — `useBlockProps()` /
  `get_block_wrapper_attributes()` is the receiver for all
  generated inline styles produced by this rule.
- `block.supports.color` — sibling capability with the same
  5-layer cascade pattern. Color is content-semantic (visual
  surface treatment); spacing is container-semantic (positioning /
  flow).
- `block.supports.typography` — sibling content-semantic
  capability. Cross-compare for the content/container split.
- `block.inner-blocks` — `blockGap` is the only supports
  subproperty whose RUNTIME effect requires InnerBlocks: it
  controls child-to-child spacing inside the InnerBlocks tree.
  This is the first capability flag with explicit InnerBlocks
  coupling.
- `theme-config.json-spacing-settings` (cross-context, planned) —
  full theme.json `settings.spacing` reference: padding/margin/blockGap
  enables, spacingSizes (explicit presets), spacingScale (algorithmic),
  units, customSpacing.
- `theme-config.json-layout-settings` (cross-context, planned) —
  blockGap's runtime effect depends on the block's layout style
  consuming `--wp--style--block-gap`. The layout primitives
  (flex / grid / flow) handle the actual `gap` CSS property
  emission.
