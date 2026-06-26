<?php
/**
 * Title: Single post
 * Slug: axismundi/single-post
 * Description: Single-post article layout without a sidebar.
 * Categories: posts
 * Block Types: core/post-content
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"patternName":"axismundi/single-post","name":"Single post","description":"Single-post article layout without a sidebar.","categories":["posts"]},"align":"wide","style":{"spacing":{"padding":{"right":"var:preset|spacing|200","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignwide" style="padding-right:var(--wp--preset--spacing--200);padding-left:var(--wp--preset--spacing--200)"><!-- wp:post-featured-image {"aspectRatio":"16/9","style":{"border":{"radius":"12px"}}} /-->

<!-- wp:post-terms {"term":"category","className":"is-style-badge"} /-->

<!-- wp:post-title {"level":1,"fontSize":"display-small"} /-->

<!-- wp:group {"metadata":{"name":"Byline"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|400"},"blockGap":"var:preset|spacing|125"}},"textColor":"on-surface-variant","fontSize":"body-small","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group has-on-surface-variant-color has-text-color has-body-small-font-size" style="margin-top:var(--wp--preset--spacing--300);margin-bottom:var(--wp--preset--spacing--400)"><!-- wp:avatar {"size":32,"style":{"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}}}} /-->

<!-- wp:post-author-name {"isLink":true} /-->

<!-- wp:post-date {"isLink":true,"metadata":{"bindings":{"datetime":{"source":"core/post-data","args":{"field":"date"}}}}} /--></div>
<!-- /wp:group -->

<!-- wp:post-content {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|200","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} /-->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|400"}}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--400)"><!-- wp:post-terms {"term":"post_tag","className":"is-style-tags"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|200"},"blockGap":"var:preset|spacing|200"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--200)"><!-- wp:post-terms {"term":"geotag","className":"is-style-tags"} /-->

<!-- wp:post-terms {"term":"geo_area","className":"is-style-tags"} /--></div>
<!-- /wp:group -->

<!-- wp:pattern {"slug":"axismundi/post-navigation"} /-->

<!-- wp:pattern {"slug":"axismundi/comments"} /--></main>
<!-- /wp:group -->
