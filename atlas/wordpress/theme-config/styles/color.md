---
rule_id: theme-config.json-styles-color
domain: theme-config
topic: styles
field_cluster: realization
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#color-2
    section: "theme.json — styles.color (3 fields: background, gradient, text)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#styles
    section: "theme.json — styles intro (top-level → body selector)"
    captured: 2026-05-09
related:
  - theme-config.json-settings-color       # the registry layer this consumes via var:preset|color|{slug}
  - block.supports.color                   # per-block opt-in that surfaces this realization in the editor
  - block.markup-representation            # block delimiter's style.color.* is the per-instance cascade leaf
  - block.wrapper-attributes               # styles realize via class names + CSS variables on the wrapper
  - theme-config.json-styles-filter        # styles.filter.duotone is where duotone realization lives (NOT in styles.color)
  - theme-config.json-styles-typography    # adjacent realization with the same string|{ref} grammar pattern
---

# RULE — `styles.color` — color realization layer

## WHEN

Configuring a theme's `theme.json` `styles.color.*` to set the
**actual color values** that flow into rendered CSS. This is the
**first KB chunk in the realization layer** — where settings
declared *what tokens exist*, styles declares *what values land
where*.

This chunk closes the color vertical pipeline:

```
settings.color.palette         (registry — declares slug + value)
   ↓ emits
--wp--preset--color--primary   (CSS custom property)
   ↓ referenced by
styles.color.text = "var:preset|color|primary"   (THIS chunk — theme-level realization)
   ↓ AND/OR
styles.blocks.{name}.color.text                   (per-block-type override)
   ↓ AND/OR
block instance: style.color.text in delimiter     (per-instance override)
   ↓ wrapper emission
has-{slug}-color class OR inline style on wrapper
   ↓
final rendered color in browser
```

**Axis shift relative to prior phases**: phases 1-4 (block-authoring,
appearanceTools, settings) all asked *"what is allowed?"*. styles
asks *"how does the value materialize?"*. KB transitions from
**schema knowledge** to **rendering systems knowledge** at this
chunk.

## SHAPE

### 3 fields (deliberate minimalism vs settings.color's 14)

| Field | Type | CSS property set |
|---|---|---|
| `background` | `string \| { ref }` | `background-color` |
| `gradient` | `string \| { ref }` | `background` (full shorthand — gradient occupies the entire background property) |
| `text` | `string \| { ref }` | `color` |

⚠ **No `duotone` field in styles.color.** Despite
`settings.color.duotone` being the preset namespace home, the
realization for duotone lives in `styles.filter.duotone` (since
`block.supports.filter.duotone` reclassified the capability to the
filter family). Namespace asymmetry persists across both the
settings and styles layers.

### Two value forms — `string` vs `{ ref }`

```json
// String form — literal value or var:preset reference
{
  "styles": {
    "color": {
      "background": "var:preset|color|primary",
      "text":       "#222222"
    }
  }
}
```

```json
// { ref } object form — structured reference (likely to another theme.json field)
{
  "styles": {
    "color": {
      "background": { "ref": "styles.color.text" }
    }
  }
}
```

⚠ **`{ ref }` syntax semantics not fully captured in source.** The
type signature `string | { ref }` is documented; full reference
shape and resolution semantics are undocumented in the captured
source. Verify per WP version implementation; treat the structured
form as an advanced feature pending verification.

### Selector scoping (3 levels in theme.json styles)

| Path | Selector | Scope |
|---|---|---|
| `styles.color.*` (top-level) | `body` | Site-wide default |
| `styles.blocks.{block-name}.color.*` | `.wp-block-{name}` (block-specific class) | Per-block-type override |
| `styles.elements.{element}.color.*` | the element selector (e.g., `a` for link) | Per-element override |

```json
{
  "styles": {
    "color": { "text": "#222" },              // body default
    "blocks": {
      "core/quote": {
        "color": { "text": "#666" }            // per-block-type override
      }
    },
    "elements": {
      "link": {
        "color": { "text": "var:preset|color|accent" }  // per-element override
      }
    }
  }
}
```

## REQUIRES

- Setting MUST be declared under `theme.json` `styles.color`
  (top-level scope) OR `styles.blocks.{name}.color` /
  `styles.elements.{name}.color`.
- For preset references (`"var:preset|color|{slug}"`):
  the slug MUST match a registered entry in
  `settings.color.palette` (or core defaults if `defaultPalette`
  is not disabled). Unknown slugs emit broken CSS variables.
- For literal string values: MUST be valid CSS color syntax
  (hex, rgb/rgba, hsl/hsla, named, etc.).
