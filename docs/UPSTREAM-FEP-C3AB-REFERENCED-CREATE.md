# Draft — FEP-c3ab: Referencing Existing Objects in ActivityPub Client-to-Server Create

> 상태: **머지됨, 개정 대기** (2026-11-13). `fediverse/fep` PR #984 머지, DRAFT 등록,
> 트래킹 이슈 #985. 아래 본문은 **머지된 것보다 앞서 있다** — `Why Create and not Announce`
> 절이 추가됐고 아직 PR 하지 않았다. 그래서 지금 검증기로 업스트림 파일과 대조하면
> 불일치가 정상이다.
>
> **개정 대기 목록** (토론 8983 의 답을 보고 묶어서 PR 한 번에):
>
> ```txt
> 확정   Why Create and not Announce 절        ← 이미 반영함. trwnh·Steve Bate 가 같은
>                                                제안을 했고 본문에 답이 없음을 인정했다.
> 확정   Delete·Update·Undo 범위 근거         소유자 판단(2026-11-13):
>                                              - 5.8.1 은 FEP-4f05 soft deletion 의
>                                                Undo(Delete) 까지 C2S 관점에서 포함한다.
>                                              - **C2S Update 는 멱등성을 확인할 방법이 없다**
>                                                → 범위 밖. 같은 액터가 같은 객체를 여러 번
>                                                정당하게 고칠 수 있다.
>                                              - Delete 재시도까지가 우리 범위. Undo(Delete)
>                                                이후의 재삭제는 **새 전이라 201**.
>                                              - **Undo(Create) 는 의미상 불가능**하다 —
>                                                객체가 이미 전달됐다. 되돌리는 수단은 Delete
>                                                뿐이고, 그래서 4f05 의 Undo(Delete) 가
>                                                "복원" 이지 "생성 취소" 가 아니다.
> 할일   본문에서 Create 전용 / 공유 규칙 분리  codex 지적. 귀속 보존·발행 주소 결정은
>                                              참조형 Create 전용이고, "새 활동이면 201,
>                                              확인된 재시도면 200+기존 URI" 는 Create·Delete
>                                              공유 규칙이다. AS2 §5.8 분류는 범위의 근거이지
>                                              재시도 판정 규칙 자체가 아니다.
> 확정   AS2 §5.8 로 범위 긋기                 ← 반영함. Create·Delete 는 5.8.1 Content
>                                                Management 와 5.8.10 Relationship Management
>                                                **양쪽에** 들어 있다. 그래서 "콘텐츠든 관계든"
>                                                으로 한 줄에 그어진다. 나머지(Like·Follow·
>                                                Block·Undo)는 응답 원칙만 적용 가능, 재시도
>                                                판정은 범위 밖. 섹션 제목은 "Motivations" 가
>                                                아니라 "Activity Type Motivating Use Cases".
> 확정   액터는 범위 밖 명시                    §6.2.1 이 비-Activity 객체 전부를 받으므로
>                                                Person/Group 도 문법상 대상이 된다. 참조형
>                                                Create 의 주제가 될지는 별개 질문으로 둔다.
> 확정   Summary 에 두 제안 분리 명시          ← 반영함. 토론에서 세 사람이 전부 (1)에
>                                                답하는 동안 우리는 (2)를 묻고 있었다.
>                                                (1) 기존 객체 참조 처리 (2) 재시도 응답.
>                                                (2)는 활동 타입과 무관하다는 것까지 적었다.
> 확정   published 두 시간선 분리               ← 반영함. 객체는 저작 시각, 활동은 발행 시각.
>                                                코드는 이미 그렇다(repository.php:1287).
>                                                WP 코어 선례: media.php:303 이 업로드 경로의
>                                                연월만 부모 글 날짜로 쓰고 attachment 의
>                                                post_date 는 업로드 시각으로 둔다.
> 미정   요구사항 8 (재시도 응답)              trwnh 가 "원래 201 재생" 을 뜻했는지에 달림.
>                                                재생이면 8 을 교체, 아니면 RFC 9110 근거로 유지.
> 미정   요구사항 4·5 (주소)                   좁히기는 허용해야 한다(수긍함). 검증 테스트가
>                                                URI 목록 subset 이 아니라서 아직 못 쓴다.
>                                                Public 객체 + 단일 액터 배달이 반례.
> 검토   Why this needs writing down            "deliberate departure" 보다 "요구가 쓰이지
>                                                않은 경우" 프레이밍이 강하다. 답글 #6 참조.
> 검토   Notify(FEP-aef6) 인용 위치            silverpill 이 발행까지 의도했는지 되물어 둠.
> ```
>
> **게시된 답글 #6 에 같은 오류가 하나 나가 있다.** "That record is what establishes
> `attributedTo` and what later `Update` and `Delete` activities refer back to" — 요구사항 1
> (기존 귀속을 대체·확장 금지)과 어긋나고, `Update`·`Delete` 는 와이어에서 **객체를 target**
> 하지 Create 활동을 가리키지 않는다. 정정 글을 따로 올리지는 말고, 다음 답글에서 자연스럽게
> 바로잡을 것 — 지적이 먼저 오면 그대로 수긍하면 된다.
>
> **개정 PR 절차**: 포크를 업스트림 main 으로 동기화 → 새 브랜치(`fep-c3ab-rev1`) → 이 본문으로
> 파일 교체 → 검증기 대조 → PR. 포크에 반쯤 쓴 파일을 두지 않는다.
>
> 이전 기록 — 토론 개설됨:
> https://socialhub.activitypub.rocks/t/fep-c3ab-referencing-existing-objects-in-client-to-server-create/8983
> (게시본 되읽기 일치, SHA-256 `a2582412…`). `dateReceived`·`discussionsTo` 채움. 포크의 `fep-c3ab` 브랜치에
> 커밋 후 PR 만 남았다.
>
> 슬러그 `c3ab` = `sha256("Referencing Existing Objects in ActivityPub Client-to-Server Create")[:4]`.
> 2026-11-12 기준 등록된 150개 식별자와 **충돌 없음** 확인. **제목을 바꾸면 슬러그가 바뀐다.**
>
> 제출 절차: fork → `./scripts/new_proposal.py "<제목>"`(슬러그·디렉터리·템플릿을 만들어 준다. 손으로 만들지 말 것)
> → 본문 작성 → `dateReceived` 설정 → SocialHub 토론 개설 → `discussionsTo` 채움 → PR.
>
> 본문 원칙 — 계약 ⑥의 분리를 그대로 따른다:
>
> ```txt
> 들어감(기구)   참조된 객체에 새 id 금지 / 주소는 객체에서 취하고 요청 주소 필드는 거부 /
>                해석 불가 URI 거부 / 이 요청은 기존 객체의 편집 명령이 아님 / 재시도 판정
> 안 들어감(정책) 한 URI 에 Create 하나, 권위 동결, 공동 발행 거절, 멱등 200
>                → FEP 이 강제하면 공동 발행 모델을 배제하게 된다. 200 은 구현 사례로만 언급
> ```
>
> 리뷰 반영(2026-11-12):
>
> - 기존 객체의 **귀속·주소를 편집하지 않는다**를 명시. §6.2 의 `actor → attributedTo` 복사가
>   이 경로에서 어떻게 적용되는지 적었다. 제출 권한(outbox)과 발행 권한(객체)도 분리.
> - 주소 필드가 오면 `SHOULD refuse` → **`MUST reject`**. SHOULD 로 두면 "조용히 무시하고 더 넓은
>   기존 주소로 발행" 이 허용되는데, 그게 바로 막으려던 상황이다.
> - 재제출 규칙을 **객체당 Create 개수와 분리.** 판정 기준은 "같은 제출의 재시도인가"이고,
>   공동 발행을 허용하는 서버도 같은 액터의 재시도는 중복 제거할 수 있다. 다른 액터에게 기존
>   Create 를 돌려주는 것은 금지. 새 활동은 §6대로 `201`, 확인된 재시도는 명시적 예외로 `200`.
> - 선례 서술에서 과한 주장 제거 — "the only prior art", §6.2.1 과 **충돌한다**는 표현을 뺐다.
>   MediaUpload 는 별도 업로드 엔드포인트 제안이고, 인접 사례로만 소개한다.
> - AS2 §3.1 Minimal Activity 를 **먼저** 보여주고 "문법은 이미 정의돼 있고 이 제안은 outbox 가
>   그 `object` 참조를 어떻게 다루는지를 정한다" 로 범위를 명확히 했다.
>
> 구현 표기는 **Axismundi (WordPress)** + 저장소 링크. 플러그인 조립형이라 단일 산출물 이름이 없다.
> 그리고 **"구현·측정 완료, 릴리즈 전"** — Activities 는 `0.1.6` 이 공개돼 있고 참조형
> Create(`43d0a83`)는 그 뒤 커밋이다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-FEP-C3AB-REFERENCED-CREATE.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```
>
> 실패 diff 를 보려면 이 머신에서는 `PYTHONIOENCODING=utf-8` 이 필요하다(em-dash 가 cp949 로 깨진다).

## Body (fep/c3ab/fep-c3ab.md — paste as is)

---
slug: "c3ab"
authors:
  - "Jiwoon Kim <kimjiwoon75@gmail.com>"
status: DRAFT
dateReceived: 2026-11-12
discussionsTo: https://socialhub.activitypub.rocks/t/fep-c3ab-referencing-existing-objects-in-client-to-server-create/8983
---
# FEP-c3ab: Referencing Existing Objects in ActivityPub Client-to-Server Create

## Summary

Client-to-server interactions are described for objects that are born with their activity: a client posts a `Note`, the server wraps it in a `Create`, and both are given identifiers.

Where federation is added to a content store that already exists, the order is reversed, and that is the normal case for a content management system. The store assigns an identifier, an author and an audience when the content is written; a module or plugin adds federation later; and so an object can sit there for years before any actor decides to publish it. Publishing older content, publishing selectively, and publishing under one of several actors are then ordinary requests rather than edge cases. The same is true of any server that serves the same content both as a website and to the fediverse, since the website copy exists whether or not an activity was ever recorded for it.

For those servers the client has nothing new to submit. It wants to name an object the server already hosts and ask for it to be published:

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Create",
  "actor": "https://example.org/actors/alice",
  "object": "https://example.org/articles/123"
}
```

