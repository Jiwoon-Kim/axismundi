<?php
/**
 * Title: Search results
 * Slug: axismundi/search-results
 * Description: Displays the search title, search form, and inherited query-loop pattern.
 * Categories: query
 * Block Types: core/query
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Search results"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|600","right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--600);padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group"><!-- wp:query-title {"type":"search","fontSize":"headline-large"} /-->

<!-- wp:search {"label":"<?php echo esc_html_x( 'Search', 'Search form label.', 'axismundi' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr_x( 'Search this site', 'Search input field placeholder text.', 'axismundi' ); ?>","buttonText":"<?php echo esc_attr_x( 'Search', 'Button text. Verb.', 'axismundi' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|300"}}}} /-->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:pattern {"slug":"axismundi/search-query-loop"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
