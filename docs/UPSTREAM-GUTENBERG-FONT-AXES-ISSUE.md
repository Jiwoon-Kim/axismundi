# Draft — Gutenberg issue: variable font axes (capability, availability, value)

> 2026-09-19: 정책 형태를 list → 축 태그 keyed object로 변경(사용자 확정, 측정 근거: `array_replace_recursive`가 list를 인덱스로 병합 — 부모 [GRAD −50~50, opsz] + 자식 [XTRA] → XTRA가 GRAD 범위를 물려받음). Open question에서 "shape" 제거. 삭제 semantics(`null`)는 넣지 않음(사용자 결정, 별도 합의). **게시됨**(Playground `90bc650` 확인 후 `gh issue edit`, `--github` 일치).
> 2026-09-19: dialog-icon을 별도 문단에서 완료 체크 항목으로 변경(사용자 결정: 추적성, 항목 안에 Gutenberg 구현 아님을 명시).
> 2026-09-19: 본문 끝에 Progress 체크리스트 추가 — **게시됨**(axismundi `23431ba` 푸시 후 `gh issue edit`, `--github` 되읽기 일치, SHA-256 `47343ef2…`). 구현 PR은 모두 미머지라 미체크, dialog-icon은 체크 항목이 아니라 "second consumer / local reference implementation" 문단(사용자 결정).
> 상태: **게시됨** 2026-09-19 — [WordPress/gutenberg#83148](https://github.com/WordPress/gutenberg/issues/83148). 사용자 Chrome에서 Feature request 양식으로 게시(`[Type] Enhancement` 자동), `--github` 되읽기 일치(SHA-256 `5e66f6f0…`). 연결: #83141 본문에 링크 추가, #82830에 안내 댓글(discussioncomment-18505721).
>
> 근거: [AXISMUNDI-FONT-LIBRARY-ROLES.md](AXISMUNDI-FONT-LIBRARY-ROLES.md) §7 B·§8. 관련: #83141(가변 `wght`, draft), #83128(범위 파싱), #83127(font provider, draft), #82848(font family usage), Discussion #82830(아이콘 토론의 축 댓글).
> 기존 이슈 검색(2026-09-19): "variable font", "font variation", "optical size", "font-variation-settings" 제목 검색에 축 노출 이슈 없음.
>
> 리뷰 반영(2026-09-19, Codex): `axes`=전체 `fvar` 능력(등록 축 포함, descriptor는 렌더링·face 선택용으로 별도 유지), `opsz` 수동값은 `fontVariationSettings` 허용(숫자 받는 전용 속성 없음), Roboto Flex 13축에 `ital` 없음(fontTools로 `fvar` 확인: opsz 8/14/144, wght 100/400/1000, GRAD −200/0/150, wdth 25/100/151, slnt −10/0/0, XOPQ, YOPQ, XTRA 323/468/603, YTUC, YTLC, YTAS, YTDE, YTFI), 거부·이동은 새 구조화 style 값에만, `FILL`은 모델 규칙이 아니라 첫 텍스트 UI 정책.
>
> 정정: #82830 토론의 선생님 댓글은 object 예시에 `wght`·`opsz`를 넣고 "Paragraph가 `wght`, `wdth`, `opsz`를 조절"한다고 썼다. 2026-09-18 측정으로 등록 축은 전용 CSS 속성으로 가야 함이 확인되어, 이 이슈가 그 예시를 명시적으로 고친다.
>
> 측정(2026-09-18, Edge/Chromium, Axismundi lab `typography-axis.html`의 Roboto Flex·Noto Sans KR 가변):
> - `font-variation-settings: 'wght' 300`인 문단 안의 `<strong>`은 300으로 렌더링(`font-weight: 300`이면 굵어짐).
> - `font-weight:400; font-variation-settings:'wght' 800`: 라틴(Roboto Flex)·한글(Noto Sans KR 가변)은 `font-weight:800`과 같게, 웹폰트가 없는 가나(시스템 폰트)는 400 그대로.
> - Roboto Flex, `font-variation-settings:'GRAD' 150`인 문단 안의 `<strong>`: 400 고정과 다름(굵어짐), grade 없는 굵은 글자와 다름(grade 적용), `<strong>`에 GRAD 150을 직접 준 것과 픽셀 동일.
> - `<em>`과 `"ital" 0`은 측정하지 않음(같은 규칙의 추론으로 본문에 표기).
> - `@font-face`의 `font-variation-settings` descriptor: Safari 미지원, Chrome 140+, Firefox 62+ (MDN browser-compat-data 8.1.2).
>
> **재작성(2026-09-26): 정책 → 가용성.** §2의 `Policy` 층을 boolean 가용성으로 교체했다.
> 근거는 #83159 프로토타입 실측(로컬 wp-env 8889): boolean이 루트·블록 설정에 실리고,
> `false`인 블록은 폰트를 골라도 패널이 없으며, 축과 범위는 어느 쪽이든 face에서 온다.
> 축별 min/max는 폰트 선언을 복제해 어긋나고 실제 제약도 아니어서 버렸다.
> `array_replace_recursive` 리스트 병합 논거도 함께 사라졌다(boolean은 치환 병합).
>
> §1에 CSS Fonts 4의 규범 문장 두 개를 인용했다 — "must apply at most one value due to
> the font-style property; both `ital` and `slnt` values must not be set together",
> "The ital axis is not used to satisfy an oblique request." (w3.org/TR/css-fonts-4 원문 확인).
> Style UI가 상호배타 선택이어야 하는 근거다.
>
> §3은 패널 조건을 "테마가 켰고 face가 선언한 축 중 등록축 제외"로, 컨트롤 기본 숨김을 명시.
> Open questions에 "등록축은 어디서 제공하는가"를 추가하고 정책 이름 질문을 가용성으로 옮겼다.
>
> 본문에 넣지 않은 것: `<em>`이 실제 `slnt`로 렌더된다는 측정. 브라우저 face-matching 세부라
> 이 이슈에서는 보조 설명이고, Axismundi 테마 쪽 근거로만 남긴다(사용자 판단).
>
> **보강(2026-09-26): 저수준 override 근거.** 등록축을 값 객체에서 빼는 이유를 실측으로 적었다.
> `font-style: oblique 10deg` + `font-variation-settings: "slnt" 0` → **직립**(Chromium,
> 합성 끔, 아무 스타일도 안 준 것과 구분 불가). 즉 저수준이 나중에 적용돼 해당 축을
> 고수준 컨트롤에서 떼어낸다. "고수준 속성을 선호해서"가 아니라 이것이 근거다.
> Open question에 `opsz`의 Auto/Manual 함정을 따로 추가했다 — 슬라이더만 주면
> `font-optical-sizing: auto`가 조용히 꺼진다.
> 대응 테스트는 #83159 커밋 `6bcadcaf78`(다섯 축을 모두 선언한 face에 대해 커스텀축만 남는지).
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-FONT-AXES-ISSUE.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Typography: Expose the variable font axes a theme chooses, and keep registered axes on their CSS properties

## Body (GitHub Markdown — paste as is)

## What problem does this address?

A variable font can have many axes. OpenType registers five (`wght`, `wdth`, `opsz`, `slnt`, `ital`); Roboto Flex has thirteen, four of the registered ones (`wght`, `wdth`, `opsz`, `slnt`) and custom ones such as `GRAD`, `XTRA` or `YOPQ`. Material Symbols has `FILL`. Today WordPress can describe very little of this:

- A face accepts `fontVariationSettings` only as a CSS string, which sets the face's default coordinates. Nothing says which axes a face has or what their ranges are, so no control can be built for them.
- There is no `fontVariationSettings` style property, so a block or Global Styles cannot set an axis value.
- Nothing says which axes a site wants to offer. Showing every axis a file has would turn the sidebar into a font engineering console.

#83141 handles the most common case, `wght`, inside the existing Appearance control. This issue is about the rest, and about a rule that decides where each axis goes.

## What is your proposed solution?

### 1. Registered axes stay on their CSS properties

| Axis | Stored as | Output |
|-|-|-|
| `wght` | `fontWeight` | `font-weight` |
| `ital`, `slnt` | `fontStyle` | `font-style: italic` / `oblique <angle>` |
| `wdth` | `fontStretch` (a new style property) | `font-stretch` |
| `opsz` | nothing by default; a manual value in `fontVariationSettings` | `font-optical-sizing: auto` already follows the font size; `"opsz" <n>` when set |
| custom axes (`GRAD`, `XTRA`, …) | `fontVariationSettings` | `font-variation-settings` |

`opsz` is the exception: its only high-level property is the `auto`/`none` switch, so a chosen size has to go through `font-variation-settings`. For the other registered axes, CSS Fonts 4 asks for the high-level properties, and for `ital` and `slnt` it goes further: "User agents must apply at most one value due to the font-style property; both `ital` and `slnt` values must not be set together", and "The ital axis is not used to satisfy an oblique request." A style control is therefore one mutually exclusive choice — normal, italic, or oblique with an angle — not an italic switch beside a slant slider. The difference from `font-variation-settings` is visible:

- `font-variation-settings` is inherited and applied after `font-weight`, so with `"wght" 300` on a paragraph, a `<strong>` inside it also renders at 300. By the same rule, `"ital" 0` on a paragraph would keep an `<em>` inside it upright in a font with an `ital` axis.
- It only reaches fonts that have the axis. With `font-weight: 400; font-variation-settings: "wght" 800`, Latin in Roboto Flex and Hangul in a variable Noto Sans KR rendered bold, but Kana drawn by a system font stayed at 400.
- Face selection among several files and faux bold are decided from `font-weight`.

Custom axes have no high-level property and don't interact with `<strong>` or `<em>`: in Roboto Flex, a `<strong>` inside a paragraph with `"GRAD" 150` still renders bolder, and with the grade applied.

This corrects the object example in my earlier comment on #82830, which put `wght` and `opsz` in `fontVariationSettings`.

### 2. Three layers: capability, availability, value

**Capability — which axes a face has.** A face gets an `axes` list: every axis in the file's `fvar` table, registered or custom, with its range and default. Fonts uploaded or installed through the Font Library can have it read from the file; a theme declares it for the fonts it ships.

```json
"fontFace": [ {
	"fontFamily": "Roboto Flex",
	"src": [ "file:./assets/fonts/roboto-flex.woff2" ],
	"fontWeight": "100 1000",
	"axes": [
		{ "tag": "opsz", "min": 8, "default": 14, "max": 144 },
		{ "tag": "wght", "min": 100, "default": 400, "max": 1000 },
		{ "tag": "GRAD", "min": -200, "default": 0, "max": 150 },
		{ "tag": "XTRA", "min": 323, "default": 468, "max": 603 }
	]
} ]
```

(Shortened: Roboto Flex has thirteen axes.) `axes` is a list because it describes the file, and it is what controls read ranges from, including a manual `opsz`. The existing descriptors stay as they are for rendering and face selection: `fontWeight: "100 1000"` is still what the browser matches on.

**Availability — whether the site offers this editing.** `settings.typography.fontVariations` is a boolean, and like other settings a block can differ through `settings.blocks`, for example an icon block that offers a fill where running text does not.

```json
"settings": {
	"typography": { "fontVariations": true },
	"blocks": { "core/quote": { "typography": { "fontVariations": false } } }
}
```

An earlier revision of this proposal had the theme name the axes instead, per family and per tag, each with an optional narrower range. Building it changed my mind. That list restates what the font already declares, so replacing the file leaves the two disagreeing, and it constrains nothing: a narrower slider does not stop a value reaching `styles` or hand-written CSS. What a site genuinely decides is whether this editing appears at all, which is the shape other opt-in features already use, and a boolean merges across origins without the keying a list needed.

Measured in the prototype: the boolean reaches root and block settings, a block that sets it false shows no panel even with the family selected, and the axes and their ranges come from the faces either way.

**Value — what the user chose.** `styles.typography.fontVariationSettings` is an object keyed by tag, serialized at the style engine boundary.

```json
"styles": { "typography": { "fontVariationSettings": { "GRAD": 20 } } }
```

An object also lets origins merge per axis. `font-variation-settings` replaces the whole list, so setting one axis on a nested element otherwise means repeating every other custom axis. In this new structured value, `wght`, `wdth`, `slnt` and `ital` would be moved to their properties or rejected by the schema, the UI and the style engine; `opsz` is allowed, since nothing else takes a coordinate for it. `font-variation-settings` is the low-level control, applied after the properties that map to the same axis, so a coordinate written here takes that axis away from the control that owns it: `font-style: oblique 10deg` with `"slnt" 0` on the same element renders upright, indistinguishable from no style at all. That is what keeps the registered axes out of this value and out of the panel, rather than a preference for the high-level properties. The existing string form of the face descriptor and CSS written by hand stay as they are, as an escape hatch; `@font-face` defaults set there are not reliable across browsers anyway (Safari does not support the descriptor), so values belong in styles.

### 3. A separate Font variations panel

Axes stored in `fontVariationSettings` get their own panel in the block inspector and in Global Styles, separate from Appearance, which keeps weight and style for static and variable fonts alike. The panel appears where the theme has turned it on, and offers the axes the faces in use declare, minus the ones OpenType registers. Its controls start hidden, as padding and margin do in Dimensions, and are added through the ToolsPanel menu or by already having a value; a font can declare a great many axes, and Roboto Flex alone leaves nine.

To start with, the text panel would not offer `FILL`, which is how icon fonts such as Material Symbols express a filled glyph; a consumer of icon fonts (#82848 would let a family declare that use), such as an icon block, would show it in its own axes panel. The data model itself doesn't need to restrict the tag.

### Open questions

- The name of the availability setting.
- What offers the registered axes this panel leaves alone. A style control has to be one mutually exclusive choice, and `opsz` has no property that takes a coordinate, so a manual optical size would write the same value this panel writes.
- Whether `opsz` needs a switch of its own. `font-optical-sizing` is `auto`, so the browser already follows the font size; a bare slider would turn that off without saying so, which argues for offering the choice as auto or a value rather than a value alone.
- Whether uploads should store `axes` read from `fvar`, and whether collections can provide them.
- Linking axes across a component: a button's label and icon sharing `GRAD`, as Material 3 suggests, seems better expressed as a component-level value both refer to than as one global grade for all text and icons.

### Progress

- [ ] Weight ranges read correctly for the Appearance control — #83128
- [ ] Any weight in a variable font's range, stored in `fontWeight` — #83141 (draft, builds on #83128)
- [ ] Capability, availability and value, with a Font variations panel — #83159 (draft, a design experiment for this issue)
- [ ] A `slnt` range read as face capability rather than a style to select — #83456 (draft, for #83455: a two-angle `font-style` descriptor is offered as an Appearance value the property discards)
- [ ] `fontStretch` for `wdth`
- [ ] `axes` read from a font file's `fvar` table when it is uploaded or installed
- [ ] The family a block's text is drawn in resolved before its faces are looked up — #83462 (draft prototype, for #83459: the Blocks screen offers the built-in weights to a block that inherits its font)
- [x] Local reference consumer, outside Gutenberg: the Axismundi Dialogs icon block, an experiment toward `core/icon` v2, stores `FILL`, `GRAD` and `opsz` as a `fontVariationSettings` object, with `wght` in `fontWeight` — [Jiwoon-Kim/axismundi@23431ba](https://github.com/Jiwoon-Kim/axismundi/commit/23431ba709599ffaa370dbd815e871d581343242)

Related: #83141, #82848, #82830, [Core Trac #66103](https://core.trac.wordpress.org/ticket/66103) (the PHP array path for `font-variation-settings` in `WP_Font_Face`, fixed in [changeset 63653](https://core.trac.wordpress.org/changeset/63653) for 7.2).
<!-- end of body -->
