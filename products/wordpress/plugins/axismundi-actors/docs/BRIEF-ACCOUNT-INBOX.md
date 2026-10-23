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
| S2 관계 통일 | 개인·위임 접근의 관계 모델 수렴, 개인 귀속과 운영 role 분리, 삭제·철회 정책 | 대기 |
| S3 transport | legacy 공식 plugin binding과 Account 접근 분리, Actor별 키 생성·저장·회전·서명 정책, **모더레이션 스코프** | S3a 커밋 완료. S3b 구현·독립 리뷰 대기 |
| S4 UNIQUE | 유일성을 transport 바인딩으로 이전. 추가 Person 의 독립 키·접근 관계 | 대기 |
| S5 isPersonal | 인증 User와 관계로 계산. Session 기본값·지원·철회 회귀 검사 | 기존 부분 구현, 재검토 |
| S6 receipts | Activity와 원자적인 Actor별 receipt, 중복·실패·보수적 백필 | 구현·리뷰 완료 (3bc752c) |
| S7 Inbox | Actor UUID URL, Account 인가 GET, 공식 검증기 재사용 POST, 기존 수신 URL 유지 | 구현·리뷰 완료 (3bc752c) |
| S8 activity view | Account 범위 Activity 뷰·query·changes·읽음 상태. AP 문서와 분리. **mailbox 아님** | 대기 |
| S11 state | Account 단위 변경 알림 경로. S8 의 선결 | 대기 |
| S9 instance | shared Inbox 및 Application Actor 소유권 이관 | 대기 |
| S10 acting-actor | 요청의 Account 선택 운반·검증. 기본 선호와 현재 선택 분리 | 대기 |

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
핀   User 당 transport_kind='upstream_user' 바인딩은 최대 하나다.
     local_user_id 의 UNIQUE 를 떼기 전에 그 제약을 transport_user_id 쪽에 세운다.
     (부분 유일성이 필요하므로 단순 UNIQUE 로는 부족하다 --
      upstream_application 행의 transport_user_id 는 NULL 이라 다중 NULL 은 허용되지만,
      제약이 upstream_user 에만 걸려야 한다는 점을 확인할 것.)
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

### 생성 흐름은 여전히 닫아 둔다

S4 가 스키마를 열더라도 **UI·REST 공개는 별도 판단**이다. 생성이 열리는 순간
위 경로들이 실제 트래픽을 받으므로, 감사가 먼저 서고 나서 연다.

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
