---
rule_id: theme-config.json-styles-typography
domain: theme-config
topic: styles
field_cluster: realization
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#typography-2
    section: "theme.json — styles.typography (12 fields all string|{ref})"
    captured: 2026-05-09
related:
  - theme-config.json-settings-typography  # registry/policy this realizes (incl. fluid → clamp output)
  - theme-config.json-styles-color         # adjacent realization with token-consumption pattern
  - block.supports.typography              # per-block opt-in surface that pairs with this realization
  - block.markup-representation            # per-instance style.typography.* in block delimiter is the cascade leaf
  - block.wrapper-attributes               # styles realize via class names + inline CSS variables on wrapper
  - theme-config.json-styles-spacing       # adjacent realization with generated-token consumption
---

# RULE — `styles.typography` — computational realization layer

## WHEN

Configuring a theme's `theme.json` `styles.typography.*` to set
**actual typography values** (font, size, line-height, etc.) that
flow into rendered CSS. This is the **second realization-layer
chunk** in KB and the **first case where computational policy from
settings (fluid) materializes as realized values**.

Where `styles.color` was **token consumption** (lookup), this
chunk is **computational realization** — the layer where settings's
`fluid` clamp policy becomes actual `clamp()` CSS expressions
emitted at render time.

This chunk also reveals the **first 4-level specificity hierarchy**
that subsequent realization chunks will share:

```
styles.typography.*                         → site-wide / body selector
styles.elements.{element}.typography.*     → per-element override (h1, link, button, etc.)
styles.blocks.{block-name}.typography.*    → per-block-type override
block instance style.typography.* in delimiter → per-instance override (highest specificity)
```

## SHAPE

### 12 fields (mostly 1:1 mirror with settings.typography gates)

All fields share the same type signature: `string | { ref }`.

| Field | CSS property set |
|---|---|
| `fontFamily` | `font-family` |
| `fontSize` | `font-size` |
| `fontStyle` | `font-style` |
| `fontWeight` | `font-weight` |
| `letterSpacing` | `letter-spacing` |
| `lineHeight` | `line-height` |
| `textIndent` | `text-indent` |
| `textAlign` | `text-align` |
| `textColumns` | `column-count` |
| `textDecoration` | `text-decoration` |
| `writingMode` | `writing-mode` |
| `textTransform` | `text-transform` |

### Asymmetry from settings — what's MISSING in styles

settings.typography has 16 fields; styles.typography has 12. Missing
in styles:
- `fluid` — synthesis policy lives in settings only (computation
  parameters are declarative authority, not realization values).
- `fontSizes` — registry lives in settings only (declarations).
- `fontFamilies` — registry lives in settings only (declarations).
- `defaultFontSizes` — gate, not a realization value.
- `customFontSize` — gate, not a realization value.
- `dropCap` — gate, no styles counterpart documented.

### Two value forms — `string` vs `{ ref }`

```json
// String form — literal CSS or var:preset reference
{
  "styles": {
    "typography": {
      "fontFamily": "var:preset|font-family|system-sans",
      "fontSize":   "var:preset|font-size|large",
      "lineHeight": "1.6"
    }
  }
}
```

```json
// { ref } object form — structured cross-reference
{
  "styles": {
    "typography": {
      "fontSize": { "ref": "styles.elements.heading.typography.fontSize" }
    }
  }
}
```

⚠ Same `{ ref }` semantics caveat as `styles.color` — full reference
shape and resolution behavior verification-needed.

### 4-level specificity hierarchy — typography example

```json
{
  "styles": {
    "typography": {
      "fontFamily": "var:preset|font-family|body",
      "lineHeight": "1.6"
    },
    "elements": {
      "heading": {
        "typography": {
          "fontFamily": "var:preset|font-family|display",
          "fontWeight": "700"
        }
      },
      "h1": {
        "typography": { "fontSize": "var:preset|font-size|hero" }
      },
      "link": {
        "typography": { "textDecoration": "underline" }
      }
    },
    "blocks": {
      "core/quote": {
        "typography": { "fontStyle": "italic" }
      }
    }
  }
}
```

Cascade behavior (high-to-low specificity):
1. Block instance `style.typography.*` in delimiter (highest).
2. Per-block-type `styles.blocks.{name}.typography.*`.
3. Per-element `styles.elements.{name}.typography.*` (e.g., heading,
   link, button, h1-h6, caption).
4. Top-level `styles.typography.*` → body (lowest).

## REQUIRES

- Setting MUST be declared under `theme.json` `styles.typography`,
  `styles.elements.{element}.typography`, OR
  `styles.blocks.{name}.typography`.
