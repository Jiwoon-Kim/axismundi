---
source_url: https://developer.wordpress.org/rest-api/reference/post-types/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: types
parent_order: 5
page_order: 36
title: "Types"
---

# Types

## Schema

The schema defines all the fields that exist within a type record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `capabilities` | All capabilities used by the post type.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| --- | --- |
| `description` | A human-readable description of the post type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit` |
| `hierarchical` | Whether or not the post type should have children.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit` |
| `viewable` | Whether or not the post type can be viewed.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `edit` |
| `labels` | Human-readable labels for the post type for various contexts.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| `name` | The title for the post type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `slug` | An alphanumeric identifier for the post type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `supports` | All features, supported by the post type.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| `has_archive` | If the value is a string, the value will be used as the archive slug. If the value is false the post type has no archive.<br><br><br>JSON data type: string or boolean<br><br>Read only<br><br>Context: `view`, `edit` |
| `taxonomies` | Taxonomies associated with post type.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `view`, `edit` |
| `rest_base` | REST base route for the post type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `rest_namespace` | REST route's namespace for the post type.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `visibility` | The visibility settings for the post type.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| `icon` | The icon for the post type.<br><br><br>JSON data type: string or null<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |

## Retrieve a Type

### Definition & Example Request

`GET /wp/v2/types`

Query this endpoint to retrieve a specific type record.

`$ curl https://example.com/wp-json/wp/v2/types`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Retrieve a Type

### Definition & Example Request

`GET /wp/v2/types/<type>`

Query this endpoint to retrieve a specific type record.

`$ curl https://example.com/wp-json/wp/v2/types/<type>`

### Arguments

| `type` | An alphanumeric identifier for the post type. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
