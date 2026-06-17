<?php
/**
 * Title: Post navigation
 * Slug: axismundi/post-navigation
 * Categories: text
 * Description: Previous and up-next posts as two Material 3 "Up Next" cards.
 * Block Types: core/post-navigation-link
 *
 * Maps the M3 "previous and up next articles" card-set (m3.material.io): two medium
 * cards side by side, each an overline (direction arrow + label) over the linked
 * post title, the whole card being the link. core/post-navigation-link with
 * linkLabel + showTitle emits separate __label / __title spans; patterns.post-
 * navigation.css turns the link into the card and draws the M3 direction symbol in
 * the overline. The decorative `arrow` (none/arrow/chevron) is an editor-level
 * choice, so it is intentionally NOT set here. Labels go through esc_attr_e so they
 * stay translatable.
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"nav","ariaLabel":"<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>","className":"ax-post-nav","style":{"spacing":{"margin":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"default"}} -->
<nav class="wp-block-group ax-post-nav" aria-label="<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>" style="margin-top:var(--wp--preset--spacing--600);margin-bottom:var(--wp--preset--spacing--600)"><!-- wp:columns {"className":"ax-post-nav__set","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|100","left":"var:preset|spacing|100"}}}} -->
<div class="wp-block-columns ax-post-nav__set"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:post-navigation-link {"type":"previous","label":"<?php esc_attr_e( 'Previous', 'axismundi' ); ?>","showTitle":true,"linkLabel":true,"className":"ax-post-nav__card"} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:post-navigation-link {"label":"<?php esc_attr_e( 'Up next', 'axismundi' ); ?>","showTitle":true,"linkLabel":true,"className":"ax-post-nav__card"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></nav>
<!-- /wp:group -->
