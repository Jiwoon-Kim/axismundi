---
rule_id: block.supports.background
domain: block-authoring
topic: supports
field_cluster: capabilities
parent_rule: block.json-supports-field
wp_min: "6.5"
wp_recommended: ""
status: stable
language: json
sources:
  - url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/#background
    section: "Supports — background (Since WP 6.5)"
    captured: 2026-05-09
  - url: https://developer.wordpress.org/block-editor/reference-guides/theme-json-reference/theme-json-living/#appearancetools
    section: "theme.json — appearanceTools (background bundled here)"
    captured: 2026-05-09
related:
  - block.json-supports-field            # parent: supports as a mechanism
  - block.json-attributes-core           # background flags inject the style attribute
  - block.wrapper-attributes             # generated styles flow through useBlockProps
  - block.supports.color                 # adjacent surface family — note color.background ≠ this
  - block.supports.shadow                # purest surface treatment for contrast
  - theme-config.json-appearanceTools    # cross-context: background IS in this bundle (theme-mediated)
  - data-layer.media-attachments         # cross-context: backgroundImage stores attachment ID + media metadata
---

# RULE — `supports.background` capability flag

## WHEN

Defining a block that should expose **background image controls**
(image picker, focal point picker, size selector) in the block
inspector. This is the **bridge capability** between surface
treatment and container semantics — a visual treatment that also
carries layout-adjacent concerns (positioning, sizing).

**Important scoping (capability-name confusion warning):**

- `supports.background.*` (THIS rule) — controls **background IMAGE**
  (URL, focal point, cover/contain/fixed sizing).
- `supports.color.background` (separate rule) — controls **background
  COLOR** (solid color picker, preset palette).

These are **two distinct supports flags** despite both being
"background" in user-facing terms. The split is by
implementation/runtime semantics (image asset vs CSS color), not by
user-facing category.

**Family classification:** Surface treatment family with container
semantics overlap. Closest cousin in pure form: `shadow` (surface).
Closest cousin in container concerns: `dimensions` (sizing).

## SHAPE

```json
{
  "supports": {
    "background": {
      "backgroundImage": true,
      "backgroundSize":  true
    }
  }
}
```

### Sub-property matrix

| Sub-property | Type | Default | Effect |
|---|---|---|---|
| `backgroundImage` | `boolean` | `false` | Enables image picker (media library) UI. |
| `backgroundSize` | `boolean` | `false` | Enables FocalPointPicker + background-size selector (cover / contain / fixed). Implies position control. |

There is no documented `background: true` shorthand — use the object
form with explicit subproperties.

### `backgroundSize` covers both size AND position

A single subproperty (`backgroundSize: true`) enables BOTH the
FocalPointPicker (for `background-position`) AND the size selector.
There is no separate `backgroundPosition` subproperty; position
control rides along with size.

## REQUIRES

- **WP 6.5 or later** (per source: *"Since WordPress 6.5"*).
- Block MUST be registered server-side. Background controls and
  theme.json integration depend on PHP-side block awareness.
- Block's `Edit` and `save` (or PHP render) MUST spread
  `useBlockProps()` / `useBlockProps.save()` /
  `get_block_wrapper_attributes()` onto the outer element.
  (See `block.wrapper-attributes`.)
- For UI to render: theme MUST opt in. Source: *"the block editor
  will show UI controls for the user to set their values if the
  theme declares support."* In modern themes, opt-in is via either:
  - Explicit `theme.json` `settings.background.backgroundImage: true`
    / `settings.background.backgroundSize: true`, OR
  - **`appearanceTools: true`** (a theme.json shortcut that bundles
    background, border, color {link/heading/button/caption},
    dimensions, position.sticky, spacing, typography.lineHeight all
    at once — see Governance below).
- For `backgroundImage` to function, the user MUST have permission
  to access the media library; the picker uses the standard WP
  media uploader.

## INVARIANTS

### Editor effects

- A **Background** panel appears in the block inspector when any
  `background.*` subproperty is set to `true`, AND the theme has
  opted in (either explicitly or via appearanceTools).
- `backgroundImage: true` renders an **image picker** that opens
  the WordPress media library uploader — user selects an existing
  attachment or uploads new.
