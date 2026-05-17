---
rule_id: theme-config.json-settings-typography
domain: theme-config
topic: settings
field_cluster: design-tokens
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#typography
    section: "theme.json — settings.typography (16 fields incl. fluid + fontFamilies asset registry)"
    captured: 2026-05-09
related:
  - block.supports.typography              # block-side counterpart that this configures globally
  - theme-config.json-settings-color       # adjacent design-token substrate (compare/contrast pattern)
  - theme-config.json-appearanceTools      # bundles ONLY lineHeight (default false). other typography fields require explicit settings
  - theme-config.json-styles-typography    # styles.typography uses these registries via var:preset|font-size|{slug} etc.
  - theme-config.json-settings-spacing     # parallel pattern — registry + custom gates
---

# RULE — `settings.typography` — computational token substrate

## WHEN

Configuring a theme's `theme.json` typography authority. Where
`settings.color` was a pure **design token authority substrate**
(static registries + governance), `settings.typography` extends into
**computational token substrate** territory through `fluid`:

- DECLARES font-size + font-family registries (preset tokens).
- GATES per-property custom-value authority (fontStyle, lineHeight,
  letterSpacing, etc.).
- DECLARES a **responsive computation policy** (`fluid`) that
  Gutenberg synthesizes into `clamp()` formulas at render time.
- REGISTERS font assets (`fontFamilies[].fontFace[]`) — first
  asset-declaration registry in the settings layer.

This is the **first KB chunk where settings carries algorithm
parameters, not just declarations**. The "settings = registry +
governance, NOT realization" invariant established in
`settings.color` partially BREAKS here: `fluid` is **realization
leakage** — settings PARTIALLY specifies how realization computes.

## SHAPE

### 16 fields organized by role

**Static token registries (2 — declare token vocabulary):**

| Field | Type | Notes |
|---|---|---|
| `fontSizes` | `[{ name, slug, size, fluid }]` | Font-size presets. **Each entry can carry its own `fluid` override** (per-preset computational policy). |
| `fontFamilies` | `[{ name, slug, fontFamily, fontFace }]` | Font-family presets. `fontFamily` is the CSS string; `fontFace` is `[]` array of `@font-face` declarations (asset declaration registry pattern). |

**Computational token policy (1 — algorithm parameters):**

| Field | Type | Default | Notes |
|---|---|---|---|
| `fluid` | `boolean \| { minFontSize, maxViewportWidth, minViewportWidth }` | `false` | When `true` (or object form), Gutenberg synthesizes `clamp()` formulas for font-size presets. Object form provides global tuning parameters. |

**Default-preset gate (1 — gate core defaults):**

| Field | Type | Default | Effect when `false` |
|---|---|---|---|
| `defaultFontSizes` | `boolean` | `true` | Removes core's default font-size presets (Small / Medium / Large / X-Large) from the picker. Note: NO equivalent `defaultFontFamilies` exists. |

**Custom-value governance (12 — gate user creativity):**

| Field | Type | Default | Notes |
|---|---|---|---|
| `customFontSize` | `boolean` | `true` | Custom font-size input (if false: presets only). |
| `fontStyle` | `boolean` | `true` | italic / normal selector. |
| `fontWeight` | `boolean` | `true` | weight numeric input. |
| `letterSpacing` | `boolean` | `true` | letter-spacing input. |
| `lineHeight` | `boolean` | **`false`** | line-height input. **Default false** — explicit opt-in or appearanceTools required. |
| `textAlign` | `boolean` | `true` | left/center/right toolbar. |
| `textColumns` | `boolean` | **`false`** | column count input. **Default false**. |
| `textDecoration` | `boolean` | `true` | underline / strikethrough. |
| `textIndent` | `boolean \| string` | **`"subsequent"`** | Indent control. Unique default: string `"subsequent"` (NOT a boolean). |
| `textTransform` | `boolean` | `true` | uppercase / lowercase / capitalize. |
| `writingMode` | `boolean` | **`false`** | horizontal / vertical writing direction. **Default false**. |
| `dropCap` | `boolean` | `true` | Drop-cap toggle (paragraph-specific feature; settings-only — no supports counterpart). |

### Registry shapes

**`fontSizes` entry** (note nested per-preset `fluid`):

