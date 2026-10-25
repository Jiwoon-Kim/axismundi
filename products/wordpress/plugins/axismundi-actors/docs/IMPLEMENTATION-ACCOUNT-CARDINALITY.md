# S4 Account 카디널리티

2026-10-24. 구현·로컬 검증 단계다. 생성 UI·REST·기존 review gate는 열지 않는다.
복호화 오류의 관리 화면 표시와 upstream 기여 조사도 이번 범위 밖이다.

## 제약의 이전

Actors DB 37.0:

- `local_user_id`는 `local_user_lookup` 일반 인덱스로 전환한다.
- `transport_user_id`는 `legacy_transport_user` nullable UNIQUE로 보호한다.
- 개인 귀속(`personal_owner_user_id`)과 Principal 접근 관계는 그대로 유지한다.

부분 UNIQUE는 필요하지 않다. 구 per-user 라우트는 upstream_user뿐 아니라 회전한 actor provider도
조회하므로, 모든 NULL 아닌 transport_user_id가 최대 한 행이어야 한다. upstream_application과
추가 Account는 NULL이며 복수 행이 허용된다. 이 필드는 소유자가 아니라 구 라우트 예약이다.

새 제약을 추가·검증한 다음 기존 local_user_id UNIQUE를 제거한다. 불필요해진 기존 transport
일반 인덱스도 정리한다. 중복 route 데이터는 임의로 한 액터에 귀속시키지 않고 마이그레이션을
중단한다. identity_id·Actor URI·키 재료를 재할당하거나 회전하지 않는다.

MySQL DDL은 트랜잭션 롤백이 아니다. advisory lock으로 동일 스키마 이행을 직렬화하며,
단계별 실패 후 재실행으로 복구한다. 완료 전 신규 Account 생성은 버전 gate에서 거부한다.
배포 중에는 구 코드의 in-flight 쓰기와 섞이지 않도록 유지보수 구간을 확보해야 한다.

## 생성과 legacy getter

첫 canonical User 바인딩은 구 per-user 라우트를 예약한다. 같은 local_user_id를 가진 추가
Person도 저장할 수 있지만 transport_user_id는 NULL이다. 모든 신규 Actor는 S3b 독립 키를
받고, identity·owner 관계·키 저장은 하나의 생성 트랜잭션이다.
동시에 첫 route를 예약하려는 요청이 충돌하면 DB UNIQUE가 한 요청을 거부한다.
실패한 생성이 부분 identity나 임의 재바인딩으로 이어지지 않는다.

`get_for_user()`는 더 이상 중복 가능한 local_user_id에서 임의의 첫 행을 고르지 않는다.
고유 transport 바인딩을 조회한다. User의 활성 workspace나 기본 Account 선호가 달라져도
이 compatibility getter와 이미 발행된 per-user 주소는 재바인딩되지 않는다.

같은 User가 두 개인 Account 모두에 접근할 수 있는 것과 두 Inbox의 멤버십이 분리되는 것은
서로 다른 보장이다. User가 자기 다른 Account를 읽지 못하도록 막는 정책을 추가하지 않는다.
Account별 Block도 각 수신자의 receipt에만 적용한다.

S2의 귀속·접근 관계 정리와 User 삭제·이전 정책은 남아 있다. 이것을 공개 생성 흐름의 완료로
해석하지 않는다. UI·REST 추가 생성 gate는 그대로 닫혀 있다.

## 검증

Bridge `tests/audit-account-cardinality.php`는 임시 테이블에서 UNIQUE 추가·기존 UNIQUE 제거·
조회 인덱스 추가·구 인덱스 제거 실패, 재시도, ambiguous route 거부를 검사한다.
실제 로컬 fixture에서 같은 User 링크의 두 Person, 접근 관계, 독립 키·RSA 서명,
Inbox 멤버십 격리, Account Block, canonical 라우트 및 선호와의 분리를 검사한다.

transport UNIQUE를 임시 fixture에서 제거한 변이는 중복 route 쓰기 거부 검사에서 실패했다.
추가 Person을 upstream_application provider로 바꾸는 변이는 독립 provider 검사에서 실패했다.
변이는 모두 원복했다. 실제 peer·스테이징 검증은 별도다.

로컬 결과: S4 50건, ownership 29건, Account operations 51건, signing keys 44건,
Actor Inbox 57건, JMAP Session 47건 통과. PHP lint와 git diff --check도 통과했다.
