---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/scrollable/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: scrollable
parent_order: 3
sub_order: 8
page_order: 96
title: "Scrollable"
---

# Scrollable

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`Scrollable` is a layout component that content in a scrollable container.

## Usage

```jsx
import { __experimentalScrollable as Scrollable } from '@wordpress/components'; function Example() { return ( <Scrollable style={ { maxHeight: 200 } }> <div style={ { height: 500 } }>...</div> </Scrollable> );}
```

## Props

### children: ReactNode

The children elements.

- Required: Yes

### scrollDirection: string

Renders a scrollbar for a specific axis when content overflows.

- Required: No
- Default: `y`
- Allowed values: `x`, `y`, `auto`

### smoothScroll: boolean

Enables (CSS) smooth scrolling.

- Required: No
- Default: `false`
