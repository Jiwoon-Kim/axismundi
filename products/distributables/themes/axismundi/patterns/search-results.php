<?php
/**
 * Title: Search results
 * Slug: axismundi/search-results
 * Description: Displays the search title, search form, and inherited query-loop pattern.
 * Categories: query
 * Inserter: no
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Search results"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|600","right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--600);padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group"><!-- wp:query-title {"type":"search","fontSize":"headline-large"} /-->

<!-- wp:search {"label":"<?php echo esc_html_x( 'Search', 'Search form label.', 'axismundi' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr_x( 'Search this site', 'Search input field placeholder text.', 'axismundi' ); ?>","buttonText":"<?php echo esc_attr_x( 'Search', 'Button text. Verb.', 'axismundi' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|300"}}}} /-->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:query {"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[]},"align":"full","layout":{"type":"default"}} -->
<div class="wp-block-query alignfull"><!-- wp:query-total {"displayType":"total-results"} /-->

<!-- wp:query-no-results -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"constrained","contentSize":"480px"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--600);padding-bottom:var(--wp--preset--spacing--600)"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}},"fontSize":"headline-medium"} -->
<h2 class="wp-block-heading has-text-align-center has-headline-medium-font-size"><?php echo esc_html_x( 'No results found', 'Search results empty-state heading.', 'axismundi' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"has-on-surface-variant-color has-text-color","style":{"typography":{"textAlign":"center"}},"textColor":"on-surface-variant","fontSize":"body-large"} -->
<p class="has-text-align-center has-on-surface-variant-color has-text-color has-body-large-font-size"><?php echo esc_html_x( 'Try another search term or check the spelling.', 'Search results empty-state guidance.', 'axismundi' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:query-no-results -->

<!-- wp:post-template {"align":"full","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<!-- wp:group {"tagName":"article","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"},"blockGap":"var:preset|spacing|125"},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"}}},"layout":{"type":"constrained"}} -->
<article class="wp-block-group alignfull" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:post-date {"isLink":true,"metadata":{"bindings":{"datetime":{"source":"core/post-data","args":{"field":"date"}}}}} /-->

<!-- wp:post-terms {"term":"category"} /--></div>
<!-- /wp:group -->

<!-- wp:post-title {"isLink":true,"fontSize":"headline-small"} /-->

<!-- wp:post-excerpt {"moreText":"<?php echo esc_html_x( 'Continue reading', 'Search result excerpt link text.', 'axismundi' ); ?>","showMoreOnNewLine":false,"excerptLength":36,"fontSize":"body-medium"} /--></article>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:query-pagination {"paginationArrow":"arrow","showLabel":false,"align":"wide","layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:group --></div>
<!-- /wp:query --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
