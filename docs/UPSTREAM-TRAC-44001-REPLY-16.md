# Draft — Core Trac #44001 reply to comment:16 (@masteradhoc)

> 상태: **게시됨** 2026-09-20 — [comment:18](https://core.trac.wordpress.org/ticket/44001#comment:18). 스크립트로 넣기 전 브라우저에서 SHA-256 대조, 게시 뒤 `cnum_edit=18` 원문도 같은 해시(`38854c59…`); 렌더링: 인용 블록, 링크 #66144·PR 13618·#12252·comment:17·#66104·[63755], 목록 2항목. 올릴 곳: [Core Trac #44001](https://core.trac.wordpress.org/ticket/44001) 댓글, [comment:16](https://core.trac.wordpress.org/ticket/44001#comment:16)에 대한 답.
>
> 확인(2026-09-20, 로그인 세션에서 읽음):
> - comment:16 = @masteradhoc(**Emoji component maintainer**, #12252 작성자), 3개월 전. GDPR/프라이버시 때문에 로컬 제공 찬성, 트레이드오프는 파일 수·패키지 크기, 선례로 smilies 이미지·**Dashicons 폰트(`wp-includes/fonts/`)**·번들 JS·로컬 emoji loader를 듦, draft PR #12252.
> - comment:17 = 사용자 본인(5일 전, `UPSTREAM-TRAC-44001-COMMENT.md` 초안이 게시된 것). 두 가지가 이제 낡음: ① #66104 미해결로 적음 → trunk [63755]에서 수정, ② 폰트를 fallback 스택에 `unicode-range`로 넣자고 제안 → 폰트 cmap에 숫자·공백·text-default 문자가 있어 wrapper 전용이어야 함(§13.6).
>
> 사용자 문구 수정(2026-09-20): 첫 항목 범위를 "측정한 Windows/Chromium 경우"로, 둘째 항목을 "capability profile 기준으로 fallback이 필요한 시퀀스를 감싸는 renderer"로 좁힘.
> 사용자 결정(2026-09-20): #12252를 공격하지 않고 같은 목표의 측정된 대안으로 제시. 구현 범위는 자산만이라고 분명히.
>
> 게시: 사용자 Chrome(로그인)에서 댓글 칸에 스크립트로 본문을 넣고 제출, 렌더링 대조.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-TRAC-44001-REPLY-16.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Comment (Trac WikiFormatting — paste as is)

Replying to [comment:16 masteradhoc]:
> For GDPR / privacy reasons im in favor of serving emoji assets locally in WordPress core instead of fetching them from s.w.org.

I agree that the fallback assets should be served locally. I opened #66144 and draft [https://github.com/WordPress/wordpress-develop/pull/13618 PR 13618] as a smaller way to bundle them than #12252: one reproducibly built Twemoji COLRv1 WOFF2 of 657 KB, in `wp-includes/fonts/` next to Dashicons, instead of the PNG and SVG image sets. In Edge it renders all 3,944 fully-qualified RGI emoji as one glyph each.

The draft only adds the font and its build provenance. It does not load the font or change the fallback yet; how the font is applied is a separate design question in #66144.

Two corrections to my comment:17:
 * The Emoji 17 detection fix (#66104) is now in trunk ([63755]). In the Windows/Chromium case I measured, only flags still need a fallback.
 * I suggested an emoji font in the fallback font stack, limited with `unicode-range`. The built font maps digits, space, and text-presentation characters such as `©`, which keycaps need, so it cannot sit in a text font stack. It has to be applied only by a renderer that wraps sequences known to need fallback for the browser's capability profile.
<!-- end of body -->
