<?php
/**
 * Title: 404
 * Slug: axismundi/hidden-404
 * Inserter: no
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"404"},"align":"full","style":{"dimensions":{"minHeight":"70vh"},"spacing":{"padding":{"top":"var:preset|spacing|800","right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center","verticalAlignment":"center"}} -->
<main class="wp-block-group alignfull" style="min-height:70vh;padding-top:var(--wp--preset--spacing--800);padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"layout":{"type":"constrained","contentSize":"480px"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"style":{"typography":{"textAlign":"center","fontStyle":"normal","fontWeight":"600","lineHeight":"1"}},"textColor":"primary","fontSize":"display-large"} -->
<p class="has-text-align-center has-primary-color has-text-color has-display-large-font-size" style="font-style:normal;font-weight:600;line-height:1">404</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"center"}},"fontSize":"headline-medium"} -->
<h1 class="wp-block-heading has-text-align-center has-headline-medium-font-size"><?php echo esc_html_x( 'Page not found', '404 error message', 'axismundi' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"textColor":"on-surface-variant","fontSize":"body-large"} -->
<p class="has-text-align-center has-on-surface-variant-color has-text-color has-body-large-font-size"><?php echo esc_html_x( 'The page may have moved, changed its name, or no longer exists. Search the site or return home to continue exploring.', '404 error message', 'axismundi' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|300"}}},"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--300)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html_x( 'Return home', '404 navigation link', 'axismundi' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:search {"label":"<?php echo esc_html_x( 'Search', 'Search form label.', 'axismundi' ); ?>","showLabel":false,"placeholder":"<?php echo esc_attr_x( 'Search this site', 'Search input field placeholder text.', 'axismundi' ); ?>","buttonText":"<?php echo esc_attr_x( 'Search', 'Button text. Verb.', 'axismundi' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|400"}}}} /--></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
