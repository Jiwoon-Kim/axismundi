# Draft — Gutenberg #83159 제목·본문 (통합 후)

> 상태: **초안, 미게시.** 올릴 곳: [WordPress/gutenberg#83159](https://github.com/WordPress/gutenberg/pull/83159) 제목·본문 교체.
>
> 핸드오프: `UPSTREAM-GUTENBERG-83159-INTEGRATION-HANDOFF.md`. 그 문서의 Deliverable대로
> **로컬 머지와 본문 초안까지만** 만들고 푸시·draft 해제·댓글은 하지 않는다.
>
> **머지 상태(2026-09-27):** `try/typography-axis-controls`(`ebd7071d7d`)를
> `add/font-variation-settings`에 일반 머지 → `02a87c926b`. force-push 없음, 충돌 없음.
> trunk 대비 27커밋 / 70파일 / +4140 −222. typecheck 0, lint 0.
>
> **경계 어긋남 1건(보고 대상):** 머지가 #83462의 수정 커밋 `8d480467fd`
> (인라인 `style.typography.fontFamily` 보존)를 함께 가져왔다. 그 커밋이 prototype
> 브랜치에서 먼저 만들어졌고 #83462로 cherry-pick된 순서라 조상 관계상 분리할 수 없다.
> 되돌리면 capability lookup이 인라인 family 블록에서 다시 상속 폰트를 읽어 VQA 1–4가
> 무너진다. **권고: 그대로 두고 본문에 명시한다.** #83462가 trunk에 들어가면 rebase 시
> 같은 패치라 자동으로 사라진다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83159-INTEGRATED-BODY.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Typography: Prototype the font capabilities a family's faces declare

## Body (GitHub Markdown — paste as is)

This draft has grown from the Font variations panel into the whole typography surface it belongs to. It is still a design experiment rather than a request to merge, and it now carries the work of #83141 and the Style, Weight and Width controls beside it, deliberately: the panel cannot be judged with one of its three neighbours missing, and reviewing them apart means reading the same font faces twice and guessing how the halves meet.

## What?

One reading of a family's faces, and the controls that follow from it.

`resolveFontFaceCapabilities()` reads `fontFace[]` once and returns what the faces cover on each axis. A face declaring a single value covers a point, where the ends are equal; a face declaring a range covers an interval. A family with a 400 and a 700 covers two points, not 400 to 700.

What each control offers follows from that shape rather than from a rule of its own:

```text
all points        a static family: the values its files draw, and no way to type one between them
an interval       a face interpolates: the named values inside it, a slider and a field over it
```

The controls themselves are one per CSS property, where Appearance was one item standing in for two:

```text
Typography
  Color
  Font
  Style     normal, italic when a face is italic, oblique when a face declares angles
  Weight    the weights the faces cover
  Width     the widths the faces cover
  Size and the text-layout controls

Font variations
  the axes no property owns: Grade, FILL, XTRA, optical size
```

## Why?

Because the two halves answer the same question and were answering it differently.

Building the width control showed what that costs. A static family with a `normal` and a `condensed` file was offered all nine widths the property names, and a slider to reach between them. Nothing draws those. The cause was not the control: one helper counted the widths declared, to decide whether to offer the control at all, and another returned a range only when a face declared one, which a static family never does, so the list had nothing to filter by. Each was right alone and wrong together.

Width is also where the shape of the whole model is clearest, because it is the axis a browser will not stand in for. There is no `font-synthesis-width`, so a family without widths has nothing to offer and nothing to fake. Weight and style have the synthesis width lacks, and that difference is worth being explicit about rather than letting it blur what a control claims.

## How?

- **Capability.** `resolveFontFaceCapabilities()` in `block-editor/src/utils`. `getFontWeightRange()` and `getFontStretchRange()` now read through it, so the range a slider moves over spans the declared intervals only: a static 900 beside a 300–800 axis does not make 850 reachable.
- **Style.** Reads the faces rather than adding to them. Italic appears for a family with an italic face, Oblique for one declaring a slant range, and a family with one upright face offers neither. Oblique opens a Slant control over the angles the face declares, read from the raw `fontStyle` descriptor, since resolving it picks one angle and a control needs both ends. An oblique starts at the angle CSS means by the word, 14deg, kept inside what the face can draw: where the face reaches it the angle is left out, and where it stops short the angle is written.
- **Weight and Width.** A select of the values the faces cover, and a toggle beside it for a slider and a field. A preset width is stored as the keyword, which is what both kinds of font have in common; a typed one as the percentage.
- **Shared input.** The slider, the field, the clamping and the out-of-range notice are one component. They had been written three times, and the declared range was applied to the slider in all three but to the field in only one, which is how a width could be typed to 200% and a slant past the angles its axis has. Each axis keeps what is its own: where its range is read from, its presets, its stored form, and what it says when a saved value is outside.
- **Order.** Style, Weight and Width sit under the font that decides them, and the size moves below, since a size is a decision about the text rather than something a font provides. No control gains or loses a value, a reset or a place in the panel's menu.

A value already saved outside a range is kept and explained rather than moved: a font can be changed under a value that suited another one.

## What this carries from elsewhere

- **#83141**, the weight work, for the reason above. If it lands first, this rebases onto trunk and the duplicate diff goes.
- **#83462**'s one-line fix, which preserves a font family written inline in a block's style. It is under review there on its own; it rides along here because the capability lookup this draft is about reads the family that fix restores, and without it a block of that kind is looked up against the inherited font instead.
- **#83456** is *not* here. The Slant control reads the raw face descriptor precisely because that fix resolves the two-angle form to a single usable value for the Appearance list, which is a different consumer with a different need.

## Testing Instructions

1. Register a variable family declaring `"fontWeight": "100 1000"`, `"fontStretch": "25% 151%"` and two faces, one `normal` and one `"oblique 0deg 10deg"`, with its `fvar` axes as `axes`. Set it as the site font.
2. Select a paragraph. Typography reads Color, Font, Style, Weight, Width, Size. Style offers Normal and Oblique and no italic; choosing Oblique saves `oblique 10deg`, since 14deg is past what the axis has, and opens a Slant control bounded 0 to 10.
3. Weight and Width each offer the values inside their ranges, with a toggle to a slider and a field. Type 200 into the width: it stops at 151.
4. Register a static family with a `normal` face and a `condensed` face. Its Width offers Normal and Condensed and nothing else, with no way to type a width between them.
5. Register a family whose faces declare no `fontStretch`. It has no Width item at all.
6. Font variations is its own panel below Typography, listing the axes no property owns. It is absent for a family that declares none.

### Testing Instructions for Keyboard

Each axis is its own panel item, so its options menu entry adds and removes it, and its reset affects that axis alone. In a slider and field pair, Tab reaches the slider and then the field; the arrow keys move by one in both.

## Not included

The faux weights. A family whose faces stop at 500 is still offered a Bold, and a font with no italic face is still offered one by the toolbar's formatting, which is right. Making the capability list stop claiming what the faces do not declare is a behaviour change worth its own decision, and #83148 has the argument.

The Blocks screen still reads the root family without the screen's prefix, as #83462 notes.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with the implementation and the measurements. I reviewed the changes and the results.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
