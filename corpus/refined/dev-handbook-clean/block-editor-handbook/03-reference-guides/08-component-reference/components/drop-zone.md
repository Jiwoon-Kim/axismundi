---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/drop-zone/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: drop-zone
parent_order: 3
sub_order: 8
page_order: 39
title: "DropZone"
code_quality: degraded
code_issue: pre_newline_loss
---

# DropZone

`DropZone` is a component creating a drop zone area taking the full size of its parent element. It supports dropping files, HTML content or any other HTML drop event.

## Usage

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```jsx
import { useState } from 'react';import { DropZone } from '@wordpress/components'; const MyDropZone = () => { const [ hasDropped, setHasDropped ] = useState( false ); return ( <div> { hasDropped ? 'Dropped!' : 'Drop something here' } <DropZone onFilesDrop={ () => setHasDropped( true ) } onHTMLDrop={ () => setHasDropped( true ) } onDrop={ () => setHasDropped( true ) } /> </div> );}
```

## Props

The component accepts the following props:

### className

A CSS `class` to give to the wrapper element.

- Type: `String`
- Default: `undefined`

### label

A string to be shown within the drop zone area.

- Type: `String`
- Default: `Drop files to upload`

### onFilesDrop

The function is called when dropping a file into the `DropZone`. It receives an array of dropped files as an argument.

- Type: `Function`
- Required: No
- Default: `noop`

### onHTMLDrop

The function is called when dropping HTML into the `DropZone`. It receives the HTML being dropped as an argument.

- Type: `Function`
- Required: No
- Default: `noop`

### onDrop

The function is generic drop handler called if the `onFilesDrop` or `onHTMLDrop` are not called. It receives the drop `event` object as an argument.

- Type: `Function`
- Required: No
- Default: `noop`
