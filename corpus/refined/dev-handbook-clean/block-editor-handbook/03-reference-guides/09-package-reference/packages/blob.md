---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-blob/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: blob
parent_order: 3
sub_order: 9
page_order: 13
title: "@wordpress/blob"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/blob

Blob utilities for WordPress.

## Installation

Install the module

```bash
npm install @wordpress/blob --save
```

## API

### createBlobURL

Create a blob URL from a file.

*Parameters*

- *file* `File`: The file to create a blob URL for.

*Returns*

- `string`: The blob URL.

### downloadBlob

Downloads a file, e.g., a text or readable stream, in the browser. Appropriate for downloading smaller file sizes, e.g., \&lt; 5 MB.

Example usage:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
const fileContent = JSON.stringify( { title: 'My Post', }, null, 2);const filename = 'file.json'; downloadBlob( filename, fileContent, 'application/json' );
```

*Parameters*

- *filename* `string`: File name.
- *content* `BlobPart`: File content (BufferSource | Blob | string).
- *contentType* `string`: (Optional) File mime type. Default is `''`.

### getBlobByURL

Retrieve a file based on a blob URL. The file must have been created by `createBlobURL` and not removed by `revokeBlobURL`, otherwise it will return `undefined`.

*Parameters*

- *url* `string`: The blob URL.

*Returns*

- `File | undefined`: The file for the blob URL.

### getBlobTypeByURL

Retrieve a blob type based on URL. The file must have been created by `createBlobURL` and not removed by `revokeBlobURL`, otherwise it will return `undefined`.

*Parameters*

- *url* `string`: The blob URL.

*Returns*

- `string | undefined`: The blob type.

### isBlobURL

Check whether a url is a blob url.

*Parameters*

- *url* `string | undefined`: The URL.

*Returns*

- `boolean`: Is the url a blob url?

### revokeBlobURL

Remove the resource and file cache from memory.

*Parameters*

- *url* `string`: The blob URL.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
