---
source_url: https://developer.wordpress.org/rest-api/reference/menu-locations/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: menu-locations
parent_order: 5
page_order: 12
title: "Menu Locations"
---

# Menu Locations

## Schema

The schema defines all the fields that exist within a menu location record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `name` | The name of the menu location.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `description` | The description of the menu location.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `menu` | The ID of the assigned menu.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |

## Retrieve a Menu Location

### Definition & Example Request

`GET /wp/v2/menu-locations`

Query this endpoint to retrieve a specific menu location record.

`$ curl https://example.com/wp-json/wp/v2/menu-locations`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Retrieve a Menu Location

### Definition & Example Request

`GET /wp/v2/menu-locations/<location>`

Query this endpoint to retrieve a specific menu location record.

`$ curl https://example.com/wp-json/wp/v2/menu-locations/<location>`

### Arguments

| `location` | An alphanumeric identifier for the menu location. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
