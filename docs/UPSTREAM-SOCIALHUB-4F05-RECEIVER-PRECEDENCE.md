# Draft — FEP-4f05 soft deletion: which branch wins for `410` + `Tombstone`

> 대상: <https://socialhub.activitypub.rocks/t/fep-4f05-soft-deletion/5254> 에 답글.
> socialhub 는 로그인이 필요하므로 **소유자가 직접 올린다.**
>
> 올린 뒤 `--posted` 로 되읽어 대조할 것.

## 무엇을 묶고 무엇을 뺐나

```txt
넣는다   수신 절의 두 분기가 410 + Tombstone 에서 겹치고 우선순위가 없다
         그 조합이 AP §6.4 가 권고하는 응답이라는 것
         Unexpected responses 가 답하는 질문은 다르다는 것
         Create(Tombstone) 을 받아 200 으로 내는 우리 구현 — **타입 우선이 왜 위험한지의 근거**
빼는다   강제 fetch 비용 (#14 Claire 가 이미 제기)
         MUST→SHOULD 완화 (#19 에서 저자가 이미 동의. 다만 본문 49행은 아직 MUST)
         Update(Tombstone) 기각 사유 (#18 trwnh 의 모델과 겹침 — 별건으로 남긴다)
```

질문은 **하나**다. 저작 사례는 둘째 질문이 아니라 첫 질문의 근거로 붙는다.

## 본문 (paste as is)

A question about the Receivers section, in the case where its two branching axes overlap.

The section says to handle the activity based on the received response code *or* object `type`, and then gives three branches: a `Tombstone` is soft deleted, something that is not a `Tombstone` updates the local representation, and an HTTP 404 or 410 is hard deleted.

A `410 Gone` whose body is a `Tombstone` matches the first and the third branch, with opposite outcomes, and I could not find a statement of which one wins.

That combination is not an unusual input. It is the response ActivityPub §6.4 recommends: for a deleted object, "410 Gone" when a `Tombstone` is presented as the body, and 404 otherwise. So a server following the base specification produces exactly the response the receiver rules do not resolve.

"Unexpected responses" does not settle this one. It answers what to follow when the activity and the refetch disagree, where the retrieved state supersedes the activity. Here a single refetch result matches two branches of the receiver rules, and both branches describe the same retrieved state.

So: should the HTTP status be read first, making `410` + `Tombstone` a hard delete, with the body type consulted only on a 200-level response?

As a related example, our C2S implementation accepts an authored `Tombstone` and serves it at its canonical URI with HTTP 200, even though the object was never deleted. I realise the Receivers section applies after receiving a `Delete`; this example is not a counterexample to that precondition. It illustrates why the body type and the HTTP status should be treated as separate inputs rather than interchangeable signals.

<!-- end of body -->
