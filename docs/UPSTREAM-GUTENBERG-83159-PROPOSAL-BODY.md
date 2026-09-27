# Draft — Gutenberg #83159 본문 (proposal 전환)

> 상태: **초안, 미게시.** 올릴 곳: [WordPress/gutenberg#83159](https://github.com/WordPress/gutenberg/pull/83159)
> 제목·본문 교체. `UPSTREAM-GUTENBERG-83159-INTEGRATED-BODY.md`(2026-09-27 게시본)를 대체한다.
>
> **왜 다시 쓰나:** 전 버전은 스스로 "a design experiment rather than a request to merge"라고 썼다. 구현은
> 끝났고 Core 짝 PR까지 준비되니, 같은 내용을 mergeable proposal로 다시 쓴다. 톤만 바뀌는 게 아니라
> **무엇이 이 PR이고 무엇이 아닌지**를 목록으로 못박아 메가 PR의 리뷰 부담을 줄이는 게 목적이다.
>
> **게시 순서 — ①②③ 완료(2026-09-28):**
>
> ```txt
> ① Trac 티켓            core.trac.wordpress.org/ticket/66198   사용자가 생성
> ② Core draft PR        wordpress-develop#13789                8파일, body 검증 통과
> ③ backport-changelog   backport-changelog/7.2/13789.md        9e5e67f54b, push 완료
> ④ 이 본문으로 #83159 교체
> ⑤ 체크 종료 확인 후 Open 전환
> ```
>
> ③ 없이 ⑤로 가면 `Check for a Core backport changelog entry`가 빨간 채로 Open이 된다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83159-PROPOSAL-BODY.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Title

Typography: Let a family's faces decide what Style, Weight and Width offer

## Body (GitHub Markdown — paste as is)

This proposes one model for the typography panel: the faces of the family the text is drawn in decide what Style, Weight and Width offer; text layout stays a block's own policy; and the axes no CSS property owns stay in a Font variations panel of their own.

It began as the Font variations panel and grew into the surface that panel belongs to, because the panel cannot be judged with one of its three neighbours missing. It carries #83141's weight work for the same reason: reviewing them apart means reading the same font faces twice and guessing how the halves meet.

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
- **One rule, one place.** Which axes may be written to `font-variation-settings` is decided by the style engine, and the sanitizer asks it rather than keeping a copy. Two readings of one rule is the defect this model exists to remove, so the PHP does not reintroduce it.

A value already saved outside a range is kept and explained rather than moved: a font can be changed under a value that suited another one.

## What is in this pull request, and what is not

```text
this PR        the capability resolver, and the Style, Weight, Width and Font variations controls
Trac 66198 / wordpress-develop 13789   the Core side: the two style properties, the
               settings, a face's axes, safe CSS, and the PHP tests
#83141         the weight work, carried here so the panel can be read whole
#83462         the family a block's text is actually drawn in, under review on its own
#83456         the two-angle font-style descriptor, resolved for a different consumer
later          a capability list that stops offering a weight or style the faces do not declare
```

- **#83141** is here because the three controls are one decision. If it lands first, this rebases onto trunk and the duplicate diff goes.
- **#83462**'s one-line fix rides along, because the capability lookup this PR is about reads the family that fix restores; without it a block with an inline family is looked up against the inherited font instead. It is reviewed there on its own, and a rebase after it lands drops it as the same patch.
- **#83456** is *not* here. The Slant control reads the raw face descriptor precisely because that fix resolves the two-angle form to a single usable value for a different consumer with a different need.
- **The faux weights** are untouched. A family whose faces stop at 500 is still offered a Bold, and the toolbar's formatting still offers an italic to a font with no italic face, which is right for a formatting request. Making the capability list stop claiming what the faces do not declare is a behaviour change worth its own decision, and #83148 now carries the argument.
- **The Blocks screen** still reads the root family without the screen's prefix, as #83462 notes.

## Testing Instructions

1. Register a variable family declaring `"fontWeight": "100 1000"`, `"fontStretch": "25% 151%"` and two faces, one `normal` and one `"oblique 0deg 10deg"`, with its `fvar` axes as `axes`. Set it as the site font.
2. Select a paragraph and add Style, Weight and Width from the Typography options menu, as any control a block does not show by default is added. They sit in that order, after the font and before the size. Style offers Normal and Oblique and no italic; choosing Oblique saves `oblique 10deg`, since 14deg is past what the axis has, and opens a Slant control bounded 0 to 10.
3. Weight and Width each offer the values inside their ranges, with a toggle to a slider and a field. Type 200 into the width: it stops at 151.
4. Register a static family with a `normal` face and a `condensed` face. Its Width offers Condensed and Normal, in that order, and nothing else: there is no toggle to a slider, and no width to type between them.
5. Register a family whose faces declare no `fontStretch`. Width is not in the Typography options menu for it at all, so there is nothing to add.
6. Font variations is its own panel below Typography, listing the axes no property owns: the custom ones a file declares, and the optical size, which `font-optical-sizing` can only switch rather than set. It is absent for a family that declares none, and its own options menu adds its axes the same way.

### Testing Instructions for Keyboard

Each axis is its own panel item, so its options menu entry adds and removes it, and its reset affects that axis alone. In a slider and field pair, Tab reaches the slider and then the field; the arrow keys move by one in both.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with the implementation and the measurements. I reviewed the changes and the results.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
