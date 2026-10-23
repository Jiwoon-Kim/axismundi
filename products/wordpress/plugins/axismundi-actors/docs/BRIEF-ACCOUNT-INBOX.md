# Account/Principal 및 Actor Inbox 구현 브리프

2026-10-23. S1–S9를 구분하는 리뷰 기준이다. 완료 선언이 아니다.
Opus가 제공한 DB 행 수는 특정 wp-env 시점의 관측값이며 코드 상수·마이그레이션 전제로 쓰지 않는다.

## 유지할 제품 결정

- User는 WP User, 로컬 Account는 Actor와 같은 운영 엔터티다.
- User–Account 접근 관계는 다중 개인 Account와 공동 운영을 수용한다.
- Actors는 JMAP을 의존하지 않는다. JMAP은 내부 관계를 표준 형식으로 투영한다.
- Principal 주체에 User·로컬 Account·원격 Actor를 표현할 수 있게 하되, 등록만으로 권한이 생기지 않는다.
- internal Actor 기능은 폐기 대상이다. 이번 Inbox 구현이 해당 기능을 필요로 하거나 보장하지 않는다.
- 기존 identity_id, a{identity_id}, 발행 Actor URI는 유지한다.

## 명세와 구현을 구별할 것

[8620 §1.6](https://www.rfc-editor.org/rfc/rfc8620.html#section-1.6)은 User와 Account를 구별하고
다중 Account를 허용한다. 그러나 특정 legacy transport 컬럼의 UNIQUE 자체를 금지하지는 않는다.
문제는 그 컬럼을 전체 소유·접근 모델과 겸용하는 제품 구조다.
isPersonal은 User 기준의 결과지만 그것을 계산할 소유 관계 사실은 저장해야 한다.
owner라는 운영 role만으로 개인 귀속을 자동 판정하지 않는다.

[9670](https://www.rfc-editor.org/rfc/rfc9670.html)은 공유 주체와 구독 계약을 정의한다.
Principal 등록이 HTTP 요청자의 인증을 대신하지 않는다. 계정 접근과 Inbox 읽기·발행 권한도
무조건 같은 권한이라고 가정하지 않고 현재 및 목표 정책을 명시한다.

[AP §5.2](https://www.w3.org/TR/activitypub/#inbox)의 컬렉션·권한·중복 조건을 따른다.
서로 다른 Actor가 endpoint를 공유한다는 사실만으로 위반을 단정하지 않는다.
실제 GET의 Actor별 범위와 인가가 성립하는지 검증한다.

## 슬라이스

| 단계 | 구현·검증 목표 | 현재 상태 |
|---|---|---|
| S1 Principal | stable subject 식별·유형·존재 검증, 기존 User 관계 이전, remote 등록의 무권한성 | 대기 |
| S2 관계 통일 | 개인·위임 접근의 관계 모델 수렴, 개인 귀속과 운영 role 분리, 삭제·철회 정책 | 대기 |
| S3 transport | legacy 공식 plugin binding과 Account 접근 분리, Actor별 키 생성·저장·회전·서명 정책, **모더레이션 스코프** | 대기 |
| S4 UNIQUE | S3 검증 후 불필요한 제약 제거. legacy binding의 고유성은 별도 판단 | 대기 |
| S5 isPersonal | 인증 User와 관계로 계산. Session 기본값·지원·철회 회귀 검사 | 기존 부분 구현, 재검토 |
| S6 receipts | Activity와 원자적인 Actor별 receipt, 중복·실패·보수적 백필 | 구현·리뷰 완료 (3bc752c) |
| S7 Inbox | Actor UUID URL, Account 인가 GET, 공식 검증기 재사용 POST, 기존 수신 URL 유지 | 구현·리뷰 완료 (3bc752c) |
| S8 activity view | Account 범위 Activity 뷰·query·changes·읽음 상태. AP 문서와 분리. **mailbox 아님** | 대기 |
| S11 state | Account 단위 변경 알림 경로. S8 의 선결 | 대기 |
| S9 instance | shared Inbox 및 Application Actor 소유권 이관 | 대기 |
| S10 acting-actor | 암묵 폴백을 단일 해소기로. 선택 자체는 남김 (primaryAccounts) | 대기 |

S3는 컬럼을 옮기는 것만으로 완료되지 않는다. 추가 Person·Group이 Application 키를
계속 사용하는지 검사하고, 독립 키 및 회전을 실제 지원해야 한다.

**모더레이션 스코프도 S3 범위다.** `local_user_id` 겸용이 세 번째로 표면화한 자리다 --
키 선택, inbox URL, 그리고 수신 차단 판정. 현재 Actor Inbox 는
`Moderation::activity_is_blocked( $activity, $actor->get_local_user_id() ?? 0 )` 로
평가하므로, `local_user_id` 가 없는 Actor 는 수신 Actor 의 차단 목록이 아니라
사이트 범위(`0`)로 판정된다. 오늘은 Org·Group 이 그 상태이고, **S4 로 다중 Person 을
열면 추가 Person 들이 조용히 Actor 별 모더레이션을 잃는다.** S3 에서 수신 차단 판정이
Account 를 기준으로 서도록 함께 옮긴다. 이것은 이번 S6·S7 구현이 만든 결함이 아니라
기존 겸용이 드러난 것이다.
S6 백필은 audience에 남지 않은 route recipient나 과거 follower를 복원할 수 없다.
모르는 수신자를 전체 Actor로 확장하지 않으며 미복원 범위를 보고한다.

S8은 RFC 8621 Email/Mailbox 구현을 주장하지 않는다. Mailbox는 Account 안의 데이터 타입이며,
Activity를 Email로 가장해서 urn:ietf:params:jmap:mail을 선언하지 않는다.
읽음 상태는 원칙적으로 User/Principal–Account–Activity별이다. 공동 Account 사용자끼리
덮어쓰는 Account 공용 상태와 구분한다. 외부 노출 시 별도 vendor capability 계약이 필요하다.
현재는 그런 capability를 만들거나 광고하지 않는다.

**Actor 의 inbox·outbox 는 JMAP mailbox 가 아니다.** JMAP 의 `Mailbox` 는 Account 안의
Email 데이터 타입이고, Actor 의 inbox 는 ActivityPub 컬렉션이다. S8 이 만드는 것은
Account 범위의 Activity 뷰이지 Mailbox 가 아니며, 그 이름을 쓰지 않는다 -- 쓰면 장차
JMAP Mail 이 들어올 자리를 선점하고, 소비자가 `Email/*` 메서드를 가정한다.
워드프레스를 메일 서버로 만드는 것은 현재 계획에 없지만, **그 자리는 비워 둔다.**

## S11 변경 알림 경로

연합 수신은 JMAP 동기화 모델이 보지 못하는 외부 변경원이다. 원격 delivery 가 원장에
inbound 행과 receipt 를 남겨도 그 Account 의 `state` 가 오르지 않으면 `/changes` 가
조용히 누락하고, 클라이언트는 에러 없이 그 활동을 영원히 못 본다.

```txt
변경원   C2S 저작 · AP 수신 · 관리 화면 · 임포트
         → 전부 같은 지점을 통과해 Account 단위 state 를 증분
소비자   JMAP /changes, Push (RFC 8887 WebSocket / RFC 9749 VAPID)
AP 쪽    아무것도 선언하지 않는다. state 는 JMAP 개념이다.
```

이것은 프로토콜 사이의 번역기가 **아니다.** 두 프로토콜은 한 도메인 위의 어댑터이고,
필요한 결합은 번역이 아니라 변경 알림 하나다. S8 은 S11 없이는 동기화되지 않으므로
클라이언트가 폴링하게 된다.

## S10 acting-actor: 암묵 폴백을 단일 해소기로

Session 이 대체하는 것은 **목록 반쪽**이다. "내가 무엇으로 활동할 수 있는가"는
workspace/Session 이 답한다. **선택 반쪽**은 대체되지 않는다 -- JMAP 에는 전환 명령이
없고 요청마다 `accountId` 를 명시하므로 "현재 선택"이 서버에 필요 없지만, 우리
acting-actor 는 **발행 귀속**을 정하고 블록 에디터·관리 화면·REST 는 `accountId` 를
보내지 않는다.

소비자를 센 결과 5개 플러그인이다 (2026-10-23):

```txt
activities   c2s.php, publish-command.php
actors       acting-actor.php
calendar     admin-calendars, envelope, event-actor, event-participation,
             jscalendar, projection, schema
emoji        catalogue.php
```

### 저장된 선택은 없앨 수 없다

WordPress 관리 화면과 기본 프론트엔드는 모놀리식이라 **페이지 로드에 `accountId` 가
없다.** 그 표면들이 현재 세션의 Account 로 활동하려면 저장된 선택을 **적용**해야 한다.
우리 React 앱은 요청마다 `accountId` 를 명시할 수 있지만, 최초 로드의 기본값은
마찬가지로 필요하다.

스펙에 이미 그 개념이 있다. `primaryAccounts` 는 capability 별로 *"the account a user
should treat as their default for that capability"* 를 알려준다. 서버가 **기본값을
제공**하고 호출자가 명시하는 모양이다.

```txt
오늘        아무도 명시하지 않으면 서버가 조용히 적용   ← 깨지는 지점
바뀔 것     해소기 한 곳에서만 적용. 표면별 폴백 금지.
남을 것     사용자의 기본 Account 선택 자체.
            JMAP 은 primaryAccounts 로 노출하고,
            모놀리식 표면은 해소기를 통해 적용한다.
```

즉 S10 은 **폐기가 아니라 단일화**다. 없애는 것은 "표면마다 각자 폴백"이고,
남기는 것은 "세션의 기본 Account" 다.

```txt
해소기   명시된 accountId 가 있으면 그것 + 권한 검사
         없으면 세션 기본값 + 권한 검사
         철회되었으면 폴백 + 그 사실을 호출자가 알 수 있게
표면     반드시 해소기를 통과. 자체 폴백·자체 재검사 금지.
```

지킬 것:

```txt
공통 유지   권한 재검사와 철회 시 폴백은 공통 서비스 하나로 남긴다.
            표면마다 재구현하면 한 곳이 빼먹고, 그 곳이 잘못된 Actor 로 발행한다.
점진 이행   5개 플러그인 호출부를 한 커밋에 바꾸지 않는다. 표면 단위로.
권한 불변   발행 권한(handle lock)과 데이터 접근 권한을 이 과정에서 합치지 않는다.
순서        S1~S5 이후. 접근 관계가 확정되기 전에 소비자를 옮기면 두 번 옮긴다.
```

## 앱 라우트는 식별자가 아니다

이 제품은 클라이언트 앱이므로 그 라우팅 경로는 **reader 가 어떤 Object 를 요청하는가**일
뿐이고, Object 의 `id` 도 `url` 도 아니다. 앱 라우트는 자유롭게 바뀔 수 있고 어디에도
발행되지 않는다. 식별자는 서버가 소유한다 -- `id`, canonical endpoint, 읽기 API,
앱 라우트의 4층 분리는 `activitystreams-open-items.md` 가 소유한다.

S9의 shared Inbox는 Actor별 Inbox와 별개 최적화다. Application Actor와 자동으로
동일한 소유 엔터티라고 가정하지 않는다. 비공개 데이터를 public GET에 노출하지 않는다.

## 의존 및 검증

- S3 -> S4, S6 -> S7, S7 이후 S9를 검토한다.
- S1–S2와 S6–S7은 별도 변경 단위다. 동일 함수·스키마를 병행 수정할 때는 합류 후 재검증한다.
- read/write·서명 실패 주입, 마이그레이션 실패·재시도, URI 불변, 관계 철회, 권한 누출을 검사한다.
- wp-env fixture로 로컬 수신 경로·인가·rollback을 검증한다. 실제 peer 서명·전달·스테이징 데이터 검증과 구분한다.
- 순서: Codex 구현 -> Opus 리뷰 -> 빌드 -> 스테이징 검증. 이번 변경에서 배포하지 않는다.

S6–S7의 구현·검증·남은 한계는
[Actor Inbox 구현 기록](../../axismundi-activitypub-bridge/docs/IMPLEMENTATION-ACTOR-INBOX.md)을 참고한다.
