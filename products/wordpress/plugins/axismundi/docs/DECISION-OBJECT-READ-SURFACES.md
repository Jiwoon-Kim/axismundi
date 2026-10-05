# 결정 기록: Object Read Surfaces — Admin과 Social이 객체를 읽는 방식

## 상태

**설계 중. 2026-10-03.** 구현 전에 경계를 적어 둔다. 미결은 §7에 모아 두었고 그것들은
프로젝트 소유자의 판정이다.

두 앱이 같은 객체를 읽어야 하는데 목적이 다르다. Admin은 `article`/`note`/group context를
`wp_posts`에서 직접 보지 않고 **Object Projections가 투영한 JSON**으로 봐야 하고, 원격 객체
캐시도 봐야 한다. Social은 실시간 피드와 단일 객체·컬렉션·스레드를 봐야 한다.

## 1. 이미 결정된 것 — 다시 논쟁하지 않는다

전부 저장소에 적혀 있다. 새로 정할 것과 섞지 않기 위해 먼저 인용한다.

```text
activities/docs/BOUNDARIES.md
  Object Projections   Object/collection JSON-LD, remote observations, cache leases
  Activities           Activity ledger, Follow/Block state, Like/Announce,
                       logical inbox/outbox membership
```

두 플러그인이 **자기 경계에 대해** 같은 모양의 말을 했다. 각각이 덮는 범위를 정확히 적는다 —
여기서 일반 법칙을 끌어내지 않는다(§1.1).

```text
op/includes/object-view-model.php:22
  "OP never reads product storage; the adapter's supports and transform callbacks do."
     → 덮는 것: 다른 제품의 저장소(Note CPT, Media Library)를 OP가 직접 읽지 않는다.
     → 덮지 않는 것: OP 자신의 테이블. OP는 remote cache를 소유하고 실제로 질의한다
       (remote-objects.php · object-relations.php · hashtags.php · hashtag-archive.php).

actors/docs/PROJECTIONS.md:11-12, 27-28
  "it does not run the projection's query or own its content."
  "Actors coordinates only URL, label, visibility, and order."
     → 덮는 것: actor hub의 projection registry — 다른 플러그인 아카이브로 가는 링크.
     → 덮지 않는 것: 시스템 전체의 query 원칙.
```

### 1.1 이 둘에서 일반 법칙을 끌어내지 않는다

초판이 여기서 "**조율하는 플러그인은 query를 갖지 않는다**"를 확정된 일반 규칙처럼 적었고,
그것이 틀렸다. 두 인용은 각자의 경계 선언이고, 합쳐도 그 명제가 되지 않는다. OP가 자기
remote cache를 질의하는 것이 반례다.

**저장소에 인용이 있다**와 **그 인용에서 더 넓은 시스템 법칙이 도출된다**는 다르다. 전자를
근거로 후자를 쓰면 §7의 미결을 이미 결정된 것처럼 좁히게 되고, 실제로 그렇게 됐다 —
초판의 선택지 표에 A4가 없었다.

**원격 객체에 대해 우리는 권한이 없다.** 이 한 문장이 §4의 Admin 분리를 혼자 정당화한다.

```text
op/docs/REMOTE-OBJECTS.md:8-11
  "wp_ax_remote_objects stores a rebuildable observation of a remote ActivityStreams
   object. It is not an Activity ledger, inbox, delivery queue, local post, or
   authority over the remote object. The canonical remote URI is always the identity;
   the local row id is private implementation detail and must never appear in JSON-LD
   or public URLs."
```

**스레드 그래프는 이미 URI 기반이고 local/remote를 가리지 않는다.**

```text
op/includes/thread-edges.php:3-6
  "URI-keyed thread graph: one direct-parent edge per reply, local or remote."
  "This is a rebuildable index, not an authority: wp_comments is not replaced and
   no envelope moves here."
```

이것이 §5의 "local이든 remote든 `inReplyTo`면 한 스레드"를 **새 장치 없이** 만족시키는
이유다. 기구는 이미 있고 Social에 필요한 것은 그 위의 읽기 표면 하나다. 다만 `root`/`depth`는
편의값이라 정확성에 기대지 않는다고 그 파일이 명시한다 — 스레드 렌더는 direct parent로
조립한다.

