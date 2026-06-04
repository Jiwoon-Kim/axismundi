<?php
/**
 * Title: VQA Theme Comments
 * Slug: omphalos/vqa-theme-comments
 * Categories: omphalos
 * Inserter: true
 * Description: Phase 2 of the Theme-block VQA (THEME-VQA-ROUTE §3) — the COMMENTS block
 *              family. Comments are CONTEXT-dependent: core/comments renders the CURRENT
 *              singular object's approved comments, so this pattern is placed on a PAGE
 *              that has comments OPEN and seeded comments (seed-vqa-theme-comments.php).
 *              Real-world comments live in the single-post TEMPLATE; this in-content
 *              specimen is for OBSERVATION + the §21 global token binding (comments are
 *              chrome, bound by block class, so the binding applies in both contexts).
 *
 *              core/comments is a static-save container with inner blocks (title /
 *              comment-template / pagination / form). comment-template renders the
 *              per-comment slots (avatar / author-name / date / content / reply / edit).
 *              post-comments (legacy) is NOTED, not specimened (§5 caveat). Comment-form
 *              INPUT surfaces are a deferred form/input lane; the submit is already M3.
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Comments VQA — baseline (Phase 2)</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>core/comments 패밀리 관찰용. 이 페이지는 댓글 <code>open</code> + seed된 댓글을 가지므로 <code>core/comments</code>가 실제 댓글을 렌더한다. 실사용 댓글은 single-post <strong>템플릿</strong> context이며(§11), 여기 in-content specimen은 관찰 + §21 global 바인딩 확인용이다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">1. Post comment meta (count / link)</h2>
<!-- /wp:heading -->

<!-- wp:post-comments-count /-->

<!-- wp:post-comments-link /-->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">2. Comments block (title · template · pagination · form)</h2>
<!-- /wp:heading -->

<!-- wp:comments -->
<div class="wp-block-comments">
<!-- wp:comments-title {"showPostTitle":false} /-->

<!-- wp:comment-template -->
<!-- wp:avatar {"size":40} /-->
<!-- wp:comment-author-name /-->
<!-- wp:comment-date /-->
<!-- wp:comment-content /-->
<!-- wp:comment-reply-link /-->
<!-- wp:comment-edit-link /-->
<!-- /wp:comment-template -->

<!-- wp:comments-pagination -->
<!-- wp:comments-pagination-previous /-->
<!-- wp:comments-pagination-numbers /-->
<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->

<!-- wp:post-comments-form /-->
</div>
<!-- /wp:comments -->
