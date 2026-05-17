---
source_url: https://developer.wordpress.org/rest-api/reference/taxonomies/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: taxonomies
parent_order: 5
page_order: 30
title: "Taxonomies"
---

# Taxonomies

## Schema

The schema defines all the fields that exist within a taxonomy record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `capabilities` | All capabilities used by the taxonomy.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| --- | --- |
| `description` | A human-readable description of the taxonomy.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit` |
| `hierarchical` | Whether or not the taxonomy should have children.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit` |
| `labels` | Human-readable labels for the taxonomy for various contexts.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| `name` | The title for the taxonomy.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `slug` | An alphanumeric identifier for the taxonomy.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `show_cloud` | Whether or not the term cloud should be displayed.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `edit` |
| `types` | Types associated with the taxonomy.<br><br><br>JSON data type: array<br><br>Read only<br><br>Context: `view`, `edit` |
| `rest_base` | REST base route for the taxonomy.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `rest_namespace` | REST namespace route for the taxonomy.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `visibility` | The visibility settings for the taxonomy.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |

## Retrieve a Taxonomy

### Definition & Example Request

`GET /wp/v2/taxonomies`

Query this endpoint to retrieve a specific taxonomy record.

`$ curl https://example.com/wp-json/wp/v2/taxonomies`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `type` | Limit results to taxonomies associated with a specific post type. |

## Retrieve a Taxonomy

### Definition & Example Request

`GET /wp/v2/taxonomies/<taxonomy>`

Query this endpoint to retrieve a specific taxonomy record.

`$ curl https://example.com/wp-json/wp/v2/taxonomies/<taxonomy>`

### Arguments

| `taxonomy` | An alphanumeric identifier for the taxonomy. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
