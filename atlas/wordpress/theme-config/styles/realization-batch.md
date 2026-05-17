---
rule_id: theme-config.json-styles-realization-batch
domain: theme-config
topic: styles
field_cluster: realization
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#background-2
    section: "theme.json — styles.background (5 fields)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#border-2
    section: "theme.json — styles.border (8 fields incl. per-side composite)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#dimensions-2
    section: "theme.json — styles.dimensions (5 fields)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#outline
    section: "theme.json — styles.outline (4 fields)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#shadow-2
    section: "theme.json — styles.shadow (section only, no property table)"
    captured: 2026-05-09
related:
  - theme-config.json-styles-color           # token consumption pattern (validated)
  - theme-config.json-styles-typography      # computational realization (validated)
  - theme-config.json-styles-spacing         # generated-token realization + per-side object grammar
  - theme-config.json-settings-color         # settings counterpart for background image-related
  - theme-config.json-settings-shadow        # settings counterpart for shadow presets
  - theme-config.json-appearanceTools        # bundle that exposes border / dimensions / position.sticky
  - block.supports.background                # block-side counterpart for image-related fields
  - block.supports.dimensions                # block-side counterpart for sizing fields
---

# RULE — `styles.{background, border, dimensions, outline, shadow}` — realization grammar variants

## WHEN

Configuring a theme's `theme.json` `styles.*` for the **5 remaining
realization fields** that closes the styles area. These fields are
**variants of validated styles subtypes** (token consumption +
structured realization), not new ontology axes:

- `styles.background` — image / position / repeat / size / attachment
  realization
- `styles.border` — color / radius / style / width with per-side
  decomposition
- `styles.dimensions` — sizing realization (aspectRatio / height /
  width / min-versions)
- `styles.outline` — outline color / offset / style / width
- `styles.shadow` — box-shadow value realization

Batched as one chunk because each is a **pattern variant** of
already-validated grammar (per styles.color / typography / spacing).
New ontology weight is in **specific grammar discoveries** and
**asymmetric existence with settings** (see invariants), not in
new realization paradigms.

## SHAPE

### `styles.background` — 5 fields

| Field | Type | CSS effect |
|---|---|---|
| `backgroundImage` | `string \| { ref } \| { url }` | `background-image` — **3-form type (UNIQUE)**: literal CSS, ref object, OR URL object form |
| `backgroundPosition` | `string \| { ref }` | `background-position` |
| `backgroundRepeat` | `string \| { ref }` | `background-repeat` |
| `backgroundSize` | `string \| { ref }` | `background-size` |
| `backgroundAttachment` | `string \| { ref }` | `background-attachment` |

```json
{
  "styles": {
    "background": {
      "backgroundImage":      { "url": "https://example.com/hero.jpg" },
      "backgroundPosition":   "50% 50%",
      "backgroundSize":       "cover",
      "backgroundAttachment": "fixed"
    }
  }
}
```

The `{ url }` object form for backgroundImage is the styles-layer
equivalent of the per-instance attribute structure
(`{ url, id, source, title }` documented in
`block.supports.background`). Theme-level images may omit `id` /
`source` / `title` since they're not user-uploaded media.

### `styles.border` — 8 fields (most complex grammar in styles)

| Field | Type | CSS effect |
|---|---|---|
| `color` | `string \| { ref }` | `border-color` (all sides) |
| `radius` | `string \| { ref } \| { topLeft, topRight, bottomLeft, bottomRight }` | `border-radius` — **per-corner object form available** |
| `style` | `string \| { ref }` | `border-style` (all sides) |
| `width` | `string \| { ref }` | `border-width` (all sides) |
| `top` | `{ color, style, width }` | per-side **composite object** — top border |
| `right` | `{ color, style, width }` | per-side composite — right border |
| `bottom` | `{ color, style, width }` | per-side composite — bottom border |
| `left` | `{ color, style, width }` | per-side composite — left border |

Two parallel addressing modes:
- **All-sides** mode: top-level `color/style/width` apply to all
  4 sides uniformly.
