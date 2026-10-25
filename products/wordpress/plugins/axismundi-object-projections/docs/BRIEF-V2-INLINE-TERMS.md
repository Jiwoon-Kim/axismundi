# V2 브리프 — 발행된 context 가 없는 용어 셋을 인라인 등록한다

2026-10-25. 선행 완료: V1(10deeac), 어휘 레지스트리(5fbba33). 설계는 레지스트리가
들고 있으므로 **이 문서는 등록할 세 용어와 검증만** 담는다.

## 구현 기록 (2026-10-25)

구현 완료, 리뷰 대기. Actors가 PropertyValue/value를 `actor-profile-fields` 인라인
그룹에 등록하고, OP media 통합이 peertube namespace와 size를 등록한다.
schema namespace는 재등록하지 않는다. value는 PropertyValue 타입과 함께 있을 때만,
size는 url/attachment 아래 rendition Link와 함께 있을 때만 선언한다.

### 보장한 것

- `audit-inline-terms.php` 26개 통과. 로컬 Actor/post/attachment/Event/followers/outbox
  투영에서 V2 용어 셋만 제외한 레지스트리를 비교 기준으로 사용했다. Actor와
  attachment context만 바뀌고 나머지 네 클래스는 바이트 동일하다.
- 로컬 Actor 투영의 실제 키·타입을 AS2 JSON-LD 문서, Security/Miscellany context,
  인라인 매핑과 대조해 미선언 0개 확인. 언어맵의 언어 태그는 용어로 세지 않았다.
  대소문자를 구분해 대조했다. 런타임 fetch 기능을 추가한 것은 아니다.
- 지정 감사 6개 통과: vocabulary-context-registry 43, context-iris 38,
  object-assembly-baseline 9, post-article 23, actor-projection 12,
  object-renderer 54. 원본 로그: 로컬 `%TEMP%/axismundi-v2-audits.log`.
- 변이 네 개 검출 후 복원: peertube 등록 누락, schema legacy base 사용은 등록 실패;
  value 그룹 분리는 단일 인라인 맵 비교 실패; size를 root-only로 제한하면 중첩 검사 실패.
- PHP lint 및 `git diff --check` 통과.

### baseline 격리 결과

브리프 예상과 달리 현재 live fixture에서 변경된 것은 **standalone Image 한 개**다.
Article/Audio/embedded/view 해시는 모두 그대로다. size 등록만 제외하면 이전 fixture로
9/9 복귀함을 먼저 확인한 뒤 Image 해시 하나만 갱신했다. 소스 콘텐츠는 변경하지 않았다.

### 확인하지 않은 것

전수 감사, 스테이징 업로드/HTTP 측정, 원격 JSON-LD expansion은 하지 않았다.
미선언 0은 로컬 Actor 표본의 용어 이름 대조 결과이지 모든 Actor 문서에 대한 일반형
검증은 아니다(V4). 기존 실패 네 건은 재실행하거나 수정하지 않았다. 패키징·커밋·push도
이 구현 단계에는 포함하지 않았다.

## 범위

```txt
PropertyValue   type      https://schema.org/PropertyValue
value           property  https://schema.org/value
size            property  https://joinpeertube.org/ns#size
```

V1 의 셋과 달리 이들에는 **발행된 context 문서가 없다.** 그래서 패턴 ②(인라인 선언)이고,
레지스트리의 `context` 필드에 인라인 매핑을 넣는다.

끝나면 **Actor 문서의 미선언 용어가 0 이 된다.** 스테이징 실측 기준이다.

```txt
스테이징 /@thaumiel999 (V1 이전, 2026-10-25 측정)
  미선언 키    manuallyApprovesFollowers  publicKey  publicKeyPem  owner  value
  미선언 타입  PropertyValue
  → V1 이 앞의 넷,  V2 가 value·PropertyValue.  남는 것 없음.
```

## 소유자 — 측정으로 정했다

```txt
PropertyValue / value
  axismundi-actors/includes/profile-fields.php:355
    axismundi_actors_profile_field_attachments() 가 {type, name, value} 를 만든다
  OP 의 actors 통합이 183 행에서 그 결과를 attachment 에 넣는다
  → 기능(Actor 프로필 필드)이 Actors 것이므로 **Actors 가 등록한다**

size
  axismundi-object-projections/includes/integrations/media-library.php:174,187
    렌디션 Link 에 정수 바이트를 싣는다
  → **OP 가 등록한다** (media-library 통합)
```

## 네임스페이스

```txt
schema     이미 등록돼 있다 (https://schema.org/). 참조만 한다. 재등록하면 거부된다.
peertube   **아직 없다. 등록해야 한다.**
             base_iri   https://joinpeertube.org/ns#
             reference  https://docs.joinpeertube.org/api/activitypub
             (실측: https://joinpeertube.org/ns 는 200 이고 그 docs 로 리다이렉트한다)
```

## 조건

```txt
PropertyValue   type 이고 attachment 안에 중첩된다.
                타입 수집이 중첩 경로에서 걸려야 한다 -- 레지스트리가 이미 하는 일이다.
value           property. PropertyValue 와 **같은 context 그룹**에 둔다 --
                항목마다 둘이 함께 나타나므로 그룹을 나누면 선언이 둘로 쪼개진다.
size            property. 렌디션 Link 안에 중첩된다.
                attachment·url 아래 Link 에 나타나므로 중첩 경로가 필요하다.
```

