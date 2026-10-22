# JMAP Plugin Architecture

작성일: 2026-10-22

상태: 개발 중. 요청 엔진과 Account 식별 외의 책임 분배는 초안이며,
공개 프로토콜 지원이나 완전한 RFC 준수를 의미하지 않는다.

## Scope

첫 목표는 RFC 8620 코어와 RFC 9610 Contacts다. JMAP 프로토콜과 Contacts 백엔드를
함께 검토할 수 있으며, 독립 플러그인이라는 이유로 기존 저장 구조를 동결하지 않는다.
Calendar는 Account·acting Actor·Event 발행 주체를 함께 조정해야 하므로 후속 범위다.
Mail, Sharing, Quotas, Blob Management 확장, WebSocket, VAPID 확장은 이번에 구현하지 않는다.
단, 코어 자체에 포함된 업로드·다운로드와 변경 알림 요구는 별도로 범위를 점검해야 한다.

JMAP은 IETF 표준이며 IANA는 관련 등록부를 관리한다. RFC 8620의 명칭은
JSON Meta Application Protocol이다. ActivityStreams는 데이터 모델,
ActivityPub은 C2S·S2S 프로토콜로 구분한다.

## Current Evidence

다음은 현재 코드에서 읽은 사실이며 런타임 검증 결과는 아니다.

- Contacts `includes/schema.php`: Card 전용 테이블에 `owner_actor_id`, `card_json`,
  `uid`, `revision`을 저장한다. 주소록 소속은 별도 membership 테이블이다.
- Contacts `includes/address-books.php`: `axismundi_contacts_can_use_book()`이
  WP User와 소유 Actor 사이의 접근 권한을 검사한다.
- Contacts `includes/rest-draft.php`: Card 편집 권한을 현재 User와 Card 소유 Actor로 검사한다.
- OP `includes/object-registry.php`: 로컬 source profile 등록과 해소 경계가 존재한다.
  이 구현은 참고 대상이지 JMAP에 복사할 계약은 아니다.

현재 Card의 revision만으로 Account 단위 변경 추적이나 삭제 이력이 충족된다고
판단하지 않는다. 현재 `card_json` 저장도 확정된 미래 설계로 취급하지 않는다.

## Proposed Layout

아래는 목표 구조다. 현재 구현된 파일은 README와 RFC 검토 문서에 명시한다.

```text
axismundi-jmap.php                 bootstrap, dependency checks
includes/
  engine/                         request dispatch, references, errors, limits
  registry/                       capabilities, data types, methods, providers
  integrations/wordpress/          authentication, Account resolver, HTTP routes
modules/
  rfc8620/                        Session and core protocol contracts
  rfc9610/                        AddressBook and ContactCard protocol mapping
docs/
tests/
```

RFC 번호는 기능의 출처와 검증 축으로 사용한다. 공통 엔진을 RFC별로 복제하지 않으며,
RFC 사이 의존성과 capability 협상을 명시한다. Contacts 전용 모듈의 물리적 위치를
JMAP에 둘지 Contacts에 둘지는 아직 결정하지 않았다.

## Registry Boundary

백엔드를 JMAP JSON으로 표현하는 명시적 등록 경계가 필요하다. 다만 JSON 직렬화만으로
프로토콜이 완성되는 것은 아니므로 다음 책임을 구분해서 설계한다.

| 등록 대상 | 책임 |
| --- | --- |
| Capability | 서버·Account 지원 범위, 의존 기능, capability별 제한 |
| Data type | AddressBook·ContactCard 등의 속성과 메서드 계약 |
| Method | 정확한 메서드 이름, 인자 검증, 오류와 처리기 |
| Backend provider | 인가된 Account 범위의 조회·투영·쓰기·검색·변경 추적 |

OP의 AS2 vocabulary 레지스트리와 JMAP capability 레지스트리는 공유 의미론이 아니다.
공통으로 참조할 수 있는 것은 도메인 식별자와 백엔드 서비스이며, wire 속성이나
정규화 규칙을 자동 공유하지 않는다. 런타임 외부 호출로 handler를 등록할 수 있게 하지 않는다.
중복 등록·알 수 없는 타입·누락된 처리기의 실패 방식은 구현 전에 정한다.

Contacts는 JMAP 경유 변경뿐 아니라 기존 편집 화면·가져오기 경유 변경도 추적해야 한다.
따라서 변경 이력을 JMAP 요청 처리기에서만 기록하는 구조는 후보에서 제외한다.

## User And Account

