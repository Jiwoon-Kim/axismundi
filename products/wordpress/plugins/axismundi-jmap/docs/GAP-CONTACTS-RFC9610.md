# Contacts / RFC 9610 Gap Audit

작성일: 2026-10-22. 조사 기준: `aeb7eed` 이후 작업 트리의 Contacts 소스.
범위: 소스 조사, 기존 감사 실행, 후속 슬라이스 제안. 제품 코드와 DB는 변경하지 않았다.

후속 구현(2026-10-23): [Contacts Account 인가 정합성](../../axismundi-contacts/docs/IMPLEMENTATION-ACCOUNT-ACCESS.md).
아래 표는 조사 시점의 차이를 보존한다. 공통 Account 접근·별도 read/write 진입점·작업 공간 선택은
후속 슬라이스에서 연결했으며, capability·세분화된 주소록 권한·동기화 기반은 아직 미구현이다.
이어 [Card 쓰기 안전성](../../axismundi-contacts/docs/IMPLEMENTATION-CARD-WRITES.md)에서
조건부 revision UPDATE·저장 transaction·실패 전파를 추가했다. 아래 위험 목록은 최초 조사 기록이며,
삭제/변경 원장과 전체 mutation 경로의 통합은 아직 완료하지 않았다.

## 결론

Contacts는 CPT가 아니라 전용 테이블을 사용한다. WP post revision은 없지만 **자체 revision
숫자와 stale-save 검사는 이미 있다.** 이를 과거 문서 복원이나 JMAP 변경 이력과 혼동하면 안 된다.
에디터도 JSContact 문서 전체를 편집·직렬화하고 draft REST를 통해 저장하도록 구현돼 있다.

RFC 9610 구현을 위해 전체 문서를 컬럼형으로 옮겨야 한다는 근거는 현재 없다.
기존 하이브리드 저장은 재사용 후보이며, 우선 과제는 Account 인가 정합성·변경 추적·원자성이다.

