# C2S 명령 계약 — 액티비티와 객체의 선후

2026-11-10. 측정은 전부 로컬 wp-env 와 저장소 소스에서 그날 수행했다.
경로 네임스페이스 판정 기준은 `products/wordpress/plugins/axismundi-object-projections/docs/PROTOCOL-ROUTE-NAMESPACES.md`를 따른다.

## 문제

C2S 컴포저가 outbox 에 POST 할 때 **액티비티와 객체를 동시에** 만든다.
그런데 이 제품군에는 액티비티보다 객체가 먼저 존재하는 경우가 이미 둘 있고,
곧 셋이 된다.

```txt
                   객체가 먼저 있나   객체 저장소      현재 맞는 계약
wp_posts Article   예 (?p=123)       wp_posts        A (에디터)
컴포저 Note         아니오            local objects   B (컴포저)
uploadMedia Video  예 (먼저 생성)     local objects   없음
```

Video 는 **저장소는 B, 선후는 A** 라서 둘 다에 안 맞는다.
B 를 그대로 쓰면 `local-objects.php:84` 가 `id` 를 버려 uploadMedia 가 만든 객체를
가리킬 수 없고, A 를 그대로 쓰면 `post_id` 가 없다.

## 측정 — 이미 명령 모양이 둘이고 같은 제출기로 들어간다

```php
// A. 에디터 — activities/includes/post-lifecycle.php:98-104
array( 'operation' => $operation, 'post_id' => (int) $post->ID,
       'expected_version' => (string) $post->post_modified_gmt )
// 객체를 문서로 싣지 않고 post_id 로 가리킨다. 서버가 ?p=123 을 유도한다.
// :64   Create → 'object' => $object_uri      스칼라 URI, 로컬객체 행 없음
// :225  Update → axismundi_op_transform_object($post) 전체 문서 + fingerprint 비교
// 둘 다 axismundi_act_submit_c2s() 로 간다 (:120, :126)

// B. 컴포저 — activities/includes/c2s-local-object.php
// 문서를 인라인으로 싣는다. Create 는 URI 를 할당한다
// (object-projections/includes/local-objects.php:84 가 id·url·published·updated·
//  deleted·formerType·@context 를 unset 한다)
// Update 는 'Note' 한정 + editable 스칼라 필드 패치 (:28-35)
```

**블록 편집은 A 에서 이미 된다.** `post_content` 는 WP 가 소유하고, 투영이 매번
재유도되며, `axismundi_act_lifecycle_fingerprint()` 가 Update 를 낼지 판단한다
(`post-lifecycle.php:218-223`). 갭은 "Update(Article) 에서 블록 편집이 안 된다"가
아니라 **같은 원장 위에 계약이 둘인데 세 번째 경우가 어디에도 안 맞는다**는 것이다.

### w3c/activitypub#438 은 우리를 물지 않는다

C2S Create 의 내부 객체 `id` 를 서버가 보존해야 하는지 재발급해야 하는지가 불명확하다는
이슈인데, 우리는 `local-objects.php:84` 에서 **받아들이지도 거부하지도 않고 버린다**.
클라이언트 id 를 보존하는 경로가 없으므로 그 모호함이 발생할 자리가 없다.
아래 결정은 이 성질을 유지한다 — Create 는 클라이언트 id 가 아니라
**서버 소유 객체 참조**를 받는다.

## 결정 ① 명령은 객체를 URI 로 가리키고, 문서는 소스 어댑터가 공급한다

A 가 일반형이고 B 가 특수형이다. 지금은 거꾸로 읽히게 돼 있어서
Video 를 B 에 억지로 맞추려는 압력이 생긴다.

```txt
일반형   명령이 객체를 가리킨다 + 소스 어댑터가 문서를 공급한다
         A = post_id → 투영
         Video = object_uri → 로컬객체 행
특수형   소스가 아직 없어서 문서를 함께 싣는다
         B = 컴포저의 새 Note
```

따라서 `local-objects.php:84` 의 `unset` 은 **풀지 않는다**. Video 는 uploadMedia 가
객체 행을 먼저 만들고, Create 는 그 URI 를 가리킨다.

