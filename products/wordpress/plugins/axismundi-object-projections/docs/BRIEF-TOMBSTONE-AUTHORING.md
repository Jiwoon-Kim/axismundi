# BRIEF — Tombstone 저작 수용과 삭제 응답의 구분

작은 슬라이스다. 근거는 AP §6.2.1 — *"The server MUST accept a valid ActivityStreams object
that isn't a subtype of Activity."* `Tombstone` 은 Object 이므로 그 MUST 에 들어온다.

## 세 가지가 서로 다른 것을 말한다

```txt
object_type     **저작된 타입**.  Create 에서 정해지고 바뀌지 않는다
document        지금 내주는 표현.  삭제되면 Tombstone 본문이 된다
object_status   이 URI 가 실제로 삭제됐는가
```

지금은 삭제가 셋을 한꺼번에 움직여서 중복처럼 보인다. **`Create(Tombstone)` 을 받으면
중복이 아니게 된다** — 저작된 Tombstone 은 `object_type='Tombstone'` 이면서
`object_status='active'` 다.

`object_status` 를 "원장에 Delete 가 있는지의 캐시" 라고 부르지 말 것. 실제로는
`axismundi_op_tombstone_local_object()` 가 **원장을 조회하지 않고** 상태를 바꾸고, 편집과
투영이 그 상태를 쓴다. 감사에도 활동 없이 삭제되는 경우가 있다. 독립 권위다.

### 타입은 **객체가 처음 저장될 때** 고정된다

`Create` 시점이 아니다. 업로드한 `Video` 는 참조형 `Create` 전에 이미 URI 와 타입을 갖고,
발행은 그 타입을 다시 정하지 않는다. 두 시점이 같아지는 것은 outbox 가 객체 생성까지 맡는
경우뿐이다(`C2S.md` §14 의 두 진입 경로).

Outbox `Update` 가 타입 변경을 이미 막는다. 그 금지는 유지하고, **이유를 적어 둔다** —
열면 `Article → Image → Note → Question` 같은 변형이 가능해진다. 수신자는 `id` 로 캐시하므로,
같은 URI 가 종류를 갈아타면 그들이 무엇을 들고 있는지 말할 수 없게 된다.

그리고 고정이 **컬럼 하나를 아껴 준다.** 삭제할 때 `object_type` 을 덮어쓸 이유가 없어지고,
`formerType` 을 그 컬럼에서 유도하면 된다.

## 응답표

```txt
직접 저작한 활성 Tombstone   조회가 허용되면 200 + Tombstone.
                            formerType · deleted 는 저자 입력에서 제거된다
삭제된 로컬 객체              삭제 표현을 제공하므로 410 + Tombstone.
                            원본이 Tombstone 이었다면 formerType: Tombstone
영구 삭제된 wp_posts         제공할 표현이 없으므로 404
```

## 바꿀 것

### ① 읽기 410 판정을 저장 상태로

```txt
application-object-read.php:87   'Tombstone' === $result['type'] → 410
```

**타입이 아니라 `object_status` 를 읽는다.** 지금은 둘이 같이 움직여서 참이지만, 저작된
Tombstone 이 들어오면 거짓이 된다 — 생성 즉시 410 으로 나간다.

수신 쪽(`remote-objects.php:249 · :719`)은 **그대로 둔다.** 받은 Tombstone 은 문서밖에 없고
실제로 삭제 통지인 경우가 압도적이다.

### ② Tombstone 을 저작 가능 타입에 추가

`axismundi_op_local_update_types()` 에 더하고, bare `Tombstone` 제출도 `Create` 로 감싼다
(지금은 bare `Note` 만 감싼다).

### ③ 삭제가 `object_type` 을 덮어쓰지 않는다

```txt
지금    object_type = 'Tombstone' 로 덮는다 (local-objects.php:280).
        원래 타입은 문서의 formerType 안에만 남고 **컬럼은 정보를 잃는다**
바꾼 뒤  object_type 은 저작된 타입 그대로.  status 가 삭제를 말하고,
        formerType 은 그 컬럼에서 나온다
```

그러면 "저작된 Tombstone 을 삭제하면 `formerType: Tombstone`" 이 **부작용이 아니라 유도
결과**가 된다. 저작 타입이 Tombstone 이었으니 그렇게 나오는 것이 맞다.

기존 행은 이미 `object_type='Tombstone'` 이라 원래 타입을 잃었다. **마이그레이션하지
않는다** — 내주는 문서의 `formerType` 은 그대로 있으므로 표현은 바뀌지 않고, 바뀌는 것은
컬럼의 정보량뿐이다. 그 사실을 적어 둔다.

레지스트리의 `as2_type` 은 **덮는 것이 맞다** (실측 2026-11-15). 소비처가 답을 정해 준다 —
`application-object-read.php:73` 이 내주는 문서의 `type` 과 `as2_type` 이 다르면
`ax_op_object_read_mismatch` 409 로 거부하고, 인덱스 이름도 `type_observed (as2_type,
as2_type_observed_at)` 다. 즉 **현재 표현**을 가리킨다.

```txt
local_objects.object_type   저작된 타입.  고정
registry.as2_type           현재 표현의 타입.  삭제하면 Tombstone
```

그래서 삭제는 `as2_type` 만 덮고 `object_type` 은 그대로 둔다. 둘을 같이 바꾸거나 같이
놔두면 한쪽이 틀린다.

