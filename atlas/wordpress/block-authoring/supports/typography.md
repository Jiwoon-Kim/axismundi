---
rule_id: block.supports.typography
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "verification-needed"
wp_recommended: "6.6"
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#typography
    section: "Supports — typography (and sub-properties: fontSize / lineHeight / textAlign)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # typography flags inject specific attributes
  - block.wrapper-attributes             # generated classes/styles flow through useBlockProps
  - block.supports.color                 # adjacent capability with similar 5-layer cascade
  - theme-config.editor-font-sizes       # cross-context: where fontSize presets are sourced (legacy theme_support)
  - theme-config.json-typography-settings # cross-context: theme.json settings.typography (presets, fluid, etc.)
  - theme-config.fluid-typography        # cross-context: fluid is a theme.json setting, NOT a supports flag
---

# RULE — `supports.typography` capability flag

## WHEN

Defining a block that should expose font-size, line-height, or
text-alignment controls in the block inspector / toolbar, without
writing the controls or persistence logic yourself.

This chunk covers `supports.typography` as a **block-side declaration**
that opts the block into typography UI. It does NOT cover global
typography configuration (font-size palettes, fluid typography,
font-family registration) — those live in `theme.json` `settings.typography`
and are described in cross-context chunks.

**Important scoping:** `supports.typography` exposes a **smaller set of
subproperties than the typography styles vocabulary** found in
`theme.json`. Only `fontSize`, `lineHeight`, and `textAlign` are
documented as supports subproperties. Other typography styles
(fontFamily, fontStyle, fontWeight, letterSpacing, textTransform,
textDecoration, writingMode, textIndent, textColumns) are NOT listed
as supports flags — they appear as theme.json settings instead.

## SHAPE

```json
{
  "supports": {
    "typography": {
      "fontSize": true,
      "lineHeight": true,
      "textAlign": true
    }
  }
}
```

Note: unlike `color`, the source does NOT document a `typography: true`
shorthand. Use the object form with explicit subproperties.

### Sub-property matrix

| Sub-property | Type | Default | Since | Effect |
|---|---|---|---|---|
| `fontSize` | `boolean` | `false` | original | Font-size UI control + preset/custom serialization. |
| `lineHeight` | `boolean` | `false` | original | Line-height number control + serialization to `style.typography.lineHeight`. Visible only if theme declares support (see REQUIRES). |
| `textAlign` | `boolean` \| `string[]` | `false` | WP 6.6 | Text-align toolbar control (left / center / right by default). Array form restricts options. |

### `textAlign` array form (restricted options)

```json
{
  "supports": {
    "typography": {
      "textAlign": [ "left", "right" ]
    }
  }
}
```

## REQUIRES

- Block MUST be registered server-side. Typography controls and
  theme.json preset integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element. Without
  this, controls work in the inspector but generated classes/styles
  never reach the markup. (See `block.wrapper-attributes`.)
- For preset-based `fontSize` values to appear in the picker, the
  active theme MUST provide font-size presets via either:
  - `theme.json` `settings.typography.fontSizes` (preferred, modern), OR
  - `add_theme_support( 'editor-font-sizes', [...] )` (legacy
    `theme_support` API).
- For `lineHeight` UI to render: the theme MUST declare line-height
  support in `theme.json` `settings.typography.lineHeight: true`. The
  source explicitly says: *"the block editor will show an UI control
  for the user to set its value if the theme declares support."*
- For `textAlign`: introduced in WP 6.6 — feature-detect or set
  `wp_min: 6.6` if you depend on it.

## INVARIANTS

### Editor effects

- A **Typography** panel appears in the block inspector when any
  `typography.*` subproperty is set to `true`.
- `fontSize` renders a font-size picker. Available presets come from
  `theme.json` `settings.typography.fontSizes` (or legacy
  `editor-font-sizes` theme_support). A custom-pixel input is also
  shown when `settings.typography.customFontSize: true` (theme.json
  default behavior).
