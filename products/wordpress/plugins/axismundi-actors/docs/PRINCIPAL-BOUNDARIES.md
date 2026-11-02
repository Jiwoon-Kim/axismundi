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

#### 범위 수정 (2026-11-01): 위 결정은 Account 운영 부여에만 해당한다

제목이 "account principal" 이라 principal 일반으로 읽힌다. 그 결정이 덮는 것은
**Account 를 운영할 자격**, 즉 `ax_actor_managers` 의 owner/manager/editor 부여다.
의존 방향 논증(Actors 는 아무것도 요구하지 않는 유일한 층)도 그 범위에서만 성립한다.

RFC 9670 의 **Principal 모델**은 Actors 것이 아니다. 오너 결정:

```txt
Actors        Account 아이덴티티 · WP 사용자 연결 · 기존 Account 운영 부여
JMAP          RFC 9670 Principal 모델 · 발견 · Account 연결 · 공유 대상 해소
자원 소유자    공유 설정 저장과 읽기·쓰기·공유 권한 집행.  JMAP Principal ID 를 참조
              (Contacts 의 AddressBook, Calendar, PoC 의 TodoList)
```

**"누구에게" 는 JMAP Principal, "무엇을 허용하는가" 는 그 자원이다.** AddressBook 이나
Calendar 의 권한을 Actors 에 넣지 않는다.

그리고 **Account 운영 부여를 자원 공유 권리로 자동 해석하지 않는다.** Account 를 운영할
수 있다는 것이 그 Account 의 주소록을 쓸 수 있다는 뜻은 아니다. 이것은 이미 세 번 나온
같은 원칙이다 -- 귀속이 권한을 끌고 오지 않고(CAP 4.1.3), 저장된 부여가 접근이 아니며,
이제 운영 자격이 자원 권리가 아니다.

#### 오너 결정 (2026-11-01): RFC 9670 Principal 은 JMAP core 가 소유한다

위 미결을 닫는다. 그리고 질문 자체가 잘못 세워져 있었다.

**투영이냐 소유냐는 거짓 양자택일이다.** 그 질문은 Actors 의 주체 해소와 RFC 9670 Principal 이
같은 개념의 두 표현이라고 전제한다. 아니다.

```txt
axismundi_actors_resolve_principal()   우리 정책에서 유효한 주체인가 (wp_user · local_account · remote_actor)
RFC 9670 Principal                     id · 수명 · 발견 메서드를 가진 프로토콜 객체
```

다른 개념을 각각 소유하고 **명시적으로 연결**하는 것은 중복이 아니다. 중복은 같은 판정이
두 곳에 있을 때다 -- 이 문서가 기록한 `can_manage` 두 함수가 그것이었고, Principal 은 그 경우가
아니다.

**결정 근거:** Actors 가 Principal 을 들고 있으면 **우리 운영 부여 모델이 표준 Principal 의
정의가 된다.** 우리 정책을 프로토콜 객체에 끼워 넣는 것이고, 그것이 안 되는 이유로 충분하다.
AP Actor 와 JMAP Principal 은 애초에 같은 개념이 아니다.

```txt
Actors      Actor·Account 식별 · WP 사용자 연결 · AP 귀속 관계 · 기존 운영 부여
JMAP core   Principal id·수명 · RFC 9670 객체와 발견 메서드 · 사용자·Account 와의 연결
자원 소유자  JMAP Principal ID 를 참조하는 공유 설정과 권한 집행
```

Actors 의 운영 부여는 **JMAP 인가의 입력이 될 수 있지만** Principal 자체나 AddressBook 공유
권리를 정의하지 않는다.

#### 세 관계를 분리한다 (2026-11-01)

관계의 양 끝이 서로 다르다. 이것이 위 결정이 필요한 이유다.

```txt
사용자 ↔ Account    개인 귀속.  isPersonal 의 근거
Actor ↔ Actor       AP 귀속.  attributedTo 의 근거
Principal ↔ 자원     위임·공유 권리
```

