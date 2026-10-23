# S3b Actor별 서명 키

2026-10-24. 구현·로컬 검증 단계다. 운영 키 전수 교체, S4 개방, HTTP 회전 API 및 관리 UI는 포함하지 않는다.

## 신규 및 기존 Actor

신규 로컬 Actor는 RSA 2048비트 키를 생성하고 identity·접근 관계·키·활성 포인터를 같은
트랜잭션으로 저장한다. 키 저장 실패 시 Actor 생성도 실패한다. 키 생성은 잠금 전에 수행한다.
기존 upstream_user/upstream_application 바인딩은 마이그레이션으로 변경하지 않는다.
따라서 기존 Application 키 공유는 각 Actor의 명시적 회전 전까지 남는다.
키 공유가 Actor 문서의 허위성을 뜻하지는 않는다. 개선하는 것은 격리 및 독립 회전 능력이다.

Actors DB 36.0의 `ax_actor_signing_keys`는 원격 공개키 캐시와 별도다.
Actor의 `local_signing_key_id`가 활성 키를 참조하고 `transport_kind=actor`가 provider를 선택한다.
공개·비밀키는 동일 키 쌍인지 검증한다. 누락·손상·복호화 실패는 upstream 키로 폴백하지 않는다.
disabled/tombstone 상태도 현재 DB 상태로 재검사해 로컬 비밀키를 제공하지 않는다.

## 보관 및 운영 주의

비밀키는 AES-256-GCM, 임의 12바이트 nonce, 16바이트 tag로 암호화한다. 공개키 지문을 AAD로 사용한다.
기본 암호화 키는 WordPress auth/secure_auth salts에서 도출한다.
운영에서는 최초 키 생성 전에 별도 지속 키를 설정할 수 있다:
`AXISMUNDI_ACTORS_KEY_ENCRYPTION_KEY` = base64로 인코딩한 무작위 32바이트 키.
이 키는 DB와 독립적으로 안전하게 백업해야 한다.

**암호화 키를 바꾸면 기존 secret_box는 복호화되지 않는다.** 기본 모드에서 WP salts 변경도 같다.
이미 생성한 뒤에 상수를 추가하는 것도 자동 전환이 아니다. 이번에 재암호화 도구는 제공하지 않는다.
운영 변경 전에는 기존 암호화 키 보존·복구 또는 별도 재암호화 절차가 필요하다.
자동으로 새 키를 생성해 이 실패를 숨기지 않는다. DB 읽기 유출 방어이지 PHP 실행 권한 침해 방어는 아니다.

`axismundi_actors_signing_key_snapshot()`의 비밀키는 신뢰한 서버 코드의 현재 요청에서만 사용한다.
REST·MCP·캐시·Activity JSON·전달 큐에 이 함수나 반환 배열을 노출하지 않는다.
큐에는 기존 actor_uri/key_id만 저장하고 worker가 현재 키를 해소한다.
worker는 투영된 공개키와 실제 비밀키의 일치도 검사한다.

## 명시적 회전

서버 내부 API: `axismundi_actors_rotate_signing_key(identity_id, expected_fingerprint)`.
현재 로그인 User가 발행 가능한 Account의 owner여야 한다. 사이트 관리자나 발행 가능한 editor라는
이유만으로 허용하지 않는다. HTTP 경로를 추가한다면 별도 CSRF/인증 계약이 필요하다.

잠금 후 status와 owner 관계를 재검사하고 현재 공개키의 SHA-256 지문을 비교한다.
오래된 지문은 실패하므로 동시에 시작한 회전이 연속 적용되지 않는다.
새 키·활성 포인터·이전 키 retirement·공개 이력을 원자적으로 반영한다.
이전 로컬 비밀키 ciphertext는 제거하지만 공개키·실행자·선행 지문·시각은 남긴다.
기존 upstream 키는 다른 Actor도 사용하므로 수정·삭제하지 않는다.

Actor URI와 `#main-key`는 유지한다. 기존 transport_user_id도 유지해 구 per-user Inbox 및
User 모더레이션 문맥이 사라지지 않게 한다. 성공 후 내용에 비밀키가 없는 회전 hook과 기존
Actor profile Update 경로를 호출한다. 이 후속 연합 기록은 키 트랜잭션과 별개이며, 기존
`axismundi_act_actor_update_failed` 진단 경로를 따른다. 모든 peer의 즉시 캐시 갱신을 보장하지 않는다.

## 검증

Bridge `tests/audit-signing-keys.php`는 신규 Group/Service/추가 Person의 독립 키, RSA 검증,
암호화·손상·권한·지문 충돌·INSERT/포인터/COMMIT 실패, retirement 이력, Actor Update,
큐 참조 및 구 per-user 라우트 보존을 검사한다.
기존 Inbox 감사의 legacy fixture는 pre-S3b 바인딩을 명시적으로 재현한다.
S4 UNIQUE 해제는 S3b 독립 리뷰 이후 별도 작업이다.

로컬 결과: 서명 키 44건, Actor Inbox 57건, outbound delivery 40건,
ownership 29건, Account operations 51건, JMAP Session 47건 통과.
지문 비교 제거 변이는 오래된 지문 거부 검사에서, owner 검사 제거 변이는 발행 가능한
editor의 회전 거부 검사에서 실패했다. 정상 코드 복원 후 키 감사를 다시 통과했다.
암호 API 기준: [PHP OpenSSL encrypt](https://www.php.net/manual/en/function.openssl-encrypt.php).
