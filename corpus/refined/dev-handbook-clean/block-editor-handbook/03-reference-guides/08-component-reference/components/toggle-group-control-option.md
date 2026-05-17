---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/toggle-group-control-option/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: toggle-group-control-option
parent_order: 3
sub_order: 8
page_order: 112
title: "ToggleGroupControlOption"
---

# ToggleGroupControlOption

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`ToggleGroupControlOption` is a form component and is meant to be used as a child of [`ToggleGroupControl`](https://developer.wordpress.org/block-editor/reference-guide/components/toggle-group-control/toggle-group-control/).

## Usage

```jsx
import { __experimentalToggleGroupControl as ToggleGroupControl, __experimentalToggleGroupControlOption as ToggleGroupControlOption,} from '@wordpress/components'; function Example() { return ( <ToggleGroupControl label="my label" value="vertical" isBlock __next40pxDefaultSize > <ToggleGroupControlOption value="horizontal" label="Horizontal" showTooltip={ true } /> <ToggleGroupControlOption value="vertical" label="Vertical" /> </ToggleGroupControl> );}
```

## Props

### label: string

Label for the option. If needed, the `aria-label` prop can be used in addition to specify a different label for assistive technologies.

- Required: Yes

### value: string | number

The value of the `ToggleGroupControlOption`.

- Required: Yes

### showTooltip: boolean

Whether to show a tooltip when hovering over the option. The tooltip will attempt to use the `aria-label` prop text first, then the `label` prop text if no `aria-label` prop is found.

- Required: No
