# Draft — Gutenberg #83148 코멘트: Appearance 해체

> 상태: **게시됨** 2026-09-26 — [issuecomment-5846008915](https://github.com/WordPress/gutenberg/issues/83148#issuecomment-5846008915). `--github` 되읽기 일치(SHA-256 `fb68c2d2…`).
>
> **트리 블록은 `text` 펜스 + ASCII**(사용자 지시 2026-09-26). 박스 드로잉(`├─`)을 쓰다가
> 사용자가 준 형태로 바꿨다. 줄바꿈이 합쳐지면 그림이 망가지므로 추출 후 raw로 `\n` 확인할 것.
>
> **왜 이 이슈인가:** #83148은 축 모델 이슈이고, Style/Slant UI는 거기 파킹돼 있다. Appearance 항목
> 해체는 그 UI를 놓을 자리를 정하는 일이라 같은 스레드가 맞다. 새 이슈를 열지 않는다.
>
> **사용자 결정(2026-09-26):** "Appearance라는 단어를 없앤다"가 아니라 **선택적 그룹 이름으로
> 남기고 항목을 해체**한다. 각 항목이 자기 capability·기본 노출·값·리셋을 가진다.
> `defaultControls.fontAppearance`는 호환 alias로 두고 내부에서 `fontStyle`·`fontWeight`로 푼다.
>
> **본문에 쓴 사실은 전부 코드·브라우저 실측(2026-09-26):**
> - `useHasAppearanceControl()`는 `settings.typography.fontStyle || fontWeight` — 설정은 이미 축별
>   (`typography-panel.jsx:122`). `useAppearanceControlLabel()`가 한 축만 켜지면 항목 이름을
>   `Font weight`/`Font style`로 바꾼다(`:126`). 3분기 → 축 셋이면 7분기.
> - 한 ToolsPanelItem이라 `hasValue`/`onDeselect`/`isShownByDefault`가 두 축에 공유된다
>   (`typography-panel.jsx:938-941`).
> - `combinedStyleAndWeightOptions`는 `fontStyles × fontWeights` **교차곱**이다
>   (`get-font-styles-and-weights.ts`). 따라서 결합 목록은 실재 face를 보장하지 않는다 —
>   Bold face와 Italic face만 있고 Bold Italic 파일이 없어도 `Bold Italic`이 나온다.
> - `defaultControls.fontAppearance` 사용처는 5곳: `typography-panel.jsx:215`(기본값)·`:940`,
>   그리고 `heading`/`pullquote`/`quote`의 **deprecated** 블록 정의 4곳. 현행 블록 등록에는 없다.
> - 합성 비대칭: Chromium에서 `CSS.supports` — `font-synthesis-weight` true,
>   `font-synthesis-style` true, `font-synthesis-small-caps` true,
>   `font-synthesis-width` **false**, `font-synthesis-stretch` **false**.
> - 현재 `fontStretch`는 theme.json **face 디스크립터에만** 있다
>   (`class-wp-theme-json-gutenberg.php:527`). `styles.typography`에 대응 키도, 블록 서포트도,
>   컨트롤도 없다 → Width 항목은 스타일 속성이 생긴 뒤의 일.
>
> **이름 일관성:** 이 이슈의 2026-09-23 댓글이 이미 "UI는 Width라 부르되 theme.json 키 이름
> (`fontWidth` vs `fontStretch`)은 구현 시점까지 미룬다"고 정해 뒀다. 본문도 그 선을 지킨다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83148-APPEARANCE-COMMENT.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

## Where the Style and Weight controls should live

The panel side of this proposal has been waiting on a question I can now answer from the code rather than from taste: whether **Appearance** should stay one control. I think it should become a group name, with one panel item per axis.

### The settings are already per axis; only the panel item is merged

`useHasAppearanceControl()` reads two independent settings, and the label changes to match whichever survives:

```js
function useHasAppearanceControl( settings ) {
	return settings?.typography?.fontStyle || settings?.typography?.fontWeight;
}
function useAppearanceControlLabel( settings ) {
	if ( ! settings?.typography?.fontStyle )  return __( 'Font weight' );
	if ( ! settings?.typography?.fontWeight ) return __( 'Font style' );
	return __( 'Appearance' );
}
```

A theme that turns off `fontStyle` gets an item called **Font weight**. So the model is already two axes, and the merge is a fact about the `ToolsPanelItem`, not about typography. An item whose name has to change to say what it currently contains is standing in for more than one thing.

### What the merge costs

`ToolsPanel` works per item, so one item means one of each of these for two axes:

- `hasValue` and `onDeselect` are shared. Resetting from the menu clears the style **and** the weight; there is no way to put back only the weight.
- `isShownByDefault` is shared. A theme cannot show weight by default and leave style in the menu.
- The label function branches three ways for two axes. A third axis makes it seven.

### The combined list does not guarantee a face

The reason to merge them would be that a static family ships `(style, weight)` as one file, so "Bold Italic" is a single face rather than two coordinates. The options are not built that way:

```js
fontStyles.forEach( ( { name: styleName, value: styleValue } ) => {
	fontWeights.forEach( ( { name: weightName, value: weightValue } ) => {
		combinedStyleAndWeightOptions.push( … );
```

It is the cross product of two lists that were derived separately. A family with a Bold face and an Italic face and no Bold Italic file still offers **Bold Italic**. So splitting the item gives up a guarantee the combined list never made.

### The shape

```text
Appearance  - optional group label
  Style
  Weight
  Width     - once a style property exists
```

Each item carries its own capability, default visibility, value and reset. Inside an item, the control follows the font: discrete options for the faces a static family ships, a range and a direct input for an axis a variable font declares. That is where slant finally has a home — **Oblique plus an angle, inside Style**, rather than a new concept beside it. And #83141 has already had to split Style from Weight for variable fonts, so this is the same seam, drawn once for both kinds of font.

Width is last on purpose. `fontStretch` exists in theme.json only as an `@font-face` descriptor today; there is no `styles.typography` key, no block support and no control, so the item needs a style property before it needs a design. (Per the naming note above, the UI says Width whichever key that turns out to be.)

### Why per axis is not only tidier

The three axes do not behave alike, so one control that mixes them hides the difference:

| | synthesised when the font lacks it |
|-|-|
| `font-weight` | yes — `font-synthesis-weight` |
| `font-style` | yes — `font-synthesis-style` |
| width | no — there is no `font-synthesis-width` |

A browser will invent a bold and a slant; it will not invent a condensed. If these controls are meant to show what a font can actually do, then weight and style have to draw the same line width already draws, and that is much easier to hold with an item per axis than with one list that spans them.

That line also separates two jobs that are currently blurred: a panel that reports what the font offers, and the toolbar's **B** and **I**, which are formatting the reader asked for and may well be synthesised. Keeping synthesised values out of the panel does not take **I** away from anyone; it stops the panel from calling a synthetic slant one of the font's styles.

### Migration

`defaultControls.fontAppearance` is used in `typography-panel.jsx` and in the deprecated definitions of Heading, Pullquote and Quote. Keeping it as an alias that expands to `fontStyle` and `fontWeight` covers those and any theme or plugin passing it, so nothing has to be changed at once.

Two things to settle before this is worth implementing:

1. Whether the panel keeps a visible **Appearance** grouping or simply lists the items. `ToolsPanel`'s only grouping today is the menu's split between shown and optional items, which follows `isShownByDefault`, not a heading over a set of items.
2. Whether capability-only listing lands with this change or separately. It is a behaviour change: a font with no italic face stops offering Italic here, and the values already saved on blocks stay saved and applied, as out-of-range weights do in #83141.

<!-- end of body -->
