<?php
/**
 * Title: Single post with sidebar
 * Slug: axismundi/single-post-sidebar
 * Description: Single-post article layout with an empty, editable sidebar slot.
 * Categories: posts
 * Block Types: core/post-content
 *
 * @package Axismundi
 */

?>
<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|500"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:pattern {"slug":"axismundi/single-post"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch","width":"280px","layout":{"type":"default"}} -->
<div class="wp-block-column is-vertically-aligned-stretch" style="flex-basis:280px"><!-- wp:group {"tagName":"aside","metadata":{"name":"Sidebar"},"style":{"spacing":{"padding":{"top":"5vh"}},"position":{"type":"sticky","top":"0px"}},"layout":{"type":"default"}} -->
<aside class="wp-block-group" style="padding-top:5vh"></aside>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
