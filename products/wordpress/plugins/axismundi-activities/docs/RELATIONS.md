# Social relationship state

## 1. Follow state machine

```text
Follow recorded            -> pending
Accept(object=Follow URI)   -> accepted
Reject(object=Follow URI)   -> rejected
Undo(object=Follow URI)     -> undone
```

Each transition is caused by a separately retained Activity. The Follow payload is never
rewritten. Duplicate delivery of the same Activity URI is idempotent. A transition must
verify that the transition Actor is authorized for the referenced relation; transport-level
signature verification is Federation's responsibility and is additional to this domain check.

A compatibility adapter may import an accepted or pending current-state assertion when the
historical Follow Activity is unavailable. Such a row is marked `legacy_snapshot`, has no
initiating Activity URI, and creates no synthetic Activity. `legacy_pending` is not delivered
or projected as following. Any later real Follow takes ownership of the pair; its subsequent
Accept, Reject, or Undo remains authoritative over every snapshot import.

An imported outbound snapshot cannot be undone because its original Follow Activity is
unknown. The user may explicitly Re-follow instead: a new outbound Follow replaces snapshot
evidence and begins an Activity-backed cycle. Likewise, a distinct newly received Follow URI
supersedes an accepted or pending cycle for the same Actor pair. Automatic approval must Accept
that exact incoming Follow URI; duplicate delivery of the same URI remains idempotent.

Accept or Reject may arrive before its Follow. The immutable transition is retained and the
relation is reconciled when the referenced Follow arrives. Only the followed Actor may Accept
or Reject. An unauthorized transition rolls back with its Activity row.

## 2. Block

Person Block 구현 전 배포 선례와 미결은 [BLOCK-PRECEDENTS.md](BLOCK-PRECEDENTS.md)를
참조한다. Mastodon·Misskey의 개인 차단과 Lemmy의 Group/Site ban은 같은 정책이 아니다.

### NON-STANDARD — Block을 대상에게 전달한다 (오너 결정, 2026-11-01)

