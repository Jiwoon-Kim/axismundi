---
source_url: https://developer.wordpress.org/rest-api/reference/wp_global_styles/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: global-styles
parent_order: 5
page_order: 10
title: "Global_Styles"
---

# Global\_Styles

## Schema

The schema defines all the fields that exist within a global\_styles record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | ID of global styles config.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `styles` | Global styles.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit` |
| `settings` | Global settings.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit` |
| `title` | Title of the global styles variation.<br><br><br>JSON data type: object or string<br><br>Context: `embed`, `view`, `edit` |

## Retrieve a Global_Styles

### Definition & Example Request

`GET /wp/v2/global-styles/<id>`

Query this endpoint to retrieve a specific global\_styles record.

`$ curl https://example.com/wp-json/wp/v2/global-styles/<id>`

### Arguments

| `id` | The id of a template |
| --- | --- |

## Update a Global_Styles

### Arguments

| `styles` | Global styles. |
| --- | --- |
| `settings` | Global settings. |
| `title` | Title of the global styles variation. |

### Definition

`POST /wp/v2/global-styles/<id>`
