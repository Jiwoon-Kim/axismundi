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
