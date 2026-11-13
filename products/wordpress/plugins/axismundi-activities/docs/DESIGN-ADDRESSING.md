# DESIGN — 주소 지정과 열람 판정

주소 데이터와 **그 주소를 해석하는 정책을 분리한다.** 이 문서는 무엇을 저장하고
무엇을 미결로 두는지를 정하지, 해석 규칙을 전부 결정하지 않는다.

## 0. 왜 지금인가 — 같은 질문에 답이 셋이다

```txt
wp_ax_activities    has_public_audience   repository.php:47
ax_local_objects    publicly_readable     ../axismundi-object-projections/includes/local-objects.php:27
ax_remote_objects   publicly_listable     ../axismundi-object-projections/includes/remote-objects.php
```

세 저장소가 **"이 GET 에 무엇을 돌려주나"** 를 각자 판정한다. 이름도 뜻도 미묘하게
다르다 — readable, listable, has public audience. 판정이 한 곳에 없으므로 한 곳을
고쳐도 나머지가 따라오지 않는다.

그리고 입력 쪽에는 주소를 직접 쓸 입구가 없다. `resolve_audience()`
([audience.php:63](../includes/audience.php)) 가 받는 것은 단어 넷
(`public`/`unlisted`/`followers`/`mentioned`) 과 멘션 목록뿐이다. 역함수
`audience_visibility()` ([audience.php:146](../includes/audience.php)) 가 저장된
`to`/`cc` 를 다시 단어로 읽고, **수신측 판정이 거기 기댄다.** Forum 은 그 프리셋이
Lemmy 모양(`cc:[group]`, `audience: group`) 을 표현하지 못해
`axismundi_forum_distribution_audience()`
([distribution.php:46](../../axismundi-forum/includes/distribution.php)) 로 같은 일을
따로 한다.

## 1. 세 판정

주소는 하나인데 그것으로 답하는 질문이 셋이다. **섞으면 한 질문의 규칙이 다른 질문으로
샌다** — 이 문서에서 고친 실수가 대부분 그것이었다.

```txt
열람    누가 이 객체를 볼 수 있나   입력 객체의 주소        권위 우리 또는 발신자
응답    이 요청에 무엇을 돌려주나   입력 열람 + 객체 상태   권위 우리
수신자  누구에게 배달하나           입력 활동의 주소        권위 발신자
```

**열람 ≠ 응답.** 열람이 허용해도 응답은 `404`·`410` 일 수 있다 — 삭제된 객체,
Tombstone, 아직 발행되지 않은 소스. 열람은 "볼 자격이 있나" 이고 응답은 "지금 무엇이
있나" 이다.

**열람 ≠ 수신자.** 배달은 **원격에 대한 전파 수단**이다. 로컬 독자는 배달을 거치지 않고
피드와 outbox 를 읽으므로, 수신자가 없는 활동도 로컬에서는 보인다. 반대로 배달받았다는
사실이 아무에게나 보여도 된다는 뜻은 아니다 — 주소지정된 액터에게만이다.

**권위가 갈린다.** 우리 객체는 우리가 판정하고, 받은 객체는 **발신자가 이미 판정했다.**
그래서 수신자 쪽에서 같은 판정을 다시 돌리지 않는다(§6).

```txt
우리 것       열람  우리 수락 기록과 우리 주소
받은 것       열람  그 배달이 해소한 주소. 다시 판정하지 않는다
가져온 것     열람  그 객체 자신의 주소. Public 이면 공개
```

세 판정이 **같은 입력(주소)을 공유하되 서로의 규칙을 빌리지 않는다**는 것이 이 문서의
한 줄 요약이다.

## 2. 개념 — 같은 속성 이름이 두 질문에 쓰인다

§5.6 이 *"In addition to ActivityStreams collections and objects, Activities may
additionally be addressed to…"* 로 **collection·object·activity 를 함께** 들므로, 주소
지정의 대상은 액터만이 아니라 **코어 타입 전반**이다. 그리고 주소 지정(§6.1)과
배달(§7.1)은 서로 다른 절이다 — 같은 필드를 읽지만 같은 질문이 아니다.

배달 쪽은 범위가 훨씬 좁다. [AP §7.1] 은 *"An activity is delivered to its targets
(which are actors)"* 라고 한다 — **대상은 액터이고, 배달되는 것은 활동이다.** 그래서
주소 지정 가능한 것과 배달 가능한 것이 다르다.

```txt
주소 지정 가능   Activity · Object · Collection 의 모든 서브타입, Public 포함
배달 가능        inbox 를 가진 액터. 보통 Person·Group·Organization·Service·Application
                 [AP §4] 는 `Profile` 같은 다른 타입도 액터로 쓰일 수 있다고 한다 —
                 기준은 타입이 아니라 inbox 다
```

여기서 따라오는 결론 하나: **객체를 그냥 inbox 로 POST 할 수 없다.** S2S 로 Article 을
보내려면 활동으로 감싸야 한다. `bto`·`bcc` 는 숨긴 주소일 뿐 배달 자체는 정상 동작한다 —
§7.1 이 수신자 결정 필드로 `to`·`bto`·`cc`·`bcc`·`audience` 를 함께 든다.

**먼저 스펙이 실제로 말하는 것부터.** "Activity 주소 = 전달, Object 주소 = 가시성"
이라고 단정하면 틀린다. ActivityPub 에는 객체 주소를 독립 GET 의 ACL 로 바꾸는 공통
규칙이 **없다.**

```txt
배달          §6.1 · §7.1.1   Activity 의 to·bto·cc·bcc·audience 로 수신자를 정한다
Activity 열람  §5.6           Public 으로 주소 지정된 활동은 인증 없이 접근 가능해야 한다
              §5.1            Outbox 항목은 요청자의 열람 권한으로 거른다
Object 열람    §3.2           서버가 인가하고 **자체 규칙을 적용할 수 있다**.
                              판정식은 주지 않는다
```

§6.2 는 최초 배포 때 `Create` 와 내장 객체 사이에 수신자를 맞추라고 권고하고, 이후
객체의 주소가 `Create` 와 별개로 바뀔 수 있다고 한다. 객체 주소에 명세상 역할이 있는
것은 맞지만 **그 역할이 곧 GET 인가는 아니다.**

그래서 정확한 문장은 이렇다 — 주소 지정은 배달 대상을 정하고, ActivityPub 은 `Public`
으로 주소 지정된 활동의 공개 접근도 규정한다. 독립 객체를 GET 할 때 그 객체의 주소를
열람 인가의 **입력으로 쓸 수는 있지만**, 그 해석과 주소가 없을 때의 기본값은
ActivityPub 이 정하지 않는다.

**어느 쪽의 결론도 다른 쪽의 권한으로 자동 승격되지 않는다.** 같은 주소를 두 판단에
쓰더라도, 배달 대상이었다는 사실이 열람 권한이 되지 않고 열람 가능하다는 사실이 배달
대상이 되지 않는다. 이름이 같다는 이유로 한 코드가 둘 다 처리하면 한쪽이 조용히 다른
쪽의 규칙을 따라간다.

### 관례는 어디서 왔나 (실측 2026-11-14)

Mastodon 1.6.0(2017-09)에 이미 있다. 다만 **주소를 GET ACL 로 쓴 것이 아니라** 기존
공개 범위를 AS2 주소로 **상호 변환**했다.

```txt
발신   내부 visibility → to/cc   공개는 to:Public, 미등록은 cc:Public, 팔로워는 to:followers
수신   to/cc → 로컬 visibility   to:Public→public, cc:Public→unlisted,
                                 to:발신자 followers→private, **그 외 전부 direct**
GET    to 를 보지 않는다          status_policy 가 저장된 공개 범위와 요청자 관계를 본다
```

읽어 낼 수 있는 설계 이유는 이것이다 — Mastodon 에는 이미 공개·팔로워 전용·직접이라는
제품 정책이 있었고, 연합을 붙이면서 그 구분을 원격에 전달할 표현이 필요했다. 주소
필드는 이미 배달 범위를 표현하고 §6.2 가 둘을 맞추라고 권고하니, 그 필드를 가시성
모델의 **운반체**로 재사용한 것이 자연스럽다. 최초 발명자나 당시 합의를 확인했다는
뜻은 아니다.

**여기서 "주소 없음 = 공개" 는 나오지 않는다.** 그 수신 코드는 신호가 하나도 없으면
오히려 `direct` 로 분류했다.

