# Upstream Trac candidates (emoji)

Axismundi Emoji의 Unicode fallback 설계 중 발견한 Core 결함 후보. 아직 티켓 아님.
각 항목은 **재현 전에는 올리지 않는다** — "source-read"는 코드를 읽고 추론한 단계, "reproduced"는 브라우저에서 확인한 단계.
재현되면 이 파일에서 개별 초안(`UPSTREAM-TRAC-*.md`)으로 분리하고, 여건이 되면 wordpress-develop PR까지 이어간다.

기준 소스: `wordpress-develop` (2026-09-15, trunk + #66104 브랜치).

| # | 후보 | 상태 | 기존 티켓 |
|---|---|---|---|
| C1 | 감지 캐시 만료 단위 혼동 | source-read | [#58663](https://core.trac.wordpress.org/ticket/58663) 관련(단위 버그는 미언급) → 댓글 |
| C2 | `wp-exclude-emoji`가 MutationObserver 경로에서 무시됨 | **reproduced** (2026-09-15) | [#52219](https://core.trac.wordpress.org/ticket/52219)(6.2 fixed)의 남은 경우 → 새 티켓 |
| C3 | `everythingExceptFlag`일 때 subdivision flag가 치환 대상에서 빠짐 | **reproduced** (잉글랜드) | [#63451](https://core.trac.wordpress.org/ticket/63451)(사용자 본인 티켓, `close` 키워드) → 원인 댓글 |
| C4 | `emoji` 감지가 단일 코드포인트만 검사 | 설계 한계(결함 아닐 수 있음) | 직접 티켓 없음. #66104과 구분, [#61806](https://core.trac.wordpress.org/ticket/61806) 맥락 |
| C5 | `WP_Font_Face`가 query string 붙은 src를 버림 | **reproduced** (2026-09-16) | 없음 → 새 티켓 |

### 기존 티켓 검색 (2026-09-16)

방법: Core Trac은 curl에 봇 확인 페이지를 돌려주므로 앱 내 브라우저에서 열고(자동 확인 통과, 체크박스 없음) 같은 세션에서 쿼리를 CSV로 받았다.
- Emoji 컴포넌트 전체 120건(열림·닫힘) 제목 검토.
- 설명 검색(`description=~`): `pathinfo`, `order_src`, `WP_Font_Face`, `wp_print_font_faces`, `604800`, `wp-exclude-emoji`, `everythingExceptFlag`, `subdivision`.
- 전체 검색(댓글·changeset 포함): `pathinfo font`, `font query string src`, `font-face ver query`, `wp-exclude-emoji`, `doNotParse`, `wpEmojiSettingsSupports timestamp`, `emoji flag tag sequence`, `England flag emoji`, `emoji zwj sequence support test`.
- GitHub: Gutenberg 이슈 `font face query string`, `pathinfo font`, `wp-exclude-emoji`와 wordpress-develop PR `pathinfo font` — 관련 없음.

읽은 티켓:
- **#58663** (열림, Future Release, trivial) "Consider eliminating expiry-based invalidation of wpEmojiSettingsSupports sessionStorage cache". 캐시가 **1주** 유지된다는 전제로 만료 제거를 검토한다. 실제로는 단위 버그로 약 10분이라는 점은 티켓·댓글 어디에도 없다. C1은 새 티켓보다 여기에 댓글로 사실을 더하는 편이 맞다.
- **#52219** (6.2 fixed, [55186]) "wp-emoji.js should always skip nodes with the `wp-exclude-emoji` CSS class". Twemoji에 `doNotParse()` 콜백을 넣어 해결. C2는 그 수정이 root 요소를 검사하지 않아 남은 경우다. 닫힌 지 오래된 티켓은 다시 열지 않고 새 티켓에서 #52219·[55186]을 참조한다.
- **#63451** (열림, Awaiting Review, `close` 키워드) "Flag emoji rendering issue on Windows environment" — **사용자 본인 티켓**. 잉글랜드·스코틀랜드·웨일스 깃발이 Windows에서 검은 깃발. peterwilsoncc(Chrome 139, Windows 10)는 재현 못 함(Twemoji로 치환됨), 사용자는 이후 "해결된 것 같다", swissspidy가 `close` 키워드. **C3이 원인 설명이다.** `flag` 검사가 실패해도 `emoji` 검사가 통과하면(`everythingExceptFlag`) 치환 정규식이 국가 깃발·무지개·해적 깃발만 잡아서 태그 시퀀스 깃발은 남는다. `emoji`가 실패하면 모든 이모지를 치환하므로 깃발도 이미지가 된다.
  - 감지 문자열 이력(wordpress-develop 태그): 6.8.1은 Emoji 15.1(불사조), 6.8.2는 Emoji 16.0(물 튀김) 검사. 사용자가 "해결됐다"고 한 시점(2025-08)은 6.8.2 뒤다. **추정**: 당시 Windows가 Emoji 16을 그리지 못해 `emoji`가 실패 → 전부 치환 → 깃발도 이미지. 6.9부터는 #66104의 잘못된 문자열 때문에 `emoji`가 늘 실패해 같은 효과가 유지. 그러면 **#66104가 고쳐지는 순간 Emoji 17을 그리는 Windows에서 #63451이 다시 나타난다.** 이 기기 하네스(#66104 브랜치)에서 잉글랜드 `img` 0으로 이미 관찰됨. 당시 Windows의 Emoji 15.1/16 지원 여부는 측정하지 않았으므로 댓글에서는 추정으로 표기할 것.
  - 이 연결은 #66104 PR(#13515) 리뷰에도 필요한 정보다(수정의 부작용).
- **#61806** (열림, accepted) "twemoji is always loaded on Windows devices". Windows는 국가 깃발을 못 그려 `flag`가 늘 실패 → Twemoji 스크립트가 항상 로드되는 것은 설계상 결과라는 논의. C4의 profile 단위 감지 제안과 폰트 fallback 제안의 배경.
- **#64222** (열림) Twemoji 업그레이드 절차 개선 — `twemoji.js`의 WP 수정분(=`doNotParse`) 보존을 언급하는 정도. C2 수정 시 이 절차에 영향.
- **#63569** (열림) 외부 src로 폰트 등록 — 빈 src 처리 문제로 C5와 원인이 다름. C5 티켓에서 "src가 없는 @font-face" 결과가 같다는 점만 참고.
- **#66103**(사용자 등록) — C5와 같은 `WP_Font_Face` 클래스. 티켓은 분리 유지.

**결정(사용자, 2026-09-16):** Trac 티켓마다 댓글을 달지 않는다. 위 내용은 Gutenberg 이슈 하나에 모은다 — Axismundi Emoji 구현과 Core를 비교하고, GitHub 릴리스를 설치하는 Playground 데모(`axismundi-emoji/wporg-assets/blueprints/release.json`)와 관련 이슈·티켓을 모두 링크한다. wp.org 승인이나 SVN 블루프린트를 기다리지 않는다. C2·C5 개별 티켓은 그 이슈 뒤에 필요하면 연다.

같은 이슈에 넣을 Gutenberg·Core 맥락(번호·상태 확인 2026-09-16):
- Gutenberg #1678 (closed) Reimplement character map — 2017.
- Core #49885 (closed, wontfix, Editor) Introduce an option to add emoji when writing a post — OS 이모지 입력기가 있다는 이유.
- Gutenberg #75144 (open) Add emoji reactions as a first class comment type — Notes.
- Gutenberg PR #76767 (open) 제한된 반응 5개, PR #78176 (open, stacked) 전체 검색 picker: Emojibase 28개 locale 데이터를 같은 origin에서 lazy fetch, `Composite` 키보드 탐색, `speak()` 검색 결과 안내, 피부색 listbox, 자주 쓴 이모지(preferences), 저장 키 = 소문자 hex 코드포인트(`FE0F` 제거). picker는 `packages/editor/src/components/collab-sidebar/` 안(아직 범용 컴포넌트 아님), trunk 미반영.
- Core #64638 (7.2, gutenberg-merge) Register emoji reactions comment meta for Notes.
- Core #66104 (7.2, reviewing, `has-patch commit`) — 사용자 티켓.
- Core #44001 (Awaiting Review, has-patch, Privacy) oEmbed two click / local emoji scripts, wordpress-develop PR #12252 (open) "Emoji: serve image assets locally by default".
- Playground 데모 관찰(앱 내 Chromium, WordPress latest): Core 검사 `emoji: false, flag: false`(#66104) → 국가·subdivision 깃발은 Noto 폰트, 나머지는 WordPress 이미지.

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
