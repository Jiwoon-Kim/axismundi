# BRIEF — activitystreams API 첫 슬라이스: 미발행 로컬 객체 편집

근거와 경계는 `DESIGN-AS2-API.md` 에 있다. 이 문서는 **무엇을 바꾸고 무엇을 바꾸지
않는지**만 적는다.

## 이 슬라이스가 닫는 구멍

지금 업로드한 `Video` 를 발행 전에 고칠 방법이 없다. outbox `Update` 는
`ax_c2s_update_unpublished` 로 거부하고(맞는 거부다 — 원격이 본 적 없는 객체에 "수정됐다"
를 기록할 수 없다), 다른 경로가 없다. **그 거부는 지금 대안 없는 막다른 길이고, 이 슬라이스가
반대편을 만든다.**

막다른 길이 하나 더 있다. `to: Public` 없이 올린 `Video` 는 `publicly_readable=0` 이라
참조형 `Create` 가 `ax_c2s_reference_projection` 으로 거부한다. 주소를 나중에 붙일 자리가
없어서 **영구히 발행 불가**다. 이 API 가 그 자리다.

## 두 표면은 상호 배타다

```txt
미발행(Create 없음)   activitystreams API 가 편집한다.  활동이 생기지 않는다
발행됨(Create 있음)   outbox Update 가 받는다.  Update 활동이 생기고 배달된다
```

거부도 서로의 거울이다. outbox 는 미발행을 거부하고(`ax_c2s_update_unpublished`), 이 API 는
**발행된 객체를 거부한다**. 둘 다 "여기가 네 자리가 아니다" 이지 "할 수 없다" 가 아니므로,
거부 메시지가 다른 표면을 가리켜야 한다.

## 범위

```txt
안에 있다   GET·PATCH  axismundi/activitystreams/local-objects/{uuid}
            GET        axismundi/activitystreams/local-objects/types   (발견)
            미발행 로컬 객체만.  Note 와 Video (axismundi_op_local_update_types())
            주소(to·cc·bto·bcc·audience) 편집 가능 — 미발행이므로 배달된 적이 없다
밖에 있다   발행된 객체 — outbox Update 가 받는다
            wp_posts · 원격 캐시 · 액터 원장 — 이 API 가 건드리지 않는다
            액티비티 원장 조회 — 나중에 별도 읽기 capability
            Session capability 광고 — 클라이언트가 URL 을 조립하지 않고 발견해야 할 때
            컬렉션 POST(생성) — 생성은 uploadMedia 와 outbox 가 이미 한다
            UI — 편집기는 별건이다
```

## 현재 (실측 2026-11-15)

```txt
local-objects.php
  axismundi_op_get_local_object( $uri )             행 + document
  axismundi_op_update_local_object( $row, $doc )    **전체 문서**를 받는다.
                                                    WHERE 에 version 과 object_status='active'
                                                    → 불일치면 ax_op_local_version 409.
                                                    publicly_readable 재계산, 투영 갱신
  axismundi_op_local_update_types()                 Note · Video
  axismundi_op_local_note_server_set()              id·url·published·updated·deleted·
                                                    formerType·@context
  axismundi_op_local_property_known( $field )       어휘 레지스트리 조회
vocabulary-registry.php
  axismundi_op_vocabulary_property( $term )         value_shape 등
  data/…vocabulary.json                             타입 54 · 속성 63(orderedItems 별칭 포함)
Activities (여기 있으면 안 되는 것)
  c2s-local-object.php                              editable 목록과
                                                    server_set/not_updatable/unsupported_property
                                                    **분류이 Activities 에 있다**
```

**낙관적 잠금이 이미 있다.** `version` 을 클라이언트에 돌려주고 PATCH 에서 돌려받으면 된다.
상태 토큰을 새로 만들지 말 것.

## 바꿀 것

### ① 분류기를 OP 로 내린다

편집 API 와 outbox 가 **같은 분류**를 두 번 하게 된다. OP 가 답하고 Activities 가 호출한다.

