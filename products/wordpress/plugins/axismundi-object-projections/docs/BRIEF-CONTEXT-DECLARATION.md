# 브리프 — 투영 문서가 선언 없이 내보내는 확장 용어를 닫는다

2026-10-25. 측정 근거는 `products/wordpress/architecture/activitystreams-open-items.md`
의 "Actor Projection Emits Undeclared Extension Terms" 절에 있다. 이 문서는 그것을
구현 단위로 쪼갠 것이고, **범위가 Actor 보다 넓다는 것이 측정으로 밝혀졌다.**

## 범위 정정 — Actor 만의 문제가 아니다

처음에는 Actor 투영만 재고 "Actors 가 seam 을 안 쓴다" 로 적었다. 네 문서 타입을
실제로 받아 규범 AS2 context 와 대조하니 **서로 다른 네 가지 상태**가 나왔다.

```txt
Actor         선언 0.  미선언 키 publicKey publicKeyPem owner
                       manuallyApprovesFollowers value,  미선언 타입 PropertyValue
post          선언 sensitive.  나머지는 절대 IRI 키 -- 적법하다
attachment    선언 license schema usedIn sensitive.  미선언 키 size
ax_event      선언 0.  미선언 키 eventStatus joinMode organizers timezone
```

즉 한 도메인(Media Library)은 제대로 하고, 하나는 절대 IRI 로 우회하고, 둘은 아예 하지
않는다. **공통 결함이 아니라 도메인별 편차**이고, 그래서 고칠 것은 각 도메인의 선언과
그것을 강제하는 감사다.

### 측정하지 못한 것

```txt
ax_note   permalink(`?post_type=ax_note&p=18722`)이 404 였다. 재지 못했다.
          **V0 으로 먼저 측정한다.** Note 가 가장 많이 발행되는 타입이므로
          여기서 미선언 용어가 나오면 우선순위가 바뀐다.
```

### 쫓지 말 것 — 거짓 양성

```txt
en-US  ko-KR    nameMap·summaryMap 안의 키다. 두 속성은 @container: @language 로
                정의되어 있으므로 그 키는 **언어 태그이지 용어가 아니다.**
                선언이 필요 없다. 측정 스크립트가 잘못 잡은 것이다.
```

## 이미 쓰이는 세 가지 적법한 패턴

새 기계를 만들 필요가 없다. 세 패턴이 이미 코드에 있고, 셋 다 맞다.

```txt
① context IRI 를 싣는다
   남이 발행한 context 문서 하나를 @context 에 추가한다. 용어를 인라인으로 적지 않는다.
   가장 적은 변경이고 가장 안전하다. 배포된 Mastodon 이 security/v1 에 이렇게 한다.

② 용어를 인라인 선언한다
   media-library.php:43 이 schema 접두사와 license·usedIn 을 그렇게 선언한다.
   renderer.php 가 sensitive 를 as:sensitive 로 그렇게 선언한다.

③ 절대 IRI 를 키로 쓴다
   post·attachment 의 `http://fedibird.com/ns#emojiReactions` 가 그렇다.
   JSON-LD 에서 절대 IRI 키는 용어 정의가 필요 없으므로 **이것은 결함이 아니다.**
   접두사를 만들 가치가 없는 단발 상호운용 속성에 적합하다.
```

선언 지점은 `axismundi_op_jsonld_context` 필터다. 계약이 `renderer.php:19` 에 적혀
있다 -- context 는 이 필터로 정하고 **transformer 의 반환값으로 정하지 않는다.**
Actors 와 Event 의 사용 횟수는 0 이다.

## V1 — context IRI 하나로 끝나는 둘

가장 큰 효과를 가장 적은 변경으로 얻는 자리다. 둘 다 **우리가 이미 구현하고 있는
명세가 발행한 context 를 안 싣고 있을 뿐**이다.

### Event — FEP-8a8e 의 context

FEP 원문을 확인했다. 자기 context IRI 를 발행하고, 예시가 AS2 와 나란히 싣는다.

```json
{
  "@context": [
    "https://w3id.org/fep/8a8e",
    "https://www.w3.org/ns/activitystreams"
  ],
  "type": "Event",
  "organizers": null,
  "timezone": "Europe/Rome"
}
```

속성 IRI 도 그 아래다.

```txt
https://w3id.org/fep/8a8e/organizers
https://w3id.org/fep/8a8e/timezone
https://w3id.org/fep/8a8e/eventStatus
https://w3id.org/fep/8a8e/joinMode        (externalParticipationUrl 도 같은 ns)
```

우리 Event 문서는 네 속성을 **FEP 이름 그대로** 내보내면서 context 를 안 싣는다.
`https://w3id.org/fep/8a8e` 를 추가하면 네 개가 한 번에 정의된다. 인라인 선언을
직접 쓰지 않는다 -- FEP 이 발행한 문서가 정본이다.

