# Draft — Gutenberg #83159 body, rewritten for the boolean model

> 상태: **게시됨** 2026-09-26 — [WordPress/gutenberg#83159](https://github.com/WordPress/gutenberg/pull/83159) 본문 교체 완료. `--github` 되읽기 일치(SHA-256 `eaf7a700…`). draft 유지(ready 전환은 이슈에서 open questions가 정리된 뒤).
>
> **왜 다시 쓰나:** 게시본은 정책이 `slug → tag → {name,min,max}` 중첩 객체이던 시절의 설명이다.
> 커밋 `fcfc51dc54`(정책→boolean)와 `0e6006c2b2`(`opsz` 제외 + 컨트롤 기본 숨김) 이후로 본문의
> Policy 항목, 패널 설명, Testing Instructions, Open questions가 모두 사실과 다르다.
>
> **제목은 이미 바뀌어 있다**(확인 2026-09-26): `Typography: Prototype fontVariationSettings with a theme-enabled panel`. 테마가 고르는 건 축이 아니라 패널이다.
>
> **Playground 데모 링크는 복구한다(2026-09-26).** `fcfbce25`의 fixture가 옛 정책 객체를 주입하던
> 문제를 해결했다: axismundi `demos/`를 boolean으로 갱신(`bd0cbf37`), blueprint를 그 SHA에 핀
> (`dfe4fe7c`), README에 포함/미포함 표와 스크린샷 추가(`deae5b66`). Playground를 실제로 부팅해
> `/wp-admin/?font-variations-demo` 랜딩과 새 fixture 본문(아이콘 문단 없음)을 확인했다.
> **PR 본문에는 스크린샷을 한 장만 넣는다(사용자 결정)** — `panel-custom-axes.png`. Material Symbols
> `FILL` 이미지는 fixture에 없는 폰트라 본문에 크게 넣으면 "이 PR이 아이콘 폰트를 포함하나"로
>읽힌다. 전체 그림은 README에서만 보인다.
>
> **스크린샷 캡처(2026-09-26)**: `computer screenshot`은 파일로 저장되지 않아 리포지토리 Playwright
> 1.63.0 + 설치된 Chrome(`channel: 'chrome'`, `deviceScaleFactor: 2`)으로 찍었다. 축 메뉴는
> 사이드바 밖 팝오버라 두 사각형의 합집합을 `clip`으로 잡아야 한다. 스크립트 출력이 곧 측정:
> `axes offered: Grade, XOPQ, YOPQ, XTRA, YTUC, YTLC, YTAS, YTDE, YTFI` /
> `heading panels: Styles, Typography, Background, Dimensions, Borders, Elements`(Font variations 없음) /
> `fill 0 value: null` → `fill 1 value: {"FILL":1}`.
>
> **실측(2026-09-25~26, 로컬 Gutenberg wp-env 8889, 이 브랜치 빌드):**
> - `root=true / core/quote=false / core/paragraph=true` — boolean이 루트와 블록 양쪽에 실림
> - Roboto Flex 13축 중 등록축 5개 제외 → 메뉴에 커스텀축 9개, 기본은 전부 숨김
>   (`All options are currently hidden`)
> - Material Symbols → `FILL`·`GRADE` (등록축 `wght`·`opsz` 제외)
> - 축을 켜면 슬라이더 표시, 기본값은 face가 선언한 `fvar` default (`GRAD` 0)
> - 값이 저장된 축은 켜지 않아도 자동 노출
> - 값 저장 형태 그대로: `styles.typography.fontVariationSettings = { FILL: 1 }`
> - 패널을 끈 블록(Heading)은 저장값이 있어도 패널 자체가 없음
> - 블록의 폰트를 `fontFamily` 속성(프리셋 클래스)으로 주든 `style.typography.fontFamily`로 주든
>   같은 축이 나온다 — 훅이 둘을 `var:preset|font-family|<slug>`로 정규화
>
> **문구 주의(사용자 지적, 2026-09-26):** `opsz`는 **이 패널에서** 빠지는 것이지
> `fontVariationSettings` 값 객체에서 금지되는 게 아니다. 확인: 값 층이 거르는 태그는
> `wght`·`wdth`·`slnt`·`ital` 넷뿐이다(`class-wp-theme-json-gutenberg.php:5157`,
> `class-wp-style-engine.php:736`, `styles/typography/index.ts:43`). 나중에 Typography의
> manual optical size가 같은 저장 경로를 쓸 수 있으므로 본문도 그렇게 적는다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83159-BODY.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

This is a design experiment for #83148, opened as a draft so the model can be tried and discussed next to working code. It is not ready for review; I'll mark it ready once the open questions below are settled in the issue.

## What?

A working version of the layers proposed in #83148:

- **Capability:** a face `axes` list, for the axes a font file has, with their ranges and defaults.
- **Value:** `styles.typography.fontVariationSettings`, an object keyed by axis tag (`{ "GRAD": 50 }`), serialized to `font-variation-settings` by the style engine in PHP and JS. `wght`, `wdth`, `slnt` and `ital` are dropped here, since they belong to `font-weight`, `font-stretch` and `font-style`. `opsz` is not: it has no property that takes a coordinate, so a manual optical size would be written through this value, whatever offers it.
- **Availability:** `settings.typography.fontVariations`, a boolean, and through `settings.blocks` a block can turn it off. It says whether this editing is offered, not which axes exist.
- A `typography.fontVariationSettings` block support, enabled on Paragraph and Heading.
- A **Font variations** panel below Typography, in the block inspector and in Global Styles, showing the axes the faces in use declare. Its controls start hidden, as padding and margin do in Dimensions.

## Why?

Variable fonts can have axes beyond weight, width and slant, such as grade (`GRAD`) or a filled state (`FILL`), but WordPress has no style property for them and no way to offer them. #83148 explains the model; this PR shows it working so the details can be judged against real behaviour.

The earlier revision of this PR let a theme list the axes it offered, per family and per tag, each with an optional narrower range. Working with it changed my mind. That list restates what the font already declares, so replacing the file leaves the two disagreeing, and it does not constrain anything: a narrower slider does not stop a value reaching `styles` or hand-written CSS. What a site genuinely decides is whether this editing appears at all, which is the shape other opt-in features already use. It also settles how origins merge the setting, which a keyed list only worked around.

## How?

- Style engine: a `fontVariationSettings` definition in PHP and JS. Only four-character alphanumeric tags with finite number values are output, the same in both engines. theme.json keeps the object when styles are sanitized for users without `unfiltered_html`, filtered by the same rules, and `font-variation-settings` is added to the safe style properties.
- Axes offered: the faces that can render the current font style and weight are picked (the style matches, and the weight or weight range includes it; all faces when none matches). An axis is offered when all of those faces declare it, over the range they all support, and it is not one OpenType registers.
- Availability only controls what the UI offers. It does not sanitize raw theme.json, stored block attributes, or inherited values. A block whose panel is turned off keeps any axis values it already has, and stops offering a way to change them. Existing values are otherwise preserved unless the user explicitly changes the font family on the same style node: choosing another font in the Typography panel clears that node's axis values. A value the current family can't use is kept, and not shown.
- The Typography panel's Reset all leaves axis values to the Font variations panel.
- The block inspector has a new `fontVariations` slot group for the panel. Block style states (hover, viewports) don't show it yet.
- Global Styles reads `typography.fontVariations` from settings: it is added to the list of settings `getSetting()` assembles.

Open questions, for #83148:

- How the panel should read several faces, for example a normal and an italic face with different `GRAD` ranges. This PR intersects the faces in use.
- The names `fontVariations` (availability) and `axes` (capability), and whether uploads and collections should provide `axes` read from the file's `fvar` table.
- Whether a panel-level slot group is the right way to place the panel.
- What offers the registered axes this panel leaves alone. `opsz` in particular: `font-optical-sizing` is `auto`, so a slider here pinned what the browser was choosing from the font size, and the panel is the wrong place to ask for it. The value still carries `opsz`, so a manual optical size in typography would write the same style.

## Demo

[Open this branch in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Jiwoon-Kim/axismundi/dfe4fe7c30e659c2b3900270fc787b3a7ccb1e85/demos/gutenberg-font-variations/blueprint.json&gutenberg-pr=83159), with a fixture that registers Roboto Flex as the two faces one binary can serve, each carrying its whole `fvar` table as `axes`, turns the panel on for the site and off for Heading, and adds a post to try it on.

The fixture covers this pull request and nothing else, and [its README says so in a table](https://github.com/Jiwoon-Kim/axismundi/blob/deae5b669808364ffbdc388d07fccdcf360675a5/demos/gutenberg-font-variations/README.md#what-this-demo-is-and-is-not): it runs on trunk, so the Appearance control there is trunk's, and the two-angle `oblique` range that control lists is #83456's subject rather than this one's. The fixture also carries no icon font, since Playground would download it to show what a screenshot shows as well.

What the panel offers for that font, and what it leaves to typography:

![The block inspector with a Font variations panel whose menu lists Grade, XOPQ, YOPQ, XTRA, YTUC, YTLC, YTAS, YTDE and YTFI, and none of the registered axes](https://raw.githubusercontent.com/Jiwoon-Kim/axismundi/deae5b669808364ffbdc388d07fccdcf360675a5/demos/gutenberg-font-variations/screenshots/panel-custom-axes.png)

## Testing Instructions

1. Add a variable font family to the active theme with a face `axes` list, for example Roboto Flex:
   ```json
   "fontFace": [ { "fontFamily": "Roboto Flex", "fontWeight": "100 1000", "src": [ "…" ],
     "axes": [ { "tag": "opsz", "min": 8, "default": 14, "max": 144 }, { "tag": "wght", "min": 100, "default": 400, "max": 1000 }, { "tag": "GRAD", "min": -200, "default": 0, "max": 150 }, { "tag": "XTRA", "min": 323, "default": 468, "max": 603 } ] } ]
   ```
   with `"settings": { "typography": { "fontVariations": true } }`, and `"fontVariations": false` under `settings.blocks["core/heading"]`. Set Roboto Flex as the site font.
2. In the editor, select a paragraph: Styles shows a Font variations panel with no controls yet, and its options menu lists Grade and XTRA. `opsz` and `wght` are not there, being registered axes.
3. Choose Grade from that menu: a slider appears at the font's default, 0. Set it to 150: the paragraph gets `font-variation-settings: "GRAD" 150`, in the editor and on the front end, and bold text inside it is still bolder.
4. Select the heading: there is no Font variations panel, since the block turns it off.
5. Select a list: there is no panel either, as the block has no support.
6. On the paragraph, choose another font in Typography: the Grade value is removed and the panel follows the new font's axes.
7. In the Site Editor, Styles > Typography > Text shows the same panel, and a value there applies to the site. Styles > Blocks > Heading does not.

### Testing Instructions for Keyboard

1. Tab to the Font variations panel's options menu and add an axis with Enter.
2. Tab into the axis: a slider moved with the arrow keys, and a number field.
3. Reset all clears the axes and hides them again.

## Use of AI Tools

AI tools (Claude Code, Claude Opus 5, and Codex) assisted with implementation and testing.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
<!-- end of body -->
