---
rule_id: block.supports.filter
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
deprecates:
  - block.supports.color.experimentalDuotone   # filter.duotone replaces __experimentalDuotone (deprecated since WP 6.3)
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#filter
    section: "Supports — filter (duotone)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # filter values store at style.color.duotone (legacy path)
  - block.wrapper-attributes             # filter emission flows through useBlockProps
  - block.supports.color                 # historical parent — duotone WAS under color, now repaired
  - block.supports.background            # adjacent surface treatment with media coupling
  - block.deprecation                    # __experimentalDuotone migration is a deprecation case
  - theme-config.json-color-duotone      # cross-context: presets STILL live at theme.json color.duotone
---

# RULE — `supports.filter` capability flag (visual transformation pipeline)

## WHEN

Defining a block (typically a media-containing block — image, gallery,
cover, etc.) that should expose **duotone filter controls** in the
block inspector. Currently `duotone` is the only sub-property
documented. Apply when the block displays media that should be
transformable via a two-color duotone effect (highlight + shadow).

This chunk also serves as the **ontology repair node** for the
`color.__experimentalDuotone → filter.duotone` migration: the
capability used to live under color but was reclassified.

**Capability sub-pattern:** This is the first capability in the
"visual transformation pipeline" sub-family — it operates on the
RENDERED surface (typically via SVG filter primitives + CSS
`filter: url()`), not by emitting flat CSS color/style properties.
Different mechanism than color/typography/spacing/dimensions/shadow/background.

## SHAPE

```json
{
  "supports": {
    "filter": {
      "duotone": true
    }
  },
  "selectors": {
    "filter": {
      "duotone": ".wp-block-image img"
    }
  }
}
```

### Sub-property matrix

| Sub-property | Type | Default | Notes |
|---|---|---|---|
| `duotone` | `boolean` | `false` | Adds duotone picker to the block inspector. |

### Companion field: `selectors.filter.duotone`

The supports flag declares the capability; the **`selectors` field**
(top-level in `block.json`, sibling of `supports`) configures **which
descendant element receives the filter**. For the image block:
`selectors.filter.duotone: ".wp-block-image img"` — the duotone
applies to the inner `<img>` element, not the wrapper.

Without `selectors.filter.duotone` configuration, the filter target
defaults to the wrapper — which may not be the visually correct
target for blocks that contain media inside additional markup.

## REQUIRES

- Block MUST be registered server-side. Filter controls and
  theme.json preset integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element.
- For media-targeted blocks (image, gallery, cover, etc.):
  `selectors.filter.duotone` MUST point to the actual media element
  (typically the inner `<img>` or `<video>`). Without correct
  targeting, the filter applies to the wrong element or the entire
  wrapper.
- Theme MUST provide duotone presets via `theme.json`
  `settings.color.duotone` (note: preset namespace is `color`,
  NOT `filter` — see Asymmetric migration below).
- Browser MUST support SVG filters via CSS `filter: url(#id)` —
  effectively all modern browsers, but very old WP installations
  on legacy browsers may degrade.

## INVARIANTS

### Editor effects

- A **duotone picker** appears in the block inspector when
  `supports.filter.duotone: true` is declared.
- The picker shows duotone presets from `theme.json`
  `settings.color.duotone` (legacy preset namespace), plus a custom
  picker that accepts a 2-color array (highlight + shadow).
- ⚠ Custom-picker availability and theme-side gating
  (`settings.color.customDuotone`?) is not explicitly documented in
  the captured source for filter.duotone.

### Attribute effects

- The `style` attribute is added to the block's schema.
- **Selected duotone value is stored at `style.color.duotone`** —
  NOT at `style.filter.duotone`. This is the **legacy storage path**
  retained from the pre-migration era when duotone lived under
  color.

```json
{
  "attributes": {
    "style": {
      "type": "object",
      "default": {
        "color": {
          "duotone": [ "#FFF", "#000" ]
        }
      }
    }
  }
}
```

The value is a 2-element array of color strings: `[highlight, shadow]`.
Preset references would use the standard `var:preset|duotone|{slug}`
form (verification needed for the exact preset reference syntax under
duotone).

This **storage-path / exposure-path mismatch** is the artifact of an
asymmetric migration (see General invariants).

### Wrapper effects

- ⚠ Source does not detail the exact CSS emission. Inferred from
  general knowledge of duotone implementations:
  - A `<svg>` filter element is injected (typically once per duotone
    preset, into the document head or a hidden container).
  - The block's wrapper (or the element targeted by
    `selectors.filter.duotone`) receives a CSS rule:
    `filter: url(#wp-duotone-{slug});` for presets.
- ⚠ The exact CSS class name pattern (`has-{slug}-duotone-filter`?
  or similar) is not enumerated in the captured source. Verify via
  actual block output.
- All emission flows through `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` —
  required spreading per `block.wrapper-attributes`.

### Serialization effects

Block delimiter stores duotone selection at the legacy color path:

```html
<!-- wp:my-plugin/foo {"style":{"color":{"duotone":["#FFF","#000"]}}} -->
```

For preset references (verification-needed for exact syntax):

```html
<!-- wp:my-plugin/foo {"style":{"color":{"duotone":"var:preset|duotone|midnight"}}} -->
```

Existing posts stored under the deprecated `__experimentalDuotone`
or pre-migration paths still parse correctly — the storage layer
was deliberately kept stable to preserve compatibility.

### theme.json interaction

- **Duotone presets live at `theme.json` `settings.color.duotone`** —
  NOT at `settings.filter.duotone`. This is the **legacy preset
  namespace** retained from the pre-migration era.