FEP 은 `status` 를 incoming fallback 으로 지원하라고 권고한다(`SHOULD`). 수신 쪽
이야기이므로 이 브리프의 범위가 아니지만, Event 도메인이 그 권고를 이미 따르는지는
별도로 확인할 일이다.

### Actor — Security Vocabulary 의 context

`publicKey`·`publicKeyPem`·`owner` 셋을 인라인으로 적지 않는다. 배포된 Mastodon 이
하는 대로 context IRI 하나를 싣는다.

```txt
"https://w3id.org/security/v1"
```

### 검증

```txt
Event 문서의 @context 에 FEP IRI 가 있고, 네 속성이 그 ns 로 정의된다.
Actor 문서의 @context 에 security/v1 이 있다.
단일 원소 collapse 가 깨지지 않는다 -- 두 경우 모두 이제 배열이다
  (renderer 가 `1 === count($context)` 일 때만 문자열로 접는다).
변이: context IRI 를 떼면 해당 감사가 실패해야 한다.
```

## V2 — Actor 의 인라인 선언 둘

### PropertyValue 와 value

`schema` 접두사 형태는 이미 결정됐다. open-items 의 "결정: 출력은
`https://schema.org/`" 절을 그대로 따른다 -- 배포된 Mastodon 은
`http://schema.org#` 를 쓰지만 **Mastodon 자신의 명세가 그것을 incorrect 라고
적는다.**

```json
{
  "schema": "https://schema.org/",
  "PropertyValue": "schema:PropertyValue",
  "value": "schema:value"
}
```

### manuallyApprovesFollowers

추측하지 않는다. 배포된 Mastodon Actor 에서 측정한 값이 있다.

```txt
"manuallyApprovesFollowers": "as:manuallyApprovesFollowers"
```

규범 context 문서에는 없지만 AS2 네임스페이스 용어로 통용된다.

### 검증

```txt
프로필 필드가 있는 Actor 의 @context 가 schema 접두사와 두 용어를 선언한다.
`https://schema.org/` 형태다. `http://schema.org#` 를 새로 내보내지 않는다.
manuallyApprovesFollowers 가 as: 로 선언된다.
변이: schema 를 http://schema.org# 로 바꾸면 형태 검사가 실패해야 한다.
```

## V3 — 접두사 선언을 한 곳으로

지금은 `schema` 를 media-library 가 선언한다. Actors 도 선언하게 되면 **같은 접두사를
두 통합이 각자 선언**하고, 거기서 형태가 갈릴 수 있다 -- 바로 이 브리프가 고치는 종류의
사고다.

```txt
핀   여러 도메인이 쓰는 접두사(`schema` 가 현재 유일한 사례)는 OP 가 한 곳에서
     선언한다. 통합은 자기 용어만 그 위에 올린다.
핀   한 문서의 @context 안에 같은 접두사가 두 번, 다른 값으로 들어가지 않는다.
     감사로 고정한다.
```

Actor 투영과 Media Library 투영은 **둘 다 OP 통합**이다
(`includes/integrations/actors.php`, `includes/integrations/media-library.php`).
도메인 플러그인으로 옮기는 작업이 아니라 OP 안에서 선언 지점을 정리하는 작업이다.

## V4 — 감사를 "있다" 에서 "선언됐다" 로

지금 감사가 이 상태를 통과시킨 이유다.

```txt
audit-actor-projection.php:47
  isset( $finalized['inbox'], $finalized['outbox'],
         $finalized['endpoints']['sharedInbox'], $finalized['publicKey'] )
