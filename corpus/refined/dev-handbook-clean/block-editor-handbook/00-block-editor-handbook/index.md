---
source_url: https://developer.wordpress.org/block-editor/
synced: 2026-05-12
handbook: block-editor
chapter: block-editor-handbook
slug: index
parent_order: 0
page_order: 1001
title: "Block Editor Handbook"
---

# Block Editor Handbook

Welcome to the Block Editor Handbook.

The Block Editor is a modern paradigm for WordPress site building and publishing. It uses a modular system of blocks to compose and format content and is designed to create rich and flexible layouts for websites and digital products.

The Block Editor consists of several primary elements:

1. **Inserter:** A panel for inserting blocks into the content canvas
2. **Content canvas:** The content editor, which holds content created with blocks
3. **Settings Panel:** A panel for configuring a block’s settings when selected or the settings of the post

Through the Block Editor, you create content modularly using blocks. Many [blocks](../03-reference-guides/02-core-blocks-reference/index.md) are available in WordPress by default, and you can also [create your own](https://developer.wordpress.org/block-editor/getting-started/create-block/).

A [block](../04-explanations/01-architecture/key-concepts.md#blocks) is a discrete element such as a Paragraph, Heading, Media, or Embed. Each block is treated as a separate element with individual editing and format controls. When all these components are pieced together, they make up the content of the page or post, which is then [stored in the WordPress database](../04-explanations/01-architecture/data-flow.md#serialization-and-parsing).

The Block Editor is the result of the work done on the [**Gutenberg project**](https://developer.wordpress.org/block-editor/getting-started/faq/#what-is-gutenberg), which aims to revolutionize the WordPress editing experience.

Besides offering an [enhanced editing experience](https://wordpress.org/gutenberg/) through visual content creation tools, the Block Editor is also a powerful developer platform with a [rich feature set of APIs](../03-reference-guides/index.md) that allow it to be manipulated and extended in countless ways.

## Navigating this handbook

This handbook is focused on block development and is divided into five major sections.

- **[Getting Started](https://developer.wordpress.org/block-editor/getting-started/):** For those just starting out with block development, this is where you can get set up with a [development environment](https://developer.wordpress.org/block-editor/getting-started/devenv/) and learn the [fundamentals of block development](../01-getting-started/02-fundamentals-of-block-development/index.md). Its [Quick Start Guide](https://developer.wordpress.org/block-editor/getting-started/quick-start-guide/) and [Tutorial: Build your first block](https://developer.wordpress.org/block-editor/getting-started/tutorial/) are great places to start learning block development.
- **[How-to Guides](../02-how-to-guides/index.md):** Here, you can build on what you learned in the Getting Started section and learn how to solve particular problems. You will also find tutorials and example code to reuse in your own projects, such as [working with WordPress data](https://developer.wordpress.org/block-editor/how-to-guides/data-basics/) or [Curating the Editor Experience](../02-how-to-guides/04-curating-editor-experience/index.md).
- **[Reference Guides](../03-reference-guides/index.md):** This section is the heart of the handbook and is where you can get down to the nitty-gritty and look up the details of the particular API you’re working with or need information on. Among other things, the [Block API Reference](../03-reference-guides/01-block-api-reference/index.md) covers most of what you will want to do with a block, and each [component](../03-reference-guides/08-component-reference/index.md) and [package](../03-reference-guides/09-package-reference/index.md) is also documented here. *Components are also documented via [Storybook](https://wordpress.github.io/gutenberg/?path=/story/docs-introduction--page).*
- **[Explanations](../04-explanations/index.md):** This section enables you to go deeper and reinforce your practical knowledge with a theoretical understanding of the [Architecture](../04-explanations/01-architecture/index.md) of the Block Editor.
- **[Contributor Guide](https://developer.wordpress.org/block-editor/contributors/):** Gutenberg is open-source software, and everyone is welcome to contribute to the project. This section details how to contribute, whether with [code](https://developer.wordpress.org/block-editor/contributors/code/), [design](https://developer.wordpress.org/block-editor/contributors/design/), [documentation](https://developer.wordpress.org/block-editor/contributors/documentation/), or in some other way.

## Further resources

This handbook should be considered the canonical resource for all things related to block development. However, there are other resources that can help you.

- **[WordPress Developer Blog](https://developer.wordpress.org/news/):** An ever-growing resource of technical articles covering specific topics related to block development and a wide variety of use cases. The blog is also an excellent way to [keep up with the latest developments in WordPress](https://developer.wordpress.org/news/tag/roundup/).
- **[Learn WordPress](https://learn.wordpress.org/):** The WordPress hub for learning resources where you can find courses like [Introduction to Block Development: Build your first custom block](https://learn.wordpress.org/course/introduction-to-block-development-build-your-first-custom-block/), [Converting a Shortcode to a Block](https://learn.wordpress.org/course/converting-a-shortcode-to-a-block/), or [Using the WordPress Data Layer](https://learn.wordpress.org/course/using-the-wordpress-data-layer/).

- **[Gutenberg repository](https://github.com/WordPress/gutenberg/):** Development of the Block Editor takes place on GitHub. The repository contains the code of interesting packages such as [`block-library`](https://github.com/WordPress/gutenberg/tree/trunk/packages/block-library/src) (core blocks) or [`components`](https://github.com/WordPress/gutenberg/tree/trunk/packages/components) (common UI elements). *The [block-development-examples](https://github.com/WordPress/block-development-examples) repository is another useful reference.*
- **[End User Documentation](https://wordpress.org/documentation/):** This documentation site is targeted to the end user (not developers), where you can also find documentation about the [Block Editor](https://wordpress.org/documentation/category/block-editor/) and [working with blocks](https://wordpress.org/documentation/article/work-with-blocks/).

## Are you in the right place?

The Block Editor Handbook is designed for those looking to create and develop for the Block Editor. However, it’s important to note that there are multiple other handbooks available within the [Developer Resources](https://developer.wordpress.org/) that you may find beneficial:

- [Theme Handbook](../../theme-handbook/00-theme-handbook/index.md)
- [Plugin Handbook](../../plugin-handbook/00-plugin-handbook/index.md)
- [Common APIs Handbook](../../common-apis-handbook/00-common-apis-handbook.md)
- [Advanced Administration Handbook](https://developer.wordpress.org/advanced-administration)
- [REST API Handbook](../../rest-api-handbook/00-rest-api-handbook.md)
- [Coding Standards Handbook](https://developer.wordpress.org/coding-standards)
