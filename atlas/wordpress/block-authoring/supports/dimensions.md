---
rule_id: block.supports.dimensions
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "6.2"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#dimensions
    section: "Supports — dimensions (height / minHeight / minWidth / width / aspectRatio)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # dimensions flags inject the style attribute
  - block.wrapper-attributes             # generated styles flow through useBlockProps
  - block.supports.spacing               # adjacent container-semantic capability with similar style.* path
  - theme-config.json-dimensions-settings # cross-context: theme-side opt-in for dimensions UI
---

# RULE — `supports.dimensions` capability flag

## WHEN

Defining a block that should expose **sizing controls** (height,
minHeight, minWidth, width, aspectRatio) in the block inspector.
Container-semantic: these properties affect how the block sizes itself
within a parent flow, but unlike `blockGap` in spacing, they do NOT
couple to InnerBlocks — they are entirely self-owned.

Use when the block needs explicit dimensional control (e.g., a hero
block with configurable min-height, an image-like block with aspect
ratio control). Avoid when default content-flow sizing is sufficient.

## SHAPE

```json
{
  "supports": {
    "dimensions": {
      "aspectRatio": true,
      "height":      true,
      "minHeight":   true,
      "minWidth":    true,
      "width":       true
    }
  }
}
```

### Sub-property matrix

| Sub-property | Type | Default | Verification |
|---|---|---|---|
| `height` | `boolean` | `false` | listed in source Subproperties |
| `minHeight` | `boolean` | `false` | listed in source Subproperties |
| `minWidth` | `boolean` | `false` | listed in source Subproperties |
| `width` | `boolean` | `false` | listed in source Subproperties |
| `aspectRatio` | `boolean` | `false` | ⚠ appears in source example + style-injection list, but NOT in top-level "Subproperties" enumeration. Treat as supported pending verification of source omission. |

All 4-5 are self-owned (no parent-controlled equivalent in this
family — contrast with `spacing.blockGap`).

## REQUIRES

- **WP 6.2 or later** (per source: *"Since WordPress 6.2"*).
- Block MUST be registered server-side. Dimensions controls and
  theme.json preset integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element.
  (See `block.wrapper-attributes`.)
- For UI to render: theme MUST opt in per source: *"the block editor
  will show UI controls for the user to set their values if the theme
  declares support."* In theme.json, this is via
  `settings.dimensions.aspectRatio`, `settings.dimensions.minHeight`,
  etc. (each a boolean enabling the respective UI). The exact set of
  theme.json gates for each subproperty needs verification per
  WordPress version.

## INVARIANTS

### Editor effects

- A **Dimensions** panel appears in the block inspector when any
  `dimensions.*` subproperty is set to `true`, AND the theme
  declares matching support.
- Each enabled subproperty renders a corresponding numeric input or
  picker control inside the Dimensions panel (height field,
  min-height field, etc.).
- `aspectRatio` typically renders as an aspect-ratio picker (preset
  ratios like 16:9, 4:3, 1:1) plus a custom value option.
- All controls accept CSS unit values (px, em, rem, vh, vw, %, etc.)
  per the active theme's `settings.spacing.units` (or equivalent
  dimensions units setting).

### Attribute effects

| Sub-property | Attributes added |
|---|---|
| `aspectRatio` | `style` (object — `style.dimensions.aspectRatio` for selected value) |
| `height` | `style` (object — `style.dimensions.height` for custom value) |
| `minHeight` | `style` (object — `style.dimensions.minHeight` for custom value) |
| `minWidth` | `style` (object — `style.dimensions.minWidth` for custom value) |
| `width` | `style` (object — `style.dimensions.width` for custom value) |

The `style` attribute is **shared** across capability flags;
dimensions adds the `style.dimensions.*` sub-namespace.

The block can declare its own defaults for these injected attributes:

```json
{
  "attributes": {
    "style": {
      "type": "object",
      "default": {
        "dimensions": {
          "aspectRatio": "16/9",
          "minHeight":   "50vh"
        }
      }
    }
  }
}
```

### Wrapper effects

- Custom dimensions values emit inline
  `style="height: ..; min-height: ..; min-width: ..; width: ..; aspect-ratio: .."`
  on the wrapper.
- All values flow through `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` —
  required spreading per `block.wrapper-attributes`.
- Unlike spacing/color, dimensions does NOT typically emit
  preset-class names (e.g. no `has-{slug}-min-height` class).
  Custom value → inline style; preset slugs (if a preset system
  exists for dimensions in the future) would resolve via CSS custom
  properties.
- ⚠ Source does not enumerate emitted classes for dimensions
  (in contrast to color's `has-{slug}-color` and spacing's
  blockGap-related classes). Treat the wrapper output as primarily
  inline-style based pending source verification.

