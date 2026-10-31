# Principal and Permission Boundaries

2026-10-30: JMAP/AP마다 별도 신원 원장을 만들지 않는다. 공통 주체 식별, Account 운영,
도메인 권한, 프로토콜 인증/표현을 구분한다. 이 문서는 옛 Principal 브리프의 원격 운영
grant 읽기 가능성을 현재 정책으로 제한한다. RFC 9670 wire Principal 구현 완료 선언이 아니다.

## Identity and Account Operation

`resolve_principal(kind,id)`는 wp_user/local_account/remote_actor의 기존 원장을 해소한다.
유효한 주체라는 판정은 인증이나 권한 부여가 아니다. 원격 Actor도 해소할 수 있고, 같은
숫자의 WP User/로컬 Account/원격 Actor는 다른 주체다.

`ax_actor_managers`의 owner/manager/editor는 로컬 Account 운영 관계다. 원격 주체에
새 grant를 쓰는 API는 계속 닫혀 있다. 이제 읽기에서도 remote_actor는 무조건 권한 없음이다.
오래된 remote_actor owner 행이 있어도 운영 권한/Account 목록/활성 principal 목록에
포함되지 않는다. 기존 행을 일괄 삭제하지 않으며 정확한 철회 API는 유지한다.

JMAP의 Account 인벤토리와 Account 해소는 기존 WP User 운영 권한을 계속 따른다.
도메인 역할만으로 Session의 접근 가능한 Account가 늘어나지 않는다. 로컬 Actor의
ID/수명과 JMAP Account를 공유하는 정책도 그대로다. 원격 Actor가 주체인 것은 우리
서버의 Account가 새로 생기는 것과 다르다.

### 오너 결정 (2026-10-30): 두 가지 예외와 한 가지 확장

**시스템 Actor 의 Account 접근 principal 은 WP user role 에서 파생한다.**
위의 grant 기반 규칙에 대한 의도된 예외다. 시스템 Account 에는 사람 소유자가 없으므로
`ax_actor_managers` 에 쓸 owner 가 없고, role 이 유일하게 쓸 수 있는 출처다.

```txt
일반 로컬 Actor   grant 가 답한다.  WP role 로 우회하지 않는다
시스템 Actor      WP role 이 답한다.  grant 행을 두지 않는다
```

이 예외가 좁다는 것이 중요하다. 파생되는 것은 **시스템 Account 하나의 접근 권한**이고,
role 이 일반 Account 운영 권한의 출처가 된다는 뜻이 아니다. 둘을 같은 함수의 분기로
구현하더라도, "관리자는 모든 Actor 를 운영한다"로 번지면 ③ 이 그은 경계가 사라진다.

**Subscriber 도 Actor 를 만들 수 있다.** 기존 감사가 단언했던 금지는 폐기한다.
근거는 워드프레스의 기존 권한 모델과의 정합이다.

```txt
Subscriber 가 못 하는 것   wp_posts 글 작성 → Article 없음.  미디어 업로드 없음
Subscriber 가 하는 것      댓글.  원래 구독자도 익명도 할 수 있다
C2S Note                   그 댓글에 가장 가까운 개념
```

따라서 `audit-admin` 의 "a Subscriber cannot manage or activate even its own retained
Actor row" 는 **방향을 뒤집어** 이관한다. `ensure_for_user()` 가 주는 owner grant 로
자기 Actor 를 운영하는 현재 동작이 정책이다.

**계획에 넣어야 할 결과.** 이 유비는 저작 권한에서는 성립하지만 도달 범위에서는 아니다.
WP 댓글은 글에 붙고 모더레이션 큐를 지나며 연합되지 않는다. C2S Note 는 자기 URI 와
`replies`/`likes`/`shares` 를 갖고 outbox 와 팔로워 inbox 로 나간다. 즉 **가장 낮은 WP
역할이 이 도메인 이름으로 연합 발행을 얻는다.** 설계에 이미 있는 완화 장치는 강제
sensitive 와 정책 삭제뿐이고, 승인 큐도 속도 제한도 없다. 금지 사유가 아니라, 모더레이션
슬라이스가 다뤄야 할 입력으로 기록한다.

### 오너 결정 (2026-10-30): Account 운영 권한은 완화 규칙 하나다

두 모델이 아니다. 요구 capability 하나와 그것을 낮추는 관계 하나다.

```txt
기본   부여 없는 Account 를 운영한다        manage_options
완화   owner/manager/editor 부여가 있다     read
집행   사용자는 완화된 요구를 자기 권한으로 충족해야 한다
```

