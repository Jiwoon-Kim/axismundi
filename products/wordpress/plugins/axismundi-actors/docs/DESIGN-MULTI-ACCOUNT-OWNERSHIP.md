# Multi-Account Ownership Design

작성일: 2026-10-22

상태: 조사와 설계 제안. Confirmed Decisions만 사용자와 합의한 결정이다.
스키마·API·마이그레이션·삭제 정책의 제안은 아직 구현 승인된 계약이 아니다.
이번 작업에서는 런타임 코드·DB·플러그인 활성화 설정을 변경하지 않았다.

## Confirmed Decisions

- 로컬 Actor와 Account는 하나의 운영 단위다. Actor -> Account의 추가 상위 소유 계층을 만들지 않는다.
- WordPress User는 인증 주체이며 여러 Person 및 다른 타입의 Actor/Account를 운영할 수 있어야 한다.
- identity ID·UUID·Actor URI·기존 객체 attribution은 유지한다.
- Actors는 공통 운영 단위·소유/접근 관계·기능 할당을 소유한다. JMAP 없이도 전환 기반이 동작한다.
- JMAP은 capability 정의·등록·최종 Account별 판정과 Session 표현을 소유한다.
- 목표 의존 방향은 Contacts -> JMAP -> Actors다. 현재 Contacts 직접 의존 전환과 Calendar는 별도 작업이다.
- personal/shared/group을 서로 배타적인 JMAP Account 타입으로 만들지 않는다.
- Actor 타입, 개인 귀속, 관리 역할, 기능 활성화, 실제 User의 작업 권한을 분리한다.

## Source Audit

현재 소스를 읽은 결과다. 다중 Account 마이그레이션을 실행하거나 그 동작을 측정한 기록이 아니다.
경로는 모두 `products/wordpress/plugins/` 기준이다.

| 위치 | 현재 가정 / 변경 영향 |
| --- | --- |
| actors/includes/repository.php:179 | local_user_id UNIQUE. 단순 제거만으로 해결되지 않음 |
| repository.php:1397, :2206 | get_for_user 단건, ensure_for_user 기존 한 개 재사용 또는 Person 자동 생성 |
| repository.php:2741 | User 삭제 시 단건 Actor만 tombstone |
| actors/includes/managed-groups.php | 생성 타입 Group/Service/Organization 한정. 관리자 추가도 managed 범위만 허용 |
| actors/includes/workspaces.php | 최근 추가한 공통 접근 API도 본인 Actor 하나 + managed 관계라는 가정이 남음 |
| actors/includes/acting-actor.php | 기본 Person 단건, 선택 identity usermeta, 발행에 handle lock 요구 |
| actors/includes/admin.php | WP User 프로필·활성화와 managed 관리 화면 분리 |
| actors/includes/routing.php, texts.php, avatar.php | local_user_id를 preview·WP 작성자 URL·이름/소개/locale/avatar fallback에 사용 |
| activitypub-bridge/includes/transport.php | local_user_id로 공식 플러그인 inbox 및 사용자 키 선택. 없으면 Application 키 경로 |
| activities/includes/local-social.php | 현재 Follow 주체를 User의 단건 공개 Person으로 선택 |
| object-projections/includes/post-article.php | 단건 author helper가 남지만 post_actor_uri 기본 경로는 attribution 필터 사용. 전부 post_author 기반이라고 일반화하지 않음 |
| contacts/includes/address-books.php | 개인은 local_user_id 일치, managed는 관계 검사 |
| contacts/includes/lifecycle.php | User 삭제 후 단건 Person 주소록 정리 및 local_user_id 기반 orphan 탐색 |
| calendar/includes/event-actor.php | WP 작성자 -> 단건 Actor 경로 존재. Calendar 재설계는 별도 범위 |
| note/includes/envelope.php, federation.php | 초안 작성자·viewer 해소에서 단건 Actor 사용 |
| note/includes/conversations.php | 읽음 상태는 WP User 기준. Actor 소유 데이터와 개인 독서 상태를 무조건 합치면 안 됨 |

