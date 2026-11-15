# Brief — `is_deleted` 술어 (로컬 객체 한정)

Tombstone 슬라이스가 닫히면서 `object_status` 를 읽는 자리가 늘었다. 이 슬라이스는 **읽는
자리를 하나로 모으는 것뿐**이고, 저장 값은 건드리지 않는다. `active | tombstone` →
`active | deleted` 개명은 마이그레이션이 필요한 **별건**이다.

## 먼저 측정한 것 — 술어는 전역이 아니다

`object_status` 는 한 컬럼이 아니라 **네 테이블의 컬럼**이고 소유자가 다르다.

```txt
ax_notes                      varchar(16)  axismundi-note 소유.  envelope 생명주기
ax_op_local_objects           varchar(12)  OP 소유.  이 슬라이스의 대상
ax_op_remote_objects          varchar(12)  OP 소유.  수신 관찰 결과
ax_op_object_listing(사영)     varchar(12)  OP 소유.  목록 질의용 복제
```

로컬의 `tombstone` 은 **우리가 기록한 Delete** 이고, 원격의 `tombstone` 은 **상대가 그렇다고
알려준 것**이다. 같은 문자열이지만 권위가 다르다. 술어 하나로 네 곳을 쓸면 그 구분이 사라진다.

**이 슬라이스는 `ax_op_local_objects` 만 손댄다.** 나머지 셋은 그대로 둔다.

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
미확인          activities/admin-workflows.php 179, activities/community-replies.php 99,
                activities/community-posts.php 84 ($local 분기), activities/like-block.php 19,
                actors/featured.php 92 187 189, op/object-read-api.php 150
```

**미확인 줄은 추측하지 말고 행의 출처 함수를 따라가 판정하고, 판정 결과를 이 표에 적는다.**
`like-block.php:19` 은 변수명이 `$remote` 라 원격으로 보이지만 확인은 필요하다.

### ③ 감사 — 소스 스캔이고, 그 스캔을 먼저 깨 본다

통과만으로는 **누락을 못 잡는다.** 단정은 "로컬 행을 읽는 파일에서 `'tombstone'` 과
`'active'` 리터럴 비교가 술어 밖에 남아 있지 않다" 다. 구현 파일 목록은 ②의 로컬 목록이다.

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