- For `gradient`: the value sets the FULL `background` shorthand,
  NOT just `background-color`. Use only when a gradient is
  intended; mixing literal colors and gradients on the same
  selector requires using `background` (gradient) AND `background-color`
  (color) separately — beyond the scope of this single field.
- For per-block-type styles: the block name MUST be a registered
  block (`vendor/slug` format). Unknown block names register the
  styles silently but they have no rendering effect.
- For per-element styles: the element name MUST be a recognized
  Gutenberg style element (`link`, `button`, `heading`, `h1`-`h6`,
  `caption`, etc. — see settings.color.elements gates for the
  enumeration).
- The block author's `block.supports.color.*` declaration controls
  whether the inspector exposes UI for the user to OVERRIDE these
  values per block instance. Without supports, the user cannot
  override; the styles values simply apply via cascade.

## INVARIANTS

- **Realization layer identity.** This is the first KB chunk that
  is NOT about "what is allowed" but about "what is rendered". The
  4-layer architecture's `styles.* = realization` framing is
  operationalized here. Consumer-side ontology, not declarative-
  side.
- **Settings ↔ styles role asymmetry:**
  - `settings.color` declares 14 fields (registry + governance).
  - `styles.color` declares 3 fields (background, gradient, text).
  Settings is BROAD — it declares what's possible; styles is
  NARROW — it declares what's realized at the theme level. The
  remaining color concerns (link/heading/button/caption/etc.) are
  realized through `styles.elements.*` paths or per-block
  overrides, NOT via top-level styles.color.
- **Three-level cascade authority:**
  1. **Top-level `styles.color.*`** → body selector → site-wide
     default value.
  2. **`styles.blocks.{name}.color.*`** → block-type-specific
     selector → per-block-type override.
  3. **Block instance `style.color.*` in delimiter** → per-instance
     user override.
  ⚠ Exact precedence order is documented at "later overrides
  earlier" but specific specificity / merging behavior under
  contradictions is verification-needed.
- **`var:preset|color|{slug}` reference grammar.** The string form
  uses a custom syntax for preset references:
  ```
  var:preset|{capability}|{slug}
  ```
  Pipe-delimited 3-part identifier. At resolution time, this
  becomes `var(--wp--preset--{capability}--{slug})`. Used
  throughout theme.json `styles.*`, block delimiter `style.*`,
  and theme stylesheets.
- **`{ ref }` object form** — an alternative reference syntax
  whose full semantics are undocumented in captured source. Likely
  used for cross-referencing other theme.json paths (e.g.,
  `{"ref": "styles.color.background"}` to inherit a value from
  another rule). Treat as **advanced / verification-needed**.
- **`gradient` vs `background` distinction:** Source notes
  `gradient` sets the `background` CSS property (full shorthand).
  This means a gradient DECLARED HERE will override any
  `background-color` value also set via `background` field. Mixing
  them on the same scope produces only the gradient.
- **Top-level `styles.*` → body selector.** Source explicit:
  *"Styles in the top-level will be added in the `body` selector."*
  Site-wide defaults sit at the document root. All block /
  element overrides cascade from here.
- **`styles.elements.*` is the home for color types not in
  styles.color directly:**
  - Link colors → `styles.elements.link.color.text`
  - Heading colors → `styles.elements.heading.color.text` (or
    `styles.elements.h1` through `h6`)
  - Button colors → `styles.elements.button.color.{text,background}`
  - Caption colors → `styles.elements.caption.color.text`
  These are NOT under `styles.color.*` but under
  `styles.elements.{element}.color.*`. The 4 color sub-controls
  bundled in appearanceTools (link / heading / button / caption)
  realize through the elements path.
- **`styles.blocks.{block-name}.*` is the home for per-block
  styling.** Mirrors `settings.blocks.{block-name}.*` (per-block
  registry overrides). Cascading to specific block types.
- **No duotone in styles.color.** Where:
  - `block.supports.filter.duotone` (exposure)
  - `settings.color.duotone` (preset namespace — legacy)
  - **`styles.filter.duotone`** (realization — NOT styles.color)
  The reclassification of duotone from color → filter persisted
  into the styles layer too. This is the SECOND test of the
  "namespace stability ≠ semantic truth" invariant established
  in `block.supports.filter`. Settings keeps the legacy
  namespace; styles uses the new namespace. **Asymmetric
  migration extends across layers.**
