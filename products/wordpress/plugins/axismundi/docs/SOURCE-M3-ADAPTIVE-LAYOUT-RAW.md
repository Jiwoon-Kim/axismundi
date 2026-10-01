# Material 3 Adaptive Layout Raw Source

## Purpose

Paste unedited Material 3 and Figma source text here. Preserve source wording, ordering,
tables, and links. Do not rewrite this file during implementation work.

## Source URLs

- https://www.figma.com/design/ixQuHzVDPis0DYdaoMixyP/Material-3-Design-Kit--Community-?node-id=55594-2480
- https://m3.material.io/foundations/layout/layout-overview/overview
- https://m3.material.io/foundations/layout/layout-overview/parts-of-layout
- https://m3.material.io/foundations/layout/layout-overview/adaptive-design
- https://m3.material.io/foundations/layout/scaffold/overview

<!-- Paste the raw source below this line. -->
<head></head>

이제 레이아웃 들어갈건데 나중에 opus handoff가능한수준으로 레퍼런스랑 같이 문서화. json으로 추상화해도 되고.

[https://www.figma.com/design/ixQuHzVDPis0DYdaoMixyP/Material-3-Design-Kit--Community-?node-id=55594-2480](https://www.figma.com/design/ixQuHzVDPis0DYdaoMixyP/Material-3-Design-Kit--Community-?node-id=55594-2480)

Layout grid

properties name, value

Window size class

1. Compact (0-599dp)

2. Medium (600-839dp)

3. Expanded (840-1199p)

4. Large (1200-1599dp)

5. Extra-large (1600+dp)

Layout regions

None

Navigation region

Navigation expanded

[https://m3.material.io/foundations/layout/layout-overview/overview](https://m3.material.io/foundations/layout/layout-overview/overview)

- Use layout to organize all elements in a screen, signal hierarchy, and draw attention to key actions
- Adapt layouts to compact, medium, expanded, large, and extra-large  breakpoints (previously window size classes)
- Build from an established [canonical layout example](https://m3.material.io/m3/pages/canonical-examples)
- Design for [bidirectionality](https://m3.material.io/m3/pages/bidirectionality-rtl) to support both left-to-right (LTR) and right-to-left (RTL) languages
- Apply consistent arrangement, sizing, and spacing to create a functional layout structure
- Material layout guidance is implemented on Android and applies to web

1. Column
2. Fold
3. Margin
4. Bar
5. Drag handle
6. Pane
7. Rail

## Layout terms

- **Adaptive design**: Techniques that allow an interface to dynamically respond to contexts like user preferences, device type, state, and breakpoints
- **Bars**: Can frame the page to help people navigate through a product, and typically house the app bar and bottom navigation bar
- **Bidirectionality**: A writing system that displays text and content from right-to-left (RTL)
- **Breakpoints**: Opinionated window sizes where a layout changes to match available space, device conventions, and ergonomics (previously window size classes)
- **Column**: One or more vertical blocks of content within a pane
- **Drag handle**: The component that resizes panes
- **Fold**: A flexible area of the screen or a hinge that separates two displays on foldable devices
- **Gap**: The space between components or elements within a container
- **Margin**: The space between the edge of the screen and any elements inside of it
- **Multi-window mode**: Enables multiple apps to share the same screen simultaneously
- **Pane**: A layout container that houses other components and elements within a single app. A pane can be fixed, flexible, floating, or semi-permanent.
- **Rails**: The perimeter space surrounding panes that holds key elements such as navigation rails, toolbars, and pane control
- **Right-to-left (RTL) language**: Languages written and read right-to-left, such as Arabic, Hebrew, and Farsi, used by [over 2 billion people](https://www.w3.org/International/questions/qa-scripts.en.html)
- **Rulers**: An opinionated set of global alignment lines that help organize building blocks in a layout
- **Safety region**: Zones reserved for system UI elements outside the application space, such as status bar or gesture bar
- **Scaffold**: A fundamental UI design structure that provides a standard platform for assembling key screen components
- **Spacer**: The space between two panes on a foldable device

[https://m3.material.io/foundations/layout/layout-overview/parts-of-layout](https://m3.material.io/foundations/layout/layout-overview/parts-of-layout)

## Parts of layout

### Windows

A window frames and contains an app or product.

Many systems support multi-window views, which display multiple apps at once.

[Multi-window support guide for Android](https://developer.android.com/develop/ui/compose/layouts/adaptive/support-multi-window-mode)

On desktop, windows can be resized and moved around freely. They should adapt to various screen sizes.

[More on adaptive design](https://m3.material.io/m3/pages/layout-overview/adaptive-design)

### Grids

The layout grid is the foundation for every layout. It provides a structural framework for organizing components, content, and actions.

Use the grid to:

- Group related information in columns
- Apply spacing consistently
- Create focal points for primary actions
- Align building blocks like bars, rails, and panes

[More on grids](https://m3.material.io/m3/pages/grids-spacing/grids)

Column count, width, and spacing dynamically adjust to different breakpoints 

## Layout scaffold

### Bars

Bars help people navigate through a product. Use bars to:

- Frame the main content
- Contain an app bar or navigation bar
- Span one or multiple panes

[More on bars](https://m3.material.io/m3/pages/scaffold/bars)

1. App bars are placed at the top of the screen to help people navigate by providing a description of the screen and 1–2 essential actions, like search or back navigation

### Rails

Rails are the next level in layout after bars, filling the perimeter space surrounding panes, or floating above them. They contain key elements such as navigation rails, toolbars, chat inputs, FABs, and other primary controls.

[More on rails](https://m3.material.io/m3/pages/scaffold/rails)

1. On mobile, the rail region can contain a toolbar
2. On desktop, the rail region can contain the navigation rail

### Panes

Just like panes of glass that make up a window in the real world, panes in Material make up most of the layout in a window.

All content must be in a pane. A layout can contain 1–3 panes of various widths, which adapt dynamically to the breakpoint (formerly window size class) and the person’s language setting. For right-to-left (RTL) languages, navigation components will be on the right.

People can navigate to or between panes. Presenting multiple panes at once can make a product more efficient and easier to use.

[More on panes](https://m3.material.io/m3/pages/scaffold/panes/)

#### Containment

On most devices, panes can blend in with the background. This is called implicit grouping, and helps show relationships between panes.

Explicit grouping uses distinct colors or outlines to visually delineate content.

[More on spacing to group content](https://m3.material.io/m3/pages/grids-spacing/spacing#e7e6d1ac-031a-4757-afcf-b223f23654ea)

In multiple-pane layouts, use color to show emphasis and close spacing to group related content 

In spatial environments, panes use a container color to separate them from the passthrough or virtual environment.

### Drag handles

Drag handles can be used to resize panes in a layout. They can:

- Adjust the width of flexible panes
- Fully collapse and expand fixed panes to quickly switch between a single and two-pane layout

### Rulers

Rulers are a set of global alignment lines. They help to align elements across all layers of the layout.

[How to implement rulers in Compose](https://developer.android.com/reference/kotlin/androidx/compose/ui/layout/Ruler)

Rulers ensure global alignment across a product, keeping margins and placement consistent 

[https://m3.material.io/foundations/layout/layout-overview/adaptive-design](https://m3.material.io/foundations/layout/layout-overview/adaptive-design)

## What’s adaptive design?

Adaptive design is a set of techniques to change an interface to fit different contexts. While responsive design scales a single layout to fit any screen, adaptive design customizes a product to optimize the experience on each device.

Designing adaptive experiences goes beyond customizable properties like color, typography, and shape. The structure, individual components, and entire layouts can adapt based on:

- People: Individual preferences and settings
- Devices: Watch, phone, foldable, tablet, desktop, or XR device
- Usage: Screens dynamically change as a person resizes windows, changes orientation, or switches device

## Designing adaptive experiences

Layouts must be versatile, designed to adapt fluidly across three primary experience types: **mobile**, **desktop**, and **spatial**. Start with mobile and make sure your product's layout and components can scale and adapt seamlessly all the way up to spatial environments.

While each experience has different primary input methods, designs should be built with all inputs in mind—touch, pointer, and physical keyboard—since users may use your product in a desktop environment regardless of their device type.

### Mobile

Mobile experiences include phones, foldables, and tablets.

On mobile, an app can be shown in several window modes:

- Full-screen: The app takes up the entire screen, the default for mobile
- Split-screen: Two or more apps share the screen simultaneously, common on tablets and foldables
- Bubbles: Floating windows that let people multitask without leaving their current context

Mobile layouts default to a full-screen window 

### Desktop

Desktop experiences use free-form windows that adapt across breakpoints.

People can use split screens, floating windows, and free-form windows for multi-tasking.

A tablet can convert to a desktop experience when a physical keyboard and mouse are connected. Similarly, Android mobile devices can transition into a desktop-like environment when connected to an external monitor.

A desktop layout can adjust from three to two columns to fit a medium breakpoint 

### Spatial

Extended reality (XR) experiences use multiple free-form windows within virtually limitless screens. Immersive modes, such as Android XR’s [full space](https://developer.android.com/design/ui/xr/guides/foundations), allow components to be positioned freely in 3D space.

[More on XR design](https://m3.material.io/m3/pages/xr-design)

## Adaptive layouts

The Material 3 adaptive system uses panes and breakpoints to organize content into adaptive layouts.

Panes are the building blocks of layout; a pane is a single destination in the product. For example, in a messaging app, the list of messages is one pane, and and a specific conversation thread is another.

Panes are the primary segments of a layout, and can change based on context 

As the pane or window resizes—or as someone navigates a product—panes may change size, enter and exit the screen, and reorganize themselves to make the experience more usable or easier to navigate. These patterns are called adaptive strategies. Material has three adaptive strategies that create a cohesive experience across breakpoints: [show and hide](https://m3.material.io/m3/pages/scaffold/panes#bbe68948-bc05-4f7c-b870-6254439e4fd8), [levitate](https://m3.material.io/m3/pages/scaffold/panes#96bf71b8-04b8-4fff-97c7-9bc782fbf401), and [reflow](https://m3.material.io/m3/pages/scaffold/panes#e0a573e9-8c62-4772-8d81-47955ff83196).

Co-planar: Panes are displayed side by side

[A foldable open screen with a floating pane displayed above other elements.](https://firebasestorage.googleapis.com/v0/b/design-spec/o/projects%2Fgoogle-material-3%2Fimages%2Fmp3xtdfv-Placeholder%20%281%29.png?alt=media&amp;token=5825b45f-fde6-46a5-a345-946dcadef428)

Floating: A pane is displayed above other panes or content, like a dialog

[A foldable open screen with a docked pane to the bottom of the screen displayed above other elements.](https://firebasestorage.googleapis.com/v0/b/design-spec/o/projects%2Fgoogle-material-3%2Fimages%2Fmp3xuwp5-Placeholder%20%28Cmd%2BV%20to%20replace%29.png?alt=media&amp;token=cb3ac17e-eb33-48f7-9297-1bee14918ffe)

Docked: A pane is displayed above other panes and one of its edges extends beyond one side of the screen, like a bottom sheet

In Compose, the [Navigation 3](https://developer.android.com/guide/navigation/navigation-3) library allows multiple destinations to be shown on screen at the same time, and enables layouts to adapt seamlessly across window sizes and screens. 

Navigation destinations remain consistent regardless of screen sizes with Navigation 3 

### Adapting components

Components can adapt in appearance, placement, and behavior based on factors like:

- Where components are placed in relation to their containers, content, and pane boundaries
- How components use space
- How components enable usage across different device and input types

Most Material components respond using three main strategies: resizing, showing and hiding, and presentation changes.

#### Resizing

Components should resize in response to their content and their placement in a layout.

For example, buttons may scale along with their parent container, or hug their contents and maintain a left or right alignment.

Buttons can hug their contents or span their containers based on context 

#### Showing & hiding

Components should show and hide information, or collapse and expand to selectively reveal content that best suits the space.

For example, list items may reveal descriptions or other additional information as their parent container scales.

List items can reveal more text on a tablet 

#### Presentation changes

Presentation changes include the orientation of elements and changes to specific properties, like color, type, and shape.

Components can also change configurations. For example, when a window size increases, a FAB can change to an extended FAB, and navigation rails can be automatically expanded.

[https://m3.material.io/foundations/layout/scaffold/overview](https://m3.material.io/foundations/layout/scaffold/overview)

- The layout scaffold structures every piece of an adaptive layout into bars, rails, and panes
- Bars can frame the page to help people navigate through a product
- Rails create the perimeter space surrounding panes, creating space for elements like navigation and toolbars
- Panes hold a product’s primary content, adapting to breakpoints (previously window size classes) and other conditions

1. Safety region
2. Bar
3. Pane
4. Rail

## Bars

Bars frame the screen to help people navigate through a product. They typically contain an app bar or bottom navigation bar.

Bars can span a single pane or across the full width of a window.

1. A navigation bar occupies the bottom bar region on mobile
2. An app bar occupies the top bar region on the web

App bars are placed at the top of the screen to help people navigate, providing a title and 1–2 essential actions like search or back.

1. The app bar sits at the top of the screen, outside of the safety region

Navigation bars let people switch between 3–5 primary UI views at compact or medium breakpoints. 

1. The navigation bar sits at the bottom of the screen, above the safety region

### Safety region

Bars are placed adjacent to the safety regions, which contain [system UI](https://developer.android.com/develop/ui/compose/system/system-bars) elements.

The safety region shouldn’t contain primary content.

1. The safety region—at the top and bottom edges of the screen on compact devices—protect system UI elements

## Rails

Rails are the next level in layout after bars, filling the perimeter space surrounding panes or floating above them.

Rails occupy the spaces immediately adjacent to bars:

1. A toolbar sits above the navigation bar
2. A navigation rail and companion rail occupy the leading and trailing sides of a large window

On compact screens, the top and bottom rail regions can be used for components like:

- Toolbars
- Chat inputs
- FABs
- Other primary controls related to an individual screen

On larger screens, there are rails on the sides of the screen (as well as top and bottom). The leading side rail region commonly holds the navigation rail. 

At larger breakpoints, the leading rail region can be occupied by an expanded navigation rail 

The rail region on the trailing side of a large screen can hold supporting controls or actions that modify or relate to the content in a pane. 

The rail region can also be occupied by a vertical toolbar or other controls 

## Panes

All layouts are made up of 1–3 panes. The type of layout and amount of panes you choose should depend on the  breakpoint (previously window size classes) and the type of product being built.

Layouts often include multiple panes that work together 

All layouts are made up of 1–3 visible panes. The type of layout and amount of panes you choose should depend on the breakpoint and the type of product you're building.

| Breakpoint | Recommended pane total | Other pane totals |  
  
| --- | --- | --- |  
  
| Compact | 1 | -- |  
  
| Medium | 1 | 2 |  
  
| Expanded | 2 | 1 |  
  
| Large | 2 | 1 |  
  
| Extra-large | 2 | 1, 3 |

Panes can be:

- Fixed: Width doesn’t change based on available space
- Flexible: Width changes based on available space, and can grow and shrink

All layouts need at least one flexible pane.

Panes can be permanent or temporary. Temporary panes can appear and be dismissed when necessary, affecting the layout and size of other panes. 

Panes can be displayed permanently side by side 

Temporary panes can be dismissed 

### Single-pane layouts

Single-pane layouts use one flexible pane that extends to fit the available space in a layout’s width. They can be used at any breakpoint, but are recommended for compact and medium.

A single flexible pane adapts to fit any breakpoint 

### Two-pane layouts

**Split-pane layout**

A split-pane layout keeps the spacer visually centered. It’s best for foldable devices and dynamic layouts.

When a navigation rail or drawer is present, it only reduces the size of one pane. The other pane remains at 50% of the window width.

The navigation and first pane should be 50% of the window width to keep the spacer centered 

With a navigation bar, or no navigation, both panes span 50% of the window width by default. 

With no navigation rail visible, split-pane layouts set each pane to 50% width by default 

**Fixed-and-flexible layout**

This layout is common for expanded, large, and extra-large breakpoints. The fixed-and-flexible panes can appear in whichever order is best for the content.

The fixed pane is often temporary, and used for side sheets or lists with light information density.

### Three-pane layouts

While less common, the extra-large breakpoint supports using a standard side sheet as a third pane. When the side sheet is present, the expanded navigation rail can remain visible, change into a collapsed navigation rail, or hide completely. Don't use more than three panes. 

Note: Fixed panes at this breakpoint are recommended to be 412dp, but side sheets have a default maximum width of 400dp. 

1. A standard side sheet can be used as a third pane

## Pane expansion & resizing

Panes can be resized, expanded, and collapsed using  drag handles. 

- In a split-pane layout, both flexible panes can be freely adjusted, or can snap to certain widths.
- In a fixed-and-flexible layout, the drag handle can fully collapse and expand the fixed pane. This makes it easy to switch between a single-pane and two-pane layout.

The drag handle should also toggle between layout sizes when selected. This can be a tap, double tap, or long press.

pause

Drag handles can adjust pane size in a list-detail layout

At expanded, large, and extra-large breakpoints, two-pane layouts can be customized to snap to set widths when resized.

The recommended custom widths are:

- 360dp
- 412dp
- Split-pane with spacer centered visually

Panes can snap to custom widths when releasing the drag handle 

### Persistent pane resizing

The persistent resizing behavior remembers a person's pane width preference. Use this for most resizable layouts.

Pane widths persist even after a person closes the app 

The width persists even after a breakpoint change. This means that if a two-pane layout is collapsed to one pane at any size, it’ll remain collapsed even when changing breakpoints. 

When a two-pane layout is resized to a single full-width pane, that pane should remain at full-width after switching breakpoints 

### Temporary pane resizing

The temporary resizing behavior doesn't remember a person’s preferences for pane width. This is primarily used in supporting pane layouts where resizing is uncommon.

Supporting pane layouts can have a pane drag handle to temporarily resize the secondary content 

With temporary resizing, panes should always return to the default layout after the pane or product is closed and reopened. This ensures content is a suitable size for most interactions. 

pause

The pane width can be temporarily adjusted using the drag handle, but will return to the default layout

## Displaying multiple panes

Multiple panes can be displayed in three ways: co-planar, floating, or docked. The layout depends on breakpoint, what the pane does, and how people interact with it:

- Co-planar: Two side-by-side panes. To stay accessible, persistent utilities like tool panels should be co-planar with primary content.
- Floating: A small pane displays above larger panes. Temporary tasks should remain floating regardless of breakpoint, such as a dialog.
- Docked: A small pane pinned to the edge of a window. For example, a bottom sheet can be docked to show additional actions.

Co-planar: Panes are displayed side by side

[A foldable open screen with a floating pane displayed above other elements.](https://firebasestorage.googleapis.com/v0/b/design-spec/o/projects%2Fgoogle-material-3%2Fimages%2Fmp3oxadg-16.png?alt=media&amp;token=be699075-a53f-4149-ba51-7f0d41116d9e)

Floating: A pane is displayed above other panes or content, like a dialog

[A foldable open screen with a docked pane to the bottom of the screen displayed above other elements.](https://firebasestorage.googleapis.com/v0/b/design-spec/o/projects%2Fgoogle-material-3%2Fimages%2Fmp3oxtwa-17.png?alt=media&amp;token=8ec788eb-43eb-4a88-9ed9-12ae8a069de4)

Docked: A pane is displayed above other panes and one of its edges extends beyond one side of the screen, like a bottom sheet

## How panes adapt

Pane layouts can adapt using three strategies: **show and hide, levitate,** or **reflow**. When a window is resized or changes orientation, these strategies allow panes to reorganize themselves to preserve context and meaning.

### Show and hide

As the breakpoint size or orientation changes, panes can enter and exit the screen or appear next to one another.

pause

A pane can be shown or hidden depending on the available space and orientation

### Levitate

Panes can be elevated above other content as **floating** or **docked** panes. This strategy helps panes appear relative to their triggers.

Floating panes:

- Appear in front of the body content
- Can be customized to be dragged or resized

When adding controls that resize or move a floating pane, provide accessible controls.

A co-planar pane can float when switching breakpoint or orientation 

On large screens:

- Floating panes are the default
- The scrim behind a floating pane is optional

1. Floating pane with a scrim
2. Floating pane without a scrim

Docked panes are usually at the bottom of the window, like a  bottom sheet.

At medium and expanded breakpoints, docked panes can adapt into floating panes.

A docked pane can adapt into a floating pane at medium and expanded breakpoints 

Alternatively, at medium and expanded breakpoints, a docked pane can adapt into a co-planar pane.  

A docked pane can also adapt into a co-planar pane at medium and expanded breakpoints 

On large screens, consider changing docked panes into co-planar panes. 

1. A compact screen can have a docked pane
2. On a large screen, it should change to a co-planar pane

### Reflow

Panes can be reorganized on screen as the breakpoint or orientation changes, also known as reflow.

For example, in a vertical orientation, the supporting pane can move underneath the primary pane.

In a vertical orientation, the supporting pane can move below the primary pane 

Reflow also applies to breakpoints. When there’s not enough horizontal space for panes, they can stack vertically instead. 

Panes can change size, location, and orientation when switching screen sizes

## Spatial panels

On XR devices, pane layouts can be presented in disconnected  spatial panels. These panels must have clear containment to make them easy to see on any background.

The content in a spatial panel can use implicit grouping when the pane has an explicit container to distinguish it from the environment. 

## Accessibility considerations

**Coplanar panes**

- The focus order should match the visual arrangement of the panes on screen

**Floating panes**

Modal floating pane:

- When active, the elements behind it can’t be interacted with
- Focus moves automatically to the first element in the pane, and when the pane is closed, focus moves back to the element that triggered it, like a dialog
- If triggered automatically, focus should still move to it, but when it’s closed, focus should go to the next most logical element on screen
- It disappears when a person interacts with something behind it.

Non-modal floating pane:

- When open, other parts of a product can be interacted with
- Focus should be able to move to and from the pane
- The pane should be available in a logical reading order of the screen

**Docked panes**

- Have the same focus requirements as modal and non-modal panes
- The focus order should match the visual arrangement of the panes on screen

이거 그냥 영어원문 그대로 문서화해둬도 되겠는데