- For preset references (`"var:preset|font-size|{slug}"`,
  `"var:preset|font-family|{slug}"`):
  - The slug MUST match a registered entry in
    `settings.typography.fontSizes` /
    `settings.typography.fontFamilies` (or core defaults if
    `defaultFontSizes` is not disabled).
  - For fluid-enabled font-size presets, the preset's
    pre-synthesized `clamp()` expression resolves automatically
    at the CSS variable level — `styles.typography.fontSize`
    does NOT need to express clamp directly.
- For literal string values: MUST be valid CSS for the target
  property. `fontSize: "1.5rem"` (length); `lineHeight: "1.6"`
  (unitless number); `fontWeight: "700"` (numeric weight);
  `textTransform: "uppercase"` (keyword); etc.
- For per-element styles: element name MUST be a recognized style
  element (`heading`, `h1`-`h6`, `link`, `button`, `caption`,
  etc.).
- For per-block-type styles: block name MUST be `vendor/slug`
  format and registered.

## INVARIANTS

- **Realization ownership (axis inversion vs settings).**
  settings.typography's `fluid` was framed as "realization
  leakage" (declarative layer reaching into computation). In
  styles.typography, the SAME computational output (clamp
  expressions) is the layer's NATIVE concern. Same content,
  inverted philosophical axis:
  - settings: "why is this declarative field carrying computation
    policy?" (tension)
  - styles: "the realization layer naturally carries computed
    values" (no tension)
- **Settings ↔ styles asymmetry profile is per-capability.**
  - `settings.color` (14) vs `styles.color` (3) — 4.7x
    asymmetry; element styling fills the missing gap.
  - `settings.typography` (16) vs `styles.typography` (12) — 1.3x
    asymmetry; mostly 1:1 mirror between settings gates and
    styles values.
  Different capabilities have different realization architectures.
  Typography is closer to a "1:1 declaration mirror" than color.
- **fluid → clamp realization is INDIRECT.** Theme authors do
  NOT write clamp() expressions in `styles.typography.fontSize`.
  Instead:
  1. Declare `settings.typography.fluid: true` (or object form
     with parameters).
  2. Declare `settings.typography.fontSizes` presets with
     `size` values (and optional per-preset `fluid` overrides).
  3. Style engine synthesizes `clamp()` formulas and assigns
     them to `--wp--preset--font-size--{slug}` CSS variables.
  4. `styles.typography.fontSize: "var:preset|font-size|large"`
     resolves to the `clamp()` expression via the CSS variable.
  The realization is materialized at the CSS variable level,
  consumed by styles via preset reference. Themes can emit
  `clamp()` literally for non-preset values, but the typical
  fluid path is preset-based.
- **4-level specificity hierarchy** — typography is the first
  capability where ALL 4 levels are commonly used:
  - **Top-level `styles.typography.*`** → body / global text
    defaults (font-family, line-height baseline).
  - **`styles.elements.heading.typography.*`** (or
    `styles.elements.h1` through `h6`) → per-heading-rank
    typography.
  - **`styles.elements.link.typography.*`** → link-specific
    text-decoration / typography.
  - **`styles.elements.button.typography.*`** → button typography.
  - **`styles.elements.caption.typography.*`** → caption typography.
  - **`styles.blocks.{name}.typography.*`** → per-block-type
    (e.g., quote in italic).
  - **Block instance `style.typography.*` in delimiter** →
    per-instance user choice.
- **Element styling pressure is HIGHER for typography than for
  color.** Typography demands differentiation across body /
  headings / buttons / captions / links because these elements
  have intrinsically distinct typographic roles. Color also
  benefits from element styling (link colors, heading colors)
  but typography demands it more strongly. The
  `styles.elements.{element}.typography.*` path is heavily used.
- **All 12 fields share `string | { ref }` type signature.**
  Uniform value grammar across the entire typography realization
  surface. Compare with styles.color (3 fields, same grammar) —
  typography is broader but architecturally identical.
- **Style engine emits unitless numbers for `lineHeight`.**
  Typography unitless line-height (e.g., `"1.6"`) is the CSS
  best practice for inheritance. Numeric strings or
  `var:preset|...` references both work.
- **`fontFamily` references resolve to either inline strings or
  CSS custom properties.** Preset font families
  (`var:preset|font-family|system-sans`) resolve to
  `--wp--preset--font-family--system-sans`. Theme can also
  declare `@font-face` rules via `settings.typography.fontFamilies[].fontFace`
  which Gutenberg emits to the document head — these are tied
  to the registered font-family slugs.
- **`textTransform`, `textDecoration`, `writingMode` realize
  enum values.** Standard CSS enums (`uppercase` /
  `underline` / `vertical-rl` / etc.) — preset slugs are
  unusual for these but allowed via `string | { ref }`.
- **`textColumns` realizes as `column-count`.** Note: source
  field name is `textColumns` (plural-style WordPress
  convention) but it sets the CSS `column-count` property.
