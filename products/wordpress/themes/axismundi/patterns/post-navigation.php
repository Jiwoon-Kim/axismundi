<?php
/**
 * Title: Post navigation
 * Slug: axismundi/post-navigation
 * Categories: text
 * Description: Previous and up-next posts as two Material 3 "Up Next" cards.
 * Block Types: core/post-navigation-link
 *
 * Maps the M3 "previous and up next articles" card-set (m3.material.io): two cards
 * side by side. Each core/post-navigation-link IS the card — its surface (background,
 * radius, padding), the full-card click target (an <a>::before overlay so the whole
 * card navigates, matching M3 where the card is one <a>), the overline-label +
 * headline-title rows, the leading/trailing arrow, and the hover/focus state all live
 * in theme.json styles.blocks.core/post-navigation-link. They are editable in Site
 * Editor > Styles > Blocks > Post Navigation Link and apply wherever the block is
 * used. This pattern owns only the two-up layout; the arrow type (none/arrow/chevron)
 * and text alignment stay core editor choices. On the first / last post the adjacent
 * link renders nothing, leaving its column an invisible empty slot (M3 keeps the lone
 * card on its side). Labels go through esc_attr_e.
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"nav","metadata":{"patternName":"axismundi/post-navigation","name":"Post navigation","description":"Previous and up-next posts as two Material 3 \u0022Up Next\u0022 cards.","categories":["text"]},"className":"ax-post-nav","style":{"spacing":{"margin":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"default"},"ariaLabel":"<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>"} -->
<nav aria-label="<?php esc_attr_e( 'Post navigation', 'axismundi' ); ?>" class="wp-block-group ax-post-nav" style="margin-top:var(--wp--preset--spacing--600);margin-bottom:var(--wp--preset--spacing--600)"><!-- wp:columns {"verticalAlignment":null,"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|100","left":"var:preset|spacing|100"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"stretch"} -->
<div class="wp-block-column is-vertically-aligned-stretch"><!-- wp:post-navigation-link {"type":"previous","label":"<?php esc_attr_e( 'Previous', 'axismundi' ); ?>","showTitle":true,"linkLabel":true,"arrow":"arrow"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch"} -->
<div class="wp-block-column is-vertically-aligned-stretch"><!-- wp:post-navigation-link {"type":"next","textAlign":"right","label":"<?php esc_attr_e( 'Up next', 'axismundi' ); ?>","showTitle":true,"linkLabel":true,"arrow":"arrow"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></nav>
<!-- /wp:group -->
