---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-route/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: route
parent_order: 3
sub_order: 9
page_order: 99
title: "@wordpress/route"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/route

Routing utilities for WordPress admin interfaces, providing a shared instance of TanStack Router.

## Installation

Install the module:

```bash
npm install @wordpress/route --save
```

## Usage

This package provides a shared instance of TanStack Router to ensure consistent routing across WordPress admin interfaces.

### Public API

The following hooks and components are available for use in routes:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { Link, useNavigate, useParams, useRouter, useSearch } from '@wordpress/route'; function MyRoute() { const params = useParams(); const navigate = useNavigate(); const search = useSearch(); return ( <div> <Link to="/other-route">Go to other route</Link> <button onClick={() => navigate({ to: '/somewhere' })}> Navigate </button> </div> );}
```

### Private API

The boot package uses private APIs for router setup. These should not be used by individual routes.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose.

To find out more about contributing to this package or Gutenberg as a whole, please read the [project’s main contributor guide](https://github.com/WordPress/gutenberg/blob/HEAD/CONTRIBUTING.md).
