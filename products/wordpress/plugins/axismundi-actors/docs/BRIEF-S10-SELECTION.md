# S10 계획: Account 선택과 전환

2026-10-25. **구현 전 계획 및 Opus 일괄 검토용**이다. 이 문서 작성으로 생산 코드를
변경하지 않았다. 계약의 배경은 `BRIEF-ACCOUNT-INBOX.md`의 S10 절이며 중복 설계를
만들지 않는다. 선행 S2 `a8176ba`, S5 검증 `6a44805`는 push됐다.

## 소유자 결정: 레거시 전환을 교체한다

기존 액터 전환은 대체로 폐기 대상이다. S10은 기존 전환 UI나 usermeta 선택기를
유지한 채 쿠키만 덧붙이는 작업이 아니다. **JMAP API 기반 Account 노출과 인증 세션에
묶인 선택 쿠키를 사용하는 새 세대로 교체한다.** 아래 A→B→C는 호환 전환기를 병행
유지하는 계획이 아니라, 새 경로를 세운 뒤 기존 전환 경로를 제거하는 작업 순서다.

폐기 대상은 기존 acting 선택 UI/POST, `AXISMUNDI_ACTORS_ACTING_META`의 현재 선택
권위, legacy Person으로 돌아가는 전환 폴백이다. 오래된 선택 usermeta는 새 기본값이나
쿠키로 자동 승격하지 않는다. 초기 선택은 새 흐름에서 명시적으로 한다.

폐기 대상이 아닌 것은 Principal 인가, 개인 귀속 사실, capability별 명시적 기본 선호,
기존 객체 귀속, Actor identity/URI 및 legacy 수신 URL 계약이다. 같은 파일이나 getter를
사용했다는 이유로 이들까지 제거하지 않는다.

## 완료선과 제외 범위

인증 User가 접근 가능한 Account를 선택하고, 브라우저 요청과 명시적 API 요청이
같은 해소·인가 규율을 따른다. 잘못되거나 철회된 선택으로 쓰면 **다른 Actor로
발행하지 않고 중단**한다. User, source `post_author`, Account, 프로필 조회 대상을
서로 바꾸지 않는다.

제외: Actor/Activity 410, Delete 수명주기, 묘지 분리, Calendar canonical URI 전환,
JSCalendar 재구조화, Note CPT 복구, 새 C2S outbox POST, JWT 도입, JMAP blob 업로드,
공동 발행·귀속 이전, 독립 React 앱 구현. 새 Account 선택 UI는 S10 범위다.
기존 명령 서비스를 새 엔드포인트 구현으로
부르지 않는다. 전체 플러그인 소비자 일괄 치환도 하지 않는다.

## 현재 코드에서 확인한 것

| 자리 | 현재 동작 / 이관 주의점 |
|---|---|
| Actors `includes/acting-actor.php` | usermeta 선택·기본 Person 폴백·admin-bar 전환 UI/POST는 교체 대상. 권한 predicate는 공통 인가로 보존 |
| Actors `includes/account-operations.php` | current_workspace가 선택→workspace 기본값→legacy Person으로 폴백. 목적별 명시적 기본값 저장 API가 이미 있다 |
| Activities `includes/c2s.php` | 명령 서비스이며 HTTP endpoint가 아님. actor_identity_id=0이면 기본 Person으로 폴백 |
| Activities `includes/publish-command.php` | 작성자 본인 저장에 acting 선택을 쓰고 그 외에는 작성자 Person으로 폴백. 기존 객체 귀속을 읽는 경로도 있다 |
| Contacts `includes/address-books.php` | current_workspace 소비자. 계정 데이터 접근을 발행 handle 요건과 합치면 안 된다 |
| Calendar `includes/event-actor.php` | 신규 선택, 기존 귀속, legacy 작성자 해소를 구별해야 한다 |
| JMAP `modules/rfc8620/session.php`, `includes/integrations/wordpress/accounts.php` | Account 목록·capability별 primaryAccounts의 in-process 투영은 구현됨. Session 50 검사 통과. 실제 HTTP 등록은 아직 없음 |

