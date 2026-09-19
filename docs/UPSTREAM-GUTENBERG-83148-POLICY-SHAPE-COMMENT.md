# Draft — Issue #83148 comment: the policy is keyed by axis tag

> 상태: **게시됨** 2026-09-19 — [issuecomment-5741348491](https://github.com/WordPress/gutenberg/issues/83148#issuecomment-5741348491), `--github` 일치. [#83148](https://github.com/WordPress/gutenberg/issues/83148) 본문의 정책 예시를 list → 축 태그 keyed object로 바꾼 이유를 남기는 짧은 댓글. 본문 수정과 함께 게시.
>
> 측정(2026-09-19, 로컬 PHP `array_replace_recursive`, `WP_Theme_JSON::merge()`가 origin 병합에 쓰는 함수 — 순서형 배열 예외는 `spacing.units`와 프리셋뿐):
>
> - list: 부모 `[ {GRAD −50~50}, {opsz} ]` + 자식 `[ {XTRA} ]` → `[ {"tag":"XTRA","min":-50,"max":50}, {"tag":"opsz"} ]`
> - object: 부모 `{ GRAD: {−50~50}, opsz: {} }` + 자식 `{ XTRA: {} }` → `{ GRAD: {−50~50}, opsz: {}, XTRA: {} }`
>
> 구현: Gutenberg `58b0b56bc8`(#83159, PHPUnit `test_font_variation_policy_merges_per_axis`), fixture axismundi `fcfbce2`, blueprint `90bc650`. 삭제 semantics(`null`)는 넣지 않음(사용자 결정).
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83148-POLICY-SHAPE-COMMENT.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

I've changed the policy example above from a list to an object keyed by axis tag:

```json
"fontVariations": { "roboto-flex": { "GRAD": { "min": -50, "max": 50 }, "opsz": {} } }
```

theme.json merges origins with `array_replace_recursive`, which merges a list by index. With a parent's `[ { "tag": "GRAD", "min": -50, "max": 50 }, { "tag": "opsz" } ]` and a child theme adding `[ { "tag": "XTRA" } ]`, the result is `[ { "tag": "XTRA", "min": -50, "max": 50 }, { "tag": "opsz" } ]`: `GRAD` is gone and `XTRA` takes its range. Keyed by tag, the same merge gives `GRAD` with its range, `opsz`, and `XTRA`.

`axes` stays a list, since it describes one font file and is not merged across origins, and the chosen value was already an object. #83159 now reads the policy this way, with a test for the merge, and the [Playground demo](https://playground.wordpress.net/?gutenberg-pr=83159&blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2FJiwoon-Kim%2Faxismundi%2F90bc650%2Fdemos%2Fgutenberg-font-variations%2Fblueprint.json&storage=temp) uses it.
<!-- end of body -->
