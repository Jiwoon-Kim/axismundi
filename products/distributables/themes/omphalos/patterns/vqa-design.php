<?php
/**
 * Title: VQA Design Blocks
 * Slug: omphalos/vqa-design
 * Categories: omphalos
 * Inserter: true
 * Description: WordPress core "Design" category blocks for Phase 8 VQA — the
 *              structural / layout group (buttons, columns, group + Row/Stack/Grid
 *              variations, separator, spacer). Markup matches the editor's
 *              canonical save output so the specimens never trip "unexpected or
 *              invalid content". This is a diagnostic baseline: it shows how core
 *              Design blocks render under the current Omphalos layer (M3 color
 *              palette + spacing presets + theme.json layout) BEFORE any
 *              Design-block chrome is contracted in blocks.css.
 *
 *              core/button is intentionally shown with core defaults only. Per the
 *              repo's core/button rule, an M3 button visual bridge is a semantic
 *              decision (navigation vs action) that must be routed before styling;
 *              this page surfaces the default so that decision can be made, but it
 *              does not yet apply an M3 button contract.
 *
 *              core/more and core/nextpage are omitted: they are post-context
 *              controls (read-more split, pagination) that render nothing on a
 *              standalone page, so a static VQA page cannot exercise them.
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Core Design VQA</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>이 페이지는 WordPress core의 <strong>Design</strong> 카테고리 블록(구조·레이아웃)만 배치한 baseline입니다. 현재 Omphalos는 M3 color palette, spacing preset, theme.json layout까지만 적용된 상태이고, Design 블록 전용 chrome은 아직 <code>blocks.css</code>에 없습니다. 이 화면은 코어 기본 렌더링이 어디까지 감당하는지 확인하는 진단 기준점입니다. <code>core/button</code>은 semantic route 확정 후 M3 Button contract가 적용됩니다(Filled / Tonal / Elevated / Outlined / Text). base는 <code>.wp-element-button</code>(전역)에 앉아 file/search submit까지 일관됩니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Buttons — M3 variants</h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Filled</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-tonal"} -->
<div class="wp-block-button is-style-tonal"><a class="wp-block-button__link wp-element-button" href="#">Tonal</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-elevated"} -->
<div class="wp-block-button is-style-elevated"><a class="wp-block-button__link wp-element-button" href="#">Elevated</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Outlined</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="#">Text</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Columns — 3 columns</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>첫 번째 열. 텍스트가 열 너비에 맞춰 흐릅니다.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>두 번째 열. 모바일에서는 세로로 쌓입니다.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>세 번째 열. 균등 분배가 기본값입니다.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Group — background + padding</h2>
<!-- /wp:heading -->

<!-- wp:group {"backgroundColor":"surface-container","textColor":"on-surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|lg","right":"var:preset|spacing|lg","bottom":"var:preset|spacing|lg","left":"var:preset|spacing|lg"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-on-surface-color has-surface-container-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--lg);padding-right:var(--wp--preset--spacing--lg);padding-bottom:var(--wp--preset--spacing--lg);padding-left:var(--wp--preset--spacing--lg)"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Surface container group</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>M3 surface-container 배경 위의 group입니다. 배경색은 color palette 토큰, padding은 spacing preset에서 옵니다.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Group — Card variations (M3)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Bare group은 카드가 아닙니다(위 layout 컨테이너). 아래 세 style variation만 M3 Card surface로 간주합니다 — filled / elevated / outlined. 콘텐츠 카드이며, whole-card action/navigation은 별도 마크업·semantic 결정 사항입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"is-style-card-filled","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-filled"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Filled card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>surface-container-highest 배경. 그림자/외곽선 없이 채워진 면으로 구분되는 가장 낮은 강조의 카드입니다.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-card-elevated","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-elevated"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Elevated card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>surface-container-low 배경 + level-1 elevation 그림자. 다크 모드에서는 M3 규칙대로 그림자 대신 surface tint로 분리됩니다.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-card-outlined","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-outlined"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Outlined card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>surface 배경 + outline-variant 1px 외곽선(offset -1px). 그림자 없이 윤곽선으로만 구분되는 카드입니다.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Row — horizontal flex</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Row 항목 A</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Row 항목 B</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Row 항목 C</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Stack — vertical flex</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Stack 항목 1</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Stack 항목 2</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Grid — 3 column grid</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"grid","columnCount":3}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Grid 셀 1</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Grid 셀 2</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Grid 셀 3</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Grid 셀 4</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Separator — default / wide / dots</h2>
<!-- /wp:heading -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:separator {"className":"is-style-wide"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-wide"/>
<!-- /wp:separator -->

<!-- wp:separator {"className":"is-style-dots"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-dots"/>
<!-- /wp:separator -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Spacer — 100px</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>아래 spacer(100px) 전.</p>
<!-- /wp:paragraph -->

<!-- wp:spacer -->
<div style="height:100px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:paragraph -->
<p>Spacer 후.</p>
<!-- /wp:paragraph -->
