# Card Write Safety

작성일: 2026-10-23. 범위: 공통 쓰기 기반의 첫 하위 슬라이스.
RFC 9610 기능을 광고하거나 새 프로토콜 필드를 정의하지 않는다. DB 스키마 변경 없음.

## 구현 범위

| 항목 | 변경 |
|---|---|
| Card 기존 행 저장 | `id + owner_actor_id + 읽은 revision` 조건으로 UPDATE |
| 경합 | 0행 UPDATE 또는 MySQL/MariaDB 충돌 오류 1020/1205/1213이면 409 conflict |
| Card 새 행 저장 | INSERT 실패를 전파하고 초기 membership 실패 시 새 Card도 rollback |
| 검색 인덱스 | DELETE와 모든 INSERT의 성공을 확인하고 실패 시 문서와 함께 rollback |
| 주소록 revision | 실제 갱신 1행을 확인. 저장 경로에서 실패를 전파 |
| 저장 hook | 최외곽 commit 이후 호출. rollback된 작업은 알리지 않음 |
| draft REST | Card·공개 선택·로컬 편집 provenance를 같은 write runner에 연결 |
| entry-key migration | Card·provenance pointer·공개 pointer 변경을 같은 runner에 연결 |

클라이언트 revision 인자가 null이어도 서버가 읽은 행 revision을 조건부 UPDATE에 사용한다.
이는 동시 요청 사이 lost update를 막는 조건이며, 오래전에 읽은 전체 문서를 revision 없이
다시 보낸 클라이언트의 의도를 복구해 주는 것은 아니다. 외부 편집 API는 읽은 revision을
제출하거나 향후 JMAP의 ifInState 계약을 사용해야 한다.

## Transaction Ownership

`includes/transactions.php`의 `Axismundi_Contacts_Write::run(operation)`을 사용한다.

- 최외곽 호출은 autocommit을 확인하고 트랜잭션을 연다.
- 이 runner 안의 중첩 저장은 SAVEPOINT를 사용한다. 중첩 START TRANSACTION으로 바깥 작업을 commit하지 않는다.
- WP_Error·false 반환과 예외는 해당 작업 범위를 rollback한다.
- START·SAVEPOINT·RELEASE·COMMIT 실패를 성공으로 반환하지 않는다.
- rollback/savepoint 정리에 실패하면 같은 PHP 요청에서 runner를 재사용하지 않는다.
- 직접 연 SQL transaction 안에서 runner를 호출하면 쓰기 전에 거부한다. 새 합성 저장은 runner로 감싸야 한다.

외부 transaction guard는 기존 OP 저장소의 `SET TRANSACTION READ WRITE` 패턴을 참조했다.
이 명령은 active transaction 내부에서 실패하며 START TRANSACTION처럼 기존 작업을 commit하지 않는다.
알 수 없는 외부 transaction을 자동으로 인수하거나 임의로 종료하지 않는다.
기존 Contacts의 draft REST와 entry-key migration은 raw transaction을 runner로 전환했다.

`save_card()`와 `save_card_for_owner()`의 공개 호출 형태는 유지한다.
`write_card()`는 내부 body이며 runner 외부 호출을 거부한다.
`add_card_to_book()`도 단독 호출 시 자체 범위를 소유하므로 membership과 book revision을 함께 저장한다.

이 원자성은 InnoDB 테이블과 같은 DB connection의 SQL 작업에 대한 계약이다.
다른 플러그인이 callback에서 임의 COMMIT/DDL을 실행하거나 connection을 바꾸는 것은 지원하지 않는다.
네트워크 단절 시 commit 여부가 불명확한 문제나 요청 재시도 deduplication까지 해결한 것은 아니다.

## Notifications

저장 알림은 중첩 작업에서 모아 최외곽 commit 이후 실행한다.
초기 filing과 draft provenance 저장이 끝나기 전에 Card 저장 hook이 발행되지 않는다.
listener가 다시 저장하는 경우의 재진입 guard도 유지하며 예외 후 초기화한다.

이 hook은 durable outbox나 JMAP 변경 원장이 아니다. listener 예외는 이미 commit된 DB를
되돌리지 않으며, 알림의 재전송도 보장하지 않는다. 변경 원장을 구현할 때는 이 알림만
구독해서 기록하지 않고 도메인 transaction 안에서 함께 기록해야 한다.

## 검증

개발 wp-env 전용:

```sh
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-card-writes.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-contacts.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-account-access.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-labels.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-directory.php --path=/var/www/html
```

신규 audit는 DB 오류를 주입해 문서·인덱스·소속·book revision 복구와 알림 억제를 검사한다.
START/COMMIT·savepoint 생성/해제·Card INSERT/UPDATE·index DELETE/INSERT·book revision·초기 filing 실패를 포함한다.
별도 DB connection을 사용해 read와 조건부 UPDATE 사이에 경쟁 revision을 commit하는
결정적 interleaving과, commit 후 listener에서 다른 connection으로 새 값을 읽는 것을 검사한다.
다수 작업자의 부하 시험이나 모든 deadlock 순서를 검증한 것은 아니다.

실행 결과: 쓰기 33개, Contacts 335개, Account 인가 33개, labels 40개, directory 34개로
총 475개 통과. PHP 문법 검사와 `git diff --check`도 통과했다. 브라우저 UI 검증은 하지 않았다.

## 남은 범위

- Account/type별 state, 변경 sequence, 생성·수정·삭제 원장, 보존 기간과 `/changes`는 아직 없다.
- 단독 membership 제거, Card 삭제, 모든 AddressBook 변경·label 명령은 아직 이 runner로 통일하지 않았다.
- profile 생성·Actor 연결·lookup import가 Card 저장 뒤 별도로 쓰는 provenance/binding 전체를 하나의 합성 작업으로 바꾸지는 않았다.
- legacy schema upgrade의 모든 직접 SQL과 실패 시 버전 표시 정책은 후속 검토 대상이다.
- label 명령의 book-first lock과 Card 저장 lock 사이 경합·잠금 순서 및 재시도 정책은 별도 검토가 필요하다.
- JMAP ifInState·PatchObject·부분 성공 응답·copy는 미구현이다.
- Self Card 발견 확장, Principal 도입, 타입별 주소록 제한과 미소속 Card 정책은 바꾸지 않았다.

다음은 모든 변경 경로의 범위를 확인한 뒤 Account/type 변경 원장을 이 저장 경계에 결합하는 작업이다.
그 기반과 RFC 필수 메서드가 준비되기 전 `urn:ietf:params:jmap:contacts`를 광고하지 않는다.
