---
source_url: https://developer.wordpress.org/rest-api/reference/widget-types/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: widget-types
parent_order: 5
page_order: 38
title: "Widget Types"
---

# Widget Types

## Schema

The schema defines all the fields that exist within a widget type record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | Unique slug identifying the widget type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `name` | Human-readable name identifying the widget type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `description` | Description of the widget.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `is_multi` | Whether the widget supports multiple instances<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `classname` | Class name<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |

## Retrieve a Widget Type

### Definition & Example Request

`GET /wp/v2/widget-types`

Query this endpoint to retrieve a specific widget type record.

`$ curl https://example.com/wp-json/wp/v2/widget-types`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Retrieve a Widget Type

### Definition & Example Request

`GET /wp/v2/widget-types/<id>`

Query this endpoint to retrieve a specific widget type record.

`$ curl https://example.com/wp-json/wp/v2/widget-types/<id>`

### Arguments

| `id` | The widget type id. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
