---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/card/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: card
parent_order: 3
sub_order: 8
page_order: 19
title: "Card"
code_quality: degraded
code_issue: pre_newline_loss
---

# Card

`Card` provides a flexible and extensible content container.

## Usage

`Card` also provides a convenient set of [sub-components](card.md#sub-components) such as `CardBody`, `CardHeader`, `CardFooter`, and more (see below).

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { Card, CardHeader, CardBody, CardFooter, __experimentalText as Text, __experimentalHeading as Heading,} from '@wordpress/components'; function Example() { return ( <Card> <CardHeader> <Heading level={ 4 }>Card Title</Heading> </CardHeader> <CardBody> <Text>Card Content</Text> </CardBody> <CardFooter> <Text>Card Footer</Text> </CardFooter> </Card> );}
```

## Props

### elevation: number

Size of the elevation shadow, based on the Style system’s elevation system. This may be helpful in highlighting certain content. For more information, check out [`Elevation`](https://developer.wordpress.org/block-editor/reference-guide/components/elevation/).

- Required: No
- Default: `0`

### isBorderless: boolean

Renders without a border.

- Required: No
- Default: `false`

### isRounded: boolean

Renders with rounded corners.

- Required: No
- Default: `true`

### size: string | object

Determines the amount of padding within the component. Can be specified either as a single size token or as an object.

- Required: No
- Default: `medium`
- Allowed values:
```text
- Single size token: `none`, `xSmall`, `small`, `medium`, `large`
- Object:

    { blockStart: 'none' | 'xSmall' | 'small' | 'medium' | 'large'; blockEnd: 'none' | 'xSmall' | 'small' | 'medium' | 'large'; inlineStart: 'none' | 'xSmall' | 'small' | 'medium' | 'large'; inlineEnd: 'none' | 'xSmall' | 'small' | 'medium' | 'large';}
```

### Inherited props

`Card` also inherits all of the [`Surface` props](https://developer.wordpress.org/block-editor/reference-guide/components/surface/#props).

## Sub-Components

This component provides a collection of sub-component that can be used to compose various interfaces.

- [`<CardBody />`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card-body/)
- [`<CardDivider />`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card-divider/)
- [`<CardFooter />`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card-footer/))
- [`<CardHeader />`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card-header/))
- [`<CardMedia />`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card-media/))

### Sub-Components Example

```php
import { Card, CardBody, CardDivider, CardFooter, CardHeader, CardMedia,} from '@wordpress/components'; const Example = () => ( <Card> <CardHeader>...</CardHeader> <CardBody>...</CardBody> <CardDivider /> <CardBody>...</CardBody> <CardMedia> <img src="..." /> </CardMedia> <CardFooter>...</CardFooter> </Card>);
```

### Logical Padding Properties

The `size` prop supports logical properties that adapt to different writing directions:

- `blockStart` – Maps to `top` in horizontal writing modes
- `blockEnd` – Maps to `bottom` in horizontal writing modes
- `inlineStart` – Maps to `left` in horizontal left-to-right writing modes
- `inlineEnd` – Maps to `right` in horizontal left-to-right writing modes

```jsx
import { Card, CardBody } from '@wordpress/components'; const Example = () => ( <Card size={ { blockStart: 'large', blockEnd: 'small', inlineStart: 'medium', inlineEnd: 'medium', } } > <CardBody size={ { blockStart: 'small', inlineStart: 'large', inlineEnd: 'large', } } > Content with logical padding properties </CardBody> </Card>);
```

### Context

`<Card />`‘s sub-components are connected to `<Card />` using [Context](https://react.dev/learn/passing-data-deeply-with-context). Certain props like `size` and `isBorderless` are passed through to some of the sub-components.

In the following example, the `<CardBody />` will render with a size of `small`:

```jsx
import { Card, CardBody } from '@wordpress/components'; const Example = () => ( <Card size="small"> <CardBody>...</CardBody> </Card>);
```

These sub-components are designed to be flexible. The Context props can be overridden by the sub-component(s) as required. In the following example, the last `<CardBody />` will render it’s specified size:

```jsx
import { Card, CardBody } from '@wordpress/components'; const Example = () => ( <Card size="small"> <CardBody>...</CardBody> <CardBody>...</CardBody> <CardBody size="large">...</CardBody> </Card>);
```
