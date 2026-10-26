# 브리프: managed 세대 퇴역, principal 소비자 이행

2026-10-26. **codex 설계·구현용.**

S1이 저장 계약을 이미 끝냈다 -- `IMPLEMENTATION-PRINCIPALS.md`를 먼저 읽는다. 이 문서는
그 저장소를 다시 설계하지 않는다. S1이 **명시적으로 미뤄 둔 것**을 가져온다.

> S1: "기존 User 쓰기 경로만 유지하며 원격 권한 부여 API·UI·서명 인증 연결은 열지
> 않는다. ... Acting Actor 소비자 이행이나 Session 프로토콜 필드 추가는 하지 않는다."

오너 결정 (2026-10-26): **principal 기반 권한이 managed group 형태보다 발전된 도메인
구조이고, 세대교체가 필요하다.** S10이 레거시 Actor 전환을 JMAP 세션으로 교체한 것과
같은 모양이다 -- 호환 레이어를 병행 유지하는 작업이 아니다.

## 일반화 — principal은 Group 정책보다 넓다

`managed-groups.php`라는 이름이 Group 전용처럼 보이게 하지만, principal 관계는 **모든
account actor type에 이미 붙어 있다.**

```txt
principal 행 (tombstone 제외)
  actor_type     scope      kind      role     n
  Person         user       wp_user   owner     27
  Group          managed    wp_user   owner     69
  Organization   managed    wp_user   owner    137
  Organization   managed    wp_user   editor    39

principal 행이 하나도 없는 로컬 비-site Account      0 개
실제로 쓰이는 principal_kind                         wp_user 뿐 (1022 행)
```

두 가지가 따라 나온다. **(1)** 개인 Person Account도 owner principal을 가진다 --
`is_personal`이 role `owner`를 읽는 이유다. principal은 managed의 속성이 아니라 **모든
Account의 보편 관계**이고, `managed`는 그 위에 덧씌운 라벨이다. **(2)** owner 외의
role이 이미 실사용 중이다(Organization editor 39). role 어휘는 가설이 아니다.

`BRIEF-ACCOUNT-INBOX.md`의 측정 `scope='managed' ≡ principal 관계 보유 191/191`은
치환 근거이고, 위 측정은 그보다 강하다 -- **principal이 managed를 포함한다.**

## 두 세대가 같은 테이블 위에 있다

```txt
새 세대  principals.php         resolve_principal( kind, id )           세 kind 전부 해소
                                principal_account_role( account, kind, id )
                                tombstone·disabled·origin 위장·site scope 거름
         스키마                 PRIMARY KEY (identity_id, principal_kind, principal_id)

옛 세대  managed-groups.php     공개 함수 15개가 전부 (identity_id, user_id) 시그니처
                                질의에 principal_kind='wp_user' 하드코딩
                                create_managed_actor · group_managers · add_manager ·
                                remove_manager · list_manageable_actors · claim_managed_group …
```

**새 테이블이 필요하지 않다.** 테이블은 S1에서 이미 이전을 마쳤다. 이 작업은
`principals.php`를 공개 API로 승격하고 `managed-groups.php`의 wp_user 전용 시그니처를
퇴역시키는 일이다.

## managed가 겹쳐 들고 있는 세 가지

```txt
①  "개인 계정이 아니라 principal이 운영한다"    principal 보유로 치환.  측정 동치
②  "운영자 API가 wp_user 전용이다"              managed-groups.php 15함수.  ← 실제 작업량
③  actor_scope='managed' 컬럼                   어휘 축소가 이미 제거 예정으로 들고 있다
```

①과 ③은 다른 문서가 근거를 들고 있다. **이 브리프의 작업은 ②다.**

## 표면적

```txt
is_managed()                production 25곳 / 5플러그인
                            actors 12 · forum 9 · notifications 2 · note 1 · activities 1
actor_scope · get_scope()   38곳
managed-groups.php 공개 함수 15개
managed-actors 화면          별도 슬라이스 (principal 기준 재설계로 이미 기록됨)
```

forum 9곳은 앞서 센 `'Person' !==` 8곳과 같은 영역이다. Forum이 Person 제약과 managed
제약을 **둘 다 제품 층에서 재선언**하고 있다. 기준선을 Actors/Activities로 내리는 일과
한 묶음으로 보는 것이 낫다.

note 1곳은 폐기 플러그인이므로 이행하지 않는다.

## 이 세대교체가 여는 것

`local_account` principal이 실제로 쓰이면 다른 작업의 난점이 구조적으로 사라진다.