#### 이 관례는 따를 것이 아니라 고칠 것이다

출처를 알고 나면 무엇이 잘못됐는지도 보인다. **배달 필드에 스펙이 주지 않은 인가
의미를 겹쳐 놓은 것**이고, 거기서 결함이 줄줄이 나온다.

```txt
① 합의 없는 환산    cc:Public = "미등록" 은 Mastodon 제품 정책이지 일반적 의미가 아니다
② 부재의 불일치     각 구현이 다르게 고른다. Mastodon 은 direct, 다른 쪽은 공개.
                    차이가 조용하고 보안에 걸린다
③ 재구성 불가       bto/bcc 는 배달 전에 제거되므로 수신본은 실제 주소를 표현하지 못한다.
                    그 경우 수신측 재구성은 **구조적으로 틀린다**
④ 권위 역전         §3.2 는 조회 인가를 **원본 서버**에 맡긴다. 원격이 주소에서 복원한
                    값은 어떤 경우에도 권위가 될 수 없는데, 관례는 그것을 권위처럼 쓴다
```

④가 핵심이다. 작성자의 의도를 수신측이 **복원**한다는 전제 자체가 틀렸다. 상호운용
가능한 것은 **원본이 실제로 무엇을 내주는가**와 **우리에게 무엇이 어떤 경로로
배달됐는가**뿐이다. 주소는 배포 의도의 **힌트**이고 ACL 이 아니다.

그래서 우리 입장은 관례 추종이 아니다 — 주소는 보존하고(§5), 받은 경로와 표현을 근거로
기록하고(§6), **작성자 의도를 역산하지 않는다.**

`IntransitiveActivity`(`Travel`, `Question` 등) 는 별도 객체가 없으므로 둘이 겹친다.
`Like`·`Announce` 의 주소는 `likes`·`shares` 컬렉션에서 **수만 보일지 활동이
항목으로 보일지**를 가른다 — 역시 열람 질문이다.

**`Public` 은 배달 목적지가 아니다.** [AP §5.6] 은 `Public` 으로 주소 지정된 활동의
익명 접근을 규정하면서 동시에 *"Implementations MUST NOT deliver to the 'public'
special collection"* 이라고 한다. 그러니 `Public` 은 **열람 축의 값**이고, 배달
목적지는 거기서 따로 유도된다.

같은 이유로 **공개 Actor 문서에 `cc: Public` 을 합성하지 않는다.** 액터가 공개로
조회된다는 사실은 액터 조회 정책이지 그 문서의 주소 지정이 아니다. AP 어디에도
액터를 `cc: Public` 과 동일하게 보라는 규정이 없다 — 합성하면 우리 해석이 스펙인
것처럼 보인다.

## 3. 주소가 없을 때 — 해석과 저작은 다른 규칙이다

**부재는 그 자체로 아무것도 허락하지 않는다.** 이전 판에서 "`id` 가 있고 `to`/`cc` 가
없으면 공개" 라고 적었는데 그것은 틀렸다. 근거가 둘이다.

첫째, 받은 문서에서는 **`bcc` 로만 배달된 객체가 무주소 객체와 같은 모양**이다.
[AP §6] 이 배달 전에 `bto`/`bcc` 를 제거하므로(§5), 수신본만 보고는 구별할 수 없다.
`audience` 만 있는 경우도 마찬가지로 설명되지 않는다. 둘째, 관례도 그렇지 않다 —
Mastodon 수신 코드의 기본값은 `direct` 다(§2).

```txt
to/cc 가 없다              공개 여부 미확인. 그 자체로 허가도 금지도 아니다
to/cc 는 있는데 Public 없다  마찬가지로 미확인. 명시적 비공개 선언이 아니다
```

### 축은 접근이 아니라 "밝혔는가" 다

[AS2 어휘]의 정의를 보면 `public`/`private` 가 접근이 아니라 **공개 진술 여부**다.

```txt
to    public primary audience      밝힌 1차 청중
cc    public secondary audience    밝힌 2차 청중
bto   private primary audience     밝히지 않은 1차 청중
bcc   private secondary audience   밝히지 않은 2차 청중
```

그래서 상태가 셋이다 — **밝힌 청중 · 밝히지 않은 청중 · 청중 진술이 아예 없음.**
셋째가 이 문서가 다루는 공백이고, 둘째와 다르다. `bto` 는 청중이 **있되 숨긴** 것이지
없는 것이 아니다.

#### `bto: Public` — 모순이 아니라 이 공백의 증명이다

`bto` 에 `Public` 을 넣는 것은 어색해 보이지만 **스펙이 금지하지 않고**, 그 귀결이
정확히 이 문서가 다루는 공백이다.

```txt
[AP §5.6]   Public 으로 주소 지정된 활동은 인증 없이 접근 가능해야 한다.
            **어느 필드인지 한정하지 않는다**
[AP §6]     bto·bcc 는 배달 전에 객체에서 제거한다
[AP B.11]   표시에서도 생략해야 한다 — **SHOULD 이지 MUST 가 아니다**.
            저장 방식은 구현 자유
```

그러므로 `bto: Public` 인 객체는 **내준 표현에 `Public` 이 어디에도 없으면서 §5.6 이
익명 접근을 요구하는** 상태가 된다. 가져온 문서의 주소는 **설계상 전체 주소가 아니다.**
그러니 거기서 열람 규칙을 유도할 수 없다 — 앞에서 bcc 수신자 사례로 말한 것을 스펙이
직접 증명하는 셈이고, 이것이 FEP 후보의 가장 선명한 동기다.

#### 그래서 규칙은 이것이다

**addressing 어디든 — `bto`·`bcc` 포함 — `Public` 이 한 번이라도 있으면 공개다.**
열람 판정의 단일 규칙으로 쓴다.

**다만 근거의 범위를 정확히 해야 한다.** §5.6 은 *"**Activities** addressed to this
special URI shall be accessible to all users, without authentication"* 이라고 한다 —
**활동**에 대한 규정이다. 필드를 한정하지 않는다는 것은 맞으므로 `bto`·`bcc` 포함은
그 문장 안에서 나온다. 그러나 **독립 `Object`·`Collection` 에 같은 판정식을 적용하는
것은 Axismundi 가 선택한 조회 정책**이지 스펙을 그대로 읽은 것이 아니다. §3.2 가 객체
조회 인가를 서버에 맡기고 판정식을 주지 않기 때문이고, 그 확장이 바로 FEP 후보가
제안하려는 것이다(§11).

#### 공개 Actor 도 같은 판정을 타되, 출처를 구분해 적는다

공개 Actor 가 **별도 GET 분기**를 타지 않게 하자는 것이 요지다. 그것은 채택한다 —
분기가 둘이면 둘이 따로 드리프트한다. 다만 **원본 주소와 내부 열람 정책의 출처를
구분**해서 기록한다.

```txt
기록   actor_policy 가 부여한 익명 열람 허용      출처: 우리 정책
       원본에서 받은 주소 (to·cc·bto·bcc·audience)  출처: 작성자
읽기   GET 판정기        둘 다 읽는다 → 분기가 하나다
       배달 계산          실제 주소만 읽는다
       AS2 직렬화        실제 주소만 읽는다
```

이러면 액터 전용 분기도 없고, 선언되지 않은 `bto` 를 합성할 일도 없다. `bto` 에
`Public` 을 **적어 넣는 것**만 하지 않는다 — `bto` 는 "밝히지 않은 1차 청중" 이라
드러내놓고 공개인 액터에 쓰면 사실과 반대를 적는 것이고(선언하려면 `to:[Public]` 이
정직하다), §6·B.11 때문에 바깥으로 나가지도 않으며, 배달 계산이 그것을 읽으면 §5.6 의
"public 으로 배달 금지" 때문에 매번 특례로 걸러야 한다.

실측으로도 액터는 주소를 싣지 않는다 — Lemmy Group 액터(`lemmy.ml/c/asklemmy`)에
다섯 필드가 **하나도 없다**(2026-11-14). 그래서 액터의 공개성은 주소에서 오지 않고,
`actor_policy` 라는 **다른 출처**로 기록하는 것이 사실에 맞는다(작업 순서 ①).

