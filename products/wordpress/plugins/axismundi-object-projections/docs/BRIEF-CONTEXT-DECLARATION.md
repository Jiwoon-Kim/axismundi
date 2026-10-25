# 브리프 — 투영 문서가 선언 없이 내보내는 확장 용어를 닫는다

2026-10-25. 측정 근거는 `products/wordpress/architecture/activitystreams-open-items.md`
의 "Actor Projection Emits Undeclared Extension Terms" 절에 있다. 이 문서는 그것을
구현 단위로 쪼갠 것이고, **범위가 Actor 보다 넓다는 것이 측정으로 밝혀졌다.**

## 설계는 이미 있다 — 이 브리프는 측정과 작업 순서만 담는다

**선결 독서: `architecture/activitystreams-vocabulary-registry.md`.** 이 브리프의 초판은
레지스트리 설계를 새로 썼는데, 그 문서가 같은 것을 2026-10-17 에 이미, 더 정확하게
결정해 두었다. 중복 설계를 지우고 아래만 남긴다.

```txt
그 문서가 이미 정한 것 (여기서 다시 정하지 않는다)
  iri(식별자) vs source(참조) 분리
  provenance 에 de-facto 제3범주 -- publicKey 가 그 예로 기록돼 있다
  "a term's iri is the predicate it expands to, not as: plus its own name"
  inbox → ldp:inbox. 그 provenance 는 Linked Data Platform
  "the owner of a feature registers its terms" (2026-10-17 결정)
  현 @context 필터가 못 하는 두 가지:
    충돌 재등록을 병합하지 말고 검출할 것 / 확장이 AS2 코어 용어를 덮지 못할 것
  "runtime must not scrape the W3C document"
  transport 허용 목록 3개가 좁다는 지적 -- FEP-de8d 가 Actor 에 기여하지 못한다
  inbox·outbox MUST 가 audit-actor-projection.php 의 복합 조건으로 부수적으로만 강제됨

이 브리프가 보태는 것 (전부 2026-10-25 측정)
  문서 타입별 미선언 용어 전수 (Actor / post / attachment / ax_event)
  우리 renderer 의 as:sensitive 한 줄
  schema 접두사 형태 결정 (Mastodon 자기 명세가 자기 형태를 incorrect 라고 적음)
  size 의 출처 = FEP-1311, https://joinpeertube.org/ns#size
  Event 네 속성의 출처 = FEP-8a8e 가 발행하는 context IRI 하나
  uploadMedia 의 둘로 갈린 상태 / 같은 도메인 업로드 귀속 위험
```

그 문서가 모르는 제약 하나도 거기서 읽었다 -- Emoji 플러그인은 **용어를 조건부로**
선언하고(`axismundi_emoji_document_has_declaration()`), 무조건 선언하는 API 는 그
플러그인 출력에 **회귀**가 된다. 아래 작업은 그 조건부 성질을 깨지 않는다.

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
   **정정:** 처음에 "결함이 아니다" 라고 적었는데, 더 정확한 기록이 이미 있다 --
   Core §5 는 내보내는 확장 속성마다 `@context` 정의를 제공하라고 하고,
   `architecture/activitystreams-vocabulary-registry.md` 는 이 키를 **의도적으로
   포기한 SHOULD** 로 기록한다. 이유도 거기 있다: 전체 키를 유지해 context 용어를
   compact 하지 않는 피어도 속성을 찾게 하고, `http` 스킴인 것은 JSON-LD 에서 IRI 가
   곧 속성 이름이라 `https` 와 다른 용어가 되기 때문이다. 더 나은 처리까지 적혀
   있다 -- 내보내는 키를 그대로 두고 context 에 alias 를 선언하면 둘이 같은 속성으로
   compact 된다. **고칠 결함이 아니라 개선 후보다.**
```

선언 지점은 `axismundi_op_jsonld_context` 필터다. 계약이 `renderer.php:19` 에 적혀
있다 -- context 는 이 필터로 정하고 **transformer 의 반환값으로 정하지 않는다.**
Actors 와 Event 의 사용 횟수는 0 이다.

## V1 — context IRI 세 줄

가장 큰 효과를 가장 적은 변경으로 얻는 자리다. 셋 다 **우리가 이미 내보내는 용어를
정의한 문서가 있는데 그 context 를 안 싣고 있을 뿐**이고, 내보내는 바이트가 바뀌지
않는다.

```txt
https://w3id.org/fep/8a8e                     Event 의 네 속성
https://w3id.org/security/v1                  publicKey publicKeyPem owner
https://purl.archive.org/miscellany/1.0.1     sensitive manuallyApprovesFollowers
                                              (Hashtag movedTo 도 정의되지만 우리는 안 쓴다)
