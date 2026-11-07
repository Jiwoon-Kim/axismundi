# 제출 패키지 검증

## 순서와 버전

dev ZIP을 먼저 빌드했고, 파일 업로드 권한 제한으로 사용자가 스테이징에 설치했다.
설치 후 Chrome의 실제 플러그인 목록과 공개 HTTP 응답을 확인한 다음에만
아래 버전의 -dev를 제거하고 ZIP을 다시 빌드했다.

| 플러그인 | 스테이징에서 확인한 활성 버전 | 제출 패키지 |
| --- | --- | --- |
| Actors | 0.1.9-dev | 0.1.9 |
| Activities | 0.1.6-dev | 0.1.6 |
| Object Projections | 0.1.8-dev | 0.1.8 |
| ActivityPub Bridge | 0.1.3-dev | 0.1.3 |

스테이징 도메인은 designbusan.ai.kr이다. 제출 버전은 아직 스테이징에 재설치하지 않았다.
각 ZIP의 엔트리를 dev ZIP과 비교했다. 바뀐 파일은 메인 PHP와 readme.txt뿐이다.
메인 PHP의 차이는 버전 헤더와 버전 상수의 -dev 제거뿐이며 실행 로직은 동일하다.
readme에는 stable tag와 해당 릴리스 changelog를 반영했다.
tests, scripts, docs가 배포 ZIP에 포함되지 않고 경로 구분자가 /임을 확인했다.

## 스테이징 확인

- 네 플러그인이 예상 dev 버전으로 활성화돼 있다.
- NodeInfo discovery는 단일 2.1 링크로 새 axismundi/nodeinfo 경로를 광고한다.
- 새 NodeInfo 문서는 HTTP 200이다.
- 구 /nodeinfo/2.1은 CDN STALE 응답에서 구 문서 200이 남아 있었다.
  새로운 release_check 쿼리로 원본을 읽으면 새 문서로 302다. 캐시를 삭제하지 않았다.
- Person 액터의 정체성 URI는 그대로이고 inbox, outbox, sharedInbox는
  axismundi/activitypub 아래를 광고한다.
- 새 공개 Outbox 페이지는 200, OrderedCollectionPage, 활동 27건이다.
  Create/Update의 inline object는 0건이다. 과거 인라인 본문 재노출 방어를
  이 액터의 현재 공개 페이지에서 확인한 것이며 모든 권한·Tombstone 분기를 증명하지 않는다.
- Sent는 200, OrderedCollectionPage, 22건이다.
- 익명 Actor Inbox GET은 403이다.
- shared Inbox 루트와 페이지는 200이고 OrderedCollection/OrderedCollectionPage다.
  현재 페이지가 비어 있어 Public-only 필터의 긍정·부정 경로를 이 응답으로 증명하지 않는다.
- 구 Outbox GET은 page=1을 보존해 새 경로로 302다.
- 구 shared Inbox GET도 새 경로로 302다.
- 원격 객체 c27d3e862bb182755af8a125328118aa1fadc6fe83ff78884a627a1be06b1614의
  HTML과 AS2를 확인했다. AS2 id는 podcastindex.social 원격 URI 그대로다.
  구 ax_object 및 uri 진입점은 같은 정규 경로로 302다.
- C2S Composer의 Create/Update 모드 전환과 Update 요청 미리보기가 동작한다.
  미리보기는 새 Outbox POST 경로를 사용하고 쿠키·nonce를 마스킹한다.
- Follows의 라벨·코드·폼 버튼과 Activity Log의 링크가 HTML로 정상 렌더된다.
- 원격 객체 블록 템플릿에서 Repost 메뉴를 실제 열고 닫아 Interactivity 동작을 확인했다.
- 브라우저 콘솔에는 화면 이동 중 ViewTransition 중단 오류가 있었다.
  이번에 검사한 모드 전환과 메뉴 열기·닫기는 동작했다. 무오류 콘솔로 보고하지 않는다.

외부 발행, Follow 변경, 배송 워커 실행, 큐 재시도, 가져오기, purge는 하지 않았다.

