# Draft — Core Trac comments for C1 (#58663) and C3 (#63451)

> 상태: **초안, 게시 안 함** (2026-09-17). Trac은 로그인이 필요해 사용자가 게시한다.
>
> 결정(사용자, 2026-09-17): Gutenberg #83032를 연 뒤, 기존 티켓이 있는 후보에는 댓글을 단다.
> - C1 → #58663(열림): 만료가 1주라는 전제가 실제로는 약 10분이라는 사실 보강.
> - C3 → #63451(사용자 본인 티켓, `close` 키워드): 원인 설명과 #66104 수정의 부작용.
> - C2(#52219 닫힘)·C5(티켓 없음)는 새 티켓 몫, C4는 #83032로 충분 — 이 파일에서 다루지 않음.
>
> 형식: Trac WikiFormatting(Markdown 아님). `#NNNNN`은 Trac 티켓으로 자동 링크, 외부 링크는 `[URL 텍스트]`.
>
> 게시 전 검증(각 댓글):
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-TRAC-EMOJI-COMMENTS.md `
>   --source-after "<해당 댓글 제목 줄 전체>" `
>   --source-before "<다음 제목 줄 또는 파일 끝 마커>" `
>   --candidate <붙여 넣을 파일>
> ```
>
> 게시 후: 화면의 댓글을 복사해 `--posted`로 대조하고, 여기에 comment 번호를 기록.
>
> ## 근거
>
> - C1: trunk `src/js/_enqueues/lib/emoji-loader.js` 82행(`item.timestamp + 604800`, 주석 "a week in seconds"), 105행(`timestamp: new Date().valueOf()`). Playground에서 읽은 저장값 `timestamp: 1789569446113`(밀리초). 11분 뒤 재검사는 **측정하지 않음** — 소스 판독으로만 씀.
> - #63451 댓글 번호(2026-09-17 확인): comment:4 = peterwilsoncc 재현 보고(2025-08-12, 6.9-alpha, Twemoji로 교체됨), comment:5 = 사용자 "해결된 듯"(2025-08-14, 6.8.2 이후), comment:8 = swissspidy `close`.
> - C3: trunk `emoji-loader.js` 230·247·264행(`flag` 검사 셋), `wp/emoji.js` 238–240행(`everythingExceptFlag` 교체 목록). wordpress-develop 태그: 6.8.1 Emoji 15.1, 6.8.2 Emoji 16.0. 재현: #66104 브랜치 loader + Core `wp-emoji.js`/`twemoji.js` 하네스, `supports = { flag: false, emoji: true, everything: false, everythingExceptFlag: true }` → 잉글랜드 `img` 0, 검은 깃발 표시, 같은 페이지 🇰🇷은 이미지. 해결 시점 원인은 **추정**(당시 Windows의 Emoji 16 지원 미측정).
> - 교체 목록에 넣을 정확한 Twemoji 코드: 잉글랜드는 사용자 comment:5의 이미지 URL `1f3f4-e0067-e0062-e0065-e006e-e0067-e007f`로 확인. 트랜스젠더 코드는 확인하지 않아 댓글에 적지 않음.

## Comment on #58663 (Trac WikiFormatting — paste as is)

A detail that changes the premise here: the cache does not last a week today. In `getSessionSupportTests()` in `src/js/_enqueues/lib/emoji-loader.js`:

{{{
new Date().valueOf() < item.timestamp + 604800 && // Note: Number is a week in seconds.
}}}

`item.timestamp` is written by `setSessionSupportTests()` as `new Date().valueOf()`, which is in milliseconds, so adding `604800` gives about ten minutes. I found this by reading the source while working on emoji fallback in a plugin; I have not timed it in a browser.

So a tab that stays open re-runs the support tests roughly every ten minutes rather than once a week. If the expiry is kept, a week would be `604800000`; if this ticket removes it, the unit mismatch goes with it.

For wider context on emoji detection and fallback: [https://github.com/WordPress/gutenberg/discussions/83032 Gutenberg discussion #83032].

## Comment on #63451 (Trac WikiFormatting — paste as is)

Following up on my report with what I found in the source, since this looks likely to come back.

The `flag` support test in `emoji-loader.js` draws three sequences, and any failure makes `flag` false: the transgender flag (a ZWJ sequence), the Sark flag, and the England flag (a tag sequence). When `flag` is false but `emoji` is true (`everythingExceptFlag`), `wp-emoji.js` only replaces what matches this list:

{{{
/^1f1(?:e[6-9a-f]|f[0-9a-f])-1f1(?:e[6-9a-f]|f[0-9a-f])$/  // Country flags.
/^(1f3f3-fe0f-200d-1f308|1f3f4-200d-2620-fe0f)$/            // Rainbow and pirate flags.
}}}

England, Scotland and Wales are not on it, and neither is the transgender flag. So on a system that fails the `flag` test only because of those sequences, they are left to a font that cannot draw them, and England shows as a plain black flag. That matches the original report.

Why it stopped reproducing is likely the other test. 6.8.1 tested for Emoji 15.1, and 6.8.2 moved the test to Emoji 16.0. On a system without Emoji 16, `emoji` is false, WordPress replaces every emoji, and the subdivision flags become Twemoji images along with everything else. That would fit what I described in comment:5 and the replacement seen in comment:4. Since 6.9, #66104 makes `emoji` false everywhere, which has the same effect.

Once #66104 is fixed, browsers that draw Emoji 17 but not these flags go back to `everythingExceptFlag`. I reproduced that with the #66104 patch and Core's `wp-emoji.js` and `twemoji.js` on a local page in Chromium on Windows: the England flag was not replaced and showed as a black flag, while a country flag on the same page was replaced.

A fix would add the sequences the `flag` test checks (the subdivision tag sequences, for example `1f3f4-e0067-e0062-e0065-e006e-e0067-e007f` for England, and the transgender flag) to the list above. I'd suggest keeping this ticket open, and it may be worth considering alongside #66104.

For wider context: [https://github.com/WordPress/gutenberg/discussions/83032 Gutenberg discussion #83032].

<!-- end of comments -->