- `backgroundSize: true` adds a **FocalPointPicker** (drag-able
  point on a thumbnail of the selected image) AND a **size
  selector** with options including `cover`, `contain`, `fixed`.
- The FocalPointPicker only appears AFTER an image has been
  selected via the image picker. Without an image, position control
  has nothing to act on.

### Attribute effects

The `style` attribute is added to the block's schema, with values
stored under `style.background.*` (namespaced — like spacing /
dimensions / typography, NOT flat like shadow):

| Path | Type | Contents |
|---|---|---|
| `style.background.backgroundImage` | `object` | `{ url, id, source, title }` — see media-reference details below |
| `style.background.backgroundPosition` | `string` | CSS `background-position` value (e.g. `"50% 50%"`) — set by FocalPointPicker |
| `style.background.backgroundSize` | `string` | CSS `background-size` value (`"cover"`, `"contain"`, `"fixed"`, custom) |

**Media reference structure** for `backgroundImage`:

```json
{
  "url":    "https://example.com/wp-content/uploads/.../image.jpg",
  "id":     1234,
  "source": "file",
  "title":  "Image title"
}
```

| Field | Type | Notes |
|---|---|---|
| `url` | `string` | Direct URL to the image file. |
| `id` | `int` | Media library attachment ID. Couples to WP entity system. |
| `source` | `string` | Currently only `"file"` is documented. Reserved for future expansion. |
| `title` | `string` | Media attachment title (from media library metadata). |

The block can declare its own defaults:

```json
{
  "attributes": {
    "style": {
      "type": "object",
      "default": {
        "background": {
          "backgroundImage":    { "url": "IMAGE_URL" },
          "backgroundPosition": "50% 50%",
          "backgroundSize":     "cover"
        }
      }
    }
  }
}
```

### Wrapper effects

- Selected background image emits inline CSS on the wrapper:
  ```css
  background-image: url(...);
  background-position: 50% 50%;
  background-size: cover;
  ```
- All effects flow through `useBlockProps()` /
  `useBlockProps.save()` / `get_block_wrapper_attributes()` —
  required spreading per `block.wrapper-attributes`.
- ⚠ Source does not document whether class names (e.g.,
  `has-background-image`, `is-position-{slug}`) are emitted. Treat
  as primarily inline-style based pending source verification.
- The `id` field in the stored attribute serves as an entity
  reference — useful if downstream code needs to fetch the
  attachment record (e.g., for srcset, alt text). The wrapper
  itself only uses `url`.

### Serialization effects

Block delimiter stores the full media reference + position + size
under `style.background.*`:

```html
<!-- wp:my-plugin/foo {"style":{"background":{"backgroundImage":{"url":"IMAGE_URL","id":1234,"source":"file","title":"hero"},"backgroundPosition":"50% 50%","backgroundSize":"cover"}}} -->
```

The `id` field round-trips intact, enabling programmatic re-resolution
of the attachment (e.g., to refresh the URL if the file is moved).
Custom CSS values (raw strings without preset references) are also
supported; no `var:preset|background|...` form is documented (unlike
spacing / typography / shadow which have preset reference syntax).

### theme.json interaction

- Theme MUST opt in to background controls. Two paths:
  1. **Explicit:** `theme.json` `settings.background.backgroundImage: true`
     and/or `settings.background.backgroundSize: true`.
  2. **Bundled:** `theme.json` `settings.appearanceTools: true` —
     enables BOTH background subproperties along with several other
     capabilities (border, color {link/heading/button/caption},
     dimensions, position.sticky, spacing, typography.lineHeight).
- ⚠ Background presets (preset image library, preset focal points)
  are NOT documented in the captured source. Background appears to
  store concrete values per-block-instance; no theme-level preset
  resolution like spacing's `spacingSizes`.
- Cross-context: see `theme-config.json-appearanceTools` for the
  full bundle reference.

### General invariants

- **Capability-name split by implementation, not by user
  perception.** `supports.color.background` is for solid background
  COLORS; `supports.background.backgroundImage` is for background
  IMAGES. Same user-facing term, two different supports flags.
  This is evidence that Gutenberg's capability ontology is driven
  by runtime/implementation semantics, not by user-facing category.
