# Bridge Shared Inbox

2026-11-07. 로컬 구현 및 wp-env 검증.

## 소유 경계

기존 sharedInbox가 처리되지 않았던 것은 아니다. 플러그인 메인 파일의
activitypub_inbox_shared 훅이 공식 HTTP 라우트에서 검증된 활동을 받아
Bridge 원장 기록자로 넘기고 있었다.

이번 변경은 광고 주소와 HTTP 진입점도 Bridge 소유로 옮긴다:

- endpoints.sharedInbox: /wp-json/axismundi/activitypub/inbox
- 같은 주소에서 Bridge가 POST 수신을, OP가 공개 GET OrderedCollection을 제공한다.
- 기존 공식 /activitypub/1.0/inbox 및 Bridge handoff 훅은 호환용으로 유지한다.
  서명된 요청을 다른 URL로 리다이렉트하거나 내부 REST 재전송하지 않는다.
- 서명 검증은 설치된 공식 Inbox_Controller.verify_signature를 그대로 사용한다.
  요청의 원래 URL과 body를 검증하며, 공식 POST 인자 스키마도 재사용한다.
- 액터별 inbox와 record_inbox, moderation, Block, 원장 및 receipt 저장을 공유한다.

## 수신 대상

명시된 로컬 Actor 주소와 accepted 로컬 팔로워를 해소한다.
Public 또는 원격 발신자의 알려진 followers 주소로 수신한 경우에만 팔로워를 확장한다.
Public은 사이트 전체 broadcast가 아니며, attributedTo/본문의 uuid는 수신 대상 근거가 아니다.
개별 대상의 moderation과 Block 판정은 원장 기록 전에 적용한다.
해소할 로컬 대상이 없으면 400, 전부 차단되면 403, 저장소 장애는 503.

원격 Actor별 수신 결과나 sharedInbox 뒤의 개별 사용자 처리를 보장하는 변경은 아니다.

## 검증

tests/audit-actor-inbox.php: OP 이전과 프로토콜 경로 호환 검사 포함.

- 무서명 shared POST 거부.
- 액터별 inbox용 서명을 shared URL에 재사용하면 거부.
- 실제 RSA 서명 shared POST는 202, 명시된 로컬 대상 2개의 receipt.
- 동일 요청 재전송 시 receipt 중복 없음.
- accepted 로컬 follower의 Public 수신. 관련 없는 로컬 액터에게 확장 안 함.
- 기존 액터별 inbox, 권한, Block 및 원장 트랜잭션 회귀 검사 유지.

## 공개 컬렉션

Bridge 설정의 Public shared Inbox collection은 기본 on이다.
끄면 GET은 404가 되지만 서명된 POST 수신에는 영향을 주지 않는다.
응답은 no-store이며, 루트는 OrderedCollection, 페이지는 OrderedCollectionPage다.

Activities의 receipt에 received_via를 추가한다. 신규 수신은 shared 또는 actor를
기록하고 기존 행은 빈 값(미확인)으로 둔다. 마이그레이션 백필은 하지 않는다.
실제로 shared 재수신을 관측한 경우 기존 receipt를 shared로 승격할 수 있으며,
이후 actor 경로의 재수신은 그 관측을 지우지 않는다.

멤버는 inbound 활동 중 shared receipt가 있고 Public으로 주소 지정된 활동뿐이다.
SQL 후보 필터 외에도 저장된 audience를 다시 검사한다. 여러 수신자 receipt는
활동별로 묶은 뒤 커서를 적용하므로 페이지에 같은 활동을 중복 표시하지 않는다.
기존 미확인 receipt와 actor 경로로만 수신한 Public 활동은 포함하지 않는다.

출력은 중첩된 bto/bcc를 제거한다. Create/Update의 인라인 객체는 URI 참조로
내보내 과거 본문 스냅샷을 다시 공개하지 않는다. 이 컬렉션은 액터별 inbox의
단순 합집합이나 사이트 전체 공개 원장 덤프가 아니다.

추가 검사는 기본 on, 미확인/actor-only 제외, 실제 재수신에 의한 승격,
수신자 중복 제거 후 페이징, 잘못된 Public 후보 플래그에 대한 재검사,
잘못된 커서 400, 설정 off의 GET 404와 POST 202를 확인했다.
관리 화면에서도 기본 체크 상태를 확인했다. 실제 원격 배송은 실행하지 않았다.

## 읽기와 전송 경계

개인 inbox와 sharedInbox 모두 GET/HEAD 라우트와 AS2 표현은 OP의
includes/integrations/inbox.php가 소유한다. Bridge는 같은 URL의 POST와
서명 검증, 수신 대상 해소, 배송을 소유한다. URL은 바꾸지 않았고 기존 Bridge
주소 공급 함수는 OP 주소 함수로 위임한다. 공개 설정 키와 관리 화면은 유지한다.
정규 네임스페이스는 axismundi/activitypub이다. 구 axismundi/v1 GET은 302로
연결하고 POST는 원래 URL에서 계속 수락한다. 상세 경계와 검증 명령은 OP의
docs/PROTOCOL-ROUTE-NAMESPACES.md를 따른다.

Activities의 ap-inbox, ap-shared-inbox 계약이 조회와 멤버십의 출처다.
개인 inbox의 현재 Account 권한은 Activities reader가 계속 검사한다.
OP는 공통 assemble_collection, JSON-LD context, Actor collection 응답 함수를
사용한다. GET 등록은 POST 서명 검증기나 upstream POST 스키마에 의존하지 않는다.
비공개 inbox의 private/no-store와 sharedInbox의 no-store는 유지한다.

추가 검사는 각 URL의 단일 OP GET/단일 Bridge POST 등록과, 별도 REST 서버에서
Bridge POST 등록 없이 OP 읽기 라우트만 등록하는 경계를 확인한다.
OP의 audit-collection-contracts.php는 sharedInbox 계약을 포함해 18개 통과했다.

tests/audit-inbox-handoff.php는 기존 공식 shared 경로의 초기 수신/중복/Follow/Actor Update
검사를 통과한 뒤, 폐기된 Note CPT 상수 AXISMUNDI_NOTE_POST_TYPE를 참조하는
기존 테스트 243행에서 중단됐다. 이 감사 전체를 통과했다고 표시하지 않는다.
Note 플러그인을 재활성화하거나 해당 테스트를 이번 범위에서 다시 쓰지 않았다.

스테이징 배포와 실제 peer 왕복은 하지 않았다. 기존 원격 서버가 캐시한 옛
sharedInbox 주소는 새 Actor 문서를 다시 읽기 전까지 계속 사용될 수 있다.