## 2. 핵심 — 묶는 축이 앱마다 다르다

이 문서의 결론 한 줄이다.

```text
Admin    정책 범위로 나눈다      내가 무엇을 할 수 있는가
Social   스레드로 합친다        이것이 무엇에 대한 답인가
```

같은 두 저장소(local projection, remote observation)가 **한쪽에서 갈리고 다른 쪽에서
합쳐진다.** 모순이 아니다. 목록의 경계는 존재론이 아니라 **그 표면이 할 수 있는 행동**이
정하고, 두 표면이 할 수 있는 행동은 다르다.

둘 다 가능한 이유는 **조인 키가 URI**이기 때문이다. local 투영과 remote observation 모두
canonical URI를 가지고, thread edge도 URI로 쓰여 있다. row id로 조인하는 설계였다면 Social의
요구(local이든 remote든 `inReplyTo`면 한 스레드)가 불가능했을 것이다.

## 3. Admin — local object 읽기 표면

**source table을 직접 해석하지 않는다.** `article`, `note`, group context 전부 OP의 정규화된
view model을 읽는다. 이유는 편의가 아니다.

- 연합에 나가는 것이 투영 결과다. Admin이 `wp_posts`를 보면 **나가지 않는 것을 검토**하게 된다.
- Tombstone에는 `WP_Post`가 없다. OP의 view-model 경로가 존재하는 이유가 그것이다
  (`object-view-model.php`: "a Tombstone has no `WP_Post` and must still render").
- `post_author` ≠ 발행 Actor다. canonical author는 Actor이고, 그 해소는 투영이 한다.

**"투영된 JSON"이 저장된 JSON을 뜻하지 않는다.** local 객체에 저장된 projection은 없다.

```text
op/docs/LOCAL-OBJECTS.md:220-222
  wp_post       the editable source, current
  OP            reads the current wp_post and projects the current AS2 Object, dynamically
  Activities    what was published or interacted with: ... the exact serialized payload
                for as long as delivery needs it
```

`payload_json`을 가진 것은 **remote observation 쪽뿐**이다 — 그건 수신 증거이기 때문이다.
그래서 이 문서는 **local projection 캐시를 새로 만들라는 요구가 아니다.** Admin은 OP가 요청
시점에 만든 view model을 읽는다. 초판 문장("OP가 투영한 JSON")이 저장된 JSON으로 읽힐 수
있었고, 그대로 두면 아무도 원하지 않는 local JSON 캐시를 만들어야 한다는 압력이 된다.

## 4. Admin — remote object cache는 별 목록이다

local과 같은 목록에 넣지 않는다. **할 수 있는 행동이 겹치지 않기 때문이다.**

| | local projection | remote observation |
|---|---|---|
| 권한 | 우리가 저자 | 없음 (REMOTE-OBJECTS §1) |
| 편집 | 가능 | **불가** |
| 삭제 의미 | Tombstone 발행 → 연합 | 로컬 캐시 축출. 원본은 그대로 |
| 중재 의미 | 내용 자체를 다룸 | 로컬 표시 억제 · 재수집 거부 |
| 재수집 | 해당 없음 | re-fetch · lease · 만료 |
| 식별자 | 로컬 + canonical URI | canonical URI만 |

한 목록에 합치면 **행 하나하나에 "이건 어떤 종류인가"를 묻고 나서야 버튼을 그릴 수 있고**,
운영자는 "삭제"가 두 가지 다른 일을 뜻하는 화면을 쓰게 된다. 그 혼동의 비용이 목록 두 개를
쓰는 비용보다 크다.

Inspector가 한 행에 대해 보여야 하는 것 — 이게 API 계약의 첫 검증 표면이다.

```text
raw payload        수집한 그대로. 정규화가 무엇을 잃었는지 보려면 둘이 나란히 있어야 한다
정규화 결과        view model
캐시 · lease 상태  object_status · 만료 · 어떤 Activity가 lease를 쥐고 있는가
resolution_state   부모 URI가 지금 해소되는가 (thread-edges)
```

## 5. Social — 피드는 Activities, 객체는 OP

