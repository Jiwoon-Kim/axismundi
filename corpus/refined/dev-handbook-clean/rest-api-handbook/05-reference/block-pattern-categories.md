---
source_url: https://developer.wordpress.org/rest-api/reference/block-pattern-categories/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: block-pattern-categories
parent_order: 5
page_order: 3
title: "Block Pattern Categories"
---

# Block Pattern Categories

## Schema

The schema defines all the fields that exist within a block pattern category record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `name` | The category name.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `label` | The category label, in human readable format.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `description` | The category description, in human readable format.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |

## Retrieve a Block Pattern Category

### Definition & Example Request

`GET /wp/v2/block-patterns/categories`

Query this endpoint to retrieve a specific block pattern category record.

`$ curl https://example.com/wp-json/wp/v2/block-patterns/categories`

There are no arguments for this endpoint.
