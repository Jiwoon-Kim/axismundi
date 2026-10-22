# Account Access Slice A

날짜: 2026-10-22. 설계 근거는 [Multi-Account Ownership](DESIGN-MULTI-ACCOUNT-OWNERSHIP.md).

## Implemented

- DB 33.0: nullable personal_owner_user_id와 non-unique 조회 인덱스 추가.
- legacy local_user_id UNIQUE·identity ID·UUID·URI·공식 전송 키 연결은 유지.
- 기존 ax_actor_managers 테이블을 공통 Account 접근 관계 저장에 재사용.
- 기존 User 연결 로컬 Actor의 개인 귀속과 owner 행을 backfill. 조직의 owner 역할을
  개인 귀속으로 변환하지 않으며 이미 있는 역할은 조용히 승격하지 않는다.
- 스키마 검증 후 데이터 마이그레이션을 트랜잭션으로 실행하고 성공 후 완료 표시.
  DDL 자체를 rollback한다고 가정하지 않는다. 미완료 상태에서는 공통 접근이 fail-closed다.
- 개인 Account의 저수준 생성은 identity/actor/owner 접근 행을 함께 commit한다.
- User당 여러 개인 Account의 저장·조회가 가능하다. 신규 생성 UI·일반 사용자용 생성 서비스는 미구현이다.
- 작업 공간 목록과 단건 판정은 공통 ACL을 사용한다. get_for_user는 기존 legacy Actor만 반환한다.
- 기존 managed 전용 조회는 개인 owner 행까지 반환하지 않도록 범위를 유지한다.
- 기존 관리자 변경 경로로 개인 귀속 User의 owner 접근을 제거/강등하지 못하게 보호한다.
  개인 소유권 양도 API는 이번에 추가하지 않았다.

## APIs

| API | 용도 |
| --- | --- |
| Actor::get_personal_owner_user_id | 개인 귀속 사실, 관리자 owner 역할과 별개 |
| account_access_ready | 데이터 이관 완료 여부 |
| account_role | 현재 User의 명시적 운영 역할 |
| account_is_personal | 현재 User 기준 개인 귀속 판정 |
| accounts_for_user | 현재 접근 가능한 로컬 운영 Account 목록 |
| personal_accounts_for_user | 개인 귀속 목록, 수명주기 조사용 retired 항목도 포함 |
| resolve_workspace / workspace_options | 기존 공통 API, 위 관계 모델을 소비 |

함수에는 axismundi_actors_ 접두사를 사용한다. 개인 귀속 조회는 인가 함수가 아니며,
도메인별 권한과 발행 조건은 별도다. managed API를 일반 ACL API처럼 재해석하지 않는다.
write_manager_row는 기존 저수준 내부 함수로 유지하며 UI의 인가 서비스가 아니다.

## Compatibility Limits

이번에는 actor_scope 값과 기존 선택 저장·hook·기본 Actor·UI를 바꾸지 않는다.
추가 Person fixture는 기존 저수준 create_local에 personal_owner_user_id를 명시하고
local_user_id 없이 저장했다. 이 사실은 전체 제품의 다중 Actor 지원 완료를 의미하지 않는다.

Contacts는 아직 legacy 소유 검사를 사용하고 Calendar·Note·Activities에도 단건 가정이 남는다.
추가 Account의 데이터 편집·User 삭제·발행 흐름은 이관 전 일반 사용자에게 개방하지 않는다.
새 필드는 기존 deleted User의 historical binding을 복원하는 기능이 아니다.
User 삭제 정책은 기존 단건 경로를 유지하며 목록 기반 이관과 소유권 양도는 다음 단계다.
Inbox 멤버십·서명 검증·키 이관·Session·capability 활성화는 변경하지 않았다.

## Verification

실제 wp-env에서 실행:

| 명령 대상 | 결과 |
| --- | --- |
| Actors tests/audit-account-ownership.php | 29개 통과 |
| Actors tests/audit-acting-actor.php | 기존 16개 통과 |
| Actors tests/audit-managed-groups.php | 기존 12개 통과 |
| Actors tests/audit-repository.php | 기존 18개 통과 |
| JMAP tests/audit-accounts.php | 기존 23개 통과 |
| Contacts tests/audit-contacts.php | 기존 335개 통과 |

WP CLI의 wp eval-file로 위 경로를 실행했다. JMAP만 CLI 컨테이너 /tmp에 복사해
검사했으며 설치·활성화하지 않았다. migration 감사는 완료 표시를 일시적으로 해제하므로
운영 사이트에서 실행하지 않는다. 생성한 fixture는 테스트의 finally에서 정리한다.
repository 감사의 의도적인 local_user_id 중복 INSERT 오류는 기존 rejection 검사다.

로컬 PHP:

- `php products/wordpress/plugins/axismundi-actors/tests/workspaces.php`: 15개 통과.
- `php products/wordpress/plugins/axismundi-jmap/tests/engine.php`: 기존 64개 통과.

새 감사는 SQL 실패의 rollback·완료 표시 금지·재시도, 충돌 역할의 무단 승격 금지,
초기 owner 기록 실패 시 identity rollback, 복수 개인 귀속과 legacy 조회 불변을 확인한다.
스키마 전체 버전 33.0의 실제 적용도 로컬 wp-env에서 확인했다.
CI 연결·다중 프로세스 경쟁·User 삭제 정책 전환·실제 원격 연합 송수신은 검증하지 않았다.