막아야 할 것은 그 구성이 아니라 **추론을 기록으로 바꾸는 일**이다. 익명 GET 이
성공한다는 이유로 `bto: Public` 을 **적어 넣으면** 없던 청중을 진술하고, 숨겼다고 거짓을
보태고, 보존된 기록에 추론을 섞는다(§5). 관측은 관측으로 적는다.

**관측은 주소가 아니라 관측으로 적는다.** 익명 GET 이 실제로 성공했다는 것은 공백을
메우는 좋은 근거이지만, 그것은 **날짜 붙은 사실**이다 — 인가는 나중에 바뀌고, 어떤
서버는 익명 요청에 **축약된 표현**을 내준다. 그러니 "그때 익명으로 받아졌다" 로 적지
"공개로 주소 지정돼 있다" 로 적지 않는다.

미확인을 해소하는 것은 **별도의 근거**다 — 익명 GET 으로 실제 조회되는가, 어떤 로컬
액터에게 어떤 경로로 배달됐는가, 그 소스의 공개 정책이 무엇인가(§4). 액터 문서와
컬렉션이 공개인 것은 "주소가 없어서" 가 아니라 **액터·컬렉션 조회 정책 때문**이다.

### `id` 가 없다는 것의 정확한 뜻

[AP §3.1] 은 배포되는 객체에 전역 식별자를 요구한다. 그것을 뒤집어 공개 여부나 수명을
단정하면 과하다 — `id` 없는 인라인 객체도 공개 부모 표현 안에서 보이고, Pre-FEP 의
Session 은 `id` 없이 인증된 엔드포인트에서 조회된다.

정확한 문장은 하나다: **이 표현에 AS2 `id` 가 없으면, 그 `id` 로 독립 조회하거나
상호작용 대상을 지정할 수 없다.** transient 라는 뜻도, 비공개라는 뜻도 아니다.

### 해석 ≠ 저작

**"to/cc 없으면 공개" 는 fail-open 이다.** 가시성은 우리가 다른 데서 전부
fail-closed 로 잡은 속성인데(`sensitive` 는 object ∨ attachment, 키 없는 액터는
fail-closed) 하필 가장 비싼 속성에서 방향이 뒤집힌다.

실패 양상이 구체적이다. 투영 층이 버그로 `to` 를 떨어뜨리면 그 객체는 **에러가
아니라 공개가 된다.** 누락과 "공개로 의도함" 이 문서상 구별되지 않으므로 아무도
잡지 못한다. OP 는 `to` 를 매번 계산하므로(`default_public_audience`) 계산이 빈
배열을 내는 경로가 생기면 그 자체가 공개 선언이다.

```txt
해석 (수신)   id 있고 to/cc 없으면 공개로 취급한다. 그러지 않으면 액터와 컬렉션을
              전부 비공개로 읽어야 한다
저작 (발신)   누락에 의존하지 않는다. 공개면 to 에 Public 을 명시한다.
              우리가 내보내는 객체의 to 가 비어 있으면 그것은 버그다
```

해석 규칙은 받아들이고 **우리 쪽에서 그 규칙에 기대지 않는다.** 그러면 누락이 다시
감지 가능한 이상이 된다.

### Activity 쪽은 한 축이 더 있다

활동에 `to`/`cc` 가 없으면 **공개로 읽히되 배달 대상이 없다.** 그래도 로컬
팔로워에게는 닿는다 — 로컬 독자는 피드와 outbox 를 읽으므로 배달을 거치지 않고
읽기 판정만 통과하면 된다. 즉 **배달은 원격에 대한 전파 수단**이고 로컬 도달은 순수
읽기 판정이다.

배달 결과가 같은 두 경우가 읽기 권위는 다르다. 이 구분을 적어 두지 않으면 "둘이
같은 것 아니냐" 로 돌아온다.

```txt
to/cc 없음        읽기  우리 정책상 공개. 스펙이 강제하지는 않는다
                  배달  대상 없음
to: [Public] 만    읽기  [AP §5.6] 이 익명 접근을 MUST 로 강제한다
                  배달  대상 없음 — Public 컬렉션으로는 배달 금지
```

[AP §7.1] 노트가 후자를 명시한다 — *"not delivered to any actors but is publicly
viewable in the actor's outbox."*

## 4. 소스별 공개 정책

표현력이 소스마다 다르다. **없는 표현력을 저장 구조가 있는 척하면 안 된다.**

### wp_posts — 공개 상태가 기준이고, 주소 메타를 두지 않는다

`axismundi_op_post_article_visible()`
([post-article.php:168](../../axismundi-object-projections/includes/post-article.php)) 은
`publish` + 암호 없음 + `is_post_publicly_viewable()` 를 요구한다. private·password
글은 **투영 자체가 되지 않는다.** 그래서 주소 지정 가능한 상태가 하나뿐이고,
`axismundi_op_default_public_audience()` 가 `to: [Public]` 과 파생된 `cc`(팔로워·
멘션) 를 매번 계산한다.

**팔로워 전용으로 바꾸는 주소 메타를 두지 않는다.** WP 코어는 그 메타를 모르므로
글은 사이트에서 그대로 공개로 뜬다. 연합본만 좁아지고 **웹사이트가 샌다.** 작성자는
좁혔다고 믿는다. 두 저장소가 같은 질문에 다르게 답하는 부류이고, 템플릿 DB shadow 와
같은 종류의 사고다.

이것은 ActivityPub 의 강제가 아니라 **웹에서 이미 공개된 글을 일관되게 표현한다는
우리 정책이다.** 더 좁히려면 프런트엔드 게이트가 선행 조건이지, 메타 하나로 될 일이
아니다.

### Local Object — 주소를 보존한다

저장소도 읽기 표면도 우리 것이므로 표현력에 제한이 없다. 지금은 주소가
`document_json` 안에만 있고 질의 가능한 형태는 `publicly_readable` 불리언 하나라,
**"누가 볼 수 있나"에 답할 수 없다.** 공개냐 아니냐만 안다.

### Remote Object 캐시 — 판정은 같고, 소유는 다르다

열람 판정은 로컬과 같은 질문이다. 다만 주소는 원본 서버가 정한 것이고 우리는 받은
대로 보존한다. 우리가 정하는 것은 **우리 표면에서 그것을 어떻게 다룰지**뿐이다.

### 독립 미디어는 자기 주소를 저작한다

`Image`·`Video`·`Audio` 를 "딸린 서술자" 로 분류하면 안 된다. WordPress 의
`inherit` 는 **저장 산출물이지 모델이 아니다.** 미디어 라이브러리는 공유 자산이고,
액터가 명시적으로 가시성을 설정하는 방향이다.

실물 선례가 있다. PeerTube 의 `Video` 를 AS2 로 받아 보면(2026-11-14 실측) 주소를
전부 싣는다.

```txt
to            [ …activitystreams#Public, <채널 Group> ]
cc            [ <계정>/followers ]
audience      <채널 Group>
attributedTo  [ Person, Group ]
sensitive     false
```

`audience` 에 소유 채널 Group 이 들어가고 **`to` 에도 같은 값이 들어간다.** Forum 이
프리셋 밖에서 따로 만들던 Lemmy 모양과 같은 계열이므로, 저층이 표현해야 할 것이
분명해진다(§10). 복수 귀속이 실전에 있다는 것도 같이 확인된다.

대조가 선명하다. Mastodon·Misskey 는 첨부를 **AS2 객체로 내놓지 않는다** — 주소가
없는 것이 아니라 정체성이 없다. 그래서 종속 첨부의 질문은 "주소가 무엇인가" 가
아니라 **"객체로 내놓을 것인가"** 이고, 내놓는 결정 자체가 가시성 결정이다.

### 예외 — Actor 와 Collection

둘은 공통 판정에 그대로 올리지 않는다. Actor 는 조회 정책이 따로 있고(위 §2),
Collection 은 자기 정책을 가진다 — `followers` 가 공개인지, `manuallyApprovesFollowers`
가 무엇을 바꾸는지는 컬렉션의 문제이지 항목 하나의 주소 문제가 아니다.

## 5. 주소 저장과 정규화

**보존이 먼저, 해석은 나중이다.** 저장은 `field` 와 대상 URI 를 있는 그대로 남기고,
Public·팔로워 컬렉션·개별 Actor·Group 의 구분은 **해석 단계**에서 한다.

```txt
보존   field          to · cc · bto · bcc · audience
       대상 URI       원문 그대로
파생   인덱스         판정을 빠르게 하기 위한 것. 권위가 아니다
```

