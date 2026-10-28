# Local Object Store / Create(Note)

2026-10-29 설계안. Create(Note)와 Delete의 구현 현황은
[구현 기록](IMPLEMENTATION-C2S-CREATE-NOTE.md)을 따른다. Question 이전 등 아래의
후속 완료선이 모두 구현됐다는 뜻은 아니다.
이번 문서는 현재 저장소 인벤토리와 새 로컬 본문 저장소의 계약을 잡는다.
Activity 원장의 보존·축약·증거 보관 정책은 이번 범위에서 결정하거나 변경하지 않는다.

## 1. 현재 저장소

아래는 wp-env에서 읽기 전용으로 측정한 시점 값이다. 영구적인 행 수나 정리 대상 목록이 아니다.

| 테이블 | 행 수 | 역할 |
|---|---:|---|
| ax_objects | 2,106 | OP Object 식별·출처 앵커. 본문이나 관계 테이블이 아님 |
| ax_object_index | 1,103 | 로컬·원격 Object의 재구성 가능한 목록 투영 |
| ax_remote_objects | 113 | 원격 본문 관찰·캐시, 갱신·실패·TTL 상태 |
| ax_object_relations | 445 | reply·quote 등 Object 간 URI 관계와 근거 |
| ax_thread_edges | 1,466 | 스레드 탐색용 투영 |
| ax_object_mentions | 340 | Object → Actor 멘션 근거 |
| ax_object_leases | 299 | 원격 캐시 사용·보존 임차 |
| ax_remote_object_hashtags | 0 | 원격 관찰 해시태그 투영 |
| ax_tag_references | 138 | 태그 참조 |
| ax_term_usage | 7 | 용어 사용 투영 |
| ax_notes | 816 | 폐기 예정 Note CPT의 별도 envelope. OP 소유 테이블은 아님 |
| ax_activity_questions | 15 | 현재 Activities의 poll 문서·상태 |
| ax_activity_question_votes | 0 | 현재 Activities의 투표 판정 투영 |

`ax_object_*`를 "로컬 전용"으로 읽으면 안 된다. 공통 URI 관계와 목록 투영은 원격에도 쓰인다.
`ax_remote_objects`만 원격 본문 캐시에 특화되어 있다.

`ax_objects`의 출처별 행 수: wordpress-core/post 1,004, wordpress-core/page 2,
axismundi-note/note-post 213, remote-object-cache 831, legacy-registry/unbound 56.
원격 본문 캐시 113행과 캐시 출처 앵커 831행이 같지 않다고 곧바로 고아로 판정하지 않는다.
본문 캐시와 영속 URI 앵커는 수명이 다르다. unbound 앵커도 근거 없이 제거하지 않는다.

Note envelope 816행 중 실제 WP Post가 있는 것은 publish 66, draft 3이다.
Post가 없는 747행은 전부 object_status=tombstone이다. 이들을 단순 테스트 쓰레기로
분류하지 않는다. 다만 아래 오너 결정으로 이 행들은 새 저장소의 이전 모집단에서 제외한다.
이번 설계 변경 자체가 기존 DB 행을 일괄 삭제하는 작업은 아니다.

### 기존 Note 하드 컷오프 (오너 결정, 2026-10-29)

기존 ax_note CPT/envelope는 새 로컬 객체 저장소로 이전하지 않는다. 구 Note URI·UUID를
새 저장소에 계승하거나 호환 redirect/alias/read fallback을 만들지 않는다. 기존 Note URL의
서비스 중단은 이 결정으로 승인된 범위다. 퇴역할 producer·route·source reader·작업 큐는
구체적인 구현 인벤토리로 확인하고, 구 경로가 객체를 자동 재생성하지 않게 끊는다.

신규 로컬 객체의 canonical URI는 `/objects/{uuid}`다. Note·내장 Question·투표 Note가
같은 경로를 사용하고, 타입 변경 또는 Tombstone 전환 후에도 이 URI는 바꾸지 않는다.
Activity는 `/activities/{uuid}`, Actor는 `/actors/{uuid}`로 구분한다.

### Canonical ID와 URL