```text
실시간 피드        Activities      무엇이 언제 일어났는가 (Create · Announce 선택)
단일 객체          OP              그 객체가 무엇인가
컬렉션             OP              OrderedCollection
스레드             OP thread edges URI 조인. local + remote 한 스레드
```

**피드는 원장의 질의이고 객체는 투영의 질의다.** 이미 그렇게 등록되어 있다 —
`actors/docs/PROJECTIONS.md:16`이 프로필 피드를 Activities의 primary surface로 두고, 객체
렌더는 OP가 한다. Object 목록도 같은 분업을 따르면 새 claim이 없다(§7-A).

**Social이 보지 않는 것**을 명시한다. 이 세 가지가 Admin 전용이고, 한 번의 실수로 공개
표면에 닿으면 안 된다.

```text
원본 payload       정화 전 문자열
캐시 · lease 상태  운영 내부 상태
moderation capability
```

## 6. 두 렌더 경로, 하나의 모델

코어가 하는 것과 같다 — 테마 요청에서는 PHP가 `WP_Query`로 읽어 HTML을 렌더하고, 에디터
요청에서는 같은 `wp_posts`를 REST JSON으로 준다. **표면마다 다르고 전역 선택이 아니다.**

```text
공개 object 영구링크   서버 HTML    SEO · JS 없음 · Tombstone
                       → OP에 이미 있다 (object-view-route.php, renderer.php,
                         axismundi/object-view 블록)
/social/ SPA           REST JSON    피드 · detail · PWA
```

## 7. 미결 — 프로젝트 소유자의 판정

### A. 목록 query 소유권

**목록은 제품 저장소를 가로질러 질의**해야 하고, OP는 다른 제품의 저장소를 읽지 않는다(§1).
그 이상은 결정되지 않았다.

| | 목록 query 소유 | 대가 |
|---|---|---|
| A1 | OP가 cross-product query claim을 가짐 | `object-view-model.php:22`를 되돌림. 이유를 적어야 함 |
| A2 | 제품이 각자 목록, OP는 행마다 view model | 경계 유지. **클라이언트가** N개를 합성 |
| A3 | 교차 질의는 Activities(원장)가 소유 | 프로필 피드 선례와 정합. Activities가 generic object catalog가 됨 |
| A4 | 제품이 각자 query를 제공하고, `axismundi`의 read-composition API가 **공개 계약만** 조합 | 경계 유지 + 합성에 서버 측 소유자가 생김. 새 층이 하나 늘어남 |
| A5 | 합성을 **읽을 때가 아니라 쓸 때** 한다 — 제품이 공통 `wp_ax_object_index`에 행을 쓰고, OP는 그 한 테이블만 조회 | 경계 유지 + 단일 테이블 질의. 파생 상태가 다시 생기고, 모든 제품이 모든 변경에서 index를 써야 함 (§A5) |

**A4가 초판에 없었다.** §1.1이 그 누락의 원인이다 — "조율자는 query를 갖지 않는다"를 법칙으로
쓰면 capstone이 합성 층을 갖는 안이 애초에 후보에 오르지 못한다. A4는 A2와 다르다. 합성이
클라이언트에서 서버로 옮겨가고 **이름 있는 소유자**를 얻는다. 두 앱을 이미 `axismundi`가
들고 있으므로 자리로는 자연스럽고, A3처럼 Activities를 범용 객체 카탈로그로 만들 필요도 없다.

판정은 소유자의 것이고, **다섯 안 중 어느 것도 기존 문서가 배제하지 않는다.**

### A5. 쓸 때 합성하기 — 이미 절반 있는 테이블

2026-10-04 추가. A1~A4는 모두 **읽을 때** 합성하는 네 방식이다. A5는 합성을 쓸 때로
옮긴다 — fan-out-on-write, 즉 materialized read model이다.

```text
각 제품이 자기 객체를 투영해 index 행을 쓴다
        ↓
OP는 자기 index 한 테이블만 조회한다
        ↓
선택된 URI를 각 소유자가 hydrate해 ObjectView를 만든다
```

**이 테이블은 이미 존재한다.** `wp_ax_object_index`,
`axismundi-object-projections/includes/remote-objects.php`. 라이브 스키마를 읽은 결과:

```sql
object_uri_hash        char(64) NOT NULL        PRIMARY KEY
publicly_listable      tinyint(1)
object_status          varchar(12)   -- active | tombstone
source                 varchar(12)   -- local | remote
attributed_to_uri_hash char(64)
is_reply               tinyint(1)
has_group_context      tinyint(1)
primary_group_uri_hash char(64)
updated_at             datetime
KEY listing_context (publicly_listable, has_group_context)
```

원격 캐시 저장이 트랜잭션 안에서 이 index를 갱신하고, local은 각 제품이 자기 source를
열거해 OP의 writer 하나를 부르는 backfill 구조다. 즉 OP가 Note CPT나 `wp_posts`를 직접
join하지 않는다는 §1의 경계는 이미 지켜지고 있다.

**그러나 지금 상태로는 목록 질의를 할 수 없다.** 함수 주석이 스스로 "listing state"라고
적고 있고, 그 말이 정확하다 — 상태는 알지만 순서를 모른다.

```text
없음: 정렬 키            updated_at은 투영 갱신 시각이며 정렬에 쓰면 안 된다
없음: object_type        Note인지 Article인지 거를 수 없다
없음: 소유자(adapter)    어느 제품이 hydrate할지 알 수 없다
없음: cursor용 복합 인덱스  두 인덱스 모두 시간순 페이지네이션을 받치지 못한다
```

#### A5의 대가 세 가지

1. **파생 상태가 다시 생긴다.** `LOCAL-OBJECTS.md:220`이 local projection을 동적으로 둔
   것은 아무도 원하지 않는 캐시를 피하려는 결정이었다. A5는 본문 없는 좁은 형태지만 파생
   저장을 다시 들인다. 모든 제품이 trash · untrash · 영구 삭제 · 가시성 변경 · group 변경 ·
   `inReplyTo` 변경마다 index를 써야 하고, 하나를 빠뜨리면 목록이 조용히 거짓말한다.
   — 완화 요인: 코드가 이미 "rebuildable projection"을 주장하고 backfill이 있다. 드리프트는
   복구 가능하다. 단, 모든 제품이 동작하는 backfill을 유지해야 하고 재구축이 돌릴 만큼
   싸야 한다.

2. **`publicly_listable` 불리언 하나가 authorization을 담지 못한다.** 가시성은 두 축이고
   audience는 공용 resolver가 소유한다. 불리언은 그것을 접는다. 공개 컬렉션은 괜찮지만
   followers-only나 group-members 목록은 index가 답할 수 없다. audience 컬럼을 늘리면
   resolver의 일을 복제하게 되고, 늘리지 않으면 audience 범위 목록은 다른 읽기 경로가 된다.
   **이것이 A5의 진짜 경계 질문이다.**

3. **원격 `published`는 신뢰할 수 없고 단조롭지도 않다.** 스키마와 무관한 정합성 문제다.
   원격 서버가 주는 `published`는 과거로 찍힐 수 있으므로, local과 remote를 섞은 "최신순"
   목록에서는 새 항목이 과거에 끼어든다. Activity 원장은 로컬 수신 시각으로 정렬하므로 이
   문제가 없다. 피드는 §5에서 이미 Activities 몫이라, 이 문제는 객체 컬렉션에만 남는다.

#### 권고 (구현자의 것이며 판정이 아니다)

index를 **후보 집합(membership)의 권위로** 채택하고 **순서의 권위로는 채택하지 않는 것**.
어느 URI가 이 컬렉션에 속하는지는 index가 답하고, 사용자에게 보이는 "최신순"은 Activities의
로컬 시각을 탄다. 그러면 대가 3이 사라지고, `published_at`을 index에 또 복제할 필요도 없다.

그러므로 A5를 고를 경우 다음 판정은 "새 합성 API를 만들까"가 아니라 **"이 index를 목록 read
model로 공식 채택하고 어떤 열 · cursor · authorization을 둘까"**가 된다.

### A′. 단일 조회도 "논쟁 없음"이 아니다

초판이 `GET …/objects/{uri}`를 "논쟁 없음"으로 적었다. 틀렸다. **lookup seam은 있고 REST
contract는 새로 결정한다**가 정확하다.

