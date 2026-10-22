# Session Account Projection

작성일: 2026-10-22. 상태: in-process 구현, HTTP 공개 없음.

## 공식 명세와 제품 정책

근거는 [RFC 8620 §1.6.2](https://www.rfc-editor.org/rfc/rfc8620.html#section-1.6.2),
[§2](https://www.rfc-editor.org/rfc/rfc8620.html#section-2),
[§3.4](https://www.rfc-editor.org/rfc/rfc8620.html#section-3.4)다.

| 항목 | 명세 계약 / 이번 제품 정책 |
|---|---|
| Account | User가 접근 가능한 데이터 경계. 로컬 Actor와 1:1인 것은 Axismundi 정책 |
| accountId | 기존 `a<identity_id>` 매핑 유지. Actor URI나 WP User ID로 교체하지 않음 |
| name | Actor 표시 이름. 인증 username이나 핸들과 독립 |
| isPersonal | 현재 User에게 개인 귀속된 Account인지. 운영 owner 역할만으로 true가 되지 않음 |
| isReadOnly | 전체 Account가 읽기 전용인지. 이번 투영에서는 허용된 provider 중 하나라도 writable이면 false |
| accountCapabilities | 등록된 Account-bound 메서드와 provider가 현재 User/Actor에 허용한 기능만 포함 |
| primaryAccounts | capability별 기본 Account. Actors의 로컬 purpose별 명시적 기본값만 사용 |
| state | Session의 다른 필드 변경을 감지하는 값. 현재 선택한 Actor 자체는 표준 Session 필드가 아님 |

명시적 기본값이 없거나 접근·capability 검사를 통과하지 못하면 primary 항목을 생략한다.
목록 첫 Account나 현재 선택한 Actor를 대신 사용하지 않는다. Core는 Account capability나
primary로 넣지 않는다. 동명 capability를 지원하는 개인 Account가 여러 개여도 임의 선택하지 않는다.

## 구현 API

`modules/rfc8620/session.php`:

- `AccountCapability(properties, writable)`: provider의 현재 Account grant 값.
- `SessionProjector(registry)`: Core 등록 확인 후 레지스트리를 freeze.
- `accounts(user_id)`: `accounts`, `primaryAccounts` 두 객체를 반환. 완전한 Session이 아닌 부분 투영.
- `account(account_id, user_id)`: 단건 접근·provider 권한을 매번 재검사. 요청 엔진 resolver로 사용 가능.
- `build(user_id, transport, core)`: 명시적 전송 계약을 받은 경우에만 전체 필드와 state 조립.

현재 인증 WP User 본인의 정보만 투영한다. 관리자에게 타인의 Session을 조회할 권한을
자동 부여하지 않는다. Actors 서비스가 없거나 관계 마이그레이션이 미완료면 예외로 차단한다.
Actors만 목록과 접근 관계를 소유하며 JMAP에 별도 ACL이나 Account 테이블은 없다.
읽기 과정에서 User preference를 저장하거나 보정하지 않는다.

## Provider 등록

기존 메서드 레지스트리에 `register_account_provider(capability, server_properties,
provider, purpose)`를 추가했다. capability와 그 Account-bound 메서드를 먼저 등록해야 한다.
중복·freeze 이후 등록·Core 등록은 거부한다. 반환한 metadata는 복사해서 외부 변경을 격리한다.

provider는 `(actor, user_id) -> AccountCapability|null` 계약이다. 다음 세 조건을 직접 확인해야 한다.

1. Actors의 해당 기능 할당·활성화 정책.
2. 실제 백엔드와 프로토콜 구현 가용성.
3. 현재 User가 이 Account에서 사용할 수 있는 기능과 제한.

`null`은 비노출이다. 잘못된 반환 타입이나 provider 예외는 전체 조립 실패로 처리한다.
플러그인 설치 여부·owner 역할·핸들 등록만으로 capability를 만들지 않는다.
**현재 실제 데이터 provider는 등록하지 않았다.** Actors 기능 할당 저장과 Contacts provider는
후속 슬라이스이며, 테스트의 `https://example.test/jmap/test`는 합성 fixture일 뿐이다.
따라서 기본 레지스트리에서는 Account capability가 `{}`, isReadOnly가 true다.

`isReadOnly`는 전체 Account 수준 요약이다. 한 capability의 쓰기가 가능하다고 다른
capability의 쓰기도 허용된 것은 아니다. 각 실제 메서드/백엔드는 기능별·레코드별 권한을
다시 검사해야 한다. Session snapshot은 인가 증명이 아니며 `account()`도 이를 대체하지 않는다.

## 전송 계약과 state

필수 URL은 기본값을 발명하지 않는다. `apiUrl`, `downloadUrl`, `uploadUrl`,
`eventSourceUrl`과 Core 제한값 전체를 호출자가 제공해야 한다. HTTPS, 자격증명·fragment 금지,
필수 template 변수를 검사한다. 이번 구현은 각 변수를 `{name}`으로 개별 표기하는 제한된
level-1 template만 허용한다. 범용 URI Template 엔진은 아니다.

Core 필드는 본문의 `maxConcurrentRequests`를 따른다. RFC 예시의 단수 표기는 사용하지 않는다.
정수는 JMAP UnsignedInt 범위를 검사한다. 이 검사는 선언의 형식만 검증하며 endpoint의
실제 존재나 동시 요청 제한 구현을 보증하지 않는다. 실제 HTTP 계층은 동일한 설정으로
Engine의 `max_size`, `max_calls`를 구성하고 나머지 제한을 실제로 강제해야 한다.

URL·제한값·User·Account·권한·기본값·표시 이름을 포함한 반환 객체를 정규화해 SHA-256
state를 만든다. 객체 key 순서 차이는 무시하고 배열 순서는 보존한다. DB state 테이블은 없다.
HTTP 통합 시 최신 조립 결과의 state를 Engine `execute()`의 `session_state`에 전달해야 한다.
Account의 데이터 변경 토큰(`/changes`)과 Session state는 별개다.

아직 발견 URL, Cookie/Nonce/App Password 인증 경계, 캐시 헤더, API·업로드·다운로드·SSE
라우트를 구현하거나 광고하지 않았다. 공개 시 인증을 모든 경로에 적용하고 Session의
HTTP 캐시를 막아야 한다. 테스트 URL과 제한값은 공개 지원 선언이 아니다.

## 검증과 다음 슬라이스

```sh
php products/wordpress/plugins/axismundi-jmap/tests/engine.php
wp eval-file wp-content/plugins/axismundi-jmap/tests/audit-session.php
wp eval-file wp-content/plugins/axismundi-jmap/tests/audit-accounts.php
```

WP audit는 개발 사이트 전용이다. 임시 User·Actor를 만들고 fixture만 정리한다.
Session 테스트는 다중 개인 Account, User 격리, 합성 provider의 읽기/쓰기, 권한 회수,
기능 비활성, 기본값 필터링, state, JSON 객체 형태, URL·제한 검사를 포함한다.
이 테스트들은 CI에 연결하지 않았으며 전체 RFC 준수나 실제 HTTP 동작을 증명하지 않는다.
이번 실행 결과: Session 45개, 기존 Account 23개, 요청 엔진 64개로 총 132개 통과.

다음은 Actors의 기능 할당 계약을 확정하고 RFC 9610 provider 및 AddressBook/ContactCard
읽기 메서드를 연결하는 작업이다. Contacts 저장 형식·기존 편집 권한·변경 추적을 함께
검토해야 하며, 이번 코드만으로 Contacts capability를 활성화하면 안 된다.
