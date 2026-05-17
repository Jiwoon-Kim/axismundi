---
rule_id: block.supports.position
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "6.2"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#position
    section: "Supports — position (sticky) — Since WP 6.2"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # position values inject style attribute
  - block.wrapper-attributes             # generated styles flow through useBlockProps
  - block.supports.layout                # adjacent composition concern; position works at root, layout works inside
  - block.supports.spacing               # container-semantic neighbor
  - theme-config.json-position-settings  # cross-context: settings.position (gated UI)
  - theme-config.json-appearanceTools    # cross-context: position.sticky IS in this bundle
---

# RULE — `supports.position` capability flag (micro-layout)

## WHEN

Defining a block that should expose **position** controls (currently
only `sticky`) in the block inspector. Use when the block needs to
remain visible during scroll within its parent container — typically
for site headers, contextual navigation, or persistent UI within
templates.

This is a **micro-layout capability**: minimal exposure surface
(1 boolean sub-property) but runtime behavior beyond simple CSS
emission — `position: sticky` couples to browser scroll context and
parent layout flow, not just to a wrapper class. Conceptually a
miniature version of the layout subsystem (governance over scroll
behavior at the block level).

## SHAPE

```json
{
  "supports": {
    "position": {
      "sticky": true
    }
  }
}
```

### Sub-property matrix

| Sub-property | Type | Default | Since | Notes |
|---|---|---|---|---|
| `sticky` | `boolean` | `false` | WP 6.2 | Adds sticky-position toggle to inspector. Currently the only documented position sub-property. |

The `type: "Object"` form on `position` (rather than a flat boolean)
implies future extensibility — other position types may be added
later. Currently `sticky` is the sole implemented sub-property.

### Stored value example

```json
{
  "attributes": {
    "style": {
      "position": {
        "type": "sticky",
        "top":  "0px"
      }
    }
  }
}
```

The stored shape includes:
- `type` — the position kind (currently always `"sticky"` when set).
- `top` — the offset applied to the sticky behavior (CSS `top` value).

⚠ Whether other CSS positioning offsets (`right`, `bottom`, `left`)
are also stored when applicable is not explicitly documented in the
captured source. For sticky specifically, `top` is the canonical
anchor.

## REQUIRES

- **WP 6.2 or later** (per source: *"Since WordPress 6.2"*).
- Block MUST be registered server-side. Position controls and
  theme.json integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element.
- For UI to render: theme MUST opt in via either:
  - `theme.json` `settings.position.sticky: true` (explicit), OR
  - `theme.json` `settings.appearanceTools: true` (bundle — position.sticky
    IS in the appearanceTools bundle, alongside background, border,
    color, dimensions, spacing, typography.lineHeight).
- The block declaring `supports.position.sticky` MUST be inserted at
  the **root level of the document** for the sticky behavior to
  function. Per source: *"sticky position controls are currently
  only available for blocks set at the root level of the document."*
  Nested blocks (inside InnerBlocks of a parent) cannot use sticky.

## INVARIANTS

### Editor effects

- A **Position** panel appears in the block inspector when
  `supports.position.sticky: true` is declared, AND the theme has
  opted in (explicit settings or appearanceTools bundle).
- The panel exposes a **sticky toggle** plus a numeric input for
  the `top` offset value (e.g., `0px`).
- ⚠ Whether the position panel shows or hides based on the
  block's current insertion location (root vs. nested) is not
  documented in the captured source. Treat as: the panel may
  appear regardless of nesting depth, but the runtime sticky
  behavior only fires for root-level blocks. Verify per WP version.

### Attribute effects

- The `style` attribute is added to the block's schema, with values
  stored under `style.position.*` (namespaced — consistent with
  spacing / dimensions / typography, NOT flat like shadow):

| Path | Type | Contents |
|---|---|---|
| `style.position.type` | `string` | The position kind, currently always `"sticky"`. |
| `style.position.top` | `string` | CSS `top` value (e.g., `"0px"`, `"1rem"`). |

The `type` field's distinct existence (rather than implicit by the
sub-property declaration) suggests the storage schema is designed
for future position kinds.

### Wrapper effects

- Sticky enables emit inline CSS on the wrapper:
  ```css
  position: sticky;
  top: 0px;
  ```
- ⚠ Whether the wrapper also receives a class name (e.g.,
  `is-position-sticky`) is not enumerated in the captured source.
  Treat as primarily inline-style based pending source verification.
- All effects flow through `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` —
  required spreading per `block.wrapper-attributes`.
- The actual sticky behavior is **browser-runtime**: the block
  sticks to its most immediate scrollable parent when the user
  scrolls the page. WordPress emits the CSS; the browser's
  scroll engine produces the visible effect.

### Serialization effects

Block delimiter stores position config under `style.position.*`:

```html
<!-- wp:my-plugin/site-header {"style":{"position":{"type":"sticky","top":"0px"}}} -->
```

