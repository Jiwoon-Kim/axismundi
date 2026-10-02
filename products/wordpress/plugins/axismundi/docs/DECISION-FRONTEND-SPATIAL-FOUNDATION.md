# Decision Record: Social Frontend Spatial Foundation

## Status

Adopted as a foundation ownership and VQA reference. 2026-10-01.

This record covers Frontend-owned spacing, grid, ruler, and density foundations. It does not
choose final numeric tokens, implement a layout, or create a user density setting.

## Problem

The Axismundi block theme already declares WordPress spacing and layout settings in
`theme.json`, including `settings.spacing.spacingSizes`, `settings.layout.contentSize`, and
`settings.layout.wideSize`. Those values are input to the WordPress block-theme and Global
Styles system. They are not a Social Frontend spatial-token source.

```text
theme.json
  -> WordPress preset/global-styles projection
  -> --wp--preset--spacing-* and block-theme presentation

/social/
  -> does not consume --wp--preset--spacing-*
  -> does not load block-theme presentation CSS
```

The current Theme foundation asset contract supplies ref/color/shape/elevation/state/motion
stylesheets only. It does not supply a Frontend M3 spacing, grid, ruler, or density stylesheet.

Therefore Social must own its spatial foundation independently of `theme.json` and the
WordPress preset namespace.

## Decision

The Social Frontend owns a spatial token source in `axismundi.tokens`.

```text
Theme foundation CSS (unlayered)
  -> shared Material color, shape, elevation, state, and motion values

Frontend spatial token source (axismundi.tokens)
  -> spacing scale
  -> breakpoint-specific grid and ruler values
  -> layout margins, gutters, and pane spacers
  -> density policy inputs, if and when density is introduced

Frontend layout CSS (axismundi.layouts)
  -> consumes spatial tokens to place bars, rails, and panes

Frontend component CSS
  -> consumes appropriate tokens for component-internal spacing
  -> owns component-specific density adaptation
```

No Social layout or component selector may consume `--wp--preset--spacing-*` as its API.
No Theme `theme.json` spacing preset is copied into a Social runtime registry or parsed at
runtime.

## File and layer boundary

The Frontend-owned spatial contract is recorded in `foundations/layout/grids-spacing/spatial.json`; the native CSS
projection is `styles/tokens/spacing.css`. The JSON is a human-readable contract, not a runtime
dependency or CSS generator. Native media queries retain their threshold literals because CSS
custom properties cannot provide media-query conditions.

Grid and spacing belong in the same spatial foundation source because both vary with breakpoint
and density.

```text
src/apps/frontend/styles/
  layers.css
  tokens/
    spacing.css                 <- Frontend-owned spacing CSS token source
src/apps/frontend/foundations/
  layout/
    breakpoints/
      viewport.json             <- Window size class contract
    grids-spacing/
      spatial.json              <- Frontend-owned spatial contract
    panes/ scaffold/ canonical-examples/
      ... layout consumers ...
```

### Contract-only is not the same as unused

Three of these files have no runtime consumer, and that is three different situations rather
than one. Recorded here because "nothing imports it" has already been read once as "delete it".

| file | status |
|---|---|
| `styles/tokens/spacing.css` | **runtime implementation.** Bundled; the app consumes it through `var()` |
| `foundations/layout/breakpoints/viewport.json` | **non-runtime contract.** The CSS media queries are its implementation |
| `foundations/layout/grids-spacing/spatial.json` | **non-runtime contract.** `spacing.css` and the layout CSS are its implementation |
| `src/apps/frontend/style.json` | **sketch.** A prototype of a future admin control schema — see `PROTOTYPE-FRONTEND-STYLE-MANIFEST.md` |

A CSS custom property is an excellent implementation and a poor contract. `--ax-layout-window-margin`
states what the margin resolves to; it does not state the spatial policy in a form that can be read,
reviewed or reused independently of the technology that implements it. That vocabulary is what a
later layout VQA surface, the admin control panel and component specifications take as their
reference point, which is why the contract is kept even while nothing imports it.

Measured 2026-10-02: the CSS does preserve the token *reference*, not merely the resolved value —
`--ax-layout-window-margin: var( --md-sys-measurement-space200 )` carries the same relationship the
JSON writes as `{md.sys.measurement.space200}`. So the duplication is real rather than a difference
in abstraction level, and the JSON earns its place as a contract rather than as extra information.

### The raw scale is a later tidy-up, not a current one

`spatial.json` currently writes all eighteen `md.sys.measurement.space*` values that `spacing.css`
also declares. That half is a pure mirror. When it is worth separating, the contract keeps the
semantic layout policy and the raw scale lives only in the CSS token source:

```json
{
  "layout": {
    "windowMargin": {
      "compact": "md.sys.measurement.space200",
      "mediumAndUp": "md.sys.measurement.space300"
    },
    "paneGap": "md.sys.measurement.space300"
  }
}
```

