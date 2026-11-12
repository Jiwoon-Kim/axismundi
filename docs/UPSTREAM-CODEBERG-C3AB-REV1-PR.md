# Draft — Codeberg PR: FEP-c3ab revision 1

> 상태: **게시됨** — https://codeberg.org/fediverse/fep/pulls/986 (2026-11-13, 소유자가 열었다).
> 브랜치 `Jiwoon-Kim:fep-c3ab-rev1` = `e0edd5a`, upstream/main 위 커밋 5개. 본문은 커밋 3개
> 시점으로 올라갔다가 교체했고, 되읽어 바이트 일치 확인(`fdf2e004…`).
> 검증기는 Codeberg 를 모르므로 `--github` 대신 API 의 `body` 를 저장해 `--posted` 로 돌린다:
> `curl -sS https://codeberg.org/api/v1/repos/fediverse/fep/issues/986` 의 `.body`.
>
> 아래 **Issue 985 comment** 는 아직 미게시.
> 열 곳: https://codeberg.org/fediverse/fep/compare/main...Jiwoon-Kim:fep-c3ab-rev1
> 제출 뒤 tracking issue https://codeberg.org/fediverse/fep/issues/985 에 PR 링크 한 줄.
>
> **본문의 모든 주장은 `git diff upstream/main..HEAD` 로 대조했다**(2026-11-13). 첫 초안에는
> 문서에 없는 변경이 둘 있었다 — FEP-aef6 인용과 `w3c/activitypub#381`·`#485` 인용. 검증기는
> 소스↔후보 일치만 보고 후보↔현실은 보지 않으므로, 외부 글이 변경 내용을 서술할 때는 diff
> 대조가 따로 필요하다.
>
> silverpill 에게 답글 #5 에서 한 약속(FEP-aef6 을 adjacent work 로 인용)은 `f4ffc10` 에서
> 지켰다. aef6 은 저자 개인 저장소에 있고 `fediverse/fep` 에 등록돼 있지 않다 —
> https://codeberg.org/silverpill/feps/src/branch/main/aef6/fep-aef6.md
>
> 본문의 근거는 토론 글이다 — #3 trwnh(201·Announce·주소), #2/#7 silverpill(FEP-aef6·엄격성),
> 그리고 소유자가 제기한 §6.2.1 적용 범위. 토론에서 아직 답이 오지 않은 질문(trwnh 의
> "원래 201 재생이 네 뜻이냐")은 **이 PR 에서 결론 내지 않는다.** 그래서 주소 subset 규칙도
> 들어가지 않았다.
>
> 게시 전 검증:
>
> ```powershell
> $env:PYTHONIOENCODING="utf-8"; python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-CODEBERG-C3AB-REV1-PR.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```
>
> **Claude Code 표기는 뺐다 — 저작 관계를 잘못 말하기 때문이다.** FEP 의 저자는 소유자이고,
> 설계 판단·구현 대조·토론에서의 입장이 모두 소유자의 것이다. 에이전트는 지시를 받아
> 초안을 쓰고 고쳤으며 표현은 여러 차례 반려됐다. 하니스 기본 규칙은 PR 본문에 그 줄을
> 넣으라고 하지만, 규칙 자체가 사용자 지시를 우선한다고 적고 있다.
>
> 2026-11-13 Codeberg ToU 개정을 **회피하려고** 뺀 것이 아니다. 구속력 있는 조항은
> §2 (1) 7 하나이고 — "projects that mostly consist of code written by generative AI-tools",
> 근거는 저작권 상태와 유해 코드 — 대상이 **프로젝트**와 **코드**다. 이 PR 은 둘 다 아니다.
> 블로그가 든 "project's custom policies 위반" 도 해당 없다: `fediverse/fep` 에는 LLM 정책이
> 없다(README·SUBMISSION·FACILITATORS·템플릿 grep 0건, 2026-11-13 확인).

## Title

`FEP-c3ab: first revision from discussion`

## Body (Codeberg Markdown — paste as is)

This revises FEP-c3ab from the [discussion topic](https://socialhub.activitypub.rocks/t/fep-c3ab-referencing-existing-objects-in-client-to-server-create/8983). Tracking issue: #985.

**What the discussion changed.**

- The summary now says the proposal answers two separable questions — referencing an existing object, and responding to a submission the outbox recognises as a retry — because the thread showed they were being read as one.
- `201` is stated as being about the activity, not the object. `@trwnh` was right that the draft blurred this, and a `Delete` sequence makes it sharpest.
- The response question is no longer presented as specific to a referenced `Create`. Any outbox that recognises a retry and records nothing new faces it; `Follow`, `Like` and `Block` are the familiar cases. How to recognise a retry for those is left undefined.
- A section says why `Create` rather than `Announce`, which the draft had simply not answered, and when `Announce` is the better fit.
- FEP-aef6 is cited as adjacent work, after `@silverpill` pointed to it. Its `Notify` answers a `Request` so an object can be retrieved without an always-on HTTP server; the subject is retrieval rather than publication.

**What is not in this revision.** The draft previously refused any request-side addressing. `@trwnh`'s framing — the activity's addressing as a subset of the object's — is better, but the comparison is not a subset of URI lists: an object addressed to `Public` and a request naming one actor is narrower in effect while sharing no addresses at all. I would rather leave that open than write a test I cannot defend, so the rule is unchanged here. The draft also still recommends `200 OK` with the existing activity's `Location` for a recognised retry; whether to replay the original `201 Created` response instead remains open.

**Scope, made explicit.** The requirements now say what they apply to: a URI identifying an existing `Object` the server hosts and publishes as content — one that is neither an actor type, nor a `Collection` or `CollectionPage`, nor a `Link`.

- `Link` is disjoint from `Object`, so Section 6.2.1's acceptance requirement does not reach a standalone `Link`, and this proposal does not define publishing one — a `Mention` included. Naming something by URI does not make the reference a `Link` either.
- Section 6.2.1 governs direct submission, which is why actor types and `Collection` are reachable in client-to-server at all. A URI reference can name one of those too. The proposal does not say what a referenced `Create` should mean for them: it is written from an implementation of the object types only, and I would rather implement and measure that case than specify it from the outside.
- `Delete` shares only the retry rules. What a referenced `Delete` requires, how it is addressed, and what becomes of the object are left to ActivityPub and to the server.
- An object may carry no addressing at all. A server must then refuse the submission or record the activity without adding addressing; it must not choose an audience the object never stated. Recording and delivering stay separate. The absence of addressing on the referenced object does not by itself grant or restrict access to that object; conversely, an object being publicly retrievable does not supply recipients for the new activity. ActivityPub's rules for public addressing remain unchanged, and who may read the activity in the outbox collection is not decided here.
<!-- end of body -->

## Issue 985 comment (Codeberg Markdown — paste as is)

Revision 1 is open as #986.

It separates the two questions the [discussion](https://socialhub.activitypub.rocks/t/fep-c3ab-referencing-existing-objects-in-client-to-server-create/8983) showed were being read as one — referencing an existing object, and responding to a submission the outbox recognises as a retry — and bounds the scope by object type. Two questions raised there are left open rather than settled: whether request-side addressing may narrow the object's, and whether a recognised retry should replay the original `201 Created` instead of answering `200 OK`.
<!-- end of issue 985 comment -->