The syntax is already defined by ActivityStreams — this is the shape of the Minimal Activity example, with both properties given as URIs rather than inlined documents. What is missing is what an outbox should do with it.

**There are two separable proposals here, and it is worth saying which is which.**

1. **Referencing an existing object.** When `Create.object` is a URI the server already hosts, what happens to the object's identifier, its existing attribution, and its addressing. This is specific to a submission that names something rather than carrying it.

2. **Responding to a retry.** When a server recognises a submission as one it has already recorded and creates no new activity, what status and `Location` it returns. This is not specific to `Create` at all: the same question arises for any submission a server deduplicates, `Follow` and `Like` among them.

The second is the one this proposal exists for, and it survives a change of activity type — if the publication were expressed as something other than a `Create`, the retry contract would read the same. The first is what makes a referenced submission well defined in the first place, so both are specified here.

Note what decides the status code. The object already existing does not make the first submission a retry: that request records a new activity and gets `201`. A later identical submission gets `200` because the *activity* already exists and this request recorded nothing — not because the object does.

The rules here are written for a URI-referenced `Create` or `Delete`, which Activity Streams lists under both Content Management and Relationship Management, so the target may be an article or a relationship without changing anything. The same response pattern can be used for other client-to-server activities — `Follow`, `Like`, `Block` and `Undo` among them — wherever a server recognises a retry and records nothing new. How to recognise a retry for those is not defined here.

