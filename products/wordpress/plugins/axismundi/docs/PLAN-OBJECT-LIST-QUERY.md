# 판정안: 목록 query 소유권 (§7-A)

## 상태

**채택됨. 2026-10-12.** `DECISION-OBJECT-READ-SURFACES.md` §7-A가 2026-10-05부터 열어 둔
미결이고, 이 문서가 그 판정을 받았다. 7절의 여덟 질문에 **모두 예**로 답했다.

```txt
Q-1  예   공개 Object 멤버십과 viewer 권한 범위를 분리한다
Q-2  예   Q1은 A5 Object index, Q2는 A4 read-composition
Q-3  예   Object 목록의 순서 권위는 OP. Activities는 사건 피드만 소유한다
Q-4  예   기본값은 first-observed 순서. declared created/updated는 후속 선택 정렬
Q-5  예   cache eviction은 identity 삭제가 아니다
Q-6  예   rebuild = upsert, id와 canonical URI 불변을 migration 핀으로
Q-7  예   첫 인도는 id · canonical URI · object_type · source_ref · 상태 · 공개성
Q-8  예   Object index의 첫 실제 소비자는 공개 Object 컬렉션
```

**§7-A는 이것으로 닫힌다. A′(단일 조회 계약)는 닫히지 않는다** — viewer authorization,
local visibility 평가, remote cache의 공개 정책, API 버전이 그대로 열려 있고, Admin
inspector가 그것들의 첫 소비자다.

구현은 아직 하지 않았다. 아래가 고정된 실행 순서다.

```txt
M3 컴포넌트 완성
  -> Admin Actors DataView + WPDS list-detail + Actor JSON detail
  -> Object card와 공개 Object 컬렉션이 실제로 필요해짐
  -> 이때 Q1/A5 첫 인도 구현
  -> Admin Object DataView가 필요해질 때 Q2/A4 구현
```

**Q-8은 Admin Actors DataView를 막지 않는다.** Actor는 이미 통합 원장(`wp_ax_identities` +
`wp_ax_actors`)을 갖고 있으므로 Object index를 기다리지 않는다. 기다리는 것은 Admin
**Object** DataView이고, 그것은 Q2/A4가 실제로 필요해질 때다.

§7-A는 이미 다섯 안과 각각의 대가, A5의 대가 셋, 채택 trigger, 그리고 구현자 권고까지
적어 두었다. **이 문서가 더하는 것은 셋이다.**

```txt
1  이것은 한 질문이 아니라 두 질문이다          다섯 안은 한 질문처럼 틀 지어져 있다
2  권한은 이미 제품마다 따로 서 있다 (실측)      그래서 index가 답할 수 없는 범위가 있다
3  순서 권위는 Activities가 아니라 OP다          §7-A의 구현자 권고를 철회한다 (4-3)
4  현재 제안된 작업 순서에 모순이 하나 있다      첫 소비자와 첫 query 범위가 안 맞는다
```

4-3은 이 문서 **초판의 판단을 뒤집은 것**이기도 하다. 초판은 §7-A의 권고를 그대로 받아
"순서는 Activities"라고 적었다. 지운 자리를 남겨 두었다.

## 1. 왜 지금 이 판정이 필요한가

막혀 있는 것이 UI가 아니라 읽기 계약이다. Admin DataView, 객체 탐색, 검색,
list-detail의 list pane이 전부 같은 것을 기다린다. 그리고 **기다리는 동안 구현이 판정을
대신 내릴 위험**이 있다.

구체적으로: "`wp_ax_object_index`에 정렬 키와 `object_type`과 cursor 복합 인덱스를 채운다"는
작업은 **A5를 채택하는 일이다.** 그 테이블이 A5의 절반이고, 나머지를 채우면 A5가 선다.
작업 지시문에 "A5를 채택한다"가 없더라도 결과는 같다. 그러니 먼저 이름을 붙이고 판정받는다.

§7-A가 이 지점을 미리 적어 두었다 — A5를 고를 경우 다음 판정은 "새 합성 API를 만들까"가
아니라 "이 index를 목록 read model로 공식 채택하고 어떤 열·cursor·authorization을 둘까"가
된다고.

## 2. 이것은 두 질문이다

다섯 안은 "목록 query를 누가 소유하는가" 하나를 두고 배열돼 있다. 그런데 실제로 요구되는
목록은 두 종류이고, **같은 소유자가 둘 다 답할 수 없다.**

