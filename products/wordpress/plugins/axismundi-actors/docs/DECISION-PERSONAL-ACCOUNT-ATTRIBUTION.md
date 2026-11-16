# Decision — 개인 Account 는 **귀속**이지 설정이 아니다

2026-11-17. 대화에서만 있던 모델을 기록한다. **대부분 현상의 이름 붙이기이고**, 새로 거는
제약은 마지막 절에 따로 적는다.

## 결정

```txt
개인 Account   로그인 User 에 **귀속**된다.  사용자가 설정으로 바꾸지 않고, 소유권 이전으로도
              옮겨지지 않는다.  아직 없을 수는 있다 (Actor 가 없는 사용자)
isPersonal    "이 Account 가 로그인 주체에 귀속되는가" 다.
              **AS2 Actor 타입과 무관하다** — Group·Organization·Service 여도 된다
생성 기본값     사용자의 첫 Actor 를 자동 생성할 때 타입은 `Person` 이다.
              **생성 기본값이지 귀속의 조건이 아니다**
```

조직 로그인 정체성을 발급하게 되면 그 User 에 귀속된 Account 의 Actor 는 처음부터
`Organization` 일 수 있다. 그때 `isPersonal` 은 "자연인이다" 가 아니라 **"이 로그인 주체에
귀속된다"** 는 뜻이다. 지금 구현하지 않아도 모델을 바꿀 필요가 없다.

## 코드가 이미 그렇다 (2026-11-17 측정)

```sql
-- account-access.php:36
SET a.personal_owner_user_id = a.local_user_id
WHERE i.origin = 'local' AND a.actor_scope = 'user' AND a.personal_owner_user_id IS NULL
```

**`IS NULL` 일 때만 채운다.** 재지정하는 경로가 없다 — 귀속이 한 번 정해지고 유지된다.

```php
// account-access.php:79
$user_id === $actor->get_personal_owner_user_id()
  && 'owner' === axismundi_actors_account_role( $actor->get_identity_id(), $user_id )
```

판정이 **Actor 타입을 보지 않는다.** `Person` 과 `isPersonal` 을 잇는 코드는 없다.

`IMPLEMENTATION-ACCOUNT-CARDINALITY.md` 도 같은 방향으로 적혀 있다 —
*"개인 귀속(`personal_owner_user_id`)과 Principal 접근 관계는 그대로 유지한다."*

## 세 가지를 가른다

```txt
귀속      personal_owner_user_id.  **바뀌지 않는다.**  isPersonal 이 이것을 보인다
소유      어느 principal 이 그 Actor 에 `owner` 를 갖는가.  **이전 가능**하다.
         이전해도 받는 쪽의 개인 Account 가 되지 않는다 — 권한을 넘기는 것과
         자기 기본 정체성으로 고르는 것은 다른 결정이다
보기 선호  GUI 가 기능별로 "처음 열 계정" 을 기억하는 것.  귀속도 소유도 바꾸지 않는다
```

귀속된 Actor 를 이전해 기존 소유자가 소유 자격을 잃으면, `isPersonal` 은 역할 검사 때문에
자연히 거짓이 된다(위 판정식). 별도 처리가 필요한지는 이전 정책에서 정한다.

### 선례 — Google Calendar

기본 캘린더는 소유권 이전이 **안 되고** 추가 생성한 캘린더만 된다. 그 모양이 우리와 같다 —
귀속된 것은 옮기지 않고, 추가된 것은 옮긴다. **선례이지 권위가 아니다.** 우리 규칙의 근거는
위의 코드와 이 결정이다.

## UI 로 내려오는 것

```txt
프로필          "내 개인 Account" 를 **보여주되 변경 선택기를 두지 않는다**
Managed Actors  추가 Account 의 권한과 이전을 다룬다.  개인 지정은 여기 없다
각 기능 화면     "이 앱에서 기본으로 사용" (보기 선호).  서버에 쓰지 않는다
```

## JMAP 쪽 결과

```txt
isPersonal       귀속을 그대로 투영한다
primaryAccounts  **다른 질문이다** — 처음 어느 계정을 열까.
                 귀속된 Account 가 지원하는 기능에만 내보내고, 없으면 생략한다
                 (BRIEF-PRIMARY-ACCOUNTS.md)
```

둘을 섞지 않는다. 내 것인 Account 를 기본이 아니라는 이유로 `isPersonal: false` 로 만들면
RFC 8620 §2 가 말하는 소유 개념이 흐려진다.

Stalwart·Cyrus 가 개인 Account 하나인 것은 **로그인이 곧 본인 계정을 정하는 구조**라서이지
제한을 건 결과가 아니다. 우리는 WordPress 로그인만으로 그것이 정해지지 않으므로 같은 결과를
**기본값으로** 낸다.

## 이 문서가 정하지 **않은** 것

```txt
개수 제약   "사용자당 개인 Account 최대 하나" 를 **강제하지 않는다.**
          오늘 그것을 막는 코드는 없다.  강제하려면 별도 결정과 마이그레이션이 필요하고,
          그 전에 기존에 여럿인 사용자를 어떻게 할지 정해야 한다 — 조용히 재분류하지 않는다
조직 로그인  지원 여부.  모델은 열려 있고 구현은 미정이다
이전 정책   Actor 소유권 이전에서 기존 소유자의 권한을 남길지
```
