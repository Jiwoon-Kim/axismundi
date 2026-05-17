---
source_url: https://developer.wordpress.org/rest-api/reference/nav_menu_item-revisions/
synced: 2026-05-12
handbook: rest-api
chapter: reference
slug: nav-menu-item-revisions
parent_order: 5
page_order: 16
title: "Nav_Menu_Item Revisions"
---

# Nav\_Menu\_Item Revisions

## Schema

The schema defines all the fields that exist within a nav\_menu\_item revision record. Any response from these endpoints can be expected to contain the fields below unless the `_filter` query parameter is used or the schema field only appears in a specific context.

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
| `title` | The title for the object.<br><br><br>JSON data type: string or object<br><br>Context: `view`, `edit`, `embed` |
| `preview_link` | Preview link for the post.<br><br><br>JSON data type: string,  <br>Format: uri<br><br>Read only<br><br>Context: `edit` |

## Retrieve a Nav_Menu_Item Revision

### Definition & Example Request

`GET /wp/v2/menu-items/<id>/autosaves`

Query this endpoint to retrieve a specific nav\_menu\_item revision record.

`$ curl https://example.com/wp-json/wp/v2/menu-items/<id>/autosaves`

### Arguments

| `parent` | The ID for the parent of the autosave. |
| --- | --- |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |

## Create a Nav_Menu_Item Revision

### Arguments

| `parent` | The ID for the parent of the object. |
| --- | --- |
| `title` | The title for the object. |
| `type` | The family of objects originally represented, such as "post\_type" or "taxonomy".  <br>One of: `taxonomy`, `post_type`, `post_type_archive`, `custom` |
| `status` | A named status for the object.  <br>One of: `publish`, `future`, `draft`, `pending`, `private` |
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

`POST /wp/v2/menu-items/<id>/autosaves`

## Retrieve a Nav_Menu_Item Revision

### Definition & Example Request

`GET /wp/v2/menu-items/<parent>/autosaves/<id>`

Query this endpoint to retrieve a specific nav\_menu\_item revision record.

`$ curl https://example.com/wp-json/wp/v2/menu-items/<parent>/autosaves/<id>`

### Arguments

| `parent` | The ID for the parent of the autosave. |
| --- | --- |
| `id` | The ID for the autosave. |
| `context` | Scope under which the request is made; determines fields present in response.<br><br><br>Default: `view`<br><br>One of: `view`, `embed`, `edit` |
