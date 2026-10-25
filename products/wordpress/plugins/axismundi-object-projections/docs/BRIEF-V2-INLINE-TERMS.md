# V2 브리프 — 발행된 context 가 없는 용어 셋을 인라인 등록한다

2026-10-25. 선행 완료: V1(10deeac), 어휘 레지스트리(5fbba33). 설계는 레지스트리가
들고 있으므로 **이 문서는 등록할 세 용어와 검증만** 담는다.

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
