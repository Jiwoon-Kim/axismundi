---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/heading/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: heading
parent_order: 3
sub_order: 8
page_order: 58
title: "Heading"
---

# Heading

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`Heading` renders headings and titles using the library’s typography system.

## Usage

```jsx
import { __experimentalHeading as Heading } from '@wordpress/components'; function Example() { return <Heading>Code is Poetry</Heading>;}
```

## Props

`Heading` uses `Text` underneath, so we have access to all of `Text`‘s props except for:

- `size` which is replaced by `level`;
- `isBlock`‘s default value, which is `true` for the `Heading` component.

For a complete list of those props, check out [`Text`](https://developer.wordpress.org/block-editor/reference-guide/components/text/#props).

### level: 1 | 2 | 3 | 4 | 5 | 6 | ‘1’ | ‘2’ | ‘3’ | ‘4’ | ‘5’ | ‘6’

Passing any of the heading levels to `level` will both render the correct typographic text size as well as the semantic element corresponding to the level (`h1` for `1` for example).

- Required: No
- Default: `2`
