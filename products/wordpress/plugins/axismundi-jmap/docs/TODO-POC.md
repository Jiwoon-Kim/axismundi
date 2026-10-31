# Todo Sharing PoC

이 구현은 RFC 8620 §5.7의 Todo 메서드 예제와 RFC 9670 §4.1의
TodoList 공유 예제를 연결하는 개발용 수직 슬라이스다. 표준 Task 구현이나
RFC 8620·9670 전체 구현을 선언하지 않는다.

## 소유 경계

- Actors는 Account 아이덴티티·개인 귀속·기존 운영 관계를 제공한다.
- JMAP은 별도 opaque Principal ID, WP 사용자/Account 연결, Todo 저장,
  자원별 공유, state와 변경 이력을 소유한다. Actors 테이블에 Todo를 저장하지 않는다.
- 사용자 ↔ Account의 `isPersonal`, Actor ↔ Actor의 `attributedTo`,
  Principal ↔ 목록의 공유 권리는 서로 추론하지 않는다.
- PoC 정책상 Account의 `owner`가 목록을 소유한다. `manager`·`editor` 운영 등급은
  목록 권리로 자동 승격하지 않는다. 공유받은 사용자는 Actor 운영 부여를 얻지 않는다.
  시스템 Account에 한해서는 기존 결정대로 `manage_options`가 소유자 역할을 한다.

Principal 사용자 행과 Account 행은 서로 다른 관계를 명시적으로 연결한다.
클라이언트가 보낸 이메일·Actor URI·WP 사용자 ID를 공유 대상의 증명으로 받지 않는다.
`shareWith`는 서버에 존재하는 WP 사용자 Principal ID만 받는다.

## 사용

`/wp-admin/admin.php?page=axismundi-jmap-todo`에서 Account를 선택한다.
사이트 관리자가 **Enable Todo**를 실행한 Account만 기능을 제공한다.
provider 등록 자체는 기능 할당이 아니다. 활성화는 additive schema 생성,
Principal 디렉터리 준비, 해당 Account의 Todo·Principal 정책 할당을 수행한다.
다른 플러그인의 기능 정책이나 Actors 부여를 바꾸지 않는다.

관리자 UI와 테스트는 동일한 registry·engine·실시간 Account resolver를 사용한다.
UI 저장은 PHP 저장 함수 직접 호출이 아니라 nonce 인증 REST 요청의 JMAP 봉투다.
인증 없는 호출·다른 세션 nonce는 거부하고, 응답은 캐시하지 않는다.

공용 API는 `POST /wp-json/axismundi-jmap/v1/api`이며 WordPress 통합 계층이 소유한다.
Todo 외 capability도 같은 registry·engine을 사용한다. 기존 `/todo` POST는 호환 alias로
공용 dispatcher에 위임한다. `/todo/context`·`/todo/enable`은 Todo 관리자 기능이다.
전체 Session은 아직 in-process 조립기뿐이다. 그 조립기에 테스트 호출자가 넣는
URL 템플릿과 실제 공개 광고를 혼동하지 않는다. 공개 Session·blob·SSE 주소는
이 API가 광고하지 않는다.

인증된 디렉터리는 활성화 시 준비한 사이트 사용자 이름과 opaque ID를 공유 후보로
제공한다. 다른 사용자의 이메일은 노출하지 않는다. 이는 시스템 Account의
`wp_users` AddressBook 관찰 투영과는 별개인 **PoC 디렉터리 정책**이다.

## 구현 계약

capability는 예제 문자열 `urn:com.example:jmap:todo`다. `TodoPoc` 네임스페이스의
상수 한 곳에서 정의하며, 실제 제품 capability로 승격할 때는 소유 URI와 버전 계약을
별도로 정한다. `TodoList`는 `name`, `isSubscribed`, `myRights`, `shareWith`를,
`Todo`는 `todoListId`, `title`, `keywords`를 가진다. 완료 UI는 `keywords.done`을 쓴다.
RFC 8620 예제의 가상 추정 기능은 구현하지 않는다.

- `TodoList/get,set,changes`, `Todo/get,set,changes`를 구현한다.
- 목록 권리는 RFC 9670 예제의 `mayRead`, `mayWrite`, `mayAdmin`이다.
- 읽기 공유자는 항목을 읽고 자신의 구독 여부만 바꾼다. 쓰기는 이름·항목을,
  관리는 공유 설정·목록 삭제를 허용한다. `myRights`는 서버가 계산한다.
