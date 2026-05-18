<?php
/**
 * Title: Axismundi Korean prose sample
 * Slug: axismundi-pilot/prose-sample
 * Categories: axismundi-prose
 * Description: Korean long-form content for validating post-body typography, spacing, and inline elements.
 */
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
	<!-- wp:heading -->
	<h2>부산에서 검증하는 온톨로지 기반 테마</h2>
	<!-- /wp:heading -->

	<!-- wp:paragraph -->
	<p>Axismundi Pilot은 워드프레스 코어 블록만으로 Material 3 토큰과 컴포넌트 매핑을 검증하는 블록 테마 실험입니다. 한국어 본문은 줄바꿈, 자간, 행간, 인라인 요소가 실제 게시글에서 자연스럽게 작동하는지 확인하기 위한 필수 샘플입니다.</p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph -->
	<p>본문 안에서는 <strong>강조</strong>, <em>기울임</em>, <code>inline code</code>, 링크, 목록, 인용문이 서로 충돌하지 않아야 합니다. 특히 모바일 390px 화면에서 가로 스크롤 없이 읽히는 것이 Phase 2D의 기본 조건입니다.</p>
	<!-- /wp:paragraph -->

	<!-- wp:quote -->
	<blockquote class="wp-block-quote"><p>좋은 테마는 장식보다 읽기 흐름을 먼저 보존한다.</p><cite>Axismundi Pilot</cite></blockquote>
	<!-- /wp:quote -->

	<!-- wp:code -->
	<pre class="wp-block-code"><code>add_theme_support( 'editor-styles' );</code></pre>
	<!-- /wp:code -->

	<!-- wp:list -->
	<ul>
		<li>토큰 그래프는 CSS 변수로 유지합니다.</li>
		<li>테마는 커스텀 블록을 등록하지 않습니다.</li>
		<li>플러그인 영역인 Carousel은 Pilot에서 제외합니다.</li>
	</ul>
	<!-- /wp:list -->

	<!-- wp:separator {"className":"is-style-divider-middle-inset"} -->
	<hr class="wp-block-separator is-style-divider-middle-inset"/>
	<!-- /wp:separator -->

	<!-- wp:table -->
	<figure class="wp-block-table"><table><thead><tr><th>Surface</th><th>Contract</th></tr></thead><tbody><tr><td>Post body</td><td>Block-first prose mapping</td></tr><tr><td>Theme chrome</td><td>Material Symbols allowed</td></tr></tbody></table></figure>
	<!-- /wp:table -->

	<!-- wp:table {"className":"is-style-stripes"} -->
	<figure class="wp-block-table is-style-stripes"><table><thead><tr><th>Variant</th><th>Expected</th></tr></thead><tbody><tr><td>Default row</td><td>No fill band</td></tr><tr><td>Stripe row</td><td>Surface-container-high band</td></tr></tbody></table></figure>
	<!-- /wp:table -->
</div>
<!-- /wp:group -->
