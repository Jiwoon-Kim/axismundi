---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/base-control/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: base-control
parent_order: 3
sub_order: 8
page_order: 6
title: "BaseControl"
code_quality: degraded
code_issue: pre_newline_loss
---

# BaseControl

See the [WordPress Storybook](https://wordpress.github.io/gutenberg/?path=/docs/components-basecontrol--docs) for more detailed, interactive documentation.

`BaseControl` is a low-level component used to generate labels and help text for components handling user inputs.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { BaseControl, useBaseControlProps } from '@wordpress/components'; // Render a `BaseControl` for a textarea inputconst MyCustomTextareaControl = ({ children, ...baseProps }) => ( // `useBaseControlProps` is a convenience hook to get the props for the `BaseControl` // and the inner control itself. Namely, it takes care of generating a unique `id`, // properly associating it with the `label` and `help` elements. const { baseControlProps, controlProps } = useBaseControlProps( baseProps ); return ( <BaseControl { ...baseControlProps }> <textarea { ...controlProps }> { children } </textarea> </BaseControl> ););
```

## Props

### as

- Type: `"symbol" | "object" | "label" | "a" | "abbr" | "address" | "area" | "article" | "aside" | "audio" | "b" | "base" | "bdi" | "bdo" | "big" | "blockquote" | "body" | "br" | "button" | ... 516 more ... | ("view" & FunctionComponent<...>)`
- Required: No

The HTML element or React component to render the component as.

### className

- Type: `string`
- Required: No

### children

- Type: `ReactNode`
- Required: Yes

The content to be displayed within the `BaseControl`.

### help

- Type: `ReactNode`
- Required: No

Additional description for the control.

Only use for meaningful description or instructions for the control. An element containing the description will be programmatically associated to the BaseControl by the means of an `aria-describedby` attribute.

### hideLabelFromVision

- Type: `boolean`
- Required: No
- Default: `false`

If true, the label will only be visible to screen readers.

### id

- Type: `string`
- Required: No

The HTML `id` of the control element (passed in as a child to `BaseControl`) to which labels and help text are being generated.  
This is necessary to accessibly associate the label with that element.

The recommended way is to use the `useBaseControlProps` hook, which takes care of generating a unique `id` for you.  
Otherwise, if you choose to pass an explicit `id` to this prop, you are responsible for ensuring the uniqueness of the `id`.

### label

- Type: `ReactNode`
- Required: No

If this property is added, a label will be generated using label property as the content.

## Subcomponents

### BaseControl.VisualLabel

`BaseControl.VisualLabel` is used to render a purely visual label inside a `BaseControl` component.

It should only be used in cases where the children being rendered inside `BaseControl` are already accessibly labeled,  
e.g., a button, but we want an additional visual label for that section equivalent to the labels `BaseControl` would  
otherwise use if the `label` prop was passed.

```jsx
import { BaseControl } from '@wordpress/components'; const MyBaseControl = () => ( <BaseControl help="This button is already accessibly labeled."> <BaseControl.VisualLabel>Author</BaseControl.VisualLabel> <Button>Select an author</Button> </BaseControl>);
```

#### Props

##### as

- Type: `"symbol" | "object" | "label" | "a" | "abbr" | "address" | "area" | "article" | "aside" | "audio" | ...`
- Required: No

The HTML element or React component to render the component as.

##### children

- Type: `ReactNode`
- Required: Yes

The content to be displayed within the `BaseControl.VisualLabel`.
