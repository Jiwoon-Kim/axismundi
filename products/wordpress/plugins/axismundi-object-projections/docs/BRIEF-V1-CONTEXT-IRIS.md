# V1 브리프 — 발행된 context 세 개를 싣는다

2026-10-25. `BRIEF-CONTEXT-DECLARATION.md` 의 V1 만 떼어낸 작업지시다. 설계 배경은
`products/wordpress/architecture/activitystreams-vocabulary-registry.md` 의
2026-10-25 측정 절에 있다. **이 문서만 읽고 작업할 수 있게 썼다.**

## 한 줄 요약

우리가 이미 내보내는 용어 아홉 개를 정의하는 문서가 셋 있는데 그 context 를 안 싣고
있다. **내보내는 바이트는 하나도 바뀌지 않는다.** `@context` 에 IRI 세 개가 늘어난다.

## 구현 및 검증 (2026-10-25)

V1 구현 완료, 리뷰 대기. Actors와 Calendar는 기존 context 필터에 각각 기여하고,
OP는 기존 sensitive 인라인 선언을 miscellany major-tier IRI로 교체한다.
OP 공통 속성 탐색은 중첩 배열을 확인하되 입력의 `@context`는 탐색하지 않는다.
속성값이 false/null이어도 존재하면 선언하며, 입력 객체를 변경하지 않는다.
리뷰 반영: Actors는 `publicKey`만 탐색한다. 그 하위의 owner/publicKeyPem은
동일 context로 정의되며, 단독 owner/publicKeyPem 키는 Security 선언을 유발하지 않는다.

등록된 OP 통합은 Article, Actor/관계 컬렉션, media/폴더, hashtag, reaction,
replies를 확인했다. 폐기 예정 ax_note 경로를 선행 작업으로 추가하지 않았다.

검증 명령은 wp-env CLI의 `wp eval-file wp-content/plugins/<plugin>/tests/<file>`:

- OP `audit-context-iris.php`: 38 PASS.
- OP `audit-post-article.php`: 23 PASS (기존 인라인 context 단정 갱신).
- OP `audit-actor-projection.php`: 12 PASS.
- OP `audit-object-renderer.php`: 54 PASS.
- 세 IRI 각각 제거 변이는 해당 양성 검사에서 실패했다.
- 공통 조건을 항상 true로 만드는 변이는 context 불변 음성 검사에서 실패했다.
- 변이 복원 후 context 감사 38 PASS, `git diff --check` 통과.

원격 context의 JSON-LD expansion을 자동 검증한 것은 아니다. 고정 사본 기반
용어 검증은 후속 V4 범위이며, 이번 감사는 선언 선택과 기존 출력 회귀를 검사한다.

## 리뷰 결과 (2026-10-25)

통과. 보고를 받지 않고 다시 측정한 것들이다.

```txt
조건부 동작    Actor miscellany+security / post·attachment miscellany /
               ax_event fep-8a8e / followers 컬렉션은 문자열 그대로 (음성 사례)
참조 context   세 문서를 직접 받아 아홉 용어 전부 정의 확인
               security/v1 43 용어, fep/8a8e 21 용어(eventStatus 의 **값**까지),
               miscellany/1 6 용어이고 **@type 이 없다** -- 1.0.1 changelog 와 일치하므로
               major 티어 선택이 실측으로 검증됐다
예측자 경계    false / true / null / 빈 문자열 전부 검출(키 존재 기준),
               중첩 검출, 기존 @context 내부는 건너뜀
실제 문서      manuallyApprovesFollowers: false 인 Actor 가 miscellany 를 받는다
변이 5         무조건 선언 / 중첩 탐색 제거 / !empty 추가 / security 제거 / fep 제거
               → 전부 검출
```

### 바이트 승인 fixture 재승인

`audit-object-assembly-baseline` 이 실패했다 -- 구현 보고의 감사 목록에 없던 파일이고,
어제 S4 의 `audit-repository` 와 같은 모양(돌린 감사는 통과했지만 범위가 좁았다)이다.

단정 전에 격리했다: `sensitive` 분기만 옛 인라인 형태로 되돌리면 9/9 로 돌아간다. 즉
V1 이 유일한 원인이고 의도된 변경이다. `article`·`image`·`audio` 를 재승인했고
`-view` 세 개는 불변이다 -- 뷰 모델이 `@context` 를 운반하지 않기 때문이고, 어휘 표현과
클라이언트 뷰 모델이 이미 분리돼 있다는 증거다.

### V1 소관이 아닌 기존 실패 네 건

세 플러그인 감사 134 개를 돌려 5 건이 실패했고, V1 을 떼고 다시 돌려 가렸다.

