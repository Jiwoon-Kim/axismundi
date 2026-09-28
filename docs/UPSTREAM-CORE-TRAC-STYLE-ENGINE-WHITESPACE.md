# Draft — Core Trac 티켓 (Style Engine이 문자열 내부 연속 공백을 접음)

> 상태: **게시됨(2026-09-28).** [Trac #66199](https://core.trac.wordpress.org/ticket/66199), 사용자가 생성.
> wiki 문법 렌더 정상(코드 블록·링크), 본문 육안 대조 일치. `--posted` 바이트 대조는 불가 —
> Trac이 `{{{#!php`를 소비하고 `[url label]`을 링크로 바꾸므로 렌더된 출력과 소스가 다르다.
> #13789에 링크 댓글 추가: [issuecomment-5862895786](https://github.com/WordPress/wordpress-develop/pull/13789#issuecomment-5862895786).
>
> ```txt
> Type        defect (bug)
> Component   Editor
> Focuses     css
> Summary     Style Engine collapses significant whitespace inside quoted CSS strings
> ```
>
> **어떻게 발견했나:** [wordpress-develop#13789](https://github.com/WordPress/wordpress-develop/pull/13789)에
> `font-variation-settings`를 넣으면서 OpenType axis tag 문법을 구현했다. 레지스트리는 4자 미만 tag를
> trailing space로 padding하는 것을 허용하므로 `'abc '`를 받아들였는데, PHPUnit이 `declarations`와
> 컴파일된 `css`가 갈린다고 실패했다. 스펙만 읽고 구현했으면 "스펙 준수"라고 쓰면서 깨진 CSS를 냈을 것이다.
>
> **범위를 정확히:** WordPress의 OpenType 구현이 틀린 게 아니다. **Style Engine의 범용 CSS
> sanitization/serialization 경로**가 따옴표 안 의미 있는 연속 공백을 보존하지 못한다. axis tag는 그 증상이
> 드러난 첫 자리일 뿐이고, 아래 `font-family` 사례가 보여주듯 이 기능과 무관하게 이미 존재한다.
>
> **#13789의 대응은 수정이 아니다.** padding tag를 거부하는 것은 손상된 CSS를 내보내지 않으려는
> 호환성 제한(defensive limit)이다. 근본 수정은 이 티켓의 몫이다.
>
> **경계:** 티켓에서 특정 수정법을 요구하지 않는다. `wp_strip_all_tags( $value, true )`는 Style Engine이
> 내보내는 **모든** declaration이 지나는 경로라, 바꾸면 typography 밖까지 회귀 범위가 열리고
> sanitization 경로인 만큼 보안 관점 검토가 붙는다. 무엇이 깨지는지 보이고 결정은 Core에 맡긴다.
>
> **실측(2026-09-28, wordpress-develop `8c63a53cb4`, PHP 8.2 컨테이너):** 아래 두 사례 모두
> `wp_style_engine_get_styles()`를 직접 호출해 얻었다. `font-family` 사례는 이 PR의 변경과 무관하게
> 현재 trunk에서 재현된다.
>
> 게시 후 `--posted`로 대조할 것.

## Trac body (paste as is)

The Style Engine keeps a declaration's value twice: once as the value it was given, and once as the CSS it compiles. The two disagree when the value contains a run of whitespace inside a quoted string, because every value is filtered through `wp_strip_all_tags( $value, true )`, whose second argument replaces each run of `\r`, `\n`, `\t` or space with a single space.

== Reproduction ==

A font family whose name contains two spaces, which needs nothing but core:

{{{#!php
<?php
$styles = wp_style_engine_get_styles(
    array( 'typography' => array( 'fontFamily' => '"My  Font", sans-serif' ) )
);

$styles['declarations']['font-family'];  // "My  Font", sans-serif
$styles['css'];                          // font-family:"My Font", sans-serif;
}}}

The serialized CSS no longer names exactly the family that was supplied, so it may match a different family or fall back. Nothing reports that the value was changed.

== Where it happens ==

`WP_Style_Engine_CSS_Declarations::filter_declaration()`:

{{{#!php
<?php
$filtered_value = wp_strip_all_tags( $value, true );
}}}

and in `wp_strip_all_tags()`:

{{{#!php
<?php
if ( $remove_breaks ) {
    $text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
}

return trim( $text );
}}}

The helper's `$remove_breaks` parameter is documented as removing "left over line breaks and white space chars", so this behavior is consistent with its contract. It is unsafe for CSS values where whitespace inside a quoted string is data rather than formatting, and the Style Engine applies it to every declaration it writes.

== How this came up ==

While adding `font-variation-settings` in [https://github.com/WordPress/wordpress-develop/pull/13789 #13789] (Trac #66198). The [https://learn.microsoft.com/en-us/typography/opentype/spec/dvaraxisreg OpenType Design-Variation Axis Tag Registry] defines an axis tag as four bytes beginning with a letter, and allows a tag with fewer than four letters or digits to be padded with trailing spaces, so `"abc "` and `"a   "` are valid tags. Accepting them produced this:

{{{
value given:   array( 'a   ' => 12 )
declarations:  font-variation-settings: "a   " 12
compiled CSS:  font-variation-settings: "a " 12
}}}

`"a "` is a different tag from `"a   "`, so the axis is not the one that was asked for.

That pull request now refuses a padded tag. That is a limit rather than a fix: it keeps the Style Engine from writing a tag other than the one requested, and it leaves WordPress unable to carry a space-padded axis tag at all. The tags in ordinary use are four characters, so the practical cost is small, but the reason it was chosen is this ticket.

== Scope ==

This is not specific to typography. Any CSS value whose meaning depends on repeated whitespace inside a quoted string is affected, and the family-name case above needs no new feature to reproduce.

I am not proposing a particular fix here, because the call sits on the path of every declaration the Style Engine emits: narrowing it changes output well beyond typography, and it is part of a sanitization path, so it deserves to be looked at with that in mind rather than patched from inside a feature branch.

Related: Trac #66198, [https://github.com/WordPress/wordpress-develop/pull/13789 wordpress-develop#13789].

<!-- end of trac body -->
