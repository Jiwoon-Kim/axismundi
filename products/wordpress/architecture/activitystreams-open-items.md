# ActivityStreams Open Items And Preconditions

이 문서는 **계약이 아니라 인수인계**다. 세션 하나가 측정해 놓고 문서에 옮기지
못한 것들이 다음 세션의 비용이 된 적이 있어서, 그 간극만 담는다. 계약은
`activitystreams-object-contract.md`,
`activitystreams-collection-model.md`,
`activitystreams-storage-boundaries.md` 가 계속 소유한다.

항목이 구현되면 여기서 지우고 해당 계약 문서에 적는다. 이 파일이 비는 것이
정상 상태다.

최종 갱신 2026-10-20. 측정은 전부 로컬 wp-env 에서 그날 실행한 것이다.

2026-10-22 추가: Actor/Activity 투영 경로 인수인계는 아래 소스 검토 기록이다.
해당 항목에서 새 런타임 감사나 와이어 측정을 수행했다는 뜻은 아니다.

## Corrections That Must Not Recur

**`wp_ax_object_index` 와 `wp_ax_objects` 는 다른 테이블이다.** 이 세션에서 두
번 혼동했고, 두 번 다 잘못된 결론으로 이어졌다.

```txt
wp_ax_object_index   object_uri_hash, publicly_listable, has_group_context,
                     primary_group_uri_hash, updated_at, object_status, source,
                     attributed_to_uri_hash, is_reply
                     → 정수 키 없음. hash 키. 캐시 purge 와 함께 축출된다
                       (remote-objects.php 의 purge 끝에서
                        axismundi_op_purge_orphan_remote_object_listing_projections()).

wp_ax_objects        identity_id, source_owner, source_kind, source_ref, as2_type,
                     as2_type_observed_at, attributed_to_uri, attributed_to_uri_hash,
                     attributed_identity_id, created_at, updated_at
wp_ax_identities     id, uuid, canonical_uri, canonical_uri_hash, object_kind,
                     origin, status, created_at, updated_at
                     → 영속 정수 키. purge 를 넘어 살아남는다.
```

2026-10-20 측정: `wp_ax_identities` 2012행(`origin` local 1237 / remote 775),
`wp_ax_objects` 1093행, **캐시 행이 없는 remote identity 442행.** 영속성은 설계
주장이 아니라 현재 상태다.

따라서 "통합 영속 키가 없다"는 결론은 틀렸다. 귀속 정보도
`wp_ax_objects.attributed_to_uri_hash` 에 이미 있으므로, 비정규화를 선행
과제로 요구하기 전에 조인 가능 여부부터 측정한다.

### Three Of Four "Identity Outlives Its Source" Entries Were Features

"정체성이 소스보다 오래 남는다"로 기록해 둔 네 건 중 셋이 설계대로 동작하는
모습이었다. 표본 하나를 측정 없이 결함으로 일반화한 것이 공통 원인이다.

```txt
wp_ax_notes 고아 744행      = object_status 'tombstone' 744행과 동일 집합,
                              비-tombstone 고아 0. Tombstone 주소 유지가 설계.
remote identity 442행       = 캐시는 축출 가능하고 정체성은 영속. 설계.
Note 정체성 shadow          = 위 tombstone 의 다른 관찰면.
page 정체성 2행             = 미결. 재측정 전까지 판단 보류.
```

## Note Cutoff: Preconditions Before Implementation

컷오프 자체의 근거와 URI 규칙은 `activitystreams-storage-boundaries.md` 의
"Identity, Authoring Order, And The Note Cutoff" 가 소유한다. 여기에는 **구현
직전에 확인해야 하는 것**만 둔다.

### 1. 삭제 경로가 권위를 검사해야 한다

계획 전체에서 되돌릴 수 없는 손실은 이 한 곳이다.

```txt
includes/leases.php          "retention leases for URI-keyed remote object observations"
includes/remote-objects.php  DELETE FROM {table} WHERE object_uri_hash = %s AND object_uri = %s
                             purge: expires_at <= now AND NOT EXISTS(활성 lease) → 실제 DELETE
```

핀: **URI 로 지우는 호출은 authority=local 행을 제거할 수 없다.** `origin`
컬럼은 `wp_ax_identities` 에 이미 있으므로 새로 만들 필요는 없고, 삭제 경로가
그것을 **검사하는지**가 확인 대상이다.

