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
규칙   요청자가 그 Account 의 Actor 로 행위할 수 있으면 currentUserPrincipalId = 그 Principal.
      아니면 **null**.  적합하다
의미   Actor 가 없는 사람은 공유 대상이 아니다.  정체성이 없으면 공유할 자리가 없다
금지   그 사람을 위해 Actor 를 자동 생성하는 것.  정체성 발급은 Actors 의 결정이고
      공유 기능이 끌어낼 일이 아니다
```

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
필수   Actor 없는 사용자의 currentUserPrincipalId 가 **null** 이다
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
