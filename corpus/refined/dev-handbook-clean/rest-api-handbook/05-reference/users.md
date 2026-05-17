---
source_url: https://developer.wordpress.org/rest-api/reference/users/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: users
parent_order: 5
page_order: 37
title: "Users"
---

# Users

## Schema

The schema defines all the fields that exist within a user record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `id` | Unique identifier for the user.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| --- | --- |
| `username` | Login name for the user.<br><br><br>JSON data type: string<br><br>Context: `edit` |
| `name` | Display name for the user.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit` |
| `first_name` | First name for the user.<br><br><br>JSON data type: string<br><br>Context: `edit` |
| `last_name` | Last name for the user.<br><br><br>JSON data type: string<br><br>Context: `edit` |
| `email` | The email address for the user.<br><br><br>JSON data type: string,  <br>Format: email<br><br>Context: `edit` |
| `url` | URL of the user.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Context: `embed`, `view`, `edit` |
| `description` | Description of the user.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit` |
| `link` | Author URL of the user.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `locale` | Locale for the user.<br><br><br>JSON data type: string<br><br>Context: `edit`<br><br>One of: ``, `en_US` |
| `nickname` | The nickname for the user.<br><br><br>JSON data type: string<br><br>Context: `edit` |
| `slug` | An alphanumeric identifier for the user.<br><br><br>JSON data type: string<br><br>Context: `embed`, `view`, `edit` |
| `registered_date` | Registration date for the user.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Read only<br><br>Context: `edit` |
| `roles` | Roles assigned to the user.<br><br><br>JSON data type: array<br><br>Context: `edit` |
| `password` | Password for the user (never included).<br><br><br>JSON data type: string<br><br>Context: `` |
| `capabilities` | All capabilities assigned to the user.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| `extra_capabilities` | Any extra capabilities assigned to the user.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `edit` |
| `avatar_urls` | Avatar URLs for the user.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `embed`, `view`, `edit` |
| `meta` | Meta fields.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit` |

## List Users

Query this endpoint to retrieve a collection of users. The response you receive can be controlled and filtered using the URL query parameters below.

### Definition

`GET /wp/v2/users`

### Example Request

`$ curl https://example.com/wp-json/wp/v2/users`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `page` | Current page of the collection.<br><br><br>Default: `1` |
| `per_page` | Maximum number of items to be returned in result set.<br><br><br>Default: `10` |
| `search` | Limit results to those matching a string. |
| `exclude` | Ensure result set excludes specific IDs. |
| `include` | Limit result set to specific IDs. |
| `offset` | Offset the result set by a specific number of items. |
| `order` | Order sort attribute ascending or descending.<br><br><br>Default: `asc`<br><br>One of: `asc`, `desc` |
| `orderby` | Sort collection by user attribute.<br><br><br>Default: `name`<br><br>One of: `id`, `include`, `name`, `registered_date`, `slug`, `include_slugs`, `email`, `url` |
| `slug` | Limit result set to users with one or more specific slugs. |
| `roles` | Limit result set to users matching at least one specific role provided. Accepts csv list or single role. |
| `capabilities` | Limit result set to users matching at least one specific capability provided. Accepts csv list or single capability. |
| `who` | Limit result set to users who are considered authors.  <br>One of: `authors` |
| `has_published_posts` | Limit result set to users who have published posts. |

## Create a User

### Arguments

| `username` | Login name for the user.<br><br><br>Required: 1 |
| --- | --- |
| `name` | Display name for the user. |
| `first_name` | First name for the user. |
| `last_name` | Last name for the user. |
| `email` | The email address for the user.<br><br><br>Required: 1 |
| `url` | URL of the user. |
| `description` | Description of the user. |
| `locale` | Locale for the user.  <br>One of: ``, `en_US` |
| `nickname` | The nickname for the user. |
| `slug` | An alphanumeric identifier for the user. |
| `roles` | Roles assigned to the user. |
| `password` | Password for the user (never included).<br><br><br>Required: 1 |
| `meta` | Meta fields. |

### Definition

`POST /wp/v2/users`

## Retrieve a User

### Definition & Example Request

`GET /wp/v2/users/<id>`

Query this endpoint to retrieve a specific user record.

`$ curl https://example.com/wp-json/wp/v2/users/<id>`

### Arguments

| `id` | Unique identifier for the user. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Update a User

### Arguments

| `id` | Unique identifier for the user. |
| --- | --- |
| `username` | Login name for the user. |
| `name` | Display name for the user. |
| `first_name` | First name for the user. |
| `last_name` | Last name for the user. |
| `email` | The email address for the user. |
| `url` | URL of the user. |
| `description` | Description of the user. |
| `locale` | Locale for the user.  <br>One of: ``, `en_US` |
| `nickname` | The nickname for the user. |
| `slug` | An alphanumeric identifier for the user. |
| `roles` | Roles assigned to the user. |
| `password` | Password for the user (never included). |
| `meta` | Meta fields. |

### Definition

`POST /wp/v2/users/<id>`

## Delete a User

### Arguments

| `id` | Unique identifier for the user. |
| --- | --- |
| `force` | Required to be true, as users do not support trashing. |
| `reassign` | Reassign the deleted user's posts and links to this user ID.<br><br><br>Required: 1 |

### Definition

`DELETE /wp/v2/users/<id>`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/users/<id>`

## Retrieve a User

### Definition & Example Request

`GET /wp/v2/users/me`

Query this endpoint to retrieve a specific user record.

`$ curl https://example.com/wp-json/wp/v2/users/me`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Update a User

### Arguments

| `username` | Login name for the user. |
| --- | --- |
| `name` | Display name for the user. |
| `first_name` | First name for the user. |
| `last_name` | Last name for the user. |
| `email` | The email address for the user. |
| `url` | URL of the user. |
| `description` | Description of the user. |
| `locale` | Locale for the user.  <br>One of: ``, `en_US` |
| `nickname` | The nickname for the user. |
| `slug` | An alphanumeric identifier for the user. |
| `roles` | Roles assigned to the user. |
| `password` | Password for the user (never included). |
| `meta` | Meta fields. |

### Definition

`POST /wp/v2/users/me`

## Delete a User

### Arguments

| `force` | Required to be true, as users do not support trashing. |
| --- | --- |
| `reassign` | Reassign the deleted user's posts and links to this user ID.<br><br><br>Required: 1 |

### Definition

`DELETE /wp/v2/users/me`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/users/me`