이번 신규 OP 객체는 `id`와 `url`을 모두 `/objects/{uuid}`로 둔다. 같은 주소에서 AP JSON과
사람용 HTML을 content negotiation으로 제공하고, 표현별 cache의 Accept 구분을 검증한다.
별도 `/notes/...`, `/tombstone/...`, 숫자 ID 쿼리 경로는 추가하지 않는다. Tombstone도 같은
주소를 점유한다. url용 SQL 컬럼은 만들지 않고 기본값을 canonical id에서 계산한다.

WP 객체의 id는 기존 source resolver가 정하는 쿼리형 URI를 그대로 사용한다. 예를 들어
`/?p={post_id}`인 객체를 `/objects/{uuid}`로 바꾸지 않는다. 기존 WP permalink와 Object.url도
그 source의 계약을 유지한다. 이 신규 URL 결정은 WP 및 원격 객체에 소급 적용하지 않는다.

| 출처 | canonical id | 공통 lookup |
|---|---|---|
| WP Post | 기존 WP source의 쿼리형 URI | exact canonical URI/hash 및 owner/kind/ref |
| OP 로컬 본문 | /objects/{local_uuid} | 같은 lookup, source_ref는 로컬 본문 PK |
| 원격 관찰 | 원격 서버의 원래 id | 같은 lookup, 로컬 경로로 신원을 대체하지 않음 |

컷오프를 이유로 기존 Activity 원장이나 일반 WP Post·미디어 바이너리를 삭제하지 않는다.
구 Note 데이터·source 앵커·파생 관계의 물리 정리는 서비스 경로 퇴역과 별도 단계로 다루며,
새 런타임에 이행 전용 분기나 구 Note dual-write를 남기지 않는다. 이 승인은 다른 플러그인의
구 버전 DB 이전 경로나 원격 Question 데이터를 일괄 폐기하라는 뜻이 아니다.

Question 15행은 모두 remote이며 URI가 있다. 같은 URI의 object_uri를 가진 Create 원장
행은 15개 모두 없다. **"원장 payload에서 전부 복원 가능"은 현재 DB에서 성립하지 않는다.**
기존 문서·원격 관찰의 이전 근거를 확보하기 전에는 테이블을 DROP하지 않는다.

## 2. 기존 두 인덱스는 당장 합치지 않는다

`ax_objects`는 exact canonical URI/hash, UUID, 출처 owner/kind/ref를 보존하는 영속 앵커다.
`ax_object_index`는 공개 목록 노출, 답글 여부, Group 맥락 등 조회에 필요한 파생 사실이다.
후자의 source/status/귀속 hash가 전자와 겹쳐도 권위가 둘이라는 뜻은 아니다.

- Object 식별·출처 lookup: ax_objects.
- 본문과 현재 읽기 정책: 해당 source 저장소.
- 목록용 accelerator: ax_object_index. 이것만으로 객체의 읽기를 허용하지 않는다.
- Actor 식별: Actors. Object ID와 Actor ID의 숫자 일치를 가정하지 않는다.

WP source를 공통 ax_objects/목록 투영에 신규 등록하는 조건은 **검증된 Create와 저장된
attributedTo**다. 두 URI가 같은 객체를 가리키고 Create의 Actor와 귀속이 일치해야 한다.
post_author·현재 선택·publish 상태만으로 등록하거나 attribution을 발명하지 않는다.
귀속이 없는 WP URL은 기존 WP source 정책대로 계속 읽을 수 있지만 공통 발행 인덱스에
등록하지 않는다. Delete 이후에도 기존 식별 앵커는 보존하고 소스의 삭제·읽기 상태를 반영한다.
이 조건을 원격 fetch 관찰까지 확대하여 "수신 Create가 없으면 원격 캐시 등록 금지"로 만들지 않는다.

현재 구현은 `save_post:100`에서 `register_local_post_object()`를 호출하므로 위 조건과
아직 같지 않다. 구현 단계에서 Create 확정과 WP source 갱신의 등록 경계를 조정하고,
기존 registry 행은 근거별로 분류한다. 이번 설계 수정에서 기존 행을 일괄 삭제하지 않는다.

