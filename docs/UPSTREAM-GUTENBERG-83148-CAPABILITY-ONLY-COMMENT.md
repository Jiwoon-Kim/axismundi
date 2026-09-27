# Draft — Gutenberg #83148 코멘트: capability-only 목록과 기존 Appearance의 한계

> 상태: **초안, 미게시.** 올릴 곳: [WordPress/gutenberg#83148](https://github.com/WordPress/gutenberg/issues/83148) 댓글.
>
> **왜 지금 쓰나:** 9-26 Appearance 해체 댓글에서 capability-only 목록(faux Bold/Italic 제거)을 **별도 결정**으로 남겨 뒀다. 9-27 prototype에서 정적 폰트 Width 버그가 나왔고, 그 원인이 "기존 구조에는 이 정책을 담을 자리가 없다"는 것이었다. 추측이 아니라 실패한 화면이 근거다.
>
> **게시 시점 주의:** 9-26에 이미 Appearance 해체 댓글을 올렸고 9-27에 본문을 교체했다. **같은 스레드에 사흘 연속 내 글을 쌓지 않는다.** 반응이 오거나, 구현 PR을 열 때 그 본문에 싣는 편이 낫다.
>
> **실측 근거 (2026-09-27, 로컬 통합 prototype):**
> - 정적 family(`fontStretch: normal` + `condensed` 두 face)에서 Width가 **아홉 개 전부 + custom 슬라이더**를 제시했다. 어떤 파일도 그 사이를 그리지 않는다.
> - 원인: `hasFontStretchCapability()`는 선언된 폭의 집합을 세고, `getFontStretchRange()`는 범위를 선언한 face만 본다. 같은 `fontFace[]`, 다른 규칙.
> - `resolveFontFaceCapabilities()`로 합친 뒤: `Condensed · Normal` 둘만, custom 토글 0개.
> - `font-synthesis-width`는 없다 → 폭은 합성되지 않는다. `-weight`/`-style`/`-small-caps`만 있다.
> - Slant 범위 밖(Roboto Flex `0..10deg`): 합성을 끄면 14°·20°가 10°와 동일 렌더, 켜면 둘 다 고정값. 축이 아니라 합성.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83148-CAPABILITY-ONLY-COMMENT.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

## What a font offers, and what a browser will stand in for

The comment above left capability-only listing as its own decision: whether Appearance should stop offering an italic to a font with no italic face, and a bold to one with no weight above 500. Building the rest of this gave me a case that decides more than I expected, so I am writing it down here rather than in a pull request.

### The case

A static family with two files, one `normal` and one `condensed`. The width control offered all nine widths the property names, and a slider to reach between them. Nothing draws those: the family has two files and no way to interpolate.

The cause was not the control. Two helpers read the same `fontFace[]` by different rules — one counted the widths declared, to decide whether to offer the control at all; the other returned a range only when a face declared one, which a static family never does, so the list had nothing to filter by. Each was right on its own and wrong together.

Reading the faces once fixes it. Each axis comes back as coverage: a face declaring one value covers a point where the ends are equal, a face declaring a range covers an interval. A family with a 400 and a 700 covers two points, not 400 to 700. What the control does follows: all points is a static family, so it offers the values its files draw and no way to type one between them; an interval means a face interpolates, so a slider and a field over it.

### Why this decides the faux question

The same reading answers what Appearance should list, because a synthesised value is not coverage. It is not a point, since no file draws it, and it is not in an interval, since no axis reaches it. A list built from what the faces declare has no place to put it, and adding one back means writing code to say something the faces did not.

Width makes this plain, because it is the axis a browser will not stand in for. There is no `font-synthesis-width`, so a family without widths has nothing to offer and nothing to fake, and a list that describes it honestly is the only list there is. Weight and style have the synthesis width lacks, and that is the whole of the difference: not that they are different kinds of axis, but that a browser will cover for them.

That it will cover is not a reason to list what it invents. Measured against a font that declares `oblique 0deg 10deg`: with `font-synthesis-style: none`, asking for 14deg or 20deg renders exactly as 10deg does. With synthesis left on, both render alike at a slant the font does not have — the same for 14 and 20, so not the angle asked for either. The editor can offer that; it should not offer it as one of the font's styles.

### What I think this means

- Appearance lists what the faces declare. A font with one upright face offers one style; a font whose weights are 400 and 700 offers two.
- The toolbar's **B** and **I** are untouched. They are formatting, and a browser standing in for a missing face is the right outcome there.
- `font-weight: bolder` stays available as formatting for the same reason: it asks for more weight than the surroundings, not for a face the font has. It should not be shown as `Bold (700)` in a capability list, since it is relative to whatever the parent computed.

### What it takes

Not much, once the faces are read once — but that reading is the part the current structure has no room for. Appearance is one control over the cross product of two lists, with no width at all, so there is nowhere to say "these are the faces this family has" for one axis without saying it for the other, and nowhere to put an axis that has no synthesis to fall back on. The static width case is that gap rather than a defect in the control.

Values already saved are a separate question and I would keep them: a font can be changed under a value that suited another one, and a list is about what to offer rather than what to erase.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with the prototype and the measurements. I reviewed the changes and the results.
<!-- end of body -->