- `lineHeight` renders a number/slider control inside the Typography
  panel — only when theme declares
  `settings.typography.lineHeight: true`.
- `textAlign` adds a text-alignment toolbar group to the **block
  toolbar** (not the inspector panel). Default options: left,
  center, right. Array form (`["left", "right"]`) restricts which
  appear.

### Attribute effects

| Sub-property | Attributes added |
|---|---|
| `fontSize` | `fontSize` (string, preset slug) + `style` (object — `style.typography.fontSize` for custom value) |
| `lineHeight` | `style` (object — `style.typography.lineHeight` for custom value) |
| `textAlign` | `style` (object — `style.typography.textAlign` for selected value) |

The `style` attribute is **shared** across multiple supports flags
(typography, color, spacing, etc.). All sub-paths under `style.*` are
namespaced by capability (`style.typography.*`, `style.color.*`,
`style.spacing.*`).

The block can declare its own defaults for these injected attributes:

```json
{
  "attributes": {
    "fontSize": { "type": "string", "default": "large" },
    "style":    { "type": "object", "default": { "typography": { "lineHeight": "1.5" } } }
  }
}
```

### Wrapper effects

- Preset font-size selection emits a class on the wrapper:
  `has-{slug}-font-size`.
- A general flag class `has-font-size` is also emitted to indicate
  "some font-size is set" (parallel to `has-background` for color).
- Custom (non-preset) values emit inline `style="font-size: ..;
  line-height: ..; text-align: .."` on the wrapper instead of preset
  classes.
- `textAlign` selections emit `has-text-align-{slug}` class
  (e.g., `has-text-align-center`).
- All effects flow through `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` —
  required spreading per `block.wrapper-attributes`.

### Serialization effects

Block delimiter stores chosen values as JSON:

```html
<!-- wp:my-plugin/notice {"fontSize":"large","style":{"typography":{"lineHeight":"1.5","textAlign":"center"}}} -->
```

- Preset `fontSize` → `fontSize: "slug"` (top-level attribute).
- Custom `fontSize` → `style.typography.fontSize: "16px"` (or rem,
  clamp(), etc.).
- `lineHeight`, `textAlign` → always under `style.typography.*`
  (no top-level shortcut).

### theme.json interaction

- Preset slugs in `fontSize` resolve to theme.json
  `settings.typography.fontSizes[].slug` entries via the CSS custom
  property `--wp--preset--font-size--{slug}`.
- **Fluid typography** (`settings.typography.fluid`) is a
  **theme.json setting, NOT a `supports.typography` subproperty**.
  When fluid is enabled, font-size preset resolution produces
  `clamp()` formulas instead of fixed values, computed from
  `settings.layout.contentSize` / `settings.layout.wideSize` and
  per-preset `fluid: { min, max }` overrides. The block does not
  opt into fluid — the THEME does, and any block declaring
  `supports.typography.fontSize` automatically benefits.
- `settings.typography.lineHeight` (theme.json) MUST be `true` for
  the line-height UI to render even when the block declares
  `supports.typography.lineHeight: true`. Both layers must agree.
- `settings.typography.customFontSize` (theme.json, default `true`)
  controls whether the custom-pixel input appears alongside the
  preset picker.
- Other typography styles (fontFamily, fontStyle, fontWeight,
  letterSpacing, textTransform, textDecoration, writingMode,
  textIndent, textColumns) are NOT controlled by
  `supports.typography`. They appear in theme.json `settings.typography`
  with their own settings flags and resolve independently.

### General invariants

- The supports.typography object form is the only documented form;
  no `typography: true` shorthand is documented (contrast with
  `color: true` which IS documented).
- The `style` attribute is shared across capability flags (color,
  typography, spacing, etc.). Its shape is a nested object whose
  top-level keys are the capability families.
