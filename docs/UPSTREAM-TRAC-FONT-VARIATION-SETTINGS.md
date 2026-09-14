# Draft — Core Trac: `WP_Font_Face` compiles array `font-variation-settings` into invalid CSS

> 상태: **게시됨** — [Core Trac #66103](https://core.trac.wordpress.org/ticket/66103) (2026-09-14)
>
> 올릴 곳: **Core Trac** 새 티켓 <https://core.trac.wordpress.org/newticket> (wordpress.org 계정 로그인 필요 — 사용자가 직접 등록).
> Gutenberg에는 `WP_Font_Face` 사본이 없다(`lib/`에 폰트 파일은 `lib/compat/plugin/fonts.php`뿐, trunk 확인) → 고칠 곳은 코어뿐.
> wordpress-develop PR은 **Trac 티켓 링크 필수**, GitHub에서 머지하지 않고 리뷰용, **AI 도구 사용 범위 공개 필수**(PR 템플릿).
> 다음 단계: #66103을 링크한 `wordpress-develop` PR(수정과 단위 테스트)을 준비한다. PR은 로컬 포크 없이 GitHub 웹 편집기로도 가능.
>
> 확인한 사실(2026-09-14):
> - `src/wp-includes/fonts/class-wp-font-face.php` trunk 328-331: `font-variation-settings`가 배열이면 `compile_variations()` 호출.
>   370-378: `$variations .= "$key $value";` — 구분자 없음, 태그 따옴표 없음. 6.4.0부터(`c29b095e76`, Fixes #59165).
> - 재현(wp-env, WP 7.1, 공개 API `wp_print_font_faces()`): `array( 'wght' => 400, 'GRAD' => 0 )` →
>   `font-variation-settings:wght 400GRAD 0;`
> - theme.json 리졸버(`class-wp-font-face-resolver.php`)는 fontFace 값을 kebab-case로만 바꿔 넘김 → theme.json에 배열을 쓰면
>   이 경로에 도달할 것으로 보임(끝까지 이어서 시험하지는 않음). 편집기 JSON 스키마는 이 값을 `"type": "string"`으로만 정의.
> - 테스트: `tests/phpunit/tests/fonts/font-face/`에 배열형 `font-variation-settings` 케이스 없음 → 기대값 수정 없이 새 케이스 추가.
> - 중복 검색: Gutenberg·wordpress-develop에서 `compile_variations` 관련 이슈/PR 없음. Trac은 봇 확인 때문에 직접 검색 못 함
>   → **등록 전에 Trac에서 `font-variation-settings`로 한 번 검색**할 것.
>
> 필드 제안: Type `defect (bug)`, Version `6.4`, Severity `normal`. Component는 #59165와 같은 값으로(등록 화면에서
> #59165를 열어 확인 — 조회를 직접 못 해 비워 둠). Keywords: 패치나 PR을 붙이면 `has-patch has-unit-tests`.
>
> 범위: 이 티켓은 **조합 버그만**. theme.json 스키마가 객체 형태를 허용하는 문제는 Gutenberg 쪽(Font Library 메타데이터 이슈)이라 섞지 않는다.

---

## Summary

WP_Font_Face compiles an array `font-variation-settings` into invalid CSS

## Description (Trac WikiFormatting — paste as is)

`WP_Font_Face::generate_font_face_css()` accepts `font-variation-settings` as an array and converts it with `compile_variations()`. That method concatenates each axis as `"$key $value"` with no separator and no quotes around the axis tag, so the output is not valid CSS.

=== Steps to reproduce

{{{#!php
<?php
wp_print_font_faces(
	array(
		'Example Variable' => array(
			array(
				'font-family'             => 'Example Variable',
				'src'                     => array( 'https://example.org/example-variable.woff2' ),
				'font-variation-settings' => array( 'wght' => 400, 'GRAD' => 0 ),
			),
		),
	)
);
}}}

=== Actual

{{{
font-variation-settings:wght 400GRAD 0;
}}}

=== Expected

{{{
font-variation-settings:"wght" 400, "GRAD" 0;
}}}

The `font-variation-settings` descriptor takes a comma-separated list of `<string> <number>` pairs, where the axis tag is a quoted four-character string. Without the quotes and commas a browser drops the declaration.

The array branch exists since 6.4.0 ([56500] / #59165) and has no unit test; the string form, which theme.json documents, is passed through unchanged and works.

=== Suggested fix

{{{#!diff
 	private function compile_variations( array $font_variation_settings ) {
-		$variations = '';
-
-		foreach ( $font_variation_settings as $key => $value ) {
-			$variations .= "$key $value";
-		}
-
-		return $variations;
+		$variations = array();
+
+		foreach ( $font_variation_settings as $tag => $value ) {
+			$variations[] = sprintf( '"%s" %s', $tag, $value );
+		}
+
+		return implode( ', ', $variations );
 	}
}}}

plus a data provider case in `tests/phpunit/tests/fonts/font-face/wpFontFace/generateAndPrint.php` (or the shared dataset) asserting the expected output above.

> 확인됨: `[56500]`은 커밋 `c29b095e76`의 `git-svn-id` (`trunk@56500`)와 일치.
> 수정안 실측(wp-env): `array( 'wght' => 400, 'GRAD' => 0 )` → `"wght" 400, "GRAD" 0`,
> `array( 'GRAD' => 0, 'XOPQ' => 96, 'YTDE' => -203 )` → `"GRAD" 0, "XOPQ" 96, "YTDE" -203`.
> 태그 이스케이프(태그에 `"`가 들어오는 경우)는 CSS 태그가
> 네 글자 ASCII라는 전제로 생략했다 — 리뷰에서 요청하면 `preg_match( '/^[\x20-\x7E]{4}$/', $tag )` 검증을 추가.
