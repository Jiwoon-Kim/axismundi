# Draft — Core Trac: the Emoji 17 support test uses a malformed string, so every emoji is replaced

> 상태: **게시됨** — [Core Trac #66104](https://core.trac.wordpress.org/ticket/66104) (2026-09-14)
>
> 등록: Core Trac `defect (bug)`, Version `6.9`, Component `Emoji`.
> PR: [WordPress/wordpress-develop#13515](https://github.com/WordPress/wordpress-develop/pull/13515) (2026-09-14, 브랜치 `fix/66104-emoji-17-support-test`, 커밋 `87b5b7c033`). 같은 브라우저에서 trunk와 이 브랜치의 `testEmojiSupports()`를 그대로 추출해 비교: trunk `{ flag: false, emoji: false }` → 브랜치 `{ flag: false, emoji: true }`. `wp-scripts lint-js`·`jshint` 통과. JS 단위 테스트는 코어에 없음.
> Trac `1FAC8` 검색은 #64318만 반환했고, 이는 관련 Twemoji 업데이트 티켓이지 이 검사 문자열 결함은 다루지 않는다.
> Severity 제안: `normal`이지만 영향이 넓다 — Emoji 17을 지원하는 브라우저에서도 모든 이모지가 `s.w.org` 이미지로 교체됨.
>
> **주의(2026-09-14):** 이 파일의 `\uXXXX` 이스케이프는 Claude Code의 Write/Edit 도구가 저장하며
> 실제 문자(ᾬ, 🫈, 🫟)로 풀어버렸다(외부 편집이 아니라 초안 작성 단계에서). Python 바이트 쓰기로 복원했다.
> **#66104 본문은 변형된 표기로 올라갔고, 사용자가 [정정 코멘트 comment:1](https://core.trac.wordpress.org/ticket/66104#comment:1)로 실제 소스 `'\uD83E\u1FAC8'` 와 올바른 `'\uD83E\uDEC8'`를 명시했다(2026-09-14). 본문은 수정하지 않음.**
>
> 확인한 사실(2026-09-14):
> - 도입: 커밋 `68ea410482` "Emoji: Update Twemoji to version `17.0.1`." `[61134]`, Fixes #64184 (2025-11-04, PR #10454).
>   이전 검사는 올바른 `'\uD83E\uDEDF'`(U+1FADF, Emoji 16). 이후 17.0.2(`875e8e8c7d`)·17.0.3(`0b4e8eb36e`, #64318) 업데이트에도 유지.
> - 영향 브랜치: `6.8` = `\uD83E\uDEDF`(정상), `6.9`·`7.0`·`7.1`·trunk = `\uD83E\u1FAC8`.
> - 배포 압축본(WP 7.1 `wp-emoji-loader.min.js`): `return!r(e,"\ud83e\u1fac8")` 그대로.
> - 문자열 분석: `'\uD83E\u1FAC8'` = 코드 포인트 3개 `U+D83E`(짝 없는 상위 서로게이트), `U+1FAC`(ᾬ), `U+0038`(`8`).
>   U+1FAC8의 UTF-16은 `\uD83E\uDEC8`.
> - 실측(Windows, Chromium 152, Core와 같은 32px 캔버스, `600 32px Arial`, `textBaseline: top`, 가운데 (16,16)):
>
> | 문자열 | 칠해진 px | 색 px | 가운데 | Core 판정 |
> |---|---|---|---|---|
> | `'\uD83E\uDEC8'` (올바른 U+1FAC8) | 503 | 443 | 색 있음 | 지원 |
> | `'\uD83E\u1FAC8'` (Core) | 478 | 0 | 비어 있음 | **미지원** |
> | U+1F600 😀 (대조) | 692 | 554 | 색 있음 | — |
> | 글리프 없는 사설 영역 문자 (대조) | 112 | 0 | 비어 있음 | — |
>
> - 결과: Core 캐시 `flag: false`, `emoji: false` → `everythingExceptFlag: false` → 페이지에 넣은 😀·🇰🇷 둘 다
>   `https://s.w.org/images/core/emoji/17.0.2/svg/` 이미지로 교체(`1f600.svg`, `1f1f0-1f1f7.svg`).
>   올바른 문자열이면 이 환경은 `flag: false`, `emoji: true` → 국기만 교체.
> - 확인 못 한 것: Firefox·Safari·macOS에서 잘못된 문자열의 가운데 픽셀(이 PC엔 Chromium만). 짝 없는 서로게이트와 ᾬ8이
>   가운데를 비우는지는 폰트·엔진에 따라 다를 수 있음 → 본문엔 측정한 환경만 적는다.
> - 중복 검색(GitHub wordpress-develop·gutenberg: `1FAC8`, `hairy creature emoji`, `everythingExceptFlag`): 해당 보고 없음.
> - 이 버그는 Font Library 메타데이터 이슈(`UPSTREAM-FONT-LIBRARY-METADATA.md`)의 이모지 사례 전제와 연결: 수정돼야 "국기만 대체" 경로가 실제로 생긴다.

---

## Summary

Emoji 17 support test uses a malformed string, so browsers that support Emoji 17 get every emoji replaced by images

## Description (Trac WikiFormatting — paste as is)

Since [61134] (#64184), `browserSupportsEmoji()` in `src/js/_enqueues/lib/emoji-loader.js` tests Emoji 17 support with:

{{{#!js
const notSupported = emojiRendersEmptyCenterPoint( context, '\uD83E\u1FAC8' );
}}}

`\u` takes exactly four hex digits, so this string is three code points: a lone high surrogate `U+D83E`, `U+1FAC` (GREEK CAPITAL LETTER OMEGA WITH DASIA AND PERISPOMENI AND PROSGEGRAMMENI) and the digit `8`. It is not U+1FAC8 HAIRY CREATURE, whose UTF-16 form is `\uD83E\uDEC8`. The same string is in the minified `wp-emoji-loader.min.js` shipped in 6.9, 7.0 and 7.1; 6.8 tested `\uD83E\uDEDF`.

The malformed string draws black text glyphs with an empty center point, so the test reports Emoji 17 as unsupported even where it is supported. With `emoji` false, `everythingExceptFlag` is false as well, and `wp-emoji.js` replaces every emoji with images, not only flags.

=== Measured

Windows 11, Chromium 152, on a WordPress 7.1 site, drawing each string the way `emojiRendersEmptyCenterPoint()` does (32px canvas, `600 32px Arial`, `textBaseline` top, pixel at 16,16):

||= String =||= Painted pixels =||= Coloured pixels =||= Center pixel =||
|| `'\uD83E\uDEC8'` (U+1FAC8) || 503 || 443 || coloured ||
|| `'\uD83E\u1FAC8'` (current test) || 478 || 0 || empty ||
|| `'\uD83D\uDE00'` (U+1F600, for comparison) || 692 || 554 || coloured ||

The browser renders U+1FAC8 as a colour emoji, but the stored support result is `{"flag":false,"emoji":false}`, and both an emoji (😀) and a flag (🇰🇷) inserted into the page were replaced with `s.w.org/images/core/emoji/17.0.2/svg/` images. With the corrected string the result would be `flag: false`, `emoji: true`, and only flags would be replaced — the case `everythingExceptFlag` exists for.

I could only measure Chromium on Windows; whether the malformed string also leaves the center empty in other engines and fonts is not verified.

=== Steps to reproduce

1. In a browser that supports Emoji 17, open any front-end page of a site on 6.9 or later.
2. In the console run `sessionStorage.getItem( 'wpEmojiSettingsSupports' )`: `emoji` is `false`.
3. Insert an emoji that the browser renders natively, for example `document.body.insertAdjacentHTML( 'beforeend', '<p>\uD83D\uDE00</p>' )`: it is replaced by an `img.emoji`.

=== Suggested fix

{{{#!diff
-			 * 0xD83E 0x1FAC8 (\uD83E\u1FAC8) == 🫈 Hairy creature.
+			 * U+1FAC8 (\uD83E\uDEC8) == 🫈 Hairy creature.
 			 *
 			 * When updating this test, please ensure that the emoji is either a single code point
 			 * or switch to using the emojiSetsRenderIdentically function and testing with a zero-width
 			 * joiner vs a zero-width space.
 			 */
-			const notSupported = emojiRendersEmptyCenterPoint( context, '\uD83E\u1FAC8' );
+			const notSupported = emojiRendersEmptyCenterPoint( context, '\uD83E\uDEC8' );
}}}

Since the support result is cached in `sessionStorage`, affected visitors pick up the fix on their next session.
