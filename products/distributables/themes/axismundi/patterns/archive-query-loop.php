<?php
/**
 * Title: Archive feed, 1 column
 * Slug: axismundi/archive-query-loop
 * Categories: query
 * Block Types: core/query
 * Description: A single-column archive feed — thumbnail beside metadata, title, and excerpt.
 *
 * @package Axismundi
 */

?>
<!-- wp:query {"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[]},"align":"full","layout":{"type":"default"}} -->
<div class="wp-block-query alignfull"><!-- wp:query-total {"displayType":"total-results"} /-->

<!-- wp:query-no-results -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"constrained","contentSize":"480px"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--600);padding-bottom:var(--wp--preset--spacing--600)"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}},"fontSize":"headline-medium"} -->
<h2 class="wp-block-heading has-text-align-center has-headline-medium-font-size"><?php echo esc_html_x( 'Nothing here yet', 'Archive empty-state heading.', 'axismundi' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"has-on-surface-variant-color has-text-color","style":{"typography":{"textAlign":"center"}},"textColor":"on-surface-variant","fontSize":"body-large"} -->
<p class="has-text-align-center has-on-surface-variant-color has-text-color has-body-large-font-size"><?php echo esc_html_x( 'There are no posts in this archive yet. Try another category or return home.', 'Archive empty-state guidance.', 'axismundi' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:query-no-results -->

<!-- wp:post-template {"align":"full","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|400","bottom":"var:preset|spacing|400"}},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"}}},"layout":{"type":"constrained"}} -->
<article class="wp-block-group alignfull" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--400);padding-bottom:var(--wp--preset--spacing--400)"><!-- wp:columns {"verticalAlignment":"top","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|400"}}}} -->
<div class="wp-block-columns are-vertically-aligned-top"><!-- wp:column {"verticalAlignment":"top","width":"200px"} -->
<div class="wp-block-column is-vertically-aligned-top" style="flex-basis:200px"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3","style":{"border":{"radius":"12px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:post-author-name {"textColor":"on-surface-variant","fontSize":"body-small"} /-->

<!-- wp:post-date {"isLink":true,"textColor":"on-surface-variant","fontSize":"body-small"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"isLink":true,"fontSize":"headline-small","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} /-->

<!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":40,"fontSize":"body-medium"} /-->

<!-- wp:post-terms {"term":"category","textColor":"on-surface-variant","fontSize":"label-small","style":{"spacing":{"margin":{"top":"var:preset|spacing|100"}}}} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:query-pagination {"paginationArrow":"arrow","showLabel":false,"align":"wide","layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:group --></div>
<!-- /wp:query -->
