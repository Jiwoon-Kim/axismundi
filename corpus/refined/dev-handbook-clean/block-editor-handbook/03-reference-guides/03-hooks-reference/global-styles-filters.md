---
source_url: https://developer.wordpress.org/block-editor/reference-guides/filters/global-styles-filters/
synced: 2026-05-12
handbook: block-editor
chapter: reference-guides
sub_chapter: hooks-reference
slug: global-styles-filters
parent_order: 3
sub_order: 3
page_order: 6
title: "Global Styles Filters"
code_quality: degraded
code_issue: pre_newline_loss
---

# Global Styles Filters

WordPress 6.1 has introduced some server-side filters to hook into the `theme.json` data provided at the different data layers:

- `wp_theme_json_data_default`: hooks into the default data provided by WordPress
- `wp_theme_json_data_blocks`: hooks into the data provided by the blocks
- `wp_theme_json_data_theme`: hooks into the data provided by the theme
- `wp_theme_json_data_user`: hooks into the data provided by the user

Each filter receives an instance of the `WP_Theme_JSON_Data` class with the data for the respective layer. To provide new data, the filter callback needs to use the `update_with( $new_data )` method, where `$new_data` is a valid `theme.json`-like structure. As with any `theme.json`, the new data needs to declare which `version` of the `theme.json` is using, so it can correctly be migrated to the runtime one, should it be different.

*Example:*

This is how to pass a new color palette for the theme and disable the text color UI:

> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
function wpdocs_filter_theme_json_theme( $theme_json ){ $new_data = array( 'version' => 2, 'settings' => array( 'color' => array( 'text' => false, 'palette' => array( /* New palette */ array( 'slug' => 'foreground', 'color' => 'black', 'name' => __( 'Foreground', 'theme-domain' ), ), array( 'slug' => 'background', 'color' => 'white', 'name' => __( 'Background', 'theme-domain' ), ), ), ), ), ); return $theme_json->update_with( $new_data );}add_filter( 'wp_theme_json_data_theme', 'wpdocs_filter_theme_json_theme' );
```
