---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-keyboard-shortcuts/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: keyboard-shortcuts
parent_order: 3
sub_order: 9
page_order: 71
title: "@wordpress/keyboard-shortcuts"
---

# @wordpress/keyboard-shortcuts

Keyboard shortcuts is a generic package that allows registering and modifying shortcuts.

## Installation

Install the module

```bash
npm install @wordpress/keyboard-shortcuts --save
```

*This package assumes that your code will run in an **ES2015+** environment. If you’re using an environment that has limited or no support for such language features and APIs, you should include [the polyfill shipped in `@wordpress/babel-preset-default`](https://github.com/WordPress/gutenberg/tree/HEAD/packages/babel-preset-default#polyfill) in your code.*

## API

### ShortcutProvider

Handles callbacks added to context by `useShortcut`. Adding a provider allows to register contextual shortcuts that are only active when a certain part of the UI is focused.

*Parameters*

- *props* `ShortcutProviderProps`: Props to pass to `div`.

*Returns*

- Component.

### store

Store definition for the keyboard shortcuts namespace.

*Related*

- [https://github.com/WordPress/gutenberg/blob/HEAD/packages/data/README.md#createReduxStore](https://github.com/WordPress/gutenberg/blob/HEAD/packages/data/README.md#createReduxStore)

### useShortcut

Attach a keyboard shortcut handler.

*Parameters*

- *name* `string`: Shortcut name.
- *callback* `( event: KeyboardEvent ) => void`: Shortcut callback.
- *options* `UseShortcutOptions`: Shortcut options.
- *options.isDisabled* `UseShortcutOptions[ 'isDisabled' ]`: Whether to disable the shortcut.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
