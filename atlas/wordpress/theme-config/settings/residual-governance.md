---
rule_id: theme-config.json-settings-residual-governance
domain: theme-config
topic: settings-residual
field_cluster: governance-bridges
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#settings
    section: "theme.json — settings (border / dimensions / lightbox / position / custom / useRootPaddingAwareAlignments)"
    captured: 2026-05-09
related:
  - theme-config.json-settings-color           # core registry substrate (contrast — these aren't registries)
  - theme-config.json-settings-typography      # core computational substrate
  - theme-config.json-settings-spacing         # core generative substrate
  - theme-config.json-styles-css               # realization-side escape hatch (settings.custom mirror)
  - block-authoring.supports.position          # supports counterpart for position.sticky
  - block-authoring.supports.dimensions        # supports counterpart for dimensions
  - style-engine.css-variable-emission         # settings.custom → --wp--custom--* namespace
  - style-engine.generated-selectors           # useRootPaddingAwareAlignments → wrapper synthesis bridge
---

# RULE — settings residual governance batch (border / dimensions / lightbox / position / custom / useRootPaddingAwareAlignments)

## WHEN

Configuring any of 6 remaining `theme.json` `settings.*` fields
that did NOT fit the 4 core settings subtypes (registry /
computational / composition / generative) covered by earlier
chunks (color / typography / layout / spacing). These 6 share a
**residual edge governance** character — they act as bridges /
adapters / escape hatches connecting settings to other systems
(runtime layout, editor features, schema escape, custom token
namespace), not as authority substrates of their own.

This batch closes the settings layer. After this chunk, all
documented settings.* fields are covered; remaining authority
surfaces escape to runtime systems (style-engine, layout engine,
browser cascade) or editor systems (Site Editor, block inserter).

## SHAPE

### settings.border — structured governance

```json
{
  "settings": {
    "border": {
      "color":  true,
      "radius": true,
      "style":  true,
      "width":  true
    }
  }
}
```

4 boolean toggles gating editor exposure of border sub-properties.
Notable: there is **no `supports.border` block-author exposure
layer** — border is theme-only authority via this settings field
+ `appearanceTools` bundle. Block authors cannot opt their block
into border controls per-block.

### settings.dimensions — token-consumption governance

```json
{
  "settings": {
    "dimensions": {
      "aspectRatio": true,
      "minHeight":   true,
      "defaultAspectRatios": false,
      "aspectRatios": [
        { "slug": "square",     "name": "Square",     "ratio": "1" },
        { "slug": "video",      "name": "Video (16:9)", "ratio": "16/9" }
      ]
    }
  }
}
```

Two governance gates (aspectRatio / minHeight) + one core-default
suppression (defaultAspectRatios) + one custom registry
(aspectRatios). Hybrid: governance + small registry slot. Object
structure mirrors the runtime synthesis complexity (aspect ratio
preset → CSS variable + apply-to-container property).

### settings.lightbox — capability exposure toggle

```json
{
  "settings": {
    "lightbox": {
      "enabled":            true,
      "allowEditing":       true
    }
  }
}
```

Two booleans gating an editor / front-end FEATURE (image lightbox
behavior). Pure capability exposure — settings does NOT realize
the lightbox; runtime image block + lightbox script realize.
Settings only declares "this feature is available."

### settings.position — layout-affordance governance

```json
{
  "settings": {
    "position": {
      "sticky": true
    }
  }
}
```

Single boolean gating sticky positioning capability. Coupled
with `block.json supports.position.sticky` — settings declares
theme-level availability, supports declares per-block opt-in.
Per-instance value materializes via runtime CSS (position:
sticky + offset) emission, not from settings directly.

### settings.custom — schema escape valve (declaration-side)

```json
{
  "settings": {
    "custom": {
      "lineHeight": {
        "body":    1.7,
        "heading": 1.125
      },
      "spacing": {
        "outer": "2rem",
        "inner": "1rem"
      }
    }
  }
}
```

