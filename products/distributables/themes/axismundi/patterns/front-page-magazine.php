<?php
/**
 * Title: Front page — magazine
 * Slug: axismundi/front-page-magazine
 * Description: A curated home for a static front page — a Featured 3-up grid over a Latest reader feed. Each section is an independent (inherit:false) query, so it works as page content: insert it into a Page and assign that page as your homepage (Settings > Reading). home.html then serves the posts index. The Latest query offsets by 3 so it does not repeat the Featured posts.
 * Categories: featured
 * Post Types: page
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|800","padding":{"bottom":"var:preset|spacing|800"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-bottom:var(--wp--preset--spacing--800)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|400"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"level":2,"fontSize":"headline-medium"} -->
<h2 class="wp-block-heading has-headline-medium-font-size">Featured</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":10,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"layout":{"type":"default"}} -->
<div class="wp-block-query"><!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|500"}},"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|250"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","style":{"border":{"radius":"12px"}}} /-->

<!-- wp:post-terms {"term":"category","className":"is-style-tags","textColor":"on-surface-variant","fontSize":"label-small"} /-->

<!-- wp:post-title {"isLink":true,"fontSize":"title-large"} /-->

<!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":33,"style":{"elements":{"link":{"color":{"text":"var:preset|color|on-surface-variant"}}}},"textColor":"on-surface-variant","fontSize":"body-medium"} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:post-author-name {"textColor":"on-surface-variant","fontSize":"label-small"} /-->

<!-- wp:post-date {"textColor":"on-surface-variant","fontSize":"label-small"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|300"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"},"top":{},"right":{},"left":{}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px"><!-- wp:heading {"level":2,"style":{"spacing":{"padding":{"bottom":"var:preset|spacing|200"}}},"fontSize":"headline-medium"} -->
<h2 class="wp-block-heading has-headline-medium-font-size" style="padding-bottom:var(--wp--preset--spacing--200)">Latest</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:query {"queryId":11,"query":{"perPage":6,"pages":0,"offset":3,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"layout":{"type":"default"}} -->
<div class="wp-block-query"><!-- wp:post-template {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","style":{"spacing":{"padding":{"top":"var:preset|spacing|400","bottom":"var:preset|spacing|400"}},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"}}},"layout":{"type":"constrained"}} -->
<article class="wp-block-group" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--400);padding-bottom:var(--wp--preset--spacing--400)"><!-- wp:columns {"verticalAlignment":"top","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|400"}}}} -->
<div class="wp-block-columns are-vertically-aligned-top"><!-- wp:column {"verticalAlignment":"top","width":"200px"} -->
<div class="wp-block-column is-vertically-aligned-top" style="flex-basis:200px"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","style":{"border":{"radius":"12px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:post-author-name {"isLink":true,"textColor":"on-surface-variant","fontSize":"body-small"} /-->

<!-- wp:post-date {"textColor":"on-surface-variant","fontSize":"body-small"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"isLink":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"fontSize":"headline-small"} /-->

<!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":40,"fontSize":"body-medium"} /-->

<!-- wp:post-terms {"term":"category","className":"is-style-tags","style":{"spacing":{"margin":{"top":"var:preset|spacing|100"}}},"textColor":"on-surface-variant","fontSize":"label-small"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
