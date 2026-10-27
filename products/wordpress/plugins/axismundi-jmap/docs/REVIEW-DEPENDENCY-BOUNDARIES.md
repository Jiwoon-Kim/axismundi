# Account, Collections, Contacts 의존성 검토

## 2026-10-28 결정 정정

이 절이 아래 2026-10-23 조사 당시의 AddressBook 소유권 제안보다 우선한다.

- Contacts가 AddressBook·ContactCard 저장소와 RFC 9610 메서드·capability provider를
  함께 소유하고 JMAP 공통 등록 체계에 등록한다. AddressBook을 JMAP DB로 이전하지 않는다.
- JMAP은 코어 실행·Session·Account 자격/한도 저장과 합성을 소유한다. Actors는 신원·수명과
  principal 관계를 소유한다. 표준 타입이라는 이유만으로 그 DB가 JMAP 코어 소유가 되지는 않는다.
- RFC 9610 §7의 IANA 등록은 표준 식별자 등록이다. 이 플러그인 배치는 별도의 제품 결정이다.

### 독립 Profile Card

공개 Profile Card는 독립 JSContact 문서이며, AddressBook에 저장한 JMAP ContactCard와
같은 리소스로 취급하지 않는다. RFC 9610 §3의 최소 한 AddressBook 소속 요구는 후자에
적용된다. WebFinger 및 선택적 Actor 확장 링크로 발견시키는 정책은 우리 제품 계약이다.
기본 JMAP/AP 메서드만으로 이 문서의 발견을 보장하지 않는다.

Profile Card는 Actor의 공개 아바타·이름·acct 주소, 명시적 프로필 입력, 공개가 허용된 외부
소스에서 합성할 수 있다. Google People는 선택적 외부 소스이지 Actor/Account의 부모 모델이
아니다. OAuth로 읽을 수 있는 비공개 정보를 공개할 수 있다는 뜻으로 해석하지 않는다.

연락처 추가는 공개 문서를 참조해 대상 Account의 AddressBook에 ContactCard를 생성하는
별도 동작이다. 출처와 사용자 편집을 구분하고, 원본 갱신으로 사용자 값을 무조건 덮어쓰지 않는다.
발견 가능성, 공개 동의, 가져오기, 지속 동기화는 서로 다른 정책이다.

### 저장 후보 — 미확정

Contacts에 Account별 Profile 저장소를 두는 안을 후보로 남긴다. 이 저장소는 Account의
새 identity나 Actor object의 복제본이 아니라, 공개 프로필 입력·소스 연결·공개 정책·투영
캐시를 다룬다. AddressBook 멤버십을 부여하거나 JMAP ContactCard로 자동 노출하지 않는다.
필드별 출처·공개 범위·사용자 override 및 캐시 무효화는 구현 전에 정해야 한다.

새 테이블을 곧바로 추가하지 않는다. 기존 Contacts의 `ax_contact_actor_profiles`가 이미
`actor_id`–`card_id` 연결과 `sharing_enabled`, `audience`, `published_json`을 저장한다
(`includes/schema.php`, `includes/profile.php`). 기존 테이블의 확장·분리·대체 중 어느 것이
맞는지 먼저 검토하고, 두 저장소를 병렬 권위로 만들지 않는다. 이름·컬럼·수명·이전 방식은 미정이다.

