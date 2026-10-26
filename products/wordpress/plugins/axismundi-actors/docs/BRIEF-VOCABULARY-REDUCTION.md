# 어휘 축소 브리프 — internal 과 actor_scope 를 없앤다

2026-10-25. 소유자 결정. 측정 근거는 `BRIEF-ACCOUNT-INBOX.md` 의
"상태와 범위 어휘를 줄인다" 절에 있다. 이 문서는 그 결정을 구현 단위로 쪼갠 것이다.

## 유지할 결정

```txt
status           public | disabled | tombstone        internal 을 뺀다
접근 판정        Principal 관계와 그 role 로만 한다
isPersonal       개인 귀속의 표현이다. **권한이 아니다.**
actor_type       프로토콜 타입이다. 역할이 아니다.
시스템 역할      저장된 분류를 만들지 않는다. 기능 설정이 Account 를 지목한다.
```

## 하지 말 것

이 브리프가 막으려는 실수들이다. 각각 리뷰에서 한 번 제안됐다가 철회됐다.

```txt
금지   scope='site' 를 actor_type='Application' 으로 치환한다
       Application 은 프로토콜 타입이다. 복수 Application Account 를 허용할 수 있으므로
       타입으로 인스턴스 역할을 식별하면 틀린다.
       일치한다는 측정값은 **행 하나에서 나온 것**이라 근거가 못 된다.

금지   isPersonal 로 접근·발행·관리 권한을 판정한다
       귀속은 "누구의 것인가", 권한은 "누가 무엇을 할 수 있는가" 다.
       전자로 후자를 대신하면 공유 Account 에서 깨진다.

금지   scope 를 없애면서 그 자리에 시스템 역할 분류를 새로 만든다
       "시스템" 은 액터의 종류가 아니라 맡긴 용도다.

금지   블로그 대표 Account 와 시스템 Actor 를 한 행으로 합친다
       공식 플러그인도 둘을 나눈다 (BLOG_USER_ID=0, APPLICATION_USER_ID=-1).
       우리 기존 scope='site' 행 하나가 그 둘을 합쳐 놓은 상태다.

금지   정의 변경과 호출부 스윕을 한 커밋에 넣는다
       전역 함수 재선언으로 fatal 을 낸 전례가 있다.
```

## V1 — internal 제거

### 측정된 현재 상태

```txt
internal 로컬 Actor 78
  Group(managed)         38   핸들 잠금 38
  Organization(managed)  36   핸들 잠금 36
  Person(user)            4   핸들 잠금  2

WP user 151 중 Actor 가 없는 user 124      ← 부재가 이미 미활성의 표현이다
```

### 할 일

```txt
생성 기본값    status 를 internal 에서 public 으로 바꾼다 (DATA-MODEL §2.3)
상태 어휘      허용 집합에서 internal 을 뺀다 (repository.php:2620 의 in_array)
마이그레이션   기존 internal 78 행을 public 으로 올린다
```

### 마이그레이션이 발화시키는 것 (측정)

status 변경은 순수한 컬럼 갱신이 아니다. 리스너를 확인했다.

```txt
webfinger.php:151   status_changed → sync_local_acct_address
                    handle@authority 를 acct 원장에 기록한다. 결정의 의도된 효과다.
forum/repository.php:290  status_changed → sync_published_group ('public' 일 때만)
                    바인딩된 Group 의 community settings 행을 만든다.
                    쓰기이지만 게시물을 발행하지는 않는다.
calendar            handle_registered 에만 붙어 있어 발화하지 않는다.
                    backfill 이 handle_locked_at 기준이라 상태와 무관하게 포괄한다.
```

78 행을 한 번에 올리면 그 리스너들이 78 번 돈다. 마이그레이션에서 리스너를 우회하지
말고 **그대로 돌게 둔다** -- acct 원장 기록이 이 결정의 목적이고, 우회하면 공개된
Actor 가 WebFinger 로 해소되지 않는 상태가 남는다.

### 부수로 닫히는 모순

```txt
signing_key_snapshot() 의 조건은 status NOT IN ('disabled','tombstone') 이다.
→ internal Actor 는 서명은 되는데 키는 공개되지 않았다.
   받는 쪽이 검증할 키가 없는 서명이다.
internal 이 없어지면 서명 가능 집합과 광고 집합이 같아진다.
```

별도 수정 항목으로 만들지 말고, 이 결정의 결과로 닫혔다는 것을 감사로 고정한다.

```txt
핀   서명 스냅샷을 얻을 수 있는 Actor 는 publicKey 를 공개하는 Actor 와 같은 집합이다.
     한쪽만 되는 Actor 가 하나라도 있으면 실패한다.
```

### 검증

```txt
새로 만든 Person·Group 이 public 으로 생긴다.
status 허용 집합에 internal 을 주면 거부된다.
마이그레이션 후 internal 행이 0 이고, 올라간 행이 acct 원장에 들어가 있다.
마이그레이션은 재실행 가능하다 (이미 public 인 행을 다시 올려도 같은 결과).
변이: 생성 기본값을 internal 로 되돌리면 상태 어휘 검사가 실패해야 한다.
변이: 서명 스냅샷 조건을 넓히면 위 집합 동일성 핀이 실패해야 한다.
```

## V2 — ensure_for_user() 자동 생성 (별도 확정 필요)

**V1 과 같은 커밋에 넣지 않는다.** 어휘 정리가 아니라 동작 변경이다.

```txt
repository.php:2783  seed() 가 플러그인 활성화 시 현재 관리자에게 만든다
admin.php:100        권한 있는 관리자가 없는 Actor 를 요청할 때 만든다
```

