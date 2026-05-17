---
source_url: https://developer.wordpress.org/rest-api/reference/wp_templates/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: templates
parent_order: 5
page_order: 32
title: "Templates"
---

# Templates

## Schema

The schema defines all the fields that exist within a template record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | ID of template.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `slug` | Unique slug identifying the template.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit` |
| `theme` | Theme identifier for the template.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit` |
| `type` | Type of template.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit` |
| `source` | Source of template<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `origin` | Source of a customized template<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `content` | Content of template.<br><br><br>JSON data type: object or string<br><br>Context: `embed`, `view`, `edit` |
| `title` | Title of template.<br><br><br>JSON data type: object or string<br><br>Context: `embed`, `view`, `edit` |
| `description` | Description of template.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit` |
| `status` | Status of template.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit`<br><br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `wp_id` | Post ID.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `has_theme_file` | Theme file exists.<br><br><br>JSON data type: bool<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `author` | The ID for the author of the template.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `modified` | The date the template was last modified, in the site's timezone.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Read only<br><br>Context: `view`, `edit` |
| `is_custom` | Whether a template is a custom template.<br><br><br>JSON data type: bool<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |

## Retrieve a Template

### Definition & Example Request

`GET /wp/v2/templates`

Query this endpoint to retrieve a specific template record.

`$ curl https://example.com/wp-json/wp/v2/templates`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `wp_id` | Limit to the specified post id. |
| `area` | Limit to the specified template part area. |
| `post_type` | Post type to get the templates for. |

## Create a Template

### Arguments

| `slug` | Unique slug identifying the template.<br><br><br>Required: 1 |
| --- | --- |
| `theme` | Theme identifier for the template. |
| `type` | Type of template. |
| `content` | Content of template. |
| `title` | Title of template. |
| `description` | Description of template. |
| `status` | Status of template.<br><br><br>Default: `publish`<br><br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `author` | The ID for the author of the template. |

### Definition

`POST /wp/v2/templates`

## Retrieve a Template

### Definition & Example Request

`GET /wp/v2/templates/<id>?)[\/\w%-]+)`

Query this endpoint to retrieve a specific template record.

`$ curl https://example.com/wp-json/wp/v2/templates/<id>?)[\/\w%-]+)`

### Arguments

| `id` | The id of a template |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Update a Template

### Arguments

| `id` | The id of a template |
| --- | --- |
| `slug` | Unique slug identifying the template. |
| `theme` | Theme identifier for the template. |
| `type` | Type of template. |
| `content` | Content of template. |
| `title` | Title of template. |
| `description` | Description of template. |
| `status` | Status of template.  <br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `author` | The ID for the author of the template. |

### Definition

`POST /wp/v2/templates/<id>?)[\/\w%-]+)`

## Delete a Template

### Arguments

| `id` | The id of a template |
| --- | --- |
| `force` | Whether to bypass Trash and force deletion. |

### Definition

`DELETE /wp/v2/templates/<id>?)[\/\w%-]+)`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/templates/<id>?)[\/\w%-]+)`
