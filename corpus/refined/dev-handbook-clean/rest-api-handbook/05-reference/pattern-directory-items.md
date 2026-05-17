---
source_url: https://developer.wordpress.org/rest-api/reference/pattern-directory-items/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: pattern-directory-items
parent_order: 5
page_order: 20
title: "Pattern Directory Items"
---

# Pattern Directory Items

## Schema

The schema defines all the fields that exist within a pattern directory item record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | The pattern ID.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `title` | The pattern title, in human readable format.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `content` | The pattern content.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `categories` | The pattern's category slugs.<br><br><br>JSON data type: array<br><br>Context: `view`, `edit`, `embed` |
| `keywords` | The pattern's keywords.<br><br><br>JSON data type: array<br><br>Context: `view`, `edit`, `embed` |
| `description` | A description of the pattern.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `viewport_width` | The preferred width of the viewport when previewing a pattern, in pixels.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `block_types` | The block types which can use this pattern.<br><br><br>JSON data type: array<br><br>Context: `view`, `embed` |

## List Pattern Directory Items

Query this endpoint to retrieve a collection of pattern directory items. The response you receive can be controlled and filtered using the URL query parameters below.

### Definition

`GET /wp/v2/pattern-directory/patterns`

### Example Request

`$ curl https://example.com/wp-json/wp/v2/pattern-directory/patterns`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `page` | Current page of the collection.<br><br><br>Default: `1` |
| `per_page` | Maximum number of items to be returned in result set.<br><br><br>Default: `100` |
| `search` | Limit results to those matching a string. |
| `category` | Limit results to those matching a category ID. |
| `keyword` | Limit results to those matching a keyword ID. |
| `slug` | Limit results to those matching a pattern (slug). |
| `offset` | Offset the result set by a specific number of items. |
| `order` | Order sort attribute ascending or descending.<br><br><br>Default: `desc`<br><br>One of: `asc`, `desc` |
| `orderby` | Sort collection by post attribute.<br><br><br>Default: `date`<br><br>One of: `author`, `date`, `id`, `include`, `modified`, `parent`, `relevance`, `slug`, `include_slugs`, `title`, `favorite_count` |