**한 관계에서 다른 관계를 자동 추론하지 않는다.** PeerTube 채널이 Alice 의 Person Actor 에
귀속된다고 해서 Alice 세션에서 그 채널 Account 가 `isPersonal: true` 인 것은 아니다.
개인 Account 인지 공동 운영 Account 인지는 별도 판정이다.

그래서 "Actors 는 `isPersonal` 만 답한다" 도 너무 좁았다. Actors 는 `isPersonal` 이라는 JMAP
필드를 답하는 것이 아니라 **그 계산에 필요한 사실**("이 Actor 가 누구의 개인 정체성인가")을
제공하고, 요청자 기준의 계산은 JMAP 이 한다. Group 의 소유·운영 관계도 Actors 쪽에 있을 수
있다 -- 다만 그것을 JMAP Principal 이나 자원 권리와 동일시하지 않는다.

Lemmy 와 PeerTube 는 **Actor ↔ Actor 관계와 운영 정책의 선례**이고 사용자 ↔ Account 를
결정하는 근거가 아니다. 두 구현이 서로 다르다는 사실 자체가, `attributedTo` 하나로 보편적인
소유권·권한 모델이 정의되지 않음을 보여 준다. (구현 세부는 2026-11-01 codex 의 소스 독해이고
이 문서의 실측이 아니다. Lemmy 가 모더레이터 컬렉션을 `attributedTo` 로 쓰는 것은 FEP-1b12 와
일치한다.)

## 제품은 Actor 가 아니라 Account 를 통해 붙는다 (방향, 2026-11-01)

Actors 는 코어 엔진이고 제품에 의존하지 않는다. 그런데 반대 방향도 정리가 필요하다 —
**제품이 Actor 에 직접 매달리는 것**과 **Account 를 통해 붙는 것**은 다르다.

```txt
지금    Calendar 가 Actor URI 에 직접 키잉한다
        axismundi_cal_calendars_table() 의 authority_actor_uri_hash
목표    Calendar 는 JMAP 의 Account 컨테이너에 붙는다
        Contacts(addressbook) · Todo(todolist) · Mail(mailbox) 과 같은 자리
```

기록된 모델과 같다 — 정체성은 컨테이너이고, JMAP 이 Account 면을 소유하며
(`ax_jmap_accounts`), Actor 면이 그 위에 얹힌다. 그러므로 Account 의 컬렉션을
다루는 제품은 **Account 를 보면 되고 Actor 를 볼 이유가 없다.**

Actors 가 직접 답해야 하는 것은 좁다.

```txt
Actors    정체성 · 핸들 · 타입 · 귀속 · ActivityPub curated collection
JMAP      Account 컨테이너와 그 컬렉션 — addressbook · calendar · todolist · mailbox
제품      자기 도메인. Account 를 통해 붙는다
```

### 증상은 픽스처 정리에서 드러났다

`axismundi-actors/tests/audit-vocabulary.php` 의 정리 코드가 Actor 하나를 지우려고
Calendar 와 Activities 와 Forum 테이블까지 지우고 있었다. 액터를 만들면 다른
플러그인이 행을 만들고, 그 행들이 Actor URI 로 키잉돼 있어서 Actors 가 치울 수밖에
없는 구조였다.

당장은 각 테이블 접근을 `function_exists` 로 감싸 **하드 의존만 끊었다.** 제품
플러그인이 꺼져 있어도 Actors 자기 감사는 돌아야 하기 때문이다. 구조적 해결은
따로다 — **Actor 삭제에 반응해 자기 행을 치우는 것은 각 제품의 책임**이고,
Calendar 가 Account 를 통해 붙으면 그 키잉 자체가 사라진다.

## 컬렉션 권한 — 두 프로토콜을 섞지 않는다 (2026-11-01)

addressbook 과 followers·moderators 는 컬렉션으로서 닮았지만 **권한 모델이 다르다.**
구현할 때 섞으면 한쪽 어휘로 다른 쪽을 제어하게 된다.

