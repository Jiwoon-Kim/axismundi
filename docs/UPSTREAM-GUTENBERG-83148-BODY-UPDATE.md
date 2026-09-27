# Draft — Gutenberg #83148 본문 업데이트 (capability-only + Progress 갱신)

> 상태: **게시됨(2026-09-28).** [WordPress/gutenberg#83148](https://github.com/WordPress/gutenberg/issues/83148) 본문 교체 완료. 게시 후 `--github` 재검증 통과(SHA-256 동일).
>
> **왜 댓글이 아니라 본문인가:** 이 스레드에 내 댓글이 이미 4개(9-18·9-19·9-23·9-26)이고 응답은 없다.
> 다섯 번째를 쌓는 대신, §3이 이미 capability 논증을 세워 놓고 멈춘 자리에 결론을 채운다.
> `UPSTREAM-GUTENBERG-83148-CAPABILITY-ONLY-COMMENT.md`의 논증이 여기로 흡수됐다(그 파일은 미게시로 종결).
>
> **동시에 고치는 사실 오류:** Progress의 `fontStretch`/Style 줄이 "not yet a pull request"라고 쓰는데
> 2026-09-27에 #83159로 올라갔다. 본문을 건드리는 독립적인 이유이기도 하다.
>
> **본문에서 바뀐 것 3곳:**
> 1. §3 끝에 `#### What a capability list should not offer` 추가 — faux weight/italic 정책, `bolder`,
>    합성 실측(0–10deg 폰트에서 14°·20°가 10°와 동일 렌더), 정적 width 버그가 드러낸 "구조에 자리가 없다".
> 2. Progress의 #83159 줄 — 범위 확대(Style·Weight·Width, `fontStretch`, slant angle) 및 #83141 diff 포함 명시.
> 3. Progress의 `fontStretch` 줄 → capability-only 미구현 항목으로 교체.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83148-BODY-UPDATE.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<!-- end of body -->" `
>   --candidate <본문 파일>
> ```

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
| `opsz` | `fontOpticalSizing` (a new style property, `auto` or `none`); a manual coordinate in `fontVariationSettings` | `font-optical-sizing`; `"opsz" <n>` when a size is chosen |
| custom axes (`GRAD`, `XTRA`, …) | `fontVariationSettings` | `font-variation-settings` |

`opsz` is the exception: its high-level property is a switch rather than a coordinate, so a chosen size has to go through `font-variation-settings`. Nothing needs to be written for the usual case — a value absent means the CSS initial `auto`, and the browser keeps following the font size — and a coordinate written in the value layer takes over from it, as it does for any axis with a property. For the other registered axes, CSS Fonts 4 asks for the high-level properties, and for `ital` and `slnt` it goes further: "User agents must apply at most one value due to the font-style property; both `ital` and `slnt` values must not be set together", and "The ital axis is not used to satisfy an oblique request." A style control is therefore one mutually exclusive choice — normal, italic, or oblique with an angle — not an italic switch beside a slant slider. The difference from `font-variation-settings` is visible:

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
		{ "tag": "opsz", "min": 8, "default": 14, "max": 144, "name": "Optical Size" },
		{ "tag": "wght", "min": 100, "default": 400, "max": 1000, "name": "Weight" },
		{ "tag": "GRAD", "min": -200, "default": 0, "max": 150, "name": "Grade" },
		{ "tag": "XTRA", "min": 323, "default": 468, "max": 603, "name": "Counter Width" }
	]
} ]
```

(Shortened: Roboto Flex has thirteen axes.) `axes` is a list because it describes the file, and it is what controls read ranges from, including a manual `opsz`. The existing descriptors stay as they are for rendering and face selection: `fontWeight: "100 1000"` is still what the browser matches on.

`tag`, `min`, `default` and `max` are the font's own. `name` is not: a file's `name` table has one for most axes, and a theme or plugin can supply one where it does not, so a control has something to label itself with other than four characters. An optional `step` may be added for the same reason — it says how finely this axis is worth editing, which the font does not say, since an axis coordinate is a continuous number. `FILL` declaring `min: 0, max: 1, step: 1` is enough for a control to be drawn as a switch without the metadata naming a control, which is a consumer's decision rather than a fact about the file.

The list belongs to the face rather than the family: a normal and an italic face can carry different axes or different ranges, and what a control offers is what the faces in use share.

**Availability — whether the site offers custom-axis editing.** `settings.typography.fontVariations` is a boolean, and like other settings a block can differ through `settings.blocks`.

This setting is about the Font variations panel alone. It is not the model for Style, Weight and Width: those are CSS properties whose options come from the font, and the section below says where each decision belongs.

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

### 3. Which decision belongs to the block, and which to the font

Typography has two kinds of control in it, and they are decided by different things.

A block can say whether it has text for a font to be applied to. It cannot say what a font is able to do. Today it says both: `fontStyle`, `fontWeight` and a width support would each be a switch a block turns on, and a block with one of them off offers nothing for that axis however the font is made. The first is a fact about the block; the second is a fact about the file, and reading it from the block means the two can disagree.

```text
block support        whether this block has a text surface font properties apply to
font capability      which styles, weights and widths the faces in use actually have
text layout          line height, letter spacing, indent and the rest: still per block
```

So Style, Weight and Width appear for a block that supports typography, and what each offers comes from the faces of the family the text is drawn in. A family with one upright face offers no italic and no oblique; a family with one width offers no width at all. This is easiest to see on width, because it is the one axis a browser will not stand in for: there is no `font-synthesis-width`, so a family without widths has nothing to offer and nothing to fake. Weight and style have the synthesis the other two lack, which is why a list built from them alone ends up describing a font that does not exist.

#### What a capability list should not offer

The same reading decides something this issue has so far left open: whether a list built from the faces should still offer a bold to a family whose weights stop at 500, and an italic to one with no italic face. Today it offers both.

A synthesised value is not coverage. It is not a point, since no file draws it, and it is not inside an interval, since no axis reaches it. A list built from what the faces declare has nowhere to put it, and putting it back means writing code to say something the faces did not.

That a browser will cover for a missing face is not a reason to list what it invents. Measured against a font declaring `oblique 0deg 10deg`: with `font-synthesis-style: none`, asking for 14deg or for 20deg renders exactly as 10deg does; with synthesis left on, both render alike at a slant the font does not have, and alike to each other, so not the angle asked for either. The editor can produce that; it should not offer it as one of the font's styles.

The width control is where the present structure showed it has no room for the rule. A static family with a `normal` file and a `condensed` one was offered all nine widths the property names, and a slider to reach between them. The cause was not the control: one helper counted the widths declared, to decide whether to offer the control at all, and another returned a range only where a face declared one, which a static family never does, so the list had nothing to filter by. Each was right alone and wrong together. Reading the faces once fixes it, and #83159 does. The point for this issue is why there were two readings: Appearance is one control over the cross product of two lists, with no width in it at all, so there was nowhere to say what faces a family has for one axis without saying it for the other, and nowhere for an axis that has no synthesis to fall back on.

The toolbar's **B** and **I** are untouched by this. They are formatting requests, and a browser standing in for a missing face is the right outcome there. `font-weight: bolder` stays available for the same reason, and by the same reason does not belong in a capability list: it asks for more weight than its surroundings rather than for a face the font has, so a list would have to show it as some fixed weight it is not.

Values already saved are a separate question, and I would keep them: a font can be changed under a value that suited another one, so this is about what to offer rather than what to erase.

Text layout stays where it is. Line height, letter spacing, indent and the rest apply whatever the font is, and whether a block offers them is a reasonable thing for that block to decide.

Font variations is the exception, and the setting above is about it: custom axes have no property of their own, a file can declare a great many of them, and whether a site wants that editing surface at all is a genuine choice.

### 4. A separate Font variations panel

Axes stored in `fontVariationSettings` get their own panel in the block inspector and in Global Styles, separate from Appearance, which keeps weight and style for static and variable fonts alike. The panel appears where the theme has turned it on, and offers the axes the faces in use declare, minus the ones OpenType registers. Its controls start hidden, as padding and margin do in Dimensions, and are added through the ToolsPanel menu or by already having a value; a font can declare a great many axes, and Roboto Flex alone leaves nine.

The panel offers every axis the faces declare except the four that belong to a property: `wght`, `wdth`, `ital` and `slnt`. `opsz` stays, since nothing else takes a coordinate for it, and so does `FILL`, which is how icon fonts such as Material Symbols express a filled glyph. Leaving `FILL` out of a text panel would take code to do and would decide for a consumer that has not asked: an icon block draws the same font through its own interface, and can read the same metadata without this panel pretending the axis is not there.

### 5. Where the metadata comes from

Three kinds of font end up in the same controls: the ones a theme ships, the ones a user installs through the Font Library, and the ones a plugin registers. They should reach the panel the same way rather than through three paths.

```text
Font Library / theme.json / a plugin
    -> the active families, as settings.typography.fontFamilies
    -> the faces of the family the text is drawn in
    -> what Style, Weight, Width and Font variations offer
```

The Font Library already stores faces in the shape theme.json uses, so this is mostly a question of what the shape can carry rather than of a new path. Adding `axes` to it is what would let the library read a file's `fvar` table once, on upload, and have every consumer of that family see the same capability. That is a direction rather than a promise: this issue asks for the contract, and reading `fvar` at upload time is #82848's neighbour rather than part of it.

### Open questions

- The name of the availability setting.
- Whether the per-property typography supports should give way to one that says a block has a text surface, and what that does to blocks and themes that set them today. Values already saved stay saved either way; this is about what is offered.
- Whether `fontOpticalSizing` should be written at all when it is `auto`, or only when a site asks for `none`. The CSS initial value is `auto`, so nothing has to be written for the usual case.
- Whether uploads should store `axes` read from `fvar`, and whether collections can provide them.
- Linking axes across a component: a button's label and icon sharing `GRAD`, as Material 3 suggests, seems better expressed as a component-level value both refer to than as one global grade for all text and icons.

### Progress

- [x] Weight ranges read correctly for the Appearance control — #83128
- [ ] Any weight in a variable font's range, stored in `fontWeight` — #83141 (open, awaiting review)
- [ ] Capability, availability and value, with a Font variations panel, and the Style, Weight and Width controls that model implies, including `fontStretch` for `wdth` and an angle for a font's slant — #83159 (draft, a design experiment for this issue; it carries #83141's diff so that the panel can be read beside its neighbours)
- [ ] A `slnt` range read as face capability rather than a style to select — #83456 (open, for #83455: a two-angle `font-style` descriptor is offered as an Appearance value the property discards)
- [ ] A list that offers only the weights and styles the faces declare — the section above; a behaviour change of its own, and not implemented
- [ ] `axes` read from a font file's `fvar` table when it is uploaded or installed
- [ ] The family a block's text is drawn in resolved before its faces are looked up — #83462 (open prototype, for #83459: the Blocks screen offers the built-in weights to a block that inherits its font)
- [x] Local reference consumer, outside Gutenberg: the Axismundi Dialogs icon block, an experiment toward `core/icon` v2, stores `FILL`, `GRAD` and `opsz` as a `fontVariationSettings` object, with `wght` in `fontWeight` — [Jiwoon-Kim/axismundi@23431ba](https://github.com/Jiwoon-Kim/axismundi/commit/23431ba709599ffaa370dbd815e871d581343242)

Related: #83141, #82848, #82830, [Core Trac #66103](https://core.trac.wordpress.org/ticket/66103) (the PHP array path for `font-variation-settings` in `WP_Font_Face`, fixed in [changeset 63653](https://core.trac.wordpress.org/changeset/63653) for 7.2).

<!-- end of body -->
