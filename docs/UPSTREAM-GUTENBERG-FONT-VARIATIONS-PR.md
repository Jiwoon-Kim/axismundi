# Draft — Gutenberg draft PR: any weight in a variable font's range

> 2026-09-26: #83128 머지 후 정리. 브랜치를 trunk `ad5b19d2c3` 위로 리베이스(`--force-with-lease`, 사용자 1회 승인)하여 범위 수정 커밋을 없애고 두 커밋만 남겼다 — `a9e305c9d2`(기능) + `fa3a8dcb12`(`parseFontWeightValue()` 공용 모듈). 본문에서 "Depends on #83128 … 앞선 커밋을 제거하겠다" 첫 줄 삭제, How?에 공용 파서·`"normal 900"` 보완 추가, Testing Instructions 8단계 추가, "Not in this PR"의 축 모델 설명을 #83148의 boolean availability와 #83159 프로토타입에 맞춤. VQA(8889, 리베이스 빌드): Keyword Range `normal 900` → `Regular (400)`–`Black (900)`, Range Test `250 750` → `Light (300)`–`Bold (700)`, Wide Range `100 1000` → `Thin (100)`–`Extra Black (1000)`, Static Test → Appearance 그대로.
> 2026-09-19: "Not in this PR" 문단에 축 모델 이슈 #83148 링크 추가.
> 상태: **draft 게시됨** — [WordPress/gutenberg#83141](https://github.com/WordPress/gutenberg/pull/83141). #83128(범위 파싱)에 의존한다. upstream에서 fork 브랜치를 base로 하는 stacked PR은 불가능하므로 `trunk` 기준 draft로 열었고, #83128이 머지되면 그 변경을 제거한다.
>
> 브랜치: `C:/Users/thaum/dev/gutenberg` `draft/font-variations`, 커밋 `134888279d` (`fix/font-weight-range-parsing`의 범위 수정 위). fork에 푸시됨.
> 범위(사용자 결정 2026-09-18): 가변 폰트에서도 Appearance 이름을 유지한다. Style은 기존 선택지를 유지하고, Weight는 preset과 직접 입력을 전환한다. 저장은 `fontWeight` 하나이며, 범위 밖 저장값은 자동 보정하지 않고 상태를 표시한다. `fontVariationSettings` object·`axes` 계약은 넣지 않는다.
> 설계 근거(2026-09-18 측정으로 정정): `font-variation-settings`의 `wght`는 wght 축이 있는 폰트에만 닿는다. Axismundi lab(`typography-axis.html`)을 Edge로 열어 `font-weight:400; font-variation-settings:'wght' 800`과 `font-weight:800`의 렌더링을 픽셀 비교: 라틴(Roboto Flex)·한글(Noto Sans KR 가변 100–900)은 같음, 웹폰트가 없는 가나(시스템 폰트)는 400 그대로. 즉 가변 폴백은 따라가고, 축 없는 폴백(시스템 폰트·정적 face)과 faux bold는 `font-weight`만 본다 → `fontWeight` 하나로 저장. 이전의 "한글 폴백이 어긋난다"는 설명은 틀렸다.
>
> ## 근거 (2026-09-18, 로컬)
>
> - 변경: `utils/get-font-weight-range.ts`(face 범위의 합집합, 정수 전체 파싱), `components/font-variations-control/index.tsx`(새 컴포넌트, `tsconfig.build.json` `files`에 추가해 타입 검사), `global-styles/typography-panel.jsx`(가변이면 교체, 항목 이름 "Font variations", 폰트 전환 시 가변 폰트는 preset 대신 범위로 유지 판단).
> - 테스트(슬라이더 레이아웃 기준): 유틸 4, jsdom 4(숫자 칸 값·슬라이더 min/max, 값 없으면 빈 칸, 범위 밖 200 그대로+안내+onChange 없음, 숫자 입력 시 스타일 유지), 브라우저 2(스타일 변경 시 굵기 유지, 슬라이더 Home 시 스타일 유지 — 로컬 Chromium 1243 없어 미실행, CI). ESLint 0, stylelint 0, typecheck.
> - VQA(8889, WordPress 7.1.1, 이 브랜치 빌드, 임시 플러그인이 theme.json에 Range Test `250 750`·Wide Range `100 1000`·Static Test 400/700 추가, 글 ID 9): 가변 폰트는 메뉴·패널 모두 "Font variations"(Style + Weight), 정적 폰트는 Appearance "Bold" 그대로. 저장된 200(Range Test) → 직접 입력 200 + 범위 밖 안내, 슬라이더 손잡이는 250, 속성 200 유지. 250·178 → 직접 입력, 안내 없음. 300 → `Light (300)`. 폰트 전환 Wide Range 420 → Range Test: 420 유지(기존 코드는 400으로 이동), → Static Test: 400(기존 동작). 콘솔 오류 0. 키보드: 숫자 칸 ↑ 178→179, Tab 순서 숫자 칸 → + → − → 슬라이더 → 전환 버튼, Enter로 preset 모드 `Custom (179)`. 1차 VQA에서 슬라이더 폭 0 → CSS로 폭 분배, 전환 버튼을 Font size처럼 제목 줄 오른쪽으로 옮김.
> - 추가 확인: 프런트 `style="font-style:normal;font-weight:178"`, 슬라이더 → 키 178→179·Home → 100(범위 최솟값), Style 선택은 Enter로 열림(선택지 3개: Default·Regular·Italic).
> - 슬라이더 레이아웃 재VQA(`419e6a8c27` 빌드): 200 → 숫자 200·슬라이더 250(범위 250–750)·눈금 5·안내, 250/300 → 그대로, 178/420(Wide Range) → 그대로·눈금 10, Static Test → Appearance "Bold", 패널 열어도 속성 유지, 슬라이더 → 178→179, Tab → 숫자 칸 ↑ 180, 콘솔 오류 0. 눈금은 트랙을 구간으로 나눠 그림(RangeControl 기본).
> - `wght`를 `font-variation-settings`에 저장하면 안 되는 측정 근거 추가: `font-variation-settings:'wght' 300`인 문단 안의 `<strong>`이 300으로 렌더링(상속된 설정이 `bolder`를 이김), `font-weight:300`이면 정상적으로 굵어짐. KR 프로바이더는 한자를 시스템 폰트에 맡기므로 한국어 본문의 한자도 가나와 같이 따라오지 않는다.
> - 최종 레이아웃(사용자 결정 2026-09-18): 슬라이더만 두고 100 단위 숫자 버튼 행을 달아 봤으나(정렬 0.5px, 넘침 없음) 빽빽하고 링크처럼 보여 폐기. Weight는 Font size처럼 제목 줄 오른쪽 토글로 preset 드롭다운(`Light (300)`, 범위 안 100 단위, 비preset 저장값은 `Custom (n)`) ↔ 슬라이더 + 숫자. Italic은 선택 목록 대신 I 토글(끄면 `normal` 저장). `RangeControl`의 `marks`는 `pointer-events: none`·`aria-hidden` 장식이라 클릭 preset으로 쓸 수 없음(rail.tsx). 최종 VQA: preset `Light (300)`, 178 직접 입력, 200 직접 입력+안내, Italic 아이콘 상자 왼쪽 937px vs 제목 936px, 토글은 제목 줄.
> - 이름(사용자 결정 2026-09-18): Typography 항목은 "Appearance" 그대로, 가변 폰트일 때 안쪽만 Style 선택 + Weight. "Font variations"는 `fontVariationSettings` 축(GRAD 등) 전용 별도 패널 이름으로 남김. 컴포넌트 `VariableFontAppearanceControl`. Italic은 토글·스위치를 시험했다가 기존 Style 선택으로 되돌림(`ital`은 `font-style` 경로). 최종 VQA(`f06a311639` 빌드): 메뉴 "Appearance", Style 선택지 Default·Regular·Italic, Tab 순서 Style → Weight → 토글 → 슬라이더 → 숫자, 가변→가변 420 유지, 가변→정적 400, 범위 밖 200 안내, 콘솔 오류 0.
> - 빌드 중에는 `build/`가 교체되어 사이트가 `gutenberg_override_style()` 미정의 fatal을 낸다(사용자가 한 번 봄). 빌드 전 사용자에게 알릴 것.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-FONT-VARIATIONS-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Typography: Let variable fonts use any weight in their range

## Body (GitHub Markdown — paste as is)

## What?

When the selected font family is variable, the **Appearance** control splits into a Style select, with the choices it already offers, and a Weight control. As with the font size, the weight is picked from a list, here the hundreds inside the font's range shown with their numbers (`Light (300)`), and a toggle next to the Weight label switches to a slider and a number field for any value in the range. Static fonts keep the combined style and weight list.

Both controls write the same `fontStyle` and `fontWeight` values as Appearance, so nothing changes in what is saved or printed: a weight of 178 is `fontWeight: "178"` and `font-weight: 178`.

## Why?

Appearance offers fixed combinations such as "Bold Italic". That matches static fonts, which ship one file per style and weight. A variable font draws any weight in its range (`"fontWeight": "100 900"` in theme.json), but the control lists only the hundreds, so 178 or 425 can't be chosen, and a saved value that is not a hundred shows as "Default".

## How?

- `getFontWeightRange()` reads the weight range that a family's faces declare. A family with a range is treated as variable; no new flag is needed.
- Both ends of a range are read by `parseFontWeightValue()`, a module the appearance list and this control share, so a face declaring `"normal 900"` is the range 400 to 900 in both. Before, the two had a parser each: #83128 taught the appearance list the keywords `@font-face` accepts, and the weight range kept its `parseInt`, which read `normal` as nothing and left the control with no range to offer. `lighter` and `bolder` are relative to a parent, so `@font-face` does not take them and a range naming one is still left alone rather than guessed at.
- `VariableFontAppearanceControl` renders the Style select and a Weight control. The Weight control follows the font size picker: a select with the hundreds inside the range, and a toggle beside the label for a slider and a number field. The number field is separate from the slider because `RangeControl`'s own input clamps the value it shows.
- A saved weight that is not a preset opens in the direct input and shows as `Custom (178)` in the select. A saved weight outside the font's range is kept and shown as it is, with a short note; it only changes when the user sets another. The slider only moves within the range.
- Style keeps the options and the behaviour of Appearance, including italic for fonts without an italic face.
- When the font family changes, a weight inside the new variable font's range is kept. Before, a weight that was not one of the listed hundreds was replaced by the nearest one.
- The weight stays in `fontWeight` rather than in `font-variation-settings`. `font-variation-settings` is inherited and applied after `font-weight`, so with `'wght' 300` on a paragraph, a `<strong>` inside it also renders at 300. It also only reaches fonts that have a `wght` axis: a fallback without one, such as a system font drawing a script the web font does not cover, stays at the element's `font-weight`, and faux bold is decided from `font-weight` as well.

Not in this PR: other axes. Slant (`fontStyle: oblique <angle>`) and width (`fontStretch`) map to CSS properties and could join Appearance later; axes without one, such as `GRAD`, would be stored in a `fontVariationSettings` style property and shown in a separate Font variations panel, which a theme can turn on. That model is proposed in #83148 and prototyped in #83159. Also open: whether italic should be offered when a font has no italic face.

## Testing Instructions

1. Add a variable font family with a face whose `fontWeight` is `"250 750"`, plus a static family with `"400"` and `"700"` faces, to the active theme's `theme.json`.
2. In the editor, select a paragraph and set its font to the variable family. Typography > Appearance shows a Style select and a Weight select listing `Light (300)` to `Bold (700)`.
3. Click the settings icon next to the Weight label. A slider and a number field appear; enter 178 or drag. The block gets `font-weight: 178`, in the editor and on the front end.
4. Click the icon again: the select shows `Custom (178)`.
5. Save a paragraph with `fontStyle: normal` and `fontWeight: 200` in that family, for example from the code editor, then select it. The Weight control opens with 200 in the number field, the slider at 250, and a note that 200 is outside the range (250–750). The value stays 200 until another weight is chosen.
6. Set a paragraph to a variable family with a range of 100–1000 and weight 420, then change its font to the 250–750 family: the weight stays 420.
7. Set the font to the static family: the Appearance control is shown as before.
8. Add a third family with a face whose `fontWeight` is `"normal 900"`, and set a paragraph to it: the Weight select lists `Regular (400)` to `Black (900)`, and the slider runs from 400 to 900.

### Testing Instructions for Keyboard

1. Tab to the Style select; it opens with Enter and is navigated with the arrow keys.
2. Tab to the Weight select; it opens with Enter and is navigated with the arrow keys. Tab to the settings icon next to the label and press Enter to switch to direct input.
3. In direct input, the slider moves by 1 with the arrow keys and Home goes to the lowest weight; Tab reaches the number field, where the arrow keys also change the weight by 1 and any value can be typed.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5, and Codex) assisted with implementation and testing.
<!-- end of body -->
