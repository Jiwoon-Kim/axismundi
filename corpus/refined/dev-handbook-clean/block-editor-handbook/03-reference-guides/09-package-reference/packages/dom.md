---
source_url: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dom/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: package-reference
slug: dom
parent_order: 3
sub_order: 9
page_order: 43
title: "@wordpress/dom"
---

# @wordpress/dom

DOM utilities module for WordPress.

## Installation

Install the module

```bash
npm install @wordpress/dom --save
```

## API

### computeCaretRect

Get the rectangle for the selection in a container.

*Parameters*

- *win* `Window`: The window of the selection.

*Returns*

- `DOMRect | null`: The rectangle.

### documentHasSelection

Check whether the current document has a selection. This includes focus in input fields, textareas, and general rich-text selection.

*Parameters*

- *doc* `Document`: The document to check.

*Returns*

- `boolean`: True if there is selection, false if not.

### documentHasTextSelection

Check whether the current document has selected text. This applies to ranges of text in the document, and not selection inside `<input>` and `<textarea>` elements.

See: [https://developer.mozilla.org/en-US/docs/Web/API/Window/getSelection#Related_objects](https://developer.mozilla.org/en-US/docs/Web/API/Window/getSelection#Related_objects).

*Parameters*

- *doc* `Document`: The document to check.

*Returns*

- `boolean`: True if there is selection, false if not.

### documentHasUncollapsedSelection

Check whether the current document has any sort of (uncollapsed) selection. This includes ranges of text across elements and any selection inside textual `<input>` and `<textarea>` elements.

*Parameters*

- *doc* `Document`: The document to check.

*Returns*

- `boolean`: Whether there is any recognizable text selection in the document.

### focus

Object grouping `focusable` and `tabbable` utils under the keys with the same name.

### getFilesFromDataTransfer

Gets all files from a DataTransfer object.

*Parameters*

- *dataTransfer* `DataTransfer`: DataTransfer object to inspect.

*Returns*

- `File[]`: An array containing all files.

### getOffsetParent

Returns the closest positioned element, or null under any of the conditions of the offsetParent specification. Unlike offsetParent, this function is not limited to HTMLElement and accepts any Node (e.g. Node.TEXT\_NODE).

*Related*

- [https://drafts.csswg.org/cssom-view/#dom-htmlelement-offsetparent](https://drafts.csswg.org/cssom-view/#dom-htmlelement-offsetparent)

*Parameters*

- *node* `Node`: Node from which to find offset parent.

*Returns*

- `Node | null`: Offset parent.

### getPhrasingContentSchema

Get schema of possible paths for phrasing content.

*Related*

- [https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Content_categories#Phrasing_content](https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Content_categories#Phrasing_content)

*Parameters*

- *context* `[string]`: Set to “paste” to exclude invisible elements and sensitive data.

*Returns*

- `Partial<ContentSchema>`: Schema.

### getRectangleFromRange

Get the rectangle of a given Range. Returns `null` if no suitable rectangle can be found. Use instead of `Range.getBoundingClientRect()`, which is often broken, especially for collapsed ranges.

*Parameters*

- *range* `Range`: The range.

*Returns*

- `DOMRect?`: The rectangle.

### getScrollContainer

Given a DOM node, finds the closest scrollable container node or the node itself, if scrollable.

*Parameters*

- *node* `Element | null`: Node from which to start.
- *direction* `?string`: Direction of scrollable container to search for (‘vertical’, ‘horizontal’, ‘all’). Defaults to ‘vertical’.

*Returns*

- `Element | undefined`: Scrollable container node, if found.

### insertAfter

Given two DOM nodes, inserts the former in the DOM as the next sibling of the latter.

*Parameters*

- *newNode* `Node`: Node to be inserted.
- *referenceNode* `Node`: Node after which to perform the insertion.

*Returns*

- `void`:

### isEmpty

Recursively checks if an element is empty. An element is not empty if it contains text or contains elements with attributes such as images.

*Parameters*

- *element* `Element`: The element to check.

*Returns*

- `boolean`: Whether or not the element is empty.

### isEntirelySelected

Check whether the contents of the element have been entirely selected. Returns true if there is no possibility of selection.

*Parameters*

- *element* `HTMLElement`: The element to check.

*Returns*

- `boolean`: True if entirely selected, false if not.

### isFormElement

Detects if element is a form element.

*Parameters*

- *element* `Element`: The element to check.

*Returns*

- `boolean`: True if form element and false otherwise.

### isHorizontalEdge

Check whether the selection is horizontally at the edge of the container.

*Parameters*

- *container* `HTMLElement`: Focusable element.
- *isReverse* `boolean`: Set to true to check left, false for right.

*Returns*

- `boolean`: True if at the horizontal edge, false if not.

### isNumberInput

Check whether the given element is an input field of type number.

*Parameters*

- *node* `Node`: The HTML node.

*Returns*

- `node is HTMLInputElement`: True if the node is number input.

### isPhrasingContent

Find out whether or not the given node is phrasing content.

*Related*

- [https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Content_categories#Phrasing_content](https://developer.mozilla.org/en-US/docs/Web/Guide/HTML/Content_categories#Phrasing_content)

*Parameters*

- *node* `Node`: The node to test.

*Returns*

- `boolean`: True if phrasing content, false if not.

### isRTL

Whether the element’s text direction is right-to-left.

*Parameters*

- *element* `Element`: The element to check.

*Returns*

- `boolean`: True if rtl, false if ltr.

### isSelectionForward

Returns true if the given selection object is in the forward direction, or false otherwise.

*Related*

- [https://developer.mozilla.org/en-US/docs/Web/API/Node/compareDocumentPosition](https://developer.mozilla.org/en-US/docs/Web/API/Node/compareDocumentPosition)

*Parameters*

- *selection* `Selection`: Selection object to check.

*Returns*

- `boolean`: Whether the selection is forward.

### isTextContent

*Parameters*

- *node* `Node`:

*Returns*

- `boolean`: Node is text content

### isTextField

Check whether the given element is a text field, where text field is defined by the ability to select within the input, or that it is contenteditable.

See: [https://html.spec.whatwg.org/#textFieldSelection](https://html.spec.whatwg.org/#textFieldSelection)

*Parameters*

- *node* `Node`: The HTML element.

*Returns*

- `node is HTMLElement`: True if the element is an text field, false if not.

### isVerticalEdge

Check whether the selection is vertically at the edge of the container.

*Parameters*

- *container* `HTMLElement`: Focusable element.
- *isReverse* `boolean`: Set to true to check top, false for bottom.

*Returns*

- `boolean`: True if at the vertical edge, false if not.

### placeCaretAtHorizontalEdge

Places the caret at start or end of a given element.

*Parameters*

- *container* `HTMLElement`: Focusable element.
- *isReverse* `boolean`: True for end, false for start.

### placeCaretAtVerticalEdge

Places the caret at the top or bottom of a given element.

*Parameters*

- *container* `HTMLElement`: Focusable element.
- *isReverse* `boolean`: True for bottom, false for top.
- *rect* `[DOMRect]`: The rectangle to position the caret with.

### remove

Given a DOM node, removes it from the DOM.

*Parameters*

- *node* `Node`: Node to be removed.

*Returns*

- `void`:

### removeInvalidHTML

Given a schema, unwraps or removes nodes, attributes and classes on HTML.

*Parameters*

- *HTML* `string`: The HTML to clean up.
- *schema* `import('./clean-node-list').Schema`: Schema for the HTML.
- *inline* `boolean`: Whether to clean for inline mode.

*Returns*

- `string`: The cleaned up HTML.

### replace

Given two DOM nodes, replaces the former with the latter in the DOM.

*Parameters*

- *processedNode* `Element`: Node to be removed.
- *newNode* `Element`: Node to be inserted in its place.

*Returns*

- `void`:

### replaceTag

Replaces the given node with a new node with the given tag name.

*Parameters*

- *node* `Element`: The node to replace
- *tagName* `string`: The new tag name.

*Returns*

- `Element`: The new node.

### safeHTML

Strips scripts and on\* attributes from HTML.

*Parameters*

- *html* `string`: HTML to sanitize.

*Returns*

- `string`: The sanitized HTML.

### unwrap

Unwrap the given node. This means any child nodes are moved to the parent.

*Parameters*

- *node* `Node`: The node to unwrap.

*Returns*

- `void`:

### wrap

Wraps the given node with a new node with the given tag name.

*Parameters*

- *newNode* `Element`: The node to insert.
- *referenceNode* `Element`: The node to wrap.

## Contributing to this package

This is an individual package that’s part of the Gutenberg project. The project is organized as a monorepo. It’s made up of multiple self-contained software packages, each with a specific purpose. The packages in this monorepo are published to [npm](https://www.npmjs.com/) and used by [WordPress](https://make.wordpress.org/core/) as well as other software projects.

To find out more about contributing to this package or Gutenberg as a whole, please read the project’s main [contributor guide](https://github.com/WordPress/gutenberg/tree/HEAD/CONTRIBUTING.md).