- **Per-side** mode: `top/right/bottom/left` objects override
  per-side with their own color/style/width composite.

```json
{
  "styles": {
    "border": {
      "color":  "var:preset|color|outline",
      "radius": { "topLeft": "8px", "topRight": "8px",
                  "bottomLeft": "0", "bottomRight": "0" },
      "width":  "1px",
      "style":  "solid",
      "bottom": { "color": "var:preset|color|accent", "width": "3px", "style": "solid" }
    }
  }
}
```

### `styles.dimensions` — 5 fields

| Field | Type | CSS effect |
|---|---|---|
| `aspectRatio` | `string \| { ref }` | `aspect-ratio` |
| `height` | `string \| { ref }` | `height` |
| `minHeight` | `string \| { ref }` | `min-height` |
| `minWidth` | `string \| { ref }` | `min-width` |
| `width` | `string \| { ref }` | `width` |

Pure token-consumption pattern. All 5 fields use the canonical
styles grammar.

### `styles.outline` — 4 fields

| Field | Type | CSS effect |
|---|---|---|
| `color` | `string \| { ref }` | `outline-color` |
| `offset` | `string \| { ref }` | `outline-offset` (gap between outline and border) |
| `style` | `string \| { ref }` | `outline-style` |
| `width` | `string \| { ref }` | `outline-width` |

Same structured grammar as `styles.border` minus per-side
decomposition (CSS outline does not support per-side specification).
NO `radius` field (CSS outline doesn't have rounded outlines —
unlike border-radius).

### `styles.shadow` — section without explicit property table

Source: *"Box shadow styles."* — section header only. No property
table documented in source.

⚠ **Field structure verification needed.** The source documents the
section but does NOT enumerate sub-fields. Inferred behavior (per
the styles pattern):
- `styles.shadow` likely accepts a string value directly
  (e.g., `"var:preset|shadow|deep"` or
  `"0 4px 8px rgba(0,0,0,0.2)"`).
- Sub-fields like `color`, `offset`, `blur`, `spread` are NOT
  documented as separate properties — `box-shadow` CSS is a
  comma-separated string in CSS itself.
- Realization is single-string consumption — closer to
  `styles.color.text` (single value) than to `styles.border`
  (decomposed properties).

```json
{
  "styles": {
    "shadow": "var:preset|shadow|deep"
  }
}
```

⚠ Confirm exact structure when implementing.

## REQUIRES

- All fields MUST be declared under `theme.json` `styles.*`,
  `styles.elements.{name}.*`, OR `styles.blocks.{name}.*`.
- **`styles.border` and `styles.outline` have NO settings
  counterparts.** Themes cannot configure border/outline presets
  in `settings.*` — these realization fields are styles-only.
  border IS exposed via `appearanceTools: true` (which enables
  border color/radius/style/width UI controls); outline has no
  documented settings opt-in.
- For preset references in any field
  (`"var:preset|{capability}|{slug}"`): the slug MUST exist in
  the corresponding `settings.{capability}` registry (or core
  defaults).
- For `backgroundImage` `{ url }` form: URL MUST be reachable at
  runtime. Theme-bundled images may use relative paths; external
  URLs work but introduce external dependency.
- For `border.radius` per-corner object form: declare only the
  corners that should differ; omitted corners default to whatever
  the all-sides `radius` (or browser default) provides.
- For `border.{top,right,bottom,left}` composite forms: declare
  only the sides that should differ from the all-sides defaults.
- For `styles.shadow` value: must be valid CSS `box-shadow`
  syntax OR a `var:preset|shadow|{slug}` reference matching a
  preset in `settings.shadow.presets`.

## INVARIANTS

- **All 5 fields confirm validated styles subtypes** — no new
  ontology paradigms:
  - background → token consumption + asset realization
    (image URL handling)
  - border → structured realization (most complex grammar:
    composite + per-side + per-corner)
  - dimensions → pure token consumption
  - outline → structured realization (no per-side)
  - shadow → token consumption (single-value endpoint)