- `Principal/get,changes`는 서버 관리 디렉터리를 읽는다. 변경된 디렉터리에는
  `cannotCalculateChanges`로 전체 재조회를 요구한다. Principal 쓰기·query·
  ShareNotification은 아직 구현하지 않는다.
- 미구독 공유 Account는 Principal/관리 UI에서 발견 가능하지만 Session의
  공유 Account 목록에는 구독 후 들어간다. 접근 인가는 Session 캐시를 사용하지 않는다.
- 상태는 사용자·Account·타입·소유 자격에 묶인 서명 토큰이다. 쓰기와 이력은
  같은 InnoDB 트랜잭션으로 커밋한다. 목록 공유/구독·목록 사이 항목 이동은 ACL epoch를
  바꾸므로 이전 토큰은 재조회가 필요하다. 읽기 공유자의 삭제 이력도 안전하게
  계산할 수 없으면 `cannotCalculateChanges`다.
- capability 정책 변경과 Todo 읽기/쓰기는 JMAP Account 행 잠금을 공유한다.
  동일 요청 배치에서도 매 호출마다 권리를 다시 확인한다.
- `/query`, `/queryChanges`, `/copy`는 미구현이다. 알 수 없는 메서드는 오류다.

## 수동 동기화

창 포커스 복귀·타이머는 네트워크 갱신을 일으키지 않는다. 사용자의 Refresh 명령은
저장한 타입별 state로 `/changes`를 호출한다. 변경이 없으면 전체 `/get`이나 DOM
재생성을 하지 않아 편집 중인 입력과 포커스가 유지된다. 변경된 ID만 `/get`으로
받고 삭제 ID를 제거한다. `cannotCalculateChanges`·`tooManyChanges`에는 해당 타입을
재조회한다. 변경 ID는 한 번에 256개 이하로 나누며 서버가 더 작은 한도를 요구하면
배치를 줄인다. Account가 바뀌면 캐시와 state를 분리해서 초기화한다.

변경 적용이 편집 중인 입력을 버리게 되는 경우 확인을 받는다. 취소하면 화면의
이전 state를 유지하고, 저장은 `ifInState`로 충돌을 검출한다. 데이터 동기화 뒤
context 요청이 실패한 경우에도 미적용 데이터를 pending으로 유지한다. 저장 실패는
입력 초안을 지우지 않는다. 갱신·저장 요청에서 권한 회수를 확인하면 오래된
편집 화면을 비운다. idle 화면에 서버 변경을 알리는 push는 아직 없다.
향후 push 수신도 같은 동기화 함수를 깨우는 입력으로 붙이며
별도 데이터 저장 경로를 만들지 않는다.

## 푸시 경계

커밋 후 `axismundi_jmap_todo_poc_committed`를 발생시킨다. observer 오류는 이미
커밋된 저장을 실패로 바꾸지 않는다. 전달된 state는 **호출자 기준**이므로 선택적
전송기는 반드시 수신자별 현재 권리와 state를 다시 계산해야 한다.

PushSubscription·SSE·공개 Session discovery·blob 전송은 이 슬라이스에 없다.
동작하지 않는 Session URL을 만들어 광고하지 않는다. Notifications/PWA를
비활성화해도 Todo 메서드와 `/changes`는 동작한다.

기존 PWA sender의 payload는 Notifications의 delivery/category 전용이며 worker는
사용자 알림을 표시한다. 이를 JMAP StateChange에 그대로 쓰지 않는다. 후속 푸시
슬라이스는 RFC 8620 §7.2의 credential 수명·수신 URL 검증·키 암호화·재시도/429
정책을 검증하고, 필요할 때 PWA 어댑터를 별도로 추가한다.

## 검증

```sh
python tools/wp-env/run_audits.py run --plugin axismundi-jmap
```

`tests/audit-todo-poc.php`는 임시 사용자·Actor·공유 데이터를 만들고 `finally`에서
정리한다. 기존 테이블 행을 전후 비교한다. 운영 DB에서 실행하지 않는다.

다른 플러그인 없이도 같은 계약이 성립하는지 별도 프로세스에서 검증한다.
개발 사이트의 활성 플러그인 옵션은 변경하지 않는다.

```sh
wp --skip-plugins --skip-themes eval-file wp-content/plugins/axismundi-jmap/tests/todo-two-plugin-fixture.php
```

