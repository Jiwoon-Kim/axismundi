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

## 1. 개념 — 같은 속성 이름이 두 질문에 쓰인다

```txt
Activity 의 to/cc/audience    누구에게 전달할지
Object 의 to/cc/audience      누구에게 보여줄지
```

둘은 다른 질문이고, 이름이 같다는 이유로 한 코드가 둘 다 처리하면 한쪽이 조용히
다른 쪽의 규칙을 따라간다.

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

## 2. 소스별 공개 정책

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

### 예외 — Actor 와 Collection

둘은 공통 판정에 그대로 올리지 않는다. Actor 는 조회 정책이 따로 있고(위 §1),
Collection 은 자기 정책을 가진다 — `followers` 가 공개인지, `manuallyApprovesFollowers`
가 무엇을 바꾸는지는 컬렉션의 문제이지 항목 하나의 주소 문제가 아니다.

## 3. 주소 저장과 정규화

**보존이 먼저, 해석은 나중이다.** 저장은 `field` 와 대상 URI 를 있는 그대로 남기고,
Public·팔로워 컬렉션·개별 Actor·Group 의 구분은 **해석 단계**에서 한다.

```txt
보존   field          to · cc · bto · bcc · audience
       대상 URI       원문 그대로
파생   인덱스         판정을 빠르게 하기 위한 것. 권위가 아니다
```

인덱스가 대상 종류를 들고 있으면 판정이 종류별 디스패치가 된다. 종류마다 연산이
다르기 때문이다 — 상수, 관계 조회, 동치, 멤버십 조회. 다만 **종류를 나누는 것과 그
종류가 열람을 허용한다고 정하는 것은 다르다**(§4, §7).

### `bto`/`bcc` 는 판정에 쓰고 표현에서 뺀다

[AP §6] — *"The server MUST remove the bto and/or bcc properties, if they exist, from
the ActivityStreams object before delivery, but MUST utilize the addressing originally
stored on the bto / bcc properties for determining recipients."* 부록 B.11 은 표시에서도
생략하라고 하고, 저장 방식은 구현 자유로 둔다.

그래서 bcc 된 액터는 **볼 수 있지만 드러나면 안 된다.** 보존 구조가 `field` 를 들고
있으면 판정에는 다섯을 다 쓰고 직렬화에는 셋만 내보낼 수 있다. 분리를 호출부마다
손으로 하지 않는다.

## 4. 열람 판정

세 저장소가 **하나의 판정 경로**를 쓴다. 질문이 같기 때문이다 — 이 요청자에게 이
GET 을 돌려주는가.

`publicly_readable` 과 그 친척들은 **`to` 에 Public 이 있는지의 캐시가 아니라 실제로
익명 열람을 허용하는지의 캐시**다. 둘은 다를 수 있고, 다를 때 권위는 판정 쪽이다.

**미결로 둔다** — `audience: Group` 의 멤버십이 열람을 허용하는가. Group URI 는
보존하되, 그것을 보자마자 ACL 로 해석하지 않는다. 그 결정에는 그룹 멤버십의 소유와
원격 그룹의 신뢰 범위가 함께 걸린다.

## 5. 배달

열람 판정과 다른 축이다. `Public` 이 목적지가 아니라는 것(§1)이 그 증거다.

**미결로 둔다** — 가시성을 좁히는 `Update` 를 직전 수신자에게 보낼 것인가.
무효화 통지와 새 표현을 받을 권한은 다르다. 주소 이력을 저장해도 **GET 으로 가져간
서버를 모두 알 수 없고**, 원격 복사본의 회수를 보장할 수도 없다. 그러니 "이전
수신자에게 새 표현을 보낸다" 는 규칙을 지금 세우지 않는다.

Forum 은 공통 주소 자료구조를 재사용한다. 다만 **Group `Announce` 의 배달 정책까지
일반 컴포저와 같다고 가정하지 않는다.**

## 6. 프리셋과 UI 표시

명시 주소가 바닥이고, 프리셋은 그 위의 얇은 층이다. 컴포저가 POST 전에 프리셋을
명시 주소로 펼치고 서버는 검증한다 — 서버가 단어를 보고 청중을 만들어내지 않는다.
이것은 FEP-c3ab 가 공개로 주장하는 규칙("서버는 청중을 만들어내지 않는다") 과 로컬
구현을 같은 말로 만드는 일이기도 하다.

역함수의 `custom` 은 **UI 표시 전용 라벨**이지 가시성 규칙이 아니다. 저장된 주소가
어느 프리셋에도 맞지 않을 때 표시할 이름이 필요할 뿐이다.

`audience_visibility()` 가 수신측 판정까지 쥐고 있으므로, 이 층을 옮기면 읽기 경로가
같이 움직인다. 작업을 쪼갤 때 그 경계를 먼저 끊는다.

## 7. 지금 정하지 않는 것

```txt
audience: Group 의 멤버십이 열람을 허용하는가
가시성을 좁히는 Update 의 배달 대상
Group Announce 의 배달 정책이 일반 컴포저와 같은가
wp_posts 를 더 좁히는 경로 (프런트엔드 게이트가 선행)
```

**근거 없이 미결을 결정으로 바꾸지 않는다.** 보존된 주소는 어느 쪽으로 정하든
충분하도록 두는 것이 이 문서의 목적이다.

## 작업 순서

```txt
① 판정 경로 하나로        세 불리언의 뜻을 맞추고 호출부를 모은다
② 주소 보존               field + 대상 URI. 인덱스는 파생
③ 직렬화 분리             bto/bcc 는 판정에만
④ 프리셋을 위로           컴포저가 펼치고 서버는 검증
⑤ Forum 복제 흡수         자료구조만. 배달 정책은 따로
```

①②는 서로 독립이고, ③은 ②를 전제한다. ④는 ①의 역함수 경계를 끊은 뒤다.

## 측정 근거

```txt
2026-11-14  세 저장소의 공개 불리언 — repository.php:47, local-objects.php:27,
            remote-objects.php
2026-11-14  wp_posts 는 투영 게이트가 publish+암호없음+공개조회 요구,
            주소는 매번 계산 — post-article.php:168, :246
2026-11-11  resolve_audience 는 단어 넷 + 멘션, 역함수가 수신측 판정을 쥠 —
            audience.php:63, :146
2026-11-11  Forum 이 Group 주소를 따로 만듦 — distribution.php:46
```

[AP §5.6]: https://www.w3.org/TR/activitypub/#public-addressing
[AP §6]: https://www.w3.org/TR/activitypub/#delivery
