---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/toggle-group-control-option-base/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: toggle-group-control-option-base
parent_order: 3
sub_order: 8
page_order: 110
title: "ToggleGroupControlOptionBase"
---

# ToggleGroupControlOptionBase

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`ToggleGroupControlOptionBase` is a form component and is meant to be used as an internal, generic component for any children of [`ToggleGroupControl`](https://developer.wordpress.org/block-editor/reference-guide/components/toggle-group-control/toggle-group-control/).

## Props

### children: ReactNode

The children elements.

- Required: Yes

### value: string | number

The value of the `ToggleGroupControlOptionBase`.

- Required: Yes

### showTooltip: boolean

Whether to show a tooltip when hovering over the option. The tooltip will only show if a label for it is provided using the `aria-label` prop.

- Required: No
