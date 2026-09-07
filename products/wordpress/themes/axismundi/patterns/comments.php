<?php
/**
 * Title: Comments (bubble thread)
 * Slug: axismundi/comments
 * Description: Comments area with comments list, pagination, and comment form.
 * Categories: text
 * Block Types: core/comments
 *
 * M3 comment structure (reverse-direction bridge: core output -> reset -> M3 map).
 * Each comment is a 32px leading avatar plus a single filled card (surface-container)
 * holding the linked author name + content, with the date / edit / reply meta row
 * below it. The row is a non-stacking Columns block so the avatar leads on mobile.
 * Block-level spacing/typography lives here; list gaps (sibling/nested inset), meta
 * link affordance, the thread connector, and the reply UI are owned by
 * blocks.comments.css + comment-thread-connectors.js. .ax-comments-thread /
 * .ax-comment-item are the CSS/JS hooks.
 *
 * @package Axismundi
 */

?>
<!-- wp:comments {"metadata":{"categories":["text"],"patternName":"axismundi/comments","name":"Comments (bubble thread)"},"className":"ax-comments-thread"} -->
<div class="wp-block-comments ax-comments-thread"><!-- wp:comments-title {"showCommentsCount":false} /-->

<!-- wp:comment-template -->
<!-- wp:columns {"isStackedOnMobile":false,"className":"ax-comment-item","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|125"},"margin":{"top":"var:preset|spacing|125","bottom":"var:preset|spacing|125"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile ax-comment-item" style="margin-top:var(--wp--preset--spacing--125);margin-bottom:var(--wp--preset--spacing--125)"><!-- wp:column {"width":"32px"} -->
<div class="wp-block-column" style="flex-basis:32px"><!-- wp:avatar {"size":32,"style":{"border":{"radius":"20px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"className":"ax-comment-item__body"} -->
<div class="wp-block-column ax-comment-item__body"><!-- wp:group {"className":"ax-comment-card is-style-card-filled","style":{"spacing":{"padding":{"right":"var:preset|spacing|175","left":"var:preset|spacing|175","top":"var:preset|spacing|100","bottom":"var:preset|spacing|100"},"blockGap":"var:preset|spacing|200"},"elements":{"link":{"color":{"text":"var:preset|color|on-surface"}}}},"backgroundColor":"surface-container","textColor":"on-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group ax-comment-card is-style-card-filled has-on-surface-color has-surface-container-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--100);padding-right:var(--wp--preset--spacing--175);padding-bottom:var(--wp--preset--spacing--100);padding-left:var(--wp--preset--spacing--175)"><!-- wp:comment-author-name {"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"500"},"elements":{"link":{"color":{"text":"var:preset|color|on-surface-variant"}}}},"textColor":"on-surface-variant","fontSize":"body-medium"} /-->

<!-- wp:comment-content {"fontSize":"body-medium"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"ax-comment-meta","style":{"spacing":{"margin":{"top":"var:preset|spacing|50","bottom":"0px"},"padding":{"left":"var:preset|spacing|200","right":"var:preset|spacing|200"},"blockGap":"var:preset|spacing|100"},"elements":{"link":{"color":{"text":"var:preset|color|on-surface-variant"}}}},"textColor":"on-surface-variant","fontSize":"body-small","layout":{"type":"flex","justifyContent":"left"}} -->
<div class="wp-block-group ax-comment-meta has-on-surface-variant-color has-text-color has-link-color has-body-small-font-size" style="margin-top:var(--wp--preset--spacing--50);margin-bottom:0px;padding-right:var(--wp--preset--spacing--200);padding-left:var(--wp--preset--spacing--200)"><!-- wp:comment-date /-->

<!-- wp:comment-edit-link /-->

<!-- wp:comment-reply-link /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:comment-template -->

<!-- wp:comments-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:comments-pagination-previous /-->

<!-- wp:comments-pagination-numbers /-->

<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->

<!-- wp:post-comments-form {"style":{"typography":{"textAlign":"left"}}} /--></div>
<!-- /wp:comments -->