The proposal does not specify how many such activities an object may have, or who may publish one. Nor does it address naming an actor: a submission may reference any object the server hosts, but whether an actor should be the subject of a referenced `Create` is a separate question, and servers generally create and publish actors by other means.

## The situation

Section 6.2.1 of ActivityPub describes submitting an object that does not exist yet, and every rule in it is written for an object the server is about to create:

> The server MUST accept a valid ActivityStreams object that isn't a subtype of Activity in the POST request to the outbox.

> The server then MUST attach this object as the object of a Create Activity.

> For non-transient objects, the server MUST attach an id to both the wrapping Create and its wrapped Object.

It does not describe naming an object the server already hosts, and it does not say what a server should do with an identifier the object already has. The same gap is recorded in `w3c/activitypub#438`, which asks what a server should do with an inner object's `id`.

An adjacent case is the `MediaUpload` proposal, where an object is created through a separate endpoint and the server may afterwards wrap it in a `Create`. It shows that an object preceding its activity is not a new idea, though it addresses a different endpoint and leaves the wrapping optional.

## Why this needs writing down

No existing proposal covers it. Of the client-to-server proposals, FEP-6606 addresses collection addressing, FEP-d8c2 an OAuth profile, FEP-ae97 client-side signing and FEP-65ca client configuration; none concerns what an outbox does with a submitted activity's `object`. The nearest by name is FEP-cd47, which collects addressing and deduplication use cases, but its subject is recognising the same actor or content across locations rather than recognising a repeated submission.