```text
있는 것   URI에서 source를 찾는 seam. local/remote fallback 포함
없는 것   viewer authorization · local visibility 평가 · remote cache의 공개 정책
          · API 버전
해소됨    tombstone 응답 (§7-A″)
```

tombstone은 §7-A″에서 해소됐다. 나머지는 미결이다.

### A″. Tombstone — 404는 플랫폼 한계가 아니라 호스트 우회다

이 구분이 410을 어디에 둘 수 있는지를 정하므로 먼저 적는다. **원인은 WordPress도 블록
테마도 아니다.** WordPress.com Atomic이 **HTML** `410` 응답 본문을 자기 오류 페이지로 교체해서,
테마의 `object-tombstone` 템플릿이 사용자에게 도달하지 않는다(OP 0.0.70).

그래서 위험한 것은 **HTML 응답뿐**이고, JSON은 이미 반증이 있다.

```text
op/includes/router.php:155
  return 'Tombstone' === (string) ( $object['type'] ?? '' ) ? 410 : 200;
```

AP 협상 요청은 **오늘 실제 410을 주고 그게 Atomic에서 동작한다.** 그 옆에서
`object-view-route.php:134-141`이 같은 객체의 HTML을 404로 내리며 이유를 주석으로 달고 있다.

**다만 Object read API가 안전하다는 것은 아직 추론이다.** 검증된 것은 negotiated
`application/activity+json`의 410이고, 미래 REST endpoint의 `application/json` delivery가
아니다. 같은 비-HTML 클래스라 매우 그럴듯하지만, Atomic의 교체 규칙이 content type을 보는지
라우트를 보는지 우리는 모른다. **endpoint를 만들 때 그 호스트에서 실제 410 본문과 content
type을 검사하는 acceptance test를 통과해야 해소다.** 그때까지는 추론으로 둔다.

```text
AP 협상 (JSON)            410   검증됨 (router.php:155)
Object read API (JSON)     410   추론. 구현 시 호스트 acceptance test로 확인
블록테마 사람용 HTML        404   호스트 우회. 의도적 비표준, 되돌리지 말 것
Social shell (HTML)        200   ↓
Social TombstoneTemplate         응답 본문으로 렌더
```

**Social shell을 410으로 만들면 안 된다.** 그 shell은 HTML이고, HTML 410이 바로 Atomic이
바꿔치기하는 것이다. 410 문서를 만들려는 시도가 404가 존재하는 이유를 그대로 재현한다.
프론트앱이 410을 **기반으로** 템플릿을 갖는 것은 맞고, 그 410은 **API 응답의 상태코드**이지
문서의 상태코드가 아니다. shell의 PHP 라우트를 우리가 소유하고 있으므로 기술적으로는 가능하다는
점이 함정이다 — 가능한 것과 안전한 것이 다르다.

**세 번째 어휘를 만들지 않는다. 다만 이미 둘이 있으므로 고른다.**

```text
federation AS2        renderer.php:134-148
  id · type: "Tombstone" · formerType? · deleted?        나머지 멤버를 전부 버림

normalized ObjectView  thread-edges.php:471
  id · type: "Tombstone" · status: "tombstone"           렌더에 필요한 최소 상태
```

**read API는 둘 중 하나를 명시적으로 고른다** — federation representation을 그대로 주고 Social이
AS2 Tombstone을 소비하거나, normalized ObjectView를 주고 기존 `type`과 `status`를 소비한다.
어느 쪽이든 `kind`나 `deletedAt` 같은 세 번째 이름은 만들지 않는다.

**renderer의 allowlist가 ObjectView API까지 강제한다고 쓰면 안 된다.** 초판이 그렇게 적었고
틀렸다 — 지금은 별도 경로다. federation 쪽은 `renderer.php`가 멤버를 버려서 이전 본문·media·
author가 나갈 수 없고, view model 쪽은 `object-view-model.php`가 author·content·interaction
없는 최소 공지를 렌더한다. **두 경로가 각자 그렇게 하고 있을 뿐, 하나가 다른 쪽을 보장하지
않는다.** read API를 만들 때 고른 쪽의 축약을 그 경로에서 다시 보장해야 한다.

