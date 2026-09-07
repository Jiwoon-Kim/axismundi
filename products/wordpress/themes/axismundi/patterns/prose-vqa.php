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
	<img src="https://picsum.photos/id/1015/600/240" alt="Picsum placeholder photo" width="600" height="240">
	<figcaption>Figure 1. picsum.photos 외부 이미지 + figcaption.</figcaption>
</figure>

<h2 id="picture">반응형 이미지 — Picture</h2>

<figure>
	<picture>
		<source media="(min-width: 800px)" srcset="https://picsum.photos/id/1016/800/300">
		<img src="https://picsum.photos/id/1016/480/240" alt="Responsive picsum placeholder" width="480" height="240">
	</picture>
	<figcaption>Figure 2. &lt;picture&gt; + &lt;source&gt; 반응형 이미지 (picsum.photos).</figcaption>
</figure>

<h2 id="inline-extra">기타 인라인 — More inline semantics</h2>

<p>화학식 H<sub>2</sub>O 와 제곱 x<sup>2</sup>는 <code>sub</code>/<code>sup</code>, 변수 <var>n</var>과 프로그램 출력 <samp>Segmentation fault</samp>는 <code>var</code>/<code>samp</code>, 짧은 인라인 인용은 <q>quote inside text</q>(<code>q</code>), 정의 대상은 <dfn>정의어 (dfn)</dfn>로 표시됩니다. 작성 시각 <time datetime="2026-06-07">2026-06-07</time>(<code>time</code>), 그리고 <small>작은 글씨 — 저작권·법적 고지 (small)</small>. 추가로 <b>b</b> / <i>i</i> / <u>u</u> 가 <strong>strong</strong> / <em>em</em> / <ins>ins</ins> 와 시각적으로 구분되는지 확인합니다.</p>

<h2 id="details">접기/펼치기 — Details &amp; summary</h2>

<details>
	<summary>자세한 내용 보기 (summary 클릭 시 펼쳐짐)</summary>
	<p>details 내부 본문. 마크다운의 접기 문법이나 federated 콘텐츠의 disclosure가 변환될 때 여백·typography가 어떻게 잡히는지 확인합니다.</p>
</details>

<h2 id="media">미디어 — Audio / Video / Iframe</h2>

<p>federated 원격 HTML에는 미디어 임베드도 섞일 수 있습니다 (prose typography는 아니지만 raw-HTML 표면의 일부):</p>

<p><audio controls src="https://www.w3schools.com/html/horse.ogg">audio fallback text</audio></p>

<video controls width="480" poster="https://picsum.photos/id/1018/480/270">
	<source src="https://www.w3schools.com/html/mov_bbb.mp4" type="video/mp4">
	video fallback text
</video>

<iframe title="srcdoc iframe" width="480" height="120" srcdoc="&lt;p style='font-family:sans-serif;padding:8px'&gt;iframe srcdoc — 외부 요청 없는 자체 문서&lt;/p&gt;"></iframe>

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
