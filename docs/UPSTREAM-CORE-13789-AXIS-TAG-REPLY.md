# Draft — wordpress-develop#13789 리뷰 답글 (axis tag 문법)

> 상태: **게시됨(2026-09-28).**
> [wordpress-develop#13789 issuecomment-5862748182](https://github.com/WordPress/wordpress-develop/pull/13789#issuecomment-5862748182)
> (robelsust의 2026-09-27 테스트 리포트에 대한 답글). 게시 후 `--github` 재검증 통과.
> 구현 커밋 `8c63a53cb4`, push 완료. 검증: probe 31케이스 + PHPUnit 517 tests/934 assertions OK + phpcs.
>
> **왜 제안을 그대로 받지 않는가:** 리뷰어는 PHP가 `'1000'` 같은 decimal-string 배열 키를 `int(1000)`으로
> 캐스팅하니 `is_string( $tag )`가 유효한 4자리 숫자 tag를 버린다고 했고, `is_int()`를 허용하거나
> `(string) $tag`로 정상화하라고 제안했다. 그런데 **`1000`은 애초에 유효한 axis tag가 아니다.**
>
> [OpenType Design-Variation Axis Tag Registry 1.9.1](https://learn.microsoft.com/en-us/typography/opentype/spec/dvaraxisreg)
> 원문 확인(2026-09-28):
>
> > Axis tags must begin with a letter (0x41 to 0x5A, 0x61 to 0x7A) and must use only letters,
> > digits (0x30 to 0x39) or space (0x20). Space characters must only occur as trailing characters
> > in tags that have fewer than four letters or digits.
>
> 즉 우리 정규식 `^[A-Za-z0-9]{4}$`는 **너무 엄격한 게 아니라 너무 느슨했다.** 제안대로 int 키를
> 받아들이면 스펙이 금지하는 값을 저장하게 된다. 방향을 뒤집어 문법을 그대로 구현했고, 그러면
> 리뷰어가 짚은 캐스팅 문제는 저절로 사라진다 — 숫자로 시작하는 tag가 없으므로.
>
> **trailing space는 결국 거부한다(테스트가 잡음).** 처음엔 스펙대로 padding을 받아들였는데, PHPUnit이
> `css`는 `"a " 12`인데 `declarations`는 `"a   " 12`로 갈린다고 실패했다. 원인은
> `WP_Style_Engine_CSS_Declarations::filter_declaration()`의 `wp_strip_all_tags( $value, true )` —
> 두 번째 인자가 `preg_replace( '/[
	 ]+/', ' ', $text )`로 **공백 연속을 하나로 접는다.**
> 모든 declaration이 지나는 경로라 이 PR에서 고칠 곳이 아니다. 요청한 것과 다른 tag를 쓰느니 거부한다.
>
> **남는 불일치(후속):** Gutenberg `schemas/json/theme.json`의 `axes[].tag` pattern이 아직
> `^[A-Za-z0-9]{4}$`다(904행). Core 쪽이 더 정확해졌으니 schema도 맞춰야 한다. Core가 schema보다
> 관대한 방향이 아니라 **좁은** 방향의 불일치이므로 보안 문제는 아니지만, 계약이 두 벌인 상태다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-CORE-13789-AXIS-TAG-REPLY.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body (GitHub Markdown — paste as is)

Thank you for running this, and for the tag observation — it found a real defect, though it turns out to point the other way.

You are right that PHP stores a decimal string array key as an integer, so `'1000' => 20` arrives as `1000 => 20` and `is_string( $tag )` refuses it. What I got wrong is treating that as a tag worth keeping. The [OpenType Design-Variation Axis Tag Registry](https://learn.microsoft.com/en-us/typography/opentype/spec/dvaraxisreg) is explicit:

> Axis tags must begin with a letter (0x41 to 0x5A, 0x61 to 0x7A) and must use only letters, digits (0x30 to 0x39) or space (0x20). Space characters must only occur as trailing characters in tags that have fewer than four letters or digits.

So `1000` is not an axis tag at all, and `^[A-Za-z0-9]{4}$` was not too strict but too loose: it accepted `1234` and `1ABC`, which no font can declare. Allowing the integer key through would have stored what the registry forbids.

I have turned the check the other way, into a validator that asks whether the tag begins with a letter and is four letters or digits, rather than counting four of anything. That covers the registered tags, such as `opsz`, and the foundry-defined ones, which the registry narrows further to uppercase letters and digits, such as `GRAD`.

The casting question then answers itself, and the code says so where the next reader will need it: a tag cannot begin with a digit, so a key made only of digits was never a tag this could accept, and nothing is lost to PHP having turned it into an integer.

One more thing came out of writing the tests, which is why the validator is not the full grammar. The registry also allows a tag of fewer than four letters or digits to be padded with trailing spaces, so I accepted `'abc '` and `'a   '` at first. The test then failed with the declaration reading `"a   " 12` and the compiled CSS reading `"a " 12`: `filter_declaration()` passes every value through `wp_strip_all_tags( $value, true )`, whose second argument collapses a run of whitespace into a single space. That is the path every declaration takes and not something to change from here, so a padded tag is refused rather than written as a different tag than the one asked for. The reason is in the docblock, next to the rule it explains.

The serializer's fixture carries both sides. Refused: `1234`, `1ABC`, `' abc'`, `'ab c'`, `'abc'`, `'abcde'`, `'abc '`, `'a   '`, alongside the value and property-owned cases that were already there. Accepted: `opsz`, `GRAD`, `A123`.

One mismatch is left over, and it is on the other side of the fence: the `axes[].tag` pattern in Gutenberg's `theme.json` schema is still `^[A-Za-z0-9]{4}$`. Core is now the narrower of the two rather than the wider, so nothing unsafe is stored, but the contract is written twice and I will bring the schema to the same grammar in the Gutenberg pull request.

## Use of AI Tools

AI assistance: Yes
Tool(s): Claude Code
Model(s): Claude Opus 5
Used for: Reading the registry's syntax requirements against the implementation, writing the validator and the test cases, and running the suite. I reviewed the reasoning and the results.
<!-- end of body -->
