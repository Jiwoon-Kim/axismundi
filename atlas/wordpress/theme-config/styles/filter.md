---
rule_id: theme-config.json-styles-filter
domain: theme-config
topic: styles
field_cluster: realization
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#filter
    section: "theme.json — styles.filter (1 field: duotone)"
    captured: 2026-05-09
related:
  - block.supports.filter                  # exposure layer (filter namespace)
  - theme-config.json-settings-color       # preset registry (LEGACY color namespace — namespace asymmetry origin)
  - theme-config.json-styles-color         # adjacent realization layer (color values, NOT duotone)
  - block.markup-representation            # block delimiter stores style.color.duotone (legacy storage path)
  - block.wrapper-attributes               # filter realization emits via CSS filter property + SVG filter element
---

# RULE — `styles.filter` — visual transformation realization

## WHEN

Configuring a theme's `theme.json` `styles.filter.*` to set duotone
filter values at the theme / per-block-type / per-element scope.
Currently `duotone` is the only documented sub-property.

This is the **smallest realization-layer chunk in the KB so far
(1 field)** but carries **disproportionate ontology weight**: it
**closes the namespace asymmetry test** that started in
`block.supports.filter`. The asymmetric migration of duotone
(exposure: color→filter; storage: kept color) is now confirmed
across all 3 layers.

## SHAPE

### 1 field

| Field | Type | CSS effect |
|---|---|---|
| `duotone` | `string \| { ref }` | Sets the duotone filter — typically resolved to a CSS `filter: url(#wp-duotone-{slug})` reference at runtime. |

### Section description

Source: *"CSS and SVG filter styles."* The styles.filter section is
explicitly framed as covering both CSS filter syntax AND SVG filter
references — duotone uses the SVG filter mechanism (a `<svg>` filter
element injected into the document, referenced via `filter: url()`).

### Theme-level example

```json
{
  "styles": {
    "filter": {
      "duotone": "var:preset|duotone|midnight"
    }
  }
}
```

This applies the "midnight" duotone preset (declared at
`settings.color.duotone`) to all elements at body scope.

### Per-block-type override

```json
{
  "styles": {
    "blocks": {
      "core/image": {
        "filter": {
          "duotone": "var:preset|duotone|sepia"
        }
      }
    }
  }
}
```

### Custom duotone (2-color array form)

```json
{
  "styles": {
    "filter": {
      "duotone": "var:preset|duotone|midnight"
    }
  }
}
```

For a custom (non-preset) duotone, the value would typically be a
literal array of 2 colors. ⚠ Source documents the type as
`string | { ref }` — exact serialization of inline 2-color arrays
in the styles layer (versus block instance attribute) is
verification-needed. Block instance form documented as
`[ "#FFF", "#000" ]` (per `block.supports.filter`).

## REQUIRES

- Setting MUST be declared under `theme.json` `styles.filter`
  (top-level), `styles.elements.{name}.filter`, OR
  `styles.blocks.{name}.filter`.
- For preset references (`"var:preset|duotone|{slug}"`):
  - The slug MUST match an entry in `settings.color.duotone`
    (LEGACY namespace — note: presets live in COLOR settings,
    NOT filter settings).
  - Or core's default duotone presets if
    `settings.color.defaultDuotone` is not disabled.
