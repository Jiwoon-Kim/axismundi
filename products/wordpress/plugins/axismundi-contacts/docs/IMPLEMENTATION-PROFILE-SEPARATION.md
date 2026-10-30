# Profile And ContactCard Separation

2026-10-30. DB version 15.

## Storage

- `ax_contact_profile_cards`: Actor 프로필의 독립 JSContact 본문·UID·revision.
  기존 provenance는 `provenance_json`에 보존한다. AddressBook 소속은 없다.
- `ax_contact_actor_profiles`: Actor와 독립 프로필의 연결·공개 항목·공개 설정.
  기존 `card_id` 컬럼은 이제 **프로필 저장소의 ID**를 가리킨다. ContactCard FK가 아니다.
  기존 연결 식별자를 보존하기 위한 호환 컬럼명이며 두 저장소의 ID는 별개다.
- `ax_contact_cards`: 주소록 ContactCard만 저장한다.
  `primary_address_book_id NOT NULL`과 `(primary_address_book_id, owner_actor_id)` 복합 FK로
  같은 Account의 실존 AddressBook 최소 하나를 DB에서 요구한다.
- `ax_contact_card_memberships`: 추가 AddressBook 소속. 필수 소속과 추가 소속의 합집합을
  `effective_memberships_sql()`로 읽는다. 관계 테이블의 행을 모두 지워도 필수 소속은 사라지지 않는다.

FK의 ON DELETE RESTRICT는 참조 중인 주소록의 직접 삭제를 막는다.
추가 소속 관계의 동일 Account 정책은 기존 애플리케이션 검사이며 모든 SQL 경로에서
DB 제약으로 강제한다고 주장하지 않는다. 이 슬라이스의 DB 보장은 최소 한 개의 유효한 동일 Account 소속이다.

## Upgrade

프로필 본문·UID·revision을 원문 그대로 복사한다. 주소록에 이미 저장된 사본은 ContactCard로
남겨 독립적인 수명·편집을 허용한다. 프로필 전용 무소속 행만 ContactCard 저장소에서 제거한다.
그 외 무소속 연락처는 버리지 않고 기존 소유 Account의 기본 주소록에 귀속시킨다.
프로필 연결·공개 설정과 WebFinger URL은 유지한다.

원본 데이터 변경 전 CLI 컨테이너의 `/tmp/contacts-before-profile-split.sql`에 개발 DB를 백업했다.
실데이터는 프로필 5개를 분리했고 ContactCard 9개 중 프로필 전용 2개가 빠져 7개가 남았다.
업그레이드 재실행 시 이미 복사한 프로필을 주소록 사본으로 덮어쓰지 않는다.

새 프로필 생성은 주소록·ContactCard를 만들지 않는다. Person의 기존 첫 주소록 초기화가
프로필을 만드는 정책은 유지하지만, 그 프로필을 ContactCard 멤버로 넣지는 않는다.
`set_profile_card()` 호환 함수는 이제 소유 ContactCard를 독립 프로필로 복사한다.

## Reads And Writes

WebFinger describedby·공개 JSContact·이름 복사 후보·프로필 화면은 독립 본문을 읽는다.
편집기 컴포넌트는 공유하지만 API는 `/profiles/{id}/draft`와 `/cards/{id}/draft`로 분리한다.
두 ID가 같다는 이유로 ContactCard를 프로필로 판정하거나 목록에서 숨기지 않는다.
프로필 편집은 기존 Account 인가·revision CAS·불변 UID·공개 항목 원자성을 유지한다.

마지막 소속 제거는 거부한다. 다른 소속이 있으면 필수 소속을 옮길 수 있다.
라벨 삭제는 ContactCard를 기본 주소록으로 옮긴 뒤 진행한다.
라벨 명령도 공통 Contacts 트랜잭션 runner로 묶어 실패 시 소속·revision을 함께 롤백한다.
프로필과 연락처 사본은 한쪽을 편집·삭제해도 다른 쪽을 변경하지 않는다.

## Verification

- `wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-profile-separation.php`: 31검사.
  독립 생성, 공개 UID, 프로필 draft 읽기·쓰기·인가·CAS, 필수 소속 생성,
  NULL·없는 주소록·타 Account FK·필수 주소록 삭제·소속 없는 INSERT의 DB 거부,
  추가 소속·필수 소속 이동, 연락처 사본 편집·삭제의 독립성, 전체 테스트 행 롤백을 확인한다.
- `wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-labels.php`: 40검사.
  새 기본 주소록 소속 정책에 맞게 기존 무소속 기대를 변경했다.
  저장 실패 주입의 전체 롤백·권한·필터·revision 회귀를 포함한다.
- `wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-jmap-addressbooks.php`: 41검사.
- `wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-profile-upgrade.php`: 3검사.
  재설치 성공·본문과 공개 설정/소속 행의 무변경·복합 FK 유지를 확인한다.
- Accounts 내비게이션 실제 HTTP 감사: 12검사 통과. Contacts 관리 화면의 200 응답을 포함한다.
- `http://localhost:8884/@admin2.jscontact`: 실제 HTTP 200, Card와 기존 UID 반환 확인.

Contacts 전체 역사 감사는 아직 새 프로필 저장 API에 맞춰 전부 이관하지 않았다.
모든 브라우저 편집 동작·동시 쓰기 스트레스·JMAP ContactCard 프로토콜 적합성 완료를 뜻하지 않는다.
