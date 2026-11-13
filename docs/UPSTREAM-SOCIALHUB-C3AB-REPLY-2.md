# Draft — SocialHub reply (FEP-c3ab topic, post 10)

> 상태: **게시됨** 2026-11-13 — post 11 (id 31663), 되읽기 일치 `5d86aa99…`.
> Discourse 가 고쳐 쓴 흔적 없음. 대상: silverpill #10 —
> https://socialhub.activitypub.rocks/t/fep-c3ab-referencing-existing-objects-in-client-to-server-create/8983/10
>
> 질문: "never published" 가 id 가 없었다는 뜻인가, 아니면 id 는 있고 작성자만 봤다는
> 뜻인가(`to == attributedTo`).
>
> **둘 다 아니다.** 코드로 확인한 세 번째 경우다:
>
> ```txt
> 발행된 오래된 글   id 있음 · 공개 fetch 가능(to:[Public]) · 활동 없음   ← c3ab 대상
> draft/private/암호 id 있음 · 투영 거부(ax_op_not_public → 404) · 활동 없음
> ```
>
> 측정 근거(2026-11-13):
> - `axismundi-object-projections/includes/post-article.php:168` — 공개 게이트는
>   `'publish' === post_status && '' === post_password && is_post_publicly_viewable()`
> - 같은 파일 `:261` — 기본 audience 는 `'to' => array( public_audience_uri() )`
> - `includes/renderer.php:327,366` — 게이트 불통과는 `ax_op_not_public`,
>   `object-read-api.php:21` 에서 404 로 나간다
> - 참조형 `Create` 는 `axismundi-activities/includes/c2s-outbox.php:105` 에서
>   lifecycle+visible 를 확인하므로, 비공개 글에는 애초에 통과하지 않는다
>
> 층의 조합 관계: 원장과 C2S 제출의 소유는 Activities 이고, 그 활동의 `actor` 와 `object`
> 프로퍼티를 각각 Actors 와 Object Projections 에서 받아온다. Bridge 는 서명·전송만 한다.
> 본문은 플러그인 이름을 들지 않고 역할로만 썼다 — 다른 구현자가 자기 시스템에 대응시킬 수
> 있어야 하고, 우리 층 이름을 외우게 할 이유가 없다.
>
> **쓰지 말 것**: "neither property is something the submission gets to decide" — 초안에
> 넣었다가 뺐다. 제출이 `object` URI 로 **무엇을 발행할지 고르는 것**이 이 FEP 의 핵심이다.
> 고를 수 없는 건 대상이 아니라 그 객체의 기존 표현을 이번 요청으로 다시 정의하는 것이다.
> 또 공개 fetch 가 가능했으므로 **다른 서버가 이미 GET 으로 읽었을 가능성을 부정하지 않는다**.
>
> 게시 전 검증:
>
> ```powershell
> $env:PYTHONIOENCODING="utf-8"; python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-SOCIALHUB-C3AB-REPLY-2.md `
>   --source-after "<Body 제목 줄 전체>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

## Body — reply to post 10 (silverpill)

Neither. The WordPress post already had a stable ActivityStreams identifier, and anyone who knew its URI could fetch it — including, in principle, another server. By "never published" I meant that our server had never recorded or federated a `Create` for it.

The client names the existing object by URI. The server derives the activity's `actor` from the authorized outbox, resolves the `object` through its projection of the WordPress row, and records the `Create`. The request selects what to publish; it does not make the object public and does not redefine it.

The visibility gate is the content store's, not the activity's. A published row projects as AS2 — a post as an `Article`, an attachment as a `Document` subtype — with `to: [Public]`, and is served to anyone who asks. A draft, private or password-protected row is refused and answered `404`, so no URI leaks content the author withheld; a referenced `Create` naming such a row is refused for the same reason. Being published in the store's terms is a precondition, not something the activity performs.

So before anyone submits a `Create`, a years-old published post is identified, fetchable, and addressed to `Public` — and still unknown to the fediverse, because no activity was ever recorded or delivered and no inbox ever saw it. "Never published" means never distributed. Not unidentified, and not withheld.

That is why the `Create` does not establish attribution or addressing: both already exist, assigned by the store when the content was written, sometimes years earlier and sometimes by a different person than the one now publishing it. The activity adds the one thing the store never had — a record that this actor put this object on the network, with an identifier that later `Update` and `Delete` activities can refer back to.

The author-only case you describe does exist in the same system, and it behaves differently: the object is not retrievable at all, and the outbox refuses a referenced `Create` for it. I think that case is a separate question from this FEP. An activity that changes an object's visibility is `Update` territory, and it would have to say what happens to an object no remote server has ever been told about.
<!-- end of body -->
