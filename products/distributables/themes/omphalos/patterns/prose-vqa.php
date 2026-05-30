<?php
/**
 * Title: Prose VQA
 * Slug: omphalos/prose-vqa
 * Categories: omphalos
 * Inserter: true
 * Description: Mixed-script prose typography specimen, rendered inside a single
 *              Custom HTML block. Carries no theme CSS yet — this is the baseline
 *              that later prose.css work is compared against.
 *
 * @package Omphalos
 */
?>
<!-- wp:html -->
<h1 id="article-title">한글 본문과 영문이 섞인 글의 typography</h1>

	<p>이 페이지는 <code>prose.css</code>의 모든 케이스를 한 페이지에서 검증합니다. <strong>한글</strong>과 영문이 자연스럽게 섞여있을 때 mixed-script line-height parity가 어떻게 작동하는지, blockquote / code / table / image / figure / list / heading anchor 같은 요소가 vertical rhythm을 어떻게 따라가는지 직접 확인할 수 있습니다.</p>

	<p>The text intentionally mixes scripts — Korean uses <code>word-break: keep-all</code> 으로 단어 단위로 줄바꿈되고, English words like "WordPress", "ActivityPub", "design system" 처럼 긴 단어는 <code>overflow-wrap: anywhere</code>로 안전하게 줄바꿈됩니다. 이 두 규칙이 함께 작동해야 narrow viewport에서 가로 스크롤 없이 깔끔하게 흘러갑니다.</p>

	<h2 id="emphasis-and-inline">강조 &amp; 인라인 요소</h2>

	<p>본문에서 <strong>강조 (strong)</strong>는 굵게, <em>이탤릭 (em)</em>은 기울임으로 표시됩니다. <code>인라인 코드 (code)</code>는 surface-container 배경과 작은 radius로 pill처럼 보이고, <a href="#emphasis-and-inline">인라인 링크</a>는 always-underline (WCAG 1.4.1 색상 단독 의존 회피).</p>

	<p>추가로 <mark>형광펜 (mark)</mark>은 tertiary-container 색으로, <del>취소선 (del)</del>과 <s>strikethrough (s)</s>는 line-through에 색을 약간 죽인 on-surface-variant로, <ins>밑줄 (ins)</ins>은 primary 색의 underline으로, <abbr title="HyperText Markup Language">HTML</abbr> 같은 약자는 dotted underline + cursor: help로 표시됩니다.</p>

	<h2 id="lists">리스트</h2>

	<h3 id="lists-unordered">Unordered (기본)</h3>

	<ul>
		<li>첫 번째 항목 — 짧은 한 줄.</li>
		<li>두 번째 항목 — 좀 더 긴 본문이 들어가는 경우 어떻게 흘러가는지 확인할 수 있는 sample. 본문이 두 줄 이상 넘어가면 들여쓰기가 정렬되어야 합니다.</li>
		<li>세 번째 항목 — 중첩
			<ul>
				<li>중첩 항목 a</li>
				<li>중첩 항목 b
					<ul>
						<li>3단계 중첩</li>
					</ul>
				</li>
			</ul>
		</li>
	</ul>

	<h3 id="lists-ordered">Ordered</h3>

	<ol>
		<li>준비 단계</li>
		<li>실행 단계
			<ol>
				<li>세부 작업 1</li>
				<li>세부 작업 2</li>
			</ol>
		</li>
		<li>마무리</li>
	</ol>

	<h3 id="lists-task">Task list (markdown)</h3>

	<ul>
		<li><input type="checkbox" disabled checked> Phase 1B — 33 컴포넌트 카탈로그</li>
		<li><input type="checkbox" disabled checked> Phase 2A — base / prose / blocks 시트</li>
		<li><input type="checkbox" disabled> Phase 2B — 정적 페이지 프로토타입</li>
		<li><input type="checkbox" disabled> Phase 3 — WordPress 통합</li>
	</ul>

	<h3 id="lists-definition">Definition list</h3>

	<dl>
		<dt>토큰 (token)</dt>
		<dd>디자인 시스템에서 의미 단위로 추상화된 값. 예: <code>--md-sys-color-primary</code>는 "primary 액션 색"을 의미하며, 실제 hex 값은 light/dark scheme에 따라 다릅니다.</dd>
		<dt>구성 (composition)</dt>
		<dd>여러 토큰이 합쳐져 컴포넌트의 한 상태를 만드는 방식.</dd>
	</dl>

	<h2 id="blockquote">Blockquote</h2>

	<p>blockquote는 prose context에서 primary-bordered emphasis로 강조됩니다 (base.css는 약한 outline-variant border, prose.css가 primary로 bump).</p>

	<blockquote>
		<p>좋은 디자인은 가능한 적은 디자인을 한다. 본질적이지 않은 모든 것을 빼버리는 것 — 그것이 좋은 디자인의 시작이다.</p>
		<cite>Dieter Rams</cite>
	</blockquote>

	<p>blockquote 다음에 다시 본문이 와도 vertical rhythm이 유지됩니다.</p>

	<h2 id="code-blocks">코드 블록</h2>

	<p>인라인 코드는 <code>const x = 1;</code> 처럼 본문 안에 자연스럽게 녹아들고, 블록 코드는 별도 컨테이너에 padding과 overflow-x scroll을 갖습니다.</p>

	<pre><code>// theme.js — Off-canvas nav drawer (excerpt)