```txt
핀   schema 접두사는 `https://schema.org/` 다. `http://schema.org#` 를 쓰지 않는다.
     배포된 Mastodon·Misskey 가 레거시 형태를 쓰지만, Mastodon 자기 명세가 그것을
     incorrect 라고 적고 정규형을 지정한다. 근거는 레지스트리 문서의 2026-10-25 절.
핀   size 는 schema:contentSize 가 아니다. FEP-1311 이 "file size in bytes is
     borrowed from PeerTube" 로 문서화하고 joinpeertube ns 로 선언한다.
     schema.org 의 contentSize 는 단위를 문자열에 담는 관행이라 의미가 다르다.
```

## 검증 — 슬라이스 범위

전수 감사를 돌리지 않는다. 다음으로 충분하다.

```txt
@context 바이트 비교    HEAD 대비 6 개 문서 클래스.
                        actor / post / attachment / event / followers / outbox
                        변해야 하는 것은 actor(PropertyValue·value)와
                        attachment(size)뿐이고, 나머지 넷은 **동일해야 한다.**
미선언 0                Actor 문서를 받아 규범 context 와 대조했을 때 미선언 키·타입이
                        없어야 한다. 언어맵 키(nameMap·summaryMap 아래)는 제외한다.
감사                    audit-vocabulary-context-registry / audit-context-iris /
                        audit-object-assembly-baseline / audit-post-article /
                        audit-actor-projection / audit-object-renderer
바이트 baseline         article·image·audio 해시가 바뀐다 (attachment 의 size 때문).
                        **격리 먼저**: size 등록만 떼고 baseline 이 돌아오는지 확인한 뒤
                        재승인하고 이유를 그 파일 관례대로 주석에 적는다.
```

```txt
변이   peertube 를 등록하지 않고 size 에 그 접두사를 쓰면 등록이 거부돼야 한다.
변이   schema 를 http://schema.org# 로 재등록하면 prefix 충돌로 거부돼야 한다.
변이   value 를 PropertyValue 와 다른 그룹으로 분리하면 선언이 둘로 쪼개지는 것이
       @context 비교에서 드러나야 한다.
변이   size 의 조건에서 중첩 경로를 제거하면 attachment 선언 검사가 실패해야 한다.
```

## 범위 밖

```txt
수신측 레거시 http://schema.org# 허용     expander 가 없으므로 지금 할 일이 없다. V4.
digestMultibase                           내보내지 않는다. 원격 바이너리 캐시의
                                          content_hash 를 공개할 때 credentials/v2 를 쓴다.
w3id.org 등록 / usedIn 전환               별개 작업.
기존 감사 실패 4 건                        audit-standalone-projection /
                                          audit-calendar-acl / audit-ics / audit-rest-read.
                                          악화 여부만 보고 원인은 추적하지 않는다.
```

## 보고 형식

"몇 개 통과" 가 아니라 **보장한 것 / 확인하지 않은 것**으로 나눠 적는다.

## 리뷰 결과 (2026-10-25)

통과.

### 보장한 것

```txt
@context 바이트 비교   V1+레지스트리 상태 대비 6 개 문서 클래스.
                       **actor 와 attachment 만** 변했고 post·event·followers·outbox 는 동일.
                       actor      + {PropertyValue, schema, value}  -- 한 그룹
                       attachment + {peertube, size}
                       schema 는 정규형 https://schema.org/
Actor 미선언 0         참조 context 를 해소하는 검사로 재측정 → 키 0, 타입 0.
                       V1+V2 가 Actor 문서를 닫았다.
schema 레거시 거부     axismundi_op_register_namespace('schema', 'http://schema.org#')
                       → REFUSED (ax_op_namespace_duplicate), 등록값은 정규형 유지
size 경로 제한         url·attachment 아래 Link 에서만 선언.
                       top-level size, tag 아래 Link 는 선언하지 않는다.
변이                   peertube 네임스페이스 미등록 → LogicException
                         ("inline mapping must agree with the registered namespace")
                       value 를 PropertyValue 와 다른 그룹으로 분리
                         → FAIL: Profile field aliases produce one exact schema map
감사                   audit-inline-terms 26 / audit-vocabulary-context-registry 43 /
                       audit-object-assembly-baseline 9, 전부 0 실패
```

구현이 브리프보다 엄격한 지점이 하나 있다. `size` 의 조건이 합집합이 아니라 **연접**이고
경로까지 제한한다 -- `Link` 타입과 `size` 키가 **둘 다** `url`/`attachment` 아래 있을 때만
선언한다.

### 브리프의 틀린 예측 하나

"바이트 baseline 의 article·image·audio 해시가 바뀐다" 고 적었는데 **image 하나만**
바뀐다. V1 에서 `sensitive` 가 셋 다 건드린 패턴을 그대로 가져온 추측이었고, 측정하지
않았다. 실제로는 렌디션 Link 에 바이트 크기가 있는 fixture 가 standalone Image 뿐이다.
구현 쪽이 격리를 먼저 하고(size 등록만 떼면 이전 해시로 9/9 복귀) image 만 재승인했다.

### 확인하지 않은 것

```txt
전수 145 감사      돌리지 않았다. 배포 전 1 회.
원격 expansion     V4.
스테이징·패키징     아직. 배포는 S3a/S3b/S4 + V1 + 레지스트리 + V2 를 한 번에.
path 변이          경로 제한을 풀어도 현재 문서에서 관측되지 않는다.
                   **관측 불가능한 변이이지 핀 누락이 아니다** -- 제한 자체는 위에서 측정했다.
emojiReactions     절대 IRI 키라 어떤 context 에도 없는 것이 정상이다.
                   미선언 검사는 절대 IRI 키를 제외해야 한다 (V4 감사 규칙).
```