음성 대조군은 제품 파일을 수정하지 않고 프로세스 안의 SQL 필터 또는 registry
복사본으로 권리·state·Account-bound 등록을 깨뜨린다. 기본 스윕에서는 제외하지만,
정확한 파일명으로 실행하면 예상 단언을 잡았을 때만 **CAUGHT + PASS**다.
변이가 적용되지 않거나 살아남으면 **MISSED + 실패**이고, 다른 예외는 성공으로
바꾸지 않는다. 따라서 핀의 탐지력이 사라졌을 때 성공으로 보고되지 않는다.

```sh
python tools/wp-env/run_audits.py run --audit audit-todo-poc-rights-mutations.php --audit audit-todo-poc-state-mutations.php --audit audit-todo-poc-registration-mutations.php
```

브라우저 왕복은 Playwright와 로컬 wp-env가 있는 환경에서 실행한다.

```sh
node products/wordpress/plugins/axismundi-jmap/tests/todo-browser.cjs
node --test products/wordpress/plugins/axismundi-jmap/tests/todo-sync.test.cjs
php products/wordpress/plugins/axismundi-jmap/tests/todo-mutation-harness.php
```

`AX_TODO_BROWSER_CHANNEL=chrome`으로 설치된 Chrome을 사용할 수 있다.
`AX_TODO_HTTP_ORIGIN`과 `AX_TODO_CLI_CONTAINER`는 같은 개발 환경을 가리켜야 한다.
테스트는 임시 로그인으로 UI를 조작하고 종료 시 fixture를 정리한다.

### PatchObject 왕복

RFC 8620 §5.3에 따라 update의 서버 설정 속성은 패치 전 값과 동일할 때만
허용한다. Todo의 `id`, TodoList의 `id`·`myRights`가 대상이며, 다른 값이나
미등록 속성은 `invalidProperties`와 `properties` 경로로 거부한다. create에
서버 설정 속성을 넣는 것은 여전히 허용하지 않는다. 객체 멤버 순서는 비교에
영향을 주지 않고, JSON 타입과 배열 순서는 보존한다. 기존 공유 인가는 유지한다.

실제 요청 감사는 두 타입의 전체 객체 왕복 및 최소 keywords 패치와 전체 객체
패치의 결과 일치를 확인한다. 다만 관측 가능한 속성이 하나도
바뀌지 않는 에코도 state를 올린다. 프로브 측정값은 `oldState` `10.1.…` →
`newState` `11.1.…`이고, 같은 호출에서 저장된 공유 맵은 불변이었다. 실제로
바뀌는 것은 `revision` 컬럼뿐이며, 저장 경로가 `read_clock($account) + 1`을
조건 없이 넣기 때문이다. 즉 왕복 한 번이 다른 클라이언트에게 `/get`으로
관측할 수 없는 변경을 통지한다. 이 판정에 `updated[id]`를 쓰면 안 된다.
§5.3에서 그 값이 `null`인 것은 변경이 없다는 뜻이 아니라, 클라이언트가
요청하지 않은 추가 변경을 서버가 돌려줄 것이 없다는 뜻이다. §5.2·§5.3이 금지하는 동작은
아니지만, 변경이 없으면 화면을 유지하는 `/changes` 설계와는 어긋난다. 저장
직전에 patch 결과가 기존 행과 동일한지 보고 쓰기와 `touch()`를 건너뛰는 것이
해결 방향이며, 아직 구현하지 않았다. §5.7의 `neuralNetworkTimeEstimation`은 구현하지
않으며, 숫자 오탈자로 보이는 예제 값을 별도 거부 계약으로 고정하지 않는다.
변이는 디스크 파일 대신 현재 patch 함수의 프로세스 내 복사본만 변경한다.

```sh
python tools/wp-env/run_audits.py run --audit audit-todo-patch.php --audit audit-todo-poc.php --audit audit-todo-patch-mutations.php
```

TodoList의 권한 검사는 필드 포함 여부가 아니라 패치 전후 변경 여부를 본다.
`mayWrite=true`, `mayAdmin=false`인 사용자도 자신에게 보이는 `shareWith:null`을
전체 객체로 돌려보낼 수 있다. 이 에코는 저장된 공유 맵을 삭제하지 않으며,
다른 공유 값을 보내면 여전히 `forbidden`이다. 다음 변이는 존재 기준 게이트로
되돌리기와 숨겨진 공유 맵 보존 제거를 실제 JMAP 요청으로 각각 검출한다.

```sh
python tools/wp-env/run_audits.py run --audit audit-todo-writer-mutations.php
```
