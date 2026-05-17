---
rule_id: theme-config.json-appearanceTools
domain: theme-config
topic: settings
field_cluster: meta-governance
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#appearancetools
    section: "theme.json — appearanceTools setting (bundled UI tools list)"
    captured: 2026-05-09
related:
  - block.supports.background          # consumes via bundle (background.backgroundImage/Size)
  - block.supports.position            # consumes via bundle (position.sticky)
  - block.supports.dimensions          # consumes via bundle (aspectRatio/height/minHeight/minWidth/width)
  - block.supports.spacing             # consumes via bundle (blockGap/margin/padding)
  - block.supports.color               # consumes via bundle (link/heading/button/caption only — NOT background/text)
  - block.supports.typography          # consumes via bundle (lineHeight only — NOT fontSize)
  - block.supports.shadow              # NOT in bundle — contrast case
  - block.supports.filter              # NOT in bundle — contrast case
  - theme-config.json-settings-color   # the per-capability settings field this bundles into
  - theme-config.json-settings-typography
  - theme-config.json-settings-spacing
  - theme-config.json-settings-dimensions
  - theme-config.json-settings-border  # border has NO supports flag — bundled-only governance
---

# RULE — `appearanceTools` — meta-governance bundle switch

## WHEN

Configuring a theme's `theme.json` and you want to **enable a curated
set of editor UI tools across multiple capability families with one
boolean** — instead of declaring each `settings.{capability}.*` field
individually.

This is the **meta-governance substrate** of theme.json — a single
switch that opens up UI controls for several capability families at
once. Useful for themes that want a "modern, full-featured" baseline
without writing out every individual gating boolean.

**This is NOT a capability** in the sense color / typography / spacing
are. It is a **governance multiplexing layer** that sits between
block authors' `supports.*` declarations and individual
`settings.{capability}.*` opt-in fields. Misclassifying it as a
capability is a category error — appearanceTools owns no styles, no
attributes, and no rendering authority. It only AGGREGATES + EXPOSES +
MEDIATES other capabilities.

## SHAPE

```json
{
  "settings": {
    "appearanceTools": true
  }
}
```

| Property | Value |
|---|---|
| Type | `boolean` |
| Default | `false` |
| Effect | When `true`, enables the bundled UI tools listed below |

### Bundled UI tools (verbatim from source)

When `appearanceTools: true` is set, the following capability surfaces
become user-accessible (subject to per-block `supports.*` declarations
also being present):

| Capability | Bundled subproperties |
|---|---|
| `background` | `backgroundImage`, `backgroundSize` |
| `border` | `color`, `radius`, `style`, `width` |
| `color` | `link`, `heading`, `button`, `caption` |
| `dimensions` | `aspectRatio`, `height`, `minHeight`, `minWidth`, `width` |
| `position` | `sticky` |
| `spacing` | `blockGap`, `margin`, `padding` |
| `typography` | `lineHeight` |

### NOT bundled (require explicit `settings.{capability}.*`)

- `color`: `background`, `text`, `gradients`, `defaultPalette`,
  `customColor`, `palette`, `duotone`, etc. (the **primary** color
  controls)
- `typography`: `fontSize`, `fontFamily`, `fontStyle`, `fontWeight`,
  `letterSpacing`, `textTransform`, `textDecoration`, `writingMode`,
  `textIndent`, `fluid`, `fontSizes`, `fontFamilies`, `dropCap`, etc.
  (the **primary** typography controls)
- `shadow` (entirely standalone — NOT in bundle)
- `filter` / duotone-specific (entirely standalone)

## REQUIRES

- Setting MUST be declared under `theme.json` `settings`
  (top-level scope only — not per-block in `settings.blocks`).
- The block author MUST still declare `supports.{capability}.*`
  for the bundled controls to appear ON THAT BLOCK. appearanceTools
  enables theme-side opt-in; block-side opt-in is a separate
  declaration. **Both layers must agree** for a control to render.
- For controls bundled here, declaring the corresponding
  `settings.{capability}.{subprop}: true` is REDUNDANT (the bundle
  already opts in). Either form works.
- For controls NOT bundled here (e.g., color.background, typography.fontSize),
  explicit `settings.{capability}.{subprop}: true` is REQUIRED in
  addition to (or instead of) `appearanceTools: true`.

## INVARIANTS

- **Meta-capability, not a capability.** appearanceTools owns no
  CSS properties, no attributes, no presets, no styles. It is a
  **governance switch** that toggles other capabilities' UI
  exposure. Conceptually outside the 6 capability families
  (Content / Container / Surface treatment / Visual transformation
  / Composition / Governance) — belongs to the **capability
  governance topology layer**.
- **Authority inversion:** The standard authority direction is
  block-author declares capability → user uses control. With
  appearanceTools, the THEME author opens a bundle of capability
  surfaces; user discoverability of these capabilities then
  depends on individual blocks ALSO opting in via `supports.*`.
  Authority flow is: theme governance (bundle) ↔ block governance
  (supports) → user surface.
- **`Capability existence ≠ Capability exposure ≠ Capability
  governance authority ≠ Capability execution`.** appearanceTools
  is the empirical proof of this distinction:
  - Existence: the capability code (e.g., border-radius) is in core.
  - Exposure: requires either explicit `settings.border.*` OR
    `appearanceTools: true`.
  - Governance authority: theme decides via this switch (or
    explicit settings); block author has NO supports flag for
    border to declare.
  - Execution: style engine emits the actual CSS at render time.
