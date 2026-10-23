# Actor별 Inbox: S6–S7 구현 기록

2026-10-23. 미커밋 리뷰 대상. 빌드·스테이징 배포·실제 peer 연합 검증은 하지 않았다.

## 구현 범위

- Activities DB 11은 `ax_activity_inbox` 관계 테이블을 추가한다.
  기존 Activity·Actor 식별자, payload, URI는 재번호하거나 덮어쓰지 않는다.
- 멤버십은 `(actor_identity_id, activity_id)`로 유일하다.
  receipt id는 수신 멤버십 기록 순서의 고정 cursor다. 발신자의 published로 정렬하지 않는다.
- record_activity의 선택적 recipient 목록은 새 Activity와 같은 transaction에서 기록된다.
  동일 payload 재수신은 기존 행에 신규 recipient만 추가할 수 있다.
  서로 다른 payload의 같은 URI, remote recipient, DB 실패는 기록을 거절한다.
- Undo로 효과가 사라져도 Inbox의 수신 이력은 삭제하지 않는다.
- Activity 저장 hook은 기존 신규 행 commit 이후 동작을 유지한다.
  재수신으로 recipient만 추가된 경우의 변경 hook/state는 S8 후속 범위다.

## HTTP 경계

새 Actor별 URL: `/wp-json/axismundi/v1/actors/{uuid}/inbox`.
Bridge가 로컬 Actor의 inbox에 이 URL을 투영하며 sharedInbox는 기존 공식 URL로 유지한다.
키 선택 정책은 변경하지 않았다. **Actor별 독립 키·회전 문제는 S3 미완료다.**

POST는 설치된 공식 shared Inbox route의 인자 schema와 공식 Inbox_Controller의
verify_signature를 재사용한다. 요청을 복제·리디렉션·내부 REST 재발행하지 않으므로
서명된 target을 다른 URL로 바꾸지 않는다. 공식 verifier의 defer 설정도 그대로 적용된다.
검증 이후 moderation을 통과한 Activity를 기존 Bridge 기록 경로로 전달한다.
이 경로는 공식 domain handler를 다시 실행하지 않는다.

GET은 현재 인증 WP User의 공통 workspace 접근 관계를 매번 확인한다.
다른 Account 사용자, anonymous, 단순히 HTTP 서명만 가진 remote peer에게는 허용하지 않는다.
Principal 기반 원격 인가·세분화된 Inbox read 권한은 S1 및 후속 정책 범위다.
응답은 private/no-store이고 bto/bcc는 중첩 객체까지 제거한다. 원장 원본은 보존한다.
root와 cursor page를 구분하며 정확한 total은 광고하지 않는다.

기존 공식 per-user/shared POST는 그대로 남아 동일한 원장과 receipt를 기록한다.
기존 공식 per-user GET은 기존의 membership-free 호환 응답을 유지한다.
새 데이터 접근은 새 Actor Inbox GET을 사용한다.
Application Actor·shared Inbox 소유권·공식 OAuth C2S 대응은 이번에 이관하지 않았다.

## 수신 대상과 백필 한계

검증된 Actor별 새 route는 URL의 Actor를 recipient로 기록한다.
기존 route는 공식 user recipient와 명시적인 로컬 주소를 사용한다.
Public이나 attributedTo만으로 모든 로컬 Actor에게 receipt를 만들지 않는다.
collection 주소의 현재 멤버를 과거 수신자로 추정하지 않는다.

백필은 자동 upgrade에서 전체 원장을 스캔하지 않는다. 관리자가 bounded CLI를 실행한다.

```sh
wp axismundi inbox backfill --after=0 --limit=200 --path=/var/www/html
```

응답의 after를 다음 실행에 전달하고 done=true까지 반복한다. 동일 구간 재실행은 안전하다.
without_explicit_target는 복원 근거가 없는 행 수이며 없는 수신자가 아니라 알 수 없는 수신자다.
과거 라우트의 실제 recipient·당시 followers 관계가 저장되지 않았다면 완전 복원은 불가능하다.
따라서 “백필 수 = 전체 실제 수신 수”를 검증 완료 조건으로 삼지 않는다.
백필 receipt 순서는 복원 기록 순서이며 실제 과거 도착 순서를 주장하지 않는다.
백필은 공개 로컬 Actor만 대상으로 한다. 상태 변경으로 제외된 과거 대상을 추측하지 않는다.

## 검증 명령

개발 wp-env에서만 실행한다.

```sh
wp eval-file wp-content/plugins/axismundi-activitypub-bridge/tests/audit-actor-inbox.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-activitypub-bridge/tests/audit-inbox-handoff.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-activities/tests/audit-repository.php --path=/var/www/html
wp eval-file wp-content/plugins/axismundi-object-projections/tests/audit-collection-contracts.php --path=/var/www/html
```

Actor Inbox audit는 권한 격리·철회·중복·충돌·DB 실패·백필·paging·unsigned POST 거부를 검사한다.
디스패치 전용 fixture는 verifier의 명시적 defer hook을 사용한다.
별도의 임시 RSA 키 fixture는 defer 없이 공식 signer/verifier를 거쳐 정상 POST를 수신하고,
본문 변조 및 수신 URL 변경을 거부하는지 검사한다. 키 조회만 fixture 응답으로 제공한다.
이는 외부 네트워크를 통한 peer 왕복 검사는 아니다.
wp-env에서도 수신 fixture와 공식 verifier의 거부 경로는 검증할 수 있다.
스테이징은 실제 peer의 서명·재전송·과거 데이터 품질을 확인하는 후속 검증 환경이다.
인스턴스 row count나 wp-env의 inbound=0은 구현 부재의 증거로 사용하지 않는다.

실행 결과: 신규 Actor Inbox 40개, 기존 handoff 28개, Activity repository 21개,
collection contracts 14개, local endpoints 7개, public outbox 4개, Bridge scaffold 21개로
총 135개 통과. 변경 PHP 9개 문법 검사 및 git diff --check 통과.
브라우저 UI·부하/동시 전달·스테이징 peer 왕복·권한 필터 제거 변이 실행은 하지 않았다.

## 남은 범위

S1–S5 Account/Principal·독립 키·legacy binding, internal 상태 폐기,
S8 mailbox/read-state/query/changes, S9 instance Application/shared Inbox 소유권은 미구현이다.
JMAP mail capability나 자체 capability를 새로 광고하지 않았다. 제품 mailbox UI도 추가하지 않았다.

## 근거

- [ActivityPub Actor](https://www.w3.org/TR/activitypub/#actors)
- [ActivityPub Inbox](https://www.w3.org/TR/activitypub/#inbox)
- [Shared Inbox delivery](https://www.w3.org/TR/activitypub/#shared-inbox-delivery)
- 공식 검증기·schema는 wp-env에 설치된 Activitypub Rest/Inbox_Controller 및 Verification trait에서 확인했다.
