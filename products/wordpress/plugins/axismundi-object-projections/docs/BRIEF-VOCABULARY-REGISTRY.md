# 브리프 — 어휘 레지스트리 (네임스페이스 / 용어 2층 등록)

2026-10-25. 설계 근거는 `products/wordpress/architecture/activitystreams-vocabulary-registry.md`
이고 이 문서는 그 중 **직렬화에 필요한 최소 조각만** 구현 단위로 쪼갠 것이다.
V1(`BRIEF-V1-CONTEXT-IRIS.md`)이 선행이고 완료됐다(10deeac).

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