측정된 upsert 동작도 같이 둔다: 행이 살아 있는 동안 재수집은 같은 `id` 를
유지하고(`SELECT … FOR UPDATE` 후 `$wpdb->update( …, array( 'id' => $existing['id'] ) )`),
purge 로 삭제된 뒤에만 새 행이 된다. "만료되면 id 가 바뀐다"는 부정확하다.

### 2. `?ax_object=` 는 현재 다른 용도가 점유하고 있다

```txt
결정   로컬 C2S 저작 객체   id = /?ax_object={통합키}   url = /objects/{통합키}
       원격 객체            로컬 뷰 = /?ax_remote_object={통합키}
                            id = 원격 발신자가 정한 URI 그대로
```

통합키는 캐시 행 ID 가 아니라 `wp_ax_identities` 의 영속 키다. 캐시 행 ID 는
purge 후 달라지므로 외부 노출 주소로 쓸 수 없다.

**선결:** 현재 `?ax_object={hash}` 가 원격 객체의 로컬 HTML 뷰를 맡고 있다.
새 용도를 붙이기 전에 기존 용도를 분리해야 한다.

### 3. Note 의 `url` 과 `?ax_note=` 노출

현재 Note 투영은 `url` 을 Link 형태로 내보내고 그 `href` 가 `id` 와 같다 —
운반하는 정보가 0 이고 id 를 중복한다. Article 은 scalar 로 고쳤고 같은 규칙이
적용되어야 한다.

그리고 `?ax_note=` 가 사람용 주소로 노출되는 것은
`axismundi-note/includes/view.php` 에 기록된 "private CPT query URL 을 발행하지
않는다"와 긴장 관계다. 컷오프 때 함께 정리될 자리다.

### 4. 보류된 결정

`wp_posts` 계열의 `inReplyTo` 저작 허용 여부. 저장소를 나눌 이유가 아니라
저작 속성을 유지할지의 문제다. postmeta 가 저작 권위, 관계 테이블이 재구축
인덱스. 수신 관계를 표현하는 능력과 로컬 편집 UI 가 허용하는 정책은 분리해서
다룬다.

## Decisions Made In Conversation, Not Yet In A Contract Document

### interactionPolicy Belongs To The Object

Actor 가 아니다. GoToSocial 문서는 "a post's `interactionPolicy`" 라고 쓰고
actor 수준의 연합 기본값을 정의하지 않는다. 문서가 정의하는 하위 정책은
`canLike`·`canReply`·`canAnnounce` 셋뿐이고 **`canQuote` 는 Pre-FEP 제안**이다.

```txt
attributedTo         누가 승인하는가 — QuoteRequest 수신 inbox, QuoteAuthorization 발행 권위
interactionPolicy    무엇이 허용되는가 — 그 객체의 정책
Actor                기본값만 보유. 저작 시 객체 정책을 채우는 로컬 설정이고 발행하지 않는다
```

Actor 전용이면 안 되는 결정적 이유: Activity 스냅샷은 불변이고 peer 가
캐시한다. 정책이 Actor 에만 있으면 Actor Update 하나가 과거 모든 객체의 정책을
소급 변경하는데 캐시된 스냅샷은 예전 값을 말한다.

이 값은 장식이 아니다. Fedify 의 quote 헬퍼는 `canQuote` 가 없으면 **거부**로
처리한다 — FEP-044f 인용 승인이 동의 기반이라, 소유자가 자동 승인을 명시하지
않으면 허용되지 않는다. 우리가 내보내는 `automaticApproval: as:Public` 이
인용을 가능하게 만드는 바로 그 값이다.

### A Published Collection Is A Dynamic Container Over Static Members

```txt
컬렉션 문서    동적   요청마다, 요청자마다 조립
멤버 Activity  정적   저장된 스냅샷. 재조립하지 않는다
순서           불변   feed_sort_at 은 편입 시 고정. 커서가 요청 간 안정적이어야 한다
```

WordPress 의 동적 블록과 같은 모양이지만, 동적 블록은 자기가 무엇을 렌더했는지
기억할 필요가 없고 컬렉션은 있어야 한다. "동적 = 매번 전부 재계산"으로 읽으면
페이징이 깨진다. `ap-outbox` 가 이미 이 모양이다
(`membership_source: side-effect:publication`, `total_capability: unavailable`,
이유 `read-time-filter`).

### Per-Requester Filtering Is Ours, Not A Spec Requirement

