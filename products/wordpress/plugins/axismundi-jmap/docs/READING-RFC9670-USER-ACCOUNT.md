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

## `Principal.type` — 채워야 하는 분류 칸 하나

§2 가 다섯 값 중 하나를 **MUST** 로 요구한다: `individual` · `group` · `resource` ·
`location` · `other`. 기계적 용도는 하나다 — §2.4.1 의 `Principal/query` 필터,
*"The type must be exactly as given to match"*. **"회의실만 보여줘"** 가 이 필드의 일이다.
그 밖에 클라이언트가 무엇을 하라는 규정은 없고, §6 은 `name` 과 함께 편집·위조 가능 필드로
꼽는다.

모양이 일정 쪽인 것은 선례가 있어 보인다 — iCal CUTYPE(§3.2.3)이 값 다섯 개를 거의 같이
들고 있고, 9670 의 예시도 *"a room or projector"*, *"Room 4B"* 다. 다만 RFC 9670 본문에
`5545`·iCalendar·CalDAV·CUTYPE 참조가 **0건**이므로(2026-11-16 전수 grep) 파생이라고
단정하지 않는다. **설명이 되는 선례까지**다.

## 비슷한 어휘를 쓰는 분류가 셋 있다 — **같은 필드가 아니다**

```txt
AS2 Actor type        Actor **문서**의 타입.  Person · Group · Organization · Service · Application
JSContact kind §2.1.4 그 **연락처가 나타내는 대상**.  individual · group · org · location ·
                      device · application.  catch-all 이 없고 기본값이 individual 이다
JMAP Principal.type   **JMAP 디렉터리**의 분류.  위 다섯 값.  query 필터용이다
Account               분류가 **없다**.  name · isPersonal · isReadOnly · accountCapabilities
```

어휘가 겹치지만 **서로를 대체할 수 있는 연산이 없다.** 한쪽 값을 다른 쪽의 근거로 쓰지
않는다. 그래서 이 문서는 **매핑표를 두지 않는다** — 값을 나란히 늘어놓으면 변환이 있는 것처럼
읽히고, 없는 "손실" 이 보이기 시작한다.

```txt
쓰지 말 것   "Actor type → kind → Principal.type" 같은 파생 연쇄.  그런 경로는 없다
             한쪽 값에서 다른 쪽을 **역산**하는 것.  group 인 Principal 이 Group 인지
             Organization 인지는 그 필드로 알 수 없다.  알아야 하면 Actor 를 본다
             **권한을 타입에서 유도하는 것.**  group 이라서 무엇을 허용하지 않는다 —
             권한은 shareWith 와 myRights 가 답한다
쓸 수 있는 것  나중에 동기화하거나 새 레코드를 만들 때의 **참조값**.  편의이고 규칙이 아니다
```

### 값을 적어야 하는 자리는 그 자리에서 정한다

각 필드는 **자기 목적에 맞는 값**을 갖는다. 다른 필드를 보고 결정하지 않는다.

```txt
Principal.type   "이 디렉터리 항목이 필터에서 어떻게 잡혀야 하나"
JSContact kind   "이 Card 가 무엇을 나타내나"
AS2 type         Actor 문서가 선언하는 것.  이미 정해져 있다
```

조직을 디렉터리에서 `group` 으로 잡히게 할지 `other` 로 둘지는 **제품 정책**이고 RFC 가
정해 주지 않는다. 정하면 그 이유를 적되, 그것을 Actor 타입의 번역이라고 부르지 않는다.

### 코드에 실제로 걸리는 것 하나 — `kind` 기본값

위의 어떤 것과도 무관하게, 이것만은 지금 위험하다. JSContact §2.1.4 는 catch-all 이 없고
기본값이 `individual` 이다.

```txt
cards.php:218-219   kind 가 없으면 'individual' 을 넣는다 (기본값과 일치 — 맞다)
admin.php:451       새 Card 는 kind: 'individual' 로 시작한다
```

그래서 **Actor 에서 Card 를 만드는 경로가 생기면 `kind` 를 반드시 명시해야 한다.** 빠뜨리면
Group·Organization·Service 가 전부 "한 사람" 으로 기록되고, 조용히 틀리고, 나중에는 어느
행이 진짜 개인인지 구별할 수 없다.

이 규칙은 **그 변환 경계 하나에만** 걸린다. Principal 의 Account 기반 키 전환과 분리하고,
위의 분류 논의와도 분리한다 — 세 가지가 서로 기다릴 이유가 없다.

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
