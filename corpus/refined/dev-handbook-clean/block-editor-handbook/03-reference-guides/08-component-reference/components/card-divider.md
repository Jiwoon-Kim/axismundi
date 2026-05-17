---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/card-divider/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: card-divider
parent_order: 3
sub_order: 8
page_order: 15
title: "CardDivider"
---

# CardDivider

`CardDivider` renders an optional divider within a [`Card`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card/). It is typically used to divide multiple `CardBody` components from each other.

## Usage

```jsx
import { Card, CardBody, CardDivider } from '@wordpress/components'; const Example = () => ( <Card> <CardBody>...</CardBody> <CardDivider /> <CardBody>...</CardBody> </Card>);
```

## Props

### Inherited props

`CardDivider` inherits all of the [`Divider` props](https://developer.wordpress.org/block-editor/reference-guide/components/divider/#props).
