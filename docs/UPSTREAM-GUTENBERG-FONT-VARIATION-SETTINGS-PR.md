# Draft — Gutenberg draft PR: fontVariationSettings prototype for #83148

> 상태: **draft 게시됨** 2026-09-19 — [WordPress/gutenberg#83159](https://github.com/WordPress/gutenberg/pull/83159), head `61ec6d56a2`, `--github` 되읽기 일치(SHA-256 `0881e5db…`). 이슈 [WordPress/gutenberg#83148](https://github.com/WordPress/gutenberg/issues/83148)의 축 모델(capability·policy·value)을 합의 전에 동작하는 구현으로 보여주는 **설계 실험 draft**. `Ready for review` 전환 기준(사용자 결정 2026-09-19): policy가 여러 face를 어떻게 해석하는지, unsupported block의 UI/출력 일치, 저장값 보존 정책이 이슈에서 정해진 뒤.
>
> 브랜치: `C:/Users/thaum/dev/gutenberg` `add/font-variation-settings`, upstream/trunk `3b5792e714` 위 5커밋: `362d9b44fe`(style 값·스키마·style engine) → `9208bf8c0d`(block support) → `66f75f6f8c`(패널 UI) → `92a89406b1`(리뷰 반영: support 검사·face 선택·family 변경 초기화·number 전용·Global Styles 설정 allowlist) → `61ec6d56a2`(blocks selector 기대값).
>
> 경계 계약(사용자 결정 2026-09-19): policy·capability는 UI가 무엇을 제안하는지만 정한다. raw theme.json·저장된 블록 속성·상속값을 정화하지 않는다. 같은 style node에서 사용자가 font family를 바꿀 때만 그 node의 축 값을 지운다. 상위 Global Styles family 변경으로 자식의 상속 family가 바뀌어도 자식의 값은 지우지 않는다. 현재 family에서 쓸 수 없는 저장값은 패널에 보이지 않고 보존된다("not available + Remove" UI는 이번 범위 밖, 필요하면 별도 이슈).
>
> ## 근거 (2026-09-19, 로컬)
>
> - PHPUnit: `--filter font_variation|Style_Engine` 86개, `font_variation|WP_Block_Supports_Typography_Test|WP_Theme_JSON_Gutenberg_Test` 321개 통과. JS: 축 계산 8개(단일 face 5 + 여러 face 3), style engine 포함 39개 통과. ESLint 0(suppressions 적용 `tools/eslint/lint-js.cjs`), typecheck 통과, phpcs 통과. `npm run docs:build` 산출물 포함.
> - VQA(8889 test env, WordPress trunk, TT5, 이 브랜치 빌드, `demos/gutenberg-font-variations/` 플러그인과 같은 fixture): Paragraph 패널 Grade·Optical size, Heading은 Counter width(XTRA) 추가, List 패널 없음. 저장 마크업 `style="font-variation-settings:&quot;GRAD&quot; 150"`, 프런트 computed `"GRAD" 150`·`"XTRA" 603, "opsz" 144`. GRAD 150 문단 안 `<strong>`은 여전히 더 굵다(스크린샷). 문단 Grade −200 → Typography Font를 Manrope로 → `fontVariationSettings` 제거, 패널 사라짐. Global Styles: Text 화면 Grade·Optical size, GRAD 40 → 캔버스 body `"GRAD" 40`, Font를 Manrope로 → 값 제거; Blocks → Heading 세 축, XTRA 560 저장; Blocks → List 패널 없음. 콘솔 오류 0. `/wp-admin/?font-variations-demo` 리다이렉트로 데모 글 생성·열기 확인.
> - 키보드(데모 글): Grade 슬라이더 ←← 150→148, Tab → Grade 숫자 칸, 입력 5 → `GRAD: 5`, 옵션 메뉴에 값 없는 축(Optical size) 추가 항목, Reset all → 값 제거. 콘솔 오류 0.
> - VQA 중 발견·수정: Global Styles `getSetting('')`가 `VALID_SETTINGS` allowlist로 설정을 조립해 `typography.fontVariations`가 빠져 있었음(`global-styles-engine/src/settings/get-setting.ts`).
> - 정적 블록(Paragraph·Heading)은 `style` 속성이 글 HTML에 저장되므로, 서버에서 출력 단계로 거르는 것은 구조적으로 불가(블록 서포트 PHP는 동적 블록에만 적용). 경계 계약상 거르지도 않는다.
> - Playground: Gutenberg는 PR CI 빌드 ZIP, fixture는 axismundi `demos/gutenberg-font-variations/demo-plugin`을 `git:directory` + 커밋 SHA로 고정. [데모](https://playground.wordpress.net/?gutenberg-pr=83159&blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2FJiwoon-Kim%2Faxismundi%2F93d994a%2Fdemos%2Fgutenberg-font-variations%2Fblueprint.json&storage=temp)는 fixture 설치 뒤 데모 글 편집 화면까지 재확인했고, PR 본문과 #83148 follow-up에 링크했다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-FONT-VARIATION-SETTINGS-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Typography: Prototype fontVariationSettings with theme-exposed axes

## Body (GitHub Markdown — paste as is)

This is a design experiment for #83148, opened as a draft so the model can be tried and discussed next to working code. It is not ready for review; I'll mark it ready once the open questions below are settled in the issue.

## What?

A working version of the three layers proposed in #83148:

- **Value:** `styles.typography.fontVariationSettings`, an object keyed by axis tag (`{ "GRAD": 50 }`), serialized to `font-variation-settings` by the style engine in PHP and JS. `wght`, `wdth`, `slnt` and `ital` are dropped, since they belong to `font-weight`, `font-stretch` and `font-style`; `opsz` is kept for a manual optical size.
- **Policy:** `settings.typography.fontVariations`, the axes a theme exposes per font family slug, optionally with a narrower range. It can differ per block through `settings.blocks`.
- **Capability:** a face `axes` list, for the axes a font file has, with their ranges and defaults.
- A `typography.fontVariationSettings` block support, enabled on Paragraph and Heading.
- A **Font variations** panel below Typography, in the block inspector and in Global Styles. It shows one slider and number field per axis, only for axes that are both in the policy and in the faces.

## Why?

Variable fonts can have axes beyond weight, width and slant, such as grade (`GRAD`) or optical size (`opsz`), but WordPress has no style property for them and no way for a theme to say which ones to offer. #83148 explains the model; this PR shows it working so the details can be judged against real behaviour.

## How?

- Style engine: a `fontVariationSettings` definition in PHP and JS. Only four-character alphanumeric tags with finite number values are output, the same in both engines. theme.json keeps the object when styles are sanitized for users without `unfiltered_html`, filtered by the same rules, and `font-variation-settings` is added to the safe style properties.
- Axes offered: the faces that can render the current font style and weight are picked (the style matches, and the weight or weight range includes it; all faces when none matches). An axis is offered only if all of those faces declare it, within the range they all support, intersected with the policy's range.
- Availability only controls what the UI offers. It does not sanitize raw theme.json, stored block attributes, or inherited values. Existing values are preserved unless the user explicitly changes the font family on the same style node: choosing another font in the Typography panel clears that node's axis values. A value the current family can't use is kept, and not shown.
- The Typography panel's Reset all leaves axis values to the Font variations panel.
- The block inspector has a new `fontVariations` slot group for the panel. Block style states (hover, viewports) don't show it yet.
- Global Styles reads `typography.fontVariations` from settings: it is added to the list of settings `getSetting()` assembles.

Open questions, for #83148:

- How a policy should read several faces, for example a normal and an italic face with different `opsz` ranges. This PR intersects the faces in use.
- The names `fontVariations` (policy) and `axes` (capability), and whether uploads and collections should provide `axes` read from the file's `fvar` table.
- Whether a panel-level slot group is the right way to place the panel.

## Testing Instructions

Try the prepared [WordPress Playground demo](https://playground.wordpress.net/?gutenberg-pr=83159&blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2FJiwoon-Kim%2Faxismundi%2F93d994a%2Fdemos%2Fgutenberg-font-variations%2Fblueprint.json&storage=temp). It installs this PR's build and the [pinned fixture](https://github.com/Jiwoon-Kim/axismundi/tree/7f02a89aae326486f68906408e15ed55f4f3199a/demos/gutenberg-font-variations/demo-plugin), then opens the demo post in the editor.

1. Add a variable font family to the active theme with a face `axes` list and a policy, for example Roboto Flex:
   ```json
   "fontFace": [ { "fontFamily": "Roboto Flex", "fontWeight": "100 1000", "src": [ "…" ],
     "axes": [ { "tag": "opsz", "min": 8, "default": 14, "max": 144 }, { "tag": "GRAD", "min": -200, "default": 0, "max": 150 }, { "tag": "XTRA", "min": 323, "default": 468, "max": 603 } ] } ]
   ```
   with `"settings": { "typography": { "fontVariations": { "roboto-flex": [ { "tag": "GRAD" }, { "tag": "opsz" } ] } } }`, and `XTRA` added under `settings.blocks["core/heading"]`. Set Roboto Flex as the site font.
2. In the editor, select a paragraph: Styles shows a Font variations panel with Grade and Optical size. Set Grade to 150: the paragraph gets `font-variation-settings: "GRAD" 150`, in the editor and on the front end, and bold text inside it is still bolder.
3. Select a heading: the panel also shows `XTRA`.
4. Select a list: there is no Font variations panel, as the block has no support.
5. On the paragraph, choose another font in Typography: the Grade value is removed and the panel goes away.
6. In the Site Editor, Styles > Typography > Text and Styles > Blocks > Heading show the same panel, and a value there applies to the site.

### Testing Instructions for Keyboard

1. Tab into the Font variations panel: each axis has a slider, moved with the arrow keys, and a number field.
2. The panel's options menu adds and removes axes, and Reset all clears them.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5, and Codex) assisted with implementation and testing.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
