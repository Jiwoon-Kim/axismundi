# CSS class naming

> **Policy** / **정책** — established 2026-09-03.

## Not a compliance rule

**EN** — WordPress.org does not regulate CSS class prefixes. The Plugin
Handbook's "at least 4 letters, 5 recommended" applies to what it lists:
functions, classes, interfaces, traits, namespaces, global variables, options and
transients. The Block Directory's namespace rule governs the `name` in
`registerBlockType()` and `block.json`, not markup classes. This document is a
design decision, not a directory requirement -- do not cite the handbook for it.

**KO** — wp.org는 CSS 클래스 접두사를 규율하지 않는다. 핸드북의 "최소 4자, 권장
5자"는 함수·클래스·인터페이스·트레이트·네임스페이스·전역변수·옵션/트랜지언트에
적용되고, Block Directory의 네임스페이스 규칙은 `registerBlockType()`과
`block.json`의 `name`을 규율한다. 이 문서는 설계 결정이지 디렉터리 요구사항이
아니므로, 핸드북을 근거로 인용하지 말 것.

## The rules

1. **Base prefix is `ax-`.**
   기본 접두사는 `ax-`.

2. **A plugin's own surfaces carry a short owner segment**: `ax-cal-`, `ax-ce-`,
   `ax-nav-`. This, not prefix length, is what keeps names from colliding --
   `ax-media-folder-tree` is safe at three letters where `ax-card` would not be.
   플러그인 고유 표면은 짧은 소유 토막을 붙인다. 충돌을 막는 것은 접두사 길이가
   아니라 이름의 구체성이다.

3. **The theme owns the visual canon of a shared component.** A plugin may carry
   a fallback so it still works without the theme, but it reads the theme's
   tokens first and does not redefine the component.
   공유 컴포넌트의 시각 정본은 테마가 소유한다. 플러그인 fallback은 허용하되
   테마 토큰을 먼저 참조하고, 컴포넌트를 재정의하지 않는다.

4. **Dynamic class names are fine.** Keep the prefix and the component root
   literal -- `` `ax-cal--${view}` `` -- and constrain the variable part to a
   constant or enum in code. A static search will not find these; that is a limit
   of the search, not a fault in the code.
   동적 클래스는 허용한다. 접두사와 컴포넌트 뿌리는 리터럴로 두고, 가변부는
   코드의 상수/enum으로 제한한다.

5. **Existing classes are not renamed in bulk.** Tidy them when the surrounding
   area is being changed for another reason.
   기존 클래스는 일괄 개명하지 않는다. 그 영역을 다른 이유로 수정할 때 정리한다.

## What this leaves alone

As of writing, `.ax-` appears 1,097 times across 109 component roots and
`.axismundi-` 604 times across 25, with both spellings present in the theme and
in five plugins. Twenty-six names exist in both forms. Some of those pairs are
the same component named twice and some only share a word -- `ax-switch` and
`axismundi-switch` are different things, and the Activities feed styles say so in
a comment. Sorting them apart needs reading, not a regex, so rule 5 stands and
the list is not a work item.

## See also

- `docs/PLUGIN-RELEASE-LEDGER.md`
- `BACKLOG.md`
