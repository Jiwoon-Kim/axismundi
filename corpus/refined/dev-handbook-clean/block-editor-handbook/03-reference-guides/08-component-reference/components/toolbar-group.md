---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/toolbar-group/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: toolbar-group
parent_order: 3
sub_order: 8
page_order: 116
title: "ToolbarGroup"
code_quality: degraded
code_issue: pre_newline_loss
---

# ToolbarGroup

A ToolbarGroup can be used to create subgroups of controls inside a [Toolbar](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/toolbar/).

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { Toolbar, ToolbarGroup, ToolbarButton } from '@wordpress/components';import { paragraph, formatBold, formatItalic, link } from '@wordpress/icons'; function MyToolbar() { return ( <Toolbar label="Options"> <ToolbarGroup> <ToolbarButton icon={ paragraph } label="Paragraph" /> </ToolbarGroup> <ToolbarGroup> <ToolbarButton icon={ formatBold } label="Bold" /> <ToolbarButton icon={ formatItalic } label="Italic" /> <ToolbarButton icon={ link } label="Link" /> </ToolbarGroup> </Toolbar> );}
```

### Props

ToolbarGroup will pass all HTML props to the underlying element.

## Related components

- ToolbarGroup may contain [ToolbarButton](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/toolbar-button/) and [ToolbarItem](https://developer.wordpress.org/block-editor/reference-guide/components/toolbar/toolbar-item/) as children.
