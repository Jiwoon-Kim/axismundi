# Gutenberg issue draft — RSS block passes null to html_entity_decode

Status: unsubmitted draft.

Target: `WordPress/gutenberg`, bug report.

Title: **RSS block: `html_entity_decode()` receives `null` for a feed item without a title (PHP 8.1 deprecation)**

Measured 2026-11-14 on WordPress 7.1.4, Gutenberg 24.4.0, PHP 8.3.36, with
`WP_DEBUG` and `WP_DEBUG_DISPLAY` on. Deactivating Gutenberg and reloading the page
reproduced the notice from core's own copy, `wp-includes/blocks/rss.php:49` — measured, not
inferred from the file. A Trac ticket is therefore likely needed too; Trac search was not
reachable from here, so this draft does not claim whether one exists.

<!-- BEGIN GITHUB BODY -->
### Description

`render_block_core_rss()` passes `$item->get_title()` straight into `html_entity_decode()`. SimplePie returns `null` for a feed item that has no `<title>`, so on PHP 8.1+ every such item emits:

```
Deprecated: html_entity_decode(): Passing null to parameter #1 ($string) of type string is deprecated in .../block-library/rss.php on line 55
```

The rendered output is still correct, because the next statement already handles an empty title:

https://github.com/WordPress/gutenberg/blob/trunk/packages/block-library/src/rss/index.php#L55-L58

```php
$title = esc_html( trim( strip_tags( html_entity_decode( $item->get_title() ) ) ) );

if ( empty( $title ) ) {
    $title = __( '(no title)' );
}
```

So the block already expects items without titles; only the decode step is missing the cast. `html_entity_decode()` was added to this line in #70491 (fixing #70477), which is where the `null` path appeared.

The `displayExcerpt` branch in the same function does not have this problem — it checks `! empty( $description )` before decoding.

### Step-by-step reproduction instructions

1. Add an RSS block to a post or page.
2. Set its feed URL to a feed whose items have no `<title>` element. A Mastodon account feed is one, for example `https://mastodon.social/@<account>.rss` — Mastodon's account RSS omits item titles.
3. View the post on the front end with `WP_DEBUG` and `WP_DEBUG_DISPLAY` enabled, on PHP 8.1 or newer.

One deprecation notice is emitted per titleless item. The items themselves render as `(no title)`, which is the intended fallback.

### Expected behaviour

No deprecation notice. The `(no title)` fallback handles the case already.

### Actual behaviour

A `Deprecated: html_entity_decode(): Passing null to parameter #1 ($string)` notice for each item without a title.

### Screenshots, screen recording, code snippet

A cast is enough, and keeps the existing fallback as the single place that decides what an empty title renders as:

```php
$title = esc_html( trim( strip_tags( html_entity_decode( (string) $item->get_title() ) ) ) );
```

### Environment info

- WordPress 7.1.4
- Gutenberg 24.4.0
- PHP 8.3.36
- Reproduced on the front end; `WP_DEBUG` and `WP_DEBUG_DISPLAY` on.

I could not find an existing issue or PR for this — searched this repository and `WordPress/wordpress-develop` for `html_entity_decode`, `rss html_entity_decode`, and `rss deprecated null`. Apologies if I missed one.

### Please confirm that you have searched existing issues in the repo.

Yes

### Please confirm that you have tested with all plugins deactivated except Gutenberg.

Not exactly, and the result seems worth stating instead: I deactivated **Gutenberg** and reloaded the same page, and the notice still appeared — from core's own copy of this renderer, `wp-includes/blocks/rss.php:49` in WordPress 7.1.4, which carries the identical line. So this is not caused by another plugin, and it is not Gutenberg-only.
<!-- END GITHUB BODY -->