## Target Model

```text
WP User -- operating access --> Actor / Account A (Person)
        -- operating access --> Actor / Account B (Person)
        -- operating access --> Actor / Account C (Organization)

Actor / Account
  identity and actor type
  personal attribution, if applicable
  explicit User access roles
  protocol-neutral enabled features
  data and protocol representations
```

관리 권한의 owner 역할은 조직의 관리자를 뜻할 수도 있다. 이를 개인 귀속과 혼동하지 않는다.
개인 귀속 User 본인의 Account는 그 User의 세션에서 isPersonal=true로 투영할 수 있다.
그 Account를 공유받은 User는 false이며 조직/집단 소유 Account는 관리자 owner여도 false다.
isPersonal과 JMAP Session 완성 JSON은 DB에 고정 저장하지 않는다.
여러 개인 Account를 허용하되 Actor 타입만으로 개인 귀속을 자동 추론하지 않는다.

## Proposed Persistence

권장안은 **추가형 스키마 + 기존 관리자 관계의 일반화**다. 아래는 승인 대기 제안이다.

1. Actors에 nullable `personal_owner_user_id`를 추가한다. User당 UNIQUE를 두지 않는다.
   조직/집단 Account는 null이다. 이것은 운영 담당자의 owner 역할과 다른 사실이다.
2. 기존 `ax_actor_managers(identity_id, user_id, role)`를 모든 로컬 운영 Actor의 접근 관계로
   일반화한다. 이름만 다른 두 ACL 저장소를 만들지 않고, 새 중립 API 뒤에서 재사용한다.
   최초에는 owner/manager/editor를 유지한다. viewer 및 그룹 Principal membership은 별도 권한 설계다.
3. 개인 귀속 User의 owner 관계를 생성·이관 트랜잭션에서 보장한다. 개인 귀속과 ACL의
   불일치를 허용하지 않으며, 생성/양도/삭제 서비스가 두 사실을 함께 갱신한다.
4. 기존 `local_user_id`는 첫 마이그레이션에서 삭제·재할당하거나 UNIQUE를 풀지 않는다.
   기존 WP/공식 ActivityPub 연동의 legacy 단건 연결로 보존하고 새 Account 소유의 근거로 쓰지 않는다.
   추가 Person은 개인 귀속과 ACL로 연결하며 legacy 연결을 억지로 두 개 만들지 않는다.
5. `actor_scope=user/managed/site`는 당장 데이터 전체를 재분류하지 않는다.
   새 접근/개인 귀속 API는 scope만으로 소유 관계를 결정하지 않고, 기존 scope 기반 UI·도메인
   정책은 호출처별로 옮긴다. 추가 Person 생성 전에 새 행의 scope 값과 모든 소비 경로를 확정한다.
6. 기능 할당은 Actors의 별도 설정 API로 관리한다. 내부 키는 contacts/calendar/mail 같은
   도메인 키를 사용하고 최종 capability URI/권한/제한값은 JMAP이 계산한다.
   영속화 방식과 default-enabled 정책은 아래 Decision Gates에서 결정한다.

`local_user_id`를 바로 non-unique 소유 컬럼으로 재사용하는 대안은 단건 조회와 전송 경로를
동시에 모호하게 만든다. 기존 compatibility 연결과 신규 관계를 분리하는 안을 우선 권장한다.
legacy 컬럼을 영구 존치하기로 결정한 것은 아니며, 소비 경로 이관 후 제거 가능성을 재검토한다.

## API And Selection

새 API 이름은 구현 전 충돌 검사 후 확정한다.

- 조회를 개인 귀속 목록, 접근 가능 목록, identity 단건 인가, 기본값 조회로 분리한다.
- Account 생성은 Actor 타입과 개인 귀속/운영 관계를 명시적으로 받아 동일한 생성 서비스를 사용한다.
  Person만 생성할 수 있는 ensure 경로와 managed 타입 whitelist를 새 생성 기능의 근거로 사용하지 않는다.
