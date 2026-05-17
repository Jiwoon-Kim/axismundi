---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils-playwright/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: e2e-test-utils-playwright
parent_order: 3
sub_order: 9
page_order: 44
title: "@wordpress/e2e-test-utils-playwright"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/e2e-test-utils-playwright

End-To-End (E2E) Playwright test utils for WordPress.

*It works properly with the minimum version of Gutenberg `9.2.0` or the minimum version of WordPress `5.6.0`.*

This package is still under active development. Documentation might not be up-to-date, and the `v0.x` version can introduce breaking changes without a detailed migration guide. Early adopters are encouraged to use a [lock file](https://docs.npmjs.com/cli/v9/configuring-npm/package-lock-json) to prevent unexpected breakages.

## Installation

Install the module

```bash
npm install @wordpress/e2e-test-utils-playwright --save-dev
```

**Note**: This package requires Node.js version with long-term support status (check [Active LTS or Maintenance LTS releases](https://nodejs.org/en/about/previous-releases)). It is not compatible with older versions.

## API

### test

The extended Playwright’s [test](https://playwright.dev/docs/api/class-test) module with the `admin`, `editor`, `pageUtils` and the `requestUtils` fixtures.

### expect

The Playwright/Jest’s [expect](https://jestjs.io/docs/expect) function.

### Admin

End to end test utilities for WordPress admin’s user interface.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const admin = new Admin( { page, pageUtils } );await admin.visitAdminPage( 'options-general.php' );
```

### Editor

End to end test utilities for the WordPress Block Editor.

To use these utilities, instantiate them within each test file:

```php
test.use( { editor: async ( { page }, use ) => { await use( new Editor( { page } ) ); },} );
```

Within a test or test utility, use the `canvas` property to select elements within the iframe canvas:

```js
await editor.canvas.locator( 'role=document[name="Paragraph block"i]' );
```

### PageUtils

Generic Playwright utilities for interacting with web pages.

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const pageUtils = new PageUtils( { page } );await pageUtils.pressKeys( 'primary+a' );
```

### RequestUtils

Playwright utilities for interacting with the WordPress REST API.

Create a request utils instance.

```js
const requestUtils = await RequestUtils.setup( { user: { username: 'admin', password: 'password', },} );
```

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
