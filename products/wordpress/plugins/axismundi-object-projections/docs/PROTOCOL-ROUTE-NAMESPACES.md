# 프로토콜 문서와 앱 API 경로

2026-11-07 오너 결정. 정체성 URI는 유지하고 HTTP 프로토콜 표면을 분리한다.

## 경계

| 표면 | 정규 REST 네임스페이스 | 소유 |
|---|---|---|
| AP 컬렉션 GET/HEAD | axismundi/activitypub | OP |
| C2S outbox POST | axismundi/activitypub | Activities |
| inbox/sharedInbox POST | axismundi/activitypub | Bridge |
| NodeInfo 2.1 | axismundi/nodeinfo | Actors |
| 앱 객체 조회와 운영 동작 | axismundi/v1 | 기존 소유자 유지 |

AP 경로는 /inbox, /actors/{uuid}/inbox, /outbox, /followers, /following,
/liked, /featured, /sent, /moderators와 /objects/replies, /likes,
/shares, /emoji-reactions, /media/{id}/used-in이다. 액터별 접미사는
/actors/{uuid} 아래에 있다. 각 GET은 Activities/Actors 등의 도메인 조회를 사용한다.

해시태그 /hashtags/{id}/members는 이름과 달리 AS2 Collection이 아니라
앱 조회 결과를 반환한다. 따라서 /objects/{숫자 id}, /objects/read,
/actors/{uuid}/moderators/initialize와 함께 axismundi/v1에 남긴다.
JMAP 경로와 NodeInfo 관리 화면은 이번 변경 범위가 아니다.

## 호환

구 axismundi/v1의 AP GET/HEAD는 302로 정규 주소를 가리킨다.
필터와 페이지 쿼리는 보존하며, 앱 API를 포괄하는 전역 리다이렉트는 사용하지 않는다.
구 shared 경로 /activitypub/inbox만 새 네임스페이스의 /inbox로 매핑한다.
구 POST는 같은 핸들러를 원래 URL에 등록한다. 서명 검증 전에 경로를
다시 쓰거나 서명된 POST를 리다이렉트하지 않는다. 호환 종료 기한은 정하지 않았다.

우리 로컬 featured/moderators 제어 대상과 followers 주소의 구 참조는
비교 시에만 현재 주소로 해석한다. 로컬 등록 액터의 정확한 주소에 한정하며
외부 호스트는 같은 UUID여도 변환하지 않는다. 원장 payload와 정체성 URI는
다시 쓰지 않는다. 이 처리는 과거 제어의 재시도와 팔로워 가시성/배송 판정을 보존한다.

/actors/{uuid}, /activities/{uuid}, /?p={id}, /objects/{uuid},
/remote-objects/{hash}는 옮기지 않는다. 원격 객체의 AS2 id도 바꾸지 않는다.

## NodeInfo

/.well-known/nodeinfo는 기존 discovery 문서이며 href 하나로
/wp-json/axismundi/nodeinfo/2.1을 광고한다. 문서는 Actors가 REST로 제공한다.
이전 /nodeinfo/2.1과 plain query 문서 진입점은 302로 새 주소를 가리킨다.
정확한 well-known 경로는 parse_request 우선순위 0에서 처리해, 저장된 rewrite가
공식 플러그인 REST 경로를 가리켜도 REST dispatch 전에 우리 discovery로 돌린다.
인접 경로는 가로채지 않는다. 스테이징이 공식 문서를 냈던 원인이 무엇인지는
이 로컬 변경으로 소급 확정하지 않는다.

## 검증 명령

- Bridge tests/audit-actor-inbox.php: 새 경로의 수신/공개 필터/권한,
  구 URL에 직접 서명한 POST 수락과 다른 경로 서명 재사용 거부를 검사한다.
- Activities tests/audit-c2s-outbox-http.php: 실제 HTTP 쿠키/nonce 제출,
  구 POST의 동일 idempotency 결과, 구 GET 302를 검사한다.
- OP tests/audit-protocol-routes.php: 구 컬렉션 필터 보존, 앱 API 비이동,
  NodeInfo REST/discovery, stale rewrite와 인접 경로 경계를 검사한다.
- OP tests/audit-actor-outbox.php, audit-replies-collection.php,
  audit-collection-contracts.php: 기존 컬렉션 회귀 검사다.

검증은 wp-env에서 수행하며 스테이징 배포나 외부 수신 서버로의 배송을 뜻하지 않는다.

이번 실행 결과: inbox 86, C2S 실제 HTTP 74, outbox 17, 컬렉션 계약 18,
프로토콜 경로 22, featured 67, moderators 48, NodeInfo 8개 검사가 통과했다.
총 340개다. 기존 replies 감사는 비활성화된 Note 플러그인의
AXISMUNDI_NOTE_POST_TYPE 상수 의존으로 42행에서 중단됐다. 이를 통과로 세지 않았고,
Note를 다시 켜거나 이번 경로 변경에 무관한 fixture를 재작성하지 않았다.
PHP 문법 검사와 git diff --check도 통과했다. 커밋/푸시/배포는 하지 않았다.
