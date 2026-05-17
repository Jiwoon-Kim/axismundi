---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-media-fields/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: media-fields
parent_order: 3
sub_order: 9
page_order: 78
title: "@wordpress/media-fields"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/media-fields

This package provides reusable field definitions for displaying and editing media attachment properties in WordPress DataViews. It’s primarily intended for internal use within Gutenberg and may change significantly between releases.

## Usage

### Available Fields

This package exports field definitions for common media attachment properties:

- `altTextField` – Alternative text for images
- `captionField` – Media caption text
- `descriptionField` – Detailed description
- `filenameField` – File name (read-only)
- `filesizeField` – File size with human-readable formatting
- `mediaDimensionsField` – Image dimensions (width × height)
- `mediaThumbnailField` – Thumbnail preview
- `mimeTypeField` – MIME type display

### Using Media Fields in DataViews

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { altTextField, captionField, filesizeField,} from '@wordpress/media-fields';import { DataViews } from '@wordpress/dataviews'; const fields = [ altTextField, captionField, filesizeField,]; export function MyMediaLibrary( { items } ) { return ( <DataViews data={ items } fields={ fields } view={ view } onChangeView={ setView } /> );}
```

## Contributing to this package

This package is part of the Gutenberg project. To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