## 결정 ② `sent` 플래그를 만들지 않는다 — 발행 사실은 원장이 권위다

JMAP 이 같은 질문에 답을 내놨고 두 기구를 쓰면서 선을 그었다.

```txt
RFC 8621  등록 키워드 $draft · $seen · $flagged · $answered   (8621.txt:1362-1369)
          FilterCondition inMailbox · inMailboxOtherThan      (8621.txt:2532-2537)
          → "$sent" 키워드는 없다. sent 는 Mailbox role 이다 (§4.1.1)

작성 중 상태 = 플래그 / 보냈다는 사실 = 컨테이너
```

우리 대응:

```txt
Mailbox role:"sent"   ≡   Activity 원장 (direction='outbound')
inMailbox 필터         ≡   원장 질의 (actor + activity_type)
Email / account        ≡   Activity / Activity.actor
$draft 키워드          ≡   대응물 없음 — 아래 참조
```

**`object_status` 는 `$draft` 에 대응하지 않는다.** 로컬객체는 할당 시점에
`'active'` 로 들어가며(`local-objects.php:129` 가 `published_at` 과 함께 쓴다),
`'active'` 는 "미발행" 을 뜻하지 않는다. 그 컬럼의 축은 존재/tombstone 이다.
발행 이력은 원장에서만 확인한다. 즉 우리에게는 JMAP 의 `$draft` 에 해당하는
객체측 플래그가 없고, 이 계약은 그것을 만들지 않는다.

**권위가 둘이 되기 때문이다.** `publicly_readable`(`local-objects.php:128`)은 같은
문서에서 쓰기 시점에 유도한 값이라 성질이 다르다. `sent` 는 다른 테이블의 나중
사건에서 유도되므로 어긋날 수 있고, 어긋났을 때 어느 쪽이 맞는지 정할 근거가 없다.