Arbitrary user-defined custom properties registry. Each
declaration emits a CSS custom property under the
`--wp--custom--{path-segments-kebab-cased}` namespace. The above
emits:

```css
:root {
  --wp--custom--line-height--body:    1.7;
  --wp--custom--line-height--heading: 1.125;
  --wp--custom--spacing--outer:       2rem;
  --wp--custom--spacing--inner:       1rem;
}
```

A **third CSS variable namespace** alongside `--wp--preset--*`
(registry-derived) and `--wp--style--*` (synthesized). Authors
extend the variable graph without going through preset
registration.

### settings.useRootPaddingAwareAlignments — runtime-layout bridge flag

```json
{
  "settings": {
    "useRootPaddingAwareAlignments": true
  }
}
```

Single boolean. Looks trivial; materially complex. When true:

- Root padding (declared in styles.spacing.padding) emits as
  `--wp--style--root--padding-{top|right|bottom|left}` variables.
- Wrapper containers consume these variables to compute alignments
  that respect root padding (alignfull blocks extend to edges
  without root padding interference; constrained blocks honor
  root padding).
- Activates the layout engine's root-padding-aware computation
  path.

Single declaration toggles a runtime layout topology behavior +
variable emission + wrapper class generation. **Declaration
surface leaking runtime topology policy** — exactly the kind of
"settings field with disproportionate runtime impact" pattern.

## REQUIRES

- All 6 fields MUST be valid top-level entries under `settings`.
- Boolean fields default to `false` (or core defaults, varying
  per field — verification-needed for exact defaults).
- `border` requires `appearanceTools: true` for full UI exposure
  in many cases (verification-needed for exact gating behavior).
- `position.sticky: true` activates the capability theme-wide,
  but block-level `supports.position.sticky` still controls
  per-block opt-in.
- `useRootPaddingAwareAlignments: true` requires
  `styles.spacing.padding` to actually emit root padding values —
  the flag activates aware behavior but does NOT itself supply
  padding values.
- `settings.custom` keys can be nested arbitrarily; each leaf
  becomes a CSS variable. Path segments are kebab-cased and
  joined with `--`.

## INVARIANTS

### 1. Residual settings are disproportionately bridge-oriented

The 4 core settings subtypes (registry / computational /
composition / generative) operate as authority SUBSTRATES —
self-contained authority surfaces with their own realization
contracts. The 6 residual fields are different: they
disproportionately act as **bridges between settings and other
systems**:

| field | bridges to |
|---|---|
| border | appearanceTools (governance bundle) |
| dimensions | runtime aspect-ratio computation + supports.dimensions |
| lightbox | editor feature + frontend image runtime |
| position | block-level supports.position + runtime CSS emission |
| custom | style-engine variable namespace (third namespace) |
| useRootPaddingAwareAlignments | layout engine + wrapper synthesis + root padding variables |

Original settings core was substrate; residuals are inter-system
adapters. This is structural, not coincidental.

### 2. Settings can govern runtime behavior without owning realization

`useRootPaddingAwareAlignments`, `lightbox`, `position`
declare CAPABILITY AVAILABILITY but do NOT realize the
behavior. Realization happens at:

- Layout engine (root padding awareness)
- Image block runtime + lightbox script (lightbox behavior)
- Style engine + per-instance attribute serialization (sticky)

Settings is the **governance surface**; runtime is the
**realization surface**. The two are decoupled — settings can
authorize without participating in the implementation.

This matters because debugging "why isn't sticky working?"
requires checking BOTH settings (theme-level enable) AND
supports + per-instance value (block-level opt-in + value),
not just settings.

### 3. Escape hatches exist on BOTH declaration and realization sides

`settings.custom` is the declaration-side escape hatch; it
mirrors `styles.css` (realization-side escape hatch) at the
authority layer:

| layer | escape hatch | role |
|---|---|---|
| settings (declaration) | `custom` | author-defined token namespace |
| styles (realization) | `css` | author-defined CSS rules |
| runtime (synthesis) | generated selectors / aggregation | engine-synthesized scoping |

