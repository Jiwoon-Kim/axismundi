# Design — 메일 스택에서 가져올 개념, 그리고 가져오지 않을 것

Mail 은 구현하지 않는다. 그런데 메일 스택은 **같은 문제를 40년 먼저 풀었고**, 우리가 C2S
설계에서 되풀어 온 구분들에 이름과 선례를 이미 갖고 있다. 이 문서는 그 지도다.

**이 문서가 하는 일과 하지 않는 일을 먼저 고정한다.** 유추는 **이름과 선례**를 준다.
요구사항을 주지 않는다. 여기 적힌 어떤 것도 ActivityPub 적합성에 대한 주장의 근거가 될 수
없고, FEP 본문에 "메일이 그렇게 한다" 를 근거로 쓰지 않는다.

## 두 층 — 이것이 핵심이다

```txt
RFC 5321 SMTP          전송.  누가 어디로 보내는가
RFC 5322 Message       형식.  메시지가 어떻게 생겼는가
ActivityPub            전송에 가깝다 — inbox/outbox, 배달, HTTP
ActivityStreams 2.0    형식에 가깝다 — EML 에 가깝다
```

둘을 뭉치는 것이 "프로토콜이 JSON 모양을 정한다" 는 범주 오류의 출처다. 우리가 AS2 Editor
(형식)와 C2S Composer(프로토콜)를 가른 것이 같은 분리이고, 이 유추가 그 결정을 뒷받침한다.

## RFC 6409 — C2S 와 S2S 의 분리는 메일에 이미 있다

**Message Submission 은 서버 간 중계와 다른 별도 프로토콜이다.** 클라이언트가 새 메시지를
제출하는 경로이고, 그것이 정확히 outbox 다. 그리고 §8 `Message Modifications` 가 제출
계층의 의무 목록이다.

```txt
§8.1 Add 'Sender'      MSA **MAY** add or replace, 보낸 이가 알려져 있고 From 에 없을 때.
                       놓는 주소가 유효함을 MUST 보장
§8.2 Add 'Date'        MSA **MAY** add if lacking, 또는 비적합한 Date 를 교정
§8.3 Add 'Message-ID'  MSA **SHOULD** add **or replace**, 없거나 문법이 유효하지 않으면
§8.4 Transfer Encode   MAY
§8.5 Sign the Message
```

조동사를 그대로 적는다 — MAY·SHOULD 이고 MUST 가 아니다. 선례는 "제출 서버가 **그렇게 해도
된다**" 이지 "그래야 한다" 가 아니다. 그래도 요점은 남는다:

```txt
Message-ID 를 서버가 발급      ↔ outbox 가 Object URI 를 발급한다 (§8.3 은 **replace** 까지 허용)
Date 를 서버가 채움            ↔ published 를 서버가 찍는다
Sender 를 서버가 놓음          ↔ **Sender 자리는 id 를 발급한 outbox 의 것**이다
                              (기록된 결정과 독립적으로 일치한다 — actor 속성이 아니다)
Sign the Message              ↔ 제출 시점의 서명·Object Integrity Proofs
```

### 가져오지 않는 것 — 다시 쓸 권한

§8 은 "Sites MAY modify submissions" 로 열리고, 바로 경고를 붙인다 — *"a paramount rule is
to limit such actions to remedies for specific problems that have clear solutions."*

우리 FEP-c3ab 규칙 1 은 참조된 객체의 내용·주소를 **고치지 못하게** 한다. 모순이 아니다.

```txt
6409    **새로** 제출되는 메시지.  제출 계층은 저자의 대리인이고, 빠진 것을 채우는 것이 맞다
c3ab    **이미 있는** 객체를 참조한다.  고치면 기록을 위조하는 것이다
```

그래서 우리는 **계층은 빌리고 다시 쓰기 권한은 빌리지 않는다.** 이 구분을 적어 두지 않으면
다음 사람이 6409 를 근거로 c3ab 규칙 1 을 느슨하게 만든다.

## 타입 대응 (RFC 8621 에서 필드명 확인)

