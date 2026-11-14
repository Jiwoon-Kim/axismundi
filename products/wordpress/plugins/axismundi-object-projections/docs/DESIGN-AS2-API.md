# AS2 문서 API — 편집은 제출이 아니다

2026-11-15 결정. 편집기가 로컬 객체를 읽고 쓰는 표면을 OP 가 소유한다. 근거는 세 가지
경계이고, 셋 다 이미 우리가 다른 자리에서 내린 판단의 반복이다.

```txt
형식 ≠ 프로토콜   본문이 AS2 라는 것과 요청이 ActivityPub 이라는 것은 다르다
편집 ≠ 제출       미발행 객체를 고치는 것은 활동이 아니다
액터 축 ≠ 객체 축  누구로 행위하는가와 이 객체의 이 필드를 쓸 수 있는가는 다른 질문
```

## 1. 왜 OP 가 소유하나

레지스트리와 저장소가 이미 여기 있다.

```txt
data/activitystreams-vocabulary.json   types 54 · properties 63 · value_shape
object-registry.php                     로컬 소스 프로파일과 객체 식별
local-objects.php                       ax_local_objects — 편집 대상
remote-objects.php                      원격 캐시 (읽기 전용 영역)
renderer · transformers                 AS2 표현
```

JMAP 플러그인에 AS2 데이터 타입을 넣으면 **JMAP 이 OP 레지스트리에 의존**하게 되어 소유
경계를 가로지른다. 반대로 OP 가 들고 있으면 편집 대상과 그 어휘가 한 플러그인에 있다.

원장이 셋으로 갈려 있는 것이 이 슬라이스를 가능하게 한다 — `wp_posts` · 로컬 객체 · 원격
캐시 중 **로컬 객체만 경계가 깨끗하다.** 액터는 로컬/리모트가 섞여 있어 마지막이다.

## 2. JMAP 에서 빌리는 것과 빌리지 않는 것

Core 엔진은 쓰지 않는다. 개념만 빌리고, 무엇을 빌렸는지 적어 둔다.

```txt
빌린다      /get · /query · /set 의 모양 (타입별 메서드, 항목 배열, notFound)
            server-set 표기 — RFC 8620 §1.1. 런타임 값이 아니라 **타입 서명**이다
            객체별 권한 — RFC 8621 myRights.  **속성별이 아니다**
            부분 수정과 null 제거 — AP §6.3.1 과 같은 모양이라 이미 구현돼 있다
빌리지 않는다 Session · capabilities 협상 · accountCapabilities 구조
            state · /changes (§5 를 보라)
            back-reference · 요청 단위 한계
```

이 수(手)는 처음이 아니다. C2S Session Pre-FEP 가 같은 문장을 쓴다 — *"It borrows the
separation of credential, accessible scope and advertised capabilities from JMAP **without
requiring JMAP**."* 같은 판단을 두 번 내렸다는 뜻이고, 그래서 일관된다.

### 엔진을 버리면 같이 버려지는 것

명시적 범위다. JMAP 문서가 적어 둔 규칙 — *"Explicit JMAP `accountId` never falls back to
a cookie or primary Account."* REST 로 가면 쿠키가 옆에 있으므로 **ambient actor 로
흘러내리기 쉽다.** C2S outbox 에서 이미 겪은 함정이고 규칙이 있다: **principal 과 acting
Actor 는 별개 인자이고 둘 다 추론 금지.** 이 API 의 첫 감사 항목이다.

## 3. 네임스페이스

```txt
axismundi/activitystreams  OP 소유. OP 저장소 범위. 편집용 조회 · 검색 · 저장
axismundi/activitypub      프로토콜 표면 — inbox · outbox · 액터 문서 · uploadMedia
axismundi/v1               앱 조회와 운영 동작
```

**둘의 관계는 소셜 프로토콜 API ∥ CMS API 다** (오너, 2026-11-15). `activitypub` 은 연합이
무엇을 주고받는가이고, `activitystreams` 는 이 사이트가 자기 객체를 어떻게 다루는가다.
`wp/v2` 가 WordPress 의 CMS API 인 것과 같은 자리이며, 그래서 `/types` 같은 발견
엔드포인트도 같은 모양으로 따라온다.

철자는 저장소의 기존 관례가 정한다. 실측하면 두 철자가 **다른 용도로** 쓰이고 있다 —
`activitystreams` 183 회는 스펙·어휘·컨텍스트 IRI 쪽(`activitystreams-vocabulary.json`,
`activitystreams-core`, `activitystreams-uri.php`), `as2_` 와 `axismundi_as2_` 는 PHP
식별자와 컬럼 쪽(`as2_type`, `axismundi_as2_normalize_uri()`). **스펙을 향한 이름은 풀어
쓰고 코드 식별자는 줄인다.** REST 네임스페이스는 URL 에 드러나고 문서화되는 쪽이므로
풀어 쓴다.

`as2` 라는 이름에 한 번 반대했다가 거뒀다. 반대의 근거는 "저장소를 가로지르는 계약을
약속하게 된다" 였는데, **OP 소유로 OP 저장소에만 범위를 두면 그 약속을 하지 않는다.** 그
근거는 철자를 바꿔도 그대로다 — 범위를 정하는 것은 이름이 아니라 소유와 문서다.

`axismundi/activitypub` 아래에 두지 않는 이유는 그 접두사가 **프로토콜 표면**을 뜻하기
때문이다. 이미 `activitypub/objects/replies` 가 AP 하위 컬렉션으로 그 자리를 쓰고 있다.

