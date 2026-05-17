---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/divider/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: divider
parent_order: 3
sub_order: 8
page_order: 37
title: "Divider"
code_quality: degraded
code_issue: pre_newline_loss
---

# Divider

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`Divider` is a layout component that separates groups of related content.

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { __experimentalDivider as Divider,} from `@wordpress/components`;import { Stack } from '@wordpress/ui'; function Example() { return ( <Stack direction="column" gap="lg"> <span>Some text here</span> <Divider /> <span>Some more text here</span> </Stack> );}
```

## Props

### margin: number

Adjusts all margins on the inline dimension.

- Required: No

### marginEnd: number

Adjusts the inline-end margin.

- Required: No

### marginStart: number

Adjusts the inline-start margin.

- Required: No

### orientation: horizontal | vertical

Divider’s orientation. When using inside a flex container, you may need to make sure the divider is `stretch` aligned in order for it to be visible.

- Required: No
- Default: `horizontal`

### Inherited props

`Divider` also inherits all of the [`Separator` props](https://ariakit.org/reference/separator#optional-props).
