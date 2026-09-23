# Draft — Gutenberg #83128 in-thread reply to the CHANGELOG suggestion

> 상태: **초안.** 올릴 곳: [#83128](https://github.com/WordPress/gutenberg/pull/83128)의 인라인
> 리뷰 스레드(`packages/block-editor/CHANGELOG.md`, @juanfra의 `suggestion`).
> 스레드 id `PRRT_kwDOBNHdeM6kx6_o`, 첫 댓글 `4072999806`, 현재 `isOutdated: true`(그 줄을 이미
> 고쳤기 때문), `isResolved: false`.
>
> 사용자 지적(2026-09-23): 인라인 제안은 **그 스레드에서 답하고 resolve**하는 것이 맞다. 일반
> 댓글([issuecomment-5793527447](https://github.com/WordPress/gutenberg/pull/83128#issuecomment-5793527447))은
> 이미 게시됨 — 그것은 리뷰 본문(키워드 결함)에 대한 답이고, 이 스레드는 CHANGELOG 문구 몫.
>
> 제안을 **그대로 적용하지 않은 이유**를 적는다: 같은 커밋에서 키워드 처리가 들어가 문장이
> 한 가지를 더 말해야 했다. 제안한 형태(컴포넌트명으로 시작)는 그대로 따랐다.
>
> 게시 후 스레드를 resolve한다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83128-THREAD-REPLY.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

Applied, with one addition: the same commit taught the parser the `normal` and `bold` keywords, so the entry names them too.
<!-- end of body -->
