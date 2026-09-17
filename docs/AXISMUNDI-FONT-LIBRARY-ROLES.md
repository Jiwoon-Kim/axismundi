# Font Library: face, usage, activation

> 상태: 설계 기준 (2026-09-18). 업스트림 제안(Gutenberg #82830·#82848·#73694)과 CJK Provider·Emoji·아이콘 폰트 작업의 공통 전제.
>
> 관련: [UPSTREAM-FONT-LIBRARY-METADATA.md](UPSTREAM-FONT-LIBRARY-METADATA.md)(#82848), [AXISMUNDI-EMOJI-UNICODE.md](AXISMUNDI-EMOJI-UNICODE.md), [UPSTREAM-GUTENBERG-EMOJI-SYSTEM.md](UPSTREAM-GUTENBERG-EMOJI-SYSTEM.md)(#83032), [Core Trac #66103](https://core.trac.wordpress.org/ticket/66103)(\[63653\]로 7.2 반영).

## 1. 왜 필요한가

Axismundi는 Font Library에 올릴 수 있는 특수 목적 폰트를 세 종류 제공한다.

| 제공 | 폰트 | 실제 동작 | Font Library에서 보이는 것 |
|---|---|---|---|
| Korean·Japanese·Traditional Chinese Provider | Noto Sans/Serif KR·JP, Noto Sans TC 서브셋 | 플러그인 CSS가 `:lang()` 안에서 테마 스택의 CJK 자리(`--axismundi-cjk-sans`)를 채우고, `unicode-range`가 걸린 `@font-face`로 해당 문자만 맡는다. 설치 없이 자동 적용 | 설치 가능한 컬렉션 → "Install" |
| Emoji | Noto Color Emoji COLRv1 (full, flags subset) | 어댑터가 브라우저 지원을 검사해 못 그리는 profile만 폰트로 감싼다 | 설치 가능한 컬렉션 → "Install"(설치하면 본문 글꼴 프리셋이 됨) |
| 테마 아이콘 폰트 | Material Symbols Outlined | 테마 theme.json 프리셋, 아이콘 블록이 ligature로 사용 | 본문 글꼴 목록에 함께 나옴 |

세 경우 모두 Font Library가 "이미 적용 중"과 "설치할 수 있음"을 구분하지 못하고, 본문 글꼴과 특수 목적 폰트를 구분하지 못한다. 이 문서는 그 경계를 정한다.

## 2. 세 축

| 축 | 질문 | 누구의 속성인가 | 예 |
|---|---|---|---|
| **Face** | 파일이 어떻게 그리는가 | face 레코드 | `src`, `fontWeight`, `fontStyle`, `unicodeRange`, `fontVariationSettings` |
| **Usage** | 이 family가 어떤 소비자에게 적합한가 | family 레코드 | `emoji`, `icon` |
| **Activation** | 이 사이트에서 왜·어디에 적용되는가 | 사이트(테마·플러그인·Typography 설정) | 사용자 설치 / Core 번들 / provider 자동 적용, 대상 스택, 언어, 우선순위 |

결정:

- **CJK 대체는 usage가 아니다.** Noto Sans KR은 단독 본문 글꼴도 될 수 있고, 어떤 테마에서는 Roboto Flex의 한국어 보완재도 된다. "Roboto Flex 뒤에, `lang=ko`에서, 한글 범위만"은 Noto의 속성이 아니라 사이트의 적용 관계다. → Activation 축(Gutenberg #73694와 연결).
- **첫 usage는 작게.** `usage: { "emoji": {}, "icon": {} }`. 일반 텍스트 글꼴은 `usage`를 두지 않는다(미선언 = 지금과 같은 동작). 배열이 아니라 object인 이유: 역할마다 나중에 선언적 정보를 붙일 수 있다. 첫 제안에서는 빈 object.
- **`emoji`와 `icon`은 본문 글꼴 선택기에서 숨기는 근거**가 되지만, 소비자 모델은 합치지 않는다(§5).
- **대체 글꼴의 숨김은 family의 성질이 아니라 배정의 성질이다.** 같은 Noto Sans KR이 한 사이트에서 두 가지로 쓰일 수 있다: 관리자가 본문 글꼴로 설치한 family(선택기에 보여야 한다)와, Roboto Flex 뒤에 `lang=ko`·한글 범위로 자동 적용된 배정(선택기에 독립 family로 나오면 안 된다). 그래서 `pickable: false` 같은 family 전역 표시는 너무 거칠다. 규칙은 **자동 대체 배정은 선택 가능한 family를 만들지 않는다**이다. 배정이 만든 face와 스택 변경은 렌더링에는 참여하되 `settings.typography.fontFamilies`의 선택지에는 들어가지 않는다.

```json
{
	"fallback": {
		"family": "noto-sans-kr",
		"after": "roboto-flex",
		"languages": [ "ko" ],
		"unicodeRange": "U+AC00-D7A3, ..."
	}
}
```

  이 모델이면 세 경우가 한 규칙으로 정리된다: CJK는 배정이라서 숨고, 자동 이모지 provider도 배정이라서 숨고, 아이콘 폰트는 `usage.icon`이라 Typography 선택기의 소비자가 아니다. `usage`는 소비자 필터이고, 숨김은 배정의 결과다. 둘을 하나의 `hidden` 플래그로 묶으면 Noto를 본문 글꼴로도 쓰려는 경우가 깨진다.
- **`fontVariationSettings`는 string과 object를 모두 받는다.** 새 UI의 기준 형태는 object(`{"FILL":1,"wght":400}`), CSS 경계에서만 `"FILL" 1, "wght" 400`으로 직렬화한다. string은 계속 허용한다(기존 theme.json). \[63653\]이 PHP 연관 배열 직렬화를 고쳤으므로 Core에 구조화된 경로가 이미 있다는 근거가 된다. string을 깨도 된다는 근거는 아니다.

## 3. 네 층: 선언 / 파일에서 읽는 값 / 검증 / 런타임

| 층 | 정하는 쪽 | 위치 | 예 |
|---|---|---|---|
| 선언 | 등록하는 쪽 | Font Library 메타데이터(공개) | `usage`, `unicodeRange`, 아이콘 주소 방식 |
| 파일에서 읽는 값 | 폰트 파일 | 업로드 시 읽음(저장은 후속 과제) | 가변 축(`fvar`), 색 형식, `cmap` 범위 |
| 검증 | 빌드·CI·설치 검사 | 공개하지 않음 | `COLR/CPAL/GSUB/cmap` 존재, HarfBuzz 합성, 선언 범위와 `cmap` 비교 |
| 런타임 | 브라우저·소비자 | Font Library 밖 | 네이티브 이모지 지원, COLRv1 지원(font probe), `:lang()` |

원칙:

- **OpenType 테이블 목록은 공개 메타데이터가 아니다.** 파일에 이미 있는 사실을 JSON에 두 벌로 두면 어긋나고, 플러그인이 적은 값을 믿을 근거도 없으며, 실제로 그릴 수 있는지는 브라우저가 정한다. Emoji의 RGI 합성은 지금처럼 manifest + HarfBuzz 검증(`scripts/build-unicode-font-manifest.py --verify`)으로 둔다.
- **`cmap`은 적용 범위가 아니다.** `cmap`은 "파일이 어떤 글리프를 갖는가", `unicodeRange`는 "이 face를 어떤 문자에 후보로 쓸 것인가"라는 정책이다. 미래 API가 `cmap`에서 지원 범위를 읽더라도 선언된 `unicodeRange`를 자동으로 덮어쓰면 안 된다. 반례가 KR Provider다(§6).

### Media Library 선례

| | Media Library | Font Library (현재) |
|---|---|---|
| 선언 | 제목·캡션·대체 텍스트 | `wp_font_face` `post_content`의 JSON 설정 |
| 파일에서 읽는 값 | 업로드 시 서버가 `wp_generate_attachment_metadata()`로 만들어 `_wp_attachment_metadata`에 따로 저장(크기, 썸네일, `wp_read_image_metadata()`의 EXIF·IPTC, getID3의 길이·코덱). 필터로 확장, `wp_update_attachment_metadata()`로 재생성 | 따로 저장하지 않음. 메타는 `_wp_font_face_file`(경로)뿐 |
| 파일을 읽는 곳 | 서버 PHP | 업로드 화면 브라우저 JS(`packages/global-styles-ui/src/font-library/upload-fonts.tsx`, lib-font). name 16/1, name 2(italic), `OS/2` 굵기, `fvar`의 `wght`만 읽어 선언 필드에 섞는다. 다른 축은 버린다 |

가져올 것: 선언과 파생 값을 다른 곳에 두기, 업로드 시 생성·필터 확장·재생성. 첫 PR에는 넣지 않는다. Core PHP에는 폰트 파서가 없고(미디어는 GD·Imagick·getID3), 브라우저가 읽은 값은 검증이 아니며, 재생성 경로까지 한꺼번에 열리기 때문이다. 순서: 선언적 `usage` → 축 object → provider 관계 → (그 뒤) 파생 메타데이터.

## 4. 역할별 정리

| | CJK 대체 | Emoji | 아이콘 폰트 |
|---|---|---|---|
| usage | 없음(일반 텍스트 글꼴) | `emoji` | `icon` |
| face 선언 | `unicodeRange` = 사이트 정책 | profile별 파일(flags subset / full) | 축(`FILL`, `wght`, `GRAD`, `opsz`) |
| 파일에서 읽는 값 | `cmap` 범위 | 색 형식 | `fvar` 축의 min/max/기본값 |
| 검증 | 선언 범위 vs `cmap` | 테이블 존재, RGI 합성 | ligature면 `GSUB liga`, 축 UI면 `fvar` |
| 런타임 | `:lang()` | 네이티브 감지 → font probe → 이미지 | 아이콘 블록이 축 object 전달 |
| activation | provider 자동 적용 + 대상 스택·언어 | provider 자동 적용 또는 Core 번들 | 테마·플러그인 등록 |
| 본문 글꼴 선택기 | 설치하면 보임(독립 family) | 숨김 | 숨김 |
| 소비자 | 테마 글꼴 스택 | Emoji 시스템 | Icon Registry / `core/icon` |

## 5. Emoji와 아이콘은 다르다

- **Emoji:** Unicode grapheme 자체가 의미이자 저장값(🇰🇷, 🧑‍🩰). 폰트는 그 텍스트를 어떻게 그릴지만 맡는다. 경로 `Unicode → glyph`, 폰트 대체가 자연스럽다. Emoji 시스템이 RGI 시퀀스·picker·지원 감지·대체 순서를 소유한다. Font Library는 적합한 폰트 자원을 제공할 뿐 감지·순서를 소유하지 않는다.
- **아이콘:** `search`, `home` 같은 ID가 의미이자 저장값이고, SVG path나 font glyph는 렌더링 구현이다. 경로 `icon ID → source → glyph/SVG`. Icon Registry가 `search` → glyph 매핑을 소유한다. ligature 방식(Material Symbols)과 codepoint 방식(Dashicons, PUA)이 공존할 수 있으므로 주소 방식은 Registry 쪽 정보다.
- **UX:** Google Fonts처럼 본문 글꼴(Roboto), 특수 목적 글꼴(Noto Color Emoji), 아이콘 목록·검색(Material Symbols)을 나눈다. 아이콘은 별도 화면·picker·Registry가 필요하지만, 아이콘 폰트 파일을 위한 별도 저장·설치 시스템은 필요 없다. 파일·face·축은 Font Library가 제공한다.

## 6. 근거 (측정)

### 6.1 `unicodeRange` 보존 실험 (2026-09-18, wp-env WordPress 7.1)

REST로 family → face(`unicodeRange` 포함) 생성, UI와 같은 방식으로 사용자 전역 스타일 `typography.fontFamilies.custom`에 등록, `wp_print_font_faces()` 출력 확인. `src`는 URL(WP-CLI는 브라우저 업로드를 흉내 낼 수 없음; UI는 파일을 업로드하고 같은 JSON을 보낸다). 실험 데이터 삭제·전역 스타일 복원 확인.

| 단계 | 결과 |
|---|---|
| face 생성 → `post_content` | `unicodeRange` 유지 |
| REST 응답 `font_face_settings` | 유지 |
| 병합된 프리셋 | 유지 |
| 출력 CSS | `unicode-range:U+AC00-D7A3, U+1100-11FF, U+3130-318F, U+A960-A97F, U+D7B0-D7FF;` |

결론: face 층은 충분하다. 부족한 것은 activation 관계다. 설치한 family는 `"…", sans-serif` 독립 프리셋이 되어, 본문 글꼴로 고르면 한글은 Noto, 라틴은 Roboto Flex가 아니라 `sans-serif`로 떨어진다. 테마의 "Roboto Flex + 한글만 Noto(ko)" 조합은 Font Library로 표현할 수 없다.

설치 흐름(Gutenberg `packages/global-styles-ui/src/font-library/context.tsx` `installFonts()`, `utils/index.ts` `makeFontFacesFormData()`): 컬렉션 face 파일을 브라우저가 내려받아 업로드하고 `font_face_settings` JSON은 컬렉션 값을 그대로 보낸다. 활성화에는 REST가 돌려준 설정을 쓴다.

### 6.2 선언 범위와 `cmap` (fontTools, 2026-09-18)

| 파일 | 글리프 코드포인트 | 선언 범위 | 둘 다 | 선언했지만 파일에 없음 | 파일에 있지만 선언 밖 |
|---|---:|---:|---:|---:|---:|
| Noto Sans KR | 12,074 | 11,636 | 11,623 | 13 | 451 (U+0250 미만 라틴 191) |
| Noto Serif KR | 12,074 | 11,636 | 11,623 | 13 | 451 (라틴 191) |
| Noto Sans JP | 14,128 | 22,544 | 14,128 | 8,416 | 0 |
| Noto Serif JP | 14,125 | 22,544 | 14,125 | 8,419 | 0 |
| Noto Sans TC | 16,901 | 28,992 | 16,901 | 12,091 | 0 |

- KR: 파일에 라틴이 있지만 선언은 한글만 → 라틴은 Roboto가 계속 맡는다. `cmap`에서 범위를 자동 추론하면 이 정책이 깨진다.
- JP·TW: 선언이 파일보다 넓다. 브라우저가 글자 단위로 다음 글꼴로 넘어가므로 깨지지는 않지만, 검증 층에서 경고할 차이다(후속 과제: 선언 범위를 파일 커버리지로 좁힐지 결정).
- 세 파일 모두 가변 축은 `wght` 하나.

### 6.3 원본과의 대조, 그리고 버전 관리 (2026-09-18)

`source.txt`에는 저장소 폴더 URL과 원본 파일 이름만 있었고 커밋·해시·빌드 스크립트가 없어서, 위 차이가 원본 탓인지 우리 서브셋 탓인지 가릴 수 없었다. google/fonts 커밋 `a54f7446`의 원본 다섯 개를 받아(git blob 해시 대조) 직접 비교한 결과:

| 폰트 | 원본 버전 = 우리 파일 | 선언 범위에서 우리가 잃은 글자 | 원본에도 없는 글자 |
|---|---|---:|---:|
| Noto Sans/Serif KR | 같음(2.004-H2 / 2.003-H1) | 0 | 0 |
| Noto Sans JP / Serif JP | 같음 | 0 | 8,357 / 8,360 |
| Noto Sans TC | 같음 | 0 | 12,030 |

원본 TTF는 2022·2024년 이후 바뀌지 않았다. 즉 서브셋 실수도, 상류 업데이트 누락도 아니고, Noto 지역판이 블록 전체를 담지 않을 뿐이다. 앞서 Google Fonts CSS와 비교했을 때 생긴 차이는 Google이 제공하는 빌드가 저장소 파일과 다르기 때문이며, 커버리지 근거로 쓸 수 없다.

### 6.4 Core는 face의 `fontFamily`를 프리셋 이름으로 덮어쓴다 (2026-09-18)

"대체 글꼴을 선택기에서 숨긴다"를 오늘의 계약으로 할 수 있는지 재 봤다. `theme.json`의 face에는 각자 `fontFamily`가 있으므로, Roboto Flex 프리셋 안에 Noto face를 넣으면 프리셋은 하나라서 선택기에 Noto가 나오지 않는다. wp-env에서 `wp_theme_json_data_theme` 필터로 넣고 `wp_print_font_faces()` 출력을 본 결과:

```css
@font-face{font-family:"Roboto Flex";...;src:url('.../probe-kr.woff2');unicode-range:U+AC00-D7A3;}
```

`WP_Font_Face_Resolver::convert_font_face_properties()`가 face의 `font-family`를 프리셋 `fontFamily`의 첫 이름으로 덮어쓴다. 동작은 원하는 대로지만(한글만 그 파일이 맡음), 파일과 CSS의 이름이 어긋나고 관리 화면에서는 Roboto Flex의 변형처럼 보인다. 즉 오늘 가능한 유일한 "숨김"은 이름을 속이는 것이며, 이것이 배정 계약이 필요하다는 증거다. REST·Font Library 경로는 face의 이름을 그대로 쓴다(§6.1).

이 확인 과정에서 CJK 폰트에 Emoji 같은 재현 가능한 빌드가 없다는 점이 드러나 파이프라인을 만들었다(세 Provider 공통, `scripts/build-noto-cjk.py` 동일 사본):

- 입력(사람이 작성) `scripts/noto-cjk.json`: 상류 저장소·커밋·라이선스, 원본별 경로·크기·SHA-256, 적용할 `unicodeRange`. 원본은 저장소 밖(`D:/axismundi-assets/noto-cjk/<commit>/`).
- 생성물: `assets/fonts/manifest.json`(파일별 바이트·SHA-256·코드포인트 수·폰트 버전·축), 각 `source.txt`, 그리고 `fonts.css` 폰트 URL의 `?ver=<sha12>`.
- `--check`: 생성물이 파일·설정과 맞는지, CSS 범위가 설정과 같은지, 세 사본이 동일한지.
- `--verify`: 원본과 비교해 **선언 범위 안에서 잃은 글자 0**, 범위 밖 글자 0, 축·버전 유지. 원본에 없는 범위 코드포인트는 정보로만 보고.
- `--rebuild-check`: 다시 빌드해 바이트가 같은지(`recalcTimestamp=False`로 재현성 확보). 세 플러그인 모두 PASS.

첫 재빌드 결과: KR은 선언 밖 라틴 451자가 빠져 Sans 1,247,668 → 1,168,500 B, Serif 2,157,104 → 2,057,712 B. JP·TC는 커버리지 그대로이며 파일만 재생성됐다. 브라우저 확인: 새 파일이 로드되고 `wght` 축이 살아 있으며(픽셀 측정), 라틴은 여전히 매칭되지 않는다. 폰트 URL에 해시가 없으면 갱신 후에도 브라우저가 예전 파일을 쓰는 것도 확인해, `?ver=<sha12>`를 생성물에 포함시켰다.

## 7. 실행 순서

1. 이 문서.
2. CJK Provider 세 개(KR·JP·TW)를 같은 계약으로 수정:
   - 컬렉션 `fontFace`에 각 CSS와 **같은** `unicodeRange`(CSS와 한 곳에서 관리하거나 audit으로 일치 확인).
   - 설명 문구: "Korean fallback is active automatically for Korean text. Installing this family makes its Korean-script face available in the Font Library; it does not recreate the theme's complete Roboto Flex fallback stack." (언어별로 맞춤)
   - readme·audit 갱신, 세 플러그인 patch release(KR·JP는 wp.org SVN, ZIP 기준).
   - Emoji는 이 묶음에 넣지 않는다. Emoji 컬렉션은 full 폰트를 설치하는 경로이고, 런타임 flags/full profile 범위는 어댑터 정책이라 계약이 다르다.
3. 업스트림(초안 → `verify_upstream_comment.py` → 게시):
   - #82830: `fontVariationSettings` string | object, \[63653\] 근거.
   - #82848: `usage`를 object로, `emoji`·`icon` 전용 family는 본문 글꼴 선택기에서 제외.
   - #73694: CJK Provider를 대체 관계(대상 스택·언어·범위) 사례로. §6.1 결론이 재현 근거.
4. Gutenberg draft PR: (1) `usage` 보존(스키마·REST·정리 네 곳) + 선택기 필터, (2) 구조화된 축 값, (3) provider activation / 대체 관계. 각 PR에 한계·열린 질문·"Maintainer edits are welcome".
5. Core: 번들 이모지 폰트(Twemoji COLRv1, `wp-includes/fonts/` — Dashicons 선례)와 "네이티브 → 폰트 → 이미지" 연결. **4의 usage·activation 합의 뒤에.** 먼저 넣으면 Emoji도 Library에는 "Install"만 보이고 실제 대체는 따로 도는 이중 구조를 반복한다.
6. Icon Registry의 font-glyph source 연결(축 UI는 4-(2) 재사용).

## 8. 열린 질문

- provider가 "자동 적용 중"을 선언하는 등록 방법(Core에 없음). `wp_theme_json_data_theme` 필터로 주입하면 선택 가능한 프리셋이 되어 대체 관계를 표현하지 못한다.
- 대체 배정을 어디에 둘지: Typography 설정(프리셋의 fallback), provider 등록, 둘 다. 배정이 선택지를 만들지 않는다는 규칙은 그대로 두고, 저장 위치만 정하면 된다.
- 파일에서 읽는 값(축 목록)을 저장할지, 쓸 때마다 읽을지. 컬렉션 설치 경로에서 채울 수 있는지.
- JP·TW 선언 범위를 파일 커버리지로 좁힐지.