- 인증 주체는 WordPress User다.
- 개인 Account는 해당 User의 Person Actor에 대응한다.
- 공유·공동 관리 Account는 기존 managed Actor 관계를 활용한다.
- 로컬 Actor와 Account는 같은 단위다. 개발 구현은 기존 숫자 identity ID에 `a`를 붙인다.
  별도 Account 테이블이나 User-Account 관계를 만들지 않으며, 공개 배포 전 형식을 검토한다.
- UI의 acting Actor는 요청할 Account를 선택하는 맥락이다. 서버는 요청의 Account ID와
  현재 User의 권한을 매번 검사하며, 전역 acting Actor 선택값만 믿지 않는다.
- Account 노출 권한과 타입별 읽기·쓰기 권한을 구분한다. 현재 Contacts의 관리 권한이
  장래의 세분화된 AddressBook 권한까지 표현한다고 가정하지 않는다.
- 원격 Actor 캐시를 자동으로 로컬 JMAP Account에 포함하지 않는다.
- JMAP은 Actors와 WP User에 의존한다. RFC 9610 매핑은 JMAP 쪽에서 소유하고,
  Contacts의 RFC 9553 데이터와 Actor/Account를 연결한다. 현재 Contacts의 직접 Actor
  의존성 제거는 소유·인가·기존 편집 경로를 함께 검토할 별도 변경이며 아직 수행하지 않는다.
- Account 식별 권한은 발행 권한과 다르므로 handle lock이나 현재 acting Actor를 요구하지 않는다.
  비활성·삭제 Actor와 관계 없는 관리자는 배제하고, 데이터 타입별 권한은 provider가 더 검사한다.

## Storage Review

Contacts의 영속화 형식은 재검토 대상이다. 다음 후보를 실제 요구와 비교한다.

1. JSContact JSON을 원본으로 유지하고 검색용 컬럼·인덱스를 파생한다.
2. 정규화된 컬럼·관계 테이블을 원본으로 삼고 JSContact를 투영한다.
3. 핵심 관계·식별자는 컬럼으로, 확장·복합 값은 구조화 JSON으로 유지한다.

선택 기준은 확장 속성·다중값·언어별 값의 무손실 왕복, 검색 비용, PatchObject 갱신,
검증, 트랜잭션, 변경 추적, 기존 데이터 마이그레이션이다. 저장을 나눈다는 이유만으로
JSContact의 미지 확장 속성이나 값별 식별자를 버려서는 안 된다.
레지스트리는 특정 테이블 컬럼이나 `card_json`에 직접 결합하지 않는 provider 경계를 제안한다.
최종 선택 전에는 스키마 변경이나 두 원본의 동시 쓰기를 시작하지 않는다.

## Implementation Gates

`php products/wordpress/plugins/axismundi-jmap/tests/engine.php`는 요청 엔진 회귀 검사다.
`tests/audit-accounts.php`는 WP CLI로 실행하는 실제 Actors 통합 검사다.
둘 다 CI에는 연결하지 않았고, RFC 준수 검사 전체를 대신하지 않는다.
아래는 전체 구현까지 필요한 검증 항목이다.

- Session·요청 envelope·capability 협상·메서드 오류·결과 참조·생성 ID 참조.
- 개인·managed Account 격리, 권한 철회, 다른 Account ID 주입, 비공개 Card 누출 방지.
- Card·주소록 CRUD, 다중 주소록 소속, 속성 선택, PatchObject와 동시 갱신 충돌.
- 생성·수정·삭제·소속 변경에 대한 state 및 changes, 보존 기간 초과 처리.
- query 정렬·필터·페이지 처리와 queryChanges의 지원 조건.
- 복합 쓰기와 변경 이력 기록의 원자성, 재시도 시 동작.
- JSON/컬럼 저장 후보의 왕복 보존과 마이그레이션 검증.

부분 구현 동안 지원하지 않는 capability를 광고하지 않는다. Core의 필수 기능과
허용된 미지원 응답을 RFC별로 대조한 뒤에만 지원 범위를 문서화한다.

## Discussion Before Code

1. Contacts의 원본 저장 형식과 provider의 최소 인터페이스.
2. RFC 모듈과 도메인 플러그인 사이 등록 코드의 소유 위치.
3. state·삭제 이력·트랜잭션을 도메인별로 둘지 공용 기반을 둘지.
4. 인증 방식, Session 발견 경로, 실제 API 경로와 Core 필수 기능의 첫 구현 범위.
5. 플러그인 의존성 방향과 Contacts 부재 시 capability 비노출 방식.

## References

- [JMAP specifications](https://jmap.io/spec/index.html)
- [RFC 8620: Core](https://jmap.io/spec/rfc8620/)
- [RFC 9610: Contacts](https://jmap.io/spec/rfc9610/)