```txt
Q1  공개 객체 목록        누가 이 컬렉션에 속하는가
    공개 컬렉션, 공개 topic directory, 비로그인 탐색

Q2  권한 범위 목록        이 뷰어가 무엇을 볼 수 있는가
    Admin inspector, followers-only 컬렉션, group-members 목록
```

§7-A는 이 구분을 A5의 대가 2번 안에 접어 두었다 — "`publicly_listable` 불리언 하나가
authorization을 담지 못한다 … **이것이 A5의 진짜 경계 질문이다**". 맞는 지적이고, 이
문서는 그것을 대가가 아니라 **질문의 축**으로 올린다. 대가로 두면 "나중에 컬럼을 늘리자"가
되고, 축으로 두면 "둘은 다른 소유자를 갖는다"가 된다.

## 3. 실측 — 권한은 이미 제품마다 따로 서 있다

2026-10-12, 저장소 전수 검색.

```txt
axismundi_actors_can_view( Axismundi_Actor, ?user_id )              actors/routing.php
axismundi_cal_can_read( calendar_id, actor_uri, user_id )           calendar/calendar-acl.php
axismundi_cal_can_view_attendee_location( actor_uri, post_id )      calendar/event-participation.php
axismundi_forum_can_view_community_topics( group_identity_id, ... ) forum/topics.php
axismundi_emoji_can_read_catalogue()                                emoji/catalogue.php
```

그리고 **객체에 대한 교차 제품 viewer 평가기는 없다.** Activities의
`axismundi_act_resolve_audience()`와 `axismundi_act_audience_visibility()`는 **저작 쪽**이다 —
actor와 visibility로 `to`/`cc`를 만들고, 거꾸로 audience를 visibility 라벨로 분류한다. "이
뷰어가 이 객체를 볼 수 있는가"는 답하지 않는다.

**이 실측이 Q2의 답을 거의 정한다.** 권한이 다섯 군데에 흩어져 있고 각자 자기 도메인의
규칙(ACL 테이블, group membership, actor status)을 안다면, 단일 index가 Q2를 답하려면 그
다섯을 복제해야 한다. 복제한 순간 그것은 projection이 아니라 **두 번째 권한 소스**이고,
드리프트가 곧 정보 유출이다.

§A5의 대가 1번은 "하나를 빠뜨리면 목록이 조용히 거짓말한다"고 적는다. 멤버십에서 거짓말하면
목록이 빈다. **권한에서 거짓말하면 비공개 객체가 보인다.** 같은 실패가 아니다.

## 4. 제안하는 판정

**Q1은 A5, Q2는 A4. 두 안은 서로 배제하지 않는다.**

§7-A의 표는 다섯을 택일로 늘어놓지만, 둘은 서로 다른 질문에 답하므로 함께 설 수 있다.

### 4-1. Q1 — 공개 목록은 A5 (index)

이유 셋.

- **이미 절반 서 있다.** `wp_ax_object_index`가 존재하고, 원격 캐시 저장이 트랜잭션 안에서
  갱신하며, local backfill 구조가 있고, OP가 다른 제품의 저장소를 직접 join하지 않는다는
  §1의 경계가 지켜지고 있다.
- **공개 범위에서는 대가 2번이 발생하지 않는다.** `publicly_listable` 불리언이 정확히
  답할 수 있는 질문만 묻기 때문이다.
- **index가 멤버십과 순서를 **둘 다** 갖는다.** §7-A의 구현자 권고는 "멤버십만 갖고 순서는
  Activities가" 쪽이었고, 이 문서의 초판도 그것을 따랐다. 4-3이 그 권고를 철회한다 —
  Activity 순서는 사건의 순서이지 객체의 순서가 아니다.

  대가 3번(원격 `published`가 신뢰할 수 없고 단조롭지 않다)은 그래도 발생하지 않는다.
  기본 정렬이 원본이 주장하는 시각이 아니라 **이 설치의 유입 순서**이기 때문이다.
  `published_at`을 index에 복제할 필요도 여전히 없다.

### 4-2. Q2 — 권한 범위 목록은 A4 (capstone의 read-composition)

이유 둘.

- **권한이 이미 제품에 있다(3절).** A4는 "제품이 각자 query를 제공하고 `axismundi`의
  read-composition API가 공개 계약만 조합"하는 안이다. 각 제품이 자기 `can_view`와 자기
  query를 그대로 쓰고, 합성만 서버 쪽에 이름 있는 소유자를 갖는다. 권한을 옮기지 않는다.