새 로컬 source의 목록 투영은 본문에서 다시 만들어 결과를 비교하는 감사를 붙인다.
기존 전체 registry를 삭제·재생성하는 작업은 아니다. 특히 unbound 앵커의 의미를 보존한다.

`object-registry.php`의 ax_identities 참조는 `ax_op_object_identifiers_migrated` 가드 뒤의
이전 코드다. 현재 옵션은 1이며 legacy 테이블 부재 분기도 있다. 이름이 남았다는 이유만으로
실행 중인 결함 또는 죽은 코드라고 단정하지 않는다. 이전 버전 DB에서의 복구·업그레이드
경로를 별도로 검증한 뒤 퇴역한다. ax_activity_questions 직접 참조도 이 이전 경로에 있다.

## 3. 새 본문 테이블 제안

이름은 **ax_local_objects**로 한다. ax_objects와 ax_object라는 단복수 한 글자 차이를
만들지 않는다. 신규 C2S 객체 원본을 저장하고 wp_posts나 원격 캐시의 본문은 복제하지 않는다.

| 컬럼 | SQL 타입 | 계약 |
|---|---|---|
| id | bigint unsigned AUTO_INCREMENT | 이 본문 source의 PK. Actor/Activity/Post ID가 아님 |
| local_uuid | char(36) NOT NULL UNIQUE | 신규 `/objects/{uuid}` URI 생성 토큰 |
| object_uri | text NOT NULL | 공개 정체성. 한번 정하면 변경하지 않음 |
| object_uri_hash | char(64) NOT NULL UNIQUE | exact URI 대조를 동반하는 lookup 키 |
| object_type | varchar(64) NOT NULL | 현재 문서 타입. 삭제 후 Tombstone |
| object_status | varchar(12) NOT NULL | active / tombstone. internal이나 WP post_status가 아님 |
| attributed_to_uri | text NOT NULL | 최초 발행 Actor의 영속 귀속 URI |
| attributed_to_uri_hash | char(64) NOT NULL | Actor별 객체 조회 |
| document_json | longtext NOT NULL | 현재 정규화된 Object 문서의 권위. 임의의 원본 POST 덤프가 아님 |
| document_hash | char(64) NOT NULL | 현재 문서 정규화 바이트의 변경 검출. 진위·서명 증명은 아님 |
| version | bigint unsigned NOT NULL | CAS용 단조 증가 버전. 과거 문서 이력이 아님 |
| publicly_readable | tinyint(1) NOT NULL | 읽기 공개 범위의 최소 판정. 삭제 시 공개 범위를 넓히지 않도록 보존 |
| published_at | datetime NOT NULL | 최초 발행 시각, UTC |
| created_at | datetime NOT NULL | 로컬 저장 시각, UTC |
| updated_at | datetime NOT NULL | 현재 로컬 버전 시각, UTC |
| deleted_at | datetime NULL | Tombstone 전환 시각, UTC |

조회 인덱스는 우선 `(attributed_to_uri_hash,object_status,id)`로 제한한다.
관계·멘션·태그마다 본문 테이블에 scalar 컬럼을 늘리지 않는다. 별도 search/full-text 인덱스는
실제 검색 계약이 생길 때 정한다. content/name/summary의 DB 컬럼과 JSON 권위를 이중으로 두지 않는다.

`source_owner=axismundi-object-projections`, `source_kind=local-object`, `source_ref=id`로
ax_objects와 연결한다. 이 id가 registry의 id와 같다고 가정하지 않는다. 새 범용 부모 테이블이나
Actor FK를 만들지 않는다. 같은 Object의 canonical URI는 두 저장소에서 정확히 일치해야 한다.
신규 URI는 OP가 한 번 발급한다. 기존 Note UUID/URI 이전 분기는 만들지 않는다.
registry UUID와 source local_uuid는 역할이 다르므로 숫자 ID나 UUID의 우연한 일치에 의존하지 않는다.

## 4. 문서와 관계의 경계