**미결**: Social의 object 라우트가 서버에서 객체를 해소해 상태코드를 정할 것인가. 지금 답은
"하지 않는다"이고(위 표), 바꾸려면 Atomic의 HTML 410 교체를 어떻게 피하는지를 먼저 답해야 한다.

### A‴. 410은 AS2가 아니라 ActivityPub의 것이고, Tombstone 자체는 MAY다

AS2 Vocabulary는 `Tombstone` · `formerType` · `deleted`의 **의미만** 정의하고 HTTP 상태를
말하지 않는다. 410은 ActivityPub §6.4의 권고다.

```text
삭제된 객체를 Tombstone으로 대체하는 것          MAY
Tombstone을 본문으로 제시하면                    410 SHOULD
제시하지 않으면                                  404 SHOULD
```

**그래서 "Tombstone이면 반드시 410"이 아니다.** 410은 Tombstone 본문과 짝이 되는 상태코드이고,
Tombstone을 만들지 않기로 하면 404가 권고다. OP의 federation route가 `Tombstone → 410`인 것은
이 짝을 지키는 것이다.

### A⁗. trash와 영구 삭제는 다른 상태다 — 우리 구현이 이미 나눠 놨다

"로컬 글을 연합하고 지우면 무조건 인덱스에 남는가"의 답은 **아니다.**

```text
publish → trash     Delete를 기록·전달. envelope는 active 유지      원격에서 사라지고 복원 가능
trash → publish     Delete 뒤 새 Create generation                 같은 URI의 새 공개 상태
영구 삭제            envelope를 privacy-minimal Tombstone으로 전환   URI·스레드 참조만 보존
```

```text
axismundi-note/includes/envelope.php:687-698
  "pre_delete_post fires only on permanent deletion (not trash) and can
   short-circuit it, so a failed tombstone write returns false to abort the
   deletion rather than orphan an active envelope for a post that no longer exists."
```

행을 버리지 않는 이유도 거기 적혀 있다 — canonical UUID와 Actor snapshot이 Core Post보다
오래 살아야 나중의 Delete Activity와 Tombstone 투영이 표현 가능하다.

**상태코드 대응이 뒤집히지 않도록 적어 둔다.** trash가 404, 영구 삭제가 Tombstone + 410이다.
반대가 아니다 — 가역적인 WordPress 편집 상태를 `410 Gone`이라 선언하면 의미가 과하고,
영구 삭제는 Tombstone 본문이 있으니 §A‴의 짝에 따라 410이다.

**FEP-4f05 준수를 주장하지 않는다.** DRAFT이고 구현체가 없으며, 그 제안의 soft deletion은
Tombstone + 2xx다. 우리 `trash`는 그 soft-delete가 아니라 **복원 가능한 비공개 withdrawal**이다.
이것은 FEP 준수가 아니라 **Axismundi의 WordPress lifecycle 정책**이다.

**남은 틈 하나**: 스레드 resolver는 source를 해소할 수 없는 reply를 건너뛴다
(`thread-edges.php:587-589`, `continue`). Note는 tombstone된 envelope 행을 남기므로 해소되지만,
**envelope를 갖지 않는 다른 로컬 source는 하드 삭제 시 스레드에서 사라진다.** 필요해지면
Activities의 최소 Delete 기록을 읽는 local thread tombstone resolver를 더하고, 그 resolver는
URI·`Tombstone`·삭제 시각만 주며 본문·첨부·작성자 스냅샷을 다시 보관하지 않는다.

### B. 식별자

`REMOTE-OBJECTS.md §1`이 row id의 공개를 금지한다. 그러면 읽기 엔드포인트의 키는 canonical
URI이거나 그 해시다. URI를 경로에 넣을지 질의 인자로 넣을지, 해시를 노출할지가 미결.

### C. 버전

버전이 3층(plugin / DB / protocol)인데 읽기 API의 `v1`이 어느 층에 묶이는가.

### B-1. Social 라우트의 모양 — 2026-10-05 추가

§7-B가 "키가 canonical URI인가 그 해시인가"를 미결로 남겼는데, 그 미결이 **라우트 모양과
묶인다**는 것이 이번에 드러났다. 식별자를 먼저 정하고 라우트를 나중에 정하는 순서가 아니다.

