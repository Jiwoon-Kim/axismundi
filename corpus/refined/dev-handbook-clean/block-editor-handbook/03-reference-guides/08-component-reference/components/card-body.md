---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/card-body/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: card-body
parent_order: 3
sub_order: 8
page_order: 14
title: "CardBody"
---

# CardBody

`CardBody` renders an optional content area for a [`Card`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card/). Multiple `CardBody` components can be used within `Card` if needed.

## Usage

```jsx
import { Card, CardBody } from '@wordpress/components'; const Example = () => ( <Card> <CardBody>...</CardBody> </Card>);
```

## Props

Note: This component is connected to [`Card`‘s Context](https://developer.wordpress.org/block-editor/reference-guide/components/card/card/#context). The value of the `size` prop is derived from the `Card` parent component (if there is one). Setting this prop directly on this component will override any derived values.

### isScrollable: boolean

Determines if the component is scrollable.

- Required: No
- Default: `false`

### isShady: boolean

Renders with a light gray background color.

- Required: No
- Default: `false`

### size: string | object

Determines the amount of padding within the component. Can be specified either as a single size token or as an object.

- Required: No
- Default: `medium`
- Allowed values:
- Single size token: `none`, `xSmall`, `small`, `medium`, `large`
- Object:

```text
    { blockStart: 'none' | 'xSmall' | 'small' | 'medium' | 'large'; blockEnd: 'none' | 'xSmall' | 'small' | 'medium' | 'large'; inlineStart: 'none' | 'xSmall' | 'small' | 'medium' | 'large'; inlineEnd: 'none' | 'xSmall' | 'small' | 'medium' | 'large';}
```