```json
{
  "settings": {
    "typography": {
      "fontSizes": [
        { "name": "Small",  "slug": "small",  "size": "0.875rem" },
        { "name": "Medium", "slug": "medium", "size": "1rem" },
        {
          "name": "Large", "slug": "large", "size": "1.5rem",
          "fluid": { "min": "1.25rem", "max": "2rem" }
        }
      ]
    }
  }
}
```

**`fontFamilies` entry** (asset declaration nested inside):

```json
{
  "settings": {
    "typography": {
      "fontFamilies": [
        {
          "name": "System Sans",
          "slug": "system-sans",
          "fontFamily": "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
        },
        {
          "name": "Custom Sans",
          "slug": "custom-sans",
          "fontFamily": "'Custom Sans', sans-serif",
          "fontFace": [
            {
              "fontFamily": "Custom Sans",
              "fontWeight": "400",
              "fontStyle": "normal",
              "src": [ "file:./assets/fonts/custom-sans-regular.woff2" ]
            },
            {
              "fontFamily": "Custom Sans",
              "fontWeight": "700",
              "fontStyle": "normal",
              "src": [ "file:./assets/fonts/custom-sans-bold.woff2" ]
            }
          ]
        }
      ]
    }
  }
}
```

### `fluid` configuration shapes

```json
// Boolean form — enable with defaults
{ "settings": { "typography": { "fluid": true } } }

// Object form — global tuning
{ "settings": { "typography": { "fluid": {
    "minFontSize":      "14px",
    "minViewportWidth": "320px",
    "maxViewportWidth": "1600px"
} } } }
```

### Per-preset fluid override

```json
{
  "settings": {
    "typography": {
      "fluid": true,
      "fontSizes": [
        { "name": "Body",  "slug": "body",  "size": "1rem" },
        {
          "name": "Hero", "slug": "hero", "size": "3rem",
          "fluid": { "min": "2rem", "max": "4rem" }
        },
        {
          "name": "Static", "slug": "static", "size": "1.125rem",
          "fluid": false
        }
      ]
    }
  }
}
```

`fluid: false` on an individual preset entry **opts out** of fluid
synthesis for that preset (stays at its `size` value across all
viewports).

## REQUIRES

- Setting MUST be declared under `theme.json` `settings.typography`
  (top-level scope or per-block-type via `settings.blocks.{name}.typography`).
- For preset registries to flow through to blocks, blocks MUST
  ALSO declare `supports.typography.{subprop}` flags. theme.json
  declares the registry; block declares opt-in. Both layers must
  align.
- `fontSizes` entries MUST have `name` + `slug` + `size`. Optional
  `fluid` override is `boolean | { min, max }`.
- `fontFamilies` entries MUST have `name` + `slug` + `fontFamily`
  (CSS font-family string). Optional `fontFace` is an array of
  `@font-face` declarations (object form mirroring CSS @font-face).
- For `fontFace` entries: `fontFamily`, `src` MUST be present;
  `fontWeight`, `fontStyle`, `fontDisplay`, etc. follow CSS
  @font-face semantics. `src` accepts the `file:` prefix for paths
  relative to the theme root (or full URLs).
- `fluid` boolean form (`true`) uses Gutenberg's default synthesis
  parameters. Object form provides custom tuning — at minimum:
  `minFontSize`, `minViewportWidth`, `maxViewportWidth`. Properties
  not provided fall back to defaults.
- For `lineHeight: true` UI to render, theme MUST declare it
  explicitly OR enable `appearanceTools: true` (which bundles
  lineHeight). Block author also needs `supports.typography.lineHeight`.

## INVARIANTS

- **Settings layer with realization leakage.** This is the first
  documented case where `settings.*` carries computation
  parameters — NOT just declarations or governance. `fluid`
  partially specifies how the style engine SYNTHESIZES `clamp()`
  formulas at render time. The "settings = registry + governance,
  NOT realization" invariant from settings.color is HERE only
  partially valid. fluid is a "realization policy" — semi-
  declarative, semi-executive.
- **Vertical pipeline with fluid synthesis:**
  ```
  settings.typography.fontSizes[]   (registry)
      ↓ if fluid policy applies
  Gutenberg synthesizes clamp(min, preferred, max)
      ↓ emits as CSS variable
  --wp--preset--font-size--{slug}: clamp(...)
      ↓ populates editor font-size picker
  user picks preset slug
      ↓ supports.typography.fontSize gates the control
  style.typography.fontSize = "var:preset|font-size|{slug}" OR fontSize: "{slug}"
      ↓ wrapper emits has-{slug}-font-size class
  CSS resolves var to clamp formula
      ↓
  responsive font-size at runtime
  ```
  Compare to settings.color's pipeline (no synthesis step) —
  typography adds an algorithmic phase.