ActivityPub 5.1 의 "the contents of the outbox are filtered by the permissions
of the person reading it" 과 "should respond with all of the Public posts" 는
**소문자**라서 RFC 2119 규범이 아니다. 규범적인 것은 3.2 의
"Servers MAY require authorization" 과 "SHOULD fail requests which do not pass
their authorization checks" 쪽이다. 우리 문서에는 **ours** 로 적는다.

따르는 비용 넷:

```txt
1. 서명된 GET 이 선결    누가 묻는지 모르면 클래스가 public 과 없음 둘뿐.
                         Appendix B.1 "there are no strongly agreed upon
                         mechanisms for authentication" → authorized fetch 는 우리 선택.
2. 캐시를 끊어야 한다    변이 축이 서명 주체라 Vary 로 표현이 안 된다.
                         Cache-Control: private, no-store.
3. 블록은 수치로 샌다    총계와 페이지 빈칸이 숨긴 항목 수를 드러낸다.
                         totalItems 생략과 불투명 커서는 성능이 아니라 공개 범위 사유다.
4. 관계 변경 중 커서     불변 정렬 키 덕에 "X 보다 오래된 것" 의미는 유지되지만,
                         새로 보이게 된 더 오래된 항목은 소급해서 나타나지 않는다.
                         버그가 아니라 선택된 동작이다.
```

미결: 거부가 404 인가 403 인가. 3.2 는 비공개 대상을 숨기려고 404 를 반환하는
것을 MAY 로 허용한다. 블록 누출을 줄이려면 404 쪽이다. Activity 핸들러·가시성
계약 때 정한다.

### Activity Handlers: Per Type First, Extract Later

```txt
판정 축   타입 + C2S/S2S 경로 + 대상 도메인
          (같은 Create 라도 로컬 저작 명령과 원격 관측은 다르다)
순서      타입별로 먼저 완성 → 반복이 확인된 것만 집행 모듈로 추출
처음부터  멱등성, 실패·재시도 경계, 원장 기록과 상태 변경의 일관성은
          핸들러마다 명시한다. 나중에 추출하는 것은 구현이지 정확성 조건이 아니다
```

### The Frontend Application Is A Reader, Not An Endpoint

```txt
id                      대상의 정체성
canonical endpoint + 협상  표현을 제공하는 프로토콜 표면
읽기 API                인증·캐시·로컬/원격 해소를 담당하는 앱의 데이터 표면
앱 라우트               탐색 위치와 화면 상태
```