```

속성이 **나타나는지만** 본다.

```txt
핀   투영 문서마다: 문서에 나타난 키·타입 중 AS2 규범 context 가 정의하지 않고,
     문서의 @context 도 선언하지 않고, 절대 IRI 도 아닌 것이 **하나라도 있으면
     실패한다.** 특정 속성 목록을 하드코딩하지 않는다 -- 새 속성이 추가될 때
     자동으로 걸려야 하고, 그것이 이 결함이 생긴 방식이다.
핀   언어 맵 키(nameMap·summaryMap 아래)는 그 검사에서 제외한다. 용어가 아니다.
```

이 감사가 먼저 서면 V1~V3 의 완료 여부가 자동으로 판정되고, 아직 못 잰 `ax_note` 도
같은 그물에 걸린다.

## 소유자 결정이 필요한 둘

구현하지 말고 물어볼 것. 둘 다 온톨로지 쪽이다.

### attachment 의 `size`

렌디션 Link 에 바이트 수를 싣는다(`media-library.php:174,187`). AS2 에 `size` 가
없다. 후보가 갈린다.

```txt
schema:contentSize   media 문서가 이미 schema 접두사를 선언하므로 추가 비용이 없다.
                     다만 schema.org 의 정의는 "File size in (mega/kilo)bytes" 로
                     단위를 문자열에 담는 관행이고, 우리 값은 정수 바이트다.
자체 ns              정수 바이트라는 우리 의미를 그대로 말한다.
                     usedIn 이 이미 그 선례다.
```

### 자체 ns 의 base IRI

```txt
현재   https://github.com/Jiwoon-Kim/axismundi/ns#usedIn
```

리포지터리 URL 을 네임스페이스 base 로 쓰고 있다. 앞으로 자체 용어가 늘어나므로
(`size` 가 그쪽으로 가면 바로 두 번째, 나중에 `discoverable`·`indexable` 도 후보)
**지금 형태를 확정하는 것이 싸다.** 리포지터리가 옮겨지거나 이름이 바뀌면 발행된
IRI 가 끊긴다. `w3id.org` 등록이나 프로젝트 도메인이 관례적인 안정 선택이다.

## 하지 말 것

```txt
금지   IRI 를 추측해서 선언한다
       출처(명세·FEP·배포된 선언)를 확인하고 쓴다. 추측한 IRI 는 그 자체로
       또 하나의 비표준이 되고, 한 번 발행하면 되돌리기 어렵다.

금지   절대 IRI 키(emojiReactions)를 접두사로 "정리" 한다
       이미 적법하다. 건드릴 이유가 없다.

금지   JSON-LD expander 를 도입해 수신 호환을 만든다
       측정 결과 우리 코드에 expander 가 하나도 없고 원격 문서를 키로 읽는다.
       이 브리프는 **출력** 문서를 고치는 것이다. 수신은 범위 밖이다.

금지   transformer 반환값에 @context 를 넣는다
       renderer.php:19 이 금지한다. 선언은 필터로만 한다.
```

## 왜 지금 깨지지 않는데 고치는가

현재 피어는 키로 읽으므로 연합은 동작하고, Mastodon·Misskey E2E 는 통과했다. 우리
쪽에도 expander 가 없다. 그래서 급한 장애가 아니다.

고치는 이유는 둘이다.

```txt
적합성   선언 없는 용어는 JSON-LD 로서 정의되지 않는다.
실질     expand 하는 수신자는 그 용어를 버린다.
         publicKey 가 버려지면 확장 그래프에 검증 키가 없고,
         PropertyValue·value 가 버려지면 프로필 필드에 내용이 없고,
         FEP 속성 네 개가 버려지면 Event 가 시간·주최자·참가 방식을 잃는다.
```

Event 가 특히 급하다 -- 네 속성 전부이고, 그 명세의 수신자는 **이벤트 연합을
구현한 서버**라서 FEP context 를 실제로 다룰 가능성이 가장 높은 집단이다.