```txt
Identity           → **Actor 객체 자체**.  §아래 Identity 절 참조
                     8621 의 "Permission to Send from an Address" 가 우리 acting Actor 인가와
                     handle lock 의 자리다
EmailSubmission    → Activity
                     id          (immutable; server-set)  ↔ Activity id 를 서버가 발급
                     identityId  (immutable)              ↔ actor.  **불변**
                     emailId     (immutable)              ↔ object.  **불변**
                     envelope    (immutable)              ↔ to/cc 주소지정
                     sendAt      (immutable; server-set)  ↔ 예약 발행
                     undoStatus                           ↔ Undo
                     threadId    (immutable; server-set)
Email              → Object
Message-ID         → Object URI.  전역 유일, 참조·스레딩의 기준.
                     **차이를 남긴다** — Message-ID 는 전역 식별자이고 GET 주소가 아니다.
                     AS2 객체 URI 는 연합 식별자이면서 **역참조할 주소가 될 수 있다**
JMAP Email id      → 우리 서버 지역 id.  Message-ID 가 아니다 → 이미 정한 id ≠ url 과 같다
In-Reply-To /
References         → inReplyTo
Thread             → 서버가 정한다.  §3 이 "same Thread is **not mandated** in this spec" 라고
                     명시한다.  AP 에 Thread 타입은 없고 inReplyTo·context 뿐이므로
                     우리 wp_ax_thread_edges 가 그 자리다
```

`identityId` 와 `emailId` 가 **불변**인 것이 눈여겨볼 지점이다. 제출의 행위자와 대상은 기록된
뒤 바뀌지 않는다 — 우리 "Activity 는 불변" 정책, 그리고 "principal 과 acting Actor 를 둘 다
명시하고 추론하지 않는다" 와 같은 모양이다.

### Mailbox — 타입은 없고, 개념 대응은 있다

**AS2/AP 에 `Mailbox` 타입은 없다.** 아래는 관념적 대응이고 타입 선언이 아니다.

```txt
JMAP Mailbox         ↔ 역할이 붙은 Collection.  AP 에서는 OrderedCollection
Mailbox.role         ↔ **Actor 의 어느 속성이 그 컬렉션을 가리키는가** — inbox, outbox,
                       followers, following, liked, shares.  필드가 아니라 **위치**다
Mailbox 멤버십        ↔ 컬렉션의 items.  저장돼 있다
담는 것              JMAP Mailbox 는 Email(객체)을 담고 EmailSubmission 은 담지 않는다.
                     AP 의 inbox/outbox 는 **Activity**(제출)를 담는다
파일링 동사           AP §6.6 Add, §6.7 Remove.  target 이 컬렉션이다 —
                     "이것을 그 컨테이너에 넣어라" 가 이미 동사로 있다
totalEmails          ↔ Collection 의 totalItems
```

그러니까 AP 의 "메일박스" 에 들어 있는 것은 객체가 아니라 제출이다. 그 차이는 남지만,
**컨테이너라는 개념 자체는 대응이 있다.**

병기를 제안하는 것은 별개의 선택지다 — FEP 로 `["OrderedCollection", "Mailbox"]` 같은
공동 타입 선언을 제안할 수 있다. **지금 없는 것이고, 제안이다.** 있는 것처럼 적지 말 것.

#### 약한 추측 — C2S API FEP 에 `Mailbox` 타입이 필요할 수 있다

**추측이다. 결정이 아니고, 아래 네 가지가 정리되기 전에는 제안서에 쓰지 않는다.**

출발점: JMAP 메서드 집합을 이식하면 `outbox/get,changes,query,queryChanges,set` 같은 것이
가능해지고, 그러려면 컬렉션이 **메서드로 주소지정되는 타입**이어야 한다.

##### ① 전제가 그대로는 성립하지 않는다 (측정함)

JMAP 은 메일박스 **내용**을 `Mailbox/query` 로 읽지 않는다.

```txt
Email/query    filter 에 inMailbox 를 받는다 (RFC 8621 §4.4 의 FilterCondition)
Mailbox/query  **Mailbox 를** 질의한다.  sortAsTree 로 parentId 트리 정렬 —
               컨테이너를 열거하는 메서드다
```

