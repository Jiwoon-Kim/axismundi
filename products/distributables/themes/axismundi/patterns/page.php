<?php
/**
 * Title: Page
 * Slug: axismundi/page
 * Description: Standard page layout with a title and content.
 * Categories: pages
 * Block Types: core/post-content
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"patternName":"axismundi/page","name":"Page","description":"Standard page layout with a title and content.","categories":["pages"]},"align":"full","layout":{"type":"default"}} -->
<main class="wp-block-group alignfull"><!-- wp:group {"metadata":{"name":"Page cover"},"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|900","right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained","contentSize":"760px","justifyContent":"center"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--900);padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:post-title {"level":1,"fontSize":"display-medium"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Page content"},"align":"wide","style":{"spacing":{"padding":{"right":"var:preset|spacing|200","bottom":"var:preset|spacing|900","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--900);padding-left:var(--wp--preset--spacing--200)"><!-- wp:post-content {"layout":{"type":"constrained"}} /--></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
