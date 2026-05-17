---
source_url: https://developer.wordpress.org/rest-api/reference/sidebars/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: sidebars
parent_order: 5
page_order: 26
title: "Sidebars"
---

# Sidebars

## Schema

The schema defines all the fields that exist within a sidebar record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | ID of sidebar.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `name` | Unique name identifying the sidebar.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `description` | Description of sidebar.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `class` | Extra CSS class to assign to the sidebar in the Widgets interface.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `before_widget` | HTML content to prepend to each widget's HTML output when assigned to this sidebar. Default is an opening list item element.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `after_widget` | HTML content to append to each widget's HTML output when assigned to this sidebar. Default is a closing list item element.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `before_title` | HTML content to prepend to the sidebar title when displayed. Default is an opening h2 element.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `after_title` | HTML content to append to the sidebar title when displayed. Default is a closing h2 element.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `status` | Status of sidebar.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit`<br><br>One of: `active`, `inactive` |
| `widgets` | Nested widgets.<br><br><br>JSON data type: array<br><br>Context: `embed`, `view`, `edit` |

## Retrieve a Sidebar

### Definition & Example Request

`GET /wp/v2/sidebars`

Query this endpoint to retrieve a specific sidebar record.

`$ curl https://example.com/wp-json/wp/v2/sidebars`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Retrieve a Sidebar

### Definition & Example Request

`GET /wp/v2/sidebars/<id>`

Query this endpoint to retrieve a specific sidebar record.

`$ curl https://example.com/wp-json/wp/v2/sidebars/<id>`

### Arguments

| `id` | The id of a registered sidebar |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Update a Sidebar

### Arguments

| `widgets` | Nested widgets. |
| --- | --- |

### Definition

`POST /wp/v2/sidebars/<id>`
