---
source_url: https://developer.wordpress.org/rest-api/reference/themes/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: themes
parent_order: 5
page_order: 35
title: "Themes"
---

# Themes

## Schema

The schema defines all the fields that exist within a theme record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `stylesheet` | The theme's stylesheet. This uniquely identifies the theme.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| --- | --- |
| `template` | The theme's template. If this is a child theme, this refers to the parent theme, otherwise this is the same as the theme's stylesheet.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| `author` | The theme author.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `author_uri` | The website of the theme author.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `description` | A description of the theme.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `is_block_theme` | Whether the theme is a block-based theme.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `` |
| `name` | The name of the theme.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `requires_php` | The minimum PHP version required for the theme to work.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| `requires_wp` | The minimum WordPress version required for the theme to work.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| `screenshot` | The theme's screenshot URL.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Read only<br><br>Context: `` |
| `tags` | Tags indicating styles and features of the theme.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `textdomain` | The theme's text domain.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| `theme_supports` | Features supported by this theme.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `theme_uri` | The URI of the theme's webpage.<br><br><br>JSON data type: object<br><br>Read only<br><br>Context: `` |
| `version` | The theme's current version.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `` |
| `status` | A named status for the theme.<br><br><br>JSON data type: string<br><br>Context: ``<br><br>One of: `inactive`, `active` |

## Retrieve a Theme

### Definition & Example Request

`GET /wp/v2/themes`

Query this endpoint to retrieve a specific theme record.

`$ curl https://example.com/wp-json/wp/v2/themes`

### Arguments

| `status` | Limit result set to themes assigned one or more statuses. |
| --- | --- |

## Retrieve a Theme

### Definition & Example Request

`GET /wp/v2/themes/<stylesheet>?)`

Query this endpoint to retrieve a specific theme record.

`$ curl https://example.com/wp-json/wp/v2/themes/<stylesheet>?)`

### Arguments

| `stylesheet` | The theme's stylesheet. This uniquely identifies the theme. |
| --- | --- |
