---
source_url: https://developer.wordpress.org/rest-api/reference/nav_menu_items/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: nav-menu-items
parent_order: 5
page_order: 17
title: "Nav_Menu_Items"
---

# Nav\_Menu\_Items

## Schema

The schema defines all the fields that exist within a nav\_menu\_item record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

| `title` | The title for the object.<br><br><br>JSON data type: string or object<br><br>Context: `view`, `edit`, `embed` |
| --- | --- |
| `id` | Unique identifier for the object.<br><br><br>JSON data type: integer<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `type_label` | The singular label used to describe this type of menu item.<br><br><br>JSON data type: string<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `type` | The family of objects originally represented, such as "post\_type" or "taxonomy".<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed`<br><br>One of: `taxonomy`, `post_type`, `post_type_archive`, `custom` |
| `status` | A named status for the object.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed`<br><br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `parent` | The ID for the parent of the object.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `attr_title` | Text for the title attribute of the link element for this menu item.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `classes` | Class names for the link element of this menu item.<br><br><br>JSON data type: array<br><br>Context: `view`, `edit`, `embed` |
| `description` | The description of this menu item.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `menu_order` | The DB ID of the nav\_menu\_item that is this item's menu parent, if any, otherwise 0.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `object` | The type of object originally represented, such as "category", "post", or "attachment".<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed` |
| `object_id` | The database ID of the original object this menu item represents, for example the ID for posts or the term\_id for categories.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit`, `embed` |
| `target` | The target attribute of the link element for this menu item.<br><br><br>JSON data type: string<br><br>Context: `view`, `edit`, `embed`<br><br>One of: `_blank`, `` |
| `url` | The URL to which this menu item points.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Context: `view`, `edit`, `embed` |
| `xfn` | The XFN relationship expressed in the link of this menu item.<br><br><br>JSON data type: array<br><br>Context: `view`, `edit`, `embed` |
| `invalid` | Whether the menu item represents an object that no longer exists.<br><br><br>JSON data type: boolean<br><br>Read only<br><br>Context: `view`, `edit`, `embed` |
| `menus` | The terms assigned to the object in the nav\_menu taxonomy.<br><br><br>JSON data type: integer<br><br>Context: `view`, `edit` |
| `meta` | Meta fields.<br><br><br>JSON data type: object<br><br>Context: `view`, `edit` |

## List Nav_Menu_Items

Query this endpoint to retrieve a collection of nav\_menu\_items. The response you receive can be controlled and filtered using the URL query parameters below.

### Definition

`GET /wp/v2/menu-items`

### Example Request

`$ curl https://example.com/wp-json/wp/v2/menu-items`

### Arguments

| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
| --- | --- |
| `page` | Current page of the collection.<br><br><br>Default: `1` |
| `per_page` | Maximum number of items to be returned in result set.<br><br><br>Default: `100` |
| `search` | Limit results to those matching a string. |
| `after` | Limit response to posts published after a given ISO8601 compliant date. |
| `modified_after` | Limit response to posts modified after a given ISO8601 compliant date. |
| `before` | Limit response to posts published before a given ISO8601 compliant date. |
| `modified_before` | Limit response to posts modified before a given ISO8601 compliant date. |
| `exclude` | Ensure result set excludes specific IDs. |
| `include` | Limit result set to specific IDs. |
| `offset` | Offset the result set by a specific number of items. |
| `order` | Order sort attribute ascending or descending.<br><br><br>Default: `asc`<br><br>One of: `asc`, `desc` |
| `orderby` | Sort collection by object attribute.<br><br><br>Default: `menu_order`<br><br>One of: `author`, `date`, `id`, `include`, `modified`, `parent`, `relevance`, `slug`, `include_slugs`, `title`, `menu_order` |
| `search_columns` | Array of column names to be searched. |
| `slug` | Limit result set to posts with one or more specific slugs. |
| `status` | Limit result set to posts assigned one or more statuses.<br><br><br>Default: `publish` |
| `tax_relation` | Limit result set based on relationship between multiple taxonomies.  <br>One of: `AND`, `OR` |
| `menus` | Limit result set to items with specific terms assigned in the menus taxonomy. |
| `menus_exclude` | Limit result set to items except those with specific terms assigned in the menus taxonomy. |
| `menu_order` | Limit result set to posts with a specific menu\_order value. |

## Create a Nav_Menu_Item

### Arguments

| `title` | The title for the object. |
| --- | --- |
| `type` | The family of objects originally represented, such as "post\_type" or "taxonomy".<br><br><br>Default: `custom`<br><br>One of: `taxonomy`, `post_type`, `post_type_archive`, `custom` |
| `status` | A named status for the object.<br><br><br>Default: `publish`<br><br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `parent` | The ID for the parent of the object. |
| `attr_title` | Text for the title attribute of the link element for this menu item. |
| `classes` | Class names for the link element of this menu item. |
| `description` | The description of this menu item. |
| `menu_order` | The DB ID of the nav\_menu\_item that is this item's menu parent, if any, otherwise 0.<br><br><br>Default: `1` |
| `object` | The type of object originally represented, such as "category", "post", or "attachment". |
| `object_id` | The database ID of the original object this menu item represents, for example the ID for posts or the term\_id for categories. |
| `target` | The target attribute of the link element for this menu item.  <br>One of: `_blank`, `` |
| `url` | The URL to which this menu item points. |
| `xfn` | The XFN relationship expressed in the link of this menu item. |
| `menus` | The terms assigned to the object in the nav\_menu taxonomy. |
| `meta` | Meta fields. |

### Definition

`POST /wp/v2/menu-items`

## Retrieve a Nav_Menu_Item

### Definition & Example Request

`GET /wp/v2/menu-items/<id>`

Query this endpoint to retrieve a specific nav\_menu\_item record.

`$ curl https://example.com/wp-json/wp/v2/menu-items/<id>`

### Arguments

| `id` | Unique identifier for the post. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Update a Nav_Menu_Item

### Arguments

| `id` | Unique identifier for the post. |
| --- | --- |
| `title` | The title for the object. |
| `type` | The family of objects originally represented, such as "post\_type" or "taxonomy".  <br>One of: `taxonomy`, `post_type`, `post_type_archive`, `custom` |
| `status` | A named status for the object.  <br>One of: `publish`, `future`, `draft`, `pending`, `private` |
| `parent` | The ID for the parent of the object. |
| `attr_title` | Text for the title attribute of the link element for this menu item. |
| `classes` | Class names for the link element of this menu item. |
| `description` | The description of this menu item. |
| `menu_order` | The DB ID of the nav\_menu\_item that is this item's menu parent, if any, otherwise 0. |
| `object` | The type of object originally represented, such as "category", "post", or "attachment". |
| `object_id` | The database ID of the original object this menu item represents, for example the ID for posts or the term\_id for categories. |
| `target` | The target attribute of the link element for this menu item.  <br>One of: `_blank`, `` |
| `url` | The URL to which this menu item points. |
| `xfn` | The XFN relationship expressed in the link of this menu item. |
| `menus` | The terms assigned to the object in the nav\_menu taxonomy. |
| `meta` | Meta fields. |

### Definition

`POST /wp/v2/menu-items/<id>`

## Delete a Nav_Menu_Item

### Arguments

| `id` | Unique identifier for the post. |
| --- | --- |
| `force` | Whether to bypass Trash and force deletion. |

### Definition

`DELETE /wp/v2/menu-items/<id>`

### Example Request

`$ curl -X DELETE https://example.com/wp-json/wp/v2/menu-items/<id>`
