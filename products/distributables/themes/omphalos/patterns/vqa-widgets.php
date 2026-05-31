<?php
/**
 * Title: VQA Widgets Blocks
 * Slug: omphalos/vqa-widgets
 * Categories: omphalos
 * Inserter: true
 * Description: WordPress core "Widgets" category blocks for Phase 8 VQA — site
 *              content / control surfaces (search, social icons, tag cloud, the
 *              widget lists, calendar). Diagnostic baseline: shows how the core
 *              widget blocks render under the current Omphalos layer before any
 *              widget chrome is contracted. Most widget blocks are DYNAMIC
 *              (render_callback) so their markup is minimal/self-closing — no
 *              static save() to mismatch; core/social-links is the one static
 *              container here (its social-link children are dynamic).
 *
 *              The list blocks render this install's real content (posts, pages,
 *              categories, comments), so they look sparse on a fresh install —
 *              that is expected; the VQA checks the surface, not the data volume.
 *
 *              Routed / deferred (not specimens): core/html (Custom HTML → Prose
 *              VQA / raw HTML), core/shortcode (plugin/runtime output), core/rss
 *              (external feed / privacy / network). core/terms-query (WP 7.0 Terms
 *              List family: terms-query + term-template + term-name/term-count) is
 *              a static-save container whose canonical markup must come from the
 *              editor — deferred to a follow-up.
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

<!-- wp:search {"label":"Search","showLabel":true,"buttonText":"Search","buttonPosition":"button-outside"} /-->

<!-- wp:search {"label":"Search","showLabel":true,"buttonText":"Search","buttonPosition":"button-inside"} /-->

<!-- wp:search {"label":"Search","showLabel":true,"buttonPosition":"no-button"} /-->

<!-- wp:search {"label":"Search","showLabel":false,"buttonText":"Search","buttonPosition":"button-only"} /-->

<!-- wp:search {"label":"Search","showLabel":false,"buttonPosition":"button-only","buttonUseIcon":true} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Social Icons — default / logos-only / pill</h2>
<!-- /wp:heading -->

<!-- wp:social-links -->
<ul class="wp-block-social-links"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"className":"is-style-logos-only"} -->
<ul class="wp-block-social-links is-style-logos-only"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:social-links {"className":"is-style-pill-shape"} -->
<ul class="wp-block-social-links is-style-pill-shape"><!-- wp:social-link {"url":"#","service":"wordpress"} /-->

<!-- wp:social-link {"url":"#","service":"github"} /-->

<!-- wp:social-link {"url":"#","service":"mastodon"} /--></ul>
<!-- /wp:social-links -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Tag Cloud — counts on / off</h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud {"showTagCounts":true} /-->

<!-- wp:tag-cloud {"showTagCounts":false} /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Widget lists</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Archives</h3>
<!-- /wp:heading -->

<!-- wp:archives {"showPostCounts":true} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Categories</h3>
<!-- /wp:heading -->

<!-- wp:categories {"showPostCounts":true} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Latest Posts</h3>
<!-- /wp:heading -->

<!-- wp:latest-posts {"displayPostDate":true} /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Latest Comments</h3>
<!-- /wp:heading -->

<!-- wp:latest-comments /-->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Page List</h3>
<!-- /wp:heading -->

<!-- wp:page-list /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Calendar</h2>
<!-- /wp:heading -->

<!-- wp:calendar /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Terms List (core/terms-query) — deferred</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>WP 7.0 Terms List는 <code>core/terms-query</code> + <code>core/term-template</code> + <code>core/term-name</code>/<code>core/term-count</code> 구조의 static-save 컨테이너입니다. canonical 마크업을 에디터에서 확보한 뒤 specimen으로 추가합니다 — accordion 교훈대로 손 추정 금지.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Routed / gap (not specimens)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><code>core/html</code>(Custom HTML)는 raw HTML 표면이라 Prose VQA / custom-HTML baseline 쪽 — Widgets에는 route note만. <code>core/shortcode</code>는 plugin/runtime 출력이라 theme contract 대상 아님. <code>core/rss</code>는 외부 feed/privacy/network 의존(wp-env/CI 외부성)이라 기본 VQA에는 note로 둡니다.</p>
<!-- /wp:paragraph -->