No preset reference syntax is documented for position — values are
concrete strings.

### theme.json interaction

- Theme opt-in via either:
  - **Explicit:** `theme.json` `settings.position.sticky: true` —
    enables the position panel for blocks declaring the supports flag.
  - **Bundled:** `theme.json` `settings.appearanceTools: true` —
    enables position.sticky alongside other appearanceTools-bundled
    capabilities (background, border, color {link/heading/button/caption},
    dimensions, spacing, typography.lineHeight).
- ⚠ Theme.json `settings.position` reference (other potential
  settings beyond `sticky`) is sparse in current source captures.
- Cross-context: see `theme-config.json-appearanceTools` for the
  full bundle mechanism — position.sticky is one of 7 capability
  categories enabled by the bundle.

### General invariants

- **Root-level constraint** is the defining structural property of
  this capability. Source explicitly: *"sticky position controls
  are currently only available for blocks set at the root level of
  the document. Setting a block to the sticky position will stick
  the block to its most immediate parent when the user scrolls
  the page."*
- **"Most immediate parent"** is the scroll behavior anchor — the
  closest scrollable ancestor in the DOM, typically the document
  viewport. Behavior at nested-block contexts is untested per
  source.
- **Capability micro-layout family.** Position joins layout as a
  capability where the runtime behavior is more complex than the
  exposure layer suggests:
  - layout: 10 sub-properties exposing 4+ layout types' editor
    controls + composition runtime
  - position: 1 sub-property exposing 1 position type + browser
    scroll-context runtime
  Both are "execution-heavier than exposure suggests".
- **Asymmetric exposure: 1 sub-property documented, but storage
  schema implies more.** The `type` field carries the position kind,
  suggesting future extensibility. New position types (e.g., fixed,
  absolute) could be added without restructuring the schema.
- **In appearanceTools bundle.** Like background, position is
  theme-mediated — the appearanceTools shortcut covers it. Compare
  to shadow which is NOT in the bundle. Position is a "block-author
  declares + theme can bulk-enable" pattern.
- ⚠ **Block context dependency unclear.** What happens if a block
  declaring sticky support is placed inside an InnerBlocks parent?
  Source says sticky only works at root level. Whether the panel
  hides, the toggle is greyed out, or the value silently has no
  effect is not specified. Verify per implementation.

## ANTIPATTERNS

- ❌ Declaring `supports.position.sticky: true` on WP < 6.2. Flag
  is parsed but the controls do not render — silent failure.
- ❌ Expecting sticky to work for nested blocks. Per source: root
  level only. A sticky child of a Group/Columns/etc. parent will
  not behave as sticky.
- ❌ Hardcoding `position: sticky` in the block's `save()` / render
  output. Bypasses the supports cascade — user can't change via
  picker, and the editor's user-controlled offset value has no
  effect.
- ❌ Storing position values at `style.sticky` or top-level
  `style.top` instead of nested `style.position.{type,top}`.
  Documented path is `style.position.*`.
- ❌ Setting `style.position.type` to a value other than `"sticky"`.
  Currently only `sticky` is documented. Other CSS position values
  (relative, absolute, fixed) are not exposed via this supports flag
  and would not produce expected runtime behavior.
- ❌ Forgetting theme opt-in. Same pattern as background / spacing:
  supports flag alone is insufficient; theme must declare
  `settings.position.sticky` or rely on `appearanceTools`.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags.
- ❌ Treating position as a layout substitute. Position controls
  scroll behavior of an individual block; layout controls how a
  container's children flow. They are complementary, not
  interchangeable. A sticky header inside a layout-managed flow is
  the typical combined pattern.
- ❌ Relying on sticky behavior in iframes / scroll-locked
  containers. Sticky depends on a scrollable ancestor; if the
  block's effective parent is non-scrollable, the sticky behavior
  has nothing to anchor to.

## RELATED

- `block.json-supports-field` — parent rule explaining the supports
  mechanism in general; this position flag is an instance.
- `block.json-attributes-core` — position values inject the shared
  `style` attribute (under `style.position.*` namespace).
- `block.wrapper-attributes` — `useBlockProps()` /
  `get_block_wrapper_attributes()` is the receiver for inline
  position styles produced by this rule.
- `block.supports.layout` — adjacent composition / runtime
  capability. Position works at block ROOT level (sticking to
  page viewport); layout works INSIDE container blocks
  (orchestrating children). Together they cover the
  document-flow-vs-individual-block-positioning split.
- `block.supports.spacing` — adjacent container-semantic
  capability. Position influences scroll behavior; spacing
  influences static layout positioning.
- `theme-config.json-position-settings` (cross-context, planned) —
  full theme.json `settings.position` reference. Currently sparse;
  primarily `sticky` boolean gating.
- `theme-config.json-appearanceTools` (cross-context, planned) —
  the bundle that enables position.sticky alongside 6 other
  capability areas. Position is a clear case of theme-mediated
  block-author capability.
