---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-patterns/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: patterns
parent_order: 3
sub_order: 9
page_order: 83
title: "@wordpress/patterns"
---

# @wordpress/patterns

> 
> **Note**  
> This package is currently only used internally by the Gutenberg project to manage the creation and editing of user patterns using the `wp_block` CPT in the context of the block editor. The likes of the `PatternsMenuItems` component expect to be rendered within a `BlockEditorProvider` in order to work.

## Installation

Install the module

```bash
npm install @wordpress/patterns --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## Components

This package doesn’t currently have any publicly exported components.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
