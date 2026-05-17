---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-react-i18n/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: react-i18n
parent_order: 3
sub_order: 9
page_order: 94
title: "@wordpress/react-i18n"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/react-i18n

React bindings for [`@wordpress/i18n`](https://developer.wordpress.org/block-editor/reference-guides/packages/i18n).

## Installation

Install the module:

```bash
npm install @wordpress/react-i18n
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## API

### I18nProvider

The `I18nProvider` should be mounted above any localized components:

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { createI18n } from '@wordpress/i18n';import { I18nProvider } from '@wordpress/react-i18n';const i18n = createI18n(); ReactDom.render( <I18nProvider i18n={ i18n }> <App /> </I18nProvider>, el);
```

You can also instantiate the provider without the `i18n` prop. In that case it will use the  
default `I18n` instance exported from `@wordpress/i18n`.

*Parameters*

- *props* `I18nProviderProps`: i18n provider props.

*Returns*

- Children wrapped in the I18nProvider.

### useI18n

React hook providing access to i18n functions. It exposes the `__`, `_x`, `_n`, `_nx`, `isRTL` and `hasTranslation` functions from [`@wordpress/i18n`](https://developer.wordpress.org/block-editor/reference-guides/packages/i18n). Refer to their documentation there.

*Usage*

```js
import { useI18n } from '@wordpress/react-i18n'; function MyComponent() { const { __ } = useI18n(); return __( 'Hello, world!' );}
```

### withI18n

React higher-order component that passes the i18n translate functions (the same set as exposed by the `useI18n` hook) to the wrapped component as props.

*Usage*

```js
import { withI18n } from '@wordpress/react-i18n'; function MyComponent( { __ } ) { return __( 'Hello, world!' );} export default withI18n( MyComponent );
```

*Parameters*

- *InnerComponent* `ComponentType< P >`: React component to be wrapped and receive the i18n functions like `__`

*Returns*

- `FunctionComponent< PropsAndI18n< P > >`: The wrapped component

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
