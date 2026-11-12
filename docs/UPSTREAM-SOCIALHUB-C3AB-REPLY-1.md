# Draft — SocialHub replies (FEP-c3ab topic)

> 상태: **게시됨** 2026-11-13 — B=#5(silverpill), A=#6(trwnh).
> 되읽기 일치: B `b303977e…`, A `0b0325fd…`. Discourse 가 고쳐 쓴 흔적 없음.
> 대상: https://socialhub.activitypub.rocks/t/fep-c3ab-referencing-existing-objects-in-client-to-server-create/8983
>
> **답글을 둘로 나눈다.** 하나로 묶으면 950단어 한 덩어리가 되고, Discourse 는 글마다
> 답글 대상이 표시되므로 각자 자기 논점만 보는 편이 낫다. Steve Bate(#4)는 trwnh 의
> Announce 에 +1 한 것이라 A 안에서 받는다.
>
> ```txt
> A → #3 trwnh    201 / 일반화 / Announce / 주소 / #662·#438
> B → #2 silverpill  Notify(FEP-aef6) / "implementation detail"
> ```
>
> 입장 정리:
>
> ```txt
> 201        전제(201 은 활동)는 공유한다. 근거는 RFC 9110 §15.3 — 아무것도 만들지 않은
>            요청에 201 은 거짓 진술. 입장은 유지하되 "원래 201 재생이 네 뜻이냐" 만 묻는다.
>            결론을 먼저 내지 않는다.
> 일반화     응답 질문은 활동 타입과 무관하다 — 재시도로 판정해 새 활동을 안 만들었는가가
>            기준. 중복 제거를 할지는 별개이고 #381·#485 에서 다투는 중이라 떠안지 않는다.
> Announce   본문에 왜 Create 인지가 없다. 비어 있던 자리이지 반박된 게 아니다.
>            다만 trwnh 자신이 "나중에 Create 를 보내도 된다" 고 썼다 — 우리 편.
> 주소       과했음을 인정하되 subset 규칙을 오늘 채택하지는 않는다. 비교 대상이 URI 목록이
>            아니다(Public 객체 + 단일 액터 배달이 더 좁다). 검증 방법을 같이 정하자고 연다.
> Notify     FEP-aef6 은 GET 없는 '조회'(Request→Notify) 제안이고 발행을 다루지 않는다.
>            인접 사례로만 언급.
> ```
>
> **쓰지 말 것(초안에서 뺀 자기모순 둘):**
> - "참조형 이전에는 모든 제출이 활동을 만들었다" — 거짓. Follow/Like 중복 제거가 이미 그 경우다.
> - "참조형에서는 중복 제거가 불가피하다" — FEP 본문이 카디널리티를 구현에 맡긴다고 명시한다.
>
> `Delete` 수열이 201/200 구분의 가장 선명한 예다 — 소유자 지적:
> `Delete`→201, 재시도→200, `Undo(Delete)`→201, 그 뒤 `Delete`→201(새 전이).
>
> #662 는 trwnh 가 2026-11-02 에 열었고 댓글 하나뿐. 우리를 선점하지 않고, 참조형이
> 그 질문("서버가 created 객체에 무엇을 해야 하나")의 답 하나를 제공한다.
>
> 게시 전 검증 — **본문마다 따로 돌린다**(마커 쌍이 둘):
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-SOCIALHUB-C3AB-REPLY-1.md `
>   --source-after "<해당 Body 제목 줄 전체>" `
>   --source-before "<해당 본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```
>
> 게시 후 `--posted` 로 되읽기 확인. 실패 diff 에는 `PYTHONIOENCODING=utf-8` 필요.

## Body A — reply to post 3 (trwnh)

Thank you — this is more useful than I expected, and two of the points change the draft.

**On `201`.** Agreed, and I should have been clearer: `201` refers to the activity being published, not to the object being created. A `Delete` makes it sharpest — the `201` there says a `Delete` activity was recorded, and says nothing about whether the object now answers with a `Tombstone`, a `404` or a `410`. Running that sequence out:

```
POST Delete        → 201, Location: .../activities/1
POST Delete again  → no new activity recorded
Undo(Delete)       → 201, Location: .../activities/2
POST Delete again  → 201, a new transition, Location: .../activities/3
```

The second line is the only one I am asking about. The first submission records an activity and gets `201`; the question is what to return for a POST the server has recognised as a retry, where it recorded nothing.

Section 6 requires `201 Created` for a submission without qualification, which reads naturally as long as an accepted submission records an activity. RFC 9110 defines `201` as meaning the request "has resulted in one or more new resources being created", and defines the content of a `200` for a POST as "the status of, or results obtained from, the action". So where a server accepted the submission and recorded nothing, reading Section 6 as covering that case obliges it to say something about its own request that is not true, while the honest answer is a status Section 6 does not mention. That is less a deviation than a case the requirement was not written for, and leaving it undocumented means every server that needs it invents its own answer quietly.

It is also not special to a referenced `Create`, now that I look at it. Whenever an outbox recognises a submission as a retry and records no new activity, it can answer `200` and identify the existing one — `Follow`, `Like` and `Block` are the familiar cases. Which submissions count as retries is a separate question, and a contested one, as `w3c/activitypub#381` and `#485` show; the draft proposes the response for a referenced `Create` without defining deduplication rules for anything else.

There is a practical reason to identify the existing activity rather than say nothing about it: that id is what a client needs later to address an `Undo`, which is close to what `#485` describes senders losing. A C2S response obviously cannot settle how a remote server deduplicates on its side, but it can at least tell its own client which activity is the authoritative one.

So: do you mean replaying the original `201` and `Location` for that second POST, or something else? I would rather understand the proposal before revising.

**On `Announce`.** You are right that the draft never says why not, which is a gap rather than a disagreement — and @steve_bate, this is the answer to your `/cc` as well. `Announce` is a reasonable choice when the goal is to draw attention to an object. The goal here is narrower: to record that an actor published this object to the fediverse for the first time. That record is what establishes `attributedTo` and what later `Update` and `Delete` activities refer back to — which is also why `Update` cannot be treated the same way, since the same actor may legitimately update the same object many times.

Your own observation supports the shape, incidentally: an object may be created beforehand and the `Create` sent later, with its own `published` date. That is exactly the case the draft is about. I will add a section on why `Create` here, and on when `Announce` is the better fit.

**On addressing, I was too strict.** "The addressing of the activity controls where the activity will be delivered; the addressing of the object might control who can fetch the object; the former should probably be a subset of the latter" states it better than the draft does. What I meant to prevent is a client supplying a narrower audience and the server publishing to the object's wider one, so the client believes it published narrowly when it did not. Refusing every addressing property also forbids delivering more narrowly, which I did not intend.

What I am less sure of is how a server should check it, because the comparison is not a subset of URI lists. If the object is addressed to `Public` and the request names a single actor, the delivery is narrower in effect even though that actor may appear nowhere in the object's `to`. So I would like to allow narrowing and work out the test, rather than write one into the draft today.

Lastly, `w3c/activitypub#662` looks closely related from the other direction: it asks what a server must do for an object that "is created". The referenced case may be a useful data point there, because what the server owes *the object* is nothing — no identifier is assigned and it is already persisted — while it still checks authorisation and records a new activity. Separating those two is what makes the response question appear at all.
<!-- end of body A -->

## Body B — reply to post 2 (silverpill)

Thank you — and thank you for the pointer to FEP-aef6, which I had not read.

If I understand it, `Notify` answers "deliver this object to a requester without a GET" — a reply to a `Request`, for cases where the origin cannot serve the object itself, such as an offline node or end-to-end encrypted content. That is a different question from recording a first publication, even though the shape resembles `Create`. I will cite it as adjacent work rather than as an alternative, but tell me if you meant it to cover publication too.

On rejecting request-side addressing being an implementation detail: I think you are right about the strictness, and I was treating a validation choice as though it were the rule. What I would still like written down is the invariant rather than the check — that a client cannot end up having published to a wider audience than it asked for. How strictly any given server validates against that, and how much an intentionally generic server tolerates, seems like exactly the local decision you describe.

The harder half is that I am no longer sure what the invariant should be. trwnh's framing in the next post — the activity's addressing as a subset of the object's — is better than what the draft says, but the comparison is not a subset of URI lists: an object addressed to `Public` and a request naming one actor is narrower in effect while sharing no addresses at all. I would rather leave that open for now than write a test I cannot defend.
<!-- end of body B -->
