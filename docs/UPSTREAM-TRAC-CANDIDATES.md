# Upstream Trac candidates (emoji)

Axismundi Emoji의 Unicode fallback 설계 중 발견한 Core 결함 후보. 아직 티켓 아님.
각 항목은 **재현 전에는 올리지 않는다** — "source-read"는 코드를 읽고 추론한 단계, "reproduced"는 브라우저에서 확인한 단계.
재현되면 이 파일에서 개별 초안(`UPSTREAM-TRAC-*.md`)으로 분리하고, 여건이 되면 wordpress-develop PR까지 이어간다.

기준 소스: `wordpress-develop` (2026-09-15, trunk + #66104 브랜치).

| # | 후보 | 상태 | 기존 티켓 |
|---|---|---|---|
| C1 | 감지 캐시 만료 단위 혼동 | source-read | 미검색 |
| C2 | `wp-exclude-emoji`가 MutationObserver 경로에서 무시됨 | **reproduced** (2026-09-15) | 미검색 |
| C3 | `everythingExceptFlag`일 때 subdivision flag가 치환 대상에서 빠짐 | **reproduced** (잉글랜드) | 미검색 |
| C4 | `emoji` 감지가 단일 코드포인트만 검사 | 설계 한계(결함 아닐 수 있음) | #66104과 구분 |
| C5 | `WP_Font_Face`가 query string 붙은 src를 버림 | **reproduced** (2026-09-16) | 미검색 |

### 재현 하네스 (C2·C3 공통)

WordPress 없이 Core 파일 세 개를 그대로 쓴다: `src/js/_enqueues/lib/emoji-loader.js`(inline module), `wp/emoji.js`, `vendor/twemoji.js`. `script#wp-emoji-settings`는 `_print_emoji_detection_script()`의 SCRIPT_DEBUG 형태(`source.wpemoji`/`source.twemoji`)로 흉내 낸다. loader에 이스케이프가 있으므로 HTML은 Python으로 조립한다. `file://`에서는 module·Worker가 막히므로 `python -m http.server`로 연다.

측정 환경: 이 기기(Windows 11) 앱 내 Chromium, #66104 브랜치 loader. `supports = { flag: false, emoji: true, everything: false, everythingExceptFlag: true }`.

## C1. 감지 캐시 만료 단위 혼동

`src/js/_enqueues/lib/emoji-loader.js` `getSessionSupportTests()`:

```js
new Date().valueOf() < item.timestamp + 604800 && // Note: Number is a week in seconds.
```

`timestamp`는 `Date.valueOf()`(밀리초)인데 더하는 값은 초 단위 1주. 실제 만료는 604.8초(약 10분).
sessionStorage라 탭 수명이 상한이지만, 긴 세션에서는 10분마다 canvas 검사(Worker)를 다시 돈다.

- 재현: 캐시 저장 직후와 11분 뒤 `wpEmojiSettingsSupports` 재생성 여부 확인.
- 수정 방향: `604800000` 또는 `7 * 24 * 60 * 60 * 1000`.
- 영향 크기는 작음(성능). 단독 티켓보다 다른 loader 수정에 묶는 편이 나을 수 있음.

## C2. `wp-exclude-emoji`가 MutationObserver 경로에서 무시됨

`src/js/_enqueues/vendor/twemoji.js` WP 수정분: `doNotParse()`는 `grabAllTextNodes()`가 **자식 요소**를 순회할 때만 호출된다. `parse(root)`의 root 자신은 검사하지 않는다.

`src/js/_enqueues/wp/emoji.js` MutationObserver: 추가된 노드가 텍스트면 부모로 올라가고, 그 노드를 `parse(node)`에 root로 넘긴다.

추론되는 결과:
- 초기 `parse(document.body)`: `.wp-exclude-emoji` 요소는 자식이므로 제외됨 — 문서대로 동작.
- 동적으로 삽입된 `<span class="wp-exclude-emoji">🇰🇷</span>` 자체가 addedNode이면 root가 되어 **치환됨**.
- 제외 요소 안의 텍스트가 바뀌어도 부모(=제외 요소)가 root가 되어 치환됨.

- 재현 결과(🇰🇷, 로드 후 1.5초 뒤 `img.emoji` 개수):

  | 경우 | img |
  |---|---|
  | 초기 HTML의 `span.wp-exclude-emoji` (아무 조작 없음) | 0 — 문서대로 |
  | 초기 HTML의 평문 (대조군) | 1 |
  | A. `span.wp-exclude-emoji` 자체를 동적 append | **1 — 치환됨** |
  | B. `span.ax-unicode-emoji > span.wp-exclude-emoji`를 append | 0 |
  | C. 초기 제외 span의 `textContent` 변경 | **1 — 치환됨** |
  | D. 평문 동적 삽입 (대조군) | 1 |
  | E. `div > p > span.wp-exclude-emoji`를 한 번에 append | 0 |

  A의 결과 마크업: `<span class="wp-exclude-emoji"><img class="emoji" alt="🇰🇷" src="https://s.w.org/images/core/emoji/17.0.2/svg/1f1f0-1f1f7.svg"></span>`.
  즉 제외 요소가 **parse root가 되는 순간**(자기 자신이 addedNode이거나, 그 안의 텍스트 노드가 바뀔 때) 제외가 무시된다.
- 수정 방향: `parse()` DOM 모드에서 root에도 `doNotParse`를 적용하거나, observer가 조상 중 제외 요소가 있으면 건너뜀(`closest('.wp-exclude-emoji')`).
- Axismundi 우회: 바깥 `span.ax-unicode-emoji` 안에 `span.wp-exclude-emoji`를 중첩하면 바깥 삽입 경로는 막히지만 안쪽 텍스트 변경 경로는 남음 → 우회보다 Core 수정이 맞음. 폰트 fallback 제안의 전제 조건이기도 함.

## C3. `everythingExceptFlag`일 때 subdivision flag 누락

`emoji.js` callback은 `everythingExceptFlag`일 때 아래만 치환한다:

```js
/^1f1(?:e[6-9a-f]|f[0-9a-f])-1f1(?:e[6-9a-f]|f[0-9a-f])$/  // country flags
/^(1f3f3-fe0f-200d-1f308|1f3f4-200d-2620-fe0f)$/            // rainbow, pirate
```

그런데 `flag` 감지는 잉글랜드 태그 시퀀스(U+1F3F4 + tag characters)와 트랜스젠더 ZWJ 깃발도 검사한다. 감지는 이들을 "깃발"로 보고 실패로 판정하지만, 치환 정규식은 이들을 깃발로 보지 않아 그대로 둔다(잉글랜드·스코틀랜드·웨일스, transgender flag).

- 재현 결과(C2와 같은 환경): 잉글랜드 깃발 `img` 0, 화면에는 native 검은 깃발(🏴)만 보이고 태그 문자는 사라짐 → **틀린 깃발이 치환 없이 남음**. 트랜스젠더 깃발도 `img` 0이지만 이 환경은 native로 제대로 그림(이 기기에서 `flag` 실패 원인은 Sark/잉글랜드 쪽). 같은 페이지 🇰🇷은 치환됨.
- 확인 필요: Twemoji가 해당 icon 코드를 어떤 문자열로 넘기는지(`1f3f4-e0067-...`), 의도된 제외인지 기록 검색.

## C4. `emoji` 감지가 단일 코드포인트만 검사

Emoji 17 검사는 U+1FAC8 하나의 중앙 픽셀. 사용자 모바일 실측(2026-09-15): 🫈·👯🏻·🤼🏻 정상, 🧑‍🩰·🧑🏻‍🐰‍🧑🏼 분해, 🧑🏻‍🫯‍🧑🏼 tofu, 드래그 선택은 한 grapheme.
단일 문자는 그리지만 새 ZWJ 시퀀스는 못 그리는 환경을 "지원"으로 판정한다.

- 결함이라기보다 설계 한계. 티켓으로 가면 "profile 단위 probe(single / modifier / zwj / flags)" 제안이 되고, Axismundi 구현을 근거로 삼는다.
- #66104(잘못된 이스케이프)와 섞지 않는다.

## C5. `WP_Font_Face`가 query string 붙은 src를 버림

`src/wp-includes/fonts/class-wp-font-face.php` `order_src()`:

```php
$format         = pathinfo( $url, PATHINFO_EXTENSION );
$src[ $format ] = $url;
```

`https://…/font.woff2?ver=abc`의 확장자는 `woff2?ver=abc`로 읽힌다. 이후 `woff2`/`woff`/`ttf`/`eot`/`otf` 키만 골라 담으므로 src가 하나도 남지 않고, `src:` 없는 `@font-face`가 출력된다. 경고도 없다.

재현(wp-env, WordPress 7.1, 2026-09-16):

```php
wp_print_font_faces( array( array( array( 'font-family' => 'T', 'src' => array( 'https://example.org/f/a.woff2?ver=abc123' ), 'unicode-range' => 'U+1F1E6-1F1FF' ) ) ) );
// @font-face{font-family:T;font-style:normal;font-weight:400;font-display:fallback;unicode-range:U+1F1E6-1F1FF;}
```

같은 URL에서 query만 빼면 `src:url('…/a.woff2') format('woff2')`가 정상 출력된다.

- 영향: 플러그인이 캐시 무효화용 `?ver=`를 붙인 폰트 URL을 Core 프린터로 넘길 수 없다. theme.json `file:./` 경로는 query가 없어 드러나지 않았을 가능성.
- 수정 방향: `pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION )`. 테스트는 `tests/phpunit/tests/fonts/font-face/` 데이터셋에 query 케이스 추가.
- Axismundi 우회: `includes/unicode.php` `axismundi_emoji_unicode_css()`가 @font-face를 직접 출력(WORKAROUND C5 주석).
- #66103(`compile_variations()`)과 같은 클래스라 PR #13514 리뷰어에게 함께 보일 수 있음. 티켓은 분리.
