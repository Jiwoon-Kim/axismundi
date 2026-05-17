---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/flex/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: flex
parent_order: 3
sub_order: 8
page_order: 47
title: "Flex"
---

# Flex

`Flex` is a primitive layout component that adaptively aligns child content horizontally or vertically. `Flex` powers components like `HStack` and `VStack`.

## Usage

`Flex` is used with any of it’s two sub-components, `FlexItem` and `FlexBlock`.

```jsx
import { Flex, FlexBlock, FlexItem } from '@wordpress/components'; function Example() { return ( <Flex> <FlexItem> <p>Code</p> </FlexItem> <FlexBlock> <p>Poetry</p> </FlexBlock> </Flex> );}
```

## Props

### [align: CSSProperties\[‘alignItems’\]](https://developer.wordpress.org/block-editor/reference-guides/components/flex/#align-csspropertiesalignitems)

Aligns children using CSS Flexbox `align-items`. Vertically aligns content if the `direction` is `row`, or horizontally aligns content if the `direction` is `column`.

- Required: No
- Default: `center`

### [direction: ResponsiveCSSValue&lt;CSSProperties\[‘flexDirection’\]&gt;](https://developer.wordpress.org/block-editor/reference-guides/components/flex/#direction-responsivecssvaluecsspropertiesflexdirection)

The direction flow of the children content can be adjusted with `direction`. `column` will align children vertically and `row` will align children horizontally.

- Required: No
- Default: `row`

### expanded: boolean

Expands to the maximum available width (if horizontal) or height (if vertical).

- Required: No
- Default: `true`

### gap: number

Spacing in between each child can be adjusted by using `gap`. The value of `gap` works as a multiplier to the library’s grid system (base of `4px`).

- Required: No
- Default: `2`

### [justify: CSSProperties\[‘justifyContent’\]](https://developer.wordpress.org/block-editor/reference-guides/components/flex/#justify-csspropertiesjustifycontent)

Horizontally aligns content if the `direction` is `row`, or vertically aligns content if the `direction` is `column`.

- Required: No
- Default: `space-between`

### wrap: boolean

Determines if children should wrap.

- Required: No
- Default: `false`
