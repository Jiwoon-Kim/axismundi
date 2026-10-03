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

**조율하는 플러그인은 query를 갖지 않는다.** 두 플러그인이 각각 독립적으로 그렇게 적었다.

```text
op/includes/object-view-model.php:22
  "OP never reads product storage; the adapter's supports and transform callbacks do."

actors/docs/PROJECTIONS.md:11-12
  "Actors renders the actor hub /@{username}/ and a navigation of its projections;
   it does not run the projection's query or own its content."

actors/docs/PROJECTIONS.md:27-28
  "Actors coordinates only URL, label, visibility, and order. The domain plugin
   owns the data, the template, and the permission logic behind the link."
```

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

**`wp_posts`를 직접 읽지 않는다.** `article`, `note`, group context 전부 OP가 투영한 JSON을
본다. 이유는 편의가 아니다.

- 연합에 나가는 것이 투영 결과다. Admin이 `wp_posts`를 보면 **나가지 않는 것을 검토**하게 된다.
- Tombstone에는 `WP_Post`가 없다. OP의 view-model 경로가 존재하는 이유가 그것이다
  (`object-view-model.php`: "a Tombstone has no `WP_Post` and must still render").
- `post_author` ≠ 발행 Actor다. canonical author는 Actor이고, 그 해소는 투영이 한다.

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

`GET …/objects/{uri}` 단일 조회는 OP의 기존 adapter가 이미 하는 일이라 논쟁이 없다.
**목록은 제품 저장소를 가로질러 질의**해야 하고, 그것이 OP가 §1에서 안 하겠다고 적은 일이다.

| | 목록 query 소유 | 대가 |
|---|---|---|
| A1 | OP가 query claim을 새로 가짐 | 두 파일의 경계를 되돌림. 되돌리는 이유를 적어야 함 |
| A2 | 제품이 각자 목록, OP는 행마다 view model | 경계 유지. 소비자가 N개 엔드포인트를 합성 |
| A3 | 교차 질의는 Activities(원장)가 소유 | `envelope=domain-owned`와 정합. OP는 렌더만 |

A3가 기존 기록과 가장 덜 부딪힌다 — 프로필 피드가 이미 그 모양이다. 판정은 소유자의 것.

### B. 식별자

`REMOTE-OBJECTS.md §1`이 row id의 공개를 금지한다. 그러면 읽기 엔드포인트의 키는 canonical
URI이거나 그 해시다. URI를 경로에 넣을지 질의 인자로 넣을지, 해시를 노출할지가 미결.

### C. 버전

버전이 3층(plugin / DB / protocol)인데 읽기 API의 `v1`이 어느 층에 묶이는가.

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
1  GET …/objects/{uri} 응답 계약                 논쟁 없음. OP adapter가 이미 하는 일
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
