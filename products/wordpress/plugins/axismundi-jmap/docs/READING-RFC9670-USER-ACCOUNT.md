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

## `Principal.type` — CUTYPE 이 **유사한 선례**다 (파생은 아니다)

일반 공유 스펙에 있기엔 이상한 열거형인데, 모양을 설명해 주는 선례가 있다.

**과장하지 말 것.** RFC 9670 본문에 `5545`·iCalendar·CalDAV·CUTYPE 참조가 **0건**이다
(2026-11-16 전수 grep). 값이 대응한다는 사실만으로 파생을 단정할 수 없다. 아래는
**값 대응과 예시의 성격**이고, 그것이 설명하는 것은 "왜 이런 모양인가" 까지다.

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

#### 이 표는 **참고 자료**다 — 하나를 다른 쪽의 원본으로 삼지 않는다

세 필드는 서로 다른 질문에 답한다. 값이 겹친다고 같은 것이 아니다.

```txt
AS2 type             **Actor 문서**의 타입
JSContact kind       그 **연락처가 나타내는 대상**의 분류
JMAP Principal.type  **JMAP 디렉터리**의 분류 (query 필터용)
Account              이 분류를 **넣지 않는다**.  type 이 없다
```

**권한을 타입에서 유도하지 않는다.** Principal 이 `group` 이라는 이유로 무엇을 허용하거나
금지하지 않는다. 권한은 `shareWith` 와 `myRights` 가 답한다.

#### 두 매핑은 성격이 다르다 — 섞어 적지 말 것

```txt
Organization → kind "org"            **표현 매핑**.  JSContact 이 조직을 조직으로 적는다.
                                     형식이 그 값을 들고 있으므로 선택의 여지가 적다
Organization → Principal "group"     **제품 정책**.  JMAP 공유 디렉터리에서 조직을 group 으로
                                     검색되게 할 것인가.  RFC 가 정해 주지 않는다
```

정책 쪽 권고는 `group` 이다 — `other` 는 분류 포기라 `Principal/query` 필터에서 쓸모가 없다.
**다만 채택하면 그 값으로 원래 Actor 타입을 역산하지 않는다.** `group` 인 Principal 이
Group Actor 인지 Organization Actor 인지는 그 필드로 알 수 없고, 알아야 하면 Actor 를 본다.

같은 방향으로 `Service`·`Application` 은 JSContact 에서 `application` 이고 Principal 에서는
`other` 다. 역산 금지가 여기에도 걸린다.

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
**Actor 에서 Card 를 만들기 전에 매핑을 먼저 쓴다.** 그 매핑은 **변환 경계**에서 정하고,
Principal 의 Account 기반 키 전환과 **분리한다** — 둘은 서로 의지하지 않는다.

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