```txt
JMAP   주체 = principal (인증으로 확정).  Account 는 행위자가 아니다
       권리 = 객체가 들고 다니는 뷰어별 myRights
       컬렉션을 직접 읽고 쓴다
AP     주체 = actor
       뷰어별 권리라는 개념이 없다 — 가시성 + Activity 제출의 부작용
       "당신 팔로워에 나를 추가" 같은 연산이 존재하지 않는다
```

실측: `myRights` 는 JMAP 쪽에만 있고 OP·Activities·Actors 에 0 건이다. 코드는
이미 갈라져 있으므로 지켜야 할 것은 개념이다.

```txt
금지   curated collection 에 myRights 를 만드는 것
       필요한 것은 "누가 큐레이션하는가" 하나이고 append_policy 가 표현한다
주의   curated 만 직접 쓰는 유일한 AP 컬렉션이라 JMAP 처럼 보인다.
       가장 혼동되기 쉬운 자리다
```

### 왜 Contacts 는 자기 컬렉션을 갖고 Forum 은 못 갖나

**Actor 는 subject 이고 contact card 는 object 다.** 카드는 결코 행위 주체가 되지
않으므로 제품 저장소에 있어도 되고, Actor 의 컬렉션은 **행위하는 정체성의 일부**라
정체성과 함께 있어야 한다.

```txt
addressbook   Account 안의 내용물. 바깥에서 역참조할 일이 없다
moderators    Actor 문서에 attributedTo 로 나가고 원격이 역참조한다
```

시험은 비활성화다. Contacts 를 끄면 addressbook 이 사라지고 Actor 문서는 멀쩡하다.
Forum 이 moderators 를 쥔 채 꺼지면 **여전히 존재하는 정체성의 Actor 문서가
attributedTo 를 잃는다.** 그래서 Forum 것일 수 없다.

### 그래서 Contacts 는 두 군데에 붙는다 (2026-11-01)

```txt
profile card   Actor 의 WebFinger acct 로 게시되므로 Actor 에 직접 매달린다
               실제로 owner_actor_id 에 키잉돼 있고, 링크는 Actors 가 선언한
               axismundi_actors_webfinger_links 필터를 Contacts 가 채워 넣는다
               (jscontact.php:211).  JMAP 은 이 경로에 0 건이다
addressbook    Account 의 것이므로 JMAP Account 에 의존한다
```

시험은 JMAP 비활성화다. 프로필이 Account 에 키잉돼 있으면 여전히 존재하는 정체성의
`describedby` 가 JMAP 유무로 깨지고, Actor 문서가 제품에 의존하게 된다.

Contacts 를 끄면 `describedby` 링크만 사라지고 Actor 문서는 멀쩡하다. 그것은
허용된다 — 프로필은 **정체성의 선택적 상세**이지 정체성 자체가 아니다. Group 의
`attributedTo` 가 사라지는 것과는 성질이 다르다.

## 세션과 소유권 (2026-11-02)

위 "세 관계를 분리한다" 에 빠져 있던 네 번째가 **세션**이다. 이미 구현돼 있다 --
`includes/acting-actor.php` 의 `axismundi_actors_acting_actor()` 와 전환기(`3e8c90b`).
없던 것은 코드가 아니라 개념이었다.

```txt
user → actor   personal   personal_owner_user_id ∧ role='owner'
                          그 액터가 그 user 자신.  양도 가능
user → actor   operated   role = owner|manager|editor
                          그 액터를 그 user 가 운영.  managed Group 이 전형
actor → actor  귀속       attributedTo.  공개·연합.  로컬 능력을 주지 않는다
session        발화 주체   acting_actor = (personal ∪ operated) 에서 고른 런타임 선택
—              출처       initial_owner_user_id.  권한에 읽지 않는다
```

### 소유는 별도 축이 아니라 비공개 축의 한 양태다

