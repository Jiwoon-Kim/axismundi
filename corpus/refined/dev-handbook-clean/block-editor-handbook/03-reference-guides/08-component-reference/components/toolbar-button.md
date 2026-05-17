---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-button/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: toolbar-button
parent_order: 3
sub_order: 8
page_order: 114
title: "ToolbarButton"
code_quality: degraded
code_issue: pre_newline_loss
---

# ToolbarButton

ToolbarButton can be used to add actions to a toolbar, usually inside a [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) or [ToolbarGroup](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-group/) when used to create general interfaces. If you’re using it to add controls to your custom block, you should consider using [BlockControls](../../../01-getting-started/02-fundamentals-of-block-development/block-in-the-editor.md).

It has similar features to the [Button](https://developer.wordpress.org/block-editor/reference-guide/components/button/) component. Using `ToolbarButton` will ensure the correct styling for a button in a toolbar, and also that keyboard interactions in a toolbar are consistent with the [WAI-ARIA toolbar pattern](https://www.w3.org/TR/wai-aria-practices/#toolbar).

## Usage

To create general interfaces, you’ll want to render ToolbarButton in a [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) component.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { Toolbar, ToolbarButton } from '@wordpress/components';import { pencil } from '@wordpress/icons'; function MyToolbar() { return ( <Toolbar label="Options"> <ToolbarButton icon={ pencil } label="Edit" onClick={ () => alert( 'Editing' ) } /> </Toolbar> );}
```

### Inside BlockControls

If you’re working on a custom block and you want to add controls to the block toolbar, you should use [BlockControls](../../../01-getting-started/02-fundamentals-of-block-development/block-in-the-editor.md) instead. Optionally wrapping it with [ToolbarGroup](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-group/).

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { BlockControls } from '@wordpress/block-editor';import { ToolbarGroup, ToolbarButton } from '@wordpress/components';import { pencil } from '@wordpress/icons'; function Edit() { return ( <BlockControls> <ToolbarGroup> <ToolbarButton icon={ pencil } label="Edit" onClick={ () => alert( 'Editing' ) } /> </ToolbarGroup> </BlockControls> );}
```

## Props

This component accepts [the same API of the Button](https://developer.wordpress.org/block-editor/reference-guide/components/button/#props) component in addition to:

#### containerClassName: string

An optional additional class name to apply to the button container.

- Required: No

#### subscript: string

An optional subscript for the button.

- Required: No

## Related components

- If you wish to implement a control to select options grouped as icon buttons you can use the [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) component, which already handles this strategy.
- The ToolbarButton may be used with other elements such as [Dropdown](https://developer.wordpress.org/block-editor/reference-guide/components/dropdown/) to display options in a popover.
