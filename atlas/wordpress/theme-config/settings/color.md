---
rule_id: theme-config.json-settings-color
domain: theme-config
topic: settings
field_cluster: design-tokens
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#color
    section: "theme.json — settings.color (14 fields: gates + registries)"
    captured: 2026-05-09
related:
  - block.supports.color                   # block-side counterpart that this configures globally
  - block.supports.filter                  # filter.duotone uses this namespace's duotone presets (legacy)
  - theme-config.json-appearanceTools      # bundles link/heading/button/caption (NOT background/text)
  - theme-config.json-styles-color         # styles.color uses settings.color presets via var:preset|color|{slug}
  - theme-config.json-settings-typography  # adjacent capability with parallel preset+gate pattern
  - theme-config.json-settings-spacing     # adjacent capability with same architectural pattern
---

# RULE — `settings.color` — design token authority substrate

## WHEN

Configuring a theme's `theme.json` color authority. This is where
the theme declares:

- WHICH colors users may pick (preset palette / gradient / duotone
  registries).
- WHICH color UIs the editor exposes (background / text / link /
  heading / button / caption gates).
- WHETHER users may step outside the theme's curated palettes
  (`custom`, `customGradient`, `customDuotone` toggles).
- WHETHER core's default presets are available
  (`defaultPalette`, `defaultGradients`, `defaultDuotone`).

This is the **first KB chunk where the full vertical token pipeline
is explicit**:

```
settings.color.palette         (theme registry declaration)
    ↓ emits
--wp--preset--color--{slug}    (CSS custom property)
    ↓ populates
editor color picker            (UI exposure, gated by other settings)
    ↓ user choice
style.color.background = "var:preset|color|primary"  (per-instance attribute)
    ↓ serializes
<!-- wp:foo {"style":{"color":{"background":"var:preset|color|primary"}}} -->
    ↓ renders
wrapper emits has-primary-background-color class OR inline style
```

Settings is **registry + governance**, NOT realization. Actual CSS
values flow through `styles.color.*` and the style engine.

**This chunk also closes the duotone namespace anomaly** discovered
in `block.supports.filter`: `settings.color.duotone` remains the
preset namespace home even though `supports.filter.duotone` is the
exposure flag. Theme.json namespaces are **historically stable
authority anchors**, not purely semantic categories.

## SHAPE

### 14 fields organized by role

**Preset registries (3 — declare token vocabulary):**

| Field | Type | Description |
|---|---|---|
| `palette` | `[{ name, slug, color }]` | Color palette presets for the color picker. |
| `gradients` | `[{ name, slug, gradient }]` | Gradient presets for the gradient picker. |
| `duotone` | `[{ name, slug, colors }]` | Duotone presets (note: `colors` plural — 2-element array `[highlight, shadow]`). Namespace home for `block.supports.filter.duotone`. |

**Default-preset governance (3 — gate core defaults):**

| Field | Type | Default | Effect when `false` |
|---|---|---|---|
| `defaultPalette` | `boolean` | `true` | Removes core's default palette from the picker. Only theme-declared `palette` presets remain. |
| `defaultGradients` | `boolean` | `true` | Removes core's default gradients. |
| `defaultDuotone` | `boolean` | `true` | Removes core's default duotone presets. |

**Custom-value governance (3 — gate user creativity):**

| Field | Type | Default | Effect when `false` |
|---|---|---|---|
| `custom` | `boolean` | `true` | Removes the custom-color picker (user must use presets only). |
| `customGradient` | `boolean` | `true` | Removes custom gradient creation. |
| `customDuotone` | `boolean` | `true` | Removes custom duotone creation. |

**Color-type / element gates (5 — control which controls appear):**

| Field | Type | Default | Notes |
|---|---|---|---|
| `background` | `boolean` | `true` | Background color UI gate (NOT bundled in appearanceTools). |
| `text` | `boolean` | `true` | Text color UI gate (NOT bundled in appearanceTools). |
| `link` | `boolean` | **`false`** | Link color UI gate. Note default `false` — explicit theme opt-in required. Bundled in appearanceTools. |
| `heading` | `boolean` | `true` | Heading color UI gate. Bundled in appearanceTools. |
| `button` | `boolean` | `true` | Button color UI gate. Bundled in appearanceTools. |
| `caption` | `boolean` | `true` | Caption color UI gate. Bundled in appearanceTools. NO direct counterpart in `block.supports.color` — settings-only gate. |

### Preset registry shapes

```json
{
  "settings": {
    "color": {
      "palette": [
        { "name": "Primary",  "slug": "primary",  "color": "#0066cc" },
        { "name": "Accent",   "slug": "accent",   "color": "#ff5500" }
      ],
      "gradients": [
        { "name": "Sunset", "slug": "sunset",
          "gradient": "linear-gradient(135deg, #f00, #ff0)" }
      ],
      "duotone": [
        { "name": "Dark",   "slug": "dark",
          "colors": [ "#000000", "#cccccc" ] },
        { "name": "Bright", "slug": "bright",
          "colors": [ "#ffffff", "#ff0066" ] }
      ]
    }
  }
}
```