이 표는 S10 시작점이지 전체 호출부의 의미 분류 완료가 아니다. 실제 검색에서 Forum,
Notifications, OP, Media Library, Bridge에도 `get_for_user()` 호출이 나왔다. 프로필
조회·과거 귀속·legacy 수신 라우트까지 새 요청 선택자로 바꾸면 안 된다. 기존 문서의
호출부 숫자를 이관 목표나 완료 판정으로 쓰지 않는다.

### 진행 중 순서 조정 (2026-10-25)

소유자는 User–Account 이후 실제 JSON API 데이터 검증을 AddressBook으로 하기로 했다.
합성 테스트용 메서드를 제품에 등록하지 않는다. Account 단계에서는 접근 목록·선택·
철회·쿠키 경계를 먼저 검증한다.

RFC 8620 §2의 완전한 Session은 upload/download/eventSource URL도 요구한다.
현재는 그 전송 기능이 없으므로 SessionProjector가 있다는 이유로 완전한 Session을
공개하지 않는다. 먼저 아래 A의 해소 계약과 B의 쿠키 운반 기반을 구현하고, 실제
JMAP HTTP 공개 범위는 AddressBook 및 Core 전송 계약 검증과 함께 확정한다.
없는 업로드/SSE를 광고하거나, 이 문제를 피하려고 별도 Account 목록 API를 만들지 않는다.

## 먼저 고정할 해소 계약

- 새 클라이언트/선택 UI는 JMAP Session의 accounts·primaryAccounts를 읽는다. 별도의
  WP User→Actor 목록 API나 레거시 전환 목록을 병행 구축하지 않는다.
- Actors가 프로토콜 중립인 선택/인가 서비스를 소유한다. 인증 User는 인증 계층이
  제공하고, 요청 body의 user_id를 신뢰하지 않는다.
- 명시적 Account가 있으면 그것만 검사한다. 값이 잘못됐거나 권한이 없으면 쿠키·
  기본값·legacy Person을 재시도하지 않는다. 실패 이유를 구별해 호출자에게 반환한다.
- 브라우저 어댑터만 인증 세션에 묶인 현재 선택 쿠키를 전달한다. 쿠키를 보냈는데
  무효/만료/철회 상태라면 쓰기를 중단한다. 쿠키는 권한 증명이 아니다.
- JMAP 필수 accountId는 쿠키나 기본값으로 보충하지 않는다. `a{identity_id}` 해석은
  기존 JMAP 어댑터에 두고 Actors가 JMAP wire 형식을 의존하지 않게 한다.
- 선택 자체가 없는 경우와 유효하지 않은 선택은 다르다. 읽기/UI는 명시적 기본값을
  제안할 수 있다. Account를 못 싣는 외부 표면은 어댑터가 허용한 목적별 명시적
  기본값만 사용하며, 그것도 없거나 부적격이면 귀속을 추측하지 않는다.
- 데이터 접근과 발행은 기존 permission kernel로 각각 검사한다. isPersonal은
  인가가 아니다. handle 없는 Account 데이터 접근을 발행 요건으로 막지 않는다.
- source 저장과 연합 명령의 성공은 별개다. Account 미선택을 이유로 WP 원본 저장을
  모두 실패시키는 전역 훅을 만들지 않는다. 연합 쓰기 실패/미실행은 명확히 보고한다.

## 단계별 구현 계획

### A. 단일 해소기와 결과 계약

먼저 JMAP의 기존 SessionProjector/Engine을 실제 인증된 HTTP 표면에 연결할 범위를
확정한다. PHP 투영기 단위 검사를 실제 Session/API 제공 완료라고 보고하지 않는다.
인증은 기존 WordPress 세션/Application Passwords와 연결하고 새 JWT 체계를 만들지
않는다. Session URL·제한값과 실제 endpoint 동작을 대조한다. 미구현 blob 업로드나
이벤트 스트림을 가짜 URL/지원 capability로 광고해 Session을 채우지 않는다. 필요한
최소 transport 작업은 별도 변경 단위로 분리하되 새 전환의 인계 범위에 포함한다.

