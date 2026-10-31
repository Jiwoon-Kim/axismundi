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

## 소유와 관찰 (오너 결정, 2026-11-01)

시스템 Account 가 들고 있게 될 두 자원 -- 코어 메일 기록과 `wp_users` 주소록 -- 은 둘 다
우리가 저작한 것이 아니다. 그래서 구분을 명시한다.

```txt
소유   우리가 권위다.  저장하고 바꿀 수 있다
관찰   남이 권위다.  투영하고 바꾸지 않는다

코어 메일              코어가 권위.  무엇이 나갔는지 기록만
wp_users 주소록        WP 가 권위.  투영
원격 객체 캐시          원본 서버가 권위 (OP 의 axObservation · lease)
사용자 자신의 Card      Account 가 저작했다 -> 소유
```

**이 구분이 삭제 동작을 결정한다.** 관찰은 원본이 사라지면 함께 사라지고 정리할 것이 없다.
소유는 남는다. 섞으면 사용자를 지웠는데 남의 주소록에서 사라지거나(소유를 관찰로 취급),
지웠는데 디렉터리에 남는다(관찰을 소유로 취급).

관찰 투영을 말하는 수단은 RFC 9610 의 `myRights` 다 -- 투영된 AddressBook 은
`mayWrite: false` 로 광고한다. **2026-11-01 측정: `myRights`·`mayRead`·`mayWrite`·
`mayShare`·`mayDelete` 가 구현에 0건이다.** 그 조각은 `shareWith` 공유에도 필요하므로
한 번 만들면 둘에 쓰인다. `AddressBook/get·set·changes` 가 아직 미구현이고 contacts
capability 를 광고하지 않는 지금이 가장 싸다 -- `set` 이 나간 뒤에는 규칙이 생기기 전에
투영된 카드에 쓰기가 일어날 수 있다.

투영을 고치려는 요청은 거부가 아니라 **내 저장소에 사본**으로 처리한다. 원격 JSContact
프로필에 대해 이미 정한 패턴과 같다("연락처에 추가" 가 ContactCard 사본을 만든다).

## 코어 메일 관찰 (오너 결정, 2026-11-01)

`wp_mail()` 을 우리로 **라우팅하지 않는다.** 코어가 계속 PHPMailer 로 보내고, 우리는
무엇이 나갔는지 기록한다. 라우팅하면 `wp_mail()` 을 호출하는 모든 것 -- 코어와 서드파티
플러그인 전부 -- 이 우리를 통해 나가고, 도달성 책임(SPF·DKIM·DMARC 정렬 · IP 평판 ·
반송 처리)이 함께 넘어온다.

기록 지점은 **의도가 아니라 결과**다. 2026-11-01 `wp-includes/pluggable.php` 측정:

```txt
622  phpmailer_init        실제 PHPMailer 객체 (참조 전달)
628  ->send()
651  wp_mail_succeeded     결과
665  wp_mail_failed        결과 (send 가 던짐)
455  wp_mail_failed        다른 실패 지점 -- 발송 전 검증 (잘못된 수신자 등)
```

실패 지점이 **둘**이다. 665 만 보면 가장 흔한 실패(주소 오류)가 로그에 남지 않는다 --
발신 기록을 만드는 목적이 바로 그걸 찾는 것이다. 그리고 `Message-ID` 는
`phpmailer_init` 시점에 확정되지 않고 `send()` 안에서 생성되므로, 충실한 기록은 두 지점을
묶는다: 622 에서 메시지 객체를 붙잡고, 결과 훅에서 확정된 ID 와 성공/실패를 읽는다.
그 ID 가 JMAP `Email.messageId` 이고 스레딩과 대조가 거기 걸린다.

JMAP 모델로는 `Mailbox(role=sent)` 에 `Email` 만 두고 **`EmailSubmission` 은 두지 않는다**
-- 우리가 제출한 것이 아니다. RFC 8621 도 Sent 는 Email 을 담고 submission 을 담지 않는다.
즉 코어에 "보낸편지함이 없다" 가 아니라 **기록할 Email 레코드가 애초에 없다**는 것이고,
관찰 투영이 그 대상을 만든다.

**기본은 메타데이터만** -- 수신자 · 제목 · 시각 · 성공/실패 · 발신 Identity. 본문과 첨부는
적지 않는다. 코어 메일 본문에 비밀번호 재설정 링크가 들어가고, 시스템 Account 는 부여가
없으므로 `manage_options` 로 열린다(PRINCIPAL-BOUNDARIES.md 2026-10-30). 본문을 적으면
그 발신 기록이 **운영자용 계정 탈취 경로**가 된다. 발신 기록의 목적은 메타데이터로 달성된다.

## 사내 메일의 경계 (오너 결정, 2026-11-01)

양쪽이 로컬 Account 면 SMTP 도 IMAP 도 필요 없다. 사내 전용은 **전송 계층을 뺀 JMAP Mail
전체**이고 외부 표면이 0 이다. 단 그 이득은 위의 "라우팅하지 않는다" 를 지킬 때만 유지된다.

```txt
주소        기본은 핸들에서 파생 (handle@site-host).  Account 는 발신 Identity 를
            여럿 가질 수 있으므로, 안정적인 주소가 필요하면 Identity 로 독립시킨다
            -- 파생은 편의 기본값이고 구속이 아니다
옛 주소     핸들이 바뀌어도 former_handle 네임스페이스로 해소하면 계속 배달된다.
            DATA-MODEL.md §9.2 의 미결과 같은 결정이고, 메일 쪽 근거가 더 강하다
mail_identity   'handle' 네임스페이스를 공유해야 한다.  repository.php:109 는 새 타입에
            자기 네임스페이스를 주므로, 기본 동작대로면 alice 가 Identity 'bob@site-host'
            를 만들 수 있고 사내 배달이라 그 메일은 실제로 bob 이름으로 도착한다.
            배열에 한 항목을 더하면 파생 주소·옛 핸들·수동 Identity 가 한 공간에서
            경쟁하고 UNIQUE 가 전부를 집행한다
외부 수신   발신보다 비싸다 -- MX 레코드와 상시 리스너가 필요하다.  "발신 안 하니 수신은
            괜찮다" 는 성립하지 않는 방향이다
램프 아님   외부의 어려운 부분을 사내 배달이 하나도 연습시키지 않는다.  1단계/2단계가
            아니라 독립적으로 가치 있는 슬라이스다
사이트      우리와 무관하게 코어가 외부로 보낸다 -- 비밀번호 재설정 · 신규 사용자 알림 ·
            이메일 변경 확인.  사내 메일을 만들어도 사이트의 메일 설정 문제는 남는다
```

`wp_users` 주소록 투영은 위 "관찰" 규칙을 따른다. UID 는 주지 않는다 -- UID 는 우리가
소유하는 지속 정체성을 뜻하고, WP User 와 공개 Actor 가 같은 주체라는 보장도 없다.
UID 없는 Card 는 JSContact `version: "2.0"` 을 전제로 한다(1.0 은 uid 필수).

## 미결 (2026-11-01)

```txt
JMAP Mail 플러그인   존재하지 않는다.  modules/ 에 rfc8620 하나뿐이고 capability 는
                     core · error 둘이다
myRights             구현 0건.  관찰 투영과 shareWith 가 같은 조각을 쓴다
RSVP 전송             axismundi-calendar/docs/RSVP-TRANSPORT.md