- For block-targeting via `selectors.filter.duotone` (declared in
  `block.json`'s `selectors` field): the realization applies to
  the matched descendant element (e.g., `.wp-block-image img`),
  not the block wrapper directly.
- For SVG-filter-based duotone: the runtime injects a `<svg>`
  filter element into the document referenced by
  `filter: url(#wp-duotone-{slug})`. Browser support for SVG
  filters via CSS `filter: url()` is required (universal in
  modern browsers).

## INVARIANTS

- **Namespace asymmetry test — 3rd layer CONFIRMED.** The
  asymmetric migration of duotone is now empirically validated
  across the full stack:

  | Layer | Namespace | Note |
  |---|---|---|
  | `block.supports.filter.duotone` | **filter** namespace (NEW) | exposure migrated from color.__experimentalDuotone |
  | `settings.color.duotone` | **color** namespace (LEGACY) | preset registry kept in original location for backward compatibility |
  | `block.markup-representation` storage path | `style.color.duotone` (LEGACY) | per-instance attribute storage kept in color path |
  | **`styles.filter.duotone` (THIS chunk)** | **filter** namespace (NEW) | theme-level realization in new namespace |

  **Pattern: exposure + realization migrated to filter; preset
  registry + storage kept in color.** The asymmetry is bidirectional:
  - 2 layers in NEW namespace (exposure + realization)
  - 2 layers in LEGACY namespace (preset registry + per-instance storage)

  This is **deliberate design** preserving ecosystem stability
  (existing themes' duotone presets in `settings.color.duotone`
  continue to work; existing posts' `style.color.duotone` continue
  to parse) while reclassifying the capability to its semantically
  correct family (filter, not color).
- **"Namespace stability ≠ semantic truth" — 3rd validation.**
  The pattern first observed in supports.filter (capability
  reclassified) and settings.color (registry kept) is now closed
  in styles. All 3 layers have settled into their final positions.
  This invariant is now empirically established as a Gutenberg
  design principle, not an artifact.
- **Section description is dual-mode: "CSS and SVG filter
  styles".** Source explicitly frames this section as covering
  BOTH:
  - **CSS filter syntax** (e.g., `filter: blur(5px)` — though no
    such fields are documented currently)
  - **SVG filter references** (the duotone mechanism uses an
    injected SVG filter element + CSS `filter: url(#id)`)
  The single documented field (`duotone`) uses the SVG path. The
  framing leaves room for future additions (e.g., a generic
  `filter` field accepting CSS filter functions).
- **Realization mechanism is SVG-based, not CSS-property-based.**
  Unlike color (sets `color`/`background-color`) or typography
  (sets `font-size`/`line-height`), duotone realization works
  by injecting an SVG `<filter>` element into the document and
  emitting `filter: url(#wp-duotone-{slug})` on the targeted
  element. The CSS variable model (`--wp--preset--duotone--{slug}`)
  exists but resolves to the SVG filter URL reference, not to a
  raw CSS color value.
- **Vertical pipeline (with namespace asymmetry):**
  ```
  settings.color.duotone[]               (preset registry — LEGACY namespace)
      ↓ at theme load
  SVG <filter> element injected to <head>
  CSS variable: --wp--preset--duotone--{slug}: url(#wp-duotone-{slug})
      ↓ referenced by
  styles.filter.duotone = "var:preset|duotone|{slug}"   (THIS chunk — NEW namespace)
      ↓ resolves to
  filter: url(#wp-duotone-{slug})  on target element
      ↓ rendered by browser
  duotone visual effect via SVG filter primitive
  ```
- **`selectors.filter.duotone` controls WHERE the filter lands.**
  Declared in `block.json`'s `selectors` field (per
  `block.supports.filter`), this points to the descendant element
  (e.g., `.wp-block-image img`) that should receive the filter.
  Without selector configuration, the filter typically lands on
  the wrapper (verification-needed for default behavior).
- **The smallest realization-layer chunk (1 field).** styles.color
  has 3, styles.typography has 12, styles.spacing has 3,
  styles.filter has 1. Field-count asymmetry vs settings:
  - settings has NO `filter` section.
  - styles has `filter` (1 field).
  - settings has `color.duotone` field as part of color section.
  This is **inverse asymmetry**: realization layer EXPOSES filter
  as its own section while authority layer keeps it nested in
  color. Reflects the "exposure migrated; storage kept" pattern.
- **No per-element scoping documented for duotone.** Unlike color
  / typography where `styles.elements.{element}.color.text` is
  common, duotone applies primarily via per-block-type
  (`styles.blocks.{name}.filter.duotone`) or via the
  `selectors.filter.duotone` declaration in block.json (which
  targets specific descendant elements).
