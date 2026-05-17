---
source_url: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-api-versions/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: block-api-reference
slug: api-versions
parent_order: 3
sub_order: 1
page_order: 2
title: "API Versions"
---

# API Versions

This document lists the changes made between the different API versions.

## Version 3 (&gt;= WordPress 6.3)

- The post editor will be iframed if all registered blocks have a Block API version 3 or higher. Adding version 3 support means that the block should work inside an iframe, though the block may still be rendered outside the iframe if not all blocks support version 3.
- **In WordPress 7.0, the post editor is planned to always work as an iframe, regardless of the `apiVersion` of registered blocks**.  
Please refer to [this migration guide](migrating-blocks-for-iframe-editor.md) to test your blocks in the iframe editor beforehand.