### Strict-curated theme example

```json
{
  "settings": {
    "color": {
      "palette": [ { "name": "Brand", "slug": "brand", "color": "#0a0a0a" } ],
      "defaultPalette": false,
      "custom": false,
      "customGradient": false
    }
  }
}
```

→ Users may pick ONLY the "Brand" color. Core defaults removed,
custom-color picker removed, custom-gradient picker removed.

## REQUIRES

- Setting MUST be declared under `theme.json` `settings.color`
  (top-level scope or per-block-type via `settings.blocks.{name}.color`).
- For preset registries to flow through to blocks, blocks MUST
  ALSO declare appropriate `supports.color.*` flags. theme.json
  declares the registry; block declares the use. Both layers must
  align.
- `palette` entries MUST have `name` (display) + `slug`
  (machine-readable) + `color` (CSS color value). `slug` MUST be
  unique within the palette array.
- `gradients` entries: `name` + `slug` + `gradient` (CSS gradient
  string).
- `duotone` entries: `name` + `slug` + `colors` (2-element array
  of CSS color strings — order matters: `[highlight, shadow]`).
- For full UI exposure of a color sub-control, BOTH gates must
  agree:
  - The `block.supports.color.{subprop}` flag (or appearanceTools
    bundle for link/heading/button/caption).
  - The `settings.color.{subprop}` boolean (or default true).

## INVARIANTS

- **Settings is registry + governance, NOT realization.** This
  field declares WHICH tokens exist and WHICH UIs are exposed.
  Realization (actual CSS emission, value substitution into styles)
  happens via the **style engine** consuming `styles.color.*` and
  the per-instance `style.color.*` attributes. Compare with
  `appearanceTools` (mediation, not ownership) — both belong to
  the meta-substrate layers.
- **Preset slugs become CSS custom properties.** Each entry in
  `palette` / `gradients` / `duotone` registries emits a
  CSS variable: `--wp--preset--color--{slug}`,
  `--wp--preset--gradient--{slug}`, `--wp--preset--duotone--{slug}`.
  The slug is the canonical reference everywhere downstream.
- **Three preset reference forms cohabit:**
  - `--wp--preset--color--{slug}` (raw CSS variable, used by
    style engine and theme stylesheets)
  - `var:preset|color|{slug}` (theme.json + block delimiter
    serialized form; resolves to the CSS variable)
  - `has-{slug}-color` / `has-{slug}-background-color` /
    `has-{slug}-gradient-background` (auto-generated wrapper
    classes for blocks using preset selection)
- **Vertical pipeline (settings → CSS var → UI → block opt-in →
  attribute → wrapper):**
  1. Theme declares `settings.color.palette` entries.
  2. Core emits CSS custom properties `--wp--preset--color--{slug}`.
  3. Editor populates color picker from this list (gated by other
     settings).
  4. User picks a preset color while editing a block.
  5. Block instance attribute serializes as
     `style.color.background = "var:preset|color|primary"` or as
     a top-level `backgroundColor: "primary"` (per
     `block.supports.color.background` semantics).
  6. Block wrapper emits `has-primary-background-color` class +
     `has-background` flag class.
  7. CSS rules consuming `var(--wp--preset--color--primary)`
     produce the final visual color.
- **Default-preset gates and custom-value gates are independent.**
  A theme can:
  - `defaultPalette: false` + `custom: true` → user picks from
    theme palette only OR uses the custom-color picker (no core
    defaults).
  - `defaultPalette: true` + `custom: false` → user picks from
    theme palette + core defaults but cannot enter custom colors.
  - `defaultPalette: false` + `custom: false` → user limited to
    theme palette ONLY (most curated mode).
- **`link` defaults to `false`** while all other color sub-controls
  default to `true`. Link colors require explicit theme opt-in
  (or appearanceTools bundle, which includes link). This is the
  **only asymmetric default** in settings.color — historical UX
  decision, not a typo.
- **`caption` is settings-only** — no `block.supports.color.caption`
  counterpart exists. Caption color is exposed through the global
  styles UI / appearanceTools bundle, not through per-block
  supports declarations.
- **`enableContrastChecker` is supports-only** — no
  `settings.color.enableContrastChecker` counterpart. Contrast
  checker is a block-author concern (per `block.supports.color`),
  not a theme governance concern.
- **`palette` / `gradients` / `duotone` array order is preserved
  in the picker UI.** Themes can curate the visual sequence.
- **Empty `palette: []` is meaningfully different from omitting
  the field.** Empty array = "I have no presets, but I could
  have"; omitted = use core defaults. With `defaultPalette: false`
  AND empty `palette: []`, the picker shows nothing.
- **Per-block-type override pattern:**
  `settings.blocks.{block-name}.color.{anything}` overrides the
  top-level setting for that specific block type. Use to give
  certain blocks a restricted palette while the rest of the site
  uses the full one.
