---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/card-footer/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: card-footer
parent_order: 3
sub_order: 8
page_order: 16
title: "CardFooter"
---

# CardFooter

`CardFooter` renders an optional footer within a [`Card`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card/).

## Usage

```jsx
import { Card, CardFooter } from '@wordpress/components'; const Example = () => ( <Card> <CardBody>...</CardBody> <CardFooter>...</CardFooter> </Card>);
```

### Flex

Underneath, `CardFooter` uses the [`Flex` layout component](https://developer.wordpress.org/block-editor/reference-guide/components/flex/flex/). This improves the alignment of child items within the component.

```jsx
import { Button, Card, CardFooter, FlexItem, FlexBlock,} from '@wordpress/components'; const Example = () => ( <Card> <CardBody>...</CardBody> <CardFooter> <FlexBlock>Content</FlexBlock> <FlexItem> <Button>Action</Button> </FlexItem> </CardFooter> </Card>);
```

Check out [the documentation](https://developer.wordpress.org/block-editor/reference-guide/components/flex/flex/) on `Flex` for more details on layout composition.

## Props

Note: This component is connected to [`Card`‘s Context](https://developer.wordpress.org/block-editor/reference-guide/components/card/card/#context). The value of the `size` and `isBorderless` props is derived from the `Card` parent component (if there is one). Setting these props directly on this component will override any derived values.

### isBorderless: boolean

Renders without a border.

- Required: No
- Default: `false`

### isShady: boolean

Renders with a light gray background color.

- Required: No
- Default: `false`

### [justify: CSSProperties\[ ‘justifyContent’ \]](https://developer.wordpress.org/block-editor/reference-guides/components/card-footer/#justify-cssproperties-justifycontent)

See the documentation for the `justify` prop for the [`Flex` component](https://developer.wordpress.org/block-editor/reference-guide/components/flex/flex/#justify)

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