- **Computational token policy is a NEW category.** It is NOT a
  static registry (like palette), NOT a governance gate (like
  custom). It declares **algorithm parameters**:
  `minFontSize` = floor for clamp's `min(...)`,
  `minViewportWidth` / `maxViewportWidth` = the viewport range
  across which font-size interpolates. The synthesis algorithm
  itself lives in core (style engine); settings only parameterizes
  it.
- **Per-preset fluid override is a 3-state system:**
  - `fluid` not specified on entry → use global `fluid` policy
  - `fluid: false` on entry → opt OUT (preset stays static at `size`)
  - `fluid: { min, max }` on entry → explicit override (custom
    interpolation range for THIS preset)
  This makes fluid both a global policy AND a per-preset attribute.
  Unique pattern not seen in color presets.
- **Asset declaration registry (`fontFace[]`) is a NEW pattern.**
  No equivalent in settings.color. fontFamilies entries can carry
  `fontFace` arrays that map to CSS `@font-face` rules. Each
  fontFace declares: `fontFamily`, `fontWeight`, `fontStyle`,
  `src` (file paths or URLs), and standard @font-face properties.
  This is the **first settings field that registers EXTERNAL
  ASSETS**, not just CSS values.
- **Asymmetric defaults — 4 distinct fields:** Most boolean fields
  default `true` (capability available), but **4 fields default
  `false` or to a non-boolean value:**
  - `lineHeight: false` — explicit opt-in or appearanceTools required
  - `textColumns: false` — explicit opt-in (column count is
    typically not a primary writing control)
  - `writingMode: false` — explicit opt-in (vertical writing modes
    affect layout heavily; opt-in protects accidental enabling)
  - `textIndent: "subsequent"` (string default!) — the only
    settings field with a non-boolean default. `"subsequent"`
    enables indenting on subsequent paragraphs (after the first);
    `true` enables on all; `false` disables.
  - `fluid: false` — explicit opt-in (responsive synthesis is
    consequential — opt-in default is conservative).
- **`textIndent` is the only settings field with a string-typed
  default.** Source: `boolean | string`, default `"subsequent"`.
  Other string-accepting fields exist but their defaults are
  boolean. Treat as a documented exception, not as a future
  pattern.
- **No `customFontFamilies` field exists.** Themes cannot let
  users freely register their own font families inline. Custom
  font choice is constrained to the theme's `fontFamilies`
  registry. This is governance asymmetry: custom-font-size is
  open (`customFontSize: true` default), custom-font-family is
  closed (no field).
- **No `defaultFontFamilies` field exists.** Themes cannot opt
  out of "core default font families" because there are NO core
  default font families in the same sense palette has core
  defaults. Asymmetric registry exposure: defaultFontSizes
  exists, defaultFontFamilies does not.
- **`dropCap` is settings-only.** No `block.supports.typography.dropCap`
  counterpart. Drop-cap is a paragraph-specific UI control
  exposed globally via this gate; the paragraph block consults
  it directly via the global settings.
- **Settings ↔ supports asymmetry — typography case:**
  - `supports.typography` has 3 sub-flags (fontSize, lineHeight,
    textAlign).
  - `settings.typography` has 16 fields (most without supports
    counterpart).
  - Most typography vocabulary lives ONLY in settings —
    fontFamily, fontStyle, fontWeight, letterSpacing,
    textDecoration, textTransform, textColumns, textIndent,
    writingMode, dropCap, fluid, fontSizes (registry),
    fontFamilies (registry), customFontSize, defaultFontSizes —
    these are theme-side decisions exposed via global typography
    panels, NOT per-block opt-in.
  - The `block.supports.typography` "scoping warning" applies in
    full force here: typography supports is the SMALL surface;
    settings is the LARGE surface.
- **`appearanceTools: true` activates ONLY `lineHeight`.** All
  other typography settings require explicit declaration. Compare
  with settings.color where appearanceTools activates 4 sub-gates
  (link/heading/button/caption). Typography's bundle coverage is
  minimal.
- **Fluid clamp synthesis algorithm uses these inputs (from source
  + style engine behavior):** preset's `size` value, preset's
  per-entry `fluid` (if set), global `settings.typography.fluid`
  (if object form, provides min/max viewport + minFontSize), and
  Gutenberg's default fallback parameters. Output is a CSS
  `clamp(min, preferred-with-vw-interpolation, max)` expression
  that interpolates between viewport sizes.
