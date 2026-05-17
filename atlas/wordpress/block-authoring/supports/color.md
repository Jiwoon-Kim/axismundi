---
rule_id: block.supports.color
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "verification-needed"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#color
    section: "Supports — color (and sub-properties)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes                # color flags mutate the attributes schema
  - block.wrapper-attributes             # generated classes/styles flow here
  - block.supports.background            # adjacent flag (separate background image API)
  - block.supports.filter.duotone        # replaces deprecated color.__experimentalDuotone
  - theme-config.editor-color-palette    # cross-context: where presets are sourced
  - theme-config.json-presets            # cross-context: theme.json preset semantics
---

# RULE — `supports.color` capability flag

## WHEN

Defining a block that should expose color controls (background, text,
link, gradient, button colors, heading colors) in the block inspector,
without writing the controls or persistence logic yourself.

## SHAPE

```json
{
  "supports": {
    "color": true
  }
}
```

`true` shorthand enables `background` + `text` defaults. Object form
selects sub-properties:

```json
{
  "supports": {
    "color": {
      "background": true,
      "text": true,
      "link": true,
      "gradients": true,
      "heading": true,
      "button": true,
      "enableContrastChecker": true
    }
  }
}
```

### Sub-property matrix

| Sub-property | Type | Default | Since | Effect |
|---|---|---|---|---|
| `background` | `boolean` | `true` | original | Solid background color UI + serialization. |
| `text` | `boolean` | `true` | original | Text color UI + serialization. |
| `link` | `boolean` | `false` | original | Link color UI (incl. `:hover`) + serialization to `style.elements.link.*`. |
| `gradients` | `boolean` | `false` | original | Gradient background UI + serialization. |
| `button` | `boolean` | `false` | WP 6.5 | Button text + background colors via `style.elements.button.*`. |
| `heading` | `boolean` | `false` | WP 6.5 | Heading text + background colors via `style.elements.heading.*`. |
| `enableContrastChecker` | `boolean` | `true` | WP 6.5 | Toggles editor-side WCAG contrast warning UI. Affects editor only — no serialization. |

### Disabling defaults

When the parent `color` is an object, `background` / `text` defaults
remain `true`. Disable explicitly:

```json
{
  "supports": {
    "color": {
      "background": false,
      "gradients": true
    }
  }
}
```

## REQUIRES

- Block MUST be registered server-side. Color controls and theme.json
  preset integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element. Without
  this, controls render but generated classes/styles never reach the
  block markup.
- For preset-based color values to appear in the picker, the active
  theme MUST provide a palette via either:
  - `theme.json` `settings.color.palette` (preferred, modern), OR
  - `add_theme_support( 'editor-color-palette', [...] )` (legacy
    `theme_support` API).
- For gradient presets, theme MUST provide
  `settings.color.gradients` (theme.json) or
  `add_theme_support( 'editor-gradient-presets', [...] )`.

## INVARIANTS

### Editor effects

- A **Color** panel appears in the block inspector for any sub-property
  set to `true`. Sub-controls render based on enabled sub-properties:
  Background / Text / Link / Gradient / Button / Heading.
- **Contrast checker** widget displays in the inspector when
  `background` and `text` are both enabled (or `color: true`), unless
  `enableContrastChecker: false` overrides it.
- Each preset color palette displays the slugs declared in
  `theme.json` `settings.color.palette` (or legacy theme_support).
  Custom color picker availability depends on `settings.color.custom`
  in theme.json (default `true`).

### Attribute effects

Each sub-property extends the block's attribute schema differently:

| Sub-property | Attributes added |
|---|---|
| `background` | `backgroundColor` (string, preset slug) + `style.color.background` (custom hex) |
| `text` | `textColor` (string, preset slug) + `style.color.text` (custom hex) |
| `gradients` | `gradient` (string, preset slug) + `style.color.gradient` (custom) |
| `link` | `style.elements.link.color.text` + `style.elements.link.:hover.color.text` |
| `button` | `style.elements.button.color.text` + `style.elements.button.color.background` |
| `heading` | `style.elements.heading.color.text` + `style.elements.heading.color.background` |
| `enableContrastChecker` | (no attribute change — editor-only UI) |

The block can declare its own defaults for these injected attributes
in its `attributes` field (e.g.,
`{ "attributes": { "backgroundColor": { "type": "string", "default": "primary" } } }`).

### Wrapper effects

- Preset selection emits a class on the wrapper:
  - `has-{slug}-background-color` for `backgroundColor`
  - `has-{slug}-color` for `textColor`
  - `has-{slug}-gradient-background` for `gradient`
