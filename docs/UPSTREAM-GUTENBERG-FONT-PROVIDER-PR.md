# Draft — Gutenberg PR: font provider registry (A)

> 상태: **게시됨** 2026-09-18 — [WordPress/gutenberg#83127](https://github.com/WordPress/gutenberg/pull/83127)(draft), 커밋 `79e44cb9b4`·`1bfc2e9afe`·`62fe8c304e`(CHANGELOG). `--github` 되읽기 일치(SHA-256 `6e9667a4…`). 스크린샷은 사용자가 본문 표에 첨부할 몫.
>
> 브랜치: `C:/Users/thaum/dev/gutenberg` `add/font-provider-registry`, 커밋 `79e44cb9b4` + 리뷰 수정 `1bfc2e9afe`(upstream trunk `cd25c3864b` 기준), fork `Jiwoon-Kim/gutenberg`에 푸시됨.
> 설계: [AXISMUNDI-FONT-LIBRARY-ROLES.md](AXISMUNDI-FONT-LIBRARY-ROLES.md) §7 A. 범위 고정: provider 등록, `PLUGIN` 읽기 전용 표시, `@font-face` 출력. `axes`·`usage`·대체 배정·Typography UI는 넣지 않는다.
>
> ## 근거 (2026-09-18, 로컬)
>
> - `git diff --check`, `php -l`, phpcs 0, ESLint 0(기존 경고 1), prettier, `npm run typecheck`, 기존 font-library JS 테스트 22/22, `npm run build`. pre-commit(lint-staged) 통과.
> - PHPUnit `--filter Font_Provider` 29/29(리뷰 수정 후). JS font-library 테스트 26/26(새 `font-providers.jsdom.test.js` 4개: 조회 전 unresolved·완료 뒤 plugin 출처 정렬 목록, 실패 시 빈 목록, 같은 slug 키 구분, 키 안정성). 빈 provider 가드를 지우는 변이에서 해당 테스트가 실패함을 확인(테마 폰트가 있어야 드러나서 테스트가 테마 폰트를 넣는다).
> - 리뷰 수정(2026-09-18): face `fontFamily` 필수화(쉼표 든 `"ACME, Inc."` 보존 테스트), 포커스 복귀 키 `source/provider/slug`, provider 조회 완료 전 빈 상태 숨김.
> - 재VQA(8889를 CLI `wp core update --version=7.1.1`로 올린 뒤, `wp core version` 7.1.1): 같은 slug `manrope`를 theme과 provider에 둔 상태에서 provider → Back → provider 카드, theme → Back → theme 카드. 프런트 블록 2개·provider 규칙 3개, iframe 규칙 3개, 선택지 `manrope`·`fira-code`만, 비활성화 뒤 블록 1개, 비로그인 REST 401, 콘솔 오류 0.
> - 1차 VQA(WordPress 7.1.0, Twenty Twenty-Five, Edge): Library에 provider 묶음, 상세는 체크박스 0·Delete 없음·Update 비활성. 프런트는 테마 뒤 별도 `wp-fonts-local` 블록, face 자기 family 이름 유지, 파일 로드. 에디터 iframe에 규칙 3개, Noto Sans KR 로드. 블록 에디터 `fontFamilies`는 theme의 `manrope`·`fira-code`만. 비활성화하면 묶음 사라짐, provider가 없으면 `wp-fonts-local` 블록 1개(기존과 같음).
> - VQA에서 고친 것: face에 `fontFamily`가 없으면 미리보기 `formatFontFamily(undefined).trim()`로 Fonts 화면 전체가 오류 경계에 걸림 → 등록 시 채움. `fontStyle`이 없으면 variant 이름이 "100 900 undefined" → 등록 시 `normal`/`400` 채움.
> - `?ver=`가 붙은 src는 `src` 없는 `@font-face`로 출력됨(#66119 재현). 본문 한계에 적는다.
> - 스크린샷: scratchpad `pr-before-list.png`, `pr-after-list.png`, `pr-after-detail.png`. GitHub API로는 이미지를 올릴 수 없어 PR을 연 뒤 사용자가 표에 끌어다 넣는다. 그 뒤 `--github` 되읽기는 이미지 줄만 다르다.
> - CHANGELOG(`packages/global-styles-ui`)는 PR 번호가 생긴 뒤 추가 커밋.
> - 키보드(Playwright, Edge): Manrope에서 Tab → "Exo 2", Enter → 상세 초점 Back, 상세 컨트롤은 Library·Upload·Install Fonts·Back·Update, Back Enter → 초점 "Exo 2" 복귀.
> - "Use of AI Tools" 문구는 사용자가 정함(2026-09-18): 전부 직접 실행했다는 뉘앙스를 뺀 표현.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-FONT-PROVIDER-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Font Library: List fonts that active plugins supply

## Body (GitHub Markdown — paste as is)

## What?

Adds an experimental font provider registry. An active plugin that applies fonts to the site, such as a script fallback, an emoji font or an icon font, can declare them with `wp_register_font_provider()`. The Font Library lists them under the plugin's name, read-only, and their `@font-face` rules are printed on the front end and in the editor. They are not added to the font choices in Typography.

Related: #73694 (script fallback fonts), #82848 (font roles in the Font Library).

## Why?

The Font Library shows theme fonts as already there, and a plugin can only offer fonts as a collection to install. A plugin whose fonts are already in use therefore shows up as something to install. For example, a Korean fallback plugin that fills the Hangul range of the theme's font stack works without installing anything, yet the Library shows its fonts only in an "Install" tab, and installing them adds a separate font family instead of what the plugin already does.

This gap was left open on purpose. When the Fonts API was replaced by Font Face in 2023, the plan said plugins would integrate directly into the Font Library "once that capability exists" ([roadmap update](https://github.com/WordPress/gutenberg/issues/41479#issuecomment-1597915077), #51769). The half for fonts a user may choose to install became font collections. The half for fonts a plugin applies itself was left for the Library to handle, with the warning that fonts printed outside it cannot be seen or managed by the user. That is the state such plugins are in today.

The name is deliberate but the model is new. The removed Fonts API had a `wp_register_font_provider()` that chose how font files were delivered (local or remote); #51769 found it no longer needed, #52485 made it non-functional and #82813 removed its stub. Here a provider is the extension that supplies the faces.

## How?

- `WP_Font_Provider_Registry` with `wp_register_font_provider( $slug, $args )` and `wp_unregister_font_provider( $slug )`, in `lib/experimental/font-providers/`. `$args` takes `label`, `description` and `fontFamilies` in the theme.json `fontFamilies` format. As in theme.json, each face must name its `fontFamily`. Registration validates the shape and fills a missing `fontStyle` or `fontWeight` with the value `WP_Font_Face` prints by default, so the listed face matches the printed rule. A face keeps its own `fontFamily`, so a provider can list a face under a family with a different name.
- `GET /wp/v2/font-providers` and `/wp/v2/font-providers/<slug>`, for users who can `edit_theme_options`, as the Font Library requires.
- Provider faces are printed after the theme's on `wp_head` and appended to the editor iframe styles in `block_editor_settings_all`, both through `wp_print_font_faces( $fonts )`. With no provider registered nothing is printed: `wp_print_font_faces()` with an empty list falls back to the theme fonts and would print them twice.
- The Font Library lists each provider under its label, between the theme and custom fonts. The detail screen lists the faces without checkboxes, and there is no Delete button: the plugin manages them.
- Provider families are not merged into `settings.typography.fontFamilies`, so they are not font choices.
- No Experiments toggle: without a registered provider the Library and the output are unchanged.

Not in this PR, to keep the first step small:

- Where a fallback goes in a font stack, for which language and range (#73694). A plugin still does that itself; this PR only makes its faces visible and prints them.
- Font roles such as emoji or icon (#82848), and variation axes.

Known limits:

- A `src` with a query string, such as `font.woff2?ver=abc`, is dropped by `WP_Font_Face` and the rule is printed without a `src` ([Core Trac #66119](https://core.trac.wordpress.org/ticket/66119)). Plugins that version their font URLs cannot use this until that is fixed.
- The Library previews a family by drawing its name. For a face limited to another script, such as Hangul, the name falls back to another font, so the preview does not show the face.

## Testing Instructions

1. Activate the "Gutenberg Test Font Providers" plugin (`packages/e2e-tests/plugins/font-providers.php`, available in the `wp-env-test` environment). It registers Exo 2 for Latin capital letters only.
2. Go to Appearance > Fonts. Under the theme fonts there is a "Gutenberg Test Font Provider" group with Exo 2.
3. Open Exo 2. The screen says the plugin supplies it, lists one face, and has no checkboxes or Delete button. Update stays disabled.
4. Open Styles > Typography, or the font family control of a block. Exo 2 is not among the choices.
5. View the source of a front-end page. After the theme's `<style class="wp-fonts-local">` there is a second one with the Exo 2 face and `unicode-range:U+0041-005A`. Open a post in the editor: the editor canvas iframe has the same rule.
6. Deactivate the plugin. The group and the second style block are gone.

PHP tests: `npm run test:unit:php:base -- --filter Font_Provider`.

### Testing Instructions for Keyboard

1. On Appearance > Fonts, Tab from the last theme font to the first font in the provider group and press Enter. The detail screen opens with focus on the Back button.
2. Besides the tabs, the Back button and the disabled Update button, the detail screen has no controls: no checkboxes and no Delete button. Press Enter on Back; the list returns with focus on the same font.

## Screenshots or screencast

|Before|After|
|-|-|
|The plugin's fonts are not listed.|The Library lists them under the plugin's name.|
|||

|Detail|
|-|
||

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with implementation and testing. I reviewed the changes and test results.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
