---
source_url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-attributes/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: block-api-reference
slug: attributes
parent_order: 3
sub_order: 1
page_order: 3
title: "Attributes"
---

# Attributes

Block attributes provide information about the data stored by a block. For example, rich content, a list of image URLs, a background color, or a button title.

A block can contain any number of attributes, and these are specified by the `attributes` field – an object where each key is the name of the attribute, and the value is the attribute definition.

The attribute definition will contain, at a minimum, either a `type` or an `enum`. There may be additional fields.

*Example*: Attributes object defining three attributes – `url`, `title`, and `size`.

```text
{ url: { type: 'string', source: 'attribute', selector: 'img', attribute: 'src', }, title: { type: 'string', }, size: { enum: [ 'large', 'small' ], },}
```

When a block is parsed this definition will be used to extract data from the block content. Anything that matches will be available to your block through the `attributes` prop.

This parsing process can be summarized as:

1. Extract value from the `source`.
2. Check value matches the `type`, or is one of the `enum` values.

*Example*: Attributes available in the `edit` and function, using the above attributes definition.

```js
function YourBlockEdit( { attributes } ) { return ( <p>URL is { attributes.url }, title is { attributes.title }, and size is { attributes.size }.</p> )}
```

The block is responsible for using the `save` function to ensure that all attributes with a `source` field are saved according to the attributes definition. This is not automatic.

