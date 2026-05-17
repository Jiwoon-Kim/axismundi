---
source_url: https://developer.wordpress.org/rest-api/reference/application-passwords/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: application-passwords
parent_order: 5
page_order: 1
title: "Application Passwords"
---

# Application Passwords

## Schema

The schema defines all the fields that exist within a application password record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `uuid` | The unique identifier for the application password.<br><br><br>JSON data type: string,  <br>Format: uuid<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `app_id` | A UUID provided by the application to uniquely identify it. It is recommended to use an UUID v5 with the URL or DNS namespace.<br><br><br>JSON data type: string,  <br>Format: uuid<br><br>Context: `view`, `edit`, `embed` |
| `name` | The name of the application password.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `password` | The generated password. Only available after adding an application.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `edit` |
| `created` | The GMT date the application password was created.<br><br><br>JSON data type: string,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Read only<br><br>Context: `view`, `edit` |
| `last_used` | The GMT date the application password was last used.<br><br><br>JSON data type: string or null,  <br>Format: datetime ([details](https://core.trac.wordpress.org/ticket/41032))<br><br>Read only<br><br>Context: `view`, `edit` |
| `last_ip` | The IP address the application password was last used by.<br><br><br>JSON data type: string or null,  <br>Format: ip<br><br>Read only<br><br>Context: `view`, `edit` |

## Retrieve a Application Password

### Definition & Example Request

`GET /wp/v2/users/<user_id>)/application-passwords`

Query this endpoint to retrieve a specific application password record.

`$ curl https://example.com/wp-json/wp/v2/users/<user_id>)/application-passwords`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Create a Application Password

### Arguments

| `app_id` | A UUID provided by the application to uniquely identify it. It is recommended to use an UUID v5 with the URL or DNS namespace. |
| --- | --- |
| `name` | The name of the application password.<br><br><br>Required: 1 |

### Definition

`POST /wp/v2/users/<user_id>)/application-passwords`

## Delete a Application Password

There are no arguments for this endpoint.

### Definition

`DELETE /wp/v2/users/<user_id>)/application-passwords`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/users/<user_id>)/application-passwords`
## Retrieve a Application Password

### Definition & Example Request

`GET /wp/v2/users/<user_id>)/application-passwords/introspect`

Query this endpoint to retrieve a specific application password record.

`$ curl https://example.com/wp-json/wp/v2/users/<user_id>)/application-passwords/introspect`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Retrieve a Application Password

### Definition & Example Request

`GET /wp/v2/users/<user_id>)/application-passwords/<uuid>`

Query this endpoint to retrieve a specific application password record.

`$ curl https://example.com/wp-json/wp/v2/users/<user_id>)/application-passwords/<uuid>`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |

## Update a Application Password

### Arguments

| `app_id` | A UUID provided by the application to uniquely identify it. It is recommended to use an UUID v5 with the URL or DNS namespace. |
| --- | --- |
| `name` | The name of the application password. |

### Definition

`POST /wp/v2/users/<user_id>)/application-passwords/<uuid>`

## Delete a Application Password

There are no arguments for this endpoint.

### Definition

`DELETE /wp/v2/users/<user_id>)/application-passwords/<uuid>`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/users/<user_id>)/application-passwords/<uuid>`
