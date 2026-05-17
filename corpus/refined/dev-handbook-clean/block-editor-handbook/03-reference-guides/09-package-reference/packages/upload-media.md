---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-upload-media/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: upload-media
parent_order: 3
sub_order: 9
page_order: 111
title: "@wordpress/upload-media"
code_quality: degraded
code_issue: pre_newline_loss
---

# @wordpress/upload-media

This package is still experimental. “Experimental” means this is an early implementation subject to drastic and breaking changes.

This module is a media upload handler with a queue-like system that is implemented using a custom `@wordpress/data` store.

Such a system is useful for additional client-side processing of media files (e.g. image compression) before uploading them to a server.

It is typically used by `@wordpress/block-editor` but can also be leveraged outside of it.

## Installation

Install the module

```bash
npm install @wordpress/upload-media --save
```

## Usage

This is a basic example of how one can interact with the upload data store:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```js
import { store as uploadStore } from '@wordpress/upload-media';import { dispatch } from '@wordpress/data'; dispatch( uploadStore ).updateSettings( /* ... */ );dispatch( uploadStore ).addItems( [ /* ... */] );
```

Refer to the API reference below or the TypeScript types for further help.

## API Reference

### Actions

The following set of dispatching action creators are available on the object returned by `wp.data.dispatch( 'core/upload-media' )`:

#### addItems

Adds a new item to the upload queue.

*Parameters*

- *$0* `AddItemsArgs`:
- *$0.files* `AddItemsArgs[ 'files' ]`: Files
- *$0.onChange* `[AddItemsArgs[ 'onChange' ]]`: Function called each time a file or a temporary representation of the file is available.
- *$0.onSuccess* `[AddItemsArgs[ 'onSuccess' ]]`: Function called after the file is uploaded.
- *$0.onBatchSuccess* `[AddItemsArgs[ 'onBatchSuccess' ]]`: Function called after a batch of files is uploaded.
- *$0.onError* `[AddItemsArgs[ 'onError' ]]`: Function called when an error happens.
- *$0.additionalData* `[AddItemsArgs[ 'additionalData' ]]`: Additional data to include in the request.
- *$0.allowedTypes* `[AddItemsArgs[ 'allowedTypes' ]]`: Array with the types of media that can be uploaded, if unset all types are allowed.

#### cancelItem

Cancels an item in the queue based on an error.

*Parameters*

- *id* `QueueItemId`: Item ID.
- *error* `Error`: Error instance.
- *silent* Whether to cancel the item silently, without invoking its `onError` callback.

#### retryItem

Retries a failed item in the queue.

*Parameters*

- *id* `QueueItemId`: Item ID.

### Selectors

The following selectors are available on the object returned by `wp.data.select( 'core/upload-media' )`:

#### getItems

Returns all items currently being uploaded.

*Parameters*

- *state* `State`: Upload state.

*Returns*

- `QueueItem[]`: Queue items.

#### getSettings

Returns the media upload settings.

*Parameters*

- *state* `State`: Upload state.

*Returns*

- `Settings`: Settings

#### isUploading

Determines whether any upload is currently in progress.

*Parameters*

- *state* `State`: Upload state.

*Returns*

- `boolean`: Whether any upload is currently in progress.

#### isUploadingById

Determines whether an upload is currently in progress given an attachment ID.

*Parameters*

- *state* `State`: Upload state.
- *attachmentId* `number`: Attachment ID.

*Returns*

- `boolean`: Whether upload is currently in progress for the given attachment.

#### isUploadingByUrl

Determines whether an upload is currently in progress given an attachment URL.

*Parameters*

- *state* `State`: Upload state.
- *url* `string`: Attachment URL.

*Returns*

- `boolean`: Whether upload is currently in progress for the given attachment.
