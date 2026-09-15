# Axismundi Emoji — Unicode rendering and emoji fonts

상태: 설계 초안 (2026-09-15). §12 1단계 구현 완료(폰트 없음): `includes/unicode.php`, `assets/unicode/adapter.js`, `tests/audit-emoji-unicode.php`(30 PASS), `tests/unicode-harness/`.
대상: `products/wordpress/plugins/axismundi-emoji` (현재 0.2.0, custom emoji 전용).
관련: [AXISMUNDI-EMOJI-ARCHITECTURE.md](AXISMUNDI-EMOJI-ARCHITECTURE.md)(custom emoji), [UPSTREAM-TRAC-CANDIDATES.md](UPSTREAM-TRAC-CANDIDATES.md), [UPSTREAM-FONT-LIBRARY-METADATA.md](UPSTREAM-FONT-LIBRARY-METADATA.md)(Gutenberg #82848), [Core Trac #44001 comment:17](https://core.trac.wordpress.org/ticket/44001#comment:17).

---

## 0. 목표

dialog의 아이콘·버튼 작업과 같은 방식이다. **플러그인 구현이 곧 Core에 보여줄 설계**여야 한다.

보여줄 명제는 하나:

> Font Library에 등록된 emoji 폰트가, 브라우저가 native로 못 그리는 grapheme에 한해 Core의 s.w.org 이미지 fallback보다 먼저 쓰일 수 있다. Unicode 텍스트는 텍스트로 남고, 이미지 fallback은 마지막 안전망으로 유지된다.

이는 #44001에 남긴 주장("이모지는 텍스트다, PNG 수천 개 번들은 방향이 아니다")의 실제 구현 증거가 된다.

### 제약

- **독립 동작.** Emoji가 Activities보다 먼저 wp.org에 제출된다. Activities, Axismundi 테마, 다른 Axismundi 플러그인 없이 임의의 테마에서 동작해야 한다. 테마 font stack 계약에 기대지 않는다.
- **Core를 복제하지 않는다.** Core 감지기와 Twemoji 로더는 그대로 두고, 그 앞에 얇은 계층만 둔다. 계층의 각 부분은 Core가 흡수하면 떼어낼 수 있어야 한다.
- **플러그인 우회책과 제안을 분리해 기록한다.** Core 결함 때문에 생긴 코드는 §7.4처럼 "workaround"로 표시하고, 대응하는 Trac 후보를 적는다.

## 1. 확정된 결정

| # | 결정 | 근거 |
|---|---|---|
| D1 | Unicode catalogue의 단일 소유자는 Emoji | 독립 제출. Activities는 나중에 소비자로 바뀐다(§2) |
| D2 | Font Library collection 등록 + provider CSS + sidecar 메타데이터를 함께 쓴다 | Korean/Japanese Font Provider 선례. Core가 `usage` 같은 키를 지우므로(#82848) 용도·coverage는 플러그인이 보관 |
| D3 | Core Twemoji 이미지 fallback은 제거하지 않는다 | 폰트가 실패하는 브라우저(컬러 폰트 포맷 미지원 등)의 안전망 |
| D4 | 정책 단위는 **grapheme별 span**. `@font-face` 등록만으로는 아무것도 우선하지 않는다 | Core가 먼저 `<img>`로 바꾸면 폰트는 선택될 기회가 없다 |
| D5 | 감지는 OS/UA가 아니라 **profile별 렌더 probe** | 같은 기기에서도 앱마다, emoji 종류마다 결과가 갈린다(§4.1 실측) |
| D6 | 초기 폰트는 Noto Color Emoji, family 이름 유지 | CJK provider와 같은 OFL 관리 체계. RFN 확인은 §9 |
| D7 | 첫 profile은 `flags` 하나 | Core가 flag를 별도 capability로 다루고, C3 때문에 Core가 못 고치는 영역도 포함 |
| D8 | 렌더링 정책 3종: `auto`(native → 필요한 profile의 로컬 폰트 → Core), `font`(사이트 폰트 우선 → Core), `core`(이 계층 끔). 현재 `auto`·`core` 구현, `font`는 설계만 | `font`는 "사이트가 이모지 표현을 소유"하는 관리자 선택. native 판정 없이 RGI 전체를 full 폰트로 감싸고, 폰트 실패 시에만 Core. Core 제안에서 "Font Library family가 원격 PNG보다 먼저 실제 렌더 공급자가 된다"는 가장 선명한 사례 |
| D9 | 배포 자산 2종 = Core 감지 2종에 대응: `axismundi-noto-colrv1.woff2`(full, Core `emoji` 실패 대응), `axismundi-noto-colrv1-flags.woff2`(flags subset, Core `flag`만 실패 대응). 판정 단위(profile)는 파일보다 잘게 유지 | 둘은 범위가 겹치므로 파일마다 CSS alias(`Axismundi Emoji Full`/`Flags`)로 로드. 폰트 내부 이름과 Font Library family는 `Noto Color Emoji` 유지. full Noto v2.051은 Emoji 17 RGI 3,944개 전부 HarfBuzz 합성 성공(`.notdef` 0) |

## 2. 책임 경계

```text
Axismundi Emoji
├─ unicode/catalogue      RGI 17 데이터, 검색, 그룹 (Activities에서 이관)
├─ unicode/picker         에디터 picker의 Unicode 탭 (custom emoji 탭과 같은 UI)
├─ fonts/profiles         profile 정의 + coverage manifest (빌드 산출물)
├─ fonts/library          Font Library collection 등록 + sidecar 메타데이터
├─ fonts/provider         @font-face CSS (front, editor canvas, picker)
├─ render/adapter         probe → span 감싸기 → Core에 넘기기  (CoreEmojiAdapter)
└─ custom/…               기존 E1–E3 그대로

Axismundi Activities (나중, 별도 리팩터)
├─ EmojiReact / Like 의미, ledger, Undo, federation   — 변경 없음
└─ reaction UI는 Emoji picker capability를 소비, 선택 결과 { value }만 받음
```

- `render/adapter`는 catalogue나 Activities를 모른다. 입력은 DOM 텍스트와 profile manifest뿐이다.
- Activities의 inbound 정규화(`axismundi_act_normalize_reaction()`)는 지금도 catalogue를 조회하지 않는다. 유지한다.
- Activities에 남은 catalogue 사본은 Activities 리팩터 때 제거한다. 그때까지의 중복은 출시 순서상 감수한다.
- 기존 문서·테스트의 "Unicode는 다루지 않는다"(ARCHITECTURE §1·§9, `picker.js` 헤더, `audit-emoji-picker.php`의 `unicodeEmoji` 부재 단언)는 이 설계 채택 시 의도적으로 바꾼다. 역사 기록으로 "v0.2 scope"라고 남긴다.

## 3. grapheme 하나의 렌더 경로

```text
grapheme G (본문 텍스트 노드 안)
│
├─ G가 어떤 profile에도 속하지 않음 ─────────────→ 손대지 않음 (native, 필요 시 Core)
│
├─ native probe(profile) = 지원 ────────────────→ 손대지 않음 (native)
│
├─ manifest(profile)가 G를 커버하지 않음 ────────→ 손대지 않음 (Core Twemoji)
│
├─ font probe(profile) = 실패/미확인 ───────────→ 손대지 않음 (Core Twemoji)
│
└─ 위를 모두 통과 ──────────────────────────────→ 감싸기 (§7.4) → Noto 폰트로 렌더
```

감싸기 조건은 **세 가지가 모두 참**일 때뿐이다: native 미지원, manifest가 커버, 이 브라우저가 폰트로 실제로 그림.
하나라도 틀리면 감싸지 않는다. 잘못 감싸면 Core Twemoji까지 막혀 tofu만 남는다.

## 4. Profile과 probe

### 4.1 왜 profile인가

Core `emoji` 검사는 U+1FAC8 하나의 중앙 픽셀만 본다(후보 C4). 사용자 Android 브라우저 실측(2026-09-15):

| 시퀀스 | 종류 | 화면 |
|---|---|---|
| 🫈 | Emoji 17 단일 | 정상 |
| 👯🏻 🤼🏻 | Emoji 17 modifier | 정상 |
| 🧑‍🩰 | Emoji 17 ZWJ (기존 구성요소) | 🧑 + 🩰 분해 |
| 🧑🏻‍🐰‍🧑🏼 | Emoji 17 ZWJ (다인) | 분해 |
| 🧑🏻‍🫯‍🧑🏼 | Emoji 17 ZWJ (신규 코드포인트 포함) | tofu |

드래그 선택은 모두 한 grapheme이었다. 텍스트 모델(segmentation)은 알고 폰트만 없는 상태다. 단일 boolean으로는 표현할 수 없다.

Windows Chromium 실측(C2 하네스): `flag: false, emoji: true`. 트랜스젠더 ZWJ 깃발은 native로 정상, 잉글랜드 태그 시퀀스는 검은 깃발로 잘못 표시.

### 4.2 profile 정의

| profile | 대상 grapheme | 분류 방법 | 첫 구현 |
|---|---|---|---|
| `flags-country` | Regional Indicator 쌍 | 정규식(RI RI) | ✅ |
| `flags-subdivision` | U+1F3F4 + tag chars + U+E007F | 정규식 | ✅ |
| `emoji17-single` | Emoji 17 신규 단일 코드포인트 | manifest 목록 | — |
| `emoji17-modifier` | Emoji 17 신규 modifier 시퀀스 | manifest 목록 | — |
| `emoji17-zwj` | Emoji 17 신규 ZWJ 시퀀스 | manifest 목록 | — |

`flags`는 Core의 한 capability지만 여기서는 둘로 나눈다. 실측상 둘의 결과가 다를 수 있고(C3), Core 치환 정규식도 국가 국기만 다룬다.

버전 profile(`emoji17-*`)은 Emoji 18이 나오면 `emoji18-*`를 추가하는 식으로 늘린다. 시간이 지나 native 지원이 퍼지면 자연히 감싸지지 않게 된다.

### 4.3 probe

Core의 두 기법을 그대로 쓴다(기법은 복제하되 Core의 결과 형태에 의존하지 않는다):

- **시퀀스 비교** — 시퀀스 S와, 구성요소 사이에 U+200B를 끼운 S′를 canvas에 그려 픽셀이 같으면 미지원. modifier·ZWJ·국기용.
- **중앙 픽셀** — 단일 코드포인트를 그려 중앙이 비면 미지원.

profile별 대표 probe. profile 규칙(`match`, `probe`, `sequences`)은 폰트가 아니라 감지의 속성이므로 `axismundi_emoji_unicode_profiles()`에 **코드포인트 목록**으로 고정한다(문자열·이스케이프가 어느 경로로도 오가지 않게). 폰트 manifest는 이를 반복하지 않는다(§5).

| profile | probe |
|---|---|
| `flags-country` | 🇨🇶 (Core와 같은 Sark: 가장 늦게 지원되는 국가 국기) |
| `flags-subdivision` | 🏴󠁧󠁢󠁥󠁮󠁧󠁿 |
| `emoji17-single` | 🫈 |
| `emoji17-modifier` | 👯🏻 |
| `emoji17-zwj` | 🧑‍🩰, 🧑🏻‍🫯‍🧑🏼 (둘 다 통과해야 지원) |

**native probe**는 Core처럼 기본 폰트(`600 32px Arial`)로 그린다. 구현은 main thread canvas(profile 수가 적어 비용이 작다). Worker 이전은 profile이 늘면 검토.

**font probe**는 폰트가 필요해진 뒤에만(= 페이지에 감쌀 후보가 있고 native가 실패한 뒤) main thread에서 `document.fonts.load()` 후 `"Noto Color Emoji"`로 같은 비교를 한다. 컬러 폰트 포맷을 못 쓰는 브라우저는 여기서 걸러진다. Worker canvas는 문서 폰트를 못 쓰므로 font probe는 Worker로 옮기지 않는다.

### 4.4 캐시

- sessionStorage 키 `axismundiEmojiProbe`, 값 `{ v: <manifest hash>, t: <ms>, native: {profile: bool}, font: {profile: bool} }`.
- Core 키(`wpEmojiSettingsSupports`)에 쓰지 않는다. 형태가 다르고, Core 로더가 그 객체를 그대로 `supports`에 복사한다.
- 만료는 밀리초로 1주. manifest hash가 바뀌면 무효.
- Core의 만료 단위 혼동(C1)은 여기서 반복하지 않는다.

### 4.5 CoreEmojiAdapter 인터페이스

구현(`assets/unicode/adapter.js`) 내부 경계:

```js
nativeSupported( profile )    // boolean; plugin canvas probe today, Core API later
fontSupported( profile )      // Promise<boolean>; always plugin-side
classify( grapheme )          // profile id | null
protect( node )               // wraps covered graphemes per §7.4
```

진단용으로 `window.axismundiEmojiUnicode = { classify, protect, decisions(), verified( profile ) }`만 노출한다.
Core가 profile 단위 감지를 노출하게 되면 `nativeSupported`만 Core를 읽도록 바꾼다. 나머지는 그대로. 현재 어댑터는 Core의 전역(`_wpemojiSettings`)도 sessionStorage 키도 읽지 않는다(audit가 단언).

## 5. Coverage manifest

폰트 빌드가 만드는 **산출물**이다. 사람이 편집하지 않는다. 폰트 파일과 함께 배포하고 해시로 묶는다.

```json
{
  "schema": 1,
  "family": "Noto Color Emoji",
  "source": {
    "project": "googlefonts/noto-emoji",
    "revision": "<commit or tag>",
    "build": "<pipeline id, e.g. axismundi-colrv1-1>",
    "emojiVersion": "17.0"
  },
  "profiles": {
    "flags-country": {
      "file": "fonts/noto-color-emoji/flags.woff2",
      "sha256": "…",
      "bytes": 0,
      "unicodeRange": "U+1F1E6-1F1FF",
      "probe": ["<sequence>"],
      "match": "regional-indicator-pair"
    },
    "flags-subdivision": {
      "file": "fonts/noto-color-emoji/flags.woff2",
      "unicodeRange": "U+1F3F4, U+E0020-E007F",
      "probe": ["<sequence>"],
      "match": "tag-sequence",
      "sequences": ["<England>", "<Scotland>", "<Wales>"]
    }
  }
}
```

- **구현이 읽는 필드는 `family`와 `profiles[id].file`뿐이다.** 파일이 실제로 있을 때만 그 profile에 `font: true`. 위 예시의 `probe`/`match`/`sequences`는 §4.3 결정에 따라 manifest에서 뺀다(빌드 측 참고용으로 남길 거면 `verifiedSequences`처럼 "폰트가 합성함을 검증한 목록"으로 이름을 달리한다).
- 필터 `axismundi_emoji_unicode_font_manifest`로 다른 폰트 소스가 같은 형태를 공급할 수 있다(Twemoji COLR 빌드 등). 알 수 없는 profile id는 버린다.
- 폰트 URL은 브라우저 config에 싣지 않는다. 브라우저는 coverage(`font: bool`)와 family만 알고, 파일은 provider CSS가 로드한다.
- 두 flags profile은 한 파일을 공유할 수 있다. profile은 감지·감싸기 단위이지 파일 단위가 아니다.
- `sequences`가 있는 profile은 목록에 있는 grapheme만 감싼다. U+1F3F4 단독(검은 깃발)은 감싸지 않는다.
- 생성기는 `--check`를 가진다(AGENTS.md "Generated files"). 검증기는 manifest의 sequence가 폰트에서 실제로 한 glyph로 shaping되는지를 HarfBuzz로 묻는다(§9).

## 6. Font Library 등록과 sidecar

### 6.1 collection

Korean Font Provider와 같은 두 경로:

| 경로 | 역할 |
|---|---|
| `wp_register_font_collection( 'axismundi-emoji', … )` | Site Editor › Typography › Manage fonts에서 발견·설치 |
| provider CSS (`@font-face`, profile별 face) | 실제 렌더. `.ax-unicode-emoji` span과 picker에만 적용 |

```php
'font_family_settings' => array(
	'name'       => 'Noto Color Emoji',
	'slug'       => 'noto-color-emoji',
	'fontFamily' => '"Noto Color Emoji"',
	'fontFace'   => array(
		array(
			'fontFamily'   => 'Noto Color Emoji',
			'fontStyle'    => 'normal',
			'fontWeight'   => '400',
			'fontDisplay'  => 'swap',
			'unicodeRange' => 'U+1F1E6-1F1FF, U+1F3F4, U+E0020-E007F',
			'src'          => $flags_src,
		),
	),
),
```

- family 하나, profile 파일마다 fontFace 하나. 정적 폰트의 Regular/Italic과 같은 모델이며 구분 축이 `unicodeRange`다.
- 사이트 관리자가 collection에서 설치하면 Core가 파일을 uploads로 복사하고 global styles에 추가한다. 이는 무해하다(글꼴 목록에 보일 뿐). 렌더 경로는 설치 여부와 무관하게 provider CSS다.
- 실측 필요: collection 등록 시 `unicodeRange`가 정리 스키마를 통과해 남는지.

### 6.2 sidecar (Core가 지우는 것)

Core는 `font_family_settings`의 알 수 없는 키를 네 곳에서 지운다(#82848). 그래서 용도와 coverage는 플러그인이 갖는다.

| 무엇 | 어디 | 비고 |
|---|---|---|
| 용도 `emoji`, profile→face 대응, 해시 | 배포 manifest (§5) | 읽기 전용 산출물 |
| 렌더링 정책 | option `axismundi_emoji_unicode_rendering` | `auto`(기본) / `core` |
| (선택) 사이트가 쓸 emoji family | 같은 option | 초기에는 Noto 고정 |

- `core` = 이 계층 전체 비활성(Core 기본 동작만). 문제 발생 시 운영자가 즉시 돌아갈 스위치.
- #82848이 `usage` 필드로 받아들여지면 용도는 family 메타데이터로 옮기고 manifest는 coverage만 남긴다.

## 7. Core와의 연결

### 7.1 Core가 하는 일 (확인됨)

- `print_emoji_detection_script`: 프론트 `wp_head`(실제 출력은 footer), admin `admin_print_scripts`, `embed_head`.
- 블록 에디터(`edit-form-blocks.php:42`)는 이를 제거한다 → **에디터에는 Twemoji 안전망이 없다.**
- loader가 `flag`/`emoji`를 검사하고, 하나라도 실패하면 Twemoji + `wp-emoji.js`를 로드한다.
- `wp-emoji.js`는 초기 `parse(document.body)` 후 MutationObserver로 추가 노드를 parse한다. `everythingExceptFlag`면 국가 국기와 무지개·해적 깃발만 치환한다.
- `doNotParse`: `wp-exclude-emoji` 클래스 요소를 **자식 순회에서만** 건너뛴다.

### 7.2 이 계층이 하는 일

- Core 스크립트를 제거·대체하지 않는다.
- adapter 모듈은 Core loader보다 먼저 실행되도록 출력한다(모듈이 head에서 로드되고 DOMContentLoaded에 초기 감싸기 수행; Core 치환은 비동기 감지 → 스크립트 추가 로드 이후라 뒤에 온다). **순서 보장은 prototype에서 측정**(§10).
- adapter의 MutationObserver는 Core보다 먼저 등록되므로 같은 mutation 배치에서 먼저 실행된다. **측정 대상.**
- 폰트가 늦게 실패하면(font probe false) 감싼 wrapper를 평문 텍스트 노드로 되돌린다. 평문 삽입은 Core observer가 받아 Twemoji로 처리한다 → 실패가 자동으로 Core 안전망으로 떨어진다.

### 7.3 건드리지 않는 것

- RSS·HTML 이메일의 `wp_staticize_emoji()` — 출력에 CSS가 없어 폰트로 해결할 수 없다.
- Core의 `emoji_url` / `emoji_svg_url` 필터 — 이미지 fallback 위치는 운영자·다른 플러그인 몫.

### 7.4 감싸기 규칙 (workaround 포함)

```html
<span class="ax-unicode-emoji" data-ax-emoji-profile="flags-country"><span class="wp-exclude-emoji">🇰🇷</span></span>
```

```css
.ax-unicode-emoji {
	font-family: "Noto Color Emoji";
	font-style: normal;
	font-weight: 400;
}
```

규칙:

1. 바깥 `span.ax-unicode-emoji`, 안쪽 `span.wp-exclude-emoji` **두 겹**. 둘을 만든 뒤 **한 번에** 삽입한다.
2. 감싼 뒤 안쪽 텍스트를 수정하지 않는다. 내용이 바뀌어야 하면 바깥 wrapper째 교체한다.
3. grapheme 하나에 wrapper 하나. ZWJ·태그 시퀀스를 쪼개지 않는다(`Intl.Segmenter`, 없으면 감싸지 않음).
4. `.ax-unicode-emoji`, `.wp-exclude-emoji`, custom emoji 렌더 요소(`img.ax-emoji`, `picture.ax-emoji-picture`), `script/style/textarea/[contenteditable]` 안은 처리하지 않는다.
5. `font-family`는 span 자신이 갖는다. 테마 font stack·CJK provider 순서와 무관하다.

> **WORKAROUND (C2).** 두 겹 구조는 Core 결함 우회다. `wp-emoji.js`/twemoji는 제외 요소가 parse root가 되면(직접 삽입, 내부 텍스트 변경) 제외를 무시한다 — 2026-09-15 재현, [UPSTREAM-TRAC-CANDIDATES.md](UPSTREAM-TRAC-CANDIDATES.md) C2. Core가 root에도 `doNotParse`를 적용하면 `span.ax-unicode-emoji.wp-exclude-emoji` 한 겹으로 줄인다. 제안 시 이 구조를 설계의 일부로 내지 말고 우회책으로 표시한다.

접근성: wrapper는 텍스트를 그대로 두므로 복사·검색·스크린리더 결과가 native와 같다. `role="img"`나 `aria-label`을 붙이지 않는다(Core img와 다른 점이며 의도).

## 8. 표면별 적용

| 표면 | 방식 | 첫 구현 |
|---|---|---|
| 프론트 본문, 댓글, 동적 피드 | §3·§7.4 adapter | ✅ |
| 에디터 picker 타일 | picker 안에서 같은 감싸기(저장되지 않는 UI) | ✅ |
| 에디터 캔버스(RichText) | 저장 마크업에 span을 넣을 수 없음. **미해결** (§10 Q3) | — |
| embed(`embed_head`) | 프론트와 같음. 측정 후 | — |
| RSS/이메일 | 제외 (§7.3) | — |

에디터 picker는 Unicode 탭을 추가한다. 선택 시 **평문 grapheme만** 삽입한다(custom emoji가 평문 `:shortcode:`만 넣는 것과 같은 원칙).
picker 데이터 로딩은 Activities reaction picker의 그룹별 지연 로드를 옮겨 온다. 피부색 선택은 이 설계 범위 밖(후속).

## 9. 폰트 산출물과 합격 기준

폰트 제작은 사용자 측이 병행한다. 이 문서는 **합격 기준**만 고정한다.

### 9.1 산출물

| 산출물 | 목적 | 배포 후보 |
|---|---|---|
| Fedora COLRv1 full → WOFF2 | 호환성·용량 기준선 | 아니오 |
| 자체 빌드 COLRv1 full → WOFF2 (고정 upstream revision) | 재현 가능한 원본, provenance | 원본 |
| `flags` subset (자체 빌드에서) | MVP | **예** |

- 배포 TTF(CBDT)를 COLRv1로 역변환하지 않는다. upstream SVG·빌드 입력 → COLRv1 → subset → WOFF2.
- 폰트 디렉터리에 `OFL.txt`, `source.txt`(upstream revision, 파이프라인, 날짜), NOTICE 항목. Korean Font Provider 형식을 따른다.

### 9.2 확인표 (산출물마다)

- [ ] `COLR`, `CPAL`, `GSUB`, `cmap` 테이블 존재
- [ ] 라이선스: OFL 1.1 전문, Reserved Font Name 유무 확인 → family 이름 유지 가능 여부 결정
- [ ] HarfBuzz(`hb-shape`): 🇰🇷 → glyph 1개
- [ ] HarfBuzz: 잉글랜드·스코틀랜드·웨일스 태그 시퀀스 → 각 glyph 1개 (U+1F3F4 단독은 검은 깃발)
- [ ] HarfBuzz: 🏳️‍⚧️ → glyph 1개 (full 빌드; flags subset 포함 여부는 결정 필요, §10 Q4)
- [ ] HarfBuzz: Emoji 17 대표(§4.3 표) → 각 glyph 1개 (full 빌드)
- [ ] Windows Chromium: native / font span / Core fallback 세 상태 스크린샷 + DOM
- [ ] Chrome·Firefox·Android Chrome·Safari: WOFF2 렌더 (Safari COLRv1 미지원이면 font probe가 false → Core로 떨어지는지)
- [ ] 크기: full / flags subset, TTF와 WOFF2 각각

"파일이 작아졌다"는 통과 조건이 아니다. 시퀀스가 한 glyph로 합성되는지가 통과 조건이다.

## 10. 열린 질문과 측정 목록

| # | 질문 | 판정 방법 |
|---|---|---|
| Q1 | adapter 초기 감싸기가 Core 초기 parse보다 항상 앞서는가 | Playground/wp-env, 느린 네트워크·캐시된 감지 결과 두 경우 |
| Q2 | 같은 mutation 배치에서 adapter observer가 먼저 실행되고 Core가 빈 부모를 건너뛰는가 | C2 하네스 확장 |
| Q3 | ~~에디터 캔버스에서 저장 마크업을 바꾸지 않고 국기를 Noto로 보일 방법~~ | **범위 밖(2026-09-16, 소유자 결정)**: 플러그인에서 하기엔 거추장스럽고 Core가 판단할 문제. picker 타일은 폰트로 보이지만 paragraph 안 국기는 Windows에서 `KR`로 보이는 상태를 알려진 한계로 둔다. Core 제안 시 참고할 아이디어(미검증): 이미 `@font-face`로 로드된 테마 family 이름 아래에 flags 범위 face를 추가해 `unicode-range`로 병합 — 시스템 폰트 이름에 선언하면 로컬 폰트를 가리므로 웹폰트 family에만 적용 가능. custom emoji가 에디터에서 shortcode로 보이는 것은 Mastodon·Misskey와 같아 한계로 보지 않는다. |
| Q4 | 🏳️‍⚧️ 등 ZWJ 깃발은 `flags`에 넣는가 `emoji*-zwj`인가 | Core는 `flag` 검사에 넣음. 실측상 Windows는 native 지원 → 우선 제외 쪽 |
| Q5 | `unicodeRange`가 font collection 정리를 통과하는가 | `wp_register_font_collection` 후 REST 응답 |
| Q6 | ~~Noto OFL에 RFN이 있는가~~ | **해결(2026-09-16)**: 배포 `OFL.txt`에 Reserved Font Name 선언 없음 → family `Noto Color Emoji` 유지 |
| Q7 | ~~Activities catalogue 이관 시점~~ | **해결(2026-09-16)**: 즉시 이관, 사본 없음, REST 경로 `axismundi/v1/emoji/unicode`로 변경(§12.4) |

## 11. Core 제안으로의 대응

| Core 개념 (제안) | 이 플러그인의 구현 | 옮겨 갈 때 |
|---|---|---|
| Font Library family `usage: "emoji"` | collection 등록 + manifest sidecar | #82848 → 필드로 이동 |
| coverage | fontFace `unicodeRange` + manifest `sequences` | `unicodeRange`는 이미 Core 필드 |
| profile 단위 capability 감지 | PluginDetector (§4) | `getNativeSupport`를 Core로 교체. C4 |
| fallback provider 순서 native → font → image | CoreEmojiAdapter (§3, §7) | Core loader가 usage:emoji family를 조회 |
| 제외 규칙 신뢰성 | 두 겹 wrapper workaround | C2 수정 후 한 겹 |
| 깃발 치환 범위 | `flags-subdivision` profile | C3 |
| 감지 캐시 | 자체 키, ms 1주 | C1 |

제안 순서(예정): C2·C3·C1 재현 티켓과 PR → flags prototype 결과(스크린샷·크기·브라우저 매트릭스) → #82848에 emoji 소비자 사례로 연결 → 필요하면 Core loader 확장 제안(Trac 또는 Ideas).

## 12. 구현 순서

1. ✅ (2026-09-15) manifest 읽기 + `flags` profile 분류기 + native probe + font probe + 감싸기(§7.4) + 실패 시 풀기. 폰트 없이 DOM으로 검증 — 결과는 §12.1.
2. 사용자 측 flags subset 도착 → provider CSS + font probe + collection 등록.
3. Q1·Q2 측정, Windows Chromium 세 상태 증거.
4. 에디터 picker Unicode 탭(catalogue 이관 포함).
5. `emoji17-*` profile.

### 12.1 1단계 측정 결과 (2026-09-15)

환경: 이 기기 Windows 11 앱 내 Chromium. Core `supports = { flag: false, emoji: true, everythingExceptFlag: true }`. 하네스: `tests/unicode-harness/build.py`(Core 원본 JS 3개 + adapter, config는 wp-env의 `axismundi_emoji_unicode_config()` 출력).

**`--font-mode assume`** (폰트가 동작한다고 가정, 감싸기와 Core 상호작용만 측정)

| 경우 | wrapper | Core img |
|---|---|---|
| 정적 🇰🇷 | 1 | 0 |
| 정적 잉글랜드 태그 시퀀스 | 1 (`flags-subdivision`) | 0 |
| 정적 U+1F3F4 단독(검은 깃발) | 0 | — |
| 한 문장 안 🇰🇷 + 잉글랜드 | 2, `textContent` 원문 그대로 | 0 |
| `<textarea>` 안 | 0 | — |
| 작성자가 넣은 `span.wp-exclude-emoji` | 0 (건드리지 않음) | 0 |
| 동적: `textContent`로 삽입 | 1 | 0 |
| 동적: `<p>…<b>🇰🇷</b></p>` innerHTML | 1 | 0 |
| 동적: 기존 div에 텍스트 노드 append | 1 | 0 |
| 어느 wrapper 안이든 img | — | 0 |

→ **Q2 통과**: 같은 mutation 배치에서 adapter observer가 먼저 감싸고, Core는 감싼 grapheme을 치환하지 않았다(C2 두 겹 우회 포함). Q1(초기 순서)도 이 하네스에서는 통과했으나, 느린 네트워크·Core 캐시 적중 경우는 WordPress 실환경에서 다시 본다.

**`--font-mode none`** (manifest는 폰트가 있다고 하지만 브라우저에 실제 폰트 없음)

- font probe `false` → 모든 wrapper 제거(0개 남음) → Core observer가 평문을 받아 🇰🇷을 img로 치환(정적·문장·동적 모두 img 1).
- 잉글랜드는 풀린 뒤 native 검은 깃발로 남는다 — Core가 원래 못 고치는 C3 그대로. 폰트가 있을 때만 해결된다.
- 결과는 sessionStorage `axismundiEmojiProbe`에 `font: false`로 캐시되어, 같은 세션 다음 페이지에서는 처음부터 감싸지 않는다.

→ 폰트 실패는 **Core 기본 동작으로 정확히 떨어진다**(D3 확인).

### 12.2 2단계: 실제 폰트, 실제 WordPress (2026-09-16)

폰트: `assets/fonts/noto-color-emoji/axismundi-noto-colrv1.woff2` — upstream `googlefonts/noto-emoji` v2.051(commit `8998f5dd…`)의 `fonts/Noto-COLRv1.ttf`를 glyph·layout 유지한 채 WOFF2로만 변환(1,976,040 bytes). flags subset은 증명 후 별도.

추가 구현:
- `scripts/build-unicode-font-manifest.py` → `assets/fonts/noto-color-emoji/manifest.json`(생성물, `--check`). `--verify`는 HarfBuzz로 profile 시퀀스가 glyph 1개로 합성되는지 검사: Sark·🇰🇷·잉글랜드·스코틀랜드·웨일스 5/5 PASS, `COLR/CPAL/GSUB/cmap` 모두 존재, family `Noto Color Emoji`.
- `@font-face`는 Core의 `wp_print_font_faces()`로 출력(`style.wp-fonts-local`), 파일 하나에 face 하나, `unicode-range`는 활성 profile의 합집합.
- `.ax-unicode-emoji{font-family:"Noto Color Emoji"}`는 인라인 스타일로. 테마 stack 무관.
- Font Library collection `axismundi-emoji`(family `Noto Color Emoji`, slug `noto-color-emoji`). collection face에는 `unicode-range`를 넣지 않는다(파일은 완전판이므로). 런타임 face만 좁힌다.
- audit 40/40 PASS.

**wp-env 실측** (WordPress 7.1 + Gutenberg 23.7.1, Axismundi 테마, 앱 내 Windows Chromium, 글 `/ax-unicode-emoji-flags/`):

| 항목 | 결과 |
|---|---|
| Core `supports` | `flag:false, emoji:false` — 7.1에는 아직 #66104(잘못된 Emoji 17 검사 문자열)가 있어 모든 이모지를 이미지로 교체하는 상태 |
| adapter decisions | `flags-country`, `flags-subdivision` 모두 true |
| font probe (실제 WOFF2) | 둘 다 true |
| 감싼 grapheme | 🇰🇷, 🇨🇶, 잉글랜드, 스코틀랜드, 웨일스 — 5개 |
| wrapper 안 `img` | 0 |
| wrapper computed `font-family` | `"Noto Color Emoji"` |
| `FontFace` 상태 | `loaded`, `U+1F1E6-1F1FF, U+1F3F4, U+E0020-E007F` |
| Core가 이미지로 바꾼 것 | 😀, U+1F3F4 단독, 🏳️‍⚧️ — 모두 profile 밖이라 의도대로 Core 몫 |
| 출력 순서 | head: config JSON → adapter(defer) … footer: `wp-emoji-settings` → Core loader module |

→ **명제(§0) 성립**: Font Library에 등록된 emoji 폰트가, native로 못 그리는 profile의 grapheme에 한해 s.w.org 이미지보다 먼저 쓰였고, 나머지는 Core fallback이 그대로 처리했다. Q1도 실제 WordPress에서 통과(단, 느린 네트워크·캐시 적중 경우는 미측정).
- Q5: collection에 `unicodeRange`를 넣지 않기로 해서 이번엔 판정하지 않음.

### 12.3 flags subset 연결과 manifest 역할 분리 (2026-09-16)

- `axismundi-noto-colrv1-flags.woff2` (716,080 bytes, full의 36%): Unicode `emoji-test.txt` 17.0에서 뽑은 fully-qualified RGI 국기 262개(국가 쌍 + subdivision)로 `scripts/build-noto-color-emoji-flags.py`가 subset, 262개 전부 HarfBuzz 단일 glyph 검증. provenance는 `source.txt`.
- manifest 역할 분리:
  - `profiles` — 런타임 fallback이 실제로 로드하는 파일(현재 flags subset, alias `Axismundi Emoji Flags`).
  - `files` — 배포·검증 대상 전부(full + flags, 각각 alias·bytes·sha256).
  - `collectionFile` — Font Library가 설치용으로 제공하는 완전판(`axismundi-noto-colrv1.woff2`). `axismundi_emoji_unicode_collection_font_url()`가 읽는다.
- audit 50/50: 런타임 기대값은 manifest `profiles`/`files`에서 읽고, collection 파일은 이름으로 고정해 따로 검사한다(manifest가 스스로와 일치해서 통과하는 일을 막기 위해). `auto`에서 full 파일이 요청되지 않는다는 것도 단언.
- wp-env 실측: `auto` 페이지가 요청한 폰트는 `axismundi-noto-colrv1-flags.woff2?ver=e75981a2f2c7` 하나(699 KB decoded), wrapper 5개 모두 `Axismundi Emoji Flags`, 내부 img 0.
- 크기 메모: subset은 base glyph 290개지만 COLR layer 22,906개 — 국기 도안의 paint 복잡도 때문이며 subset 결함 아님.

### 12.4 Unicode RGI catalogue를 Activities에서 Emoji로 이관 (2026-09-16)

소유자 결정: Emoji가 먼저 독립 제출되므로 catalogue는 Emoji 소유, Activities에는 사본을 남기지 않는다. REST 경로 이름 변경 승인(Activities는 스테이징에만 있음).

| 이전 (Activities) | 이후 (Emoji) |
|---|---|
| `assets/unicode-rgi-17.0.json`, `assets/unicode-rgi-17.0/*.json` (9), `.LICENSE.txt`, `.manifest.json`(소비자 없음) | `assets/unicode/catalogue/rgi-17.0.json`, `rgi-17.0/*.json` (9), `rgi-17.0.LICENSE.txt` — manifest 파일은 폐기 |
| `scripts/build-unicode-emoji-catalogue.ps1` | `scripts/build-unicode-emoji-catalogue.py` (`--check`, 입력 SHA-256 고정 = flags subset 입력과 같은 `emoji-test.txt`) |
| `includes/unicode-catalogue.php` (`axismundi_act_*`) | `includes/unicode-catalogue.php` (`axismundi_emoji_unicode_catalogue()`, `axismundi_emoji_find_unicode()`, `axismundi_emoji_unicode_groups()`, `axismundi_emoji_unicode_picker_source()`) |
| REST `axismundi/v1/reactions/unicode` | REST `axismundi/v1/emoji/unicode` (옛 경로 alias 없음, 404) |
| `tests/audit-unicode-catalogue.php` (데이터+picker) | 데이터 검증은 `axismundi-emoji/tests/audit-emoji-unicode-catalogue.php`(17), Activities 쪽은 picker 연결 계약만(11) |

- 무손실 확인: 새 생성물과 기존 파일을 파싱해 비교 — 인덱스 3,944개·메타데이터·그룹 9개 모두 동일(직렬화만 달라 1,709 KB → 1,678 KB).
- Activities는 `axismundi_act_unicode_picker_source()`만 남기고 `function_exists( 'axismundi_emoji_unicode_picker_source' )`로 Emoji를 읽는다. Emoji 없음 → 빈 source → Unicode 섹션 없음(JS는 빈 source에서 fetch 전에 반환). 수신 정규화는 원래 catalogue를 보지 않음(audit 단언).
- 검증: Activities audit-unicode-catalogue 11/11, audit-emoji-reactions 39/39, audit-reaction-summary 27/27; Emoji catalogue 17/17, unicode 50/50, picker 19/19. 등록 경로는 `emoji/unicode` 하나, 정적 JSON 200.
- 커밋 순서 제안(정의/호출부 한 커밋 금지): ① Emoji에 catalogue 추가 ② Activities 전환·사본 삭제.

### 12.5 에디터 picker Unicode 탭 + Activities 단독 fatal 수정 (2026-09-16)

**커밋 전 확인(Activities만)** — `wp --skip-plugins=axismundi-emoji`로 재현.
- 발견: `reaction-blocks.php`가 `AXISMUNDI_EMOJI_CATALOGUE_MAX_PER_PAGE`를 가드 없이 읽어 **Emoji 없이 리액션 블록이 있는 페이지가 fatal**(이관 전부터 있던 결함). `defined()` 가드 + JS `loadCustomCatalogue()`가 빈 endpoint면 fetch하지 않고 빈 catalogue로 처리(전에는 페이지 HTML을 JSON으로 읽어 오류 표시).
- 결과: Emoji 없음 → picker HTML에 Unicode 그룹 0, custom endpoint `''`, seed 정상. Unicode 리액션 정규화 정상(❤️ `unicode:U+2764`, 🇰🇷), custom `:axismundi:`은 null. Emoji 있음 → 그룹 9.
- Activities audit: unicode-catalogue 13(가드 없는 `AXISMUNDI_EMOJI_*` 상수 스캔 추가)·emoji-reactions 39·reaction-summary 27·reaction-mutations 25.

**에디터 picker Unicode 탭** (`assets/editor/picker.js`)
- ~~한 툴바 버튼 안에 탭 `Custom`·`Unicode`~~ → **폐기(사용자 피드백: 디자인이 나빠짐).** 원래 인서터는 `a2b98cf`의 custom 전용 그리드(HEAD까지 불변, 스타일 공유 이력 없음). 레이아웃을 프론트 리액션 picker(Activities, Material Symbols 소유)와 같게 재작성: 검색 필드(상단 붙음·하단 진행바) → 아이콘 jump strip(48px, 3px 활성 표시, `role="toolbar"`·`aria-current`) → 한 페이지 스크롤. 섹션 Recent·Custom(카테고리별 접기, 기본 펼침)·Unicode 그룹 9(기본 접힘, 처음 펼칠 때 그룹 파일 fetch, 닫힌 섹션은 타일 미마운트). jump는 접힌 목적지를 먼저 펼친 뒤 스크롤, 스크롤 위치가 활성 jump를 따라감. 검색은 페이지를 결과 목록(custom 먼저 + Unicode)으로 대체. 아이콘은 wp-admin에 항상 있는 Dashicons, 치수는 리액션 picker와 동일(24rem 패널, 2.5rem 타일). 최근 목록은 custom·Unicode 통합 키. audit-emoji-picker 28 PASS.
- 둘러보기 = 그룹 정적 JSON 한 번에 하나(편집기 로드당 그룹별 1회 캐시), 검색 = `axismundi/v1/emoji/unicode`(영어 이름만). 편집기 로드에는 그룹 URL 10개만 인라인(`window.axismundiEmojiUnicodeSource`), 데이터는 탭을 열 때.
- 삽입 = 평문 grapheme, 패딩 없음. 최근 목록은 custom과 별도 키.
- 피부색 변형은 목록에서 제외(reaction picker와 동일). tone 선택 UI는 범위 밖.
- 폰트: adapter를 에디터에서 **passive**(`observe:false`, 인라인 `window.axismundiEmojiUnicodeConfig`)로 로드 → probe·classify만, React DOM은 건드리지 않음. picker가 자기 타일에만 `ax-unicode-emoji` + `data-ax-emoji-profile`을 붙이고, 같은 stylesheet(alias face)를 쓴다. 블록 에디터는 Core 감지가 제거돼 `wp-exclude-emoji` 불필요.
- 기존 "Unicode는 picker에 없다" 문구·단언은 의도적으로 변경(picker.js 헤더, `audit-emoji-picker.php`).
- audit: emoji-unicode 56, emoji-picker 22, emoji-unicode-catalogue 17 — 전부 PASS.
- **미검증: 실제 에디터 화면**(탭 전환, 국기 타일 폰트, 삽입). 브라우저 pane이 wp-admin 로그인 화면이라 중단 — 로그인은 사용자가 해야 함.


### 12.6 일반 글·댓글의 custom emoji 렌더 (2026-09-16)

- 증상(사용자 보고): `/emoji/`(일반 글 36153, 본문 `:wordpress: 🇺🇳🏢`)에서 `:wordpress:`가 평문. `:wordpress:`는 approved·renderable.
- 원인: 회귀 아님. Emoji의 shortcode→이미지 치환은 처음부터 OP `axismundi_op_object_content_html`(object 본문)과 Actors 이름·소개에만 걸려 있었고, `the_content`/`comment_text`에는 hook이 없었다(`apply_filters('the_content', ':wordpress: test')` → 평문 실측). Emoji 단독 제출 조건에서 드러난 빈 곳.
- 수정: `includes/content.php` — `the_content`·`comment_text` priority 12(블록 9·shortcode 11 뒤, `convert_smilies` 20 앞). 선언 맵 = 본문에 쓰인 shortcode 중 **로컬·렌더 가능한** registry 행만(원격 emoji는 이름으로 매칭하지 않음). `outbound_allowed = 0`(외부 발행 보류)도 자기 사이트에서는 렌더 — ARCHITECTURE 문서의 기존 원칙. feed는 건너뜀. `pre`/`code` 안은 렌더러가 원래 건드리지 않음, 재적용해도 동일(OP 뷰와 이중 적용 안전).
- 검증: `audit-emoji-content` 12/12(렌더, alt 유지, code 제외, 미등록·시각 표기 무시, 멱등, 댓글, 발행 보류도 렌더, pending은 텍스트, DB 행 원복), renderer 32·op-integration 14·local 51 PASS. `/emoji/` 실측: `<img class="ax-emoji" … alt=":wordpress:">`, 평문 잔존 0. 사용자 확인 "뜬다".
- 별건: `audit-emoji-actors-integration` 2건 실패는 이 변경과 무관 — `example.com` authority에 2026-07-29 fixture 잔존 행(`unreviewed`, pending)이 있어 "정확히 1행" 단언이 테스트 중 2행이 됨. includes 중 이번에 바뀐 건 `editor.php`뿐. → 소유자 승인 후 삭제(행 id 3623 + `wp_ax_emoji_references` 참조 7, 캐시 파일 없음). 재실행 10/10 PASS, 테스트 후 `example.com` 행 0(테스트가 자기 fixture를 정리함).

### 12.7 선언은 원문에서, 렌더는 결과에서 (2026-09-16)

§12.6의 `the_content` 렌더가 드러낸 순서 버그와 그 수정. 모델 확인: 로컬 emoji는 attachment를 만들지 않고(ARCHITECTURE §8 "The registry is the record"), 로컬 글의 선언은 저장된 shortcode 평문을 tokenize해 outbound `tag[]`로 매번 재구성한다. 별도 post meta 선언 저장소는 만들지 않는다.

- **버그(실측):** OP Article(`post-article.php`)과 Note(`federation.php`)가 `the_content`를 거친 본문을 `axismundi_emoji_outbound_tags()`에 넘긴다. Emoji가 `the_content`에서 `:wordpress:`를 `<img>`로 먼저 바꾸고 tokenizer는 태그를 지우므로, `/emoji/` Article의 Emoji `tag[]`가 `[":wordpress:"]` → `[]`. 연합되는 글에서 선언이 조용히 사라지는 결함.
- **수정 1 (Emoji, 우선):** `axismundi_emoji_tokenize()`가 태그를 지우기 전에 자기 이미지 `img.ax-emoji`의 bare `alt=":name:"`를 shortcode로 되돌린다. 원격 형태(`:name@host:`)와 다른 클래스 이미지는 세지 않는다. 소비자가 렌더 결과를 넘겨도 선언을 잃지 않게 Emoji가 경계를 책임진다.
- **수정 2 (audit):** `audit-emoji-content` 18/18 — tokenizer 복원, decorated/authored outbound 동일, 원격·무관 이미지 제외, hook 활성 상태에서 임시 Article(관리자 작성자)이 `:wordpress:`를 선언. fixture 함정: WP-CLI에서 작성자 0이면 `ax_op_post_identity`로 Article 자체가 안 만들어져 선언 누락처럼 보였음 → 투영 성공을 먼저 단언.
- **수정 3 (OP·Note):** 두 곳 모두 tokenizer에 **원문 + 렌더 결과**를 함께 넘김(`$sections['content']`, `$post->post_content`). 원문은 저자 의도를, 렌더 결과는 동적 블록이 만든 텍스트를 보장하고 중복은 shortcode 단위로 제거.
- 검증: Emoji outbound 41·parser 16·renderer 32, OP `audit-emoji-reactions-collection` 23·`audit-hashtag-localization` 11, Note `audit-note-federation` 16 — 전부 PASS. `/emoji/` Article 재측정: hook 활성 상태 `tag[]` = `[":wordpress:"]`.

## 13. 크기와 전송 근거 (#44001 후속 근거, 2026-09-16)

[Core Trac #44001 comment:17](https://core.trac.wordpress.org/ticket/44001#comment:17)이 제기한 "Core 설치마다 수천 개 이미지를 번들할 이유가 있나"에 대한 실측 근거. **아직 게시하지 않음** — 게시할 때는 source-bound 규칙(`verify_upstream_comment.py`)으로 별도 초안을 만든다.

### 13.1 자산 크기

| 자산 | 크기 | 출처 |
|---|---:|---|
| Noto Color Emoji full COLRv1 WOFF2 (`axismundi-noto-colrv1.woff2`, v2.051, RGI 3,944개 전부 합성) | 1,976,040 B (1.88 MiB), 파일 1개 | 직접 측정 |
| flags subset WOFF2 (`axismundi-noto-colrv1-flags.woff2`, RGI 국기 262개) | 716,080 B (699 KiB), 파일 1개 | 직접 측정 |
| Twemoji 17.0.2 72×72 PNG (WordPress 7.1이 가리키는 버전) | 4,009개, 4.25 MB (4.05 MiB) | Codex 측정, [jdecked/twemoji v17.0.2 assets](https://github.com/jdecked/twemoji/tree/v17.0.2/assets) |
| Twemoji 17.0.2 SVG | 4,009개, 10.12 MB (9.65 MiB) | 같음 |
| PNG + SVG를 함께 번들할 경우 | 8,018개, 약 13.7 MiB | 위 두 줄의 합 |
| Korean Font Provider — Noto Sans KR | 1,247,668 B (1.19 MiB) | 직접 측정 |
| Korean Font Provider — Noto Serif KR | 2,157,104 B (2.06 MiB) | 직접 측정 |
| Korean Font Provider 합계 | 3,404,772 B (3.25 MiB) | 직접 측정 |

- full emoji 폰트는 Noto Sans KR의 약 1.6배, Noto Serif KR보다 약 8% 작고, Korean Font Provider 두 family 합계보다 약 42% 작다. 즉 `font` 모드의 비용은 이미 배포 중인 지역 폰트 provider 수준 안에 있다.
- **Core가 실제로 쓰는 쪽은 SVG다.** `_print_emoji_detection_script()` 설정에 PNG `baseUrl`도 있지만, `wp-emoji.js`는 `browserSupportsSvgAsImage()`가 참이면 `svgUrl`/`svgExt`를 쓴다(소스 확인). PNG는 SVG를 이미지로 못 쓰는 환경의 경로.
- WOFF2는 이미 압축 컨테이너다. flags subset에 `gzip -9` → 705,676 B(−1.5%). 서버 gzip/brotli로 더 줄일 여지는 거의 없다(직접 측정).

### 13.2 전송 시간 (계산)

순수 전송만, 첫 요청 RTT 별도. 같은 origin의 기존 연결이면 TLS 비용은 따로 크지 않다.

| 실효 대역폭 | flags 699 KiB | full 1.88 MiB |
|---:|---:|---:|
| 25 Mbps | 0.23초 | 0.63초 |
| 10 Mbps | 0.57초 | 1.58초 |
| 3 Mbps | 1.9초 | 5.3초 |

### 13.3 누가 언제 비용을 내나

- `auto`: 이모지가 없거나 native가 그리면 **폰트 요청 0**(`unicode-range` + probe). 국기만 native에서 빠진 브라우저(Windows Chromium 실측)만 flags subset을 한 번 받는다. 이후는 SHA 해시 `?ver=` URL로 브라우저 캐시 재사용.
- `font`(설계만, D8): 사이트가 Unicode 이모지 표현을 소유하기로 선택한 경우에만 full 1.88 MiB를 받는다.
- Core 이미지 fallback: 등장한 **서로 다른** emoji마다 파일 1개(같은 emoji 반복은 캐시). 요청은 별도 origin `s.w.org` — 첫 요청이면 DNS·연결 비용이 있고, 브라우저 캐시 분할 때문에 다른 WordPress 사이트에서 본 파일을 재사용한다고 기대하기 어렵다.

### 13.4 전환점 (추정 — 측정 전)

핵심 변수는 총 표시 횟수가 아니라 **서로 다른 grapheme 수**.

| 경우 | Core 이미지 fallback | COLRv1 WOFF2 |
|---|---|---|
| 같은 😀 500번 | 파일 1개 | 폰트 1개 |
| 서로 다른 emoji 500종 | 파일 약 500개 + DOM `<img>` 500개 + decode | 폰트 1개 |

- 이미지 한 장 평균 3–5 KiB로 가정하면(가정, 미측정) 바이트만으로 flags 699 KiB ≈ 140–230종, full 1.88 MiB ≈ 380–640종과 비슷하다. 실제 전환점은 요청 수·헤더·우선순위 경쟁·decode·DOM 노드 때문에 이보다 빨리 올 것으로 본다.
- 일반 글에 몇 개: Core 이미지가 더 가벼울 수 있음. picker·리액션 그리드·긴 타임라인처럼 수십~수백 종: 폰트가 유리.
- **측정 계획:** 서로 다른 emoji 50/250/500종 VQA 페이지에서 `auto`(flags)·Core 이미지·full font 세 경우를 `PerformanceResourceTiming`으로 전송량·요청 수·glyph 안정화 시간 비교. 13.4의 추정은 이 측정으로 대체한다.

### 13.5 #44001에 대한 함의

"self-hosted fallback = 이미지 전체 번들"이라는 전제는 성립하지 않는다. 같은 3,944개 RGI를 PNG+SVG 8,018개(13.7 MiB) 대신 COLRv1 WOFF2 한 파일(1.88 MiB)로 제공할 수 있고, 국기만 보완하면 699 KiB다. Axismundi Emoji는 두 경로(`auto`: 필요한 profile만 보완, `font`: 사이트가 전체 제공)를 실제 WordPress에서 보여주는 구현 근거가 된다(§12.2–12.7).

### 12.8 설정 서브메뉴: 세 렌더링 모드 + WordPress 이미지 스위치 (2026-09-16)

**위치:** Emojis › Settings(`includes/settings.php`, `manage_options`). 기존 Emojis 화면은 custom emoji 검토·로컬 등록(S2S) 전용이라 분리. 저장은 Settings API, sanitize는 1-인자 래퍼.

**렌더링 (`axismundi_emoji_unicode_rendering`)** — D8 구현 완료:

| 값 | 화면 이름 | 동작 | 폰트 요청 |
|---|---|---|---|
| `auto`(기본) | Automatic fallback | native 먼저, native가 못 그리는 flags profile만 flags subset | 필요한 브라우저만 flags 699 KiB |
| `font` | Use Noto Color Emoji | native 판정 없이 RGI 이모지 전부를 full 폰트로 감쌈. 폰트 실패 시 wrapper 해제 → Core | 이모지가 있는 첫 페이지에 full 1.88 MiB, 이후 캐시 |
| `core` | WordPress fallback only | 이 계층 끔 | 없음 |

구현: profile에 `modes` 추가(flags = `auto`, 새 `emoji` profile = `font`, match `rgi-emoji`). 어댑터는 현재 모드의 profile만 쓰고, `rgi-emoji`는 `new RegExp('^\p{RGI_Emoji}$','v')`(미지원 브라우저는 Extended_Pictographic|Regional_Indicator). `font` 모드 probe는 시퀀스만(🇨🇶, 🧑‍🩰) — 단일 코드포인트는 우리 폰트가 실패해도 시스템 emoji 폰트로 통과하므로. @font-face·wrapper 규칙은 현재 모드 profile만 출력(`auto`는 full 파일을 참조조차 안 함). full face는 unicode-range 없음(어댑터가 붙인 wrapper만 alias를 쓰므로).

**WordPress emoji images (`axismundi_emoji_core_image_fallback`, 기본 켬)** — 렌더링과 독립:
- 끄면 프론트·embed에서 `print_emoji_detection_script`·emoji 스타일 제거 → s.w.org 이미지 요청 0. 브라우저도 폰트도 못 그리는 이모지는 브라우저 표시 그대로.
- 관리 화면(블록 에디터는 Core가 원래 제거), RSS·이메일(`wp_staticize_emoji`)은 건드리지 않음 — 화면에 명시.

**실측 (wp-env, Windows Chromium):**
- 스위치 끔: `/emoji/`에 `wp-emoji-settings` 0·`wp-emoji-styles` 0, adapter는 유지. 기본값으로 되돌리면 Core 다시 1.
- `font` 모드 `/emoji/`(VQA 본문): 모든 Unicode 이모지(🏢, Emoji 17 ZWJ·피부색, 무지개·해적·트랜스젠더 깃발, U+1F3F4 단독 포함)가 `emoji` profile로 감싸짐, 폰트 요청은 `axismundi-noto-colrv1.woff2?ver=cac0e9358d0d` 하나, probe true, Core img 0, custom `:wordpress:` img 유지. `/ax-unicode-emoji-flags/`: 8개 모두 full, Core img 0(auto에서 이미지였던 😀·🏳️‍⚧️ 포함).
- 설정 서버 출력: `font` 모드 face는 `Axismundi Emoji Full` 하나, flags 파일 참조 0.
- audit: emoji-unicode 62, emoji-settings 15, emoji-picker 28, emoji-content 18 PASS. manifest `--verify`: `emoji` probe 2개 단일 glyph.
- 테스트 후 옵션 원복(미설정 = `auto`, 스위치 켬).

**작업 중 사고 기록:** Bash heredoc 속 Python 문자열로 PHP 정규식을 치환하다 백슬래시가 사라져 audit 65행이 PHP 문법 오류가 됨 → raw 문자열 Python 패치 파일(Write)로 복구. 이스케이프가 섞인 치환은 heredoc을 쓰지 않는다.

### 12.9 에디터 picker 실제 화면 검증 (2026-09-16, 로그인된 Chrome)

글 36153 편집 화면(창 1568×609, 서식 More 메뉴 › Emoji)에서 확인.

**동작 확인:** 서식 등록, Unicode 그룹 9, passive adapter(flags 두 profile true), picker CSS·폰트 스타일 로드. 패널 384px, 검색 자동 포커스, jump 48px, 타일 40px. 섹션: Recent 6, Custom 카테고리 3(펼침), Unicode 9(접힘, 타일 0). Recent의 🇰🇷·🇺🇳은 Windows에서 국기 폰트로 렌더. Flags jump → 270 타일, 262개 폰트 클래스(`flags-country`/`flags-subdivision`), 나머지 8은 native(🏁 🚩 등). 국기 타일 클릭 → 커서 위치에 🇦🇨 삽입, picker 열린 채 유지, Recent 맨 앞에 추가, undo로 원복(dirty false).

**발견해서 고친 것 3가지:**
1. 이중 스크롤: 창이 낮으면 Popover가 내용 최대 높이(인라인 `max-height`, `overflow:auto`)를 걸고 picker가 넘쳐 Popover에 스크롤이 따로 생김. jump의 `scrollIntoView`가 조상까지 스크롤해 검색·strip이 화면 밖으로 밀림. → `.components-popover__content`를 column flex + `overflow:hidden !important`, 패널 `flex:1 1 auto; min-block-size:0`. 결과: 패널이 Popover 높이(268px)에 맞게 줄고 Popover 스크롤 0.
2. `scrollTo({behavior:'smooth'})`가 에디터 Popover 안에서 전혀 움직이지 않음(1.3초 후 0px, 같은 오프셋을 직접 대입하면 즉시 이동; reduced-motion·`scroll-behavior` 아님). → 스크롤 영역 `scrollTop` 직접 대입. 결과: Flags·Smileys·Custom·Recent jump 모두 섹션 상단이 스크롤 영역 0px, 활성 jump 일치.
3. 검색 필드가 wp-admin `input[type=search]` 스타일(흰 박스·테두리·포커스 링)을 받음. → 선택자를 패널 아래로 올려 리셋. 결과: 테두리 0, 배경 투명, 그림자 없음, radius 0, 포커스 유지.
- 가로 스크롤은 실측상 없었음(scrollWidth = clientWidth 353). `overflow-x:hidden`은 재발 방지로 유지.

**자동화 메모:** 새로고침 직후 캔버스 좌표 클릭이 iframe에 전달되지 않는 경우가 반복됨 → `wp.data.dispatch('core/block-editor').selectBlock()`로 선택하고 버튼은 `element.click()`으로 진행.
4. 스크롤된 그리드가 strip 구분선 바로 아래에서 반쯤 잘린 줄로 시작해, 그리드가 strip 밑으로 파고드는 것처럼 보임(사용자 보고). 실제 요소 겹침은 없었다(strip 하단 448, 스크롤 영역 상단 456) — 카테고리 제목이 이미 스크롤되어 사라져 경계를 알려줄 것이 없었던 것. → 리액션 picker와 같이 카테고리 제목(h3)을 sticky(`inset-block-start:0`, `z-index:1`, 불투명 Popover 배경)로, custom 하위 카테고리(h4)는 제외. 스크롤 영역 상단 padding 제거(padding은 함께 스크롤되어 붙은 제목 위로 그리드 한 줄이 비침). 결과: Smileys 이동 후 180px 더 스크롤해도 제목이 스크롤 영역 0px에 붙어 있음, picker audit 28 PASS.
5. 패널의 flex `gap: 0.5rem`이 검색·strip·스크롤 영역 사이에 빈 띠를 만들어, strip 구분선과 sticky 제목 사이가 떠 보임(사용자가 DevTools 오버레이로 지적). → `gap: 0`. 결과: 검색→strip 0px, strip→스크롤 영역 0px, Smileys 이동 후 제목이 strip 구분선 바로 아래(스크롤 영역 top)에 붙음.

**릴리스 후 다듬기 (보류, 2026-09-16):** 에디터 picker 검색 필드(38px)·strip(48px+1px 테두리)·sticky 카테고리 제목(25–26px)이 `rem` padding·line-height 합산으로 소수점 높이를 가져, strip 구분선과 붙은 제목 사이에 1–2px 이모지 조각이 비침. 그림자로 덮지 말고 세 요소 높이를 정수 px로 계산해 소수점이 생기지 않게 한다(소유자 결정: 사이즈 계산이 정답, 우선순위는 문서 작업 뒤).

### 12.10 0.3.0 릴리스 준비 (2026-09-16)

- 문서: `readme.txt` 0.3.0으로 재작성(custom·Unicode 설명, Settings, External services — 원격 custom emoji 수신 시 선언 서버에서 가져오기 / Core의 WordPress.org 이미지와 끄는 스위치, 번들 폰트 OFL·Unicode 데이터 라이선스, changelog), `changelog.txt`에 0.2.0 이동, 플러그인 헤더 설명·버전·`AXISMUNDI_EMOJI_VERSION` 0.3.0. ARCHITECTURE는 상단 v0.3 안내, §1 세 층 표 Unicode 행, "out of scope" 문단, §9 Unicode 문단을 "v0.2 범위 → v0.3에서 대체"로 표시(삭제하지 않음).
- 배포 ZIP(`products/wordpress/_dist/axismundi-emoji.zip`): 2.94 MB, 45 항목, 압축 해제 4.56 MB, `tests/`·`scripts/` 유출 0, 폰트 2 + manifest·OFL·source, 카탈로그 11, 새 PHP·JS 포함, 역슬래시 경로 0.
- Plugin Check: 이번 작업 파일에서 나온 3건 수정 — `Tested up to` 7.0→7.1(ERROR), 짧은 설명 150자 초과(WARNING), `settings.php` 안내 문구의 `s.w.org` 표기가 OffloadedContent로 오판(ERROR, 실제 로드 아님 → "WordPress.org"로 표기). 재실행 결과 이번 작업 파일 0건.
- **남은 Plugin Check ERROR 23건은 기존 파일(이번 작업에서 미변경):** `admin.php` 번역자 주석 누락 11·출력 이스케이프 1, `catalogue.php` prepare 미사용 5, `binary-cache.php` prepare 1·`rename()` 2, `local.php` `rename()` 3. wp.org 제출 전 별도 커밋으로 정리 필요(소유자 결정 대기).