```txt
기존 실패    audit-standalone-projection (OP)
             audit-calendar-acl / audit-ics / audit-rest-read (Calendar)
             → V1 없이도 동일하게 실패한다. 별도 사안이다.
asset-cache  V1 복원 상태 단독 실행 16/16 통과.
             감사 본문에 @context·jsonld·local_endpoints·transport_members·publicKey
             참조 0 건 -- V1 이 바꾼 것과 교차하지 않는다.
             expires_at·next_refresh_at·failure_count·time() 사용.
             26 분짜리 전수 실행 중 시간 경계를 넘은 것으로 **추정**하며 단정하지 않는다.
```

## 배경

선언 없는 용어는 JSON-LD 로서 정의되지 않는다. expand 하는 수신자는 그 용어를 버린다.

```txt
publicKey 가 버려지면        확장 그래프에 검증 키가 없다
FEP 속성 네 개가 버려지면    Event 가 시간·주최자·참가 방식을 잃는다
sensitive 가 버려지면        콘텐츠 경고가 사라진다
```

지금 깨지지 않는 이유는 측정했다 -- 현재 피어는 키로 읽고, 우리 코드에도 JSON-LD
expander 가 하나도 없다. 그래서 급한 장애가 아니고, **회귀 위험이 거의 없는 작업**이다.

## 무엇을 어디에 등록하는가

규칙은 레지스트리 문서가 이미 정했다 -- **기능의 소유자가 자기 용어를 등록한다.** OP 는
등록 지점(`axismundi_op_jsonld_context` 필터)을 소유하고 내용을 소유하지 않는다. 각 속성이
문서에 들어오는 지점을 측정해서 소유자를 정했다.

| context IRI | 덮는 용어 | 등록할 플러그인 | 근거 (그 속성이 들어오는 자리) |
| --- | --- | --- | --- |
| `https://w3id.org/fep/8a8e` | `eventStatus` `joinMode` `organizers` `timezone` | **axismundi-calendar** | `includes/projection.php` -- `eventStatus` 120, `joinMode` 131, `organizers` 154 (조건부), `timezone` 도 같은 투영 |
| `https://w3id.org/security/v1` | `publicKey` `publicKeyPem` `owner` | **axismundi-actors** | `includes/local-endpoints.php:149` 가 `publicKey` 를 transport member 로 기여 |
| `https://purl.archive.org/miscellany/1` | `sensitive` `manuallyApprovesFollowers` | **axismundi-object-projections** | `renderer.php:48` 이 `sensitive` 를, `integrations/actors.php:121` 이 `manuallyApprovesFollowers` 를 넣는다 -- 둘 다 OP 자기 코드 |

세 플러그인 중 **Calendar 와 Actors 는 이 필터를 한 번도 쓴 적이 없다.** 선례는
`integrations/media-library.php:43` 과 `axismundi-emoji/includes/outbound.php` 다.

## 조건부로 선언한다 (필수)

무조건 선언하면 안 된다. 선례가 이미 조건부다 -- Emoji 플러그인의
`axismundi_emoji_document_has_declaration()` 이 `tag` 와 중첩 `object` 를 훑어
해당 용어가 있을 때만 매핑을 넣는다.

```txt
핀   그 속성이 문서에 없으면 context IRI 를 넣지 않는다.
     Note 하나에 FEP-8a8e context 가 붙으면 안 되고, 이모지를 안 쓰는 문서의
     @context 는 지금과 같아야 한다.
핀   필터는 `$object` 를 받는다. 그것으로 판단한다.
     `array_key_exists()` 로 보고, 중첩이 필요한 경우(예: attachment 안의 sensitive)는
     선례처럼 훑는다.
```

## 버전 티어는 결정됐다

```txt
https://purl.archive.org/miscellany/1        ← major 티어. 이것을 쓴다.
```

리포트의 SHOULD 는 무버전 URL 이므로 **의도적 이탈이고 주석에 그렇게 적는다.** 근거는
둘이다 -- 발행된 context 가 바뀌면 이미 보낸 문서의 의미가 바뀐다는 이 프로젝트의 규칙,
그리고 1.0.1 changelog 가 "drops the types for boolean terms for compatibility with
existing implementations" 인데 `sensitive`·`manuallyApprovesFollowers` 가 바로 그
boolean 용어라서 그 아래로 고정하면 구현들이 맞추지 않은 버전을 인용하게 된다는 것.
리포트는 backwards-incompatible 변경을 예상하지 않고, major 가 생겨도 1.x.x URL 을
계속 서비스한다고 적는다.

