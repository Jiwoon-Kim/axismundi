# Draft — SocialHub topic: C2S `Create` for an already-hosted object

> 상태: **미게시** (2026-11-11 작성). [SocialHub ActivityPub 카테고리](https://socialhub.activitypub.rocks/c/activitypub/5)에 올릴 토론 글.
> FEP 제출 전 단계다 — FEP 저장소 안내가 **토론 주제 개설 → `discussionsTo` 링크 추가 → PR** 순서를 요구한다.
> 합의를 기다릴 필요는 없고, 같은 날 PR까지 가도 된다(FEP-a4ed는 `DRAFT` 등록 후에도 저자가 토론을 이어가게 한다).
>
> **FEP 슬러그는 이 토론 제목이 아니라 FEP 제목에서 계산한다**
> (`echo -n "<FEP title>" | sha256sum | cut -c-4`). 제목을 확정한 뒤 계산할 것.
>
> 인용 검증(2026-11-11, 전부 원문 확인):
>
> - AP §6 — `"Servers MUST return a 201 Created HTTP code"`, 클라이언트 `id` 무시 후 새 `id` 생성
> - AP §6.2.1 — 비-Activity 객체 수용 MUST, `Create`로 감싸기 MUST, 비일시적 객체면 **양쪽에** `id` 부착 MUST,
>   객체의 `to`/`bto`/`cc`/`bcc`/`audience`를 Create로 복사 MUST
> - AS2 §3.1 Minimal Activity — `object`가 URI 참조인 예제
> - [MediaUpload 위키](https://www.w3.org/wiki/SocialCG/ActivityPub/MediaUpload) — `Create` 감싸기는 **MAY**,
>   `Location`은 **객체**를 가리킴(§6.2.1 주석은 Create를 가리킨다 — 기존 지침끼리 어긋나는 지점).
>   "last edited on 24 October 2017" — 멈춘 제안 문서다.
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
> 게시 후 되읽기 일치 확인(`--posted`)까지 하고 상태 줄을 갱신할 것.

## Title

`C2S outbox: Create for an object the server already hosts`

## Body (Discourse Markdown — paste as is)

Most C2S discussion assumes the object is born with its activity: a client posts a `Note`, the server wraps it, and both get fresh ids. But a large class of servers already hold the object before any activity exists — anything backed by a CMS. A WordPress post has a canonical URL, an author, an audience and a revision history long before anyone decides to federate it.

For that case the client has nothing new to submit. It wants to say "publish this one", naming it:

```json
{ "type": "Create", "actor": "https://example.org/actors/alice",
  "object": "https://example.org/?p=123" }
```

That shape is ordinary AS2 — it is the Minimal Activity example in AS2 §3.1, where `object` is a plain URI. What I cannot find is guidance on what a server should do when it receives one for an object it already hosts.

ActivityPub §6.2.1 covers the adjacent case well, but every rule in it is written for an object the server is about to create: it must accept a non-Activity object, it must wrap it in a `Create`, and for non-transient objects it "MUST attach an id to both the wrapping Create and its wrapped Object". None of that tells you what to do with an object that already has one. (This is close to w3c/activitypub#438, which asks the same question about an inner object's id.)

So, four questions:

1. **Is a referenced `Create` a legitimate C2S submission at all**, or should publishing an existing object be out-of-band?
2. **Addressing.** §6.2.1 requires copying the object's `to`/`bto`/`cc`/`bcc`/`audience` onto the Create. For a referenced object the server can read those itself. Should addressing supplied in the request be merged, ignored, or refused? Silently ignoring it seems worst: a client that believed it was publishing to a narrow audience would get the object's existing one.
3. **Re-submission.** §6 says servers "MUST return a 201 Created HTTP code". If a server records at most one `Create` per object, what should a second identical submission return? 201 claims a creation that did not happen.
4. **Prior art.** The [MediaUpload](https://www.w3.org/wiki/SocialCG/ActivityPub/MediaUpload) proposal is the only place I found where the object exists before the activity. There the `Create` wrapping is a MAY, and `Location` points at the *object*, while §6.2.1 says it should point at the *Create*. That page was last edited in 2017.

We have this running in a WordPress implementation and are happy to write it up as a FEP if the shape is useful to others. What we settled on, as one data point rather than a proposal: the referenced object never gets a new id; addressing in the request is refused rather than ignored; and a repeat submission by the same actor returns `200` with the `Location` of the original `Create`, recording nothing new. The `200` is a deliberate departure from the §6 `201` requirement, and if there is a better answer to question 3 I would rather adopt it than document a deviation.

I am mainly interested in whether others have hit this and what they did.
<!-- end of body -->
