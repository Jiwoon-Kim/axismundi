---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-priority-queue/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: priority-queue
parent_order: 3
sub_order: 9
page_order: 91
title: "@wordpress/priority-queue"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/priority-queue

This module allows you to run a queue of callback while on the browser’s idle time making sure the higher-priority work is performed first.

## Installation

Install the module

```bash
npm install @wordpress/priority-queue --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## API

### createQueue

Creates a context-aware queue that only executes the last task of a given context.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { createQueue } from '@wordpress/priority-queue'; const queue = createQueue(); // Context objects.const ctx1 = {};const ctx2 = {}; // For a given context in the queue, only the last callback is executed.queue.add( ctx1, () => console.log( 'This will be printed first' ) );queue.add( ctx2, () => console.log( "This won't be printed" ) );queue.add( ctx2, () => console.log( 'This will be printed second' ) );
```

*Returns*

- `WPPriorityQueue`: Queue object with `add`, `flush` and `reset` methods.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