- **Responsive authority sits with the theme, not the user.** The
  user picks a preset (e.g., "Hero"); the theme's `fluid` policy
  determines whether that preset interpolates with viewport size.
  User cannot override fluid on a per-block basis (no per-instance
  fluid attribute documented). This is theme-controlled responsive
  scaling that the user accepts implicitly when picking a preset.
- ⚠ **Minimum WP version unknown** for settings.typography as a
  whole. fluid system likely arrived around WP 6.1 (per the
  block-metadata `render` field's similar timeline) but this is
  not directly verified in the captured source. Frontmatter
  `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Setting `fluid: true` and expecting all font sizes to become
  responsive without configuring `fontSizes` entries with
  appropriate `size` values. Fluid synthesizes from preset values;
  if presets are unfit (e.g., all the same size), clamp output
  is degenerate.
- ❌ Assuming `fluid` global object replaces individual preset
  customization. Per-preset `fluid` overrides take precedence —
  the global policy is the FALLBACK for entries that don't
  override.
- ❌ Setting per-preset `fluid: false` while expecting the global
  policy to still apply to that preset. False explicitly opts out.
- ❌ Using `customFontFamilies` field. Does NOT exist. Themes
  must register all available font families in `fontFamilies`
  array; users cannot add their own.
- ❌ Setting `lineHeight: true` without realizing
  `appearanceTools: true` already enables it. Both work; redundant.
  Choose one based on whether you want the broader bundle.
- ❌ Treating `dropCap` as a per-block supports flag. It's
  settings-only — themes enable / disable globally, paragraph
  block consults the setting directly.
- ❌ Treating `textIndent: true` and `textIndent: "subsequent"`
  as equivalent. They're different modes (`true` = all paragraphs,
  `"subsequent"` = paragraphs after the first). String default
  `"subsequent"` is the documented WordPress UX convention.
- ❌ Hardcoding font URLs in CSS instead of declaring them via
  `fontFamilies[].fontFace[]`. The theme.json declaration enables
  Gutenberg to manage @font-face emission, font loading
  optimization, and editor preview accuracy.
- ❌ Confusing this rule with `styles.typography.*`. Settings
  declares the registries + gates + fluid policy.
  `styles.typography.*` is the cascading style layer that USES
  preset references (`var:preset|font-size|{slug}`,
  `var:preset|font-family|{slug}`) and emits actual CSS values.
- ❌ Renaming font-size or font-family slugs across theme
  versions without migration. Existing posts reference slugs
  (`var:preset|font-size|hero`); a rename breaks references.
  Slugs are stable contract once shipped.
- ❌ Confusing fluid's `minFontSize` (global typography fluid
  parameter) with a preset's `fluid.min` (per-preset minimum
  for clamp). The global field is for theme-wide minimum;
  per-preset is for that specific preset's clamp range.

## RELATED

- `block.supports.typography` — block-side counterpart. The
  3 supports sub-flags (fontSize / lineHeight / textAlign) are a
  SMALL subset of this settings field's 16 entries. Most
  typography is theme-controlled.
- `theme-config.json-settings-color` — adjacent design-token
  substrate. Compare patterns:
  - Both: registry + governance + custom-gate + default-gate.
  - color: pure declarative.
  - typography: declarative + computational (fluid) + asset
    declarations (fontFace) — escalation in scope.
- `theme-config.json-appearanceTools` — bundles ONLY `lineHeight`
  from this section. All other typography fields require explicit
  declaration. Compare with color where bundle covers 4 sub-gates;
  typography's bundle coverage is minimal (1 of 16 fields).
- `theme-config.json-styles-typography` (planned) —
  `styles.typography.*` consumes `fontSizes` + `fontFamilies`
  registries via `var:preset|font-size|{slug}` and
  `var:preset|font-family|{slug}` references and emits actual
  CSS property values. Realization layer that complements this
  registry layer. Fluid clamp formulas are emitted at this
  realization stage.
- `theme-config.json-settings-spacing` (planned) — parallel
  pattern: registry (`spacingSizes`/`spacingScale`) + custom
  gates + default gates. spacingScale is also a "computed token
  policy" (algorithmic generation of preset values from operator/
  increment/steps spec) — second computational settings case.
