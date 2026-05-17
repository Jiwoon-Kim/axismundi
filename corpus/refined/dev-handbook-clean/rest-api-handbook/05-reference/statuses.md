---
source_url: https://developer.wordpress.org/rest-api/reference/post-statuses/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: statuses
parent_order: 5
page_order: 28
title: "Statuses"
---

# Statuses

## Schema

The schema defines all the fields that exist within a status record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `name` | The title for the status.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `private` | Whether posts with this status should be private.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `edit` |
| `protected` | Whether posts with this status should be protected.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `edit` |
| `public` | Whether posts of this status should be shown in the front end of the site.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit` |
| `queryable` | Whether posts with this status should be publicly-queryable.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit` |
| `show_in_list` | Whether to include posts in the edit listing for their post type.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `edit` |
| `slug` | An alphanumeric identifier for the status.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `date_floating` | Whether posts of this status may have floating published dates.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit` |

## Retrieve a Status

### Definition & Example Request

`GET /wp/v2/statuses`

Query this endpoint to retrieve a specific status record.

`$ curl https://example.com/wp-json/wp/v2/statuses`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Retrieve a Status

### Definition & Example Request

`GET /wp/v2/statuses/<status>`

Query this endpoint to retrieve a specific status record.

`$ curl https://example.com/wp-json/wp/v2/statuses/<status>`

### Arguments

| `status` | An alphanumeric identifier for the status. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
