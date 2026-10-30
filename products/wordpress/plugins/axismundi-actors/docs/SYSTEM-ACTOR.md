# System Actor

2026-10-30 오너 결정: 퇴역한 site Actor와 별개로 로컬 Application Actor를 자동 생성한다.
기본 preferredUsername은 `wordpress`이며 표시는 `@wordpress@사이트도메인`이다.
이 핸들은 편의상 기본값이지 이메일 발신 Identity나 권한 판정 키가 아니다.

## Provisioning

- Actors init 40에서 기존 생성 서비스를 사용한다. 로컬 Actor 본체, UUID 기반 canonical
  URI, 서명 키, 핸들 등록 이벤트는 기존 경로를 따른다. 새 테이블은 없다.
- 최초 활성화의 seed도 같은 경로를 호출한다. 신규 설치에서 퇴역한 blog/site Actor를
  새로 생성하지 않는다. 기존 옛 Actor의 퇴역은 아래 이행 절차로 처리한다.
- 시스템 역할은 `ax_actors_system_actor_id` 옵션의 Actor ID로 식별한다. 이름으로 기존
  Actor를 채택하지 않는다. 옛 site 역할과 별개이며 아직 인스턴스 서명 기본값으로 연결하지 않는다.
- 초기 핸들은 `ax_actors_system_actor_username` 옵션(기본 wordpress)에서 읽는다.
  충돌 시 오류로 멈춘다. SQL advisory lock은 동일 사이트의 동시 자동 생성을 직렬화한다.
- 바인딩 후 핸들을 기본값으로 되쓰지 않는다. invalid/deleted/disabled 바인딩에서 새
  Actor를 자동 생성하거나 재활성화하지 않는다. read getter는 생성하지 않는다.
- 기존 local 생성의 커밋과 후속 바인딩 저장은 한 트랜잭션이 아니다. 그 사이 실패하면
  생성된 Actor는 남을 수 있다. 충돌 검사가 무조건 재생성하는 것을 막으며 명시적 복구가
  필요하다. 다른 생성 경로와의 핸들 경합으로 suffix가 생겨도 조용히 채택하지 않는다.
- WP User 소유권/transport user/운영 principal을 자동 부여하지 않는다. 시스템 역할과
  사람이 운영할 권한은 별도다. 정상 로컬 생성 이벤트에 의해 JMAP이 활성 상태이면 기존
  Account provisioner가 같은 Actor ID의 Account 행을 만든다. Actors는 JMAP에 의존하지 않는다.
- 삭제 불가: 공통 set_status의 tombstone 전이와 cleanup_deleted_actor를 ID 바인딩으로
  거부한다. disabled는 삭제와 별개이며 허용한다. 직접 SQL이나 DB 관리자까지 막는
  제약은 아니며, 플러그인 API 경계의 보호다.

## Deferred

핸들 변경을 지원할 의도는 유지하지만 현재 공통 handle-lock 규칙과 alias/WebFinger 정책을
우회하지 않는다. 변경 UI와 rename 계약은 후속이다. 초기 옵션 변경은 기존 핸들 rename이 아니다.
시스템 Actor의 표시 name은 일반 설정의 사이트 제목, icon은 사이트 아이콘을 조회 시점에
사용한다. Actor별 이름 번역이나 별도 아바타가 이 두 값을 덮지 않는다. 제목/아이콘 변경은
다음 조회부터 반영하며 Actor 테이블에 설정 사본을 계속 쓰지는 않는다. UUID/핸들/summary와
권한에는 영향을 주지 않는다. 아이콘을 해제하면 WP User Gravatar로 폴백하지 않는다.

Contacts의 wp_users 기반 읽기 전용 AddressBook, Mail의 발신 Identity와 EmailSubmission,
wp_mail 어댑터는 이번 범위가 아니다. WP 코어 발신자, 사용자 데이터, 메일 전송은 변경하지 않는다.

