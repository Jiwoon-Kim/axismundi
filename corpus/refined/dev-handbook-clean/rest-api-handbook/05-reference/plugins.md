---
source_url: https://developer.wordpress.org/rest-api/reference/plugins/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: plugins
parent_order: 5
page_order: 21
title: "Plugins"
---

# Plugins

## Schema

The schema defines all the fields that exist within a plugin record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `plugin` | The plugin file.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `status` | The plugin activation status.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed`<br><br>One of: `inactive`, `active` |
| `name` | The plugin name.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `plugin_uri` | The plugin's website address.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Read only<br><br>Context: `view`, `edit` |
| `author` | The plugin author.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `view`, `edit` |
| `author_uri` | Plugin author's website address.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Read only<br><br>Context: `view`, `edit` |
| `description` | The plugin description.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `view`, `edit` |
| `version` | The plugin version number.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit` |
| `network_only` | Whether the plugin can only be activated network-wide.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `requires_wp` | Minimum required version of WordPress.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `requires_php` | Minimum required version of PHP.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `textdomain` | The plugin's text domain.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit` |

## Retrieve a Plugin

### Definition & Example Request

`GET /wp/v2/plugins`

Query this endpoint to retrieve a specific plugin record.

`$ curl https://example.com/wp-json/wp/v2/plugins`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `search` | Limit results to those matching a string. |
| `status` | Limits results to plugins with the given status. |

## Create a Plugin

### Arguments

| `slug` | WordPress.org plugin directory slug.<br><br><br>Required: 1 |
| --- | --- |
| `status` | The plugin activation status.<br><br><br>Default: `inactive`<br><br>One of: `inactive`, `active` |

### Definition

`POST /wp/v2/plugins`

## Retrieve a Plugin

### Definition & Example Request

`GET /wp/v2/plugins/<plugin>?)`

Query this endpoint to retrieve a specific plugin record.

`$ curl https://example.com/wp-json/wp/v2/plugins/<plugin>?)`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `plugin` |  |

## Update a Plugin

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `plugin` |  |
| `status` | The plugin activation status.  <br>One of: `inactive`, `active` |

### Definition

`POST /wp/v2/plugins/<plugin>?)`

## Delete a Plugin

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `plugin` |  |

### Definition

`DELETE /wp/v2/plugins/<plugin>?)`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/plugins/<plugin>?)`
