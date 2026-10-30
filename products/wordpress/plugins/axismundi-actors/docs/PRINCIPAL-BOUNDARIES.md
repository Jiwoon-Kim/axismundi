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

표준 참고: [RFC 9670 §1.3](https://jmap.io/spec/rfc9670/#section-1.3),
[Principal §2](https://jmap.io/spec/rfc9670/#section-2).
