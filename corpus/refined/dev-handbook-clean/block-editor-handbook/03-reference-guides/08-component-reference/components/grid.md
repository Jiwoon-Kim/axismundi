---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/grid/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: grid
parent_order: 3
sub_order: 8
page_order: 55
title: "Grid"
---

# Grid

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`Grid` is a primitive layout component that can arrange content in a grid configuration.

## Usage

```jsx
import { __experimentalGrid as Grid, __experimentalText as Text,} from '@wordpress/components'; function Example() { return ( <Grid columns={ 3 }> <Text>Code</Text> <Text>is</Text> <Text>Poetry</Text> </Grid> );}
```

## Props

### [align: CSS\[‘alignItems’\]](https://developer.wordpress.org/block-editor/reference-guides/components/grid/#align-cssalignitems)

Adjusts the block alignment of children.

- Required: No

### alignment: GridAlignment

Adjusts the horizontal and vertical alignment of children.

- Required: No

### [columnGap: CSSProperties\[‘gridColumnGap’\]](https://developer.wordpress.org/block-editor/reference-guides/components/grid/#columngap-csspropertiesgridcolumngap)

Adjusts the `grid-column-gap`.

- Required: No

### columns: number

Adjusts the number of columns of the `Grid`.

- Required: No
- Default: `2`

### gap: number

Gap between each child.

- Required: No
- Default: `3`

### isInline: boolean

Changes the CSS display from `grid` to `inline-grid`.

- Required: No

### [justify: CSS\[‘justifyContent’\]](https://developer.wordpress.org/block-editor/reference-guides/components/grid/#justify-cssjustifycontent)

Adjusts the inline alignment of children.

- Required: No

### [rowGap: CSSProperties\[‘gridRowGap’\]](https://developer.wordpress.org/block-editor/reference-guides/components/grid/#rowgap-csspropertiesgridrowgap)

Adjusts the `grid-row-gap`.

- Required: No

### rows: number

Adjusts the number of rows of the `Grid`.

- Required: No

### [templateColumns: CSS\[‘gridTemplateColumns’\]](https://developer.wordpress.org/block-editor/reference-guides/components/grid/#templatecolumns-cssgridtemplatecolumns)

Adjusts the CSS grid `template-columns`.

- Required: No

### [templateRows: CSS\[‘gridTemplateRows’\]](https://developer.wordpress.org/block-editor/reference-guides/components/grid/#templaterows-cssgridtemplaterows)

Adjusts the CSS grid `template-rows`.

- Required: No
