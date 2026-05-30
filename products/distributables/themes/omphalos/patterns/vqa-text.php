<?php
/**
 * Title: VQA Text Blocks
 * Slug: omphalos/vqa-text
 * Categories: omphalos
 * Inserter: true
 * Description: Pure WordPress core text blocks for Phase 8 baseline VQA. Markup
 *              matches the editor's canonical save output (validated in wp-env)
 *              so the blocks never trip "unexpected or invalid content".
 *
 *              core/footnotes is a dynamic block: it renders from the post's
 *              `footnotes` meta keyed by the inline <sup data-fn="UUID"> refs
 *              below. The two UUIDs here must be paired with matching meta on
 *              the page that embeds this pattern (seeded by scripts/seed.ps1);
 *              without that meta the footnotes list renders empty by design.
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Core Text VQA</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>이 페이지는 <strong>core text blocks</strong>만 배치한 baseline입니다. 현재 Omphalos는 <code>prose.css</code>만 적용된 상태이며, 아직 <code>blocks.css</code>는 없습니다. 따라서 이 화면은 Phase 8 진입 전, WordPress core 렌더링과 FSE prose surface가 어디까지 감당하는지 확인하기 위한 기준점입니다.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Mixed Korean and English text should wrap naturally. Long identifiers like <code>--md-sys-typescale-body-large-line-height</code> must not force the page wider than the viewport, while regular Korean phrases keep readable word boundaries.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Headings</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">h1 — headline-large</h1>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">h2 — headline-medium</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">h3 — headline-small</h3>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">h4 — title-large</h4>
<!-- /wp:heading -->

<!-- wp:heading {"level":5} -->
<h5 class="wp-block-heading">h5 — title-medium</h5>
<!-- /wp:heading -->

<!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading">h6 — title-small, not uppercase</h6>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Paragraph, inline elements, and links</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Paragraph text includes <a href="#core-text-vqa-link">a regular link</a>, <strong>strong emphasis</strong>, <em>emphasis</em>, <code>inline code</code>, <mark>mark</mark>, <del>deleted text</del>, <ins>inserted text</ins>, and an <abbr title="Application Programming Interface">API</abbr> abbreviation.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">처음 글자가 큰 드롭캡으로 표시됩니다. 본문은 자연스럽게 흘러갑니다. WordPress block editor에서 paragraph block의 "Drop cap" 옵션을 켜면 자동으로 <code>has-drop-cap</code> 클래스가 추가됩니다. 한글 첫 글자도 드롭캡 처리되며 brand typeface로 강조됩니다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Text alignment</h3>
<!-- /wp:heading -->

<!-- wp:group {"className":"is-style-section-1","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-section-1"><!-- wp:paragraph -->
<p>왼쪽 정렬 (기본).</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center">중앙 정렬.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"right"}}} -->
<p class="has-text-align-right">오른쪽 정렬.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Lists</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>첫 번째 항목 — 짧은 한 줄.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>두 번째 항목 — 본문이 길어져 두 줄 이상이 될 때 marker와 텍스트 들여쓰기가 안정적으로 유지되어야 합니다.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>세 번째 항목 — 중첩<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>중첩 항목 a</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>중첩 항목 b<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>3단계 중첩</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>준비 단계</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>실행 단계<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item -->
<li>세부 작업 1</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>세부 작업 2</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list --></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>마무리</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Quote and Pullquote</h2>
<!-- /wp:heading -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>좋은 디자인은 가능한 적은 디자인을 한다.</p>
<!-- /wp:paragraph --><cite>Dieter Rams</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:pullquote -->
<figure class="wp-block-pullquote"><blockquote><p>중심석 — 첫 착지점.</p><cite>Omphalos VQA</cite></blockquote></figure>
<!-- /wp:pullquote -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Code, Preformatted, and Verse</h2>
<!-- /wp:heading -->

<!-- wp:code -->
<pre class="wp-block-code"><code>const veryLongTokenName = "--md-sys-typescale-body-large-line-height --md-sys-color-surface-container-high --md-sys-shape-corner-medium";</code></pre>
<!-- /wp:code -->

<!-- wp:preformatted -->
<pre class="wp-block-preformatted">Preformatted text keeps spaces
  and line breaks exactly as typed.</pre>
<!-- /wp:preformatted -->

<!-- wp:verse -->
<pre class="wp-block-verse">Verse block keeps poetic line breaks
without becoming a code specimen.</pre>
<!-- /wp:verse -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Table</h2>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table"><table class="has-fixed-layout"><thead><tr><th>토큰</th><th>값</th><th>설명</th></tr></thead><tbody><tr><td><code>--space-xs</code></td><td>4px</td><td>작은 inline gap.</td></tr><tr><td><code>--space-md</code></td><td>16px</td><td>본문 rhythm 기본 단위.</td></tr><tr><td><code>--space-xl</code></td><td>48px</td><td>섹션 간격.</td></tr></tbody><tfoot><tr><td>요약</td><td colspan="2">5개 spacing token 중 일부.</td></tr></tfoot></table><figcaption class="wp-element-caption">Table 1. Core table block with thead, tbody, tfoot, and figcaption. No custom wrapper.</figcaption></figure>
<!-- /wp:table -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Details</h2>
<!-- /wp:heading -->

<!-- wp:details -->
<details class="wp-block-details"><summary>접근성 지침 요약</summary><!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>모든 이미지에 대체 텍스트를 제공합니다.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>색상만으로 상태를 전달하지 않습니다.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>키보드로 모든 인터랙션에 접근할 수 있어야 합니다.</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></details>
<!-- /wp:details -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Footnotes</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>fn1<sup data-fn="1e3b8bdd-8cf2-475b-b015-54c86f93e1b1" class="fn"><a href="#1e3b8bdd-8cf2-475b-b015-54c86f93e1b1" id="1e3b8bdd-8cf2-475b-b015-54c86f93e1b1-link">1</a></sup></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>fn2<sup data-fn="7873f5ac-f713-4c02-84fa-0356553a6d1a" class="fn"><a href="#7873f5ac-f713-4c02-84fa-0356553a6d1a" id="7873f5ac-f713-4c02-84fa-0356553a6d1a-link">2</a></sup></p>
<!-- /wp:paragraph -->

<!-- wp:footnotes /-->