- **A4의 대가인 "새 층이 하나 늘어남"에 소비자가 있다.** Admin DataView가 그 소비자다.
  소비자 없는 층을 만들지 않는다는 이 저장소의 규칙에 걸리지 않는다.

A3(Activities가 교차 질의 소유)을 고르지 않는 이유는 §7-A가 이미 적었다 — Activities가
범용 object catalog가 된다. 그리고 3절의 실측이 그것을 더 밀어낸다: Activities는 저작 쪽
audience는 알지만 다섯 제품의 viewer 규칙은 모른다.

### 4-3. 순서 권위는 Activities가 아니라 OP 자신이다 (초판 철회)

**이 절의 초판은 "index는 멤버십, 순서는 Activities의 `feed_sort_at`"이었다. 철회한다.**
지운 자리를 남겨 두는 이유는 그 추론이 그럴듯했기 때문이다 — §A5가 "Activity 원장은 로컬
수신 시각으로 정렬하므로 원격 `published` 문제가 없다"고 적었고, 나는 거기서 "그러니 목록도
Activity 순서를 타면 된다"로 넘어갔다. 그 한 걸음이 틀렸다.

**두 시간축은 서로 다른 것을 재고 있다.** 소유자의 비유가 정확하다 — 미디어 라이브러리에서
실제 파일은 글 발행일의 연/월 경로에 올라가고, attachment page는 **지금 시각** 기준으로
만들어진다. 같은 대상의 두 시각이고, 하나를 다른 하나로 대신할 수 없다.

```txt
Activity   이 설치가 사건을 언제 처리했는가       feed_sort_at
Object     객체가 언제 만들어지고 바뀌었는가,      ← 따로 필요하다
           우리가 언제 관측했는가
```

Activity 순서를 목록에 쓰면 **`Update`나 `Announce` 하나가 객체의 자리를 바꾼다.** 누가
3년 전 글을 Announce하면 그 글이 목록 맨 위로 올라온다. 사건 피드에서는 맞는 거동이고 객체
목록에서는 틀린 거동이다.

그러므로 **Activity feed의 순서는 Activities가, Object 목록의 순서는 OP가 소유한다.**

#### 기본 정렬은 "이 설치에 들어온 순서"다

`created`를 기본으로 둘 수 없다. WordPress는 과거 날짜로 발행할 수 있고(1996년생이 1982년으로
발행할 수 있다), 원격 `published`도 신뢰·단조성이 없다. **둘 다 "원본이 주장하는 시간"이지
우리 시간축이 아니다.**

데이터가 들어오는 경로는 셋이고 전부 다른 시각을 갖는다.

```txt
local publish          로컬 객체가 투영됨
manual fetch           원격 객체를 명시적으로 관측함
activity observation   inbound Activity를 통해 관측함
```

기본 정렬은 이 셋을 한 축에 놓는 **유입 순서**다. 의미 정렬은 선택으로 제공한다.

```txt
observed  (기본)   이 설치가 처음 받아들인 순서
declared_created   원본이 주장한 생성 시각
declared_updated   원본이 주장한 수정 시각
changed            우리 projection의 실제 상태 변경 순서
```

기본 `observed`는 나중에 `Update`나 단순 refresh가 와도 자리를 바꾸지 않는다. 새로 들어온
객체가 위에 오고, 이미 있는 객체의 갱신은 `changed`에서만 움직인다.

#### 첫 인도에 필요한 것은 컬럼 하나다

제안된 설계는 `object_observations` 원장을 따로 둔다. **첫 인도에는 필요하지 않다.**

기본 정렬이 요구하는 것은 index 행마다 하나씩, 삽입 시 부여되는 단조 증가 수 하나뿐이다.
그것은 `object_index.id AUTO_INCREMENT`다. 그리고 그 값은 유일하므로 **cursor가 단일
컬럼이다** — 동점 해소용 튜플이 필요 없다.

```txt
지금 넣는다   id AUTO_INCREMENT      기본 정렬과 cursor. 이것만으로 선다
              object_type            Note인지 Article인지 거르기
              source_ref             어느 adapter가 hydrate할지

나중에        declared_* 정렬용 컬럼  SQL에서 정렬하려면 index에 있어야 한다.
                                     소비자가 요구할 때 넣는다
              changed 시퀀스          material change에서만 전진. 소비자 없음
              object_observations    경로별 관측 이력이 실제로 필요해질 때
```

