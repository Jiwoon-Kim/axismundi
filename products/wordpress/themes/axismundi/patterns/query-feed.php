<?php
/**
 * Title: Query feed
 * Slug: axismundi/query-feed
 * Description: The shared reader-style posts feed — an inherited Query Loop of feed rows (featured image, meta, title, excerpt, category terms), enhanced pagination, and an empty state. Width-neutral so home.html wraps it in a single reader column while the archive templates place it beside a sidebar; every posts index speaks the same feed language.
 * Categories: query
 * Inserter: no
 *
 * inherit:true means this reads the main query, so the same pattern serves the
 * blog home (home.html), archives, and category/tag templates. It carries no
 * alignment or column of its own — the consumer owns the surrounding width and
 * any sidebar. Keep it in sync with the feed-row specimen in query-feed-row.php.
 *
 * @package Axismundi
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[]},"enhancedPagination":true,"layout":{"type":"default"}} -->
<div class="wp-block-query"><!-- wp:query-total /-->

<!-- wp:post-template {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
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
<!-- /wp:post-template -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:query-pagination {"paginationArrow":"arrow","showLabel":false,"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:group -->

<!-- wp:query-no-results -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"constrained","contentSize":"480px"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--600);padding-bottom:var(--wp--preset--spacing--600)"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}},"fontSize":"headline-medium"} -->
<h2 class="wp-block-heading has-text-align-center has-headline-medium-font-size"><?php echo esc_html_x( 'Nothing here yet', 'Archive empty-state heading.', 'axismundi' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"has-on-surface-variant-color has-text-color","style":{"typography":{"textAlign":"center"}},"textColor":"on-surface-variant","fontSize":"body-large"} -->
<p class="has-text-align-center has-on-surface-variant-color has-text-color has-body-large-font-size"><?php echo esc_html_x( 'There are no posts in this archive yet. Try another category or return home.', 'Archive empty-state guidance.', 'axismundi' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