Attributes without a `source` will be automatically saved in the block [comment delimiter](../../04-explanations/01-architecture/key-concepts.md#data-attributes).

For example, using the above attributes definition you would need to ensure that your `save` function has a corresponding img tag for the `url` attribute. The `title` and `size` attributes will be saved in the comment delimiter.

*Example*: Example `save` function that contains the `url` attribute.

```js
function YourBlockSave( { attributes } ) { return ( <img src={ attributes.url } /> )}
```

The saved HTML will contain the `title` and `size` in the comment delimiter, and the `url` in the `img` node.

```html
<!-- block:your-block {"title":"hello world","size":"large"} --><img src="/image.jpg" /><!-- /block:your-block -->
```

If an attribute changes over time then a [block deprecation](deprecation.md) can help migrate from an older attribute, or remove it entirely.

## Type validation

The `type` indicates the type of data that is stored by the attribute. It does not indicate where the data is stored, which is defined by the `source` field.

A `type` is required, unless an `enum` is provided. A `type` can be used with an `enum`.

The `type` field MUST be one of the following:

- `null`
- `boolean`
- `object`
- `array`
- `string`
- `integer`
- `number` (same as `integer`)

Note that the validity of an `object` is determined by your `source`. For an example, see the `query` details below.

## Enum validation

An attribute can be defined as one of a fixed set of values. This is specified by an `enum`, which contains an array of allowed values:

*Example*: Example `enum`.

```text
{ size: { enum: [ 'large', 'small', 'tiny' ] }}
```

## Value source

Attribute sources are used to define how the attribute values are extracted from saved post content. They provide a mechanism to map from the saved markup to a JavaScript representation of a block.

The available `source` values are:  
– `(no value)` – when no `source` is specified then data is stored in the block’s [comment delimiter](../../04-explanations/01-architecture/key-concepts.md#data-attributes).  
– `attribute` – data is stored in an HTML element attribute.  
– `text` – data is stored in HTML text.  
– `html` – data is stored in HTML. This is typically used by `RichText`.  
– `query` – data is stored as an array of objects.  
– `meta` – data is stored in post meta (deprecated).

The `source` field is usually combined with a `selector` field. If no selector argument is specified, the source definition runs against the block’s root node. If a selector argument is specified, it will run against the matching element(s) within the block.

The `selector` can be an HTML tag, or anything queryable with [querySelector](https://developer.mozilla.org/en-US/docs/Web/API/Document/querySelector), such as a class or id attribute. Examples are given below.

For example, a `selector` of `img` will match an `img` element, and `img.class` will match an `img` element that has a class of `class`.

Under the hood, attribute sources are a superset of the functionality provided by [hpq](https://github.com/aduth/hpq), a small library used to parse and query HTML markup into an object shape.

To summarize, the `source` determines where data is stored in your content, and the `type` determines what that data is. To reduce the amount of data stored it is usually better to store as much data as possible within HTML rather than as attributes within the comment delimiter.

### attribute source

Use an `attribute` source to extract the value from an attribute in the markup. The attribute is specified by the `attribute` field, which must be supplied.

*Example*: Extract the `src` attribute from an image found in the block’s markup.

Saved content:

```html
<div> Block Content <img src="https://lorempixel.com/1200/800/" /></div>
```

Attribute definition:

```text
{ url: { type: 'string', source: 'attribute', selector: 'img', attribute: 'src', }}
```

Attribute available in the block:

```json
{ "url": "https://lorempixel.com/1200/800/" }
```

Most attributes from markup will be of type `string`. Numeric attributes in HTML are still stored as strings, and are not converted automatically.

*Example*: Extract the `width` attribute from an image found in the block’s markup.

Saved content:

```html
<div> Block Content <img src="https://lorempixel.com/1200/800/" width="50" /></div>
```

Attribute definition:

```text
{ width: { type: 'string', source: 'attribute', selector: 'img', attribute: 'width', }}
```

Attribute available in the block:

```json
{ "width": "50" }
```

The only exception is when checking for the existence of an attribute (for example, the `disabled` attribute on a `button`). In that case type `boolean` can be used and the stored value will be a boolean.

*Example*: Extract the `disabled` attribute from a button found in the block’s markup.

Saved content:

```html
<div> Block Content <button type="button" disabled>Button</button></div>
```

Attribute definition:

```text
{ disabled: { type: 'boolean', source: 'attribute', selector: 'button', attribute: 'disabled', }}
```

Attribute available in the block:

```json
{ "disabled": true }
```

### text source

Use `text` to extract the inner text from markup. Note that HTML is returned according to the rules of [`textContent`](https://developer.mozilla.org/en-US/docs/Web/API/Node/textContent).

*Example*: Extract the `content` attribute from a figcaption element found in the block’s markup.

Saved content:

```html
<figure> <img src="/image.jpg" /> <figcaption>The inner text of the figcaption element</figcaption></figure>
```

Attribute definition:

```text
{ content: { type: 'string', source: 'text', selector: 'figcaption', }}
```

Attribute available in the block:

```json
{ "content": "The inner text of the figcaption element" }
```

Another example, using `text` as the source, and using `.my-content` class as the selector to extract text:

*Example*: Extract the `content` attribute from an element with `.my-content` class found in the block’s markup.

Saved content:

```html
<div> <img src="/image.jpg" /> <p class="my-content">The inner text of .my-content class</p></div>
```

Attribute definition:

```text
{ content: { type: 'string', source: 'text', selector: '.my-content', }}
```

Attribute available in the block:

```json
{ "content": "The inner text of .my-content class" }
```

### html source

Use `html` to extract the inner HTML from markup. Note that text is returned according to the rules of [`innerHTML`](https://developer.mozilla.org/en-US/docs/Web/API/Element/innerHTML).

*Example*: Extract the `content` attribute from a figcaption element found in the block’s markup.

Saved content:

```html
<figure> <img src="/image.jpg" /> <figcaption>The inner text of the <strong>figcaption</strong> element</figcaption></figure>
```

Attribute definition:

```text
{ content: { type: 'string', source: 'html', selector: 'figcaption', }}
```

Attribute available in the block:

```json
{ "content": "The inner text of the <strong>figcaption</strong> element" }
```

### query source

Use `query` to extract an array of values from markup. Entries of the array are determined by the `selector` argument, where each matched element within the block will have an entry structured corresponding to the second argument, an object of attribute sources.

The `query` field is effectively a nested block attributes definition. It is possible (although not necessarily recommended) to nest further.

*Example*: Extract `src` and `alt` from each image element in the block’s markup.

Saved content:

```html
<div> <img src="https://lorempixel.com/1200/800/" alt="large image" /> <img src="https://lorempixel.com/50/50/" alt="small image" /></div>
```

Attribute definition:

```text
{ images: { type: 'array', source: 'query', selector: 'img', query: { url: { type: 'string', source: 'attribute', attribute: 'src', }, alt: { type: 'string', source: 'attribute', attribute: 'alt', }, } }}
```

Attribute available in the block:

```json
{ "images": [ { "url": "https://lorempixel.com/1200/800/", "alt": "large image" }, { "url": "https://lorempixel.com/50/50/", "alt": "small image" } ]}
```

### Meta source (deprecated)

Although attributes may be obtained from a post’s meta, meta attribute sources are considered deprecated; [EntityProvider and related hook APIs](https://github.com/WordPress/gutenberg/blob/c367c4e2765f9e6b890d1565db770147efca5d66/packages/core-data/src/entity-provider.js) should be used instead, as shown in the [Create Meta Block how-to](../../02-how-to-guides/meta-boxes.md#step-2-add-meta-block).

## Default value

A block attribute can contain a default value, which will be used if the `type` and `source` do not match anything within the block content.

The value is provided by the `default` field, and the value should match the expected format of the attribute.

*Example*: Example `default` values.

```json
{ type: 'string', default: 'hello world'}

{ type: 'array', default: [ { "url": "https://lorempixel.com/1200/800/", "alt": "large image" }, { "url": "https://lorempixel.com/50/50/", "alt": "small image" } ]}

{ type: 'object', default: { width: 100, title: 'title' }}
```

## Role

The `role` property designates an attribute as being of a particular conceptual type. This property can be applied to any attribute to provide semantic meaning about how the attribute should be handled.

Use `content` to designate the attribute as user-editable content. Blocks with attributes marked as `content` may be enabled for privileged editing in special circumstances such as content only locking.  
Use `local` to mark the attribute as temporary and non-persistable. Attributes marked as `local` are ignored by the Block Serializer and never saved to post content.

*Example*: `content` role used by the paragraph block.

```text
{ content: { type: 'string', source: 'html', selector: 'p', role: 'content', }}
```

*Example*: `local` role used for temporary data.

```text
{ blob: { type: 'string', role: 'local', }}
```

Learn more in the [WordPress 6.7 dev note](https://make.wordpress.org/core/2024/10/20/miscellaneous-block-editor-changes-in-wordpress-6-7/#stabilized-role-property-for-block-attributes).
