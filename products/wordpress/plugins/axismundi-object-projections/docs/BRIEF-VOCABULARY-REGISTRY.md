# 브리프 — 어휘 레지스트리 (네임스페이스 / 용어 2층 등록)

2026-10-25. 설계 근거는 `products/wordpress/architecture/activitystreams-vocabulary-registry.md`
이고 이 문서는 그 중 **직렬화에 필요한 최소 조각만** 구현 단위로 쪼갠 것이다.
V1(`BRIEF-V1-CONTEXT-IRIS.md`)이 선행이고 완료됐다(10deeac).

## 구현 기록 (2026-10-25)

구현 완료, 리뷰 대기. 상세 계약과 이관 범위는
`IMPLEMENTATION-VOCABULARY-CONTEXT-REGISTRY.md`를 참조한다.
기존 vocabulary snapshot 및 의미/바인딩 API는 유지하고, namespace와 조건부 선언
등록을 확장했다. 승인 바이트 fixture는 변경하지 않았다.

관련 감사 14개를 순차 실행해 모두 통과했다. 전체 134개 전수를 재실행한 것은 아니다.

| 플러그인 | 감사 | 검사 수 |
| --- | --- | --- |
| OP | audit-context-iris | 38 |
| OP | audit-vocabulary-registry | 15 |
| OP | audit-vocabulary-context-registry | 43 |
| OP | audit-object-assembly-baseline | 9 |
| OP | audit-object-renderer | 54 |
| OP | audit-actor-projection | 12 |
| OP | audit-post-article | 23 |
| OP | audit-quote-context | 5 |
| OP | audit-quote-authorizations | 3 |
| OP | audit-collection-contracts | 14 |
| Emoji | audit-emoji-outbound | 41 |
| Emoji | audit-emoji-op-integration | 14 |
| Emoji | audit-emoji-actors-integration | 10 |
| Calendar | audit-collection-projection | 10 |

최종 보강 후 신규 레지스트리 감사 43개·V1 감사 38개·승인 baseline 9개를 재실행해
통과했다. 네 변이는 모두 검출하고 복원했다: prefix 충돌 허용, 무조건 선언,
타입 수집 제거, 명시 IRI 대신 as+이름 유도. 변경 PHP lint 및 diff whitespace 확인도
통과했다. 알려진 기존 실패 네 건은 수정하지 않았다.

일괄 실행 원본 로그는 로컬 `%TEMP%/axismundi-vocabulary-audits.log`에 보관했다.
등록 보강 후 재실행에서 신규 감사가 42에서 43으로 늘었으므로 원본 일괄 로그의
42라는 수와 구별한다.

## 왜 V2 보다 먼저인가

V2 는 `schema` 접두사를 **Actors 가 두 번째로** 선언하게 만든다. media-library 가 이미
선언하고 있으므로, 레지스트리 없이 V2 를 하면 곧 걷어낼 중복 선언을 추가하는 것이다.
레지스트리 문서가 이미 요구사항으로 적어 둔 자리이기도 하다 -- *"Conflicting
re-registration is detected, not merged. The filter merges, so two plugins
registering one IRI with different value shapes or cardinalities resolve by last
writer and then disagree quietly."*

## 가장 중요한 제약 — context 는 네임스페이스당 하나가 아니다

같은 접두사 안에서 선언 의존성이 갈린다. 측정값이다.

```txt
as:attachment  as:image  as:icon  as:Public        규범 AS2 context 가 정의 → 선언 불필요
as:sensitive  as:manuallyApprovesFollowers         Miscellany 가 정의 → purl.archive.org/miscellany/1
as:Hashtag  as:movedTo                             Miscellany 가 정의하나 우리는 안 내보낸다
```

접두사 하나에 선언 상태가 셋이다. 그러므로 **`context` 는 네임스페이스 엔트리가 아니라
용어 엔트리의 필드**다. 네임스페이스 엔트리에 `context` 를 두면 `as:attachment` 를
내보내는 모든 문서에 miscellany 가 붙는다 -- V1 이 통과시킨 음성 검사를 깨뜨리는 회귀다.

## 엔트리 두 층

```txt
네임스페이스
  prefix       schema
  base_iri     https://schema.org/          @context 에 들어가는 **식별자**. 불변.
  reference    어휘 정의 문서 URL           사람이 읽는 곳. 바뀌면 갱신. @context 에 안 들어감.
```

