---
rule_id: theme-config.json-settings-layout
domain: theme-config
topic: settings
field_cluster: composition-substrate
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#layout
    section: "theme.json — settings.layout (4 fields: contentSize, wideSize, allowEditing, allowCustomContentAndWideSize)"
    captured: 2026-05-09
related:
  - block.supports.layout                  # block-side counterpart with overlapping allowEditing field name
  - block.supports.governance-toggles      # alignWide depends on these widths; align uses wide/full classes
  - theme-config.json-settings-typography  # wideSize is ALSO consumed by fluid font-size calculation
  - theme-config.json-styles-spacing       # styles.spacing.padding interacts via useRootPaddingAwareAlignments
  - theme-config.json-appearanceTools      # NOT bundled — layout settings always require explicit declaration
  - theme-config.json-settings-spacing     # adjacent capability — layout consumes blockGap from spacing
---

# RULE — `settings.layout` — composition authority substrate

## WHEN

Configuring a theme's `theme.json` layout authority. Where
`settings.color` and `settings.typography` were design-token
substrates (palettes, fontSizes, fluid policies),
`settings.layout` is the **composition authority substrate** — it
declares the **backing values for alignment semantics**:

- `contentSize` = the resolved width that "no alignment" content
  uses (default content max-width).
- `wideSize` = the resolved width that `.alignwide` content uses
  AND the maximum viewport for fluid font-size synthesis
  (cross-capability coupling — see invariants).
- `allowEditing` (theme-level) governs whether the layout UI
  panel renders globally.
- `allowCustomContentAndWideSize` governs whether the user can
  override these widths per-block.

This chunk also formalizes the **first documented cross-capability
coupling within settings**: `wideSize` serves DOUBLE DUTY for
layout AND typography. Up to this point, settings sub-sections were
treated as independent capabilities; layout breaks that.

## SHAPE

### 4 fields

| Field | Type | Default | Notes |
|---|---|---|---|
| `contentSize` | `string` | (no default) | CSS max-width value (e.g., `"650px"`, `"40rem"`). Sets the resolved width for default-aligned content. |
| `wideSize` | `string` | (no default) | CSS max-width value. Sets `.alignwide` content max-width AND maximum viewport for fluid typography clamp synthesis. |
| `allowEditing` | `boolean` | `true` | Disables the layout UI controls globally when `false`. |
| `allowCustomContentAndWideSize` | `boolean` | `true` | When `false`, removes the per-block custom-content-and-wide-size controls. |

### Typical theme example

```json
{
  "settings": {
    "layout": {
      "contentSize": "650px",
      "wideSize":    "1200px"
    }
  }
}
```

### Locked-down example (theme controls layout entirely)

```json
{
  "settings": {
    "layout": {
      "contentSize": "650px",
      "wideSize":    "1200px",
      "allowCustomContentAndWideSize": false
    }
  }
}
```

→ Users see content at 650px / wide at 1200px and cannot override
either per block.

### Hidden layout panel example

```json
{
  "settings": {
    "layout": {
      "contentSize": "650px",
      "wideSize":    "1200px",
      "allowEditing": false
    }
  }
}
```

→ Layout panel hidden globally; widths still resolve to declared
values, but no UI is shown for inspection or modification.

## REQUIRES

- Setting MUST be declared under `theme.json` `settings.layout`
  (top-level scope or per-block-type via
  `settings.blocks.{name}.layout`).
- `contentSize` and `wideSize` MUST be valid CSS length values
  (with units: `px`, `rem`, `em`, `vw`, `%`, etc.). Bare numbers
  are invalid.
- For `wideSize` to function as fluid-typography max viewport, the
  `settings.typography.fluid` policy MUST be enabled (boolean
  `true` or object form). Without fluid enabled, wideSize affects
  only alignWide.
- For `allowEditing: false` to take effect at the block-instance
  level: `block.supports.layout.allowEditing` semantics may
  interact (both layers carry the same field name — see
  invariants).

## INVARIANTS

- **Composition authority substrate.** Where color and typography
  declared design-token registries (palettes, fontSizes), this
  field declares **resolved width values** for alignment
  semantics. The block-level `align: "wide"` / `align: "full"`
  attributes reference these values implicitly — the alignment
  class (e.g., `alignwide`) is meaningless without
  `settings.layout.wideSize` providing the actual max-width.
