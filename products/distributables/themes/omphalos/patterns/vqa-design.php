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
 *              core/accordion is a real specimen. Its markup must be the block's
 *              canonical save() output (heading toggle button with the
 *              __toggle-title / __toggle-icon spans, role="group" / role="region",
 *              is-open on the default-open item) — a simplified-but-round-trippable
 *              version still trips editor block validation. The Interactivity
 *              directives are wired onto this static markup at render.
 *              core/more and core/nextpage are page/editor-context controls
 *              (read-more split, pagination) with no standalone-page visual —
 *              they are described in a note, not rendered (core/nextpage would
 *              actually split the page).
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
<h2 class="wp-block-heading">Separator — default / wide / dots / inset / middle-inset</h2>
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

<!-- wp:separator {"className":"is-style-divider-inset"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-divider-inset"/>
<!-- /wp:separator -->

<!-- wp:separator {"className":"is-style-divider-middle-inset"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-divider-middle-inset"/>
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

<!-- wp:heading -->
<h2 class="wp-block-heading">Accordion — open / closed, nested content</h2>
<!-- /wp:heading -->

<!-- wp:accordion -->
<div role="group" class="wp-block-accordion"><!-- wp:accordion-item {"openByDefault":true} -->
<div class="wp-block-accordion-item is-open"><!-- wp:accordion-heading -->
<h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">첫 번째 항목 (기본 열림)</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph -->
<p>패널 본문. heading 토글로 열고 닫힙니다(Interactivity API가 디렉티브를 렌더 시 wiring).</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item -->

<!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading -->
<h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">두 번째 항목 (중첩 콘텐츠)</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>중첩 리스트 항목 1</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>중첩 리스트 항목 2</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item --></div>
<!-- /wp:accordion -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Page-context controls — More / Page Break</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><code>core/more</code>(Read More)와 <code>core/nextpage</code>(Page Break)는 post/editor 컨텍스트 컨트롤입니다 — core/more는 아카이브·발췌의 "더 보기" 분할, core/nextpage는 글 페이지네이션을 만듭니다. 단일 페이지 프런트엔드에는 시각적 계약이 없어 specimen으로 두지 않습니다. 특히 core/nextpage는 페이지를 실제로 분할하므로 VQA 본문에 넣으면 안 됩니다. → editor utility / route-forward로 기록.</p>
<!-- /wp:paragraph -->

<!-- wp:separator {"className":"is-style-divider-inset"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-divider-inset"/>
<!-- /wp:separator -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Theme switcher (omphalos/theme-switcher)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>테마 소유 Design 컨트롤 블록. Auto / Light / Dark 클릭 시 <code>&lt;html data-theme&gt;</code>를 바꿔 토큰·<code>color-scheme</code>·native UI가 즉시 따라가고 cookie로 영속됩니다. 헤더/푸터 배치는 별도 UX 결정이고, 여기서는 블록 표면(inserter·canvas·front render·icon·active state·interactivity)을 검증합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:omphalos/theme-switcher /-->

<!-- wp:heading -->
<h2 class="wp-block-heading">Card usage — pattern / template compositions</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>card 자체는 <code>is-style-card-*</code> group, 내부 버튼은 버튼 contract(<code>.wp-element-button</code>)를 그대로 소비합니다. whole-card clickable은 보류이고, 이 조합들은 패턴/템플릿에서 바로 쓸 형태의 미리보기입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"is-style-card-filled","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-filled"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Content card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>제목 + 본문 + 액션. filled 카드에 버튼을 얹어 카드 안 버튼 contract 소비를 같이 검증합니다.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Action</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="#">Cancel</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-card-outlined","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-outlined"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Action card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>본문(body) 위에 supporting text, 그 아래 supplemental action row를 별도 anatomy로 둡니다. M3 카드 actions는 하단에 일관되게(여기선 우측 정렬) 배치하고 2개 이하로 둡니다 — overflow menu는 보류.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"right"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="#">Cancel</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-tonal"} -->
<div class="wp-block-button is-style-tonal"><a class="wp-block-button__link wp-element-button" href="#">Confirm</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-card-elevated","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-elevated"><!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/assets/media/image/image-placeholder-mogu-1024.webp' ); ?>" alt="Mogu placeholder"/></figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Media card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>이미지 + 제목 + 본문. elevated 카드. 이미지가 카드 폭을 넘지 않고(max-width) radius와 함께 들어가는지 확인.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-card-outlined","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-outlined"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Metadata card</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><strong>Dimensions</strong> 1024 × 768 px</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Format</strong> WebP</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><strong>Uploaded</strong> 2026-05-31</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Card grid (group layout:grid)</h3>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"grid","minimumColumnWidth":"14rem"}} -->
<div class="wp-block-group"><!-- wp:group {"className":"is-style-card-filled","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-filled"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Filled</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>grid 셀 안의 카드.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-card-elevated","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-elevated"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Elevated</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>grid 셀 안의 카드.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-card-outlined","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-outlined"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">Outlined</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>grid 셀 안의 카드.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Query card mock (post 카드 미리보기)</h3>
<!-- /wp:heading -->

<!-- wp:group {"className":"is-style-card-outlined","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card-outlined"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">예시 글 제목</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"label-small"} -->
<p class="has-label-small-font-size">2026-05-31 · 카테고리</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>발췌(excerpt) mock 텍스트. 실제 Query Loop의 post-title / post-date / post-excerpt / post-terms 조합을 카드로 감싼 형태의 미리보기 — 추후 Theme/Query VQA로 연결됩니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"label-small"} -->
<p class="has-label-small-font-size">#tag-one #tag-two</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"fontSize":"label-small"} -->
<p class="has-label-small-font-size"><strong>Deferred (M3 card anatomy, future):</strong> full-bleed media card(상단 미디어가 카드 edge까지 — padding 탈출 + overflow:clip을 실제로 행사하는 별도 contract 필요), clickable whole-card(semantic/keyboard/ripple route), draggable/checkable cards(Omphalos core-block 범위 밖). 현재 media card는 안정적인 padded 버전을 유지합니다.</p>
<!-- /wp:paragraph -->
