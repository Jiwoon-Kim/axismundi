---
source_url: https://developer.wordpress.org/block-editor/how-to-guides/
synced: 2026-05-12
handbook: block-editor
chapter: how-to-guides
slug: index
parent_order: 2
page_order: 0
title: "How-to Guides"
---

# How-to Guides

The new editor is highly flexible, like most of WordPress. You can build custom blocks, modify the editor’s appearance, add special plugins, and much more.

## Creating blocks

The editor is about blocks, and the main extensibility API is the Block API. It allows you to create your own static blocks, [Dynamic Blocks](01-blocks/creating-dynamic-blocks.md) ( rendered on the server ) and also blocks capable of saving data to Post Meta for more structured content.

If you want to learn more about block creation, see the [Create a Block tutorial](https://developer.wordpress.org/block-editor/getting-started/devenv/get-started-with-create-block/) for the best place to start.

## Extending blocks

It is also possible to modify the behavior of existing blocks or even remove them completely using filters.

Learn more in the [Block Filters](../03-reference-guides/03-hooks-reference/block-filters.md) section.

Specifically for `Query Loop` block, besides the available filters, there are more ways to extend it and create bespoke versions of it. Learn more in the [Extending the Query Loop block](01-blocks/extending-query-loop.md) section.

## Extending the Editor UI

Extending the editor UI can be accomplished with the `registerPlugin` API, allowing you to define all your plugin’s UI elements in one place.

Refer to the [Plugins](https://developer.wordpress.org/block-editor/reference-guide/packages/packages-plugins/) and [Edit Post](https://developer.wordpress.org/block-editor/reference-guide/packages/packages-edit-post/) section for more information.

You can also filter certain aspects of the editor; this is documented on the [Editor Filters](../03-reference-guides/03-hooks-reference/editor-hooks.md) page.

## Meta boxes

Porting PHP meta boxes to blocks or sidebar plugins is highly encouraged, learn how in the [meta box](meta-boxes.md) and [sidebar plugin](plugin-sidebar.md) guides.

## Theme support

By default, blocks provide their styles to enable basic support for blocks in themes without any change. Themes can add/override these styles, or rely on defaults.

There are some advanced block features which require opt-in support in the theme. See [theme support](05-themes/theme-support.md) and [how to filter global styles](../03-reference-guides/03-hooks-reference/global-styles-filters.md).

## Autocomplete

Autocompleters within blocks may be extended and overridden. Learn more about the [autocomplete](../03-reference-guides/03-hooks-reference/autocomplete.md) filters.

## Block parsing and serialization

Posts in the editor move through a couple of different stages between being stored in `post_content` and appearing in the editor. Since the blocks themselves are data structures that live in memory it takes a parsing and serialization step to transform out from and into the stored format in the database.

Customizing the parser is an advanced topic that you can learn more about in the [Extending the Parser](../03-reference-guides/03-hooks-reference/parser-filters.md) section.
