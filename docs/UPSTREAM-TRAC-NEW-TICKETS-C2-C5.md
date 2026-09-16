# Draft — new Core Trac tickets for C5 (font src query string) and C2 (wp-exclude-emoji root)

> 상태: **등록됨** 2026-09-17(사용자) — C5 [#66119](https://core.trac.wordpress.org/ticket/66119), C2 [#66120](https://core.trac.wordpress.org/ticket/66120).
> - #66119: 저장된 Description이 초안과 바이트 단위 일치(티켓 `?format=tab`, SHA-256 `b29ad601…`). Summary는 백틱 없이 등록.
> - #66120: Trac이 예시의 국기 `alt` 리터럴(🇰🇷)을 빈 값으로 저장해, 사용자가 이모지 리터럴 없는 설명으로 고쳐 저장. 아래 C2 Description은 **등록된 최종본**으로 교체했다(티켓 `?format=tab`에서 받아 대조). 교훈: Trac 본문에는 보조 평면 이모지 리터럴을 넣지 말 것.
>
> 순서 제안: C5 먼저(작고 독립적, 재현 한 줄, 수정 한 줄 + 데이터셋 케이스), 그다음 C2.
> 두 티켓 모두 기존 티켓 없음(2026-09-16 검색, [UPSTREAM-TRAC-CANDIDATES.md](UPSTREAM-TRAC-CANDIDATES.md)). C2는 #52219(6.2 fixed)가 닫혀 있어 새 티켓으로 연다.
>
> 형식: Trac WikiFormatting. 필드는 각 초안 위 표대로 넣는다. 본문은 "Description" 칸에 붙인다.
>
> 게시 전 검증(각 본문):
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-TRAC-NEW-TICKETS-C2-C5.md `
>   --source-after "<해당 Description 제목 줄 전체>" `
>   --source-before "<다음 제목 줄 또는 파일 끝 마커>" `
>   --candidate <붙여 넣을 파일>
> ```
>
> 게시 후: 티켓 번호를 여기와 후보 문서에 기록.
>
> ## 근거 (2026-09-17)
>
> - C5 소스: wordpress-develop trunk `87b5b7c033`, `src/wp-includes/fonts/class-wp-font-face.php` `order_src()` 251–252행. 재현: wp-env WordPress 7.1, `wp --skip-plugins eval`로 `wp_print_font_faces()` 호출 — query가 있으면 `src` 없음, 없으면 `src:url(...) format('woff2')`. 테스트 위치: `tests/phpunit/tests/fonts/font-face/wp-font-face-tests-dataset.php`(데이터셋, `single woff2 format font` 등). 클래스 도입은 6.4(#59165, 사용자 메모리·#66103 기준).
> - C2 소스: trunk `src/js/_enqueues/vendor/twemoji.js` 314–319행(`doNotParse`는 `grabAllTextNodes()`가 자식 요소를 돌 때만 호출), `src/js/_enqueues/wp/emoji.js` 126–152행(추가된 텍스트 노드면 부모로 올라가 `parse( node )`), 256–268행(`doNotParse` 콜백). observer는 `childList`만 본다(`characterData` 아님).
> - C2 재현(2026-09-15, 하네스: Core loader·`wp-emoji.js`·`twemoji.js`, 앱 내 Chromium/Windows, 🇰🇷, 1.5초 뒤 `img.emoji` 수): 초기 HTML의 제외 span 0, 평문 1, A 제외 span 자체 append 1, B 바깥 wrapper 안 제외 span append 0, C 초기 제외 span의 `textContent` 변경 1, D 평문 삽입 1, E `div > p > span.wp-exclude-emoji` 한 번에 append 0.
> - "제외 요소 안쪽 깊은 곳에 요소를 추가"하는 경우는 **재현하지 않음** — 소스 판독으로만 적고 본문에 그렇게 밝힘.

---

## C5

| Field | Value |
|---|---|
| Summary | `WP_Font_Face` drops a font `src` URL that has a query string |
| Type | defect (bug) |
| Component | General |
| Version | 6.4 |
| Keywords | `needs-patch needs-unit-tests` |

## Description C5 (Trac WikiFormatting — paste as is)

`WP_Font_Face::order_src()` picks each URL's format from its file extension:

{{{
$format         = pathinfo( $url, PATHINFO_EXTENSION );
$src[ $format ] = $url;
}}}

For `https://example.org/f/a.woff2?ver=abc123`, `pathinfo()` returns `woff2?ver=abc123`. The method then only keeps the `woff2`, `woff`, `ttf`, `eot` and `otf` keys, so the URL is dropped and the `@font-face` rule is printed without a `src`. There is no notice.

To reproduce (WordPress 7.1, no plugins):

{{{
wp_print_font_faces( array( array( array(
	'font-family' => 'T',
	'src'         => array( 'https://example.org/f/a.woff2?ver=abc123' ),
) ) ) );
}}}

Output:

{{{
@font-face{font-family:T;font-style:normal;font-weight:400;font-display:fallback;}
}}}

Without `?ver=abc123` the same call prints `src:url('https://example.org/f/a.woff2') format('woff2');`.

Fonts from `theme.json` use `file:./` paths with no query string, which may be why this has not shown up. It does affect a plugin that adds a version to its font URLs for cache busting, the usual practice for assets; such a plugin has to print its own `@font-face` rules instead of using `wp_print_font_faces()`.

A possible fix is to read the extension from the URL path only:

{{{
$format = pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
}}}

with a dataset case in `tests/phpunit/tests/fonts/font-face/wp-font-face-tests-dataset.php` for a URL with a query string.

Found while building an emoji font fallback; wider context in [https://github.com/WordPress/gutenberg/discussions/83032 Gutenberg discussion #83032]. Related, same class: #66103.

<!-- end of C5 -->

---

## C2

| Field | Value |
|---|---|
| Summary | `wp-exclude-emoji` is ignored when the excluded element is the node being parsed |
| Type | defect (bug) |
| Component | Emoji |
| Version | 6.2 |
| Keywords | `needs-patch` |

## Description C2 (Trac WikiFormatting — paste as is)

#52219 made `wp-emoji.js` skip elements with the `wp-exclude-emoji` class ([55186]) through a `doNotParse()` callback added to `twemoji.js`. That callback is only consulted in `grabAllTextNodes()`, for the child elements of the node passed to `parse()`. The node passed to `parse()` itself is never checked.

The `MutationObserver` in `wp-emoji.js` passes exactly such a node: for an added text node it moves up to the parent and calls `parse( node )` on it. So an excluded element is parsed, and its emoji replaced, whenever it becomes that node:

* the excluded element itself is inserted after the page has loaded, or
* its own text is replaced, for example with `textContent`.

To reproduce, load a front-end page where the emoji fallback script is active (on current releases #66104 makes that every browser; otherwise one where a support test fails, such as Chromium on Windows for `flag`) and run in the console:

{{{
const span = document.createElement( 'span' );
span.className = 'wp-exclude-emoji';
span.textContent = '\uD83C\uDDF0\uD83C\uDDF7'; // Flag: South Korea
document.body.appendChild( span );
}}}

After the observer runs, the span contains an `img.emoji` whose `alt` is the original South Korea flag and whose `src` is `https://s.w.org/images/core/emoji/17.0.2/svg/1f1f0-1f1f7.svg`.

What I measured on a local page in Chromium on Windows with Core's `wp-emoji.js`, `twemoji.js` and `emoji-loader.js` (with the #66104 patch, so only `flag` failed), counting `img.emoji` after 1.5 seconds:

||= Case =||= Images =||
|| `span.wp-exclude-emoji` in the initial HTML || 0 ||
|| the same span appended after load || 1 ||
|| the initial span's `textContent` replaced || 1 ||
|| `div > p > span.wp-exclude-emoji` appended in one piece || 0 ||
|| the span appended inside a wrapper element || 0 ||
|| plain text appended (control) || 1 ||

Reading the source, an element or text added anywhere inside an excluded element should be replaced the same way, since its parent becomes the node passed to `parse()`; I have not tested that case.

A possible fix is to skip the node in the observer when it is inside an excluded element, for example `node.closest( '.wp-exclude-emoji' )` before `parse( node )`, or to apply `doNotParse()` to the node itself and its ancestors in `parse()`. The first keeps the change out of the vendored `twemoji.js`.

This matters for anything that inserts text the fallback should leave alone after load: editors on the front end (the reason for #52219), and code that draws emoji another way and marks it with this class. Wider context in [https://github.com/WordPress/gutenberg/discussions/83032 Gutenberg discussion #83032].

<!-- end of tickets -->