- A general flag class (`has-background`, `has-text-color`,
  `has-link-color`) is also emitted to indicate "some color is set"
  — used by core stylesheets as scoping.
- Custom (non-preset) colors emit inline `style="color: ...; background-color: ..."`
  on the wrapper instead of preset classes.
- For `button` / `heading` / `link`, classes/styles target nested
  elements via the `style.elements.{element}.*` path; serialization
  produces inline styles on the matching element selectors.

### Serialization effects

Block delimiter stores chosen attributes as JSON:

```html
<!-- wp:my-plugin/notice {"backgroundColor":"contrast","textColor":"accent-4"} -->
<p class="wp-block-my-plugin-notice has-accent-4-color has-contrast-background-color has-text-color has-background">…</p>
<!-- /wp:my-plugin/notice -->
```

Custom (non-preset) values serialize under the `style` attribute:

```html
<!-- wp:my-plugin/notice {"style":{"color":{"background":"#aabbcc"}}} -->
```

### theme.json interaction

- Preset slugs in attributes (`backgroundColor: "contrast"`) resolve
  to the theme.json palette entry's CSS custom property
  `--wp--preset--color--contrast`.
- The `style.color.*` path maps to theme.json's `styles.color.*`
  cascade — block-level styles override theme-level which override
  core defaults.
- Gradient presets map similarly via `--wp--preset--gradient--{slug}`.
- For `button` / `heading` / `link`, theme.json `styles.elements.*`
  provides the cascade origin; block-level overrides via
  `style.elements.*` win at the block instance level.

### General invariants

- `color: true` shorthand is functionally equivalent to
  `color: { background: true, text: true }` (the defaults). It does
  NOT enable link/gradient/button/heading.
- `__experimentalDuotone` is **deprecated since WP 6.3** — moved to
  `filter.duotone`. Do not use the experimental form in new code.
- ⚠ **Per-sub-property `wp_min` mixed.** background/text/link/gradients
  are original Block API; button/heading/enableContrastChecker are
  WP 6.5+. A block declaring all of them needs `wp_min: 6.5`. The
  field-level `wp_min` is `verification-needed` because the parent
  `color` flag itself predates per-sub-flag versioning.

## ANTIPATTERNS

- ❌ Declaring `color` support without ensuring the theme provides a
  palette. Controls appear but the picker shows only "Custom Color"
  fallback (or nothing if `settings.color.custom: false`).
- ❌ Spreading `useBlockProps` on a non-outermost element. Generated
  color classes land on the wrong element — visible in markup but
  not in CSS cascade scope.
- ❌ Using `__experimentalDuotone` (deprecated since WP 6.3). Migrate
  to `filter.duotone`.
- ❌ Hardcoding hex colors in the block's `save` / render output.
  Bypasses the supports cascade — user can't change the color via
  the inspector and theme.json overrides have no effect.
- ❌ Setting `gradients: true` while keeping `background: true` and
  `text: true`. Most blocks should pick gradient OR solid background,
  not both. Disable defaults explicitly when enabling gradients.
- ❌ Forgetting that `enableContrastChecker` only affects editor UI —
  it is **not** a runtime accessibility enforcement. Failing the
  contrast check is a warning; the block still saves.
- ❌ Declaring `color.link: true` and expecting it to style `<a>` tags
  inside the block automatically. The `style.elements.link.*` path
  generates inline styles via core's style engine — works for known
  block elements (paragraph, heading) but not for arbitrary nested
  HTML the block author writes.

## RELATED

- `block.json-supports-field` — parent rule explaining the supports
  mechanism in general; this color flag is an instance of that
  pattern.
- `block.json-attributes` — color flags inject specific attributes
  (`backgroundColor`, `textColor`, `style`, `gradient`) into the
  block's schema; understand the attribute model to handle defaults.
- `block.wrapper-attributes` — `useBlockProps()` /
  `get_block_wrapper_attributes()` is the receiver for all
  generated classes/inline styles produced by this rule.
- `block.supports.background` — separate flag for **background image**
  (since WP 6.4); not to be confused with `color.background` which
  is solid color only.
- `block.supports.filter.duotone` — replaces deprecated
  `color.__experimentalDuotone`.
- `block.supports.typography` — sibling capability with a similar
  cascade pattern (preset/custom + style cascade).
- `theme-config.editor-color-palette` (cross-context) — the
  theme_support / theme.json source of preset color options.
- `theme-config.json-presets` (cross-context) — semantics of preset
  resolution to `var(--wp--preset--color--{slug})` CSS custom
  properties.
