---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/disabled/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: disabled
parent_order: 3
sub_order: 8
page_order: 36
title: "Disabled"
code_quality: degraded
code_issue: pre_newline_loss
---

# Disabled

Disabled is a component which disables descendant tabbable elements and prevents pointer interaction.

## Usage

Assuming you have a form component, you can disable all form inputs by wrapping the form with `<Disabled>`.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { Button, Disabled, TextControl } from '@wordpress/components'; const MyDisabled = () => { const [ isDisabled, setIsDisabled ] = useState( true ); let input = ( <TextControl __next40pxDefaultSize label="Input" onChange={ () => {} } /> ); if ( isDisabled ) { input = <Disabled>{ input }</Disabled>; } const toggleDisabled = () => { setIsDisabled( ( state ) => ! state ); }; return ( <div> { input } <Button variant="primary" onClick={ toggleDisabled }> Toggle Disabled </Button> </div> );};
```

A component can detect if it has been wrapped in a `<Disabled />` by accessing its [context](https://react.dev/learn/passing-data-deeply-with-context) using `Disabled.Context`.

```js
function CustomButton( props ) { const isDisabled = useContext( Disabled.Context ); return <button { ...props } style={ { opacity: isDisabled ? 0.5 : 1 } } />;}
```

*Note: this component may not behave as expected in browsers that don’t support [the `inert` HTML attribute](https://developer.mozilla.org/en-US/docs/Web/API/HTMLElement/inert). We recommend adding [the official WICG polyfill](https://github.com/WICG/inert) when using this component in your project.*

### Props

The component accepts the following props:

#### isDisabled

Whether to disable all the descendant fields. Defaults to `true`.

- Type: `Boolean`
- Required: No
- Default: `true`
