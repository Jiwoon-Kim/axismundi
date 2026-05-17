---
source_url: https://developer.wordpress.org/rest-api/reference/wp_template_part-revisions/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: template-part-revisions
parent_order: 5
page_order: 33
title: "Template_Part Revisions"
---

# Template\_Part Revisions

## Schema

The schema defines all the fields that exist within a template\_part revision record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `author` | The ID for the author of the revision.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `date` | The date the revision was published, in the site's timezone.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Context: `view`, `edit`, `embed` |
| `date_gmt` | The date the revision was published, as GMT.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Context: `view`, `edit` |
| `guid` | GUID for the revision, as it exists in the database.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit` |
| `id` | Unique identifier for the revision.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `modified` | The date the revision was last modified, in the site's timezone.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Context: `view`, `edit` |
| `modified_gmt` | The date the revision was last modified, as GMT.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Context: `view`, `edit` |
| `parent` | The ID for the parent of the revision.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `slug` | An alphanumeric identifier for the revision unique to its type.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `title` | Title of template.<br><br><br>JSON data type: object or string<br><br>Context: `embed`, `view`, `edit` |
| `content` | Content of template.<br><br><br>JSON data type: object or string<br><br>Context: `embed`, `view`, `edit` |

## List Template_Part Revisions

Query this endpoint to retrieve a collection of template\_part revisions. The response you receive can be controlled and filtered using the URL query parameters below.

### Definition

`GET /wp/v2/template-parts/<parent>/revisions`

### Example Request

`$ curl https://example.com/wp-json/wp/v2/template-parts/<parent>/revisions`

### Arguments

| `parent` | The ID for the parent of the revision. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| `page` | Current page of the collection.<br><br><br>Default: `1` |
| `per_page` | Maximum number of items to be returned in result set. |
| `search` | Limit results to those matching a string. |
| `exclude` | Ensure result set excludes specific IDs. |
| `include` | Limit result set to specific IDs. |
| `offset` | Offset the result set by a specific number of items. |
| `order` | Order sort attribute ascending or descending.<br><br><br>Default: `desc`<br><br>One of: `asc`, `desc` |
| `orderby` | Sort collection by object attribute.<br><br><br>Default: `date`<br><br>One of: `date`, `id`, `include`, `relevance`, `slug`, `include_slugs`, `title` |

## Retrieve a Template_Part Revision

### Definition & Example Request

`GET /wp/v2/template-parts/<parent>/revisions/<id>`

Query this endpoint to retrieve a specific template\_part revision record.

`$ curl https://example.com/wp-json/wp/v2/template-parts/<parent>/revisions/<id>`

### Arguments

| `parent` | The ID for the parent of the revision. |
| --- | --- |
| `id` | Unique identifier for the revision. |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Delete a Template_Part Revision

### Arguments

| `parent` | The ID for the parent of the revision. |
| --- | --- |
| `id` | Unique identifier for the revision. |
| `force` | Required to be true, as revisions do not support trashing. |

### Definition

`DELETE /wp/v2/template-parts/<parent>/revisions/<id>`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/template-parts/<parent>/revisions/<id>`

## Retrieve a Template_Part Revision

### Definition & Example Request

`GET /wp/v2/template-parts/<id>/autosaves`

Query this endpoint to retrieve a specific template\_part revision record.

`$ curl https://example.com/wp-json/wp/v2/template-parts/<id>/autosaves`

### Arguments

| `parent` | The ID for the parent of the autosave. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Create a Template_Part Revision

### Arguments

| `parent` | The ID for the parent of the autosave. |
| --- | --- |
| `slug` | Unique slug identifying the template. |
| `theme` | Theme identifier for the template. |
| `type` | Type of template. |
| `content` | Content of template. |
| `title` | Title of template. |
| `description` | Description of template. |
| `status` | Status of template.  <br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `author` | The ID for the author of the template. |
| `area` | Where the template part is intended for use (header, footer, etc.) |

### Definition

`POST /wp/v2/template-parts/<id>/autosaves`

## Retrieve a Template_Part Revision

### Definition & Example Request

`GET /wp/v2/template-parts/<parent>/autosaves/<id>`

Query this endpoint to retrieve a specific template\_part revision record.

`$ curl https://example.com/wp-json/wp/v2/template-parts/<parent>/autosaves/<id>`

### Arguments

| `parent` | The ID for the parent of the autosave. |
| --- | --- |
| `id` | The ID for the autosave. |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
