---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-wordcount/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: wordcount
parent_order: 3
sub_order: 9
page_order: 118
title: "@wordpress/wordcount"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/wordcount

WordPress word count utility.

## Installation

Install the module

```bash
npm install @wordpress/wordcount --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## API

### count

Count some words.

*Usage*

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { count } from '@wordpress/wordcount';const numberOfWords = count( 'Words to count', 'words', {} );
```

*Parameters*

- *text* `string`: The text being processed
- *type* `Strategy`: The type of count. Accepts ‘words’, ‘characters\_excluding\_spaces’, or ‘characters\_including\_spaces’.
- *userSettings* `UserSettings`: Custom settings object.

*Returns*

- `number`: The word or character count.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
