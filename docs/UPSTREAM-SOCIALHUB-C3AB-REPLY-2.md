# Draft — SocialHub reply (FEP-c3ab topic, post 10)

> 상태: **미게시.** 대상: silverpill #10 —
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

Neither, as it turns out, and the third case is the one the FEP is about: the object has an `id` and is publicly retrievable. What it has never had is an activity.

Concretely, in the implementation this came from. A content store holds the row — this is a WordPress plugin set, so a post or an attachment. A projection layer renders that row as AS2, a post as an `Article` and an attachment as a `Document` subtype, and serves it at a stable URI. The visibility gate is the store's own: a published row projects with `to: [Public]` and is served to anyone who asks, while a draft, private or password-protected row is refused with a `404`, so no URI leaks content the author withheld. Identity lives in its own layer that owns actors, a ledger layer records activities, and a bridge signs and delivers them to remote inboxes. An activity is assembled rather than authored: the ledger owns the activity, but its `actor` comes from the identity layer and its `object` from the projection layer, which is why neither property is something the submission gets to decide.

So before anyone submits a `Create`, a years-old published post is identified, fetchable, and addressed to `Public` — and entirely unknown to the fediverse, because no activity was ever recorded or delivered and no inbox ever saw it. "Never published" means never distributed. Not unidentified, and not private.

That is also why the `Create` cannot be what establishes attribution or addressing: both already exist, assigned by the store when the content was written, sometimes years earlier and by a different person than the one now publishing it. The activity adds the one thing the store never had — a record that this actor put this object on the network, with an identifier that later `Update` and `Delete` activities can refer back to.

The author-only case you describe does exist in the same system, and the rules fall out differently there. The projection refuses it, so the object is not retrievable at all, and the outbox refuses a referenced `Create` for it too: being published in the store's terms is a precondition, not something the activity performs. I think that case is a separate question from this FEP — an activity that changes an object's visibility is `Update` territory, and it would need to say what happens to an object that no remote server has ever seen.
<!-- end of body -->
