<?php
/**
 * Title: Post navigation
 * Slug: axismundi/post-navigation
 * Categories: text
 * Description: Next and previous post links.
 * Block Types: core/post-navigation-link
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|400","bottom":"var:preset|spacing|400"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--400);margin-bottom:var(--wp--preset--spacing--400)"><!-- wp:group {"metadata":{"name":"Post navigation"},"ariaLabel":"<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>","tagName":"nav","style":{"border":{"top":{"color":"var:preset|color|outline-variant","width":"1px"}},"spacing":{"padding":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<nav class="wp-block-group" aria-label="<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>" style="border-top-color:var(--wp--preset--color--outline-variant);border-top-width:1px;padding-top:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300)"><!-- wp:post-navigation-link {"type":"previous","showTitle":true,"arrow":"arrow"} /-->

<!-- wp:post-navigation-link {"showTitle":true,"arrow":"arrow"} /--></nav>
<!-- /wp:group --></div>
<!-- /wp:group -->
