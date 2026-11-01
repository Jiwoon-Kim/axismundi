# curated collection 선례 조사

2026-11-01 소스 독해. 실행·연합 왕복 검증은 하지 않았다. 우리 정책을 확정하는
문서가 아니라 `featured`·`moderators` 를 구현할 때의 판단 자료다.

## 1. 결론 — 둘 다 로컬과 원격을 같은 원장에서 다룬다

```txt
Mastodon featured    원격 고정글도 StatusPin 모델. 로컬과 같은 모델이고 account 로 스코프
Lemmy moderators     CommunityModeratorView / CommunityActions / CommunityModeratorForm
                     원격 모더레이터용 별도 테이블 없음. 같은 구조를 그대로 쓴다
```

**저장을 공유하되 권위는 저쪽에 둔다.** 둘 다 원격 문서를 받으면 목록을 통째로
맞춘다 — 없어진 것은 지우고 새로 생긴 것은 넣는다. 로컬 조작이 원격 목록을
편집하는 경로는 없다.

## 2. Mastodon — featured

`app/services/activitypub/fetch_featured_collection_service.rb`

```txt
대상 조건    account 에 featured_collection_url 이 있고, suspended 도 local 도 아닐 것
읽기         collection_items( max_pages: 1 ) — **첫 페이지만** 읽는다
항목 필터    문자열이거나 type 이 'Note'. 로컬 URI 와 호스트 불일치 URI 는 건너뛴다
저자 조건    FetchRemoteStatusService 로 가져온 뒤 status.account_id === account.id
             **그 계정이 쓴 글만** 고정으로 인정한다
조정         현재 핀을 읽어, 목록에 없는 것은 delete_all,
             이미 있는 것은 건너뛰고, 나머지는 StatusPin.create!
오류         RecordInvalid 는 debug 로그만 남기고 건너뛴다
해시태그     FeaturedTag 가 같은 패턴을 따른다
```

주의할 점: **첫 페이지만 읽으므로 긴 featured 는 잘린다.** 그리고 "저자 조건" 이
있어서 남의 글을 고정해 둔 것은 반영되지 않는다.

## 3. Lemmy — moderators

`crates/apub/apub/src/collections/community_moderators.rs`

```txt
타입         ApubCommunityModerators,  Kind = GroupModerators
verify       verify_domains_match — 컬렉션 id 의 도메인이 기대한 도메인과 같은가
from_json    apub.ordered_items 를 handle_community_moderators 로 넘기고
             빈 placeholder 를 돌려준다 (반환값을 쓰지 않는다)
읽기         CommunityModeratorView::for_community
삭제         현재 모더레이터 중 ap_id 가 새 목록에 없으면 CommunityActions::leave
추가         각 ObjectId<ApubPerson> 를 dereference,  실패는 .ok() 로 버린다
             현재 목록에 없으면 CommunityActions::join
순서         집합 비교라 순서를 무시한다. 단 is_new_instance 면 루프 첫 항목에서 반환 —
             "Only add the top mod in case of new instance"
권위         verify 의 도메인 검사가 전부다. 누가 모더레이터를 더하고 뺄 수 있는지는
             **더 검사하지 않고** 컬렉션 내용을 그대로 적용한다
```

주의할 점: 권위 검사가 도메인 일치 하나다. 그 도메인을 통제하는 쪽이 목록을
통제한다. 그리고 dereference 실패를 조용히 버리므로 **일시적 조회 실패가 조용한
누락**이 된다.

## 4. 우리에게 주는 것

```txt
저장         로컬과 원격을 같은 원장에서 다루는 것이 배포 관행이다.
             별도 원격 테이블을 만들 근거가 선례에는 없다
권위         저장을 공유하는 것과 권위를 공유하는 것은 다르다.
             원격 목록은 원격 문서가 권위이고, 우리 쪽 편집 경로를 만들지 않는다
조정         받을 때마다 목록을 맞춘다 — 없어진 것 삭제, 새것 추가. 증분 패치가 아니다
실패         Lemmy 처럼 조용히 버리면 누락이 보이지 않는다.
             일시적 실패와 "목록에서 빠짐" 을 구분해 기록해야 한다
한계         Mastodon 의 1 페이지 제한처럼, 우리도 어디까지 읽는지 명시해야 한다
```