- 기존 get_for_user는 legacy Actor를 안정적으로 반환한다. 현재 선택값이나 SQL 첫 행을 반환하도록
  바꾸지 않는다. ensure_for_user도 legacy 초기화의 호환 경로로 한정한다.
- 현재 선택, 사용자 기본 작업 공간, 기능별 기본 Account는 다른 설정이다.
- 기존 `ax_acting_actor_identity`를 이관 중 유지하고 별도의 경쟁하는 activeAccount 저장값을 만들지 않는다.
- 기능별 기본값은 Actors에 프로토콜 중립적인 선호로 저장하고 JMAP primaryAccounts로 투영한다.
  기능 지원·권한을 재검사하며 활성 선택을 바꾼다고 primaryAccounts까지 바꾸지 않는다.
- Session/목록 GET에서 Actor·주소록·프로필 Card를 자동 생성하거나 stale 선호를 자동 기록하지 않는다.
- 발행에는 기존 handle lock·상태·도메인 조건을 유지한다. 데이터 접근 가능과 발행 가능을 구분한다.
- 사용자의 WP 권한이나 post_author는 Actor 선택으로 바뀌지 않는다.

## Migration And Lifecycle

마이그레이션 권장 순서:

1. 기존 개인 local_user_id 및 managed 관리자 관계를 진단한다. 존재하지 않는 User,
   원격/사이트 Actor, owner 없는 조직을 자동 소유 이전하지 않고 보고한다.
2. 새 필드와 API를 추가한다. 기존 개인은 personal_owner_user_id와 owner ACL을 backfill한다.
   기존 managed의 관리자 owner는 개인 소유로 바꾸지 않는다. 기존 역할은 그대로 보존한다.
3. 재실행 가능한 단계 표시와 검증 후 새 관계 읽기를 활성화한다. dbDelta의 DDL과 데이터
   변경을 하나의 rollback 가능한 트랜잭션이라고 가정하지 않는다.
4. 작업 공간/발행용 선택/관리 화면을 새 API로 이관한다. 그 뒤에 추가 Person 생성을 개방한다.
5. Contacts 의존성·인가·삭제를 Account API로 이관한 뒤 RFC 9610 capability를 연결한다.
6. Bridge·Note·Activities·Calendar의 단건 가정은 명시적인 후속 작업으로 처리한다.
   신규 Account의 연합 발행은 전송 검증 전까지 가능하다고 광고하지 않는다.

User 삭제는 단건에서 목록 기반으로 바꿔야 하지만 정책은 아직 승인 대기다.
권장 기본은 개인 귀속 Account의 retirement 대상으로 전체 목록을 산출하고,
조직 Account는 삭제하지 않고 해당 User의 운영 관계만 정리하는 것이다.
마지막 운영 owner 삭제는 사전 이관/명시적 retirement 없이는 차단하는 방향을 검토한다.
다른 User의 Card·과거 Activity·공개 identity URI는 연쇄 삭제하지 않는다.
Contacts의 기존 purge, WP 삭제 hook 순서·재할당·멀티사이트 동작을 함께 검증하기 전
새 삭제 정책을 적용하지 않는다.

## Decision Gates

다음 항목은 사용자 승인과 추가 구현 조사 전에 확정하지 않는다.

1. 위 personal_owner_user_id + 공통 ACL 구조, 추가 Person의 scope와 legacy API 수명.
2. 생성 권한·Account 개수 제한·타입별 초기값. 기본 생성은 Person을 제안하지만
   User 등록 시 자동 생성과 명시적 create-account 흐름의 전환 시점은 별도 결정이다.
