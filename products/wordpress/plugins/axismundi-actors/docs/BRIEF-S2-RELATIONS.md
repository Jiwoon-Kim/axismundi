# S2 브리프 — 접근 관계를 하나로 모으고, 삭제가 닿지 않는 곳을 닫는다

2026-10-25. 선행: S1(f798f5f) · S3(f5fa995) · S4(d0e7062) 완료.
설계 배경은 `BRIEF-ACCOUNT-INBOX.md`. 이 문서는 **측정된 것만** 담는다.

## 범위를 먼저 좁힌다

S2 는 새 권한 모델을 만드는 작업이 아니다. 개인 귀속 컬럼은 유지하고, 삭제
경로가 닿지 않는 자리를 닫는다. 아래는 이 슬라이스에 **넣지 않는다.**

```txt
제외   공동 귀속·공동 발행·승인 프로토콜
       → open-items "Research Note: Collaborative Attribution And Publication".
         연구 기록이고 선결 조건이 아니다.
제외   Account 간 객체 귀속 이전 (Update 로 attributedTo 변경)
       → 대량 Update 부하와 피어 권한 검증이 걸린 별개 문제다.
제외   Actor 퇴역·Move
       → S2 는 User 삭제에 따라오는 정리만 다룬다. Actor 자신의 생명주기는 별개다.
제외   공유 Account (여러 User 가 한 Account 운영)
       → 관계 모델이 모이면 가능해지지만, 그 자체는 이 슬라이스가 아니다.
```

## 측정된 현재 상태 (2026-10-25, wp-env)

### ① 값은 겹치지만 같은 사실이 아니다

```txt
personal_owner_user_id 가 있는 Actor                    338
  그중 같은 User 의 owner principal 행도 있는 것        338   ← 338/338, 예외 0
```

**한 번도 갈라진 적이 없다.** 처음에는 그것을 중복으로 읽고 "읽는 쪽을 관계로 옮기고
컬럼을 지운다" 로 적었는데, 그 판단이 틀렸다 -- 겹치는 값은 같은 사실의 증거가 아니다.
아래 V3 가 그 계획을 철회한 이유다.

다만 컬럼이 담는 뜻이 관계와 같지는 않다.

```txt
personal_owner_user_id   "이 Account 는 이 User 에게 개인적으로 귀속된다"  = isPersonal
ax_actor_managers        "이 principal 이 이 Account 에서 이 role 을 갖는다" = 권한
```

둘이 지금 같은 값을 갖는 것은 **개인 Account 의 소유자가 곧 운영자**라서다. 공유
Account 가 생기면 갈라진다 — 운영자가 여럿이어도 개인 귀속은 한 명이거나 없다.
그러므로 **컬럼을 지우는 것이 isPersonal 을 지우는 것은 아니다.** 관계에서 파생할
방법을 먼저 정한다.

```txt
핀   isPersonal 은 권한이 아니다. 관계로 옮기더라도 "owner role 이 있으면 isPersonal"
     로 치환하면 안 된다 -- 공유 Account 의 owner 가 전부 개인 귀속자가 된다.
```

### ② 삭제가 닿지 않는 자리 셋

S4 리뷰에서 측정한 둘과, 이번에 센 모집단이다.

```txt
tombstone 이 Person 하나만 처리한다
  axismundi_actors_tombstone_for_user() 는 get_for_user() 를 쓴다.
  그 함수는 **구 per-user 라우트를 예약한 Actor 하나**를 돌려준다(S4 가 바꿨다).
  실측(wp_delete_user 로): 첫 Person 은 tombstone, 두 번째는 **public 유지 +
  서명키 복호 가능**, local_user_id 는 없는 User 를 가리킨 채.

삭제가 키 저장소에 닿지 않는다
  고아 서명키 206 행. 전부 secret_box 보유, 전부 복호 가능, 전부 retired_at = NULL.
  identity·actor 행이 사라져도 키 행은 남는다.
  오용은 아니다 -- signing_key_snapshot() 이 join 과 status 로 막는다. **보존의 문제**다.

죽은 User 를 가리키는 행
  로컬 Actor 564  (그중 public 0)
  principal 행   926
  personal_owner 311
```

