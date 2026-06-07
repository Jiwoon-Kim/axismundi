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

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Latest Posts</h2>
<!-- /wp:heading -->

<!-- wp:latest-posts {"postsToShow":3,"displayPostDate":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Latest Comments</h2>
<!-- /wp:heading -->

<!-- wp:latest-comments {"displayAvatar":true,"displayDate":true,"displayExcerpt":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Archives &amp; Categories</h2>
<!-- /wp:heading -->

<!-- wp:archives {"showPostCounts":true} /-->

<!-- wp:categories {"showPostCounts":true,"showHierarchy":true} /-->

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
<h2 class="wp-block-heading">7. RSS</h2>
<!-- /wp:heading -->

<!-- wp:rss {"feedURL":"https://wordpress.org/news/feed/","itemsToShow":3,"displayDate":true,"displayExcerpt":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Social Links</h2>
<!-- /wp:heading -->

<!-- wp:social-links -->
<ul class="wp-block-social-links"><!-- wp:social-link {"url":"https://wordpress.org","service":"wordpress"} /-->

<!-- wp:social-link {"url":"https://github.com/Jiwoon-Kim/axismundi","service":"github"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:heading -->
<h2 class="wp-block-heading">9. Custom HTML &amp; Login/out</h2>
<!-- /wp:heading -->

<!-- wp:html -->
<p>Custom HTML block — raw markup passed through (<code>core/html</code>).</p>
<!-- /wp:html -->

<!-- wp:loginout /-->
