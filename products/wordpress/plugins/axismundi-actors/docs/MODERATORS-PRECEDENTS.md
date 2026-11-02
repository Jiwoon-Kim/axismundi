# Lemmy moderators — 이식 선례

조사 기준: `LemmyNet/lemmy` 태그 `0.19.21`(배포본)과 `main`. 소스 확인이며
**서버 간 실행 검증은 아직 없다.** 범위는 Community 프로필 Group 의 moderators 컬렉션.

Person `featured` 의 선례 조사는 `CURATED-COLLECTION-PRECEDENTS.md`. 그쪽에서 지은 기계
(OrderedCollection 서빙 · 완전 스냅샷 재조정 · 항목별 미확정이 목록 진행을 막지 않음)가
여기서도 거의 그대로 맞고, **다른 것은 정책 세 가지**다.

## 1. attributedTo 가 Community/Channel 판별자다

`crates/apub/objects/src/utils/protocol.rs`

```rust
pub enum AttributedTo {
  Lemmy(PersonOrGroupModerators),      // moderators 컬렉션 URL 하나
  Peertube(Vec<AttributedToPeertube>), // {type: Person|Group, id} 배열
}
impl AttributedTo { fn url(self) -> Option<DbUrl> {
  Lemmy(l)    => Some(l.moderators()),
  Peertube(_) => None,   // 읽기는 하되 moderators 를 꺼내지 않는다
}}
```

**레미는 배열을 쓰지 않는다.** `attributedTo` 는 컬렉션 URL 하나다. 배열 형태는 PeerTube 것이고
레미는 그걸 관용적으로 읽되 moderators 로 해석하지 않는다.

따라서 "owners 는 Person URI, moderators 는 컬렉션" 을 **한 배열에 섞는 것은 어느 생태계의
형태도 아니다** — 우리 발명이 된다.

### 형태로 상대 플랫폼을 추측하지 않는다

레미의 enum 변형 이름(`Lemmy`, `Peertube`)은 **형태밖에 없는 자리에 플랫폼 이름을 붙인 것**이다.
`attributedTo` 가 one-or-many 인 것은 그냥 AS2 이므로, "배열이면 PeerTube" 는 스펙이 허용하는
형태에 벤더 정체를 읽어 넣는 것이고 건전하지 않다 — 우리 Group 도 정당하게 배열을 낼 수 있다.
다만 레미의 *동작*은 멀쩡하다. `url()` 은 URL 형태면 Some, 배열이면 None 을 줄 뿐 플랫폼 능력을
추론하지 않는다. 이름이 출처 기록을 정체 판정처럼 입고 있을 뿐이다.

우리 규칙은 방향으로 적는다.

```
원격 발신자의 플랫폼     추측하지 않는다
로컬 수신 Group 프로필   명시적으로 저장된 정책을 적용한다
```

```
로컬 Group   프로필은 생성 시 우리가 고른 상태다.  추측이 개입하지 않는다
             발신 계약(무엇을 내보내는가)과 수신·집행 정책을 함께 결정한다
             — 게시 자격 · 그룹 귀속 · 승인 · Announce 정책이 전부 여기 속한다
원격 Group   플랫폼을 추측하지 않는다
             문서의 구조와 명시된 관계만 해소한다                   문서 → 그 문서의 내용
             문서 → 보낸 쪽의 정체 · 능력 으로는 절대 가지 않는다
```

**프로필을 발신 전용으로 축소하면 안 된다.** 우리 Group 이 받은 제출을 어떻게 다룰지가 프로필에
달려 있다. 상대 플랫폼은 몰라도 되지만, **명령을 받는 우리 Group 의 운영 계약은 알아야 한다.**

그리고 `attributedTo` 가 컬렉션 URI 라는 것만으로 moderators 라고 확정하면 안 된다. 역참조해서
Collection 임을 확인해도 알 수 있는 것은 **귀속 대상으로 컬렉션을 참조했다는 사실까지**다.
세 가지를 분리한다.