선택 출처(명시적 요청/브라우저 선택/허용된 명시적 기본값)와 실패 상태를 보존하는
계약부터 추가한다. 기존 workspace·acting API의 호출부를 읽기, 신규 쓰기, 기존 객체
수정, 백그라운드, legacy 라우팅으로 분류한다. 제거할 전환 API와 유지할 domain/route
API를 목록화한다. 새 경로가 세워지면 전환 API는 소비자와 함께 제거한다. 호환용
폴백을 영구 보존하거나 새 해소기에서 옛 선택자를 다시 호출하지 않는다.

검증: 동일 User의 개인 Account 둘과 Organization, 다른 User, 철회/disabled/tombstone,
미지정과 잘못된 지정, 접근 가능하지만 발행 불가인 Account. 잘못된 명시적 선택이
유효한 쿠키/기본값으로 폴백하지 않는 변이를 필수로 잡는다.

### B. JMAP 기반 새 선택 UI와 세션 쿠키

새 선택 UI는 JMAP Session의 Account 데이터를 사용한다. 기존 acting admin-bar 메뉴와
전환 POST는 병행 유지하지 않고 새 흐름으로 교체한다. 현재 선택을 변경하는 것과
목적별 기본값을 저장하는 것은 별개 명령이다. 기존 usermeta를 자동으로 명시적
기본값에 승격하지 않는다. 새 active-account usermeta를 하나 더 만들지도 않는다.

JMAP은 Account 목록과 요청별 accountId를 제공하는 기반이고, 브라우저 현재 선택의
저장은 인증된 웹 세션 어댑터가 쿠키로 담당한다. 현재 선택 변경을 표준 JMAP 전환
메서드라고 부르거나 임의의 Core 메서드를 신설하지 않는다. 앱은 요청마다 명시적인
accountId를 보내며 브라우저 쿠키를 필수로 요구하지 않는다.

쿠키는 User 및 실제 로그인 세션에 묶는다. 수명은 세션의 남은 만료 시각을 넘지 않고,
로그아웃/로그인 주체 변경 시 무효화한다. 경로·HTTPS Secure·HttpOnly·SameSite·CSRF는
WordPress 실제 인증 API와 환경을 확인해 구현한다. 원시 로그인 세션 토큰은 새 쿠키나
로그로 복제하지 않는다. 쿠키가 없는 Application Passwords/XML-RPC에 브라우저 선택을
추정 적용하지 않는다. 이 계획은 공식 앱 전체의 전송 경로를 재검증한 보고가 아니다.

검증: 실제 로그인 후 전환·새로고침·로그아웃·다른 User 로그인, 임의 Account 쿠키,
세션 만료, nonce 없는 전환, role 철회. 단위 검사와 별도로 브라우저 흐름을 한 번 잰다.

### C. 신규 쓰기 경로부터 표면별 이관

Activities 명령 서비스와 편집기 어댑터부터 A의 해소기를 사용하게 한다. 그다음 실제
선택을 소비하는 Contacts/Calendar 등의 경로를 하나씩 옮긴다. 플러그인 이름만 보고
모든 getter를 바꾸지 않는다. 각 단위는 영향 감사 후 별도 커밋 가능한 크기로 둔다.
마지막에는 레거시 전환 UI/POST/선택 usermeta 읽기 및 Person 폴백의 도달 가능한
생산 경로가 남지 않았는지 검사한다. 공개 identity와 수신 URL의 호환성은 별개로 지킨다.

기존 객체의 귀속은 현재 선택으로 다시 계산하지 않는다. 예약 발행은 예약 시의
명시적 principal/Account 의도를 보존하고 실행 시 권한을 재검사한다. cron 시점의
쿠키·관리자 선택·새 기본값으로 발신자를 바꾸지 않는다. 새 예약 기능 자체는 범위 밖이다.

