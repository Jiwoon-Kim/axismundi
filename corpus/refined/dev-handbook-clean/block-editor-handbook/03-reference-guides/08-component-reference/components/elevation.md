---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/elevation/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: elevation
parent_order: 3
sub_order: 8
page_order: 43
title: "Elevation"
code_quality: degraded
code_issue: pre_newline_loss
---

# Elevation

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`Elevation` is a core component that renders shadow, using the component system’s shadow system.

## Usage

The shadow effect is generated using the `value` prop.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { __experimentalElevation as Elevation, __experimentalSurface as Surface, __experimentalText as Text,} from '@wordpress/components'; function Example() { return ( <Surface> <Text>Code is Poetry</Text> <Elevation value={ 5 } /> </Surface> );}
```

## Props

### active: number

Size of the shadow value when active (see the `value` and `isInteractive` props).

- Required: No

### [borderRadius: CSSProperties\[ ‘borderRadius’ \]](https://developer.wordpress.org/block-editor/reference-guides/components/elevation/#borderradius-cssproperties-borderradius)

Renders the border-radius of the shadow.

- Required: No
- Default: `inherit`

### focus: number

Size of the shadow value when focused (see the `value` and `isInteractive`props).

- Required: No

### hover: number

Size of the shadow value when hovered (see the `value` and `isInteractive` props).

- Required: No

### isInteractive: boolean

Determines if `hover`, `active`, and `focus` shadow values should be automatically calculated and rendered.

- Required: No
- Default: `false`

### offset: number

Dimensional offsets (margin) for the shadow.

- Required: No
- Default: `0`

### value: number

Size of the shadow, based on the Style system’s elevation system. The `value` determines the strength of the shadow, which sense of depth.

In the example below, `isInteractive` is activated to give a better sense of depth.

```jsx
import { __experimentalElevation as Elevation } from '@wordpress/components'; function Example() { return ( <div> <Elevation isInteractive value={ 200 } /> </div> );}
```

- Required: No
- Default: `0`
