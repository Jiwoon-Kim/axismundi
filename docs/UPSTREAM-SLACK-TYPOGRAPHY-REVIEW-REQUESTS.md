# Draft — WordPress Slack 리뷰 요청 2건 (typography capability 작업)

> 상태: **초안, 미게시.** 올릴 곳: WordPress Slack `#core-editor`, `#core`.
> **사용자가 직접 올려야 한다** — 이 세션의 Slack 커넥터가 미인증이고 비대화형이라 OAuth를 돌릴 수 없다.
>
> **형식:** Slack mrkdwn이다. `*굵게*`(별 하나), `_기울임_`, `` `코드` ``, 링크는 `<URL|라벨>`.
> Markdown의 `**굵게**`는 Slack에서 별이 그대로 보인다.
>
> **길이:** 채널 메시지는 짧아야 읽힌다. 각 3~4문단, 링크는 처음 등장할 때만.
>
> **사실 확인(2026-09-28):**
>
> ```txt
> gutenberg#83159        OPEN  head 7ef8196980  CI 78 pass / 12 skip / 0 fail  MERGEABLE
> wordpress-develop#13789 OPEN  head 8c63a53cb4  118 pass / 22 skip / 1 fail
>                         실패 1건 = Tests_Comment::…notify_postauthor…link_to_parent
>                         (multisite+memcached, 27,285개 중 1개, typography 무관)
> Trac #66198            Core contract 티켓
> Trac #66199            Style Engine 공백 접힘 (별개 defect)
> ```
>
> **`[Type]` 라벨을 요청하는 이유:** 외부 기여자는 라벨을 붙일 수 없다(`AddLabelsToLabelable` 거부).
> 봇이 이미 라벨 없다고 코멘트를 달아 둔 상태다.
>
> **하지 말 것:** #13789의 CI 실패를 먼저 꺼내지 않는다. 물어보면 답하면 되고, 먼저 변명처럼 쓰면
> 실제보다 커 보인다. schema가 머지 커밋에서 빠졌다가 다음 커밋에 들어간 경위도 쓰지 않는다 —
> 최종 head가 일치하고 커밋 이력이 설명한다.
>
> 게시 후 붙여넣은 텍스트를 주면 `--posted`로 대조한다.

## #core-editor body (paste as is)

Hi — I'd like a review on <https://github.com/WordPress/gutenberg/pull/83159|#83159>, which proposes one model for the typography panel: the faces of the family the text is drawn in decide what Style, Weight and Width offer, text layout stays a block's own policy, and the axes no CSS property owns get a Font variations panel of their own.

It began as the Font variations panel and grew, because the same question was being answered separately in each control. Building the width control is what showed the cost: a static family with a `normal` and a `condensed` file was offered all nine widths the property names, plus a slider between them, since one helper counted the widths declared and another returned a range only where a face declared one. Reading the faces once fixes that, and the same reading is what Style, Weight and Width now use.

The body has a short table of what is in the PR and what is not, which is the part worth reading first for a change this size. The one behaviour change to look at closely: for a family that declares faces, synthesized weights and italics are no longer added to these lists; where a family declares none, the lists are what they have always been. The design discussion is in <https://github.com/WordPress/gutenberg/issues/83148|#83148>.

The Core side is separate and already up: <https://core.trac.wordpress.org/ticket/66198|Trac #66198> and <https://github.com/WordPress/wordpress-develop/pull/13789|wordpress-develop#13789>.

Could someone also add `[Type] Enhancement`? I can't set labels on the repo myself, which is what the bot comment is about.

<!-- end of core-editor body -->

## #core body (paste as is)

Hi — <https://core.trac.wordpress.org/ticket/66198|#66198> and its PR <https://github.com/WordPress/wordpress-develop/pull/13789|wordpress-develop#13789> are ready for review. It's a narrow one: the Core-side contract for two typography style properties, so that editor work happening in Gutenberg has somewhere to land.

It adds `styles.typography.fontStretch` and `styles.typography.fontVariationSettings`, the settings that go with them, `axes` on a font face, `font-variation-settings` to what `safecss_filter_attr()` allows, and the two block supports. No block opts in here. The variation settings value is an object keyed by axis tag rather than a string, so the theme.json origins can merge per axis; which tags may be written is decided in `WP_Style_Engine`, and `WP_Theme_JSON` asks it rather than keeping a second copy.

Thanks to @robelsust for testing it and for spotting that the tag check refused numeric array keys. It turned out to point the other way: the OpenType registry requires an axis tag to begin with a letter, so the check was too loose rather than too strict, and it now holds the grammar instead of counting four characters.

Writing those tests turned up something separate, which I've filed as <https://core.trac.wordpress.org/ticket/66199|#66199>: the Style Engine collapses runs of whitespace inside quoted CSS strings, so a font family named `"My  Font"` is stored with both spaces and serialized with one. It needs no new feature to reproduce and I'm not proposing a fix in the ticket, since the call is on the path of every declaration the engine writes.

<!-- end of core body -->
