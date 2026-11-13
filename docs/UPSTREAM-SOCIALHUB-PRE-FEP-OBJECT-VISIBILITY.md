# SocialHub draft

Status: unpublished discussion draft. No FEP number has been assigned.

Topic title: **Pre-FEP: Interpreting Object Visibility in ActivityPub**

Internal design: [Addressing and visibility](../products/wordpress/plugins/axismundi-activities/docs/DESIGN-ADDRESSING.md).

> **보류한 카드** — `bto` 에 `Public` 자체를 넣을 수 있고, §6 이 배달 전 제거하며 B.11 이
> 표시 생략을 권하므로, **내준 표현에 `Public` 이 없는데 익명 접근이 요구되는** 상태가
> 가능하다. 스펙 내부에서 유도되는 가장 강한 논거지만 "누가 그러겠나" 를 부르므로,
> 본문에서는 *"visible addressing [is not] necessarily complete"* 로만 말하고 아껴 둔다.
> 누군가 "주소가 없으면 공개 아니냐" 로 밀 때 꺼낼 카드다.
>
> 게시 전 검증:
>
> ```powershell
> $env:PYTHONIOENCODING="utf-8"; python tools/validators/verify_upstream_comment.py `
>   --source docs/UPSTREAM-SOCIALHUB-PRE-FEP-OBJECT-VISIBILITY.md `
>   --source-after "<본문 시작 주석 줄>" `
>   --source-before "<본문 끝 주석 줄>" `
>   --candidate <본문 파일>
> ```

<!-- BEGIN SOCIALHUB BODY -->
## Summary

A server can send a followers-only `Create` about an object that anyone can retrieve. It can also deliver a restricted object to a single inbox without exposing its blind recipients. In both cases the object may carry no visible addressing. How should a receiving server interpret that?

This proposal separates three decisions: who receives an activity, who may see a representation within a particular context, and who may retrieve an object at its own URI. The same distinctions apply to actors, media, and posts. They do not require those objects to share a default visibility policy.

## What ActivityPub already says

[Delivery] uses the activity's addressing to find recipients. [Public Addressing] requires unauthenticated access to activities addressed to `Public`, but `Public` is not itself an inbox destination. [Create] recommends copying recipients between the activity and the object at initial distribution, while allowing the object's addressing to change later. [Retrieving Objects] allows origin servers to enforce their own authorization rules, including returning `404` to conceal a restricted object's existence.

None of those rules supplies a general mapping from a non-Activity object's addressing to its independent GET permissions. Nor is the visible addressing necessarily complete: [blind recipients] are removed before delivery.

## Proposed interpretation

1. **Do not inherit the activity's delivery audience as the object's retrieval policy.** A narrow delivery does not narrow an independently public object. A wide delivery does not authorize access to every referenced resource.
2. **Do not treat absent addressing as either a grant or a restriction.** If the origin returns an AS2 representation to an unauthenticated GET, that is evidence that this representation was anonymously retrievable. It is not evidence of a hidden `bto: Public`, a permanent grant, or permission to expose a richer representation received elsewhere. A failed GET does not by itself distinguish an absent object from a restricted one.
3. **Keep contextual display separate from redistribution.** A recipient may read the body delivered to their inbox without first fetching the object. That does not make the recipient's cached copy available to unrelated users. Conversely, an object already known to be publicly retrievable is not made private merely because it arrived in a restricted activity. Neither push nor pull is itself a visibility classification, and this proposal does not require a new GET for each delivery.

## Examples

- **An actor without addressing.** If the origin serves its actor document anonymously, the document is publicly retrievable without a synthetic `to` or `bto` field. This does not require every actor document to be anonymous-readable.
- **A public image announced narrowly.** An independently public attachment can be carried in a followers-only `Create`. The notification stays narrow; the image's retrieval policy is unchanged. A public Note need not repeat `to: Public` on every inline image for a renderer to display them in that Note. A renderer may honor an explicit per-image display restriction, but hiding a public file URL in one UI does not enforce confidentiality.
- **A body delivered only through `bto`.** After the blind address is stripped, the body may look like any other unaddressed object. A recipient can read it in their inbox. The receiver cannot reconstruct the original blind recipient list or use the missing fields to justify public redistribution.

The failure pattern in each case is using evidence from one surface as authority for another. The unusual addressing combinations are examples, not proposed bans.

Out of scope: how recipients are selected, whether an origin should restrict actor retrieval at all, what an activity carrying content with no `object` means, and how collections decide which items and counts to expose. Each is a separate decision that this interpretation neither settles nor depends on.

## Implementation status

Axismundi's WordPress projection provides a motivating example: an attachment can be independently retrievable as AS2 without addressing. This is not a claim that Axismundi or another implementation already implements the complete interpretation above.

## Discussion

1. Do implementations distinguish the visibility of an activity, the representation embedded in it, and the resource at the object's URI? Where do they intentionally carry a decision across those boundaries?
2. Do implementations send restricted objects inline with addressing only on the activity, or use object-level `bto` or `bcc` as input to retrieval authorization? Concrete payloads and observed behavior would help.
3. Is there an existing interoperable way to convey an object's intended display restrictions without implying either anonymous GET access or confidentiality? Is guidance without new vocabulary enough?

[Delivery]: https://www.w3.org/TR/activitypub/#delivery
[Public Addressing]: https://www.w3.org/TR/activitypub/#public-addressing
[Create]: https://www.w3.org/TR/activitypub/#create-activity-outbox
[Retrieving Objects]: https://www.w3.org/TR/activitypub/#retrieving-objects
[blind recipients]: https://www.w3.org/TR/activitypub/#remove-bto-bcc-before-delivery
<!-- END SOCIALHUB BODY -->
