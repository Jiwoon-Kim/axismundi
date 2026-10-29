# C2S Create(Note), 2026-10-29

## Storage and submission

`ax_local_objects` owns current local Note documents, independently of `wp_posts` and
`ax_remote_objects`. Its primary key is its own ID. `ax_objects` remains the common
URI/source index; `ax_object_index` remains the listing projection, not a second body.
The source binding is `axismundi-object-projections / local-object / body-row-id`.

Canonical Object `id` and `url` are both `/objects/{uuid}`. Activity and Object UUIDs
are separately server allocated. Existing WordPress objects keep their existing URLs.
Creating a Note does not allocate a WordPress post or guess a CPT from its AS2 type.

The existing Actor outbox POST accepts Create with an embedded Note, or a bare Note
which is wrapped in Create. It also accepts Delete of an Object owned by that Actor.
Cookie/REST nonce and Application Password authentication use the existing adapter;
principal authorization is rechecked under the submission lock. No JMAP capability
is introduced. Like/Undo continue through their existing service.

The Activity writer owns one SQL transaction. Body, common registry, reply/quote,
mention, tag, thread, listing and optional media usage writes participate without
nested COMMIT. SQL failure prevents the post-commit event and delivery scheduling.
New taxonomy term caches are invalidated after rollback; arbitrary external hooks
are not claimed to be transactional. Idempotency-Key replays return the same Activity;
reusing a key for different input, including another supported verb, returns 409.

New local Create payloads reference the Object URI, following the existing local WP
lifecycle convention. The current body has one authoritative store, with no body
revision/history collection. This changes no existing ledger payload and establishes
no inbound retention, redaction or evidence preservation policy.

## Projection and deletion

Text is sanitized; language maps, addressing and structured document properties are
preserved. Attachments are URI descriptors, not an upload API. Local attachment IDs
require the submitting User's edit permission and use the Media Library usage index.
Delete removes usage links, not shared media binaries.

Delete replaces the body with exactly `id`, `type=Tombstone`, `formerType`, `deleted`.
It uses version CAS and reconciles projections; thread position can remain. Public
Objects return 200 before deletion and 410 afterward for both AP JSON and HTML.
HEAD has the same status and no body. Unknown and nonpublic Objects return 404,
independently of login cookies. A private/operator read API is not implemented here.

WordPress registry registration requires recorded Create attribution matching the
source. `save_post` alone and direct registry calls cannot invent that attribution.
The committed Activity observer admits the matching WordPress source afterward.

Core Post status policy is distinct from the local body deletion contract: draft,
private and trash produce no automatic Update/Delete. A permanently removed published
Core Post emits Delete through the existing principal/attribution guard, but its
source URI returns 404 without an OP body or registry Tombstone. See Activities
`docs/C2S.md` and `tests/audit-wp-post-deletion.php`. This does not change other CPTs
or remote-cache policy, and reversible hiding does not immediately retract remote copies.

## Hard Cutoff and Deferred Work

Local Note projection now adds server-owned replies, likes, shares and emoji reaction
collection URLs through the existing decorators. These are derived at read time,
not persisted as client-controlled collection membership. Nonpublic and Tombstone
local Objects are rejected by the authoritative collection source resolver.
Likes/shares retain the existing count-only representation; replies remain paged.
Storage checks cover a C2S child replying to a WP Object and its advertised replies
root/page. HTTP checks cover all four local collection URLs, a local-parent reply,
Like count and rejection of private/deleted parent collections.

No dedicated wp-admin Object list/detail/editor or Media Library attachment picker
has been added. Do not assume a wp-admin composer is the next step: the owner prefers
to decide draft/edit/publish policy with the frontend C2S authoring UX. Separate
object-edit APIs, draft storage and authoring UI remain deferred until then.

The Note plugin bootstrap is inert. It no longer registers its CPT, REST APIs,
routes, publishers or source adapters. Existing Note tables/data are untouched;
there is no migration, dual-write or old-URI compatibility layer.

Update, Create(Question), Question/vote table retirement, native uploads and frontend
composer work are not included. The existing 15 remote Question observations are
not deleted or given invented historical Creates. Ledger retention is deferred.

## Verification

Run storage/failure-injection checks:

```sh
docker exec wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/axismundi-activities/tests/audit-c2s-create-note.php
```

Run real HTTP checks using the internal wp-env origin:

```sh
docker exec -e AX_C2S_HTTP_ORIGIN=http://wordpress wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/axismundi-activities/tests/audit-c2s-outbox-http.php
```

Storage audit: 26 checks passed, including injected body/registry/relation/tag/listing/
media failures, WP admission and minimal Tombstone/CAS. Real HTTP audit: 53 checks
passed, including existing Like/Undo, authentication, Create/bare Note, cross-verb
retry conflicts, private 404 and AP/HTML 410/HEAD. Shared relation regression: 16/16;
tag-reference regression: 34/34. No mutation-suite claim is made by these fault tests.

