# S1 Principal 관계 일반화

> 현재 Account 운영과 도메인 모더레이션의 경계는 [Principal Boundaries](PRINCIPAL-BOUNDARIES.md)를 따른다.
> 특히 remote_actor는 공통 주체로 해소할 수 있지만 저장된 stale grant도 Account 운영 권한으로 읽지 않는다.

2026-10-23. 구현 범위이며 S2 이후 및 RFC 9670 전체 구현의 완료 선언이 아니다.

## 저장 계약

기존 Actor가 Account다. 별도 Accounts 테이블이나 Principal 신원 복제 테이블을 만들지 않는다.
기존 `ax_actor_managers` 관계의 기본 키를
`(identity_id, principal_kind, principal_id)`로 전환한다.

- `wp_user`: 기존 WP User ID.
- `local_account`: 로컬 Actor의 기존 identity_id.
- `remote_actor`: 관측한 원격 Actor의 기존 identity_id.

이 값들은 내부 참조 종류다. RFC 9670 Principal.type 또는 JMAP wire ID가 아니다.
기존 User 관계의 role·시간·ID를 보존하고 `user_id`는 WP User 호출부의 호환 투영으로 유지한다.
다른 종류는 user_id가 NULL이다. 같은 숫자의 User와 Actor가 권한을 공유하지 않는다.

## 권한 계약

리졸버는 원본 존재·로컬/원격 구분·상태를 확인한다. 조회만으로 관계나 권한을 생성하지 않는다.
종류를 명시한 role 조회는 HTTP 요청자를 인증하는 API가 아니다.
기존 User 쓰기 경로만 유지하며 원격 권한 부여 API·UI·서명 인증 연결은 열지 않는다.
그룹 상속·순환 해소도 이번 범위 밖이다. 마지막 User 소유자 보호에 원격 행을 포함하지 않는다.

개인 귀속, transport binding, 키, 모더레이션은 그대로다. `local_user_id` UNIQUE도 유지한다.
S2가 소유·접근 관계 수렴을, S3가 transport 및 모더레이션 독립을 완성해야 한다.
Acting Actor 소비자 이행이나 Session 프로토콜 필드 추가는 하지 않는다.

## 마이그레이션

DB 버전 34.0. 기존 user_id를 보존하면서 컬럼 추가, backfill, 기본 키 전환, 인덱스 추가 순서로 진행한다.
MySQL DDL은 트랜잭션 롤백 대상이 아니다. 중간 실패는 부분 스키마를 남길 수 있으며 재실행으로 복구한다.
알 수 없는 기본 키나 불일치 행은 임의 교정하지 않고 실패한다.
전체 키·호환 투영 검증을 통과하기 전 readiness를 완료로 표시하지 않아 Account 인가는 fail-closed다.
기존 Account ID와 Actor URI는 변경하지 않는다.

## 검증

`tests/audit-principals.php`는 별도 임시 관계 테이블에서 단계별 SQL 실패 주입과 재시도를 검사한다.
기존 역할·시간 보존, 완료 후 재실행, 동일 숫자의 주체 namespace 격리도 검사한다.
기존 ownership·managed group·Inbox 감사는 실제 소비자 회귀 검사다.
스테이징 실제 연합 검증 및 독립 리뷰는 별도다.

추가 행동 감사 `tests/audit-principal-behavior.php`는 같은 숫자의 User/Actor를 실제로 생성해
역할 비전파, origin 위장 거부, disabled/tombstone 주체 및 대상 거부를 검사한다.
JMAP `tests/audit-session.php`에는 동일 개인 Account의 소유자/위임자 isPersonal 차이와 철회 검사를 둔다.
개인 귀속 컬럼으로도 요청 User와 비교하는 파생 계산은 가능하다. 이 검사가 특정 저장 형태를 강제하지는 않는다.
관계를 usermeta로 옮기지 않는다. 기본 Account 선호와 접근 관계는 별개다.

2026-10-23 로컬 검증: 마이그레이션 43건, 행동 21건, Session 47건, Account 23건 통과.
role 조회 kind 조건 제거, kind/origin 일치 제거, 주체 상태 거부 제거 변이는 각각
동일 숫자 User 권한 비전파, 원격의 로컬 위장, disabled 주체 거부에서 실패했다.
변이 후 정상 코드를 복원하고 행동·Session 감사를 다시 통과했다.
JMAP은 `.wp-env.json`에 마운트되어 Session 감사를 실행할 수 있다.

표준 기준: [RFC 8620 User/Account](https://www.rfc-editor.org/rfc/rfc8620.html#section-1.6),
[RFC 9670 Principal](https://www.rfc-editor.org/rfc/rfc9670.html#section-2).
