# Brief — `primaryAccounts` 투영

> **범위**: 이미 있는 기본 계정 선호를 Session 에 반영한다. 저장 모델도 로그인 흐름도
> 바꾸지 않는다. `Todo/set` 과 독립이고 서로 기다리지 않는다.

## 지금 상태 (측정)

```txt
우리 Session   primaryAccounts: {}            ← 비어 있다
               a199 isPersonal=true + 비개인 Account 셋 → **한 번 로그인, 여러 Account** 는 이미 성립
저장소         axismundi_actors_default_account( user, purpose )  — user meta.  **이미 있다**
Stalwart       거의 모든 capability 에 primary 를 채운다 (2026-11-15 실측)
```

그러니 이 슬라이스는 **새 저장이 아니라 투영**이다.

## Actors 가 보관하고 JMAP 이 해석한다

Actors 쪽 소스가 경계를 이미 적어 뒀다.

```php
/** A default's purpose is a local domain key, not a protocol capability URI. */
/** User preference only: does not assert that a feature is implemented or enabled. */
```

```txt
Actors   사용자의 기본 계정 **선호**를 보관한다.  purpose 는 로컬 도메인 키다
JMAP     지원하는 capability 에 대해서만 그 선호를 해석한다.
         `capability URI → purpose` 매핑을 **명시적으로** 들고 있는다 — 유도하지 않는다
```

Principal 때와 같은 분할이다. 저장은 Actors, 프로토콜 표현은 JMAP.

구현은 이미 Account provider 등록의 `purpose` 로 이 매핑을 보관한다.
Todo는 `todo_poc`, Principal 디렉터리는 `principals_poc` 를 쓴다.
Session의 빈 맵은 내보낼 수 있는 기본값이 없다는 뜻이지, 투영이 없다는 뜻이 아니다.

## RFC 8620 §2 가 정한 것 (원문 확인)

```txt
키      "capability URIs (**as found in accountCapabilities**) to the account id"
        → 세션 수준 capabilities 가 아니라 **계정 수준 맵**의 키다
생략    "If no account being returned belongs to the user, or in any other way there is
        no appropriate way to determine a default account, there **MAY** be no entry
        for a particular URI, even though that capability is supported by the server"
        → **생략이 적합하다.**  억지로 채우지 않는다
core    "urn:ietf:params:jmap:core **SHOULD NOT** be present"
```

### 제외 목록에 `principals:owner` 도 넣는다

`urn:ietf:params:jmap:principals:owner` 는 `accountCapabilities` 에 나타나지만(우리 a199
실측) **데이터 capability 가 아니라 계정 표식**이다(§1.5.2 — 세션 capabilities 에는 나오지
않는다). 기본 계정을 물을 대상이 아니다.

독립 구현이 같은 판단을 한다 — Stalwart 는 `principals:owner` 를 광고하면서
`primaryAccounts` 에는 넣지 않는다(2026-11-15 실측).

```txt
제외   urn:ietf:params:jmap:core            RFC SHOULD NOT
       urn:ietf:params:jmap:principals:owner  계정 표식이다
```

## 넣는 조건 — 셋 다 만족할 때만

```txt
① 저장된 기본이 있다        axismundi_actors_default_account( user, purpose )
② 그 계정이 **이번 Session 의 accounts 에 있다**
③ 그 계정이 그 capability 를 **실제로 광고한다** (accountCapabilities 에 키가 있다)
```

③이 핵심이다. 저장된 값은 선호일 뿐 "그 기능이 켜져 있다" 를 주장하지 않는다(위 주석).
③ 없이 투영하면 Session 이 **못 쓰는 Account 를 기본으로 가리킨다.**

```txt
금지   설정이 없거나 권한이 회수됐을 때 **임의로 다른 계정을 고르는 것**.
      생략한다.  RFC 가 생략을 허용한다
금지   accountCapabilities 의 모든 키에 기계적으로 primary 를 다는 것.  제외 목록이 있다
```

## 이 값이 결정하지 않는 것

```txt
currentUserPrincipalId   다른 질문이다.  "요청자에 대응하는 **그** Principal" 이고
                         모호하면 null 이다.  **기본 계정에서 유도하지 않는다**
AP acting Actor          별개다.  JMAP Account 선택과 Actor 선택을 하나로 묶지 않는다
GUI 의 현재 선택          primaryAccounts 는 **초기 선택**으로만 쓴다
```

**사용자가 다른 계정으로 전환해도 저장된 기본값을 쓰지 않는다.** 기본값은 명시적 선호이고
세션 상태가 아니다. 거기에 "마지막에 쓴 계정" 을 반영하면 전환할 때마다 Session `state` 가
바뀌어 클라이언트가 재동기화한다.

## 감사

```txt
선택    같은 capability 를 가진 계정이 **둘** 있을 때, 저장된 쪽이 primary 로 나온다
        (하나뿐이면 어느 규칙이든 통과하므로 아무것도 재지 않는다)
사라짐   그 계정의 접근 권한을 없애면 그 항목이 **Session 에서 사라진다**
사라짐   그 계정에서 그 capability 를 끄면 그 항목이 사라진다 (②와 ③을 따로 잰다)
제외    core 와 principals:owner 가 **들어 있지 않다**
null    기본이 없으면 그 URI 에 항목이 없다.  **{} 가 아니라 그 키가 없는 것**이다
변이    ③ 검사를 지우면 감사가 실패해야 한다 — 지금은 그 단정 자체가 없다
```

마지막 줄이 완료 판정이다. 통과만 보고 끝내면 ③이 있는지 없는지 알 수 없다.

## 바꾸지 말 것

```txt
저장 모델     Actors 의 user meta 와 purpose 키.  건드리지 않는다
로그인 흐름    서버 연결 → 발견 → 로그인 → 계정 선택.  이 슬라이스는 마지막 단계의 초기값일 뿐이다
Session 구조   accounts · isPersonal 은 이미 맞다.  Stalwart 와 같은 모양이다
```
