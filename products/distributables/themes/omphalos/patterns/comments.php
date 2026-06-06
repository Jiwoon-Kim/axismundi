<?php
/**
 * Title: Omphalos Comments (bubble thread)
 * Slug: omphalos/comments
 * Inserter: false
 * Description: core/comments authored as a FACEBOOK-style comment BUBBLE thread (THEME-VQA-ROUTE
 *              §11.1) rather than a microblog NOTE (the note model = custom post type /
 *              ActivityPub object renderer, too rich for WP core comments). Structure: per-comment
 *              = LEADING avatar + MAIN column { rounded BUBBLE (author top + comment text) →
 *              action STRIP (date · reply · edit, below the bubble) }; nested replies = core
 *              thread chain + a connector rail. The PATTERN owns the structure; CSS (blocks.css
 *              §22) does the bubble surface + strip + rail. Referenced by
 *              templates/page-with-comments.html; replaces the TT5 comments pattern.
 *
 * @package Omphalos
 */
?>
<!-- wp:comments -->
<div class="wp-block-comments">
<!-- wp:comments-title /-->

<!-- wp:comment-template -->
<!-- wp:group {"className":"omph-comment-item","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"}} -->
<div class="wp-block-group omph-comment-item">
<!-- wp:avatar {"size":40} /-->

<!-- wp:group {"className":"omph-comment-main","layout":{"type":"default"}} -->
<div class="wp-block-group omph-comment-main">
<!-- wp:group {"className":"omph-comment-bubble","layout":{"type":"default"}} -->
<div class="wp-block-group omph-comment-bubble">
<!-- wp:comment-author-name /-->
<!-- wp:comment-content /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"omph-comment-actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group omph-comment-actions">
<!-- wp:comment-date /-->
<!-- wp:comment-reply-link /-->
<!-- wp:comment-edit-link /-->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
<!-- /wp:comment-template -->

<!-- wp:comments-pagination -->
<!-- wp:comments-pagination-previous /-->
<!-- wp:comments-pagination-numbers /-->
<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->

<!-- wp:post-comments-form /-->
</div>
<!-- /wp:comments -->