근거: [RFC 9610](https://www.rfc-editor.org/rfc/rfc9610.html),
[RFC 8620 Standard Methods](https://www.rfc-editor.org/rfc/rfc8620.html#section-5).
RFC 9610의 JSContact 확장과 백엔드 SQL 스키마를 같은 것으로 취급하지 않는다.

## 1. 현재 구현과 증거

| 영역 | 확인한 구현 | 소스 |
|---|---|---|
| 저장 | `card_json` 원본 + owner/uid/display_name/sort_key/revision 컬럼 | Contacts `includes/schema.php:211` |
| 검색값 | emails/phones/onlineServices/links의 일부 값을 파생 인덱스로 저장 | `includes/cards.php:30`, `:543` |
| 주소록 소속 | Card와 AddressBook의 별도 membership 테이블 | `includes/schema.php:240` |
| 자체 revision | 새 Card 1, 기존 Card 저장마다 기존 값 + 1 | `includes/cards.php:319` |
| stale-save | 인자로 전달한 revision과 읽어 둔 행 revision 비교, 다르면 409 | `includes/cards.php:286` |
| 주소록 revision | 해당 주소록에 Card가 저장되거나 소속이 바뀌면 카운터 증가 | `includes/address-books.php:289` |
| 문서 편집 | 시각 필드·이름 편집·Advanced JSON이 같은 draft 객체를 편집 | `assets/admin/card-editor.js:5004`, `:5049` |
| 저장 전송 | `{revision, card: prepare(card)}`를 draft PUT으로 전송하고 응답 revision 반영 | `assets/admin/card-editor.js:5130` |
| 에디터 초기값 | PHP가 `card`, `revision`, draftPath를 주입 | `includes/card-editor.php:169`, `:201`, `:284` |
| REST 저장 | 검증 후 Card·공개 선택·provenance를 transaction 안에서 저장 | `includes/rest-draft.php:262` |
| 공개 프로필 | Card와 Actor의 별도 binding 및 공개 필드 선택 | `includes/profile.php`, `includes/public-projection.php` |

여기서 revision은 세 가지 의미와 구분한다.

- WP revision: 과거 post 스냅샷. 이 저장소에는 해당 구현이 없다.
- Card revision: 현재 한 행에 붙은 로컬 편집 버전. 과거 JSON을 저장하지 않는다.
- JMAP state/changes: Account·데이터 타입별 동기화 계약. 삭제·가시성 변경까지 포함하는 별도 설계가 필요하다.

JSContact `version`은 문서 형식 버전이며 이 셋 중 어느 것도 아니다.

## 2. 요구사항 대비 차이

| 우선순위 | 요구 / 목표 | 현황과 차이 |
|---|---|---|
| 선행 | Account 접근 권한 | `can_use_book()`은 managed 관리 권한 또는 legacy `local_user_id` 동등 비교. 새 personal_owner/ACL 기반 추가 개인 Account에 맞지 않음 |
| 선행 | Actor 상태와 접근 회수 | 기존 Contacts 인가 함수는 공통 workspace 상태 필터를 사용하지 않음. JMAP resolver와 기존 편집 API의 인가가 어긋날 수 있음 |
| 선행 | 기능 할당으로 가용성 결정 | `type_keeps_books()`가 Person/Organization만 허용. Group/Application/Service를 기능 할당과 무관하게 제외하는 기존 제품 정책 |
| 선행 | Contacts -> JMAP -> Actors | 현재 readiness는 Actors 직접 의존. JMAP provider 등록·버전·준비 상태 계약은 아직 없음 |
| 선행 | Account / 타입별 state와 변경 추적 | Card·book revision만 있음. 타입별 `/changes`용 생성·수정·삭제 추적과 보존 경계 없음 |
| 선행 | 삭제 기록 | `delete_card()`는 membership/index/provenance/profile/Card를 hard delete. 삭제 ID를 동기화할 원장이 없음 |
| 선행 | 동시 갱신·실패 일관성 | 아래 3절의 SQL 실패·CAS·공통 transaction 경계가 미완성 |
| 선행 | ContactCard는 최소 한 AddressBook에 소속 | 저장소는 owner-only Card 및 마지막 membership 제거를 허용. 미소속 self-profile도 의도적으로 존재 |
| 필요 | AddressBook 필드 | name/is_default는 있음. description/sortOrder, User별 isSubscribed, myRights 투영 및 정책이 없음 |
| 필요 | 주소록 권한 | 단일 read/write bool에 가까운 기존 인가. mayRead/mayWrite/mayShare/mayDelete를 조립할 정책이 필요 |
| 필요 | Sharing 연동 | shareWith와 Principal 기반 공유는 미구현. RFC 9670 미지원이면 shareWith null, 실제 없는 공유 권한은 부여하지 않아야 함 |
| 필요 | AddressBook/get, changes, set | 도메인 목록·생성·소속 함수는 있지만 JMAP 인자·오류·결과·기본 주소록 변경 계약은 없음 |
| 필요 | ContactCard/get | Card 조회 재사용 가능. Account 범위 인가, Id 변환, properties, notFound, addressBookIds, state 투영 필요 |
| 필요 | ContactCard/query | 디렉터리 검색은 단일 검색어·고정 sort_key 정렬. RFC 필터 트리와 필드별 검색·정렬·anchor·queryState와 다름 |
| 필요 | ContactCard/set / copy | 기존 에디터 PUT은 문서 전체 교체. JMAP PatchObject, creation refs, ifInState, 부분 성공 결과, copy 인가와 다른 계약 |
| 필요 | queryChanges | 구현 없음. 지원 범위와 cannotCalculateChanges 응답 정책을 먼저 정할 것 |
| 필요 | Media Blob | 사진을 JMAP Blob으로 올리고 참조·검증하는 경로 없음. Core 업로드/다운로드가 먼저이며 RFC 9404 전체를 필수 선행으로 가정하지 않음 |
| 검토 | JSContact 형식 버전 | 현 코드는 `AXISMUNDI_CONTACTS_JSCONTACT_VERSION = '2.0'`, UID 없는 문서도 보관. RFC 9553만 구현한 저장소라고 설명하면 부정확. 9610 경계의 버전·UID 정책 재검토 필요 |
| 검토 | User 삭제 | `purge_for_deleted_user()`는 legacy get_for_user 단건. 여러 개인 Account와 공동 운영 Account 수명주기는 별도 결정 필요 |

형식 버전은 공식 [RFC 9982](https://datatracker.ietf.org/doc/html/rfc9982)도 확인했다.
JSContact 2.0은 UID를 optional로 바꾸고 나머지는 RFC 9553 정의를 유지한다. 따라서 현재
저장소의 2.0 선언 자체를 오류로 보거나 무조건 1.0으로 되돌리면 안 된다. UID 없는 Card는
UID 기반 group member/relatedTo 참조 대상이 될 수 없다는 차이도 query·표현에 반영해야 한다.

AddressBook rights·필드는 RFC 9610 §2, ContactCard의 소속·UID 제약은 §3,
필터·정렬은 §3.3, 사진 업로드는 §3.5에서 확인했다.
미구현 공유를 추가할 필요와 공유 미지원 표현을 구현할 필요는 구분한다.

## 3. 저장 경로 위험: 정적 분석 결과

이 절은 코드에서 확인한 위험이며 동시 요청/DB 장애를 새로 주입해 재현한 결과는 아니다.

### 3.1 revision 검사는 있지만 원자적 CAS는 아님

`save_card_for_owner()`는 먼저 행을 읽어 revision을 비교한 뒤, UPDATE의 WHERE에는
`id`만 넣는다. 두 요청이 같은 revision을 읽으면 모두 통과해 같은 다음 revision을
저장할 수 있다. 순차 stale-save 테스트 통과와 동시 갱신 안전성은 다르다.
조건부 UPDATE와 affected-row 판정 또는 명시적 row lock이 필요하다.

### 3.2 성공 반환이 DB 성공을 보장하지 않는 경로

- 기존 Card UPDATE의 반환값을 검사하지 않는다 (`cards.php:322`).
- reindex는 기존 값을 지우고 다시 넣으며 함수가 void다 (`cards.php:543`).
- add membership은 INSERT IGNORE 결과를 검사하지 않고 true를 반환한다 (`address-books.php:208`).
- remove membership/delete Card도 개별 삭제 실패를 조립해 반환하지 않는다.
- draft REST는 START TRANSACTION / COMMIT 성공 여부를 검사하지 않는다 (`rest-draft.php:262`, `:297`).

따라서 draft REST 바깥의 import/Actor 이름 동기화/프로필 생성 등도 사용하는
**도메인 쓰기 경계**에서 Card·membership·index·provenance·변경 원장을 함께 다뤄야 한다.
JMAP 요청 처리기에서만 transaction 또는 변경 기록을 붙이면 기존 편집 경로 변경이 누락된다.
현재 save hook은 외부 transaction commit 전에 발생할 수 있으므로 후속 callback의
DB 쓰기·외부 효과와 중첩 transaction 여부도 구현 전에 조사해야 한다.

### 3.3 현재 조회 도우미를 그대로 JMAP API로 쓰면 안 됨

`get_card(id)`와 `get_book(id)`는 인가 없는 저수준 조회다. provider는 먼저 Account 및
주소록 권한을 확인해야 한다. `directory_where()`는 self-profile을 의도적으로 제외하며
전화번호 뒤쪽 숫자 매칭을 사용한다. 이를 그대로 ContactCard/query 의미론으로 삼으면 안 된다.
저장 시각 `created_at/updated_at`과 JSContact 문서의 `created/updated`도 같다고 가정하지 않는다.

## 4. 저장 형식 판단

**현재 권고: JSON 원본 + 관계/메타데이터 컬럼 + 필요한 파생 인덱스 유지.** 아직 최종 동결은 아니다.

- 문서 원본: 복합 값·entry ID·localizations·확장 속성. 에디터와 import/export 왕복에 활용.
- 관계: Account owner, AddressBook membership. 권한과 소속의 기준.
- 파생 인덱스: kind, members UID, 이름 component, label, 날짜 등 실제 query 요구에서 부족한 항목만 추가 후보.
- 동기화 메타데이터: Account/type state, 변경 sequence, 삭제 ID/보존 경계. 문서 revision과 별도.

query 인덱스를 설계하기 전에 RFC 필터별 현재 coverage·collation·문서 시각과 저장 시각의
차이를 명시한다. 사용하지 않는 모든 JSContact 속성을 미리 컬럼으로 만들지 않는다.
문서와 인덱스 사이 양방향 쓰기는 만들지 않고, JSON이 원본이면 인덱스는 한 방향으로 파생한다.
JSON map과 array, 빈 객체, 알 수 없는 확장·localization의 손실 없는 왕복도 추가 시험 대상이다.

미소속 profile Card는 RFC 9553/JSContact 문서로 존재할 수 있지만 RFC 9610 ContactCard로
그대로 내보낼 수는 없다. 모든 해당 Card에 주소록을 부여할지, JMAP 대상과 별도 도메인 문서를
구분할지 먼저 결정한다. Account별 주소록을 가질 수 있는 타입을 넓히는 문제와 함께 검토한다.

## 5. 권장 슬라이스

1. **Contacts Account 호환**: 공통 workspace 인가와 읽기/쓰기 권한 계약, 타입별 고정 제한을
   기능 할당으로 옮기는 범위, 미소속 Card 정책 확정. 기존 editor/REST/프로필 권한 회귀 포함.
2. **공통 쓰기·변경 추적 기반**: CAS, 실패 전파, transaction ownership, Account/type 변경 토큰과
   삭제 이력. 기존 editor·import·Actor 동기화·membership 변경 경로를 같은 기반에 연결.
3. **RFC 9610 읽기 provider**: AddressBook/get·ContactCard/get, Id/rights/소속 투영과 properties.
   부분 읽기 구현을 전체 contacts capability 지원으로 광고하지 않을 것.
4. **query와 최소 인덱스**: 필터/정렬/페이지와 state 안정성을 검증하며 필요한 컬럼·관계 추가.
5. **set/copy 및 Media**: PatchObject·동시성·권한·정규화 결과·Blob 경계를 순차 구현.

1과 2는 Opus가 볼 Actor ownership/lifecycle 변경과 접점이 있다. 특히 User 삭제·마지막 owner는
이번 조사에서 임의 확정하지 않는다. 문서 편집 UI는 재작성할 필요가 확인되지 않았으며,
새 프로토콜 adapter가 공통 도메인 서비스를 사용하도록 설계하는 것이 우선이다.

## 6. 실행한 검증과 한계

개발 wp-env에서 실행:

```sh
wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-contacts.php --path=/var/www/html
```

결과: **335 checks, 0 failed**. 임시 fixture 생성·정리가 있는 기존 감사다.
문서 보존·순차 revision 충돌·draft REST·기존 editor 소스 계약 등 기존 동작을 확인했다.
이 감사에는 UI 소스 문자열 검사도 포함되므로 브라우저에서 실제 편집을 확인한 결과가 아니다.
동시 SQL 실행·강제 COMMIT 실패·RFC 9610 conformance·신규 개인 Account의 Contacts 지원까지
검증한 것으로 확대 해석하지 않는다. 제품 코드 변경과 신규 migration은 하지 않았다.