## 배포 ZIP 검사

별도 에이전트가 실제 Activities dev ZIP을 /tmp의 별도 디렉터리에 추출하고
wp plugin check의 절대 디렉터리 대상을 사용했다. 마운트된 원본을 설치·교체하지 않았다.

- 전체 검사: 오류 0건, 경고 337건. 종료 코드 0만으로 전체 통과라 부르지 않는다.
- late_escaping: 지적 사항 없음.
- 경고 분류: DB parameter 86, 직접 질의·캐시 145, nonce 53,
  보간 SQL 22, placeholder 9, 입력 sanitation/unslash 14,
  schema 변경 7, DONOTCACHEPAGE prefix 1.
- 비활성 절대 디렉터리 대상이라 런타임 검사는 수행되지 않았다.
- stderr에 Activities 번역 로딩 시점 notice가 남았다.
- Contributor Follows 감사의 옛 profile.php 기대값은 현재 Accounts의
  admin.php?page=axismundi-follows 계약과 다르다. 감사 파일은 이번에 변경하지 않았다.

증거는 _dist/release-candidate-20261107/의 staging-plugins.png와
plugin-check-full.json, plugin-check-late-escaping.json 등에 보관한다.

## 남은 범위

아래 후속 검증에서 깨끗한 WordPress 설치의 런타임 검사까지 수행했다.
전체 Plugin Check 경고는 해소되지 않았다. wp.org 승인이나 warning-clean을 뜻하지 않는다.
wp.org 업로드, 리뷰 회신 발송, 커밋, 푸시는 하지 않았다.

## 깨끗한 설치 후속 검증

2026-11-08 KST에 별도 Docker 프로젝트 ax-clean-review-20261108을 만들었다.
기존 wp-env 및 스테이징의 DB·볼륨을 사용하지 않았다.
WordPress 7.1.4를 새로 다운로드하고 기본 Twenty Twenty-Five 테마를 사용했다.
새 DB의 기본 콘텐츠 외에는 이전 사이트의 콘텐츠·옵션·사용자를 가져오지 않았다.
네 플러그인은 제출 ZIP에서 설치했다. 공식 ActivityPub 9.4.3과 Plugin Check 2.2.0은
wordpress.org에서 설치했다. WP_DEBUG=true, WP_DEBUG_LOG=true이며,
자동 배송을 방지하기 위해 DISABLE_WP_CRON=true로 실행했다.
검증 주소는 127.0.0.1에만 바인딩한 http://localhost:8886 이다.

### 실행 결과

- 네 플러그인의 제출 버전이 새 설치에서 활성화됐다.
- 초기 Account가 없는 컴포저는 선택된 Account가 없음을 표시하고 제출 폼을 열지 않았다.
- 로컬 검증용 개인 Account를 준비한 뒤 브라우저로 Create(Note)와 Update(Note)를
  실제 제출했다. 서로 다른 Activity URI가 생성되고 같은 Object URI를 유지했다.
- script 태그 모양의 입력은 컴포저 요청 문서에서 엔티티 처리됐다.
- 수정 후 Object AS2 GET에서 변경된 content와 같은 id를 직접 확인했다.
- 홈페이지, NodeInfo discovery·문서, shared Inbox 페이지, Actor, Object,
  Outbox 페이지 일곱 경로가 모두 200이다.
- 최종 공개 HTTP 점검 전 기존 로그를 별도 파일로 보존했으며,
  해당 점검 후 새 PHP debug.log 항목은 없었다.
- 새 설치의 활성 Activities에 전체 Plugin Check를 실행했다.
  결과는 오류 0건·경고 337건으로 앞선 ZIP 정적 검사와 같았다.
  late_escaping 재검사도 지적 사항이 없고 stderr는 비어 있었다.

### 선택 의존성 조합

