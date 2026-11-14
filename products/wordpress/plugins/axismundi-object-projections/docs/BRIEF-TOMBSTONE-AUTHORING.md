# BRIEF — Tombstone 저작 수용

작은 슬라이스다. 근거는 AP §6.2.1 — *"The server MUST accept a valid ActivityStreams object
that isn't a subtype of Activity."* `Tombstone` 은 Object 이므로 그 MUST 에 들어온다.

## 선행: 읽기 경로가 타입으로 상태를 추론한다

```txt
application-object-read.php:87   'Tombstone' === $result['type'] → 410
```

**이건 Tombstone 저작과 무관하게 틀린 추론이다.** 타입이 상태를 결정하지 않고, 저장소는
이미 `object_type` 과 `object_status` 를 **다른 칼럼**으로 갖고 있다. 상태 쪽을 읽게 고친다.
고치지 않으면 저작된 Tombstone 이 생성 즉시 410 으로 나간다.

수신 쪽(`remote-objects.php:249 · :719`)은 **그대로 둔다.** 받은 Tombstone 은 문서밖에 없고
실제로 삭제 통지인 경우가 압도적이다. FEP-4f05 의 backreference 규칙도 같은 방향이다 —
조회된 표현이 활동의 주장을 이긴다.

## 바꿀 것

```txt
① 읽기 410 판정을 object_status 기준으로
② Tombstone 을 저작 가능 타입에 추가 — axismundi_op_local_update_types()
   bare Tombstone 제출도 Create 로 감싼다 (지금은 bare Note 만 감싼다)
```

## 이미 성립해 있는 것 — 새로 만들지 말 것

`formerType` 과 `deleted` 가 **이미 server_set** 이다(`axismundi_op_local_note_server_set()`).
`prepare_local_document()` 가 저자 입력에서 제거하므로, **저작된 Tombstone 은 그 둘을 가질
수 없다.** 새 규칙이 필요 없고, 그래서 우리 출력에서 두 가지가 문서로도 갈린다.

```txt
저작된 Tombstone   { id, type: Tombstone, …저자가 쓴 editable 필드 }
삭제된 객체         { id, type: Tombstone, formerType, deleted }   정확히 4 키
```

다른 서버에서는 둘 다 optional 이므로 이 신호를 **보장으로 주장하지 않는다.** 우리 저장·복원
계약과 원격 구현의 해석은 따로 검증한다.

## 바꾸지 말 것

```txt
수신 Tombstone 해석    remote-objects 의 status 유도는 그대로
hard delete            본문을 Tombstone 으로 덮고 410 을 내는 현재 동작은 유지.
                       FEP-4f05 의 soft delete 는 **이 슬라이스의 선행 조건이 아니다**
복원 의미              복원은 삭제 상태 해제이지 타입 변환이 아니다. 원본이 Note 면 Note,
                       Tombstone 이면 Tombstone.  지금은 복원 경로 자체가 없다
편집 API               별개 변경이다. 저작된 Tombstone 이 active 라서 편집 대상이 되는지는
                       이 슬라이스에서 정한다
```

## 수용 기준

```txt
저작          bare Tombstone 과 Create(Tombstone) 둘 다 Create 가 기록되고
              원본이 **활성 Tombstone** 으로 저장된다
서빙          익명 열람이 허용된 저작 Tombstone → 200 + Tombstone
              삭제된 객체 → 여전히 410 + Tombstone
              **type 만 보고 410 을 정하지 않는다** — 상태를 바꿔 가며 잰다
server_set    저자가 formerType · deleted 를 실어도 저장되지 않는다
이중 삭제      저작된 Tombstone 을 Delete 하면 formerType='Tombstone' 이 된다.
              이상해 보이지만 정확한 기록이고, 깨지는 것이 없어야 한다
회귀          audit-activitystreams-api 17 · audit-c2s-create-note 55 ·
              audit-c2s-update-refusal 18 · audit-media-upload 39
```

**변이로 깨 볼 것.** 최소 둘 — 읽기를 다시 타입 기준으로(저작 Tombstone 이 410 이 되는지),
`formerType` 을 editable 로(저자가 위조할 수 있는지).

## FEP 는 지금 할 말이 없다

`Create(Tombstone)` 이 저작자 의도와 반대로 읽히는지는 **측정해야 안다.** 수신 구현이 어떻게
처리하는지 재기 전에는 조일 근거가 없고, 재서 아무 일도 없으면 조일 것도 없다. 이 슬라이스는
그 측정을 가능하게 만드는 것까지다.
