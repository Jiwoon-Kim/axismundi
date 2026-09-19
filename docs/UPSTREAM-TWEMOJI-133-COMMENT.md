# Draft — jdecked/twemoji #133 comment: watermelon viewBox in font builds

> 상태: **게시됨** 2026-09-19 — [issuecomment-5742131079](https://github.com/jdecked/twemoji/issues/133#issuecomment-5742131079), `--github` 일치(사용자 승인: 새 이슈 대신 기존 #133에 댓글). 대상 [jdecked/twemoji#133](https://github.com/jdecked/twemoji/issues/133)("1f349.svg viewBox issue", 2025-05-09, open). 새 이슈를 만들지 않고 기존 이슈에 최소 재현과 영향만 보탠다(중복 방지). 고치는 PR은 [#102](https://github.com/jdecked/twemoji/pull/102)(2024-07-18, open, 미머지) — #101 작성자가 "aspect ratio를 실수로 바꿨다"고 밝힌 PR.
>
> 사용자 지시(2026-09-19): 최소 재현과 함께 보고, "upstream defect"라고 단정하지 않기. 본문은 관찰한 사실과 영향만 쓴다.
>
> 확인(2026-09-19):
> - `v17.0.3`(`b6b55fef…`) `assets/svg/`의 4,009개 중 `1f349.svg`만 viewBox가 `0 0 36 25.22`, 나머지는 `0 0 36 36`.
> - 변경 이력: #101 커밋 `36000f1be7`(2024-07-17)이 viewBox를 `0 0 36 36` → `0 0 36 25.22`로 바꿈. 72×72 PNG는 72×72 그대로.
> - nanoemoji 0.16.0(`glyf_colr_1`)으로 `assets/svg` 전체를 빌드한 폰트: upem 1024, `1F349` advance 1713, 나머지 매핑 글리프 1,427개는 모두 1275.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-TWEMOJI-133-COMMENT.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

This is still the case in v17.0.3: of the 4,009 files in `assets/svg`, `1f349.svg` is the only one whose viewBox is not `0 0 36 36`.

One effect, in case it helps with #102: tools that build a font from `assets/svg` keep each SVG's aspect ratio, so the watermelon gets a wider advance than every other emoji. Building all of `assets/svg` with nanoemoji 0.16.0 (`glyf_colr_1`, 1024 units per em), `1F349` has an advance of 1713 and the other 1,427 mapped glyphs all have 1275, so it takes about a third more room in a line of text.

To reproduce:

```sh
grep -L 'viewBox="0 0 36 36"' assets/svg/*.svg
# assets/svg/1f349.svg
```

The viewBox changed in #101 (`0 0 36 36` → `0 0 36 25.22`); the 72×72 PNG is still square.
<!-- end of body -->
