<?php
/**
 * Title: Compact list, no image
 * Slug: axismundi/query-compact-list
 * Description: A text-only divided list — date and category, then title and excerpt. For search results or dense secondary listings.
 * Categories: query
 * Block Types: core/query
 *
 * A Query Loop starter: its own query (inherit:false) and just the repeated
 * post-template row. No media, so it stays scannable in sidebars, search
 * results, and related-post strips. Add pagination from the editor when needed.
 *
 * @package Axismundi
 */

?>
<!-- wp:query {"queryId":3,"query":{"perPage":8,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"layout":{"type":"default"}} -->
<div class="wp-block-query"><!-- wp:post-template {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100","padding":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"}},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300)"><!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:post-date {"textColor":"on-surface-variant","fontSize":"label-small"} /-->

<!-- wp:post-terms {"term":"category","prefix":" · ","textColor":"on-surface-variant","fontSize":"label-small"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"isLink":true,"fontSize":"title-large"} /-->

<!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":28,"style":{"elements":{"link":{"color":{"text":"var:preset|color|on-surface-variant"}}}},"textColor":"on-surface-variant","fontSize":"body-medium"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