- **NEW grammar discovery: 3-form types.** Two fields exhibit
  3-option type signatures (`string | { ref } | { specific
  object }`):
  - `backgroundImage`: `string | { ref } | { url }` — URL object
    form is unique to this field, handles theme-bundled images
    distinctly from preset references.
  - `border.radius`: `string | { ref } | { topLeft, topRight,
    bottomLeft, bottomRight }` — per-corner object form, unique
    to this field.
  Source's value-grammar pattern in styles is no longer just
  "string | {ref}" + occasional structured object — it admits
  3-form discriminated unions.
- **NEW grammar discovery: composite per-side objects.**
  `border.top/right/bottom/left` use `{ color, style, width }`
  composite objects — each side can have its own complete
  border specification. This is more expressive than spacing's
  per-side simple-value object (`{ top: "16px" }`) — border
  per-side decomposes into THREE properties per side.
- **`border` and `outline` have NO settings counterparts.**
  Settings layer has `settings.background`, `settings.shadow`,
  `settings.dimensions` but NO `settings.border` and NO
  `settings.outline` documented. Implications:
  - Border can be CONTROLLED via `appearanceTools: true` (UI
    controls become available) OR explicit
    `settings.border.{property}: true` (verification-needed —
    appearanceTools description lists border subproperties
    directly).
  - Outline has NO documented theme-side opt-in. Themes use
    `styles.outline.*` directly without governance gates.
  - This is the **inverse asymmetry** of `styles.layout` absence:
    layout is settings-only; outline is styles-only.
- **Settings ↔ styles asymmetry profile per remaining
  capability:**
  - **background**: settings has 2 sub-fields (backgroundImage /
    backgroundSize toggles via appearanceTools); styles has 5 →
    asymmetric, styles BROADER (all CSS background properties).
  - **border**: settings has 0; styles has 8 → fully styles-only.
  - **dimensions**: settings has 4 (height/minHeight/minWidth/width
    toggles); styles has 5 (adds aspectRatio realization) →
    near 1:1 mirror with one extra realization field.
  - **outline**: settings has 0; styles has 4 → fully
    styles-only.
  - **shadow**: settings has 2 (defaultPresets / presets);
    styles has ~1 (single-value endpoint) → asymmetric, settings
    governs registry, styles consumes preset reference.
- **Per-side/per-corner forms are SCOPED REALIZATION.** Border's
  per-side and per-corner objects allow targeted realization
  WITHOUT writing full all-sides values. Mirrors spacing's
  per-side approach but with composite (multi-property) objects
  rather than simple values.
- **`backgroundImage` `{ url }` form is for THEME-LEVEL images.**
  Block-instance backgroundImage stores
  `{ url, id, source, title }` (per `block.supports.background`)
  — full media reference. Theme-level styles use
  `{ url }` only — themes don't reference user-uploaded media,
  they ship their own. The `id` / `source` / `title` are
  per-instance metadata.
- **`styles.outline.offset` is unique among outline-related
  styling.** No equivalent `border.offset` exists in CSS or
  here — offset is outline-specific (the gap between the
  element and the outline). Authors switching from border to
  outline for accessibility focus indication get this
  capability.
- **`styles.dimensions` includes `aspectRatio` while
  `settings.dimensions` does not enumerate it explicitly.**
  Per `settings.dimensions` source, only height/minHeight/
  minWidth/width are listed as Subproperties; aspectRatio
  appears in the example but not the property list.
  styles.dimensions explicitly includes aspectRatio as a
  documented field. **Settings/styles asymmetry corner case** —
  if relying on aspectRatio governance, verify the
  settings-side actually exposes it.
- **`styles.shadow` minimal documentation hints at single-value
  endpoint.** Unlike border with 8 fields or background with 5,
  shadow's "section only, no property table" structure suggests
  the value is consumed as a whole (CSS box-shadow syntax is
  itself a comma-separated single string in CSS). No
  per-shadow-component decomposition (color/offset/blur/spread)
  in the styles layer.
