# Contacts Account Authorization

2026-10-30 후속: Account ID는 JMAP 저장소를 통해 해소한다. Actor 타입별 주소록 제한은
폐기했다. 현재 경계·검증 범위는 [JMAP Account AddressBooks](IMPLEMENTATION-JMAP-ADDRESSBOOKS.md)를 참고한다.

작성일: 2026-10-23. 범위: Account 인가 정합성. DB 스키마·문서 형식 변경 없음.

## 기준

WP User가 접근할 수 있는 Account를 기준으로 Contacts를 사용한다.
Actor는 같은 로컬 Account의 ActivityPub 표현이며, 발행 권한과 비공개 Contacts 권한은 다르다.
물리적 `owner_actor_id` 컬럼과 기존 함수 인자는 이번에 이름을 바꾸지 않았다.

## 구현

- `account_permissions(account_id, user_id)`: Actors 공통 workspace resolver로 로컬·상태·관계·migration readiness를 확인한다.
- Contacts의 운영 역할 정책은 owner/manager/editor 허용을 명시한다. 새 관리자 override나 viewer 역할은 없다.
- `can_read_account()`와 `can_write_account()`를 별도 검사 진입점으로 제공한다.
- 두 권한의 현재 허용 역할은 동일하다. 기존 managed editor의 쓰기 권한을 임의로 축소하지 않았다.
- `can_use_book()`은 두 권한이 모두 필요한 기존 소비자용 호환 함수다.
- `current_account()`는 공통 `current_workspace()`를 사용하며 발행용 `acting_actor()`에 의존하지 않는다.
- Actors 필수 API가 없는 구버전 조합은 dependency readiness에서 차단한다.

별도의 Account 목록·선택 usermeta·ACL을 Contacts에 만들지 않는다.
`local_user_id`가 null인 추가 개인 Account도 명시적 운영 관계로 접근한다.
이 API는 Account 전역의 로컬 Contacts 인가이며 JMAP capability 선언이나 AddressBookRights가 아니다.

## 소비자 연결

| 소비자 | 검사 |
|---|---|
| 디렉터리·Card 상세·label 목록 | read |
| 에디터 자산·편집 화면·주소록 변경 인가·label 명령 | write |
| draft REST GET/HEAD | read |
| draft REST PUT | write |
| Contacts 화면·프로필 편집 대상·label 명령의 현재 선택 | Account workspace |

요청의 Card/AddressBook 소유 Account를 다시 검사한다. 선택값만으로 다른 Account의
Card를 읽거나 편집할 수 없다. draft REST의 권한 없는 Card 응답은 기존 404 정책을 유지한다.
disabled/tombstone 및 관계 회수는 다음 권한 검사부터 반영한다. internal은 접근 가능 상태다.
현재 선택이 무효일 때의 fallback은 Actors 서비스 정책을 따르며 Contacts에서 새 정책을 만들지 않는다.

## 미변경 / 후속 범위

- Contacts -> JMAP -> Actors로의 물리적 dependency 전환은 아직 하지 않았다. 현재 domain 인가는 Actors 공통 서비스를 직접 사용한다.
- Actors의 기능 할당, 실제 JMAP Contacts provider, RFC 9610 myRights·shareWith·isSubscribed는 미구현이다.
- Person/Organization만 주소록을 생성하는 기존 타입 정책과 미소속 Card 허용 정책은 유지했다.
- 추가 Account 생성 gate·선택 UI·소유권 이전·User 삭제 정책은 바꾸지 않았다.
- revision CAS·DB 실패 전파·공통 transaction·변경 원장·삭제 이력은 다음 쓰기 기반 슬라이스다.
- JSON editor와 공개 profile 투영·공개 선택은 재작성하지 않았다.
- 인가 없는 저수준 저장/조회 함수는 신뢰된 내부 서비스다. 새 외부 진입점은 해당 Account의 작업 권한을 먼저 확인해야 한다.

## 검증

개발 wp-env 전용:

```sh
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-account-access.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-contacts.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-labels.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-directory.php --path=/var/www/html
```

새 Account 감사는 임시 User/Account/주소록/Card를 만들고 finally에서 fixture만 정리한다.
추가 개인 Account·별도 조직 Account, 핸들 없는 접근, GET/PUT, 디렉터리 격리, 기존 운영 역할,
개인 Account 위임, 권한 회수, disabled/tombstone, migration 미완료를 검사한다.
기존 editor 감사에는 소스 문자열 검사도 포함돼 있으며 실제 브라우저 UI 검사와 구분한다.
동시 저장·SQL 실패·JMAP 전체 준수는 이 슬라이스의 검증 범위가 아니다.

실행 결과: Account 인가 33개, Contacts 335개, labels 40개, directory 34개로 총 442개 통과.
PHP 문법 검사와 `git diff --check`도 통과했다. 브라우저 수동 UI 검증은 실행하지 않았다.

후속: [Card 쓰기 안전성](IMPLEMENTATION-CARD-WRITES.md). revision CAS·저장 transaction을
추가했으나 변경 원장과 모든 삭제/소속 경로의 통합은 여전히 후속 범위다.
