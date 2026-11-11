# Draft — SocialHub topic: FEP-c3ab

> 상태: **게시됨** 2026-11-12 —
> https://socialhub.activitypub.rocks/t/fep-c3ab-referencing-existing-objects-in-client-to-server-create/8983
> `--posted` 되읽기 일치(SHA-256 `a2582412…`). Discourse 가 마크업을 고쳐 쓴 흔적 없음.
> [SocialHub ActivityPub 카테고리](https://socialhub.activitypub.rocks/c/activitypub/5)에 올린다 —
> FEP 저장소 README 9단계가 지목하는 곳이다. FEP 전용 카테고리(`/c/standards/fep/54`)도 활발하지만,
> README 가 안내하는 쪽을 따른다.
>
> 순서: **이 글을 먼저 게시** → URL 을 `fep-c3ab.md` 의 `discussionsTo` 에 넣고 `dateReceived` 설정
> → `git add -- fep/c3ab/fep-c3ab.md` → 포크에 푸시 → PR. 게시 시점엔 PR 링크가 없으므로,
> 글 하나만 읽고도 판단할 수 있게 썼다.
>
> 제목에 `FEP-c3ab` 를 넣는다. 그래야 나중에 PR 과 연결된다.
>
> 게시 전 검증:
>
> ```powershell
> python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-SOCIALHUB-C2S-REFERENCED-CREATE.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```
>
> 게시 후 `--posted` 로 되읽기 일치를 확인하고 상태 줄을 갱신할 것.
> 실패 diff 를 보려면 이 머신에서는 `PYTHONIOENCODING=utf-8` 이 필요하다.

## Title

`FEP-c3ab: Referencing existing objects in client-to-server Create`

## Body (Discourse Markdown — paste as is)

I have written a draft FEP and would like comment before opening the pull request.

**The situation.** Client-to-server submission is described for an object that is born with its activity: a client posts a `Note`, the server wraps it in a `Create`, and both are given identifiers. Where federation is added to a content store that already exists, the order is reversed, and that is the normal case for a content management system. The store assigns an identifier, an author and an audience when the content is written; a plugin or module adds federation later; so an object can sit there for years before any actor decides to publish it. Publishing older content, publishing selectively, and publishing under one of several actors are then ordinary requests.

For those servers the client has nothing new to submit. It wants to name something the server already hosts:

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Create",
  "actor": "https://example.org/actors/alice",
  "object": "https://example.org/articles/123"
}
```

The syntax is not new — this is the shape of the Minimal Activity example, with both properties as URIs. What is missing is what an outbox should do with it. Section 6.2.1 is written throughout for an object the server is about to create, including the requirement to attach an identifier to the wrapped object, and says nothing about one that already has an identifier. `w3c/activitypub#438` asks a neighbouring question and has been open since 2024. `MediaUpload` reached for the same shape in 2017 and has not been edited since.

**What the draft says.** Most of it follows from roles the specifications already define: do not give the referenced object a new identifier; take the activity's addressing from the object rather than from the request; reject a request that carries its own `to`/`cc`/`bto`/`bcc`/`audience` rather than ignoring it, because ignoring it lets a client believe it published narrowly when it did not; refuse a reference the server cannot resolve rather than creating something to satisfy it; and check two permissions separately — permission to submit to that outbox, and permission for that actor to publish that object.

**The part I think is new.** Because such a submission carries no document, `actor` and `object` describe it completely, so a server can recognise a repeat as a retry. That is not generally possible in client-to-server: two submissions carrying identical documents may be two genuine posts. It does depend on the server's own policy — if a server lets the same actor publish the same object more than once, the reference alone does not distinguish a retry from a new publication — but where that policy exists, the submission is idempotent and the server records nothing the second time.

Which produces the one place the draft departs from a MUST. Section 6 requires `201 Created`. When the server recorded nothing, `201` asserts a creation that did not happen, so the draft recommends `200 OK` with the existing activity's `Location`. The same reasoning extends to a `Delete` naming an object by URI, with the complication that a deletion after an `Undo(Delete)` is a new transition rather than a retry.

**What it deliberately leaves alone.** How many `Create` activities one object may have, and who may publish them. A server may record one and treat its actor as the publishing authority; a server may accept one from each of several actors and call the object jointly published. Both should be able to implement this.

**Questions.**

1. Is a referenced `Create` a reasonable client-to-server submission at all, or should publishing an existing object stay out of band?
2. Is rejecting request-side addressing right, or does someone have a case for merging it?
3. Is `200` on a recognised retry acceptable as a documented exception, or is there a better answer I have not found?
4. Has anyone hit this already and solved it differently? I would rather adopt an existing answer than add one.

We have this implemented in a WordPress plugin set and covered by tests; it is not in a released version yet. Draft text follows in the pull request once there is a discussion link to point at.
<!-- end of body -->