```
구조 확인     attributedTo 가 가리키는 것이 Collection 인가, 같은 도메인인가
역할 해석     그 구성원이 moderator 라는 의미 — 별개의 판단이다
변경 권한     누가 그 목록을 바꿀 수 있는가 — 또 별개다 (§5)
```

미확정이면 **원본 참조와 구조만 보존**하고 역할로 읽지 않는다. 기록해 둔
`type ≠ relation` · `grant ≠ access` · `declared willingness ≠ consent` 와 같은 족속이다.

PeerTube 와의 상호운용이 필요해지면 그건 **우리가 Channel 프로필을 골라 배열을 내보내는 일**이지,
상대가 PeerTube 라고 판정하는 일이 아니다. 받는 쪽에서 플랫폼을 알아야 하는 자리는 지금 없다 —
moderators 는 컬렉션 URL 유무로 충분하고, `Create(Page)` 수용도 타입으로 판정한다
(Forum `inbound-topics.php:38` 이 이미 그렇게 한다).

현재 우리 코드에 플랫폼 분기는 없다(2026-11-02 실측). 벤더 네임스페이스 속성 IRI 로 분기하는 것은
여기 해당하지 않는다 — `https://misskey-hub.net/ns/#_misskey_quote` 는 **그 속성의 이름**이지
상대의 정체가 아니다.

Group 은 `featured: Option<Url>` 도 가진다. 레미에 커뮤니티 고정글이 있다는 뜻이다(§6).

## 2. 컬렉션

`crates/apub/apub/src/collections/community_moderators.rs`

```
type           OrderedCollection
orderedItems   Person URI 인라인
없는 것        페이지 · totalItems
verify         verify_domains_match(collection.id, expected_domain)
               컬렉션은 액터와 같은 도메인에 있어야 한다
```

**순서는 장식이 아니다.** `crates/db_views_actor/src/community_moderator_view.rs:56` 이
`.order_by(community_moderator::published)` — 임명 시각 오름차순이다. 그래서
`orderedItems[0]` 이 top mod 이고, Community 이전은 대상자를 맨 앞으로 옮기는 방식이다
(`crates/api/src/community/transfer.rs`).

## 3. 변경 와이어

`crates/apub/activities/src/community/collection_add.rs`,
`crates/apub/src/protocol/activities/community/collection_add.rs` (0.19.21)

```rust
pub struct CollectionAdd {
  actor:    ObjectId<ApubPerson>,            // Person 으로 타입 고정 — Group 이 actor 가 되지 않는다
  object:   Url,                             // 임명·해임 대상 Person
  target:   Url,                             // moderators 컬렉션 URI
  to:       Vec<Url>,                        // deserialize_one_or_many — 단수도 관용
  cc:       Vec<Url>,                        // 같음
  kind:     AddType,
  id:       Url,                             // Option 이 아니다 — 필수
  audience: Option<ObjectId<ApubCommunity>>, // 선택
}
```

**레미는 Activity id 를 붙인다** (`generate_activity_id(AddType::Add, …)`). Mastodon featured 의
id 누락은 **Mastodon 고유이지 threadiverse 관행이 아니다**. 그러므로 moderators 쪽에서는
featured 에 필요했던 수신 증거 우회(원격 id 없이 내부 id 로 기록)가 필요 없다.

**한 액티비티가 두 컬렉션을 담당한다.** `target` 이 `generate_moderators_url()` 이냐
`generate_featured_url()` 이냐로 갈리고, 수신측은 `Community::get_by_collection_url(target)` 으로
커뮤니티와 컬렉션 종류를 동시에 해소한다. 별도 Activity 타입이 아니라 **target URL 이 판별자**다.

**배포는 Group 의 Announce 다.** `AnnouncableActivities::CollectionAdd` — 로컬 Community 면
Group 이 Announce 하여 팔로워에게 배포하고 대상 Person 에게도 직접 보낸다. 원격 Community 면
그 Community 로 보내 중계받는다. 내부 Add 의 actor 는 끝까지 Person 이고 Group 은 감싸는
Announce 의 actor 로만 등장한다 — 이 둘을 섞지 말 것.