인덱스가 대상 종류를 들고 있으면 판정이 종류별 디스패치가 된다. 종류마다 연산이
다르기 때문이다 — 상수, 관계 조회, 동치, 멤버십 조회. 다만 **종류를 나누는 것과 그
종류가 열람을 허용한다고 정하는 것은 다르다**(§6, §11).

### `bto`/`bcc` 는 판정에 쓰고 표현에서 뺀다

[AP §6] — *"The server MUST remove the bto and/or bcc properties, if they exist, from
the ActivityStreams object before delivery, but MUST utilize the addressing originally
stored on the bto / bcc properties for determining recipients."* 부록 B.11 은 표시에서도
생략하라고 하고, 저장 방식은 구현 자유로 둔다.

그래서 bcc 된 액터는 **볼 수 있지만 드러나면 안 된다.** 보존 구조가 `field` 를 들고
있으면 판정에는 다섯을 다 쓰고 직렬화에는 셋만 내보낼 수 있다. 분리를 호출부마다
손으로 하지 않는다.

## 6. 열람 판정

세 저장소가 **하나의 판정 경로**를 쓴다. 질문이 같기 때문이다 — 이 요청자에게 이
GET 을 돌려주는가.

`publicly_readable` 과 그 친척들은 **`to` 에 Public 이 있는지의 캐시가 아니라 실제로
익명 열람을 허용하는지의 캐시**다. 둘은 다를 수 있고, 다를 때 권위는 판정 쪽이다.

### 컬렉션의 항목은 상속하지 않는다

`likes`·`shares`·`replies` 는 `to`/`cc` 가 없으므로 §3 규칙상 공개다. 그래서
**항목을 박아 넣으면 그 항목의 가시성이 컬렉션을 따라간다.** 좁게 주소 지정된
`Like` 하나가 공개 컬렉션에 나열되는 것만으로 공개가 된다.

**집계도 노출이다.** 숨긴 항목을 `totalItems` 에 무조건 세면 **존재가 드러난다.**
안전한 기본값은 요청자에게 보이는 항목만 세는 것이고, 비공개 반응까지 익명 집계에
포함하려면 그 자체를 명시적 공개 정책으로 정해야 한다. 항목 노출과 집계 노출은 별개다.

그러므로 "수만 보여줄지 활동을 항목으로 보여줄지" 는 표현 선택이 아니라 **가시성
결정**이다. 올바른 동작은 컬렉션 단위 상속이 아니라 **항목마다 열람 판정**이고,
판정을 통과하지 못한 항목은 수에만 기여한다. 첨부 유출(§4) 과 같은 모양이다 —
컨테이너가 공개라는 사실이 내용물의 주소를 덮어쓰면 안 된다.

### 팔로워 범위는 우리가 가진 관계에서 판정한다

`<액터>/followers` 로 주소 지정된 객체를 요청자 R 에게 보일지 판정할 때, 논리적으로
두 질문은 같다 — 발신자의 followers 에 R 이 있는가, R 의 following 에 발신자가
있는가.

**로컬에서는 성능 차이가 없다.** `wp_ax_activity_relations` 에
`relation_identity (relation_type, subject_actor_uri_hash, object_actor_uri_hash)`
유니크 키가 있어 어느 방향이든 한 행을 집는다
([relations.php:46](../includes/relations.php)).

차이는 **그 followers 컬렉션이 원격 액터의 것일 때** 생긴다. 원격 컬렉션은 열거할 수
없고(fetch 가 필요하고 숨겨져 있을 수도 있다), 반대 방향은 우리가 가진 행이다.
그러니 `following` 쪽이 빠른 것이 아니라 **네트워크 없이 답할 수 있는 유일한
방향**이다. 성능 논거는 인덱스를 바꾸면 무너지지만 이 논거는 무너지지 않는다.

**관계의 존재가 아니라 수락을 요구한다.** 팔로우가 성립했다는 권위는 `Accept`
활동이다. 우리 `state` 컬럼은 그것을 기록한 **로컬 장부**이고, "pending" 은 AP 의
상태가 아니다 — 스펙에는 `Accept`/`Reject` 만 있다. 그래서 판정은 행의 존재가 아니라
수락 기록을 본다. 존재만 보면 아직 수락되지 않은 요청자에게 팔로워 전용 글이 보인다.

**`manuallyApprovesFollowers` 는 이 판정의 근거가 아니다.** 근거는 침묵이
중의적이라는 것이다. [AP §7.5] 는 `Accept`/`Reject` 를 SHOULD 로 두고
*"MAY choose to not explicitly send a `Reject`"* 를 허용한다 — 그래서 응답 없음은
조용한 거절일 수도, 보내지 않는 구현일 수도 있다. 침묵을 수락으로 읽으면 **조용히
거절된 것이 "팔로우 중" 이 된다.** 그것이 발신 Follow 를 자동 완료하지 않는 이유이고,
어휘 보고서의 문구가 이유는 아니다 — [apmisc] 는 Miscellaneous Terms 이고, 속성의
용도를 서술할 뿐 프로토콜 동작을 규정하지 않는다.

현재 코드는 이 선을 지킨다: `axismundi_act_local_follow_requires_approval()`
([local-social.php:29](../includes/local-social.php)) 은 **우리 로컬 액터**의 승인
요구만 판정하고 OP 필터로 광고하며, 원격 속성으로 발신 Follow 를 완료시키는 경로는
없다. 원격이 `Update` 로 이 속성을 바꾸면 캐시가 따라가야 하지만 **표시만 바뀌고
관계 상태는 바뀌지 않는다** — 켜도 기존 팔로워가 끊기지 않고, 꺼도 대기 중 요청이
자동 수락되지 않는다.

### 배달 자체가 증거다 — 수신자는 발신자의 판정을 다시 하지 않는다

수락이 오지 않는 Follow 에는 실제 위험이 하나 따라온다. 원격이 내부적으로는 우리를
팔로워로 여기면서 `Accept` 를 보내지 않으면, 우리는 그 액터의 팔로워 전용 객체를
**inbox 로 받으면서도** 우리 읽기 판정이 수락 기록을 요구하는 탓에 **스스로 숨긴다.**
받은 것을 우리가 가리는 것이고, 이것은 자동 완료로 풀 문제가 아니다.

풀이는 권위를 나누는 것이다.

**다만 근거는 받은 표현과 그 경로에만 붙는다.** 받은 활동이 **URI 로 가리키는** 객체까지
따라가지 않는다. A 의 개인 inbox 에 `Like` 나 `Announce` 가 도착했다고 해서, 그 활동이
가리키는 원격 객체의 제한된 캐시를 A 에게 열어도 된다는 뜻이 아니다 — 발신자가 판정한
것은 **그 활동을 A 에게 보낸다는 것**이고 그 객체의 열람이 아니다. 그러니 기록도 그렇게
남긴다: **어떤 표현을, 누구에게, 어떤 경로로 받았는가.** 단순 URI 참조에는 열람 권한을
부여하지 않는다. 이 구분은 Follow 수락 상태와 무관하게 성립한다.

```txt
우리가 권위일 때   우리 팔로워 전용 객체에 원격이 GET — 우리 수락 기록을 요구한다
수신자일 때        발신자가 이미 판정했으므로 우리가 다시 판정하지 않는다.
                   읽는 것은 그 배달이 해소한 주소다
```

**증거는 배달이 아니라 그 배달이 해소한 주소다.** 배달 자체를 증거로 삼으면 두 군데서
깨진다. 공개 글은 널리 배달되므로 관계를 말해 주지 않는다. 그리고 **sharedInbox 는
Public 전용 수신 엔드포인트가 아니다** — [AP §7.1.3] 은 여러 팔로워에게 갈 배달을
sharedInbox 로 합치는 것을 명시적으로 허용한다. 그러니 거기 도착했다는 사실만으로는
**어느 로컬 액터에 대해서도** 수락이나 열람 권한을 추론할 수 없다.

```txt
Public 을 지목                     누구에게나. 관계와 무관하다
이 액터를 직접 지목                그 액터에게. 직접 주소지정이지 팔로우의 증거는 아니다
발신자 followers 지목 + 개인 inbox  그 액터에게. 발신자가 자기 멤버십을 해소했다
sharedInbox 도착                   추론 불가. 그 액터를 수신자로 확인할 근거가 따로 필요하다
```