- **Custom (non-preset) duotone serialization in styles is
  unverified.** The `string | { ref }` type signature suggests
  any string is allowed. Per-instance block delimiters store
  `[ "#FFF", "#000" ]` array form (per
  `block.supports.filter`), but whether `styles.filter.duotone`
  also accepts inline arrays vs requiring preset references is
  not crisply documented. Use preset references for theme-level
  styles (safer + more maintainable).
- **`{ ref }` form usage caveat** — same as styles.color /
  typography / spacing. Type documented; full semantics
  verification-needed.
- ⚠ **Minimum WP version unknown.** The `filter.duotone`
  exposure was introduced when duotone was reclassified
  (replacing `color.__experimentalDuotone` deprecated since WP
  6.3). The `styles.filter.duotone` realization presumably
  arrived in the same release. Frontmatter `wp_min` is
  `"verification-needed"`.

## ANTIPATTERNS

- ❌ Looking for duotone in `styles.color.duotone`. Does not
  exist. Realization is at `styles.filter.duotone`. Despite the
  preset registry living at `settings.color.duotone`, the
  realization layer uses the new filter namespace.
- ❌ Looking for duotone presets at `settings.filter.duotone`.
  They live at `settings.color.duotone` (LEGACY namespace).
  This is the reverse mistake — assuming the reclassification
  applied to ALL layers when in fact the registry stayed in
  color.
- ❌ Hardcoding `filter: url(#wp-duotone-...)` CSS in stylesheets.
  Bypasses the supports cascade — user choices and theme-level
  styles can't apply, and the SVG filter element may not be
  injected for that slug.
- ❌ Setting `styles.filter.duotone` without ensuring the slug
  exists in `settings.color.duotone` (or relying on default
  presets). Resolves to undefined CSS variable; visible result
  is no filter applied.
- ❌ Setting per-element scoping like
  `styles.elements.image.filter.duotone`. Source doesn't
  document `image` as a recognized style element; the
  per-element styling system targets specific element types
  (link, heading, button, etc.) not arbitrary tags. Use
  per-block-type scoping (`styles.blocks.core/image.filter.duotone`)
  or block.json's `selectors.filter.duotone` for descendant
  targeting.
- ❌ Renaming duotone preset slugs in `settings.color.duotone`
  after styles references them. Same risk as renaming color or
  font-size slugs — references break silently.
- ❌ Expecting duotone to work without `settings.color.duotone`
  (no presets) AND without `settings.color.defaultDuotone: true`.
  No presets available → picker is empty → no duotone can be set.
- ❌ Looking for other CSS filter functions (blur, grayscale,
  saturate, hue-rotate) in `styles.filter.*`. Currently only
  duotone is documented. The section description ("CSS and SVG
  filter styles") leaves room for future additions, but they
  are not in the captured source.

## RELATED

- `block.supports.filter` — exposure layer counterpart. The
  block declares `supports.filter.duotone: true` to enable the
  duotone control in the inspector. Block-side opt-in pairs
  with this theme-side realization.
- `theme-config.json-settings-color` — preset registry for
  duotone. **LEGACY NAMESPACE**: presets live at
  `settings.color.duotone` (NOT settings.filter.duotone, which
  doesn't exist). The asymmetric migration kept registries in
  the original color namespace for backward compatibility.
- `theme-config.json-styles-color` — adjacent realization layer.
  styles.color handles background/gradient/text;
  styles.filter handles duotone. The capability split is by
  semantic family (color values vs filter transforms), even
  though both relate to "color" in the user-facing sense.
- `block.markup-representation` — block delimiter's
  `style.color.duotone` (LEGACY storage path) is the
  per-instance cascade leaf. Theme styles set defaults via
  `styles.filter.duotone`; per-instance values override at
  `style.color.duotone` in the delimiter.
- `block.wrapper-attributes` — filter realization emits via the
  CSS `filter` property on the targeted element (block wrapper
  by default, or descendant if `selectors.filter.duotone` is
  configured). The SVG filter element is injected separately by
  the style engine.