완화값 `read` 는 발명한 것이 아니라 앞 절의 Subscriber 결정에서 따라 나온다. Subscriber 가
자기 Actor 를 운영할 수 있어야 하므로 완화된 요구는 `read` 보다 높을 수 없다. 그러면 실질적인
인가를 **부여의 존재가** 한다는 뜻이 되고, 그것이 의도한 바다. 부여가 권한이다.

**시스템 Actor 는 예외가 아니다.** 부여 행이 없으므로 완화가 적용되지 않고 기본 요구가 남는다.
분기를 따로 두지 않는다. 2026-10-30 측정: `ax_actors_system_actor_id` = 54895, 부여 0건이며
새 Accounts 화면은 이미 `manage_options` 로 게이트한다. 규칙과 현재 동작이 일치한다.

role 이름은 검사하지 않는다. 저장소 전체에 role 이름 검사가 0건이고 `manage_options` 가
15회 쓰인 것이 기존 선택이다. 시스템 Actor 가 이름·아이콘을 `options-general.php` 에서
읽으므로 그 화면과 같은 게이트라는 정합성도 있다.

**완화는 귀속에서 추론하지 않는다.** `attributedTo` 에 이름이 올라갔다는 이유로 완화가
적용되면 안 된다. 부여 행만이 완화의 근거다.

#### 선례