**단 재생성 가능한 투영에 두는 것은 다르다.** 금지 대상은 로컬객체 행의 저장 상태이고,
`wp_ax_object_index`(`op/includes/remote-objects.php:40-52`, 주석이 "rebuildable Object
listing projection")에 유도 컬럼을 두는 것은 권위를 늘리지 않는다. 판정 기준은
**"재생성 명령이 있고 그것이 원장을 읽는가"** 다.

```sql
-- 현재 컬럼: object_uri_hash(PK) · publicly_listable · object_status · source
--            attributed_to_uri_hash · is_reply · has_group_context · primary_group_uri_hash
-- 추가할 것:  federated tinyint(1)      Create 이력이 있는가
```

**불리언으로 충분하다** — 아래 "한 URI 에 Create 는 한 번" 이 성립하므로 lifecycle
타입 컬럼이 필요 없다. `object_status`(active|tombstone)가 존재 축을,
`federated`(0|1)가 발행 축을 맡는다.

```txt
Not federated   federated = 0 인 조회 필터다. 저장 상태가 아니다
pending         사용자가 발행을 요청·예약해 제출 작업이 실제로 생겼을 때만 붙인다.
                Create 가 없다는 이유로 자동으로 걸면 "사이트에만 올리려던 글" 과
                "발행 대기열" 이 한 컬럼에 섞인다
배달 대기·실패   제출 이후의 전송 상태이며 Bridge 소유다. 활동 초안이 아니다
```

갱신 지점과 재빌드 비용은 미측정이다 — 지금 `refresh_object_listing_projection()`
(`remote-objects.php:216-228`)은 객체 변경 때 호출된다(`post-attribution.php:109`).
원장 기록 시에도 호출돼야 하며 `axismundi_act_activity_recorded` 훅이 이미 있다
(`activities/includes/post-attribution.php:39` 가 선례다).

`published_at`(`local-objects.php:129`)은 **할당 시각**이지 Create 가 원장에 닿은
시점이 아니다. 지금 sent 표시는 없다.

## 결정 ③ Update 후보 조회는 기존 outbox 를 쓴다 — 새 라우트도 투영 테이블도 없다

```txt
GET axismundi/activitypub/actors/{uuid}/outbox?activity_type=Create&page=1
그 다음은 응답의 next 를 따라간다 — 페이지 번호를 증가시키지 않는다
```

이미 전부 있다.

```php
// op/includes/integrations/actors.php:480-491   라우트와 필터
'/actors/(?P<uuid>[0-9a-f-]{36})/outbox'
'methods' => WP_REST_Server::READABLE, 'permission_callback' => '__return_true'
'args' => array( 'uuid', 'page', 'cursor', 'activity_type' )

// activities/includes/repository.php:1010   실제 질의
WHERE actor_uri_hash = %s AND actor_uri = %s AND direction = 'outbound'
  {$status}{$where} ORDER BY feed_sort_at DESC, id DESC LIMIT %d
// :1000-1003  activity_type 정확 일치 필터
```

페이징은 커서다. `op/.../actors.php:296` 이 `$source->get_cursor()` 로 호출하고
`:333` 이 `$out['next'] = …outbox_page_url(…, next_cursor, …)` 를 낸다.

**`$content_management` 는 이 경로에 걸리지 않는다.** `repository.php:1004-1006` 에
`activity_type IN ('Create','Update','Delete') AND object_uri IS NOT NULL` 플래그가
있지만, outbox 는 `axismundi_act_get_public_outbox_page()`(`:643`)를 거치고 그 함수에는
해당 인자가 없으며 `:660` 이 다섯 인자로만 호출해 기본값 `false` 로 떨어진다.
같은 경로의 조건으로 읽지 말 것 — 후보를 Create 로 좁히는 것은 `activity_type`
필터 하나이고, 위 플래그는 다른 호출자를 위한 것이다.

`permission_callback => '__return_true'` 는 **라우트 접근만** 공개이고, 가시성은
reader 클로저가 정한다. 소유자는 전부 본다 — AP 5.1 대로다.

> "The outbox stream contains activities the user has published, subject to the
> ability of the requestor to retrieve the activity (that is, the contents of the
> outbox are filtered by the permissions of the person reading it). If a user
> submits a request without Authorization the server should respond with all of
> the Public posts." — ActivityPub §5.1

**폐기한 두 제안**(기록용): lifecycle 투영 테이블, 별도 저작용 라우트
(`publishable`). 둘 다 불필요하다. 저작용 라우트를 `axismundi/v1` 의 outbox 주소에
두려던 것은 **그 주소가 이미 302 알리아스**라서 성립하지도 않는다
(`op/includes/activitypub-routes.php:6-10`, `:17-19`).

### 후보 판정은 조회가 아니다 — 세 겹을 더 본다

```txt
① Delete 제외   effective_status 는 Undo 에만 반응한다 (repository.php:1353
                $desired = $undone ? 'undone' : 'active'). Delete 는 이 컬럼을
                건드리지 않으므로 삭제된 객체의 Create 도 'active' 로 남는다.
                객체별 axismundi_act_get_object_lifecycle() (repository.php:719,
                KEY object_uri_hash) 로 최신 lifecycle 이 Delete 인지 본다.
                이는 post-lifecycle.php:201-204 가 이미 쓰는 규칙이다.
② 수정 지원     조회된다고 수정되는 것이 아니다. c2s-local-object.php:28 은
                Update 를 'Note' 로 한정한다. Article·Video 가 목록에 나와도
                지금 계약으로 수정되지 않는다. 조회 ⊥ 수정 지원은 별도 계약이다.
③ 권한 재확인   제출 시 소스 어댑터가 현재 객체 상태와 액팅 액터의 수정 권한을
                다시 검증한다. 목록 시점의 판정을 신뢰하지 않는다.
```

**`Create` 목록도 lifecycle 조회도 수정 권한의 근거가 아니다.**
`axismundi_act_get_object_lifecycle()`(`repository.php:719`)은 객체 URI 전체에서
최신 활동을 찾을 뿐 **actor 로도 direction 으로도 제한하지 않는다.** 그래서 그
결과는 "이 객체가 살아 있나" 에만 답한다. "이 액터가 이것을 수정할 수 있나" 는
제출 시 소스 어댑터가 따로 답해야 한다. 목록은 후보 탐색용이다.

### 한 URI 에 Create 는 한 번이다 — Create → Delete → Create 는 금지다

```php
// op/includes/local-objects.php:51-52   로컬객체 — UUID 할당
home_url( '/objects/' . strtolower( $uuid ) )
// 삭제 후 재게시는 새 wp_generate_uuid4() → 새 URI

// op/includes/post-article.php:97-98    wp_posts — post ID 유도
add_query_arg( 'p', (int) $post->ID, home_url( '/' ) )
// WordPress 의 휴지통이 아니라 영구삭제 경로가 outbound Delete 를 기록한다.
```

**LOCAL_POLICY:** outbound Delete 가 기록된 URI 에 새 outbound Create 를 기록하지
않는다. ActivityPub 의 URI 재사용 금지 조항이나 DB ID 할당 방식에 의존하는 규칙이
아니다. 같은 URI 의 기존 Create 를 멱등하게 다시 읽는 것은 새 Create 가 아니다.

서로 다른 WordPress 글에 같은 ID 가 할당되어 URI 가 충돌하는 경우는 별도의 식별자
할당 문제다. 이 정책은 그 충돌을 해결하지 않는다. 로컬 오브젝트를 새로 만들 때는
새 UUID 를 할당한다. `Undo(Delete)` 를 도입하더라도 기존 객체 복원은 새 Create 와
별개이며, 여기서는 복원을 구현하지 않는다.

```txt
발행 이력 목록       URI 당 Create 한 건
Update 대상 선택기   접기 로직이 필요 없다. 위 ① 의 lifecycle 판정으로 충분하다
```

### `actor` 와 `attributedTo` 를 같은 뜻으로 쓰지 않는다

후보 필터는 **`actor` 정확 일치**다. `attributedTo` 포함으로 뽑으면 공동저자가
후보에 들어와 **수정권 없는 사람에게 Update 가 보인다**. 공동저자에게 수정권이
없다는 것은 기록된 결정이다.

```txt
actor          정확 일치. 권위. 후보 목록이 쓰는 것
attributedTo   집합 포함. "내 이름이 올라간 것". 권한이 아니다
```

### FilterCondition 형식은 ContactCard/query 를 따른다

Email/query 에는 타입 축이 없다(단일 타입). `ContactCard.kind` 가 우리
`activity_type` 과 같은 평평한 타입 축이다.

```txt
RFC 9610 §3.3.1                       (9610.txt:365-400)
  inAddressBook · uid · hasMember · kind · createdBefore/After · updatedBefore/After
  "If zero properties are specified ... MUST always evaluate to true.
   If multiple properties are specified, ALL must apply" (9610.txt:467-472)
```

필드 이름은 **이미 공개 필터로 광고된 `activity_type` 을 그대로 쓴다.** 같은 뜻에
이름이 둘이면 호출부가 갈린다. 빈 필터는 인증된 범위 안에서 전체를 뜻한다.

## 결정 ④ 페이지네이션은 두 계통이고 합치지 않는다

```txt
연합 outbox        커서. 나가 있는 next 링크를 바꾸지 않는다
관리 화면           숫자 페이지(offset). WP All Posts·Mastodon admin 과 같은 쪽
```

AP §5 는 삽입 순서의 예로 자동증가 정수 id 를 들지만 무엇으로 최신을 판단할지는
구현에 맡긴다. 우리는 `feed_sort_at DESC, id DESC`(`repository.php:1010`)다.
`id` 단독으로 바꾸면 단순해지지만 **두 순서가 갈리는 행에서 outbox 표시 순서가
바뀐다.** 지금 건드리지 않는다.

Mastodon·Lemmy 도 같은 분리를 한다 — 연합 outbox 는 ID 커서(`?page=true` 는
페이지 번호가 아니라 스위치), 관리 목록은 페이지 번호다. Lemmy 공식 UI 는
버튼이 이전/다음이지만 뒤로는 `page_cursor` 를 넘기고, Group outbox 는
`first`/`next` 없이 최근 n개만 담아 **전체 순회 API 가 아니다.** 즉 연합 outbox 를
순회용으로 쓰지 않는 것은 양쪽 다 같다.

**적용 순서가 요구사항이다.** 액터 범위 → 열람 권한 → `activity_type` → 그 결과에
페이지. 원장을 먼저 자르고 숨길 행을 빼면 페이지가 비고 총건수가 어긋난다.

### 관리 화면은 이미 원장을 직접 읽는다

```php
// activities/includes/admin-outbox.php:1
/** Private, read-only activity history for the selected local Actor. */
// :18-34  axismundi_act_admin_outbox_rows()
//   :20-23  axismundi_act_outbox_authorize() 를 질의 전에 통과시킨다
SELECT id, activity_type, object_uri, activity_uri, feed_sort_at, effective_status
WHERE actor_uri_hash=%s AND actor_uri=%s AND direction='outbound'
  AND (%s='' OR activity_type=%s)
  AND (%s='' OR (object_uri_hash=%s AND object_uri=%s))
  AND (날짜 범위) AND (id < before)
// :141-142  "Older activities" — id<before 하나뿐. 숫자 페이지·처음/마지막 없음
// :67-71    Collection 탭은 외부 HTTP 가 아니라 AP 핸들러를 내부에서 호출한다
```

권한과 필터가 질의 안에 있어 위 적용 순서가 구조적으로 지켜진다. 남은 작업은
`Activity history` 에 숫자 페이지와 처음/마지막을 붙이고 그 탭을 기본으로 두는
것이며, `Collection` 탭은 연합에 나가는 JSON 확인용으로 남긴다.
프론트앱에서 Lemmy 식 "한 페이지 교체" 는 커서로도 되고, 무한 스크롤이면
커서가 그대로 맞다.

## 측정 — outbox 가시성 계층

`op/includes/integrations/actors.php:663-685`. 요청당 1회 해석 후 액티비티별 판정.

```txt
소유자(로컬 세션)         전부              :675  if ($owner) return $payload;
공개                     공개 payload      :676-677  명단 스캔 전에 반환
로컬 뷰어 + 지명          해당 액티비티      :680  viewer_uri ∈ to|cc|bto|bcc|audience
로컬 뷰어 + 수락된 팔로워   해당 액티비티      :681  followers URI 가 명단에 있음
비인증 / 리모트 서명 GET   공개분만          :666  get_current_user_id() 필요
```

**컬렉션을 열거하지 않는다.** 멤버십은 `wp_ax_relations` 단일 행으로 답하고
(`relations.php:88-96`), 컬렉션 URI 는 문자열 조립일 뿐이며
(`actors.php:82-84`) 명단과 문자열 비교에만 쓰인다(`:681`). 팔로워가 1만이어도
비용은 관계 조회 1 + 비교다.

### bto/bcc 당사자는 열람하고, 비소유자 응답에서 명단을 제거한다

아래는 **비소유자 경로에만** 해당한다. 소유자는 `:675` 에서 원본 payload 를 그대로
먼저 반환하므로 bto/bcc 가 남는다 — 자기 발송의 수신자 명단이라 가려야 할 이유가 없다.

```php
// :679-684  (비소유자)
foreach ( array('to','cc','bto','bcc','audience') as $field ) { … $visible … }
unset( $payload['bto'], $payload['bcc'] );
if ( is_array( $payload['object'] ?? null ) ) { unset( $payload['object']['bto'], $payload['object']['bcc'] ); }
```

RFC 5322 §3.6.3 두 번째 방식과 같은 구조다 — bcc 당사자에게 **메시지는 주고**,
갈리는 것은 그 사본에 Bcc 줄을 넣느냐뿐이다(5322.txt:1306-1313). 우리는 넣지 않고,
그것이 AP §B.11 "servers should omit these properties during display as well" 과 맞는다.

보여야 하는 이유는 일관성이다. bcc 로 배달되면 수신자 inbox 에 이미 그 액티비티가
있다. outbox 에서만 숨기면 같은 사실에 두 답이 나오고, 보호되는 것도 없다.
숨겨야 하는 것은 **공동 수신자 명단**뿐이고(5322.txt:1300 "addresses ... not to be
revealed to other recipients"), `:683` 이 그것을 한다. 뷰어가 to/cc 가 빈 액티비티를
보고 자기가 bto/bcc 였음을 추론하는 것은 누출이 아니다.

## 결정 ⑤ soft deletion 은 채택하지 않는다

로컬객체의 `Delete` 는 영구삭제다. FEP-4f05 의 **hard delete 절은 이미 준수**하고
있으며(`fep-4f05.md:39` "the object MUST no longer have an ActivityPub representation",
410 이 "a more explicit signal"), soft delete 절을 **구현하지 않는 선택**이다.
비표준이 아니라 고르지 않은 선택지다.

```php
// op/includes/local-objects.php:197-199   현재 동작
$document = array( 'id' => …, 'type' => 'Tombstone', 'formerType' => …, 'deleted' => … );
$wpdb->update( …, array( 'object_type' => 'Tombstone', 'document_json' => $json, … ) );
```

**직전 상태 스냅샷이 우리 저장소에 없다**는 것이 이유다. 발신 원장은 참조만 남긴다.

```php
// activities/includes/c2s-local-object.php:82
$activity = array( 'type' => $type, 'actor' => …, 'object' => $uri );
// :111
// Match the existing local WP lifecycle convention: object URI, not a second authoritative body.
// :90  문서를 싣는 경우는 하나뿐
if ( $group && 'Group' === $group->get_type() ) { $activity['object'] = $document; }
//     "Group forwarding must preserve a creation snapshot, including after local deletion."
```

```txt
inbound    원격 payload 를 그대로 저장(axismundi_op_store_remote_object).
           S2S Update 가 complete 라서 스냅샷이 자연히 남는다
outbound   원장은 참조. 본문은 wp_ax_local_objects.document_json 한 곳이고
           Delete 가 그것을 Tombstone 으로 덮는다
```

FEP-4f05 도 본문을 주지 않는다 — `fep-4f05.md:90` "Implementors are free to handle a
soft deletion in the way they prefer". 연합 쪽 의미만 정의하고 보관 규칙은 구현자에게
맡긴다.

열려면 세 가지가 선행한다. 지금은 비용으로 판단해 열지 않는다.

```txt
① 보관 위치   (A) document_json 을 덮지 않고 Tombstone 을 투영 시점에 만든다
                  — "삭제했는데 서버에 본문이 남는다" 를 제품으로 약속하게 되고
                    개인정보용 hard delete 가 따로 필요해진다
              (B) Update·Delete 원장 payload 에 완전 문서를 싣는다
                  — :90 선례와 같은 모양이고 S2S complete 와 일치하나 원장이 커지며
                    :111 의 "second authoritative body" 금지와 정면으로 부딪친다
② soft/hard 구분을 명시값으로 저장
③ 410 ⊥ 200 분기   FEP-4f05 soft 는 SHOULD 200 + Tombstone 이고, 우리 로컬객체는
                  의도적으로 410 이다. 현재 한 경로이므로 그 결정을 다시 열어야 한다
```

## 결정 ⑥ outbox POST 는 입구 하나다 — 참조형과 신규 생성을 거기서 가른다

세 문장이 이 결정을 고정한다.

```txt
AP §6.2    "When a Create activity is posted, the actor of the activity SHOULD be
           copied onto the object's attributedTo field."
AP §6.2.1  "The server MUST accept a valid ActivityStreams object that isn't a
           subtype of Activity in the POST request to the outbox."
           "The server then MUST attach this object as the object of a Create Activity."
           "For non-transient objects, the server MUST attach an id to both the
           wrapping Create and its wrapped Object."
AS2 §3.1   { "type": "Create", "actor": "…/martin",
             "object": "http://example.org/foo.jpg" }     ← object 가 참조다
```

§6.2.1 은 "Create 없이 보내면 Create 가 없다" 는 뜻이 **아니다**. 서버가 반드시
Create 를 만들고 양쪽에 id 를 붙인다. 그 Create 의 actor 는 §6 의 인증 규칙상
outbox 소유자이고, §6.2 가 그 actor 를 `attributedTo` 로 복사한다. 즉 bare object
제출은 다른 존재론이 아니라 클라이언트 편의다.

### 분기는 `object` 자리에서 난다

```txt
참조형   object = 이미 있는 URI
         → 레지스트리가 소스를 해석하고(thread-edges.php:405
           axismundi_op_resolve_source_by_uri, 필터 기반), 소스 어댑터가 문서를
           공급한다. 결정 ① 의 일반형이다.
         → 참조된 객체에는 새 id 를 붙이지 않는다. §6.2.1 의 MUST 는 서버가
           만드는 객체에 대한 것이지 지명된 객체에 대한 것이 아니다.
신규     id 없는 Create(object) 또는 bare object
         → /objects/{uuid} 를 발급한다. 결정 ① 의 특수형(B)이다.
```

해석되지 않는 URI, 또는 principal 이 발행할 수 없는 소스는 **거부한다**. 지어내지
않는다.

### 신규 객체는 `wp_posts` 에 만들지 않는다 — 이유는 타입이 아니라 소유권이다

`type` 만으로 `post_type` 을 정할 수 없는 것은 맞다(`Article` 은 `post` 일 수도,
Forum 의 `topic` 일 수도 있다). 그러나 그것은 증상이고, 거기서 멈추면 "클라이언트가
`post_type` 을 같이 보내면 된다" 는 잘못된 해결이 열린다.

`wp_posts` 의 행은 제품이 소유한다. outbox 가 거기에 행을 만들면 OP/Activities 가
다른 제품의 저장소에 쓰는 writer 가 되고, 레지스트리가 URI→소스를 해석하는 이유
자체가 사라진다. 소유자가 정하는 것은 `post_type` 만이 아니다 — `post_author`(WP
사용자다), 분류, 편집 상태, 제품별 불변식이 전부 그쪽에 있고 C2S 제출물은 그중
아무것도 들고 오지 않는다. 제출한 액터가 WP 사용자에 대응되지 않을 수도 있다.

`/objects/{uuid}` 영역이 존재하는 이유가 이것이다. **outbox 가 스스로 소유하는
저장소.** 신규 생성은 자기 땅에만 하고, 남의 땅에 있는 것은 URI 로 지명해서 주인에게
문서를 받는다. 소스 어댑터가 Event 를 거부한 근거(coexistence guard)와 같은 모양이다.

### 네임스페이스 — 쪼개지 않는다

`PROTOCOL-ROUTE-NAMESPACES.md` 의 surface 기준을 그대로 적용한다. AS2 문서를
주고받고 프로토콜이 규율하면 AP 네임스페이스, 앱 조회 결과면 `axismundi/v1`
(`/hashtags/{id}/members` 가 이름과 달리 후자로 남은 선례).

결정적인 것은 **§6.2.1 의 bare object 와 AS2 §3.1 의 참조형이 스펙상 같은
엔드포인트**라는 점이다. 페이로드 모양에 따라 네임스페이스를 가르면 스펙이 하나로
정의한 입구를 둘로 쪼개는 것이고, 그것은 "AP 로 통합" 보다도 "axismundi API 로
분리" 보다도 나쁘다. 그러므로 쓰기 표면은 outbox 하나로 두고, 후보 목록 같은
조회는 `axismundi/v1` 에 남긴다(AS2 문서가 아니다).

AP C2S 자체로 얻는 상호호환은 거의 없다 — 클라이언트 생태계가 사실상 없다. 채택
근거는 외부 호환이 아니라 **입구가 하나라는 것**이다.

### 측정 — 지금 있는 것과 없는 것

```txt
있다   컴포저의 새 Note       outbox 로 실제 POST, bare object → 서버가 Create 로 감쌈
있다   참조형 Core WP 글      outbox 가 { type: Create, object: "<uri>" } 를 받아 기존 글 발행 명령을 호출
```

All Posts 의 `Submit Create` 는 **outbox POST 가 아니다**. `submit_c2s()` 가 받는
것은 AS2 문서가 아니라 로컬 명령이고(`operation` / `post_id` / `expected_version`),
Minimal Activity 모양은 제출물이 아니라 원장에 기록될 때 만들어진다. 여기서 "C2S" 는
서비스 이름이지 ActivityPub 의 C2S 와이어가 아니다 — "이미 C2S 로 동작한다" 고 읽지
말 것. Outbox 는 프로토콜 어댑터이며 어드민은 서비스를 직접 호출한다.

참조형 요청에는 클라이언트 버전 선행조건이 없다. 서버가 읽은 글의 버전을 명령에 넘겨
요청 처리 중 변경을 검사하되, 사용자가 이전에 본 버전은 보장하지 않는다. 어드민은
기존 폼의 버전 검사를 유지한다.

[ActivityPub §6](https://www.w3.org/TR/activitypub/#client-to-server-interactions)은
outbox 제출 성공에 `201 Created`를 요구하고, 비일시적 활동의 `Location`에
서버가 발급한 활동 ID 를 싣도록 한다. 아래는 표준 요구가 아니라 Axismundi 정책이다.

- **LOCAL_POLICY: 멱등 재제출.** 권한과 삭제 이력을 확인한 뒤, 같은 액터가 같은 URI 를
  재제출하면 키 없이도 최초 outbound Create(`id ASC`)를 `200`과 기존
  `Location`으로 반환한다. 새 활동이나 Update 를 기록하지 않으므로, 새 리소스가
  생겼다고 응답하는 대신 §6 의 `201` 요구에서 명시적으로 벗어난다.
- **LOCAL_POLICY: 주소 필드 거부.** 참조형 요청의 수신자는 기존 글 설정과 멘션
  해석으로만 계산한다. `to`, `cc`, `bto`, `bcc`, `audience`가 있으면
  거부한다. [주소 복사 프리머](https://www.w3.org/wiki/ActivityPub/Primer/Copying_addresses_between_Activity_and_object)는
  인라인 객체를 다루며 이 참조형 정책을 규정하지 않는다.
- **LOCAL_POLICY: 액터 명시.** 참조형 Create 의 `actor`는 필수이며 인증된 outbox
  소유 액터와 일치해야 한다. ActivityPub §6 의 제출 형식 요구에 이 검사는 없다.

§6.2 의 복사는 구현돼 있다. `activities/includes/post-attribution.php:18`
`axismundi_act_store_recorded_post_attribution()` 이 **최초 Create 한 번만**
actor 를 postmeta 로 쓴다. 권위 동결 이후 `get_object_attribution()` 도 같은 원장
값을 읽으므로 postmeta 는 같은 값의 캐시이고, 둘은 갈릴 수 없다.

## 미결

```txt
authorized fetch     리모트 서명 GET 에 공개분만 준다(:666 이 로컬 세션을 요구).
                     수락된 리모트 팔로워가 followers-only 글을 outbox 조회로
                     가져갈 수 없다(배달로는 받는다). 서명 GET 을 신뢰할지는
                     보안 결정이고 Mastodon 도 인스턴스 설정으로 둔다.
                     지금 상태를 적어 두는 이유는 "팔로워인데 왜 안 보이지"를
                     나중에 버그로 집지 않기 위해서다.
following 계층        발신·열람 양쪽 미구현. 열람 판정 비용은 followers 와 같다
                     (relations 의 subject/object 를 뒤집으면 된다). 비싼 쪽은
                     발신 프리셋(audience.php:86 에 그 visibility 가 없다)과
                     상호호환이다 — Mastodon·Misskey 에 following-only 가시성이
                     없어 수신측이 그것을 "팔로워에게"로 읽지 않는다.
                     following 컬렉션을 audience 로 쓰면 "누구에게 보냈나"가 그
                     컬렉션의 공개 설정과 묶인다. liked 가 별도 가시성을 갖는 것과
                     같은 문제이므로 그 정책을 먼저 정해야 한다.
확장 불가 컬렉션       리모트 Group members 등 우리가 권위를 갖지 않는 컬렉션은
                     역참조가 필요하다(요청당 왕복 + SSRF 면). 의도적 미지원.
Update 타입 확대      Article·Video 를 C2S Update 로 수정하는 계약. 위 ② 참조.
```

## 검증 명령

이 문서는 결정 기록이며 그 자체로 검사되지 않는다. 구현 시 아래를 돌린다.

```txt
activities/tests/audit-c2s-outbox-http.php     실제 HTTP 제출·idempotency·302
activities/tests/audit-c2s-create-note.php     Create(Note) 경로
activities/tests/audit-questions.php           원장 투영 패턴
op/tests/audit-actor-outbox.php                outbox 컬렉션 회귀
op/tests/audit-protocol-routes.php             네임스페이스 경계
```