`public 0` 이 중요하다. 지금 **공개된 Actor 가 죽은 User 를 가리키는 경우는 없다** --
위의 두 번째 Person 사례는 내가 만든 측정 픽스처였고 정리했다. 즉 지금은 잠재
결함이고, **생성 UI·REST 가 열리는 순간 실제 결함이 된다.** S4 가 생성을 닫아 둔
이유가 여기서 값을 한다.

## 할 일

### V1 — 삭제가 모든 Person 에 닿게 한다

```txt
tombstone_for_user() 가 그 User 의 **모든** 로컬 Actor 를 처리한다.
단수 getter 로 하나만 고르지 않는다.
```

```txt
핀   "모든" 의 범위를 측정으로 정한다. local_user_id 로 묶을지,
     personal_owner_user_id 로 묶을지, principal 관계로 묶을지가 다른 집합이다.
     셋의 교집합과 차집합을 세고 나서 고른다 -- 지금 숫자가 564 / 311 / 926 으로 다르다.
핀   tombstone 은 상태 전이다. 행을 지우지 않는다 (기존 계약).
```

### V2 — 삭제가 키 저장소에 닿게 한다

```txt
identity 가 tombstone 이 되면 그 Actor 의 서명키 secret_box 를 비우고 retired_at 을 찍는다.
공개 half(public_key_pem)는 남긴다 -- 과거 서명의 검증 근거다.
```

```txt
핀   키를 지우는 것과 폐기 표시는 다르다. secret_box 를 NULL 로 만드는 것이
     폐기이고, 행 삭제는 과거 서명의 검증 근거를 없앤다.
핀   기존 고아 206 행은 **마이그레이션이 정리한다.** 그 행들은 identity 가 이미 없어
     소유자를 복원할 수 없으므로, 비우는 것이 유일한 선택이다.
```

**정정 (2026-10-25)**: 초판에 "연합 삭제 전달이 끝난 뒤에 비운다 -- 전달에 그 키가
필요하다" 를 핀으로 적었는데, 그것은 **tombstone 시 폐기와 모순이고 사실도 틀렸다.**

```txt
signing-keys.php:93   signing_key_snapshot() 이 status NOT IN ('disabled','tombstone')
transport.php:58      전송기는 개인키를 **그 스냅샷에서만** 가져온다
→ tombstone 이 된 Actor 는 이미 서명할 수 없다.
  키를 남겨 두는 것만으로 삭제 전달이 가능해지지 않는다.
```

```txt
핀   tombstone 전이와 같은 트랜잭션에서 폐기한다. 전달을 기다리지 않는다.
핀   연합 삭제 전달이 필요하면 **tombstone 이전 단계**에서 처리한다.
     Actor 자신의 Delete 생명주기는 S2 범위 밖이고, 그 순서 제약은
     그 슬라이스가 지킬 일이다 -- S2 가 키를 붙잡아 둘 이유가 아니다.
```

### V3 — 개인 귀속 컬럼은 유지한다 (범위 축소)

초판은 `personal_owner_user_id` 의 읽는 쪽을 옮기고 컬럼을 제거하는 3 단계로 적었다.
**그 계획을 철회한다.**

```txt
측정        접근 권한은 이미 Principal 관계를 읽는다 (S4 리뷰에서 확인:
            can_access_workspace() 가 account_role() 로 판정하고, scope 가드는
            그것과 중복이었다).
그러므로    personal_owner_user_id 는 접근의 중복 표현이 아니라 **개인 귀속이라는
            별개의 사실**이다. 338/338 로 값이 겹치는 것은 개인 Account 의 소유자가
            곧 운영자이기 때문이고, 같은 뜻이라는 증거가 아니다.
결정        S2 에서 컬럼을 유지한다. 제거를 예정하지 않는다.
            대체 관계가 정의되지 않은 컬럼을 지우는 일정을 세우지 않는다.
```

