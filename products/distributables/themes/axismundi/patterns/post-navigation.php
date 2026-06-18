<?php
/**
 * Title: Post navigation
 * Slug: axismundi/post-navigation
 * Categories: text
 * Description: Previous and up-next posts as two Material 3 "Up Next" cards.
 * Block Types: core/post-navigation-link
 *
 * Maps the M3 "previous and up next articles" card-set (m3.material.io): two cards
 * side by side. The CARD is the Column (is-style-card-filled + surface-container-low
 * background), so the editor owns its background, text/link colour, padding, radius,
 * and the link's text alignment (the next card defaults to right). Each
 * core/post-navigation-link inside owns only the link, title, label overline, and
 * the decorative arrow (none/arrow/chevron — a core editor choice). The arrow type
 * is set to a plain arrow here; patterns.post-navigation.css owns only the
 * arrow/label/title layout and the hover/focus state. Labels go through esc_attr_e.
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"nav","ariaLabel":"<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>","className":"ax-post-nav","style":{"spacing":{"margin":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"default"}} -->
<nav class="wp-block-group ax-post-nav" aria-label="<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>" style="margin-top:var(--wp--preset--spacing--600);margin-bottom:var(--wp--preset--spacing--600)"><!-- wp:columns {"className":"ax-post-nav__set","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|100","left":"var:preset|spacing|100"}}}} -->
<div class="wp-block-columns ax-post-nav__set"><!-- wp:column {"backgroundColor":"surface-container-low","className":"ax-post-nav__card is-style-card-filled","style":{"spacing":{"padding":{"top":"var:preset|spacing|250","bottom":"var:preset|spacing|250","left":"var:preset|spacing|300","right":"var:preset|spacing|300"}}}} -->
<div class="wp-block-column ax-post-nav__card is-style-card-filled has-surface-container-low-background-color has-background" style="padding-top:var(--wp--preset--spacing--250);padding-bottom:var(--wp--preset--spacing--250);padding-left:var(--wp--preset--spacing--300);padding-right:var(--wp--preset--spacing--300)"><!-- wp:post-navigation-link {"type":"previous","label":"<?php esc_attr_e( 'Previous', 'axismundi' ); ?>","arrow":"arrow","showTitle":true,"linkLabel":true} /--></div>
<!-- /wp:column -->

<!-- wp:column {"backgroundColor":"surface-container-low","className":"ax-post-nav__card is-style-card-filled","style":{"spacing":{"padding":{"top":"var:preset|spacing|250","bottom":"var:preset|spacing|250","left":"var:preset|spacing|300","right":"var:preset|spacing|300"}}}} -->
<div class="wp-block-column ax-post-nav__card is-style-card-filled has-surface-container-low-background-color has-background" style="padding-top:var(--wp--preset--spacing--250);padding-bottom:var(--wp--preset--spacing--250);padding-left:var(--wp--preset--spacing--300);padding-right:var(--wp--preset--spacing--300)"><!-- wp:post-navigation-link {"textAlign":"right","label":"<?php esc_attr_e( 'Up next', 'axismundi' ); ?>","arrow":"arrow","showTitle":true,"linkLabel":true} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></nav>
<!-- /wp:group -->
