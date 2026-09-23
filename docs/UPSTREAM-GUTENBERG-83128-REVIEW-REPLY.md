# Draft — Gutenberg #83128 reply to juanfra's review

> 상태: **초안.** 올릴 곳: [WordPress/gutenberg#83128](https://github.com/WordPress/gutenberg/pull/83128)
> 댓글. @juanfra의 리뷰(2026-09-22, `COMMENTED`)에 대한 답.
>
> 확인(2026-09-23, `gh api`로 원문 읽음): 리뷰 본문 = `"normal 900"`처럼 absolute keyword를 쓰는
> face를 우리 수정이 버린다, #81748과 비슷한 매핑을 하자. 인라인 제안 =
> `packages/block-editor/CHANGELOG.md:19`을 `FontAppearanceControl`로 시작하게.
>
> 대응 커밋 `5123e7365d`: `FONT_WEIGHT_KEYWORDS`(normal 400, bold 700)를 #81748과 같은 형태로
> 두고, 양 끝이 모두 풀릴 때만 variable로 취급. `lighter`/`bolder`는 `@font-face`에서 허용되지
> 않는 상대값이라 매핑하지 않고 범위 해석만 건너뛴다(face 자체 포맷팅은 유지). 테스트 12/12,
> lint·typecheck 통과. CHANGELOG 문구도 반영.
>
> 문구는 사용자가 확정(2026-09-23).
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83128-REVIEW-REPLY.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

Thanks, both points are addressed in 5123e7365d. The range parser now maps `normal` and `bold` using the same approach as #81748, while unsupported relative keywords leave the range unresolved. I also updated the changelog wording.
<!-- end of body -->