문서에는 content/contentMap, name/nameMap, summary/summaryMap, mediaType, addressing,
sensitive, inReplyTo, context, tag, attachment, quote 관련 등록 속성 및 확장을 보존한다.
언어맵·복수 값·내장 descriptor를 첫 값 하나로 축약하지 않는다. 로컬 저작 HTML은 안전한
입력 계약으로 정규화하고 렌더링 시에도 OP의 공통 escape/sanitize 계약을 거친다.

- inReplyTo·quote: 기존 ax_object_relations와 스레드 투영에 연결. 본문 JSON이 원본이다.
- mention·hashtag: 기존 URI·태그 투영을 재사용. Hashtag 타입을 새로 등록하지 않는다.
- attachment: AS2 descriptor는 본문에 둔다. 로컬 미디어 참조는 Media Library 사용 권한을
  검사하고, 기존 Post ID 전용 subject를 local-object source로 확장해야 한다.
- likes·shares·emoji reactions: 본문에 카운터 권위를 만들지 않는다. Activities가 제공한다.
- comment/commentmeta: 신규 Note 답글을 그쪽에 저장하지 않는다. inReplyTo를 가진 새 Object다.
- WP 편집기의 Article·CPT: 기존 source를 유지한다. C2S Note를 CPT에 우회 저장하지 않는다.

알 수 없는 확장의 무손실 보존과 그 확장을 실행할 권한은 별개다. 어휘 등록만으로 서버 효과를
열지 않는다. 여러 관계 값을 저장할 수 있어도 기존 모든 소비자의 복수 값 지원 완료를 주장하지 않는다.

## 5. Create(Note) 쓰기 경계

1. 기존 outbox 인가를 통과한 뒤 Create(Note) 또는 bare Note를 명시적으로 지원하는 handler로 보낸다.
   bare Note는 Create로 감싼다. 임시 wp_posts 행은 만들지 않는다.
2. Actor 귀속·audience·본문·참조·첨부 권한과 요청 크기를 정규화한다. 클라이언트 ID를 서버
   identity로 채택하지 않는다. C2S Like의 활성 상태 멱등성과 객체 생성 재시도를 혼동하지 않는다.
3. 트랜잭션 전에 Activity URI와 Object URI를 각각 예약한다. URI를 만들었다고 발행한 것은 아니다.
4. Activities의 `record_source_activity(..., before_commit)`가 원장 트랜잭션을 소유한다.
   OP participant는 같은 DB 연결에서 본문·registry·필수 관계·목록 투영을 쓴다.
   participant가 START TRANSACTION/COMMIT 또는 DDL을 호출하면 안 된다.
5. 어느 단계든 실패하면 원장과 객체 쓰기를 함께 rollback한다. 성공 후에만 recorded hook과
   전달 큐가 관찰하고 `201 + Location(Create URI)`를 반환한다. Object id는 Create에서 해소 가능해야 한다.

이 경계에는 재시도에서 중복 Object를 만들지 않는 결과 매핑도 포함한다. 현재 Like adapter의
허용 타입 목록에 Create만 추가하는 식으로 열지 않는다. 현재 post-commit Question 투영기나
remote cache writer의 내부 COMMIT을 이 participant에서 재사용하지 않는다.

**원장 payload를 URI 참조로 축약할지, 내장 문서를 보존할지는 이번 설계에서 확정하지 않는다.**
Activity writer가 받을 표현과 S2S renderer가 내장할 문서의 계약은 Create 구현 단계에서
별도로 연결하되, 기존 원장 내용은 변경하지 않는다. AS2 타입 또는 ID 발급만으로
본문 저장·보존 정책이 자동 결정되는 것은 아니다.

## 6. Question과 삭제

독립 Question은 Activities 원장의 AS2 Activity다. Create(Question)의 내장 문서는 같은
OP 로컬 본문 저장소를 쓰는 다음 소비자이며, 투표 Note도 inReplyTo/name을 가진 같은 Note다.
두 입력을 자동으로 같은 poll 처리로 합치지 않는다.