```txt
핀   owner role 로 isPersonal 을 치환하지 않는다. 공유 Account 의 운영자 전원이
     개인 귀속자가 된다.
핀   isPersonal 을 관계에서 파생할 방법은 S5 가 정한다. 그 정의가 생긴 뒤에
     컬럼 제거를 **별도로** 판단한다.
```

그래서 S2 에 남는 "관계 수렴" 작업은 없다. 수렴할 중복이 측정상 존재하지 않는다 --
접근은 이미 한 곳에서 읽히고, 컬럼은 다른 사실을 담는다. 슬라이스 제목이 가리키던
문제는 **S4 가 이미 절반을 해결했고**(scope 가드 제거 결정), 나머지 절반은 S5 다.

```txt
핀   tombstone 253 행이 personal_owner 를 유지한다. 컬럼을 지우지 않으므로
     S4 가 넘긴 질문이 지금 강제되지 않는다. 묘지 슬라이스에서 다시 본다.
```

## 검증

```txt
한 User 의 Person 둘을 만들고 wp_delete_user 를 실행한다.
  → 둘 다 tombstone. 둘 다 서명 스냅샷이 blocked.
  → 둘 다 secret_box 가 비고 retired_at 이 찍혔다. public_key_pem 은 남았다.
마이그레이션 후 고아 서명키의 secret_box 보유 수가 0 이다.
public 상태이면서 죽은 User 를 가리키는 로컬 Actor 가 0 이다 (현재도 0 -- 회귀 금지선).
핸들 해제: tombstone 이 된 Actor 의 핸들을 새 Actor 가 접미사 없이 받는다.
  → get_by_handle(그 핸들) 이 **새** Actor 를 돌려준다.
  → 구 Actor 의 identity·URI 는 유지되고 새 Actor 가 승계하지 않는다.
    410 은 기존 구현이 아니었다. 아래 구현 기록의 HTTP 범위 정정을 따른다.
  → tombstone 의 acct·local_handle 주소 행이 전이 시점에 해제됐다.
변이: tombstone 을 단수 getter 로 되돌리면 두 번째 Person 검사가 실패해야 한다.
변이: secret_box 를 비우지 않게 하면 고아 키 검사가 실패해야 한다.
변이: tombstone 의 local_handle_key 를 NULL 로 비우지 않으면
       기존 UNIQUE 에 막혀 핸들 재사용 검사가 실패해야 한다.
변이: 주소 해제를 전이에서 빼면 새 Actor 가 접미사를 받게 되어 검사가 실패해야 한다.
```

`isPersonal` 파생은 이 슬라이스의 검증 항목이 아니다 -- 컬럼을 유지하므로 S5 가 그
정의와 검사를 함께 가진다.

전수 감사는 돌리지 않는다. 영향 계약 감사 + 위 변이로 충분하고, 전수는 배포 시점이다.

## 보고 형식

"몇 개 통과" 가 아니라 **보장한 것 / 확인하지 않은 것**으로 나눠 적는다.

## S2 구현 기록 (2026-10-25)

### 보장한 것

- 삭제 대상은 로컬 개인 귀속(`personal_owner_user_id`)으로 정한다. 귀속이 없는
  과거 `Person/user` 행만 `local_user_id`로 보완한다. Principal 운영 권한은 삭제
  근거가 아니다. `actor_scope=NULL`이어도 명시적 개인 귀속은 처리한다.
- 구현 전 측정에서 살아 있는 Person 27개는 legacy 연결과 개인 귀속자가 모두
  일치했다. 개인 귀속 없는 legacy 연결 253개는 모두 `user` scope의 기존 묘비였다.
  로컬 Actor에 연결된 WP User Principal 행 583개 중 개인 귀속 일치는 338개였다.
  따라서 모든 운영 관계를 삭제 대상으로 확장하지 않았다.
- `set_status()`가 Actor→identity 순으로 잠근다. tombstone 상태 변경·모든 서명
  이력의 비밀 폐기·`retired_at` 기록·핸들 점유값 해제·로컬 acct/handle 주소 해제는
  같은 트랜잭션이다. 실패하면 함께 롤백하고, 상태 변경 훅은 커밋 뒤에 실행한다.
