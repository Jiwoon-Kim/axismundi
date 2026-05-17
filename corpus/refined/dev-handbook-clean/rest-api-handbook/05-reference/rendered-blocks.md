---
source_url: https://developer.wordpress.org/rest-api/reference/rendered-blocks/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: rendered-blocks
parent_order: 5
page_order: 24
title: "Rendered Blocks"
---

# Rendered Blocks

## Schema

The schema defines all the fields that exist within a Rendered Block record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `rendered` | The rendered block.<br><br><br>JSON data type: string<br><br>Context: `edit` |
| --- | --- |

## Create a Rendered Block

### Arguments

| `name` | Unique registered name for the block. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `edit` |
| `attributes` | Attributes for the block. |
| `post_id` | ID of the post context. |

### Definition

`POST /wp/v2/block-renderer/<name>`