`wp_ax_remote_objects.id`를 공통 키로 쓰지 않는다. **remote 전용이고** 로컬에는 대응값이
없으며, cache 재구축 방식을 목록 계약에 묶는다. 실측으로 확인했다 — 그 `id`는 cache
테이블에만 있다.

#### `id`는 순서이지 정체성이 아니다 — 그리고 재구축과 충돌한다

이 두 줄을 적지 않으면 나중에 깨진다.

`object_uri_hash`가 정체성이고 `id`는 **순서 전용**이다. 공개 cursor에 나가더라도 설치 간에
의미가 없는 불투명한 값이며, 설치 사이에 옮겨 쓸 수 없다.

그리고 **"재구축 가능한 projection"과 "유입 순서"는 서로 당긴다.** index를 비우고 backfill로
다시 채우면 `id`는 backfill 순서로 재부여되고, 원래 유입 순서는 사라진다. 로컬은 글 발행일로
복원할 수도 없다 — 그 값이 신뢰할 수 없다는 게 이 절의 출발점이다.

그러므로 재구축의 의미를 좁힌다: **재구축은 listing state를 다시 계산하는 upsert이지 비우고
다시 쓰는 것이 아니다.** 이미 있는 행의 `id`는 건드리지 않는다. 처음 관측 시각은 소스에서
유도되지 않는 정보이므로 보존 대상이다.

이것은 §A5가 "rebuildable projection"이라고 적은 것을 바꾸는 게 아니라 **그 말의 범위를
확정하는 것**이다.

### 4-4. identity의 수명과 payload의 수명은 다르다

4-3의 `id`가 **영구 번호**여야 한다는 것이 따라 나온다. WordPress가 글 1·2·3을 쓰고 4를
지우고 5·6으로 가도 나중에 4를 다시 쓰지 않는 것과 같다. 번호가 재발급되면 "그 자리에
있던 것"을 가리킬 수 없다.

Object에서 그 필요가 구체적이다. **원격 payload가 만료된 뒤에 `Delete`가 도착할 수 있다.**
그때 우리가 알아야 하는 것은 "예전에 관측했던 그 객체"이고, 그러려면 payload가 사라진 뒤에도
identity가 남아 있어야 한다.

```txt
payload 수명    retention policy가 정한다. 만료 + lease 없음 -> 지워도 된다
identity 수명   cache retention과 독립이다
```

**"영구"의 뜻을 좁혀 적는다.** identity retention이 cache retention과 독립이라는 말은
**cache purge가 identity를 지우지 않는다**는 뜻이지, 그 행을 영원히 들고 간다는 뜻이 아니다.
오래된 tombstone을 언제 잊어도 되는지는 **별도 보존 정책의 미래 결정**이고 이 판정안이
정하지 않는다. 지금 정하는 것은 "누가 지우지 못하는가"이지 "언제 지울 수 있는가"가 아니다.

#### 실측 — 현재 코드는 반대 계약을 들고 있다

2026-10-12, `axismundi-object-projections/includes/remote-objects.php`.

cache 만료 유지보수가 만료 대상 URI를 모아 cache 행을 지운 **뒤 listing projection 행도
같이 지운다.** 주석이 그 계약을 그대로 적는다 — "Keep the listing projection in lockstep
with cache eviction."

```php
$purged_uris = /* expires_at <= now AND lease 없음 */;
$wpdb->query( "DELETE o FROM {$table} o WHERE {$where}" );
foreach ( $purged_uris as $purged_uri ) {
    axismundi_op_delete_object_listing_projection( (string) $purged_uri );
}
```

**오늘은 이것이 맞다.** 지금 index는 파생된 listing state일 뿐이고 `id`도 identity 역할도
없으므로, 지워도 잃는 것이 없고 재관측하면 같은 행이 다시 생긴다. 4-3이 `id`를 넣는 순간
그 전제가 깨진다 — 재관측이 **새 번호**를 받고, 늦게 온 `Delete`를 옛 객체에 연결할 수 없다.

그러므로 4-3을 채택하면 이 lockstep을 함께 바꾼다. 바뀌는 것은 한 줄의 의미다.

```txt
cache 만료        payload 행 삭제 + index 행의 status를 evicted로        (삭제 아님)
명시적 remote 삭제 payload 행 삭제 + index 행의 status를 tombstone으로    (삭제 아님)
Delete 수신       canonical URI로 기존 행을 찾아 같은 id의 status 전환
```

