---
source_url: https://developer.wordpress.org/rest-api/reference/widgets/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: widgets
parent_order: 5
page_order: 39
title: "Widgets"
---

# Widgets

## Schema

The schema defines all the fields that exist within a widget record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | Unique identifier for the widget.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `id_base` | The type of the widget. Corresponds to ID in widget-types endpoint.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `sidebar` | The sidebar the widget belongs to.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `rendered` | HTML representation of the widget.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `rendered_form` | HTML representation of the widget admin form.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `edit` |
| `instance` | Instance settings of the widget, if supported.<br><br><br>JSON data type: object<br><br>Context: `edit` |
| `form_data` | URL-encoded form data from the widget admin form. Used to update a widget that does not support instance. Write only.<br><br><br>JSON data type: string<br><br>Context: |

## Retrieve a Widget

### Definition & Example Request

`GET /wp/v2/widgets`

Query this endpoint to retrieve a specific widget record.

`$ curl https://example.com/wp-json/wp/v2/widgets`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `sidebar` | The sidebar to return widgets for. |

## Create a Widget

### Arguments

| `id` | Unique identifier for the widget. |
| --- | --- |
| `id_base` | The type of the widget. Corresponds to ID in widget-types endpoint. |
| `sidebar` | The sidebar the widget belongs to.<br><br><br>Required: 1<br><br>Default: `wp_inactive_widgets` |
| `instance` | Instance settings of the widget, if supported. |
| `form_data` | URL-encoded form data from the widget admin form. Used to update a widget that does not support instance. Write only. |

### Definition

`POST /wp/v2/widgets`

## Retrieve a Widget

### Definition & Example Request

`GET /wp/v2/widgets/<id>`

Query this endpoint to retrieve a specific widget record.

`$ curl https://example.com/wp-json/wp/v2/widgets/<id>`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Update a Widget

### Arguments

| `id` | Unique identifier for the widget. |
| --- | --- |
| `id_base` | The type of the widget. Corresponds to ID in widget-types endpoint. |
| `sidebar` | The sidebar the widget belongs to. |
| `instance` | Instance settings of the widget, if supported. |
| `form_data` | URL-encoded form data from the widget admin form. Used to update a widget that does not support instance. Write only. |

### Definition

`POST /wp/v2/widgets/<id>`

## Delete a Widget

### Arguments

| `force` | Whether to force removal of the widget, or move it to the inactive sidebar. |
| --- | --- |

### Definition

`DELETE /wp/v2/widgets/<id>`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/widgets/<id>`
