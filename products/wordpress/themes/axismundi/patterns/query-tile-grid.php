<?php
/**
 * Title: Tile grid
 * Slug: axismundi/query-tile-grid
 * Description: Responsive 3-up grid — a 4:3 image on top, then category, title, excerpt, author and date. A home / front-page layout.
 * Categories: query
 * Block Types: core/query
 *
 * A Query Loop starter: its own query (inherit:false) and just the repeated
 * post-template tile. The tile is a plain (transparent) group so the grid reads
 * light; promote to is-style-card-* for a contained look. Add pagination from
 * the editor when composing a page.
 *
 * @package Axismundi
 */

?>
<!-- wp:query {"queryId":2,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|250"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","style":{"border":{"radius":"12px"}}} /-->

<!-- wp:post-terms {"term":"category","textColor":"on-surface-variant","fontSize":"label-small"} /-->

<!-- wp:post-title {"isLink":true,"fontSize":"title-large"} /-->

<!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":33,"style":{"elements":{"link":{"color":{"text":"var:preset|color|on-surface-variant"}}}},"textColor":"on-surface-variant","fontSize":"body-medium"} /-->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:post-author-name {"textColor":"on-surface-variant","fontSize":"label-small"} /-->

<!-- wp:post-date {"textColor":"on-surface-variant","fontSize":"label-small"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