→ **moderators 이식보다 그 좁은 `Announce(Add/Remove)` 중첩 처리가 먼저 필요하다.**

## 4. 원격 동기화 — 복사하면 안 되는 한계가 있다

`handle_community_moderators()`

```
재조정    orderedItems 에 없는 현재 mod 제거, 새로 있는 사람 추가
항목 실패 mod_id.dereference().ok()
          "Ignore errors as mod accounts might be deleted or instances unavailable"
          → 한 항목이 못 풀려도 나머지 목록은 진행
저장      원격 mod 도 로컬과 같은 테이블 (CommunityActions::join) — 별도 캐시 없음
```

항목별 미확정이 목록 진행을 막지 않는 것은 우리가 featured 에서 3분할로 내린 결론과 같다.
"다른 서버의 curated 를 캐싱하나 같은 원장에서 다루나" 는 **같은 원장**이 답이다.

**한계 둘, 그대로 가져오면 안 된다.**

- **순서 미반영.** 재조정은 멤버십만 맞추고 기존 구성원의 순서는 건드리지 않는다. 구성원이
  같고 순서만 바뀌면 원격 권한 순위가 복원되지 않는다. 순서가 top mod 판정에 쓰이는데
  순서를 복원하지 않는 셈이다. **우리는 멤버십과 `orderedItems` 순서를 둘 다 보존한다**
  (featured 에서 핀 시각 순서를 보존한 것과 같은 요구).
- **`is_new_instance` 분기.** 새 인스턴스일 때 top mod 만 추가하고 반환한다. 레미 운영 사정이지
  계약이 아니다.

## 5. 권한 검증은 별도 판단이 필요하다

`crates/apub/src/activities/mod.rs:104` (0.19.21)

```rust
if is_mod_or_admin { return Ok(()); }
// mod action comes from the same instance as the community, so it was presumably done
// by an instance admin.
// TODO: federate instance admin status and check it here
if mod_id.inner().domain() == community.actor_id.domain() { return Ok(()); }
Err(NotAModerator)
```

같은 도메인 통과는 여기서 **설계된 규칙이 아니라 TODO 가 달린 추정**이다. instance admin 지위가
연합되지 않아서 생긴 임시방편이고 주석이 "presumably" 라고 자인한다.

`main` 은 이것을 **조인 게 아니라 넓혔다.** `crates/apub/objects/src/utils/functions.rs:310`

```rust
// Mod action comes from the same instance as the community, or same instance as the object
// creator. We trust that the origin instance performed necessary permission checks.
// https://codeberg.org/fediverse/fep/src/branch/main/fep/fe34/fep-fe34.md
if mod_id.inner().domain() == community.ap_id.domain()
  || mod_id.inner().domain() == object_id.domain() { return Ok(()); }
let mod_ = mod_id.dereference(context).await?;
check_is_mod_or_admin(&mut context.pool(), mod_.id, community.id).await?;
CommunityPersonBanView::check(&mut context.pool(), mod_.id, community.id).await?;
```

추가된 `object_id` 는 순위 검사 인자가 아니라 **도메인 신뢰 예외를 하나 더 넣는 인자**이고,
그 예외가 이제 맨 앞에서 돈다. TODO 자리에는 **FEP-fe34 를 근거로 단 선언**이 들어갔다
(ban 검사가 붙은 것은 조여진 쪽이다).

**FEP-fe34 "Origin-based security model" 은 DRAFT 다**(2024-11-15 접수). 우리 분류에서 draft 는
*채택한 정책* 칸이므로, 받아들이면 그건 기록해야 할 선택이지 준수가 아니다. 그리고 FEP 는
**cross-origin 모더레이터에 대해 양쪽 origin 의 상호 주장을 요구한다** — 원격 origin 의 말
하나로는 부족하다고 명시한다. 레미 구현에는 그 상호 확인이 없으니 **부분 채택**이다.

