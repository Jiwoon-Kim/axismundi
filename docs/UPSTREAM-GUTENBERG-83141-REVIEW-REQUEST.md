# Draft — Gutenberg #83141 review request

> 상태: **게시됨** 2026-09-26 — [issuecomment-5836932521](https://github.com/WordPress/gutenberg/pull/83141#issuecomment-5836932521). `--github` 되읽기 일치(SHA-256 `73504245…`).
>
> 맥락: #83128이 trunk에 머지된 뒤(2026-09-24) 이 브랜치를 trunk `ad5b19d2c3` 위로 리베이스해
> 범위 수정 커밋을 없앴다(`--force-with-lease`, 사용자 1회 승인). 남은 두 커밋은
> `a9e305c9d2`(기능)과 `fa3a8dcb12`(`parseFontWeightValue()` 공용 모듈). 2026-09-26 본문 교체 후
> draft → ready 전환 완료(`isDraft: false`, `mergeable: MERGEABLE`).
>
> **누구에게:** `@t-hamano`, `@juanfra` — #83128을 승인한 두 사람이고 이 PR이 그 후속이다.
> `@ellatrix`는 리뷰 요청 상태로 남아 있으므로 **중복 멘션하지 않는다**(#83456에서 쓴 판단과 같다).
>
> **라벨:** 현재 labels = `[Package] Block editor` 하나이고 `Check the type label` 체크가
> **FAILURE**다. 저장소 권한이 `pull`뿐이라 `gh pr edit --add-label`도 `--add-reviewer`도
> `does not have the correct permissions`로 거부된다. 그래서 댓글로 함께 부탁한다.
>
> **게시 시점:** Playwright 실행이 끝나 라벨 외 실패가 없는 것을 확인한 뒤 올린다.
> 본문에 "체크가 초록"이라고 쓰지 않은 이유이기도 하다 — 라벨 체크는 실제로 실패 중이다.
> 확인 완료(2026-09-26): **77 success / 12 skipped / 1 failure = `Check the type label`**.
>
> **범위 밖 문단 추가(사용자 지시 2026-09-26):** 아직 PR이 없는 다음 단계를 댓글에 적어 둔다.
> 근거는 8889 실측 — `zz-vqa-weight-range`를 잠시 끄고 `zz-vqa-font-variations`의 Roboto Flex
> 2-face(`normal` + `oblique 0deg 10deg`)로 이 브랜치 빌드를 열었더니 Style 선택지가
> `Default · Regular · oblique 0deg 10deg · Italic`, Weight는 `Thin (100)`–`Extra Black (1000)` +
> custom 토글. raw 범위는 #83456이 해소하고(그 브랜치 테스트가 이 입력을 `Regular`+`Italic`로 고정),
> faux Italic과 `slnt` 각도 컨트롤은 #83148 Style 쪽 — 셋 다 #83141의 회귀가 아니다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83141-REVIEW-REQUEST.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

@t-hamano @juanfra, this is the follow-up to #83128, now that the range parsing is in trunk: when a family is variable, Appearance splits into a Style select and a Weight control that reaches any weight in the range, not only the hundreds. I have rebased the branch, so it is the feature commit plus one more — both controls now read a face's weight range through the same module, which is what taught the weight range the keywords #83128 taught the appearance list. A face declaring `"normal 900"` used to be read by one and skipped by the other.

Out of scope here, and not a PR yet: the Style select beside it. Checking this branch against Roboto Flex, whose faces declare `normal` and `oblique 0deg 10deg`, the select offers `Regular`, the two-angle range as written, and a faux `Italic` for a font that has `slnt` and no `ital` axis. The raw range is #83456, which resolves it to a style the property accepts. Giving `slnt` an angle of its own, and deciding what Italic should mean for such a font, is the Style side of #83148; I have not opened anything for it.

Would you have a moment to look? It also needs a `[Type]` label, which I cannot add myself — `[Type] Enhancement` if that reads right to you.
<!-- end of body -->