- **Per-instance overrides serialize to block delimiter
  `style.typography.*`.** When users adjust typography via
  block inspector controls (gated by
  `block.supports.typography.*`), the values land in the
  delimiter's `style.typography.*` path — taking precedence over
  all theme.json styles for that block instance. See
  `block.markup-representation`.
- **The fluid policy applies even when styles.typography
  uses literal values.** A literal `styles.typography.fontSize:
  "2rem"` does NOT participate in fluid synthesis — fluid
  applies to PRESETS (`fontSizes` registry entries), not to
  inline literals. To get fluid behavior on a literal value,
  write the `clamp()` expression manually. Most themes use
  preset references for fluid coverage.
- **`{ ref }` form for cross-typography references is plausible
  but unverified.** Likely use case: button typography inheriting
  from heading typography
  (`{"ref": "styles.elements.heading.typography.fontFamily"}`).
  Captured source documents the type but not full semantics.
  Treat as advanced / verification-needed.
- **`dropCap` has NO styles counterpart.** This settings-only
  gate (per `settings.typography.dropCap`) doesn't appear in
  styles.typography. dropCap is a UI behavior toggle, not a
  CSS-property-emitting realization.
- ⚠ **Minimum WP version unknown.** styles.typography fields
  exist since theme.json v1; specific fields (e.g., textAlign in
  styles, textColumns) may be later. Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Writing `clamp()` literals in `styles.typography.fontSize`
  thinking that's the only way to get fluid behavior. The
  preferred path is via preset references — fluid is synthesized
  at the CSS variable level. Literals work but bypass theme
  configurability.
- ❌ Setting `styles.typography.fluid` (this field does NOT exist
  in styles). Fluid is a settings policy, not a realization
  value. Wrong layer.
- ❌ Setting `styles.typography.fontSizes` or `fontFamilies`
  arrays. Registry declarations live in settings, not styles.
  Realization layer doesn't declare token registries.
- ❌ Setting `styles.typography.dropCap`. No such field — dropCap
  is a settings gate only.
- ❌ Hardcoding font-family strings site-wide via
  `styles.typography.fontFamily: "Helvetica, sans-serif"`.
  Loses theme palette switching capability and `@font-face`
  registration via `settings.typography.fontFamilies[].fontFace`.
  Use preset references.
- ❌ Looking for typography concerns at `styles.color.*` paths.
  Wrong field; concerns separate cleanly between color
  (background/gradient/text) and typography (font properties).
- ❌ Setting `styles.typography.fontSize` at top-level and
  expecting it to apply to headings. Headings have their own
  `styles.elements.heading.typography.fontSize` (or per-rank
  via `styles.elements.h1` etc.). Top-level only sets body
  default; heading sizes must be set on the element path.
- ❌ Renaming font-size or font-family preset slugs in settings
  after styles references them. References break silently.
- ❌ Setting `lineHeight: "24px"` (with unit). Best practice is
  unitless (`"1.6"`) for inheritance. Pixel values work but
  inherit absolutely — child elements get parent's pixel value
  rather than computing relative to their own font-size.
- ❌ Using `{ ref }` form without verifying its semantics in
  the target WP version. Type is documented; resolution
  behavior is verification-needed (same caveat as styles.color).

## RELATED

- `theme-config.json-settings-typography` — the registry +
  governance + computational POLICY layer this realizes.
  settings declares fluid policy and font-size/family registries;
  styles consumes the resulting preset values via
  `var:preset|font-size|{slug}` and
  `var:preset|font-family|{slug}` references. Vertical pipeline
  closure: settings (registry + computation policy) →
  CSS variable (synthesized clamp / static preset) → styles
  (consumption) → wrapper / element selectors → final CSS.
- `theme-config.json-styles-color` — adjacent realization with
  the **token-consumption** pattern (vs typography's
  **computational realization**). Compare:
  - color: 3 fields, narrow realization; element styling
    (link/heading/button/caption color) fills the gap.
  - typography: 12 fields, broad realization; element styling
    intensifies further.
  - Both share `string | { ref }` grammar.
- `block.supports.typography` — per-block opt-in surface that
  pairs with this realization. supports declares what
  inspector controls appear; styles declares the theme defaults
  those controls override.
- `block.markup-representation` — block delimiter's
  `style.typography.*` is the cascade leaf (highest specificity
  layer in the 4-level hierarchy). Theme styles set defaults;
  per-instance values override.
- `block.wrapper-attributes` — styles realize via wrapper class
  names (`has-{slug}-font-size`, `has-{slug}-font-family`,
  alignment classes) and inline CSS variables. Wrapper hook is
  the receiver layer.
- `theme-config.json-styles-spacing` (planned) — adjacent
  realization with **generated-token consumption** pattern
  (spacingScale-generated presets). Third subtype in styles
  taxonomy after color (consumption) and typography
  (computational realization).
