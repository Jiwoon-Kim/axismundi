# Decision Record: Social Frontend Adaptive Layout Reference

## Status

Adopted as an implementation and VQA reference. 2026-10-01.

This record preserves the Material 3 layout source material and defines the boundary for
the next Frontend layout checkpoint. It is intended to let a later implementation or
Opus handoff distinguish an established constraint from an unmade product decision.

It does not define a runtime JSON schema, a persisted layout format, a widget editor, or
component implementations.

## Source material

- [Material 3 Design Kit: Layout grid](https://www.figma.com/design/ixQuHzVDPis0DYdaoMixyP/Material-3-Design-Kit--Community-?node-id=55594-2480)
- [Material 3 layout overview](https://m3.material.io/foundations/layout/layout-overview/overview)
- [Material 3 parts of layout](https://m3.material.io/foundations/layout/layout-overview/parts-of-layout)
- [Material 3 adaptive design](https://m3.material.io/foundations/layout/layout-overview/adaptive-design)
- [Material 3 scaffold overview](https://m3.material.io/foundations/layout/scaffold/overview)
- `SOURCE-M3-ADAPTIVE-LAYOUT-RAW.md` preserves the supplied English source text without normalization when remote Material pages or Figma context are unavailable to a later handoff.
- `REFERENCE-M3-ADAPTIVE-LAYOUT-SOURCE.md` is the formatted reading aid derived from that raw source.

The Material references describe an adaptive layout system for web as well as Android.
They are the visual and behavioral precedent; Axismundi must still make product-specific
decisions through implementation and VQA rather than copy an Android API or a Figma
property model into React.

## Material vocabulary retained by Social

```text
Window
  Frames and contains the application. It can be resized, tiled, or share space with
  other applications.

Scaffold
  The fundamental structure that arranges bars, rails, and panes.

Bar
  A perimeter region that can contain an app bar or compact navigation bar.

Rail
  Perimeter space adjacent to panes. It can contain a navigation rail, toolbar, FAB,
  chat input, or other primary controls.

Pane
  A content container. Every product destination is rendered in a pane. A layout has
  one to three visible panes; at least one must be flexible.

Column
  A vertical content block inside a pane.

Margin / gap
  Space between the window edge and content, or between content inside a container.

Drag handle
  A control that resizes, collapses, or expands a pane. It is not merely decorative.

Ruler
  A global alignment line shared across layout layers.
```

Use CSS logical properties and logical grid placement whenever the layout has a directional
meaning. The leading navigation rail must not be encoded as an inherently left-side product
assumption; RTL is a first-class layout requirement.

## Window size classes

Material's current terminology is *breakpoints*; the preceding term was *window size
classes*. Social retains the following five-class vocabulary.

| Name | Window width |
| --- | --- |
| Compact | 0-599dp |
| Medium | 600-839dp |
| Expanded | 840-1199dp |
| Large | 1200-1599dp |
| Extra-large | 1600dp and above |

CSS media queries use CSS pixels, not Android density-independent pixels. For this web
implementation, `600px`, `840px`, `1200px`, and `1600px` are the corresponding documented
thresholds unless a later VQA result changes the contract. A custom property cannot supply a
media-query condition, so those threshold literals remain in the owning layout stylesheet.

The classes are adaptive structure vocabulary, not component variant names and not a
responsive typography scale.

## Pane guidance retained from Material

| Breakpoint | Recommended visible panes | Other permitted totals |
| --- | ---: | ---: |
| Compact | 1 | -- |
| Medium | 1 | 2 |
| Expanded | 2 | 1 |
| Large | 2 | 1 |
| Extra-large | 2 | 1, 3 |

Material describes fixed and flexible panes. A fixed-and-flexible two-pane composition is
common from expanded upward; a split-pane composition keeps a fold/spacer visually centered.
At extra-large, a standard side sheet may become a third pane. Do not show more than three
panes.

The three adaptive strategies are:

```text
show and hide  -> a pane enters or leaves the visible layout
levitate       -> a pane becomes floating or docked above other content
reflow         -> panes change position or stack when space is insufficient
```

These are available strategies, not an instruction to implement all three now. Drag handles,
resizable panes, persisted pane width, fold handling, and spatial panels remain deferred until
there is a Social product requirement and an accessibility plan.

## Canonical layout examples

Material's canonical examples are product-layout precedents, not a new Social template format.

```text
Feed
  -> initial product reference for a Social timeline/content grid inside the main pane

List-detail
  -> future reference for a collection/object detail destination with a parent-child relationship

Supporting pane
  -> future reference for contextual secondary content that is meaningful only with a focus pane
```

The Feed layout is not the application scaffold: it composes content inside a pane. Cards and
list items are components. Likewise, supporting-pane placement is a destination layout choice,
not a reason for `AppLayout` to manufacture a permanent sidebar.

The source references are preserved in `SOURCE-M3-CANONICAL-LAYOUTS-RAW.md` and organized for
VQA in `REFERENCE-M3-CANONICAL-LAYOUTS.md`. No canonical layout is implemented by this record.

## Axismundi layout/component boundary

The layout owns placement of regions. It does not own the visual or interaction contract of
the controls injected into those regions.

```text
Layout (`axismundi.layouts`)
  - App window and region placement
  - bar, rail, main-pane, and supporting-pane grid areas
  - show/hide or reflow rules at documented breakpoints
  - directional placement and pane containment

Material component (`axismundi.material`)
  - md-navigation-bar
  - md-navigation-rail
  - app bar, FAB, drag handle, and their interaction/accessibility contracts

Axismundi component (`axismundi.components`)
  - domain/product surfaces placed inside panes
  - feed, object card, actor header, composer, and supporting widgets
```

`AppLayout` must not manufacture a navigation rail, navigation bar, supporting widget, or
page-specific content. It exposes slots for independent components. Conversely, a navigation
component must not silently claim application grid ownership.

## Current implementation baseline

The current Frontend implementation is deliberately smaller than the full Material guidance.

```text
src/apps/frontend/layouts/app-layout/
  - compact default: one flexible main region
  - >= 600px: optional rail region may appear beside main
  - >= 840px: optional supporting region may appear beside main
  - optional compact bar region is placed after main and hidden when the rail is visible
```

This is a structural baseline for Color and Layout VQA. It is not yet a verified five-class
Material layout, a pane policy, or an implementation of `md-navigation-bar` or
`md-navigation-rail`.

The current React surface contract is intentionally small:

```jsx
<AppLayout
  navigationBar={ /* optional component */ }
  navigationRail={ /* optional component */ }
  supporting={ /* optional content */ }
>
  { /* route template content in the main pane */ }
</AppLayout>
```

This JSX is not a persisted template format. It must not be promoted to a `style.json` layout
schema, Gutenberg serialization, or user-editable instance tree before a real product need
proves that model.

## CSS ownership and token boundary

```text
Theme foundation CSS (unlayered)
  -> shared --md-ref-* and --md-sys-* values for color, shape, elevation, state, motion

Frontend layout CSS (`axismundi.layouts`)
  -> structural grid and layout rules
  -> consumes existing system tokens where a layout surface needs color
  -> contains no newly invented raw color, spacing, radius, elevation, or typography metrics

Frontend typography CSS (`axismundi.typography`, future)
  -> Frontend-owned M3 typescale tokens and semantic HTML mapping

Component CSS (`axismundi.material` / `axismundi.components`, future)
  -> visual, state, and interaction styling for the component that occupies a layout slot
```

The Theme does not deliver a Frontend M3 typescale stylesheet. Typography therefore follows
Color and Layout VQA, under a separate Frontend-owned contract. Layout breakpoints must not be
used as a substitute for that typography contract.

## VQA order and acceptance questions

The next checkpoint is not a broad Social product build. Verify the existing foundation and
layout baseline first.

1. Color VQA: confirm effective `--md-sys-color-*` values in the real `/social/` document
   across theme schemes.
2. Layout VQA: inspect the baseline at Compact, Medium, Expanded, Large, and Extra-large
   widths, including LTR/RTL placement, one-main-pane behavior, optional-region visibility,
   overflow, and keyboard focus order.
3. Typography VQA: establish the separate Frontend typescale contract only after layout
   surfaces and available width are understood.
4. Component VQA: introduce production Material components into the proved slots; Stylebook
   fixtures must import those same production components.

The following questions intentionally remain open for implementation/VQA rather than being
guessed in this record:

- Which Social destinations require one, two, or three panes at each breakpoint?
- Whether the medium layout uses compact navigation, a rail, or a temporary pane for a given
  destination.
- Whether a supporting pane is co-planar, floating, docked, reflowed, or hidden.
- Whether a pane can resize, whether its width persists, and what accessible drag-handle
  behavior it requires.
- Which M3 components occupy bar and rail regions.
- The actual grid margins, columns, gutters, and ruler contract.

## Documentation-only reference shape

The following is a documentation aid only. It is explicitly not a runtime schema or an
authoritative configuration format.

```json
{
  "windowSizeClasses": [
    { "name": "compact", "minWidth": 0, "maxWidth": 599 },
    { "name": "medium", "minWidth": 600, "maxWidth": 839 },
    { "name": "expanded", "minWidth": 840, "maxWidth": 1199 },
    { "name": "large", "minWidth": 1200, "maxWidth": 1599 },
    { "name": "extra-large", "minWidth": 1600 }
  ],
  "layoutRegions": ["none", "navigation", "navigation-expanded"],
  "paneStrategies": ["show-hide", "levitate", "reflow"]
}
```

The `layoutRegions` values preserve the supplied Material Design Kit reference vocabulary;
they do not prescribe the names of React slots or CSS classes.

## Related records

- `DECISION-FRONTEND-PRODUCT-FIRST.md`
- `DECISION-FRONTEND-NAVIGATION-MODEL.md`
- `DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
- `REFERENCE-M3-CANONICAL-LAYOUTS.md`