`includes/account-access.php` 가 이미 그렇게 돼 있다(2026-11-02 실측).

```php
function axismundi_actors_account_is_personal( Axismundi_Actor $actor, int $user_id ) : bool {
	return $user_id > 0 && $actor->is_local()
		&& $user_id === $actor->get_personal_owner_user_id()
		&& 'owner' === axismundi_actors_account_role( $actor->get_identity_id(), $user_id );
}
```

**personal 은 소유 사실과 owner 역할의 논리곱**이다. 소유만으로 권한이 따라오지 않는다.
주석이 `never inferred from Actor type or manager role alone` 이라고 적고 있다.

필드가 셋으로 갈라져 있고 역할이 다르다.

```txt
personal_owner_user_id   현재 개인 소유.  양도 가능.  권한 판정의 한쪽 항
initial_owner_user_id    생성 시 기록.    쓰기만 있고 권한에 읽히지 않음 (실측)
local_user_id            레거시 upstream 호환 링크
```

출처를 `owner_user_id` 하나로 뭉쳐 말하면 **양도 가능한 현재 배정과 불변 출처가 섞인다.**
양도 가능한 필드는 정의상 출처가 아니다.

### 양방향 규칙 -- 방향이 반대다

```txt
귀속은 권한을 넓히지 않는다    원격 커뮤니티가 우리 Person 을 moderators 에 실어도
                               로컬 능력은 생기지 않는다
권한은 와이어에 나가지 않는다   managed accounts 를 공개하면 운영 권한이 샌다
```

하나는 **비공개 전용**, 하나는 **공개 전용**이다. 같은 테이블에 있으면 반드시 둘 중 하나가 샌다.
FEP-fe34 의 상호 주장 요구가 같은 이야기다 -- 원격 origin 의 "이 사람은 우리 mod 다" 는 귀속
주장이고, 그것을 로컬 권한으로 번역하면 남의 서버가 우리 권한을 발급하게 된다.

### 왜 개념화가 어려웠는가, 그리고 moderators 이식의 성패 기준

현재 코드에서 그 둘이 **한 행**이기 때문이다. `axismundi-forum/includes/moderators.php:123` 이
moderators 를 Forum memberships 테이블의 role 컬럼에 쓰고, `:147` 이 같은 사람에게 principal
`manager` 를 부여한다. 귀속과 권한이 같은 쓰기 경로에 있으니 갈라질 자리가 없었다.

> **성패 기준:** moderators 를 썼는데 principal 행이 같이 생기면 이식은 실패한 것이다.

### 미결 -- owner 의도와 현재 불변식이 어긋난다

소유자 의도: "is personal 이라도 관리자가 principal 일부를 제한하는 조치를 취할 수 있다."

그런데 `account-access.php:50-57` 의 마이그레이션 불변식은 **personal owner 인데
`role <> 'owner'` 이면 inconsistent** 로 세고, 주석이
`A conflicting pre-existing role needs repair, not silent promotion` 이다. 즉 personal owner 는
반드시 owner 역할이어야 한다. 역할을 낮추는 방식으로 제한하면 그 계정은 정의상 personal 이
아니게 되고 수리 대상이 된다. 그 제한을 하려면 **역할 아래 층위**가 필요한데 지금은 없다.

런타임 검사가 아니라 마이그레이션 불변식이라 오늘의 버그는 아니다. 의도가 들어갈 자리가 없다는
것이 미결이다.

## 불이익은 코어 스펙이 아니라 서버 규칙이다 (오너 결정, 2026-11-02)

AP/AS2 는 **무슨 일이 일어났는지 말할 어휘**를 준다. **누가 그 일을 해도 되는지는 주지 않는다.**
권한·제한·차단·정원·금지는 전부 서버 규칙 쪽이다. 확인된 사례:

```txt
Block           AP 는 전달 대상에서 빼라는 정도.  집행은 전적으로 서버 정책
                우리는 NON-STANDARD 로 표시하고 FEP-070c 를 근거로 삼았다
moderators      스펙에 없다.  Lemmy 의 verify_mod_action 은 서버 정책이고
                인용된 FEP-fe34 조차 DRAFT
```

**정책을 실은 속성도 여전히 정책이다.** `posting_restricted_to_mods` 가 와이어에 있다고 그
제한이 표준이 되지는 않는다 -- 우리 정책이 피어에게 읽히게 될 뿐이다. 기록해 둔
`declared willingness ≠ consent`(FEP-7aa9) 와 같은 족속이다.

그래서 우리 서버 규칙은 **우리 것으로 기록**해야지 준수인 척하면 안 된다.
5층 분류에서 FEP draft 는 *채택한 정책* 칸이고, 우리만의 규칙은 칸이 아예 없다 -- 그게 정상이다.

## 계정 양도와 이주는 다른 질문이다 (2026-11-02)

거울상이 아니라 **독립된 두 차원**이다. AP 에는 사람이 없다 -- `Move` 는 `object` 와 `target`
이 둘 다 actor URI 이고 사람은 등장하지 않는다. 즉 Move 는 **한 계정이 두 URI 에 걸쳐 연속임을
말하는 진술**이지 사람에 대한 진술이 아니다.

```txt
              URI 가 바뀐다    소유 user 가 바뀐다
Move          예               관여하지 않음
양도           아니다            예
```

둘 다 일어날 수 있고 순서도 자유다. 계정이 호스트를 옮긴 뒤 나중에 소유자가 바뀔 수 있고,
`personal_owner_user_id` 재배정은 `movedTo` 에 대해 할 말이 없다.

> 철회(2026-11-02): "Move 가 없으면 이사를 표현할 유일한 수단이 양도뿐" 이라고 적었던 것은
> 틀렸다. 양도는 URI 를 바꾸지 않으므로 애초에 이사를 표현할 수 없고, 아무도 그 용도로 양도를
> 집지 않는다. Move 의 근거는 원래 것 하나다 -- 팔로우한 원격 계정이 이사하면 relation 이 죽은
> actor 를 가리킨다(재현 미확인).

양도를 어디까지 허용할지는 **정책**이고, 모델은 양도 가능한 채로 두어야 한다. "primary account 는
user 에 귀속" 을 엔진에 박으면 한 관할의 정책을 엔진에 굽는 것이고, Mastodon 의 고정글 상한을
Actors 의 const 로 박은 것과 같은 실수가 된다.

다만 Person 양도와 Group 양도는 **행위의 성격이 다르다**. 법적 우려는 이 차이의 하류다.

```txt
Group 양도   공동의 것의 관리권이 넘어간다.  글은 애초에 커뮤니티의 것이었다
Person 양도  actor_uri 는 불변이고 outbox 가 거기에 귀속되므로
             한 사람이 쓴 글의 저자가 조용히 바뀐다
```

권고: Group/managed 양도는 통상대로 되게 하고, Person/personal 양도는 **기본 불허하되
하드코딩된 불가능이 아니라 서버 규칙으로** 둔다.

## 한쪽 주장은 사실이 아니다 -- 맞물림이 검증을 만든다 (2026-11-02)

같은 모양이 하루에 세 번 나왔다.

```txt
FEP-fe34      cross-origin 모더레이터는 양쪽 origin 의 상호 주장을 요구한다
moderators    Group 의 attributedTo 가 컬렉션을 지목하고
              그 컬렉션이 그 Person 을 싣는 양쪽이 있어야 "모더레이터" 가 검증된다
Move          Move 는 힌트이고, 양쪽 문서의 alsoKnownAs 가 서로를 가리켜야 확인된다
```

**한쪽만 있으면 주장이고, 맞물려야 사실이다.** 기록해 둔 `activity = hint, fetch = authority` 와
`declared willingness != consent` 의 같은 가족이며, 집행자를 쓸 때 기본값으로 삼을 것.
