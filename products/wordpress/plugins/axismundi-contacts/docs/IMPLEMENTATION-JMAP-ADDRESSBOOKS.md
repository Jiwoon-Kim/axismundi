# JMAP Account AddressBooks

후속 DB version 15: 프로필 본문 분리와 ContactCard 필수 소속 제약은
[Profile And ContactCard Separation](IMPLEMENTATION-PROFILE-SEPARATION.md)을 참고한다.

2026-10-30. Contacts의 AddressBook 저장소를 JMAP Account에 연결하는 첫 슬라이스다.

## Ownership

- JMAP이 Account ID(`a{actor_id}`)의 형식·저장소 존재를 확인한다. Contacts가 Actor ID만
  보고 Account를 발급하거나 없는 Account를 생성하지 않는다.
- Contacts가 AddressBook과 ContactCard 데이터를 소유한다. 기존 `owner_actor_id`는
  같은 ID를 공유하는 로컬 Account의 저장 키로 유지한다. 기존 행·Card UID를 재번호화하지 않는다.
- AddressBook 보유 여부는 AP Actor 타입과 무관하다. Group·Application·Service도 지원한다.
  Actor Profile Card 발행 정책과 원격 JSContact 일시 조회 정책은 별개이며 바꾸지 않는다.
- Contacts의 플러그인 의존성에 Actors와 JMAP을 명시했다. 런타임 Account 저장소가 준비되지
  않으면 Contacts readiness와 주소록 접근을 거부한다. Actor-only 폴백은 없다.

## Registration

`axismundi_jmap_register`에서 Contacts가 `urn:ietf:params:jmap:contacts` 식별자와
`AddressBook` 데이터 타입을 등록한다. 근거는 [RFC 9610 §7.2](https://jmap.io/spec/rfc9610/#section-7.2)다.
JMAP의 `Registry::register_type()`는 capability 식별자·사양 링크를 보존하고 중복·동결 후 등록을 거부한다.

타입 등록은 프로토콜 메서드 구현이나 Account entitlement 부여가 아니다.
현재 AddressBook/get·changes·set 전체 계약, ContactCard 메서드, HTTP transport는 제공하지 않는다.
Account provider를 등록하지 않으므로 Session.capabilities와 accountCapabilities에는
Contacts 지원을 광고하지 않는다. 이 단계에서 RFC 9610 전체 구현 완료를 주장하지 않는다.

## Application Services

- `axismundi_contacts_create_book_for_account(account_id, name, default=false)`:
  명시적 쓰기. 현재 인증 User의 운영 관계를 재검사한다. default=true는 기본 주소록을
  멱등적으로 생성한다. 일반 생성은 기존 기본 주소록 정책을 유지하며 이름 있는 추가 주소록을 만든다.
- `axismundi_contacts_books_for_account(account_id)`: 읽기 전용. 주소록을 자동 생성하지 않는다.
- ID는 JMAP 서비스로 해소한다. 현재 Actor 쿠키나 WP User의 기본 Actor로 대체하지 않는다.
- Contacts의 기존 owner·manager·editor 권한 정책을 유지한다. 사이트 관리자에게
  다른 Account의 사적 주소록 접근을 자동 부여하지 않는다.
- 기존 `book_for_actor()` 호환 경로도 JMAP Account 존재를 확인한다. 권한을 검사한
  기존 내부 소비자를 위한 저장소 함수이지 새 HTTP API가 아니다.
- 새 생성 서비스는 `Axismundi_Contacts_Write` 트랜잭션을 사용한다.
  기존 Person 기본 주소록의 자기 프로필 Card seed 동작은 유지한다.

## Verification

`wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-jmap-addressbooks.php`

41검사: 런타임 타입 등록·사양 출처·동결/중복 거부, 조기 capability 광고 방지,
Account ID 해소, Group/Application 기본 주소록, 멱등성, 읽기의 무생성,
Account 격리, 익명·무권한·권한 회수·비활성·Tombstone·잘못된 ID·없는 Account 거부.
최종 WP_Error로 테스트 쓰기를 롤백하며 기존 Actor·principal·Account·주소록·Card·프로필 행을 대조한다.
네트워크 요청이 없음을 검사한다. User/Actor 생성 훅을 돌리거나 실제 계정을 추가하지 않는다.

함께 실행한 회귀: JMAP `tests/engine.php` 71검사, `tests/audit-runtime.php` 34검사 통과.
후자는 실제 Account 정책·공통 authorize 경로와 감사 종료 후 저장소 대조를 포함한다.

기존 Contacts 전체 감사와 생성 HTTP 왕복, 동시 생성 경쟁, JMAP AddressBook/set 적합성은
이 감사 범위가 아니다. `type_keeps_books`의 옛 제한을 검사하던 기존 감사 두 곳은 새 정책으로
수정했지만 이번 슬라이스에서 그 전체 감사를 실행했다고 주장하지 않는다.