`base_iri` 와 `reference` 를 왜 나누는지는 측정된 사례가 있다.

```txt
toot       http://joinmastodon.org/ns#     404   reference: docs.joinmastodon.org/spec/activitypub/
misskey    https://misskey-hub.net/ns#     200
ldp        http://www.w3.org/ns/ldp#       200   규범 context 가 http 로 선언, 문서는 https 로 링크
peertube   https://joinpeertube.org/ns#    200 → docs.joinpeertube.org/api/activitypub
```

`toot` 의 base IRI 는 죽었다. 그것을 reference 로 바꾸면 우리 `toot:discoverable` 이
아무도 쓰지 않는 술어로 확장된다. **죽은 IRI 는 틀린 IRI 가 아니라 역참조되지 않는
IRI 다.** 필드 이름과 주석으로 그 구분을 코드에 박아, 다음 사람이 "링크가 죽었네" 하고
식별자를 고치지 못하게 한다.

```txt
용어
  term          PropertyValue
  iri           https://schema.org/PropertyValue    전체 IRI. 접두사+이름으로 유도하지 않는다.
  kind          property | type
  context       선언에 필요한 context IRI, 또는 인라인 매핑, 또는 없음(규범)
  provenance    as2-core | cg-final:apmisc | fep:<id> | vendor:<ns> | product:<plugin>
  source        명세 URI + 앵커
  contributor   이 용어를 문서에 넣는 플러그인
```

`iri` 를 접두사와 이름으로 유도하면 안 되는 이유도 레지스트리 문서가 이미 적었다 --
`inbox` 는 `ldp:inbox` 로, `orderedItems` 는 `as:items` 로 확장된다. 유도하면 아무도
쓰지 않는 술어가 된다.

`provenance` 를 접두사로 결정하지 않는다. 위 `as:` 세 줄이 그 반례다.

## 직렬화 규칙

```txt
1  문서에서 나타난 키와 타입을 **한 번** 수집한다.
   지금은 필터마다 전체 문서를 각각 훑는다(현재 4 개 기여자).
2  수집된 집합에 걸리는 용어의 context 를 union 한다. 중복 IRI 는 한 번만.
3  context 가 없는(규범) 용어는 아무것도 추가하지 않는다.
4  모르는 키는 보존하되 선언하지 않는다 (Core §5 는 소비자가 멈추지 말라는 MUST,
   그리고 확장 속성 보존을 SHOULD 로 요구한다).
```

```txt
핀   등록된 용어가 문서에 없으면 그 context 를 넣지 않는다.
     V1 이 통과시킨 음성 검사(속성 없는 문서의 @context 가 스칼라 문자열 그대로)를
     그대로 유지한다.
핀   런타임에 어떤 context 문서도 가져오지 않는다. 원격 @context 는 더더욱 아니다.
```

## 마이그레이션 대상 — 현재 선언 전수 (측정)

회귀 금지선이 이 표다. **모든 항목의 출력이 바이트 단위로 같아야 한다.**

| 기여자 | 트리거 | 선언 | 비고 |
| --- | --- | --- | --- |
| OP renderer | type `QuoteRequest` | `QuoteRequest` → `w3id.org/fep/044f#QuoteRequest` | 인라인 |
| OP renderer | type `QuoteAuthorization` | `QuoteAuthorization`, `gts`, `interactingObject`, `interactionTarget` | 인라인 |
| OP renderer | key `quote` / `quoteAuthorization` | `fep/044f#quote` / `#quoteAuthorization` | 인라인, 키별로 다름 |
| OP renderer | key `sensitive` / `manuallyApprovesFollowers` | `purl.archive.org/miscellany/1` | V1. context IRI |
| OP renderer | key `dcterms:subject` | `dcterms` → `purl.org/dc/terms/` | 접두사만 |
| OP renderer | key `interactionPolicy` | `gts`, `interactionPolicy`, `canQuote`, `automaticApproval` | 인라인 4 항목 |
| OP media-library | type Image·Video·Audio·Document | `schema`, `license`; `usedIn` 은 그 키가 있을 때만 | 2 단 조건 |
| Actors | key `publicKey` | `w3id.org/security/v1` | V1 |
| Calendar | key eventStatus·joinMode·organizers·timezone | `w3id.org/fep/8a8e` | V1 |
| Emoji | `tag`/중첩 `object` 에 `Emoji`·`toot:Emoji`·전체 IRI | `Emoji` → `toot:Emoji` | **조건 판정이 가장 복잡** |

