# BRIEF — uploadMedia 첫 슬라이스

근거와 계약은 `DESIGN-MEDIA-UPLOAD.md` 에 있다. 이 문서는 **무엇을 바꾸고 무엇을 바꾸지
않는지**만 적는다. 설계 문서의 §10 이 작업 순서이고, 여기서는 그것을 실측 위에 올린다.

## 범위

```txt
안에 있다   POST axismundi/activitypub/actors/{uuid}/uploadMedia (OP 소유)
            multipart: object 초안 + file.  동기 201 + Location(객체 URI)
            영상 타입만.  바이트는 OP 공개 경로.  Create 는 만들지 않는다
밖에 있다   Actor 문서 광고 — endpoints.uploadMedia 는 이번에 넣지 않는다 (오너 승인 별건)
            GC — 마지막 참조 회수.  경합 검증이 선행이다 (DESIGN §8)
            바이트/객체 분리 업로드(raw blob) — 조건이 안 생겼다 (DESIGN §3)
            트랜스코딩 · 202 · 완료 신호 — 미결 (DESIGN §6)
            이미지 렌디션 — attachment 레인에서 이미 풀렸다 (FEP-1311)
            클라이언트 UI — 컴포저는 별건
```

## 현재 (실측 2026-11-14)

```txt
local-objects.php:114   'Note' !== type → ax_op_local_type "accepts Note only"
local-objects.php:105   known_properties 가 **손으로 쓴** Note 목록이다
vocabulary-registry.php  axismundi_op_vocabulary_property() 가 속성과 value_shape 를 안다
data/…vocabulary.json    types 54 · properties 63.  코어 타입의 declares 는 비어 있다
ax_local_objects         object_type · publicly_readable 칼럼이 이미 있다
c2s-local-object.php:39  Update 게이트가 Note 의 server_set · known 목록을 쓴다
actors.php:266           액터 문서 투영이 inbox · endpoints · publicKey 를 받는다
c2s-outbox.php:207       outbox 가 axismundi/activitypub 과 axismundi/v1 둘에 등록된다
```

Emoji 가 같은 문제를 이미 풀었다 — `axismundi-emoji/includes/local.php:454` 의
`axismundi_emoji_handle_upload()`, `:440` 의 `upload_dir` 필터, `binary-cache.php:317` 의
sniff. **읽고 패턴을 가져오되 호출하지 않는다** (별 플러그인이고 비활성일 수 있다).

## 바꿀 것

### ① 속성 허용은 레지스트리가 정한다 — 타입별 어휘를 쓰지 않는다

현재 `axismundi_op_local_note_known_properties()` 는 **손으로 쓴 목록**이고, 그것이
`c2s-local-object.php` 의 Update 거부에서 `unsupported_property` 판정의 근거다. Video 를
위해 같은 모양의 목록을 하나 더 만들면 **AS2 에 없는 제약을 발명한다.**

```txt
실측   data/activitystreams-vocabulary.json — types 54 · properties 63
       Video  parents:["Document"]  declares:[]  excludes:[]
       Note   parents:["Object"]    declares:[]  excludes:[]
       url → "iri-or-link"   duration → "duration"
```

`declares` 가 빈 것은 누락이 아니다. AS2 는 타입별로 속성을 제한하지 않는다 — 어떤 Object 든
어떤 속성을 가질 수 있다. 그러므로 타입별 허용 목록은 스펙에 대응물이 없다.

**세 층으로 가른다.**

```txt
어휘      그 속성이 존재하는가 · 값 형태가 맞는가
          → axismundi_op_vocabulary_property() 와 그 value_shape.
            타입별이 아니라 속성별이다
작성      C2S 는 문법이 맞으면 넓게 받는다.  타입으로 거르지 않는다
수정영역  무엇을 서버가 소유하고 무엇이 수정 가능한가
          → JMAP 의 server-set · immutable 개념(RFC 8620 §1.1)과
            이미 구현된 분류(server_set · not_updatable · unsupported_property).
            이것도 속성별이다
```

바꿀 것은 **`unsupported_property` 의 출처**다. 손으로 쓴 목록이 아니라 "레지스트리가 모르는
속성" 이어야 한다. `server_set` 과 `not_updatable` 은 우리 저장소의 정책이므로 선언으로
남지만, 그 선언도 타입별 어휘가 아니라 **속성별 분류표**다.

`declares` · `excludes` 는 **제품 소유 타입이 진짜로 제한할 때만** 채운다. AS2 코어 타입에
채우는 것은 스냅샷을 왜곡하는 일이다.

#### 그래도 남는 게이트는 의미 게이트다

"넓게 받는다" 가 "아무거나 저장한다" 는 아니다. 아래는 값 형태로 판정되지 않으므로 그대로
남고, **이것들도 타입별이 아니라 속성별이다.**

```txt
attributedTo   권한이다.  outbox Actor 와 일치해야 한다
attachment     파일 참조여야 한다 — 발행물 참조가 아니다 (C2S.md §13)
content 류     HTML sanitization.  value_shape 가 대신할 수 없다
tag · inReplyTo · quote   각자 이미 있는 의미 계약을 쓴다
```