다중 탭은 쿠키가 공유된다는 전제로 다룬다. A로 준비한 폼/명령이 다른 탭의 B 전환으로
B 명의가 되지 않도록, 쓰기 어댑터가 기대 Account를 명시하고 검증하는 계약이 필요하다.
어떤 기존 표면이 그 값을 운반할 수 있는지는 구현 전 분류한다. 조용한 귀속 변경을
허용한 채 "쿠키를 읽으니 완료"라고 하지 않는다.

## Opus가 한 번에 검토할 항목

1. JMAP HTTP 배선의 실제 범위·인증·Session URL 계약. A의 미지정·무효 선택 구별과 폴백 금지.
2. B의 실제 세션 만료 근거, 세션/User 바인딩, 다중 탭 및 오래 열린 편집기 동작.
3. Account를 못 싣는 표면의 명시적 기본값 목적 키와 실패 처리. 원본 저장과 연합 실패의 구별.
4. 기존 귀속·예약 principal/Account 보존. 관리자 대리 편집을 새 작성자 선택으로 바꾸지 않는지.
5. 제거할 legacy 전환 경로와 유지할 domain/route 경로의 목록. 폐기 대상 Note가 섞이지 않았는지.

아직 이 다섯 항목의 구현 세부를 승인된 계약이라고 부르지 않는다. Opus 검토 후
A→B→C 순서로 실행하고, 원래 제품 결정과 충돌하는 항목만 소유자에게 묻는다.

## 검증 비용과 완료 보고

각 단계는 새 선택 계약 감사와 영향 감사만 실행한다. 기존 후보는 Actors
`audit-acting-actor.php`, `audit-account-operations.php`, JMAP `audit-session.php`,
Activities `audit-post-create.php`/`audit-local-social.php`다. 실제 변경 경로에 맞춰 고르고
Calendar/Contacts는 그 표면을 이관할 때만 추가한다. 전수는 통합·배포 시점 한 번이다.

필수 변이: 무효 명시적 선택의 기본값 폴백, 철회 선택의 Person 폴백, JMAP accountId
생략을 쿠키로 보충, 세션 바인딩 없는 쿠키 재사용. 실제 브라우저/다중 탭 검증 없이
HTTP 선택 전환까지 완료했다고 보고하지 않는다.
HTTP Session의 Account 목록과 선택 UI가 일치하고, 레거시 전환 경로가 제거된 것까지
완료 기준으로 둔다. 옛 동작 보존 테스트는 새 계약에 맞게 바꾸되 제거 이유를 기록한다.

인계 보고는 변경 파일·보장한 것·확인하지 않은 것·실제 실행 명령으로 작성한다.
현재는 계획만 작성했으며, S10 생산 코드 변경·런타임 검증·패키징은 하지 않았다.

## 구현 진행: 선택 계약과 쿠키 기반

위 마지막 문장은 계획 작성 당시 상태다. 현재 추가한 생산 코드는 Actors
`includes/account-selection.php`이며 bootstrap에서 로드한다. **S10 전체 완료가 아니다.**

- `resolve_account_selection(user, explicit_id, purpose, publication, browser)`은
  Actor와 선택 출처를 반환하거나 구별된 WP_Error를 반환한다. null과 명시적 0을
  구별하고, legacy acting usermeta 및 단수 Person getter를 사용하지 않는다.
- 명시적 요청은 쿠키에 의존하지 않는다. browser를 허용한 어댑터만 쿠키를 읽고,
  명시적으로 허용한 purpose가 있을 때만 저장된 기본값을 사용한다. 무효/철회된
  선택은 다른 출처로 재시도하지 않는다. 데이터 접근과 발행 요건도 분리한다.
- 쿠키는 실제 `wp_validate_auth_cookie()`와 `WP_Session_Tokens`로 확인한 User,
  로그인 토큰, 사이트에 바인딩한다. 원시 로그인 토큰은 쿠키에 넣지 않는다.
  만료는 인증 쿠키와 저장된 세션 만료 중 이른 시각이며 새 기간을 더하지 않는다.