```

남는 인라인 선언은 `PropertyValue`·`value`·`size` 셋뿐이다(V2·V2b).

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

### manuallyApprovesFollowers — 배포 식별자를 쓰되 비표준으로 명시한다

```txt
"manuallyApprovesFollowers": "as:manuallyApprovesFollowers"
```

**정정 -- 이 절은 V1 으로 옮긴다.** 인라인 선언이 필요 없다. 세 단계로 틀렸다가
바로잡혔다.

```txt
1차   "배포된 Mastodon 이 as:manuallyApprovesFollowers 로 선언한다" 만 근거로 권고
2차   그 IRI 가 가리키는 정의가 W3C 문서에 **없음**을 측정. 같은 결함이 우리
      renderer.php:48 의 'sensitive' => 'as:sensitive' 에도 있음
3차   **집이 이미 있음을 확인.** Mastodon 명세 페이지가 이 용어들을 "proposed but
      not officially adopted yet" 로 적고 "now been documented by the ActivityPub
      Miscellaneous Terms document" 를 가리킨다 (SWICG 리포트, context 1.0.1)
```

```txt
결정   context IRI 하나를 싣는다. 인라인 선언도 NON-STANDARD 주석도 필요 없다.
       https://purl.archive.org/miscellany/1.0.1
       → sensitive  manuallyApprovesFollowers  Hashtag  movedTo 가 정의된다
       IRI 는 as: 그대로다. **내보내는 바이트가 바뀌지 않는다.**
핀     새 as: 용어를 우리가 만들지는 않는다. IRI 를 할당한 문서를 인용하는 것은
       주조가 아니고, 그런 문서 없이 as:<우리용어> 를 쓰는 것이 주조다.
```

그래서 이 둘은 V2 가 아니라 **V1(context IRI 추가)에 속하고**, V1 이 세 줄 작업이
된다 -- FEP-8a8e, security/v1, miscellany. 버전 고정 여부는 결정 사항이다: 레지스트리
문서의 규칙("한 번 발행한 context 를 바꾸면 이미 발행된 문서의 의미가 바뀐다")이 고정
URL 을 지지하고, 갱신은 의도적으로 받는다.

### 검증

```txt
프로필 필드가 있는 Actor 의 @context 가 schema 접두사와 두 용어를 선언한다.
`https://schema.org/` 형태다. `http://schema.org#` 를 새로 내보내지 않는다.
manuallyApprovesFollowers·sensitive 는 miscellany context 로 정의된다 (V1 에서).
변이: schema 를 http://schema.org# 로 바꾸면 형태 검사가 실패해야 한다.
```

## V2b — attachment 의 `size` (출처 확인됨)

렌디션 Link 에 바이트 수를 싣는다(`media-library.php:174,187`). AS2 에 `size` 가
없어서 후보를 두 개 띄웠는데 **둘 다 틀렸다.** FEP-1311(Media Attachments) 이 이미
문서화하고 있다.

```txt
"Finally `size` being the file size in bytes is borrowed from PeerTube."
"The `size` should tell us if we want to preload the media or not."
```

그 FEP 의 예시 context 가 선언 형태까지 보여준다.

```json
{
  "@context": [
    "https://www.w3.org/ns/activitystreams",
    "https://www.w3.org/ns/credentials/v2",
    { "size": "https://joinpeertube.org/ns#size" }
  ]
}
```

```txt
결정   size → https://joinpeertube.org/ns#size 로 인라인 선언한다.
       정수 바이트라는 의미가 우리 값과 정확히 같다.
       schema:contentSize 는 쓰지 않는다 -- 단위를 문자열에 담는 관행이라 의미가 다르다.
       자체 ns 도 쓰지 않는다 -- 주장과 수신자가 모두 저쪽이므로 저쪽 IRI 가 맞다.
```

이것은 네임스페이스 규칙의 ② 다 -- 남의 기능에 올라타는 것이므로 IRI 까지 빌린다.

덤으로 같은 예시가 `digestMultibase` 의 context 도 알려 준다
(`https://www.w3.org/ns/credentials/v2`, Verifiable Credential Data Integrity).
우리는 아직 내보내지 않지만 원격 미디어 바이너리 캐시에 `content_hash` 가 이미 있으므로,
그것을 공개 표현에 싣기로 하면 그 context 를 쓴다. **지금 싣지 않는다.**

## V3 — 접두사 충돌

레지스트리 문서가 이미 요구사항으로 적어 둔 것이다 -- *"Conflicting re-registration is
detected, not merged. The filter merges, so two plugins registering one IRI with
different value shapes or cardinalities resolve by last writer and then disagree
quietly."* 여기서 새로 정할 것은 없고, **측정된 사례만 보탠다.**