sharedInbox **컬렉션을 읽을 때** 비공개를 노출하지 않는 것은 별개의 규칙이고, 그쪽은
이미 구현돼 있다 — `received_via='shared' AND has_public_audience=1` 로 걸고
hydration 에서 `audience_is_public()` 을 다시 확인한다
([inbox.php:62](../includes/inbox.php)). 두 규칙을 섞어 "sharedInbox 는 공개만
받는다" 로 읽으면 안 된다.

## 7. 관계 — Follow 의 상태

열람 판정이 "수락된 관계" 를 묻기 때문에 여기 둔다. 관계 자체는 주소 문제가 아니다.

### Accept 미수신은 수락 미확인이다 (실측 2026-11-14)

§7.5 가 SHOULD 이므로 "Accept 를 보내지 않는 구현" 을 상정해야 하는지가 문제였다.
주요 구현 다섯을 재 보면 **만장일치로 보낸다.**

```txt
Mastodon   AuthorizeFollowService → AcceptFollowSerializer → DeliveryWorker   로직 읽음
Lemmy      AcceptFollow::send — Follow 수신 네 분기                            로직 읽음
Misskey    ApRendererService.renderAccept, UserFollowingService 가 사용        심볼 확인
Pixelfed   Verb/AcceptFollow 트랜스포머 + FollowAcceptPipeline                 심볼 확인
PeerTube   send-accept.ts, process-follow.ts 가 사용                           심볼 확인
```

**이것이 보여 주는 것은 Accept 가 널리 쓰인다는 사실이다.** 모든 경로에서 반드시
전송한다는 증거도, 중복 `Follow` 에 `Accept` 를 **재전송한다**는 증거도 아니다. §7.5 는
지연을 허용하고 명시적 `Reject` 생략도 허용한다. 그래서 Accept 미수신은 고장이 아니라
**수락 미확인**이다.

유실 두 경우는 우리 쪽에서 **구별할 수 없다.** 우리 `Follow` 가 닿지 않았는지, 그쪽
`Accept` 가 닿지 않았는지 알 방법이 없다. 재시도는 **복구 수단이지 복구 보장이
아니다** — 첫 `Follow` 가 유실됐다면 도움이 되지만, `Accept` 만 유실되고 상대가 중복
`Follow` 를 무시하면 재시도로도 풀리지 않는다.

그러므로 UI 는 처음에 **요청 보냄**, 오래 지나면 **아직 수락 확인 안 됨**이다. 수락을
꾸며내지도, 고장이라 단정하지도 않는다.

그리고 그 고장 상태에서도 §6 의 주소 기반 판정이 안전망으로 남는다 — 배달이 해소한
주소로 보이는 것은 보인다.

### 짝짓기 키는 `Follow.id` 다

`Follow` 에 대한 정상 응답은 `Accept(Follow)` 이고, `Accept.object` 가 원래 `Follow`
활동을 가리킨다. 그래서 상태는 **`Follow.id` 별로** 처리한다.

```txt
Follow F1 → Accept A1(object: F1)
Undo   U1(object: F1)   선택적으로 Undo U2(object: A1)
Follow F2 → Accept A2(object: F2)
```

늦게 도착한 `A1` 은 F1 에만 영향을 주고 F2 의 상태를 바꾸지 않는다. 같은 `U1` 이
재배달되면 이미 처리했으므로 아무것도 새로 만들지 않는다 — 그것이 멱등이다.

**이미 그렇게 구현돼 있다.** 수신 `Accept`/`Reject` 를 `object_uri_hash` = 해당
`Follow` 활동 URI 의 해시로 집고([relations.php:265](../includes/relations.php)),
순서가 뒤바뀐 도착을 재조정하며([:295](../includes/relations.php)), `Undo(Accept)` 를
증거로 다룬다([:329](../includes/relations.php)). `initiating_activity_uri` 와
`termination_activity_uri` 가 그 짝이다.

`Undo(Accept)` 를 **보내는** 것은 이전 수락의 효력이 끝났음을 알리는 확장으로 괜찮지만,
두 번째 `Accept` 를 구별하거나 중복을 막는 데 **필요하지는 않다.** AP 의 `Undo` 규칙은
`Undo(Follow)` 에 `Undo(Accept)` 로 응답하라고 정의하지 않는다. 상대 구현이 그 뜻을
이해할 때만 더한다.

### 재시도와 취소는 다른 전이다

```txt
같은 요청의 재시도   기존 Follow 의 id 를 유지해 다시 전달한다. 같은 요청을 한 번 더
                     도달시키는 것이고, 새 관계를 만들지 않는다. 상대가 Accept 를 다시
                     보낸다는 보장은 없다
취소 후 재요청       Undo 가 기존 Follow 를 가리키고, 새 Follow 는 새 id 를 쓴다.
                     늦게 도착한 옛 Accept 가 새 요청을 완료시키면 안 된다
```

UI 도 **전송 재시도**와 **요청 취소 후 다시 보내기**를 구분해야 한다.

**자동 `Undo` 는 할 이유가 없다.** 금지해야 할 위험이라서가 아니라 **풀 문제가 없기
때문이다.** 짝짓기가 `Accept.object = Follow.id` 이므로 늦게 도착한 `A1` 은 F1 에만
걸리고 F2 를 완료시킬 수 없다. 재시도는 같은 `Follow` id 를 유지하므로 그 전에 정리할
상태도 없다. 그러니 `Undo` 는 **사용자의 의사**(팔로우 중단, 요청 취소) 일 때만
보내고, 복구 절차의 일부가 아니다. 그렇게 쓰면 상대가 이미 들고 있을 관계를 아무
이득 없이 버리게 된다 — 그것은 결과이고, 이유는 위의 것이다.

**이것을 "tentative accept" 로 해석하지 않는다.** 그러면 표준에 없는 관계 상태를 또
하나 발명하는 것이고, `pending` 과 같은 함정이다. 관계는 수락 전까지 요청 중으로
두고, 가시성 질문만 답한다.

그래서 §4.3 의 "판정은 같다" 에 단서가 붙는다 — **질문은 같고 권위가 다르다.** 원격
객체는 세 경우로 갈린다.

```txt
배달받음              주소지정된 로컬 액터에게 보인다
가져왔고 Public       누구에게나 보인다
가져왔고 비공개       애초에 가질 이유가 없다. 있다면 보이지 않는다
```

이렇게 하면 없는 관계를 만들지 않으면서(발신 Follow 는 수락 전까지 "요청 중") 배달받은
콘텐츠가 보인다. §2 의 두 축을 캐시에 적용한 것이다.

**미결로 둔다** — `audience: Group` 의 멤버십이 열람을 허용하는가. Group URI 는
보존하되, 그것을 보자마자 ACL 로 해석하지 않는다. 그 결정에는 그룹 멤버십의 소유와
원격 그룹의 신뢰 범위가 함께 걸린다.

## 8. 배달

열람 판정과 다른 축이다. `Public` 이 목적지가 아니라는 것(§2)이 그 증거다.

### 어디로 보내는가 — 주소의 종류가 엔드포인트를 정한다

**필연과 정책을 나눠야 한다.**

```txt
압축 불가 (필연)   bto · bcc 에서만 유도된 수신자. 객체에서 제거되므로 라우팅 불가
압축 가능 (상한)   to · cc · audience 에서 유도된 수신자 — 주소가 객체에 남아 있다
첫 구현 (정책)     컬렉션에서만 나온 수신자는 sharedInbox,
                   **개별 지목(to·cc 의 액터 URI)은 개인 inbox**
```

상한과 첫 구현이 다른 이유는, 압축이 **상대가 그 주소를 우리와 같은 수신자 집합으로
해소한다는 가정**을 필요로 하기 때문이다. 특히 `audience: Group` 만으로는 상대가 그것을
배달 대상으로 해석할지 확정할 수 없다. 그러니 상한까지 열지 않고 컬렉션 유도분만
압축하는 것으로 시작하고, 더 넓은 압축은 **상호운용 검증 뒤에** 연다. 이것은
[AP §7.1.3] 의 금지가 아니라 **우리 정책이다.**