- HttpOnly/SameSite=Lax 및 HTTPS Secure를 사용한다. WordPress의 home/install
  쿠키 경로를 모두 처리하고, logout과 새 login 시 선택을 비운다. 선택 usermeta를
  새로 쓰지 않으며, setter를 연결할 웹 어댑터는 별도로 CSRF 검사를 해야 한다.

명령: `wp eval-file wp-content/plugins/axismundi-actors/tests/audit-account-selection.php`.
새 감사는 실제 WordPress 세션 토큰으로 다른 User/같은 User의 다른 로그인,
서명 변조·만료 연장·세션 폐기, role 철회, default 폴백 금지 등을 검사한다.
최종 44개가 통과했다.
핵심 변이 세 개(명시적 0의 default 폴백, 로그인 토큰 바인딩 제거, 철회 선택의
default 폴백)는 각각 의미 있는 실패로 검출한 뒤 복원했다. 기존 Account 운영 51개와
JMAP Session 50개도 통과했다. 전체 감사는 실행하지 않았다.

아직 하지 않은 것: 새 전환 UI/HTTP 어댑터, 브라우저의 실제 Set-Cookie·다중 탭 검증,
레거시 전환 경로 제거, 생산 쓰기 소비자 이관, JMAP HTTP Session/API 공개, AddressBook
실데이터 API 검증. 기존 전환기는 아직 살아 있으며, 새 선택 기반이 자동 대체한 것이 아니다.
원래 계획의 B UI와 C 이관을 완료하기 전에는 배포 가능한 전환 기능으로 보고하지 않는다.

## 다음 변경 단위: S10-B 선택 UI와 웹 세션 어댑터

이 절은 다음 구현 계획이며 아직 실행하지 않았다.

### 소유 경계와 시작 파일

- 새 UI/웹 어댑터는 JMAP의 `includes/integrations/wordpress/`에 둔다. 기존
  `SessionProjector::accounts()`와 `resolve_account_actor()`로 같은 목록·매핑·현재
  접근 검사를 사용한다. 초기 WordPress 관리 화면은 이 in-process JMAP API를 사용한다.
  이것을 HTTP Session 제공이나 실제 JSON API 검증이라고 부르지 않는다.
- Actors의 `account-selection.php`는 선택·쿠키·공통 인가만 소유한다. Actors에서
  JMAP 클래스를 호출하지 않는다. 별도 Account 목록 REST API는 추가하지 않는다.
- `acting-actor.php`의 기존 admin-bar 전환 메뉴/POST와 선택 usermeta 쓰기는 제거
  목록이다. 새 UI와 옛 UI를 사용자에게 동시에 노출하지 않는다. 폐기할 setter와
  아직 읽는 소비자용 함수는 구별해 fatal 없이 이관한다.

### 한 번의 브라우저 흐름

1. 현재 인증 User의 JMAP Account 목록과 현재 쿠키 선택 상태를 읽는다. `isReadOnly`
   또는 handle 유무만으로 데이터 Account를 목록에서 제외하지 않는다.
2. Account 선택/해제를 별도의 nonce POST로 제출한다. body의 user_id를 받지 않고,
   JMAP accountId를 기존 어댑터로 해소한 뒤 현재 principal 접근을 재검사한다.
3. 선택은 S10-A setter, 해제는 cookie clear를 호출한다. 임의 리다이렉트나 GET으로
   선택을 변경하지 않는다. 현재 선택 변경은 기본 선호 저장 명령과 합치지 않는다.
4. 만료·철회·변조된 선택은 선택 필요 상태로 표시한다. 다른 Person을 이미 선택된
   것처럼 표시하거나, 원래 실패한 쓰기를 다른 Account로 재실행하지 않는다.

### 소비자 연결과 출시 경계