```txt
현재   schema 를 media-library 가 선언한다. 선언하는 곳이 하나뿐이라 아직 충돌이 없다.
다음   Actors 가 PropertyValue 를 선언하면 **같은 접두사를 두 통합이 각자 선언**한다.
       그 순간이 위 요구사항이 실제로 필요해지는 첫 지점이다.
```

그러므로 V2 를 하는 커밋이 이 충돌을 만든다. 등록 API 가 아직 없다면 최소한 **두 선언이
같은 값인지 검사하는 감사**를 같이 넣는다(V4 에 포함).

Actor 투영과 Media Library 투영은 **둘 다 OP 통합**이다
(`includes/integrations/actors.php`, `includes/integrations/media-library.php`).
도메인 플러그인으로 옮기는 작업이 아니라 OP 안에서 선언 지점을 정리하는 작업이다.
소유 규칙은 레지스트리 문서가 이미 정했다 -- "the owner of a feature registers its
terms", 그리고 OP 는 등록 지점을 소유하되 내용을 소유하지 않는다.

## V4 — 감사를 "있다" 에서 "선언됐다" 로

레지스트리 문서가 이 감사의 현재 약점을 이미 적어 두었다 -- inbox·outbox MUST 가
"only incidentally, by a composite condition inside `audit-actor-projection.php` whose
subject is transport-field override" 로만 강제된다는 것. 그 복합 조건이 선언 여부를
통과시킨 바로 그 줄이다.

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

## 결정: 자체 네임스페이스는 w3id.org 에 등록한다 (2026-10-25)

```txt
현재   https://github.com/Jiwoon-Kim/axismundi/ns#usedIn
결정   w3id.org 에 등록한 영속 IRI 로 옮긴다.
```

리포지터리 URL 은 리포지터리가 옮겨지거나 이름이 바뀌면 끊긴다. w3id.org 는 그 목적으로
존재하는 영속 식별자 서비스이고, 우리가 이미 쓰는 두 선례가 그것을 쓴다 --
`https://w3id.org/security/v1`, `https://w3id.org/fep/8a8e`.

자체 용어가 **지금 `usedIn` 하나뿐일 때 옮기는 것이 가장 싸다.** `size` 가 PeerTube ns
로 확정되면서 자체 용어 후보가 줄었고, 남은 후보는 `discoverable`·`indexable` 이다.

### 하위 선택 둘

```txt
경로 형태   fragment  misskey:  https://misskey-hub.net/ns#   → ns#usedIn
            slash     FEP-8a8e: https://w3id.org/fep/8a8e/   → 8a8e/timezone
            둘 다 통용된다. 하나로 정해 전부 같게 쓴다.

이전 처리   usedIn 은 우리 용어이고 소비자가 아직 없다. 구 IRI 를 영속 보장한
            적도 없으므로 그대로 교체한다. 다만 구 IRI 가 이미 발행된 문서에
            들어가 있으므로(attachment 투영) **바꿨다는 사실은 기록한다.**
```

### 접두사 질문 — 두 결정을 분리한다

Misskey 의 context 를 보고 `axis:` 접두사를 쓸지 물었는데, 거기에는 **별개의 두 가지**가
섞여 있다.

```txt
(a) @context 의 접두사 이름        "misskey": "https://misskey-hub.net/ns#"
(b) JSON 키 자체에 붙은 벤더 표시   "_misskey_content", "_misskey_quote", ...
```

Misskey 는 벤더 이름을 **두 번** 쓴다 -- 키에도, IRI 에도.

### (a) 접두사는 쓴다

`axis` 같은 짧은 접두사를 @context 에 바인딩하고 용어를 그 위에 올린다. 접두사는
문서 안에서만 유효하므로 다른 프로젝트와 충돌할 여지가 없고, context 가 읽기 쉬워진다.
Misskey·Mastodon·FEP 전부 이렇게 한다.

### (b) 키에는 붙이지 않는다 (권고)

`_axis_usedIn` 같은 키를 만들지 않는다.

```txt
붙이는 쪽의 이유     피어는 대부분 JSON-LD 를 처리하지 않고 키로 읽는다.
                     키 접두사는 그런 구현에게 "이건 벤더 전용이니 기대지 말라" 를
                     전달하는 유일한 수단이다. 목적이 있는 관행이다.

붙이지 않는 이유     ① 우리 class ③ 용어는 **남이 채택해도 되는 주장**이다.
                        usedIn 은 의미가 있는 속성이고 벤더 전용 표시는 틀린 신호다.
                     ② 키 이름은 와이어 계약이다. 나중에 표준화되면 키를 바꿔야 하고
                        그것이 깨뜨리는 변경이다. Misskey 자신이 그 사례다 --
                        quote 가 quoteUrl / _misskey_quote / FEP-044f quote 로 흘렀다.
                     ③ 적합한 소비자에게는 IRI 가 이미 네임스페이스다. 키 접두사는 중복이다.
```