3. 기본 작업 공간/기능별 기본값의 저장 방식과 접근 철회 시 fallback. SQL 첫 행을 기본값으로 삼지 않는다.
4. 기능 할당의 비활성 의미. 새 쓰기만 막을지 기존 데이터 읽기도 막을지, 제거 후 보관 정책.
5. 개인 소유권 양도·마지막 owner·User 삭제·개인 Account 공유 해제 정책.
6. 추가 Person의 공식 ActivityPub 키/inbox 사용 방식. 기존 URI와 키를 자동 교체하지 않는다.
7. Actor별 Inbox URI의 파생 규칙과 endpoint 소유권, 기존 공식 경로의 호환 수신 수명.
8. 검증된 수신 사실의 Actor별 멤버십 저장, sharedInbox fan-out, 중복·재시도·부분 실패 처리.
9. Inbox GET의 권한·페이지 처리·비공개 데이터 보호 및 검증 엔진 재사용 가능 범위.

## Actor Inbox Ownership

목표 결정: Inbox/Outbox는 같은 Actor/Account에 귀속되는 논리적 컬렉션이다.
Actor별 Inbox의 canonical 주소와 읽기 계약을 Axismundi가 소유하는 방향으로 이관한다.
실제 URI 문자열·기존 주소 호환 방법은 미정이며, 이 문서로 공개 URL을 변경하지 않는다.
원격 Actor URI와 기존 Activity 식별자는 이 이관에서도 유지한다.

### Existing Implementation

2026-10-22 소스 확인 결과이며 새 원격 송수신 실측은 하지 않았다.

- Bridge `includes/transport.php`의 inbox_url은 local_user_id가 있으면 공식
  `/activitypub/1.0/actors/{user_id}/inbox`, 없으면 공식 shared `/activitypub/1.0/inbox`를 반환한다.
  Org/Group이 Inbox 없이 광고되는 구조라고 해석하지 않는다.
- Bridge bootstrap의 inbox_targets는 공식 route User 목록뿐 아니라 Activity의 주소·대상 URI
  등을 통해 공개 로컬 Actor를 해소한다. 실제 수신 멤버십으로 인정할 근거는 이관 시 재검토한다.
- 공식 검증 후 Bridge가 Activities 원장에 기록하는 경로는 있다.
- Activities `includes/repository.php`는 URI-keyed Activity payload 원장을 보유한다.
  그러나 `includes/collection-contracts.php`의 ap-inbox reader는
  `ax_act_inbox_membership_not_stored`를 반환한다. Actor별 수신 멤버십은 아직 materialize하지 않는다.
- Bridge의 공식 Inbox GET filter도 별도 CPT에서 잘못된 멤버십을 읽지 않도록 항목을 제거한다.
  수신 POST 경로 존재와 Actor별 GET 완성을 구분한다.

### Responsibility And Storage

| 책임 | 소유 방향 |
| --- | --- |
| Actor 식별·Inbox 주소 바인딩 | Actors의 endpoint 계약 |
| 컬렉션 표현·페이징 | 기존 OP 컬렉션 계약과 연계, HTTP 소유 플러그인은 구현 시 확정 |
| HTTP POST·서명 검증 | Bridge 또는 검증된 전송 구현 재사용 |
| Activity 저장·도메인 처리·수신 멤버십 | Activities |

공유 수신점은 계속 유지할 수 있다. 전용 Inbox POST가 특정 Actor를 지정하더라도
주소 지정·차단·권한 정책을 우회하지 않는다. HTTP 서명 검증은 서명자 키로 수행하며,
키 조회가 필요한 경우 SSRF 등 기존 검증 경계도 유지한다.

새로 필요한 것은 두 번째 payload 저장소가 아니라 원장 Activity와 로컬 수신 Actor 사이의
receipt/membership 관계다. 하나의 Activity가 여러 Actor에게 도달할 수 있으므로 이를
Activity 원장과 분리하고 Activity-Actor 쌍을 중복 없이 기록하는 방향을 검토한다.
동일 Activity의 재수신에서도 새로 인정된 수신 Actor의 멤버십은 누락되지 않아야 한다.
payload 충돌, 검증 실패, fan-out 중간 실패, 원장 기록과 멤버십 기록의 원자성을 함께 검증한다.