The gap has been visible for some time without closing. `MediaUpload` reached for the same shape in 2017 and has not been edited since; `w3c/activitypub#438` has been open since 2024.

The proposal uses the existing ActivityStreams roles of [actor and object in a Minimal Activity][AS2-Minimal]. It preserves the object's [global identifier][AP-Identifiers] and follows the distinction between attribution and addressing in [ActivityPub Section 6.2][AP-Create]. If a server uses a Create record to register publication of an object URI, returning that record on a retry preserves the registration rather than creating a second one. The global identifier requirement does not itself mandate one Create per object.

The explicit departure from an ActivityPub MUST is the response to a recognised retry: [Section 6][AP-C2S] requires `201 Created`, but this proposal recommends `200 OK` when no new activity is recorded. Documenting that exception gives servers and clients an agreed way to distinguish a new activity from a replay.

## Requirements

A server MAY accept a `Create` activity whose `object` is a string URI. When it does:

1. **The request is a request to publish, not to edit.** The server MUST NOT alter the referenced object's content, attribution or addressing in order to satisfy it. Section 6.2 recommends copying a `Create`'s `actor` onto the object's `attributedTo`; on this path that recommendation is satisfied only where the object carries no attribution yet. A server MUST NOT use it to replace or extend attribution the object already carries.

2. **Identifiers.** The server MUST NOT assign a new identifier to the referenced object. The requirement to attach an identifier applies to objects the server creates, not to objects it is asked to name. The new `Create` activity is itself a new object and receives an identifier as usual.

3. **Two permissions, checked separately.** The authenticated request must be permitted to submit to that outbox, and the outbox's actor must be permitted to publish that object. A server MUST refuse when either is absent, and MUST NOT treat the first as implying the second.

4. **Addressing.** The server MUST take the activity's addressing from the addressing the referenced object already carries. Recipients are then computed from that addressing by the ordinary delivery rules; this proposal does not change them.

5. **Addressing in the request is rejected, not ignored.** If the request carries `to`, `cc`, `bto`, `bcc` or `audience`, the server MUST reject the submission. Ignoring those properties and publishing to the object's existing, possibly wider audience would let a client believe it had published narrowly.

6. **Unresolvable references.** If the URI does not resolve to an object the server hosts, the server MUST refuse the submission. It MUST NOT create an object to satisfy the reference.

7. **Repeated submissions are idempotent.** When an authorised submission repeats the same publication transition for the same actor and object, the server SHOULD return the previously recorded activity without recording or delivering another. A server MUST NOT answer one actor's submission with an activity recorded for another. A reference identifies the object; if the server permits more than one publication by the same actor, the reference alone does not distinguish a retry from a new publication.

8. **Responding to a retry.** Where a new activity is recorded the response is `201 Created` with its identifier in the `Location` header, as for any other submission. Where none is recorded, `201 Created` would assert a creation that did not happen; a server SHOULD answer `200 OK` instead, with the existing activity's identifier in `Location`. Section 6 requires `201 Created` for submissions without qualification, so this is a deliberate departure, and it is confined to the case where the server has determined that it created nothing.