범용 curated 원장은 **패턴이 충분히 드러난 뒤에** 합치는 것을 검토한다.
`moderators` 는 Lemmy 모양을, `featured` 는 Mastodon 모양을 각각 가져온다.

## 5. Mastodon featured — 와이어와 서빙 (독립 확인, 커밋 147bfc1)

이식 대상이므로 업스트림 계약을 직접 읽었다. 위 2 절의 수신(fetch) 쪽과 달리
여기는 **발신·수신 Activity 와 컬렉션 서빙** 쪽이다.

### Add 수신은 target 으로 세 갈래다

`app/lib/activitypub/activity/add.rb` — `target` 이 비면 즉시 반환하고,
`value_or_id` 로 정규화한 뒤 갈린다.

```txt
featured_collection_url    object.type == 'Hashtag' → add_featured_tags
                           그 외 → add_featured
collections_url            add_collection → ProcessFeaturedCollectionService
그 밖의 target             @account.collections 에서 URI 로 찾아
                           add_collection_item → ProcessFeaturedItemService
```

**세 번째 갈래가 중요하다.** Mastodon 에는 `featured` 밖에도 일반화된 collections
개념이 이미 있고, 임의 컬렉션의 항목 추가를 그 경로로 처리한다. 우리 curated
collection 타입 체계에 업스트림 유사물이 있다는 뜻이다.

### 고정글 추가의 검증은 셋뿐이다

```txt
add_featured   status 가 존재할 것
               status.account_id === @account.id      ← 자기 글만 고정 가능
               @account.pinned?(status) 가 거짓일 것   ← 중복 방지
               → StatusPin.create!
add_featured_tags   object['name'] 에서 앞의 '#' 를 떼고 FeaturedTag.create!
                    **소유권 검사가 없다** — actor 가 그 계정이라는 것 외에는
```

### 서빙은 페이지를 나누지 않는다

`app/controllers/activitypub/collections_controller.rb`

```txt
SUPPORTED_COLLECTIONS = %w(featured tags)
                        라우트 id 는 'tags' 다. Actor 속성 이름(featuredTags)과 다르다
featured   @type = :ordered     → OrderedCollection
tags       :unordered
항목       전체를 한 응답에 인라인한다. size = @items.size.
           이 파일에 페이지 파라미터 처리가 없다
비공개 항목 distributable? 가 아니면 **버리지 않고 URI 로 치환**한다
           → 공개 불가한 고정글이 맨 URI 로 노출된다
캐시       expires_in 3.minutes, public: public_fetch_mode?
           authorized fetch 모드면 vary_by 에 Signature 를 더한다
```

### 차단된 조회자에게는 빈 목록을 준다

`check_authorization` 이 요청자가 그 계정에게 차단됐거나 도메인이 차단됐으면
`@unauthorized` 를 세우고, `for_signed_account` 가 고정글 대신 **빈 목록**을
돌려준다. 거부가 아니라 빈 컬렉션이다. 그리고 public fetch 모드에서는 이 검사를
건너뛴다 — 주석이 이유를 적어 둔다: 역프록시가 어차피 차단된 계정에도 캐시된
응답을 줄 것이기 때문이다.

우리 Block 결정과 이어지는 지점이다. **차단이 컬렉션에서는 403 이 아니라 빈 목록으로
나타난다**는 것, 그리고 그 보호가 캐시 앞에서 무력해진다는 것을 업스트림이 스스로
인정하고 있다는 것. 복제할지는 우리 정책이되, 복제한다면 그 한계까지 알고 해야 한다.

### 이식에 쓸 때

