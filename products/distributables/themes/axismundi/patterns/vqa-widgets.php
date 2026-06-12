<?php
/**
 * Title: VQA Widgets Blocks
 * Slug: axismundi/vqa-widgets
 * Categories: axismundi
 * Inserter: false
 * Description: WordPress core WIDGET-family blocks for the Axismundi baseline VQA —
 *   search, latest-posts, latest-comments, archives, categories, tag-cloud,
 *   calendar, page-list, rss, social-links, custom-html, loginout. Block-rooted
 *   (wp:* / .wp-block-*), unstyled, for the blank/core render snapshot before M3
 *   binding. The data-driven widgets need demo posts/comments (the seed creates
 *   them). Dev-only specimen — excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">VQA Widgets — core widget-family blocks</h1>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Search</h2>
<!-- /wp:heading -->

<!-- wp:search {"label":"Search","buttonText":"Search"} /-->

<!-- wp:search {"label":"Search","buttonText":"Search","buttonPosition":"button-inside"} /-->

<!-- wp:search {"label":"Search","buttonPosition":"no-button"} /-->

<!-- wp:search {"label":"Search","showLabel":false,"buttonText":"Search","buttonPosition":"button-only","isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","buttonText":"Search","buttonUseIcon":true} /-->

<!-- wp:search {"label":"Search","buttonText":"Search","buttonPosition":"button-inside","buttonUseIcon":true} /-->

<!-- wp:search {"label":"Search","buttonPosition":"no-button","buttonUseIcon":true} /-->

<!-- wp:search {"label":"Search","showLabel":false,"buttonText":"Search","buttonPosition":"button-only","buttonUseIcon":true,"isSearchFieldHidden":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Latest Posts — list / grid</h2>
<!-- /wp:heading -->

<!-- wp:latest-posts {"postsToShow":3,"displayPostContent":true,"displayAuthor":true,"displayPostDate":true,"displayFeaturedImage":true,"featuredImageAlign":"left"} /-->

<!-- wp:latest-posts {"displayPostContent":true,"displayAuthor":true,"displayPostDate":true,"postLayout":"grid","displayFeaturedImage":true,"featuredImageAlign":"center"} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Latest Comments</h2>
<!-- /wp:heading -->

<!-- wp:latest-comments /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Archives &amp; Categories</h2>
<!-- /wp:heading -->

<!-- wp:archives {"showPostCounts":true} /-->

<!-- wp:categories {"showHierarchy":true,"showPostCounts":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Tag Cloud &amp; Calendar</h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud /-->

<!-- wp:calendar /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Page List</h2>
<!-- /wp:heading -->

<!-- wp:page-list /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. RSS — list / grid</h2>
<!-- /wp:heading -->

<!-- wp:rss {"feedURL":"https://wordpress.org/news/feed/","itemsToShow":3,"displayExcerpt":true,"displayAuthor":true,"displayDate":true} /-->

<!-- wp:rss {"blockLayout":"grid","feedURL":"https://wordpress.org/news/feed/","itemsToShow":4,"displayExcerpt":true,"displayAuthor":true,"displayDate":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Social Links — sizes / logos-only / pill</h2>
<!-- /wp:heading -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(--md-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(--md-sys-color-secondary-container)","size":"has-small-icon-size","className":"is-style-default"} -->
<ul class="wp-block-social-links has-small-icon-size has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(--md-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(--md-sys-color-secondary-container)","className":"is-style-default"} -->
<ul class="wp-block-social-links has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(--md-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(--md-sys-color-secondary-container)","size":"has-large-icon-size","className":"is-style-default"} -->
<ul class="wp-block-social-links has-large-icon-size has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(--md-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(--md-sys-color-secondary-container)","size":"has-huge-icon-size","className":"is-style-default"} -->
<ul class="wp-block-social-links has-huge-icon-size has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"showLabels":true,"className":"is-style-default"} -->
<ul class="wp-block-social-links has-visible-labels is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"className":"is-style-logos-only"} -->
<ul class="wp-block-social-links is-style-logos-only"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"className":"is-style-pill-shape"} -->
<ul class="wp-block-social-links is-style-pill-shape"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:heading -->
<h2 class="wp-block-heading">9. Custom HTML &amp; Login/out</h2>
<!-- /wp:heading -->

<!-- wp:html -->
<p>Custom HTML block — raw markup passed through (<code>core/html</code>).</p>
<!-- /wp:html -->

<!-- wp:loginout /-->
