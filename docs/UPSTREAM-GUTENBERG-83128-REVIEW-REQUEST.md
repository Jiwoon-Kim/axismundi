# Draft — PR #83128 comment: review request

> 상태: **게시됨** 2026-09-19 — [issuecomment-5740991219](https://github.com/WordPress/gutenberg/pull/83128#issuecomment-5740991219), `--github` 되읽기 일치(SHA-256 `46674d9e…`). [WordPress/gutenberg#83128](https://github.com/WordPress/gutenberg/pull/83128)(가변 폰트 weight 범위 파싱 수정, 일반 PR)에 리뷰어 한두 명을 지목하는 댓글. Slack에 넓게 던지기 전에 관련 파일의 실제 이력으로 고름(사용자 결정 2026-09-19).
>
> 선정 근거(2026-09-19, `git log upstream/trunk`·`gh`):
>
> - `@t-hamano`: `get-font-styles-and-weights`를 만든 #61915의 리뷰어, Font Style UI 무한 루프 수정 #73955(`typography-panel`, `font-appearance-control` e2e) 작성. 8월 이후 리뷰 100+·작성 55.
> - `@juanfra`: #81748(2026-08-21 머지) "Font Library: Fix the preview weight for variable fonts" — 같은 `"100 900"` 형태의 가변 weight 범위를 다룸. 8월 이후 리뷰 11.
> - 제외: `mikachan`(#61915 작성·#64953·#73955 승인, 8월 이후 활동 0), `ramonjd`(#64953 머지만, 타이포그래피 이력 없음).
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83128-REVIEW-REQUEST.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

@t-hamano @juanfra, could one of you take a look when you have a moment? You've both worked on this area: #73955 and the review of #61915, where this function came from, and #81748, which handled the same kind of weight range for the Font Library preview.

It's a small parsing fix: a face with `fontWeight: "250 750"` was read from its first digit, so the Appearance control offered 200. #83141 (draft) builds on it.
<!-- end of body -->
