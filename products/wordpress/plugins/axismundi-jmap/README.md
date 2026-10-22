# Axismundi JMAP

현재 상태: `0.1.0-dev` 요청 엔진 구현. 완성된 JMAP 서버는 아직 아니다.

첫 목표는 RFC 8620 코어와 RFC 9610 Contacts를 기존 Actors·Contacts 백엔드에
연결하는 것이다. Contacts의 저장 구조도 재검토 범위이며, 현재 구현을 영구 계약으로
고정하지 않는다. Calendar와 ActivityPub 투영 변경은 이번 범위에서 제외한다.

- [구조 및 논의 항목](docs/ARCHITECTURE.md)
- [RFC 검토와 현재 구현 범위](docs/REFERENCE-RFC8620-AND-EXTENSIONS.md)
- [공식 명세 목록](https://jmap.io/spec/index.html)
- [RFC 8620](https://jmap.io/spec/rfc8620/)
- [RFC 9610](https://jmap.io/spec/rfc9610/)

메서드 등록·요청 실행·결과 참조·Core/echo와 Actor 기반 Account 식별을 구현했다.
Account 접근 판정은 Actors의 프로토콜 중립 작업 공간 API에 위임한다.
HTTP 라우트·Session 발견·업로드·Push·Contacts 메서드는 아직 노출하지 않는다.
새 테이블이나 의존 라이브러리는 없고, 기존 Contacts·Actors 저장 구조도 변경하지 않는다.

목표 의존 방향은 `Contacts -> JMAP -> Actors`다. 현재 Contacts의 직접 Actors 참조를
JMAP으로 전환하는 작업은 아직 수행하지 않았다.

```sh
php products/wordpress/plugins/axismundi-jmap/tests/engine.php
```

실제 WP/Actors 통합 검사는 개발 사이트에서 `wp eval-file`로
`tests/audit-accounts.php`를 실행한다. 임시 사용자와 Actor를 만들고 정리하는 검사이므로
운영 사이트에서는 실행하지 않는다. wp-env 등록·활성화·배포·커밋은 하지 않았다.
