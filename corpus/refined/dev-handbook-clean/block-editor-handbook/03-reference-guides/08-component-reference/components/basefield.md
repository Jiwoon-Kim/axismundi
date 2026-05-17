---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/base-field/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: basefield
parent_order: 3
sub_order: 8
page_order: 1
title: "BaseField"
code_quality: degraded
code_issue: pre_newline_loss
---

# BaseField

This feature is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

`BaseField` is an internal (i.e., not exported in the `index.js`) primitive component used for building more complex fields like `TextField`. It provides error handling and focus styles for field components. It does *not* handle layout of the component aside from wrapping the field in a `Flex` wrapper.

## Usage

`BaseField` is primarily used as a hook rather than a component:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
function useExampleField( props ) { const { as = 'input', ...baseProps } = useBaseField( props ); const inputProps = { as, // more cool stuff here }; return { inputProps, ...baseProps };} function ExampleField( props, forwardRef ) { const { preFix, affix, disabled, inputProps, ...baseProps } = useExampleField( props ); return ( <View { ...baseProps } disabled={ disabled }> { preFix } <View autocomplete="off" { ...inputProps } disabled={ disabled } /> { affix } </View> );}
```

## Props

### disabled: boolean

Whether the field is disabled.

- Required: No

### hasError: boolean

Renders an error style around the component.

- Required: No
- Default: `false`

### isInline: boolean

Renders a component that can be inlined in some text.

- Required: No
- Default: `false`

### isSubtle: boolean

Renders a subtle variant of the component.

- Required: No
- Default: `false`