UI만 연결해도 기존 `acting_actor()`/`current_workspace()`는 옛 usermeta를 읽을 수
있다. 따라서 B 단독을 전환 기능 출시로 취급하지 않는다. 다음 C에서 먼저 데이터
workspace 읽기를 새 해소기에 연결하고, Activities 발행 어댑터는 별도 변경으로 옮긴다.
잘못된 선택을 nullable 반환으로 숨긴 뒤 호출자가 다시 기본 Person으로 폴백하는
경로가 없는지 확인한다. 새 UI에서 고른 Account와 실제 소비자가 사용하는 Account가
일치하기 전에는 배포/패키징하지 않는다.

기본 선호를 바꾸거나, 이미 발행된 객체의 귀속을 옮기거나, JMAP accountId가 필수인
요청에 쿠키를 보충하는 것은 전환이 아니다. 이들은 UI 연결의 부수효과로 만들지 않는다.

### B 검증과 Opus 인계

- nonce 없는 POST·타 User accountId·role 철회·세션 만료·선택 해제를 검사한다.
- 실제 HTTP의 Set-Cookie 경로·Secure/HttpOnly/SameSite·만료를 확인한다. PHP에서
  options 배열이 맞다는 것만으로 브라우저 동작 검증을 대신하지 않는다.
- 로그인→Account A 선택→새로고침→B 선택→로그아웃→다른 User 로그인을 브라우저에서
  한 번 실행한다. 다중 탭 쓰기의 Account 고정은 C의 어댑터 검증과 함께 마감한다.
- 기존 전환 UI/POST 제거 목록, 아직 미이관인 읽기·쓰기 소비자 목록, 실제 실행한
  감사와 브라우저 확인 결과를 이 문서에 추가한다. 전체 감사는 반복하지 않는다.
- AddressBook 단계는 실제 JSON 요청/응답·데이터 권한·지원 capability를 검증하는
  축이다. B의 PHP 투영 및 쿠키 검증을 그 단계의 완료 증거로 재사용하지 않는다.

## S10-B 구현 및 Opus 검증 인계

2026-10-25. 소유자 요청에 따라 구현과 기본 문법 확인만 수행하고, 런타임 검증은
Opus에게 맡긴다. 아래 항목은 **통과 보고가 아니다.**

### 구현한 것

- JMAP `includes/integrations/wordpress/account-selection.php`에 새 관리 화면,
  Account 투영 뷰, 선택/해제 명령, POST 어댑터를 추가했다. 목록과 accountId 해소는
  기존 JMAP SessionProjector/Account 어댑터를 사용하며 별도 목록 API는 없다.
- review gate `axismundi_actors_account_selection_enabled`의 기본값은 false다.
  켜면 기존 admin-bar 전환 메뉴와 옛 전환 POST를 해제하고 새 Accounts 메뉴를
  노출한다. B만으로 생산 쓰기 귀속까지 바뀌는 것이 아니므로 기본 활성화하지 않는다.
- 새 POST는 현재 User/브라우저 세션·nonce·동작 종류·현재 Account 접근을 검사한다.
  body의 user_id와 redirect는 사용하지 않는다. 성공 시 고정된 선택 화면으로 303을
  반환한다. GET과 배열 인자는 거부한다. 선택 변경으로 기본 선호를 쓰지 않는다.
- handle 없는/read-only Account도 데이터 작업 공간으로 선택할 수 있다. 발행 가능성은
  C의 쓰기 경로에서 따로 검사한다. stale 선택은 경고와 미선택 상태로 표시하며
  기본 Person을 선택한 것으로 표시하지 않는다.
- Actors의 clear 함수가 쿠키 전송 성공 여부를 bool로 반환하도록 바꿨다. logout/login
  훅은 반환값을 소비하지 않는다. 웹 어댑터는 전송 실패를 성공 리다이렉트로 숨기지 않는다.

### Opus 실행 목록

1. `wp eval-file wp-content/plugins/axismundi-jmap/tests/audit-account-selection.php`
   신규 어댑터 감사. opt-in, 목록/선택/해제, nonce, foreign/malformed accountId,
   role 철회, 메타 비변경, 레거시 메뉴/POST 해제, GET/배열 인자 거부를 검사하도록 작성했다.
   이 감사 자체도 아직 실행하지 않았으므로 검토 대상이다.