기본 네 플러그인만으로 브라우저 Create·Update와 입력·출력 검사 8개는 통과했다.
Dialogs 없이 실행한 모달 감사는 사전조건 예외로 중단됐고 Follow 버튼 감사는
모달 항목 하나가 실패했다. 이를 플러그인 런타임 오류나 통과로 숨기지 않았다.
그 시점의 로그는 /tmp/debug-before-final-smoke.log로 보존했다.

그 다음 선택 의존성 Dialogs 0.3.1도 별도 빌드 ZIP으로 설치했다.
감사 파일은 설치 플러그인에 포함하지 않고 /tmp로 따로 복사해 실행했다.

- audit-review-output.php: 8/8.
- audit-follow-modal-kses.php: 9/9, allowlist 변이 탐지 포함.
- audit-follow-button.php: 13/13, 로컬 Follow/Unfollow 및 mocked 원격 검사.
- audit-publish-selection.php: 24/24.

총 54개 통과다. 원격 배송 워커를 실행하거나 실제 원격 Follow를 보내지 않았다.

### 경고 분류와 회신

EscapeOutput 또는 late_escaping 지적은 없었다. NonceVerification은 53건 남았다.
파일별로 migration 14, workflows 33, editor-selection 3,
scheduled-publication 3이다. 서비스 함수의 nonce 검증과 호출 지점 사이를
정적 검사기가 연결하지 못하는 경우가 포함되지만 전부 오탐이라고 결론내리지 않는다.

입력 정화·unslash 경고 14건은 REQUEST_METHOD 직접 비교,
편집기 Account 선택 값의 엄격한 숫자 검증, 발행 목록의 post_id·operation을
읽는 지점에 분포한다. 이번 지적의 whole-POST 전달이 남았다는 뜻은 아니다.
DB parameter·직접 질의·캐시·SQL placeholder 등 나머지 경고도 그대로 남긴다.
경고라는 이유만으로 리뷰어가 무시하거나 제출에 문제가 없다고 보장하지 않는다.

editor-selection nonce는 검증 직전 정화뿐 아니라 원래 POST를 읽는 곳에서도
정화하도록 수정했으므로, 회신에서 오탐이라고 되묻지 않는다.
회신에는 실제 수정 내용과 깨끗한 설치 검증 사실만 적는다.

후속 증거는 _dist/clean-review-20261108/의 검사 출력과
create-note.png, update-note.png에 있다. 제출 ZIP의 실행 코드는 추가로 바꾸지 않았다.

## Actors와 OP SVN 릴리스

위의 미제출 기록은 Activities 심사 업로드와 Bridge 신규 제출에 대해서는 유지한다.
이후 사용자의 별도 SVN 배포 지시로 공개 플러그인 둘만 릴리스했다.
Activities와 Bridge는 이번 SVN 배포 대상이 아니다.

| 플러그인 | 버전 | SVN revision | 파일 수 |
| --- | --- | --- | --- |
| Actors | 0.1.9 | 3789806 | 103 |
| Object Projections | 0.1.8 | 3789809 | 142 |

깨끗한 기존 SVN 작업 사본을 update한 뒤 검증 ZIP만 별도 stage에 추출했다.
작업 소스 디렉터리를 복사하지 않았다. ZIP과 trunk의 파일별 SHA-256을 대조하고,
working-copy trunk를 tags/버전으로 복사해 trunk와 tag를 같은 revision으로 커밋했다.
삭제할 구 파일은 없었다. assets와 이전 tags는 바꾸지 않았다.

커밋 후 해당 revision의 원격 tag를 새 디렉터리에 export해 파일 수와 SHA-256을
다시 대조했다. WordPress.org Plugin API가 새 버전을 반환함을 확인하고,
downloads.wordpress.org의 실제 버전 ZIP도 내려받아 원래 ZIP의 모든 파일과
SHA-256이 일치함을 확인했다. SVN 작업 사본 둘은 배포 후 깨끗하다.

로컬 stage와 원격 export·공개 다운로드 증거는
C:/Users/thaum/dev/wporg-svn/_release-20261108/에 보관한다.
Git 소스 변경의 커밋·푸시, Activities 심사 업로드·회신, Bridge 신규 제출은 하지 않았다.
