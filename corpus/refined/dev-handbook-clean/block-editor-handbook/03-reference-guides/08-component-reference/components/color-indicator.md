---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/color-indicator/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: color-indicator
parent_order: 3
sub_order: 8
page_order: 23
title: "ColorIndicator"
---

# ColorIndicator

ColorIndicator is a React component that renders a specific color in a circle. It’s often used to summarize a collection of used colors in a child component.

### Single component

![simple color indicator](https://i0.wp.com/user-images.githubusercontent.com/881729/147558034-cba09db5-2f06-458b-a7b1-fd2f2ffb982a.png?ssl=1)

### Used in sidebar

![multiple color indicator](https://i0.wp.com/user-images.githubusercontent.com/881729/147559177-69ce52e1-30dc-4f24-8483-ca2a580f434f.png?ssl=1)

## Usage

```jsx
import { ColorIndicator } from '@wordpress/components'; const MyColorIndicator = () => <ColorIndicator colorValue="#0073aa" />;
```

## Props

The component accepts the following props:

### className: string

Extra classes for the used `<span>` element. By default only `component-color-indicator` is added.

- Required: No

### [colorValue: CSSProperties\[ ‘background’ \]](https://developer.wordpress.org/block-editor/reference-guides/components/color-indicator/#colorvalue-cssproperties-background)

The color of the indicator. Any value from the CSS [`background`](https://developer.mozilla.org/en-US/docs/Web/CSS/background) property is supported.

- Required: Yes
