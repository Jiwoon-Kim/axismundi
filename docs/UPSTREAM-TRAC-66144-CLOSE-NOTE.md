# Draft — Core Trac #66144 comment after the wontfix close

> 상태: **초안.** 올릴 곳: [Core Trac #66144](https://core.trac.wordpress.org/ticket/66144),
> [comment:6](https://core.trac.wordpress.org/ticket/66144#comment:6)(@peterwilsoncc, Core
> Committer, 2026-09-21, `wontfix`/unplanned)에 대한 짧은 기록.
>
> 사용자 결정(2026-09-23): **후속 티켓을 지금 열지 않는다.** 닫힌 직후 새 티켓은 집착으로 읽히고
> 짜증을 유발한다. 댓글 하나만 남기고, 재오픈을 요청하지 않는다.
>
> 확인(2026-09-23, 브라우저에서 원문 읽음): comment:6의 근거 셋 = ①글 하나에 이모지 한 개면
> 854바이트인데 웹폰트는 657,364바이트 ②RSS는 `wp_staticize_emoji()`, HTML 메일은
> `wp_staticize_emoji_for_email()`이라 웹폰트로 불가 ③대체 renderer는 detection 스크립트를
> `admin_print_scripts`·`wp_head`·`embed_head`에서 떼고 self-hosted URL 필터로 이미 가능.
>
> 담을 것 셋(사용자 지시): ①이 PR은 이미지 fallback을 대체하지 않는다 ②#44001을 로컬 자산으로
> 풀려면 결국 번들이 필요하고, 그때 flags 서브셋이 이미지보다 우아하다 ③옵트인이면 된다고 본다.
>
> 담지 않을 것: 재오픈 요청, full font 재주장, #63451 끌어오기(별도 판단).
>
> 후속(2026-09-23): wordpress-develop **PR #13618은 별도 정리 댓글 없이 닫음**(티켓 결정에 맞춤,
> `CLOSED` 10:30Z). 재현 빌드와 플러그인 구현은 우리 저장소에 그대로 남는다.
> `opt-in`은 #44001의 해법이 아니라 **기본 전송량을 바꾸지 않는 정책 레버**로만 쓴다 — #44001의
> 목적은 원격 fallback을 기본에서 없애는 것이므로 그 자리를 대신하지 않는다.
>
> 수치 근거: flags-only 서브셋 108,888 bytes(전체 657,364의 16.6%), Edge 153에서 flag profile
> 265/265 통과. `research/twemoji-colrv1/`의 manifest.
>
> 게시: Trac은 로그인·봇 확인이라 사용자 Chrome에서 스크립트로 본문을 넣고 제출, 게시 뒤
> `--posted`로 대조.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-TRAC-66144-CLOSE-NOTE.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Comment (Trac WikiFormatting — paste as is)

Understood, and thank you for the measurement — the transfer cost of the full font against a post with one or two emoji is the right thing to weigh.

Two notes for the record, not a request to reopen.

The patch does not replace the image fallback. The scope in this ticket keeps the existing image renderer, so `wp_staticize_emoji()` for feeds and `wp_staticize_emoji_for_email()` for HTML email would have gone on producing images; the font path would have applied to HTML pages only.

If #44001 is resolved by serving the fallback locally, something has to be bundled either way, and there a flags-only subset looks smaller than the image sets: cut from the same font, it is 108,888 bytes and covers country, subdivision, rainbow, transgender and pirate flags. Since [63755] that is also the only profile Windows with Chromium reports as unsupported, so a site would download it only where flags actually need replacing. Making it opt-in would leave the default transfer unchanged.

The reproducible build stays in my own repository, and the plugin path you describe is the one I am using.
<!-- end of body -->
