# Brief — user 키 Principal 을 걷어내고 account 하나로 모은다

## 왜 지금인가 — 측정된 근거 (2026-11-16)

`principals` 테이블은 **두 식별자 공간**을 동시에 들고 있다.

```txt
principal_for_account()   actor_id 키.  §1.5.2 의 principalId.  **소유자 축**
principal_for_user()      user_id 키.   공유 후보 디렉터리.  **공유 축**
소스 주석                  "The two identity domains intentionally allocate different Principal rows"
```

둘이 서로소라서 **§4 의 소유자 배제가 죽은 코드다.**

```php
// validate_shares(), methods.php:262
if ( ! $row || empty( $row['user_id'] ) || ! get_userdata( (int) $row['user_id'] )
     || (string) $id === principal_for_account( $account ) || ... ) throw;
```

앞 조건이 **user 키만 통과시키는데**, 비교 대상은 **actor 키**다. 그 등식은 참이 될 수 없다.

```txt
변이   `|| (string) $id === principal_for_account( $account )` 를 통째로 삭제
결과   **180 checks 그대로 통과**.  도달 불가능하고 시험되지도 않는다
```

문자 그대로의 MUST NOT 위반은 아니다 — 소유 Principal 과 운영자의 user Principal 은 다른
행이다. 그러나 **그 규칙이 막으려던 일이 가능하다**: 암묵적 권한을 이미 가진 사람에게
명시적 권한을 또 줘서, 같은 사람의 접근 기록이 둘로 갈린다.

### 그리고 Group 과 공유할 수 없다

`shareWith` 가 `user_id` 를 요구하므로 **Account 를 가진 Group·Organization Actor 가 공유
대상이 될 수 없다.** 실측: `isPersonal=false` 인 Account 가 셋 있다.

## 목표

```txt
하나의 공간   Principal 은 accountId 로만 키 잡는다 (actor_id).
             principal_for_account() 가 유일한 생성 경로가 된다
결과         소유자 배제가 **같은 공간에서** 돌아 실제로 작동한다
             Group Actor 가 공유 대상이 된다
            "누구와 공유했는가" 가 Actor 하나로 읽힌다
유지         Principal id 는 지금처럼 JMAP 이 발급하는 'P' + uuid.  바꾸지 않는다
            소유 경계도 그대로 — Actors 는 Account 아이덴티티만 제공한다
```

## 먼저 답할 것 — Actor 가 없는 사용자

`axismundi_actors_accounts_for_user()` 는 Actor 가 없으면 빈 배열을 낸다. 그런 사용자는
account 키 세계에서 Principal 이 없다.

**답은 스펙에 있다.** §1.5.1 의 `currentUserPrincipalId` 는 타입이 **`Id|null`** 이다.

```txt
규칙   그 Account 의 Principal 디렉터리에서 **요청 사용자에 대응하는** Principal 의 id.
      없으면 **null**
아니다  그 Account 를 **소유한** Principal 이 아니다 — 그건 principals:owner 가 답한다
의미   Actor 가 없는 사람은 공유 대상이 아니다.  정체성이 없으면 공유할 자리가 없다
금지   그 사람을 위해 Actor 를 자동 생성하는 것.  정체성 발급은 Actors 의 결정이고
      공유 기능이 끌어낼 일이 아니다
```

**두 질문을 섞지 말 것 — 권한 결과가 다르다.**

```txt
누가 요청했나        currentUserPrincipalId
누구를 대신해 행위하나  Actors 의 account principal.  다른 개념이다 (§이름 충돌)
```

§2.3 이 그 id 를 **쓰기 핸들**로 쓴다 — *"a server SHOULD allow an update to the `name`,
`description`, and `timeZone` properties of the Principal with **the same id as the
`currentUserPrincipalId`** ... This allows the user to update their own details."*

그래서 Alice 가 어떤 Account 를 운영한다는 이유로 그 Account 의 소유 Principal 을 거기
넣으면, Alice 가 **자기 정보를 고치는 경로로 남의 디렉터리 레코드를 고치게 된다.**