Not now, and no generator or checker in the meantime. A checker here would make CI enforce
agreement between a live file and one with no consumer, and a generator is the JSON-parsing work
this decision explicitly declines. The file name follows that change rather than leading it: what
remains after the raw scale leaves is layout policy, including `fixedPaneWidth`, which is a window
dimension rather than spacing — so `spacing.json` would be the wrong name both now and then.

`spacing.css` declares Foundation values on `:root` in `axismundi.tokens`; grid placement rules
remain in `axismundi.layouts`. The Social document body consumes these tokens as its default
surface rather than becoming their owner.

```text
tokens
  -> names and values: spacing scale, margins, gutters, pane spacers, rulers

layouts
  -> structural use of those values in a specific AppLayout or page layout
```

This avoids hard-coded spatial values in layout/component selectors while also avoiding a
second dependency on WordPress Global Styles.

## Initial token projection

The initial Frontend scale uses Material system measurement names and mirrors the
already-established Axismundi spacing values without consuming the WordPress preset namespace.
`--md-sys-measurement-space0` through `--md-sys-measurement-space900` map to `0px` through
`72px`; the CSS values are web projections of the corresponding Material `dp` measurements.
They are owned and consumed by Social.

The first layout aliases are deliberately narrow:

```text
--ax-layout-window-margin
  Compact: 16px
  Medium and above: 24px

--ax-layout-pane-gap
  24px
```

Candidate fixed pane widths, ruler positions, and density values remain unpromoted until a
canonical layout needs them and VQA establishes their product role.

## Material source constraints retained for VQA

Material grids adapt column count, column width, and spacing across breakpoints. Bars and rails
are placed near the usable window edge before primary and supporting panes. Rulers align titles,
content, margins, bars, rails, and safety regions across a product.

The supplied Material breakpoint guidance provides the following candidate spatial baseline for
web VQA. These are not yet Axismundi token values until tested in the actual Social layout.

| Breakpoint | Margin | Pane spacer | Pane guidance |
| --- | ---: | ---: | --- |
| Compact | 16dp | n/a | One pane |
| Medium | 24dp | 24dp | One pane preferred; two only for low-density content |
| Expanded | 24dp | 24dp | One or two panes; two often preferred |
| Large | 24dp | 24dp | One or two panes; two often preferred |
| Extra-large | 24dp | 24dp | One to three panes; two often preferred |

Material's related pane guidance supplies candidate fixed widths of `360dp` at Expanded and
`412dp` at Large/Extra-large for fixed-and-flexible layouts. A third side-sheet pane at
Extra-large has a default maximum width of `400dp` in the cited guidance. These are product
choices, not values to insert automatically into the first Social grid.

For web CSS, breakpoint conditions use CSS pixels. The existing Social thresholds are `600px`,
`840px`, `1200px`, and `1600px`; the units in Material source text are retained as `dp` because
they describe the design reference rather than a CSS declaration.

## Density boundary

Information density is not merely a smaller spacing scale. Material distinguishes:

```text
layout density
  -> margins, spacers, padding, and the amount of information visible in a layout

component density / scaling
  -> a component's internal dimensions and padding
```

Density must not change automatically merely because a breakpoint or orientation changes.
People must opt in when a dense mode is offered. The default target size remains at least
`48x48` CSS pixels, and component scaling must not shrink selectable targets below that size.

The Frontend does not implement density selection, density persistence, or component density
tokens in this checkpoint. A later density setting must keep layout policy separate from a
component's internal density contract.

## Grid and ruler boundary

M3 rulers are global alignment lines, not DOM components and not decorative CSS helpers.
The eventual Frontend spatial token source may expose ruler/margin values; an individual page
layout decides which grid areas and content columns consume them.

```text
spatial tokens
  -> window margin, gutter, pane spacer, content ruler values

AppLayout / page layout
  -> region placement and grid tracks

page content
  -> aligns a title, feed, media, or supporting content to an appropriate ruler
```

Do not add an abstract ruler runtime, drag handles, resizable panes, or a layout JSON schema
until a concrete Social destination requires one.

## VQA order

1. Verify Color foundation in the real `/social/` document.
2. Establish the Frontend spatial token source from the cited grid/spacing reference.
3. Verify compact through extra-large layout behavior, including logical-direction/RTL behavior.
4. Establish the separate Frontend typescale contract after available widths and spatial rhythm
   are understood.
5. Add production components that consume the proved spatial and typography foundations.

## Source records

- `SOURCE-M3-GRIDS-SPACING-RAW.md`
- `REFERENCE-M3-GRIDS-SPACING.md`
- `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`
- `DECISION-FRONTEND-THEME-ASSET-CONTRACT.md`
