# SVG Icon Policy — v3.4.2

> Bucket: F (plugin territory), with D-bucket support surfaces in theme
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §4 (plugin should host editor UI), §6 (federation portability)

> The contract for SVG icon usage in Axismundi. SVG is the icon track
> for WordPress-native editor surfaces, social icons, brand glyphs,
> content-portable icons, and federation-safe content. This document
> specifies when SVG is **required** (not just permitted), which
> WordPress integration points consume SVG, and the sanitization
> baseline that plugin authors must respect.

## Scope statement

SVG icons are the **WordPress ecosystem and content interoperability
engine**. The icon font track (see `ICON-FONT-POLICY.md`) handles M3
chrome glyphs. SVG handles everything WordPress already does in SVG
and everything that must survive serialization.

### Required (not optional) SVG cases

| Case | Why SVG is required |
|---|---|
| **WordPress block editor icon** for a custom block or block variation | `@wordpress/icons` accepts SVG only, not icon-font ligatures. New icons added to that library go to `src/library/` as SVG files. Plugin authors registering custom blocks pass `<SVG>` JSX to the block's `icon` field. |
| **Social Icons block (`core/social-link` variations)** | WP 6.9 documented `core/social-link` block variation registration accepts `icon: <SVG><Path/></SVG>` JSX. Icon font ligatures are not a valid input. |
| **Brand logos** (Mastodon, GitHub, WordPress, Apple, etc.) | Brand glyphs are not in the Material Symbols set. They have legal-design requirements (preserved shape, no axis-distortion). Icon fonts cannot represent them. |
| **Icon Block plugin (NickDiego)** | Standalone content block that inserts user-chosen SVG into post body. The block stores the SVG markup, not an icon name. |
| **`core/post-author-name` / author-avatar style icons** | When the WordPress core block renders an icon as part of the content, it ships SVG inline. The theme should not override these to use the icon font. |
| **Content-portable icons** (icon block, icon variations) | Anything inside `post_content` must remain portable when the post is serialized to ActivityPub / RSS / excerpt — see charter §6. |
| **Federation content** | ActivityPub federation strips theme JS and may not load the icon font. Glyphs intended to survive federation must be SVG (or text, or `<image>`). |
| **Email rendering** | Future email-template / share-card use cases must use SVG inline; icon fonts don't render reliably in email clients. |

### Permitted (but not required) SVG cases

| Case | Why SVG is permitted |
|---|---|
| Chrome glyph for which Material Symbols does not have an exact match | The icon font has ~2,500 glyphs but is not exhaustive. Filling gaps with SVG in the theme is acceptable as long as the SVG follows the chrome scope and accessibility rules below. |
| Inline-SVG fallback for chrome glyph when the icon font fails to load | Per `ICON-FONT-POLICY.md §Fallback policy` — optional resilience layer. |
| Decorative illustrations / hero graphics | SVG is the appropriate format for vector illustrations regardless of icon system. |

### Forbidden SVG cases

| Case | Why forbidden |
|---|---|
| Embedding SVG with `<script>` tags inside post content | XSS vector. WordPress core's media upload policy disallows scripts in SVG; plugins MUST replicate this for any SVG they ingest. |
| Embedding SVG with `<foreignObject>` containing HTML inside federated content | Federated consumers may evaluate the embedded HTML in a context where the original CSS / JS is missing, producing unpredictable rendering. |
| Inline-SVG inside `.prose` for what should be a Material Symbols glyph | Chrome glyphs go in chrome (`ICON-FONT-POLICY.md`). Inline-SVG in prose for chrome purposes blurs the layer separation. |

## WordPress integration points

This section is the contract a future `axismundi-icons` plugin must
honor. The theme does not implement any of these directly; this is
the plugin's surface area.

### 1. `@wordpress/icons` parity

```jsx
// Plugin-side: register a custom block icon
import { Icon } from '@wordpress/icons';

const myCustomBlockIcon = (
  <SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
    <Path d="..." />
  </SVG>
);

registerBlockType('axismundi/example', {
  title: 'Example',
  icon: myCustomBlockIcon,
  // ...
});
```

