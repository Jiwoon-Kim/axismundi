---
rule_id: block.supports.shadow
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "6.5"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#shadow
    section: "Supports — shadow (Since WP 6.5)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#shadow
    section: "theme.json — settings.shadow (defaultPresets + presets)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # shadow flag injects the style attribute
  - block.wrapper-attributes             # generated style flows through useBlockProps
  - block.supports.color                 # adjacent surface-treatment capability (closest cousin)
  - theme-config.json-shadow-settings    # cross-context: settings.shadow (presets + defaultPresets)
  - theme-config.json-appearanceTools    # cross-context: NOT in appearanceTools bundle (unlike border)
---

# RULE — `supports.shadow` capability flag

## WHEN

Defining a block that should expose a **box-shadow picker** in the
inspector / toolbar, allowing the user to select preset shadows or
(potentially) custom values. Surface-treatment family — affects the
block's outer wrapper visual presentation, not its content or layout.

This is the **simplest capability flag in the supports family**:
boolean only (no sub-properties), single storage path, single emission
target. Use to opt blocks into shadow controls without authoring the
picker UI yourself.

**Family classification (2026-05-09 ontology):**

| Family | Members |
|---|---|
| Content | color, typography |
| Container | spacing, dimensions |
| **Surface treatment** | **shadow**, background, filter |

## SHAPE

```json
{
  "supports": {
    "shadow": true
  }
}
```

| Property | Value |
|---|---|
| Type | `boolean` |
| Default | `false` |
| Sub-properties | **none** (flat boolean — only supports flag in the family without an object form) |

No object form is documented; no sub-property selection is available.
The flag is binary: shadow controls appear, or they don't.

## REQUIRES

- **WP 6.5 or later** (per source: *"Since WordPress 6.5"*).
- Block MUST be registered server-side. Shadow controls and
  theme.json preset integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element.
  (See `block.wrapper-attributes`.)
- For preset values to appear in the picker, the active theme MUST
  provide shadow presets via `theme.json` `settings.shadow.presets`,
  OR rely on the default presets that core ships
  (`settings.shadow.defaultPresets: true` is the default).

## INVARIANTS

### Editor effects

- A **box-shadow picker** appears in the block toolbar / inspector
  when `supports.shadow: true` is declared. The picker shows shadow
  presets sourced from `theme.json` `settings.shadow.presets`.
- Default core shadow presets are also shown unless the theme sets
  `settings.shadow.defaultPresets: false`.
- ⚠ Whether a CUSTOM (non-preset) shadow input appears alongside the
  picker is not documented in the supports reference. The source
  describes preset selection but does not state whether arbitrary
  CSS box-shadow values can be entered. Verify per WP version.

### Attribute effects

- The `style` attribute is added to the block's schema.
- Selected shadow value is stored at `style.shadow` (not
  `style.shadow.shadow` — direct path).

```json
{
  "attributes": {
    "style": {
      "type": "object",
      "default": {
        "shadow": "var:preset|shadow|deep"
      }
    }
  }
}
```

The `style` attribute is **shared** across capability flags; shadow
adds the **flat `style.shadow` key** (NOT a nested namespace like
`style.spacing.padding` or `style.dimensions.height`).

This flat-path placement is **structurally distinct** from the
namespaced approach used by spacing / dimensions / typography (which
all use `style.{capability}.{subproperty}`). Treat as an exception.

### Wrapper effects

- Selected shadow emits as inline CSS on the wrapper:
  `box-shadow: var(--wp--preset--shadow--{slug})` for preset slugs,
  or the raw CSS value for custom shadows (if custom is supported).
- Custom CSS variable reference: `--wp--preset--shadow--{slug}`
  (matches the standard preset → CSS-var pattern).
- ⚠ Source does not document whether a `has-{slug}-shadow` class is
  emitted (in contrast to `has-{slug}-color` for color presets).
  Treat as primarily inline-style based pending source verification.
- All effects flow through `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` —
  required spreading per `block.wrapper-attributes`.

### Serialization effects

Block delimiter stores the value at `style.shadow`:

```html
<!-- wp:my-plugin/foo {"style":{"shadow":"var:preset|shadow|deep"}} -->
```