- **Vertical pipeline (alignment side):**
  ```
  settings.layout.wideSize: "1200px"        (theme declaration)
      ↓ emits
  --wp--style--global--wide-size: 1200px    (CSS variable, verification-needed for exact name)
      ↓ consumed by
  .alignwide CSS rule                        (max-width: var(--wp--style--global--wide-size))
      ↓ activated by
  block instance with align: "wide"          (per-instance attribute)
      ↓ wrapper emits
  class="alignwide ..."                      (wrapper class)
      ↓ renders at
  1200px maximum width
  ```
- **wideSize cross-capability coupling — DOUBLE DUTY.** Source:
  *"Sets the max-width of wide (.alignwide) content. Also used as
  the maximum viewport when calculating fluid font sizes."*
  This is the **first documented case of a settings field consumed
  by a different capability** (layout's wideSize → typography's
  fluid clamp formula). Implication for ontology:
  - The 4-layer architecture's "settings = authority substrate"
    framing extends to "settings can have CROSS-CAPABILITY
    consumers".
  - Renaming or removing `wideSize` would break BOTH alignment
    AND fluid typography synthesis. Treat as a stable contract
    once shipped.
  - Theme designers tuning fluid typography MUST consider
    `wideSize` as a typography input, not just a layout input.
- **`allowEditing` field name shared with `block.supports.layout.allowEditing`.**
  Same field name appears in both layers:
  - `settings.layout.allowEditing` (theme-level — this chunk)
  - `block.supports.layout.allowEditing` (block-level — declared
    by block author per `block.supports.layout`)
  ⚠ **Precedence between the two is not explicitly documented in
  the captured source.** Likely behavior: theme-level disables
  globally regardless of block declaration; block-level disables
  for specific blocks even when theme allows. Verify per WP
  version.
- **`allowCustomContentAndWideSize` parallels
  `block.supports.layout.allowCustomContentAndWideSize`.** Same
  pattern as allowEditing — same name in both layers, same
  precedence question.
- **Alignment classes are theme-resolved, NOT user-configurable
  per-block.** Users pick `wide` / `full` via the alignment
  toolbar (gated by `block.supports.align` or
  `supports.governance-toggles`); the WIDTH that wide/full
  resolves to comes from this settings field. Users cannot say
  "this block's wide is 800px while another's is 1400px" —
  unless `allowCustomContentAndWideSize: true` exposes per-block
  override controls.
- **NO `defaultContentSize` / `defaultWideSize` fields.** Unlike
  color (defaultPalette / defaultGradients / defaultDuotone) and
  typography (defaultFontSizes), layout does NOT have core
  default values that themes opt out of. Either the theme declares
  contentSize/wideSize OR the layout has no max-width constraint
  (browser viewport limits apply).
- **NO custom-value gate equivalent to `customColor` /
  `customFontSize`.** The relevant control is
  `allowCustomContentAndWideSize` (singular gate for both fields
  jointly), not separate `customContentSize` /
  `customWideSize`. This couples the two fields' user-override
  behavior together.
- **NOT in `appearanceTools` bundle.** appearanceTools enables
  background, border, color-elements, dimensions, position.sticky,
  spacing, typography.lineHeight — but layout settings are NEVER
  bundled. Themes ALWAYS declare contentSize/wideSize explicitly
  (or accept "no max-width").
- **`useRootPaddingAwareAlignments` interacts with this section.**
  This top-level setting (per `settings.useRootPaddingAwareAlignments`,
  documented in theme.json overview) makes
  `styles.spacing.padding` apply to full-width block CONTENT
  rather than to the root element. Effectively this lets full-
  width blocks ignore root padding while keeping content-width
  blocks padded normally. Cross-couples to layout.
- **Per-block-type override via `settings.blocks.{name}.layout`.**
  A theme can give specific block types different content/wide
  widths. Less common than per-block overrides for color or
  spacing; mostly used for special template blocks.
