# Brief — `is_deleted` 술어 (로컬 객체 한정)

Tombstone 슬라이스가 닫히면서 `object_status` 를 읽는 자리가 늘었다. 이 슬라이스는 **읽는
자리를 하나로 모으는 것뿐**이고, 저장 값은 건드리지 않는다. `active | tombstone` →
`active | deleted` 개명은 마이그레이션이 필요한 **별건**이다.

## 먼저 측정한 것 — 술어는 전역이 아니다

`object_status` 는 한 컬럼이 아니라 **네 테이블의 컬럼**이고 소유자가 다르며, 그중 하나는 사영이다.

```txt
ax_op_local_objects   varchar(12)  OP 소유.  **이 슬라이스의 대상**
ax_remote_objects     varchar(12)  OP 소유.  수신 관찰 결과
ax_object_index       varchar(12)  OP 소유.  조인용 사영 — 아래
ax_notes              varchar(16)  axismundi-note.  **컷오프 대상이라 손대지 않는다**
```

로컬의 `tombstone` 은 **우리가 기록한 Delete** 이고, 원격의 `tombstone` 은 **상대가 그렇다고
알려준 것**이다. 같은 문자열이지만 권위가 다르다. 술어 하나로 네 곳을 쓸면 그 구분이 사라진다.

**이 슬라이스는 `ax_op_local_objects` 만 손댄다.** 나머지 셋은 그대로 둔다.

### `ax_object_index` 는 원장의 중복이 아니다 — 측정함

권위는 `ax_activities` 다. 사영은 **원장이 혼자 못 하는 조인의 반대쪽**이다.

```txt
ax_activities     활동 축.  "어떤 활동이 있었나"              ← 권위
ax_object_index   객체 축.  10컬럼, 페이로드 없음, 재생성 가능  ← 조인 대상
```

피드 질의(`activities/repository.php:1084`)가 한 SQL 안에서 객체 쪽 사실을 WHERE 에 쓴다 —
`publicly_listable` · `attributed_to_uri_hash` · `has_group_context` · `is_reply`. 그 사실은
**로컬 테이블과 원격 테이블 둘로 나뉘어** 있어서, 원장 행을 "둘 중 어느 쪽인지 모르는 테이블"
에 인덱스 타고 조인할 수 없다. URI 해시로 묶은 조인 대상이 그래서 필요하고, `source` 가 어느
저장소에서 왔는지를 적는다.

원장에 컬럼을 붙이면 안 되는 이유도 같은 자리에서 나온다 — 객체 하나에 활동이 여럿이다
(Create + Announce N개). 비정규화하면 활동마다 복제되고 객체 가시성이 바뀔 때 전부 낡는다.

권위 관계는 코드에 이미 적혀 있다 (`admin-publishing-filters.php:293`):

> A missing or zero projection is not proof of non-publication. Check the ledger by URI.

**사영의 `object_status` 는 건드리지 않는다.** 사영 행은 원본에서 파생되므로, 술어는 원본을
읽는 자리에만 들어간다.

## 이름

```txt
쓸 것     axismundi_op_local_object_is_deleted( array $row ) : bool
쓰지 말 것 이름에 object_status 를 넣는 것 — axismundi_op_object_status_html() 이
          이미 **카드 표시** 개념으로 선점했다 (object-blocks.php:248)
```

전역 함수 추가 전에 **정확한 함수명으로 grep** 한다. 2026-11-15 확인 시점에는 비어 있었다.

### `is_active` 를 쌍으로 만들지 말 것

지금 값이 둘이라 `! is_deleted()` 가 "활성" 과 같지만, **세 번째 값이 들어오는 순간 조용히
틀린다** — `draft` 가 추가되면 `! is_deleted()` 는 "활성 또는 초안" 이 된다. 술어는 자기가
단정하는 사실의 이름을 갖는다. 활성을 묻는 자리가 필요하면 그때 별도 술어를 만든다.

## 작업

```txt
① 술어 추가            local-objects.php.  행 배열을 받고 bool 을 낸다
② 로컬 읽기만 교체      아래 목록.  **표를 grep 보다 먼저 고정한다**
③ 감사                 누락이 진짜 위험이므로 소스 스캔으로 단정한다
```

### ② 교체 대상 — 테이블을 확인한 것

```txt
확인됨: 로컬    op/local-objects.php 220 243 269 276 284 317
                op/application-object-read.php 47 52 68 77 93   ← $local 분기 안쪽만
                op/activitystreams-api.php 81
                op/local-object-route.php 14
                op/renderer.php 384
                op/replies-collection.php 58
                op/integrations/actors.php 314 573
                op/admin.php 114 126 133 158 429
                activities/c2s-local-object.php 92 129
                activities/c2s-outbox.php 201 205
                media-library/relations.php 288
확인됨: 원격    op/object-view-route.php 111 114    ← 건드리지 말 것 (캐시 행)
                op/hashtags.php 300, op/thread-edges.php 350 509,
                op/remote-objects.php 전부, op/mentions.php 131,
                op/inbox-observations.php 58, op/tag-references.php 238 240
추가 확인: 로컬  activities/admin-workflows.php 179, activities/community-replies.php 99,
                activities/community-posts.php 84 ($local 분기), actors/featured.php 92 ($local)
추가 확인: 원격  activities/like-block.php 19, actors/featured.php 187 189 ($cached),
                op/object-read-api.php 150 (Axismundi_Op_Remote_Source)
```

로컬 행은 `axismundi_op_get_local_object()`에서, 원격 행은
`axismundi_op_get_remote_object()` 또는 이를 감싼 원격 소스에서 온다.
명시적인 활성 요구, SQL 조건 및 표시값은 그대로 둔다.

### ③ 감사 — 소스 스캔이고, 그 스캔을 먼저 깨 본다

통과만으로는 **누락을 못 잡는다.** `audit-local-object-is-deleted.php`는 확인한 로컬 소비
파일에서 `object_status`와 `'tombstone'`을 함께 쓰는 직접 판정을 찾는다.
술어 본체, SQL 갱신과 확인된 원격 캐시 분기는 제외한다.

```txt
필수   known-positive 로 먼저 깨 본다 — 교체한 자리 하나를 리터럴 비교로 되돌리고
       스캔이 **실패하는지** 본다.  실패하지 않으면 판정식이 결함이다
금지   리터럴을 세는 방식으로 통과 기준을 만드는 것.  개수는 다음 편집에서 바뀐다
```

기존 감사도 같이 돌린다 — `audit-c2s-create-note.php` 는 `AX_C2S_HTTP_ORIGIN=http://wordpress`
없이 돌리면 canonical 단정 4개가 빠지고 `SKIPPED` 를 찍는다. 79 와 75 를 혼동하지 말 것.

## 바꾸지 말 것

```txt
저장 값        'active' / 'tombstone' 문자열.  개명은 별건이고 마이그레이션이 필요하다
원격·envelope  권위가 다르다.  같은 술어를 쓰면 그 구분이 사라진다
스키마          컬럼·인덱스·varchar 길이
```