2. `wp eval-file wp-content/plugins/axismundi-actors/tests/audit-account-selection.php`
   S10-A 회귀. clear 반환형 변경이 기존 쿠키/세션 계약을 깨지 않는지 확인한다.
3. `wp eval-file wp-content/plugins/axismundi-jmap/tests/audit-session.php`
   Account 투영·명시적 기본값·capability 및 철회 회귀만 확인한다. 전수는 돌리지 않는다.
4. 임시 개발 설정에서 review gate를 켠 뒤 실제 로그인→A 선택→새로고침→B 선택→
   선택 해제→로그아웃→다른 User 로그인 흐름을 검사한다. Set-Cookie의 경로, HTTPS
   Secure, HttpOnly, SameSite, 만료와 303 Location이 실제 응답에서도 맞는지 본다.
   설정은 검증 후 복구한다. 스테이징 활성화나 배포는 별도 승인 없이 하지 않는다.
5. nonce 검사 제거와 accountId 인가 우회를 변이로 확인한다. 다중 탭 쓰기 귀속과
   레거시 선택기 물리 제거는 C에서 소비자와 함께 검증한다.

생산 변경은 문법 검사 및 diff 공백 검사만 확인했다. 브라우저/스크린샷, 위 감사,
변이, 전체 감사, 패키징은 실행하지 않았다. C 소비자 이관과 JMAP HTTP 공개는 아직 남았다.

## S10-C1 구현 기록 — 데이터 workspace

소유자 지시에 따라 B 리뷰를 기다리지 않고 데이터 소비자 이관을 진행했다.
런타임 검증은 Opus에 맡기며 이 기록은 통과 보고가 아니다.

- UI와 데이터 소비자는 Actors의 `axismundi_actors_account_selection_enabled` gate를
  공유한다. 기본 false이며 JMAP 브라우저 어댑터의 available 신호도 필요하다.
  이는 JMAP HTTP Session/API 구현 완료를 뜻하지 않는다.
- `current_workspace_result()`는 선택 오류를 보존한다. gate가 켜지면 UI와 똑같이
  세션 쿠키만 읽고, workspace 기본값·옛 선택 meta·기본 Person으로 보충하지 않는다.
  typed `current_workspace()`는 오류를 null로 반환할 뿐 재해소하지 않는다.
- `set_workspace()`도 같은 gate에서 로그인 세션 쿠키를 변경한다. 해제도 실제
  브라우저 세션을 요구하며 레거시 meta나 목적별 기본값을 쓰지 않는다.
- Contacts는 rich 결과의 오류를 화면에 전달한다. 미선택·잘못된 쿠키·권한 철회로
  실패하면 기본 AddressBook 생성 전에 반환한다. 기존 읽기/쓰기 역할 정책과
  제출된 Card/label 소유 Account 검사는 변경하지 않았다.
- gate가 꺼져 있으면 기존 workspace 해소를 유지한다. 이것은 검토용 rollout 경계다.
  레거시 전환 폐기 목표를 철회한 것이 아니며 아직 기본 활성화하거나 배포하지 않는다.

### Opus에 넘길 검증

1. 위 B 감사에 추가한 gate 동기화, cookie B 대 legacy/default A, 오류 후 fallback 금지,
   workspace setter와 UI 일치, 세션 만료 후 해제 거부 검사를 실행한다.
2. `wp eval-file wp-content/plugins/axismundi-contacts/tests/audit-workspace-selection.php`
   미선택·잘못된 쿠키·철회·해제에서 화면 오류와 기본 book 미생성, handle 없는 B 선택,
   JMAP UI와 Contacts 일치, 선호 meta 불변을 검사하도록 작성했다. 아직 실행하지 않았다.
3. Contacts의 기존 account-access/card-writes/labels 영향 감사를 확인한다. 기존
   account-access의 tombstone 후 internal 복귀 fixture는 S2의 terminal 전이와
   충돌할 수 있으므로 C1 회귀로 단정하지 말고 먼저 fixture 순서를 확인한다.