근거 둘이 맞물린다. §7.1.3 은 *"a server MAY reduce the number of receiving actors"*
로 압축을 허용하고, 수신 서버가
*"participates in determining targeting and performing delivery to specific inboxes"*
이므로 **수신자는 객체의 주소를 보고 라우팅한다.** 그런데 [AP §6] 은 배달 **전에**
객체에서 `bto`/`bcc` 를 제거하라고 한다. 그러면 라우팅 근거가 사라지므로 bcc 된 액터가
수신자인지 알 방법이 없고, sharedInbox 로 보내면 **영영 도달하지 않는다.** 선호가
아니라 두 조문의 곱이다.

개별 지목(`to` 에 액터 URI)도 압축 대상이다. 이름이 객체에 남아 있으므로 수신자가
라우팅할 수 있고, 같은 호스트의 개별 수신자가 여럿이면 실제로 줄어든다. 줄일 fan-out
이 없다는 것은 수신자가 그 호스트에 하나일 때만 참이다.

### 압축 프리프로세서

수신자 집합이 정해진 뒤, **배달 엔드포인트를 정하는 단계가 따로 있어야 한다.**
수신자가 여섯이고 그중 셋이 같은 `sharedInbox` 를 쓰면 여섯 번이 네 번이 된다.

```txt
① URI 종류 판별    컬렉션인가 액터인가. URI 문자열만으로는 알 수 없다
② 컬렉션 펼치기    우리 액터의 팔로워는 relations 에서 로컬로 해소한다
③ sharedInbox 묶기 액터의 endpoints.sharedInbox 로 그룹화
④ 엔드포인트 산출  그룹마다 한 번 + 공유 엔드포인트가 없는 액터마다 한 번
                   + bto·bcc 수신자는 각자 개인 inbox
```

①이 조용히 어려운 부분이다. 액터 문서에는 `inbox`·`outbox`·`endpoints` 가 있고
컬렉션에는 `type: Collection`/`OrderedCollection` 과 `items`/`first` 가 있으므로,
**해소해 봐야 안다.** 다행히 흔한 경우는 발신 액터 **자신의** 팔로워 컬렉션이고 그것은
우리가 로컬로 안다. 원격 컬렉션이 `to` 에 들어오는 경우는 드물고, 비공개일 수 있어
펼칠 수 없다 — 펼칠 수 없으면 그 멤버에게 배달할 방법이 없다는 것을 **실패로
인정해야 하고**, 대신 컬렉션 소유자에게 보내는 것으로 바꿔치기하지 않는다.

### 현재 구현 (실측 2026-11-14)

압축은 **이미 일어난다.** 수신자마다
`endpoints['shared_inbox'] ?? endpoints['inbox']` 로 해소하고
([transport.php:280](../../axismundi-activitypub-bridge/includes/transport.php))
`array_values( array_unique( $inboxes ) )` 로 접는다([:294](../../axismundi-activitypub-bridge/includes/transport.php)).
배달 큐도 inbox 단위로 유니크하다(`UNIQUE KEY recipient (delivery_id, inbox_hash)`,
[delivery.php:77](../../axismundi-activitypub-bridge/includes/delivery.php)). 팔로워
펼치기도 있고([:244](../../axismundi-activitypub-bridge/includes/transport.php)),
원격 컬렉션을 펼칠 수 없을 때는 조용히 버리지 않고
`axismundi_activitypub_bridge_unresolved_delivery_candidate` 로 드러낸다 — 위에서
"실패로 인정해야 한다" 고 적은 것이 이미 그렇게 돼 있다.

#### 결함 — bcc 수신자가 sharedInbox 로 접힌다

```txt
repository.php:453   저장 audience 는 to·cc·bto·bcc·audience 다섯을 한 목록으로 정규화
repository.php:590   payload 에서는 bto·bcc 를 제거 — §6 대로 맞다
transport.php:280    수신자마다 shared_inbox 우선 — bcc 에서 온 수신자도 같이
```

그래서 bcc 수신자의 배달이 **그 액터를 지목하지 않는 객체**를 들고 호스트 공유
엔드포인트에 도착한다. 수신 서버는 라우팅할 근거가 없다. `prepare_local_note` 가
`bcc` 를 받으므로 도달 가능한 경로다.

원인은 정책이 아니라 **출처 상실**이다. 다섯 필드를 한 후보 목록으로 평탄화하면 어떤
수신자가 `bcc` 에서만 왔는지 알 수 없다. 고치려면 해소 단계까지 출처를 들고 가서,
`bto`/`bcc` 에서만 유도된 수신자는 개인 inbox 로 보내야 한다.

#### 수신자가 하나면 공유 엔드포인트를 쓰지 않는다

`is_direct_group_submission` 은 후보를 Group URI 하나로 줄이는데, 그 뒤 일반 해소가
`shared_inbox ?? inbox` 를 적용하므로 **원격 호스트의 공유 엔드포인트로 간다.**
수신자가 하나인데 압축한 것이다.

§7.1.3 의 목적은 fan-out 축소이고, 수신자가 그 호스트에 하나뿐이면 줄일 것이 없다.
그러니 **공유 엔드포인트는 둘 이상이 그리로 모일 때만 쓴다.** 그러면 Group 제출이
자연히 Group 자신의 inbox 로 가고, Group 을 위한 특례를 만들 필요가 없다. Group 은
받아서 `Announce` 하는 **행위자**이므로 우리 배달이 그 처리에 의존한다 — 라우팅 홉을
하나 줄이는 편이 맞다.

이 규칙은 이미 만들어진 목록에서 계산되므로 출처를 들고 다닐 필요가 없다. bcc 결함은
그것과 별개로 출처가 필요하다(같은 호스트에 `to` 수신자와 `bcc` 수신자가 함께 있을 수
있다).

## 9. 입회와 Announce

커뮤니티에 무엇이 들어오는지는 배달이 아니라 입회의 문제다. 주소가 그 판정의 입력이라 여기 둔다.

### 행위하는 Group 이 받은 것을 아무거나 Announce 하지는 않는다 (실측 2026-11-14)

우리 Group 액터는 행위할 수 있으므로 Person 이나 다른 Group 을 팔로우할 수 있고,
그러면 팔로워 주소 지정된 글의 **수신자로 잡힌다.** 그것이 커뮤니티에 올라가지 않는지
확인했다. 세 겹으로 막혀 있다.

```txt
① 명시 지목 요구   Group URI 가 객체의 context·audience·to·cc 또는 액티비티의 다섯
                   필드에 나와야 한다 — remote_root_addresses_group()
② 타입 제한        기본 Article·Page — is_root_object_type(), 필터로 확장 가능
③ pending 입회     수신 제출은 admission_state='pending' 으로 들어가고 Announce 는
                   별도 발행 단계에서만 난다 — publish_validated_pending_entry()
```

팔로워로서 받은 글은 `to: [<상대>/followers]` 라 Group URI 가 어디에도 없으므로 ①에서
끝난다. 반대로 상대가 우리 Group 을 `cc` 하면 통과하는데, 그것은 Lemmy 식 제출 관행이라
의도된 동작이다 — 그리고 ③ 이 있으므로 입회가 곧 발행은 아니다.

#### 타입 제한은 풀어야 하고, 그 신호는 `audience` 다

위 ②의 `Note` 제외는 의도적이고 근거가 주석에 적혀 있다 — *"A `Note` with no `inReplyTo`
addressed to a Group is ambiguous between a NodeBB topic and an ordinary microblog post
that merely mentioned the Group"*, 그래서 *"needs a signal beyond the type, and that is
its own piece of work."*

**그 신호는 `audience` 다.** 실측 둘이 같은 모양이다.

```txt
SocialHub 의 토픽 루트    type: Note · name: 제목 · audience: 카테고리 액터
(2026-11-14 실측)        to: [Public, 같은 액터] · context: 스레드 컬렉션 · inReplyTo 없음
PeerTube Video           audience: 채널 Group (to 에도 같은 값)
```

지나가는 멘션은 `cc` 와 `tag` 에 들어가지 **`audience` 에는 들어가지 않는다.** 그러니
`Note` 를 루트로 받되 `remote_root_addresses_group()` 의 느슨한 판정(`context`·
`audience`·`to`·`cc` 아무 데나) 대신 **`audience` 또는 `context` 가 Group 을 지목할
것**을 요구하면 주석이 걱정한 모호성이 닫힌다.

