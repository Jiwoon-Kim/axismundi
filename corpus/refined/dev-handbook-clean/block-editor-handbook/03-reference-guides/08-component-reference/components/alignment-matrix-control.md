---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/alignment-matrix-control/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: alignment-matrix-control
parent_order: 3
sub_order: 8
page_order: 2
title: "AlignmentMatrixControl"
code_quality: degraded
code_issue: pre_newline_loss
---

# AlignmentMatrixControl

See the [WordPress Storybook](https://wordpress.github.io/gutenberg/?path=/docs/components-alignmentmatrixcontrol--docs) for more detailed, interactive documentation.

AlignmentMatrixControl components enable adjustments to horizontal and vertical alignments for UI.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { AlignmentMatrixControl } from '@wordpress/components';import { useState } from '@wordpress/element'; const Example = () => { const [ alignment, setAlignment ] = useState( 'center center' ); return ( <AlignmentMatrixControl value={ alignment } onChange={ setAlignment } /> );};
```

## Props

### defaultValue

- Type: `"center" | "top left" | "top center" | "top right" | "center left" | "center center" | "center right" | "bottom left" | "bottom center" | "bottom right"`
- Required: No
- Default: `'center center'`

If provided, sets the default alignment value.

### label

- Type: `string`
- Required: No
- Default: `'Alignment Matrix Control'`

Accessible label. If provided, sets the `aria-label` attribute of the  
underlying `grid` widget.

### onChange

- Type: `((newValue: AlignmentMatrixControlValue) => void)`
- Required: No

A function that receives the updated alignment value.

### value

- Type: `"center" | "top left" | "top center" | "top right" | "center left" | "center center" | "center right" | "bottom left" | "bottom center" | "bottom right"`
- Required: No

The current alignment value.

### width

- Type: `number`
- Required: No
- Default: `92`

If provided, sets the width of the control.

## Subcomponents

### AlignmentMatrixControl.Icon

#### Props

##### disablePointerEvents

- Type: `boolean`
- Required: No
- Default: `true`

If `true`, disables pointer events on the icon.

##### value

- Type: `"center" | "top left" | "top center" | "top right" | "center left" | "center center" | "center right" | "bottom left" | "bottom center" | "bottom right"`
- Required: No
- Default: `center`

The current alignment value.