#### 타입 한정은 어휘가 아니라 슬라이스 정책이다

첫 슬라이스가 영상만 받는 것은 **이 슬라이스가 발행하는 타입 목록**이고, 속성 허용과는 다른
축이다. 목록 하나이고 레지스트리를 가리킨다 — `category: object` 이고 이 슬라이스가
명시한 타입. `'Note' !== $type` 하드코딩(`local-objects.php:114`)을 그 목록으로 교체한다.

```txt
해야 할 것   슬라이스가 발행하는 타입을 한 곳에 선언.  레지스트리로 검증
하지 말 것   타입마다 known_properties 복제.  AS2 코어 타입의 declares 채우기
확인할 것    DB 스키마 변경이 필요 없다는 것.  object_type · document_json 으로 충분하다
```

#### 타입 축의 일반 규칙은 레지스트리의 `category` 다

손으로 쓴 타입 목록이 아니라 `category: object` 하나다. 그것이 activity·actor·collection·
link 네 범주를 한꺼번에 제외하고, FEP-c3ab 가 산문으로 적은 경계와 같다.

**전역 규칙으로 일반화하면 틀린다.** 활동의 대상이 활동인 것이 정당한 자리가 셋 있다.

```txt
Undo              Undo(Like)·Undo(Follow) — AP 가 활동을 거두는 방법
Accept · Reject   object 가 Follow 활동이다.  **Person 도 보낸다 — Group 특례가 아니다.**
                  실측: local-social.php:214 는 'object' => $follow->get_uri(),
                  :263 은 'object' => $follow->get_payload()
Announce          Group 전달 패턴에서 Create 를 감싼다.  이쪽이 Group 특례다 —
                  Mastodon 의 boost 는 Announce(객체 URI) 이고 활동이 아니다
```

그러므로 이 규칙은 **콘텐츠 명령**(로컬 객체의 Create·Update·Delete)에만 적용된다. 스냅샷
실측으로 `Undo`·`Accept`·`Reject`·`Announce` 는 모두 `category: activity` 이므로, 범주
검사를 이 셋에 적용하면 정상 동작을 막는다.

`Question` 은 예고된 예외다 — 스냅샷이 `category: activity`·부모 `IntransitiveActivity` 로
기록하는데(AS2 가 거기 두었으므로) 우리는 그것을 콘텐츠로 작성한다. Question 을 작성하게
되면 규칙은 "category object, 또는 이 슬라이스가 콘텐츠로 작성하는 activity 범주 타입" 이
된다 — **명시적 예외 목록**이고 category 검사를 느슨하게 푸는 것이 아니다.

Update 도 타입으로 거부하지 않는다. **같은 분류표가 결정한다** — 어떤 속성이 수정 가능한지
만 보고, 타입이 Video 라서 거부하는 경로를 만들지 않는다.

### ② 수신은 코어 검증을 통과한다

```txt
POST          wp_handle_upload()
디스크 파일    wp_handle_sideload()  (is_uploaded_file() 이 false 이므로)
목적지        upload_dir 필터.  호출 직전에 add, finally 에서 remove
```

함정 둘이 Emoji 주석에 있고 둘 다 조용히 실패한다.

```txt
필터 수명   요청 끝까지 걸려 있으면 같은 요청의 다른 플러그인 업로드를 우리 저장소로
            끌어온다.  두 플러그인이 한 요청에서 업로드할 때만 나타난다
필터 재귀   필터 안에서 경로 헬퍼를 부르면 wp_upload_dir() 가 이 필터를 다시 적용한다.
            스택 오버플로가 PHP 를 죽이므로 **에러가 아무 데도 남지 않는다.**
            코어가 막 건넨 basedir · baseurl 로만 조립한다
```

### ③ allowlist 는 코어 목록이 아니다

바이트를 sniff 해서 **자체 목록**과 대조한다. `get_allowed_mime_types()` 는 필터 가능하므로
보안 불변식의 근거가 될 수 없다. 첫 슬라이스는 영상 타입만.

확장자는 sniff 한 타입에서 유도하고 알 수 없으면 거부한다. 정적 서버가 확장자로
Content-Type 을 고르므로, **allowlist 가 activity+json · ld+json · json 으로 매핑되는
확장자를 포함하면 FEP-fe34 의 origin 분리 조건이 참이 된다**(DESIGN §9).

### ④ 공개는 원자적이고 중복은 해시로 제거한다

```txt
경로       uploads/axismundi-object-projections/blobs/{shard}/{hash}.{ext}
순서       incoming 수신 → 검증 → 해시 확정 → staging 쓰기 → rename
있으면     쓰지 않는다.  같은 바이트는 같은 파일이다
기록       실제 상대 경로를 행에 적는다.  읽기·삭제는 그 경로만 쓴다 (JMAP 선례)
```

`rename` 과 DB 기록은 한 트랜잭션이 아니다. **파일만 공개되고 객체 기록이 실패한 상태는
오류가 아니다** — 다음 업로드가 같은 해시로 재사용한다. 보상 삭제를 넣지 말 것.

