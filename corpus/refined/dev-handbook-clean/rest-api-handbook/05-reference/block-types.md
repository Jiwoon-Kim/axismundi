---
source_url: https://developer.wordpress.org/rest-api/reference/block-types/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: block-types
parent_order: 5
page_order: 6
title: "Block Types"
---

# Block Types

## Schema

The schema defines all the fields that exist within a block type record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `api_version` | Version of block API.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `title` | Title of block type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `name` | Unique name identifying the block type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `description` | Description of block type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `icon` | Icon of block type.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `attributes` | Block attributes.<br><br><br>JSON data type: object or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `provides_context` | Context provided by blocks of this type.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `uses_context` | Context values inherited by blocks of this type.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `selectors` | Custom CSS selectors.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `supports` | Block supports.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `category` | Block category.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `is_dynamic` | Is the block dynamically rendered.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `editor_script_handles` | Editor script handles.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `script_handles` | Public facing and editor script handles.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `view_script_handles` | Public facing script handles.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `editor_style_handles` | Editor style handles.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `style_handles` | Public facing and editor style handles.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `styles` | Block style variations.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `variations` | Block variations.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `textdomain` | Public text domain.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `parent` | Parent blocks.<br><br><br>JSON data type: array or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `ancestor` | Ancestor blocks.<br><br><br>JSON data type: array or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `keywords` | Block keywords.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `example` | Block example.<br><br><br>JSON data type: object or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `editor_script` | Editor script handle. DEPRECATED: Use `editor_script_handles` instead.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `script` | Public facing and editor script handle. DEPRECATED: Use `script_handles` instead.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `view_script` | Public facing script handle. DEPRECATED: Use `view_script_handles` instead.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `editor_style` | Editor style handle. DEPRECATED: Use `editor_style_handles` instead.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `style` | Public facing and editor style handle. DEPRECATED: Use `style_handles` instead.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |

## Retrieve a Block Type

### Definition & Example Request

`GET /wp/v2/block-types`

Query this endpoint to retrieve a specific block type record.

`$ curl https://example.com/wp-json/wp/v2/block-types`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `namespace` | Block namespace. |

## Retrieve a Block Type

### Definition & Example Request

`GET /wp/v2/block-types/<namespace>`

Query this endpoint to retrieve a specific block type record.

`$ curl https://example.com/wp-json/wp/v2/block-types/<namespace>`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `namespace` | Block namespace. |

## Retrieve a Block Type

### Definition & Example Request

`GET /wp/v2/block-types/<namespace>/<name>`

Query this endpoint to retrieve a specific block type record.

`$ curl https://example.com/wp-json/wp/v2/block-types/<namespace>/<name>`

### Arguments

| `name` | Block name. |
| --- | --- |
| `namespace` | Block namespace. |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