Co-Authors Plus 가 같은 모양을 쓴다. 변경 내역의 산문("rights the post's author would have
under the co-author's own role")과 코드가 다르므로 코드를 봐야 한다.

```txt
php/class-coauthors-plus.php
  add_filter( 'map_meta_cap', … )
  filter_map_meta_cap() 은 role 을 조회하지 않는다.
  co-author 이면 요구를 edit_others_posts → edit_posts 로 낮춘다
  Subscriber 가 편집하지 못하는 이유는 role 조회가 아니라 edit_posts 가 없어서다
```

CAP 는 4.1.3 에서 바이라인이 편집 권한을 주던 동작을 끊었다. 귀속과 권한을 합쳤다가 사후에
분리한 사례이므로, 위의 "완화는 귀속에서 추론하지 않는다" 는 가설이 아니다.
CAP 는 `attachment` 를 지원 대상에서 제외하므로 **미디어 귀속에는 이 선례가 없다.**

#### 구현상 따라오는 것

```txt
admin.php:26   can_manage()            부여 전용.  완화 후만 구현한다
admin.php:121  can_manage_user_actor()  manage_options 우회 있음.  기본만 구현한다
```

두 함수가 서로 다른 모델인 것이 아니라 한 규칙의 두 절반이다. 하나로 합친다.
`audit-admin` 의 실패 2건("an administrator may manage any actor",
"a Subscriber cannot manage … its own retained Actor row")이 이 분열의 표면이고,
감사 이관은 이 규칙을 단언하도록 쓴다.

#### 아직 결정하지 않은 것

```txt
부여 표면 셋의 관계      Account 운영 · 자원 공유(RFC 9610 shareWith 의
                         mayRead/mayWrite/mayShare/mayDelete) · 도메인 모더레이션
personal ↔ owner 동반     personal_owner_user_id 가 가리키는 사용자가 owner 부여를
                         갖는다는 불변식.  현재 집행 수단 없음.  KEY 는 UNIQUE 아님.
                         사용자당 personal 복수는 허용한다 -- primaryAccounts 가 기본값
누가 부여를 쓰는가        CAP 는 current_user_can_set_authors() 에서 allcaps 를 직접 읽는다
미디어 귀속              CAP 선례 없음.  Misskey DriveFile 을 참조한다
```

## Domain Moderation

Forum의 `accepted + moderator` 멤버십은 해당 Group의 모더레이션 권한이다.
로컬/원격 Actor를 다룰 수 있지만 Account 운영 grant를 생성하지 않는다. 로컬 모더레이터의
운영 User도 별도 Group grant 없이는 Group workspace/일반 발행/JMAP 접근을 얻지 않는다.

기존 정책의 단방향 포함은 유지한다: 명시적 local_account 운영 grant를 가진 Actor를
Forum이 모더레이터로 인정할 수 있다. 반대 방향은 암묵적으로 일어나지 않는다. 기존의
명시적 local moderator -> manager 승격 명령은 별도 운영자 인가와 로컬 주체 검사를 유지한다.
원격 moderator를 local Account manager로 승격하지 않는다.

모더레이터 판정은 공통 주체 resolver로 현재 상태를 다시 읽는다. 오래된 Actor 객체를
호출자가 보관하고 있어도 disabled/tombstone이면 거부한다. 공개 moderator 컬렉션과
역할 변경 경로에도 같은 생존성 경계를 적용한다. Forum의 기존 Person 타입 정책은 바꾸지 않는다.

## Protocol Boundaries

WP 쿠키/Application Passwords의 로그인 주체와 원격 Actor의 검증된 전송 주체는 같은
인증 수단이 아니다. 요청 본문의 actor나 principal_kind를 신뢰해 원격 권한을 행사하게 하지
않는다. 이번 변경은 정책 함수와 도메인 관계를 다루며 새 HTTP/서명 인증 입구를 열지 않는다.

RFC 9670 Principal은 JMAP 표현이다. 공통 내부 subject kind를 Principal.type으로 그대로
내보내거나 AP moderator 역할을 JMAP 공유 권한으로 자동 변환하지 않는다. JMAP Sharing
provider와 데이터 타입별 권한은 그 기능을 구현할 때 별도로 연결한다.

UI에서 Accounts는 로컬 운영 Account를 다룬다. 원격 모더레이터는 Actor 검색/Group 권한
관리의 대상이며, 단지 principal이라는 이유로 All Accounts에 들어가지 않는다.

## Verification

`wp eval-file wp-content/plugins/axismundi-forum/tests/audit-principal-boundaries.php`:
29검사 통과. 원격 moderator 허용, Group 범위 격리, 오래된 원격 운영 행 비활성/철회,
로컬 moderator의 workspace/발행/JMAP 비승격, pending/member/disabled/tombstone 거부,
공개 roster 제외를 확인한다. 전체 Actor/principal 픽스처는 한 트랜잭션에서 rollback하고
기존 행/원장 수 보존 및 네트워크 0회를 검사한다. 새 User는 만들지 않는다.

이 감사의 Actor/멤버십은 정책 검사용 직접 SQL 픽스처다. 승인된 Add/Remove 발행의
전체 트랜잭션, 원격 HTTP Signature, 실제 JMAP 메일/주소록 접근을 검증한 것은 아니다.
기존 audit-principal-behavior에도 원격 stale grant 읽기 거부 단언을 추가했지만 이번에는
그 감사 전체를 재실행하지 않았다.

### 오너 결정 (2026-11-01): account principal 도 Actors 가 소유한다

"actor principal 은 Actors, account principal 은 JMAP" 으로 나누지 않는다. 근거는 둘이다.

```txt
의존 방향   axismundi-jmap   Requires Plugins: axismundi-actors
            axismundi-actors  선언 없음 -- 우리 플러그인 중 의존이 없는 유일한 층
            옮기면 Actors 의 can_manage() 와 Accounts 화면이 JMAP 을 요구하게 되어
            그 유일한 방향이 뒤집힌다
주체 수     로컬 Actor 와 Account 는 ID 와 수명을 공유한다.  부여는 identity_id 로
            키잉된 한 테이블에 있다.  "actor principal" 과 "account principal" 은
            같은 행에 대한 부여이고, 나눌 두 주체가 아니다
```

이미 기록된 층 분할이 올바른 선을 말한다 -- Actors 는 principal·act-as 를, JMAP 은
Account 컨테이너·capability·limits·Session 을 소유한다. "누가 이 이름으로 행동할 수
있나" 와 "이 Account 가 프로토콜상 무엇을 할 수 있나" 의 구분이다.

#### 측정된 캡슐화 위반 (2026-11-01)

의존 **방향**은 깨끗하지만 **캡슐화**는 뚫려 있다.

```txt
JMAP -> Actors 호출 빈도
  axismundi_actors_managers_table    10
  axismundi_actors_actors_table       9
  axismundi_actors_write_manager_row  4
```

JMAP 이 Actors 에서 테이블 이름만 받아 와 직접 SQL 을 치고, 읽기뿐 아니라 쓰기도 한다.
그래서 Actors 의 스키마가 바뀌면 JMAP 이 조용히 깨진다. 함수명 변경은 bare-token grep 으로
잡히지만 **컬럼은 잡히지 않는다** -- 2026-10-30 의 개명 스윕이 전자였고, 그 방법이 후자에는
없다.

**결정: JMAP 은 Actors 의 스키마를 모른다.** API 경유 전환은 별도 슬라이스로 둔다(오너 보류).
특히 `write_manager_row` 쪽이 먼저다 -- 쓰기가 남의 스키마를 직접 만지는 자리다.

표준 참고: [RFC 9670 §1.3](https://jmap.io/spec/rfc9670/#section-1.3),
[Principal §2](https://jmap.io/spec/rfc9670/#section-2).