```txt
가져온다    Add/Remove 로 멤버십을 표현하는 것 — target 이 featured 컬렉션 URI
            자기 글만 고정 가능한 검증 (Person 에 한해)
            ordered vs unordered 구분
재판정한다  Group 은 Announce 가 후보 근거이므로 "저자 일치" 검증을 그대로 쓸 수 없다
            원격 조회 실패를 빈 목록으로 보아 기존 핀을 지우는 동작
            비공개 항목을 URI 로 치환해 노출하는 선택
```

## 6. featured 이식 계약 — 정해진 것 (2026-11-02)

### 와이어 (실측, 커밋 147bfc1)

```txt
AddNoteSerializer    attributes :type, :actor, :target  +  object
RemoveNoteSerializer 동일, type 만 다름
actor   계정 URI        object  고정할 Object URI (스칼라)
target  그 계정의 featured 컬렉션 URI
origin  없다 — 고정은 이동이 아니라 추가·제거다
id      **없다.** 기반 직렬화기(app/lib/activitypub/serializer.rb)는 JSON-LD
        컨텍스트만 다루고 id 를 붙이지 않는다
```

id 를 뺄 수 있었던 이유는 `Remove` 가 있어서다 — `Undo(Add)` 를 쓰지 않으면 Add 의
id 를 참조할 일이 없다.

### 정렬은 핀 시각이다

```txt
collections_controller   featured → @type = :ordered,  tags → :unordered
associations.rb:62       has_many :pinned_statuses,
                         -> { reorder(status_pins: { created_at: :desc }) }
                         **핀 생성 시각 내림차순.** 글 작성 시각이 아니다
```

그러므로 멤버십뿐 아니라 **고정 순서를 보존**해야 하고, 원격 Add 의 도착 순서를
원격의 고정 순서로 확정하면 안 되며, 전체 fetch 의 `orderedItems` 순서를 보존해야
한다.

### 수신 기록과 원격 Activity 를 분리한다

id 없는 Add 에 **우리 URI 를 지어 붙여 원격 Activity 로 기록하지 않는다.** 우리가
식별할 수 있는 것은 *이 수신 사건*이지 상대가 발행한 Activity 의 정체성이 아니다.

```txt
수신 기록   내부 ID. 원문 · 인증된 발신자 · 수신 시각 · 처리 결과를 보존하고
            원격 Activity 의 id 는 **없는 그대로** 둔다
집행        발신자가 그 target 컬렉션을 제어하는지 확인한 뒤
            (컬렉션 URI, Object URI) 멤버십에 적용
재조정      컬렉션 fetch 로 목록과 순서를 확인한다.
            조회 실패나 불완전한 페이지를 빈 목록으로 취급하지 않는다
우리 발신   Add/Remove 에 안정적인 Activity id 를 부여한다
```

### 멱등만으로는 부족하다

중복 Add 는 상태상 멱등이지만, **오래된 Add 가 Remove 뒤에 도착하면 부활한다.**
upsert 만으로 해결되지 않는다 — Follow 를 Block 이 끝낸 뒤 늦은 Accept 가 되살리던
것과 같은 부류다. 그래서 갱신 신호를 받고 **재조정하는 경로가 본체**이고 Activity 는
힌트다.

### 삭제된 객체는 현재 목록에서 빠진다

표준의 필연이 아니라 **우리 정책**이다. 컬렉션은 현재 멤버십의 목록이므로 삭제가
확정된 항목은 제외한다. Tombstone 은 그 객체 자신의 URI 에서 410 으로 나타나고
컬렉션 안에 넣지 않는다 — 넣으면 "삭제된 것이 지금 고정되어 있다" 는 거짓이 된다.

```txt
구분해야 함   일시적 조회 실패 ≠ 삭제
보존해야 함   과거 Add 와 수신 증거.  현재 목록에서 빠질 뿐 이력은 남는다
미확인        Mastodon 에서 status 삭제 시 핀이 어디서 정리되는지.
              has_many :status_pins 는 Account 에 있고 Status 에는 없다
```
