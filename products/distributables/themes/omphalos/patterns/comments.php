<?php
/**
 * Title: Omphalos Comments (feed-item)
 * Slug: omphalos/comments
 * Inserter: false
 * Description: core/comments authored as a MICROBLOG FEED ITEM (THEME-VQA-ROUTE §11.1) rather
 *              than the legacy blog-comment stack. Structure (grounded in Mastodon
 *              status.jsx / Misskey MkNote.vue): per-comment = LEADING avatar + MAIN column
 *              { meta row: author · date → body → action row: reply / edit }; nested replies =
 *              core thread chain. The PATTERN owns the structure (block order + Group/Row);
 *              CSS (blocks.css §22) only does the flex glue (grow / shrink / gaps). Referenced
 *              by templates/page-with-comments.html; replaces the TT5 comments pattern.
 *
 * @package Omphalos
 */
?>
<!-- wp:comments -->
<div class="wp-block-comments">
<!-- wp:comments-title /-->

<!-- wp:comment-template -->
<!-- wp:group {"className":"omph-comment-item","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group omph-comment-item">
<!-- wp:avatar {"size":40} /-->

<!-- wp:group {"className":"omph-comment-main","layout":{"type":"default"}} -->
<div class="wp-block-group omph-comment-main">
<!-- wp:group {"className":"omph-comment-meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group omph-comment-meta">
<!-- wp:comment-author-name /-->
<!-- wp:comment-date /-->
</div>
<!-- /wp:group -->

<!-- wp:comment-content /-->

<!-- wp:group {"className":"omph-comment-actions","layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group omph-comment-actions">
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