그래서 이식의 결과는 `outbox/query` 가 아니라 **`Activity/query` + 컬렉션 필터**다. 이것이
추측이 약한 첫 번째 이유이고, 동시에 읽기 쪽은 `Mailbox` 타입 없이도 된다는 뜻이다.

##### ② 그러면 `Mailbox` 타입이 값을 하는 지점은 좁다

컨테이너를 **레코드로 열거·관리**해야 할 때다. 앞 절의 네 가지와 같은 목록으로 수렴한다.

```txt
사용자 생성·중첩 컨테이너    name·parentId 에 해당하는 것이 필요할 때
컨테이너별 권리·구독         myRights·isSubscribed
unread 등 컨테이너 집계      totalItems 로는 부족할 때
옮길 수 있는 role           위치가 아니라 필드로 role 을 들어야 할 때
```

**넷 중 하나라도 제품 요구가 되는 순간** 타입이 값을 하고, 그 전에는 하지 않는다.

##### ③ `/set` 이 가장 깊은 충돌이다

AP 에서 **변경 자체가 기록**이다 — 컬렉션 멤버십을 바꾸는 것은 `Add`/`Remove` **활동**이고
그것이 원장에 남는다. JMAP `/set` 은 기록을 남기지 않는 메서드 호출이다.

```txt
순진한 이식    컬렉션을 바꾸는 길이 둘이 된다.  하나는 원장에 활동을 남기고 하나는 남기지 않는다
우리 결정      이미 같은 결론에 있다 — C2S 는 **outbox 제출 API 이고 저장 hook 이 아니다**
따라서        outbox/set 을 "컬렉션 멤버십 변경" 으로 정의하면 그 결정과 어긋난다.
              Add/Remove 가 이미 그 자리를 차지하고 있다
```

`Mailbox/set` 이 성립할 수 있는 범위는 **컨테이너 자신의 속성**(이름, 부모, 구독)이지
그 안의 멤버십이 아니다. 거기까지 좁히면 ②와 맞는다.

##### ④ 식별자가 둘이다

JMAP 메서드는 서버 지역 `Id` 위에 서 있고(`/get ids:[...]`), AP 컬렉션은 URI 로 주소지정된다.
타입을 세우면 **id 와 url 을 둘 다 들어야** 하고, 그것은 우리가 이미 정한 id ≠ url 과 같은
모양이다. 걸림돌이 아니라 결과다.

##### 무엇이 이 추측을 결론으로 바꾸는가

```txt
결론으로    ②의 넷 중 하나가 제품 요구가 된다.  그때 Mailbox 는 **컨테이너 관리 타입**으로
           제안되고, 읽기는 Activity/query + 필터로 남는다
기각으로    읽기는 필터로 충분하고, 쓰기는 Add/Remove 로 충분하고, 컨테이너가 프로토콜이
           정한 역할 컬렉션으로 끝난다.  그러면 타입은 불필요하다
쓰지 말 것   "JMAP 을 이식하려면 Mailbox 가 필요하다" — ①이 그것을 반증한다
```

이것이 우리가 이미 내린 결정과 맞는다 — 공유폴더는 Group 이 아니라
`OrderedCollection attributedTo Group` 이다. 폴더가 컬렉션이라는 그 결정이 여기서
메일 쪽 이름과 만난다.

#### 그래서 실제로 없는 것은 더 좁다

```txt
settable role    AP 의 role 은 **위치**다 — 어느 속성이 가리키는지.  바꿀 수 있는 필드가 아니다.
                 JMAP 은 role 을 Mailbox 의 속성으로 들고 있어서 옮길 수 있다
사용자 생성 컨테이너  JMAP Mailbox 는 name 과 parentId 를 갖는다 → 사용자가 "Projects/2026" 을
                 만든다.  AP 의 역할 컬렉션은 프로토콜이 정한 것이고, 임의로 이름 붙인
                 중첩 컨테이너를 정의하는 표준 패턴은 없다 (동사는 Add/Remove 로 있다)
컨테이너별 권리     myRights · isSubscribed.  AP core 에 없다.  RFC 9670 영역이다
unread           JMAP 의 unreadEmails.  AP 에 없고 우리 Notifications 가 그 자리다
```

