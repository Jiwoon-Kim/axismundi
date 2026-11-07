# Bridge Shared Inbox

2026-11-07. 로컬 구현 및 wp-env 검증.

## 소유 경계

기존 sharedInbox가 처리되지 않았던 것은 아니다. 플러그인 메인 파일의
activitypub_inbox_shared 훅이 공식 HTTP 라우트에서 검증된 활동을 받아
Bridge 원장 기록자로 넘기고 있었다.

이번 변경은 광고 주소와 HTTP 진입점도 Bridge 소유로 옮긴다:

- endpoints.sharedInbox: /wp-json/axismundi/v1/activitypub/inbox
- POST 전용. 공용 inbox의 멤버 GET은 제공하지 않는다.
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

tests/audit-actor-inbox.php: 66개 검사 통과.

- 무서명 shared POST 거부.
- 액터별 inbox용 서명을 shared URL에 재사용하면 거부.
- 실제 RSA 서명 shared POST는 202, 명시된 로컬 대상 2개의 receipt.
- 동일 요청 재전송 시 receipt 중복 없음.
- accepted 로컬 follower의 Public 수신. 관련 없는 로컬 액터에게 확장 안 함.
- 기존 액터별 inbox, 권한, Block 및 원장 트랜잭션 회귀 검사 유지.

tests/audit-inbox-handoff.php는 기존 공식 shared 경로의 초기 수신/중복/Follow/Actor Update
검사를 통과한 뒤, 폐기된 Note CPT 상수 AXISMUNDI_NOTE_POST_TYPE를 참조하는
기존 테스트 243행에서 중단됐다. 이 감사 전체를 통과했다고 표시하지 않는다.
Note 플러그인을 재활성화하거나 해당 테스트를 이번 범위에서 다시 쓰지 않았다.

스테이징 배포와 실제 peer 왕복은 하지 않았다. 기존 원격 서버가 캐시한 옛
sharedInbox 주소는 새 Actor 문서를 다시 읽기 전까지 계속 사용될 수 있다.
