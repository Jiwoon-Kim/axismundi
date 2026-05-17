---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: toolbar
parent_order: 3
sub_order: 8
page_order: 118
title: "Toolbar"
code_quality: degraded
code_issue: pre_newline_loss
---

# Toolbar

Toolbar can be used to group related options. To emphasize groups of related icon buttons, a toolbar should share a common container.

## Design guidelines

### Usage

### Best practices

Toolbars should:

- **Clearly communicate that clicking or tapping will trigger an action.**
- **Use established colors appropriately.** For example, only use red for actions that are difficult or impossible to undo.
- When used with a block, have a consistent location above the block. **Otherwise, have a consistent location in the interface.**

### States

#### Active and available toolbars

A toolbar’s state makes it clear which icon button is active. Hover and focus states express the available selection options for icon buttons in a toolbar.

#### Disabled toolbars

Toolbars that cannot be selected can either be given a disabled state, or be hidden.

## Development guidelines

### Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { Toolbar, ToolbarButton } from '@wordpress/components';import { formatBold, formatItalic, link } from '@wordpress/icons'; function MyToolbar() { return ( <Toolbar label="Options"> <ToolbarButton icon={ formatBold } label="Bold" /> <ToolbarButton icon={ formatItalic } label="Italic" /> <ToolbarButton icon={ link } label="Link" /> </Toolbar> );}
```

### Props

Toolbar will pass all HTML props to the underlying element. Additionally, you can pass the custom props specified below.

#### className: string

Class to set on the container div.

- Required: No

#### label: string

An accessible label for the toolbar.

- Required: Yes

#### variant: ‘unstyled’ | undefined

Specifies the toolbar’s style.

Leave undefined for the default style. Or `'unstyled'` which removes the border from the toolbar, but keeps the default popover style.

- Required: No
- Default: `undefined`

#### orientation: ‘horizontal’ | ‘vertical’

Specifies the toolbar’s orientation.

Leave undefined or ‘horizontal’ for horizontal orientation keyboard interactions, choose ‘vertical’ for the alternative.

- Required: No
- Default: `horizontal`

## Related components

- Toolbar may contain [ToolbarGroup](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-group/), [ToolbarButton](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-button/) and [ToolbarItem](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar-Item/) as children.
