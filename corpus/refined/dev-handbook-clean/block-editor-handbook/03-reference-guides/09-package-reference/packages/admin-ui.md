---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-admin-ui/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: admin-ui
parent_order: 3
sub_order: 9
page_order: 3
title: "@wordpress/admin-ui"
---

# @wordpress/admin-ui

UI components for building consistent admin page layouts.

While `@wordpress/ui` provides low-level, generic UI components that can be composed in flexible arrangements for building admin features, the purpose of this package is to guarantee consistency in the common page structure of an admin page layout. This includes high-level abstractions for a page, its sidebar, header, navigation, and other standardized page layout elements. The goal of standardizing these layouts is to provide a cohesive and predictable experience for users.

## Installation

Install the module

```bash
npm install @wordpress/admin-ui --save
```

## Setup

This package requires CSS from multiple dependency packages.

### Within WordPress

To ensure proper load order, add the `wp-components` stylesheet as a dependency of your plugin’s stylesheet. See [wp_enqueue_style documentation](https://developer.wordpress.org/reference/functions/wp_enqueue_style/#parameters) for how to specify dependencies.

### Outside WordPress

Install and load these stylesheets in your application:

```js
npm install @wordpress/admin-ui @wordpress/theme @wordpress/components

import '@wordpress/theme/design-tokens.css';import '@wordpress/components/build-style/style.css';
```

RTL versions of the stylesheets are available in the same paths, but with `-rtl` appended to the filename (`style-rtl.css`). The design tokens stylesheet is universal and does not have a separate RTL version.

## API

### Breadcrumbs

Renders a breadcrumb navigation trail.

All items except the last one must provide a `to` prop for navigation. In development mode, an error is thrown when a non-last item is missing `to`. The last item represents the current page and its `to` prop is optional. Only the last item (when it has no `to` prop) is rendered as an `h1`.

*Usage*

```jsx
<Breadcrumbs items={ [ { label: 'Home', to: '/' }, { label: 'Settings', to: '/settings' }, { label: 'General' }, ] }/>
```

*Parameters*

- *props* `BreadcrumbsProps`:
- *props.items* `BreadcrumbsProps[ 'items' ]`: The breadcrumb items to display.

### NavigableRegion

Undocumented declaration.

### Page

Undocumented declaration.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