The icon registry must accept SVG JSX (the WordPress-canonical input
shape) — not icon name strings. A "convenience" path that accepts
Material Symbols ligature strings and renders SVG internally is
acceptable but must NOT be the only path.

### 2. `core/social-link` variation registration (WP 6.9+)

```jsx
// Plugin-side: register a custom social icon
wp.blocks.registerBlockVariation('core/social-link', {
  name: 'mastodon',
  attributes: { service: 'mastodon' },
  icon: (
    <SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
      <Path d="..." />
    </SVG>
  ),
  isActive: ['service'],
});
```

This is how WP 6.9 expects custom social platforms (Mastodon,
Bluesky, etc.) to be added. The icon must be a brand-accurate SVG;
Material Symbols has no Mastodon glyph.

### 3. Icon Block parity

If the project ships a content-icon block (own implementation or
shim over the existing Icon Block plugin), the block stores the
SVG markup as a block attribute. The frontend renders the stored
SVG; no icon font lookup happens for content-icon blocks.

### 4. theme.json icons (template part icons, navigation icons)

`theme.json` does not currently accept icon font references for
template-part or navigation icons. SVG paths via `core/site-logo`,
custom `core/navigation` markers, etc., remain SVG.

## Sanitization baseline

Plugin authors who ingest SVG (e.g. picker upload of a custom SVG,
or paste-from-clipboard SVG into Icon Block) MUST sanitize before
storing. Minimum policy:

| Element / attribute | Action |
|---|---|
| `<script>` | Strip |
| `<foreignObject>` | Strip (unless explicitly allowed by site admin) |
| `<style>` containing `@import` | Strip the `@import` directive |
| `on*` event attributes (`onclick`, `onload`, etc.) | Strip |
| `href` / `xlink:href` with `javascript:` scheme | Strip |
| `href` / `xlink:href` with `data:` scheme | Strip unless `data:image/*` |
| External font references inside SVG | Strip |
| External image references (`<image href="https://...">`) | Strip or rewrite to local |

A practical implementation can reuse one of the maintained SVG
sanitizer libraries (e.g. svg-sanitizer for PHP) rather than rolling
its own.

## Accessibility for SVG icons

The accessibility pattern depends on whether the SVG is *decorative*
or *semantic*. Decorative SVG is one whose meaning is fully captured
by a sibling label or by the parent control's `aria-label` — the
same situation as icon font glyphs.

### Decorative SVG (icon inside a labeled control)

```html
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="다운로드">
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path d="M5 20h14v-2H5v2zm7-18L5.33 8.67l1.41 1.41L11 5.83V18h2V5.83l4.26 4.25 1.41-1.41L12 2z" fill="currentColor"/>
  </svg>
</button>
```

`aria-hidden="true"` + `focusable="false"` together. `focusable` is
SVG2-specific; IE / older Edge needed it to prevent focus landing on
the SVG itself. Modern browsers don't, but setting it costs nothing.

### Semantic SVG (icon carries the meaning)

```html
<!-- Status banner where the icon IS the indicator -->
<div class="ax-banner">
  <svg viewBox="0 0 24 24" role="img" aria-label="경고">
    <title>경고</title>
    <path d="..." fill="currentColor"/>
  </svg>
  <span class="t-body-medium">서버 오류가 발생했습니다.</span>
</div>
```

`role="img"` + `aria-label` makes the SVG itself an accessible image.
`<title>` inside the SVG provides redundant text for assistive tech
that doesn't read `aria-label` on SVG (rare in 2026 but harmless).

### Brand / logo SVG

```html
<!-- Brand logo in footer -->
<a href="https://example.org">
  <svg viewBox="0 0 100 24" role="img" aria-label="Axismundi">
    <title>Axismundi</title>
    <path d="..." fill="currentColor"/>
  </svg>
</a>
```

Same pattern as semantic SVG — brand names are content, not chrome.

## Color and theme adaptation