**거부되는 것은 루트일 때의 `Note` 뿐이다.** 답글 경로는 이미 `Note` 를 받는다 —
`'Note' === object_type` 이고 `in_reply_to_uri` 가 차 있으면 `entry_type: 'reply'` 로
`pending` 입회한다([inbound-topics.php:192](../../axismundi-forum/includes/inbound-topics.php)).
토픽의 댓글은 그 경로로 들어오고 Group 이 `Announce` 한다. 루트는 `in_reply_to_uri` 가
비어 있을 것을 요구하므로([:112](../../axismundi-forum/includes/inbound-topics.php))
둘은 겹치지 않는다.

**그리고 지금은 우리끼리 연합이 되지 않는다.** 우리 컴포저는 `audience: Group` 인
`Note` 를 보낼 수 있는데(C2S 저작은 Note 로 제한돼 있다) 루트 게이트가 `Note` 를
거부한다. Forum 토픽은 `Article` 로 나가므로 그 경로는 멀쩡하고, 컴포저로 Group 에
올린 **새 글**만 상대가 우리 코드를 돌려도 토픽이 되지 않는다. 답글은 된다.

#### 답글의 부모는 검증되지 않는다

`in_reply_to_uri` 는 **존재 여부로만** 쓰인다. 그 부모가 이 커뮤니티의 글인지는 확인하지
않으므로, 아무 객체에 단 답글이라도 Group 을 `cc` 하기만 하면 댓글 항목으로 들어온다.
경계는 둘뿐이다 — `admission_state: 'pending'` 과 `remote_actor_can_submit_comment()`.
발행되지는 않지만 **큐에는 쌓인다.** 루트 쪽을 `audience` 로 좁힐 때 답글 쪽도 같이 볼
자리다.

**남는 것은 Announce 가 아니라 표면의 질문이다.** 행위하는 Group 은 팔로워로서 받은
것을 inbox 에 쌓는다. "이 Group 이 받은 것" 을 보여 주는 화면이 생기면 거기에 커뮤니티
제출과 팔로우 타임라인이 섞인다. 둘은 다른 것이므로 섞어 보여 줄지, 나눌지는 그 화면을
만들 때 정한다.

### 수신 쪽은 이미 된다

`audience` 에 우리 Group 이 있으면 **sharedInbox 로 받은 액티비티도** 타깃이 된다 —
`receipt_targets()` 가 다섯 필드를 액티비티와 그 객체 양쪽에서 모아 로컬 액터로
해소한다([actor-inbox.php:80](../../axismundi-activitypub-bridge/includes/actor-inbox.php)).
그 제출에 대한 Group `Announce` 도 기록된다
([distribution.php:215](../../axismundi-forum/includes/distribution.php)).

**이 절은 제가 한 번 틀렸던 자리다.** `head -8` 로 잘린 grep 결과에서 "발신 경로는
sharedInbox 를 쓰지 않는다" 고 결론했었다. 잘린 출력에서 부재를 읽은 것이다 —
[[validate-measurement-tooling]] 의 사례가 하나 늘었다.

**미결로 둔다** — 가시성을 좁히는 `Update` 를 직전 수신자에게 보낼 것인가.
무효화 통지와 새 표현을 받을 권한은 다르다. 주소 이력을 저장해도 **GET 으로 가져간
서버를 모두 알 수 없고**, 원격 복사본의 회수를 보장할 수도 없다. 그러니 "이전
수신자에게 새 표현을 보낸다" 는 규칙을 지금 세우지 않는다.

Forum 은 공통 주소 자료구조를 재사용한다. 다만 **Group `Announce` 의 배달 정책까지
일반 컴포저와 같다고 가정하지 않는다.**

## 10. 프리셋과 UI 표시

명시 주소가 바닥이고, 프리셋은 그 위의 얇은 층이다. 컴포저가 POST 전에 프리셋을
명시 주소로 펼치고 서버는 검증한다 — 서버가 단어를 보고 청중을 만들어내지 않는다.
이것은 FEP-c3ab 가 공개로 주장하는 규칙("서버는 청중을 만들어내지 않는다") 과 로컬
구현을 같은 말로 만드는 일이기도 하다.

역함수의 `custom` 은 **UI 표시 전용 라벨**이지 가시성 규칙이 아니다. 저장된 주소가
어느 프리셋에도 맞지 않을 때 표시할 이름이 필요할 뿐이다.

`audience_visibility()` 가 수신측 판정까지 쥐고 있으므로, 이 층을 옮기면 읽기 경로가
같이 움직인다. 작업을 쪼갤 때 그 경계를 먼저 끊는다.

## 11. 지금 정하지 않는 것

```txt
audience: Group 의 멤버십이 열람을 허용하는가
가시성을 좁히는 Update 의 배달 대상
Group Announce 의 배달 정책이 일반 컴포저와 같은가
wp_posts 를 더 좁히는 경로 (프런트엔드 게이트가 선행)
Tombstone 의 가시성 — id 가 있고 to/cc 가 없으니 §3 규칙으로는 공개인데,
  팔로워 전용 글의 Tombstone 이 공개면 그런 글이 존재했고 삭제됐다는 사실이
  누설된다. 내용은 없어도 존재는 누설이다. 410 정책이 이미 이 근처를 지났다
```

### FEP 후보 — `Public` 으로 주소 지정되지 않은 객체의 익명 조회

임시 제목: **Anonymous Retrieval of ActivityPub Objects Without Public Addressing**

**질문 하나로 좁힌다.**

> When may an ActivityPub object that is not explicitly addressed to `Public` be served
> to an unauthenticated requester?

"비공개 객체" 라고 부르지 않는다. 그렇게 부르면 익명 접근을 막아야 한다는 결론을 이미
내려 버린다 — 공개 Actor 문서는 `Public` 주소 없이도 익명 조회되고, 반대로 `id` 가
있어도 인가가 필요한 객체가 있다.

```txt
주소가 아예 없다             어느 쪽도 단정하지 않는다
주소는 있는데 Public 이 없다   어느 쪽도 단정하지 않는다
```

둘을 구분하되 **그 사실만으로 허용도 금지도 하지 않고**, 무엇을 근거로 판단할지를
제안한다.

**가장 선명한 동기는 스펙 안에 있다.** `bto: Public` 은 허용되고, §6 이 배달 전에 그
필드를 제거하며, B.11 은 표시에서도 생략하라고 한다(SHOULD). 그래서 **내준 표현에
`Public` 이 없는데 §5.6 이 익명 접근을 요구하는** 객체가 가능하다. 가져온 문서의 주소가
설계상 전체 주소가 아니라는 뜻이고, 그러면 **그 문서만 보고 열람 규칙을 유도하는 모든
방식이 근거를 잃는다**(§3). 관례가 바로 그것을 하고 있다. [AP §5.6] 의 `Public` 활동 규칙은 그대로 두고, §3.2 가 서버 정책에 맡긴 조회
인가 중 **익명 접근만** 좁혀 다룬다. 배달 대상 선정과 인증된 팔로워의 접근까지 한꺼번에
표준화할 필요가 없다.

**이것은 관례의 표준화가 아니라 교정이다.** §2 에 그 결함 넷을 적었다 — 합의 없는
환산, 부재의 불일치, `bto`/`bcc` 때문에 구조적으로 불가능한 재구성, 그리고 §3.2 가
원본 서버에 맡긴 인가를 원격의 복원값이 대신하는 권위 역전. 그러니 골자는 "주소가
ACL 이다" 를 선언하는 것이 아니라 **주소를 열람 근거로 언제 신뢰할 수 있고, 주소가
없을 때 무엇을 추론해서는 안 되는지**를 정하는 것이다.

기존 FEP 를 뒤졌지만 이 자리를 차지한 것이 없다 — FEP-7502 는 `Public` 으로 **주소
지정된** 객체에 인증을 요구하는 서버를 다루고, FEP-cd47 은 위치 간 식별 가능성 분류다.
우리가 확인한 범위에서 그 환산을 일반화한 FEP 는 없다.

#### 제안할 판정 패턴

`Public` 컬렉션 자체가 배달 대상이 아닌 **가상의 개념**이므로, 누구나 GET 할 수 있는
대상이면 주소에 그것이 **생략돼 있다고 해석하는 것**이 자연스럽다. 그 해석을 규범으로
만들어 구현들이 각자 다르게 추측하지 않게 하는 것이 제안의 골자다.

**누가 하는 일인지로 나눠야 단단해진다.**

