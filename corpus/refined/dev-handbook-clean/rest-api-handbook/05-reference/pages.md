---
source_url: https://developer.wordpress.org/rest-api/reference/pages/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: pages
parent_order: 5
page_order: 19
title: "Pages"
---

# Pages

## Schema

The schema defines all the fields that exist within a page record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `date` | The date the post was published, in the site's timezone.<br><br><br>JSON data type: string or null,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `date_gmt` | The date the post was published, as GMT.<br><br><br>JSON data type: string or null,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Context: `view`, `edit` |
| `guid` | The globally unique identifier for the post.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `view`, `edit` |
| `id` | Unique identifier for the post.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `link` | URL to the post.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `modified` | The date the post was last modified, in the site's timezone.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Read only<br><br>Context: `view`, `edit` |
| `modified_gmt` | The date the post was last modified, as GMT.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Read only<br><br>Context: `view`, `edit` |
| `slug` | An alphanumeric identifier for the post unique to its type.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `status` | A named status for the post.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`<br><br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `type` | Type of post.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `password` | A password to protect access to the content and excerpt.<br><br><br>JSON data type: string<br><br>Context: `edit` |
| `permalink_template` | Permalink template for the post.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `edit` |
| `generated_slug` | Slug automatically generated from the post title.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `edit` |
| `parent` | The ID for the parent of the post.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit` |
| `title` | The title for the post.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit`, `embed` |
| `content` | The content for the post.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit` |
| `author` | The ID for the author of the post.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `excerpt` | The excerpt for the post.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit`, `embed` |
| `featured_media` | The ID of the featured media for the post.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `comment_status` | Whether or not comments are open on the post.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`<br><br>One of: `open`, `closed` |
| `ping_status` | Whether or not the post can be pinged.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`<br><br>One of: `open`, `closed` |
| `menu_order` | The order of the post in relation to other posts.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit` |
| `meta` | Meta fields.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit` |
| `template` | The theme file to use to display the post.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit` |

## List Pages

Query this endpoint to retrieve a collection of pages. The response you receive can be controlled and filtered using the URL query parameters below.

### Definition

`GET /wp/v2/pages`

### Example Request

`$ curl https://example.com/wp-json/wp/v2/pages`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `page` | Current page of the collection.<br><br><br>Default: `1` |
| `per_page` | Maximum number of items to be returned in result set.<br><br><br>Default: `10` |
| `search` | Limit results to those matching a string. |
| `after` | Limit response to posts published after a given ISO8601 compliant date. |
| `modified_after` | Limit response to posts modified after a given ISO8601 compliant date. |
| `author` | Limit result set to posts assigned to specific authors. |
| `author_exclude` | Ensure result set excludes posts assigned to specific authors. |
| `before` | Limit response to posts published before a given ISO8601 compliant date. |
| `modified_before` | Limit response to posts modified before a given ISO8601 compliant date. |
| `exclude` | Ensure result set excludes specific IDs. |
| `include` | Limit result set to specific IDs. |
| `menu_order` | Limit result set to posts with a specific menu\_order value. |
| `offset` | Offset the result set by a specific number of items. |
| `order` | Order sort attribute ascending or descending.<br><br><br>Default: `desc`<br><br>One of: `asc`, `desc` |
| `orderby` | Sort collection by post attribute.<br><br><br>Default: `date`<br><br>One of: `author`, `date`, `id`, `include`, `modified`, `parent`, `relevance`, `slug`, `include_slugs`, `title`, `menu_order` |
| `parent` | Limit result set to items with particular parent IDs. |
| `parent_exclude` | Limit result set to all items except those of a particular parent ID. |
| `search_columns` | Array of column names to be searched. |
| `slug` | Limit result set to posts with one or more specific slugs. |
| `status` | Limit result set to posts assigned one or more statuses.<br><br><br>Default: `publish` |

## Create a Page

### Arguments

| `date` | The date the post was published, in the site's timezone. |
| --- | --- |
| `date_gmt` | The date the post was published, as GMT. |
| `slug` | An alphanumeric identifier for the post unique to its type. |
| `status` | A named status for the post.  <br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `password` | A password to protect access to the content and excerpt. |
| `parent` | The ID for the parent of the post. |
| `title` | The title for the post. |
| `content` | The content for the post. |
| `author` | The ID for the author of the post. |
| `excerpt` | The excerpt for the post. |
| `featured_media` | The ID of the featured media for the post. |
| `comment_status` | Whether or not comments are open on the post.  <br>One of: `open`, `closed` |
| `ping_status` | Whether or not the post can be pinged.  <br>One of: `open`, `closed` |
| `menu_order` | The order of the post in relation to other posts. |
| `meta` | Meta fields. |
| `template` | The theme file to use to display the post. |

### Definition

`POST /wp/v2/pages`

## Retrieve a Page

### Definition & Example Request

`GET /wp/v2/pages/<id>`

Query this endpoint to retrieve a specific page record.

`$ curl https://example.com/wp-json/wp/v2/pages/<id>`

### Arguments

| `id` | Unique identifier for the post. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| `password` | The password for the post if it is password protected. |

## Update a Page

### Arguments

| `id` | Unique identifier for the post. |
| --- | --- |
| `date` | The date the post was published, in the site's timezone. |
| `date_gmt` | The date the post was published, as GMT. |
| `slug` | An alphanumeric identifier for the post unique to its type. |
| `status` | A named status for the post.  <br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `password` | A password to protect access to the content and excerpt. |
| `parent` | The ID for the parent of the post. |
| `title` | The title for the post. |
| `content` | The content for the post. |
| `author` | The ID for the author of the post. |
| `excerpt` | The excerpt for the post. |
| `featured_media` | The ID of the featured media for the post. |
| `comment_status` | Whether or not comments are open on the post.  <br>One of: `open`, `closed` |
| `ping_status` | Whether or not the post can be pinged.  <br>One of: `open`, `closed` |
| `menu_order` | The order of the post in relation to other posts. |
| `meta` | Meta fields. |
| `template` | The theme file to use to display the post. |

### Definition

`POST /wp/v2/pages/<id>`

## Delete a Page

### Arguments

| `id` | Unique identifier for the post. |
| --- | --- |
| `force` | Whether to bypass Trash and force deletion. |

### Definition

`DELETE /wp/v2/pages/<id>`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/pages/<id>`
