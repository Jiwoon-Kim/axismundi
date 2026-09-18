# Draft — Discussion #82830 comment: pointer to the axes issue

> 상태: **게시됨** 2026-09-19 — [discussioncomment-18505721](https://github.com/WordPress/gutenberg/discussions/82830#discussioncomment-18505721). #83148(축 모델 이슈) 게시 뒤 #82830 토론에 다는 안내 댓글. 새 논지는 넣지 않고, 앞 댓글(object 예시에 `wght`·`opsz`)을 #83148이 정정했다는 사실과 링크만.

## Body (GitHub Markdown — paste as is)

The variable font axis model is now proposed in #83148. It corrects the object example in my comment above: `wght`, `wdth`, `slnt` and `ital` stay on their CSS properties (`fontWeight`, `fontStretch`, `fontStyle`), since `font-variation-settings` would override `<strong>` and miss fallback fonts without the axis, and only custom axes such as `GRAD`, plus a manual `opsz`, go in `fontVariationSettings`. #83141 covers `wght` in the Appearance control.
<!-- end of body -->