#### 층을 섞지 말 것 — 편집 대상이 둘이고 시스템이 다르다

```txt
AS2 Actor 객체    정체성 문서다.  type: Person|Group|... 이 여기 있다.
                 편집은 **ActivityPub 쪽** 일이고, 그 Actor 를 대신해 행위할 수 있는
                 주체(Actors 의 account principal)가 권한을 갖는다
JMAP Principal   디렉터리 레코드다.  §2.3 이 name·description·timeZone 을 열어 준다.
                 type: individual|group|resource|location|other 는 **Principal 의 속성**이다
Account          **type 이 없다.**  name·isPersonal·isReadOnly·accountCapabilities 뿐이다
                 (RFC 8620 §1.6.2, 그리고 우리 Session 실측).  "Group Account" 라는 것은 없다
```

그러니 §2.3 의 위험은 "Group 이 개명된다" 가 아니다 — 그건 AP 쪽 필드이고 다른 경로다.
위험은 **JMAP 디렉터리 레코드를 자기 것인 양 고치는 것**이다.

**다만 경로가 생길 수 있다.** `Principal.name` 을 Actor 의 표시 이름에서 **투영**하면,
§2.3 의 자기 편집이 뒷문으로 정체성 편집이 된다. 투영할지 말지는 이 브리프의 결정이 아니고,
**투영한다면 §2.3 쓰기를 막아야 한다**는 것만 적어 둔다.

#### 미결 — 개인 Actor 가 여럿이면 어느 것인가

§1.5.1 은 단수로 말한다("the Principal ... that corresponds to the user"). 지금은 문제가
되지 않는다 — 실측상 개인 Account 가 자기 디렉터리이고(`accountIdForPrincipal: a199`),
비개인 Account 들은 `principals` capability 자체가 없다. 그러나 코드에 이미
`directory_account()` 가 있어 **공유 디렉터리 하나로 가는 경로가 열려 있다.** 그때 개인
Actor 가 둘 이상인 사용자의 답을 정해야 한다.

```txt
선택지   ① 요청의 acting Actor 를 따른다 — 그런데 Session 은 acting 맥락이 없다
        ② 지정된 primary 개인 Actor 하나
        ③ 모호하면 null
정하기 전  구현하지 않는다.  틀린 답을 내보내면 §2.3 의 쓰기 핸들이 틀린 곳을 가리킨다
```

### 같이 고친다 — `currentUserPrincipalId` 가 **아예 없다**

Session 은 Account 를 나열하고 Principal 을 나열하지 않는다. 다리는 `accountCapabilities`
안에 둘이다.

```txt
principals         이 Account 가 Principal 객체를 담는다 +
                   §1.5.1 "an object that **MUST contain** ... currentUserPrincipalId"
principals:owner   이 Account 를 소유한 Principal(principalId)과 그것을 가져올
                   Account(accountIdForPrincipal)
```

실측(2026-11-16, Application Password 로 받은 Session 원문):

```txt
principals:owner  {"accountIdForPrincipal":"a199","principalId":"P99136e91-..."}   정확하다
principals        **{}**                                                           MUST 위반
저장소 전체        currentUserPrincipalId grep **0건** — 어디서도 내보내지 않는다
결과              클라이언트가 우리 Session 에서 **자기 Principal 을 알 수 없다**
```

세션 수준 `capabilities` 의 값이 빈 객체인 것은 §1.5.1 이 그렇게 정한 것이라 맞다. **Account
수준만 내용이 있어야 한다.** 고칠 자리는 `principals` account-capability **provider** 다 —
`modules/rfc8620/session.php:101` 이 provider 의 `properties` 를 그대로 싣는다.

**이 작업과 분리하지 않는다.** 그 필드의 *값*이 바로 여기서 바뀌는 것이기 때문이다.

```txt
지금 고치면   값이 "그 사용자의 user Principal" 이 된다 → 걷어낼 때 또 바꾼다.  마이그레이션 두 번
같이 하면     값이 처음부터 "요청자가 행위할 수 있으면 그 Account 의 Actor Principal,
             아니면 null" 이다
```

