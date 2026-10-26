# 브리프 C3: 예약 발행의 Account 고정

2026-10-26. **codex 설계·구현용.**

**설계는 이미 있다.** `C2S.md` §8 "Scheduled publication"이 규칙을 전부 들고 있다 --
스케줄 시점에 얼리고, 발사 시점에 다시 읽지 않고 재검증하며, pending은 원장의 active
행이 아니고, 예약 글 편집은 의도를 교체한다. 이 브리프는 그것을 다시 쓰지 않는다.
여기서 더하는 것은 **현재 구현 상태 측정과 범위·순서·검증**뿐이다.

읽는 순서: `C2S.md` §8 → 이 문서 → (귀속 축이 걸리면) `axismundi-actors/docs/
BRIEF-ACTOR-AXIS-MIGRATION.md`.

## 측정 — 지금 있는 것과 없는 것

```txt
pending submission 코드      없음
  grep pending_submission|scheduled_for|ax_act_pending  →  includes/ 에 0건

현재 예약 발행의 결과         federation 없음. 글만 발행된다
  publish-command 경로이 아니라 post-lifecycle.php:
  axismundi_act_on_object_publish_candidate() 가 get_current_user_id() <= 0 이면
  do_action( 'axismundi_act_unattributed_publication' ) 하고 Create를 만들지 않는다
  주석이 이유를 적어 두었다 -- 세션이 없으므로 고를 identity가 없고, author의 Person
  으로 폴백하는 것은 "a guess, and the wrong one for anything scheduled as an
  Organization"

귀속 저장소                  이미 있고, 이미 옳다
  op/post-article.php:88      apply_filters( 'axismundi_op_local_object_attribution', '' )
  activities/publish-command.php:139  그 필터에 붙어 원장에 묻는다
  즉 "무엇으로 발행했는가"의 권위는 Activities 원장이다. C3가 새 귀속 저장소를
  만들 이유가 없다 -- 만들면 권위가 둘이 된다
```

그래서 C3는 **귀속을 어디에 적을지 정하는 작업이 아니라, 예약 시점의 의도를 어디에
적을지 정하는 작업**이다. 둘은 다르다.

```txt
pending intent   "이 Object를 이 Account로, 이 시각에 발행하겠다"   발사 전에만 존재
ledger Create    "이 Object는 이 Actor가 발행했다"                  발사 후 권위
```

§8이 "A pending submission is not an active ledger row"라고 못박았으므로 pending은
`wp_ax_activities`의 active 행이 아니다. 저장 위치를 고르는 것이 이 슬라이스의 첫
설계 결정이고, 선택지는 별도 테이블과 원장의 비-active 상태 둘이다. **둘 중 무엇이든
outbox 투영·전달·공개 Activity 읽기에서 제외된다는 것이 조건이다.**

## 범위

```txt
포함   예약 시점에 Account 선택을 얼려 저장한다 (object_uri · actor identity ·
       expected version · audience snapshot · scheduled_for · state)
포함   발사 시점에 그 identity로 can_act_as() 재검증. 통과하면 Create가 active가 되고,
       실패하면 submission은 실패로 남는다. 다른 Actor로 발행하지 않는다
포함   예약 글 편집이 pending intent를 교체한다. 살아 있는 intent는 항상 하나
포함   pending은 outbox 투영·전달·공개 읽기에서 제외
제외   C2S outbox POST 엔드포인트, JWT, blob 업로드 -- S10이 이미 제외로 적었다
제외   새로운 귀속 저장소. 원장이 이미 권위다
제외   이미 예약된 기존 글의 소급 귀속. intent가 기록된 적이 없으므로 추측이 된다.
       기존 예약 글은 지금과 같이 federation 없이 발행된다
```

## 결정이 필요한 곳

```txt
1  pending 저장 위치     별도 테이블 vs 원장 비-active 상태.
                         조건은 위 "제외" 세 가지를 구조적으로 보장하는 것
2  발사 경로             wp-cron publish_future_post 인지, transition_post_status 인지.
                         post-lifecycle.php가 이미 transition_post_status:40 에 붙어
                         있으므로 두 번 발사되지 않도록 한 곳에서 결정한다
3  재검증 실패의 표면     실패한 submission을 사용자가 어디서 보는가.
                         Notifications가 자연스러운 자리지만 미구현 플러그인이다
4  audience snapshot      §8이 저장 항목으로 적었다. 발사 시점에 audience를 다시 푸는
                         것과 스냅샷을 쓰는 것이 다를 때 무엇이 이긴다고 할 것인가
```

1번은 뒤 작업과 부딪히므로 **먼저 확정하고 오너 확인을 받는다.**

## 검증

감사 통과는 완료선이 아니다. 이 슬라이스는 "시간이 지나야 틀리는" 종류라 실측이
필요하다.

```txt
핵심 시나리오   Organization Account를 선택한 상태에서 글을 예약한다
                → 선택을 자기 Person으로 되돌린다
                → 예약 시각을 지나게 한다 (wp-cron 강제 실행)
                → 발행된 Create의 actor가 **예약 당시 Organization**이어야 한다
                   지금 선택인 Person이면 이 슬라이스는 실패다

철회 시나리오   예약 후 그 Organization에 대한 act-as 권한을 뺏는다
                → 발사 시각
                → 글은 WordPress에서 발행되고, Create는 만들어지지 않으며,
                   Person이나 site Actor로 폴백하지 않는다

pending 비노출  발사 전에 outbox · 공개 Activity 읽기 · 전달 큐에 나타나지 않는다
                §8이 측정해 둔 사실과 짝이 맞아야 한다 -- future 글의 Object URI는
                activity+json·HTML 둘 다 404다. 그 Object를 가리키는 Create를
                outbox에 보이게 하면 outbox가 404를 광고하는 셈이다

편집 교체       예약 글을 두 번 편집하면 살아 있는 intent가 하나다
```

변이 검사도 같이 심는다 -- 발사 경로에서 "얼린 identity 대신 현재 선택을 읽는다"로
바꿨을 때 감사가 잡는가. 안 잡히면 그 감사는 이 슬라이스의 유일한 불변을 안 보는
것이다.

## 선행·후속

```txt
선행   없음. 지금 착수 가능하다
후속   BRIEF-ACTOR-AXIS-MIGRATION. 그 문서의 B(저장된 기록의 주체)는 "원장에 묻는다"로
       귀결되는데, 예약 발행이 원장에 기록을 남기지 않는 한 예약 글에는 물을 대상이
       없다. C3가 그 구멍을 메운다 -- 오너가 C3를 먼저 둔 이유다
```
