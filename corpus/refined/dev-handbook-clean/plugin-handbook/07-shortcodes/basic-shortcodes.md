---
source_url: https://developer.wordpress.org/plugins/shortcodes/basic-shortcodes/
synced: 2026-05-12
handbook: plugin
chapter: shortcodes
slug: basic-shortcodes
parent_order: 7
page_order: 1
title: "Basic Shortcodes"
code_quality: degraded
code_issue: pre_newline_loss
---

# Basic Shortcodes

## Add a Shortcode

It is possible to add your own shortcodes by using the Shortcode API. The process involves registering a callback `$func` to a shortcode `$tag` using `add_shortcode()`.


```php
add_shortcode( string $tag, callable $func);
```

`[wporg]` is your new shortcode. The use of the shortcode will trigger the `wporg_shortcode` callback function.


> [!WARNING]
> Code block appears degraded due to lost newlines during scraping.

```php
add_shortcode('wporg', 'wporg_shortcode');function wporg_shortcode( $atts = [], $content = null) { // do something to $content // always return return $content;}
```

## Remove a Shortcode

It is possible to remove shortcodes by using the Shortcode API. The process involves removing a registered `$tag` using [remove_shortcode()](https://developer.wordpress.org/reference/functions/remove_shortcode/) .


```php
remove_shortcode( string $tag);
```

Make sure that the shortcode have been registered before attempting to remove. Specify a higher priority number for [add_action()](https://developer.wordpress.org/reference/functions/add_action/) or hook into an action hook that is run later.

## Check if a Shortcode Exists

To check whether a shortcode has been registered use `shortcode_exists()`.
