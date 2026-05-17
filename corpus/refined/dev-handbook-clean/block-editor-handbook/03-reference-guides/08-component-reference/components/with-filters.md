---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/with-filters/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: with-filters
parent_order: 3
sub_order: 8
page_order: 63
title: "WithFilters"
code_quality: degraded
code_issue: pre_newline_loss
---

# WithFilters

`withFilters` is a part of [Native Gutenberg Extensibility](https://github.com/WordPress/gutenberg/issues/3330). It is also a React [higher-order component](https://facebook.github.io/react/docs/higher-order-components.html).

Wrapping a component with `withFilters` provides a filtering capability controlled externally by the `hookName`.

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { withFilters } from '@wordpress/components';import { addFilter } from '@wordpress/hooks'; const MyComponent = ( { title } ) => <h1>{ title }</h1>; const ComponentToAppend = () => <div>Appended component</div>; function withComponentAppended( FilteredComponent ) { return ( props ) => ( <> <FilteredComponent { ...props } /> <ComponentToAppend /> </> );} addFilter( 'MyHookName', 'my-plugin/with-component-appended', withComponentAppended); const MyComponentWithFilters = withFilters( 'MyHookName' )( MyComponent );
```

`withFilters` expects a string argument which provides a hook name. It returns a function which can then be used in composing your component. The hook name allows plugin developers to customize or completely override the component passed to this higher-order component using `wp.hooks.addFilter` method.

It is also possible to override props by implementing a higher-order component which works as follows:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { withFilters } from '@wordpress/components';import { addFilter } from '@wordpress/hooks'; const MyComponent = ( { hint, title } ) => ( <> <h1>{ title }</h1> <p>{ hint }</p> </>); function withHintOverridden( FilteredComponent ) { return ( props ) => ( <FilteredComponent { ...props } hint="Overridden hint" /> );} addFilter( 'MyHookName', 'my-plugin/with-hint-overridden', withHintOverridden ); const MyComponentWithFilters = withFilters( 'MyHookName' )( MyComponent );
```
