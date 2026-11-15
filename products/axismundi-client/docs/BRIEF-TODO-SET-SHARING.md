# Brief 보충 — Todo 쓰기는 RFC 9670(JMAP Sharing)과 같이 구현한다

[BRIEF-TODO-SET.md](BRIEF-TODO-SET.md) 가 `/set` 의 **오류 층**을 다룬다. 이 문서는 그 위에
얹히는 **공유 모델**이다. 둘을 따로 하면 안 되는 이유는 단순하다 — `TodoList` 의
`isSubscribed`·`myRights`·`shareWith` 는 Todo 가 발명한 속성이 아니라 **RFC 9670 §4 가 공유
가능한 타입에 MUST 로 요구하는 세 속성**이고, 우리 서버는 이미 그 셋을 들고 있다.

## §4 — 세 속성의 정의 (본문 확인)

```txt
isSubscribed  Boolean.  권한이 아니라 **의사** 다.  공유받았을 때의 초기값은 구현에 달렸다
myRights      String[Boolean].  현재 사용자가 가진 권한.  키 이름은 타입별로 정의한다
shareWith     Id[String[Boolean]]|null.  Principal id → 줄 권한.
              **Account 를 소유한 Principal 은 이 맵에 있어서는 안 된다**(MUST NOT) —
              소유자의 권한은 암묵적이다
```

마지막 줄이 클라이언트 쪽 함정이다. 공유 편집 화면이 "현재 공유 대상" 을 그릴 때 **소유자를
목록에 넣으면 안 되고**, 사용자가 소유자를 추가하려 하면 서버가 거부하는 것이 정상이다.
그것을 버그로 적지 말 것.

`shareWith` 의 Principal id 가 **어느 Account 에 있는지**는 그 객체가 든 Account 의
capabilities 에서 찾는다(§1.5.2). 추측하지 않는다.

## §1.5 — 클라이언트가 추측하면 안 되는 두 가지

```txt
urn:ietf:params:jmap:principals
    accountCapabilities 의 값이 currentUserPrincipalId 를 **MUST 로 담는다**
    → 클라이언트는 "내가 누구인가" 를 **사용자명이 아니라 여기서** 읽는다

urn:ietf:params:jmap:principals:owner
    accountCapabilities 의 키로만 쓰이고 **세션 capabilities 에는 나오지 않는다**
    값: accountIdForPrincipal, principalId
    → Account 를 소유한 Principal 을 따라가는 경로가 명시돼 있다.  조립하지 않는다
    키가 없는 Account 도 있다(Principal 자신들이 든 Account 등) — 없음을 결함으로 읽지 말 것
```

우리 Session 과 Stalwart Session **양쪽에** `principals` 와 `principals:owner` 가 떠 있다
(2026-11-15 실측). 그래서 이 축은 Todo 와 달리 **상호운용 지반이 될 수 있다.**

## §1.4 — "Session 에 없다" 를 "권한 없다" 로 읽지 않는다

```txt
MUST  Session 의 accounts 에는 사용자가 **그 Account 의 레코드 하나 이상을 구독했거나**
      Account 가 사용자 소유인 경우만 들어간다
따라서 접근 권한은 있으나 미구독인 Account 는 **Session 에 나타나지 않는다**
발견   Principal/query 가 접근 가능한 Principal 집합을 주는 경로다 (§2.4)
SHOULD StateChange 는 구독한 데이터에만 보낸다
MAY   권한이 있어도 서버가 구독을 거부할 수 있다(장소를 나타내는 캘린더 등)
```

이것이 UI 를 둘로 가른다 — **구독한 것을 읽는 화면**과 **접근 가능한 전부를 훑어 구독을
관리하는 화면**. RFC 가 직접 그 분리를 권한다.

우리 Todo PoC 문서가 적어 둔 "미구독 공유 Account 는 Principal·관리 UI 에서 발견 가능하지만
Session 의 공유 Account 목록에는 구독 후 들어간다" 가 **이 MUST 와 일치한다.** 우리 서버는
여기서 적합하다.

## 우리 서버의 실제 공백 — 기록하고, 실패로 적지 않는다

Todo PoC 문서가 명시한다: **`Principal/set`·`Principal/query`·ShareNotification 은
미구현**이고, 변경된 디렉터리에는 `cannotCalculateChanges` 로 전체 재조회를 요구한다.

```txt
결과  RFC 9670 이 이름 붙인 **발견 경로(Principal/query)가 우리 쪽에 없다**
      → "접근 가능한 전부를 훑는 화면" 을 우리 서버만으로는 만들 수 없다
클라이언트  미구현 메서드는 unknownMethod 로 온다.  **capability 공백으로 분류**하고
      전송 실패나 우리 클라이언트의 결함으로 적지 않는다
Stalwart  principals 를 광고하므로 Principal/query 가 거기서는 돌 수 있다 —
      그러면 그것은 **우리 서버의 공백**이라는 측정이 된다.  재서 적는다
```

## 작업 — `/set` 브리프의 ③에 더하는 판정

```txt
공유 쓰기        TodoList/set 의 update 에 shareWith 를 넣는다.
                대상 Principal id 는 **Principal/get 으로 받은 것만** 쓴다.
                Todo PoC 정책: 이메일·Actor URI·WP 사용자 ID 를 증명으로 받지 않는다
소유자 추가       shareWith 에 Account 소유 Principal 을 넣는다 → §4 의 MUST NOT 에 따라 거부.
                거부되는 것이 정상이고, **항목 수준인지 메서드 수준인지 재서** 적는다
isSubscribed    읽기 전용 공유자로 isSubscribed 만 바꾼다 → 허용되어야 한다.
                같은 사용자가 name 을 바꾸면 거부되어야 한다.  **두 쓰기를 한 배치에** 넣어
                호출마다 권리를 다시 확인하는지 본다
myRights 쓰기    거부.  §4 가 "현재 사용자가 가진 권한" 으로 정의한 **서버 계산값**이다
currentUserPrincipalId  Session 에서 읽어 Principal/get 으로 자기 자신을 조회한다.
                사용자명에서 유도하지 않는다
```

## 바꾸지 말 것

```txt
소유자는 shareWith 에 없다        MUST NOT.  UI 가 그려서도 안 되고 보내서도 안 된다
권한은 서버가 계산한다            myRights 를 클라이언트가 추론하지 않는다
Session 부재 ≠ 권한 부재          §1.4.  구독 화면과 탐색 화면을 하나로 합치지 않는다
미구현은 공백이고 결함이 아니다     unknownMethod 를 우리 클라이언트의 실패로 적지 않는다
```
