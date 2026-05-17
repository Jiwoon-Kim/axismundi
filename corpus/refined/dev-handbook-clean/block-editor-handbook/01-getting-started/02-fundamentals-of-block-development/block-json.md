---
source_url: https://developer.wordpress.org/block-editor/getting-started/fundamentals/block-json/
synced: 2026-05-12
handbook: block-editor
chapter: getting-started
sub_chapter: fundamentals-of-block-development
slug: block-json
parent_order: 1
sub_order: 2
page_order: 2
title: "block.json"
---

# block.json

The `block.json` file simplifies the process of defining and registering a block by using the same block’s definition in JSON format to register the block on both the server and the client (Block Editor).

To view a complete block example and its associated [`block.json`](https://github.com/WordPress/block-development-examples/blob/trunk/plugins/block-supports-6aa4dd/src/block.json) file, visit the [Block Development Examples](https://github.com/WordPress/block-development-examples/tree/trunk/plugins/block-supports-6aa4dd) GitHub repository.

Besides simplifying a block’s registration, using a `block.json` has [several benefits](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#benefits-using-the-metadata-file), including improved performance.

The [Metadata in block.json](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md) documentation has a comprehensive guide on all the properties you can use in a `block.json` file for a block. This article will cover the most common options, which allow you to specify:

- The block’s basic metadata.
- The files that dictate the block’s functionality, appearance, and output.
- How data is stored within the block.
- The block’s setting panels within the user interface.

## Basic metadata of a block

Using `block.json` properties, you can define how the block will be uniquely identified and what information is displayed in the Block Editor. These properties include:

- **[`apiVersion`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#api-version):** Specifies the [API](../../03-reference-guides/01-block-api-reference/api-versions.md) version the block uses. Use the latest version unless you have specific requirements.
- **[`name`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#name):** The unique name of the block, including namespace (e.g., `my-plugin/my-custom-block`).
- **[`title`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#title):** The display title for the block, shown in the Inserter.
- **[`category`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#category):** The category under which the block appears in the Inserter. Common categories include `text`, `media`, `design`, `widgets`, and `theme`.
- **[`icon`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#icon):** An icon representing the block in the Inserter. This can be a [Dashicon](https://developer.wordpress.org/resource/dashicons) slug or a custom SVG icon.
- **[`description`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#description):** A short description of the block, providing more context than the title.
- **[`keywords`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#keywords):** An array of keywords to help users find the block when searching.
- **[`textdomain`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#text-domain):** The text domain for the block, used for internationalization.

## Files for the block’s behavior, output, or style

The `block.json` file also allows you to specify the essential files for a block’s functionality:

- **[`editorScript`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#editor-script):** A JavaScript file or files for use only in the Block Editor.
- **[`editorStyle`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#editor-style):** A CSS file or files for styling within the Block Editor.
- **[`script`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#script):** A JavaScript file or files loaded in both the Block Editor and the front end.
- **[`style`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#style):** A CSS file or files applied in both the Block Editor and the front end.
- **[`viewScript`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#view-script):** A JavaScript file or files intended solely for the front end.

For all these properties, you can provide a [file path](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#wpdefinedpath) (starting with `file:`), a [handle](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#wpdefinedasset) that has been registered using `wp_register_script` or `wp_register_style`, or an array combining both options.

Additionally, the [`render`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#render) property, [introduced on WordPress 6.1](https://make.wordpress.org/core/2022/10/12/block-api-changes-in-wordpress-6-1/), specifies the path to a PHP template file responsible for generating a [dynamically rendered](static-or-dynamic-rendering.md) block’s front-end markup. This approach is used if a `$render_callback` function is not provided to the `register_block_type()` function.

## Using block attributes to store data

Block [attributes](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#attributes) are settings or data assigned to blocks. They can determine various aspects of a block, such as its content, layout, style, and any other specific information you need to store along with your block’s structure. If the user changes a block, such as modifying the font size, you need a way to persist these changes. Attributes are the solution.

When registering a new block type, the `attributes` property of `block.json` describes the custom data the block requires and how they’re stored in the database. This allows the Block Editor to parse these values correctly and pass the `attributes` to the block’s `Edit` component and `save` function.

Here’s an example of three attributes defined in `block.json`:

```json
"attributes": { "fallbackCurrentYear": { "type": "string" }, "showStartingYear": { "type": "boolean" }, "startingYear": { "type": "string" }},
```

Blocks are “delimited” using HTML-style comment tags that contain specific JSON-like attributes. These delimiters make it possible to recognize block boundaries and parse block attributes when rendering post content or editing a post in the Block Editor.

The code example below demonstrates the attributes defined in the block delimiter.

```html
<!-- wp:block-development-examples/copyright-date-block-09aac3 {"fallbackCurrentYear":"2023","showStartingYear":true,"startingYear":"2020"} --><p class="wp-block-block-development-examples-copyright-date-block-09aac3">© 2020–2023</p><!-- /wp:block-development-examples/copyright-date-block-09aac3 -->
```

All attributes are serialized and stored in the block’s delimiter by default, but this can be configured to suit your needs. Check out the [Understanding Block Attributes](https://developer.wordpress.org/news/2023/09/understanding-block-attributes/) article to learn more.

### Reading and updating attributes

These [attributes](../../03-reference-guides/01-block-api-reference/edit-and-save.md#attributes) are passed to the block’s `Edit` React component for display in the Block Editor, to the `save` function for generating the markup that gets stored in the database, and to any server-side rendering definition for the block.

The `Edit` component uniquely possesses the ability to modify these attributes through the [`setAttributes`](../../03-reference-guides/01-block-api-reference/edit-and-save.md#setattributes) function.

*See how the attributes are passed to the [`Edit`](https://github.com/WordPress/block-development-examples/blob/trunk/plugins/copyright-date-block-09aac3/src/edit.js) component, the [`save`](https://github.com/WordPress/block-development-examples/blob/trunk/plugins/copyright-date-block-09aac3/src/save.js) function, and [`render.php`](https://github.com/WordPress/block-development-examples/blob/trunk/plugins/copyright-date-block-09aac3/src/render.php) in this [complete block example](https://github.com/WordPress/block-development-examples/tree/trunk/plugins/copyright-date-block-09aac3).*

For more information about attributes and how to use them in your custom blocks, visit the [Attributes API](../../03-reference-guides/01-block-api-reference/attributes.md) reference page.

## Using block supports to enable settings and styles

Many blocks, including Core blocks, offer similar customization options, such as background color, text color, and padding adjustments.

The [`supports`](../../03-reference-guides/01-block-api-reference/metadata-in-block-json.md#supports) property in `block.json` allows a block to declare support for a set of these common customization options. When enabled, users of the block can then adjust things like color or padding directly from the Settings Sidebar.

Leveraging these predefined block supports helps ensure your block behaves consistently with Core blocks, eliminating the need to recreate similar functionalities from scratch.

Here’s an example of color supports defined in `block.json`:

```json
"supports": { "color": { "text": true, "link": true, "background": true }}
```

The use of block supports generates a set of properties that need to be manually added to the [wrapping element of the block](block-wrapper.md). This ensures they’re properly stored as part of the block data and taken into account when generating the markup of the block that will be delivered to the front end.

The following code demonstrates how the attributes and CSS classes generated by enabling block supports are stored in the markup representation of the block.

```html
<!-- wp:block-development-examples/block-supports-6aa4dd {"backgroundColor":"contrast","textColor":"accent-4"} --><p class="wp-block-block-development-examples-block-supports-6aa4dd has-accent-4-color has-contrast-background-color has-text-color has-background">Hello World</p><!-- /wp:block-development-examples/block-supports-6aa4dd -->
```

*See the [complete block example](https://github.com/WordPress/block-development-examples/tree/trunk/plugins/block-supports-6aa4dd) of the [code above](https://github.com/WordPress/block-development-examples/blob/trunk/plugins/block-supports-6aa4dd/src/block.json).*

For more information about supports and how to use them in your custom blocks, visit the [Supports API](../../03-reference-guides/01-block-api-reference/supports.md) reference page.
