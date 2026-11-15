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
Identity           → Actor
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
Message-ID         → Object URI.  전역 유일, 참조·스레딩의 기준
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

### 어긋나는 곳 하나 — Mailbox

```txt
JMAP    Mailbox 가 **Email**(객체)을 담는다.  EmailSubmission 은 Mailbox 에 들어가지 않는다
AP      inbox/outbox 는 **Activity** 의 컬렉션이다
```

그러니까 AP 의 "메일박스" 에 들어 있는 것은 객체가 아니라 **제출**이다. 그리고 그 결과로
**AP 에는 Mailbox 에 대응하는 타입이 없다.**

없는 것이 폴더가 아니라는 점이 중요하다. 없는 것은 **멤버십이 저장되고 변경 가능한 질의
가능 컨테이너**다. JMAP Mailbox 의 `role`(inbox/drafts/sent/trash)은 저장된 가변 상태인데,
우리 읽기 표면은 그 사실을 매번 **유도**한다. 우리가 Notifications 와 Object read surfaces
에서 반복해 다시 끌어내던 질문이 이 공백이다.

지금 결정하지 않는다. 다만 **"폴더가 필요하다" 가 아니라 "저장된 멤버십이 필요하다"** 로
질문을 고쳐 둔다.

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
Mailbox as folder      제품 요구가 생기기 전까지.  위의 질문만 바꿔 둔다
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