SVG icons should use `fill="currentColor"` (or `stroke="currentColor"`)
so they pick up the surrounding text color and adapt automatically to
light / dark theme via the theme-state cascade (charter §2 — theme
state cascades through tokens).

```html
<!-- Good — adapts to theme via currentColor -->
<svg viewBox="0 0 24 24"><path fill="currentColor" d="..."/></svg>

<!-- Avoid — hardcoded color won't follow theme -->
<svg viewBox="0 0 24 24"><path fill="#1c1b1f" d="..."/></svg>
```

For brand SVGs that have a fixed brand color (e.g. Mastodon purple,
GitHub black), hardcoded colors are correct — brands are content, and
content does not change with theme state. Charter §2 is for chrome /
UI state, not for brand identity.

## Brand specimen references in the styleguide

The styleguide may render brand SVGs as **reference specimens** to
demonstrate the SVG track's interoperability scope (e.g. showing
`@wordpress/icons` or `core/social-link` integration shape). These are
NOT theme-shipped icon primitives. Required handling:

| Requirement | Detail |
|---|---|
| **Official source** | Fetch from the brand's official channel at the time the specimen is added. Do not use stale copies. Seed files (if mirrored locally) go in `compare/brand-assets-research/` with attribution README. |
| **Storage scope** | The seed file lives outside theme/styleguide. The render-time inline form lives only in the lab pattern HTML or styleguide HTML — never in `stylesheets/`, never in published assets, never in a content block. |
| **Color** | Apply `currentColor` normalization for monochrome silhouettes (W mark, similar single-path glyphs); follow theme palette via the parent's `color` token. Multi-color brand assets are not currentColor-compatible — display as-authored and note this in the caption. |
| **Caption (required)** | Trademark notice attributing the mark to its owner, an explicit "shown for SVG interoperability reference only" disclaimer, a "does not imply endorsement or affiliation" statement, and a source link to the brand's official asset page. Pattern in `icon-system-pattern.html §SVG icons` (WordPress wmark specimen) — copy that figure structure for new specimens. |
| **Scope discipline** | One specimen per release at most. Adding multiple brand specimens in a single release expands trademark-attribution surface and dilutes the "interoperability reference only" framing. |
| **Federation** | Brand specimens are styleguide-only (lab-internal); they do not enter `post_content` or any federated surface. Cf. charter §6. |

The intent of this section is **scope-managing**, not approval-granting.
A reference specimen embedded under these rules is *manageable* — it
is not, by virtue of these rules alone, automatically trademark-policy
compliant. Final responsibility for trademark fitness sits with the
release author at the time of embedding.

## Cross-references

- Icon font track (chrome glyph engine): `ICON-FONT-POLICY.md` (sibling)
- Inventory of inline SVGs in current styleguide: `INLINE-SVG-INVENTORY.md` (sibling)
- Picker UX (Bucket F): `ICON-PICKER-UX.md` (sibling)
- WP integration references (external):
  - `@wordpress/icons` package — Block Editor Handbook
  - `core/social-link` variation registration — WP 6.9 release notes
  - Social Icons block documentation — wordpress.org documentation
  - The Icon Block plugin (NickDiego) — wordpress.org plugin directory

## Change log

- **v3.4.2 — initial draft.** SVG-required cases enumerated (8 cases),
  permitted cases (3 cases), forbidden cases (3 cases). WordPress
  integration points specified for `@wordpress/icons`, `core/social-link`
  variations, Icon Block parity, and `theme.json`. Sanitization
  baseline defined (8-row policy table). Accessibility patterns
  documented for decorative / semantic / brand SVG. `fill="currentColor"`
  guidance recorded with brand-exception note.

- **v3.4.4 — brand-specimen-as-reference section added.** Codifies
  the rules for rendering brand SVGs as reference specimens in the
  styleguide (official source, currentColor for monochrome,
  trademark caption + disclaimer + source link, one-per-release
  scope discipline, lab-internal only). Applied for the first time
  to the WordPress wmark in `icon-system-pattern.html §SVG icons`.