근거: [RFC 9610 §1.3](https://jmap.io/spec/rfc9610/#section-1.3),
[§3](https://jmap.io/spec/rfc9610/#section-3),
[§7](https://jmap.io/spec/rfc9610/#section-7),
[Google People Person](https://developers.google.com/people/api/rest/v1/people).
이번 정정은 문서만 변경하며 DB·메서드·공개 엔드포인트를 추가하지 않는다.

## 이전 조사 기록

조사일: 2026-10-23. 코드 기준: `0f1b0f6`.
RFC 원문과 소스를 읽은 설계 조사다. 런타임 재검증·마이그레이션·RFC 준수 인증은 아니다.
이 문서에서 제안한 API·저장 경계는 아직 구현하지 않았다.

## 요약

제품 기준은 `Contacts -> JMAP(AddressBook) -> Actors(Account)`다.
Calendar 역시 JMAP의 Account–컬렉션 소속 계층과 연결하는 것이 목표다.
Card와 일정 문서 자체의 데이터 모델은 각각 Contacts와 Calendar가 소유한다.
이 소유권 결정은 RFC가 WordPress 플러그인 구성을 강제해서가 아니라 제품의 모듈 결정이다.

공개 프로필·WebFinger의 Actor–Card 발견 관계는 별도 축이다.
Contacts가 Actors를 직접 참조하는 경로가 남는 것 자체는 오류가 아니다.
Actors가 JMAP Session에 의존하거나, JMAP이 Contacts를 필수 의존해서
`Contacts -> JMAP -> Contacts` 로딩 순환을 만드는 것은 피한다.

## 공식 명세 대조

### RFC 8620

[1.6](https://www.rfc-editor.org/rfc/rfc8620.html#section-1.6),
[2](https://www.rfc-editor.org/rfc/rfc8620.html#section-2),
[5](https://www.rfc-editor.org/rfc/rfc8620.html#section-5)를 확인했다.

- User와 Account는 다르며 같은 인증으로 여러 Account에 접근할 수 있다.
- Account는 여러 데이터 타입을 포함하는 경계다. 레코드 ID의 유일성 범위는 Account와 타입이다.
- isPersonal은 인증 User 기준이지 Actor 타입이나 관리 역할만의 함수가 아니다.
- primaryAccounts는 capability별 기본 Account이고 현재 선택 Account와 다르다.
- Account 전체의 isReadOnly와 타입·레코드별 권한은 구분한다.
- Account=Actor는 Axismundi 결정이다. 표준에는 personal/shared/group Account 타입 enum이 없다.

### RFC 9670

[1.3–1.5](https://www.rfc-editor.org/rfc/rfc9670.html#section-1.3),
[2](https://www.rfc-editor.org/rfc/rfc9670.html#section-2),
[4](https://www.rfc-editor.org/rfc/rfc9670.html#section-4)를 확인했다.

- Principal은 협업·공유 주체다. User에 대응할 수 있지만 User나 Account의 동의어가 아니다.
- Principal의 accounts는 해당 주체의 데이터가 담긴 접근 가능한 Account 목록이다.
- currentUserPrincipalId는 Principal 데이터가 있는 Account의 capability 안에 있다.
- principals:owner는 Account 전용 capability이며 서버 capabilities에 넣지 않는다.
- Sharing 지원 시 Session에는 소유 Account 또는 하나 이상의 레코드를 구독한 Account만 포함한다.
- 권한과 구독은 다르며 공유 권한은 각 데이터 타입이 구체화한다.

Principal을 Me Card 대체물로 도입하지 않는다. Sharing은 단순 프로필 조회 기능이 아니다.

### RFC 9610

[1.3–3](https://www.rfc-editor.org/rfc/rfc9610.html#section-1.3)을 확인했다.

- AddressBook은 Account 안의 ContactCard 컬렉션이다. Card는 항상 하나 이상의 주소록에 소속된다.
- ContactCard.id와 JSContact uid는 별개이며 같은 Account에 동일 uid의 Card가 중복되면 안 된다.
- 공유 단위는 AddressBook이다. myRights는 mayRead/mayWrite/mayShare/mayDelete를 구별한다.
- isDefault는 Account 기준, isSubscribed는 User 기준이다. 기본값은 최대 하나다.
- AddressBook 삭제는 내용 제거 옵션과 마지막 소속 Card의 삭제 동작을 구별한다.
- ContactCard는 JSContact에 id/addressBookIds 등의 프로토콜 속성을 더한 표현이다.

현재 Account 단위 role 허용만으로 AddressBook별 공유·인가를 구현했다고 볼 수 없다.
RFC 9610에는 Account의 대표 Me Card를 지정하는 필드가 없다.

### RFC 9553 및 현재 JSContact 버전

[9553 §2](https://www.rfc-editor.org/rfc/rfc9553.html#section-2)의 Card 모델과
[keywords](https://www.rfc-editor.org/rfc/rfc9553.html#section-2.8.2)를 확인했다.
JSContact는 연락처 문서 형식이며 Account–AddressBook 저장 관계를 규정하지 않는다.
keywords는 태그 집합이고 AddressBook 멤버십과 다르다.

현재 Contacts는 JSContact `2.0`을 저장한다.
[RFC 9982](https://www.rfc-editor.org/rfc/rfc9982.html)는 2.0에서 uid를 선택적으로 만들고
나머지 정의는 9553을 유지한다. 따라서 uid 없는 문서를 곧바로 잘못된 저장으로 판단하지 않는다.
다만 RFC 9610과의 버전 호환·외부 클라이언트 왕복 정책은 provider 구현 전 별도 확정해야 한다.
uid를 몰래 생성하거나 1.0으로 이름만 바꾸지 않는다.

### Calendar 카디널리티 정정

[draft-ietf-jmap-calendars-33](https://datatracker.ietf.org/doc/draft-ietf-jmap-calendars/33/)
§4–5도 컨테이너 비교에 한해 확인했다. 현재는 RFC가 아닌 초안이다.
CalendarEvent는 `calendarIds`를 사용하며 복수 Calendar 소속을 다룬다.
이전 논의의 “calendarId 단일 소속이므로 Card의 N:M과 다르다”는 전제를 철회한다.
공통 컬렉션 기반의 가능성은 열려 있으나 ACL·삭제·개인별 설정까지 같다고 결론내리지 않는다.
Task/Group API 전체와 Calendar 이전 구현은 이번 조사 범위가 아니다.

## 현재 코드와 도입 전후

JMAP 최초 추가 커밋은 `1abd41e`다. 도입 후에도 다음 기존 책임은 아직 이관하지 않았다.

| 영역 | 현재 소스 및 동작 | 목표에서 검토할 이동 |
|---|---|---|
| Account 실체·관계 | Actors account-access.php, workspaces.php, account-operations.php | Actors 유지 |
| Account ID·Session | JMAP accounts.php, rfc8620/session.php | JMAP 유지 |
| AddressBook·기본값·멤버십 | Contacts address-books.php, schema.php | JMAP 9610 계층으로 이관 |
| Card 문서·파생 인덱스 | Contacts cards.php, canonical.php, schema.php | Contacts 유지 |
| 공개 프로필 binding | Contacts profile.php, ax_contact_actor_profiles | Me Card 정책 별도 결정 |
| Actor 공개 Card 조회 | Actors jscontact.php가 Contacts를 선택 호출 | 공개 발견 경로로 별도 취급 |
| Calendar 소속·ACL | Calendar calendar.php, calendar-acl.php, schema.php | 후속 Calendar 설계에서 검토 |
| AS2 컬렉션 표현 | OP collection-registry.php | 표현 계약이지 공통 저장 원장 아님 |

현재 코드 의존성은 `JMAP -> Actors`, `Contacts -> Actors`다.
Contacts dependencies.php는 JMAP 존재를 검사하지 않는다.
JMAP bootstrap에는 8620 엔진·레지스트리·Account 해소·Session만 로드된다.
실제 9610 provider, 9670 모듈, 공개 HTTP Session/API는 없다.
Calendar dependencies.php도 JMAP을 사용하지 않으며 기존 Actor/OP/Activities 경계를 갖는다.

## 리뷰 우선 항목

### 1. Account와 capability 범위

Actors account-access.php:86은 personal_owner_user_id와 owner 역할을 함께 검사한다.
이는 개인 귀속과 운영 권한을 분리한 제품 정책이며 모든 owner 역할을 isPersonal로 만들지 않는다.
계정 생성·철회·마지막 owner·User 삭제 정책은 따로 검증해야 한다.

JMAP SessionProjector는 현재 모든 접근 가능한 로컬 workspace를 투영한다.
8620 기반에서는 현재 범위지만 9670 활성화 시 구독 조건을 추가해야 한다.
현재 provider는 명시적 feature 할당·백엔드·권한을 검사하도록 계약되어 있으나,
설치된 플러그인을 자동 capability로 만드는 실제 provider는 아직 없다.
feature 할당의 영속 저장·공통 API도 별도로 설계해야 한다.

### 2. Registry의 서버/Account 구분

registry.php:42의 register_account_provider는 서버 속성·Account callback을 한 쌍으로 등록한다.
session.php:91은 모든 등록 provider를 서버 capabilities에 넣는다.
따라서 principals:owner를 같은 API에 그대로 등록하면 9670의 Account 전용 조건과 충돌한다.
아직 등록하지 않았으므로 현재 노출 버그가 아니라 확장 전 설계 차이다.
서버 전용·Account 전용·양쪽 capability와 메서드 연결 규칙을 분리하는 슬라이스가 필요하다.

Principal 레코드를 담는 Account 역시 현재 “로컬 nonsite Actor만 Account” 정책으로
표현할지 검토해야 한다. 디렉터리 Account를 위해 임의 Actor를 생성하는 결론은 내리지 않는다.

### 3. Contacts의 잔존 정책

- address-books.php:34는 owner/manager/editor에게 동일한 읽기·쓰기 권한을 준다.
  AddressBookRights와 User별 구독은 아직 없으며 계정 전체 접근만으로 대체할 수 없다.
- book_for_actor()는 타입 정책으로 주소록 생성을 제한하며 새 기본 주소록 이름이 빈 문자열이다.
  타입 제한은 RFC 요구가 아니며 빈 이름은 9610 공개 전에 처리해야 한다.
- save_card_for_owner()는 미소속 Card를 허용한다. JMAP ContactCard 노출 조건과 다르다.
- lifecycle.php:300은 get_for_user() 단건을 사용해 User 삭제 시 purge한다.
  여러 개인 Account의 이전·삭제·고아 정책과 일치하는지 재검토해야 한다.
- revision·낙관적 충돌·부분 쓰기 transaction은 있지만 Account/type 변경 원장은 없다.
  멤버십 이동과 공유 권한 변경으로 생기는 가시성 변화까지 동기화에 포함해야 한다.

### 4. 트랜잭션과 직접 편집

AddressBook·멤버십을 JMAP으로 옮겨도 기존 Contacts 편집·import가 원장을 우회하면 안 된다.
HTTP 엔진이 아니라 호출 가능한 JMAP 컬렉션 서비스와 Contacts 저장 서비스가 합성 쓰기를 제공한다.
Card 문서·멤버십·인덱스·변경 기록의 성공/실패 경계를 함께 정해야 한다.
현재 Contacts transaction runner와 별도 JMAP transaction이 같은 연결에서 충돌하지 않게 해야 한다.
개별 모듈이 독립적으로 START/COMMIT하는 구조를 추가하지 않는다.

## Me Card 후보: 저장·운영과 공개 발견 분리

사용자 확인: 공개 Actor–Card 바인딩에는 Contacts -> Actors 직접 경로가 남아도 된다.
Me Card는 Axismundi 공개 프로필 역할이며 표준 RFC 9610 레코드 종류가 아니다.
현재 ax_contact_actor_profiles는 Card에 대한 binding·공개 선택을 저장하고 문서는 Card 원장에 있다.

| 후보 | 이점 | 확인할 비용 |
|---|---|---|
| 기존 Card 원장 + profile binding 유지 | 기존 UID·에디터·공개 경로 재사용 | 미소속 profile과 ContactCard 조건 분리 |
| 독립 profile 원장 + JSContact 직렬화 재사용 | 공개 프로필과 사적 주소록의 수명주기 분리 | 이동 시 UID·URI·provenance·공개 선택·편집 호환 |

분리하더라도 동일 프로필의 독립적인 두 원본을 동시에 만들지 않는다.
독립 Me Card를 JMAP ContactCard로 제공하려면 표준 소속·ID·변경 추적 조건을 만족해야 한다.
읽기마다 임시 ContactCard나 가짜 AddressBook을 자동 생성하는 해법은 채택하지 않는다.
공개 발견 링크는 비공개 주소록 접근 권한을 부여하지 않는다.
Self Card 확장이나 Principal 우회는 이번 범위에 추가하지 않는다.

## 권장 후속 슬라이스

1. Opus: Actor–Account·Session 기존 변경과 위 리뷰 항목을 먼저 검증한다.
2. AddressBook 서비스와 Card 저장의 호출자·transaction·삭제·직접 참조를 전수 목록화한다.
3. JMAP 소유 AddressBook·멤버십 계약을 확정한다. 테이블 통합 여부는 별도 결정한다.
4. Contacts -> JMAP 로딩과 provider 등록 순서를 구현한다. JMAP은 Contacts를 필수 의존하지 않는다.
5. 기존 편집/import를 새 합성 쓰기에 연결하고 Account/type별 변경 원장을 결합한다.
6. 그 후 9610 필수 메서드·transport를 완성한다. 9670은 별도 활성화 게이트로 둔다.

공통 원장 물리화, Me Card 분리, 9670 활성화, Calendar 이전은 아직 결정/구현하지 않았다.
조사 중 PHP 코드는 수정하지 않았고 기존 475개 테스트도 재실행하지 않았다.
