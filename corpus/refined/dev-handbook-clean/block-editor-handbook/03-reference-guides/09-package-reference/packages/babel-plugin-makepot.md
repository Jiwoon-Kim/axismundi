---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-babel-plugin-makepot/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: babel-plugin-makepot
parent_order: 3
sub_order: 9
page_order: 9
title: "@wordpress/babel-plugin-makepot"
---

# @wordpress/babel-plugin-makepot

Babel plugin used to scan JavaScript files for use of localization functions. It then compiles these into a [gettext POT formatted](https://en.wikipedia.org/wiki/Gettext) file as a template for translation. By default the output file will be written to `gettext.pot` of the root project directory. This can be overridden using the `"output"` option of the plugin.

```json
{ "plugins": [ [ "@wordpress/babel-plugin-makepot", { "output": "languages/myplugin.pot" } ] ]}
```

## Installation

Install the module:

```bash
npm install @wordpress/babel-plugin-makepot --save-dev
```

**Note**: This package requires Node.js version with long-term support status (check [Active LTS or Maintenance LTS releases](https://nodejs.org/en/about/previous-releases)). It is not compatible with older versions.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
