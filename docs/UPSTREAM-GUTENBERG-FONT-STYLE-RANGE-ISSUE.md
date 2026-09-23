# Draft — Gutenberg issue: Appearance treats a `font-style` descriptor range as a selectable value

> 상태: **초안.** 올릴 곳: [WordPress/gutenberg](https://github.com/WordPress/gutenberg/issues) 새 이슈.
>
> **시점(2026-09-24 수정):** [#83128](https://github.com/WordPress/gutenberg/pull/83128) 머지를
> 기다리지 않는다. 수정 브랜치 `fix/font-style-range-appearance`는 trunk에서 따서 #83128과
> 같은 파일의 다른 위치만 건드리므로 충돌이 없고, 미머지 PR에 의존시키지 않는 편이 맞다.
> 순서만 지킨다: **이슈 먼저, 그 URL을 참조하는 PR 다음.**
>
> **스크린샷:** 필수가 아니다(사용자 확인, 2026-09-24). 본문 증거는 `CSS.supports()`와 CSSOM
> 측정이다. 이미지를 넣는다면 trunk의 깨진 상태에서 두 장 — ① Appearance에 범위가 옵션으로
> 보이는 화면 ② 선택 뒤 `font-style` 선언이 사라지고 computed가 `normal`인 DevTools — 이고,
> 게시 뒤 이슈를 편집해 덧붙이면 된다.
>
> **범위 결정(2026-09-24, 사용자):** 이 이슈는 A 하나만 다룬다. 섞지 않을 것 —
> ① synthetic italic/bold를 Appearance에 광고하는 정책(별도 이슈),
> ② Appearance = weight × style 재설계(#83148 축 논의),
> ③ `fontStretch`가 `styles`와 UI에 없는 문제(#43777).
>
> **수정안을 "범위를 `oblique`로 정규화"로 쓰지 말 것.** 각도 없는 `oblique`는 `oblique 14deg`
> 요청이라 Flex의 `-10deg..0deg`와 다른 좌표를 고른다. 안전한 최소 수정은 범위 디스크립터를
> Appearance의 선택값으로 쓰지 않는 것이고, `slnt`를 어떻게 제어할지는 #83148에 남긴다.
>
> **단일값 `oblique`는 건드리지 않는다.** 정적 face의 `fontStyle: "oblique"`는 실재하는 이산
> face이므로 Appearance에 나오고 적용되는 것이 정상이다. 이 이슈는 범위형만 대상으로 한다.
>
> 측정 기록(2026-09-24, 이 저장소에서 실측):
>
> - `fvar`(fontTools): `axismundi-roboto-flex.woff2`의 `slnt` = -10..0 (default 0).
>   테마의 `"fontStyle": "oblique -10deg 0deg"`는 이 범위를 정확히 옮긴 유효한 디스크립터다.
> - 에디터 실측(2026-09-24, `localhost:8884`, 사용자 Chrome): Typography → Text에서 Roboto Flex가
>   선택된 상태의 Appearance 옵션 20개 = 범위 라벨 10 + faux italic 10, **upright 0개**.
>   같은 패널 컴포넌트인 Blocks → core/paragraph는 Font가 Default라 패밀리가 해소되지 않아
>   `fontFamilyFaces = []` → `isSystemFont` 기본 목록으로 떨어진다. 코드 경로는 하나
>   ([typography-panel.jsx:928](https://github.com/WordPress/gutenberg/blob/trunk/packages/block-editor/src/components/global-styles/typography-panel.jsx#L928))이고
>   표면 차이는 해소된 패밀리 차이일 뿐, 두 번째 결함이 아니다. 라벨은 소문자 `oblique …`.
> - CSSOM(Chromium): 요소 속성 `font-style: oblique -10deg 0deg`는 CSSOM에 남지 않고
>   (`el.style.fontStyle === ""`), computed는 `normal`, `CSS.supports()`는 `false`.
>   단일 각도와 각도 없는 형태는 모두 유효.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-FONT-STYLE-RANGE-ISSUE.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

### Description

`getFontStylesAndWeights()` treats an `@font-face` `font-style` descriptor as a discrete appearance value. When a variable font declares a range, such as `oblique -10deg 0deg`, that raw descriptor becomes both the label and the stored value of an Appearance option, and the value it stores is not valid for the `font-style` property.

This is the style-side counterpart of the `font-weight` range handling fixed in #83128. The difference is that a weight range reduces reasonably to a discrete list of hundreds, while a slant range is a continuous angle and has no comparable discrete reduction.

Single-valued descriptors are not affected. A static face declaring `font-style: oblique` is a real discrete face, and offering `Oblique` for it is correct.

### Step-by-step reproduction instructions

1. Register a variable font whose `@font-face` declares a `font-style` range. Roboto Flex is the reference case, since its `fvar` table carries `slnt` from `-10` to `0` with a default of `0`, so the range descriptor is an accurate description of the binary:

```json
{
	"fontFamily": "Roboto Flex",
	"fontStyle": "oblique -10deg 0deg",
	"fontWeight": "100 1000",
	"fontStretch": "25% 151%",
	"src": [ "file:./assets/fonts/roboto-flex.woff2" ]
}
```

2. Open **Styles → Typography → Text** in the site editor and select that family.
3. Open the **Appearance** dropdown.

A panel where no family is resolved, such as **Styles → Blocks → Paragraph** with its font left at Default, falls back to the built-in style and weight lists and does not show this. The family has to be the one selected in the panel.

### Expected results

Appearance should not offer the descriptor range as a selectable value. Whatever the eventual control for a continuous slant axis turns out to be, an option whose value cannot be applied should not be listed.

### Actual results

Every weight is offered combined with the raw descriptor. For Roboto Flex the list holds twenty options, ten of them `Thin oblique -10deg 0deg` through `Extra Black oblique -10deg 0deg`, and ten synthetic italics. None of them is upright: the range has taken the place of `normal`, so this family cannot be set to `Regular` at all.

Selecting one stores `fontStyle: "oblique -10deg 0deg"`, which the `font-style` property does not accept. The two-angle form is allowed in the `@font-face` descriptor only. Measured in Chromium:

| Value | Kept in CSSOM | Computed | `CSS.supports()` |
| --- | --- | --- | --- |
| `oblique -10deg 0deg` | *(dropped)* | `normal` | `false` |
| `oblique -10deg` | `oblique -10deg` | `oblique -10deg` | `true` |
| `oblique` | `oblique` | `oblique` | — |

So the option is selectable and persists in the saved styles, but the declaration is discarded by the browser and the text does not slant. The failure is silent.

### Why this matters beyond the parse

The current Appearance control can remain useful for discrete static faces and named-instance presets. It cannot be the canonical control for variable fonts, whose `@font-face` descriptors may advertise independent continuous ranges. Variable-font capabilities and their selected values need a separate model, which is the subject of #83148.

Whether the combined weight-and-style dropdown should remain the default for static faces is a separate question that needs more observation, and is not what this issue asks to change.

### A note on the fix

Normalising the range to a bare `oblique` would not be equivalent. An angle-less `oblique` requests `oblique 14deg`, outside the `-10deg` to `0deg` the face advertises, so it would select a different coordinate than the descriptor describes.

The smaller change is to resolve the range to the end nearest upright, which is the slant the face gives a `normal` request, so this family offers `Regular`. That keeps Appearance from storing a declaration the property discards without inventing a control for the rest of the range, which belongs to the axis discussion.

### Screenshots, screen recording, code snippet

The measurement above, as a snippet to paste into a console:

```js
CSS.supports( 'font-style', 'oblique -10deg 0deg' ); // false
CSS.supports( 'font-style', 'oblique -10deg' ); // true

const el = document.createElement( 'div' );
el.style.fontStyle = 'oblique -10deg 0deg';
el.style.fontStyle; // "" — the declaration was not kept
```

### Environment info

- WordPress trunk / Gutenberg trunk
- Chromium

### Please confirm that you have searched existing issues in the repo.

Yes

### Please confirm that you have tested with all plugins deactivated except Gutenberg.

Yes
<!-- end of body -->
