<?php
/**
 * Title: Single post
 * Slug: axismundi/single-post
 * Description: Single-post article layout without a sidebar.
 * Categories: posts
 * Block Types: core/post-content
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"patternName":"axismundi/single-post","name":"Single post","description":"Single-post article layout without a sidebar.","categories":["posts"]},"align":"wide","style":{"spacing":{"padding":{"right":"var:preset|spacing|200","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignwide" style="padding-right:var(--wp--preset--spacing--200);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|150"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-featured-image {"aspectRatio":"2/1","style":{"border":{"radius":"12px"}}} /-->

<!-- wp:post-terms {"term":"category","className":"is-style-inline"} /-->

<!-- wp:post-title {"level":1,"fontSize":"display-small"} /-->

<!-- wp:group {"metadata":{"name":"Byline"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|400"},"blockGap":"var:preset|spacing|125"}},"textColor":"on-surface-variant","fontSize":"body-small","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group has-on-surface-variant-color has-text-color has-body-small-font-size" style="margin-top:var(--wp--preset--spacing--300);margin-bottom:var(--wp--preset--spacing--400)"><!-- wp:avatar {"size":32,"style":{"border":{"radius":{"topLeft":"16px","topRight":"16px","bottomLeft":"16px","bottomRight":"16px"}}}} /-->

<!-- wp:post-author-name {"isLink":true} /-->

<!-- wp:post-date {"metadata":{"bindings":{"datetime":{"source":"core/post-data","args":{"field":"date"}}}},"className":"wp-block-post-date__modified-date"} /--></div>
<!-- /wp:group -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:axismundi/toc {"variant":"disclosure","openByDefault":true,"metadata":{"categories":["text"],"patternName":"axismundi/toc-before-content","name":"Table of contents (above content)"}} /--></div>
<!-- /wp:group -->

<!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /-->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|400"}}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--400)"><!-- wp:post-terms {"term":"post_tag","className":"is-style-tags"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"nav","metadata":{"patternName":"axismundi/post-navigation","name":"Post navigation","description":"Previous and up-next posts as two Material 3 \u0022Up Next\u0022 cards.","categories":["text"]},"className":"ax-post-nav","style":{"spacing":{"margin":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"default"},"ariaLabel":"Post navigation"} -->
<nav aria-label="Post navigation" class="wp-block-group ax-post-nav" style="margin-top:var(--wp--preset--spacing--600);margin-bottom:var(--wp--preset--spacing--600)"><!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|100","left":"var:preset|spacing|100"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"verticalAlignment":"stretch"} -->
<div class="wp-block-column is-vertically-aligned-stretch"><!-- wp:post-navigation-link {"type":"previous","label":"Previous","showTitle":true,"linkLabel":true,"arrow":"arrow"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch"} -->
<div class="wp-block-column is-vertically-aligned-stretch"><!-- wp:post-navigation-link {"textAlign":"right","label":"Up next","showTitle":true,"linkLabel":true,"arrow":"arrow"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></nav>
<!-- /wp:group -->

<!-- wp:comments {"metadata":{"categories":["text"],"patternName":"axismundi/comments","name":"Comments (bubble thread)","description":"Comments area with comments list, pagination, and comment form."},"className":"ax-comments-thread"} -->
<div class="wp-block-comments ax-comments-thread"><!-- wp:comments-title {"showCommentsCount":false} /-->

<!-- wp:comment-template -->
<!-- wp:columns {"isStackedOnMobile":false,"className":"ax-comment-item","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|125"},"margin":{"top":"var:preset|spacing|125","bottom":"var:preset|spacing|125"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile ax-comment-item" style="margin-top:var(--wp--preset--spacing--125);margin-bottom:var(--wp--preset--spacing--125)"><!-- wp:column {"width":"32px"} -->
<div class="wp-block-column" style="flex-basis:32px"><!-- wp:avatar {"size":32,"style":{"border":{"radius":"20px"}}} /--></div>
<!-- /wp:column -->

<!-- wp:column {"className":"ax-comment-item__body"} -->
<div class="wp-block-column ax-comment-item__body"><!-- wp:group {"className":"ax-comment-card is-style-card-filled","style":{"spacing":{"padding":{"right":"var:preset|spacing|175","left":"var:preset|spacing|175","top":"var:preset|spacing|100","bottom":"var:preset|spacing|100"},"blockGap":"var:preset|spacing|200"},"elements":{"link":{"color":{"text":"var:preset|color|on-surface"}}}},"backgroundColor":"surface-container","textColor":"on-surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group ax-comment-card is-style-card-filled has-on-surface-color has-surface-container-background-color has-text-color has-background has-link-color" style="padding-top:var(--wp--preset--spacing--100);padding-right:var(--wp--preset--spacing--175);padding-bottom:var(--wp--preset--spacing--100);padding-left:var(--wp--preset--spacing--175)"><!-- wp:comment-author-name {"style":{"typography":{"fontStyle":"normal","fontWeight":"500"},"elements":{"link":{"color":{"text":"var:preset|color|on-surface-variant"}}}},"textColor":"on-surface-variant","fontSize":"body-medium"} /-->

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
<!-- /wp:comments --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
