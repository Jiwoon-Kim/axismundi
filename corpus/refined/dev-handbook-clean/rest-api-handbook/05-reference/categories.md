---
source_url: https://developer.wordpress.org/rest-api/reference/categories/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: categories
parent_order: 5
page_order: 7
title: "Categories"
---

# Categories

## Schema

The schema defines all the fields that exist within a category record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | Unique identifier for the term.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `view`, `embed`, `edit` |
| --- | --- |
| `count` | Number of published posts for the term.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `view`, `edit` |
| `description` | HTML description of the term.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit` |
| `link` | URL of the term.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Read only<br><br>Context: `view`, `embed`, `edit` |
| `name` | HTML title for the term.<br><br><br>JSON data type: string<br><br>Context: `view`, `embed`, `edit` |
| `slug` | An alphanumeric identifier for the term unique to its type.<br><br><br>JSON data type: string<br><br>Context: `view`, `embed`, `edit` |
| `taxonomy` | Type attribution for the term.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `embed`, `edit`<br><br>One of: `category` |
| `parent` | The parent term ID.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit` |
| `meta` | Meta fields.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit` |

## List Categories

Query this endpoint to retrieve a collection of categories. The response you receive can be controlled and filtered using the URL query parameters below.

### Definition

`GET /wp/v2/categories`

### Example Request

`$ curl https://example.com/wp-json/wp/v2/categories`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `page` | Current page of the collection.<br><br><br>Default: `1` |
| `per_page` | Maximum number of items to be returned in result set.<br><br><br>Default: `10` |
| `search` | Limit results to those matching a string. |
| `exclude` | Ensure result set excludes specific IDs. |
| `include` | Limit result set to specific IDs. |
| `order` | Order sort attribute ascending or descending.<br><br><br>Default: `asc`<br><br>One of: `asc`, `desc` |
| `orderby` | Sort collection by term attribute.<br><br><br>Default: `name`<br><br>One of: `id`, `include`, `name`, `slug`, `include_slugs`, `term_group`, `description`, `count` |
| `hide_empty` | Whether to hide terms not assigned to any posts. |
| `parent` | Limit result set to terms assigned to a specific parent. |
| `post` | Limit result set to terms assigned to a specific post. |
| `slug` | Limit result set to terms with one or more specific slugs. |

## Create a Category

### Arguments

| `description` | HTML description of the term. |
| --- | --- |
| `name` | HTML title for the term.<br><br><br>Required: 1 |
| `slug` | An alphanumeric identifier for the term unique to its type. |
| `parent` | The parent term ID. |
| `meta` | Meta fields. |

### Definition

`POST /wp/v2/categories`

## Retrieve a Category

### Definition & Example Request

`GET /wp/v2/categories/<id>`

Query this endpoint to retrieve a specific category record.

`$ curl https://example.com/wp-json/wp/v2/categories/<id>`

### Arguments

| `id` | Unique identifier for the term. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Update a Category

### Arguments

| `id` | Unique identifier for the term. |
| --- | --- |
| `description` | HTML description of the term. |
| `name` | HTML title for the term. |
| `slug` | An alphanumeric identifier for the term unique to its type. |
| `parent` | The parent term ID. |
| `meta` | Meta fields. |

### Definition

`POST /wp/v2/categories/<id>`

## Delete a Category

### Arguments

| `id` | Unique identifier for the term. |
| --- | --- |
| `force` | Required to be true, as terms do not support trashing. |

### Definition

`DELETE /wp/v2/categories/<id>`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/categories/<id>`
