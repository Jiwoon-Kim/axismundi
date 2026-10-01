# Material 3 Canonical Layouts Raw Source

## Purpose

Unedited source text supplied for the Social Frontend layout checkpoint. Preserve wording,
ordering, tables, and links. Do not rewrite this file during implementation work.

## Sources

- https://m3.material.io/foundations/layout/canonical-examples/overview
- https://m3.material.io/foundations/layout/canonical-examples/feed
- https://m3.material.io/foundations/layout/canonical-examples/list-detail
- https://m3.material.io/foundations/layout/canonical-examples/supporting-pane

## Canonical layout examples

Canonical layout examples demonstrate how to implement the layout scaffold. They’re also available in code to provide a strong starting point for your product.

Each layout example considers common use cases and components to address expectations and user needs for how products adapt across breakpoints (previously window size classes).

A layout scaffold can include bar, rail, and pane regions.

There are three canonical layout examples: feed, list-detail, and supporting pane. Each example has configurations for compact, medium, and expanded breakpoints.

Use these canonical examples as a starting point to create layouts for a product.

## Feed

Use a feed layout to arrange elements like cards in a configurable grid for a quick, convenient view of a large amount of content.

A feed layout uses a grid composition to enable quick content browsing and discovery. Key use cases include news, photos, and social media.

Use a feed layout to show different pieces of content through cards and lists. Feeds support displays of almost any size as grids can adapt from single to multi-column.

A feed composition is flexible enough to allow for content with varying proportions and sizing. Feed items should reflow when the amount of available space changes, including when rotating or unfolding a device or entering multi-window mode. The order of items is determined by their position.

Compact: A feed layout should stack vertically, like a list of cards with individual items filling the width of the pane.

Medium: A feed layout can support components with different widths and be split across multiple columns.

Expanded, large, and extra-large: A feed layout can support components with different widths and be split across multiple columns. The number of columns should usually increase at expanded breakpoints. Column width can increase at larger breakpoints.

## List-detail

Many layouts can be split into a list view and a detail view. Key use cases include text message + conversation, file browser + open folder, musical artist + album detail, settings + category detail, and email inbox + selected email.

Use the list-detail layout for quickly accessing details of an item from a long list of content.

A list-detail layout uses two panes. Depending on the breakpoint, the two panes may appear together in the same layout or across separate layouts. List-detail layouts use the same pane guidance as all single and two-pane layouts, including special behavior for foldables.

| Breakpoint (dp) | Visible panes |
| --- | --- |
| Compact (0-599) | 1 pane |
| Medium (600-839) | 1 (recommended) or 2 panes |
| Expanded (840+) | 2 panes |
| Large (1200-1599) | 2 panes |
| Extra-large (1600+) | 2 panes |

Compact: Use a single-pane layout. Only one view is visible at a time, either list or detail.

Medium: Use a single-pane layout for information-dense content or deep focus. Use a two-pane layout to browse collections and switch between items quickly. To maximize horizontal space for two-pane layouts, use a bottom navigation bar or modal navigation rail.

Expanded, large, and extra-large: Use a two-pane layout.

Single vs two-pane behavior: A back button appears in detail view only for single-pane layouts. A selected state appears only in list view for two-pane layouts. Use explicit and implicit grouping to direct focus in two-pane layouts.

When switching from a single- to two-pane layout, both panes should be shown and the selected item’s details are visible. When going from a two- to single-pane layout, the detail pane should typically show with an app bar; products that support selection without deep navigation may instead show the list view with the item selected. Consistency is key.

In most cases, a state should be saved when navigating between detail views. This includes read and unread content.

## Supporting pane

The supporting pane layout organizes content into primary and secondary areas. The primary area contains the main content and occupies the majority of the space. The secondary area contains supporting content.

Key use cases include productivity, document editing and commenting, and content and media browsing.

Use the supporting pane layout when the secondary content is only meaningful in relation to the primary content. For content with a parent-child relationship, use a list-detail layout instead.

The window is divided between a focus pane and a supporting pane. Depending on the breakpoint, the supporting pane may appear below or beside the focus pane.

| Supporting pane placement | Pane width | Breakpoint |
| --- | --- | --- |
| Below | Flexible | Compact or Medium |
| Leading or trailing | Fixed (360dp) | Expanded |

Compact: The supporting pane should appear below the focus pane. A bottom sheet can be useful for keeping focus on the primary pane while providing access to supporting information.

Medium: The supporting pane should appear below the focus pane.

Expanded: The supporting pane should appear on the leading or trailing side of the focus pane.

## Advanced custom layouts

To create a custom layout, build on top of canonical layouts or layer scaffold elements.

Use the levitate adaptive strategy to create a layered layout. Layering panes above other content can create a focused, task-oriented experience such as reviewing a shopping basket, responding to comments, or creating a calendar event.
