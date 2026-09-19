# Draft — Core Trac #66144 comment: CC BY 4.0 is not a blocker

> 상태: **게시됨** 2026-09-20 — [comment:4](https://core.trac.wordpress.org/ticket/66144#comment:4). 댓글 칸에 스크립트로 넣기 전 브라우저에서 본문 SHA-256 대조, 게시 뒤 `cnum_edit=4`의 원문도 같은 해시(`4247a30b…`). 올릴 곳: [Core Trac #66144](https://core.trac.wordpress.org/ticket/66144) 댓글. PR [#13618](https://github.com/WordPress/wordpress-develop/pull/13618) 템플릿이 "PR은 코드 리뷰만, 다른 논의는 Trac에서"라고 하므로 PR이 아니라 티켓에 단다.
>
> 목적(사용자 결정 2026-09-20): 라이선스가 블로커가 아니라는 근거를 남기고, 남는 질문을 attribution 위치로 좁힌다.
>
> 확인한 원문(2026-09-20):
> - FSF [license list #ccby](https://www.gnu.org/licenses/license-list.html#ccby): CC BY 4.0은 "a non-copyleft free license that is good for art and entertainment works, and educational works. It is compatible with all versions of the GNU GPL; however, like all CC licenses, it should not be used on software."
> - Core Handbook [Licensing](https://make.wordpress.org/core/handbook/about/licensing/): "Third-Party libraries must be GPLv2 compatible, but retain their own copyright."
> - Themes Handbook [Resources](https://make.wordpress.org/themes/handbook/review/resources/): 이미지에 "compatible and suitable": CC0, CC BY 4.0, CC-BY-SA 4.0(GPLv3 only). [Required](https://make.wordpress.org/themes/handbook/review/required/): "All code, data, and images … must comply with the GPL or a GPL-Compatible license."
> - CC BY 4.0 조건: 저작자 표시, 라이선스 링크, 변경 여부 표시([deed](https://creativecommons.org/licenses/by/4.0/)). 이 빌드의 변경(폰트 변환, alias, 수박 viewBox)은 `source.txt`에 기록.
>
> 게시: 사용자 Chrome(로그인)에서 댓글 칸에 스크립트로 본문을 넣고(타이핑 금지 — #66144 설명 입력 때 글자 변형 사고), 제출 뒤 렌더링된 댓글을 원문과 대조.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-TRAC-66144-LICENSE-COMMENT.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Comment (Trac WikiFormatting — paste as is)

On licensing, since the font would put Twemoji graphics in Core itself: I don't think it is a blocker.

 * The Twemoji graphics are CC BY 4.0, which the FSF lists as "compatible with all versions of the GNU GPL" ([https://www.gnu.org/licenses/license-list.html#ccby license list]). The Core handbook asks that third-party libraries be GPLv2 compatible and keep their own copyright ([https://make.wordpress.org/core/handbook/about/licensing/ Licensing]).
 * The Themes team already lists CC BY 4.0 as compatible and suitable for images bundled with themes ([https://make.wordpress.org/themes/handbook/review/resources/ Resources]).
 * CC BY 4.0 asks for attribution, a link to the license, and an indication of changes. In [https://github.com/WordPress/wordpress-develop/pull/13618 PR 13618] the font carries the attribution and license link in its name table, `LICENSE-GRAPHICS` sits next to it, and `source.txt` records what the build changed: the conversion to a font, the aliases for spellings without FE0F, and the padded viewBox of one SVG.

What is left to decide is where Core wants the attribution: whether those files next to the font are enough, or whether `license.txt` or the credits should mention Twemoji's graphics as well.
<!-- end of body -->
