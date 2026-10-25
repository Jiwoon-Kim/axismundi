# Account/Principal 및 Actor Inbox 구현 브리프

2026-10-23. S1–S9를 구분하는 리뷰 기준이다. 완료 선언이 아니다.
Opus가 제공한 DB 행 수는 특정 wp-env 시점의 관측값이며 코드 상수·마이그레이션 전제로 쓰지 않는다.

## 유지할 제품 결정

- User는 WP User, 로컬 Account는 Actor와 같은 운영 엔터티다.
- User–Account 접근 관계는 다중 개인 Account와 공동 운영을 수용한다.
- Actors는 JMAP을 의존하지 않는다. JMAP은 내부 관계를 표준 형식으로 투영한다.
- Principal 주체에 User·로컬 Account·원격 Actor를 표현할 수 있게 하되, 등록만으로 권한이 생기지 않는다.
- internal Actor 기능은 폐기 대상이다. 이번 Inbox 구현이 해당 기능을 필요로 하거나 보장하지 않는다.
- 기존 identity_id, a{identity_id}, 발행 Actor URI는 유지한다.

## 명세와 구현을 구별할 것

[8620 §1.6](https://www.rfc-editor.org/rfc/rfc8620.html#section-1.6)은 User와 Account를 구별하고
다중 Account를 허용한다. 그러나 특정 legacy transport 컬럼의 UNIQUE 자체를 금지하지는 않는다.
문제는 그 컬럼을 전체 소유·접근 모델과 겸용하는 제품 구조다.
isPersonal은 User 기준의 결과지만 그것을 계산할 소유 관계 사실은 저장해야 한다.
owner라는 운영 role만으로 개인 귀속을 자동 판정하지 않는다.

[9670](https://www.rfc-editor.org/rfc/rfc9670.html)은 공유 주체와 구독 계약을 정의한다.
Principal 등록이 HTTP 요청자의 인증을 대신하지 않는다. 계정 접근과 Inbox 읽기·발행 권한도
무조건 같은 권한이라고 가정하지 않고 현재 및 목표 정책을 명시한다.

[AP §5.2](https://www.w3.org/TR/activitypub/#inbox)의 컬렉션·권한·중복 조건을 따른다.
서로 다른 Actor가 endpoint를 공유한다는 사실만으로 위반을 단정하지 않는다.
실제 GET의 Actor별 범위와 인가가 성립하는지 검증한다.

## 슬라이스

| 단계 | 구현·검증 목표 | 현재 상태 |
|---|---|---|
| S1 Principal | stable subject 식별·유형·존재 검증, 기존 User 관계 이전, remote 등록의 무권한성 | 구현·리뷰 완료 (f798f5f) |
| S2 관계 통일 | 개인·위임 접근의 관계 모델 수렴, 개인 귀속과 운영 role 분리, 삭제·철회 정책. **S4 가 측정으로 노출한 결함 1·2 를 받는다** | 대기 |
| S3 transport | legacy 공식 plugin binding과 Account 접근 분리, Actor별 키 생성·저장·회전·서명 정책, **모더레이션 스코프** | 구현·리뷰 완료 (f5fa995) |
| S4 UNIQUE | 유일성을 transport 바인딩으로 이전. 추가 Person 의 독립 키·접근 관계 | 구현·리뷰 완료 ([범위](IMPLEMENTATION-ACCOUNT-CARDINALITY.md)) |
| S5 isPersonal | 인증 User와 관계로 계산. Session 기본값·지원·철회 회귀 검사 | 기존 부분 구현, 재검토. 살아있는 행에서는 파생이 이미 성립(불일치 0) |
| SV 어휘 축소 | internal 제거(생성 즉시 public), actor_scope 제거. 접근 판정은 Principal 관계·role, isPersonal 은 귀속 표현. 시스템 역할은 기능 바인딩 | 결정 완료, 구현 대기 (아래 절) |
| S6 receipts | Activity와 원자적인 Actor별 receipt, 중복·실패·보수적 백필 | 구현·리뷰 완료 (3bc752c) |
| S7 Inbox | Actor UUID URL, Account 인가 GET, 공식 검증기 재사용 POST, 기존 수신 URL 유지 | 구현·리뷰 완료 (3bc752c) |
| S8 activity view | Account 범위 Activity 뷰·query·changes·읽음 상태. AP 문서와 분리. **mailbox 아님** | 대기 |
| S11 state | Account 단위 변경 알림 경로. S8 의 선결 | 대기 |
| S9 instance | shared Inbox 및 Application Actor 소유권 이관 | 대기 |
| S10 acting-actor | 요청의 Account 선택 운반·검증. 기본 선호와 현재 선택 분리. **`axismundi_actors_get_for_user()` 단수 가정 폐기, production 27 파일**. 세 표면(WP REST / JMAP / AP C2S)이 공유할 인증·선택·인가 경계 | 대기 |

S3는 컬럼을 옮기는 것만으로 완료되지 않는다. 추가 Person·Group이 Application 키를
계속 사용하는지 검사하고, 독립 키 및 회전을 실제 지원해야 한다.

**모더레이션 스코프도 S3 범위다.** `local_user_id` 겸용이 세 번째로 표면화한 자리다 --
키 선택, inbox URL, 그리고 수신 차단 판정. 현재 Actor Inbox 는
`Moderation::activity_is_blocked( $activity, $actor->get_local_user_id() ?? 0 )` 로
평가하므로, `local_user_id` 가 없는 Actor 는 수신 Actor 의 차단 목록이 아니라
사이트 범위(`0`)로 판정된다. 오늘은 Org·Group 이 그 상태이고, **S4 로 다중 Person 을
열면 추가 Person 들이 조용히 Actor 별 모더레이션을 잃는다.** S3 에서 수신 차단 판정이
Account 를 기준으로 서도록 함께 옮긴다. 이것은 이번 S6·S7 구현이 만든 결함이 아니라
기존 겸용이 드러난 것이다.
S6 백필은 audience에 남지 않은 route recipient나 과거 follower를 복원할 수 없다.
모르는 수신자를 전체 Actor로 확장하지 않으며 미복원 범위를 보고한다.

S8은 RFC 8621 Email/Mailbox 구현을 주장하지 않는다. Mailbox는 Account 안의 데이터 타입이며,
Activity를 Email로 가장해서 urn:ietf:params:jmap:mail을 선언하지 않는다.
읽음 상태는 원칙적으로 User/Principal–Account–Activity별이다. 공동 Account 사용자끼리
덮어쓰는 Account 공용 상태와 구분한다. 외부 노출 시 별도 vendor capability 계약이 필요하다.
현재는 그런 capability를 만들거나 광고하지 않는다.

**Actor 의 inbox·outbox 는 JMAP mailbox 가 아니다.** JMAP 의 `Mailbox` 는 Account 안의
Email 데이터 타입이고, Actor 의 inbox 는 ActivityPub 컬렉션이다. S8 이 만드는 것은
Account 범위의 Activity 뷰이지 Mailbox 가 아니며, 그 이름을 쓰지 않는다 -- 쓰면 장차
JMAP Mail 이 들어올 자리를 선점하고, 소비자가 `Email/*` 메서드를 가정한다.
워드프레스를 메일 서버로 만드는 것은 현재 계획에 없지만, **그 자리는 비워 둔다.**

## S11 변경 알림 경로

연합 수신은 JMAP 동기화 모델이 보지 못하는 외부 변경원이다. 원격 delivery 가 원장에
inbound 행과 receipt 를 남겨도 그 Account 의 `state` 가 오르지 않으면 `/changes` 가
조용히 누락하고, 클라이언트는 에러 없이 그 활동을 영원히 못 본다.

```txt
변경원   C2S 저작 · AP 수신 · 관리 화면 · 임포트
         → 전부 같은 지점을 통과해 Account 단위 state 를 증분
소비자   JMAP /changes, Push (RFC 8887 WebSocket / RFC 9749 VAPID)
AP 쪽    아무것도 선언하지 않는다. state 는 JMAP 개념이다.
```

이것은 프로토콜 사이의 번역기가 **아니다.** 두 프로토콜은 한 도메인 위의 어댑터이고,
필요한 결합은 번역이 아니라 변경 알림 하나다. S8 은 S11 없이는 동기화되지 않으므로
클라이언트가 폴링하게 된다.

## S3 transport: 주소는 나뉘었는데 키와 차단은 아직 사이트 공용이다

### 측정된 현재 상태 (스테이징, 2026-10-24)

S7 이 배포된 뒤 실데이터로 확인했다.

```txt
Person  @thaumiel999   inbox  /wp-json/axismundi/v1/actors/{uuid}/inbox
Group   @community     inbox  /wp-json/axismundi/v1/actors/{uuid}/inbox
        (local_user_id 없음)
둘 다   endpoints.sharedInbox  /wp-json/activitypub/1.0/inbox   별도 속성
        inbox 미인증 GET 403 / outbox 미인증 GET 200
```

**주소는 액터별로 나뉘었다.** 그런데 같은 문서가 남은 결함도 보여준다.

```txt
publicKey.id   .../actors/{uuid}#main-key
실제 키 재료    Application 키                  ← local_user_id 가 없으므로
모더레이션      activity_is_blocked( $activity, $actor->get_local_user_id() ?? 0 )
                → Group 수신이 그 Group 의 차단이 아니라 사이트 범위로 판정
```

지금이 가장 어긋난 상태다 — **자기 주소로 받는데 사이트 키로 서명하고 사이트 기준으로
차단한다.**

문서가 거짓을 말하는 것은 아니다. 발행된 `publicKeyPem` 이 실제로 서명한 키이므로
검증은 정상이고 원격이 속는 것도 아니다. 비용은 **격리와 회전**에 있다: 독립 정체성
여럿이 키 하나를 공유하므로 한 키의 유출이 비-user 액터 전부에 닿고, 한 액터만 키를
바꾸는 것이 불가능하다.

### S3 가 하는 일

2026-10-24: 바인딩 분리와 Account Block은 S3a로 구현했다.
[구현 범위](../../axismundi-activitypub-bridge/docs/IMPLEMENTATION-TRANSPORT-SCOPE.md).
S3a 시점에는 기존·신규 모두 provider를 보존했다. 현재 S3b는 신규 Actor의 독립 키와
기존 Actor의 명시 회전을 구현했다. [키 수명주기](IMPLEMENTATION-SIGNING-KEYS.md)의
독립 리뷰 전에는 S4를 열지 않는다. 기존 Actor의 provider는 명시 회전 전까지 유지한다.

```txt
1. transport 바인딩을 자기 컬럼으로 분리
   local_user_id 의 "어느 공식 키·inbox 인가" 역할만 옮긴다.
   Bridge 가 접근 관계가 아니라 그 컬럼을 읽는다.
2. Actor 별 키 생성·저장·회전
3. 모더레이션 스코프를 Account 기준으로
```

### 키 회전은 연합 사건이다 — 마이그레이션의 부수효과로 만들지 않는다

지금 Application 키를 쓰는 액터에게 자기 키를 주면 **발행된
`publicKey.publicKeyPem` 이 바뀐다.** 그 값을 캐시한 원격 서버는 재조회 전까지
서명 검증에 실패한다. 대개 실패 시 Actor 문서를 다시 가져가므로 self-heal 되지만,
그 구간이 존재한다.

```txt
핀   기존 액터는 명시적 회전 전까지 현재 키를 유지한다.
     마이그레이션이 전체 키를 한 번에 바꾸지 않는다.
핀   새로 만들어지는 액터는 처음부터 자기 키를 받는다.
핀   publicKey.id 는 #main-key 를 유지한다. 재료가 바뀌어도 식별자는 같다 —
     이것이 통용되는 회전 방식이다.
핀   회전은 관찰 가능한 사건으로 기록한다. 언제 무엇이 바뀌었는지 남는다.
```

즉 S3 는 **능력을 만드는 것**이지 전수 교체가 아니다. 교체는 별도 운영 결정이다.

### 모더레이션은 교체가 아니라 두 층이다

```txt
사이트 수준   공식 Moderation — 인스턴스 차단 목록. 계속 적용한다.
Account 수준  우리 Block 관계 — 그 Account 의 차단. 추가로 적용한다.
```

우리에게는 이미 Block 모델이 있다 (`axismundi-activities/includes/relations.php`:
Follow 와 Block 이 root relation 이고 Accept·Reject·Undo 가 그 위에 선다).
공식 `Moderation::` 호출은 `actor-inbox.php:72` 한 곳뿐이다. 그 호출을 없애는 것이
아니라, **그 뒤에 Account 수준 판정을 더하는 것**이다. 사이트 차단은 사이트의
정당한 권한이고, Account 차단은 그 Account 의 권한이다.

### 순서: S3 를 S2 보다 먼저

브리프의 기존 의존은 `S3 -> S4` 뿐이고 `S2 -> S3` 는 없다. 그런데 S3 를 먼저 하는
편이 낫다.

```txt
지금      local_user_id = 접근 + transport + 카디널리티   세 의미
S3 이후   local_user_id = 접근 + 카디널리티               두 의미
S2 가 그 둘을 정리한다 (관계로 수렴할지, 컬럼에 불변식을 붙일지)
S4 가 UNIQUE 를 떼어낸다
```

의미 하나를 먼저 빼내면 S2 가 남은 둘만 다루면 된다. 반대로 S2 를 먼저 하면
`local_user_id` 가 transport 때문에 계속 살아 있어야 해서, 접근을 관계로 옮기고도
컬럼을 못 치운다.

두 단계가 같은 파일(`repository.php`)의 스키마를 건드리므로 **병행하지 않는다.**

### 검증

```txt
Group·Organization 액터가 자기 키로 서명한다. Application 키를 쓰지 않는다.
기존 액터의 publicKeyPem 이 마이그레이션만으로 바뀌지 않는다.
회전 후에도 publicKey.id 가 #main-key 로 유지된다.
Group 수신이 그 Group 의 Block 으로 판정된다. 사이트 차단도 여전히 적용된다.
S4 를 열기 전에 두 번째 Person 이 자기 키·자기 inbox 로 연합하는지 확인한다.
변이: transport 바인딩 분리를 되돌리면 실패해야 한다.
변이: Account 수준 차단 판정을 제거하면 실패해야 한다.
```

### 측정 제약

스테이징은 wp.com 호스팅이라 **WP-CLI 접근이 없다.** S3 의 키 관련 검증은 공개
Actor 문서(`publicKey.publicKeyPem` 비교)와 감사로만 확인할 수 있고, DB 수치는
확인할 수 없다.

## S4 UNIQUE: 유일성은 사라지는 것이 아니라 옮겨간다

### 선결 조건은 충족됐다

```txt
S3a  transport 바인딩 분리          완료 (e1d3ff4)
S3b  Actor 별 독립 키 + 명시적 회전  완료 (f5fa995)
```

### 측정된 현재 상태 (2026-10-24)

```txt
wp_ax_actors 인덱스
  local_user_id           UNIQUE      ← S4 가 뗄 것
  transport_user_id       비-UNIQUE   ← 아무 제약이 없다
  personal_owner_user_id  비-UNIQUE

로컬 Actor 476
  upstream_user         289
  upstream_application  187
  actor                   0           ← 자기 키를 쓰는 Actor 가 아직 없다
```

### 핵심: 제약을 떼는 것이 아니라 옮기는 것이다

`axismundi_actors_get_transport_actor_for_user()` 는 `LIMIT 2` 로 조회해
**정확히 1건일 때만** Actor 를 돌려준다. 지금 그 1건을 보장하는 것은
`local_user_id` 의 UNIQUE 다. 그것을 떼면 보장이 사라지고, 한 User 에
`upstream_user` 바인딩이 둘 생기는 순간 **레거시 inbox 라우트가 조용히 null 이 된다** --
원격이 캐시한 구 주소로 온 전달이 수신자를 못 찾는다.

```txt
핀   User 당 구 per-user route 바인딩은 최대 하나다. upstream_user 및 회전한 actor 모두 포함한다.
     local_user_id 의 UNIQUE 를 떼기 전에 그 제약을 transport_user_id 쪽에 세운다.
     nullable UNIQUE로 NULL 아닌 transport_user_id 전체를 보호한다.
     upstream_application 및 추가 Account는 NULL이므로 다중 행이 가능하다.
     upstream_user에만 한정한 부분 제약이면 회전한 actor와의 충돌을 막지 못한다.
```

### 새 Actor 경로는 실데이터로 돌아본 적이 없다

`transport_kind='actor'` 인 행이 **0** 이다. S3b 가 만든 독립 키 경로의 첫 실사용자가
S4 다. 따라서 S4 는 UNIQUE 를 떼는 작업이 아니라 **새 Actor 생성 경로의 첫 검증**이다.

```txt
핀   추가 Person 은 transport_kind='actor' 로 생성되어 자기 키를 받는다.
     upstream_application 으로 떨어지면 안 된다 -- S3 가 고친 바로 그 상태로
     되돌아가는 것이고, 조용히 일어난다.
```

### 접근 관계도 같이 생겨야 한다

S4 는 S2 보다 먼저 **복수 개인 Account 라는 조건을 만든다.** 그러면 개인 귀속이
컬럼(`personal_owner_user_id`)에만 있고 관계에는 없는 행이 생길 수 있다.

```txt
핀   추가 Person 생성이 접근 관계 행도 함께 만든다.
     컬럼만 채우고 관계를 비우면 S2 가 수렴시킬 때 둘이 어긋난 채로 발견된다.
```

### 검증

```txt
추가 Person 이 자기 키로 서명한다. Application 키를 쓰지 않는다.
추가 Person 의 inbox 가 자기 주소다. shared 로 떨어지지 않는다.
한 User 의 두 Person 이 서로의 수신을 보지 못한다.
레거시 inbox 라우트가 여전히 정확히 하나의 Actor 로 해소된다.
Account 수준 Block 이 추가 Person 에도 적용된다 (S3a 의 성질이 actor 종류에도 유지되는지).
모더레이션이 사이트가 아니라 그 Account 기준으로 판정된다.
변이: transport 유일성 제약을 떼면 레거시 라우트 검사가 실패해야 한다.
변이: 추가 Person 을 upstream_application 으로 만들면 키 검사가 실패해야 한다.
```

### S4 리뷰 결과: 그 경로가 처음으로 돌았다 (2026-10-24)

`transport_kind='actor'` 가 0 이었다는 것은 **독립 키 경로가 실데이터로 한 번도 실행된
적이 없었다**는 뜻이다. wp-env 에서 기존 User 1 에 두 번째 Person 을 실제로 만들어
돌렸다.

```txt
기존 199    upstream_user  transport_user_id=1  local_signing_key_id=NULL
신규 51486  actor          transport_user_id=NULL  local_signing_key_id=158

키          secret_box 2315 바이트, 복호 성공, fingerprint 가 행과 일치
공개        publicKey.id = .../actors/{uuid}#main-key
            공개된 PEM 의 fingerprint == 저장된 fingerprint
서명         private 로 서명 → 공개된 half 로 검증 성공
구별        레거시 199 가 공개하는 키와 다른 키다
inbox       /wp-json/axismundi/v1/actors/{uuid}/inbox  — UUID 별로 분리
접근 관계    wp_user/1/owner 행이 생성과 함께 생겼다
레거시 라우트 user 1 → 여전히 identity 199. 신규가 가로채지 않는다
```

`actor_scope='user'` 로 만들었을 때 `status` 가 `internal` 이면 `publicKey` 가 비는데,
이는 결함이 아니라 **비공개 Actor 를 광고하지 않는 정상 게이트**다 (`transport.php` 의
`'public' !== $actor->get_status()`). `public` 으로 올리면 위 표대로 나온다.

### 변이: 핀은 이중이다

```txt
변이 route    $route_user 의 기존-Actor 검사 제거
              → FAIL: Second Person with same legacy user link can be stored
              UNIQUE 가 legacy_transport_user 에서 실제로 막고, 감사가 알아챈다

변이 appkey   생성 insert 의 transport_kind 를 upstream_application 으로
              → 세 감사 모두 PASS. 단독으로는 아무것도 증명하지 못한다
```

두 번째 변이가 통과한 것은 감사 공백이 아니다. `store_signing_key()` 가 **키 삽입과
같은 statement 로** `transport_kind='actor'` 를 쓰기 때문에 insert 한 값이 살아남지
못한다. 즉 `transport_kind` 와 `local_signing_key_id` 는 **구조적으로 갈라질 수 없다.**

그 구조 보호까지 함께 떼면 감사가 잡는다.

```txt
변이 appkey + store_signing_key 의 transport_kind 기록 제거
              → FAIL: New Actor uses independent provider
```

S3b 의 소유자 검사와 같은 모양이다 — 핀이 두 겹이라 한 겹만 떼면 변이가 무효가 된다.

### 신규 설치와 마이그레이션이 같은 상태로 수렴한다

마이그레이션 경로는 wp-env 의 기존 DB 로 측정됐지만 **신규 설치 경로는 아직 돌지 않았다** --
기존 사이트에서는 영원히 돌지 않고 새 사이트에서만 돌므로, 선언을 읽는 것으로는
증명되지 않는다. 선언한 DDL 을 임시 테이블로 실제 실행해 인덱스를 읽었다.

```txt
신규 설치 (dbDelta 실제 실행)
  legacy_transport_user   Non_unique=0   transport_user_id
  local_user_lookup       Non_unique=1   local_user_id
  personal_owner_user_id  Non_unique=1   personal_owner_user_id
  local_user_id 의 UNIQUE: 0 건

마이그레이션된 설치 (wp-env 실측)  동일
```

두 경로가 갈라지지 않는다. 마이그레이션은 advisory lock 으로 직렬화되고, 중복 route
가 있으면 **DDL 을 하나도 실행하기 전에** 중단하며, 새 UNIQUE 를 세운 다음에 옛
UNIQUE 를 뗀다 -- 보호가 없는 창이 생기지 않는다.

### 전수 상태 (측정, 정리 후)

```txt
actors 738   orphan actor rows 0   keyless local public Person 0
upstream_user 289 / upstream_application 187 / NULL 262
```

`upstream_application` 187 은 전부 레거시 **Group 65 · Organization 121 · Application 1**
이고 Person 은 없다. S4 의 범위는 Person 이므로 이들은 S9 로 남는다.

### 생성 흐름은 여전히 닫아 둔다

S4 가 스키마를 열더라도 **UI·REST 공개는 별도 판단**이다. 생성이 열리는 순간
위 경로들이 실제 트래픽을 받으므로, 감사가 먼저 서고 나서 연다.

### S4 가 열어 놓은 것: User 하나당 Actor 하나라는 가정 (2026-10-24 측정)

S4 는 스키마를 정확히 열었다. 그런데 **그 가정에 기대고 있던 코드가 함께 열리지는
않았다.** S4 의 검증 목록에 없던 자리이고, 측정해서 찾았다.

#### 결함 0 — 뒤집힌 불변식을 단정하는 감사가 남아 있었다

전수 실행에서 `audit-repository.php` 가 실패했다.

```txt
[FAIL] a second actor for the same user is rejected
```

S4 가 의도적으로 허용한 것을 **거부되어야 한다고 단정하는** 감사다. S4 이전에는
`local_user_id` UNIQUE 가 그것을 보장했고 그 단정이 맞았다. 제약을 옮긴 순간 틀려졌다.

놓친 이유는 범위다. 구현 보고가 돌린 것은 S4·ownership·account operations·signing
keys·Actor Inbox·JMAP Session 여섯 건이고, 그 여섯 건은 전부 통과한다. `audit-repository`
는 그 목록에 없었다.

```txt
핀   불변식을 옮기는 변경은 그 불변식을 단정하는 감사 전체를 돌려야 한다.
     새로 쓴 감사가 통과하는 것은 **옛 감사가 아직 맞다는 증거가 아니다.**
```

단정을 새 불변식으로 교체했다. 옛 단정이 지키려던 것 -- 구 주소가 엉뚱한 Actor 로
옮겨가지 않는 것 -- 은 사라지지 않았으므로 그것을 명시적으로 단정한다.

```txt
[PASS] a second actor for the same user is accepted
[PASS] the second actor reserves no legacy route and holds its own key
[PASS] the legacy per-user route still resolves to the first actor
== 20 checks, 0 failed ==
```

#### 결함 1 — User 삭제가 Person 하나만 묘지로 보낸다

`axismundi_actors_tombstone_for_user()` 는 `axismundi_actors_get_for_user()` 를 쓰고,
그 함수는 **Actor 하나**를 돌려준다. 실제 `wp_delete_user()` 로 측정했다.

```txt
삭제 전   51521 S4 Probe A  public     키 복호 DECRYPTS
          51522 S4 Probe B  public     키 복호 DECRYPTS

삭제 후   51521 S4 Probe A  tombstone  snapshot blocked      ← 처리됨
          51522 S4 Probe B  public     snapshot DECRYPTS     ← 남는다
          user 51396 존재: no
```

두 번째 Person 은 **없는 User 를 가리킨 채 public 으로 남고 그 키는 계속 복호된다.**
`signing_key_snapshot()` 의 `status NOT IN ('disabled','tombstone')` 가 fail-closed 로
동작하지만, 그 조건이 참이 되지 않으므로 보호가 걸리지 않는다. S4 이전에는 UNIQUE 가
"하나뿐"을 보장해서 이 함수가 맞았다. 제약을 옮긴 순간 조용히 틀려졌다.

#### 결함 2 — 삭제가 키 저장소에 닿지 않는다

Actor 행과 identity 행이 사라져도 서명키 행은 남는다. 오늘 감사 실행이 남긴 것을
측정했다.

```txt
고아 서명키 28   전부 secret_box 보유, 전부 복호 가능
                 전부 retired_at = NULL  ← 폐기가 아니라 활성으로 읽힌다
                 전부 identity 행 없음
```

유일한 운영 삭제 경로는 `deleted_user` → 상태 변경뿐이고 키를 건드리지 않는다.
사용 경로가 join 으로 막혀 있어 **오용은 아니고 보존의 문제**다. 그러나 삭제된 Account
의 개인키가 사이트 키로 복호 가능한 상태로, 활성 표시로 남는다.

#### 결함 3 정정 — 모호해진 것이 아니라 의미가 바뀌었다

처음에 "27 파일이 조용히 하나를 고른다"고 적었는데 **틀렸다.** S4 가 같은 커밋에서
`get_for_user()` 를 고쳤다.

```diff
- return $user_id > 0 ? axismundi_actors_query_one( 'a.local_user_id = %d', $user_id ) : null;
+ return axismundi_actors_get_transport_actor_for_user( $user_id );
```

임의의 첫 행이 아니라 **구 per-user 라우트를 예약한 Actor** 를 돌려준다. 유일 제약이
걸린 컬럼으로 조회하므로 결정적이다. 측정했다.

```txt
두 Person 모두 public       get_for_user -> 51570 (라우트 보유자)
라우트 보유자 tombstone     get_for_user -> 51570 (status tombstone 인데도 반환)
라우트 보유자 행 제거       get_for_user -> NULL   ← public Person 1 이 남아 있는데도
```

그래서 남는 성질은 모호성이 아니라 둘이다.

```txt
상태를 안 본다    tombstone 된 Actor 도 그대로 돌려준다. S4 이전 쿼리도 같았으므로
                  S4 의 회귀가 아니다. 보호는 호출부나 snapshot 의 status 조건에 있다.
NULL 의 뜻이 좁다 "이 User 에게 Actor 가 없다" 가 아니라 "구 라우트가 예약되어 있지
                  않다" 다. 쓸 수 있는 Person 이 남아 있어도 NULL 이 나온다.
```

production 호출 27 파일(플러그인 10: activities, activitypub-bridge, actors, calendar,
contacts, forum, media-library, note, notifications, object-projections)은 전부 이
좁은 뜻을 물려받는다. 호환 getter 로서는 맞고, **"이 User 의 활동 주체"** 를 묻는
자리에서는 틀린다. 그 구분이 S10 이 할 일이고, 이제 드리프트가 아니라 정의된 질문이다.

#### 소속

0 은 이 리뷰에서 고쳤다. 나머지는 S4 의 범위가 아니다. 1·2 는 결함이고 codex 가 S2 로 분리해 둔 삭제·전송
정책이 받는다. 3 은 결함이 아니라 **의미 변경**이고 S10 의 범위다. 다만 이것이 S10 의
성격을 바꾼다 -- "흩어진 폴백 정리" 가 아니라 **"User 하나당 Actor 하나라는 가정의
폐기"** 이고, 27 파일이 그 작업량이다.

생성 UI·REST 가 닫혀 있는 동안은 1·2 에 실트래픽이 닿지 않는다. 그 순서를 지킨
것이 S4 의 "생성 흐름은 여전히 닫아 둔다" 였고, 여기서 값을 한다.

## 상태와 범위 어휘를 줄인다 (소유자 결정, 2026-10-25)

두 결정이 같은 방향이다. **분류를 저장하지 않고 판정한다.**

```txt
결정 1   status 에서 internal 을 없앤다.      public | disabled | tombstone
결정 2   actor_scope 를 없앤다.               isPersonal + principal 보유로 판정
```

### internal 은 자기 이유보다 오래 살았다

원래 이유는 user↔actor 강결합이었다 -- Like·Create 의 주체가 있어야 해서 User 당 기본
Actor 를 자동 생성하고, tombstone 아닌 비활성을 표현할 칸이 필요했다. 그 전제가 S4 로
사라졌고, 자동 생성은 이미 일어나지 않는다.

```txt
WP user 151 중 Actor 가 없는 user 124
admin.php 는 GET 도달 생성을 의도적으로 제거함
  "an absent actor is simply the un-activated state, and creation happens in
   the activation POST"
```

즉 **Actor 행이 없는 것**이 이미 미활성의 표현이고, JMAP 도 Account 0 개를 그대로
표현하므로(RFC 8620 §2 `primaryAccounts`: "there MAY be no entry for a particular URI,
even though that capability is supported by the server") 거기서 오는 압력도 없다.

남은 internal 78 행은 용도가 바뀌어 있었다.

```txt
Group(managed)         38   핸들 잠금 38
Organization(managed)  36   핸들 잠금 36
Person(user)            4   핸들 잠금  2
```

**판정: 공개가 맞다.** 실제 발견은 WebFinger 와 follow 에서 일어나므로, 내용 없는 Group
이 조회 가능해지는 것은 비용이 아니다. 공개 전 상태를 위해 어휘 한 칸을 유지할 이유가
되지 못한다. 철회는 `disabled`, 종결은 `tombstone` 이 이미 맡는다.

```txt
핀   마이그레이션은 internal 78 행을 public 으로 올린다.
     status 기본값도 internal 에서 public 으로 바뀐다 (DATA-MODEL §2.3).
```

### 마이그레이션이 발화시키는 것 (측정)

status 변경은 순수한 컬럼 갱신이 아니다. 두 리스너가 붙어 있다.

```txt
webfinger.php:151   status_changed → sync_local_acct_address
                    handle@authority 를 acct 원장에 기록한다. 결정의 의도된 효과다.
forum/repository.php:290  status_changed → sync_published_group ('public' 일 때만)
                    바인딩된 Group 의 community settings 행을 만든다.
                    쓰기이지만 게시물을 발행하지는 않는다 (기존 settings 행 132).
calendar            handle_registered 에만 붙어 있어 발화하지 않는다.
                    다만 backfill 이 handle_locked_at 기준이라 상태와 무관하게 포괄한다 -- 공백 없음.
```

### 해결되는 모순 하나

`signing_key_snapshot()` 의 조건은 `status NOT IN ('disabled','tombstone')` 이어서
**internal Actor 는 서명은 되는데 키는 공개되지 않았다** -- 받는 쪽이 검증할 수 없는
서명이다. internal 이 없어지면 서명 가능 집합과 광고 집합이 같아진다. 별도 수정이
필요한 결함이 아니라 이 결정의 부수 효과로 닫힌다.

### 남은 결정 하나 — `ensure_for_user()`

internal 을 만드는 경로가 둘 남아 있다.

```txt
repository.php:2783  seed() 가 플러그인 활성화 시 현재 관리자에게 만든다
admin.php:100        권한 있는 관리자가 없는 Actor 를 요청할 때 만든다
```

internal 이 없어지면 이 둘은 **아무도 선택하지 않은 정체성을 public 으로 발행한다.**
`admin.php` 가 GET 생성을 제거한 이유와 같은 성질이다.

```txt
권고   seed() 의 생성을 없앤다. 부재가 미활성이라는 것이 이미 문서화된 계약이고,
       생성은 nonce 를 운반하는 activation POST 에만 둔다.
```

**상태: 확정 대기.** internal 제거와 함께 묶지 않는다 -- 어휘 정리가 아니라 동작
변경이고, 승인 없이 자동 생성을 끄면 관리자가 활성화 직후 기대하던 Actor 가 사라진다.

### actor_scope 를 없앤다 — 다만 측정된 일치를 불변식으로 바꾸지 않는다

먼저 방법론 정정. 앞선 판에서 `scope='site' ≡ actor_type='Application'` 을 "불일치 0"
으로 적었는데, 그 값은 **행 하나에서 계산한 것**이다. n=1 에서 일치는 우연과 구별되지
않으므로 치환 근거가 되지 못한다. 그리고 Application 은 **프로토콜 타입**이라 복수
Application Account 를 허용할 수 있으니, 타입으로 인스턴스 역할을 식별하면 틀린다.

```txt
scope='managed'  ≡ principal 관계 보유       191/191        ← 치환 근거로 쓸 수 있다
scope='user'     ≡ personal_owner IS NOT NULL  살아있는 행 불일치 0
                                               불일치 253 은 전부 삭제된 User 의 tombstone
scope='site'                                   행 1개. 근거 없음.
remote actor                                   scope 가 항상 NULL -- 연합 개념이 아니다
```

### site 는 공식 플러그인의 운영 모드가 남긴 흔적이다

공식 플러그인은 프로필 모드를 운영 설정으로 둔다 -- Author Profiles Only / Blog profile
only / Both. 그리고 특수 Actor 를 user id 공간의 sentinel 로 식별한다 (원본 확인,
`includes/collection/class-actors.php`).

```txt
const BLOG_USER_ID = 0;           블로그 대표
const APPLICATION_USER_ID = -1;   시스템 서명
```

**공식도 둘을 구분한다.** 결함은 "안 나눴다" 가 아니라, Actor 정체성이 WP user id 라서
WP user 가 아닌 주체를 sentinel 로 표현할 수밖에 없다는 것이다. 우리는 User 와 Actor 를
이미 분리했으므로 sentinel 도, 그것을 받아 적은 `site` 계층도 필요 없다.

우리 쪽에서 그 흔적이 어떻게 남았는지는 한 행에 그대로 보인다.

```txt
identity 25564   local_handle_key    'blog'                 ← 공식의 블로그 대표 이름
                 transport_kind      'upstream_application'  ← 공식의 시스템 키를 빌림
                 status              disabled
                 local_signing_key_id NULL
```

공식이 나눠 둔 둘을 우리가 한 행에 합쳤다. 그러므로 분리해야 하는 것은 이렇다.

```txt
블로그 대표 Account   사이트 콘텐츠를 발행·공유하는 편집 주체. 여러 Account 중 하나다.
시스템 Actor          인스턴스 서명 등 시스템 기능을 맡은 주체.
```

둘은 겹칠 수 있으나 같지 않다. 대표 Account 가 시스템 서명을 맡을 이유도, 시스템 Actor
가 모든 글의 발행 주체일 이유도 없다.

### 시스템 액터를 특별 취급하지 않는다

별도 시스템 역할 분류조차 두지 않는다. **"시스템" 은 액터의 종류가 아니라 맡긴 용도**
이므로, 기능 설정이 Account 를 지목하면 된다 -- 인스턴스 서명 설정이 `accountId` 를
지정하고 그 Account 의 키로 서명한다. 접근은 다른 Account 와 완전히 같게 판정한다.

```txt
핀   actor_type       프로토콜 타입 (Person·Group·Service·Application). 역할이 아니다.
     기능 바인딩      시스템 기능이 Account 를 지목한다. 저장된 분류가 아니다.
     isPersonal       개인 귀속의 표현. **권한이 아니다.**
     Principal + role 접근·발행·관리 여부는 전부 여기서 판정한다.
```

`isPersonal` 로 권한을 판정하지 않는다는 것이 특히 중요하다. 개인 귀속은 "누구의
것인가" 이고 권한은 "누가 무엇을 할 수 있는가" 여서, 전자로 후자를 대신하면 공유
Account 에서 곧 깨진다.

### `<> 'site'` 가드는 권한을 지탱한 적이 없다 (측정)

권한 층의 scope 용법은 거의 전부 `<> 'site'` 다. 그 가드가 실제로 무엇을 막는지 재 보면
**이미 principal 검사가 같은 일을 하고 있다.**

```txt
identity 25564 에 대해
  ax_actor_managers 행           0
  account_role(admin 1)          ''        ← principal 검사만으로 거부
  resolve_workspace(admin 1)     refused
  is_public_profile              no
  local_public_key               none
  signing_key_snapshot           blocked
```

`can_access_workspace()` 는 scope 가드와 `account_role() !== ''` 를 **둘 다** 들고 있고,
뒤의 것만으로 결과가 같다. 즉 가드는 분류 단축이었고, 남겨 두면 오히려 해롭다 -- 나중에
그 Account 에 principal 을 부여해도 scope 가 막아 버린다.

```txt
핀   `<> 'site'` 가드들은 다른 술어로 치환하지 않고 **제거한다.**
     판정은 principal 관계와 status 가 이미 하고 있다.
```

### 우리 시스템 액터는 아직 없다

정정해 둔다. 앞서 인스턴스 서명 정체성이 있는 것처럼 쓴 대목이 있었다면 부정확하다.

```txt
우리 쪽    repository.php:2770  'blog' / scope=site 행을 만들지만 의도적으로 disabled.
                                "실제 Instance Actor 설계 전까지 비활성"
공식 쪽    transport.php:39     Bridge 가 upstream_application 바인딩에서 공식 키를 가져다 쓴다
```

둘 다 우리 시스템 액터 구현이 아니다. 현재는 공식 키 의존이 남아 있고, 구현 대상은 S9 다.

## S10 acting-actor: 암묵 폴백을 단일 해소기로

### 요청 운반 계약 (후속 구현)

- WP 관리 화면·기본 프론트는 Account 선택 쿠키를 운반하고 앱·JMAP은 명시적인 accountId를 운반한다.
- 쿠키는 권한 증명이 아니다. 서버가 매 요청 공통 인가 서비스로 재검증한다.
- 현재 활동 선택과 사용자 기본 선호는 구분한다. primaryAccounts는 capability별 기본값이며 현재 선택 상태가 아니다.
- 사용자 기본 선호는 명시적으로 설정한다. 기존 acting-actor usermeta를 자동으로 기본 선호로 간주하지 않는다.
- 선택 쿠키 수명은 실제 로그인 세션 만료에 맞춘다. auth_cookie_expiration 기간만 새로 더해서 기존 인증보다 오래 살게 하지 않는다.
- 로그아웃·인증 사용자 변경 시 이전 선택을 제거하거나 무효화한다. 쿠키 범위·보안 속성·CSRF 및 다중 탭 정책은 구현 시 검증한다.
- 철회된 선택으로 쓰기를 시도했을 때 다른 Actor로 조용히 재발행하지 않는다. 기본값 제안과 실제 쓰기 재시도는 구분한다.

쿠키 구현은 S1/S5 Session 감사의 선결이 아니다.

Session 이 대체하는 것은 **목록 반쪽**이다. "내가 무엇으로 활동할 수 있는가"는
workspace/Session 이 답한다. **선택 반쪽**은 대체되지 않는다 -- JMAP 에는 전환 명령이
없고 요청마다 `accountId` 를 명시하므로 "현재 선택"이 서버에 필요 없지만, 우리
acting-actor 는 **발행 귀속**을 정하고 블록 에디터·관리 화면·REST 는 `accountId` 를
보내지 않는다.

소비자를 센 결과 5개 플러그인이다 (2026-10-23):

```txt
activities   c2s.php, publish-command.php
actors       acting-actor.php
calendar     admin-calendars, envelope, event-actor, event-participation,
             jscalendar, projection, schema
emoji        catalogue.php
```

### 쿠키가 닿지 않는 표면이 이미 있다 (공식 앱 측정, 2026-10-25)

"관리 화면·기본 프론트는 쿠키, 앱·JMAP 은 accountId" 로 나눴는데, **공식
WordPress 앱이 그 둘 중 어디에도 들어가지 않는다.** Android 소스에서 업로드 전송
선택 로직을 직접 읽었다 (`fluxc` `MediaStore.java:974` `performUploadMedia()` 의
`switch`, 선택자는 같은 파일의 `getMediaRestClientType()`).

```txt
site.isUsingWpComRestApi()       → WPCOM_REST            MediaRestClient
site.isUsingSelfHostedRestApi()  → SELF_HOSTED_RS        MediaRSApiRestClient
site.isJetpackCPConnected()      → JETPACK_CP            WPComV2MediaRestClient
ORIGIN_WPAPI + 앱 설정 활성      → APPLICATION_PASSWORDS ApplicationPasswordsMediaRestClient
그 외                            → XMLRPC                MediaXMLRPCClient
```

우리 같은 자체 호스팅 사이트에 닿는 것은 아래 둘이다.

```txt
Application Passwords   코어 REST /wp/v2/* + **토큰 인증**. 브라우저 쿠키가 없다.
XML-RPC                 아직 기본 폴백이다. 헤더로 accountId 를 실어 보낼 자리가 없다.
```

두 가지가 따라온다.

```txt
① JWT 를 새로 도입할 이유가 줄어든다.
   공식 앱이 자체 호스팅에 쓰는 것이 이미 Application Passwords 다.
   토큰 **형식**을 고르는 문제가 아니라 범위·인가 모델이 문제라는 판단을 뒷받침한다.
② 쿠키만으로는 선택을 운반할 수 없다.
   Application Passwords 요청은 쿠키를 공유하지 않고, XML-RPC 는 실을 자리가 없다.
   → 이 표면들은 **명시적으로 설정된 기본 Account** 로 해소되어야 하고,
     그것이 "저장된 선택을 없앨 수 없다" 의 두 번째 이유다.
     첫 번째는 블록 에디터·관리 화면이 accountId 를 안 보내는 것이었다.
```

```txt
핀   XML-RPC 로 들어온 쓰기를 임의의 Account 로 귀속시키지 않는다.
     기본 Account 가 명시되지 않았으면 발행 귀속을 추측하지 말고 거부하거나
     귀속 없이 저장한다. 조용한 선택이 S10 이 없애려는 바로 그 동작이다.
```

측정하지 않은 것: iOS, 실제 앱으로 업로드해 본 것, `MediaRSApiRestClient` 의 정체
(이름상 Rust 기반 클라이언트로 보이나 확인하지 않았다). 글·댓글 경로는 이전 조사에서
WP.com REST / XML-RPC 로 갈린다는 것까지만 확인됐고 이번 측정은 **미디어 업로드**다.

### WP User ↔ Account 연결에 JMAP 이 맞는 자리

맞다. 다만 **노출 표면과 저장 위치를 구별한다.**

```txt
저장    ax_actor_managers 의 principal 관계 + personal_owner_user_id.
        JMAP 이 없어도 존재하고, 권위는 여기다.
노출    JMAP Session 의 `accounts` -- RFC 8620 §2 "each account the user has access to".
        "이 인증된 User 가 어느 Account 로 활동할 수 있는가" 를 묻는 **정규 표면**이다.
        기본값은 `primaryAccounts` 로 같이 내려간다.
인가    세 표면(WP REST / JMAP / AP C2S)이 **같은 principal 검사**를 호출한다.
```

```txt
핀   WP REST 와 AP C2S 가 JMAP 을 경유하지 않는다. 같은 관계를 읽을 뿐이다.
     JMAP 이 목록의 정규 표면이라는 것과, 다른 표면이 그것을 호출해야 한다는 것은
     다른 주장이다. 후자를 택하면 JMAP 이 인가 경로가 되어 비활성화할 수 없게 된다.
```

Contacts 가 Actor 와 연결되려면 JMAP 이 있어야 한다는 것도 같은 이유다 -- 그쪽이
필요한 것은 Session 의 Account 목록이고, 권한 자체가 아니다.

### 저장된 선택은 없앨 수 없다

WordPress 관리 화면과 기본 프론트엔드는 페이지 요청의 쿠키로 현재 Account 선택을
운반할 수 있다. 그 표면은 서버가 검증한 선택을 적용해야 한다.
우리 React 앱은 요청마다 `accountId` 를 명시할 수 있지만, 최초 로드의 기본값은
마찬가지로 필요하다.

스펙에 이미 그 개념이 있다. `primaryAccounts` 는 capability 별로 *"the account a user
should treat as their default for that capability"* 를 알려준다. 서버가 **기본값을
제공**하고 호출자가 명시하는 모양이다.

```txt
오늘        아무도 명시하지 않으면 서버가 조용히 적용   ← 깨지는 지점
바뀔 것     요청에 선택을 운반하고 해소기 한 곳에서 검증. 표면별 폴백 금지.
남을 것     사용자의 기본 Account 선택 자체.
            JMAP 은 primaryAccounts 로 노출하고,
            모놀리식 표면의 현재 선택은 별도 쿠키로 운반한다.
```

즉 S10 은 **폐기가 아니라 단일화**다. 없애는 것은 "표면마다 각자 폴백"이고,
남기는 것은 "현재 Account 선택"과 "사용자의 명시적 기본 선호"다.

```txt
해소기   명시된 accountId 가 있으면 그것 + 권한 검사
         모놀리식 표면은 운반된 쿠키 선택 + 권한 검사
         없으면 기본값 제안. JMAP 메서드의 필수 accountId를 쿠키로 보충하지 않음
         철회되었으면 전이를 알리고 쓰기 중단. 다른 Actor로 조용히 재발행 금지
표면     반드시 해소기를 통과. 자체 폴백·자체 재검사 금지.
```

지킬 것:

```txt
공통 유지   권한 재검사와 철회 시 폴백은 공통 서비스 하나로 남긴다.
            표면마다 재구현하면 한 곳이 빼먹고, 그 곳이 잘못된 Actor 로 발행한다.
점진 이행   5개 플러그인 호출부를 한 커밋에 바꾸지 않는다. 표면 단위로.
권한 불변   발행 권한(handle lock)과 데이터 접근 권한을 이 과정에서 합치지 않는다.
순서        S1~S5 이후. 접근 관계가 확정되기 전에 소비자를 옮기면 두 번 옮긴다.
```

## 앱 라우트는 식별자가 아니다

이 제품은 클라이언트 앱이므로 그 라우팅 경로는 **reader 가 어떤 Object 를 요청하는가**일
뿐이고, Object 의 `id` 도 `url` 도 아니다. 앱 라우트는 자유롭게 바뀔 수 있고 어디에도
발행되지 않는다. 식별자는 서버가 소유한다 -- `id`, canonical endpoint, 읽기 API,
앱 라우트의 4층 분리는 `activitystreams-open-items.md` 가 소유한다.

S9의 shared Inbox는 Actor별 Inbox와 별개 최적화다. Application Actor와 자동으로
동일한 소유 엔터티라고 가정하지 않는다. 비공개 데이터를 public GET에 노출하지 않는다.

## 의존 및 검증

- S3 -> S4, S6 -> S7, S7 이후 S9를 검토한다.
- S1–S2와 S6–S7은 별도 변경 단위다. 동일 함수·스키마를 병행 수정할 때는 합류 후 재검증한다.
- read/write·서명 실패 주입, 마이그레이션 실패·재시도, URI 불변, 관계 철회, 권한 누출을 검사한다.
- wp-env fixture로 로컬 수신 경로·인가·rollback을 검증한다. 실제 peer 서명·전달·스테이징 데이터 검증과 구분한다.
- 순서: Codex 구현 -> Opus 리뷰 -> 빌드 -> 스테이징 검증. 이번 변경에서 배포하지 않는다.

S6–S7의 구현·검증·남은 한계는
[Actor Inbox 구현 기록](../../axismundi-activitypub-bridge/docs/IMPLEMENTATION-ACTOR-INBOX.md)을 참고한다.
