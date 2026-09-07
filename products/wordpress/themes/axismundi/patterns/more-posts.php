<?php
/**
 * Title: More posts
 * Slug: axismundi/more-posts
 * Description: Displays a list of recent posts with title and date.
 * Categories: query
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|400","bottom":"var:preset|spacing|400"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--400);padding-bottom:var(--wp--preset--spacing--400)"><!-- wp:heading {"align":"wide","style":{"typography":{"textTransform":"uppercase","fontStyle":"normal","fontWeight":"700","letterSpacing":"1.4px"}},"fontSize":"label-medium"} -->
<h2 class="wp-block-heading alignwide has-label-medium-font-size" style="font-style:normal;font-weight:700;letter-spacing:1.4px;text-transform:uppercase"><?php esc_html_e( 'More posts', 'axismundi' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:query {"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"exclude","inherit":false,"taxQuery":null,"parents":[]},"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"align":"full","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"default"}} -->
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"}},"border":{"bottom":{"color":"var:preset|color|outline-variant","width":"1px"}}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center","justifyContent":"space-between"}} -->
<div class="wp-block-group alignfull" style="border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px;padding-top:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300)"><!-- wp:post-title {"level":3,"isLink":true,"fontSize":"title-medium"} /-->

<!-- wp:post-date {"textAlign":"right","isLink":true,"fontSize":"body-small"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->