따라서 레미의 검사를 베끼면 **스펙의 안전성도 FEP 의 규칙도 아닌 중간**을 얻는다.
우리가 할 일은 FEP-fe34 를 채택할지를 따로 판단하는 것이고, 채택한다면 상호 주장까지 포함해야 한다.

그 상호 주장이 마침 §1 의 분리와 같은 것이다. "이 Person 이 여기 모더레이터다" 가 검증 가능해지려면
**우리 Group 의 `attributedTo` 가 그 컬렉션을 지목하고, 그 컬렉션이 그 Person 을 싣는** 양쪽이
필요하다. 한쪽만으로는 주장이지 사실이 아니다.

로컬 API 쪽에는 상위 모더레이터 검사와 마지막 모더레이터의 자진 해임 방지가 있고
(`crates/api/src/community/add_mod.rs`), 연합 수신 쪽에는 위 예외가 있다. 원격 Community 해임에서는
순위 검사를 생략한다. **"서명됨 + 같은 도메인" 을 우리 로컬 Group 운영 권한으로 번역하지 말 것.**

## 6. Group featured 판단을 되돌린다

이전에 "해석할 인스턴스가 없으니 FEP 부터" 라고 적은 것은 **틀렸다.** 레미가 해석자다 —
Group 에 `featured` 가 있고 `generate_featured_url()` 로 §3 과 같은 Add/Remove 기구를 쓴다.

후보 출처 걱정도 레미에서는 다르게 풀린다. 수신측이 `post.community_id != community.id` 면
거부하므로, 커뮤니티 고정글은 **이미 그 커뮤니티에 속한 글**이지 `Announce` 에서 고르는 게 아니다.
따라서 Group featured 는 FEP 가 아니라 **Community 프로필의 이식 항목**으로 내려올 수 있고,
순서상 moderators 다음이다.

## 7. featured 와 다른 세 가지 — 이식 설계의 본체

```
권한    verify_mod_action — 행위자가 이미 mod 여야 한다
        featured 의 "발신자가 그 컬렉션을 제어하는가" / 저자 일치와는 다른 종류의 게이트
배포    Group 의 Announce 로 감싸 배포 (FEP-1b12).  featured 는 저자가 직접 보낸다
멤버    member_models 가 ['Object'] 가 아니라 ['Actor'].  Actor 를 싣는 첫 컬렉션
```

그리고 **mod log 가 계약의 일부**다 —
`ModlogInsertForm::mod_add_to_community(actor, community, new_mod)` + 알림. 누가 바꿨는지를
남기는 것이 moderation 이 감사 가능해지는 방식이고, 우리에게는 대응물이 없다.

## 8. 저장은 버전 사이에서도 바뀐다

```
0.19.21   전용 community_moderator 행 (community · person · published)
main      community_actions.became_moderator_at 로 통합
```

**와이어 계약은 가져오되 DB 구조까지 고정할 이유는 없다.** Actors 소유의 Community 전용
멤버십으로 구현하고, Forum 의 memberships 테이블이나 principal `manager` 부여는 가져오지 않는다.
Forum 의 `includes/moderators.php` 는 복사 대상이 아니라 **직렬화·동작 비교 대상**이다
(FEP-8a8e 에서 event-bridge 를 그렇게 쓴 것과 같은 용법).

## 9. 이식 범위와 순서

```
1  Community 프로필 격리   Community/Channel, Channel 은 비활성 표시, 생성 후 고정·서버 검증
2  Announce(Add/Remove)    moderators 가 타는 중첩 Activity 의 좁은 처리
3  moderators              Community 전용 저장 · 순서 · 서빙 · Add/Remove · 그에 한정된 Announce
4  게시 Activity 해석       Create(Page) 와 답글 Create(Note)
—  Group featured          3 다음. §6 으로 FEP 선행 요구는 철회
```

실행 검증 전까지 "Lemmy 호환" 이라고 쓰지 않는다. "Lemmy 상호운용 프로필" 또는 구현 중으로 표시한다.
