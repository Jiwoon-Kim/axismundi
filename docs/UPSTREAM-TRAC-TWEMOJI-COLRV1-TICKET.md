# Core Trac #66144 — versioned Twemoji COLRv1 font fallback

> 상태: **게시됨** (2026-09-19). [Core Trac #66144](https://core.trac.wordpress.org/ticket/66144). #44001에 연결한다.
>
> 범위(사용자 결정 2026-09-19):
> - `#66119`(`WP_Font_Face`가 query string 든 `src`를 버리는 버그)와 분리. 이 티켓은 일반 폰트 버전 API가 아니라 **Twemoji라는 구체적 Core 자산**으로 연다.
> - 단순 "번들" 제안이 아니라 소비 경로(감지 → renderer → 실패 시 기존 이미지)와 Gutenberg 쪽 큰 그림(Font Library `usage: emoji`, renderer 교체)을 보여 준다. 다만 첫 구현 범위는 작게.
> - 첫 PR은 Twemoji(현재 Core 자산). Noto는 유지 비용이 싼 대안으로 설계 근거에 남긴다.
> - 표현 원칙: FE0F 원인은 단정하지 않는다. Edge VQA에서 fully-qualified RGI를 정상 렌더링하려면 FE0F-less alias가 필요했다는 관찰만 쓴다. 수박은 "후보 사례"이지 결함 단정 아님. HarfBuzz는 보조 근거.
> - 이전 초안 `UPSTREAM-TRAC-44001-COMMENT.md`(2026-09-14, 미게시)는 폰트를 fallback 스택에 `unicode-range`로 넣자고 했고 #66104를 미해결로 적었다 — 둘 다 이 초안이 대체한다(cmap 위험 → wrapper 전용, #66104는 trunk [63755](git `8c008ff3c7`)에서 수정).
>
> 근거: `docs/AXISMUNDI-EMOJI-UNICODE.md` §13(특히 §13.6), `research/twemoji-colrv1/`(커밋 `1dec590`).
>
> 게시 기록:
> - research commit `1dec590`과 이 문서 commit `2cc6f01`은 게시 전에 GitHub에 푸시됐다.
> - 원문은 Trac 미리보기에서 확인하고, Summary·Component·Type과 함께 제출했다.
> - end-to-end 데모(감지 결과·wrapper 대상·원문/selection 결과)는 이후 Evidence 보강 후보로 남긴다.

## Fields

- Summary: `Emoji: Bundle a versioned Twemoji COLRv1 font as the emoji fallback`
- Type: enhancement
- Component: Emoji
- Keywords: (none)

## Description (Trac WikiFormatting — paste as is)

== Problem ==

When a browser cannot draw an emoji, Core replaces it with a Twemoji image from `s.w.org`. #44001 asks for that fallback to be served locally, and [https://github.com/WordPress/wordpress-develop/pull/12252 PR 12252] does it by bundling the images: 4,009 PNG files (4.05 MiB) and 4,009 SVG files (9.65 MiB), which every Twemoji update would re-ship. The images also replace text: the emoji becomes an `<img>`, and the block editor does not load the fallback at all, so the editor and the front end draw emoji differently.

== Proposal ==

Core ships Twemoji as one COLRv1 font instead of images, and uses it only for RGI graphemes in capability profiles that the browser reports as unsupported.

 * '''One static file.''' Twemoji 17.0.3 as a COLRv1 WOFF2 is 657,364 bytes (642 KiB) and covers every fully-qualified RGI emoji in Emoji 17.0. Sites serve it as a static file; no site builds anything.
 * '''Built once, reproducibly.''' The font is built by maintainers when Twemoji is updated, from a pinned Twemoji release (commit, archive and SVG-set SHA-256), with pinned tools, and a manifest that records the input, the tools and the output SHA-256. A second build is byte-identical.
 * '''Emoji stay text.''' The fallback wraps the RGI graphemes in an unsupported capability profile and gives the wrapper the font. Copying, searching and screen readers keep the Unicode text.
 * '''Versioned URL.''' The font is served with `?ver=<output SHA-256>`, so a Twemoji update is downloaded once and cached until the next one. That relies on #66119 (`WP_Font_Face` drops a `src` with a query string) if the font is printed through `WP_Font_Face`.

== How it is used ==

 1. Emoji detection runs as it does today. With the Emoji 17 test fixed in [63755] (#66104), Windows with Chromium reports `{ flag: false, emoji: true }`: the flag profile needs a fallback there. The detector does not identify support for each individual grapheme.
 2. For an unsupported profile, the renderer wraps its defined RGI graphemes and applies the font to the wrapper, on the front end and in the editor iframe alike.
 3. The implementation must choose the font renderer or the existing image renderer before either mutates the text. A font-load failure must leave the source text for the image renderer; this handoff is implementation work, not yet demonstrated by the standalone prototype.

The font must not be placed in a general `font-family` stack. To shape keycaps, its cmap includes `#`, `*`, `0`–`9` (with zero advance) and the space, and it includes characters that default to text presentation, such as `©` and `↔`. At the front of a stack, digits would disappear; as a fallback after the text font, text-presentation characters the text font lacks would turn into colour emoji. Only a renderer that wraps detected emoji can use it safely.

== Evidence ==

The build is at [https://github.com/Jiwoon-Kim/axismundi/tree/1dec590/research/twemoji-colrv1 research/twemoji-colrv1] (Dockerfile, lock file, build script, manifests).

||= Twemoji =||= WOFF2 =||= Rebuild =||= Edge 153: fully-qualified RGI =||
|| 17.0.3 || 657,364 bytes || byte-identical || 3,944 / 3,944 ||
|| 17.0.2 (what Core serves today) || 657,128 bytes || — || 3,944 / 3,944 ||

In the Edge check each sequence is drawn in the font and in the system emoji font; it passes a single-glyph-width check, a colour check and a pixel-difference check against the system drawing. Minimally-qualified (1,029), unqualified (243) and component (9) sequences pass too. The 17.0.2 and 17.0.3 SVG sets are byte-identical; 17.0.3 changed only the JavaScript parser.

The build normalizes its input in two recorded ways:

 * '''Aliases for spellings without FE0F.''' In the first build, 960 fully-qualified sequences rendered split in Edge: every one whose Twemoji SVG name includes FE0F. Each fully-qualified sequence now also gets its spelling without FE0F (1,052 aliases), and all 3,944 render correctly. HarfBuzz alone had shaped all of them to one glyph, so the check is done in a browser.
 * '''One SVG with a non-square viewBox.''' `1f349.svg` (watermelon, viewBox `0 0 36 25.22`) got an advance of 1713 against 1275 for every other glyph, so it is padded to a square. It is reported upstream in [https://github.com/jdecked/twemoji/issues/133 jdecked/twemoji#133], with a fix open in [https://github.com/jdecked/twemoji/pull/102 #102]; the normalization can go once that lands.

The font carries the CC-BY 4.0 attribution for the Twemoji graphics in its name table, and `LICENSE-GRAPHICS` and `source.txt` travel with it.

== Alternative: Noto Color Emoji ==

In a Chrome measurement of representative sequences, Noto Color Emoji COLRv1 handled both FE0F spellings and had uniform metrics. It is OFL; the full font is 1.88 MiB, and a subset of the RGI flags alone is 699 KiB. Twemoji is proposed here because it is what Core draws today, it is smaller for the full set, and it keeps the look of existing sites.

== Scope ==

First:
 * The Twemoji 17.0.3 COLRv1 WOFF2 under `wp-includes/fonts/twemoji/`, with its manifest, `source.txt` and `LICENSE-GRAPHICS`, and the build tooling under `tools/emoji/`.
 * Keep the existing 17.0.2 image fallback in this PR. Its SVG set is byte-identical to 17.0.3; updating Core's image/parser release is separate work.
 * The renderer: wrap the RGI graphemes in unsupported capability profiles and apply the font on the front end and in the editor iframe. It must coordinate a font-load failure with the existing image renderer before either mutates the text.

In the editor, the wrappers must not change what is edited or saved: caret movement, selection, paste, undo and redo, and the saved content and front-end output after reopening all need checking.

Later:
 * The font shown in the Font Library as an emoji font (`usage: emoji`, [https://github.com/WordPress/gutenberg/issues/82848 Gutenberg issue 82848]) that is not offered as a text font; the discussion is in [https://github.com/WordPress/gutenberg/discussions/83032 Gutenberg discussion 83032].
 * Letting a plugin or theme provide another emoji renderer, such as Noto, with users choosing between the system's emoji, automatic fallback and a site font.
<!-- end of body -->