function open() {
  if (drawer.open) return;
  drawer.showModal();
  allToggles.forEach(btn =&gt;
    btn.setAttribute('aria-expanded', 'true'));
}</code></pre>

	<p>긴 한 줄 코드도 horizontal scroll로 처리됩니다 — 강제 줄바꿈 없이 원본 그대로 보존:</p>

	<pre><code>document.querySelectorAll('[role="toolbar"] [aria-pressed]').forEach(btn =&gt; btn.addEventListener('click', () =&gt; { ... }));</code></pre>

	<p>키 입력은 <kbd>Ctrl</kbd> + <kbd>K</kbd> 처럼 키 캡 스타일로 표시됩니다.</p>

	<h2 id="tables">표</h2>

	<p>표는 wrapper div 없이 raw <code>&lt;figure&gt;</code> + <code>&lt;table&gt;</code> 구조 그대로 둡니다. 좁은 viewport에서 가로 overflow가 어떻게 처리되는지, <code>thead</code> / <code>tbody</code> / <code>tfoot</code> / <code>figcaption</code>이 vertical rhythm을 따르는지 직접 검증합니다.</p>

	<figure>
		<table>
			<thead>
				<tr>
					<th>토큰</th>
					<th>값</th>
					<th>용도 (long-form description to force horizontal overflow on narrow viewports)</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>--space-xs</code></td>
					<td>4px</td>
					<td>가장 작은 spacing — inline gap, micro padding</td>
				</tr>
				<tr>
					<td><code>--space-sm</code></td>
					<td>8px</td>
					<td>component 내부 element 간 gap</td>
				</tr>
				<tr>
					<td><code>--space-md</code></td>
					<td>16px</td>
					<td>component padding 기본값</td>
				</tr>
				<tr>
					<td><code>--space-lg</code></td>
					<td>24px</td>
					<td>section 내부 단락 간 spacing</td>
				</tr>
				<tr>
					<td><code>--space-xl</code></td>
					<td>32px</td>
					<td>section 사이 spacing, heading 위 간격</td>
				</tr>
			</tbody>
			<tfoot>
				<tr>
					<td>요약</td>
					<td>—</td>
					<td>5개 spacing 토큰 — xs 4px 부터 xl 32px 까지</td>
				</tr>
			</tfoot>
		</table>
		<figcaption>Table 1. Spacing 토큰 스케일 — tfoot 합계 행 + figcaption 포함.</figcaption>
	</figure>

	<h2 id="images-figures">이미지 &amp; figure</h2>

	<p>figure 안에 이미지 + figcaption 쌍을 두면 캡션이 자동으로 작은 typography (body-small)와 on-surface-variant 색으로 표시되고, 가운데 정렬됩니다.</p>

	<figure>
		<div style="block-size: 240px; background: linear-gradient(135deg, var(--md-sys-color-primary-container) 0%, var(--md-sys-color-tertiary-container) 100%); border-radius: var(--md-sys-shape-corner-medium);"></div>
		<figcaption>Figure 1. 그라데이션 placeholder — 외부 이미지 요청 없이 token primary-container → tertiary-container로 합성</figcaption>
	</figure>

	<h2 id="headings-deep">헤딩 깊이 검증</h2>

	<p>vertical rhythm이 깊이별로 어떻게 작아지는지 확인:</p>

	<h3 id="heading-h3">h3 — title-large</h3>
	<p>h3 다음 본문 — top margin이 h2보다 작음.</p>

	<h4 id="heading-h4">h4 — title-medium</h4>
	<p>h4 다음 본문.</p>

	<h5 id="heading-h5">h5 — label-large</h5>
	<p>h5 다음 본문 — 가장 작은 spacing.</p>

	<h6 id="heading-h6">h6 — label-medium</h6>
	<p>h6 다음 본문.</p>

	<h2 id="hr-section">섹션 구분 (HR)</h2>

	<p>HR은 prose context에서 큰 vertical margin (space-xl)으로 강한 break 효과:</p>

	<hr>

	<p>HR 다음 새 단락. 위/아래 각각 32px 여백.</p>

	<h2 id="conclusion">맺음말</h2>

	<p>이 페이지의 모든 요소가 정상적으로 보인다면 prose.css §1–§11 전부 작동하는 것입니다. 시각 점검 항목:</p>

	<ol>
		<li>vertical rhythm — 모든 sibling 사이 일관된 spacing</li>
		<li>headings — 깊이별 차등 spacing (h1/h2 큰 top → h6 작은 top)</li>
		<li>blockquote — primary 색의 좌측 bar + 큰 padding</li>
		<li>code — 인라인 pill + 블록 큰 padding + overflow-x</li>
		<li>table — wrapper border + radius, header tint, last-row no-border</li>
		<li>image / figure — radius-medium + figcaption center</li>
		<li>list — item 간격, 중첩 들여쓰기, task list 체크박스 정렬</li>
		<li>links — always-underline (a11y)</li>
	</ol>

	<p>문제가 보이면 다음 chat에서 spot-fix.</p>
<!-- /wp:html -->
