<?php
/**
 * Title: VQA Widgets Blocks
 * Slug: omphalos/vqa-widgets
 * Categories: omphalos
 * Inserter: true
 * Description: WordPress core "Widgets" category blocks for Phase 8 VQA — site
 *              content / control surfaces (search, social icons, tag cloud, the
 *              widget lists, calendar, RSS). Diagnostic baseline: shows how the
 *              core widget blocks render under the current Omphalos layer before
 *              any widget chrome is contracted. Most widget blocks are DYNAMIC
 *              (render_callback) so their markup is minimal/self-closing — no
 *              static save() to mismatch; core/social-links is the one static
 *              container here (its social-link children are dynamic). Markup is
 *              captured from the editor (canonical), so the social-links colour
 *              tokens keep the -- escaping the block serializer uses.
 *
 *              The list blocks render this install's real content (posts, pages,
 *              categories, comments) and are sparse on a fresh install. Tag Cloud
 *              uses the CATEGORY taxonomy (post_tag is empty on a fresh install).
 *              Social Icons run the §12 icon-button geometry: a size row
 *              (small/normal/large/huge → M3 XS/S/M/L container) plus labels /
 *              logos-only / pill. Colour policy: the M3-toned sets override the
 *              per-service brand with tonal tokens (secondary-container /
 *              on-secondary-container); logos-only / pill keep the service brand.
 *
 *              core/rss needs the network (errors offline in wp-env) — it uses a
 *              neutral public feed here; a distributable must not hard-code a
 *              personal feed URL. core/terms-query (WP 7.0 Terms List family) is a
 *              static-save container whose canonical markup must come from the
 *              editor — deferred; a hierarchical core/categories stands in for now.
 *              core/html → Prose VQA, core/shortcode → plugin/runtime (route notes).
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Core Widgets VQA</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WordPress core <strong>Widgets</strong> 카테고리 블록 baseline입니다. 대부분 dynamic(render_callback) 블록이라 마크업은 최소이고, 실제 사이트 콘텐츠(글·페이지·분류·댓글)를 렌더하므로 새 설치에선 sparse하게 보입니다 — 표면 검증이 목적입니다. search submit은 전역 <code>.wp-element-button</code> 버튼 base를 소비합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Search — button positions</h2>
<!-- /wp:heading -->

<!-- wp:search {"label":"Search","buttonText":"Search","isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","buttonText":"Search","buttonPosition":"button-inside","isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","buttonPosition":"no-button","isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","showLabel":false,"buttonText":"Search","buttonPosition":"button-only","isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","buttonText":"Search","buttonUseIcon":true,"isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","buttonText":"Search","buttonPosition":"button-inside","buttonUseIcon":true,"isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","buttonPosition":"no-button","buttonUseIcon":true,"isSearchFieldHidden":true} /-->

<!-- wp:search {"label":"Search","showLabel":false,"buttonText":"Search","buttonPosition":"button-only","buttonUseIcon":true,"isSearchFieldHidden":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Social Icons — default / logos-only / pill</h2>
<!-- /wp:heading -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(\u002d\u002dmd-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(\u002d\u002dmd-sys-color-secondary-container)","size":"has-small-icon-size","className":"is-style-default","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-small-icon-size has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(\u002d\u002dmd-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(\u002d\u002dmd-sys-color-secondary-container)","className":"is-style-default","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(\u002d\u002dmd-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(\u002d\u002dmd-sys-color-secondary-container)","size":"has-large-icon-size","className":"is-style-default","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-large-icon-size has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(\u002d\u002dmd-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(\u002d\u002dmd-sys-color-secondary-container)","size":"has-huge-icon-size","className":"is-style-default","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-huge-icon-size has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"iconColor":"on-secondary-container","iconColorValue":"var(\u002d\u002dmd-sys-color-on-secondary-container)","iconBackgroundColor":"secondary-container","iconBackgroundColorValue":"var(\u002d\u002dmd-sys-color-secondary-container)","showLabels":true,"className":"is-style-default","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-visible-labels has-icon-color has-icon-background-color is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"showLabels":true,"className":"is-style-default","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-visible-labels is-style-default"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"className":"is-style-logos-only","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links is-style-logos-only"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"showLabels":true,"className":"is-style-logos-only","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-visible-labels is-style-logos-only"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"className":"is-style-pill-shape","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links is-style-pill-shape"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"showLabels":true,"size":"has-large-icon-size","className":"is-style-pill-shape","layout":{"type":"flex","flexWrap":"wrap","orientation":"horizontal","justifyContent":"left"}} -->
<ul class="wp-block-social-links has-large-icon-size has-visible-labels is-style-pill-shape"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /-->

<!-- wp:social-link {"url":"#","service":"facebook"} /-->

<!-- wp:social-link {"url":"#","service":"instagram"} /-->

<!-- wp:social-link {"url":"#","service":"threads"} /-->

<!-- wp:social-link {"url":"#","service":"youtube"} /-->

<!-- wp:social-link {"url":"#","service":"x"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Tag Cloud — counts on / off</h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud {"showTagCounts":true} /-->

<!-- wp:tag-cloud {"taxonomy":"category","showTagCounts":true} /-->

<!-- wp:tag-cloud {"taxonomy":"category","showTagCounts":true,"className":"is-style-outline"} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Widget lists</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Archives</h3>
<!-- /wp:heading -->

<!-- wp:archives {"showPostCounts":true} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Categories List</h3>
<!-- /wp:heading -->

<!-- wp:categories /-->

<!-- wp:categories {"displayAsDropdown":true} /-->

<!-- wp:categories {"showPostCounts":true} /-->

<!-- wp:categories /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Latest Comments</h3>
<!-- /wp:heading -->

<!-- wp:latest-comments /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Page List</h3>
<!-- /wp:heading -->

<!-- wp:page-list /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Content collections — list / grid (Latest Posts / RSS)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Latest Posts / RSS는 content-collection 블록입니다. toolbar의 list/grid 옵션은 프런트 출력 클래스(<code>is-grid columns-N</code>)로 나타나며, 테마는 그 출력을 해석합니다 — <strong>list view = M3 List + Divider</strong>, <strong>grid view = filled(비-elevated) Card grid</strong>(<code>is-grid</code>에만 카드화). 둘 다 동적·데이터 의존이라 새 설치에선 sparse하고, RSS는 외부 fetch(wp-env offline 시 실패 가능)입니다. 계약: <code>docs/LIST-GRID-ROUTE.md</code>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Latest Posts — List view</h3>
<!-- /wp:heading -->

<!-- wp:latest-posts {"displayPostContent":true,"displayAuthor":true,"displayPostDate":true} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Latest Posts — Grid view</h3>
<!-- /wp:heading -->

<!-- wp:latest-posts {"postLayout":"grid","columns":3,"displayPostContent":true,"displayAuthor":true,"displayPostDate":true} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">RSS — List view</h3>
<!-- /wp:heading -->

<!-- wp:rss {"feedURL":"https://wordpress.org/news/feed/","displayExcerpt":true,"displayAuthor":true,"displayDate":true} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">RSS — Grid view</h3>
<!-- /wp:heading -->

<!-- wp:rss {"blockLayout":"grid","columns":2,"feedURL":"https://wordpress.org/news/feed/","displayExcerpt":true,"displayAuthor":true,"displayDate":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Calendar</h2>
<!-- /wp:heading -->

<!-- wp:calendar /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Terms List (core/terms-query) — deferred</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WP 7.0 Terms List는 <code>core/terms-query</code> + <code>core/term-template</code> + <code>core/term-name</code>/<code>core/term-count</code> 구조의 static-save 컨테이너입니다. canonical 마크업을 에디터에서 확보한 뒤 specimen으로 추가합니다 — accordion 교훈대로 손 추정 금지. 그동안 계층형 <code>core/categories</code>를 terms-list stand-in으로 둡니다.</p>
<!-- /wp:paragraph -->

<!-- wp:categories {"showHierarchy":true,"showPostCounts":true,"showEmpty":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Routed / gap</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><code>core/html</code>(Custom HTML)는 raw HTML 표면이라 Prose VQA / custom-HTML baseline 쪽 — Widgets에는 route note만. <code>core/shortcode</code>는 plugin/runtime 출력이라 theme contract 대상 아님. <code>core/rss</code>는 위 <strong>Content collections</strong> 섹션에서 list/grid specimen으로 다룹니다(외부 fetch 의존 — 중립 공개 피드, 개인 피드 URL 금지).</p>
<!-- /wp:paragraph -->