### Emoji 가 설계를 결정한다

`axismundi_emoji_document_has_declaration()` 은 세 가지 표기(`Emoji`, `toot:Emoji`,
전체 IRI)를 `tag` 와 중첩 `object` 에서 찾는다. 레지스트리 문서가 이미 경고한 자리다 --
**무조건 선언하는 API 는 이 플러그인 출력의 회귀다.**

```txt
핀   수집 단계가 **타입도** 모은다. 키만 모으면 Emoji·QuoteRequest·QuoteAuthorization 이
     깨진다.
핀   같은 용어의 여러 표기(compact / 접두사 / 전체 IRI)를 하나로 인식한다.
     Emoji 가 이미 그렇게 하고 있으므로 레지스트리가 그 능력을 잃으면 회귀다.
```

## 충돌 거부

기존 레지스트리 계약을 그대로 따른다 -- `collection-registry.php` 의
`register_*( $id, $args )` → normalize → `WP_Error` → **중복 등록 거부** →
`do_action( 'axismundi_op_register_*' )` 로 요청당 한 번 로드.

```txt
거부할 것   같은 prefix 를 다른 base_iri 로 등록
            같은 term 을 다른 iri 로 등록
            provenance 가 as2-core 인 용어를 재정의
            mint(자체 ns) 로 등록하면서 as: 나 남의 접두사를 쓰는 것
허용할 것   같은 prefix·같은 base_iri 를 여러 플러그인이 참조 (등록이 아니라 참조)
            같은 용어를 여러 플러그인이 contributor 로 선언 (어느 쪽이 넣어도 선언된다)
```

## 검증

```txt
현재 공개 문서의 @context 가 작업 전과 **바이트 단위로 같다.**
  최소 측정 대상: Actor / post / attachment / ax_event / followers 컬렉션 /
  이모지 있는 문서 / 이모지 없는 문서 / quote 있는 문서
audit-context-iris 38 검사가 그대로 통과한다.
audit-object-assembly-baseline 9 검사가 그대로 통과한다 (바이트 승인 fixture).
변이: 같은 prefix 를 다른 base_iri 로 등록하면 등록이 WP_Error 로 거부돼야 한다.
변이: 조건 판정을 무조건으로 바꾸면 음성 검사가 실패해야 한다.
변이: 수집에서 타입을 빼면 Emoji·QuoteRequest 검사가 실패해야 한다.
변이: 용어의 iri 를 접두사+이름으로 유도하게 바꾸면 inbox 또는 orderedItems 검사가
      실패해야 한다 (둘은 as:<term> 이 아니다).
```

## 범위 밖

```txt
원격 어휘 해석기 / source adapter      별개. expander 가 없고 지금은 키로 읽는다.
React 렌더러 등록                      별개. 뷰 모델은 @context 를 운반하지 않는다
                                       (baseline 의 `-view` 해시가 V1 에서 불변이었다).
Calendar 재구조화                      JSCalendar 가 코어·Event 가 파생인 문제이고
                                       CPT 와 함께 볼 덩어리다. S10·Contacts 뒤.
V2 의 PropertyValue·value·size         이 레지스트리 **위에서** 등록한다. 다음 작업.
기존 OP·Calendar 감사 실패 4 건        별도 재현으로 추적한다. 섞지 않는다.
  audit-standalone-projection / audit-calendar-acl / audit-ics / audit-rest-read
```

## 리뷰 결과 (2026-10-25)

통과. **보장한 것과 확인하지 않은 것을 나눠 적는다.**

### 보장한 것

```txt
@context 바이트 동일성   HEAD(V1) 과 stash 전후를 6 개 문서 클래스로 대조 → IDENTICAL
                         actor / post / attachment / event / followers / outbox
                         attachment 의 인라인 맵 그룹 경계(schema+license, usedIn)까지 같다
합성 문서 14 개          빈 문서는 스칼라 문자열, emoji 3 표기(compact·toot:·전체 IRI)와
                         중첩 object 가 하나로 통일, type·formerType 수집,
                         quote·interactionPolicy·dcterms:subject·sensitive:false·publicKey·event,
                         **owner 단독은 선언되지 않는다**(V1 리뷰 발견이 이관 후에도 유지)
네임스페이스 10 개        base_iri / reference 분리가 실측과 일치
                         toot 은 죽은 base IRI(404)를 유지하고 reference 만 docs 로
                         ldp 는 http 형태, schema 는 정규형,
                         sec 는 https://w3id.org/security# -- security/v1 자체 선언과 일치
유도 금지 반례           inbox → ldp:inbox, orderedItems → as:items(+@list) 가 명시 등록
감사                     vocabulary-context-registry 43 / context-iris 38 /
                         object-assembly-baseline 9 / post-article 23 /
                         actor-projection 12 / object-renderer 54, 전부 0 실패
변이 4                   prefix 충돌 거부 / 무조건 선언 거부 / iri 유도 / @list 컨테이너
                         → 전부 검출
기존 레지스트리          vocabulary-registry.php 를 **확장**했고 중복 생성이 아니다
```