```txt
만들 것   axismundi_op_local_editable_properties( string $type ) : array
          axismundi_op_local_property_class( string $type, string $field ) : string
          → 'editable' | 'server_set' | 'not_updatable' | 'unsupported_property'
바꿀 것   c2s-local-object.php 가 자체 $editable 목록과 분기 대신 그 함수를 쓴다
          **거부 응답의 모양과 코드는 그대로** — audit-c2s-update-refusal 18 개가 회귀 기준
```

두 표면에서 **같은 필드가 같은 분류를 받는 것**이 이 리팩토링의 목적이다. 편집기는 저장에서
거부당한 필드와 제출에서 거부당한 필드를 같은 코드로 표시한다.

주의: 이 API 는 주소를 편집 가능으로 두고 outbox Update 는 아니다. 그러므로 분류는
**표면별 정책을 받는 인자**를 갖거나, 공통 분류 위에 표면이 좁히는 층을 둔다. 공통 함수가
한 표면의 정책을 전체의 정책으로 만들지 말 것.

### ② 라우트 둘

```txt
GET   …/local-objects/{uuid}   편집용 원본 + version + 필드별 분류
PATCH …/local-objects/{uuid}   부분 수정.  null 은 제거(AP §6.3.1 과 같은 모양)
                               version 불일치면 409
GET   …/local-objects/types    이 슬라이스가 편집하는 타입과 각 타입의 속성 분류,
                               값 형태는 어휘 레지스트리에서
```

응답에서 **AS2 문서와 봉투를 섞지 말 것.** 발행 상태·version·필드 분류는 AS2 어휘에 없다.
문서는 문서대로 두고 봉투는 별도 멤버로 싣는다.

### ③ 발행과 편집의 경합을 막는다

**버전 검사로는 부족하다.** `version` 은 문서 대 문서를 지키지 **문서 대 원장**을 지키지
않는다.

```txt
PATCH 가 "Create 없음" 을 확인한다
같은 URI 로 참조형 Create 가 기록된다
PATCH 가 저장한다  →  원격은 fetch 하라고 들은 문서가 조용히 바뀐 것을 받는다
                      Update 활동도 없다
```

불변식: **발행된 문서는 Update 활동 없이 바뀌지 않는다.** 그러므로 "Create 유무" 확인과
문서 쓰기가 참조형 Create 에 대해 **원자적**이어야 한다.

```txt
실측   axismundi_act_submit_referenced_video() 는
       axismundi_act_submission_lock( source_owner='axismundi-activities',
       source_kind='outbox', source_ref=<actor identity id> ) 안에서 Create 를 기록한다
       → **Actor 범위 잠금**이다.  같은 객체를 다른 Actor 가 발행할 수는 없다
         (attributed_to 가 먼저 걸린다). 그래서 Actor 범위로 충분하다
```

선택지 둘이고, **오너 결정이 필요한 것은 결합 방향**이다.

```txt
① 같은 잠금을 쓴다      OP 가 axismundi_act_submission_lock() 을 호출한다.
                       상호 배제만을 위한 결합이고 function_exists 가드를 둔다
② 조건부 UPDATE        쓰기 자체를 "이 URI 에 outbound Create 가 없을 때만" 으로.
                       원장 테이블 이름이 OP 의 SQL 에 들어간다
```

어느 쪽이든 **경합을 감사로 재야 한다** — 확인과 쓰기 사이에 Create 를 끼워 넣고 저장이
거부되는지 본다. 끼워 넣을 수 없으면 그 감사는 아무것도 재지 않는다.

### ④ 범위는 명시적이다

```txt
인증         기존 패턴 — 로그인 + nonce 또는 application password
권한         principal 과 Actor 를 **둘 다 명시**하고 둘 다 추론하지 않는다.
             쿠키에서 acting Actor 로 흘러내리지 않는다
객체 접근     객체의 attributed_to_uri 와 요청 Actor 가 일치해야 한다
```

JMAP 엔진을 쓰지 않으므로 *"accountId never falls back to a cookie"* 가 공짜로 오지 않는다.
**이것이 첫 감사 항목이다.**

