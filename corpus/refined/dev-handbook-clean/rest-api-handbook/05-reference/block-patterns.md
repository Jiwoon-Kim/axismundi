---
source_url: https://developer.wordpress.org/rest-api/reference/block-patterns/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: block-patterns
parent_order: 5
page_order: 4
title: "Block Patterns"
---

# Block Patterns

## Schema

The schema defines all the fields that exist within a block pattern record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `name` | The pattern name.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `title` | The pattern title, in human readable format.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `content` | The pattern content.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `description` | The pattern detailed description.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `viewport_width` | The pattern viewport width for inserter preview.<br><br><br>JSON data type: number<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `inserter` | Determines whether the pattern is visible in inserter.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `categories` | The pattern category slugs.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `keywords` | The pattern keywords.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `block_types` | Block types that the pattern is intended to be used with.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `post_types` | An array of post types that the pattern is restricted to be used with.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `template_types` | An array of template types where the pattern fits.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `source` | Where the pattern comes from e.g. core<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed`<br><br>One of: `core`, `plugin`, `theme`, `pattern-directory/core`, `pattern-directory/theme`, `pattern-directory/featured` |

## Retrieve a Block Pattern

### Definition & Example Request

`GET /wp/v2/block-patterns/patterns`

Query this endpoint to retrieve a specific block pattern record.

`$ curl https://example.com/wp-json/wp/v2/block-patterns/patterns`

There are no arguments for this endpoint.