4. 실제 브라우저에서 A의 편집 폼을 연 뒤 B로 전환했을 때, 기존 A 대상 명령이
   B에 재귀속되지 않고 대상 Account 검사로 거부되는지 본다.

확인 범위는 PHP 문법과 diff 공백뿐이다. HTTP/browser·감사·변이·패키징은 하지 않았다.
다음 C2는 발행 소비자와 제출 시 Account 고정이다. 기존 객체 귀속·예약 발행·Calendar,
AddressBook JMAP JSON provider/API 구현은 C1에 포함하지 않는다.

## S10-C2 구현 기록 — 발행 선택과 편집기 의도

기본 false인 동일 rollout gate 안에서만 신규 경로를 사용한다. C1 리뷰 후 진행했다.

- Activities 명령 서비스는 명시적인 양수 Account를 공통 해소기로 재검사한다.
  actor_identity_id=0은 더 이상 Person을 뜻하지 않는다. 이 서비스 자체는 쿠키나
  기본값을 읽지 않으며 HTTP endpoint를 추가하지 않았다.
- WP 저장 어댑터는 기존 Object 귀속이 있으면 그 Actor를 유지하고 현재 Principal의
  권한을 다시 검사한다. 다른 탭의 Account 선택으로 기존 객체를 재귀속하지 않는다.
  Delete도 기존 attribution/인가 경로 그대로다.
- 신규 브라우저 발행은 현재 선택과 편집기를 열 때 고정한 Account가 같아야 한다.
  classic은 post/Account에 묶인 nonce와 hidden 필드, block editor는 apiFetch 헤더를
  사용한다. 미선택·무효·철회·미제출·다중 탭 변경을 다른 Account로 보충하지 않는다.
- 쿠키 없는 인증 요청은 `publish` 목적의 명시적 기본값만 허용한다. 저장된 기본값이
  없거나 부적격이면 발행하지 않는다. 레거시 현재 선택 meta/Person은 읽지 않는다.
- WordPress source 저장과 연합 성공은 별개다. 실패는 기존 실패 훅, classic 관리자
  notice, block editor 저장 응답/notice로 전달한다. source 저장을 전역 거부하지 않는다.
- 예약 시 Account를 저장하는 구현은 기존에도 없었다. cron의 자동 Create 차단을
  그대로 유지한다. 예약 의도 저장/실행 기능을 구현했다고 보고하지 않는다.

### Opus 검토 목록

1. `wp eval-file wp-content/plugins/axismundi-activities/tests/audit-publish-selection.php`
   신규 감사 자체도 미실행이다. 명시적 0 거부, browser Account 기대값/nonce,
   다른 탭 변경, handle 없는 Account 발행 거부, 비브라우저 publish 기본값 및 철회를 다룬다.
2. 기존 `audit-post-create.php`의 명령/생명주기 검사를 gate-off 회귀로 확인하고, gate-on에서
   실제 신규 Create·기존 귀속 보존·Delete 인가·실패 시 원장 미기록을 추가 측정한다.
3. 실제 classic/block 편집기로 A에서 연 편집기→다른 탭 B 선택→저장 시 source는
   저장되지만 Create는 없고 경고가 보이는지 확인한다. reload 후 B 제출은 허용되어야 한다.
4. REST 실제 요청의 헤더 전달, autosave/예약/대리 편집, nonce/권한 철회 및
   expected Account 비교 제거 변이를 확인한다. 내부 rest_do_request의 set_header는
   PHP $_SERVER 헤더를 자동 생성하지 않으므로 실제 HTTP와 구별한다.

이번 변경은 문법/정적 검사까지만 수행한다. 감사·변이·브라우저·패키징은 Opus 검토용이다.
S10 전체 완료나 기본 활성화/배포 승인이 아니다. Calendar 소비자 및 레거시 물리 제거,
예약 의도 영속화, 실제 JMAP AddressBook HTTP 검증은 남아 있다.