### 확인하지 않은 것

```txt
전수 145 감사            돌리지 않았다. 직전 전수(134)가 26 분에 기존 실패 4 건만 찾았고
                         새 결함은 0 이었다. 전수는 통합·배포 시점 도구이고 슬라이스
                         완료 기준이 아니다. 배포 전에 한 번 돌린다.
기존 실패 4 건           audit-standalone-projection / audit-calendar-acl /
                         audit-ics / audit-rest-read. 이번 변경이 악화시켰는지만 보고
                         원인은 추적하지 않았다.
원격 문서 expansion      expander 가 없다. V4 범위.
provenance enum          자유 문자열이다. `inbox` 에 'ldp' 가 들어가 있고 브리프 enum 에
                         없는 값인데 검증은 비어 있지 않은 문자열만 본다.
                         **강제가 아니라 관례다.** 이것 때문에 재구현하지 않는다.
```

## 채택 결정 — lemmy:postingRestrictedToMods

Group 액터의 게시 제한을 나타내는 속성을 레지스트리에 받아들인다. FEP 에는 없다.
근거는 배포된 구현의 합의이며, `as:manuallyApprovesFollowers` 를 받아들일 때 쓴
것과 같은 기준이다.

```txt
lemmy.world/c/lemmykorea    "@context": [ "https://join-lemmy.org/context.json",
                                          "https://www.w3.org/ns/activitystreams" ]
                            postingRestrictedToMods 를 원격 context 로 정의

fedi.video/c/music          "lemmy": "https://join-lemmy.org/ns#"
                            "postingRestrictedToMods": "lemmy:postingRestrictedToMods"
                            PeerTube 가 자기 네임스페이스를 새로 만들지 않고
                            Lemmy 의 IRI 를 인라인 용어로 채택했다
```

두 번째가 결정적이다. 독립된 두 구현이 **같은 IRI** 를 쓰고, 뒤에 온 쪽이 앞선 쪽의
네임스페이스를 그대로 받았다. 그러므로 canonical IRI 는
`https://join-lemmy.org/ns#postingRestrictedToMods` 이고, 우리가 접두사를 새로
만들면 그 합의에서 이탈한다.

선언 방식은 PeerTube 선례를 따라 **인라인 용어**로 한다. Lemmy 의 원격 context
문서를 `@context` 에 넣지 않는다 -- 레지스트리는 원격 문서 의존보다 명시 선언을
선호하고, 그 문서 한 장이 우리가 내보내지 않는 용어까지 끌고 들어온다.

### 이 속성이 말하는 축

```txt
postingRestrictedToMods     누가 글을 쓸 수 있는가
manuallyApprovesFollowers   누가 팔로우할 수 있는가
```

Lemmy·PeerTube 는 팔로우를 전원 자동 수락으로 열고 게시 쪽을 잠그는 모델이라
`manuallyApprovesFollowers` 를 아예 내보내지 않는다 (2026-10-31 실측, 두 Group 모두
키 없음). 우리는 승인제 Group 을 운영할 수 있으므로 두 축을 **둘 다** 내보낸다.
AS2 에서 속성 없음은 false 가 아니라 "주장하지 않음" 이고, 우리는 두 질문의 답을
모두 알고 있다.

### 아직 하지 않은 것

```txt
방출 구현        우리 코드에 postingRestrictedToMods 는 0 건이다. 값을 어디서 읽을지는
                 Forum 바인딩 Group 의 모더레이션 모델에 달려 있고 그쪽이 먼저다.
마스토돈 수용    Group 에 대해 manuallyApprovesFollowers 를 존중하는지 재지 않았다.
                 정합성 근거는 그와 무관하게 유지된다.
```
