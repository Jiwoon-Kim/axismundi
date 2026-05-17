---
source_url: https://developer.wordpress.org/block-editor/reference-guides/components/sandbox/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: component-reference
slug: sandbox
parent_order: 3
sub_order: 8
page_order: 94
title: "Sandbox"
---

# Sandbox

This component provides an isolated environment for arbitrary HTML via iframes.

## Usage

```jsx
import { SandBox } from '@wordpress/components'; const MySandBox = () => ( <SandBox html="<p>Content</p>" title="SandBox" type="embed" />);
```

## Props

### html: string

The HTML to render in the body of the iframe document.

- Required: No
- Default: “”

### [onFocus: React.DOMAttributes&lt; HTMLIFrameElement &gt;\[ ‘onFocus’ \]](https://developer.wordpress.org/block-editor/reference-guides/components/sandbox/#onfocus-react-domattributes-htmliframeelement-onfocus)

The `onFocus` callback for the iframe.

- Required: No

### [scripts: string\[\]](https://developer.wordpress.org/block-editor/reference-guides/components/sandbox/#scripts-string)

An array of script URLs to inject as `<script>` tags into the bottom of the `<body>` of the iframe document.

- Required: No
- Default: []

### [styles: string\[\]](https://developer.wordpress.org/block-editor/reference-guides/components/sandbox/#styles-string)

An array of CSS strings to inject into the `<head>` of the iframe document.

- Required: No
- Default: []

### title: string

The `<title>` of the iframe document.

- Required: No
- Default: “”

### type: string

The CSS class name to apply to the `<html>` and `<body>` elements of the iframe.

- Required: No
- Default: “”

### [tabIndex: HTMLElement\[ ‘tabIndex’ \]](https://developer.wordpress.org/block-editor/reference-guides/components/sandbox/#tabindex-htmlelement-tabindex)

The `tabindex` the iframe should receive.

- Required: No
