---
source_url: https://developer.wordpress.org/apis/metadata/
synced: 2026-05-12
handbook: common-apis
chapter: metadata
slug: metadata
parent_order: 9
page_order: 0
title: "Metadata"
---

# Metadata

## Overview

The **Metadata API** is a simple and standarized way for retrieving and manipulating metadata of various WordPress object types.

Metadata for an object is a represented by a simple key-value pair.

Objects may contain multiple metadata entries that share the same key and differ only in their value.

## Function Reference

Add/Delete Metadata:

- [add_metadata()](https://developer.wordpress.org/reference/functions/add_metadata/)
- [delete_metadata()](https://developer.wordpress.org/reference/functions/delete_metadata/)

Get/Update Metadata:

- [get_metadata()](https://developer.wordpress.org/reference/functions/get_metadata/)
- [update_metadata()](https://developer.wordpress.org/reference/functions/update_metadata/)

## Database Requirements

This function assumes that a dedicated MySQL table exists for the `$meta_type` you specify. Some desired `$meta_types` do not come with pre-installed WordPress tables, and so they must be created manually.

### Default Meta Tables

Assuming a prefix of `wp_`, WordPress’s included meta tables are:

- `wp_commentmeta`: Metadata for specific comments.
- `wp_postmeta`: Metadata for pages, posts, and all other post types.
- `wp_usermeta`: Metadata for users.

### Meta Table Structure

To store data for meta types not included in the above table list, a new table needs to be created. All meta tables require four columns.

- `meta_id` – BIGINT(20): unsigned, auto\_increment, not null, primary key.
- `object_id` – BIGINT(20): unsigned, not null.  
Replace *object* with the singular name of the content type being used.  
For instance, this column might be named post\_id or term\_id.  
Although this column is used like a foreign key, it should not be defined as one.
- `meta_key` – VARCHAR(255): The key of your custom meta data.
- `meta_value` – LONGTEXT: The value of your custom meta data.

## Source File

Metadata API is located in `wp-includes/meta.php`.

## Related

**Metadata API**: [add_metadata()](https://developer.wordpress.org/reference/functions/add_metadata/), [get_metadata()](https://developer.wordpress.org/reference/functions/get_metadata/), [update_metadata()](https://developer.wordpress.org/reference/functions/update_metadata/), [delete_metadata()](https://developer.wordpress.org/reference/functions/delete_metadata/).
