# Draft — Gutenberg issue: font families cannot say what they are for

> 상태: **게시됨** 2026-09-14, [WordPress/gutenberg#82848](https://github.com/WordPress/gutenberg/issues/82848) — 제목을 "Allow Font Library font families to declare their intended use"로 바꾸고, 필드 모양은 열어두고, 이모지는 후속 소비자(해결 조건 아님)로, #57980을 Related에 추가한 본문(4,350자). API로 올려 라벨 없음.
>
> 올릴 곳: WordPress/gutenberg › Issues › **Feature request** (`[Type] Enhancement`). 라벨 후보(유지관리자 몫):
> `[Feature] Font Library`, `[Feature] Typography`. #82830의 로우레벨 하위 작업 — 게시 뒤 #82830에 "font family
> metadata is tracked in #xxxxx" 한 줄 링크(새 논지 아님, 위치 안내).
>
> ## 두 연결은 따로인가 (사용자 질문에 대한 답, 게시 안 함)
>
> 따로가 아니라 **한 파이프라인의 두 구간**이다. 블록은 Font Library를 직접 보지 않고 theme.json 프리셋만 본다.
>
> | 구간 | 코드 (Gutenberg trunk `edd6dcba5f`, Core trunk) | 통과하는 키 |
> |---|---|---|
> | Font Library 설치 | `wp_font_family` 글 + REST `font_family_settings` (`class-wp-rest-font-families-controller.php:324-361`) | `name`, `slug`, `fontFamily`, `preview`, `additionalProperties: false` |
> | 활성화 → theme.json | `global-styles-ui/src/font-library/context.tsx:459` `typography.fontFamilies.custom`, `cleanFontsForSave()`는 `id`만 제거하고 객체 통째 복사 | 클라이언트는 다 넘김 |
> | theme.json 정리 | `WP_Theme_JSON` `FONT_FAMILY_SCHEMA` + `remove_keys_not_in_schema()` (Gutenberg `lib/class-wp-theme-json-gutenberg.php:511, 1438, 1492`), 편집기 스키마 `fontFamilies` 항목 `additionalProperties: false` | `fontFace`, `fontFamily`, `name`, `slug` |
> | 블록 | `block-editor/src/components/font-family/index.jsx:19` `useSettings( 'typography.fontFamilies' )` | 프리셋 |
> | `@font-face` | Core `class-wp-font-face-resolver.php` 병합 theme.json `typography.fontFamilies` | fontFace |
>
> 그래서 메타데이터 하나를 쓸모 있게 하려면 **REST 스키마, theme.json 스키마·정리, 편집기 설정** 세 관문을 같이 열어야 한다.
> 플러그인은 우회 불가(정리 과정에서 삭제).
>
> ## 확인한 사실
> - wp-env(Axismundi 계열 테마) 병합 프리셋: `roboto-flex`, `roboto-serif`, `roboto-mono`, **`material-symbols-outlined`**,
>   `system-font` → 블록 글꼴 드롭다운에 아이콘 폰트가 본문 글꼴과 같은 목록으로 들어감(에디터 UI는 로그인 필요라 미확인, 데이터 출처로 판단).
> - Core emoji: `emoji-loader.js`가 `flag`/`emoji`를 따로 검사, `everythingExceptFlag` 상태(`:360`, `:412-424`), 하나라도 실패하면
>   스크립트 로드(`:427`). `emoji.js:238-240`은 `everythingExceptFlag`면 **국기만** 교체. 이미지 `https://s.w.org/images/core/emoji/17.0.2/72x72/`
>   (`formatting.php:6042`), SVG `/svg/`(`:6060`).
> - fontFace 스키마에 이미 `unicodeRange`가 있음 → 국기 서브셋 범위는 새 키 없이 선언 가능.
> - 중복 검색(`font library icon font`, `font family metadata`, `emoji font`, `font family role`): 같은 제안 없음. 가까운 것 #73694.
>
> ## 범위에서 뺀 것
> - Core가 Noto 국기 서브셋을 번들할지(라이선스 OFL, 업데이트, 포맷 CBDT/COLRv1) — 배포 정책, 후속.
> - emoji loader 동작 변경 자체 — 메타데이터가 생긴 뒤의 소비자 작업.
> - 가변 폰트 축 제어 — #82830 typography 작업. 배열 조합 버그는 Core Trac #66103 / wordpress-develop#13514.
> - Math — `core/math`는 font family 지원이 없고 해결할 문제가 아직 없음. 각주로만.
>
> ## 게시 전 확인
> - 아이콘 폰트가 글꼴 목록에 나오는 것은 문제가 아님(사용자 결정 2026-09-14: 글꼴로 쓰고 싶을 수 있음) → 제외 주장은 넣지 않음.
> - [x] Windows Chromium 실측(2026-09-14, Chromium 152, wp-env WP 7.1): Core 캐시 `flag: false`, **`emoji: false`** →
>   `everythingExceptFlag: false` → 😀와 🇰🇷 **둘 다** `s.w.org/.../17.0.2/svg/` 이미지로 교체됨. 원인은 Core 검사 버그:
>   6.9부터 Emoji 17 검사 문자열이 `'\uD83E\u1FAC8'`(짝 없는 서로게이트 + ᾬ + `8`)이라 가운데 픽셀이 비어 "미지원"으로 판정.
>   올바른 `'\uD83E\uDEC8'`은 이 브라우저에서 컬러로 렌더(색 픽셀 443) → **수정되면 이 환경은 `flag: false`, `emoji: true`로
>   국기만 교체**되어 이모지 사례의 전제가 성립. 이 버그는 별도 [Core Trac #66104](https://core.trac.wordpress.org/ticket/66104)로 올렸고,
>   이 이슈 본문의 이모지 사례는 "수정 후 동작" 기준으로 쓰되 그 티켓을 링크한다.
> - [ ] trunk 줄 번호 재확인
> - [ ] 의도 추정 문장 없음

---

## Title

Allow Font Library font families to declare their intended use

## What problem does this address?

WordPress treats every font family as a text font. A font family is described by `name`, `slug`, `fontFamily` and `fontFace`, and that is all that survives from the Font Library to the editor. Some fonts are installed for a different job, and neither the editor nor Core can tell them apart.

**The pipeline.** Blocks do not read the Font Library; they read `typography.fontFamilies` presets. So a font travels through three steps, and each one has a closed schema:

| Step | Where | Keys kept |
|---|---|---|
| Install | REST `font_family_settings` (`WP_REST_Font_Families_Controller`) | `name`, `slug`, `fontFamily`, `preview` (`additionalProperties: false`) |
| Activate | `global-styles-ui` writes to `typography.fontFamilies.custom` | the whole object is copied client-side |
| Save and merge | `WP_Theme_JSON` sanitizes `fontFamilies` with `FONT_FAMILY_SCHEMA`; the theme.json JSON schema has `additionalProperties: false` | `fontFace`, `fontFamily`, `name`, `slug` |
| Use | `FontFamilyControl` reads `useSettings( 'typography.fontFamilies' )`; `WP_Font_Face_Resolver` prints `@font-face` from the same data | presets |

Any key a theme or plugin adds is removed at the REST or theme.json step, so there is no way to extend this from outside Core.

**Two consumers that need to know.**

1. **Icon fonts.** A theme that ships an icon font such as Material Symbols registers it like any other family. It stays usable as a font, which is fine, but nothing can tell that it is also an icon source, so an icon picker or provider cannot offer it — the gap discussed in #82830 and, for the registry, #82229.
2. **Emoji fallback.** Core already detects missing flag support separately from other emoji: `emoji-loader.js` tests `flag` and `emoji` and records `everythingExceptFlag`, and when only flags fail, `wp-emoji.js` replaces only country flags, with images from `s.w.org`. (On Windows this is the expected outcome once the Emoji 17 test string is fixed — [Core Trac #66104](https://core.trac.wordpress.org/ticket/66104); today that test misreports, so every emoji is replaced.) A site that installs a small flags-only emoji font through the Font Library has no way to tell Core that this font could cover exactly that case, locally, before the remote images are used. The font can already declare its range with the existing `unicodeRange` descriptor; what it cannot declare is that it is an emoji font.

Other specialised fonts exist — mathematical fonts, for example — but they do not currently present the same integration pressure, so they are mentioned only for completeness.

## What is your proposed solution?

Add a schema-supported, family-level metadata field that lets a font family declare its intended use, and carry it through the whole pipeline: the Font Library REST schema and Font Collection schema, the theme.json JSON schema and `FONT_FAMILY_SCHEMA`, and the editor settings. With that in place, each consumer decides for itself what to do:

- an icon picker or icon provider can offer icon fonts, while they stay available as fonts;
- later, the emoji loader could consider a local emoji font for a capability it already knows is missing. That is a follow-up consumer the field would make possible, not a condition of this issue.

This issue is only about the metadata and its path, not about those consumers. Open questions:

- **Shape.** The field's name and values are open for discussion: a single string naming the use, a boolean per kind, or something else. A string extends without a schema break; a boolean is the smallest change. Coverage should not need a new key, since `fontFace` already has `unicodeRange`.
- **Default.** Absent means no declared use: existing fonts and themes behave exactly as they do today.
- **Scope of values.** Starting with the two consumers above, or naming a value only when a consumer lands.

Out of scope here: whether Core should bundle an emoji font, changes to the emoji loader, and variable-font axis controls (tracked with #82830; a related `font-variation-settings` compilation bug is [Core Trac #66103](https://core.trac.wordpress.org/ticket/66103)).

Related: #82830, #82229, #73694 (another piece of family-level information the Font Library cannot express — a generic fallback), #57980 (Font Collections list font family definitions in theme.json `fontFamily` format, the shape this field would extend).
