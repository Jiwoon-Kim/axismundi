# Session HTTP 노출

## 표면과 인증

`/.well-known/jmap`의 GET/HEAD는 아래 Session으로 302한다.
POST는 405이고 본문·자격증명을 다른 경로로 재전송하도록 유도하지 않는다.

| 경로 | 메서드 | 동작 |
| --- | --- | --- |
| /wp-json/axismundi/jmap/session | GET | 인증된 현재 User의 Session |
| /wp-json/axismundi/jmap/api | POST | 단일 JMAP 메서드 배치 |
| /wp-json/axismundi/jmap/todo* | 기존 메서드 | 기존 개발 UI 어댑터 |
| /wp-json/axismundi-jmap/v1/api 및 todo* | 기존 메서드 | 리다이렉트 없는 호환 alias |

브라우저는 WP 쿠키 + 정화한 X-WP-Nonce를 사용한다.
일반 다운로드 링크는 Core 표준 `_wpnonce` 쿼리도 검증한다. 쿼리 nonce만으로
인증을 대신하지 않으며 로그인 쿠키가 없거나 nonce가 틀리면 거부한다.
외부 클라이언트는 WordPress가 실제 인증한 Application Password를 사용하며 nonce는
요구하지 않는다. Authorization 헤더가 있다는 이유만으로 허용하지 않는다.
Bearer는 구현하지 않았다. Account 접근·capability·쓰기 권한은 기존 authorize가
요청마다 재검사한다. 로그인이나 앱 비밀번호가 모든 Account 권한을 주지는 않는다.

Session과 API는 같은 SessionProjector 및 transport 계약을 사용한다.
API sessionState가 Session GET의 state와 일치한다.
성공·permission 오류·프로토콜 오류에 private, no-store와
Vary: Authorization, Cookie를 설정한다.

## 부분 구현의 한계

RFC 8620의 Session URL 필드를 모두 낸다. upload/download는
[blob 저장소](BLOB-STORE.md)에 연결하고 EventSource만 인증 후 501을 낸다.
완성된 RFC 8620 전송 서버라고 주장하지 않는다. Blob/copy와 EventSource,
RFC 9404의 인라인 Blob/upload·get·lookup 메서드는 이번 구현에 포함하지 않는다.
HTTP 업로드 상한은 Core maxSizeUpload(기본 10 MiB와 WP 상한 중 작은 값),
maxConcurrentUpload는 사용자당 1이다.

계약 근거: [RFC 8620 Session](https://www.rfc-editor.org/rfc/rfc8620.html#section-2),
[Discovery](https://www.rfc-editor.org/rfc/rfc8620.html#section-2.2).
과거 BRIEF-TRANSPORT-SCOPE.md의 업로드·공개 attachment 설계는 이번 구현 지시가 아니다.
비공개 blob, 불변성, Account·업로더·참조 객체별 접근과 수명은 BLOB-STORE.md에 분리했다.
Todo PoC의 urn:com.example:jmap:todo 교체도 공개 출시 전 별도 작업으로 남긴다.

HTTPS 검증은 유지한다. 개발용 wp-env를 위해 WP 환경이 local/development이고
호스트가 localhost·127.0.0.1·::1인 경우만 HTTP를 허용하는 NON-STANDARD 예외를
명시했다. production HTTP나 임의 개발 호스트는 이 예외에 들지 않는다.

## 검증

별도 WordPress 7.1.4 + local 환경, 자동 cron 비활성 상태에서 확인했다.

- audit-session-http.php: 실제 HTTP 35개. discovery, 쿠키 nonce 누락·오류,
  Session, Core/echo, 구 POST 무리다이렉트, state 일치, malformed JSON,
  잘못된 blob 경로 거부, EventSource 501, 실제 앱 비밀번호 생성·호출·폐기 후 거부.
- audit-http-transport.php: 18개.
- audit-session.php: 50개. Account 부여·회수·read-only 및 재검사 포함.
- audit-runtime.php: 34개.

신규 기능을 스테이징에 배포하거나 배포 ZIP·공개 버전을 바꾸지 않았다.