- Each preset entry has the shape `{ name, slug, colors }` where
  `colors` is a 2-element array `[highlight, shadow]`.
- The `appearanceTools: true` shortcut does NOT explicitly enable
  duotone — duotone is not in the appearanceTools bundle (per the
  background spike's enumeration).
- ⚠ Whether `defaultPresets` toggle exists for duotone (analogous
  to `settings.shadow.defaultPresets`) is not documented in the
  captured source. Verify per WP version.

### General invariants

- **Asymmetric migration — exposure moved, storage stayed.** The
  pre-WP-6.3 capability `color.__experimentalDuotone` was renamed
  in two steps:
  1. **Exposure layer reclassified:** `supports.filter.duotone`
     replaces `supports.color.__experimentalDuotone`. Block authors
     opt in via the new `filter` namespace.
  2. **Storage path retained:** Attribute values still serialize at
     `style.color.duotone`. Preset namespace stays at
     `theme.json` `settings.color.duotone`.

  Why asymmetric? Storage / preset migration would break existing
  posts and themes. Only the exposure (block author surface) was
  changed; the runtime data plane was deliberately frozen.
- **Ontology correction:** Gutenberg explicitly judged that duotone
  is NOT a color capability. It's a **render-stage transformation**
  — operating via SVG filter primitives on the rendered surface.
  Conceptually closer to CSS `filter` than to `color`.
- **Capability sub-family — visual transformation pipeline.**
  Distinct from token/state → wrapper emission patterns:
  - color: CSS color values → CSS color properties
  - typography: CSS typography values → CSS typography properties
  - spacing/dimensions: CSS box values → CSS box properties
  - shadow: shadow CSS string → CSS box-shadow
  - background: image URL + position/size → CSS background properties
  - **filter.duotone: 2-color array → SVG filter primitive + CSS filter:url() reference** ← new pattern
- **`selectors.filter.duotone` is a structural prerequisite for
  media-containing blocks.** Unlike most supports flags that target
  the wrapper directly, filter often needs to apply to a specific
  inner element (the actual media). The `selectors` field configures
  this targeting.
- **Currently the only `filter.*` sub-property is `duotone`.** The
  `filter` namespace is open for future filter-type additions
  (blur, saturate, hue-rotate, etc.) but only duotone is documented.
- ⚠ **Minimum WP version unknown for `filter.duotone` proper.** The
  deprecated `color.__experimentalDuotone` predates WP 6.3 (when it
  was deprecated). The replacement `filter.duotone` was introduced
  AT or BEFORE WP 6.3 (must exist by then for the deprecation to
  point to it). Frontmatter `wp_min` is `"verification-needed"`.

## ANTIPATTERNS

- ❌ Using `supports.color.__experimentalDuotone` in new code.
  **Deprecated since WP 6.3.** Use `supports.filter.duotone`. The
  experimental form may still work in older WP versions but will
  be removed. See `block.deprecation`.
- ❌ Storing duotone values at `style.filter.duotone`. Documented
  storage path is the legacy `style.color.duotone`. Storing at the
  "expected" filter-namespace path breaks round-trip — the parser
  reads from `style.color.duotone`.
- ❌ Looking for duotone presets at `theme.json`
  `settings.filter.duotone`. They live at `settings.color.duotone`
  (legacy preset namespace). Theme authors moving duotone presets
  to "filter" will silently break the picker.
- ❌ Forgetting `selectors.filter.duotone` for blocks that contain
  media inside additional markup. The filter applies to the wrong
  element (the wrapper instead of the media), producing visual
  artifacts.
- ❌ Storing a single color string for duotone. Duotone REQUIRES
  a 2-element array `[highlight, shadow]`. Single value or 3+
  values are malformed.
- ❌ Hardcoding `filter: url(...)` CSS in the block's `save()` /
  render output. Bypasses the supports cascade — user can't change
  via picker, theme presets have no effect, the SVG filter element
  may not be injected.
- ❌ Treating duotone as a color picker. The two values represent
  the highlight (replaces light tones in the source media) and
  shadow (replaces dark tones) — they are NOT a primary/secondary
  color pair, NOT a gradient, NOT a foreground/background. Custom
  pickers should communicate this semantics.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags.
- ❌ Expecting `appearanceTools: true` to enable duotone. Not in
  the bundle. Theme must provide `settings.color.duotone` presets
  explicitly; block must declare `supports.filter.duotone`
  explicitly.

## RELATED

- `block.json-supports-field` — parent rule explaining the supports
  mechanism in general; this filter flag is an instance with the
  ontology-repair caveat.
- `block.json-attributes-core` — filter values inject into the
  shared `style` attribute, but at the legacy `style.color.duotone`
  path (NOT `style.filter.*`).
- `block.wrapper-attributes` — filter emission flows through the
  wrapper hooks, with the `selectors.filter.duotone`-targeted
  element as the final receiver of the CSS `filter` property.
- `block.supports.color` — **historical parent.** Duotone WAS a
  `color.__experimentalDuotone` sub-property. The `experimentalDuotone`
  is now deprecated; storage / presets retain "color" naming.
- `block.supports.background` — adjacent surface-treatment family.
  Background and filter both target rendered surfaces; filter can
  apply ON TOP of a background-image setup.
- `block.deprecation` — the `color.__experimentalDuotone →
  filter.duotone` migration is a deprecation case. Block authors
  shipping deprecation entries for blocks that previously used the
  experimental form should reference this rule.
- `theme-config.json-color-duotone` (cross-context, planned) —
  full theme.json `settings.color.duotone` reference: preset shape
  `{ name, slug, colors }`, default presets, custom-duotone gating.
  Lives in color namespace despite being filter capability — direct
  reference of the asymmetric migration.
