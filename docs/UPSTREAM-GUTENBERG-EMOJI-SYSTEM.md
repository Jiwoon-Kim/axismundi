# Draft — Gutenberg Discussion (Ideas): emoji picker, support detection and fallback as one system

> 상태: **초안, 게시 안 함** (2026-09-16).
>
> 올릴 곳: WordPress/gutenberg › [Discussions › Ideas](https://github.com/WordPress/gutenberg/discussions/new?category=ideas) (사용자와 결정 2026-09-16).
> 이유: 글의 목적이 계층 경계에 대한 열린 질문 3개라 바로 처리할 작업 단위가 없다(이슈로 열면 "무슨 변경이냐"로 분류가 막힘).
> Show and tell은 #82501처럼 "만든 것을 보여주고 제안은 하지 않는" 글에 맞는데, 이 글은 제안을 담는다. 선례: #82253, #82830(Ideas).
> 구체 작업이 합의되면 그때 이슈(Feature request, 웹에서 템플릿 선택 → `[Type] Enhancement`)나 Trac 티켓(C2, C5)으로 나눈다.
> 제목 칸에 아래 Title, 본문 칸에 Body를 붙인다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-EMOJI-SYSTEM.md `
>   --source-after "<아래 Body 제목 줄 전체, 한 번만 나와야 함>" `
>   --candidate <붙여 넣을 파일>
> ```
>
> 게시 후: Discussion 본문은 `--github`가 읽지 못할 수 있으므로 화면에서 복사해 `--posted`로 대조. 이 파일 상태를 게시됨으로 바꾸고 Discussion 번호 기록.
>
> ## 근거 (확인일 2026-09-16)
>
> - 번호·상태: `gh api`(Gutenberg #1678 closed, #75144 open, PR #76767 open, PR #78176 open·trunk 미반영, picker 위치 `packages/editor/src/components/collab-sidebar/`), Core Trac 쿼리(#49885 closed wontfix, #44001 Awaiting Review has-patch, #64638 7.2, #66104 7.2 reviewing `has-patch commit`, #63451 Awaiting Review `close`, #52219 6.2 fixed [55186], #58663 Future Release), wordpress-develop PR #12252 open.
> - 첫 문장: #75144 본문 89행 "Codebase exploration found no existing emoji picker component in Gutenberg."을 인용. "Gutenberg"가 아니라 "WordPress has no shared emoji system"으로 쓴 이유 = 감지·대체는 Core 코드.
> - #78176 내용: PR 본문(Emojibase 28 locale, same-origin lazy fetch, `Composite`, `speak()`, 피부색 listbox, 자주 쓴 이모지, 소문자 hex 저장 키·`FE0F` 제거, Frimousse 철회 이유).
> - Core 동작: [UPSTREAM-TRAC-CANDIDATES.md](UPSTREAM-TRAC-CANDIDATES.md) C1–C5(C2·C3·C5 재현, C1·C4 소스 판독), 6.8.1 Emoji 15.1 / 6.8.2 Emoji 16.0 검사(wordpress-develop 태그).
> - #63451의 "저절로 해결" 원인은 **추정**(당시 Windows의 Emoji 16 지원을 측정하지 않음) — 본문에서도 "likely"로 표기.
> - 크기: [AXISMUNDI-EMOJI-UNICODE.md](AXISMUNDI-EMOJI-UNICODE.md) §13(폰트 직접 측정, Twemoji 17.0.2 개수·크기는 Codex 측정).
> - 데모: 커밋 `6ebc3a0` 고정 `release.json`(패널 수정 반영; 처음 확인은 `95c1dae`), Playground 재실행 확인(모드 auto, Core 검사 `flag: no, emoji: no`, 깃발 3개 Noto, 8개 이미지, 사용자 이모지 2개).
> - #44001 측정(2026-09-16, 같은 Playground, 모드 font + 이미지 대체 끔): `s.w.org` 요청 0, HTML 속 `s.w.org` 0, 외부 호스트 요청 0, `wp-emoji-settings` 없음, `img.emoji` 0, 유니코드 11줄 모두 `emoji` profile, 폰트 요청은 full 1건. 데모 패널이 이전 sessionStorage 값을 보여주던 문제는 수정(스크립트 없으면 "not run on this page").
> - FEP-9098: readme·`includes/outbound.php`(선언). 반응: Activities 0.1.0 `includes/reactions.php` `axismundi_act_normalize_reaction()`(FE0E/FE0F만 제거, `unicode:U+…` 공백 구분, `custom:<authority>:<key>`는 선언 필수, 자기 사이트 authority만 예외), `repository.php`(Like+content = EmojiReact, 복수 반응 허용). Notes 저장: #76767 본문(`reaction` comment_type, curated slug), #78176(hex 키). Core #64638 요약은 "Register emoji reactions comment meta for Notes" — 이후 comment_type으로 바뀐 것과 어긋남.
> - 우리 picker에 없는 것: 방향키 grid 탐색, 피부색 선택(변형은 목록에서 제외), 검색 결과 `speak()`, 번역된 이모지 이름(영어 CLDR 이름), 사용 빈도 순(최근 순).
>
> ## 쓰지 않은 것
>
> - "상위호환"류 비교. picker는 #78176이 앞선 부분이 있고, 이 글의 요점은 계층 경계다.
> - Safari 등 특정 브라우저의 COLRv1 지원 단정 — 측정하지 않음. 폰트 probe 실패 시 물러난다는 동작만 적음.

## Title

Emoji: treat the picker, support detection, and fallback rendering as one system

## Body (GitHub Markdown — paste as is)

## The problem

While planning reactions for Notes, #75144 found no existing emoji picker component in Gutenberg. The gap is wider than the picker: WordPress has no shared emoji system. It handles emoji in three places that were built at different times and do not share a model:

1. **Input.** There is no reusable emoji picker in the editor today. A character map was requested in #1678 (2017), and an emoji button in [Core Trac #49885](https://core.trac.wordpress.org/ticket/49885) (2020, closed as `wontfix` because operating systems ship their own). Notes reactions are adding one now: #75144, #76767, and the full searchable picker in #78176, which lives in `packages/editor/src/components/collab-sidebar/`.
2. **Support detection.** `wp-emoji-loader.js` runs two tests on a canvas: `flag` (the transgender flag, the Sark flag, and the England flag; any failure fails it) and `emoji` (the centre pixel of U+1FAC8). The result becomes `everything` or `everythingExceptFlag`. It runs on the front end only; the block editor does not load it.
3. **Fallback.** When a test fails, `wp-emoji.js` replaces emoji text with images from `s.w.org`. [Core Trac #44001](https://core.trac.wordpress.org/ticket/44001) and [wordpress-develop#12252](https://github.com/WordPress/wordpress-develop/pull/12252) discuss serving those images locally.

Because the three parts do not know about each other, a small mistake in one changes what every site shows, and the picker has to choose its data and storage format without a shared answer to "which sequences does WordPress treat as emoji, and which can this browser draw?". Things I reproduced or read in the source while building a plugin across all three:

- [Core Trac #66104](https://core.trac.wordpress.org/ticket/66104): the Emoji 17 test string is malformed, so `emoji` is always false and every emoji is replaced by an image, including in browsers that draw Emoji 17 (now in 7.2 with `commit`).
- When `flag` fails and `emoji` passes, only regional-indicator pairs and the rainbow and pirate flags are replaced. Two of the flags the `flag` test draws are not on that list: England (with Scotland and Wales, a tag sequence) stays as a plain black flag, and the transgender flag, a ZWJ sequence, is left as well. That is [Core Trac #63451](https://core.trac.wordpress.org/ticket/63451). It likely stopped reproducing because the `emoji` test began to fail (Emoji 16 in 6.8.2, then [Core Trac #66104](https://core.trac.wordpress.org/ticket/66104)), which makes WordPress replace everything. With the [Core Trac #66104](https://core.trac.wordpress.org/ticket/66104) patch applied, a local test page in Chromium on Windows left the England flag unreplaced again.
- `.wp-exclude-emoji` is skipped only when it is below the element being parsed. If the excluded element is itself inserted later, or its own text changes, it becomes the parse root and its emoji are replaced. A follow-up to [Core Trac #52219](https://core.trac.wordpress.org/ticket/52219).
- The `emoji` test checks a single code point. A system that draws U+1FAC8 but not the new Emoji 17 ZWJ sequences counts as supporting Emoji 17.
- Smaller findings from the same work: the detection cache expires after about ten minutes rather than the week [Core Trac #58663](https://core.trac.wordpress.org/ticket/58663) assumes, and `WP_Font_Face` drops a font `src` that carries a query string such as `?ver=`.

## What I'd like to discuss

Not a plugin to merge. I'd like to discuss where the boundaries should be, using a working implementation as a reference:

| Layer | Question |
|---|---|
| Data | One emoji list (RGI sequences, names, groups) that both the picker and rendering can use. |
| Picker | Whether the picker from #78176 becomes a shared component for rich text, comments, and reactions, not only Notes. |
| Detection | Results per kind of sequence (country flags, subdivision flags, single code points, ZWJ and skin-tone sequences) instead of two booleans, available to scripts that need them. |
| Fallback | An ordered chain: the browser's own font, then a color emoji font, then images. A font keeps the emoji as text, so copying, search, and screen readers see the characters. |

### Working reference: Axismundi Emoji 0.3.0

[Axismundi Emoji](https://github.com/Jiwoon-Kim/axismundi/releases/tag/emoji-v0.3.0) is under review for the Plugin Directory, so here is a Playground that installs the release ZIP and opens a demo page:

**[Open the demo in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Jiwoon-Kim/axismundi/6ebc3a05ef2d400c142b1e2f0c8c1ab4f46b002a/products/wordpress/plugins/axismundi-emoji/wporg-assets/blueprints/release.json)**

The page lists 11 Unicode sequences and the two bundled custom emoji and, after load, says what drew each row: the browser's font, the bundled font, a WordPress.org image, or the site's own image. A panel shows the plugin's mode and WordPress's own test results. In Chromium on Windows with the latest WordPress, WordPress reported `flag: no, emoji: no` ([Core Trac #66104](https://core.trac.wordpress.org/ticket/66104)); the plugin drew the three flags with its font and the other eight became images.

What the plugin does:

- **Detection per profile**: Core's `flag` probe is split into a country-flag profile and a subdivision-flag profile, each probed on its own canvas. A third profile, all RGI emoji, applies only when a site chooses the complete font. Results are cached with a version tied to the font files.
- **Two self-hosted font files**, both Noto Color Emoji (COLRv1, SIL OFL): a flags subset of 262 country and subdivision sequences for the two flag profiles, and the complete font for the third. Wrapped text carries WordPress's own `.wp-exclude-emoji`, so WordPress does not replace it as well.
- **Automatic mode** wraps only the flag profiles this browser cannot draw and uses the flags subset; everything else is left to the browser and to WordPress.
- **Complete-font mode** wraps every RGI emoji and draws it with the complete font, whatever the browser can draw.
- **When the font cannot be drawn** (its own probe fails), the wrapper is removed. The text then goes to WordPress's image fallback if that is enabled, and otherwise stays as text.
- **Settings**: the two modes above or WordPress's fallback only, and whether WordPress's detection script and image fallback load at all.
- **Font Library**: the font is offered as a collection.
- **Custom emoji**: the site's own images, written as `:shortcode:` and rendered in posts and comments, and declared as [FEP-9098](https://codeberg.org/fediverse/fep/src/branch/main/fep/9098/fep-9098.md) `Emoji` tags when a federation plugin is present.
- **Editor picker**: Unicode 17.0 RGI emoji and the site's custom emoji in one searchable picker with collapsible groups that load on demand. The editor has no detection script, so the picker runs the probes without touching the editor's DOM.

Sizes, for the local-assets discussion in [Core Trac #44001](https://core.trac.wordpress.org/ticket/44001): the complete font covers all 3,944 RGI emoji in one 1.88 MiB WOFF2 file; the flags subset covers 262 flags in 699 KiB. Twemoji 17.0.2 is 4,009 PNG plus 4,009 SVG files, about 13.7 MiB together. With the complete font chosen and WordPress's image fallback switched off, the demo page made no requests to `s.w.org` and its HTML did not mention it: one font file drew all 11 Unicode rows.

**Reactions.** The companion [Axismundi Activities](https://github.com/Jiwoon-Kim/axismundi/releases/tag/activities-v0.1.0) plugin (not part of the demo) has emoji reactions that federate as [FEP-c0e0](https://codeberg.org/fediverse/fep/src/branch/main/fep/c0e0/fep-c0e0.md) (`EmojiReact`, and `Like` with content), and its reaction picker reads the same catalogue from the Emoji plugin. Its key rule matches the one #78176 arrived at: only U+FE0E and U+FE0F are removed, so skin tones, ZWJ sequences, and flags stay distinct (`unicode:U+1F44D` there, `1f44d` in Notes). Two things federation adds:

- A custom-emoji reaction is keyed by the authority that declared it (`custom:example.com:blobcat`), because two servers can ship the same shortcode. A shortcode that arrives without its `Emoji` declaration is not accepted as a reaction.
- One person may send several reactions to the same object, since FEP-c0e0 allows it and some servers send them.

Notes stores curated slugs and hex keys in `comment_content` of a `reaction` comment, while [Core Trac #64638](https://core.trac.wordpress.org/ticket/64638) registers comment meta for the earlier design, so the storage format is still open. It may be worth settling a key that local reactions, federated reactions, and custom emoji can share before it lands in Core.

Where #78176 is ahead: arrow-key navigation over the grid, a skin-tone selector (this plugin leaves skin-tone variants out of the grid), announced search results, translated emoji names, and ordering by frequency. This plugin is not meant to replace that picker; it shows the layers below it.

Known costs of the font approach: the download (the flags subset in automatic mode, the full font only when a site chooses it), a brief swap from the system font while the font loads, and it depends on the browser drawing COLRv1, which is why the plugin probes the font and steps aside when it cannot.

### Questions

1. Should the Notes picker become a reusable component, and where should its emoji data live so rendering can use the same list?
2. Should detection report per kind of sequence rather than `flag` and `emoji`?
3. Could a Font Library family declare that it is an emoji font (see #82848), so that detection prefers it before images?
4. Should the reaction key in Notes leave room for custom emoji with an authority, so the same key works for reactions that arrive from other sites?

### Related

- Gutenberg: #1678, #75144, #76767, #78176, #82848
- Core Trac: [#44001](https://core.trac.wordpress.org/ticket/44001), [#49885](https://core.trac.wordpress.org/ticket/49885), [#52219](https://core.trac.wordpress.org/ticket/52219), [#58663](https://core.trac.wordpress.org/ticket/58663), [#63451](https://core.trac.wordpress.org/ticket/63451), [#64638](https://core.trac.wordpress.org/ticket/64638), [#66104](https://core.trac.wordpress.org/ticket/66104)
- wordpress-develop: [#12252](https://github.com/WordPress/wordpress-develop/pull/12252)
