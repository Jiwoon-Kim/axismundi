# Draft — Gutenberg prototype PR description: capability lookup from the effective family

> 상태: **게시됨** — [WordPress/gutenberg#83462](https://github.com/WordPress/gutenberg/pull/83462).
> 2026-09-27 본문 갱신 + draft 해제(Open). Open은 리뷰 대화를 받겠다는 신호이지 머지 요청이 아니며,
> 제목의 `Prototype`과 "asking a question rather than to merge" 문장을 그대로 유지해 오해를 막는다(#78176 선례).
>
> **2026-09-27 추가된 것:** 같은 경로의 실제 버그 수정. `attributesToStyle()`이 `style.typography.fontFamily`
> (인라인)를 버리고 속성만 읽어, 그런 블록이 패널에 family 없이 도착했다. 형제 셋(fontSize·textShadow·
> color.text)은 이미 style로 폴백하는데 family만 `undefined`였다. 실측: Material Symbols(`100 700`) 블록의
> Weight가 `Extra Black (1000)` → 수정 후 `Bold (700)`. 커밋 분리(`bd3199db6c` 수정+테스트 6개 /
> `e0acff6b8e` CHANGELOG), trunk CHANGELOG 충돌은 웹에서 해소, CI 76 success / 0 failure.
>
> **표현 주의(사용자 지적):** "resolver 계약이 넓어졌다"처럼 크게 쓰지 말고 사실만 —
> "the panel style now preserves a family written inline, matching the existing attribute path".
> 참조 이슈: [#83459](https://github.com/WordPress/gutenberg/issues/83459) (게시·대조 완료, `1c81d66d…`).
>
> **이건 머지를 노리는 PR이 아니다.** 설계 질문(A/B/C 중 B가 맞는가)을 코드로 보여주는 것이 목적이고,
> 유지보수자가 "Blocks 화면은 block-local 값만 다룬다"고 하면 닫는다.
>
> **테스트 상태(2026-09-27 갱신).** 인라인 family 수정에는 단위 테스트 6개가 붙었다
> (`typography-attributes-to-style.jsdom.test.js`, 세 경로를 같은 기대값으로 묶음). Appearance가
> 무엇을 제시하는지에 대한 browser 회귀 테스트는 **여전히 없다** — 그 컨트롤은 패널 옵션 메뉴 뒤에 있고
> 기존 커버리지가 전부 browser 테스트인데, 방향(A/B/C)이 정해지면 다시 써야 한다. 본문에 그대로 적는다.
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

It also carries a fix found while testing that: the panel style now preserves a family written inline in `style.typography.fontFamily`, matching the existing attribute path. A block that records its family that way reached the panel with none at all, so the faces looked up were the inherited font's rather than its own.

The three places a family can come from, all of which the faces are now read through:

```text
fontFamily attribute            the family a picker sets
style.typography.fontFamily     a family written into the style
inherited or root family        what capabilityFontFamily supplies
```

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

The inline family is a separate commit. `attributesToStyle()` read the `fontFamily` attribute and fell to `undefined` without one, while the three values beside it — font size, text shadow and text colour — each fall back to the style. Measured in the editor: a paragraph whose own family is an icon font declaring `fontWeight: "100 700"` was offered weights to 1000, the range of the site's font; with the family preserved the same control stops at 700.

Deliberately not included, because they are the broader change #83459 mentions: making the Blocks screen resolve inheritance generally, and widening the inheritance indicators to match.

## Testing Instructions

1. In the active theme's `theme.json`, register a family whose face declares `"fontWeight": "100 700"` and set it as the root font, as in #83459.
2. Open **Styles → Blocks → Paragraph** with its own font left at `Default`, and open **Appearance**.
3. On trunk the list runs `Thin` to `Extra Black`. With this change it stops at `Bold`, and the Font control still reads `Default`.
4. Set a font on the block itself. Appearance follows that family, as before.

### Testing Instructions for Keyboard

No change to the controls themselves; only the options listed for a block that inherits its font change.

## Not included

No browser test for what Appearance offers. The inline family fix has unit coverage, binding the three paths above to one expectation, but the options a block is offered are behind the panel's menu and the existing coverage for that control is browser-based. That test would have to be rewritten if the answer to the question above is A or C, so I would rather add it once the model is settled.

One known limitation: the root family is read without the screen's `prefix`, so a block style variation screen reads the root rather than the variation's typography. Worth fixing if this direction is taken.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5) assisted with implementation and with the measurements in #83459. I reviewed the changes and the results.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