## Verification

`wp eval-file wp-content/plugins/axismundi-actors/tests/audit-system-actor.php`가 반복 생성,
seed 재실행, 이름 기반 채택 거부, invalid binding, 두 삭제 경계, 키/핸들 보존, disabled
비재활성화, principal 부재를 검사한다. JMAP 저장소 활성 환경에서는 같은 ID의 Account도
확인한다. 상태/옵션 픽스처는 rollback하며 감사용 Actor를 새로 만들지 않는다.
실제 신규 설치 전체나 동시 요청의 경합/장애 주입을 검증하는 명령은 아니다.

로컬 환경에서 `@wordpress?activitypub`의 200 Application 문서, preferredUsername,
canonical UUID URI와 기존 inbox/outbox 투영을 확인했다. 이 확인은 메일 전송 또는
인스턴스 서명 기본값 연동을 검증한 것이 아니다.

사이트 브랜딩 연결 후 로컬 감사 34검사 통과: 제목 변경의 즉시 반영, 저장된 Actor 이름
번역보다 사이트 제목 우선, 오래된 nameMap 제거, icon getter/SSR/profile/AP 출력 일치,
아이콘 해제 시 Gravatar 비폴백을 확인했다. 옵션/번역 픽스처는 rollback했다.
실제 AP 응답에서 name=Axismundi Workspace와 현재 사이트 아이콘을 확인했다.

## Legacy Site Retirement

오너 요청으로 옛 site 전용 구현을 제거한다. 검토 후 재사용한 것은 사이트 제목/icon의
라이브 조회와 Article generator다. 브랜딩은 위 시스템 역할에 연결하고 generator도 새
시스템 Actor로 변경했다. 옛 site-owner 옵션의 권한 우회는 새 Account에 이식하지 않는다.

init 45에서 새 시스템 Actor가 존재할 때만 옛 설정 ID를 tombstone으로 전이한다. 명시 ID가
없는 옛 설치는 단일 local actor_scope=site 행만 이행 대상으로 삼고 복수면 중단한다.
URI/행과 기존 원장 참조를 보존하고, 정상 삭제 서비스로 핸들과 비밀키를 해제한다.
물리 삭제나 과거 URI를 새 Actor로 덮어쓰는 작업은 아니다. 이행 receipt만 남기고 기존
instance/site-owner/type/disabled 옵션은 제거한다. getter/instance 판정/강제 disable 훅은
폐기한다. 재실행은 receipt로 중단한다.

Bridge에서 옛 공식 플러그인의 -1/0 site 식별자는 더 이상 새 시스템 Account에 자동 매핑하지
않는다. 근거 없는 과거 귀속 이전을 피하기 위해 명시적 이행이 없으면 blocked 처리한다.
과거 site Actor를 공유하던 감사는 새 시스템 Actor 또는 일반 Actor 판정으로 변경한다.
다국어 이름 감사는 사이트 제목에 묶인 시스템 Actor 대신 일반 로컬 Actor를 사용한다.

식별 근거는 preferredUsername이 아니라 사이트 설정의 역할 바인딩이다.
`ax_actors_system_actor_id -> ax_actors.id`가 한 사이트의 시스템 역할을 지정한다.
Application은 일반 액터 타입일 뿐 시스템 역할을 뜻하지 않는다. UUID/URI는 정체성을,
설정은 그 정체성의 용도를 나타낸다. 나중에 핸들을 변경해도 이 바인딩은 유지한다.

퇴역 후 system 감사 39검사, vocabulary 감사 32검사, 일반 Actor projection 감사 12검사
통과. 로컬의 옛 Actor 25564는 Activity 참조 0건인 disabled blog였으며 Tombstone으로
전이됐다. UUID/기존 원장 기록을 물리 삭제하거나 새 Actor 54895로 재귀속하지 않았다.
참조만 이전한 모든 역사적 감사 전체를 재실행한 것은 아니다.
