# Draft — Gutenberg PR description: resolve a variable font's oblique range

> 상태: **초안.** 올릴 곳: `fix/font-style-range-appearance` 브랜치의 draft PR.
> 참조 이슈: [#83455](https://github.com/WordPress/gutenberg/issues/83455) (게시·대조 완료, `13dc81d9…`).
> 커밋: `d2b829dc5a` (trunk `4b9625ffc3` 기준).
>
> 형식은 [#83128](https://github.com/WordPress/gutenberg/pull/83128) 본문의 관례를 따른다 —
> What / Why / How / Testing Instructions / Testing Instructions for Keyboard / Use of AI Tools,
> 그리고 Claude Code 표기.
>
> **범위:** faux italic 정책, Paragraph 패널의 상속 결손, `font-synthesis-*`는 전부 제외.
> 범위 디스크립터를 유효한 값으로 정규화하는 것까지만.
>
> **정정(2026-09-25): 부호.** `oblique -10deg 0deg`를 "바이너리를 정확히 옮긴" 것으로 쓴
> 문장은 틀렸다. CSS는 `slnt` 좌표의 부호를 뒤집으므로 `slnt -10..0`인 face는
> `oblique 0deg 10deg`로 선언한다. 음수 범위로 선언하면 어떤 oblique 요청도 기울지 않는다
> (Chromium 실측). 수정 코드는 부호와 무관하다 — 어느 쪽이든 upright에 가장 가까운 끝이
> `0deg`이므로 테스트 기대값도 그대로다. 커밋 `cb86e418ca`에서 CHANGELOG 예시와 테스트
> 주석을 함께 고쳤다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-FONT-STYLE-RANGE-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

Fixes #83455.

## What?

A `@font-face` may declare `font-style` as a two-angle oblique range. The Appearance control offered that range as a value, which `font-style` does not accept. After this change the range resolves to the end nearest upright, so the family offers `Regular` instead of a row of options the browser discards.

## Why?

A variable font with a `slnt` axis declares the slant requests its face can match. Roboto Flex has `slnt` from `-10` to `0`, and `font-style: oblique` takes the angle with the sign flipped, so its face declares `font-style: oblique 0deg 10deg`.

`getFontStylesAndWeights()` passed that descriptor through as a style value, so it became both the label and the stored value of an appearance option. With Roboto Flex selected the list holds twenty options, ten of them `Thin oblique 0deg 10deg` through `Extra Black oblique 0deg 10deg` and ten synthetic italics, and none upright: the range has taken the place of `normal`.

The two-angle form belongs to the descriptor. The property takes at most one angle, so the declaration is dropped:

```js
CSS.supports( 'font-style', 'oblique 0deg 10deg' ); // false

const el = document.createElement( 'div' );
el.style.fontStyle = 'oblique 0deg 10deg';
el.style.fontStyle; // "" — not kept
```

Selecting one of those options therefore saves a declaration that does nothing, and the text never slants.

## How?

Read the descriptor as a value the property accepts before formatting it:

- `normal`, `italic`, `oblique`, and a single-angle `oblique 40deg` are styles an element can use and are kept as declared.
- A two-angle range resolves to the end nearest upright, which is the slant the face gives a `normal` request. `oblique 0deg 10deg` becomes `normal`; a range that excludes upright, such as `oblique 5deg 20deg`, becomes `oblique 5deg`.

This follows up on the capability-based Appearance list introduced in #61915 for #49090: a `font-style` descriptor range describes matching capability, not a discrete style value that can be stored on an element.

This is the style-side counterpart of the weight range parsing in #83128, and it deliberately stops there. Control over the rest of the range is a font axis question, discussed in #83148, not something an appearance list can express. The existing synthetic italic behaviour is unchanged.

## Testing Instructions

1. In the active theme's `theme.json`, register a variable font with a `slnt` axis and declare its range, for example Roboto Flex with `"fontStyle": "oblique 0deg 10deg"` and `"fontWeight": "100 1000"`.
2. Open **Styles → Typography → Text** in the site editor, select that family, and open **Appearance**.
3. On trunk every option carries the raw range, such as `Black oblique 0deg 10deg`, and there is no upright option. With this change the list starts at `Thin` through `Extra Black`, and `Regular` is selectable.
4. Choose `Regular` and confirm the front end prints `font-style:normal`. On trunk, choosing `Regular oblique 0deg 10deg` prints a declaration the browser drops, leaving the text upright with no indication that the setting had no effect.

A panel where no family is resolved, such as **Styles → Blocks → Paragraph** with its font left at Default, falls back to the built-in lists and shows neither behaviour. Select the family in the panel being tested.

Unit tests: `npm run test:unit -- packages/block-editor/src/utils/test/get-font-styles-and-weights.js`. Of the three cases added, the two range ones fail on trunk; the third pins that single-valued descriptors keep passing through unchanged.

### Testing Instructions for Keyboard

No change to the controls themselves; only the options listed for a font whose face declares a style range change.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with implementation and testing. I reviewed the changes and test results.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
