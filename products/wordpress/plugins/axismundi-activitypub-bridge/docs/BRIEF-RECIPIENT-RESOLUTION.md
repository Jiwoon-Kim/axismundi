# BRIEF — 수신자 해소와 배달 엔드포인트

근거와 조문은 `axismundi-activities/docs/DESIGN-ADDRESSING.md` §5 에 있다. 이 문서는
**무엇을 바꾸고 무엇을 바꾸지 않는지**만 적는다.

## 범위

```txt
안에 있다   수신자 해소가 출처를 들고 가게 한다 (bto·bcc 구분)
            공유 엔드포인트를 둘 이상 모일 때만 쓴다
밖에 있다   Forum 입회 게이트(Note 루트·audience 신호·답글 부모) — 다른 플러그인, 별건
            likes/shares 항목 상속 — 코드 미확인. 재는 것이 먼저다
            주소 저층 재설계(프리셋을 컴포저로) — 이 작업의 전제가 아니다
```

## 현재 (실측 2026-11-14)

```txt
repository.php:453   저장 audience 는 to·cc·bto·bcc·audience 다섯을 한 목록으로 정규화한다
repository.php:590   payload 에서 bto·bcc 를 제거한다 — AP §6 대로 맞다. 유지한다
transport.php:216    activity_inboxes() 가 후보를 모은다. get_audience() 를 평탄화하므로
                     여기서 출처가 사라진다
transport.php:280    수신자마다 endpoints['shared_inbox'] ?? endpoints['inbox']
transport.php:294    array_values( array_unique( $inboxes ) ) — 같은 엔드포인트가 접힌다
delivery.php:77      UNIQUE KEY recipient (delivery_id, inbox_hash) — 큐도 inbox 단위
```

압축은 이미 일어난다. 팔로워 펼치기도 있고, 원격 컬렉션을 펼칠 수 없을 때
`axismundi_activitypub_bridge_unresolved_delivery_candidate` 로 드러내는 것도 있다.

## 바꿀 것 둘

### ① 컬렉션에서 나온 수신자만 공유 엔드포인트로 보낸다

압축은 **상대가 그 주소를 우리와 같은 수신자 집합으로 해소한다는 가정**을 필요로 한다.
`audience: Group` 만으로는 상대가 그것을 배달 대상으로 해석할지 확정할 수 없다. 그래서
첫 구현은 상한까지 열지 않는다.

```txt
컬렉션 유도분      공유 엔드포인트로 압축한다 (팔로워 컬렉션을 펼친 것)
개별 지목          to·cc 의 액터 URI → 그 액터의 개인 inbox
그 호스트에 하나    개인 inbox. §7.1.3 의 목적이 fan-out 축소이므로 줄일 것이 없다
```

더 넓은 압축은 **상호운용 검증 뒤에** 연다. 이것은 §7.1.3 의 금지가 아니라 우리
정책이므로, 코드에 "스펙이 금지한다" 고 적지 말 것.

부수 효과가 의도한 것이다 — `is_direct_group_submission` 이 후보를 Group 하나로 줄이는
경로가 지금은 원격 호스트의 공유 엔드포인트로 가는데, 이 규칙이면 Group 자신의 inbox 로
간다. Group 은 받아서 `Announce` 하는 행위자이므로 라우팅 홉이 하나 줄어든다. **Group
특례를 따로 만들지 말 것** — 이 규칙으로 저절로 따라온다.

개별 지목과 컬렉션 유도분을 가르려면 **출처가 필요하다** — ②와 같은 배관을 쓴다.
단독 수신자 판정만은 목록에서 계산된다.

### ② `bto`·`bcc` 에서만 유도된 수신자는 개인 inbox 로 보낸다

AP §6 은 배달 **전에** 객체에서 `bto`·`bcc` 를 제거하라고 하고, §7.1.3 의 수신 서버는
객체의 주소를 보고 라우팅한다. 그러니 bcc 된 액터를 공유 엔드포인트로 보내면 수신
서버가 그 액터를 식별할 근거가 없어 **영영 도달하지 않는다.** 지금 그렇게 하고 있다.

원인은 정책이 아니라 **출처 상실**이다. `activity_inboxes()` 가 다섯 필드를 한 후보
목록으로 합치므로 어떤 수신자가 `bto`·`bcc` 에서만 왔는지 알 수 없다. 후보를
`(uri, 출처 필드 집합)` 으로 들고 해소 단계까지 가져가면 된다.

**①로는 해결되지 않는다.** 같은 호스트에 `to` 수신자와 `bcc` 수신자가 함께 있으면
그 호스트의 공유 엔드포인트가 둘 이상이 되어 ①의 조건을 통과한다.

## 바꾸지 말 것

```txt
payload 의 bto·bcc 제거          AP §6 요구. repository.php:590 유지
수신자 집합 자체                 누구에게 배달되는지는 바뀌지 않는다. 엔드포인트만 바뀐다
큐의 inbox 단위 유니크           delivery.php:77 그대로
펼치지 못한 원격 컬렉션 처리     조용히 버리지 않는다. 컬렉션 소유자로 바꿔치기하지 않는다
수신 경로                        actor-inbox.php 는 이 작업과 무관하다
```

## 수용 기준

새 감사 하나를 `tests/` 에 둔다. 집계 문구는 기존 감사와 같은 형식으로.

```txt
컬렉션 유도 수신자 둘 이상     공유 엔드포인트 하나로 접힌다
개별 지목 수신자 둘 이상       같은 호스트라도 각자 개인 inbox 로 간다
한 호스트에 수신자 하나        그 액터의 개인 inbox 로 간다
Group 직접 제출                Group 자신의 inbox — 특례 없이 위 규칙으로 따라온다
bcc 수신자 단독                개인 inbox 로 간다
bcc 수신자 + 같은 컬렉션 유도   컬렉션분은 공유로 접히고 bcc 는 개인 inbox 로 따로 간다
공유 엔드포인트 없는 액터      개인 inbox — 회귀 확인
payload                        bto·bcc 가 들어 있지 않다
```

**변이로 깨 볼 것.** 판정식이 통과만 하고 아무것도 재지 않는 경우가 이 저장소에서 네 번
있었다. 최소 둘 — 출처 추적 제거, 둘 이상 조건을 항상 참으로.

기존 감사도 같이 돌린다: `audit-outbound-delivery` (50 checks),
`axismundi-activities/tests/audit-c2s-create-note.php`,
`audit-c2s-outbox-mutations.php`.

## 검수할 때 볼 것

- 수신자 **집합**이 바뀌지 않았는가. 이 작업은 엔드포인트 선택만 바꾼다
- Group 특례가 새로 생기지 않았는가
- `bto`·`bcc` 가 payload 로 새지 않았는가
- 감사가 known-positive 로 실제로 깨지는가
