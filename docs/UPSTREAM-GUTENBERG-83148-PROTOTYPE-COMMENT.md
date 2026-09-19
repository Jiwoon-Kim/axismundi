# Draft — Issue #83148 comment: prototype pointer (#83159)

> 상태: [issuecomment-5735646615](https://github.com/WordPress/gutenberg/issues/83148#issuecomment-5735646615)로 2026-09-19 게시됨(Codex 게시, 이 파일은 게시본에서 복원). 2026-09-19 **편집됨**(`gh api PATCH`, `--github` 일치): Playground 링크의 blueprint `93d994a` → `90bc650`(정책 keyed object, fixture `fcfbce2`). 옛 링크는 PR의 최신 빌드와 list 정책 fixture를 섞어 패널이 비므로 그대로 두면 안 됨(사용자 결정). 본문의 다른 문장은 게시본 그대로.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-GUTENBERG-83148-PROTOTYPE-COMMENT.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

I opened [#83159](https://github.com/WordPress/gutenberg/pull/83159) as a draft implementation of this model. It is deliberately a design experiment, not a request for code review yet.

It includes the three layers in this issue: a face `axes` capability list, `settings.typography.fontVariations` as the theme policy, and `styles.typography.fontVariationSettings` as an object value. The UI offers only the policy-exposed axes, in a separate Font variations panel for Paragraph, Heading, and the corresponding Global Styles nodes.

The prepared [Playground demo](https://playground.wordpress.net/?gutenberg-pr=83159&blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2FJiwoon-Kim%2Faxismundi%2F90bc650%2Fdemos%2Fgutenberg-font-variations%2Fblueprint.json&storage=temp) installs the PR build and a Roboto Flex fixture, then opens the demo post in the editor.

The draft intentionally treats capability and policy as availability for the UI, not as sanitization: changing the font family on a style node clears that node's axis values; otherwise stored or inherited values remain intact, including values the current panel does not offer. The open questions in this issue remain the decisions needed before the PR is ready for review.
<!-- end of body -->
