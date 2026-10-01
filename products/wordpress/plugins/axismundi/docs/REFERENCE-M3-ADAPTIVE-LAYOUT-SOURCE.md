# Material 3 Adaptive Layout Reference Notes

## Status

Derived reference notes. 2026-10-01.

This is a formatted reading aid derived from `SOURCE-M3-ADAPTIVE-LAYOUT-RAW.md`. The raw file preserves the supplied source text without normalization; this file organizes the layout concepts needed during implementation and VQA. It is separate from `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md`, which records Axismundi decisions rather than source material.

The original linked pages remain authoritative. Images, diagrams, and animations are not embedded here; open the linked pages and raw transcript when their visual context is required.

## Sources

- [Material 3 Design Kit: Layout grid](https://www.figma.com/design/ixQuHzVDPis0DYdaoMixyP/Material-3-Design-Kit--Community-?node-id=55594-2480)
- [Material 3 layout overview](https://m3.material.io/foundations/layout/layout-overview/overview)
- [Material 3 parts of layout](https://m3.material.io/foundations/layout/layout-overview/parts-of-layout)
- [Material 3 adaptive design](https://m3.material.io/foundations/layout/layout-overview/adaptive-design)
- [Material 3 scaffold overview](https://m3.material.io/foundations/layout/scaffold/overview)

## Layout grid

```text
properties: name, value

Window size class
1. Compact (0-599dp)
2. Medium (600-839dp)
3. Expanded (840-1199dp)
4. Large (1200-1599dp)
5. Extra-large (1600+dp)

Layout regions
None
Navigation region
Navigation expanded
```

## Layout overview

```text
Use layout to organize all elements in a screen, signal hierarchy, and draw attention to key actions.
Adapt layouts to compact, medium, expanded, large, and extra-large breakpoints (previously window size classes).
Build from an established canonical layout example.
Design for bidirectionality to support both left-to-right (LTR) and right-to-left (RTL) languages.
Apply consistent arrangement, sizing, and spacing to create a functional layout structure.
Material layout guidance is implemented on Android and applies to web.
```

```text
1. Column
2. Fold
3. Margin
4. Bar
5. Drag handle
6. Pane
7. Rail
```

## Layout terms

```text
Adaptive design: Techniques that allow an interface to dynamically respond to contexts like user preferences, device type, state, and breakpoints.
Bars: Can frame the page to help people navigate through a product, and typically house the app bar and bottom navigation bar.
Bidirectionality: A writing system that displays text and content from right-to-left (RTL).
Breakpoints: Opinionated window sizes where a layout changes to match available space, device conventions, and ergonomics (previously window size classes).
Column: One or more vertical blocks of content within a pane.
Drag handle: The component that resizes panes.
Fold: A flexible area of the screen or a hinge that separates two displays on foldable devices.
Gap: The space between components or elements within a container.
Margin: The space between the edge of the screen and any elements inside of it.
Multi-window mode: Enables multiple apps to share the same screen simultaneously.
Pane: A layout container that houses other components and elements within a single app. A pane can be fixed, flexible, floating, or semi-permanent.
Rail: The perimeter space surrounding panes that holds key elements such as navigation rails, toolbars, and pane control.
Right-to-left (RTL) language: Languages written and read right-to-left, such as Arabic, Hebrew, and Farsi.
Rulers: An opinionated set of global alignment lines that help organize building blocks in a layout.
Safety region: Zones reserved for system UI elements outside the application space, such as status bar or gesture bar.
Scaffold: A fundamental UI design structure that provides a standard platform for assembling key screen components.
Spacer: The space between two panes on a foldable device.
```

## Parts of layout

```text
A window frames and contains an app or product.
Many systems support multi-window views, which display multiple apps at once.
On desktop, windows can be resized and moved around freely. They should adapt to various screen sizes.

The layout grid is the foundation for every layout. It provides a structural framework for organizing components, content, and actions.
Use the grid to group related information in columns, apply spacing consistently, create focal points for primary actions, and align building blocks like bars, rails, and panes.
Column count, width, and spacing dynamically adjust to different breakpoints.

Bars help people navigate through a product. Use bars to frame the main content, contain an app bar or navigation bar, and span one or multiple panes.
App bars are placed at the top of the screen to help people navigate by providing a description of the screen and 1-2 essential actions, like search or back navigation.

Rails are the next level in layout after bars, filling the perimeter space surrounding panes, or floating above them. They contain key elements such as navigation rails, toolbars, chat inputs, FABs, and other primary controls.
On mobile, the rail region can contain a toolbar. On desktop, the rail region can contain the navigation rail.

All content must be in a pane. A layout can contain 1-3 panes of various widths, which adapt dynamically to the breakpoint and the person's language setting. For RTL languages, navigation components will be on the right.
People can navigate to or between panes. Presenting multiple panes at once can make a product more efficient and easier to use.

On most devices, panes can blend in with the background. This is called implicit grouping, and helps show relationships between panes.
Explicit grouping uses distinct colors or outlines to visually delineate content.
In multiple-pane layouts, use color to show emphasis and close spacing to group related content.

Drag handles can adjust the width of flexible panes or fully collapse and expand fixed panes to quickly switch between a single and two-pane layout.
Rulers are a set of global alignment lines. They help to align elements across all layers of the layout and keep margins and placement consistent.
```

## Adaptive design

```text
Adaptive design is a set of techniques to change an interface to fit different contexts. While responsive design scales a single layout to fit any screen, adaptive design customizes a product to optimize the experience on each device.

Layouts must be versatile, designed to adapt fluidly across mobile, desktop, and spatial experiences. Designs should be built with touch, pointer, and physical keyboard input in mind.

The Material 3 adaptive system uses panes and breakpoints to organize content into adaptive layouts. Panes are the building blocks of layout; a pane is a single destination in the product.

As the pane or window resizes, panes may change size, enter and exit the screen, and reorganize themselves to make the experience more usable or easier to navigate.
```

```text
show and hide: panes can enter and exit the screen or appear next to one another.
levitate: a pane can become floating or docked above other content.
reflow: panes can reorganize on screen; a supporting pane can move below a primary pane or panes can stack vertically.

Co-planar: Panes are displayed side by side.
Floating: A pane is displayed above other panes or content, like a dialog.
Docked: A pane is displayed above other panes and one edge extends beyond a window side, like a bottom sheet.
```

```text
Components can adapt in appearance, placement, and behavior based on their placement in relation to containers, content, pane boundaries, and available space.
Most Material components respond using resizing, showing and hiding, and presentation changes.
Examples include buttons hugging content or spanning a container, list items revealing more information when space permits, extended FAB presentation, and expanded navigation rails.
```

## Scaffold overview

```text
The layout scaffold structures every piece of an adaptive layout into bars, rails, and panes.
Bars can frame the page to help people navigate through a product.
Rails create the perimeter space surrounding panes, creating space for elements like navigation and toolbars.
Panes hold a product's primary content, adapting to breakpoints and other conditions.

An app bar occupies the top bar region on the web.
Navigation bars let people switch between 3-5 primary UI views at compact or medium breakpoints, above the safety region.
Safety regions reserve space for system UI elements. Primary content should not occupy them.

On compact screens, top and bottom rail regions can contain toolbars, chat inputs, FABs, and other primary controls related to a screen.
On larger screens, the leading side rail commonly holds a navigation rail. A trailing rail can hold supporting controls or actions related to a pane.
```

| Breakpoint | Recommended pane total | Other pane totals |
| --- | --- | --- |
| Compact | 1 | -- |
| Medium | 1 | 2 |
| Expanded | 2 | 1 |
| Large | 2 | 1 |
| Extra-large | 2 | 1, 3 |

```text
Panes can be fixed or flexible. All layouts need at least one flexible pane.
Panes can be permanent or temporary. Temporary panes can appear and be dismissed when necessary, affecting the layout and size of other panes.

Single-pane layouts use one flexible pane and are recommended for compact and medium.
Split-pane layouts keep the spacer visually centered. They are useful for foldable devices and dynamic layouts.
Fixed-and-flexible layouts are common for expanded, large, and extra-large breakpoints.
At extra-large, a standard side sheet can be used as a third pane. Do not use more than three panes.

Drag handles can resize, expand, and collapse panes. In a split-pane layout, both flexible panes can be freely adjusted or snapped to widths. In a fixed-and-flexible layout, the drag handle can fully collapse and expand the fixed pane. The handle should also toggle between layout sizes when selected.

Recommended custom widths at expanded, large, and extra-large breakpoints are 360dp, 412dp, or a split-pane layout with the spacer visually centered.
Persistent resizing remembers a person's pane-width preference, including after closing the application and across breakpoint changes.
Temporary resizing does not remember the preference. It is intended primarily for supporting pane layouts where resizing is uncommon.
```

## Accessibility considerations

```text
Co-planar panes: Focus order should match the visual arrangement of panes.
Modal floating panes: Elements behind the pane cannot be interacted with. Focus moves to the first element in the pane and returns to the trigger when the pane closes.
Non-modal floating panes: Other parts of the product remain usable. Focus can move to and from the pane, and the pane must be available in a logical reading order.
Docked panes: Follow the relevant modal/non-modal focus requirements, and their focus order matches the visual arrangement of panes.
```

## Relationship to Axismundi decisions

Do not infer an Axismundi implementation decision directly from this transcript. Use `DECISION-FRONTEND-ADAPTIVE-LAYOUT.md` for the current implementation boundary, VQA order, and deferred work.
