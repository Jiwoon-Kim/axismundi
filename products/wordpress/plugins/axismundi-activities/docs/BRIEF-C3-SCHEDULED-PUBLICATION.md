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

## 구현 결정 (2026-10-26)

소유자 승인: 별도 `ax_activity_pending` 테이블. **근거는 수명 차이**다. 원장 읽기가
active allowlist라는 사실상 원장 비활성 상태도 비노출은 가능하다. 그러나 intent는
편집·교체·폐기되는 의도이고 원장은 일어난 사건이다. pending에 Activity URI/local_uuid를
발급하지 않는다. intent_uuid는 내부 교체 세대 식별자이며 외부 Activity ID가 아니다.

- 시계와 트리거는 WP `future`/`publish_future_post` 그대로다. 새 cron job을 만들지 않는다.
  `scheduled_for`는 `post_date_gmt`의 검증용 사본이며 재예약 저장 때마다 교체한다.
- metadata/mentions 저장 후 미래 글의 intent를 기록하고, REST 저장은 REST 완료 훅에서
  기록한다. post_id 기본 키로 살아 있는 의도는 하나다. 취소는 cancelled, 실패는 failed.
  이전 글에 intent를 소급 생성하지 않는다.
- 발사는 OP 공개 후보 훅 한 곳이다. 기록된 principal/Actor, source version/body hash,
  Object URI, 예약 시각, 현재 권한을 재검사한다. cron의 선택 쿠키나 새 기본값은 읽지 않는다.
  발행 전후 Core가 수정하는 status/modified와 별도로 source hash를 검증한다.
- audience는 스냅샷을 사용한다. 실행 시 해소한 audience가 달라졌으면 조용히 최신 값으로
  바꾸지 않고 실패한다. 다시 예약해 명시적인 의도를 받아야 한다.
- repository의 trusted before-commit 콜백으로 Create INSERT와 정확한 intent 세대 소비를
  같은 DB 트랜잭션에서 처리한다. 콜백 실패는 INSERT를 롤백하고 기록/전달 훅도 안 낸다.
  named lock은 저장/발사를 직렬화하며 원장의 SQL transaction을 중첩하지 않는다.
- 실패 사유는 pending 행에 남고 source 편집 화면에서 확인한다. WordPress 발행 자체는
  취소하지 않는다. 성공한 귀속은 기존 원장만 권위이며 intent는 귀속 조회에 사용하지 않는다.

검증 명령: `wp eval-file wp-content/plugins/axismundi-activities/tests/audit-scheduled-publication.php`.
실제 예약 시각을 기다리므로 약 70초가 추가된다. 임시 User/Actor/글/intent를 정리한다.

### 검증 결과

- 예약 행동 36건 통과: Organization 예약 → Person 전환 → 세션 없는 Core 발사,
  frozen audience, 재편집 교체, 취소, legacy intent 미추정, 권한 철회 실패,
  pending 소비 SQL 실패 시 Create INSERT 롤백, 중복 후보 멱등성, 네트워크 호출 0.
- 발사 Actor를 `current_workspace()`에서 읽게 하는 변이는 예약 완료 단정에서 검출했다.
  복원 후 같은 예약 감사 36건을 다시 통과했다.
- 영향 감사: repository 21, post-create 23, publish-selection 24건 통과.
  post-create는 legacy 선택 전제라 해당 실행에만 gate-off를 적용했다. 개발용 opt-in
  mu-plugin은 수정하지 않았다. gate-on 신규 경로는 별도 selection 감사에서 검증했다.
- 최초 예약 프로브는 단일 sleep 중단 후 아직 future인 글을 발사해 실패했다.
  시계를 반복 확인하도록 수정했으며, 실제 Core 예약 시각 이후에만 검사한다.

이 모델은 JMAP EmailSubmission처럼 source와 제출 의도를 분리하는 데 참고할 수 있지만,
Mail capability/EmailSubmission 구현은 아니다. JMAP identityId와 우리 Account를 동일시하지
않는다. post_id PK는 이번 범위의 '현재 예약 의도 하나' 제약이지 모든 Activity 제출의
보편 모델이 아니다. 상태는 전달 성공이 아니라 연합 원장 기록 성공/실패를 뜻한다.

브라우저 예약 UI와 스테이징 cron/전달은 미검증이다. 동시에 들어오는 임의의 외부 SQL
source 수정까지 예약 named lock에 참여시키지는 않는다. 발사 시 source/version/audience
검사는 있지만, 모든 WP 외부 writer를 포괄하는 쓰기 직렬화라고 주장하지 않는다.