These three together are the structural complement to Gutenberg's
schema design. Without them, the schema would have to
exhaustively cover every author authoring need; with them,
Gutenberg explicitly accepts being a **partially-governed
compiler ecosystem** rather than a strict schema system.

The 3-layer escape pattern is a load-bearing design choice,
not redundant overlapping safety valves.

### 4. Structured settings mirror runtime topology complexity

`border` (4 sub-properties) and `dimensions` (multiple fields
+ aspectRatios registry) are structurally MORE complex than
core settings booleans. This is not arbitrary — the structure
reflects the runtime topology their values feed into:

- border: 4 sub-properties because border CSS realization has
  4 sub-properties (color / radius / style / width), each
  potentially per-side, each with preset registry potential.
- dimensions: aspectRatio + minHeight + custom aspectRatios
  registry mirrors the multi-axis dimensional control space
  (proportional ratio + absolute height + named presets).

When settings field structure feels complex, look at what
runtime topology it governs — the structure usually reflects
realization shape.

### 5. `useRootPaddingAwareAlignments` is declaration leaking runtime topology policy

A single boolean activates:
- Variable emission (`--wp--style--root--padding-*` synthesis).
- Wrapper class behavior (root-padding-aware alignment classes).
- Layout engine computation path (alignfull / constrained
  behavior re-computed against root padding).
- Container generated selectors (consumed by `.wp-container-*`
  rules).

The flag's surface is small (boolean); its runtime topology
impact is large. This is the cleanest example in the settings
layer of **a single declaration leaking runtime policy** —
similar in spirit to `supports.layout`'s subsystem-tier
character but at the settings (theme-level) authority.

Treating this as "just a boolean" misses what it activates.

### 6. `settings.custom` is the third CSS variable namespace

After `--wp--preset--*` (registry-derived) and `--wp--style--*`
(synthesized), `--wp--custom--*` is the third documented variable
namespace:

| namespace | source | character |
|---|---|---|
| `--wp--preset--*` | settings.{capability}.{registry} | stable registry ABI |
| `--wp--style--*` | styles.* values needing cross-rule consumption | synthesized runtime state |
| `--wp--custom--*` | settings.custom arbitrary tree | author-defined token namespace |

`--wp--custom--*` differs from `--wp--preset--*` in that there's
no editor UI consuming custom values (no preset picker for
custom properties); they emit as variables for theme-author
CSS / styles.css consumption only.

Custom is the **escape valve for the variable namespace itself**:
when a theme needs a token that doesn't fit any preset category,
custom provides the surface without creating a new preset
category.

### 7. Boolean / scalar settings field semantics are heterogeneous

The 6 fields use boolean / scalar value types but the SEMANTICS
differ per field:

| field | type | semantic |
|---|---|---|
| border.{color,radius,style,width} | boolean | UI exposure gate (per sub-property) |
| dimensions.{aspectRatio,minHeight} | boolean | feature gate (capability available) |
| dimensions.defaultAspectRatios | boolean | core defaults suppression switch |
| lightbox.enabled | boolean | feature toggle (capability on/off) |
| lightbox.allowEditing | boolean | UI authority gate (user can change) |
| position.sticky | boolean | capability availability declaration |
| useRootPaddingAwareAlignments | boolean | runtime behavior activation |
| custom.* | arbitrary | token value declarations (any type) |

"Boolean settings field" is NOT a uniform pattern. Each field's
boolean means a different KIND of authority decision. Reading
them as interchangeable misses what each governs.

### 8. Residual fields close the settings layer; remaining authority escapes to runtime

After this batch, the settings.* schema is fully documented:
- 4 core subtype representatives (color / typography / layout /
  spacing) — substrate authority.
- 6 residual fields (this chunk) — bridge / governance / escape
  authority.

What is NOT in settings.* (and never will be):
- Composition / topology authority (escapes to layout engine,
  generated selectors).
