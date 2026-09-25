# Draft — Gutenberg #83456 review request

> 상태: **초안.** 올릴 곳: [WordPress/gutenberg#83456](https://github.com/WordPress/gutenberg/pull/83456) 댓글.
>
> 맥락: #83128이 `139e47158c`로 trunk에 머지됨(2026-09-24). 그 머지로 생긴 충돌은 GitHub 웹
> Resolve conflicts로 해소(`087e7fb69f`), 체크 **77 pass / 0 fail**, `mergeable: MERGEABLE`.
>
> **누구에게:** `@t-hamano`, `@juanfra` — #83128을 승인한 두 사람이고, 이 PR은 같은 헬퍼의
> 바로 다음 수정이다. `@ellatrix`는 리뷰 요청 상태로 남아 있으므로 **중복 멘션하지 않는다**
> (#83128도 ellatrix 요청이 남은 채 두 사람 승인으로 머지됐다).
>
> **라벨:** 확인 결과 `[Type]` 라벨이 **없다**(labels = `[Package] Block editor` 하나,
> 봇 댓글 `Found: none`). `Check the type label`은 통과한 게 아니라 이번 실행에서 빠졌다.
> 저장소 권한이 `pull`뿐이라 직접 붙일 수 없고, 봇 안내도 리뷰어에게 부탁하라고 한다.
> 그래서 리뷰 요청과 함께 한 줄로 묻는다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83456-REVIEW-REQUEST.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

@t-hamano @juanfra, now that #83128 is in trunk, this is the `font-style` side of the same helper: a `@font-face` may declare a two-angle oblique range, and it was being offered as an appearance value that `font-style` discards. The conflict with trunk is resolved and the checks are green.

Would you have a moment to look? It also still needs a `[Type]` label, which I cannot add myself — `[Type] Bug` if that reads right to you.
<!-- end of body -->