**이 네 가지가 질문이고, "Mailbox 가 없다" 는 질문이 아니었다.** 특히 `role` 이 위치인지
필드인지가 실제 설계 분기다 — 위치면 컬렉션의 역할을 옮길 수 없고, 필드면 옮길 수 있다.

#### 기록: 타입의 부재에서 기능의 부재를 끌어낸 오류

초안의 "`Mailbox` 타입이 없다" 는 **맞았다.** 틀린 것은 거기서 끌어낸 결론이다 —
*"없는 것은 멤버십이 저장되고 변경 가능한 질의 가능 컨테이너다."* role 컬렉션이 바로 그
컨테이너이고, items 는 저장돼 있다. **타입 이름이 없다는 사실로 기능이 없다고 적었다.**

그 전 교정에서는 반대로 넘어가 "Mailbox 는 Collection 의 서브타입" 이라고 적었다. 그것도
사실이 아니다 — 대응은 **관념적**이고, 타입 병기는 아직 **제안**일 뿐이다.

판정 규칙 둘:

```txt
① 타입·이름의 부재 ≠ 기능의 부재.  개념이 다른 이름, 조립된 식별자, 상위 타입, 또는
   **위치**(어느 속성이 가리키는가)로 존재할 수 있는 경로를 먼저 확인한다
② 교정할 때 반대편으로 넘어가지 않는다.  "없다" 를 고칠 때 "있다" 로 적지 말고,
   **무엇이 어떤 형태로 있는지**를 적는다
```

같은 주에 ①을 세 번 어겼다 — `TodoList/changes` 가 `$type . '/changes'` 로 조립 등록되는데
리터럴 grep 으로 "없다" 고 한 것, 공개 Session 이 이미 구현됐는데 낡은 문서를 믿은 것,
그리고 이것이다.

## Identity — 우리에게는 선택 단계가 없다

이것은 우리 선호가 아니라 **inbox 가 어디 붙어 있느냐가 강제하는 결과**다.

```txt
JMAP   role 은 Mailbox 의 속성이고 §2 가 못박는다 — "a Mailbox MUST only have a single role,
       and there MUST NOT be two Mailboxes in the same account with the same role"
       → **inbox 는 Account 당 하나**
       그런데 Identity 는 한 Account 안에 여럿 둘 수 있다  → **N 발신 : 1 수신**
AP     §4.1 — "Actor objects MUST have ... inbox ... outbox"
       → **inbox 는 Actor 마다 있다**
```

그래서 한 Account 에 Actor 가 여럿이면 inbox 도 여럿이 되고, "Account 당 inbox 하나" 가
깨진다. **Actor 하나 = Account 하나가 강제되고, Identity 를 따로 고를 자리가 없다.**

메일에서 N:1 이 성립하는 이유도 같은 자리에서 나온다 — `Identity` 는 **From 주소**일 뿐
자기 수신 종점이 없다. AP Actor 는 수신 종점을 직접 들고 있으므로 "주소" 일 수 없고,
Account 에 상당한다. 그래서 대응이 `Identity → Actor 의 하위 필드` 가 아니라
**`Identity → Actor 객체 자체`** 다.

`sharedInbox` 가 반례처럼 보이지만 아니다 — 여러 Actor 가 배달 종점을 공유하는 **전달
최적화**(§7.1.3)이고, 수신자는 여전히 해당 Actor 들에게 분배해야 한다. 정체성을 합치지
않는다.

JMAP Mail 은 한 Account 안에 **여러 `Identity`** 를 둘 수 있다(보낼 수 있는 여러 주소).
우리 Actor 기반 Account 는 **이미 Actor 하나를 가리킨다.**

