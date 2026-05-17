---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-latex-to-mathml/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: latex-to-mathml
parent_order: 3
sub_order: 9
page_order: 73
title: "@wordpress/latex-to-mathml"
---

# @wordpress/latex-to-mathml

Convert LaTeX math syntax to MathML.

## Installation

Install the module:

```bash
npm install @wordpress/latex-to-mathml --save
```

## Usage

```js
import latexToMathML from '@wordpress/latex-to-mathml'; // Convert LaTeX to MathML (display mode, for block equations)const mathML = latexToMathML( 'E = mc^2' ); // Convert LaTeX to MathML (inline mode)const inlineMathML = latexToMathML( 'x^2', { displayMode: false } );
```

## API

### latexToMathML( latex, options )

Converts LaTeX math syntax to MathML.

#### Parameters

- `latex` (`string`): The LaTeX string to convert.
- `options` (`Object`): Optional conversion options.
```text
- `displayMode` (`boolean`): Whether to render in display mode (block) or inline mode. Default: `true`.
```

#### Returns

- (`string`): The MathML string.

#### Throws

- Will throw an error if the LaTeX syntax is invalid.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