오너가 정한 방향은 ax_activity_questions와 ax_activity_question_votes의 퇴역이다.
다만 본문·원격 관찰·투표 수락/집계의 대체 경로가 존재하기 전에 테이블을 제거하지 않는다.
15개의 원격 Question은 기존 관찰 자체를 보존해 remote cache 또는 명시적 이전 보관본으로
옮길지 개별 검증해야 한다. 새로운 과거 Create를 발명하지 않는다. votes=0은 재구성 알고리즘의
정확성 증명이 아니다. 선택지 변경·마감·Undo·삭제·중복 투표를 검증한 뒤 퇴역한다.

로컬 Object Delete는 행과 URI를 남기고 privacy-minimal Tombstone으로 전환한다.
본문 document_json은 id/type/formerType/deleted의 최소 문서로 대체하고 첨부·CW·본문 검색
투영을 정리한다. 버전별 본문이나 object history collection은 이번에 만들지 않는다.
개인 파일이 다른 객체에서도 쓰일 수 있으므로 attachment 링크 제거와 미디어 바이너리 삭제를
같은 것으로 취급하지 않는다. reply 관계의 최소 위치는 스레드 Tombstone 표시를 위해 보존한다.

기존 비공개 객체가 삭제 후 공개 410으로 존재를 새로 드러내지 않게 publicly_readable을
보존한다. 비공개 Object의 세부 read-authority 계약은 별도 구현이 필요하며, 그 전에는 공개
읽기에서 fail-closed한다. 원장 보존·증거 보관·과거 payload 수정은 이 Tombstone 설계에 포함하지 않는다.

신규 `/objects/{uuid}`의 삭제 응답은 **410 Gone**으로 한다 (오너 결정, 2026-10-29).
AP JSON은 privacy-minimal Tombstone, 사람용 HTML은 삭제된 객체 표현을 같은 410 상태로
제공한다. 기존 WP source에서 쓰던 HTML 404 예외를 이 전용 라우트에 옮기지 않는다.
이 결정은 WP 객체의 현재 HTTP 응답을 일괄 변경하라는 뜻이 아니다.
상태 코드 선택보다 읽기 권한 판정이 먼저다. 공개되지 않았던 객체의 존재를 410으로 새로
노출하지 않으며, 접근 권한을 확인할 수 없는 요청은 fail-closed 404로 처리한다.
처음부터 존재하지 않는 UUID도 404다. 기존 Note 경로의 모든 생존·삭제 유형을 전수 검증한
것으로 간주하지 않는다. 하드 컷오프는 앞 절의 명시적 오너 승인에 근거한다.

## 7. 구현 순서와 완료선

1. 로컬 본문 schema/repository와 CAS·Tombstone 단위 검증. 아직 Create 입구를 열지 않는다.
2. OP source reader·공통 assembler·정확한 URI 읽기·기존 관계/태그/멘션/미디어 adapter를 연결한다.
3. Create(Note) + bare Note handler를 원장 participant와 묶는다. 실제 HTTP 재시도와 rollback을 검증한다.
4. 새 경로의 본문·언어맵·audience·관계·첨부·Tombstone을 검증한 뒤 기존 Note CPT의
   producer·route·source adapter를 하드 컷오프한다. 데이터 이전·구 URI 호환은 구현하지 않는다.
5. 구 Note 자동 생성·dual-write·fallback·예약 작업이 남지 않았는지 검사한다.
   Question/투표 테이블 퇴역은 그 대체 경로 검증과 별도의 전환 경계로 처리한다.

검증에는 두 ID의 독립 발급, Object 저장 실패/registry 실패/관계 실패 시 원장도 rollback,
동시 재시도에서 Object·Create 각 하나, URI 불변, 버전 충돌, 비공개 Object의 익명 읽기 거부,
Tombstone의 본문·첨부·과거 버전 미노출, 공개 삭제 객체의 AP/HTML 410, 미존재 UUID와
비공개 객체 존재 비노출의 404, 목록 인덱스 재구성 동치를 포함한다.
신규 URI의 문서·관계·작성자·권한 판정과 구 Note 경로의 퇴역을 검사한다. 하드 컷오프
대상이 아닌 기존 WP·원격 Object의 읽기와 관계는 회귀 검증한다.
현재 기록은 설계이며 이 검증을 이미 통과했다는 뜻이 아니다.
