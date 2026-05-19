# Axismundi v3.6.0 — Ontology Theme Pilot Phase 3 Report

Status: PASS WITH FINDINGS
Date: 2026-05-19
Scope: Acceptance verification for the v3.6.0 WordPress block theme Pilot

## 1. User Request Log Verification

- Pilot directory remains `products/reference-implementations/axismundi-pilot/`.
- Theme slug remains `axismundi-pilot`; display name remains `Axismundi Pilot`.
- Existing `products/reference-implementations/ontology-theme-pilot/` remains historical and untouched.
- Pilot scope remains Wave 1 minus Carousel.
- Carousel remains plugin-routed; no Carousel block, plugin, markup, or runtime is registered.
- Color token policy follows the prior `bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md` decision: Theme-only mode, static slug bridge, `settings.color.custom = false`.
- Korean prose rendering is required and verified.
- Material Symbols remains a chrome icon font, not a WordPress Font Library content font.

## 2. Technical Baseline

```txt
Active theme:        axismundi-pilot
WordPress:           6.9.4
Front-end URL:       http://localhost:8888/
Validator:           1.000 / 1.000 / 1.000 / 1.000 PASS
npm test:            PASS
functions.php lint:  PASS
```

The Pilot remains active under wp-env and renders without fatal errors.

## 3. Runtime Rendering Smoke

Playwright smoke covered:

- Front page: `http://localhost:8888/`
- Korean single post: `http://localhost:8888/?p=1`
- Page template: `http://localhost:8888/?page_id=5`
- Pattern QA page: `http://localhost:8888/?page_id=10`
- Editor new page screen: `http://localhost:8888/wp-admin/post-new.php?post_type=page`

Viewport matrix:

```txt
390px  PASS
768px  PASS
1280px PASS
```

Runtime checks:

```txt
HTTP status:             200 for all front-end targets
Horizontal overflow:     0 for all front-end targets
Console/page errors:     0
Pilot CSS files loaded:  7 / 7
Editor canvas detected:  yes
wp.blocks available:     yes
Korean prose render:     PASS
```

Computed-style audit was added after Phase 3 visual QA found several issues
that ordinary smoke tests did not catch. The audit now checks rendered values
instead of relying on selector presence:

```txt
Audit script:            tools/validators/validate_pilot_computed_styles.js
Command:                 npm run validate:computed
Pattern QA page:         PASS
Single prose page:       PASS
Front page:              PASS
Styleguide blocks table: PASS
Screenshots:             tmp/phase3-computed-audit/*.png
```

Computed-style gate:

```txt
Button fill:             non-transparent M3 container
Button outline:          native border reset to 0px; inset outline retained
Button outline tokens:   color = on-surface-variant; outline = outline-variant
Button text:             transparent container
Search filled:           border reset to 0px
Search button:           core default background removed
Inline code:             visible token surface
Code block wrapper:      visible token surface + 24px padding + core border removed
Quote:                   leading indicator present
Separator:               visible M3 line; core gray border removed
Default table:           no top cell border; thead 3px border removed; bottom separator present
Stripes wrapper:         core #f0f0f0 border removed
Stripes odd row/cell:    transparent background
Stripes even row/cell:   surface-container-high background
Horizontal overflow:     0
Console/page errors:     0
```

## 4. Block Styles and Patterns

Registered block styles:

```txt
core/button:    native fill / outline + custom tonal, elevated, text
core/group:     card-filled, card-elevated, card-outlined
core/list:      list-segmented
core/separator: divider-inset, divider-middle-inset
core/search:    filled-search
```

Phase 3 visual QA clarified that WordPress core Button styles (`fill` /
`outline`) should not be hidden. They are the native WordPress equivalents of
M3 Filled / Outlined. The Pilot therefore registers only the missing M3 styles
(`tonal`, `elevated`, `text`) and maps native `fill` / `outline` through
`blocks.css`.

Registered Pilot patterns:

```txt
axismundi-pilot/button-actions
axismundi-pilot/card-list
axismundi-pilot/hero
axismundi-pilot/prose-sample
axismundi-pilot/search-section
```

Registered Pilot pattern category labels:

```txt
Axismundi Showcase
Axismundi Composition
Axismundi Prose
```

No `register_block_type()` call exists in the Pilot theme. The Pilot remains core-block-only.

## 5. Color and Font Policy

Theme JSON color policy:

```txt
settings.color.custom:         false
settings.color.defaultPalette: false
palette slugs:                 24
palette values:                all var(--md-sys-color-*) references
```

Theme JSON font policy:

```txt
fontFamilies: 5
missing fontFace src: 0
root font: var(--wp--preset--font-family--roboto-flex)
```

Registered content font families:

- Roboto Flex
- Noto Sans KR
- Roboto Serif
- Noto Serif KR
- Roboto Mono

Material Symbols Rounded remains loaded as a chrome icon font through `fonts.css`, not exposed as a content font family in WordPress Font Library.

