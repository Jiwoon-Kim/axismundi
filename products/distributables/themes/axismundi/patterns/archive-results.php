<?php
/**
 * Title: Archive results
 * Slug: axismundi/archive-results
 * Description: A tonal archive hero (breadcrumbs + title + description) over a two-column body — the inherited query-loop feed beside a sticky sidebar.
 * Categories: query
 * Inserter: no
 *
 * The hero is a full-width surface-container-low band (breadcrumbs, archive
 * query-title, optional term description). Below it, a wide two-column body: the
 * inherited (inherit:true) feed in the main column and a 280px sticky aside that
 * references axismundi/archive-sidebar. Core Columns stacks the aside under the
 * feed below ~782px; the aside column stays stretch so its sticky group can
 * travel the full feed height. The aside is a standalone pattern so specialised
 * archive templates (category/tag/date) can reorder or replace it.
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Archive results"},"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"align":"full","style":{"border":{"top":{"color":"var:preset|color|outline-variant","width":"1px"},"right":{},"bottom":{"color":"var:preset|color|outline-variant","width":"1px"},"left":{}}},"backgroundColor":"surface-container-low","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-container-low-background-color has-background" style="border-top-color:var(--wp--preset--color--outline-variant);border-top-width:1px;border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px"><!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|700","bottom":"var:preset|spacing|700"},"blockGap":"var:preset|spacing|200"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--700);padding-bottom:var(--wp--preset--spacing--700)"><!-- wp:breadcrumbs {"align":"wide","style":{"spacing":{"padding":{"right":"var:preset|spacing|0","left":"var:preset|spacing|0","top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"}}}} /-->

<!-- wp:query-title {"type":"archive","align":"wide","fontSize":"display-medium"} /-->

<!-- wp:term-description {"align":"wide","className":"has-on-surface-variant-color has-text-color","style":{"spacing":{"margin":{"top":"var:preset|spacing|200"}}},"textColor":"on-surface-variant","fontSize":"body-large"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|600"},"margin":{"top":"var:preset|spacing|500"}}}} -->
<div class="wp-block-columns alignwide" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[]},"enhancedPagination":true,"layout":{"type":"default"}} -->
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
<!-- /wp:query --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch","width":"280px"} -->
<div class="wp-block-column is-vertically-aligned-stretch" style="flex-basis:280px"><!-- wp:group {"className":"is-position-sticky","style":{"position":{"type":"sticky","top":"0px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group is-position-sticky"><!-- wp:pattern {"slug":"axismundi/archive-sidebar"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></main>
<!-- /wp:group -->
