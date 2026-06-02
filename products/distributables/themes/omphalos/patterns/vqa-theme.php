<?php
/**
 * Title: VQA Theme Blocks
 * Slug: omphalos/vqa-theme
 * Categories: omphalos
 * Inserter: true
 * Description: WordPress core "Theme" category blocks for Phase 8 VQA (Phase 1
 *              baseline) — site identity, navigation, the Query Loop + post-context
 *              blocks, post navigation, and template infrastructure. Markup uses the
 *              REGISTERED block names (the WP 7.0 "Title / Date / …" renames are
 *              editor labels only; the names stay core/post-*). Many of these blocks
 *              are CONTEXT-dependent: the Query Loop supplies its own post context,
 *              so the post-meta blocks render inside `core/post-template`; blocks that
 *              need a SINGLE-post context (post-navigation-link) reference this PAGE
 *              and may render empty — that mismatch is part of the observation.
 *
 *              core/navigation is a static-save / canonical-risk block: this is a
 *              MINIMAL inline menu (home-link + one custom link + a submenu) — verify
 *              editor validity before trusting it; rich menus should be captured from
 *              the editor. Comments family, term/terms-query, and the query-title
 *              archive/search variations are SEPARATE VQA pages (see THEME-VQA-ROUTE
 *              §3). Dynamic blocks render this install's seeded posts/terms/comments.
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Core Theme VQA — baseline</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Theme 블록 등록/렌더/context/validity 관찰용 baseline. CSS는 아직 없음. 마크업은 실제 등록명(core/post-*) 사용. post-meta 블록은 Query Loop의 post context 안에서 렌더된다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Site identity</h2>
<!-- /wp:heading -->

<!-- wp:site-logo {"width":48} /-->

<!-- wp:site-title /-->

<!-- wp:site-tagline /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Navigation</h2>
<!-- /wp:heading -->

<!-- wp:navigation {"overlayMenu":"never"} -->
<!-- wp:home-link {"label":"Home"} /-->
<!-- wp:navigation-link {"label":"About","url":"#about","kind":"custom"} /-->
<!-- wp:navigation-submenu {"label":"More","url":"#","kind":"custom"} -->
<!-- wp:navigation-link {"label":"Sub one","url":"#sub-one","kind":"custom"} /-->
<!-- wp:navigation-link {"label":"Sub two","url":"#sub-two","kind":"custom"} /-->
<!-- /wp:navigation-submenu -->
<!-- /wp:navigation -->

<!-- wp:breadcrumbs /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Query Loop + post context</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":10,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false}} -->
<div class="wp-block-query">
<!-- wp:post-template -->
<!-- wp:post-featured-image {"isLink":true} /-->
<!-- wp:post-title {"isLink":true} /-->
<!-- wp:post-author {"showAvatar":true,"showBio":false,"byline":"by"} /-->
<!-- wp:post-author-name /-->
<!-- wp:avatar {"size":40} /-->
<!-- wp:post-date /-->
<!-- wp:post-terms {"term":"category"} /-->
<!-- wp:post-terms {"term":"post_tag"} /-->
<!-- wp:post-excerpt /-->
<!-- wp:post-time-to-read /-->
<!-- wp:read-more /-->
<!-- wp:post-comments-count /-->
<!-- wp:post-comments-link /-->
<!-- wp:post-author-biography /-->
<!-- /wp:post-template -->
<!-- wp:query-pagination -->
<!-- wp:query-pagination-previous /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->
<!-- wp:query-total /-->
</div>
<!-- /wp:query -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Post navigation (single-post context)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>post-navigation-link은 single-post context가 필요하다. 이 페이지(page)에서는 prev/next가 비어 보일 수 있음 — context mismatch 관찰용.</p>
<!-- /wp:paragraph -->

<!-- wp:post-navigation-link {"type":"previous"} /-->

<!-- wp:post-navigation-link {"type":"next"} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Infrastructure</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>core/template-part는 실제 header/footer 템플릿 설계 후 관찰(여기서 렌더하면 전체 헤더를 끌어옴). core/loginout는 dynamic 로그인/아웃 링크.</p>
<!-- /wp:paragraph -->

<!-- wp:loginout /-->