**봉투는 우리 것이고 AS2 가 아니다.** 발행 상태 · 수정 가능성 · 페이지 · 검색 조건은 AS2
어휘에 없다. 응답에서 이 둘을 섞지 말 것.

## 4. Session 과의 경계

```txt
Session   액터 축.  이 자격증명이 **누구로** 행위하는가 — 완전한 목록
객체 API   객체 축.  이 객체의 이 필드를 쓸 수 있는가 — 객체별
```

**Outbox 에 `Create` 를 제출할 수 있다는 것이 로컬 객체를 편집할 수 있다는 뜻이 아니고,
그 반대도 아니다.** 같은 자격증명과 액터 범위를 공유해도 두 capability 는 따로다.

Session 확장은 첫 슬라이스의 선행 조건이 아니다. 붙이는 시점은 숫자가 아니라 조건이다 —
**클라이언트가 URL 을 조립하지 않고 편집 API 를 발견해야 할 때.** uploadMedia 에서 세운
원칙과 같다(광고된 값을 읽지 경로를 조립하지 않는다).

업스트림과 내부를 가른다.

```txt
우리 Session    활동 capability + 객체 저장소 capability.  벤더 capability 는
                네임스페이스 URI 로 표준 것 옆에 둔다 — JMAP 방식
토픽 8990       활동에 관한 것으로 남긴다.  "객체 저장소가 있다" 는 우리 발명이지
                ActivityPub 에 있는 것이 아니다
```

Pre-FEP 가 스스로 건 제약도 그대로 적용된다 — *"a client MUST NOT read the absence of an
operation as a denial"*, *"the Session is not a preflight check"*. **편집 가능 여부를
Session 으로 판단하지 않는다.** 저장소가 객체별로 답한다.

## 5. 아직 정하지 않은 것

### id

이것이 이 API 의 실제 설계 문제이고 나머지는 보일러플레이트다.

```txt
JMAP id   서버가 발급하는 불투명 문자열.  account 범위.  URL 템플릿에 들어간다
AS2 id    URI.  전역.  우리 것은 불변이고 공개다
```

blob 에서 한 번 푼 문제가 다시 온다(`blobId` 를 URL 로 두지 않기로 했다). URI 를 키로 쓰면
범위가 어색하고 인코딩이 이중화되며, 별도 id 를 쓰면 클라이언트가 매핑을 왕복한다. **깔끔한
일반 답이 있으면 그것이 기여이고, "URI 를 쓰고 로컬만" 으로 끝나면 구현이다.** 구현해 보기
전에는 갈리지 않는다.

### state 와 /changes

**정정(2026-11-15):** "append-only 원장이라 `/changes` 가 가장 싸다" 는 과한 말이었다.
단조 증가하는 마지막 id 는 **새 행 커서**로는 쓸 수 있지만 상태 토큰이 아니다 — 원장이
그대로여도 **권한이 바뀌면 클라이언트의 조회 결과에서 행이 사라진다.** JMAP `/changes` 는
`destroyed` 도 보고해야 하므로 커서만으로 부족하다.

### 나머지

```txt
액티비티 원장 조회   나중에 **읽기 capability** 로 연다.  Outbox 컬렉션과 다른 표면이고,
                   원장 조회를 연다고 /activities/set 까지 열 이유는 없다
액터                로컬/리모트 혼재.  마지막
원격 객체            읽기 전용 영역.  /set 대상이 아니다
```

## 6. 따라오는 리팩토링 하나

지금 **데이터는 OP 에 있고 분류는 Activities 에 있다.**

```txt
OP          axismundi_op_local_note_server_set() · axismundi_op_local_property_known()
Activities  c2s-local-object.php 가 그것을 읽어
            server_set / not_updatable / unsupported_property 로 분류한다
```

OP 소유 편집 API 가 생기면 **같은 분류를 두 번 하게 된다.** 분류기를 OP 로 내리고 Activities
가 호출하면, "두 표면의 거부가 같은 모양" 이 설계가 아니라 **한 함수**가 된다. 편집기는
저장에서 거부당한 필드와 제출에서 거부당한 필드를 같은 코드로 표시할 수 있다.

## 7. 첫 검증 기준

하나면 된다.

```txt
미발행 Video 를 activitystreams API 에서 찾아 주소를 수정·저장한다
  → 원장은 그대로다 (Activity 0, 배달 작업 0)
같은 URI 를 Outbox 에 제출한다
  → 그때만 Create 가 생긴다
```

확장 속성도 같이 본다 — **JSON 에만 있는 속성을 넣고 Visual 에서 제목을 바꿔도 그 속성이
살아 있어야 한다.** 어휘 레지스트리가 모른다는 것은 저장 금지가 아니라 분류가
`unsupported_property` 라는 뜻이다.

이 흐름이 JMAP 에서 빌린 구분이 **왜 필요한지**를 보여주면서, JMAP Core 도 Pre-FEP 범위도
건드리지 않는다.

## 관련

```txt
axismundi-activities/docs/C2S.md §14     같은 Create, 다른 진입 경로
axismundi-activities/docs/DESIGN-C2S-COMPOSER.md §10.1   편집기 ∥ 컴포저
docs/UPSTREAM-SOCIALHUB-PRE-FEP-C2S-SESSION.md           액터 축
PROTOCOL-ROUTE-NAMESPACES.md                             경계 표
axismundi-jmap/docs/IMPLEMENTATION-CORE-RUNTIME.md       빌리지 않는 엔진
```
