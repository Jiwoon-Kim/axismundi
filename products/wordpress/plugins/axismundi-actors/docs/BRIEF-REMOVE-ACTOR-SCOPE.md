# Actor scope 제거 인계

완료 기록: [물리 제거 및 Actor 디렉터리](IMPLEMENTATION-REMOVE-ACTOR-SCOPE.md).
시스템 Actor도 공개 디렉터리에 포함한다. 사용자 Account 권한과는 별개다.

2026-11-18. 기존 [어휘 축소 구현](IMPLEMENTATION-VOCABULARY-REDUCTION.md)의
읽기 전환 이후 남은 작업이다. 이 문서는 설계이며 스키마 변경을 적용하지 않는다.

**이미 끝난 범위를 먼저 적는다.** 접근·개인 귀속 판정의 site scope 가드는 그 구현에서
제거됐고, principal 과 status 가 판정한다(같은 문서 43행). Group 디렉터리, Forum·
Activities 의 Group 판정, Calendar 백필도 scope 를 쓰지 않는다. `get_scope()` 와
`is_managed()` 는 구 클라이언트용 파생 호환 어댑터다. 그러므로 이 브리프가 여는 것은
**새 방향이 아니라 남은 물리 삭제**다. 스키마에 컬럼이 남아 있다는 이유로 그것을 살아
있는 판정 축으로 읽지 말 것 — 2026-11-18 에 그렇게 읽고 걷어낸 가드를 되돌리자는 제안이
나왔다.

**순서 제약.** `actors?type=` 은 이 작업 **뒤에** 만든다. `actor_type` 을 담은 인덱스는
`scope_type(actor_scope, actor_type)` 하나뿐이고 선두가 scope 라, 지금 만들면 인덱스를
못 타거나 그것을 태우려고 `?scope=` 를 API 에 노출시키게 된다. 걷어내는 중인 축을 공개
계약으로 만드는 것이 가장 비싼 실수다.

## 모델

- 인증 주체는 WordPress User다. AP Session과 JMAP Session은 그 주체의 접근 관계를
  투영한다. 세션 또는 로그인 세션 소유자를 Actor 행으로 만들지 않는다. Actor가 없는
  로그인과 여러 Actor에 접근하는 로그인을 모두 유지한다.
- `personal_owner_user_id`는 귀속 사실로 유지한다. JMAP `isPersonal`은 현재 사용자와
  귀속이 일치하고 현재 owner 관계가 성립할 때 계산한다. Actor 타입, `local_user_id`,
  접근 가능한 Account 수나 기본 선택으로 대체하지 않는다.
- 추가 Account는 유효한 principal 관계로 찾는다. 귀속이 없거나 관계가 없다는 것
  자체가 시스템 Actor라는 뜻은 아니다.
- 시스템 Actor는 기존 `ax_actors_system_actor_id` 바인딩으로 식별한다. 일반 Application,
  역할 없는 Actor, 과거 `site` scope 행을 새 시스템 Actor로 추정하지 않는다.
  시스템 Actor는 Actor 원장과 기존 시스템 서명 표면에 남되 사용자 Account가 아니다.

## 구현 순서

1. 권한부터 닫는다. 지정된 시스템 Actor를 대상으로 하는 신규 역할 부여와 역할 변경을
   거부한다. 시스템 Actor를 `local_account` principal로 사용하는 사용자 Account 권한도
   열지 않는다. 기존 오염은 역할 판정 및 Account 열거에서 무효로 처리하고, 정리 경로의
   회수는 가능하게 한다. 직접 SQL 백필과 생성 시 owner 부여도 함께 점검한다.
2. `wp eval-file wp-content/plugins/axismundi-actors/tests/report-actor-scope-retirement.php`
   읽기 전용 점검으로 현재 바인딩, 레거시 퇴역 영수증, 귀속과 직접 owner 관계를
   대조한다. 로컬 scope 누락, `user`인데 귀속이나 WP User가 없는 행, 레거시
   `site` 후보를 따로 보고한다. 예전 측정 수치는 완료 조건이 아니다. 기존에
   승인된 백필만 쓰고, 불명확한 활성 행은 해당 설치에서 DROP 전에 명시적으로
   처리한다. 묘비는 재분류하거나 삭제하지 않는다.
3. 저장된 scope 읽기와 쓰기를 걷는다. 소유·언어 백필, 시스템 Actor 퇴역의
   scope 기반 fallback, Group 인벤토리, 로컬 생성 및 원격 ingest 쓰기, 픽스처를
   각각 확인한다. 퇴역이 끝나지 않은 구 설치에서 컬럼을 먼저 없애지 않는다.
   `get_scope()`와 `is_managed()`는 남은 소비자를 확인해 따로 제거한다. 호환
   어댑터를 남기더라도 DB 컬럼이나 권한의 근거로 쓰지 않는다.
4. 런타임 의존성이 사라진 뒤 별도 마이그레이션으로 `scope_type`과
   `actor_scope`를 제거한다. 신규 설치, 업그레이드, 재실행을 각각 검사한다.
   `actors?type=`용 인덱스는 이 작업에서 추측해 추가하지 않는다. 실제 목록의
   WHERE/ORDER BY와 실행계획을 보고 정한다.

## 완료 판정

- 지정된 시스템 Actor에 owner를 부여하려는 요청이 실패한다. 일반 Application에는
  같은 제한을 적용하지 않는다. 구버전 오염을 흉내 낸 fixture에서도 시스템 Actor가
  선택기, AP Session, JMAP Accounts 및 사용자 outbox 제출 권한에 나타나지 않는다.
- Actor 없는 로그인, 본인 귀속 Actor, 귀속 없는 추가 Account로 세션을 검증한다.
  `username`, Account ID, Actor URI, 기존 시스템 공개 문서와 서명 경로는 보존한다.
- 구 설치 이관 전후에 Actor URI/UUID, 상태, 귀속, 유효한 역할을 비교한다.
  백필 실패 또는 미해결 퇴역 상태에서 DROP을 진행하지 않고, 성공한 이관은 재실행해도
  상태를 바꾸지 않는다.
- `ids` 기반 인증 컬렉션, outbox 타입 필터, 관리자 Activity 목록은 이 변경의
  범위에 넣지 않는다.