```txt
JMAP        Account 선택 + Identity 선택 = 두 단계
우리        **Actor Account 선택 한 번.**  그것이 발신 Actor 와 outbox 를 같이 정한다
구분        "자격증명 하나에 Actor 하나" 가 아니다 — 같은 자격증명으로 Person 과 Group 을
            둘 다 운영하면 Session 에 Account 가 둘 보인다 (2026-11-15 실측: 계정 4개)
거부        요청 본문의 actor 가 다른 Actor 를 가리키면 서버가 거부한다.  기존 계약이다
```

구현 결과: **`Identity` 데이터 타입을 만들 이유가 없고**, 제출에 `identityId` 를 따로 두면
`accountId` 와 중복된다. JMAP 의 두 단계가 우리에게는 한 단계로 접힌다.

혼동하기 쉬운 곳을 적어 둔다 — **발신 Actor 가 하나라는 것은 귀속이 하나라는 뜻이 아니다.**
권위는 `Create.actor` 이고 귀속 집합은 `attributedTo`(배열)다. 복수 귀속은 다른 축이다.

### 관측: 메일 서버의 Group 멤버십은 `Join` 이 아니다 (2026-11-21)

로컬 Stalwart 에서 alice 를 Group `team` 에 넣고 alice 의 Session 을 실측했다
(`products/axismundi-client/docs/FIXTURE-MAIL-PROBE.md` §다중 Account·다중 Identity).

```txt
가입 한 번으로 둘이 생긴다
  ①  team 의 Account 가 alice Session 에 따로 보인다 — 자기 inbox·sent 를 가진 데이터 컨테이너
  ②  alice **개인** Account 에도 From `team@example.org` Identity 가 자동으로 생긴다
결과  team@ 으로 보내는 길이 둘 — 개인 Account 에서(Sent 사본은 alice 쪽) · team Account 에서
     같은 주소가 서로 다른 두 Account 의 서로 다른 두 Identity 다 (RFC 8621 은 email 중복을 막지 않는다)
```

**②는 우리에게 표현 불가능하다.** `acct:team@host` 는 From 주소가 아니라 로컬 Actor 하나를 찾는
핸들이고, 위 절대로 발신 주소 = Actor 자체다. 다른 Actor 의 핸들을 자기 별칭으로 들 자리가 없다.
Team 이름으로 쓰려면 Team Actor 로 **전환해 Team 의 outbox 에 제출**한다. 로그인은 그대로이고
행위 주체(acting Actor)만 바뀐다. 개인 outbox 가 Team 명의로 발행하는 길은 Sender = id 를 발급하고
서명하는 outbox 라는 기존 결정이 막는다.

**①은 현행 멤버십(수락된 Follow)의 결과가 아니다.** Stalwart 의 Group 멤버는 공유 메일함을
**같이 운영하는 사람**이다. 지금 그 대응은 Actors 의 Account 운영 관계(owner/editor)다 — 그 관계가
Group Account 를 Session 에 올리고 위의 전환을 가능하게 한다. 아래 미결 절이 이것을 `Join` 멤버십으로
가져올지 다룬다.

```txt
운영자 (Actors 관리 관계)   Group Account 가 Session 에 보임 · Group 으로 전환해 발행     ← Stalwart 멤버
가입자 (Follow 멤버십)      자기 Actor 로 쓰고 Group 을 수신자로 지정 · Group 이 Announce
                          Group 의 Account·inbox 에 접근하지 않음 · Group 명의 발행 없음
```