- **Selective composition is the defining ontology pattern.**
  appearanceTools is NOT "appearance" comprehensively — it is a
  **deliberately curated subset**. Notable selections:
  - Bundles `color.link/heading/button/caption` but NOT
    `color.background/text` (which are the primary color controls).
  - Bundles `typography.lineHeight` but NOT `fontSize` /
    `fontFamily` (which are the primary typography controls).
  - Bundles ALL of `dimensions` and most of `spacing`.
  - INCLUDES `border` entirely (border has NO supports flag —
    appearanceTools is the primary opt-in path).
  - EXCLUDES `shadow` entirely (shadow is fully block-owned).
  - EXCLUDES `filter` entirely.
- **Aggregator, not owner.** Each capability listed in the bundle
  is OWNED by its own settings field
  (`settings.background.*`, `settings.color.*`, etc.).
  appearanceTools **routes opt-in** for the listed subproperties
  but does not replace those fields. The capability's actual
  presets, gates, and other configuration still live in the
  per-capability settings sections.
- **Asymmetrical bundling is a deliberate design choice, not a
  documentation gap.** Why is border bundle-only while shadow is
  standalone? Why is typography.lineHeight bundled while
  typography.fontSize is not? The bundle composition reflects
  Gutenberg's UX-driven + historical + governance-driven
  evolution, NOT a uniform taxonomy. The KB documents this
  asymmetry as INVARIANT, not as TODO.
- **No precedence override semantics.** appearanceTools does NOT
  override individual `settings.{capability}.{subprop}` values
  set explicitly. If a theme sets `appearanceTools: true` AND
  `settings.spacing.padding: false`, the explicit field MAY
  override (verification-needed for actual precedence). The
  documented behavior is "bundle enables"; selectively disabling
  bundled subproperties is not part of the documented API.
- **Per-block opt-in is independent.** Setting `appearanceTools:
  true` does NOT make every block expose every bundled control —
  blocks STILL need to declare the corresponding `supports.*`
  flag. The bundle is a theme-side gate; the block-side gate is
  separate. Both must align for the control to surface.
- **Affects theme-global UI tools, not per-block.** Source: *"Setting
  that enables the following UI tools."* The bundle operates at the
  global settings layer, not at `settings.blocks.{name}` per-block
  override.
- ⚠ **Minimum WP version unknown.** appearanceTools has been part
  of theme.json for several WP versions (existed by WP 5.9 era);
  exact introduction version not in captured source. Frontmatter
  `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Treating appearanceTools as "enables all appearance options".
  It is selective. `color.background`, `color.text`,
  `typography.fontSize`, `shadow`, `filter`, etc. are NOT
  bundled — explicit settings still required for those.
- ❌ Setting `appearanceTools: true` and assuming every block
  exposes every bundled control. Blocks STILL must declare
  `supports.*` for each capability they want to support — the
  bundle is theme governance, not per-block enablement.
- ❌ Documenting a capability as "appearanceTools-only" when
  individual `settings.{capability}.{subprop}` could also enable
  it. Both paths work; appearanceTools is convenience, not
  exclusivity.
- ❌ Misclassifying appearanceTools as a "capability". It owns no
  styles, no attributes. It is a meta-capability governance
  switch — outside the 6 capability families.
- ❌ Expecting `appearanceTools` to surface controls for capabilities
  not in the documented bundle (e.g., enabling shadow, fontSize,
  duotone). Those have their own settings paths.
- ❌ Treating the bundled subproperty list as exhaustive of what
  the capability can do. Each bundled capability has MANY more
  configuration fields under `settings.{capability}.*`; the
  bundle just opts in to specific UI tools, not to the full
  capability configuration.
- ❌ Setting `appearanceTools: true` at the block-level
  (`settings.blocks.{name}.appearanceTools`). The setting is
  documented as a top-level setting only; block-level use is
  unverified.
- ❌ Relying on appearanceTools as the ONLY opt-in for a capability
  when the theme target spans WP versions where appearanceTools
  may not have included that subproperty. Use explicit
  `settings.{capability}.{subprop}` for version safety; use
  appearanceTools as a convenience for modern themes.

## RELATED

- `block.supports.background` — consumes via bundle. Background's
  appearanceTools coverage: `backgroundImage` + `backgroundSize`.
- `block.supports.position` — consumes via bundle.
  `position.sticky` is bundled; position's documented sub-
  properties are limited to sticky in any case.
- `block.supports.dimensions` — consumes via bundle. ALL
  documented dimensions subproperties are bundled (aspectRatio,
  height, minHeight, minWidth, width).
- `block.supports.spacing` — consumes via bundle. blockGap +
  margin + padding bundled (the three documented subproperties).
- `block.supports.color` — partial bundle. Bundled: link,
  heading, button, caption. NOT bundled: background, text,
  gradients (the primary color controls).
- `block.supports.typography` — partial bundle. Bundled:
  lineHeight only. NOT bundled: fontSize, textAlign,
  fontFamily, etc.
- `block.supports.shadow` — **NOT in bundle.** Contrast case:
  shadow is fully block-owned, no theme aggregation path.
- `block.supports.filter` — **NOT in bundle.** Contrast case.
- `theme-config.json-settings-color` (planned) — full color
  settings reference; appearanceTools governs only a subset.
- `theme-config.json-settings-typography` (planned) — full
  typography settings reference.
- `theme-config.json-settings-spacing` (planned) — full spacing
  settings.
- `theme-config.json-settings-dimensions` (planned) — full
  dimensions settings.
- `theme-config.json-settings-border` (planned) — border is the
  cleanest case of theme-only authority. Border has NO
  `supports.border` — appearanceTools (or explicit
  `settings.border.*`) is the only opt-in path.
