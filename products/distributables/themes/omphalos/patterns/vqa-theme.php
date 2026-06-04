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

// Reference the seeded wp_navigation menu by id so the nav specimen renders an
// ACTUAL menu (a bare core/navigation falls back to the page list). Computed at
// pattern-include time; falls back to a bare nav if the menu has not been seeded.
$omph_nav     = get_posts( array( 'post_type' => 'wp_navigation', 'name' => 'vqa-theme-nav', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
$omph_nav_ref = $omph_nav ? (int) $omph_nav[0] : 0;
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

<!-- wp:paragraph -->
<p>VQA sitemap 메뉴 (2~4 depth nested). 좌측 정렬(justifyContent:left) — 깊은 submenu에서 우측 정렬은 시각적으로 불규칙해짐. <strong>주의</strong>: content-context specimen은 DOM 관찰용이며 interaction(hover-path)은 header/template context와 다를 수 있다(실제 헤더 = TT5 header 패턴 nav).</p>
<!-- /wp:paragraph -->

<?php if ( $omph_nav_ref ) : ?>
<!-- wp:navigation {"ref":<?php echo $omph_nav_ref; ?>,"overlayMenu":"mobile","layout":{"type":"flex","justifyContent":"left"}} /-->
<?php else : ?>
<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","justifyContent":"left"}} /-->
<?php endif; ?>

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">2b. Overlay Visibility specimens (never / mobile / always)</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>같은 메뉴, <code>overlayMenu</code>만 다름. <code>always</code>는 desktop에서도 hamburger overlay → desktop submenu surface 규칙(@media min-width:600px)이 새는지 진단 대상.</p>
<!-- /wp:paragraph -->

<?php if ( $omph_nav_ref ) : ?>
<!-- wp:paragraph --><p><strong>never</strong> (overlay off — always inline):</p><!-- /wp:paragraph -->
<!-- wp:navigation {"ref":<?php echo $omph_nav_ref; ?>,"overlayMenu":"never","layout":{"type":"flex","justifyContent":"left"}} /-->
<!-- wp:paragraph --><p><strong>always</strong> (overlay at every width):</p><!-- /wp:paragraph -->
<!-- wp:navigation {"ref":<?php echo $omph_nav_ref; ?>,"overlayMenu":"always","layout":{"type":"flex","justifyContent":"left"}} /-->
<?php endif; ?>

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">2c. Orientation / Submenu-visibility specimens (nav-rail-like — Menu skin must NOT apply)</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Vertical orientation(<code>is-vertical</code>) / <code>submenuVisibility:always</code>(<code>.open-always</code>)는 floating dropdown이 아니라 nav-rail/drawer nested section이다. §19 Menu 측정값(surface, 16dp, elevation, first/last corner)이 여기로 새면 안 된다 — gate 제외 검증용. <strong>주의</strong>: <code>submenuVisibility:always</code>는 <em>vertical</em>에서만 정합(가로 nav는 submenu가 absolute dropdown이라 always-open이 성립 안 됨 → 에디터가 horizontal+always를 안 반영). 그래서 두 specimen 모두 vertical nav-rail-like로 둔다.</p>
<!-- /wp:paragraph -->

<?php if ( $omph_nav_ref ) : ?>
<!-- wp:paragraph --><p><strong>vertical</strong> (orientation vertical, overlay off, submenu open-on-click):</p><!-- /wp:paragraph -->
<!-- wp:navigation {"ref":<?php echo $omph_nav_ref; ?>,"submenuVisibility":"click","overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"}} /-->
<!-- wp:paragraph --><p><strong>submenu always</strong> (vertical, submenuVisibility always = always-expanded tree → §20 core-leaning baseline skin: capsule affordance only, NO rail geometry):</p><!-- /wp:paragraph -->
<!-- wp:navigation {"ref":<?php echo $omph_nav_ref; ?>,"showSubmenuIcon":false,"submenuVisibility":"always","overlayMenu":"never","layout":{"type":"flex","justifyContent":"left","orientation":"vertical","flexWrap":"wrap"}} /-->
<!-- wp:paragraph --><p><strong>expanded rail OPT-IN</strong> (<code>.is-style-expanded-rail</code> → §21 component: width clamp 220-360, full-width 56dp rows, label-large, circular active indicator; opt-in only, never auto-applied):</p><!-- /wp:paragraph -->
<!-- wp:navigation {"ref":<?php echo $omph_nav_ref; ?>,"className":"is-style-expanded-rail","showSubmenuIcon":false,"submenuVisibility":"always","overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"}} /-->
<?php endif; ?>

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