- **Settings is BROAD; Styles is NARROW; Element styles fill the
  gap.** The combined coverage is:
  - settings.color.{many fields} → registries + governance for
    color tokens.
  - styles.color.{3 fields} → site-wide background / gradient /
    text realization.
  - styles.elements.{link/heading/button/caption}.color.* →
    realization for color-elements.
  - block instance style.color.* → per-instance overrides.
  All four layers work in concert; none is sufficient alone.
- **Block-instance `style.color.*` in delimiter is the cascade
  leaf.** When users pick colors via block inspectors (gated by
  `block.supports.color.*`), the values serialize into the block
  delimiter's `style.color.*` path — overriding theme-level
  styles for THIS specific block instance. See
  `block.markup-representation` for delimiter encoding.
- **Wrapper emission preserves the cascade source.** When a value
  comes from a preset, the wrapper emits the `has-{slug}-color`
  / `has-{slug}-background-color` class — referencing the CSS
  variable. When a value comes from a custom hex / RGB, the
  wrapper emits inline `style="..."` directly. This split
  preserves theme overridability for preset colors while making
  custom colors authoritative locally.
- **`{ ref }` form is settings/styles-cross-referencing**
  (probable). The captured source describes the type but not the
  syntax. Likely use case: a button that should always inherit
  the link color via `{"ref": "styles.elements.link.color.text"}`.
  Until verified, write theme.json with string-form preset
  references and treat ref form as exotic.
- ⚠ **Minimum WP version unknown.** `styles.color.*` has been part
  of theme.json since early versions. The `{ ref }` form may be
  newer (likely WP 6.x). Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Setting `styles.color.text: "primary"` (bare slug). Must be
  the full preset reference: `"var:preset|color|primary"`.
  Bare slugs are not interpreted as preset references.
- ❌ Setting `styles.color.background` AND `styles.color.gradient`
  on the same scope. Gradient occupies the full `background`
  shorthand — the color value is overridden. Use one or the
  other.
- ❌ Looking for `styles.color.duotone`. Does not exist.
  Realization for duotone lives at `styles.filter.duotone`
  despite the preset namespace remaining at
  `settings.color.duotone`.
- ❌ Looking for `styles.color.link` / `styles.color.heading` /
  etc. These element-scoped color realizations live at
  `styles.elements.link.color.text` /
  `styles.elements.heading.color.text` / etc. — NOT under
  `styles.color.*`.
- ❌ Hardcoding hex values site-wide instead of using preset
  references. Bypasses the theme palette cascade — color
  changes in theme.json don't propagate to body / blocks /
  elements that hardcode their values.
- ❌ Setting `styles.color.text` and expecting per-block-type
  override at the same path. The per-block-type override path
  is `styles.blocks.{name}.color.text`, not nested
  `styles.color.blocks.{name}.text`.
- ❌ Using the `{ ref }` form without verifying its semantics in
  the target WP version. Type is documented; resolution
  behavior is verification-needed.
- ❌ Confusing `styles.color.*` with the per-instance block
  delimiter `style.color.*`. The theme.json field is theme-wide
  / per-block-type default. The block delimiter field is
  per-instance. Both layers exist independently in the cascade.
- ❌ Renaming preset slugs in `settings.color.palette` after
  shipping styles that reference them via
  `var:preset|color|{slug}`. Existing references break silently
  (CSS variable resolves to `var(--wp--preset--color--unknown)`
  → unset).
- ❌ Setting `styles.color.gradient: "#0066cc"`. Gradient field
  expects a CSS gradient string (e.g.,
  `"linear-gradient(135deg, #f00, #ff0)"`), not a flat color.

## RELATED

- `theme-config.json-settings-color` — the registry / governance
  layer this realizes. Settings declares the palette; styles
  consumes it via `var:preset|color|{slug}` references. Vertical
  pipeline closure: registry → realization.
- `block.supports.color` — per-block-side counterpart. supports
  declares what UI controls appear in the inspector for users
  to OVERRIDE these theme-level styles per instance. The cascade:
  styles (theme defaults) → block instance style.color.* (user
  override).
- `block.markup-representation` — the per-instance `style.color.*`
  in the block delimiter is the cascade leaf. Theme styles set
  defaults; block instances override.
- `block.wrapper-attributes` — styles realize via wrapper class
  names (`has-{slug}-color`) and CSS variables. The wrapper hook
  is the receiver layer.
- `theme-config.json-styles-filter` (planned) — `styles.filter.duotone`
  is where duotone realization lives (NOT in styles.color despite
  the preset namespace being at settings.color.duotone).
  Asymmetric migration test case.
- `theme-config.json-styles-typography` (planned) — adjacent
  realization with the same `string | { ref }` grammar pattern.
  Same vertical-pipeline structure (settings registry → styles
  realization).