- 공개키 이력·Actor/identity 행·canonical URI·개인 귀속 컬럼은 유지한다. tombstone은
  재활성화하지 않는다. 핸들 등록도 같은 잠금 순서를 사용해 삭제와 직렬화한다.
- 기존 `local_handle_key`는 이미 nullable UNIQUE다. 인덱스를 교체하지 않고 묘비의
  값을 NULL로 해제한다. 따라서 유일성 보호를 없애는 DDL이나 보호 공백이 없다.
  시스템 예약어와 살아 있는 identity의 주소 점유는 유지한다.
- DB 버전 38.0 마이그레이션은 기존 tombstone과 identity/Actor가 없는 고아 키의
  비밀을 정리하고, 로컬 묘비의 핸들·주소 점유를 해제한다. 실패 시 버전을 올리지
  않으며 재실행 가능하다. 살아 있는 Actor의 키는 보존한다.

검증 명령은 `wp eval-file wp-content/plugins/<plugin>/tests/<audit>.php`다.

| 감사 | 결과 |
|---|---|
| Actors `audit-user-deletion.php` | 최종 59 검사 통과 |
| Actors `audit-repository.php` | 20 검사 통과 |
| Actors `audit-addresses.php` | 5 검사 통과 |
| Actors `audit-account-ownership.php` | 29 검사 통과 |
| Actors `audit-identity-registry.php` | 14 검사 통과 |
| Bridge `audit-signing-keys.php` | 44 검사 통과 |
| Bridge `audit-account-cardinality.php` | 50 검사 통과 |
| JMAP `audit-session.php` | 50 검사 통과 |

새 감사는 User 삭제 후 여러 개인 Account와 legacy Person이 모두 tombstone인지,
운영하던 Organization은 남는지, 비밀 폐기·공개키 보존·UUID/URI 비승계·핸들/acct
재사용을 검사한다. 키 정리·주소 정리·COMMIT 실패 및 마이그레이션 실패의 롤백도 검사한다.

직접 적용한 네 변이는 모두 실패를 검출했고 원본을 복원했다: 삭제 대상을 하나로
축소, 고아 키 secret_box 보존, 핸들 UNIQUE 점유값 보존, 전이 시 주소 삭제 생략.
최종 wp-env SQL 측정은 고아/묘비의 비밀키 0, 묘비 핸들 점유 0, 묘비 로컬 주소
점유 0, 죽은 User를 legacy 연결로 가리키는 공개 로컬 Actor 0이었다.

### 확인하지 않은 것과 범위 정정

- 전체 감사, 스테이징 배포, 실제 원격 Delete 전달 및 동시 요청 부하 검사는 하지 않았다.
- 브리프가 전제한 구 Actor URI의 410은 현재 코드의 동작이 아니었다.
  Actors 라우트는 비공개 identity를 404로 숨기고, OP Actor visible gate도 tombstone을
  거부한다. S2는 이 공개 응답 정책을 바꾸지 않았다. 삭제 표현/410 제공은 Actor
  삭제 생명주기에서 별도로 다룬다. 위 검사는 HTTP 실측이 아닌 identity 보존 검사다.
- Actor 자신의 Delete 전달은 여전히 범위 밖이다. 키가 필요한 전달은 tombstone 전
  단계에서 끝내야 한다. 비밀을 보존한다고 현재 전송기가 tombstone을 서명하지 않는다.
- Calendar canonical URI/별칭 전환, 묘지 분리, S5 isPersonal 파생 변경은 하지 않았다.

### 후속 기록: 삭제된 Actor의 410 응답

소유자 방향(2026-10-25): 삭제된 Actor의 canonical URI에는 410을 제공하는 쪽이 좋다.
지금은 기록만 남기고 S10으로 진행한다. S2 완료나 Account 전환의 선결 조건이 아니다.
기존 identity/tombstone 상태로 삭제 여부를 구별할 수 있으므로 새 묘지 저장소를
전제로 하지 않는다. HTTP 410과 AS2 Tombstone 본문 제공 여부는 별도로 다룬다.
