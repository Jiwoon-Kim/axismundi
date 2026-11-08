# Axismundi JMAP

현재 상태: `0.1.1-dev` 코어 런타임·Account 정책 및 Todo 공유 PoC 구현.
완성된 공개 JMAP 서버는 아직 아니다.

이번 목표는 RFC 8620 코어 백엔드다. Contacts/AddressBook 프로토콜 구현은 후속 작업이며,
Todo PoC가 Principal·공유·동기화 계약의 첫 소비자다. Calendar와 프로필 카드의
저장 위치도 이번 범위에서 변경하지 않는다.

- [구조 및 논의 항목](docs/ARCHITECTURE.md)
- [RFC 검토와 현재 구현 범위](docs/REFERENCE-RFC8620-AND-EXTENSIONS.md)
- [Session·Account 투영 구현과 공개 전제](docs/IMPLEMENTATION-SESSION.md)
- [코어 런타임·Account 정책 구현](docs/IMPLEMENTATION-CORE-RUNTIME.md)
- [Session HTTP·인증과 부분 전송 구현](docs/HTTP-SESSION.md)
- [Account blob 저장소와 접근 통제](docs/BLOB-STORE.md)
- [Todo·Principal 공유 PoC와 검증 범위](docs/TODO-POC.md)
- [공식 명세 목록](https://jmap.io/spec/index.html)
- [RFC 8620](https://jmap.io/spec/rfc8620/)
- [RFC 9610](https://jmap.io/spec/rfc9610/)

메서드 등록·요청 실행·결과 참조·Core/echo와 Actor 기반 Account 식별을 구현했다.
Account 접근 판정은 Actors의 프로토콜 중립 작업 공간 API에 위임한다.
Session·Account의 in-process 투영과 명시적 capability provider 등록을 추가했다.
Todo PoC 데이터 provider를 제공하며 전체 Session 조립은 URL·Core 제한값을 명시적으로 요구한다.
쿠키+nonce 및 Application Password 인증 REST API와 Todo 관리자 경로를 제공한다.
Session 발견과 인증된 Session GET은 axismundi/jmap 아래로 노출한다.
Account별 blob 업로드·인증 다운로드 및 Tools의 JMAP Blobs 화면을 제공한다.
blobId는 `b` + SHA-256이며 내용 행과 Account·업로더별 업로드 행을 분리한다.
저장 전 정적 접근 차단을 검사하고 실패하면 업로드를 열지 않는다. Apache에서는
통과했고 nginx 호스트에서는 플러그인이 만들 수 있는 차단 수단이 없다.
RFC 9404 Blob 메서드와 AP uploadMedia 어댑터는 아직 없다.
EventSource는 인증 후 501이며 Contacts 메서드는 아직 없다.
WordPress 선택 화면·아바타 전환기·세션 쿠키 어댑터는 Actors 소유다. JMAP 없이도
동작하며 기존 URL·POST action·nonce 및 PHP 호환 진입점은 유지한다. Actors의
`ax_actors_account_selection_enabled` 옵션(기본 false) 또는 기존 필터로 명시적으로
켜며, 이번 작업에서 사이트 설정은 켜지 않았다. JMAP 메서드는 선택 쿠키를 읽지 않는다.

JMAP은 `ax_jmap_accounts`와 `ax_jmap_account_capabilities`에 관리자 할당·한도를
저장한다. Account ID와 수명은 로컬 Actor에서 파생한다. JMAP Principal은 별도
JMAP 저장소에서 WP 사용자·Account와 연결하며 Actors 운영 부여를 만들지 않는다.
provider 등록만으로 Account에 기능이 허용되지는 않는다.

목표 의존 방향은 `Contacts -> JMAP -> Actors`다. 현재 Contacts의 직접 Actors 참조를
JMAP으로 전환하는 작업은 아직 수행하지 않았다.
AddressBook·ContactCard DB와 RFC 9610 등록은 Contacts가 소유하며 JMAP으로 DB를
이전하지 않는다. 독립 Profile Card 및 Contacts의 Account별 Profile 저장소 후보는
[경계 정정](docs/REVIEW-DEPENDENCY-BOUNDARIES.md#2026-10-28-결정-정정)에 기록했다.

```sh
php products/wordpress/plugins/axismundi-jmap/tests/engine.php
```

실제 WP/Actors 통합 검사는 개발 사이트에서 `wp eval-file`로
`tests/audit-accounts.php`를 실행한다. 임시 사용자와 Actor를 만들고 정리하는 검사이므로
운영 사이트에서는 실행하지 않는다. 이번 런타임은 로컬 wp-env에서 검증했으며,
커밋·패키징·스테이징 배포는 하지 않았다.
Session 투영의 실제 WP 검사는 같은 방식으로 `tests/audit-session.php`를 실행한다.
Account 정책과 공통 서비스는 `tests/audit-runtime.php`로 검증한다.
