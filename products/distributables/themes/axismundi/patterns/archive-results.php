<?php
/**
 * Title: Archive results
 * Slug: axismundi/archive-results
 * Description: Displays the archive title, optional term description, and inherited query-loop pattern.
 * Categories: query
 * Block Types: core/query
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Archive results"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|600","right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--600);padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group"><!-- wp:query-title {"type":"archive","fontSize":"headline-large"} /-->

<!-- wp:term-description {"className":"has-on-surface-variant-color has-text-color","style":{"spacing":{"margin":{"top":"var:preset|spacing|200"}}},"textColor":"on-surface-variant","fontSize":"body-large"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:pattern {"slug":"axismundi/archive-query-loop"} /--></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
