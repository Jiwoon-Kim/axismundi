---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/box-control/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: box-control
parent_order: 3
sub_order: 8
page_order: 9
title: "BoxControl"
code_quality: degraded
code_issue: pre_newline_loss
---

# BoxControl

See the [WordPress Storybook](https://wordpress.github.io/gutenberg/?path=/docs/components-boxcontrol--docs) for more detailed, interactive documentation.

A control that lets users set values for top, right, bottom, and left. Can be  
used as an input control for values like `padding` or `margin`.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { BoxControl } from '@wordpress/components'; function Example() { const [ values, setValues ] = useState( { top: '50px', left: '10%', right: '10%', bottom: '50px', } ); return ( <BoxControl __next40pxDefaultSize values={ values } onChange={ setValues } /> );};
```

## Props

### __next40pxDefaultSize

- Type: `boolean`
- Required: No
- Default: `false`

Start opting into the larger default height that will become the default size in a future version.

### allowReset

- Type: `boolean`
- Required: No
- Default: `true`

If this property is true, a button to reset the box control is rendered.

### id

- Type: `string`
- Required: No

The id to use as a base for the unique HTML id attribute of the control.

### inputProps

- Type: `UnitControlPassthroughProps`
- Required: No
- Default: `{min: 0,}`

Props for the internal `UnitControl` components.

### label

- Type: `string`
- Required: No
- Default: `__( 'Box Control' )`

Heading label for the control.

### onChange

- Type: `(next: BoxControlValue) => void`
- Required: No
- Default: `() => {}`

A callback function when an input value changes.

### presets

- Type: `Preset[]`
- Required: No

Available presets to pick from.

### presetKey

- Type: `string`
- Required: No

The key of the preset to apply.  
If you provide a list of presets, you must provide a preset key to use.  
The format of preset selected values is going to be `var:preset|${ presetKey }|${ presetSlug }`

### resetValues

- Type: `BoxControlValue`
- Required: No
- Default: `{top: undefined,right: undefined,bottom: undefined,left: undefined,}`

The `top`, `right`, `bottom`, and `left` box dimension values to use when the control is reset.

### sides

- Type: `readonly (keyof BoxControlValue | "horizontal" | "vertical")[]`
- Required: No

Collection of sides to allow control of. If omitted or empty, all sides will be available.

Allowed values are “top”, “right”, “bottom”, “left”, “vertical”, and “horizontal”.

### splitOnAxis

- Type: `boolean`
- Required: No
- Default: `false`

If this property is true, when the box control is unlinked, vertical and horizontal controls  
can be used instead of updating individual sides.

### units

- Type: `WPUnitControlUnit[]`
- Required: No
- Default: `CSS_UNITS`

Available units to select from.

### values

- Type: `BoxControlValue`
- Required: No

The current values of the control, expressed as an object of `top`, `right`, `bottom`, and `left` values.
