# S2 브리프 — 접근 관계를 하나로 모으고, 삭제가 닿지 않는 곳을 닫는다

2026-10-25. 선행: S1(f798f5f) · S3(f5fa995) · S4(d0e7062) 완료.
설계 배경은 `BRIEF-ACCOUNT-INBOX.md`. 이 문서는 **측정된 것만** 담는다.

## 범위를 먼저 좁힌다

S2 는 새 권한 모델을 만드는 작업이 아니다. 이미 있는 두 표현을 하나로 모으고, 삭제
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

### ① 접근이 두 곳에 적혀 있다 — 그리고 완전히 중복이다

```txt
personal_owner_user_id 가 있는 Actor                    338
  그중 같은 User 의 owner principal 행도 있는 것        338   ← 338/338, 예외 0
```

**한 번도 갈라진 적이 없다.** 그래서 수렴은 데이터 마이그레이션이 아니라 **읽는 쪽을
관계로 옮기는 작업**이다. 컬럼은 그 뒤에 지운다.

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
핀   순서: 연합 삭제 전달이 끝난 뒤에 비운다. 전달에 그 키가 필요하다.
     (Actor 자신의 Delete 전달은 S2 범위 밖이지만, 순서 제약은 여기서 지킨다.)
핀   기존 고아 206 행은 **마이그레이션이 정리한다.** 그 행들은 identity 가 이미 없어
     소유자를 복원할 수 없으므로, 비우는 것이 유일한 선택이다.
```

### V3 — 접근을 관계로 읽는다

```txt
1  personal_owner_user_id 를 읽는 곳을 관계·파생으로 옮긴다. 컬럼은 둔다.
2  감사가 통과하는 것을 확인한다.
3  그 다음 커밋에서 컬럼을 제거한다.
```

읽는 쪽을 먼저 옮기는 이유는 이 리포에서 정의 변경과 스윕을 한 커밋에 넣어 fatal 을
낸 전례가 있기 때문이다.

```txt
핀   isPersonal 의 파생 규칙을 먼저 정하고 그 다음 컬럼을 지운다.
     순서를 바꾸면 공유 Account 가 생길 때 복원할 근거가 없다.
핀   tombstone 253 행이 personal_owner 를 잃는다. 삭제된 User 의 묘비가 개인 귀속을
     유지해야 하는지는 **이 슬라이스가 정한다** -- S4 리뷰에서 S2 로 넘긴 질문이다.
```

## 검증

```txt
한 User 의 Person 둘을 만들고 wp_delete_user 를 실행한다.
  → 둘 다 tombstone. 둘 다 서명 스냅샷이 blocked.
  → 둘 다 secret_box 가 비고 retired_at 이 찍혔다. public_key_pem 은 남았다.
마이그레이션 후 고아 서명키의 secret_box 보유 수가 0 이다.
public 상태이면서 죽은 User 를 가리키는 로컬 Actor 가 0 이다 (현재도 0 -- 회귀 금지선).
isPersonal 이 관계·파생으로 계산되고, owner role 만으로 참이 되지 않는다.
변이: tombstone 을 단수 getter 로 되돌리면 두 번째 Person 검사가 실패해야 한다.
변이: secret_box 를 비우지 않게 하면 고아 키 검사가 실패해야 한다.
변이: isPersonal 을 owner role 로 치환하면 공유 Account 검사가 실패해야 한다.
       (그 검사를 위해 운영자 둘인 Account 픽스처가 필요하다.)
```

전수 감사는 돌리지 않는다. 영향 계약 감사 + 위 변이로 충분하고, 전수는 배포 시점이다.

## 보고 형식

"몇 개 통과" 가 아니라 **보장한 것 / 확인하지 않은 것**으로 나눠 적는다.