멤버십은 그때 검증·인가되어 수신된 사실에 근거한다. GET 때 현재 Follow 관계나
공개 audience만으로 재계산하지 않는다. 과거 수신 사실을 복원할 증거가 부족하면
소급 backfill이 완전하다고 주장하지 않는다. 새 수신부터 기록하는 선택도 명시한다.

기존 공식 Inbox 주소로 오는 signed POST는 호환 수신 경로를 통해 검증·분배해야 한다.
HTTP redirect로 서명된 요청을 다른 경로에 다시 보내면 검증이 깨질 수 있으므로
단순 301/302 이관을 기본 해법으로 삼지 않는다. 이중 처리·서명 우회·무조건 성공 응답을 피한다.
Inbox 주소 이관을 이유로 기존 키를 회전하거나 Actor별 새 키를 자동 발급하지 않는다.
GET에서는 수신 payload·blind addressing·다른 Actor의 멤버십을 무권한으로 노출하지 않는다.

## Delivery Slices

| 단계 | 구현 범위 | 완료 기준 |
| --- | --- | --- |
| A | 관계 모델·추가형 migration·중립 조회 API | 기존 identity/관계 보존, 재실행·부분 실패 검증 |
| B | 명시적 생성·선택·기본값·관리 UI | 같은 User의 두 Person을 생성/선택, JMAP 없이 동작 |
| C | Account 기능 할당 + JMAP 투영/provider | 설정·설치·실제 메서드·현재 권한의 교집합만 노출 |
| D | Contacts 의존성·인가·수명주기 전환 | 기존 편집·가져오기 보존, 격리·삭제 감사 통과 |
| E | Session·엄격한 입력·HTTP 인증 경계 | 미구현 capability/URL 광고 없이 계약 검증 |
| F | Actor Inbox 소유권·수신 멤버십·호환 수신 | 전용/shared POST와 권한 있는 GET, 중복·격리·구 URL 검증 |

Bridge/Activities/Note/Calendar의 연합·이력 변경은 별도 승인 후 교차 검증한다.
A/B를 마쳤다는 이유로 전체 제품의 다중 Actor 지원이 완성됐다고 표기하지 않는다.
F는 E를 끝내야 시작할 수 있는 종속 단계가 아니라 별도 전송 슬라이스다.
추가 Actor의 완전한 연합 지원을 선언하기 전에는 F와 전송 검증을 완료해야 한다.

## Verification Plan

다음은 작성할 검사 목록이며 아직 구현·실행하지 않았다.

- legacy fixture migration의 identity ID/UUID/URI/키/attribution 불변.
- 두 Person의 생성·언어/이름/avatar 독립·선택·개인 귀속·primary 기본값.
- Person 위임·권한 철회·타 User 접근 차단·관리자 implicit access 금지.
- 조직 owner 역할과 isPersonal 분리, 신규 역할이 기존 권한을 넓히지 않음.
- 동시 생성/마지막 owner 변경의 잠금·원자성·복구, migration 재실행·중간 실패.
- User 삭제 시 여러 개인 Account와 공동 조직 Account의 다른 처리 및 orphan 진단.
- JMAP 부재 시 Actors 작동, 기능 provider 부재 시 capability 비노출.
- 동일 Activity의 여러 Actor 수신 및 재전송에서 payload 중복 없이 멤버십 보존.
- 전용/shared/구 공식 Inbox 경로의 검증 일관성·잘못된 대상·차단·권한 없는 GET 거부.
- Inbox 저장 중간 실패·재시도·페이지 처리 및 기존 원장의 불완전한 과거 멤버십 진단.
- 기존 `tests/audit-acting-actor.php`, `tests/workspaces.php`, JMAP `tests/audit-accounts.php`,
  Contacts 삭제/소유 감사 및 Bridge 송신·수신 감사 회귀.

기존 단일 Actor에서 통과한 테스트는 새 다중화 모델의 증거로 재사용하지 않는다.
설계 상태이며 자동 강제가 아닌 convention이다. 구현마다 실제 검증 명령과 결과를 추가한다.