Preset references serialize as the `var:preset|shadow|{slug}` string
form. The preset slug round-trips intact; resolution to actual
`box-shadow` CSS happens at render time via the style engine.

### theme.json interaction

- Shadow presets are sourced from `theme.json`
  `settings.shadow.presets`, an array of `{ name, slug, shadow }`
  objects.
- `settings.shadow.defaultPresets` (boolean, default `true`) controls
  whether core's default shadow presets are also shown. Setting to
  `false` removes them from the picker.
- **shadow is NOT included in the `appearanceTools` bundle.** The
  appearanceTools shortcut enables UI for background / border / color
  (link/heading/button/caption) / dimensions / position.sticky /
  spacing / typography.lineHeight — but NOT shadow. Shadow is a
  standalone capability with its own theme.json settings group.
- ⚠ Whether shadow has block-level styles cascade (e.g.,
  `theme.json` `styles.blocks.{name}.shadow`) is not surfaced in
  the captured source. Likely follows the standard cascade pattern
  shared with other styles.

### General invariants

- **Simplest exposure surface in the supports family.** Boolean
  type, no sub-properties, single storage path. The pattern
  "simple exposure + preset-driven execution" is exemplified by
  shadow more cleanly than any other supports flag captured so far.
- **Storage path is FLAT (`style.shadow`), not namespaced.** This
  contrasts with spacing (`style.spacing.*`), dimensions
  (`style.dimensions.*`), typography (`style.typography.*`). Authors
  used to the namespaced pattern must remember this exception.
- The `style` attribute is shared across capability flags. Defining
  `attributes.style.default` for shadow may collide with concurrent
  color / spacing / typography defaults — use a single default
  object that includes all needed paths.
- **Shadow is not in appearanceTools.** Cannot be bulk-enabled via
  the appearanceTools convenience setting. Theme must explicitly
  provide presets (or rely on defaults) and block must explicitly
  declare `supports.shadow: true`.
- Per family classification: shadow is **surface treatment** —
  closest cousin is color (background/text), not spacing/dimensions
  (container) and not typography (content). The three families have
  distinct ontology profiles.

## ANTIPATTERNS

- ❌ Declaring `supports.shadow: true` on WP < 6.5. Flag is parsed
  but the picker doesn't render — silent failure. Feature-detect
  or set `wp_min: "6.5"`.
- ❌ Storing shadow value at `style.shadow.shadow` (nested form).
  The documented path is the flat `style.shadow` — nesting breaks
  the round-trip.
- ❌ Hardcoding `box-shadow` in the block's `save()` / render
  output. Bypasses the supports cascade — user can't change via
  inspector, theme presets have no effect.
- ❌ Expecting shadow to be enabled via `appearanceTools: true`
  in theme.json. Shadow is NOT in the appearanceTools bundle.
  Supports flag must be declared per-block; theme presets must be
  defined explicitly.
- ❌ Defining `supports: { shadow: { somesubprop: true } }` (object
  form). No object form documented. Use the flat boolean.
- ❌ Removing `defaultPresets` (`settings.shadow.defaultPresets: false`)
  AND not providing custom `presets`. Picker becomes empty — control
  shows but offers no choices.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags.

## RELATED

- `block.json-supports-field` — parent rule explaining the supports
  mechanism in general; this shadow flag is an instance.
- `block.json-attributes-core` — shadow flag injects the shared
  `style` attribute (with the flat `style.shadow` path).
- `block.wrapper-attributes` — `useBlockProps()` /
  `get_block_wrapper_attributes()` is the receiver for the
  generated `box-shadow` inline style.
- `block.supports.color` — closest cousin in the surface-treatment
  family. Both inject preset-resolved CSS values onto the wrapper.
  Differences: color has many sub-properties (background / text /
  link / etc.) and namespaced storage (`style.color.*`); shadow is
  flat boolean with single `style.shadow` path.
- `theme-config.json-shadow-settings` (cross-context, planned) —
  full theme.json `settings.shadow` reference: `defaultPresets`,
  `presets` array shape (`{ name, slug, shadow }`).
- `theme-config.json-appearanceTools` (cross-context, planned) —
  the bundle that DOES NOT include shadow. Useful contrast for
  understanding why shadow needs explicit per-block opt-in
  (unlike border which lives entirely under appearanceTools'
  authority).