검토된 형태는 이것이다. **채택이 아니라 검토 결과이고**, A5 판정이 소유자에게 남아 있는 한
이것도 같이 열려 있다.

```text
/social/objects/:objectKey      타입을 모르는 진입점 — resolver
/social/notes/:objectKey        Note의 canonical view
/social/articles/:objectKey     Article의 canonical view
```

**두 축이지 하나가 아니다.** 섞어서 한 번 틀렸으므로 적어 둔다.

```text
라우트 교정   resolver가 타입을 알아낸 뒤 올바른 typed route로 replace 이동한다.
              typed route에 잘못된 타입이 들어와도 같은 교정을 한다.
              `replace`인 이유는 경유지가 히스토리에 남으면 뒤로 가기가 이상해지기 때문이다.

렌더러 폴백   object.type -> NoteView | ArticleView | ... -> GenericObjectView -> NotFound
              전용 화면이 없는 타입(Question, Image …)은 generic이 받는다.
```

착상은 WordPress의 template hierarchy이고, **가져온 것은 폴백 사슬이지 "URL을 고정한다"가
아니다.** WordPress는 서버에서 같은 주소에 다른 템플릿을 고르지만, React SPA에서는 공유
가능한 의미를 URL에 두고 `replace`로 교정하는 편이 낫다. 이 문서의 초고 논의에서 그 둘을
묶어 "라우트는 하나여야 한다"고 읽은 적이 있는데, 유추를 문자 그대로 적용한 오독이었다.

**타입은 조회의 주키가 아니다.** URL의 `notes`/`articles`는 표현과 검증 제약이고, 조회는
`objectKey`로 한다. 그래야 새 Object type이 들어와도 라우팅을 다시 설계하지 않는다.

#### objectKey

숫자 PK를 쓰지 않는다. `?p=123`은 local post id이고 remote cache의 `id=123`은 저장소 구현
상세이며(`REMOTE-OBJECTS.md §1`이 노출을 금지한다), **둘이 같은 숫자일 수 있다.**

키는 canonical Object URI에서 파생한다. 원격 캐시가 이미 URI의 SHA-256을 쓰고 있으므로
그것을 그대로 공통 키로 삼을 수 있고, 캐시가 만료됐다 다시 관측돼도 같은 URI면 같은 키다.

표현은 아직 미결이고, 길이가 공개 계약의 일부라는 점만 기록한다.

```text
hex SHA-256        64자
base64url SHA-256  43자   같은 256비트의 다른 표현. 절단이 아니므로 충돌 위험을 더하지 않는다
```

DB는 hex를 계속 쓰고 라우트/API 경계에서만 encode·decode하는 형태가 가능하다. 어느 표현을
택하든 resolver는 해시로 조회한 뒤 **canonical URI를 다시 검증**해야 한다.

#### 원격 Object를 우리 주소에서 보이는 것은 미결이 아니다

이것을 미결로 적지 않는다. **이미 채택된 전제다** — `object-view-route.php`가 캐시된 원격
Object의 사람용 HTML 문서를 이미 SSR하고, Mastodon과 Misskey도 원격 Actor·Note를 자기
reader surface에서 보여준다.

`ROUTING.md` §2.1이 남긴 미결은 이것과 다른 질문이다. 그것은 **actors의 `/actors/{uuid}`
라우트**가 원격 Actor를 다시 서빙할 때 canonical 포인터를 유지할지 철회할지였다. 두 미결을
묶으면 Object 쪽의 이미 선 결정을 열어 둔 것처럼 만들게 되고, 한 번 그렇게 적은 적이 있다.

대신 Social route가 **기존 SSR view의 보호 성질을 상속한다**고 적는다.

```text
cached projection만 읽는다 — 라우트 렌더 중 원격 fetch는 하지 않는다
publicly_listable / visibility gate를 다시 적용한다
원본 human_url 또는 canonical Object URI를 유지하고 표시한다
Social 셸이 noindex이므로 원격 원본의 검색 정체성을 빼앗지 않는다
```

#### 이것이 A5와 묶인다

