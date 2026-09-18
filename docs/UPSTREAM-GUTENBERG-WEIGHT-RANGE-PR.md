# Draft — Gutenberg PR: variable font weight range parsing

> 상태: **게시됨** 2026-09-18 — [WordPress/gutenberg#83128](https://github.com/WordPress/gutenberg/pull/83128)(일반 PR), 커밋 `906a8bfc20`·`14b1c6a72c`(block-editor CHANGELOG). `--github` 되읽기 일치(SHA-256 `97d9e4ff…`). 프런트 출력 `style="font-style:normal;font-weight:200"` 확인.
>
> 브랜치: `C:/Users/thaum/dev/gutenberg` `fix/font-weight-range-parsing`, 커밋 `906a8bfc20`(upstream trunk `cd25c3864b` 기준), fork `Jiwoon-Kim/gutenberg`에 푸시됨.
> 변경: `packages/block-editor/src/utils/get-font-styles-and-weights.ts`(범위 양 끝을 숫자 전체로 읽고 `ceil`/`floor`로 100 단위 경계), 회귀 테스트 4개.
> 제목 라벨: 사용자 제안은 "Font Library:"였으나 함수는 `block-editor`의 Typography → Appearance 드롭다운(블록·Global Styles)에서 쓰이므로 "Typography:".
>
> ## 근거 (2026-09-18, 로컬)
>
> - trunk 코드에 회귀 테스트 4개: `"250 750"` 기대 300–700 / 실제 200–700, `"50 900"` 기대 100–900 / 실제 500–900 → 2개 실패로 재현. `"100 950"`, `"100 1000"`은 우연히 통과. 수정 후 10/10.
> - ESLint 0, prettier, `npm run typecheck`, block-editor global-styles·font-appearance-control jsdom/node 테스트 150/150. 같은 경로의 브라우저 모드 테스트 4파일은 로컬에 Playwright Chromium 1243이 없어 실행 못 함(CI에 맡김). pre-commit 통과.
> - VQA(8889, WordPress 7.1.1, 이 브랜치 빌드, 임시 플러그인이 theme.json에 `fontWeight: "250 750"` face "Range Test" 추가, 글 ID 9): 선택지 Light(300)–Bold(700)와 같은 범위의 Italic, 200 없음. 저장된 200(`fontStyle: normal` 포함, UI 저장 형태) → 표시 "Default", 패널 연 뒤 속성 200 유지, 계산된 font-weight 200. 저장된 250 → "Default"(trunk에서도 목록 밖), 저장된 300 → "Light". 블록 4개 유효. 사용자가 브라우저 패널에서 직접 확인.
> - 사용자 결정(2026-09-18): 일반 PR로 연다. 후속 draft는 가변 폰트에서 Appearance를 Font variations 패널로 교체(Style/Weight 분리, Weight는 preset↔직접 입력, 범위 밖 저장값은 자동 보정 없이 상태 표시, `Light (300)` 표기). faux italic 정책은 그 draft에도 넣지 않고 후속 논의.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-WEIGHT-RANGE-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Typography: List only the weights inside a variable font's range

## Body (GitHub Markdown — paste as is)

## What?

The Appearance control lists weights outside a variable font's range, and leaves out weights inside it, when the range does not start on a hundred. After this change it lists only the hundreds inside the declared range, so some fonts get fewer options; weights already saved on blocks are left as they are.

## Why?

A variable face declares its weight range in `fontWeight`, such as `"250 750"`. `getFontStylesAndWeights()` read each end of the range from its first digit only:

- `"250 750"` offered Extra Light (200), which the font cannot draw; the browser renders 250 instead.
- `"50 900"` started at 500 and left out Thin to Regular (100–400).

Ranges on hundreds, such as `"100 900"`, and the `1000` end, which had its own case, were not affected.

## How?

Parse both ends as whole numbers and list the hundreds from the first at or above the start to the last at or below the end: `"250 750"` gives 300–700 and `"50 900"` gives 100–900. The separate case for `1000` is no longer needed.

## Testing Instructions

1. Add a variable font face whose weight range does not start on a hundred. For example, in the active theme's `theme.json` add a family with one face that uses any variable font file and `"fontWeight": "250 750"`.
2. In the editor, select a paragraph, set its font to that family and open Typography > Appearance.
3. On trunk the list starts at Extra Light (200). With this change it runs from Light (300) to Bold (700), with the same range in italic.
4. Add a paragraph in that font that already has `fontStyle: normal` and `fontWeight: 200` saved, for example from the code editor. Selecting it and opening the panel does not change the value, and the front end still prints `font-weight:200`. The control shows "Default" for it, as trunk already does for a saved weight that is not in the list, such as 250. Showing such values is left for a follow-up.

Unit tests: `npm run test:unit -- packages/block-editor/src/utils/test/get-font-styles-and-weights.js`. The two new range cases fail on trunk.

### Testing Instructions for Keyboard

No change to the controls themselves; only the options listed for a variable font change.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with implementation and testing. I reviewed the changes and test results.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