## 6. Accessibility Smoke

Front-end DOM smoke at 390px:

```txt
main landmark:             present
h1:                        present
unnamed buttons:           0
unnamed links:             0
images missing alt:        0
unlabeled content inputs:  0
horizontal overflow:       0
console/page errors:       0
```

The WordPress editor screen also loaded without console errors and exposed the block editor canvas. The editor itself contains WordPress core admin controls outside the Pilot theme's ownership surface, so front-end accessibility smoke remains the Pilot acceptance basis for Phase 3.

## 7. Spec Surface Verification

`blocks.html` is represented through the Pilot's block style registry and block CSS coverage:

- Button styles map to `core/button`.
- Card surfaces map to `core/group`.
- Segmented list maps to `core/list`.
- Filled search maps to `core/search`.
- Divider styles map to `core/separator`.

`prose.html` is represented through the Pilot prose template and Korean post sample:

- Prose rhythm now maps through `pilot-block-bridge.css` using WordPress block
  selectors, not a forced `.prose` wrapper.
- `single.html` and `page.html` keep `core/post-content` block-first so
  per-block customization remains available.
- Korean content renders with the Roboto Flex -> Noto Sans KR fallback chain.
- Tables, blockquote, code, and list rhythm are covered by the Pilot block
  bridge on real WordPress post bodies.
- Core table `is-style-stripes` is re-asserted inside the bridge so prose table
  cell defaults do not leak into the Stripes variant.

## 8. Findings

### P3 — Carousel CSS is present as a passive copied asset

The Pilot does not register a Carousel block, plugin, markup, or runtime. However, the Phase 2B asset bridge copies the full `components.css`, so passive `.ax-carousel*` selectors are present in `assets/styles/components.css`.

This does not violate the Phase 3 runtime acceptance criteria, but it exposes a future asset-slicing question for the Carousel plugin extraction cycle:

- Keep full public-surface CSS in the theme for historical compatibility, or
- Slice plugin-routed component CSS out of the Pilot/theme asset bundle.

Route: BACKLOG #38 / future Carousel plugin extraction, with the broader asset
slicing policy tracked by BACKLOG #40.

### Fixed in cycle — WordPress block bridge MVP

Phase 3 visual QA surfaced that a container-level `.prose` wrapper would
undermine WordPress block-level customization. Phase 2E replaced that temporary
approach with a Pilot-specific block bridge:

- `pilot-block-bridge.css` maps `core/post-content` descendants to M3 prose
  contracts block-by-block.
- `pilot-block-bridge.css` maps `.wp-block-button__link` hover, focus, pressed,
  disabled, finite-radius morph, and ripple host styling.
- `pilot-block-bridge.js` adds front-end bounded ripple enhancement to
  `core/button` links.

Full block bridge expansion remains BACKLOG #41.

### P3 — blocks.html / prose.html shell consistency remains cosmetic maintenance

`prose.html` received an in-cycle 390px overflow fix because it is a Pilot specification surface. Broader shell consistency for `blocks.html` / `prose.html` remains BACKLOG #39 and is not a Pilot blocker.

### Fixed in cycle — Core Button style picker mapping

The initial fix tried to unregister WordPress core `fill` / `outline` Button
styles. Phase 3 corrected this: native `fill` / `outline` remain available and
are mapped to M3 Filled / Outlined, while the theme registers only `tonal`,
`elevated`, and `text`.

### Fixed in cycle — Core Table default / stripes reset

Phase 3 visual QA also caught that WordPress core table defaults could leak
through as native-looking borders and stripe fills. A follow-up computed-style
inventory also found `thead { border-bottom: 3px solid; }` and
`.wp-block-table.is-style-stripes { border-bottom: 1px solid #f0f0f0; }`
leaking from core styles. `blocks.css` and the Pilot bridge now explicitly map:

- default table cells to M3 horizontal separators, and
- `is-style-stripes` odd rows/cells to transparent backgrounds while even
  rows/cells use `surface-container-high`, and
- table header / stripes wrapper borders to M3 outline-variant rules.

### Fixed in cycle — Core Search and Code residual defaults

Computed-style inventory surfaced two additional non-M3 residuals:

- `core/search` default button background (`rgb(50, 55, 60)`) outside the
  filled-search specimen, and
- `core/code` / `core/preformatted` default `#ccc` borders.

Both now reset through `blocks.css`, with the Pilot bridge reinforcing code
block reset inside `core/post-content`.

### Limitation — core/button renders links, not native button elements

`core/button` renders `.wp-block-button__link` as an anchor. The Pilot maps this
anchor to the M3 Button visual/state contract, but it does not alter WordPress
core markup semantics. Changing anchor-vs-button semantics would require a
render filter or a custom block, both outside v3.6.0 theme-only scope.

## 9. Verdict

Phase 3 automated acceptance passes.

The Pilot is ready for user visual confirmation and Phase 5 mechanical close.
