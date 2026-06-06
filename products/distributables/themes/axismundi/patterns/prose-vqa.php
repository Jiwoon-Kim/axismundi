<?php
/**
 * Title: Prose VQA
 * Slug: axismundi/prose-vqa
 * Inserter: false
 * Description: Prose / element-layer typography specimen, rendered inside a single
 *   Custom HTML block — RAW HTML elements with NO .wp-block-* wrappers. This is the
 *   prose/federated surface (markdown-style / remote HTML output), distinct from
 *   core BLOCK rendering. It carries no theme CSS, so the blank/core render can be
 *   snapshotted and compared against later foundation / .federated-content work.
 *   Mixed Korean + English to exercise mixed-script line-height + word-break.
 *   Dev-only specimen — excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */
?>
<!-- wp:html -->
<h1 id="prose-title">Prose VQA — 한글과 English가 섞인 본문 typography</h1>

<p>이 페이지는 <code>.wp-block-*</code> 래퍼가 전혀 없는 <strong>순수 HTML 엘리먼트</strong>만 담습니다. markdown이나 federated(ActivityPub) 원격 콘텐츠가 만들어내는 raw HTML이 어떻게 렌더되는지, 그리고 나중에 prose / <code>.federated-content</code> 스타일이 들어갔을 때 어떻게 바뀌는지를 한 페이지에서 비교하기 위한 baseline입니다.</p>

<p>The text mixes scripts on purpose. Korean wraps by word, while long English tokens like "WordPress", "ActivityPub", and "design system" must wrap safely on narrow viewports. 본문에는 <a href="#prose-title">inline link</a>, <strong>bold</strong>, <em>italic</em>, <code>inline code</code>, <mark>highlight (mark)</mark>, <del>deleted</del>, <s>strikethrough</s>, <ins>inserted</ins>, <abbr title="HyperText Markup Language">HTML</abbr> 약자, 그리고 <kbd>Ctrl</kbd> + <kbd>K</kbd> 키캡이 들어 있습니다.</p>

<h2 id="lists">리스트 — Lists</h2>

<ul>
	<li>Unordered 첫 번째 항목 — 짧은 한 줄.</li>
	<li>두 번째 항목 — 두 줄 이상 넘어갈 때 hanging indent가 정렬되는지 확인하는 더 긴 본문 sample text that wraps.
		<ul>
			<li>중첩 항목 a</li>
			<li>중첩 항목 b
				<ul><li>3단계 중첩</li></ul>
			</li>
		</ul>
	</li>
	<li>세 번째 항목.</li>
</ul>

<ol>
	<li>준비 단계</li>
	<li>실행 단계
		<ol><li>세부 작업 1</li><li>세부 작업 2</li></ol>
	</li>
	<li>마무리</li>
</ol>

<dl>
	<dt>토큰 (token)</dt>
	<dd>디자인 시스템에서 의미 단위로 추상화된 값 — definition list rendering test.</dd>
	<dt>구성 (composition)</dt>
	<dd>여러 토큰이 합쳐져 컴포넌트의 한 상태를 만드는 방식.</dd>
</dl>

<h2 id="blockquote">인용 — Blockquote</h2>

<blockquote>
	<p>Good design is as little design as possible. 좋은 디자인은 가능한 적게 디자인하는 것이다.</p>
	<cite>Dieter Rams</cite>
</blockquote>

<p>blockquote 다음에 본문이 다시 와도 vertical rhythm이 유지되는지 확인합니다.</p>

<h2 id="code">코드 — Code</h2>

<p>인라인 코드는 <code>const x = 1;</code> 처럼 본문에 녹아들고, 블록 코드는 padding과 overflow-x를 가집니다:</p>

<pre><code>function axismundi() {
  return 'block code — preserves whitespace and line breaks';
}</code></pre>

<p>긴 한 줄도 강제 줄바꿈 없이 가로 스크롤로 보존되어야 합니다:</p>

<pre><code>document.querySelectorAll('[role="toolbar"] [aria-pressed]').forEach(btn =&gt; btn.addEventListener('click', () =&gt; { /* ... */ }));</code></pre>

<h2 id="table">표 — Table</h2>

<figure>
	<table>
		<thead>
			<tr><th>토큰</th><th>값</th><th>용도 (long-form description to force horizontal overflow on narrow viewports)</th></tr>
		</thead>
		<tbody>
			<tr><td>space-xs</td><td>4px</td><td>가장 작은 spacing — inline gap</td></tr>
			<tr><td>space-md</td><td>16px</td><td>component padding 기본값</td></tr>
			<tr><td>space-xl</td><td>32px</td><td>section 사이 spacing</td></tr>
		</tbody>
		<tfoot>
			<tr><td>요약</td><td>—</td><td>3개 spacing 토큰 예시</td></tr>
		</tfoot>
	</table>
	<figcaption>Table 1. Spacing 스케일 — thead / tbody / tfoot / figcaption 포함.</figcaption>
</figure>

<h2 id="figure">이미지 — Image &amp; figure</h2>

<figure>
	<img src="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='600'%20height='240'%3E%3Crect%20width='600'%20height='240'%20fill='%23d9d9d9'/%3E%3Ctext%20x='300'%20y='128'%20font-family='sans-serif'%20font-size='20'%20text-anchor='middle'%20fill='%23555'%3Eplaceholder%20600%C3%97240%3C/text%3E%3C/svg%3E" alt="Placeholder image" width="600" height="240">
	<figcaption>Figure 1. 외부 요청 없는 SVG data-URI placeholder + figcaption.</figcaption>
</figure>

<h2 id="headings">헤딩 깊이 — Heading depth</h2>
<h3 id="h3">h3 heading</h3>
<p>h3 다음 본문.</p>
<h4 id="h4">h4 heading</h4>
<p>h4 다음 본문.</p>
<h5 id="h5">h5 heading</h5>
<p>h5 다음 본문.</p>
<h6 id="h6">h6 heading</h6>
<p>h6 다음 본문.</p>

<h2 id="hr">섹션 구분 — HR</h2>
<p>HR 위 단락.</p>
<hr>
<p>HR 아래 단락 — 위/아래 여백 확인.</p>
<!-- /wp:html -->