- The `fontSize` attribute (preset slug) and `style.typography.fontSize`
  (custom value) are **mutually exclusive in practice** — when the
  user picks a preset, custom is cleared; when the user enters a
  custom value, preset is cleared. The serialized block carries
  whichever the user last set.
- `textAlign` array form constrains options shown in the toolbar —
  it does NOT constrain serialization (a block whose `textAlign`
  array allows only `["left","right"]` cannot have its toolbar set
  to `center`, but if attributes are programmatically set to
  `center` the value still serializes).
- ⚠ **Per-sub-property `wp_min` mixed.** `fontSize` and `lineHeight`
  are original Block API; `textAlign` is WP 6.6+. A block declaring
  all three needs `wp_min: 6.6`. The field-level `wp_min` is
  `verification-needed` because the parent `typography` flag itself
  predates per-sub-flag versioning.

## ANTIPATTERNS

- ❌ Declaring `supports.typography.fontSize: true` without
  ensuring the theme provides font-size presets. UI shows only
  custom-pixel input (if `customFontSize` is true) or appears empty.
- ❌ Declaring `supports.typography.lineHeight: true` and expecting
  the UI to render unconditionally. The theme MUST declare
  `settings.typography.lineHeight: true` in theme.json — without
  that, the control is hidden.
- ❌ Trying to enable fluid typography via `supports.typography.fluid: true`.
  No such subproperty exists. Fluid typography is a theme-level
  decision in `theme.json` `settings.typography.fluid`. The block
  cannot opt in or out per-block.
- ❌ Declaring `supports.typography.fontFamily` (or fontWeight,
  letterSpacing, textTransform, etc.). These are NOT documented
  subproperties of `supports.typography`. The corresponding controls
  appear via theme.json `settings.typography` and the global typography
  panels, not via per-block supports flags.
- ❌ Hardcoding font-size or line-height values in the block's
  `save()` / render output, bypassing the supports cascade. User
  cannot change the values via the inspector and theme.json
  overrides have no effect.
- ❌ Setting `textAlign: ["center", "justify"]` with options that
  the editor doesn't expose. Stick to documented options
  (`left`, `center`, `right`); other values may not produce a
  toolbar button.
- ❌ Not declaring `wp_min: "6.6"` (or feature-detecting) when
  using `textAlign`. Pre-6.6 environments will silently ignore
  the flag.
- ❌ Treating the `style` attribute as a typography-only field. It
  carries values for ALL capability families (typography, color,
  spacing, border, etc.). Defining `attributes.style.default` for
  typography may collide with concurrent color / spacing defaults.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all other supports flags — without the
  wrapper hook, controls work but generated classes/styles never
  reach the rendered DOM.

## RELATED

- `block.json-supports-field` — parent rule explaining the
  supports mechanism in general; this typography flag is an
  instance of that pattern.
- `block.json-attributes-core` — typography flags inject the
  `fontSize` (string) and shared `style` (object) attributes; the
  attribute model determines how defaults and serialization work.
- `block.wrapper-attributes` — `useBlockProps()` /
  `get_block_wrapper_attributes()` are the receiver for all
  generated classes/inline styles produced by this rule.
- `block.supports.color` — sibling capability with the same
  5-layer cascade pattern (Editor / Attribute / Wrapper /
  Serialization / theme.json). Cross-compare: color has 7
  subproperties, typography has only 3; both share the
  `style` attribute namespace.
- `theme-config.editor-font-sizes` (cross-context, planned) —
  legacy `theme_support` API that pre-dates theme.json for
  font-size presets.
- `theme-config.json-typography-settings` (cross-context,
  planned) — full theme.json `settings.typography` reference
  including fluid, lineHeight gating, customFontSize,
  fontSizes, fontFamilies, and all other typography style
  controls not exposed through supports.
- `theme-config.fluid-typography` (cross-context, planned) —
  the fluid typography system. Critical to understand because
  fluid changes preset RESOLUTION (clamp formulas instead of
  fixed values), but does NOT change the supports.typography
  contract on the block side.
