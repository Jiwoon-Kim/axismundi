<?php
/**
 * Title: Archive results
 * Slug: axismundi/archive-results
 * Description: Archive title + optional term description, then a two-column body — the inherited query-loop feed beside a sticky sidebar.
 * Categories: query
 * Block Types: core/query
 *
 * Hero (query-title + term-description) sits above a wide two-column body: the
 * feed (axismundi/archive-query-loop) in the main column, a 280px aside holding
 * axismundi/archive-sidebar. Core Columns stacks the aside under the feed on
 * narrow viewports; the aside's inner group is sticky so the cards follow the
 * scroll on desktop. The aside is a standalone pattern so specialised archive
 * templates (category/tag/date) can reorder or replace it.
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Archive results"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|600","right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--600);padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group"><!-- wp:query-title {"type":"archive","fontSize":"headline-large"} /-->

<!-- wp:term-description {"className":"has-on-surface-variant-color has-text-color","style":{"spacing":{"margin":{"top":"var:preset|spacing|200"}}},"textColor":"on-surface-variant","fontSize":"body-large"} /--></div>
<!-- /wp:group -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|600"},"margin":{"top":"var:preset|spacing|500"}}}} -->
<div class="wp-block-columns alignwide" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:pattern {"slug":"axismundi/archive-query-loop"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"280px"} -->
<div class="wp-block-column" style="flex-basis:280px"><!-- wp:group {"style":{"position":{"type":"sticky","top":"32px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group is-position-sticky" style="top:32px"><!-- wp:pattern {"slug":"axismundi/archive-sidebar"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></main>
<!-- /wp:group -->