위 라우트는 `objectKey` 하나로 local과 remote를 모두 해소할 수 있어야 성립하고, 그러려면
§A5가 지적한 결손 — 정렬 키, `object_type`, 소유자 adapter — 이 메워져야 한다. **즉 라우트
모양을 먼저 구현하면 A5를 코드로 채택하는 것이 된다.** 다섯 안의 판정이 먼저다.

### D. local과 remote의 공통 목록이 나중에 필요해지는가

§4는 **지금** 합치지 않는다는 결정이다. "모든 미해소 신고" 같은 교차 목록이 필요해지면,
그때는 두 목록의 합이 아니라 **별도 queue**로 설계한다 — 행마다 행동이 갈리는 목록을 다시
만드는 것이 아니라.

## 8. 하지 않기로 한 것

- **서버가 완성 HTML을 SPA에 넘기고 React가 재삽입하기.** sanitize 경계가 흐려지고(누가
  마지막으로 정화했는가), 이미 결정된 클라이언트 상태 — quick-view singleton hub, 캐러셀 —
  가 설 자리를 잃는다.
- **공식 ActivityPub 플러그인의 `admin.php?page=activitypub-social-web`을 렌더 경로로 쓰기.**
  clean-break 정책이 공식 AP를 S2S transport로만 쓰기로 했고, 그 리더 화면을 표현 층에
  끌어들이면 그 claim이 다시 넓어진다. 게다가 그 리더는 `skip_inbox_storage` 때문에 우리
  환경에서 빈 화면이다.
- **`ObjectView` React 컴포넌트를 두 앱이 공유하기.** Admin이 보는 세 필드(§5)가 공개
  표면에 닿는 경로가 된다. 공용은 렌더러가 아니라 데이터 계약과 authorization이다.
- **raw ActivityStreams JSON을 DataView에 직결하기.** 그러면 DataView의 컬럼 정의가 사실상
  정규화 계약이 되고, 계약이 UI 안에 숨는다. 정규화된 행을 읽는다.

## 9. 순서

```text
0  §7-A 판정                                    소유자
1  GET …/objects/{uri} 응답 계약                 seam은 있음. 계약은 새로 정함 (§7-A′)
2  Admin object inspector                        1의 유일한 소비자이자 검증 표면
3  목록 + Admin DataView (local / remote 둘)     0이 풀린 뒤
4  Social Card + scaffold                        0~3과 무관하게 병렬
5  Social object detail + feed card              1·3을 소비
```

2를 3보다 앞에 두는 이유는 inspector가 **단일 객체 계약만 쓰므로 0번 없이 시작할 수 있고**,
목록은 못 그렇기 때문이다. 그리고 inspector가 "정규화가 원본을 어디서 잃는가"를 보여주는
자리라, 목록 UI보다 계약 오류를 훨씬 잘 잡는다.

4는 기다릴 이유가 없다. `src/apps/admin/`(WPDS)과 `src/apps/frontend/`(Material)는 파일
트리도, 디자인 시스템도, 검증기도 겹치지 않는다.

## 10. DataViews를 쓸 때

`@wordpress/dataviews`는 코어에 들어간 패키지이고 Admin은 이미 WPDS 위에 있으니 시각 언어가
어긋나지 않는다. 목록·필터·정렬·저장된 뷰를 처음부터 만들 이유가 없다.

두 가지만 걸어 둔다 — **Social로 넘어오지 않는다**(공개 피드는 운영 데이터 탐색기가 아니고,
그 순간 두 앱의 디자인 시스템 경계가 무너진다), 그리고 **§8의 raw JSON 직결 금지**.

## 관련 기록

- `axismundi-object-projections/docs/SPEC.md` · `REMOTE-OBJECTS.md` · `LOCAL-OBJECTS.md`
- `axismundi-object-projections/includes/object-view-model.php` — adapter 계약과 OP의 금지
- `axismundi-object-projections/includes/thread-edges.php` — URI 기반 스레드 그래프
- `axismundi-activities/docs/BOUNDARIES.md` — 플러그인 소유 표
- `axismundi-actors/docs/PROJECTIONS.md` — "조율자는 query를 갖지 않는다"의 선례
- `DECISION-ADMIN-ROUTE-AREAS.md` — Admin 라우트 영역
- `DECISION-FRONTEND-CORE-PRESENTATION-ISOLATION.md`