### Serialization effects

Block delimiter stores values as JSON under `style.dimensions.*`:

```html
<!-- wp:my-plugin/foo {"style":{"dimensions":{"aspectRatio":"16/9","minHeight":"50vh"}}} -->
```

Values are stored as their authored CSS strings (e.g., `"16/9"`,
`"50vh"`, `"400px"`). No preset-reference form is documented for
dimensions in the source — values are concrete, not deferred.

### theme.json interaction

- Theme MUST declare the respective dimensions controls in
  `theme.json` `settings.dimensions.*` for the UI to render. The
  exact key naming per subproperty (e.g., is it
  `settings.dimensions.aspectRatio: true` or
  `settings.dimensions.aspectRatios: [...]`) needs verification per
  source — the spacing-style "boolean gate" pattern is the most
  consistent assumption, but dimensions has fewer documented
  theme.json keys.
- Dimensions appear to lack a preset system equivalent to
  `spacing.spacingSizes` or `color.palette` in the documented
  source. If presets exist, they are not surfaced through this
  supports flag.
- ⚠ Theme.json `settings.dimensions` reference content is sparse
  in current source captures. Cross-reference
  `theme-config.json-dimensions-settings` (planned) once that chunk
  is written.

### General invariants

- All dimensions subproperties are **self-owned** — they affect THIS
  block's own size. No parent-controlled equivalent exists in this
  family (contrast with `spacing.blockGap`).
- All subproperties are simple booleans — no array form for
  side selection or option restriction (contrast with spacing's
  side-spec or color's per-element-elements scoping).
- All values store under `style.dimensions.*` — no top-level
  shortcut attributes (contrast with color's `backgroundColor` /
  `textColor` top-level attributes; contrast with typography's
  `fontSize` top-level attribute).
- The `style` attribute is shared across capability flags. Defining
  `attributes.style.default` for dimensions may collide with
  concurrent color / spacing / typography defaults — use a single
  default object that includes all needed namespaces.
- Dimensions values are **concrete CSS strings**, not preset
  references. Changing a dimension value requires editing the
  block; there's no theme-side update propagation like preset-based
  font-sizes or spacing scales.
- ⚠ `aspectRatio` enumeration discrepancy: source's top-level
  Subproperties list shows 4 entries (height / minHeight / minWidth /
  width), but the example code and style-injection paragraph include
  `aspectRatio`. Treat as supported pending source clarification.
- ⚠ **Minimum WP version: 6.2** — explicitly stated in source.
  Feature-detect or set `wp_min: "6.2"` if relying on this flag.

## ANTIPATTERNS

- ❌ Declaring `supports.dimensions.height: true` on WP < 6.2.
  The supports object is parsed but the controls do not render —
  silent failure on older sites.
- ❌ Declaring dimensions support without ensuring the theme has
  opted in via `theme.json` `settings.dimensions.*`. Same pattern
  as spacing: supports flag alone is insufficient; theme.json gating
  is required.
- ❌ Hardcoding height / width values in the block's `save()` or
  PHP render output. Bypasses the supports cascade — user cannot
  modify, theme cannot override.
- ❌ Storing dimensions values at top-level `style.height` /
  `style.minWidth` instead of `style.dimensions.*`. The
  documented namespace is `style.dimensions.*`; flat top-level
  paths may not round-trip through the parser correctly.
- ❌ Using `aspectRatio` together with explicit `height` AND
  `width`. Conflicting CSS — `aspect-ratio` only takes effect
  when one of `width` / `height` is unspecified or `auto`. Author
  code should validate or document the combination.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags.
- ❌ Treating `width` as a layout/positioning control. It sets the
  wrapper element's `width` CSS property — not its container slot,
  not its alignment. For "fill the parent / wide / full" semantics
  use `align` (see align supports flag) or layout primitives.

## RELATED

- `block.json-supports-field` — parent rule explaining the
  supports mechanism in general.
- `block.json-attributes-core` — dimensions flags inject the shared
  `style` attribute (under `style.dimensions.*` namespace).
- `block.wrapper-attributes` — `useBlockProps()` /
  `get_block_wrapper_attributes()` is the receiver for all
  generated inline styles produced by this rule.
- `block.supports.spacing` — sibling container-semantic capability.
  Compare: spacing has 3 properties with ownership split
  (margin/padding self vs blockGap parent-controlled); dimensions
  has 4-5 properties all self-owned. Dimensions is structurally
  simpler — no side-spec, no layout coupling.
- `theme-config.json-dimensions-settings` (cross-context, planned) —
  full theme.json `settings.dimensions` reference; the theme-side
  opt-in pattern that gates UI rendering.