- **Constrained layout type (per `block.supports.layout`)
  consumes these widths.** When a block's layout is "constrained",
  its `contentSize` / `wideSize` defaults to these settings
  values unless overridden via block instance.
- **Layout settings have FEWER fields than other capabilities
  but BROADER cross-coupling.** color: 14 fields, typography: 16,
  layout: 4 — yet layout is consumed by alignment, fluid
  typography, useRootPaddingAwareAlignments, and constrained
  layout type. Field count is not a measure of ontological
  importance.
- ⚠ **Minimum WP version unknown.** Layout settings have evolved
  significantly; useRootPaddingAwareAlignments was a later
  addition. allowEditing and allowCustomContentAndWideSize align
  with the layout system's introduction (likely WP 5.9+ era).
  Frontmatter `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Declaring `settings.layout.contentSize: "650"` (bare
  number). Must include unit: `"650px"`. Source spec is `string`
  CSS length value.
- ❌ Setting `wideSize` smaller than `contentSize`. Wide
  alignment is supposed to be WIDER than content alignment;
  reversing produces visual bugs. Validate ratios in design
  reviews.
- ❌ Removing or renaming `wideSize` thinking it's only a layout
  concern. **Fluid typography synthesis depends on it as
  maximum viewport.** Removing it breaks both alignment AND
  responsive font-size scaling.
- ❌ Setting `allowCustomContentAndWideSize: false` without
  declaring `contentSize` and `wideSize`. Users can't override
  AND theme provides no values → no max-width applies (full
  browser viewport).
- ❌ Expecting `allowEditing: false` to PREVENT alignment.
  allowEditing only hides the layout panel UI; alignment classes
  (`alignwide`, `alignfull`) still apply to blocks where users
  set them via the align toolbar (which is a separate UI path).
- ❌ Looking for `defaultContentSize` or `defaultWideSize` to opt
  out of "core defaults". No core defaults exist for layout
  widths; either the theme declares them or there's no
  max-width constraint.
- ❌ Looking for `customContentSize` / `customWideSize` separate
  fields. The single `allowCustomContentAndWideSize` field gates
  both jointly.
- ❌ Hardcoding alignment max-widths in CSS (e.g.,
  `.alignwide { max-width: 1200px; }`). Bypasses the settings
  cascade — theme widths can't propagate to blocks, and per-block
  overrides have no effect.
- ❌ Treating `settings.layout.allowEditing` as fully overriding
  `block.supports.layout.allowEditing` (or vice versa) without
  testing. Two layers carry the same field name; precedence is
  not explicitly documented. Test combinations before committing
  to expectations.
- ❌ Forgetting that `appearanceTools: true` does NOT activate
  layout settings. Layout always requires explicit declaration
  in `settings.layout`. Compare to color/typography/dimensions
  which are partially bundled.
- ❌ Treating `wideSize` as ONLY a max-width. Source explicitly
  notes the second use as fluid font-size max viewport — both
  consumers matter.

## RELATED

- `block.supports.layout` — block-side counterpart. Notably,
  `block.supports.layout.allowEditing` shares its name with
  `settings.layout.allowEditing` here. Same name in two
  authority layers; precedence not fully documented.
- `block.supports.governance-toggles` — `align` and `alignWide`
  emit alignment classes (`alignwide`, `alignfull`) that
  resolve to widths declared HERE. align is the consumer;
  layout settings are the producer.
- `theme-config.json-settings-typography` — `wideSize` is
  CONSUMED by `settings.typography.fluid` as the maximum viewport
  for clamp formula synthesis. **Cross-capability coupling
  within settings** — first documented case.
- `theme-config.json-styles-spacing` (planned) —
  `useRootPaddingAwareAlignments` (top-level setting) interacts
  with layout: makes `styles.spacing.padding` apply to full-width
  block content instead of root element.
- `theme-config.json-appearanceTools` — layout settings are NOT
  in the bundle. Themes always declare explicitly. Compare to
  color/typography/dimensions/spacing which are partially
  bundled.
- `theme-config.json-settings-spacing` (planned) — adjacent
  capability. Layout consumes spacing's `blockGap` value as the
  CSS `gap` for flex/grid layout types (per
  `block.supports.layout`). spacing declares the value, layout
  consumes it.
