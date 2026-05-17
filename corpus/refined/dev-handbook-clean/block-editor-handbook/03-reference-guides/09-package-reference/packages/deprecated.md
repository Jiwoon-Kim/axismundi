---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-custom-templated-path-webpack-plugin/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: deprecated
parent_order: 3
sub_order: 9
page_order: 11
title: "@wordpress/custom-templated-path-webpack-plugin"
code_quality: degraded
code_issue: pre_newline_loss
---

---

# @wordpress/custom-templated-path-webpack-plugin

> 
> **DEPRECATED for webpack v5**: please use [`output.filename`](https://webpack.js.org/configuration/output/#outputfilename) instead.

[Deprecated]
@wordpress/custom-templated-path-webpack-plugin

[Reason]
- only works with webpack v4
- replaced by native output.filename templating in webpack v5

[DoNotUse]
true

[ModernAlternative]
Use webpack output.filename with template strings

[Example]
filename: 'build-[name].js'

---

---

https://developer.wordpress.org/block-editor/reference-guides/packages/packages-boot/
내용없음

---

---

https://developer.wordpress.org/block-editor/reference-guides/packages/packages-experiments/
리디렉션됨
https://developer.wordpress.org/block-editor/reference-guides/packages/packages-private-apis/
---


---

https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils/
# @wordpress/e2e-test-utils

End-To-End (E2E) test utils for WordPress.

*It works properly with the minimum version of Gutenberg `13.8.0` or the minimum version of WordPress `6.0.0`.*

**Note that there’s currently an ongoing [project](https://github.com/WordPress/gutenberg/issues/38851) to migrate E2E tests to Playwright instead. This package is deprecated and will only accept bug fixes until fully migrated.**

---

---

https://developer.wordpress.org/block-editor/reference-guides/packages/packages-deprecated/
# @wordpress/deprecated

Deprecation utility for WordPress. Logs a message to notify developers about a deprecated feature.

## Installation

Install the module

```bash
npm install @wordpress/deprecated --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## Hook

The `deprecated` action is fired with three parameters: the name of the deprecated feature, the options object passed to deprecated, and the message sent to the console.

*Example:*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { addAction } from '@wordpress/hooks'; function addDeprecationAlert( message, { version } ) { alert( `Deprecation: ${ message }. Version: ${ version }` );} addAction( 'deprecated', 'my-plugin/add-deprecation-alert', addDeprecationAlert);
```

## API

### default

Logs a message to notify developers about a deprecated feature.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import deprecated from '@wordpress/deprecated'; deprecated( 'Eating meat', { since: '2019.01.01', version: '2020.01.01', alternative: 'vegetables', plugin: 'the earth', hint: 'You may find it beneficial to transition gradually.',} ); // Logs: 'Eating meat is deprecated since version 2019.01.01 and will be removed from the earth in version 2020.01.01. Please use vegetables instead. Note: You may find it beneficial to transition gradually.'
```

*Parameters*

- *feature* `string`: Name of the deprecated feature.
- *options* `[DeprecatedOptions]`: Personalisation options

### logged

Object map tracking messages which have been logged, for use in ensuring a message is only logged once.

*Type*

- `Record< string, true >`

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).

---

---

https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-tests/
# @wordpress/e2e-tests

This package contains test plugins and mu-plugins used by E2E tests in WordPress.

**Note**: The E2E tests themselves have been migrated to Playwright and are now located in `/test/e2e/`.

## Contents

- `/plugins/` – Test plugins used by E2E tests
- `/mu-plugins/` – Must-use plugins for test environment configuration
- `/assets/` – Test assets (images, etc.)

## Usage

These plugins and mu-plugins are automatically loaded in the test environment via `wp-env`. They provide test fixtures and functionality needed for various E2E test scenarios.

For information about writing E2E tests, see the [E2E testing guide](https://github.com/WordPress/gutenberg/tree/HEAD/docs/contributors/code/e2e/README.md).

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).

---

https://developer.wordpress.org/block-editor/reference-guides/packages/packages-library-export-default-webpack-plugin/
DEPRECATED for webpack v5: please use output.library.export instead.

---
