# Draft — Core Trac #44001 comment: keep emoji as text, fix detection first

> 상태: **게시됨** — [comment:17](https://core.trac.wordpress.org/ticket/44001#comment:17)(2026-09-14경, 이 파일의 상태 줄이 갱신되지 않았던 것을 2026-09-20 확인). 두 전제가 낡아 [comment:18](https://core.trac.wordpress.org/ticket/44001#comment:18)에서 정정: #66104는 trunk [63755]에서 수정, `unicode-range` 폰트 스택 제안은 wrapper 전용 renderer로 대체(`UPSTREAM-TRAC-44001-REPLY-16.md`). 원래 표기: 초안, 미게시 (2026-09-14). 올릴 곳: Core Trac #44001 댓글(사용자, Trac 로그인 필요).
> PR #12252 본문이 "토론은 Trac에서"라고 요청 → GitHub PR에는 댓글 안 함.
>
> 확인한 사실(2026-09-14):
> - wordpress-develop PR #12252 "Emoji: serve image assets locally by default": draft, 변경 파일 8,020개
>   (API 목록 상한 3,000개 중 PNG 2,999개 `src/wp-includes/images/emoji/72x72` + `formatting.php`), 본문 "bundled locally under 72x72 and svg",
>   리뷰 없음, 댓글 2개는 모두 봇.
> - Core `formatting.php`에 이미 `emoji_url`(72x72 PNG)·`emoji_svg_url` 필터 있음 → 사이트가 자체 호스팅 가능.
> - #66104 / PR #13515: Emoji 17 검사 문자열 오류로 Windows 11·Chromium 152에서 `emoji: false` → 모든 이모지 교체.
>   수정하면 같은 환경에서 `{ flag: false, emoji: true }` → 국기만 교체. 다른 엔진·OS는 미측정.
> - #44001 티켓 본문은 Trac 봇 확인 때문에 직접 못 읽음 → PR #12252 본문 기준으로만 인용.
>
> 게시 전: 티켓의 최신 댓글 흐름을 읽고 이미 나온 논점이면 빼기. 우리 채운 경로를 강요하는 어조 피하기.

---

## Comment (Trac WikiFormatting — paste as is)

I agree with the goal here: a visitor's IP and headers should not go to a third party just because a page contains an emoji. I would question the way [https://github.com/WordPress/wordpress-develop/pull/12252 PR 12252] gets there, though, for three reasons.

**1. Emoji are text.** The fallback replaces Unicode text with `<img>` elements. Bundling the image sets moves that representation into every WordPress install: PR 12252 adds about 8,000 files (72x72 PNGs and SVGs), and each Twemoji or Unicode update would mean re-shipping thousands of binaries in Core.

**2. Much of today's fallback comes from a detection bug.** Since [61134] the Emoji 17 support test draws a malformed string, so browsers that support Emoji 17 are reported as not supporting it, and every emoji is replaced with images rather than only what is actually missing (#66104, patch in [https://github.com/WordPress/wordpress-develop/pull/13515 PR 13515]). On Windows 11 with Chromium 152, trunk reports `{ flag: false, emoji: false }`; with the fix it reports `{ flag: false, emoji: true }`, so only country flags would still need a fallback there. I have only measured that browser, but it suggests fixing #66104 first and then measuring what really still needs a fallback, before deciding how much to bundle.

**3. Self-hosting is already possible.** The `emoji_url` and `emoji_svg_url` filters let a site serve these images from its own host today, without every install carrying the files by default.

If a local fallback is still wanted, another option keeps emoji as text: a locally hosted emoji font, limited with `unicode-range` to what the browser is missing (flags, for example), placed in the fallback font stack. The browser keeps rendering Unicode, and only the glyphs it lacks load, from the site itself. For Core to use a font that way, it has to know that a Font Library family is an emoji font, and today nothing can declare that: the field is removed along the way. That metadata is proposed in [https://github.com/WordPress/gutenberg/issues/82848 Gutenberg issue 82848]. Colour-font support across browsers would need checking before that becomes a recommendation, so I am raising it as a direction rather than a replacement patch.