```txt
원본이 한다      addressing 에 Public (bto·bcc 포함) → 공개로 응답한다
                 활동이면 §5.6 이 요구한다. 독립 객체·컬렉션으로의 확장이 제안 내용이다
                 Public 없음 + attributedTo 의 followers → 그 팔로워에게    §3.2. 권고
요청자가 안다     요청해 보고 안다. 인증 없이 **전체 표현**이 왔으면 공개다
범위 밖          id 가 없으면 답할 GET 이 없다
금지             캐시된 문서의 주소에서 역산하기 — `bto: Public` 때문에 불가능하다
```

세 군데를 명시해야 한다.

**`id` 가 없는 것은 비공개가 아니라 범위 밖이다.** 공개 부모 표현 안의 인라인 객체는
`id` 없이도 보인다. 답해야 할 독립 GET 이 없을 뿐이다(§3).

**"GET 으로 얻어진다" 는 전체 표현이어야 한다.** 어떤 서버는 익명 요청에 **축약된
표현**을 내준다. 응답이 돌아왔다는 사실만으로는 부족하고, 그러면 축약본을 공개 전체로
오해한다.

**followers 는 "누구의" followers 인지 고정해야 한다.** `attributedTo` 의 followers
컬렉션이어야 한다. 그냥 "followers 컬렉션이 있으면" 으로 두면 **남의 followers 를
지목한 객체가 그쪽 팔로워에게 열리는** 규칙이 되고, 원본은 그 멤버십을 평가할 수조차
없다. Mastodon 수신 코드도 발신 계정의 followers URL 인지를 확인한다(§2).

아래 둘은 그 **밑에** 두는 쟁점이다. 각각으로는 사소해 보이고 위 질문 안에서만 값이 있다.

```txt
id 의 뜻        이 표현에 id 가 없으면 그 id 로 독립 조회·상호작용 지정이 불가하다.
                공개 여부나 수명의 증거는 아니다 (§3)
무주소 기본값   정하지 않는 것이 답일 수 있다. 정한다면 저작 쪽 단서가 붙는다 —
                부재를 공개로 읽는 것은 fail-open 이고, 투영 버그가 공개 선언이 되는
                설계를 표준으로 권할 수는 없다
```

그 안에 §3 의 좁은 조각이 들어간다 — **주소를 가질 수 없는 객체.** 범위는 미디어가
빠지면서 좁아졌다 —
독립 미디어는 자기 주소를 저작하므로(§4) 남는 것은 **다른 것을 대체하거나 서술하는
객체**다: `Tombstone`(대체한 것), `Profile`(`describes`), `Relationship`(`subject`).

**FEP-c3ab 와의 경계를 반드시 명시해야 한다.** c3ab 규칙 4 는 *"a server MUST NOT
read the absence of addressing as either a grant or a restriction"* 라고 적었고, 이
규칙은 부재에 뜻을 부여한다. 층이 다르다 — c3ab 는 참조형 `Create` 를 처리하는
**서버의 판단**을, 이쪽은 객체를 받은 **수신자의 해석 기본값**을 말한다. 그 선을 안
그으면 리뷰어가 두 FEP 가 반대로 말한다고 읽는다.

그리고 Q5 와 같은 교훈이 걸린다 — 구현과 측정이 먼저다. 특히 종속 첨부에 대한 우리
답이 둘이다: 첨부 페이지는 부모의 비공개를 **상속하지 않고**(의도적, 근거가 주석에
있음), `sensitive` 는 **상속한다**(fail-closed). 같은 컨테이너·같은 첨부인데 한
속성은 상속하고 한 속성은 안 한다. **왜 다른지를 일반화할 수 있을 때** 쓸 수 있다.

**근거 없이 미결을 결정으로 바꾸지 않는다.** 보존된 주소는 어느 쪽으로 정하든
충분하도록 두는 것이 이 문서의 목적이다.

## 작업 순서

재면서 드러난 것이 둘로 갈린다 — **지금 틀린 것**과 **아직 없는 층**이다. 전자가 먼저다.
없는 층을 세우는 중에 고치려 들면 둘 다 반쯤 된다.

### 지금 틀린 것

```txt
A  수신자 하나인데 공유 엔드포인트          목록에서 계산. 출처 불필요        §8
   Group 제출 홉이 덤으로 사라진다
B  bto·bcc 수신자가 공유 엔드포인트로 접힘   출처 필요. A 로 해결되지 않는다   §8
C  Note 루트 미수용 + audience 신호 미사용   Forum 게이트. 자기연합 간극       §9
D  답글의 부모 미검증                        C 와 같은 자리                    §9
E  likes·shares 항목 상속 여부               **코드 미확인. 재는 것이 먼저**   §6
```

A·B 는 `BRIEF-RECIPIENT-RESOLUTION.md` 로 분리했다. C·D 는 Forum 플러그인이라 별건이고,
E 는 규칙만 있고 구현을 보지 않았으므로 측정이 선행한다.

### 아직 없는 층

```txt
① 판정 경로 하나로        세 불리언(has_public_audience · publicly_readable ·
                          publicly_listable)의 뜻을 맞추고 호출부를 모은다.
                          권위 입력이 둘이다 — 우리 수락 기록, 배달이 해소한 주소
② 주소 보존               field + 대상 URI. 인덱스는 파생
③ 직렬화 분리             bto/bcc 는 판정에만
④ 프리셋을 위로           컴포저가 펼치고 서버는 검증
⑤ Forum 복제 흡수         자료구조만. 배달 정책은 따로
```

①②는 서로 독립이고, ③은 ②를 전제한다. ④는 ①의 역함수 경계를 끊은 뒤다 —
`audience_visibility()` 가 수신측 판정까지 쥐고 있다.

## 측정 근거

```txt
2026-11-14  세 저장소의 공개 불리언 — repository.php:47, local-objects.php:27,
            remote-objects.php
2026-11-14  wp_posts 는 투영 게이트가 publish+암호없음+공개조회 요구,
            주소는 매번 계산 — post-article.php:168, :246
2026-11-11  resolve_audience 는 단어 넷 + 멘션, 역함수가 수신측 판정을 쥠 —
            audience.php:63, :146
2026-11-11  Forum 이 Group 주소를 따로 만듦 — distribution.php:46
2026-11-14  AP §7.5 는 Accept/Reject 를 SHOULD 로 두고 지연과 Reject 미발송을 허용 —
            침묵이 중의적이라 수락으로도 고장으로도 읽을 수 없다
2026-11-14  §7.1.3 은 여러 팔로워 배달을 sharedInbox 로 합치는 것을 허용 —
            sharedInbox 는 Public 전용 수신 엔드포인트가 아니다
2026-11-14  수신 Accept/Reject 는 이미 Follow 활동 URI 해시로 짝지어진다 —
            relations.php:265, :295, :329
2026-11-14  **폐기된 관측** — "발신 경로에 sharedInbox 압축 없음". head -8 로 잘린
            grep 에서 부재를 읽었다. 실제로는 압축한다(§8). 배달 큐가 inbox 단위
            유니크인 것은 맞다 —
            bridge delivery.php:77, actor-inbox.php 는 수신만
2026-11-14  manuallyApprovesFollowers 는 Miscellaneous Terms 의 어휘 항목.
            용도 서술이지 프로토콜 규정이 아니다 — apmisc 4.1 Notes
2026-11-14  PeerTube Video 가 to/cc/audience/attributedTo 를 전부 실음.
            audience 와 to 에 같은 채널 Group. Accept: application/activity+json
            으로 skeptikon.fr 의 공개 영상 하나
```

[AP §5.6]: https://www.w3.org/TR/activitypub/#public-addressing
[AP §3.1]: https://www.w3.org/TR/activitypub/#obj-id
[AS2 어휘]: https://www.w3.org/TR/activitystreams-vocabulary/#dfn-bto
[AP §7.1]: https://www.w3.org/TR/activitypub/#delivery
[AP B.11]: https://www.w3.org/TR/activitypub/#security-not-displaying-bto-bcc
[AP §4]: https://www.w3.org/TR/activitypub/#actors
[apmisc]: https://www.w3.org/community/reports/socialcg/CG-FINAL-apmisc-20260828/#manuallyApprovesFollowers
[AP §7.5]: https://www.w3.org/TR/activitypub/#follow-activity-inbox
[AP §7.1.3]: https://www.w3.org/TR/activitypub/#shared-inbox-delivery
[AP §4.1]: https://www.w3.org/TR/activitypub/#actor-objects
[AP §6]: https://www.w3.org/TR/activitypub/#delivery
