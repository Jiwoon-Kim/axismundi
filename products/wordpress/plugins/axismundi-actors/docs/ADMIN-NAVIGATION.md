# Accounts Navigation

2026-10-30: WP User 관리와 Account 관리를 구분한다. Actors가 Accounts 최상위 메뉴를
등록하고 All Accounts, Local Actors, Add Local Actor, Remote Actors, Managed actors를
그 순서로 배치한다. Actor Profile과 Activities의
Follows와 Contacts 화면도 같은 부모를 사용한다. 선택 UI 게이트가 켜져 있으면 기존
Account 선택 화면은 Switch Account로 배치한다. JMAP 활성화가 메뉴의 전제는 아니다.

All Accounts는 관리자용 JMAP Account 저장소 목록이며 현재 User의 Session.accounts가 아니다.
JMAP 비활성 시 목록을 만들어 내지 않고 사용할 수 없음을 표시한다. Local Actors는
시스템 Actor와 삭제 이력까지 포함하는 로컬 Actor 목록이다. 두 목록은 읽기 전용이고
manage_options를 요구하며, 50행씩 페이지를 나눈다. Local Actors의 프로필 링크는 기존
Account 인가를 우회하지 않는다.

Add Local Actor는 edit_posts와 nonce를 요구한다. 표준 Actor 5종을 공통
create_principal_account 서비스로 생성하고 현재 WP User를 초기 owner로 지정한다.
JMAP 활성 시 기존 local_actor_created 훅이 같은 ID의 Account를 생성한다.
별도 Account/set 메서드나 프로토콜 API를 추가하지 않는다. Managed Actors의 기존 생성과
권한 관리 기능은 당장 삭제하지 않는다. Remote Actors의 인스턴스·자산 캐시 기능도 유지한다.
관리자가 아닌 User의 최상위 진입점은 기존 Actor Profile이다.
WP Users의 기본 프로필과 사용자 목록은 그대로다. Contacts는 Actors 미활성 시 기존
Users 부모를 사용하지만 데이터 접근의 기존 의존성 검사를 완화하지 않는다.

주소는 `admin.php?page=<기존 slug>`다. 옛 users.php/profile.php의 알려진 GET 주소만
새 주소로 리디렉션하고 query context를 보존한다. POST/AJAX와 다른 WP 페이지는
가로채지 않는다. 삭제된 부모에 대한 Core의 권한 검사가 admin_init보다 먼저 실행되므로
알려진 주소의 리디렉션은 init 0에서 수행한다. 목적지의 권한 검사는 그대로 적용된다.

화면 hook이 변경되므로 Actors 미디어 피커는 등록된 hook 이름으로 판정하고, Contacts는
add_submenu_page가 반환한 실제 hook을 자산 enqueue/관리자 notice에 사용한다.
목록 검색폼, 편집/조회/프로필 URL, 관리자 바의 Account 선택 링크도 새 부모를 사용한다.

## Verification

- `wp eval-file wp-content/plugins/axismundi-actors/tests/audit-accounts-navigation.php`:
  34검사. 관리자/읽기 전용 capability 문맥의 메뉴 순서·기존 권한·URL·hook과 새 화면 렌더를 확인한다.
  실제 subscriber 로그인이 아니라 현재 User의 메모리상 capability를 바꾼 메뉴 단위 감사다.
- `AX_ADMIN_HTTP_ORIGIN=http://wordpress wp eval-file wp-content/plugins/axismundi-actors/tests/audit-accounts-navigation-http.php`:
  12검사. 기존 관리자에 임시 로그인 세션을 만들어 다섯 관리 페이지와 Profile/Contacts 200, Accounts 메뉴,
  Profile 미디어 피커/Contacts 스크립트, 옛 주소 302 및 query 보존을 실제 HTTP로 확인한다.
  새 User/Actor는 만들지 않으며 세션은 finally에서 폐기한다.

Contacts의 모든 편집/저장 작업이나 비관리자의 전체 브라우저 동작을 재검증한 것은 아니다.
새 Add Local Actor의 실제 생성 POST 왕복은 이번 메뉴 감사 범위에 포함하지 않는다.