**lease 계약은 바뀌지 않는다.** `REMOTE-OBJECTS.md` §2의 "만료됐고 동시에 아무도 붙잡고
있지 않아야 지워진다"는 그대로 payload에 적용된다. 바뀌는 것은 그 삭제가 index 행까지
끌고 가느냐 하나뿐이고, 그래서 이 변경은 작다.

#### canonical URI도 index에 보존한다

지금 index는 `object_uri_hash`만 들고 URI 원문은 cache 테이블에 있다. cache가 비워진 뒤
`Delete`의 URI를 **정확히 일치시키려면** URI가 index에 있어야 한다. hash는 인덱스 가속용이고
identity 판정을 대신하지 않는다 — Actors가 `canonical_uri`를 원장에 들고 있는 것과 같은
이유다.

#### 첫 인도 스키마 (4-3에서 갱신)

```txt
id AUTO_INCREMENT              영구 내부 번호. 재발급하지 않는다
object_uri + object_uri_hash   cache와 독립된 canonical identity
source + source_ref            hydrate 힌트
object_type
object_status                  active | tombstone | evicted
publicly_listable              (이미 있음)
```

#### migration 조건 — 핀으로 남긴다

이 둘이 서면 `evicted -> active`와 `evicted -> tombstone`이 **같은 Object identity 위에서**
안전하게 처리된다. 둘 중 하나라도 깨지면 `id`를 넣은 의미가 사라지므로, 구현이 아니라
조건으로 먼저 적는다.

```txt
재구축은 기존 index 행을 upsert한다. 비우고 다시 쓰지 않는다.
index의 id와 canonical URI는 cache eviction · backfill · refresh에서 바뀌지 않는다.
```

#### 대가 — index는 단조 증가하고 줄지 않는다

한 번 관측한 모든 객체가 행을 영구히 갖는다. 연합 인스턴스에서 이것은 큰 수가 될 수 있다.
그 대가를 적어 두고 받아들이는 근거는 **행이 좁다**는 것이다 — payload·content·summary는
cache에 있고 index에는 해시·URI·타입·상태·플래그만 남는다. WordPress가 글 ID를 재사용하지
않으면서 치르는 대가와 같은 종류다.

줄이고 싶어지면 그때 묻는 질문은 "행을 지울까"가 아니라 **"얼마나 오래된 tombstone을
잊어도 되는가"**이고, 그것은 별도 보존 정책이다. 지금 정하지 않는다.

### 4-5. 지금 채택하지 않는 것

**Q2의 구현은 지금 하지 않는다.** 판정만 받는다. §7-A의 "없는 부하를 근거로 있는 비용을
지불하지 않는다"가 그대로 적용된다 — Admin DataView가 실제로 착수될 때 A4를 세운다.

## 5. 현재 제안된 작업 순서의 모순

검토 중인 구현 계획이 둘을 동시에 말한다.

```txt
"첫 query는 publicly_listable 공개 객체만 대상으로 좁히는 편이 안전하다"
"Admin inspector → DataView를 첫 소비자로 붙인다"
```

**두 문장이 맞지 않는다.** Admin은 "내가 무엇을 할 수 있는가"로 묶는 표면이다
(`DECISION-OBJECT-READ-SURFACES.md` §2). 공개 객체만 보는 inspector는 admin 도구가 아니다.
그리고 §4는 Admin이 **remote object cache를 별 목록으로** 봐야 한다고 적는데, 그 목록은
공개 여부로 좁혀질 수 있는 성격이 아니다.

즉 첫 query를 공개로 좁히는 것은 옳고(대가 2를 피한다), 그렇다면 **첫 목록 소비자는 Admin
DataView가 아니다.** 둘 중 하나를 바꿔야 한다.

**다만 "Admin이 아니다"는 너무 넓었다.** 막히는 것은 Admin **DataView**이지 Admin 전체가
아니다. 단일 `objectKey`를 보는 inspector는 목록 query 없이 **resolver의 첫 소비자**가 될 수
있고, A′가 열어 둔 단일 조회 계약(viewer authorization · local visibility 평가 · remote cache의
공개 정책 · API 버전)을 거기서 닫는 편이 자연스럽다. 목록과 단일 조회는 서로를 기다리지
않는다.

## 6. 따라 나오는 작업 순서

판정이 4절대로 나올 경우.

