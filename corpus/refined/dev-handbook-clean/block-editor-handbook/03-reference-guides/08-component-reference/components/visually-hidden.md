---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/visually-hidden/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: visually-hidden
parent_order: 3
sub_order: 8
page_order: 128
title: "VisuallyHidden"
---

# VisuallyHidden

`VisuallyHidden` is a component used to render text intended to be visually hidden, but will show for alternate devices, for example a screen reader.

## Usage

```jsx
import { VisuallyHidden } from '@wordpress/components'; function Example() { return ( <VisuallyHidden> <label>Code is Poetry</label> </VisuallyHidden> );}
```

## Best practices

The element that `VisuallyHidden` renders has the style `position: absolute`. When using this component be careful of the [stacking context](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Positioning/Understanding_z_index/The_stacking_context). Even though `VisuallyHidden` isn’t visible, it can still affect layout. An example of this is that `VisuallyHidden` may ignore `overflow` styles of ancestor elements because it instead adopts the `overflow` of its stacking context. One known side-effect can be an unexpected scrollbar appearing. To fix this kind of issue, introduce a stacking context on a more immediate parent of `VisuallyHidden`. Adding `position: relative` is often an easy way to do this.