- **Namespace stability anchor (duotone case).** `settings.color.duotone`
  is the preset namespace for duotone, EVEN THOUGH the supports
  flag was reclassified to `block.supports.filter.duotone`. This
  asymmetry is deliberate: theme.json namespaces are
  **ecosystem stability anchors**, NOT purely semantic categories.
  Migration of preset namespaces would break existing themes;
  Gutenberg preserved the legacy namespace home and only moved the
  exposure layer. See `block.supports.filter` for the migration
  pattern.
- **Settings ↔ supports asymmetry is structural.** They do NOT
  1:1 mirror:
  - settings has `caption` (no supports counterpart).
  - supports has `enableContrastChecker` (no settings counterpart).
  - settings has `defaultPalette/Gradients/Duotone` (no supports
    counterpart — block author can't opt out of core defaults).
  - supports has `gradients` boolean (no settings counterpart for
    on/off — gradients availability flows from `gradients` array
    presence).
  Each layer addresses different concerns: settings governs what
  EXISTS and what UIs APPEAR; supports governs what THIS BLOCK
  participates in.
- **`appearanceTools: true` partially activates this section.**
  appearanceTools enables `link`, `heading`, `button`, `caption`
  gates as a bundle. It does NOT enable `background`, `text`,
  `gradients`, `custom`, `customGradient`, `customDuotone`,
  `palette`, `gradients` array, `duotone` array, or any default-
  governance fields. These remain the theme's explicit decision.
- **Theme authority vs user authority distinction surfaces here.**
  - `palette` declaration = theme decides what colors EXIST.
  - `defaultPalette` toggle = theme decides whether core's
    additional defaults are added.
  - `custom` toggle = **theme decides whether the user has
    custom-color authority at all**.
  This is governance over user creative authority, NOT just UI
  configuration.
- ⚠ **Minimum WP version unknown** for settings.color as a section.
  Specific gate fields (link/heading/button/caption) tie to
  appearanceTools and likely arrived alongside it.
  Frontmatter `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Declaring `settings.color.palette` and expecting blocks to
  automatically use it. Blocks must ALSO declare
  `supports.color.background` (or `text` / `link` / etc.) for the
  color picker to appear ON THAT BLOCK.
- ❌ Setting `defaultPalette: false` without providing your own
  `palette` array. Picker becomes empty (only the custom-color
  input remains, if `custom: true`).
- ❌ Using `defaultPalette: false` AND `palette: []` AND
  `custom: false`. The user has NO color choices at all — block
  controls render but offer nothing.
- ❌ Hardcoding colors (`backgroundColor: "#0066cc"`) in block
  attribute defaults instead of preset references
  (`backgroundColor: "primary"`). Preset references survive theme
  changes; hex values do not adapt.
- ❌ Storing `settings.color.duotone` presets at
  `settings.filter.duotone`. The namespace is `settings.color.duotone`
  regardless of the supports flag's location at
  `block.supports.filter.duotone`. Mismatch → presets not found.
- ❌ Expecting `appearanceTools: true` to enable `background` /
  `text` / `gradients` / custom-value controls. The bundle covers
  ONLY `link/heading/button/caption`. Other color controls require
  explicit settings or default-true behavior.
- ❌ Renaming preset slugs across theme versions without migration.
  Existing posts reference slugs (`var:preset|color|primary`); a
  rename breaks references. Treat slugs as stable contract once
  shipped.
- ❌ Forgetting that `link: false` is the documented default.
  Themes wanting link colors must explicitly set `link: true` OR
  enable `appearanceTools: true` (which bundles link).
- ❌ Treating `palette`, `gradients`, `duotone` arrays as
  uncoupled. They're sibling registries within the color
  capability — theme designers should consider how they harmonize
  visually, not just declare each in isolation.
- ❌ Per-block-type setting `settings.blocks.{name}.color.palette`
  with entirely different presets and expecting cross-block
  visual consistency. Per-block overrides fragment the design
  system; use sparingly.
- ❌ Confusing this rule with `styles.color.*`. Settings declares
  the registry + gates. `styles.color.*` is the cascading style
  layer that USES preset references and emits actual CSS values.
  Mixing them produces invalid theme.json.

## RELATED

- `block.supports.color` — block-side counterpart. Each
  `supports.color.{subprop}` is a per-block opt-in that pairs
  with the matching `settings.color.{subprop}` gate. Both layers
  must agree for the control to surface.
- `block.supports.filter` — `filter.duotone` exposure flag uses
  presets stored at `settings.color.duotone` (legacy namespace
  home — namespace-stability anchor).
- `theme-config.json-appearanceTools` — bundles a SUBSET of color
  sub-gates (link / heading / button / caption only). Background
  / text / gradients / custom / defaults remain explicit settings
  decisions.
- `theme-config.json-styles-color` (planned) — `styles.color.*`
  consumes the registries declared here via
  `var:preset|color|{slug}` references and emits actual CSS
  property values. Realization layer that complements this
  registry layer.
- `theme-config.json-settings-typography` (planned) — adjacent
  capability with the same architectural pattern: registry
  (fontSizes, fontFamilies) + custom gates + default gates +
  per-element gates.
- `theme-config.json-settings-spacing` (planned) — same
  architectural pattern: registry (spacingSizes / spacingScale)
  + custom gates + per-element gates (padding / margin / blockGap
  individually).
