# Draft — Core Trac 티켓 + wordpress-develop PR (typography style properties)

> 상태: **①② 완료(2026-09-28).** 티켓 [#66198](https://core.trac.wordpress.org/ticket/66198)은 사용자가 생성,
> draft PR [wordpress-develop#13789](https://github.com/WordPress/wordpress-develop/pull/13789)은 8파일로
> 열림(`7e4e549f9c`). 본문 게시 후 `--github` 재검증 통과. 상호 링크 완료 —
> Core PR 본문이 #83159·#83148을, #83159 본문이 #66198·#13789를 가리킨다.
> Gutenberg 쪽 `backport-changelog/7.2/13789.md`(`9e5e67f54b`) 푸시 후
> `Check for a Core backport changelog entry`가 **pass**로 바뀌었다.
>
> 티켓은 소유자가 제목을 다듬었다: `Typography: Accept a font width and font variation settings as styles`
> → `Typography: Add font-stretch and font-variation-settings support to Theme JSON styles`. **PR 제목은
> 티켓 제목을 따른다.** 티켓 필드: Component `Editor`, Type `enhancement`, Focuses `css, rest-api`.
>
> **알려진 흠(사용자 판단 대기):** 티켓 본문의 `[83148](https://…)`가 Markdown 문법이라 Trac에서
> 링크로 렌더되지 않고 문자 그대로 보인다. Trac WikiFormatting은 `[url label]`(공백 구분)이다.
> 고치려면 소유자가 Trac에서 본문을 편집해야 한다.
>
> **왜 티켓이 먼저인가:** Core의 GitHub PR은 코드리뷰용 미러이고 작업 단위·최종 추적은 Trac이 맡는다.
> [Core handbook](https://make.wordpress.org/core/handbook/tutorials/trac/submitting-a-patch/)이 PR 제목에 Trac
> 번호를 넣으라고 안내한다. Gutenberg의 `backport-changelog` 체크도 Core PR **URL**을 요구하므로 순서가 고정된다.
>
> ```txt
> Gutenberg #83148   설계 허브 — capability 모델, 블록 대 폰트의 책임
> Gutenberg #83159   에디터 prototype
> Core Trac 티켓      두 typography style property의 Core-side contract
> wordpress-develop  그 티켓의 구현
> ```
>
> **경계:** 티켓은 #83148 전체를 Core로 옮기는 제안이 **아니다.** UI·capability 모델은 Gutenberg에 남고,
> Core는 값을 받아 직렬화하는 계약만 맡는다. 어느 블록이 opt-in하는지(`block.json` supports)도 넣지 않는다 —
> 그건 Gutenberg 쪽 결정이 먼저다.
>
> **준비된 브랜치:** `wordpress-develop` `add/typography-font-stretch-and-variation-settings`
> (`upstream/trunk` `95ea25e816` 기준), 커밋 `cd54c9142e`, 8파일 +563 −61. 미푸시.
> `@ticket TRAC_TICKET` 플레이스홀더 6곳 — 번호가 나오면 일괄 치환한다.
>
> **검증(2026-09-28):** phpcs 8파일 전부 통과. `npm run test:php --filter
> 'Typography|Theme_JSON|Style_Engine|StyleEngine|ThemeJson|font_stretch'` → **516 tests,
> 933 assertions, OK**. Gutenberg 쪽 같은 스위트는 456 tests, 816 assertions, OK.
>
> **실측으로 교정한 추측 1건:** Core의 `get_settings()`도 `settings.typography.fontFamilies`를
> origin으로 키를 쓴다 → `['theme'][0]`. Core는 평평할 거라 보고 `[0]`으로 썼다가
> `Undefined array key 0`으로 깨졌다. Gutenberg와 동일하다.
>
> **로컬 환경 주의:** wordpress-develop `.env`의 `LOCAL_PORT=8889`가 Gutenberg wp-env과 충돌한다.
> `LOCAL_PORT=8899 npm run env:start && ... env:install && ... test:php`로 오버라이드할 것
> (추적 파일이라 `.env`를 고치면 커밋에 섞인다).
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-CORE-TYPOGRAPHY-STYLE-PROPERTIES.md `
>   --source-after "<해당 Body 제목 줄 전체>" `
>   --source-before "<해당 본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Trac title

Typography: Accept a font width and font variation settings as styles

## Trac component

Editor

## Trac body (paste as is)

Related: Gutenberg [83148](https://github.com/WordPress/gutenberg/issues/83148) is the design discussion, and [83159](https://github.com/WordPress/gutenberg/pull/83159) is the editor prototype. This ticket is not a proposal to move that discussion here: it covers only the Core-side contract two typography style properties need, so that the editor work has somewhere to land. Which controls appear, and what a font's faces say they can do, stay in Gutenberg.

== What this adds ==

Two style properties, each stored under `styles.typography` and written by the style engine:

|| Stored as || Written as ||
|| `fontStretch` || `font-stretch` ||
|| `fontVariationSettings` || `font-variation-settings` ||

With them:

* `settings.typography.fontStretch`, alongside the existing `fontStyle` and `fontWeight`, and on by default in `wp-includes/theme.json`.
* `settings.typography.fontVariations`, a boolean saying whether a site offers editing of a font's custom axes at all.
* `axes` on a font face, so a face can say which OpenType axes it has and over what range.
* `font-variation-settings` in the list `safecss_filter_attr()` allows. `font-stretch` is already there.
* `fontStretch` and `fontVariationSettings` as typography block supports.
* Tests for the block support, the style engine and `WP_Theme_JSON`.

== Why a width needs a property of its own ==

`font-stretch` is the only axis of the four that map to a CSS property which a browser will not stand in for: there is no `font-synthesis-width` to go with `font-synthesis-weight` and `font-synthesis-style`. A variable font that declares a width range has nothing in Core today that can carry a chosen width, so the value has to be written by hand.

The name is the older one deliberately. CSS Fonts 4 renames the property `font-width` and keeps `font-stretch` as its legacy alias; no browser implements `font-width` yet, and the alias drives the `wdth` axis identically.

== Why the variation settings value is an object ==

`font-variation-settings` replaces the whole list rather than merging, so a string value cannot be inherited through the theme.json origins the way other styles are: setting one axis on a block would mean repeating every axis the root set. Keyed by tag, origins merge per axis, and the style engine assembles the string once at the boundary.

The four axes that have a property of their own — `wght`, `wdth`, `slnt`, `ital` — are refused there. The property is applied after the properties that map to the same axis, so a coordinate written here takes the axis away from the control that owns it: `font-weight: 700` with `"wght" 400` renders at 400, and a `<strong>` inside an element carrying `"wght" 300` does not get bolder. `opsz` is allowed, because `font-optical-sizing` can only switch the browser's own tracking on or off and has no way to carry a chosen size.

A tag must be four letters or digits, which is what OpenType allows and also keeps quotation marks out of the serialized value, and a value must be a number rather than a numeric string. Those rules live in one place, `WP_Style_Engine`, and `WP_Theme_JSON` reads them through it when it sanitizes rather than repeating them.

== What is not here ==

* The editor controls, and the reading of a family's faces that decides what they offer. That is Gutenberg's, and #83148 has the model.
* Which blocks opt in. The supports exist; no block in this patch turns them on.
* Reading `axes` from a font file's `fvar` table on upload. The contract is here so that a theme can declare it; the Font Library reading it is separate work.

<!-- end of trac body -->

## PR title

Typography: Add font-stretch and font-variation-settings support to Theme JSON styles.

## PR body (paste as is)

## Why

Two typography style properties have no way to reach CSS today, and a variable font needs both. `font-stretch` is the one axis with a CSS property that a browser will not synthesize — there is no `font-synthesis-width` — so a width a font declares cannot be used at all without it. `font-variation-settings` is what the axes with no property of their own have, and there is no style property for it.

This is the Core half of editor work happening in Gutenberg: [WordPress/gutenberg#83159](https://github.com/WordPress/gutenberg/pull/83159) is the editor side, and [WordPress/gutenberg#83148](https://github.com/WordPress/gutenberg/issues/83148) is the design discussion behind it. This carries the contract only: what may be stored, what is written, and what is refused.

## What

| Stored under `styles.typography` | Written as |
| --- | --- |
| `fontStretch` | `font-stretch` |
| `fontVariationSettings`, an object keyed by axis tag | `font-variation-settings` |

Also: `settings.typography.fontStretch` beside the existing `fontStyle` and `fontWeight`, on by default; `settings.typography.fontVariations`, a boolean for whether a site offers custom-axis editing; `axes` on a font face, for the axes a file has and their ranges; `font-variation-settings` added to what `safecss_filter_attr()` allows, `font-stretch` being there already; and the two block supports.

## How

`WP_Style_Engine` holds the rule for what may be written to `font-variation-settings`: a tag of four letters or digits, a numeric value, and not one of the four axes a CSS property owns (`wght`, `wdth`, `slnt`, `ital`), because the property is applied after those and would take the axis from them. `opsz` is allowed, since `font-optical-sizing` only switches the browser's tracking rather than taking a coordinate.

`WP_Theme_JSON` reads that rule through the style engine in both places it needs it — serializing a stylesheet, and sanitizing styles for a user without `unfiltered_html` — rather than holding a second copy. A second copy is what this whole line of work started from: two helpers reading the same font faces by different rules offered a static family nine widths it had no files for.

The block support's two lists of variables are regenerated rather than patched, because the longest name sets the column the coding standard aligns the assignments to. No line in them changes meaning.

## Testing Instructions

1. `npm run test:php -- --filter 'Typography|Theme_JSON|Style_Engine|StyleEngine|ThemeJson|font_stretch'`. 516 tests pass here.
2. In a theme's `theme.json`, set `styles.typography.fontStretch` to `"75%"` and `styles.typography.fontVariationSettings` to `{ "GRAD": 50, "wght": 700 }`. The stylesheet carries `font-stretch: 75%` and `font-variation-settings: "GRAD" 50`; the weight is left to `font-weight`, so a `<strong>` inside still renders bolder.
3. As a user without `unfiltered_html`, save Global Styles with `fontVariationSettings` set to `{ "GRAD": 20, "wght": 700, "XTRA": "500; color: red" }`. Only `GRAD` is stored.

## Not included

The editor controls and the reading of a family's faces that decides what they offer, which stay in Gutenberg; which blocks opt in, since the supports here are off everywhere; and reading `axes` from a font file's `fvar` table when it is uploaded.

Trac ticket: https://core.trac.wordpress.org/ticket/66198

## Use of AI Tools

AI assistance: Yes
Tool(s): Claude Code
Model(s): Claude Opus 5
Used for: Porting the implementation and tests from the Gutenberg prototype, and running the test suites. I reviewed the changes and the results, and one wrong assumption the port made was found by the tests rather than by reading: Core keys the font families setting by origin, as the plugin does.

---
**This Pull Request is for code review only. Please keep all other discussion in the Trac ticket. Do not merge this Pull Request. See [GitHub Pull Requests for Code Review](https://make.wordpress.org/core/handbook/contribute/git/github-pull-requests-for-code-review/) in the Core Handbook for more details.**

<!-- end of pr body -->
