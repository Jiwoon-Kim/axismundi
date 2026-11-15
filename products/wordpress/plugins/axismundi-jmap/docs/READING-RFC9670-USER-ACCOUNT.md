# 전수조사 — RFC 9670 에서 `user` 와 `account` 는 무엇인가

본문에서 직접 세고 읽었다(2026-11-16). 페이지 머리말·바닥글 제거 후.

```txt
Account / Accounts  대문자 38   소문자 7    총 45
User / Users        대문자  2   소문자 71   총 73
```

§1.2 가 대소문자를 **규범으로** 만든다 — *"The terms 'Principal' and 'ShareNotification'
(**with this specific capitalization**) are used to refer to the data types defined in this
document."*

```txt
Account   대문자는 RFC 8620 §1.6.2 의 **타입**이다.  id 가 있다
          소문자 7개는 전부 JSON 속성명 "accounts" 이거나 일반 산문
          (§6 의 "a transitory account compromise")
user      대문자 2개는 **문장 첫머리**일 뿐이다.  `User` 타입은 없고 `userId` 도 없다
          데이터 모델로 들어가는 유일한 다리가 §1.5.1 의 currentUserPrincipalId 다
```

**`user` 는 타입이 아니라 산문의 역할이다.** 이것이 전수조사의 첫 결론이다.

## `user` 의 다섯 가지 쓰임 (58개 문장 분류)

```txt
A 요청자          이 세션의 주체.  "the user fetching this object"(§1.5.1),
                 "the requesting user"(§4.1), "Accounts where the user has permission"
B 상대방 사람      공유의 다른 쪽.  **§2 가 결정적이다** —
                 "a Principal **representing** another user or their team"
                 → Principal 은 user 를 **대리**하지 user 가 아니다.  그리고 팀도 대리한다
C 화면 앞의 사람   "inform the user", "present the list of notifications to the user",
                 "the end user"(§5).  UX 주체
D 과금·남용 단위   "the maximum number of notifications it will store **for a user**"(§3),
                 "limiting ... **per user**"(§6), "a malicious user"(§6)
E 권한의 주어      "The user may fetch this TodoList"(§4.1 mayRead),
                 "The user may give this Principal permission"(§4.1 mayShareWith)
```

## 우리에게 의미 있는 다섯 가지

### ① Principal 은 user 를 **대리**한다 — 같은 것이 아니다

§2: *"a Principal representing another user or their team."* 그리고 §1.3: *"A Principal
represents an individual, team, or resource."* user 는 Principal 이 대리할 수 있는 것 중
하나일 뿐이다. 그래서 `user ⊂ Principal 이 대리하는 것들` 이지 `user = Principal` 이 아니다.

### ② 권한은 Principal 에 주고, 평가는 user 로 한다

이 비대칭이 본문 전체에 깔려 있다.

```txt
부여   shareWith 는 **Principal id → 권한** 맵이다 (§4)
서술   권한 설명은 전부 "**The user** may ..." 다 (§4.1 mayRead·mayWrite·mayAdmin)
결과   myRights 는 §4 가 "the set of permissions **the user** currently has" 로 정의한다
```

**이것이 앞서 기록한 §4 ↔ §4.1 긴장을 푼다.** §4.1 의 mayAdmin 이 "may see and modify the
`myRights` property" 라고 한 것은 느슨한 문장이다 — `myRights` 는 정의상 **요청자에게
해소된 결과**이고 저장된 필드가 아니다. 고칠 수 있는 것은 `shareWith` 다. 우리 서버가
`myRights` 를 계산하는 것은 §4 와 맞고, 그것은 우리 정책이자 §4 의 정의다.

### ③ "Account 는 user 의 것" 은 산문이고, 모델은 Principal 소유다

```txt
산문   §1.4 — "the Account belongs to the user"
모델   §1.5.2 — "that Account (and the data therein) is **owned by a Principal**"
```

같은 관계의 두 표현이고 **규범은 뒤쪽**이다. 산문을 모델로 옮기면 Account 소유를 WP 사용자에
묶게 된다. 우리 `principal_for_account()` 가 이미 actor 키로 맞게 돼 있다.

### ④ 한도와 남용 방어는 **user 단위**다

§3 과 §6 이 "per user" 로 말한다. 한 user 가 여러 Principal 로 행위할 수 있으므로, 알림
저장 한도·속도 제한은 **사람(자격증명)** 에 붙지 Principal 이나 Actor 에 붙지 않는다.

**우리 쪽 적용**: ShareNotification 이나 그에 해당하는 것을 만들 때 한도를 Actor 당으로 두면
Actor 를 늘려 우회할 수 있다. WP 사용자 당으로 둔다.

### ⑤ Principal 을 어떻게 만드는지는 **범위 밖**이다

§1.3: *"Managing the set of Principals within a system is out of scope for this
specification, as it is highly domain specific. It is likely to map directly from a
directory service or other user management system."*

그래서 Principal 을 `accountId` 로 키 잡는 우리 선택을 RFC 가 제약하지 않는다. 다만 §1.3 이
"디렉터리 서비스에서 직접 매핑될 것" 이라고 예상한다는 점은 적어 둔다 — 우리 디렉터리는
Actors 다.

## `Principal.type` 은 iCalendar 의 CUTYPE 이다