`social/objects/...` 는 화면 주소이지 Object `id` 도 연합 엔드포인트도 아니다
(`AXISMUNDI_CAPSTONE_ROUTE = 'social'`). 앱을 바꾸거나 없애도 정체성과 연합
동작은 유지된다. [browser.pub](https://browser.pub/) 이 같은 구성의 참고
사례다 — 하나의 읽기 표면 위에 일반 화면과 구조 확인 화면.

## Known Defects, Measured

### The Emitter Can Mint A Hashtag `id` It Cannot Serve

```txt
axismundi_op_hashtag_archive_uri()     ax_hashtag term 이면 무조건 ?tag_id={term_id} 발행
axismundi_op_numeric_hashtag_term()    같은 term_id 가 post_tag 에도 있으면 해소 거부
```

두 taxonomy 를 겸하는 term 이면 `tag[]` 와 컬렉션 문서에 발행한 `id` 가 코어
태그 아카이브로 간다. `?tag_id=` 쪽 거부는 옳다 — 코어 경로를 빼앗으면 안 된다.
고칠 쪽은 발신이다: 겸용 term 일 때 `id` 를 내지 않거나, 그 아카이브에 다른
주소를 준다.

2026-10-20 측정 기준 해당 term 0건이라 발현하지 않는다.

### The Archive's `member_types` Is Not Declared Yet

Actor 와 Activity 의 tag 는 참조 저장소에만 남고 term 사용 인덱스로 파생되지
않는다. 이것이 reader 필터가 아니라 **파생 규칙**이고, 디스크립터로 선언하고
감사가 핀으로 고정해야 한다. Mastodon 의 태그 메뉴가 같은 결론을 독립적으로
보여준다 — "@actor 의 #tag 게시물"은 그 Actor 의 **게시물**이지 Actor 가 아니다.
Actor 의 관심사는 `featuredTags` 라는 별도 Collection 에 있다.

### Actor-Scoped Filtering Needs A Measurement Before Denormalizing

필터 축은 WP author 가 아니라 **Actor URI** 다. 원격 멤버의 `attributedTo` 는
WP user 가 아예 없으므로 `author` 로는 걸러낼 수 없다.

`wp_ax_term_usage` 에는 저자 컬럼이 없지만 `wp_ax_objects` 에 있다. 조인으로
충분한지 먼저 측정하고, 실제 조회·페이징이 요구할 때만 비정규화한다. 필터가
인덱스에 덮이지 않으면 term 안에서 스캔이 되고, 그것은 이 슬라이스가 없앤
문제와 같은 모양이다.

필터된 결과를 별도 컬렉션으로 발행하지 않는 한 새 `id` 는 필요 없다. 발행한다면
`id` 가 무필터 주소면 안 된다 — 정체성에 대한 거짓말이 된다.

## Deferred, With Their Evidence

Release/build boundary (owner decision, 2026-10-21): Object Projections 0.1.4
is not approved for deployment. The working version is `0.1.4-dev`; published
0.1.3 tags and ZIPs remain unchanged. Plugin ZIPs stay directly under `_dist/`;
only theme ZIPs move to `_dist/themes/` to avoid the shared `axismundi` slug.
The full `phpcs:ignore` reassessment is deferred until Activities receives its
manual WordPress.org review result; it is not a prerequisite for frontend work.

```txt
ap-liked 커서 리더            200 clamp + ORDER BY id DESC
flat-reader 스윕              relation_actor_list 500, get_by_actor, get_recent
delivery spool 보존 정책      16,632행 / 15,040 failed, 정책 없음
Content-Type profile 패치     ~/.codex/patches/axismundi-content-type-profile-jsonld.patch
                              와이어 측정 완료: 협상 응답이 ld+json;profile= 요청에
                              application/activity+json 라벨로 온다. 적용 판단 가능.
Actors 컬럼 개명              avatar_attachment_id → icon_attachment_id (내부, accessor 가 가림)
로컬 actor 렌디션             asset_variants() ladder 사용해야 함 (attachment 367 에 large 없음)
page 정체성 2행               위 "셋 중 넷" 참조. 미결
tools/ 상태 보고 스크립트     없음
발신 {id,name} 의 수신 동작    Mastodon 이 tag[] 를 type 으로 걸러 읽으므로 링크화되지
                              않을 것으로 **예상**. 실제 peer 측정 미완.
                              외부 발행은 owner 가 시점을 정한다.
```

## Wanted: Contract Introspection, Not A Second Vocabulary

모델의 축을 잡기 어려웠던 문제는 **통합 레지스트리 하나로 풀린 게 아니라 책임을
나누면서** 풀렸다. 지금 서 있는 네 축은 이렇다.

```txt
Object 레지스트리        무엇이며, 어느 소스가 사실을 소유하는가
Collection 계약          누가 멤버십을 정하고 어떻게 열거·정렬·공개·집계하는가
Link·Mention 참조        어느 문서의 어느 속성에 등장한 참조인가
Term 사용 인덱스         그 참조 중 무엇을 로컬 아카이브 참여로 해석하는가
```

Link 에 별도 원장을 두지 않아도 되는 이유가 여기서 나온다. 필요한 것은 독립
객체의 수명주기가 아니라 **문서에 등장한 descriptor 와 그 출처의 보존**이다.
`tag_references` 가 보존하고 `term_usage` 가 멤버십을 파생한다 — **참조가 있다는
것과 컬렉션 멤버라는 것이 분리된 것이 핵심**이다.

### The Missing Piece Is A Query, Not A Model

남은 불편은 "이 타입이 어떤 속성을 지원하는지 물어볼 데가 없다"는 것이다. 모델이
아니라 **질의 층**이 없다.

```txt
describe_type( "Mention" )            Link 상속 속성 + 이 프로젝트가 지원하는 속성
describe_type( "OrderedCollection" )  Collection/Object 상속 + orderedItems 등
describe_collection( "hashtag-archive" )
                                      그중 실제로 제공하는 속성·열거 방식·공개 정책
```

세 층을 **구분해서** 답해야 쓸모가 있다. 하나로 뭉치면 지금 문서를 읽는 것과
다를 바 없다.

```txt
어휘에 정의됨      그 타입에서 의미가 있는 속성
프로젝트가 지원함  reader 와 assembly 가 실제로 처리하는 속성
이 문서에서 제공됨 데이터·권한·요청 맥락에 따라 실제로 나오는 속성
```

그래야 **"`totalItems` 는 정의돼 있는데 왜 해시태그 응답에는 없는가"가 조회
가능한 질문**이 된다. 지금은 세 문서를 읽어야 답이 나온다.

### Constraints On Building It

- **속성 목록을 새로 쓰지 않는다.** 어휘의 `parents` 와 컬렉션 디스크립터가 이미
  사실을 들고 있다. 두 번째 목록을 만들면 그 둘이 갈라지고, 갈라진 쪽이 거짓말을
  한다.
- **타입 계약 등록과 인스턴스 원장 등록은 다른 일이다.** Link 와 Mention 은 타입
  계약을 가지되 독립 ID 도 저장 행도 받지 않는다.
- 세 번째 층("이 문서에서 제공됨")은 요청자에 따라 달라지므로, 응답도 요청자
  맥락을 받아야 한다. 이 축은 이미 `total_disclosure` 가 요청자 클래스별 map 인
  것과 같다.

### Not Blocking Anything

이것은 편의이지 선결 조건이 아니다. 남은 실제 작업은 **공급자 연결**이다 — WP
taxonomy 와 원격 캐시는 연결됐고, Actor 프로필·Activity·새 C2S 저장소는 아직이다.

읽을 때 주의: `activitystreams-storage-boundaries.md` 에는 옛 `object_id` 중심
제안이 남아 있고, 뒤 절이 **URI 기반 2층 모델로 대체한다고 명시**한다. 뒤 절이
기준이다.

## Inline Media References: The CID Direction

ActivityPub 에는 **본문 안의 `<img>` 와 `attachment` 의 미디어 객체를 잇는
상호운용 규약이 없다.** `<img src>` 자체는 있으므로 "인라인 이미지를 표시할
방법이 없다"는 표현은 부정확하다. 없는 것은 **연결**이다.

이것을 WordPress 쪽에서 보면 무엇을 내보내려는 것인지 분명해진다.

```txt
WordPress 내부                      원격 수신 측
블록의 attachment 참조          →   본문의 CID 참조
로컬 attachment ID              →   미디어 Object URI
_wp_attachment_metadata.sizes   →   미디어 객체의 렌디션 Link 배열
이미지 HTML 생성                →   수신 렌더러가 srcset 구성
```

즉 새 이미지 로딩 기술이 아니라 **CMS 내부의 미디어 참조를 연합 경계 너머로
전달하는 계약**이다. 본문은 위치와 배치 의도를, 미디어 객체는 사용 가능한 파일
표현과 상태를 제공한다.

연결이 복원되면 그 위치에서 할 수 있는 일이 늘어난다: `sensitive`/CW 를 인라인
이미지에도 적용하고, 미디어 `id` 로 그 이미지 자체의 댓글 스레드를 열고,
Tombstone 이면 원래 자리에 플레이스홀더를 둔다.

### Four Values, Four Jobs

섞이기 쉬우므로 분리해 둔다.

```txt
Media id          재사용되는 미디어 객체의 정체성
CID               본문 위치와 첨부 항목의 연결
URL               지금 가져올 표현의 주소
digestMultibase   특정 바이너리의 동일성 (FEP-1311)
```

**바이너리 중복 제거는 CID 가 하는 일이 아니다.** CID 는 참조 발생을 가리키지 바이트를 가리키지 않아서 dedup
키가 될 수 없다. 그 일은 미디어 `id` 와 digest 가 한다. 같은
이미지를 담은 두 문서는 서로 다른 CID 를 갖는다. 대화에서 CID 의 이점으로 말한
적이 있으나 정확하지 않다.

주의: RFC 2392 는 Content-ID 에 **전역 고유성**을 요구한다. 아래에서 정하는 것은
**해소 범위**이지 고유성 범위가 아니다. 둘은 별개다.

### Resolution Scope Is A Security Requirement, Not Syntax

RFC 2392 에서 `cid:` 는 **같은 MIME multipart 메시지 안에서** 해소된다.
ActivityPub 에는 multipart 가 없으므로 그 자리를 무엇이 대신하는지 명시해야
한다.

```txt
핀   content 안의 cid: 는 같은 Object 의 attachment 배열 안에서만 해소한다
     cid 값으로 캐시나 다른 객체를 조회하지 않는다
핀   범위 제한은 권한을 보장하지 않는다. 해소한 미디어의 접근 권한을 따로 검사한다
```

범위를 정하지 않으면 `cid:` 가 전역 네임스페이스가 되고, 악의적 발신자가 cid
값을 추측해 **남의 미디어를 자기 본문에 끌어다 쓰는** 경로가 열린다. 문법보다
이 규칙이 먼저다.

### CID Does Not Replace `usedIn`; It Adds What `usedIn` Never Carried

`usedIn` 을 발행 속성으로 유지할 필요가 없는 이유는 CID 가 아니라 **그것이 관계
인덱스의 역조회**라는 것이고, 그 사실은 CID 이전부터 참이었다. CID 가 더하는
것은 `usedIn` 이 한 번도 운반하지 않은 **그 위치의 요소와 첨부 항목의 연결**이다.
위치 자체는 HTML 이 들고 있고, 같은 CID 를 여러 위치에서 참조할 수도 있다.

범위도 일치하지 않는다. featured image, cover, 프로필 이미지는 인라인 CID 가
없는 사용이라 CID 모델로 표현되지 않는다. 전체 `usedIn` 범위를 덮으려면 인라인
참조와 비인라인 사용을 **같은 관계로** 표현해야 한다.

그리고 현재 관계 테이블은 같은 미디어의 반복 사용을 `occurrence_count` 로
접는다. CID 별 참조를 보존하는 모델과 충돌하므로, 구현 전에 이 축을 정해야
한다.

### Read Path First; Do Not Publish `cid:` Yet

`src="cid:…"` 를 받은 미지원 클라이언트는 인라인 이미지를 깨뜨린다. 첨부
갤러리로 대신 보여줄지도 보장되지 않는다.

```txt
지금   우리 읽기 API 와 지원 렌더러 경로에서 해소를 구현하고 검증한다
아직   S2S 발행 HTML 은 HTTP src 를 유지한다
먼저   미지원 수신자의 폴백을 실측한 뒤에 발행 모양을 바꾼다
```

**이것은 선호가 아니라 핀이다.** 이 저장소에는 이미 측정되지 않은 와이어 변경이
하나 있다 — 해시태그 `{id, name}` 의 수신 동작(이 문서의 Deferred 참조). 두
번째를 쌓으면 나중에 무엇이 깨졌는지 분리할 수 없다.

`data-cid` 대신 `src="cid:…"` 를 쓰면 지원 렌더러 내부는 단순해지고, HTTP
이미지를 먼저 로드했다가 교체하는 위험도 줄어든다. 다만 **생략되는 것은
`data-cid` 속성이지 매핑 계약 자체가 아니다.**

### Rendering Rules That Follow

```txt
srcset   해상도 선택
picture  포맷·구도 분기가 필요할 때만. 둘은 대체재가 아니다
순서     CID 해소 → 삭제·권한·CW 판단 → 그 다음에 실제 src/srcset 을 DOM 에 넣는다
```

마지막 줄이 중요하다. HTTP `src` 를 먼저 DOM 에 넣으면 **CW 판단 전에 요청이
시작된다.** 그러면 CW 가 표시만 가리고 바이너리는 이미 받은 상태가 된다.

Link 배열을 그대로 `srcset` 에 넣지 않는다. 이미지 MIME 인지 확인하고, HTML
페이지 URL 을 제외하고, **정사각 크롭과 원본 비율 렌디션을 같은 `w` 후보군에
섞지 않는다.** 브라우저는 후보들을 같은 이미지의 다른 너비로 취급하므로, 크롭이
섞이면 뷰포트에 따라 구도가 바뀐다. WordPress 의 `thumbnail` 이 하드 크롭인지는
사이트 설정에 달려 있으므로 구현 시 확인한다.

객체 캐시와 바이너리 캐시도 분리한다. 객체 캐시(JSON·상태·렌디션 URL)만 있어도
원격 URL 로 `srcset` 을 구성할 수 있다. 바이너리 취득은 재사용·프라이버시·운영
비용으로 **선택**하는 것이고, 검증된 로컬 파일이 있을 때만 해당 후보 URL 을
로컬 주소로 바꾼다. ID 가 있다는 이유로 자동 취득하지 않는다.

### Where This Belongs

프론트앱이 실제 Object 를 호출해 렌더하는 단계의 일이다. 컴포넌트 작업의 선결
조건이 아니며, 그렇게 만들지 않는다.

`cid:` 의 정확한 문법, 매핑의 저장 위치, 그리고 `contentId` 를 첨부 항목의 어느
자리에 둘지는 **미결**이다. 구현으로 계약을 검증한 뒤 FEP 로 제안하는 순서다.

참고 문서: FEP-1311(미디어 표현·여러 버전·`digestMultibase`),
FEP-b2b8(Article HTML 과 인라인 미디어), RFC 2392(`cid:` 문법과 식별 범위).
**`data-cid` 또는 `src="cid:"` 와 `contentId` 의 연결은 이 문서들이 이미 정의한
규약이 아니라 새 제안이다.**

### Reuse Across Servers Needs A Policy On The Image, Not On The Note

공유 폴더의 목적은 Openverse 처럼 **남의 Image 객체를 자기 Note 에 첨부로
추가**할 수 있게 하는 것이다. 그러면 남의 미디어 ID 를 첨부 배열에 적는 것
자체는 공격이 아니라 **의도된 기능**이 된다. 위의 보안 핀은 그것을 금지하는
것이 아니라, 적었다는 사실만으로 접근이 허가되지 않는다는 뜻이다.

판단을 넷으로 나눈다. 하나가 다른 하나를 대신하지 않는다.

```txt
이미지 열람        공개 범위와 접근 권한
첨부·인라인 재사용  재사용을 허용하는가
바이너리 보관      수신 서버의 캐싱 정책
재배포·변형        라이선스와 저작권 조건
```

**공개 이미지라고 재사용까지 허용된 것이 아니고, 첨부 허용이 라이선스를
대신하지도 않는다.** 정책의 소유자는 부모 Note 가 아니라 **Image 객체**다.

첫 범위는 **라이선스 기반 재사용**으로 잡는다. `schema:license` 에 CC 라이선스
URI 를 싣고 출처·저작자 표시를 함께 제공하면 Openverse 식 사용의 대부분이
해결된다. 승인 프로토콜을 먼저 만들 이유가 없다.

`canAttach` 계열의 대상별 승인 정책은 **새 확장 제안**이고, 라이선스로 해결되지
않는 요구가 실제로 생길 때 후속으로 둔다. 설계하게 되면 첫 질문은 이것이다 —
**첨부 당시 승인으로 충분한가, 이후 철회와 정책 변경은 기존 사용에 어떻게
적용되는가.** 공유 폴더의 재사용 수명주기가 여기에 걸린다.

핀 하나는 지금 박아 둔다. **CC 라이선스로 이미 허용한 사용을 나중의 정책
변경으로 소급 취소하도록 설계하지 않는다.** 이미지 삭제와 라이선스 철회는 같은
뜻이 아니다.

## Actor And Activity Projection: Follow-Up After Opus Returns

상태: **문서화만. 구현은 Opus 복귀 후 검토한다.** Note cutoff와 별도 작업이다.
2026-10-22 현재 소스를 읽어 확인했으며, 아래 내용을 결함 확정이나 통합 설계 채택으로
해석하지 않는다. 특히 "Actor/Activity가 OP를 거치지 않는다"는 진술은 틀리다.

### Actor: Shared Dispatch And Finalization, Dedicated Field Assembly

현재 경로:

```txt
axismundi_op_transform_object(Axismundi_Actor)
  → 공통 transformer resolver
  → axismundi_op_actor_transform(): Actor 전용 속성 배열 조립
  → Actors transport bundle + OP transport filter 직접 병합
  → axismundi_op_finalize_object(): 검증 / 정제 / context
```

등록은 `plugins/axismundi-object-projections/includes/integrations/actors.php`의
`axismundi_op_register_actor_transformers()`에 있다. Actor 속성은 같은 파일의
`axismundi_op_actor_transform()`에서 직접 읽고 조립한다. WP Post의
`axismundi_op_register_object_source_reader()` → document facts → type assembly와는
다른 내부 경로이며, **공통 진입점이 있다는 것과 속성 해석까지 통합되었다는 것은 다르다.**

`publicKey`도 확인 대상이다. Actors의 `includes/local-endpoints.php`는
`axismundi_actors_local_public_key` 필터에서 공개 descriptor를 받고,
`axismundi_actors_transport_members()`가 inbox/publicKey/endpoints를 묶는다.
OP Actor transform은 이 묶음 중 허용된 속성을 병합한다. 이 경로에서 publicKey의
OP 어휘/속성 binding 등록은 확인하지 못했다. **직접 병합을 없애는 것 자체가 목적은 아니며,**
등록 계약과 context가 다른 확장 속성들과 일관되는지부터 검토한다.

키 재료의 현재 소유자는 공식 ActivityPub 플러그인이다. Bridge의
`includes/transport.php`는 User Person에 공식 `Collection\Actors`의 사용자 키를,
사용자 연결이 없는 Actor에는 공식 `Application` 키(없으면 Actors의 0번 키)를 사용한다.
Bridge가 우리 Actor URI 기반 key id/owner를 붙여 Actors에 제공한다.
현재 Managed Actor별 독립 키쌍을 생성하는 구조가 아니다. **속성 등록 정리와 키 생성·보관
이관은 별개**이며, 이 작업으로 개인키 저장이나 키 회전을 변경하지 않는다.

### Activity: Assembly Already Exists On The Snapshot Read Path

공개 canonical Activity 읽기:

```txt
OP includes/activity-read-route.php: axismundi_op_read_activity_jsonld()
  → Activities ledger의 로컬 outbound/effective/public snapshot 확인
  → type별 axismundi_op_resolve_object_assembly(type, 'jsonld')
  → jsonld callback(payload, 'snapshot')
  → 원래 snapshot/context와 비교, 변경되었으면 오류
```

기본 `axismundi_op_assemble_read_jsonld()`는 snapshot 모드에서 일반 application 표현의
Link 변환·HTML 정규화를 하지 않는다. 과거 Activity를 현재 Object/Actor 상태로 재생성하면
이력과 서명·전달 의미가 바뀔 수 있으므로, **이 차이를 레거시라고 보고 제거하지 않는다.**
`plugins/axismundi-object-projections/tests/audit-activity-read-route.php`에는 assembly가
과거 snapshot을 바꾸면 `ax_op_activity_snapshot_changed`로 거부하는 감사가 이미 있다.
이번 문서화에서 그 감사를 재실행하지는 않았다.

별도로 추적할 경로:

- Bridge 송신은 `axismundi_op_finalize_activity()`를 거쳐 ledger payload의 식별자를 검사하고
  blind addressing을 제거하며 context를 구성한다. 공개 snapshot 읽기의 assembly와 동일 경로는 아니다.
- `plugins/axismundi-activities/includes/actor-lifecycle.php`의
  `axismundi_act_record_actor_update()`는 `axismundi_op_actor_transform()`을 직접 호출하여
  Update.object와 fingerprint를 만든다. 일반 transform dispatcher/finalizer 호출과의 차이를
  검토하되, 경로를 바꿔 기존 fingerprint 및 Update 발행 동작이 달라지는지도 확인해야 한다.

### Review Scope

1. Actor의 source reader/facts/type assembly와 어휘·속성 binding 적용 범위를 결정한다.
   기존 transformer 등록이 없다고 가정하거나 별도의 Actor 저장소를 만들지 않는다.
2. publicKey의 어휘/context/공개값 제공 책임을 Actors·OP·Bridge 사이에서 명시한다.
   현재 공식 플러그인 키 제공 경로와 private-key 비노출은 보존한다.
3. canonical Actor 읽기, Actor Update.object, canonical Activity 읽기, Outbox, 송신 payload를
   각각 대조한다. application 표현과 역사 snapshot의 동일 출력을 강제하지 않는다.
4. 기존 `audit-actor-projection.php`, `audit-object-renderer.php`, `audit-object-assembly.php`,
   `audit-activity-read-route.php`, Activities의 `audit-actor-update.php`, Bridge의
   `audit-outbound-delivery.php`를 확인하고,
   바뀌는 계약에 필요한 회귀 검증을 추가한다. 현재 단계에서 모든 경로가 통합되었다고 표기하지 않는다.

## Sources

- ActivityPub, W3C Recommendation 2018-01-23, sections 3.1, 3.2, 5.1, 5.2, Appendix B.1.
- Activity Streams 2.0 Core, section 4.1 on optional properties.
- [GoToSocial interaction controls](https://docs.gotosocial.org/en/latest/federation/interaction_controls/)
- [Pre-FEP: Quote posts, quote policies and quote controls](https://socialhub.activitypub.rocks/t/pre-fep-quote-posts-quote-policies-and-quote-controls/5031)
- [Fedify interaction controls](https://unstable.fedify.dev/manual/interaction-controls)