- **First capability flag with media library coupling.** Stored
  values include attachment IDs and media metadata (title, source).
  This is the first supports flag that crosses into the
  data-layer / entity system.
- **Bridge between surface and container.** `backgroundImage` is a
  surface treatment (visual decoration), but `backgroundSize` /
  `backgroundPosition` (cover, contain, focal point) are
  container-positioning concerns. The single supports flag covers
  both ontology profiles — making it the "transition node" in the
  surface family.
- **Storage path is `style.background.*` (namespaced).** Consistent
  with spacing / dimensions / typography. Contrasts with shadow's
  flat `style.shadow` path.
- **Background is in the appearanceTools bundle** — theme-mediated
  exposure. Compare to shadow which is NOT bundled (block-owned).
  This is the first capability spike where appearanceTools becomes
  a primary opt-in path, not just an aside.
- **No preset reference syntax** documented for backgrounds. All
  values are concrete. This contrasts with spacing/typography/color/
  shadow which all use `var:preset|{capability}|{slug}` references.
- ⚠ **Whether background uses style engine class generation
  (`has-{slug}-background-image` / similar) is unverified.** Source
  describes inline-style emission; class emission unclear.

## ANTIPATTERNS

- ❌ Confusing `supports.background.*` with `supports.color.background`.
  Two separate flags with disjoint storage paths
  (`style.background.backgroundImage` vs
  `style.color.background`). Declaring one does not enable the other.
- ❌ Declaring `supports.background.backgroundSize: true` without
  `supports.background.backgroundImage: true`. The size/position
  controls work on the selected image; without image-picker support,
  there's no image to size or position.
- ❌ Hardcoding background image URL in the block's `save()` /
  render output. Bypasses the supports cascade — user can't change
  via picker, theme can't override.
- ❌ Declaring background support without theme opt-in (no explicit
  `settings.background` AND no `appearanceTools`). Same pattern as
  spacing: supports flag alone is insufficient.
- ❌ Storing background values at top-level `style.backgroundImage`
  instead of nested `style.background.backgroundImage`. Documented
  path is `style.background.*`; flat top-level paths break round-trip.
- ❌ Storing background image as just a URL string, omitting the
  attachment ID. The `id` field enables re-resolution if the file
  moves; URL-only loses this. Use the full media-reference object
  shape.
- ❌ Setting `backgroundPosition` without `backgroundImage`. Position
  has nothing to position; the value persists but produces no visible
  effect.
- ❌ Forgetting `useBlockProps` / `get_block_wrapper_attributes`.
  Same antipattern as all supports flags.
- ❌ Treating background as pure decoration. The `cover` / `contain`
  / focal-point semantics affect layout perception (especially on
  responsive viewports). Test across breakpoints.
- ❌ On WP < 6.5: declaring `supports.background.*`. Flag is
  parsed but the controls do not render — silent failure.

## RELATED

- `block.json-supports-field` — parent rule explaining the
  supports mechanism in general; this background flag is an instance.
- `block.json-attributes-core` — background flags inject the shared
  `style` attribute (under `style.background.*` namespace).
- `block.wrapper-attributes` — `useBlockProps()` /
  `get_block_wrapper_attributes()` is the receiver for inline
  background-image styles produced by this rule.
- `block.supports.color` — adjacent capability. CRITICAL contrast:
  `color.background` is solid background COLOR
  (`style.color.background`); this rule is background IMAGE
  (`style.background.backgroundImage`). Same user-facing term, two
  separate supports flags / two storage paths.
- `block.supports.shadow` — purest surface treatment in the family
  (no container concerns). Contrast: shadow is fully block-owned
  (NOT in appearanceTools); background is theme-mediated (IN
  appearanceTools).
- `theme-config.json-appearanceTools` (cross-context, planned) —
  the bundle that enables background plus 6 other capability areas.
  Background is one of the strongest cases for understanding
  appearanceTools as a "capability bundles" governance layer.
- `data-layer.media-attachments` (cross-context, planned) — the
  attachment ID stored in `backgroundImage.id` references the WP
  media entity system. Re-resolving URLs, fetching alt text, or
  generating srcset all flow through this entity.
