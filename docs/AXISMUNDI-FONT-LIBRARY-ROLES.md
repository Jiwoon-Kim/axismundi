# Font Library: face, usage, activation

> 상태: 설계 기준 (2026-09-18). 업스트림 제안(Gutenberg #82848·#73694, 2026-09-19 게시한 축 모델 이슈 #83148, PR #83127·#83128·#83141)과 CJK Provider·Emoji·아이콘 폰트 작업의 공통 전제. #82830은 이슈가 아니라 아이콘 토론(Discussion)이다. 그 댓글의 `fontVariationSettings` object 예시는 `wght`·`opsz`를 담았는데, #83148이 등록 축을 전용 CSS 속성으로 보내도록 정정했다(안내 댓글 discussioncomment-18505721).
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
- **대체 배정은 프로바이더가 소유한다. 사용자 권한으로 일반화하지 않는다.** 대체를 조립하려면 언어별 유니코드 블록, 파일의 실제 `cmap` 커버리지, 스택 안의 위치와 범위(잘못 잡으면 불필요한 다운로드나 Latin 가로채기)를 알아야 한다. 사이트 운영 판단이 아니라 폰트 엔지니어링이다. 그래서 파일·언어·`unicodeRange`·삽입 위치는 프로바이더가 함께 책임지고, 사용자는 켜고 끈다(지금은 플러그인 활성화). 선택이 필요해져도 "한국어 대체: Noto Sans KR / 끔" 같은 언어 단위까지다. Typography에 사용자가 폴백을 조립하는 UI는 두지 않는다.
- **플러그인 폰트의 두 경로를 나눈다.** 사용자가 설치해 볼 글꼴은 기존 컬렉션(`wp_register_font_collection`) 그대로다. CJK 대체·이모지·아이콘처럼 플러그인이 지금 적용 중인 런타임 제공자는 별도 선언 경로가 필요하다. 업스트림에 요구할 최소 계약:
  1. 활성 플러그인이 현재 적용 중인 face와 배정을 선언할 수 있다.
  2. Font Library가 이를 `PLUGIN` 출처로 읽기 전용 표시한다(비활성화하면 사라진다).
  3. 자동 대체 배정은 Typography 선택지를 만들지 않는다.
  4. 실제 범위와 우선순위는 플러그인이 소유한다.

  이것은 옛 Fonts API의 등록·강제 활성화를 되살리는 것이 아니라, 그때 "Library 안에서 하라"며 비워 둔 통합을 역할과 배정까지 포함해 채우는 것이다(§8 이력). 순서는 플러그인에서 작동을 보이고 → 선언 형식을 굳히고 → 그 형식을 Core 등록 경로로 제안한다.
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
3. ~~업스트림 댓글 → draft PR 세 개~~ → 2026-09-18 재정리. 한 API로 합치지 않고 다섯 묶음으로 나눈다. 순서:
   1. **A. provider registry (Gutenberg draft PR).** `lib/experimental/font-providers/`, Experiments 토글 없음(등록 provider가 없으면 UI·출력 모두 기존과 같다). 범위는 등록(`wp_register_font_provider()`), `GET /wp/v2/font-providers`, Library의 `PLUGIN` 읽기 전용 묶음, 프런트·에디터 iframe `@font-face` 출력(`wp_print_font_faces( $fonts )`)까지. **넣지 않는 것:** `axes`, `usage`, 대체 배정, Typography UI, theme.json 병합. 이름은 옛 Fonts API와 겹치지만 역할이 다르다(옛 provider=원격 전송 방식, 이번=활성 확장이 face 선언을 제공)는 점을 PR에 명시한다.
   2. **B. 축.** face의 `axes`(선언: 테마·플러그인 / 설치 폰트는 `fvar`에서) → Core가 `font-weight`·`font-stretch`·`font-style` 범위 descriptor를 파생. 옛 `fontWeight: "100 1000"`은 `axes.wght`가 없을 때의 호환 입력. Library는 가변 face를 variant 묶음이 아니라 face 하나와 축 범위로 표시. `styles.typography.fontVariationSettings`(object)는 **새 스타일 속성**이고 face descriptor의 동명 string 필드와 섞지 않는다. `wdth`용 `fontStretch` 스타일 속성도 이 묶음. UI는 Global Styles Text와 블록 Typography가 같은 컨트롤을 공유(paragraph는 첫 소비자), `wght`는 기존 Weight 컨트롤이 연속값으로 소유하고 variation 컨트롤은 전용 속성이 없는 축(GRAD·FILL·수동 opsz)만. 이 구분은 이슈 #83148로 게시(#82830 토론 댓글의 object 예시를 정정).
   3. **C. 아이콘.** `usage`는 family보다 renderer capability에 가깝다(#82848과 연결, A에 넣지 않음). Icon Source/Registry가 ID→표현 매핑(ligature 이름·PUA·SVG)을 소유하고, Font Library는 파일과 축만 제공. 임의 face를 아이콘 소스로 쓰지 않는다.
   4. **D. 이모지 renderer/provider registry.** 감지·font probe·이미지 폴백·우선순위는 클라이언트 런타임 문제라 Font Library가 정하지 않는다. Core 번들 Twemoji COLRv1(`wp-includes/fonts/`, Dashicons 선례)은 A의 등록 경로로 Core 자신이 provider가 되어 Library에 보이고, 교체(Noto 등)는 renderer registry의 우선순위로. Axismundi Emoji가 시제품.
   5. **E. 대체 배정.** Core가 배정을 모아 한 번만 출력하고, 테마가 스택에서 명시적으로 참조하는 슬롯(`sans-serif`·`serif`·`monospace`)에 기여시킨다. 플러그인이 전역 변수를 직접 덮어쓰지 않는다(여러 provider 충돌). 배정 형태 예: `{ "target": "sans-serif", "language": "ko", "unicodeRange": "…", "fontFamily": "Noto Sans KR" }`. 선택지를 만들지 않고 inspector에는 "활성 폴백"으로만. CJK에 한정하지 않는다(한자 때문에 language는 필요). #73694에 모델 제안이 코드보다 먼저.
4. 축 검증은 스택 사이에서 하지 않는다. Roboto Flex(주)와 Noto Sans KR(폴백)의 축이 달라도 정상이다: 각 폰트는 자기 축만 반영하고 없는 축은 무시, 범위 밖 값은 잘린다(한글은 wdth·GRAD 없이, wght 1000→900). 축 UI는 주 face의 `axes`만 근거로 한다. Core는 선언을 믿는다. 파일과 선언의 대조는 작성자 빌드·CI의 선택 도구다(예: `build-noto-cjk.py --verify`).

## 8. 열린 질문

- provider가 "자동 적용 중"을 선언하는 등록 방법(Core에 없음). `wp_theme_json_data_theme` 필터로 주입하면 선택 가능한 프리셋이 되어 대체 관계를 표현하지 못한다.
- ~~대체 배정을 어디에 둘지~~ → provider 등록으로 결정(§2). Typography 설정에 두지 않는다.
- 파일에서 읽는 값(축 목록)을 저장할지, 쓸 때마다 읽을지. 컬렉션 설치 경로에서 채울 수 있는지.
- JP·TW 선언 범위를 파일 커버리지로 좁힐지.
- 플러그인 출처가 없다. Library는 활성 폰트를 출처로 묶는데 출처가 `theme`과 `custom` 두 개뿐이다(`installed-fonts.tsx`). 그래서 테마 폰트는 이미 있는 폰트로 Library에 나오고, 플러그인이 제공하는 폰트는 컬렉션 탭에 Install 대상으로만 나온다. 필터로 주입하면 THEME 아래에 테마 폰트처럼 섞인다. KR 프로바이더의 자동 폴백은 이미 작동하는데 UI는 설치를 권하는 것도 이 때문이다. `plugin`(provider) 출처가 생기면 Library에 THEME 옆 묶음이 되고, 컬렉션 탭은 추가로 받을 폰트에만 쓰인다.
  - 경로마다 보여 줄 수 있는 것이 다르다. 모든 폰트를 업로드 파일처럼 다루지 않고, 각 경로가 가진 선언을 그대로 보여 준다.

    | 경로 | 파일 | Library에 보여 줄 것 |
    |---|---|---|
    | 활성 테마 | 테마 폴더(디스크) | theme.json `typography` 선언, 읽기 전용 |
    | 활성 플러그인 | 플러그인 폴더 | 플러그인 선언, `PLUGIN` 출처, 읽기 전용 — **선언 경로가 없음** |
    | 업로드·컬렉션 설치 | `wp-content/fonts`(서버를 거침) | 설치 메타데이터: 크기, 형식, `fvar` 축, `cmap` 범위 |
    | 컬렉션(미설치) | 없음 | 카탈로그. 여기만 Install |

    가변 축도 같다. 선언 화면은 `fontWeight: "100 900"` 같은 CSS 적용 범위를, 설치 파일 화면만 `fvar`의 실제 축 목록을 보여 준다. 두 값을 한 필드에 합치지 않는다.
  - 이력(2026-09-18 확인). Gutenberg 플러그인의 Fonts API(`wp_register_fonts()`, `wp_enqueue_fonts()`, 옛 `wp_register_font_provider()`)는 Core에 들어간 적이 없다. 2023-06-19 범위 변경([#41479 코멘트](https://github.com/WordPress/gutenberg/issues/41479#issuecomment-1597915077), [#51769](https://github.com/WordPress/gutenberg/issues/51769))으로 Font Face(theme.json 병합 데이터를 읽기만 해서 `@font-face` 출력)가 대체했고, 16.3에서 기능 제거([#52485](https://github.com/WordPress/gutenberg/pull/52485)), 파일 삭제([#57972](https://github.com/WordPress/gutenberg/pull/57972)), BC 스텁 제거([#82813](https://github.com/WordPress/gutenberg/pull/82813), 2026-09-12). 그 코멘트의 약속과 공백:
    - "Plugins will no longer interact with the Fonts API. Instead, they will integrate directly into the Font Library (once that capability exists)."
    - 사용자에게 권하는 폰트("register their fonts for user consideration")는 Stage 2([#53307](https://github.com/WordPress/gutenberg/issues/53307))에서 컬렉션 API로 구현됐다. 의도된 설계다.
    - 강제 활성화는 저수준 API가 아니라 Library "안에서" 하라고 했고, Library를 건너뛰면 사용자가 폰트를 보지도 관리하지도 못한다고 경고했다. 그 기능은 만들어지지 않았다(Stage 3 [#53926](https://github.com/WordPress/gutenberg/issues/53926) 2023-10 Not planned). KR 폴백과 Emoji가 지금 그 경고 상태다.
    - 당시 모델은 모든 폰트가 선택지에 나온다고 전제했다. 선택지에 나오면 안 되는 폰트(대체·이모지·아이콘)는 범위 밖이었다. 업스트림 제안에는 usage/activation 구분의 근거가 함께 가야 한다.
    - 이름: 옛 `wp_register_font_provider()`는 전달 방식(local/Google) 개념이었고 #51769에서 불필요로 정리됐다. 같은 이름을 쓰면 폐기된 모델의 부활로 읽힌다. 다른 이름을 쓴다.
- 축 지원 범위와 CSS 선언을 분리한다. `fontVariationSettings`는 값을 정할 뿐 지원 범위를 선언하지 않는다(`"wght" 400`은 400에 고정). 등록 축은 CSS 범위 descriptor가 있다: wght→`fontWeight` `"100 900"`, wdth→`fontStretch` `"75% 125%"`, slnt→`fontStyle` `"oblique 0deg 10deg"`. GRAD·FILL 같은 사용자 정의 축은 지원을 선언할 CSS 수단이 없다. 파일이 실제로 무엇을 지원하는지는 `fvar`에만 있는데, 업로드는 wght만 읽어 `fontWeight` 문자열로 바꾸고 나머지를 버린다(Roboto Flex 13축 → "1 variant"). 안: `axes: [{ tag, min, default, max }]`를 파일에서 읽은 층(§3)에 두고 CSS로는 출력하지 않는다. UI는 `axes`로 컨트롤을 만들고, 고른 값은 등록 축 속성이나 `fontVariationSettings`(object, #83148)로 쓴다. `fontWeight` 범위도 `axes`의 wght와 대조해 검증 층에서 확인할 수 있다.
- 정적 face 식별 키가 weight + style뿐이다(`mergeFontFaces`, `checkFontFaceInstalled`, `fonts-outline`). stretch만 다른 face는 병합 때 서로 덮어쓰고, 같은 family의 가변 face(`"100 700"`)와 정적 인스턴스(`"400"`)는 서로를 모른다(Google Fonts 탭에서 테마의 Roboto Mono가 미설치로 보임).
- 설치는 face 단위, 삭제는 family 단위다. 컬렉션에서 Roboto 400 normal과 700을 따로 설치할 수 있지만, Library의 Delete는 `uninstallFontFamily`로 family 전체를 지운다. face 하나를 빼려면 체크 해제(비활성)뿐이고, 파일과 `wp_font_face` 글은 남는다. REST에는 `DELETE /wp/v2/font-families/<id>/font-faces/<id>`가 있으나 UI가 쓰지 않는다. 관련 기존 이슈는 찾지 못했다(2026-09-18 검색).
- face의 `fontVariationSettings`(string)는 역할이 셋으로 뭉쳐 있다: 파일이 지원하는 축·범위(capability), 그 face의 기본 좌표(descriptor), 요소에 적용할 값(style). WordPress에 이 필드밖에 없어서다. 분리: `axes`(capability, §7 B) / face `fontVariationSettings`(descriptor, 호환 string 유지, 내부 정규형 object) / `styles.typography.fontVariationSettings`(style, 새 속성). 가변 face의 `font-weight` 범위 descriptor는 CSS 출력에 계속 필요하다(브라우저 face 매칭) — 원천은 `axes.wght`, 출력은 파생. 주의: `@font-face`의 `font-variation-settings` descriptor는 Safari 미지원, Chrome 140부터, Firefox 62부터다(MDN browser-compat-data 8.1.2, 2026-09-18 확인). face 기본 좌표에 기대는 설계는 Safari에서 무시되므로, 실제 값은 style 층에 두는 쪽이 안전하다.
- 가변 축 UI 결정(2026-09-18, Gutenberg Font variations draft): `wght`는 슬라이더 + 숫자, 저장 `fontWeight`(측정: `font-variation-settings`의 `wght`는 상속되어 `<strong>`의 `bolder`를 이기고, wght 축 없는 폴백 — 시스템 폰트, KR 서브셋 밖의 한자 — 에는 닿지 않는다). 첫 PR에서는 `ital`을 축으로 다루지 않고 기존 Appearance의 Style 선택지를 그대로 둔다(저장 `fontStyle`). I 토글과 스위치를 시험했으나, `ital`은 정적 이탤릭 face 선택과 같은 `font-style: italic` 경로로 정리할 문제라 뺐다. `slnt`는 face가 `fontStyle: "oblique <min>deg <max>deg"` 범위를 선언했을 때만 나타나는 슬라이더로 별도 PR, 저장 `fontStyle: "oblique -10deg"`. Weight UI는 Font size처럼 제목 줄 토글로 preset 드롭다운(`Light (300)`, 범위 안 100 단위, 비preset 저장값 `Custom (n)`) ↔ 슬라이더 + 숫자. 100 단위 숫자 버튼 행은 빽빽하고 링크처럼 보여 폐기, `RangeControl`의 `marks`는 장식(`pointer-events: none`, `aria-hidden`)이라 클릭 preset으로 쓸 수 없다.
- 등록 축과 `fontVariationSettings`(CSS Fonts 4): 같은 축이면 `font-variation-settings`가 고수준 속성을 이긴다. 부모의 `"ital" 0`은 `<em>`의 `italic`을, `"wght" 300`은 `<strong>`의 `bolder`를 무효로 만든다(후자 측정). 그래서 등록 축 다섯 개는 전용 속성으로만 저장한다: `wght`→`fontWeight`, `ital`·`slnt`→`fontStyle`, `wdth`→`fontStretch`(최신 CSS `font-width`), `opsz`→`fontOpticalSizing`. `fontVariationSettings` object에는 GRAD·FILL 등 나머지 축만 두고, 등록 축이 들어오면 거부하거나 전용 속성으로 정규화한다. GRAD는 `<strong>`과 충돌하지 않는다(굵기는 바뀌고 GRAD는 상속 유지). 다만 `font-variation-settings`는 축 목록 전체를 덮는 한 속성이라, 하위 요소에서 한 축만 바꾸려면 나머지 커스텀 축도 다시 적어야 한다 — object 모델과 스타일 병합이 필요한 이유.
- 텍스트와 아이콘의 축 동기화(Axismundi lab `typography-axis.html`의 GRAD 공유 모델): 같은 축 이름이라도 텍스트 `wght`와 아이콘 `wght`는 별도 컨트롤. 강한 동기화는 버튼·메뉴·라벨처럼 텍스트와 아이콘이 한 UI 단위 안에서 함께 의미를 만드는 경우에만, 컴포넌트 토큰(예: `--wp--component--button--grade`)에 둘 다 바인딩한다. 전역 GRAD 하나로 모든 본문과 아이콘을 묶지 않는다. Font Library·축 메타데이터는 "GRAD를 지원한다"는 사실만 제공하고, 동기화 정책은 Button·Icon 블록 같은 소비자가 가진다.
- Appearance와 Font variations 패널의 경계(2026-09-18): Appearance는 `fontWeight`·`fontStyle`·(나중) `fontStretch` — 정적·가변 공통의 표준 CSS 서식. 가변 폰트면 Appearance 이름은 그대로 두고 안쪽만 Style 선택 + Weight(preset ↔ 슬라이더 + 숫자). "Font variations"는 `fontVariationSettings`에 저장하는 축(GRAD·XTRA 같은 커스텀 축, 수동 `opsz`) 전용 별도 패널 이름으로 남긴다(Gutenberg draft 컴포넌트는 `VariableFontAppearanceControl`로 개명). `FILL`은 `usage: icon` 폰트의 축이라 텍스트 패널에 노출하지 않고 아이콘 블록의 Font variations에만.
- 축 노출은 세 층: 능력(face `axes` — 업로드 폰트는 `fvar`에서, 테마 폰트는 테마가 선언) / 정책(`settings.typography` 쪽, 예 `fontVariations: { "roboto-flex": [ { tag, min, max } ] }` — 테마가 노출할 축과 허용 범위를 고르고, `settings.blocks.*`로 블록별로도 정할 수 있다) / 값(`styles.typography.fontVariationSettings`). Font variations 패널은 파일의 모든 축이 아니라 정책이 노출한 축만 그리고, 슬라이더 범위는 능력과 정책의 교집합. 정책에만 있고 파일에 없는 축은 무시. 이름과 모양은 4번 PR에서 정한다.
