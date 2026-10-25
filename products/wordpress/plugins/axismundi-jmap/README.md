# Axismundi JMAP

현재 상태: `0.1.0-dev` 요청 엔진 구현. 완성된 JMAP 서버는 아직 아니다.

첫 목표는 RFC 8620 코어와 RFC 9610 Contacts를 기존 Actors·Contacts 백엔드에
연결하는 것이다. Contacts의 저장 구조도 재검토 범위이며, 현재 구현을 영구 계약으로
고정하지 않는다. Calendar와 ActivityPub 투영 변경은 이번 범위에서 제외한다.

- [구조 및 논의 항목](docs/ARCHITECTURE.md)
- [RFC 검토와 현재 구현 범위](docs/REFERENCE-RFC8620-AND-EXTENSIONS.md)
- [Session·Account 투영 구현과 공개 전제](docs/IMPLEMENTATION-SESSION.md)
- [공식 명세 목록](https://jmap.io/spec/index.html)
- [RFC 8620](https://jmap.io/spec/rfc8620/)
- [RFC 9610](https://jmap.io/spec/rfc9610/)

메서드 등록·요청 실행·결과 참조·Core/echo와 Actor 기반 Account 식별을 구현했다.
Account 접근 판정은 Actors의 프로토콜 중립 작업 공간 API에 위임한다.
Session·Account의 in-process 투영과 명시적 capability provider 등록을 추가했다.
실제 데이터 provider는 아직 없으며 전체 Session 조립은 URL·Core 제한값을 명시적으로 요구한다.
HTTP 라우트·Session 발견·업로드·Push·Contacts 메서드는 아직 노출하지 않는다.
단, S10-B 검토용 WordPress Account 선택 화면과 nonce POST 어댑터를 추가했다.
`axismundi_actors_account_selection_enabled` 필터가 명시적으로 true일 때만 사용할 수
있으며 기본은 비활성이다. 이것은 표준 JMAP HTTP 메서드가 아니고, 기존 Session의
in-process Account 투영과 Actors의 로그인 세션 쿠키를 사용한다. 생산 쓰기 소비자
이관 및 Opus 검증 전에는 전환 기능을 출시하거나 기본 활성화하지 않는다.
S10-C1은 같은 gate로 Contacts 데이터 workspace를 세션 선택에 연결한다. JMAP의
브라우저 어댑터가 없으면 gate는 켜지지 않는다. 미선택·잘못된 쿠키·권한 철회에는
기존 선택이나 기본 Account로 돌아가지 않는다. 발행 경로 이관은 아직 남아 있다.
새 테이블이나 의존 라이브러리는 없고, 기존 Contacts·Actors 저장 구조도 변경하지 않는다.

목표 의존 방향은 `Contacts -> JMAP -> Actors`다. 현재 Contacts의 직접 Actors 참조를
JMAP으로 전환하는 작업은 아직 수행하지 않았다.

```sh
php products/wordpress/plugins/axismundi-jmap/tests/engine.php
```

실제 WP/Actors 통합 검사는 개발 사이트에서 `wp eval-file`로
`tests/audit-accounts.php`를 실행한다. 임시 사용자와 Actor를 만들고 정리하는 검사이므로
운영 사이트에서는 실행하지 않는다. wp-env 등록·활성화·배포·커밋은 하지 않았다.
Session 투영의 실제 WP 검사는 같은 방식으로 `tests/audit-session.php`를 실행한다.