The same replay rule applies to a URI-referenced [Delete][AP-Delete]: a retry of the same effective deletion SHOULD return the existing Delete without recording or delivering another. [FEP-4f05][FEP-4f05] describes restoring a soft-deleted object using `Undo(Delete)`, not a new Create. A deletion after restoration can be a new transition and receive `201 Created`; a delayed retry of the earlier deletion must not be mistaken for that new transition. Servers that support both need a way to distinguish them, such as a request identifier or a lifecycle precondition.

## Why `Create` and not `Announce`

Announcing an object's existence and recording its first federated publication are different purposes, and neither is always the better one. `Announce` serves the first. This proposal serves the second: the `Create` records that an actor published the object, without replacing the attribution the object already carries. A server can use that record to track publication history, while later `Update` and `Delete` activities still target the object itself.

Nothing about this requires the object to be new. An object may be written long beforehand and the `Create` sent later; that is the ordinary case for a content store that predates its federation. The two carry different times and should keep them: the object keeps the `published` date it was written with, while the activity carries the time the publication actually happened. Backdating the activity to the object's date would assert that a publication occurred when it did not, and would bury the activity for receivers that order by it.

The same reasoning is why `Update` is not treated this way. The same actor may legitimately update the same object many times, so a repeated `Update` is not a retry, and the question this proposal answers does not arise for it.

## What this does not specify

How many `Create` activities one object may have, and who may publish them, are left to the implementation. A server may record exactly one and treat the actor of that activity as the object's publishing authority; a server may instead accept a `Create` from each of several actors and treat the object as jointly published. Both remain compatible with the requirements above, which concern a single submission in isolation.

Deletion and restoration semantics beyond the replay rule, re-use of an identifier after deletion, and the relationship between a referenced `Create` and later `Update` activities are out of scope.

## Implementation notes

Axismundi records at most one `Create` per object and treats its actor as that object's publishing authority. That constraint is its own, not part of this proposal, and it is what makes every repeat submission by the same actor a retry: there is never a second creation to record. A submission naming an object another actor created is refused as an authority violation rather than answered idempotently, which is the distinction requirement 7 draws.

A request that carries addressing is answered with `400`, and a URI that does not resolve to a local object with `404`, so that a client can tell a malformed submission from one naming something the server does not have.

Only `Create` is implemented. A referenced `Delete` follows the same shape but has not been built.

## Implementations

- Axismundi (WordPress), a set of plugins rather than a single package: https://github.com/Jiwoon-Kim/axismundi — implemented and covered by its test suite, not yet in a released version.

## References

- Christine Lemmer-Webber, Jessica Tallon, Erin Shepherd, Amy Guy, Evan Prodromou, [ActivityPub], 2018
- James M Snell, Evan Prodromou, [Activity Streams 2.0][AS2], 2017
- [C2S Create activity has unspecified behavior wrt assigning an ID to the inner object][issue438], 2024
- SocialCG, [ActivityPub MediaUpload][MediaUpload], 2017
- [FEP-4f05: Soft Deletion][FEP-4f05]

[ActivityPub]: https://www.w3.org/TR/activitypub/
[AP-Identifiers]: https://www.w3.org/TR/activitypub/#obj-id
[AP-C2S]: https://www.w3.org/TR/activitypub/#client-to-server-interactions
[AP-Create]: https://www.w3.org/TR/activitypub/#create-activity-outbox
[AP-Delete]: https://www.w3.org/TR/activitypub/#delete-activity-outbox
[AS2]: https://www.w3.org/TR/activitystreams-core/
[AS2-Minimal]: https://www.w3.org/TR/activitystreams-core/#example-1
[FEP-4f05]: https://codeberg.org/fediverse/fep/src/branch/main/fep/4f05/fep-4f05.md
[issue438]: https://github.com/w3c/activitypub/issues/438
[MediaUpload]: https://www.w3.org/wiki/SocialCG/ActivityPub/MediaUpload

## Copyright

CC0 1.0 Universal (CC0 1.0) Public Domain Dedication

To the extent possible under law, the authors of this Fediverse Enhancement Proposal have waived all copyright and related or neighboring rights to this work.
<!-- end of body -->