### ④ 삭제 본문에 시각을 남긴다

지금 삭제 본문은 4 키다 — `id · type · formerType · deleted`. **AP §6.4 의 예시 자체가
`published` 와 `updated` 를 싣는다.**

```json
{ "id": "…/note/72", "type": "Tombstone",
  "published": "2015-02-10T15:04:55Z", "updated": "…", "deleted": "…" }
```

시각은 콘텐츠가 아니므로 "본문·첨부·CW 를 없앤다" 는 의도와 충돌하지 않는다. 그리고 AS2 가
Tombstone 의 목적으로 든 *"there used to be an object at this position"* 은 **순서가 있어야
자리가 된다** — 정렬된 컬렉션에서 `published` 없이는 위치를 잡을 수 없다.

원본 문서의 `published` 를 보존하고 `updated` 는 삭제 시각으로 둔다. 감사의
`4 === count( $tombstone['document'] )` 가 함께 바뀐다. **본문·첨부·CW 가 없다는 단정은
그대로 둔다.**

## 이미 성립해 있는 것 — 새로 만들지 말 것

`formerType` 과 `deleted` 가 **이미 server_set** 이다(`axismundi_op_local_note_server_set()`).
`prepare_local_document()` 가 저자 입력에서 제거하므로 **저작된 Tombstone 은 그 둘을 가질 수
없다.** 새 규칙이 필요 없고, 그래서 우리 출력에서 두 가지가 문서로도 갈린다.

```txt
저작된 Tombstone   { id, type: Tombstone, …저자가 쓴 editable 필드 }
삭제된 객체         { id, type: Tombstone, formerType, published, updated, deleted }
```

다른 서버에서는 전부 optional 이므로 이 신호를 **보장으로 주장하지 않는다.** 우리 저장 계약과
원격 구현의 해석은 따로 검증한다.

## 허용 수열 — Delete 는 종단이다

```txt
Create → Update* → Delete        Delete 뒤에는 아무것도 오지 않는다
```

이미 강제돼 있으므로 **감사로 고정만 한다.**

```txt
Delete 후 Update        ax_c2s_update_state 409
Delete 후 Create 재발행  ax_act_object_deleted
```

**이것은 우리 원장의 정책이지 ActivityPub 이 모든 서버에 강제하는 수열이 아니다.** 코드나
문서에 "스펙이 금지한다" 고 적지 말 것.

## 바꾸지 말 것

```txt
수신 Tombstone 해석    remote-objects 의 status 유도는 그대로
hard delete 의미       본문을 비우고 410 을 내는 현재 동작은 유지.
                      FEP-4f05 의 soft delete 는 **이 슬라이스의 선행 조건이 아니다**
타입 변경              Outbox Update 가 타입 변경을 막는다. 그대로 둔다 — §타입 고정.
                      Update(Tombstone) 로 soft delete 를 하려면 그 금지를 열어야 한다.
                      **열지 않는 쪽이 soft delete 를 더 깨끗하게 만든다**: 표현을 고르는
                      것은 status 이고 문서는 그대로 남으면 되므로, 타입을 바꿀 일이 없다
trash status           만들지 않는다
편집 API               저작된 Tombstone 이 active 라서 편집 대상이 되는지는 여기서 정한다
```

## 수용 기준

```txt
저작          bare Tombstone 과 Create(Tombstone) 둘 다 Create 가 기록되고
              원본이 **활성 Tombstone** 으로 저장된다
서빙          저작 Tombstone(조회 허용) → 200 + Tombstone
              삭제된 객체 → 410 + Tombstone
              **type 만으로 410 을 정하지 않는다** — 상태를 바꿔 가며 잰다
server_set    저자가 formerType · deleted 를 실어도 저장되지 않는다
시각          삭제 본문에 published · updated · deleted 가 있고,
              본문 · 첨부 · CW 는 여전히 없다
타입 불변      Delete 후에도 object_type 이 저작된 타입 그대로다.
              Note 를 지우면 컬럼은 'Note', 문서는 Tombstone(formerType: Note)
이중 삭제      저작된 Tombstone 을 Delete 하면 formerType='Tombstone' 이 된다.
              유도 결과이고 깨지는 것이 없어야 한다
수열          Delete 후 Update 는 409, Create 재발행은 거부
회귀          audit-activitystreams-api 17 · audit-c2s-create-note 55 ·
              audit-c2s-update-refusal 18 · audit-media-upload 39
```

**변이로 깨 볼 것.** 최소 둘 — 읽기를 다시 타입 기준으로(저작 Tombstone 이 410 이 되는지),
`formerType` 을 editable 로(저자가 위조할 수 있는지).

## FEP 는 지금 할 말이 없다

`Create(Tombstone)` 이 저작자 의도와 반대로 읽히는지는 **측정해야 안다.** 수신 구현이 어떻게
처리하는지 재기 전에는 조일 근거가 없고, 재서 아무 일도 없으면 조일 것도 없다. 이 슬라이스는
그 측정을 가능하게 만드는 것까지다.

4f05 에 대한 별도 지적은 이 슬라이스와 무관하게 남아 있다 — 수신 절의 `MUST` fetch 가 그
문서 자신의 캐시 전제와 어긋나고, `Update(Tombstone)` 기각 사유가 한 줄이다. 우리가 수신
Delete 를 구현하지 않았으므로 **"대안을 구현해 봤다" 고는 말할 수 없다.**
