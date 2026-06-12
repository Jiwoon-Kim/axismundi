<?php
/**
 * Title: VQA Theme Blocks
 * Slug: axismundi/vqa-theme
 * Categories: axismundi
 * Inserter: false
 * Description: WordPress core THEME-family blocks for the Axismundi baseline VQA —
 *   site identity, basic navigation, post identity/meta, and query/archive blocks.
 *   Comments and advanced navigation overlays are intentionally split into later
 *   lanes. Dev-only specimen — excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">VQA Theme — core theme-family blocks</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Theme blocks are dynamic template blocks. This specimen checks site identity, navigation, post identity, post meta, and query/archive rendering without treating widget utility lists or comments as part of this lane.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Site identity</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":64,"shouldSyncIcon":false} /-->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:site-title /-->

<!-- wp:site-tagline /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Navigation / breadcrumbs</h2>
<!-- /wp:heading -->

<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","justifyContent":"left"}} -->
<!-- wp:home-link {"label":"Home"} /-->

<!-- wp:navigation-link {"label":"VQA Design","url":"http://localhost:8884/?page_id=13"} /-->

<!-- wp:navigation-submenu {"label":"VQA Sections","url":"#"} -->
<!-- wp:navigation-link {"label":"Text","url":"http://localhost:8884/?page_id=8"} /-->

<!-- wp:navigation-link {"label":"Media","url":"http://localhost:8884/?page_id=14"} /-->

<!-- wp:navigation-link {"label":"Widgets","url":"http://localhost:8884/?page_id=20"} /-->
<!-- /wp:navigation-submenu -->
<!-- /wp:navigation -->

<!-- wp:breadcrumbs /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Current post/page identity</h2>
<!-- /wp:heading -->

<!-- wp:post-featured-image {"isLink":false} /-->

<!-- wp:post-title {"level":2,"isLink":false} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:avatar {"userId":1,"size":40} /-->

<!-- wp:post-author-name {"isLink":true} /-->

<!-- wp:post-date /-->

<!-- wp:post-modified-date /--></div>
<!-- /wp:group -->

<!-- wp:post-excerpt {"moreText":"Read more"} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:post-terms {"term":"category"} /-->

<!-- wp:post-terms {"term":"post_tag"} /-->

<!-- wp:post-time-to-read /-->

<!-- wp:post-comments-link /-->

<!-- wp:post-comments-count /--></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Query loop — post cards</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":101,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"displayLayout":{"type":"flex","columns":3}} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:group {"className":"is-style-card-filled","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-filled"><!-- wp:post-featured-image {"isLink":true} /-->

<!-- wp:post-title {"level":3,"isLink":true} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:post-date /-->

<!-- wp:post-author-name {"isLink":true} /-->

<!-- wp:post-terms {"term":"category"} /--></div>
<!-- /wp:group -->

<!-- wp:post-excerpt {"moreText":"Read more"} /-->

<!-- wp:read-more /--></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>No posts matched this query.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Query / archive labels</h2>
<!-- /wp:heading -->

<!-- wp:query-title {"type":"archive"} /-->

<!-- wp:query-total /-->

<!-- wp:term-name /-->

<!-- wp:term-description /-->

<!-- wp:term-count /-->
