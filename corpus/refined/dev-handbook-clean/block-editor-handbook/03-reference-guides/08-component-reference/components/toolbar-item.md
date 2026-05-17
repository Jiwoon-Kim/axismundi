---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-item/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: toolbar-item
parent_order: 3
sub_order: 8
page_order: 117
title: "ToolbarItem"
code_quality: degraded
code_issue: pre_newline_loss
---

# ToolbarItem

A ToolbarItem is a generic headless component that can be used to make any custom component a [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) item. It should be inside a [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) or [ToolbarGroup](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-group/) when used to create general interfaces. If you’re using it to add controls to your custom block, you should consider using [BlockControls](../../../01-getting-started/02-fundamentals-of-block-development/block-in-the-editor.md).

## Usage

### as prop

You can use the `as` prop with a custom component or any HTML element.

```jsx
import { Toolbar, ToolbarItem, Button } from '@wordpress/components'; function MyToolbar() { return ( <Toolbar label="Options"> <ToolbarItem as={ Button }>I am a toolbar button</ToolbarItem> <ToolbarItem as="button">I am another toolbar button</ToolbarItem> </Toolbar> );}
```

### render prop

You can pass children as function to get the ToolbarItem props and pass them to another component.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { Toolbar, ToolbarItem, DropdownMenu } from '@wordpress/components';import { table } from '@wordpress/icons'; function MyToolbar() { return ( <Toolbar label="Options"> <ToolbarItem> { ( toolbarItemHTMLProps ) => ( <DropdownMenu icon={ table } toggleProps={ toolbarItemHTMLProps } label={ 'Edit table' } controls={ [] } /> ) } </ToolbarItem> </Toolbar> );}
```

### Inside BlockControls

If you’re working on a custom block and you want to add controls to the block toolbar, you should use [BlockControls](../../../01-getting-started/02-fundamentals-of-block-development/block-in-the-editor.md) instead. Optionally wrapping it with [ToolbarGroup](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-group/).

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { BlockControls } from '@wordpress/block-editor';import { ToolbarGroup, ToolbarItem, Button } from '@wordpress/components'; function Edit() { return ( <BlockControls> <ToolbarGroup> <ToolbarItem as={ Button }>I am a toolbar button</ToolbarItem> </ToolbarGroup> </BlockControls> );}
```

## Related components

- ToolbarItem should be used inside [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) or [ToolbarGroup](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-group/).
- If you want a simple toolbar button, consider using [ToolbarButton](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-button/) instead.