일반 공유 스펙에 있기엔 이상한 열거형인데, 출처가 있다.

```txt
RFC 5545 §3.2.3 CUTYPE   INDIVIDUAL / GROUP / RESOURCE / ROOM     / UNKNOWN
RFC 9670 §2  type        individual / group / resource / location / other
```

다섯 값이 1:1 이고 `ROOM → location`, `UNKNOWN → other` 로 소문자화했다. **Principal 은
일정 디렉터리 항목을 공유 대상으로 일반화한 것**이고, 그래서 같은 객체에 일정용 필드가
셋 있다 — `type`, `timeZone`, `email`(addr-spec).

본문의 예시도 전부 그쪽이다: *"a room or projector"*, *"a calendar representing a
location"*, *"Jane Doe" or "Room 4B"*.

```txt
기계적 용도   §2.4.1 의 Principal/query 필터.  "The type must be exactly as given to match"
            → "회의실만 보여줘" 가 이 필드의 일이다
그 밖        9670 은 클라이언트가 type 으로 무엇을 하라고 정하지 않는다
주의         §6 이 편집 가능·위조 가능 필드로 name 과 함께 type 을 든다
```

Stalwart 가 `urn:ietf:params:jmap:principals:availability` 를 광고하는 것도 같은 계보다
(2026-11-15 실측) — availability 는 free/busy 다.

### 같은 질문의 열거형이 **넷**이다 — 호환되지 않는다

```txt
                     individual  group  org    location  resource  device  application  catch-all
iCal CUTYPE §3.2.3   INDIVIDUAL  GROUP  —      ROOM      RESOURCE  —       —            UNKNOWN
JMAP Principal §2    individual  group  —      location  resource  —       —            other
JSContact kind §2.1.4 individual group  org    location  —         device  application  **없음**
AS2 Actor types      Person      Group  Organization  —   —       —       Service·Application  —
```

**셋 이상이 합의하는 값은 `individual` 과 `group` 뿐이다.** 나머지는 전부 갈린다.

```txt
org          JSContact 와 AS2 에만 있다.  JMAP·CUTYPE 에는 없다 → 거기서 **손실된다**
application  JSContact 와 AS2 에만 있다.  JMAP 에서는 other 로 간다
resource     CUTYPE·JMAP 에만.  JSContact 의 device 가 가장 가깝다
location     CUTYPE(ROOM)·JMAP·JSContact 에 있고 **AS2 Actor 타입에는 없다**
             (AS2 Place 는 객체이지 actor 가 아니다)
catch-all    CUTYPE UNKNOWN, JMAP other.  **JSContact 에는 없다**
```

#### JSContact 이 AS2 에 가장 가깝다 — 그래서 손실은 JMAP 쪽에서 난다

```txt
Person       → kind individual   → Principal individual
Group        → kind group        → Principal group
Organization → kind **org**      → Principal group 또는 other   ← **손실**
Service      → kind application  → Principal other              ← **손실**
Application  → kind application  → Principal other              ← **손실**
```

권고: Organization 은 Principal 쪽에서 **`group`** 으로 평탄화한다. `other` 는 분류 포기
값이라 `Principal/query` 필터에서 쓸모가 없고, CUTYPE 의 GROUP 이 "a group of individuals"
라 조직이 그 자리에 들어가는 것이 자연스럽다. **평탄화라는 사실을 같이 적는다** — 되돌릴 수
없다.

#### 측정된 함정 — `kind` 기본값이 `individual` 이다

JSContact 에는 catch-all 이 없고 §2.1.4 의 기본값이 `individual` 이다. 우리 코드는 그 기본값을
**실체화**한다.

```txt
cards.php:218-219   kind 가 없으면 'individual' 을 넣는다 (기본값과 일치 — 맞다)
admin.php:451       새 Card 는 kind: 'individual' 로 시작한다
Actor type → kind   **매핑이 저장소에 없다** (2026-11-16 grep)
```

그래서 Actor 에서 Card 를 만드는 경로가 생기면 **Group·Organization·Service 가 전부
"한 사람" 으로 기록된다.** 조용히 틀리고, 나중에 고치려면 어느 행이 진짜 개인인지 알 수 없다.
**Actor 에서 Card 를 만들기 전에 매핑을 먼저 쓴다.**

새 의미를 발명하지 말 것. 이 필드는 "사람인가 방인가" 를 묻는 자리이고 우리 Actor 타입에서
유도된다.

## 부수 확인

```txt
§1.3   "A Principal may be associated with **zero or more** Accounts" — 0 이 허용된다.
       Account 없는 Principal 이 정상이다 (공유 후보로만 존재하는 경우)
§1.5.2 Principal 이 소유하지 않는 Account 도 있다 — Principal 들 자신이 담긴 Account
```

## 쓰지 말 것

```txt
"user = Principal"           §2 가 representing 이라고 쓴다
"myRights 를 저장한다"        §4 가 요청자에게 해소된 값으로 정의한다
"Account 는 사용자의 것"       산문이다.  모델은 Principal 소유 (§1.5.2)
한도를 Actor 당으로            §3·§6 이 per user 다.  Actor 를 늘려 우회할 수 있다
대소문자를 임의로              §1.2 가 대문자를 타입 이름으로 규범화한다
```
