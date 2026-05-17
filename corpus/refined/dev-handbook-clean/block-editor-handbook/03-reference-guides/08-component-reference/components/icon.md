---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/icon/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: icon
parent_order: 3
sub_order: 8
page_order: 68
title: "Icon"
---

# Icon

See the [WordPress Storybook](https://wordpress.github.io/gutenberg/?path=/docs/components-icon--docs) for more detailed, interactive documentation.

Renders a raw icon without any initial styling or wrappers.

```js
import { wordpress } from '@wordpress/icons'; <Icon icon={ wordpress } />
```

## Props

### icon

- Type: `IconType | null`
- Required: No
- Default: `null`

The icon to render. In most cases, you should use an icon from  
[the `@wordpress/icons` package](https://wordpress.github.io/gutenberg/?path=/story/icons-icon--library).

Other supported values are: component instances, functions,  
[Dashicons](https://developer.wordpress.org/resource/dashicons/)  
(specified as strings), and `null`.

The `size` value, as well as any other additional props, will be passed through.

### size

- Type: `number`
- Required: No
- Default: `'string' === typeof icon ? 20 : 24`

The size (width and height) of the icon.

Defaults to `20` when `icon` is a string (i.e. a Dashicon id), otherwise `24`.