곁들여 적어 둔다: `primaryAccounts` 가 `{}` 다. 구조상 합법이지만 클라이언트가 principals
Account 를 찾으려면 전부 훑어야 한다. Stalwart 는 채운다. **이번 범위는 아니다.**

## 작업

### ① 생성 경로를 하나로

```txt
삭제   principal_for_user()
       store.php:155 의 전 WP 사용자 일괄 등록
       runtime.php:165 register_user() 훅
남김   principal_for_account()
```

### ② `validate_shares()` 를 같은 공간에서 돌린다

```txt
받는다  actor_id 가 있는 Principal 행만
검사    $id === principal_for_account( $account ) → 거부.  **이제 도달 가능하다**
추가    해당 Actor 가 살아 있는지 (live_account 상당)
```

### ③ 디렉터리

`principal_objects( int $user )` 가 내는 후보를 **Actor 기반**으로 바꾼다. 어떤 Actor 를
후보로 내줄지는 정책이고, 지금의 "사이트 전체 사용자" 를 그대로 옮기지 않는다 — PoC 정책이
표준 경로가 아니라고 이미 적혀 있다. §4.1 의 Principal capability(`accountId`,
`mayShareWith`)가 표준 경로다.

### ④ 생명주기 훅

```txt
지금   register_user / delete_user — **WP 사용자** 생명주기에 붙어 있다
바꾼다  Actor 생명주기로.  Actor 가 은퇴하면 그 Principal 을 공유 맵에서 걷어낸다
주의   **WP 사용자 삭제로 Actor 를 정리하지 않는다** — 기록된 제약이다.
       delete_user 가 공유를 쓸어내는 현재 동작은 그 제약과 같은 방향이 아니다
```

### ⑤ 데이터

기존 `shareWith` 행은 user 키 Principal 을 가리킨다. PoC 저장소이므로 **버릴지 옮길지는
소유자 판단**이다. 옮긴다면 "그 사용자의 personal Actor 로" 가 유일하게 말이 되는 매핑이고,
Actor 가 없는 사용자의 공유는 버려진다.

`user_id` 컬럼도 같이 뗄지는 ⑤의 답에 딸린다. 코드 경로만 지우고 컬럼을 남기면
**그 자체가 다음 드리프트**다.

## 감사 — 지금 불가능했던 것을 단정한다

```txt
필수   소유자 배제가 **실제로 발동**한다.  Account 소유 Actor 의 Principal 을 shareWith 에
      넣으면 거부된다.  지금은 이 단정을 쓸 수 없다 — 그 id 가 받아들여지지도 않는다
필수   **Group Actor 와 공유된다.**  isPersonal=false 인 Account 의 Principal 이 유효한
      공유 대상이다.  지금은 불가능하다
필수   principals 의 accountCapabilities 값에 currentUserPrincipalId 가 **존재한다**.
      지금은 키 자체가 없어서 이 단정을 쓸 수 없다
필수   Actor 없는 사용자의 currentUserPrincipalId 가 **null** 이다 (부재가 아니라 null)
필수   **운영할 수 있다는 것이 자신의 Principal 을 만들지 않는다.**
      요청자가 대신 행위할 수 있는 비개인 Account 에서
      currentUserPrincipalId 가 principals:owner 의 principalId 와 같으면 실패다 —
      §2.3 의 쓰기 핸들이 남의 레코드를 가리킨다는 뜻이다
변이   배제 절을 지우면 감사가 **실패해야 한다**.  지금은 실패하지 않는다 —
      그것이 이 작업의 출발점이었다
```

마지막 줄이 완료 판정이다. **변이가 잡히지 않으면 아무것도 고쳐지지 않은 것이다.**

## 바꾸지 말 것

```txt
Principal id 발급      'P' + wp_generate_uuid4().  JMAP 소유, opaque
소유 경계              Actors 는 Account 아이덴티티만 제공한다.  Principal 은 JMAP 것이다
Actors 의 principal    다른 개념이다 — "이 Actor 로 행위할 수 있는 주체".  재사용하지 않는다
자동 Actor 생성        공유 기능이 정체성을 발급하지 않는다
```
