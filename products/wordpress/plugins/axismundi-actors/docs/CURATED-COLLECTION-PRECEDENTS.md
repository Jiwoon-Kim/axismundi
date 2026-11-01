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
