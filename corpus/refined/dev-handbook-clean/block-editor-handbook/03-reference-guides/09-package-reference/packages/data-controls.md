---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-data-controls/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: data-controls
parent_order: 3
sub_order: 9
page_order: 33
title: "@wordpress/data-controls"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/data-controls

The data controls module is a module intended to simplify implementation of common controls used with the [`@wordpress/data`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/data/README.md) package.

**Note:** It is assumed that the registry being used has the controls plugin enabled on it (see [more details on controls here](https://github.com/WordPress/gutenberg/tree/HEAD/packages/data#controls))

## Installation

Install the module

```bash
npm install @wordpress/data-controls --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

The following controls are available on the object returned by the module:

## API

### apiFetch

Dispatches a control action for triggering an api fetch call.

*Usage*

```js
import { apiFetch } from '@wordpress/data-controls'; // Action generator using apiFetchexport function* myAction() { const path = '/v2/my-api/items'; const items = yield apiFetch( { path } ); // do something with the items.}
```

*Parameters*

- *request* `Object`: Arguments for the fetch request.

*Returns*

- `Object`: The control descriptor.

### controls

The default export is what you use to register the controls with your custom store.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
// WordPress dependenciesimport { controls } from '@wordpress/data-controls';import { registerStore } from '@wordpress/data'; // Internal dependenciesimport reducer from './reducer';import * as selectors from './selectors';import * as actions from './actions';import * as resolvers from './resolvers'; registerStore( 'my-custom-store', { reducer, controls, actions, selectors, resolvers,} );
```

*Returns*

- `Object`: An object for registering the default controls with the store.

### dispatch

Control for dispatching an action in a registered data store. Alias for the `dispatch` control in the `@wordpress/data` package.

*Parameters*

- *storeNameOrDescriptor* `string | StoreDescriptor`: The store object or identifier.
- *actionName* `string`: The action name.
- *args* `any[]`: Arguments passed without change to the `@wordpress/data` control.

### select

Control for resolving a selector in a registered data store. Alias for the `resolveSelect` built-in control in the `@wordpress/data` package.

*Parameters*

- *storeNameOrDescriptor* `string | StoreDescriptor`: The store object or identifier.
- *selectorName* `string`: The selector name.
- *args* `any[]`: Arguments passed without change to the `@wordpress/data` control.

### syncSelect

Control for calling a selector in a registered data store. Alias for the `select` built-in control in the `@wordpress/data` package.

*Parameters*

- *storeNameOrDescriptor* `string | StoreDescriptor`: The store object or identifier.
- *selectorName* `string`: The selector name.
- *args* `any[]`: Arguments passed without change to the `@wordpress/data` control.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
