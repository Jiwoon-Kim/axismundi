---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/angle-picker-control/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: angle-picker-control
parent_order: 3
sub_order: 8
page_order: 3
title: "AnglePickerControl"
code_quality: degraded
code_issue: pre_newline_loss
---

# AnglePickerControl

See the [WordPress Storybook](https://wordpress.github.io/gutenberg/?path=/docs/components-anglepickercontrol--docs) for more detailed, interactive documentation.

`AnglePickerControl` is a React component to render a UI that allows users to  
pick an angle. Users can choose an angle in a visual UI with the mouse by  
dragging an angle indicator inside a circle or by directly inserting the  
desired angle in a text field.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from '@wordpress/element';import { AnglePickerControl } from '@wordpress/components'; function Example() { const [ angle, setAngle ] = useState( 0 ); return ( <AnglePickerControl value={ angle } onChange={ setAngle } /> );}
```

## Props

### as

- Type: `"symbol" | "object" | "a" | "abbr" | "address" | "area" | "article" | "aside" | "audio" | "b" | ...`
- Required: No

The HTML element or React component to render the component as.

### label

- Type: `string`
- Required: No
- Default: `__( 'Angle' )`

Label to use for the angle picker.

### onChange

- Type: `(value: number) => void`
- Required: Yes

A function that receives the new value of the input.

### value

- Type: `string | number`
- Required: Yes

The current value of the input. The value represents an angle in degrees  
and should be a value between 0 and 360.