```txt
Organization Account 가 Group Account 를 운영한다        표현 가능해진다
그 principal 은 Actor URI 를 가진다                      FEP-1b12 moderator 컬렉션에
                                                          그대로 들어간다
get_for_user( $manager['user_id'] ) 파생이 필요 없다     forum/moderators.php:196
```

지금은 moderator 컬렉션에 넣을 Actor를 WP user에서 파생한다. user가 Account를 여럿
가지면 **"어느 Account로서 moderator인가"가 미정**이고, 그것이
`BRIEF-ACTOR-AXIS-MIGRATION.md`가 폐기 대상으로 잡은 파생이다. wp_user principal을
그 user가 명시적으로 고른 Account의 `local_account` principal로 **승격**하면 파생이
사라진다. 승격은 이 세대교체의 마이그레이션 단계다.

## 범위

```txt
포함   managed-groups.php 15함수를 principal 시그니처로 교체. (identity_id, kind, id)
포함   is_managed() 25곳 판정을 "principal 보유"로 교체하거나, 제품 층 규칙이면 그렇게 표시
포함   wp_user principal -> local_account principal 승격 경로.
       승격은 user가 명시적으로 고른 Account에 대해서만. 추측 금지
포함   role 어휘(owner/manager/editor)를 kind와 직교하게 유지
제외   원격 권한 부여 API·UI·서명 인증 연결 -- S1이 닫아 둔 것을 여기서 열지 않는다
제외   group 상속·순환 해소 -- S1 범위 밖 그대로
제외   actor_scope 컬럼 제거 -- 어휘 축소가 소유
제외   managed-actors 화면 재설계 -- 별도 슬라이스
제외   axismundi-note -- 폐기 플러그인
```

## 결정이 필요한 곳

```txt
1  승격 시점        언제 wp_user principal 이 local_account 로 올라가는가.
                    Account 생성 시인가, moderator 지정 시인가, 사용자가 명시할 때인가
2  공존 기간        wp_user 와 local_account principal 이 같은 Account 에 동시에 있을 때
                    role 이 다르면 무엇이 이기는가. 아니면 그 상태를 금지하는가
3  마지막 소유자     S1 은 "마지막 User 소유자 보호에 원격 행을 포함하지 않는다"고 적었다.
                    local_account 소유자는 그 보호에 포함되는가
4  is_managed 의 운명 전부 "principal 보유"로 치환되는가, 아니면 남겨야 할 뜻이 있는가.
                    25곳을 읽고 판정한다
```

2번은 권한 판정의 정합성이라 먼저 확정한다.

## 완료선과 검증

```txt
완료선   production 에서 principal_kind='wp_user' 하드코딩이 사라진다.
         managed-groups.php 의 (identity_id, user_id) 공개 함수가 남지 않는다.
         남은 is_managed() 호출은 각각 왜 managed 라는 뜻이 필요한지 주석을 가진다

검증     principal 1022 행이 이행 후에도 같은 권한을 낸다. 행 수가 아니라 **판정 결과**를
         비교한다 -- 이행 전후로 (account, user) 쌍의 role 을 전수 덤프해 대조한다
         S1 감사(audit-principals · audit-principal-behavior)가 회귀 검사다.
         "감사 통과"는 완료선이 아니다 -- 그 감사들은 wp_user 세계에서 쓰였다

변이     local_account principal 에 대해 role 조회가 kind 를 무시하도록 바꿨을 때
         감사가 잡는가. S1 이 같은 변이를 이미 한 번 썼다 -- 같은 숫자의 User/Actor 가
         권한을 공유하지 않는다는 검사다. kind 가 늘어난 뒤에도 성립하는지 다시 본다

금지     같은 숫자의 User 와 Actor 가 권한을 공유하지 않는다 (S1 불변).
         조회가 관계를 만들지 않는다 (S1 불변).
         principal 판정에 actor_type 을 읽지 않는다
         (`axismundi/docs/DECISION-ACCOUNT-VS-ACTOR-POLICY.md`)
```

## 순서

```txt
C3 예약 발행                독립. 먼저. 브리프 있음
→ 이 브리프 (principal 세대교체)
→ BRIEF-ACTOR-AXIS-MIGRATION   B 가 한 겹 줄어든 상태로 착수
→ Forum 제품 층 정리            Person 8곳 · managed 9곳을 기준선으로 내림
→ managed-actors 화면 재설계
```

축 마이그레이션보다 **이 작업이 앞선다.** 순서를 뒤집으면 축 마이그레이션에서
`get_for_user( $manager['user_id'] )` 를 한 번 옮기고, 세대교체에서 그 자리를 다시
들어낸다.