- **All fields support the standard 4-level cascade** (top-level
  / styles.elements.* / styles.blocks.{name}.* / per-instance
  style.* in delimiter). Per-element styling is most useful for
  border (per-element border styling like buttons), background
  (per-element background images), and shadow (per-element
  shadow effects). dimensions / outline are typically
  per-block-type concerns.
- ⚠ **Minimum WP version unknown** for individual fields. Most
  realization fields have been part of theme.json since early
  v1; specific additions (e.g., border per-side composite,
  border.radius per-corner) likely came later. Frontmatter
  `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Looking for `settings.border.*` to gate border UI fully.
  Settings has NO border section; use `appearanceTools: true`
  for the bundle OR omit settings entirely (themes write
  `styles.border.*` directly without theme-side governance).
- ❌ Looking for `settings.outline.*`. Doesn't exist. Use
  `styles.outline.*` directly.
- ❌ Setting `styles.border.top: "1px solid black"` (string
  shorthand). Per-side fields use composite OBJECT form
  (`{ color, style, width }`), not CSS shorthand strings.
- ❌ Setting `styles.border.radius: { top: "8px" }` (per-side
  rather than per-corner). Border-radius is per-CORNER
  (`topLeft, topRight, bottomLeft, bottomRight`), NOT per-side.
  Per-side is for color/style/width.
- ❌ Setting `styles.dimensions.aspectRatio: "16:9"` and
  expecting CSS `aspect-ratio` syntax with colon. CSS
  `aspect-ratio` accepts both `16/9` (slash) and `16 / 9` (with
  spaces) but NOT colon. Use `"16/9"` format.
- ❌ Hardcoding image URLs in `backgroundImage` instead of using
  theme-relative paths or preset references. Hardcoded URLs
  may break across deployments.
- ❌ Setting `styles.outline.color` for accessibility focus
  styling without considering `:focus-visible` pseudo-class.
  styles.outline applies the outline always; accessibility
  focus typically uses `:focus-visible` — express via
  `styles.css` for pseudo-class scoping.
- ❌ Confusing `styles.outline.offset` with margin. Outline
  offset is the gap BETWEEN the element and the outline (CSS
  `outline-offset`), not external margin around the outline.
  The outline does NOT take up space in layout regardless of
  offset.
- ❌ Setting `styles.shadow` and `styles.color.gradient` on the
  same scope expecting both. shadow doesn't conflict with
  background/gradient (different CSS properties: box-shadow vs
  background), so they coexist — but verify visual stacking.
- ❌ Treating `styles.shadow` as an array. Source documents
  single-value section without decomposition; multiple shadows
  in CSS box-shadow are comma-separated within ONE string, not
  separate fields.

## RELATED

- `theme-config.json-styles-color` — token consumption pattern
  (canonical instance). background / dimensions follow the
  same lookup-and-consume model.
- `theme-config.json-styles-typography` — computational
  realization (canonical instance). Distinct from these 5
  fields — none of background/border/dimensions/outline/shadow
  involve runtime computation.
- `theme-config.json-styles-spacing` — structured realization
  with per-side objects. Border extends this pattern with
  COMPOSITE per-side objects (multi-property per side) and
  per-CORNER objects (border.radius).
- `theme-config.json-settings-color` — registries for color
  presets that backgroundImage's preset references can consume.
- `theme-config.json-settings-shadow` — registry / governance
  for shadow presets that styles.shadow consumes.
- `theme-config.json-appearanceTools` — bundles border + dimensions
  + position.sticky + others. The opt-in path for border (no
  settings.border equivalent).
- `block.supports.background` — block-side counterpart for
  image-related fields. Block uses
  `style.background.{ url, id, source, title }` per-instance;
  theme uses `styles.background.backgroundImage.{ url }` at
  theme scope.
- `block.supports.dimensions` — block-side counterpart for
  sizing fields. Same pattern as background.
