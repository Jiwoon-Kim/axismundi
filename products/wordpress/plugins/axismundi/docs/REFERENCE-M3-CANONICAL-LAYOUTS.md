# Material 3 Canonical Layouts Reference Notes

## Status

Derived reading aid. 2026-10-01.

The unedited supplied source is preserved in `SOURCE-M3-CANONICAL-LAYOUTS-RAW.md`.
This file indexes the distinctions needed for Axismundi implementation and VQA; it does not
select a product layout by itself.

## Canonical examples

| Example | Material purpose | Axismundi reading |
| --- | --- | --- |
| Feed | Fast discovery of cards/lists in an adaptive grid | Primary reference for Social home/timeline content inside a main pane |
| List-detail | Parent/child or collection/detail exploration | Reference for a future collection/object detail destination, not for contextual widgets |
| Supporting pane | Secondary content meaningful only with a primary focus pane | Reference for contextual Social secondary content, not a permanent generic sidebar |

## Boundary with the application scaffold

```text
AppLayout
  -> window-level bar, rail, main-pane, and optional supporting-pane regions

Feed layout
  -> content-grid layout inside a pane

List-detail layout
  -> destination-level relationship between collection and detail panes

Supporting-pane layout
  -> destination-level relationship between focus and contextual secondary content

Card, list item, navigation bar, navigation rail
  -> components, not layouts
```

The canonical examples guide composition and adaptive behavior. They do not permit an
`AppLayout` to create cards, navigation components, drag handles, or product widgets itself.

## VQA prompts retained from the examples

```text
Feed
  Compact: one vertical card/list column filling the pane.
  Medium: assess whether multiple columns improve browsing.
  Expanded+: increase columns only when content ordering, readability, and scanability hold.

List-detail
  Compact: only list or detail is visible; detail has an appropriate back path.
  Medium: two panes only for lower-density collection browsing with clear actions.
  Expanded+: two panes; preserve the selected item and detail when moving between modes.

Supporting pane
  Compact/Medium: supporting content below the focus content, or temporarily docked when that
  preserves focus.
  Expanded+: supporting content can be a fixed leading/trailing pane when it is contextual.
```

## Deferred

- Choosing the first Social destination that uses a canonical layout
- Feed card/grid token values and column count
- List-detail selection, back behavior, and persistence
- Supporting-pane visibility, fixed width, and levitate/dock behavior
- Any drag handle or resizable pane behavior