[AP §6.9](https://www.w3.org/TR/activitypub/#block-activity) 원문:

> Servers SHOULD NOT deliver Block Activities to their object.

**우리는 보낸다.** AP 에서 벗어나므로 표시해 두지만, 독단이 아니라
[FEP-070c](https://codeberg.org/fediverse/fep/src/branch/main/fep/070c/fep-070c.md)
가 권고하는 방향이다.

> Instances that want blocks to work on servers without this FEP SHOULD also send
> `Block` and `Undo Block`.

**같은 행동에 대해 AP 는 SHOULD NOT, FEP-070c 는 SHOULD 다.** 한쪽을 고를 수밖에
없고, 우리는 후자를 고른다.

```txt
보내지 않으면      상대 서버가 모르고 전송을 계속 시도한다. 차단은 우리 쪽 필터로만
                   남고 트래픽과 알림 시도는 그대로다
숨겨지지도 않는다   차단 사실은 이미 관측 가능하다. 기존 SNS 에서도 상대 프로필에
                   들어가면 드러난다. 숨겨서 지켜지는 비밀이 아니다
배포 선례          Mastodon·Misskey 둘 다 원격 대상에게 보낸다 (BLOCK-PRECEDENTS 2·3)
```

### 전달과 통지는 다르다

FEP-070c 는 동기화 경로에 대해 이렇게 쓴다.

> Receiving implementations SHOULD NOT notify the blocked actor as a result of
> synchronization.

우리는 이것을 inbound Block 전반에 적용한다. **서버에는 전달하되, 받은 쪽이
차단당한 사용자에게 알리지 않는다.** 전달은 상대 서버가 전송을 멈출 근거를
주기 위한 것이지 통보가 목적이 아니다.

### 노출 범위

[FEP-c648](https://codeberg.org/fediverse/fep/src/branch/main/fep/c648/fep-c648.md)
은 `blocked`·`blocks` 컬렉션을 정의하면서 보안 절에 이렇게 쓴다 — 둘 다
`SHOULD NOT be publicly readable` 이고 기본적으로 소유자 외에는 읽을 수 없어야
한다. 우리의 "Public 수신자를 붙이지 않는다" 와 같은 방향이고, 이제 근거가 있다.

그 FEP 가 `blocks` 에 Block Activity 의 `id` 를 보존하라는 이유는 Undo 를
가능하게 하기 위해서다. **우리 불변 원장이 이미 그 조건을 만족한다.** 나중에
`blocked`·`blocks` 를 붙인다면 새 저장소가 아니라 원장 위의 투영이다.

### FEP-070c 본체는 이번 범위 밖이다

동기화 엔드포인트는 전제가 세다 — 모든 액터가 하나의 URI scheme·authority 를
공유해야 하고, 서명된 GET 을 검증하고 자기 요청도 서명할 수 있어야 한다.
나중에 볼 선택지로 남긴다.

### FEP-8fcf 를 우리는 지금 쓰지 않는다 (실측, 2026-11-01)

[FEP-8fcf](https://codeberg.org/fediverse/fep/src/branch/main/fep/8fcf/fep-8fcf.md)
는 **FINAL** 이고 Mastodon · Pixelfed · Fedify · WordPress ActivityPub 가 구현한다.
`Collection-Synchronization` 헤더로 팔로워 목록의 불일치를 싸게 탐지하고, 다르면
부분 컬렉션을 받아 **로컬에 남은 유령 팔로워를 제거**한다. 막으려는 위험이
바로 `sharedInbox` 를 통한 낡은 팔로워 전송이다.

우리 Bridge 는 공식 플러그인의 구현을 **네 개 모두 언훅한다.**

```txt
composition.php:30    activitypub_inbox_create → Collection_Sync::handle_collection_synchronization
              :31    http_request_args        → Collection_Sync::maybe_add_headers
              :107   activitypub_collection_sync → Scheduler\Collection_Sync::schedule_reconciliation
              :108   activitypub_followers_sync_reconcile → reconcile_followers
```

그리고 저장소 전체에 `Collection-Synchronization` · `collectionId` 구현이 **0 건**
이다. 즉 **헤더를 보내지도 받지도 않고 조정도 하지 않는다.**

Block 슬라이스와 이어지는 지점: 차단은 양쪽 팔로워 관계를 끊는데, 원격이 그
제거를 반영하지 않으면 우리를 following 에 남긴 채 계속 전송한다. 우리가 Block 을
전달하기로 한 결정이 그 정리의 **근거**를 주지만, FEP-8fcf 는 그것이 실패했을 때의
**안전망**이다. 지금은 안전망이 없다.

이번 범위 밖이다. 다만 "Block 하면 상대가 전송을 멈춘다" 고 **광고하지 않는다** —
우리는 근거를 보낼 뿐이고 inbound 차단 검사가 실제 집행이다.

### Block 이 기존 Follow 를 끊을 때의 전이

표준이 비워 둔 자리다. FEP-070c 가 명시한다 — Follow 와 Reject 는 규범적으로
다루지 않으며, 차단이 팔로워에서 제거한다는 점만 언급하고 그 분기는 FEP-8fcf
로 넘긴다. 그러므로 아래는 **표준에 반하는 정책이 아니라 표준이 비워 둔 자리를
채우는 정책**이다.

원칙은 하나다. **Activity 를 합성하지 않는다.**

```txt
Undo(Follow) 합성   팔로워가 저자다. 우리 사용자가 하지 않은 행동을 그에게 귀속시킨다
Reject 합성         대상이 저자다. 원격이 저자면 원격 Actor 사칭이다
```

`axismundi_act_transition_relation()` 은 도달한 상태와 **그 상태를 만든 Activity**
를 따로 받는다. 그러므로 Block 이 원인인 전이를 합성 없이 쓸 수 있다.
차단자가 그 Follow 에서 어느 쪽인지가 상태를 정한다.

```txt
방향                        차단자 역할        상태        state_uri
L→R 이고 R 이 차단          object (대상)      rejected    Block Activity URI
R→L 이고 R 이 차단          subject (팔로워)   undone      Block Activity URI
```

**우리가 저자인 쪽은 진짜로 발행한다.** 우리가 object 면 `Reject(Follow)` 를,
subject 면 `Undo(Follow)` 를 우리 이름으로 발행한다 — 사칭이 아니고 Mastodon·
Misskey 의 와이어 동작과 같다. 그 경우 새 메커니즘이 필요 없다.
`relation_transitions()` 가 `actor_uri === object_uri` 인 Accept/Reject 를 이미
받아들이고, `undone` 은 Follow 의 `effective_status` 에서 온다. 그러므로 새로
만드는 것은 **Block 을 원인으로 하는 전이 하나뿐**이다.

### Accept 를 취소하지 않는다

메뉴얼 승인으로 accepted 가 된 Follow 에 inbound Block 이 와도, 그 Accept 를
Undo 하거나 Reject 로 바꾸지 않는다. SPEC.md 3 이 기록된 Activity payload 는
불변이라고 못박는다. **수락은 그때 실제로 일어났고 나중에 차단이 왔을 뿐이다.**
원장은 둘 다 보존하고 현재 상태만 바뀐다.

전달 정책:

```txt
원격 대상     원격 inbox
로컬 대상     로컬 inbox 처리 경로로 전달한다.
              **`local` 방향 구분을 되살리지 않는다** — 없앤 결정은 유지되고,
              목적지 해소와 전달 방식만 달라진다
전달 ≠ 집행   로컬 차단은 즉시 집행한다. 전달 성공이나 상대 서버의 협조에
              의존하지 않는다
inbound 검사  유지한다. Block 을 받은 서버가 실제로 전송을 중단한다는 보장이 없다
공개 금지     대상에게 전달하되 방송하지 않는다. AP Block 으로 기록했다는 이유로
              Public 수신자를 붙이지 않는다
해제          Undo(Block) 도 같은 경로로 보낸다. 해제를 통지받아도 이전 Follow 를
              자동 복원하지 않는다
```

Block is directional (`subject` blocks `object`). Undo(Block) changes the materialized
relation state to undone while preserving both Activity rows. Block does not imply remote
delivery succeeded and must not be used as a delivery status.

## 3. Direction

- `outbound`: subject is local and object is non-local.
- `inbound`: subject is non-local and object is local.
- `local`: both Actors are local.

Remote-remote relations are not materialized merely because they appeared in an unrelated
payload. Locality is resolved through Actors when available; canonical Actor URIs remain the
stored identity.

## 4. Administrator inspection

`Tools > Activity Log` is a read-only ledger inspector for administrators. It shows recent
Activities, immutable payload JSON, and current materialized relations. It performs no fetch,
delivery, state transition, or notification action.

## 5. Local product workflow

Activated public local Person actors may follow each other without HTTP transport. An explicit
`manually_approves_followers=true` leaves the Follow pending until the target records Accept or
Reject. False, or NULL under the default site policy, records an automatic target-authored
Accept. The immutable Follow and Accept remain separate Activities. The `Follows` admin screen
exposes only the current Actor's requests and accepted edges. Remote handles include their
instance while local handles omit it. Activity-backed following can be undone, imported
outbound snapshots can be replaced through Re-follow, and Activity-backed followers can be
removed with a Reject that references the original Follow. Follow back uses the same control
for local and cached remote Actors. Local product actions require the Contributor-level
`edit_posts` capability. Administrators with an activated Actor may also act from local rows
on the core Users table. Activities records transport-neutral outbound Activities; a transport
adapter decides whether and how remote inboxes receive them.

## 6. Open — Person Follow transitions

다음 슬라이스의 범위다. 아래 전이는 구현되어 있고, 빈칸만 정하면 된다.

```txt
Follow 도착      axismundi_act_start_relation()        → pending
자동 수락        can_auto_accept() 면 즉시 Accept       local-social.php:38-46
승인 / 거절      axismundi_act_transition_relation()   → accepted / rejected
Undo             → undone
Block            별도 관계. active / undone
```

정할 것 셋.

```txt
거절 후 재-Follow          rejected 상태에서 새 Follow 를 받는가
Block 과 기존 Follow       accepted 상태에서 Block 하면 follow 관계는 어떻게 되는가
정책 변경과 기존 pending   승인제를 끈 뒤 대기 중이던 요청을 소급 수락하는가
```

세 번째의 측정값과 권고:

```txt
측정   axismundi_actors_set_local_policy() 은 훅을 발화하지 않는다.
       auto-accept 핸들러는 axismundi_act_relation_changed 에만 걸려 있다.
       따라서 승인제를 켜 둔 동안 들어온 Follow 는 끈 뒤에도 pending 으로 남는다.
권고   소급 수락하지 않는다. 승인제 동안의 요청은 사람이 본 적 없는 요청이고,
       정책을 끄는 행위가 그것들의 일괄 수락으로 읽히면 안 된다.
       대신 대기 중인 요청이 있다는 것이 Follows 화면에서 확인·처리 가능해야 한다.
```

### 합격선 — 무엇이 광고 불일치가 아닌지

`manuallyApprovesFollowers` 는 **새 요청에 적용할 승인 정책**이지 개별 관계의 현재
상태가 아니다. 그러므로 자동 수락으로 바꾼 뒤 예전 요청이 pending 으로 남아 있는
것은 그 자체로 광고 불일치가 **아니다**. 합격선은 "각 전이 후 광고값이 그 시점의
**새 Follow 에 대한 처리**와 일치하는가" 이다.