### ⑤ 재시도는 키로 식별한다

해시로 기존 객체를 돌려주면 안 된다. 같은 바이트로 제목이 다른 Video 둘은 정당하다.

```txt
같은 키 · 같은 입력    기존 객체의 Location
같은 키 · 다른 입력    409
키 없음                매 요청이 새 제출
```

선례는 outbox 의 `Idempotency-Key` 와 intent 해시 비교다(`ax_c2s_retry_conflict`).

### ⑥ 거부는 FEP-c180 problem details

에러 표현을 새로 만들지 않는다. c180 의 applicability 에 "media upload" 가 명시돼 있다.

## 바꾸지 말 것

```txt
attachment 행                만들지 않는다.  이 레인의 미디어를 attachment 수명주기에
                            묶는 것이 금지 이유다 (경로 때문이 아니다)
Create 기록                  업로드는 원장에 아무 행도 만들지 않는다
jmap/blobs                   공개 바이트는 그 저장소를 쓰지 않는다.  루트 deny 가
                            트리 전체에 상속된다
emoji 함수 호출              패턴만 가져온다.  별 플러그인이다
Note 동작                    Create(Note) · Update(Note) 의 **결과**가 바뀌지 않는다.
                            목록의 출처는 바뀐다 — 그것이 ①이다
AS2 코어 타입 선언            스냅샷의 declares · excludes 를 채우지 않는다
Actor 문서                   endpoints.uploadMedia 를 넣지 않는다
공개 URL                     한 번 공개한 파일 URL 에 다른 바이트를 쓰지 않는다
```

## 수용 기준

감사 하나를 `tests/audit-media-upload.php` 에 둔다. 집계 문구는 기존 감사 형식으로.
변이 감사가 필요하면 `audit-media-upload-mutations.php` 로 분리한다(저장소 관례).

**함수 호출이 아니라 결과를 본다.**

```txt
WP attachment 행이 없다            업로드 후 posts 에 attachment 가 생기지 않는다
바이트가 OP 경로에 있다             기록된 상대 경로가 그 루트 안이고 파일이 읽힌다
Location 이 Video 객체 URI 다       파일 URL 도 Activity URI 도 아니다
원장에 Create 가 없다               업로드만으로 활동이 생기지 않는다
allowlist                          activity+json 으로 매핑되는 확장자가 없다.
                                   알 수 없는 타입은 거부된다
중복 제거                          같은 바이트 · 다른 제목 → 객체 둘, 파일 URL 하나
삭제                               둘 중 하나를 지워도 다른 객체의 파일이 조회된다
재시도                             같은 키 · 같은 입력 → 같은 Location.
                                   같은 키 · 다른 입력 → 409
Note 회귀                          기존 Create(Note) · Update(Note) 의 결과가 같다.
                                   거부 코드와 properties 분류까지 같다
레지스트리 출처                     레지스트리에 없는 속성이 unsupported_property 이고,
                                   손으로 쓴 목록을 읽지 않는다
타입 축                            타입 한정은 슬라이스 목록 하나로 끝난다 —
                                   타입별 속성 목록이 새로 생기지 않는다
```

**변이로 깨 볼 것.** 판정식이 통과만 하고 아무것도 재지 않는 경우가 이 저장소에서 네 번
있었다. 최소 셋 —

```txt
upload_dir 필터를 finally 에서 떼지 않는다   같은 요청의 두 번째 업로드가 어디로 가나
필터 안에서 경로 헬퍼를 부른다                이것이 조용히 죽는지 확인한다.
                                            에러가 안 남으므로 감사가 "통과" 할 수 있다
allowlist 에 json 매핑 확장자를 넣는다        감사가 실제로 깨지는가
```

둘째가 이 작업에서 가장 위험하다 — 실패 신호가 없으므로, 감사 자체가 거짓 통과한다.

기존 감사도 같이 돌린다: `audit-local-attribution`, `audit-identifier-separation`,
`audit-media-library`, `axismundi-activities/tests/audit-c2s-create-note.php`,
`audit-c2s-update-refusal.php`.

## 문서

`PROTOCOL-ROUTE-NAMESPACES.md` 의 경계 표에 행 하나를 더한다 —
`uploadMedia POST | axismundi/activitypub | OP`. AP 경로 목록에도 넣는다. 광고하지 않는
상태라는 것을 같은 줄에 적는다.

## 검수할 때 볼 것

- WP attachment 가 정말 안 생기는가. 함수를 안 썼다는 것이 아니라 행이 없다는 것
- 타입별 속성 목록이 새로 생기지 않았는가. 허용의 출처가 레지스트리인가
- AS2 코어 타입의 `declares` 에 손대지 않았는가
- `upload_dir` 필터가 `finally` 에서 떨어지는가. 재귀 함정 감사가 known-positive 로 깨지는가
- allowlist 가 코어 목록을 읽지 않는가
- 해시가 재시도 식별에 쓰이지 않는가
- 보상 삭제가 들어가지 않았는가
