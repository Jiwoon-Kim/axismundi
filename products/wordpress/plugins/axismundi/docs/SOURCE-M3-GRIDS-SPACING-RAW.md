# Material 3 Grids and Spacing Raw Source

## Purpose

Paste unedited Material 3 source text here. Preserve source wording, ordering, tables, and
links. Do not rewrite this file during implementation work.

## Source URLs

- https://m3.material.io/foundations/layout/grids-spacing/overview
- https://m3.material.io/foundations/layout/grids-spacing/grids
- https://m3.material.io/foundations/layout/grids-spacing/spacing
- https://m3.material.io/foundations/layout/grids-spacing/density
- https://m3.material.io/foundations/layout/breakpoints/overview
- https://m3.material.io/foundations/layout/breakpoints/compact
- https://m3.material.io/foundations/layout/breakpoints/medium
- https://m3.material.io/foundations/layout/breakpoints/expanded
- https://m3.material.io/foundations/layout/breakpoints/large-extra-large

<!-- Paste the raw source below this line. -->
<head></head>

지금 spacing도 theme.json에 있기때문에 프론트앱은 토큰파일 독립적으로 가져야함. 그리드도 같이가도되고. 이것도 문서화를 먼저하거나 보존해두는게 나을듯

[https://m3.material.io/foundations/layout/grids-spacing/overview](https://m3.material.io/foundations/layout/grids-spacing/overview)

- Grids create a consistent foundation and adapt across breakpoints (previously window size classes)
- Use spacing to group related information and direct people’s attention to key actions
- Density helps people see and compare more information in data-heavy views

Layouts in Material are based on a grid that adapts across all screen sizes 

[https://m3.material.io/foundations/layout/grids-spacing/grids](https://m3.material.io/foundations/layout/grids-spacing/grids)

- Layouts in Material are based on a grid that adapts across all breakpoints (previously window size classes)
- Parts of the layout scaffold like rails and panes are positioned on this grid to create consistent adaptive layouts
- The structure and spacing values used in a grid can add personality to a product’s layout

## How to use grids

### Start with placing grid columns

Grids adapt across breakpoints. As the size increases, column count, width, and spacing change as well.

The number and size of columns changes based on breakpoints 

When moving between sizes, column count may increase to show more content or controls. 

On compact screens, fewer columns are used to create a focused layout 

As screen size increases, for example when a foldable screen is unfolded, additional columns allow for a richer layout 

### Place bars & rails

Populate regions of the layout scaffold that are closest to the edges of the screen’s usable space first. This may include:

- Bars like the navigation bar and rail
- Components like toolbars and app bars

The bar region can contain a toolbar 

The rail region on larger screens usually contains a navigation rail

### Place panes

Next, populate the main region of the screen with panes with content and components, based on available space and structure.

See the [canonical layout examples](https://m3.material.io/m3/pages/canonical-examples) for ideas on which panes are appropriate for a product.

1. Primary pane
2. Supporting pane

## Rulers & alignment

Rulers are a set of recommended global alignment lines that help create consistent focal points in a product, while keeping content and components consistently aligned.

[How to implement rulers in Compose](https://developer.android.com/reference/kotlin/androidx/compose/ui/layout/Ruler)

1. Margin
2. Bar or safety region
3. Title
4. Content 1
5. Content 2
6. Content 3
7. Content 4
8. Bar or safety region
9. Rail

### Bar & safety rulers

Bar and safety rulers reserve space for [system UI](https://developer.android.com/training/system-ui) elements like the status bar and gesture navigation.

They ensure actionable content like app bars aren’t covered by system UI.

Bar and safety rulers align to the edges of a screen’s usable space, providing a reference for where system UI like the status bar or gesture navigation appear

### Title rulers

The title ruler creates consistency for the screen’s title, aligning the text, icons, and other components in an app bar.

The title ruler aligns with the title in an app bar

### Content rulers

Use content rulers to align and anchor key content, such as headlines and carousels.

- First content ruler: Emphasizes major blocks like hero images, headlines, or primary components
- Secondary rulers: Determine where supplementary text or actions begin

Content rulers offer flexible alignment options to help create a consistent layout across a product 

Realigning primary components or content to a content ruler can create strong hierarchy and visual rhythm across a product 

### Ruler options

Margin rulers come with some wiggle room to determine how tight or loose a product’s content feels on-screen. The standard ruler can be adjusted to the left or right.

Choosing a narrower or wider margin can create or remove negative space, or create expressive moments in a content-forward product.

Margin rulers can adjust to create more or less negative space 

Rulers can also be used to create more immersive experiences. For example, a photo grid can take the full width of the screen, while components like search use wider margins. 

Rulers allow components and media to use different margin widths 

[https://m3.material.io/foundations/layout/grids-spacing/spacing](https://m3.material.io/foundations/layout/grids-spacing/spacing)

- Spacing helps group content, direct attention, and shape the personality of a product
- A denser layout can feel more serious and focused, while a more spacious layout can feel calm and open
- Material’s spacing system can adapt to breakpoints and density settings. [More on the spacing system](https://m3.material.io/m3/pages/spacing/overview)

Desktop layouts can use more generous spacing than mobile layouts

## Spacing to group content

Grouping connects related elements that share context, such as an image and its caption. Use spacing to visually tie elements together and establish boundaries between unrelated items. 

Placing a caption under an image creates an implicit group 

**Explicit grouping** uses visual boundaries like outlines, dividers, and shadows to group related elements in an enclosed area.

It can also indicate that an item is interactive, such as:

- List items between dividers
- A card displaying an image and its caption

Outlines define clear boundaries to explicitly group elements 

**Implicit grouping** uses close proximity and open space (rather than lines and shadows) to group related items.

For example, the items in a carousel are placed close together, with space around the composition to separate them from other content.

Close spacing implicitly groups carousel images 

## Spacing to direct attention

Use rhythm, similarity, and other grouping principles to distinguish and highlight important elements.

### Rhythm

Consistent spacing between related elements or groups makes them easier to navigate with the eye.

Cards should maintain consistent horizontal spacing to establish a strong rhythm when their height varies 

### Similarity

Similar elements should have the same spacing and sizing in a layout to show they’re related.

Leading elements like thumbnails, avatars, or icons should always be aligned.

Thumbnails in a shopping basket should use identical sizes and styles to signal that each one represents a product, even if the original photos have different aspect ratios 

### Proximity

Place components near each other to create cohesive groups. This helps people understand the relationships between information and actions.

For example, buttons should be close to the content they’re affecting.

Placing two buttons as a group near content implies they’ll both affect it in similar ways

### Continuity

Place related elements in a container, row, or column to establish a clear group or relationship.

Use a row of chips to signal a single, unified control 

## Spacing as expression

Give the most important content, tasks, or actions visual prominence with generous spacing and the brightest surfaces.

### Focal points

Consistent placement of key actions and information helps build recognizable focal points across a product.

Carousel images, categories, and titles should appear in a consistent location across pages 

### Negative space

Allow negative space to give form and meaning to elements on screen. Framing important actions or content with generous spacing creates emphasis.

Negative space gives shape and emphasis to the course header 

[https://m3.material.io/foundations/layout/grids-spacing/density](https://m3.material.io/foundations/layout/grids-spacing/density)

- Information density is the consideration of the amount of information visible on the screen
- The default target size should be at least 48x48 CSS pixels
- People can change density as long as the density controls are accessible
- Apply density thoughtfully; not every layout needs it
- Layout and component scaling (component adaptation or component density) can allow people to scan, view, or compare more information at once

Information density can change based on context and preference 

Consider whether components should scale

**Information density**

- Information density can be achieved through layoutand design decisions without using componentscaling
- Some people may not benefit from increased density

**Component scaling**

- Components can adapt and change dimensions to help people scan, view, or compare different amounts of information
- Don't apply component scaling by default if it would result in a target below 48x48 CSS pixels

Information density and component scaling can be used together to provide more information and additional user control 

## Information density

Information density refers to the amount of content (such as text, images, or videos) in a given space.

A layout’s spacing dimensions, including margins, spacers, and padding, can change to increase or decrease its information density. High density layouts are useful when people need to scan, view, or compare a lot of information, such as in a data table. Increasing the layout density of lists, tables, and long forms makes more content available on-screen.

Consider density settings in the context of a device. Although a person may prefer a denser layout for desktop, they may not for mobile. Density shouldn’t automatically change across breakpoints or orientation unless a person changes it.

Consider using higher density information design when people need to scan lots of information 

Consider the amount and priority of information on-screen. Higher density can be useful for data-rich products where people expect to scan lots of information quickly. Examples: News, financial portals, dashboards 

Lower density can be better for sites prioritizing aesthetics, a focused message, less information, or easier navigation 

## Component scaling

The component density scale controls the internal spacing of individual components.

The density scale is numbered, starting at 0 for a component’s default density. The scale moves to negative numbers (-1, -2, -3) as space decreases, creating higher density.

Higher density is typically applied by decreasing the top and bottom padding or overall height by 4dp.

Apply component density based on the needs and layout of a design 

Center the grouped element within the component container.

Text size shouldn’t change as the container size scales.

The measurement between the label and input is 20dp 

The label and input are centered within their parent container

Don’t increase density in UIs that involve focused tasks, such as selecting from a menu. It reduces usability by limiting selectable space. 

Don't increase the density in components that alert a person of changes, such as snackbars or dialogs 

### Avoid applying component scaling by default

People should be able to **opt in** to dense layouts and components.

To ensure density settings can be easily reverted, settings interactions must use default target sizes (48x48 CSS pixels). 

Don't scale layouts below 48x48dp by default.

People can opt in to dense layouts in settings

## Targets

Dense components can be less accessible because interactive elements are smaller, so use caution when increasing information density.

Use caution when applying component scaling where selectable targets will be reduced to less than 48x48dp. Only apply density where it provides a better experience. 

Use caution when applying density to interaction targets. Accessible targets should retain a minimum of 48x48dp, even if the visual element, such as an icon, is smaller.

The target should remain 48x48dp, even if the icon is smaller 

The interaction target of a common button can be larger, as long as it meets the 48x48dp minimum size 

## Pixel density

Pixel density is the number of pixels per inch. High-density screens have more pixels per inch than low-density ones. Elements with the same pixel dimensions appear larger on low-density screens and smaller on high-density screens.

To calculate pixel density:

Pixel density = Screen width (or height) in pixels / Screen width (or height) in inches

High-density elements have more pixels per inch 

Low-density elements have fewer pixels per inch 

### Density-independent pixels

Density-independent pixels, written as dp, are flexible units that scale to have uniform dimensions on any screen. They provide a flexible way to accommodate a design across devices. The Material design system uses density-independent pixels to display elements consistently on screens with different densities.

A dp is equal to one physical pixel on a screen with a density of 160.

To calculate dp:  
dp = (width in pixels \* 160) / screen density

Low-density screen displayed with density independence 

High-density screen displayed with density independence 

| Screen physical width | Screen density | Screen width in pixels | Screen width in dps |  
  
| --- | --- | --- | --- |  
  
| 1.5 in | 120 | 180 px | 240dp |  
  
| 1.5 in | 160 | 240 px |  
  
| 1.5 in | 240 | 360 px |

* * *

[https://m3.material.io/foundations/layout/breakpoints/overview](https://m3.material.io/foundations/layout/breakpoints/overview)

Material uses breakpoints to create adaptive designs that work across devices:

- There are five main breakpoints: compact, medium, expanded, large, and extra-large
- Layouts typically transition from a single pane to two or three panes as window size increases
- When moving across breakpoints, decide which elements to reveal, divide, resize, reposition, or swap

## Breakpoints overview

A breakpoint (previously window size class) is the window size at which a layout needs to change to match available space, device conventions, and ergonomics. These apply to Android and web.

All devices fall into one of five Material breakpoints:

- Compact
- Medium
- Expanded
- Large
- Extra-large

Rather than designing for an ever-increasing number of display states, focusing on breakpoints ensures layouts work across a wide range of devices.

Large and extra-large breakpoints are used on devices like laptops, desktops, and external monitors. 

**Design for breakpoints instead of specific devices because:**

- The amount of available window space is dynamic and changes based on user behavior, such as multi-window modes or unfolding a foldable device
- Devices fall into different breakpoints based on orientation

| Breakpoint | Width (dp) | Common devices |  
  
| --- | --- | --- |  
  
| Compact | Under 600dp | Phone in portrait |  
  
| Medium | 600–839dp | Tablet in portrait&lt;br&gt;&lt;br&gt;Foldable in portrait (unfolded) |  
  
| Expanded | 840–1199dp | Phone in landscape&lt;br&gt;&lt;br&gt;Tablet in landscape&lt;br&gt;&lt;br&gt;Foldable in landscape (unfolded)&lt;br&gt;&lt;br&gt;Desktop |  
  
| Large | 1200–1599dp | Desktop |  
  
| Extra-large | 1600dp+ | Desktop&lt;br&gt;&lt;br&gt;Ultra-wide monitors |

### Height breakpoints

On Android, compact, medium, and expanded breakpoints are also available for [height](https://developer.android.com/develop/ui/compose/layouts/adaptive/support-different-display-sizes#window_size_classes). These can be used to adjust the layout when available vertical space is unusually small or large. However, since most layouts contain vertically scrolling content, it's rare that layouts need to adjust to available height.

## Designing across breakpoints

Products should automatically adapt to any breakpoint 

A product’s layout should adjust to fit each breakpoint. For example, a large window can have two panes, while an extra-large window can have three. 

Each product view should have a layout for the breakpoints most appropriate for your platform and users.

Different components are recommended for performing the same function across the five layouts.

| Breakpoint | Panes | Navigation | Communication | Action |  
  
| --- | --- | --- | --- | --- |  
  
| Compact | 1 | Navigation bar, modal expanded&lt;br&gt;&lt;br&gt;navigation rail | Simple dialog&lt;br&gt;&lt;br&gt;Full-screen dialog | Bottom sheet |  
  
| Medium | 1 (recommended) or 2 | Navigation bar, modal expanded&lt;br&gt;&lt;br&gt;navigation rail | Simple dialog | Menu |  
  
| Expanded | 1 or 2 (recommended) | Modal or standard expanded&lt;br&gt;&lt;br&gt;navigation rail | Simple dialog | Menu |  
  
| Large | 1 or 2 (recommended) | Modal or standard expanded&lt;br&gt;&lt;br&gt;navigation rail | Simple dialog | Menu |  
  
| Extra-large | 1 to 3 (recommended) | Modal or standard expanded&lt;br&gt;&lt;br&gt;navigation rail | Simple dialog | Menu |

Start by designing for one breakpoint, then adjust the layout for the next size by asking these five questions: 

### 1. What should be revealed?

Parts of the UI that are hidden on smaller devices can be revealed in larger layouts. 

For example:

- On mobile, the navigation rail is collapsed by default
- On an expanded device, the navigation rail can be open by default, revealing more actions and features

The same can be applied to [panes](https://m3.material.io/m3/pages/scaffold/panes). Larger layouts can simultaneously display an inbox pane and a pane containing a selected conversation. Additional space doesn’t just mean making the same thing bigger. 

### 2. How should a screen be divided?

When dividing a screen into layout panes, consider the breakpoint:

- Compact and medium breakpoints: A single pane works best
- Expanded and large breakpoints: Two panes are recommended
- Extra-large breakpoints: Consider using three panes

At medium breakpoints, two panes are useful when they contain low-density content with clear actions. 

Don’t use two panes in medium layouts with high information density, as it can reduce usability.

Single-pane layouts can focus attention on one action or view, creating a distraction-free environment for a specific goal such as:

- Playing a game
- Watching a movie
- Video calls
- Creative applications

### 3. What should be resized?

UI elements that are small on compact screens can grow as breakpoints increase. Panes can also expand to rearrange elements and make better use of space.

Consider resizing:

- Cards
- Feeds
- Lists
- Panes

Resizing can highlight imagery and improve text readability. This type of adaptation affects the scale of content and the relationship between objects on screen. For example, a vertical card on mobile can adjust its margins, orientation, text size, and density to better fit a tablet.

Across all breakpoints, adjust margins and type styles to keep text between 40–60 characters per line.

### 4. What should be repositioned?

A UI and its components can reflow or reposition to make use of additional space on expanded screens and in resized panes. Repositioning is also a way to match the ergonomic and input needs that change across device sizes, such as shifting actions from the bottom of a compact window to the leading edge of medium and expanded windows. This method is similar to responsive design on the web.

Consider:

- Repositioning cards
- Adding a second column of content
- Creating a more complex layout of photos
- Introducing more negative space
- Ensuring reachability for navigation and interactive elements

Internal elements can be anchored to the left, right, or center as a parent container scales. Internal elements can also maintain fixed positions, such as a floating action button (FAB) in a navigation rail. 

In the case of a button, the icon and text label within the button container can remain anchored to each other, staying centered as the button container scales horizontally. 

### 5. What should be swapped?

As a layout changes across breakpoints, components with similar functions can also be exchanged. This makes it possible to adjust a layout for large-scale changes to the ergonomic and functional qualities of an interface.

For example, a bottom navigation bar in a compact layout can be swapped with a navigation rail in a medium layout.

Swap a navigation bar in a compact layout for a navigation rail in a medium or expanded layout 

Likewise, a navigation rail can swap from collapsed to expanded at larger breakpoints.

Use caution when swapping components. Make sure:

- The interchangeable components are functionally equivalent
- The component swap serves a functional and ergonomic purpose

Don’t swap a button for a chip. Be careful when changing between list items and cards.

A collapsed navigation rail in medium or expanded layouts can become an expanded navigation rail in large or extra-large layouts 

Don’t arbitrarily swap components that aren’t functionally equivalent, such as swapping a button with a menu 

### Common swappable components

| Component type | Compact | Medium | Expanded |  
  
| --- | --- | --- | --- |  
  
| Navigation | Navigation bar | Collapsed navigation rail | Collapsed navigation rail |  
  
| Navigation | Modal expanded navigation rail | Modal expanded navigation rail | Standard expanded navigation rail |  
  
| Communication | Basic or full-screen dialog | Basic dialog | Basic dialog |  
  
| Supplemental selection | Bottom sheet | Menu | Menu |

[https://m3.material.io/foundations/layout/breakpoints/compact](https://m3.material.io/foundations/layout/breakpoints/compact)

Layouts for compact breakpoints are for **screen widths smaller than 600dp.** 

## Navigation

Use a navigation bar or modal expanded navigation rail.

Place navigation components close to the edge of the screen where they’re easier to reach.

## Panes

Use a single pane in compact layouts.

## Spacing

Margins are 16dp from the leading and trailing edge of the window.

## Special considerations

A compact layout will need to transition dynamically to a medium or expanded layout when:

- A foldable device is unfolded
- A mobile device is rotated from portrait to landscape
- A tablet exits split-screen mode
- A product is resized to be larger in multi-window mode
- A free-form window is resized

[https://m3.material.io/foundations/layout/breakpoints/medium](https://m3.material.io/foundations/layout/breakpoints/medium)

Layouts for medium breakpoints are for **screen widths from 600dp to 839dp.** 

## Navigation

Place navigation components close to edges of the window where they’re easier to reach:

- Single-pane layouts: Navigation rail
- Two-pane layouts: Navigation bar

The navigation rail can be hidden in secondary destinations as long as the primary destination can still be accessed using a back button.

## Panes

### Single-pane layout 

In a medium layout, a single pane is recommended because of limited screen width.

### Two-pane layout

Limit use of two panes for content with lower information density, such as a settings screen.

Each pane in a two-pane layout should take up 50% of the window width. Avoid setting custom widths. A drag handle can be used to expand or collapse panes to be 100% of the window width.

When adding navigation to a two-pane layout, use a navigation bar. This allows the panes to fully use the available window width. 

## Spacing

Medium layouts have margins of 24dp.

The spacer between panes is also 24dp.

## Special considerations

A medium layout will need to transition dynamically to a compact or expanded layout when:

- A foldable device is folded
- A tablet is rotated from portrait to landscape
- A product goes from full-screen to split-screen
- Multi-window mode is initiated
- A free-form window is resized

### Reachability

For horizontal tablets and unfolded foldables, the top 25% of the screen is likely out of reach, unless the grip is adjusted. To accommodate device and hand sizes, limit the amount of interactions that are placed in the upper 25% of the screen.

1. Limit interactions in the upper quarter of a screen, as they can be hard to reach

Avoid placing essential interactive elements too close to the bottom edge of the screen. Some users, particularly those with larger hands, might struggle to reach this area.

Specify interactions in a layout with these ergonomic regions in mind:

1. Users can reach this area by extending their fingers, which makes it inconvenient
2. Users can reach this area comfortably
3. Reaching this area is challenging when holding the device

[https://m3.material.io/foundations/layout/breakpoints/expanded](https://m3.material.io/foundations/layout/breakpoints/expanded)

Layouts for expanded breakpoints are for **screen widths from 840dp to 1199dp.** 

## Navigation

Place navigation components close to edges of the window where they’re easier to reach. Use a navigation rail, either collapsed or expanded.

The navigation rail can be hidden in secondary destinations as long as the primary destination can still be accessed using a back button.

For sorting, filtering, or secondary navigation, use tabs or other components directly in the pane.

## Panes

Use a single-pane or two-pane layout.

A two-pane layout is often best for expanded breakpoints. However, a single-pane layout can work when displaying visually- or information-dense content, such as videos.

When using a [fixed-and-flexible](https://m3.material.io/m3/pages/scaffold/panes#92371c3b-587d-4c6f-8105-05b69dcec81a) layout, the fixed pane should have a width of 360dp by default.

A [split-pane layout](https://m3.material.io/m3/pages/scaffold/panes#dc7982b7-754c-410a-9e88-18a54557c87b) uses two flexible panes and visually centers the spacer by default.  

## Spacing

Expanded layouts have a leading and trailing margin of 24dp.

The spacer between panes is 24dp.

## Special considerations

An expanded layout will need to transition dynamically to a compact or medium layout when:

- A foldable device is folded
- A tablet is rotated from landscape to portrait
- The app goes from full-screen to split-screen
- Multi-window mode is initiated
- A free-form window is resized

[https://m3.material.io/foundations/layout/breakpoints/large-extra-large](https://m3.material.io/foundations/layout/breakpoints/large-extra-large)

These breakpoints are most useful for creating web experiences tailored to laptop and desktop devices. Some products may not need large and extra-large breakpoints. Consider your platform’s conventions and users when making decisions on which breakpoints to design for.

- Layouts for large breakpoints are for screen widths **from 1200dp to 1599dp**
- Layouts for extra-large breakpoints are for screen widths of **1600dp and larger**

## Navigation

Use a navigation rail, either collapsed or expanded, depending on the amount of content. 

For sorting, filtering, or secondary navigation, use tabs or other components directly in the pane.

An expanded navigation rail is best suited for extra-large windows, where there's still plenty of room for content. Consider collapsing the navigation rail when space is needed, or when on pages deeper in the page hierarchy. 

## Panes

A two-pane layout is often best for large and extra-large breakpoints.  

However, a single-pane layout can work when displaying visually- or information-dense content, such as videos.

When using a [fixed-and-flexible](https://m3.material.io/m3/pages/scaffold/panes#92371c3b-587d-4c6f-8105-05b69dcec81a) layout, the fixed pane should have a width of 412dp by default.  

When using a [split-pane layout](https://m3.material.io/m3/pages/scaffold/panes#dc7982b7-754c-410a-9e88-18a54557c87b), the spacer should be visually centered by default, even when using an expanded navigation rail.  

## Additional panes

The extra-large breakpoint supports using a standard side sheet as a third pane. When the side sheet is present, the navigation rail can remain visible, collapse, or hide completely. Don't use more than three panes.  

Note: Fixed panes in this window size are recommended to be 412dp, but side sheets have a default maximum width of 400dp. 

1. Standard side sheet (third pane)

## Spacing

Large and extra-large layouts have a leading and trailing margin of 24dp.

The spacer between panes is 24dp.

## Special considerations

Large and extra-large layouts will need to transition dynamically to a smaller layout when:

- The app goes from full-screen to split-screen
- Multi-window mode is initiated
- A free-form window is resized

Pay attention to typographic elements such as line length to ensure readability on large and extra-large layouts.