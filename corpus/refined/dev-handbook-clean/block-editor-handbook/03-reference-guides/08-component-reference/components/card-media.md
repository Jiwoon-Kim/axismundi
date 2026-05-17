---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/card-media/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: card-media
parent_order: 3
sub_order: 8
page_order: 18
title: "CardMedia"
---

# CardMedia

`CardMedia` provides a container for full-bleed content within a [`Card`](https://developer.wordpress.org/block-editor/reference-guide/components/card/card/), such as images, video, or even just a background color.

## Usage

```php
import { Card, CardBody, CardMedia } from '@wordpress/components'; const Example = () => ( <Card> <CardMedia> <img src="..." /> </CardMedia> <CardBody>...</CardBody> </Card>);
```

## Placement

`CardMedia` can be placed in any order as a direct child of a `Card` (it can also exist as the only child component). The styles will automatically round the corners of the inner media element.
