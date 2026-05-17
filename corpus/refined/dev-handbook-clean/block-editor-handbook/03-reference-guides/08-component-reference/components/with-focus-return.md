---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/with-focus-return/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: with-focus-return
parent_order: 3
sub_order: 8
page_order: 65
title: "WithFocusReturn"
code_quality: degraded
code_issue: pre_newline_loss
---

# WithFocusReturn

`withFocusReturn` is a higher-order component used typically in scenarios of short-lived elements (modals, dropdowns) where, upon the element’s unmounting, focus should be restored to the focused element which had initiated it being rendered.

## Usage

### withFocusReturn

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { withFocusReturn, TextControl, Button } from '@wordpress/components'; const EnhancedComponent = withFocusReturn( () => ( <div> Focus will return to the previous input when this component is unmounted <TextControl __next40pxDefaultSize autoFocus={ true } onChange={ () => {} } /> </div>) ); const MyComponentWithFocusReturn = () => { const [ text, setText ] = useState( '' ); const unmount = () => { document.activeElement.blur(); setText( '' ); }; return ( <div> <TextControl __next40pxDefaultSize placeholder="Type something" value={ text } onChange={ ( value ) => setText( value ) } /> { text && <EnhancedComponent /> } { text && ( <Button variant="secondary" onClick={ unmount }> Unmount </Button> ) } </div> );};
```

`withFocusReturn` can optionally be called as a higher-order function creator. Provided an options object, a new higher-order function is returned.

Currently, the following options are supported:

#### onFocusReturn

An optional function which allows the developer to customize the focus return behavior. A return value of `false` should be returned from this function to indicate that the default focus return behavior should be skipped.

- Type: `Function`
- Required: No

*Example:*

```js
function MyComponent() { return <textarea />;} const EnhancedMyComponent = withFocusReturn( { onFocusReturn() { document.getElementById( 'other-input' ).focus(); return false; },} )( MyComponent );
```
