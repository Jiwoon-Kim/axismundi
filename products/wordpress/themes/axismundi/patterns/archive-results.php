<?php
/**
 * Title: Archive results
 * Slug: axismundi/archive-results
 * Description: A tonal archive hero (breadcrumbs + title + description) over a two-column body — the shared query-feed beside a sticky sidebar.
 * Categories: query
 * Inserter: no
 *
 * The hero is a full-width surface-container-low band (breadcrumbs, archive
 * query-title, optional term description). Below it, a wide two-column body: the
 * shared axismundi/query-feed (inherit:true) in the main column and a 280px
 * sticky aside that references axismundi/archive-sidebar. Core Columns stacks the
 * aside under the feed below ~782px; the aside column stays stretch so its sticky
 * group can travel the full feed height. The feed body and the aside are each
 * standalone patterns, so home.html reuses the same feed without a sidebar and
 * specialised archive templates (category/tag/date) can reorder or replace the
 * aside.
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
<div class="wp-block-column"><!-- wp:pattern {"slug":"axismundi/query-feed"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch","width":"280px"} -->
<div class="wp-block-column is-vertically-aligned-stretch" style="flex-basis:280px"><!-- wp:group {"className":"is-position-sticky","style":{"position":{"type":"sticky","top":"0px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group is-position-sticky"><!-- wp:pattern {"slug":"axismundi/archive-sidebar"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></main>
<!-- /wp:group -->