```txt
예외   진짜로 사적이고 실험적이며 **아무도 이것에 기대지 않기를 바라는** 속성이라면
       붙인다. 그때는 이름이 보기 싫은 것이 기능이다.
       기본은 붙이지 않는 쪽이고, 붙이려면 그 이유를 주석에 적는다.
```

### 그 다음 수: 자체 context 문서를 발행할지

영속 base 가 생기면 선택지가 하나 열린다. 지금은 통합마다 용어를 인라인 선언하는데,
**context 문서 하나를 발행하면 문서는 IRI 하나만 싣는다.**

```txt
인라인       통합마다 용어 맵을 쓴다. 지금 방식.
             같은 접두사를 두 통합이 각자 선언할 위험이 남는다.
context 문서 https://w3id.org/<path>/ns 를 발행하고 문서는 그것만 싣는다.
             security/v1 과 FEP-8a8e 가 하는 방식이고, 선언이 한 곳으로 모인다.
             브리프 V3 의 "접두사 선언을 한 곳으로" 가 자동으로 달성된다.
비용         그 URL 을 올바른 content type 으로, 영구히 서비스해야 한다.
             한 번 발행한 context 는 바꾸면 이미 발행된 문서의 의미가 바뀐다.
```

지금 결정할 필요는 없다. 용어가 하나뿐이라 인라인으로 충분하고, 자체 용어가 셋을 넘으면
그때가 발행할 시점이다. **다만 base IRI 는 그 선택을 미리 막지 않게 정한다** --
`ns` 경로를 비워 두면 나중에 그 자리에 context 문서를 올릴 수 있다.

### Misskey context 에서 배운 것, 그리고 베끼지 말 것

```txt
배울 것   내보내는 확장 용어를 **전부** 선언한다. 측정해 보니 그들이 인라인 선언한
          항목 전부가 실제로 AS2 context 에 없는 것들이다 -- 장식이 아니라 필요한 선언이다.
          sensitive → as:sensitive 는 우리 renderer 가 이미 같게 한다.

참고      Hashtag → as:Hashtag 로 선언한다. 우리는 type:Hashtag 를 내보내지 않기로
          했으므로 해당 없지만, 되돌릴 경우의 선언 경로가 그것이라는 것은 기록해 둔다.
          quoteUrl → as:quoteUrl 도 규범 context 에 없는 as: 용어다.

베끼지 말 것  schema → http://schema.org# 를 쓴다. Mastodon 과 같은 레거시 형태이고,
          우리는 Mastodon 자신의 명세가 incorrect 라고 적은 근거로 https 정규형을 쓴다.
          구현 둘이 같이 쓴다는 것이 그 형태를 맞게 만들지 않는다.
          freeText 의 "@type": "schema:text" 도 의심스럽다 -- schema.org 의 데이터타입은
          대문자 Text 다. 남의 context 에 오류가 있을 수 있으므로 통째로 베끼지 않는다.
```

## 하지 말 것

```txt
금지   IRI 를 추측해서 선언한다
       출처(명세·FEP·배포된 선언)를 확인하고 쓴다. 추측한 IRI 는 그 자체로
       또 하나의 비표준이 되고, 한 번 발행하면 되돌리기 어렵다.

금지   절대 IRI 키(emojiReactions)를 **내보내는 키를 바꿔서** "정리" 한다
       그 키는 의도적으로 선택된 것이고 이유가 레지스트리 문서와 코드에 있다
       (`integrations/reactions.php`). 키를 바꾸면 compact 하지 않는 피어가
       속성을 잃는다. 개선하려면 키를 그대로 두고 context 에 alias 를 선언한다.

금지   Emoji 플러그인의 조건부 선언을 무조건 선언으로 바꾼다
       `axismundi_emoji_document_has_declaration()` 이 `tag` 와 중첩 `object` 를
       훑어 Emoji 가 있을 때만 매핑을 넣는다. 무조건 선언은 그 플러그인 출력의
       **회귀**다. 이모지를 쓰지 않는 문서의 @context 가 지금과 같아야 한다.

금지   uploadMedia 를 위해 용어를 선언한다
       AS2 규범 context 가 `as:uploadMedia` (`@type: @id`) 로 이미 정의한다
       (`oauthAuthorizationEndpoint`·`proxyUrl`·`streams` 와 함께).
       **다만 용어가 선언 가능한 것과 기능이 명세된 것은 다르다** -- 업로드
       메커니즘은 상태 라벨도 없는 2017 년 wiki 페이지에만 있고, 우리는 업로드를
       WordPress 코어 API 로 하기로 이미 정했다 (open-items
       "업로드는 WordPress 코어 API 를 쓴다"). 이 브리프는 선언만 다룬다.

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