- Cascade ordering (escapes to style-engine cascade-aggregation).
- Editor-affordance authority for editor-only governance flags
  (lives in supports, not settings).
- Per-block runtime values (live in block instance attributes).

Settings is a closed declarative surface; everything outside it
is runtime / editor / instance authority. This batch confirms
the boundary.

## ANTIPATTERNS

- ❌ Adding `supports.border` expecting it to work. There is no
  block-author exposure layer for border; border is theme-only
  authority via settings + appearanceTools.
- ❌ Setting `useRootPaddingAwareAlignments: true` without
  declaring `styles.spacing.padding` values. The flag activates
  aware behavior but does not supply padding; padding values
  must come from styles.* declarations.
- ❌ Treating `settings.custom` as a preset registry. Custom
  values do NOT appear in editor UI (no preset picker); they
  emit as variables only. Use it for theme-author / styles.css
  consumption, NOT for end-user-facing token choices.
- ❌ Renaming `settings.custom.*` keys after publishing.
  Keys become CSS variable names; renaming breaks any CSS that
  references the variables.
- ❌ Setting `position.sticky: true` in settings and expecting
  per-block sticky to work without `supports.position.sticky:
  true` on the block. Both gates required.
- ❌ Using `lightbox` settings to control image lightbox CSS.
  Lightbox is a runtime feature toggle; CSS for the lightbox
  overlay lives in core / theme stylesheet, not in settings
  realization.
- ❌ Treating `border.{color,radius,style,width}` toggles as
  border value declarations. The toggles gate UI exposure; values
  live in styles.border.*. Setting the toggle does NOT supply
  border values.
- ❌ Conflating `settings.custom` with theme-level CSS variable
  declarations in a stylesheet. settings.custom emits with the
  --wp--custom-- namespace and is ingested by Gutenberg's
  variable graph; raw stylesheet variables bypass the system.
- ❌ Adding fields to `settings.*` expecting Gutenberg to honor
  arbitrary keys. Settings schema is closed (only documented
  fields are processed); custom is the documented extension
  point for arbitrary author-defined tokens.

## RELATED

- `theme-config.json-settings-color` — core registry substrate
  example. Border / dimensions are NOT registries; they govern
  exposure of capabilities whose values come from styles.* or
  per-instance attributes.
- `theme-config.json-settings-typography` — core computational
  substrate. Residuals are NOT computational; they're governance
  / bridges.
- `theme-config.json-settings-spacing` — core generative
  substrate. Residuals are NOT generative; the dimensions
  aspectRatios registry is small + non-algorithmic.
- `theme-config.json-styles-css` — realization-side escape hatch.
  Pairs with settings.custom to form the 3-layer escape pattern
  (settings.custom / styles.css / runtime synthesis).
- `block-authoring.supports.position` — counterpart per-block
  exposure for position.sticky. Both gates required for
  per-block sticky.
- `block-authoring.supports.dimensions` — counterpart per-block
  exposure for dimensions controls (aspectRatio / minHeight).
- `style-engine.css-variable-emission` — settings.custom emits
  the third CSS variable namespace (`--wp--custom--*`)
  documented there.
- `style-engine.generated-selectors` —
  useRootPaddingAwareAlignments activates the runtime topology
  synthesis path that generated-selectors documents.
- (planned) `style-engine.cascade-aggregation` — residual
  governance settings contribute scoped variable declarations
  and runtime layout flags that cascade-aggregation will
  formalize ordering for.

## NOTE — batch character

This is a batch chunk covering 6 fields with shared "residual
edge governance" character, NOT 6 separate ontology pivots.
Per-field SHAPE subsections give concrete schema; INVARIANTS
focus on the unifying patterns (bridges, escape hatches,
runtime-leaking declarations, namespace heterogeneity). When a
specific residual field needs deeper treatment in the future,
spike a dedicated chunk; this batch establishes the shared
ontology framing.