### ⑤ 확장 속성을 보존한다

어휘가 모르는 속성은 `unsupported_property` 로 **분류**되지만 저장에서 지워지지 않는다.
JSON 에만 있는 속성을 넣고 다른 필드를 고쳐도 그 속성이 살아 있어야 한다.

## 바꾸지 말 것

```txt
활동 기록           이 API 는 원장에 아무 행도 만들지 않는다.  배달 작업도 없다
발행된 객체          거부한다.  outbox Update 를 가리킨다
object_uri · id     불변.  편집이 정체성을 바꾸지 않는다
server_set          id·url·published 등은 저장에서 제거한다 (prepare 가 이미 한다)
publicly_readable   직접 쓰지 않는다.  문서의 주소에서 **유도**된다
version 잠금        WHERE 의 version·object_status 를 완화하지 않는다
ax_c2s_update_unpublished   그대로 둔다.  이 API 가 생겨도 outbox 의 답은 같다
```

## 수용 기준

새 감사 `tests/audit-activitystreams-api.php`. 집계 문구는 기존 형식으로.

```txt
미발행 Video 편집     제목·주소를 PATCH → 저장된다.  **원장 0 행, 배달 0 건**
주소를 붙인 뒤        같은 URI 를 outbox 에 제출 → 그때 Create 가 생긴다
                     (= DESIGN-AS2-API §7 의 첫 검증 기준)
발행된 객체 PATCH     거부.  메시지가 outbox Update 를 가리킨다
version 불일치        409.  두 번째 쓰기가 첫 번째를 덮어쓰지 않는다
발행 경합             확인과 쓰기 사이에 Create 가 기록되면 저장이 거부된다.
                     **끼워 넣어서** 재지 않으면 이 검사는 아무것도 재지 않는다
server_set 필드       PATCH 에 넣어도 저장되지 않는다
확장 속성             JSON 에만 있는 속성이 다른 필드 수정 후에도 남아 있다
분류 일치             같은 필드에 대해 이 API 와 outbox 가 같은 분류를 돌려준다
범위                  다른 Actor 의 객체는 403.  acting Actor 를 쿠키에서 추론하지 않는다
types 발견            타입 목록과 분류가 레지스트리에서 나온다.  하드코딩된 목록이 없다
회귀                  audit-c2s-update-refusal 18 · audit-c2s-create-note 55 ·
                     audit-media-upload 39 가 그대로 통과
```

**변이로 깨 볼 것.** 최소 셋 — version 조건 제거(덮어쓰기가 통과하는지), 확장 속성 제거
(보존 검사가 실제로 깨지는지), acting Actor 를 쿠키에서 읽기(범위 검사가 깨지는지).

이 저장소에서 판정식이 통과만 하고 아무것도 재지 않은 경우가 다섯 번 있었다. 마지막 것이
어제였고, 감사 안의 가드가 없으면 거짓 통과한다.

## 측정 비용에 대한 주의

이 환경은 WordPress 부팅이 **요청당 약 12 초**다(플러그인 26 · Windows 바인드 마운트).
HTTP 왕복이 많은 감사는 3~5 분이 걸린다. 감사를 설계할 때 **HTTP 로 재야 하는 것과 서비스
계층으로 충분한 것**을 가르면 비용이 줄어든다 — outbox-http 가 HTTP 계약을 재는 것은 목적이
있어서이고, 이 API 의 분류·보존·잠금은 서비스 계층에서도 잴 수 있다.

## 검수할 때 볼 것

- 원장과 배달 큐가 **정말** 그대로인가. 함수를 안 썼다가 아니라 행이 없다는 것
- 분류가 한 곳에서 나오는가. 두 표면이 각자 목록을 들고 있지 않은가
- 표면별 정책(주소 편집 가능 여부)이 공통 분류를 오염시키지 않았는가
- acting Actor 가 쿠키에서 추론되지 않는가
- 확장 속성 보존이 known-positive 로 실제로 깨지는가
- 응답에서 AS2 문서와 봉투가 섞이지 않았는가
