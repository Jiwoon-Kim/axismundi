# Draft — wordpress-develop PR for Core Trac #63451 (flag fallback)

> 상태: **게시됨** 2026-09-17 — [WordPress/wordpress-develop#13581](https://github.com/WordPress/wordpress-develop/pull/13581), 커밋 `87ff1bf6bc`. `--github` 되읽기 일치. 사용자 결정: #63451은 우리가 PR을 연다. Trac #63451에 `has-patch has-unit-tests` 키워드와 PR 링크 댓글은 사용자 몫(Trac 로그인 필요); `close` 키워드는 제거 요청 필요.
>
> 브랜치: `C:/Users/thaum/dev/wordpress-develop` `fix/63451-flag-fallback` (upstream/trunk `eb3383316f` 기준).
> 변경: `src/js/_enqueues/wp/emoji.js`(`everythingExceptFlag` 교체 목록에 subdivision 태그 시퀀스와 트랜스젠더 깃발 추가), 새 QUnit 페이지 `tests/qunit/wp-includes/js/emoji-flags.html`·`emoji-flags.js`.
> 파일 이름을 `emoji-flags.*`로 한 이유: #66120의 외부 PR #13568이 `tests/qunit/wp-includes/js/emoji.html`·`emoji.js`를 새로 만든다. 같은 이름이면 두 PR이 충돌한다. `tests/qunit/qunit.js`는 `tests/qunit` 아래 모든 `.html`을 실행하므로 별도 페이지도 CI에서 돈다.
>
> ## 근거
>
> - Twemoji 콜백 코드(trunk `src/js/_enqueues/vendor/twemoji.js`를 Node `vm`으로 실행): 잉글랜드 `1f3f4-e0067-e0062-e0065-e006e-e0067-e007f`, 스코틀랜드 `…-e0073-e0063-e0074-e007f`, 웨일스 `…-e0077-e006c-e0073-e007f`, 트랜스젠더 `1f3f3-fe0f-200d-26a7-fe0f`, 무지개 `1f3f3-fe0f-200d-1f308`, 해적 `1f3f4-200d-2620-fe0f`, 한국 `1f1f0-1f1f7`, 검은 깃발 단독 `1f3f4`, 흰 깃발 단독 `1f3f3`.
> - QUnit(Playwright, `CI=1`로 시스템 Chrome, 빌드 없이 두 파일을 `build/wp-includes/js/`에 복사 — Gruntfile 596–597행이 그대로 복사하는 파일): trunk `emoji.js` 16개 중 8개 실패(잉글랜드·스코틀랜드·웨일스·트랜스젠더 × 2), 수정본 16/16 통과. jshint(`.jshintrc`, `tests/qunit/.jshintrc`) 통과, terser 최소화 성공.
> - 검은 깃발·흰 깃발 단독은 깃발 시퀀스가 아니라 일반 이모지라 기존대로 교체하지 않는다(테스트로 고정).
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-PR-63451-FLAG-FALLBACK.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --candidate <본문 파일>
> ```

## Title

Emoji: Replace subdivision and transgender flags when only the flag test fails

## Body (GitHub Markdown — paste as is)

When a browser passes the `emoji` support test but fails the `flag` test, `settings.supports.everythingExceptFlag` is true and `wp-emoji.js` replaces only what matches its flag list: country flags (regional indicator pairs) and the rainbow and pirate flags. The `flag` test itself checks three sequences, the transgender flag, the Sark flag and the England flag, and the first and last of those are not on the list. So on a system that fails the test because of them, England, Scotland, Wales and the transgender flag are left to a font that cannot draw them; England shows as a plain black flag. That is the report in [Core Trac #63451](https://core.trac.wordpress.org/ticket/63451).

This adds the two missing kinds to the list:

- subdivision flags, as Twemoji passes them to the callback: a black flag, tag characters and a cancel tag (`1f3f4-e0067-e0062-e0065-e006e-e0067-e007f` for England);
- the transgender flag, `1f3f3-fe0f-200d-26a7-fe0f`.

A black or white flag on its own is not a flag sequence and is still left as text, as before.

The bug is hidden at the moment because [Core Trac #66104](https://core.trac.wordpress.org/ticket/66104) makes the `emoji` test fail in every browser, so everything is replaced. Once Core Trac #66104 is committed, browsers that draw Emoji 17 but not these flags return to `everythingExceptFlag`, and the report comes back.

Testing: a new QUnit page, `tests/qunit/wp-includes/js/emoji-flags.html`, loads `wp-emoji.js` with `everythingExceptFlag` and checks, through `wp.emoji.parse()`, that country, England, Scotland, Wales, transgender, rainbow and pirate flags each become one image with the expected code, and that a non-flag emoji and a lone black flag stay text. With trunk's `wp-emoji.js`, 8 of its 16 assertions fail (England, Scotland, Wales and transgender); with this change all pass. The page is separate from the `emoji.html` page proposed in #13568 for [Core Trac #66120](https://core.trac.wordpress.org/ticket/66120), so the two pull requests do not touch the same test file. `jshint` passes on both changed scripts.

Trac ticket: https://core.trac.wordpress.org/ticket/63451

## Use of AI Tools

This pull request was prepared with Claude Code (Anthropic). It read the flag test and replacement list, confirmed the icon codes by running Core's `twemoji.js`, wrote the fix and the QUnit tests, and ran the tests and lint checks locally.

<!-- end of body -->
