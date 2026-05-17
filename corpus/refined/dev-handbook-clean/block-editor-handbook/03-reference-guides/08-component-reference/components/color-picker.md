---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/color-picker/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: color-picker
parent_order: 3
sub_order: 8
page_order: 26
title: "ColorPicker"
code_quality: degraded
code_issue: pre_newline_loss
---

# ColorPicker

`ColorPicker` is a color picking component based on `react-colorful`. It lets you pick a color visually or by manipulating the individual RGB(A), HSL(A) and Hex(8) color values.

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { ColorPicker } from '@wordpress/components'; function Example() { const [color, setColor] = useState(); return ( <ColorPicker color={color} onChange={setColor} enableAlpha defaultValue="#000" /> );}
```

## Props

### color: string

The current color value to display in the picker. Must be a hex or hex8 string.

- Required: No

### onChange: (hex8Color: string) =&gt; void

Fired when the color changes. Always passes a hex or hex8 color string.

- Required: No

### enableAlpha: boolean

When `true` the color picker will display the alpha channel both in the bottom inputs as well as in the color picker itself.

- Required: No
- Default: `false`

### defaultValue: string | undefined

An optional default value to use for the color picker.

- Required: No
- Default: `'#fff'`

### copyFormat: ‘hex’ | ‘hsl’ | ‘rgb’ | undefined

The format to copy when clicking the displayed color format.

- Required: No