FEP-8a8e 와 security/v1 은 버전 경로가 없으므로 그대로 쓴다.

## 하지 말 것

```txt
금지   용어를 인라인으로 선언한다
       셋 다 발행된 context 문서가 있다. 인라인 선언은 그 문서와 어긋날 수 있고,
       security/v1 은 용어가 셋이라 특히 그렇다. 배포된 Mastodon 도 context IRI 를 싣는다.

금지   내보내는 키나 값을 바꾼다
       이 작업은 @context 만 건드린다. sensitive 의 as: IRI 도 그대로다 --
       리포트가 "Changing the namespace for the terms would change their meaning
       and would not be backwards compatible" 라고 적는다.

금지   PropertyValue·value·size 를 같이 한다
       그 셋은 인라인 선언이고 V2·V2b 다. schema 접두사 결정이 걸려 있어 범위가 다르다.

금지   transformer 반환값에 @context 를 넣는다
       renderer.php:19 이 금지한다. 선언은 필터로만.

금지   무조건 선언으로 단순화한다
       위 "조건부" 절 참조. Emoji 플러그인 출력의 회귀가 된다.
```

## 검증

```txt
Event 문서의 @context 에 w3id.org/fep/8a8e 가 있고, 네 속성이 그 ns 로 정의된다.
Actor 문서의 @context 에 security/v1 과 miscellany/1 이 있다.
sensitive 를 가진 Note·attachment 문서의 @context 에 miscellany/1 이 있다.
**그 속성이 없는 문서의 @context 는 작업 전과 바이트 단위로 같다.**
  (Note 에 FEP-8a8e 가 붙지 않는다. 이모지 없는 문서가 그대로다.)
@context 가 단일 원소일 때 문자열로 접히는 동작이 깨지지 않는다
  (renderer 가 `1 === count($context)` 에서만 접는다 -- 이제 Actor·Event 는 배열이다).
내보내는 키와 값이 작업 전과 동일하다. 추가·삭제·변경 0.
변이: 세 IRI 를 각각 떼면 그에 해당하는 검사가 실패해야 한다.
변이: 조건 검사를 제거해 무조건 선언하게 하면, 해당 속성이 없는 문서의
      @context 동일성 검사가 실패해야 한다.
```

## ax_note 는 V1 의 대상이 아니다

초판에 "ax_note 를 먼저 재라" 를 V0 으로 넣었는데 **틀렸다.** `ax_note` CPT 는
제거 대상이다 -- `architecture/activitystreams-storage-boundaries.md:1174`,
"`ax_note` CPT 를 제거한다. 하드 컷오프이며 복원 경로를 만들지 않는다." 삭제될
투영에 선언을 붙이는 작업을 하지 않는다. "가장 많이 발행되는 타입" 이라고도 적었는데
그것도 측정하지 않은 말이었다 (2026-10-20 측정: `ax_note` post 76 행, publish 66).

컷오프 후에도 V1 은 영향받지 않는다.

```txt
sensitive 는 OP 의 renderer 가 **객체 종류와 무관하게** 넣는다(renderer.php:48).
실제로 지금 일반 `post` 문서가 sensitive 를 선언하고 있다 -- 측정됨.
따라서 OP 가 miscellany 를 등록하면 컷오프 전후의 Note 가 모두 덮인다.
컷오프 후 Note 는 §7 경로로 등록되는 타입이 되고, 그 경로는 Event·Place 와 같다.
```

측정이 필요한 것은 Note 가 아니라 **아직 안 본 투영이 더 있는지**다. 네 타입(Actor,
post, attachment, ax_event)을 쟀다. 작업 전에 OP 가 등록한 transformer 목록을 훑어
다른 공개 문서 타입이 있는지 확인하고, 있으면 같은 대조를 한다.

규범 context 는 런타임에 가져오지 않는다. 대조는 작업 중 수동 확인이고, 자동 감사는
V4 에서 고정 사본으로 한다 (레지스트리 문서: "runtime must not scrape the W3C
document").

## 참고 — 이 작업이 건드리지 않는 것

```txt
transport 허용 목록 3개(`inbox`·`endpoints`·`publicKey`)가 좁다는 문제
  레지스트리 문서가 이미 지적했다 (FEP-de8d 가 Actor 에 기여하지 못한다).
  V1 은 그 목록을 바꾸지 않는다.
등록 API / 충돌 검출
  레지스트리 문서의 미래 작업이다. V1 은 기존 필터를 쓴다.
  다만 V1 은 접두사를 새로 선언하지 않으므로 충돌을 만들지 않는다 -- 셋 다 IRI 하나씩이다.
```
