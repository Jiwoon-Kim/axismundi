<?php
/**
 * Title: Feed row, image left
 * Slug: axismundi/query-feed-row
 * Description: One-column feed — a 4:3 thumbnail beside author, date, title, excerpt, and category. A blog-index / archive-style listing.
 * Categories: query
 * Block Types: core/query
 *
 * A Query Loop starter: its own query (inherit:false) and just the repeated
 * post-template item, matching how core's Standard / Image-left / Grid starters
 * work. Add pagination or an empty state from the editor when composing a page.
 *
 * @package Axismundi
 */

?>
<!-- wp:query {"queryId":1,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"layout":{"type":"default"}} -->
<div class="wp-block-query"><!-- wp:post-template {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","style":{"spacing":{"padding":{"top":"var:preset|spacing|400","bottom":"var:preset|spacing|400"}},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"}}},"layout":{"type":"constrained"}} -->
<article class="wp-block-group" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--400);padding-bottom:var(--wp--preset--spacing--400)"><!-- wp:columns {"verticalAlignment":"top","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|400"}}}} -->
<div class="wp-block-columns are-vertically-aligned-top"><!-- wp:column {"verticalAlignment":"top","width":"200px"} -->
<div class="wp-block-column is-vertically-aligned-top" style="flex-basis:200px"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","style":{"border":{"radius":"12px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:post-author-name {"textColor":"on-surface-variant","fontSize":"body-small"} /-->

<!-- wp:post-date {"isLink":true,"textColor":"on-surface-variant","fontSize":"body-small"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"isLink":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"fontSize":"headline-small"} /-->

<!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":40,"fontSize":"body-medium"} /-->

<!-- wp:post-terms {"term":"category","style":{"spacing":{"margin":{"top":"var:preset|spacing|100"}}},"textColor":"on-surface-variant","fontSize":"label-small"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></article>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