```txt
1  A5를 Q1의 read model로 공식 채택                      판정
2  index에 빠진 것을 채운다                              id AUTO_INCREMENT · object_type · source_ref
3  공개 목록 REST read 계약                              기본 sort=observed, cursor는 id 단일 컬럼.
                                                         source_ref와 remote cache PK는 나가지 않는다
5  첫 목록 소비자 — 공개 컬렉션                           Admin DataView가 아니다 (5절)
6  (병렬) Admin inspector — 단일 조회 resolver            목록을 기다리지 않는다. A′를 닫는다
7  (별건) Q2가 실제로 필요해질 때 A4를 세운다             Admin DataView 착수 시점
```

2번의 "정렬 키는 넣지 않는다"가 §A5 구현자 권고와 다른 점이 없어 보이지만, 실제로는 이
한 줄이 대가 3번과 "원격 `published` 신뢰" 문제 전체를 작업 범위에서 들어낸다.

## 7. 판정 — 여덟 개의 질문

**2026-10-12에 여덟 모두 예로 판정됐다(상태 절).** 아래는 질문의 원형과,
각 질문이 아니오였을 때 열렸을 것을 남겨 둔 것이다.

판정은 소유자의 것이다. 각 질문은 **예/아니오로 답할 수 있는 형태**로 적었고, 아니오일 때
무엇이 열리는지도 함께 적었다. 그래야 거절이 공백이 되지 않는다.

**스키마 마이그레이션과 REST query 구현은 이 여덟이 답을 받기 전에는 시작하지 않는다.**
§1에 적은 이유 그대로다 — `wp_ax_object_index`에 열을 채우는 작업이 곧 A5 채택이고,
지시문에 그 말이 없어도 결과는 같다.

### 구조

**Q-1. 목록 질문을 Q1(공개 멤버십)과 Q2(뷰어 권한)로 가르는가?**
아니오면 다섯 안 중 하나를 택일해야 하고, A5 단독은 Q2를 답할 수 없다는 3절의 실측이
그대로 남는다.

**Q-2. Q1 = A5(index), Q2 = A4(capstone의 read-composition)인가?**
아니오면 Q2의 소유자를 따로 정해야 한다. A3(Activities)은 §7-A가 이미 "범용 object
catalog가 된다"는 대가를 적었고, 3절의 실측이 그것을 더 밀어낸다.

### 순서

**Q-3. Object 목록의 순서 권위를 OP가 갖는가?**
Activities는 사건 피드의 순서만 갖는다. 아니오면 `Update`·`Announce`가 객체의 자리를
바꾸는 거동을 받아들이는 것이고, 그 근거를 적어야 한다 (4-3).

**Q-4. 기본 정렬을 "이 설치에 들어온 순서"로 두는가?**
`declared_created`·`declared_updated`는 선택 정렬로 제공한다. 아니오면 WordPress의 과거
발행일과 원격 `published`의 비단조성을 기본 정렬이 그대로 받는다.

### identity

**Q-5. cache 만료와 identity 삭제를 분리하는가?**
지금 코드는 lockstep이다. 아니오면 `id`를 넣어도 재관측이 새 번호를 받고, 늦게 온 `Delete`를
옛 객체에 연결할 수 없다 (4-4).

**Q-6. migration 두 조건을 핀으로 채택하는가?**
재구축은 upsert이고, `id`와 canonical URI는 eviction·backfill·refresh에서 불변이다.
아니오면 Q-5가 서도 무너진다.

### 범위

**Q-7. 첫 인도를 4-4의 스키마로 좁히는가?**
`object_observations` 원장과 `declared_*` 정렬 컬럼은 소비자가 생길 때. 아니오면 지금
없는 부하를 근거로 있는 비용을 지불하게 된다 — §A5가 경계한 그 모양이다.

**Q-8. 첫 목록 소비자를 Admin DataView가 아닌 공개 컬렉션으로 두는가?**
Admin inspector는 단일 조회 resolver의 소비자로 병렬 진행한다. 아니오면 첫 query의 범위를
공개로 좁힐 수 없고, 대가 2번(권한을 index가 복제함)을 즉시 받는다 (5절).

## 관련 기록

- `DECISION-OBJECT-READ-SURFACES.md` — §7-A의 다섯 안, A5의 대가와 trigger, 구현자 권고.
  이 문서가 판정을 받으면 그 미결이 닫힌다.
- `axismundi-object-projections/includes/remote-objects.php` — `wp_ax_object_index`의 현재
  스키마.
- `axismundi-activities/includes/audience.php` — 저작 쪽 audience resolver. viewer 평가기가
  아니다(3절).
- `LOCAL-OBJECTS.md` — local projection을 동적으로 둔 이유. A5의 대가 1번의 출처.
