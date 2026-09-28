# Draft — Gutenberg #83462 리뷰 답글 (effective family cascade)

> 상태: **게시됨(2026-09-28).**
> [#83462 issuecomment-5864252999](https://github.com/WordPress/gutenberg/pull/83462#issuecomment-5864252999)
> (jahirulwp의 2026-09-27 리뷰에 대한 답글). 게시 후 `--github` 재검증 통과.
>
> **왜 지금 하나:** #83159·#13789 머지를 기다리지 않는다. #83462는 그 둘과 **의존 관계가 없다** —
> #83462의 한 줄 수정은 이미 #83159에 조상으로 실려 있고 rebase 때 같은 패치로 사라진다. effective
> family 작업 자체는 그 머지와 무관하게 남는다. 반면 리뷰어는 **지금** 붙어 있고 직접 PR을 열겠다고
>했다. 리뷰 동력은 상하기 쉬운 자원이라 이쪽이 먼저다.
>
> **직접 구현하지 않고 branch PR을 요청하는 이유:** 이미 2,411개 테스트까지 돌린 구현을 다시 만들면
> 그 검증이 버려지고 작성자 credit도 사라진다.
>
> **확인한 기술 전제(2026-09-28, `a215bb9f9d`):**
>
> ```txt
> getElementLayers( blockName, headingLevel )   inherited-value-context.js:120, 비공개
> useResolvedStyle()가 내부에서 호출(:245)
> headingLevel 생략 → falsy → [ 'heading' ]      Blocks 화면에 맞는 답
> global-styles/index.js에서 export 안 됨        → 리뷰어 말대로 노출 필요
> ```
>
> 리뷰어 스니펫이 인자 하나만 넘기는 게 빠뜨린 게 아니라 의도대로다. 답글에 그 점을 적어 두면
> 다음 읽는 사람이 "인자 누락"으로 오해해 고치지 않는다.
>
> **범위를 넓히지 않는다:** `styleToAttributes()`의 non-preset inline family 문제는 이 PR 이전부터
> 있었고 다른 write path라 별도 후속으로 남긴다.
>
> **두 번 틀리고 세 번째에 맞춘 기록:**
>
> 1. 처음엔 "TT4가 `var()` 형태라 루트 조회가 실패한다"고 썼다. **TT5(slug 형태)도 똑같이 built-in
>    전체**라서 반례가 됐다. 테마 형태 문제가 아니었다.
> 2. 다음엔 "#83462의 `screen-block.tsx` 변경이 내 빌드에 없다"고 봤다. 이것도 틀렸다 —
>    **#83159도 그 파일을 27줄 건드린다.**
> 3. 실제 이유: 같은 파일이지만 **다른 패널**이다.
>
> ```txt
> #83159  rootFontFamily → StylesFontVariationsPanel   Font variations 패널
> #83462  rootFontFamily → typography panel            Style·Weight·Width capability 조회
> ```
>
> #83159의 `rootFontFamily`는 Font variations 패널로만 들어가고 Weight 목록을 만드는 조회에는 닿지
> 않는다. 그래서 모든 테마가 built-in 전체를 준다. **측정은 유효했고 내가 붙인 이유가 틀렸다.**
>
> **따라서 "#83159 머지를 기다린다"는 선택지는 없다.** 두 PR이 같은 파일의 다른 패널을 고치므로
> #83159가 들어가도 Blocks 화면의 Weight는 그대로다.
>
> **답글에서 뺀 것:** "TT4·TT5 모두 Blocks 화면이 built-in 목록으로 끝난다(Paragraph 포함)"는 측정.
> #83462의 typography-panel 변경이 **없는** 빌드에서 나온 수치라, #83462 답글에 쓰면 "네 패치가 실제
> 테마에서 실패한다"로 읽힌다. 사실이 아니다. 테마가 두 표현을 쓴다는 **정적 조사**만 남겼다 —
> 그건 빌드와 무관하고 테스트 커버리지 요청의 근거로 충분하다.
>
> **환경 원복 완료(2026-09-28):** 활성 테마 `gutenberg-test-themes/twentytwentyone`. 게시 전에 되돌렸다.
>
> **살아남은 실측(현재 빌드에서 유효):**
>
> ```txt
> TT4 활성, Styles > Typography > Headings
>   Font Cardo · Weight Default·Regular·Bold · Style Default·Normal·Italic
> ```
>
> element 화면은 TT4의 `var(--wp--preset--font-family--heading)` 형태를 **오늘 이미 해석한다.**
> 그래서 `var()`는 위험이 아니라 오히려 기계가 그 형태를 다룬다는 증거다. 답글엔 "확인해 보라"는
> 질문으로만 남겼다.
>
> **테마 조사(정적, 빌드 무관):**
>
> ```txt
> twentytwentyfive   root=slug    heading=-       axismundi  root=slug  heading=slug
> twentytwentyfour   root=var()   heading=var()   omphalos   root=-     heading=-
> twentytwentythree  root=var()   heading=-
> ```
>
> **중간 정정 2:** element 화면 Style을 처음엔 `Default·Normal`뿐이라고 읽었는데 드롭다운이 열리기 전
> 상태였다. 다시 재니 Italic도 있다.
>
> **환경 원복 필요:** 측정하려고 활성 테마를 `gutenberg-test-themes/twentytwentyone` → `twentytwentyfour`로
> 바꿨다. e2e가 TT21을 쓰므로 되돌릴 것.
>
> 게시 전 검증 + 게시 후 `--github` 대조.

## Body (GitHub Markdown — paste as is)

Thank you — this is the case I could not find, and the Twenty Twenty-Four setup is what makes it obvious. It also answers the A/B/C question, in favour of B: the family the text is actually drawn in, resolved the same way the inspector resolves it, so that the Blocks screen and the block inspector offer the same weights for the same block.

Could you open a pull request against this branch with the `resolveStyle()` and `getElementLayers()` change and the browser test? I would rather review yours than rewrite what you have already tested. I will run the manual editor checks alongside it.

One thing worth a case, since your test models Twenty Twenty-Four rather than running it: the shipped themes do not agree on how a family is written into `styles`. Twenty Twenty-Five uses `var:preset|font-family|manrope`, while Twenty Twenty-Four and Twenty Twenty-Three use `var(--wp--preset--font-family--heading)`. It would be worth having both forms in the test rather than whichever one the modeled config happens to use.

That the element screen already handles the second form is a point for `resolveStyle()`: on a real Twenty Twenty-Four, Styles &gt; Typography &gt; Headings shows Cardo and offers its Regular and Bold and the italic it has a face for.

One note for whoever reads the diff after us: passing only the block name to `getElementLayers()` is deliberate rather than an omission. Its second parameter is a resolved heading level, and leaving it out returns `[ 'heading' ]`, which is what the Blocks screen wants, since that screen is about a block type rather than a particular heading in a document.

On the `styleToAttributes()` note: I agree, and I would keep it out of this pull request. It predates this branch and it is a different write path, so it deserves its own issue rather than being folded into a change about what the panel reads.

<!-- end of body -->
