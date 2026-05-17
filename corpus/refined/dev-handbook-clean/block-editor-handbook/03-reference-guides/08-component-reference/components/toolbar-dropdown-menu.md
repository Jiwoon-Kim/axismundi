---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-dropdown-menu/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: toolbar-dropdown-menu
parent_order: 3
sub_order: 8
page_order: 115
title: "ToolbarDropdownMenu"
code_quality: degraded
code_issue: pre_newline_loss
---

# ToolbarDropdownMenu

ToolbarDropdownMenu can be used to add actions to a toolbar, usually inside a [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) or [ToolbarGroup](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-group/) when used to create general interfaces. If you’re using it to add controls to your custom block, you should consider using [BlockControls](../../../01-getting-started/02-fundamentals-of-block-development/block-in-the-editor.md).

It has similar features to the [DropdownMenu](https://developer.wordpress.org/block-editor/reference-guide/components/dropdown-menu/) component. Using `ToolbarDropdownMenu` will ensure that keyboard interactions in a toolbar are consistent with the [WAI-ARIA toolbar pattern](https://www.w3.org/TR/wai-aria-practices/#toolbar).

## Usage

To create general interfaces, you’ll want to render ToolbarButton in a [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/) component.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { Toolbar, ToolbarDropdownMenu } from '@wordpress/components';import { more, arrowLeft, arrowRight, arrowUp, arrowDown,} from '@wordpress/icons'; function MyToolbar() { return ( <Toolbar label="Options"> <ToolbarDropdownMenu icon={ more } label="Select a direction" controls={ [ { title: 'Up', icon: arrowUp, onClick: () => console.log( 'up' ), }, { title: 'Right', icon: arrowRight, onClick: () => console.log( 'right' ), }, { title: 'Down', icon: arrowDown, onClick: () => console.log( 'down' ), }, { title: 'Left', icon: arrowLeft, onClick: () => console.log( 'left' ), }, ] } /> </Toolbar> );}
```

### Inside BlockControls

If you’re working on a custom block and you want to add controls to the block toolbar, you should use [BlockControls](../../../01-getting-started/02-fundamentals-of-block-development/block-in-the-editor.md) instead.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { BlockControls } from '@wordpress/block-editor';import { Toolbar, ToolbarDropdownMenu } from '@wordpress/components';import { more, arrowLeft, arrowRight, arrowUp, arrowDown,} from '@wordpress/icons'; function Edit() { return ( <BlockControls group="block"> <ToolbarDropdownMenu icon={ more } label="Select a direction" controls={ [ { title: 'Up', icon: arrowUp, onClick: () => console.log( 'up' ), }, { title: 'Right', icon: arrowRight, onClick: () => console.log( 'right' ), }, { title: 'Down', icon: arrowDown, onClick: () => console.log( 'down' ), }, { title: 'Left', icon: arrowLeft, onClick: () => console.log( 'left' ), }, ] } /> </BlockControls> );}
```

## Props

This component accepts [the same API of the DropdownMenu](https://developer.wordpress.org/block-editor/reference-guide/components/dropdown-menu/#props) component.