internal 이 없어지면 이 둘은 **아무도 선택하지 않은 정체성을 public 으로 발행한다.**
`admin.php` 가 GET 도달 생성을 제거한 이유와 같은 성질이다. 다만 끄면 관리자가 활성화
직후 기대하던 Actor 가 없어지므로, 소유자 확정 전에는 손대지 않는다.

V1 을 먼저 하면 이 구간에 **public Actor 가 자동 생성되는 창**이 생긴다. 생성 UI·REST 가
닫혀 있어 트래픽은 닿지 않지만, V1 작업 중 수동으로 seed 를 돌리지 않는다.

## V3 — actor_scope 제거

### 치환 근거가 있는 것과 없는 것 (측정)

```txt
scope='managed'  ≡ principal 관계 보유            191/191        근거 충분
scope='user'     ≡ personal_owner IS NOT NULL     살아있는 행 불일치 0
                                                  불일치 253 은 전부 삭제된 User 의 tombstone
scope='site'                                      행 1 개. **근거 없음.**
remote actor                                      scope 가 항상 NULL
```

마지막 줄이 이 제거를 정당화한다 -- 원격 Actor 는 scope 를 한 번도 가진 적이 없다.
연합 개념이 아니라 로컬 분류였다.

### `<> 'site'` 가드는 치환하지 않고 제거한다

권한 층의 scope 용법은 거의 전부 `<> 'site'` 다. 그 가드가 막는 것을 측정했다.

```txt
identity 25564 (유일한 scope='site', handle 'blog', status disabled)
  ax_actor_managers 행           0
  account_role(admin 1)          ''        ← principal 검사만으로 거부
  resolve_workspace(admin 1)     refused
  is_public_profile              no
  local_public_key               none
  signing_key_snapshot           blocked
```

`can_access_workspace()` 는 scope 가드와 `account_role() !== ''` 를 **둘 다** 들고
있고, 뒤의 것만으로 결과가 같다. 가드는 분류 단축이었고, 남겨 두면 해롭다 -- 나중에
그 Account 에 principal 을 부여해도 scope 가 막는다.

```txt
핀   `<> 'site'` 가드를 다른 술어로 바꾸지 않고 제거한다.
     판정은 principal 관계와 status 가 이미 하고 있다.
```

### 범위

```txt
production  9 파일, 36 줄 / 38 occurrence
  axismundi-actors/includes/  repository.php 14, managed-groups.php 6,
                              account-access.php 6, routing.php 4, principals.php 2,
                              workspaces.php 1, texts.php 1, account-operations.php 1
  바깥        calendar/includes/primary-calendar.php 1
              (`actor_scope IN ('user','managed')` -- backfill 대상 선택)
```

Calendar 쪽은 "로컬이고 사이트 Actor 가 아닌 것" 을 고르는 용도이므로, 같은 규칙으로
옮긴다.

### 순서

```txt
1  읽는 쪽을 먼저 옮긴다. 컬럼은 그대로 둔다.
2  감사가 통과하는 것을 확인한다.
3  그 다음 커밋에서 컬럼과 scope_type 인덱스를 제거한다.
```

### 검증

```txt
principal 이 있는 Account 는 workspace 로 해소된다. scope 와 무관하다.
principal 이 없는 Account 는 거부된다. 관리자여도 마찬가지다.
'blog' Account 에 principal 을 주면 해소된다 (이전에는 scope 가 막았다).
개인 귀속이 있는 Account 는 isPersonal 로 보고되고, 그것이 권한을 주지는 않는다.
원격 Actor 경로는 영향이 없다.
변이: principal 검사를 제거하면 접근 검사가 실패해야 한다.
변이: isPersonal 로 접근을 판정하게 바꾸면 공유 Account 검사가 실패해야 한다.
```

## tombstone 253 행

`isPersonal` 을 잃는 행들이다. 삭제된 User 의 묘비가 개인 귀속을 유지해야 하는지는
S2 의 삭제 정책이 정한다. **이 브리프에서 정하지 않는다.** 같은 행들이 S4 리뷰에서
찾은 결함 1·2 의 모집단이다.

## 이 브리프에 없는 것

```txt
시스템 Actor 구현        S9. 지금 우리 시스템 액터는 없다 --
                         'blog' 행은 의도적 disabled 이고, Bridge 는 공식 키를 빌린다.
authorized fetch         요구가 없다. 구현하지 않는다.
projection @context      OP 쪽 작업이다. open-items 의
                         "Actor Projection Emits Undeclared Extension Terms" 참조.
```

## 순서 — Account 작업과 같이 간다 (2026-10-27)

오너 결정. V1·V3 를 따로 돌리지 않고 Account 쪽 작업(축 마이그레이션 · AddressBook ·
accountCapabilities)과 한 묶음으로 본다.

이유는 겹침이다. `actor_scope` 는 `is_managed()` 25곳과 같은 사실을 들고 있고, 그
25곳은 축 마이그레이션과 principal 세대교체가 이미 만지는 자리다. 따로 하면 같은 파일을
두 번 옮긴다.

**브리프의 모집단 숫자가 낡았다. 착수 전에 다시 잴 것.**

```txt
작성 시점        internal 78 행
2026-10-27 실측  internal 1,279 · public 1,233 · tombstone 775 · disabled 1
                 actor_scope  user 599 · managed 208 · site 1 · NULL 274
```

internal 이 78 에서 1,279 로 늘었다. 감사 픽스처가 만들어 온 것으로 보이며, 이행 대상이
대부분 **테스트 잔여물**일 가능성이 높다. V1 을 "78 행을 public 으로" 로 적어 둔 계획은
그대로 쓸 수 없다 -- 살아 있는 행과 픽스처 잔여물을 먼저 가른 뒤에 이행 범위를 정한다.
