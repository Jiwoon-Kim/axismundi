# Draft — Gutenberg prototype PR description: capability lookup from the effective family

> 상태: **초안.** 올릴 곳: `try/block-screen-inherited-font-capabilities` 브랜치의 **draft prototype PR**.
> 참조 이슈: [#83459](https://github.com/WordPress/gutenberg/issues/83459) (게시·대조 완료, `1c81d66d…`).
>
> **이건 머지를 노리는 PR이 아니다.** 설계 질문(A/B/C 중 B가 맞는가)을 코드로 보여주는 것이 목적이고,
> 유지보수자가 "Blocks 화면은 block-local 값만 다룬다"고 하면 닫는다.
>
> **테스트 없음 — 정직하게 적는다.** Appearance는 패널 옵션 메뉴 뒤에 있고 이 컨트롤의 기존 커버리지는
> 전부 browser 테스트다. 로컬에서 신뢰할 만한 하네스를 만들지 못했고(메뉴 토글 클릭이 타임아웃),
> 접근이 바뀌면 테스트도 다시 써야 하므로 방향이 정해진 뒤로 미룬다. 이 사실을 본문에 쓴다.
>
> **알려진 한계:** 루트 읽기가 `prefix`를 무시하므로 블록 스타일 변형 화면에서는 변형의 typography가
> 아니라 루트를 본다. 프로토타입 범위로 두고 본문에 적는다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-BLOCK-SCREEN-INHERITED-FAMILY-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

Prototype for #83459. Opened to ask a question rather than to merge, and happy to close it if the answer is no.

## What?

In **Styles → Blocks → <block>**, Appearance is built from the block's own font family. A block that draws its text in the root font has none, so the control falls back to the built-in list and offers weights the font does not provide.

This adds an optional `capabilityFontFamily` to `TypographyPanel`: the family the text is drawn in when neither the panel's value nor the value it inherits names one. The Blocks screen passes the root font family, and the control looks its faces up instead of falling back.

## Why?

The question behind #83459 is which family should decide what Appearance offers:

- **A.** The block's own `fontFamily` only, as today.
- **B.** The family the block actually renders in, resolved through the cascade.
- **C.** Neither: disable Appearance when no family is resolved.

This prototype implements B, because the text really is drawn in the inherited family and #49090 asked for the control to offer what the font has. If A is the intended model for this screen, the bug is instead that the built-in list stands in for "unknown", and this should be closed in favour of C or of saying so in the UI.

Worth noting that the three surfaces disagree today. The block inspector resolves the inherited family already, through `resolveStyle()` and its `root` layer; the Blocks screen reads `getStyle( gs, path, blockName )`, which stops at `styles.blocks.<name>`. The same block gets different Appearance options depending on where it is edited.

## How?

`TypographyPanel` takes `capabilityFontFamily` and uses it for one thing:

```js
const familyForFaces = fontFamily ?? decodeValue( capabilityFontFamily );
```

That value feeds `getMergedFontFamiliesAndFontFamilyFaces()` and nothing else. It does not become a value, is not written on change, does not affect what the Font control displays, and does not mark anything as inherited: `fontFamily`, `inheritedFontFamily` and `isFontFamilyPlaceholder` are untouched, so the Font control still reads `Default` and the inheritance treatment is unchanged.

`screen-block.tsx` reads the root family separately, rather than folding it into `inheritedStyle`, which 22 controls in the panel read for their own inheritance display.

Deliberately not included, because they are the broader change #83459 mentions: making the Blocks screen resolve inheritance generally, and widening the inheritance indicators to match.

## Testing Instructions

1. In the active theme's `theme.json`, register a family whose face declares `"fontWeight": "100 700"` and set it as the root font, as in #83459.
2. Open **Styles → Blocks → Paragraph** with its own font left at `Default`, and open **Appearance**.
3. On trunk the list runs `Thin` to `Extra Black`. With this change it stops at `Bold`, and the Font control still reads `Default`.
4. Set a font on the block itself. Appearance follows that family, as before.

### Testing Instructions for Keyboard

No change to the controls themselves; only the options listed for a block that inherits its font change.

## Not included

No test yet. Appearance is not shown by default and the existing coverage for it is browser-based; the harness needed to drive the panel's options menu did not work locally, and the test would have to be rewritten anyway if the answer to the question above is A or C. I would rather add it once the model is settled than write one for an approach that may not survive review.

One known limitation: the root family is read without the screen's `prefix`, so a block style variation screen reads the root rather than the variation's typography. Worth fixing if this direction is taken.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with implementation and with the measurements in #83459. I reviewed the changes and the results.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