Collection follow-up: the storage audit now passes 34 checks, including WP-parent
replies and exact server-owned collection URLs even when input supplies another URL.
Temporarily removing the `axismundi_op_add_replies_property()` call from
`axismundi_op_transform_local_body()` makes that audit fail with
`Local Note advertises server-owned collection: replies`. The production call was
restored and the 34 checks passed again. This is one targeted mutation, not a full
mutation suite. The HTTP rerun passed 72 checks: the four advertised URLs resolve,
a local-parent reply appears, Like then targeted Undo changes its count from 1 to 0,
and all four collections return 404 for private or deleted parents. The first
expanded run failed an older liked cursor expectation because the new fixture Like
remained active. The test now explicitly Undoes that Like before the older paging
assertions; the original expectations were not relaxed.
After cleanup: Activity rows 30,770, principals 1,038, local body rows 0. Existing
ledger contents were compared, not just counted, by the submission audits.

WP deletion-policy audit passed 25 checks, using real draft/private/trash transitions
and AP/HTML requests. It checks no status-driven Update/Delete, exactly one Delete
at permanent removal, no local body/registry Tombstone, and full ledger/principal
snapshot equality after fixture cleanup. The legacy post-create audit's draft/republish
expectation was updated to the new policy; that older whole fixture suite was not
rerun as part of this focused verification.

## External Review: Enforcement and Coverage

오너가 전달한 외부 리뷰 결과를 기록한다. 아래는 Codex가 이번 기록 작업에서 재실행한
결과가 아니다. 리뷰의 베이스라인 보고는 HTTP 72, 저장소 34, WP 삭제 정책 25,
`audit-post-create` 23/23 통과다. 위의 "미실행"은 당시 Codex 검증 범위에 대한 기록이며,
후속 리뷰에서 그 공백을 확인했다. 리뷰 보고 시점의 최종 재확인은 별도로 진행 중이었다.

| 변이 | 리뷰 결과 | 의미 |
|---|---|---|
| M1: local body 변환의 collection `unset()` 제거 | MISSED | Activities 활성 구성에서는 뒤의 데코레이터가 덮어써 결과가 동일 |
| M2: shares 광고만 제거 | CAUGHT | `Local Note advertises server-owned collection: shares` |
| M3: local body 변환의 active 가드 무력화 | MISSED (저장소/HTTP) | 최종 renderer의 Tombstone 화이트리스트가 추가 속성을 제거 |
| M3b: authoritative collection source resolver 가드 제거 | CAUGHT | `Private Object collection fails closed: likes` |
| M4: WP 상태 전환 withdrawal 복원 | CAUGHT | post-create 2개 실패 및 WP 삭제 정책 감사 실패 |

실제 집행 지점과 중복 가드를 구분한다.

1. `axismundi_op_transform_local_body()`의 active 가드는 최종 공개 Tombstone의 최소 키를
   보장하는 지점이 아니다. `renderer.php`의 `axismundi_op_finalize_object()`가 Tombstone을
   id/type/formerType/deleted 화이트리스트로 재구성한다. M3가 통과한 것을 이 최종 불변식의
   실패로 읽지 않는다. 내부 raw 변환의 조기 반환과 공개 출력의 집행은 별개다.
2. `function_exists('axismundi_op_add_likes_property')`는 현재 bootstrap에서 항상 참이다.
   같은 플러그인의 `integrations/reactions.php`를 무조건 require하기 때문이다. Activities
   존재 여부에 따른 실제 분기는 그 함수 안의 Activities getter 검사다. 바깥 가드를
   Activities 의존성 보호나 검증된 보안 경계로 설명하지 않는다. 생산 코드는 이번에 변경하지 않는다.
3. collection `unset()`은 Activities 비활성 구성에서 하중을 받는다. 그때 일부 데코레이터가
   서버 URL을 덮어쓰지 않으므로, 이 제거가 없으면 저장된 클라이언트 URL이 남을 수 있다.
   현재 감사는 Activities를 요구하므로 이 구성을 검증하지 않는다. M1의 통과는 해당 구성이
   안전하다는 증명이 아니며, 별도의 OP 단독/Activities 비활성 구성 감사가 남은 커버리지 공백이다.

리뷰 판정은 동작 결함 없이 통과다. 세 항목은 실제 집행 위치와 구성별 커버리지를
명확히 하기 위한 기록이며, 이 기록만으로 비활성 구성 검증이 완료되지는 않는다.
감사의 RuntimeException 첫 줄에는 실패 항목이 출력된다. tail로 마지막 줄만 본 탓에
진단이 없는 것으로 오해했던 리뷰 중간 판단은 철회됐다.