가입자 쪽 와이어는 지금 **수락된 `Follow` = 멤버십**이다(Actors `DATA-MODEL.md`, Lemmy 에 Join 이
없어서). AS2 `Join`("the actor has joined the object", https://www.w3.org/TR/activitystreams-vocabulary/#dfn-join)
은 `TYPE-SUPPORT.md` 에서 미지원이다.

### 미결: Follow 멤버십과 Join 멤버십을 가른다 (소유자 방향, 2026-11-21)

```txt
Follow 멤버십   Lemmy 에서 온 것.  가입자가 자기 Actor 로 Create(Note, audience=Group) →
               Group 이 Announce(Create).  Group 명의 발행 없음.  현행
Join 멤버십     Axismundi Group 은 Group Actor 자체로 발행할 수 있다.  Group Account 가 멤버의
               Session 에 들어오고, 멤버는 그 Group outbox 에 직접 C2S 제출한다.  ← Stalwart ①
```

정하기 전에 답할 것:

1. **원격 Join.** `Join` 은 연합 활동이라 원격 Actor 도 보낸다. 원격 Actor 는 우리 Session 이
   없으니 Group outbox 에 C2S 할 수 없다. 원격 Join 수락이 주는 것은 무엇인가 — Follow 멤버십과
   같은가, 멤버 목록 등재 같은 다른 것인가.
2. **Actors 관리 관계(owner/editor)와의 관계.** 로컬 Join 멤버가 Group Account 를 받는다면
   Join 은 관리 관계의 **연합 표현**인가, 관리보다 아래의 **별도 역할**(Group 명의 발행은 되고
   Group 설정·모더레이션은 안 되는)인가.
3. **Session 에 Account 가 있다 ≠ 모든 권한.** Session 은 provider 가 capability 별로 준 것만
   광고한다(`IMPLEMENTATION-SESSION.md`). Stalwart 멤버는 Group inbox 도 읽지만, Join 멤버에게
   outbox 제출만 주고 inbox 읽기는 주지 않는 조합이 가능하다. 어느 쪽인가.

변하지 않는 것: Group 명의 발행은 **Group outbox 를 통해서만** 한다(Sender = id 를 발급하고
서명하는 outbox). 개인 outbox 가 Group 핸들을 별칭으로 드는 길(Stalwart ②)은 어느 안에서도 없다.
`Join` 을 받기 시작하면 Accept 가 권한 부여 지점이 되므로, 자동 Accept 정책을 그 권한 크기에 맞춘다.

다음 할 일 — **Mobilizon 선례 조사.** Group 에 `Join` 과 역할(member·moderator·administrator)을
쓰는 것으로 기억하지만 확인하지 않았다. 원문으로 확인할 것: Join/Accept 와이어, 역할의 표현과
전달, Group 명의 게시물의 `actor`·`attributedTo`, 원격 멤버가 받는 것(위 질문 1). 그룹 액터 모델
작업은 이 조사 뒤로 미룬다.

## Principal 은 user·account·identity 중 어느 것도 아니다 — Account **위**다

RFC 9670 §2 에서 확인한 것.

```txt
정의     "A Principal represents an individual, a group, a location (e.g., a room),
         a resource (e.g., a projector), or another entity"
역할     "Sharing in JMAP is generally configured by assigning rights to certain data
         within an Account to **other Principals**"  → **공유의 대상**이다
accounts "A map of Account id to Account object for each JMAP Account containing data
         for this Principal"  → Principal 이 Account 들을 **거느린다**
type     individual | group | resource | location | other
기타     name, description, email, timeZone, capabilities
```

그래서 층은 넷이다.

```txt
user       인증된 당사자.  JMAP 에 타입이 없다.  한 user 가 여러 Principal 에 접근한다
           §1.5.1 의 currentUserPrincipalId 가 user ↔ Principal 을 잇는다 — 둘은 같지 않다
principal  **엔티티**.  사람·팀·회의실·프로젝터.  디렉터리 항목이자 공유 대상.  Account 를 거느린다
account    한 종류의 데이터를 담는 컨테이너.  최대 한 Principal 이 소유한다 (§1.5.2)
identity   **Mail 전용**.  Mail Account 안의 From 주소.  §2 에 등장하지 않는다
```

### 우리 쪽 대응 — Actor 가 둘을 겸한다

```txt
Principal  ↔ Actor (엔티티·디렉터리·공유 대상).  type 도 가깝다 —
             individual↔Person, group↔Group.  AP 의 Organization·Service·Application 은
             other 쪽이고, resource·location 에 해당하는 Actor 타입은 없다
Identity   ↔ Actor (발신 주소).  앞 절 참조
Account    ↔ 그 Actor 의 데이터 컨테이너 (API 층)
```

**AP 의 Actor 는 JMAP 이 Principal 과 Identity 로 쪼갠 것을 하나로 들고 있다.** 연합
식별자와 inbox·outbox 를 같은 객체가 갖기 때문이다. 앞 절의 inbox 논증과 같은 뿌리다.

### 소유는 JMAP 플러그인에 있다 — Principal 은 `accountId` 기반으로 둔다

기록된 경계 그대로다 — *"Actors 는 Account 아이덴티티·개인 귀속·기존 운영 관계를 제공한다.
JMAP 은 별도 opaque Principal ID, WP 사용자/Account 연결, … 자원별 공유를 소유한다."*
**Principal 을 Actors 플러그인으로 올리지 않는다.**

필요한 것은 그보다 작다. Account 는 이미 `'a' . $identity_id` 이고
`axismundi_actors_workspace_options()` 에서 나온다 — **모든 Account 가 이미 Actor 하나**다.
그러니 Principal 을 `accountId` 로 키 잡으면 **엔티티 대응이 공짜로 따라온다.**

#### 측정: 이미 그렇게 돼 있다 (2026-11-16)

```txt
Principal id   'P' . wp_generate_uuid4() — **JMAP 이 발급하는 자체 opaque UUID**.
               기록된 경계의 "별도 opaque Principal ID" 그대로다
principals     (id, user_id UNIQUE, actor_id UNIQUE).  **두 매퍼가 이미 있다** —
               principal_for_user( int ) ∥ principal_for_account( string )
               주석: "The two identity domains intentionally allocate different Principal rows"
actor_id       Actors 의 identity_id 를 가리키는 **연결 컬럼**.  user_id 가 WP 를 가리키는 것과
               같은 역할이다.  소유권 누수가 아니라 링크다
소유           principals 테이블을 건드리는 것은 axismundi-jmap 뿐이다
```

소유자 축은 **이미 actor 키**다 — `principal_for_account()` 가 §1.5.2 의 `principalId` 를
낸다. 그러니 "갈아엎는다" 가 아니다.

```txt
남은 질문   **공유 후보 디렉터리**를 사용자로 둘 것인가 Actor 로 둘 것인가.
           지금은 methods.php:262 의 shareWith 검증이 user 키 Principal 만 받는다
권고        actor 기반 하나로.  소유자 축이 이미 actor 키라 "소유자는 shareWith 에 없다"
           검사가 같은 공간에서 돌고, Actor 셋을 가진 사람과는 그중 하나로 공유하는 것이
           AP 의미와 맞는다.  둘 다 두면 shareWith 한 맵에 정체성 체계 둘이 섞여 이중 부여가 된다
크기        호출부 3곳 + 감사.  스키마 변경 없음
시점        지금이 아니라 **다음 공유 표면 전**.  Todo 공유는 로컬 사용자 간이라 현재 동작이 맞다
```

#### 이름 충돌 — `principal` 이 두 개념이다

```txt
axismundi-actors   axismundi_actors_account_principals(), principal_kind,
                   principal_can_manage() → **이 Actor 를 대신해 행위할 수 있는 주체**
axismundi-jmap     RFC 9670 Principal → **공유 대상 엔티티**
```

"누가 이 Actor 로 행위할 수 있나" 와 "누구와 공유할 수 있나" 는 다른 질문이다. 같은 단어라
다음에 "principal 은 이미 있으니 재사용하자" 가 나온다. **재사용하지 않는다** — 둘을 잇는
자리가 생기면 그때 변환을 명시한다.

#### 앞서 "어긋남" 이라 적은 것의 정정

원격 Actor 와 공유할 수 없는 것은 **결함이 아니라 범위선**이다.

```txt
원격 Actor   우리 서버에 Account 가 없다 → JMAP Principal 도 없다.  그게 맞다
JMAP 공유     로컬이다.  accountId 가 있는 엔티티 사이의 ACL
연합 참여     AP 활동이 한다.  초대·RSVP 는 Calendar 가 활동으로 처리한다
```

둘을 섞으면 JMAP ACL 이 연합 권한을 주는 것처럼 보인다. 그 분리는 이미 기록돼 있다 —
수락은 목록 추가이지 권한 부여가 아니고, Federation 은 공식 ActivityPub 에 위임한다.

## 세 층의 등록 — 개념은 맞고, 우리 코드는 아직 둘을 뭉쳐 둔다

```txt
① AS2 어휘 등록        Video 는 어떤 타입이고 duration 은 어떤 값인가
                      → vocabulary-registry.php, vocabulary-context-registry.php
② API 데이터 타입 등록   LocalObject API 는 무엇을 조회·편집할 수 있는가
                      → axismundi_op_local_update_types() = Note, Video, Tombstone
③ Session capability   이 Actor Account 에 어떤 API 표면이 제공되는가
                      → axismundi/activitypub (소셜 프로토콜) ∥ axismundi/activitystreams
                        (CMS) ∥ axismundi/jmap.  **셋은 별개 capability 다**
```

ActivityPub 은 capability 그 자체가 아니라 **capability 가 광고할 수 있는 프로토콜 표면
하나**다. 그래서 세 가지를 따로 말할 수 있어야 한다 — 어떤 타입을 **안다**, 그 타입을
**편집할 수 있다**, 그 타입에 대한 `Create` 를 **제출할 수 있다**.

### 측정한 현재 상태 (2026-11-16)

```txt
①  별개로 존재한다.  어휘 레지스트리가 세 타입보다 훨씬 많이 안다
②  존재한다 — axismundi_op_local_update_types()
③  **JMAP 만 Session 으로 광고된다.**  activitypub·activitystreams 는 REST 네임스페이스이고
    광고되는 capability 가 아니다 → 그것이 Session Pre-FEP 의 자리다
②∥③ **같은 목록을 공유한다.**  c2s-local-object.php:53 이 Create 의 object.type 을
    local_update_types() 로 검사한다 — 편집 가능 타입과 제출 가능 타입이 한 함수다
```

마지막 줄이 **지금 버그는 아니고 이름 붙인 이음새다.** 둘이 갈리는 순간 — 편집은 못 하지만
제출은 되는 타입(uploadMedia 가 만든 `Video` 가 그 방향이다), 또는 편집은 되지만 연합하지
않는 타입 — 한 함수가 두 질문에 답하게 된다. 그때 나눈다. 미리 나누지 않는다.

## 이미 설계한 것의 JMAP 이름 둘

```txt
Email/import   블롭을 받아 보내지 **않고** Mailbox 에 Email 을 만든다
               ↔ **CMS 발행 경로**.  기존 객체를 원장에 기록하고 배달하지 않는다
               C2S.md §14 의 두 진입 경로 중 두 번째가 이것이고, 이름이 이미 있었다
Email/parse    블롭을 메시지로 해석해 보여주되 **기록하지 않는다**
               ↔ **Object Inspector**.  AP 클라이언트에 없다고 지적했던 그 표면
```

둘 다 우리가 따로 도달한 설계이고, JMAP 이 그것을 별도 메서드로 **이미 이름 지어 두었다.**
새 이름을 만들기 전에 이 목록을 본다.

## 가져오지 않는 것

```txt
Mail 자체              구현하지 않는다
사용자 생성 컨테이너    제품 요구가 생기기 전까지.  컨테이너 자체는 이미 있고, 네 질문으로 남긴다
Vacation Response      AP 유사물은 Follow 자동 Accept 정책이지만 지금 범위 밖
Search Snippets        파생되고 독립 주소가 없는 결과.  목록 사영 질문과 닿지만 별건
HTML Email Display     표현 문제이고 우리 renderer 경계와 다르다
```

## 쓰지 말 것

```txt
"메일이 그렇게 한다"       FEP 본문의 근거로 쓰지 않는다.  유추는 이름과 선례를 주고
                        요구사항을 주지 않는다
6409 으로 c3ab 완화       새 제출과 기존 객체 참조는 다른 경우다 (위 §가져오지 않는 것)
MUST 로 격상             §8 은 MAY·SHOULD 다.  인용할 때 조동사를 바꾸지 않는다
Thread 를 규범으로        §3 이 "not mandated" 라고 적었다
```
