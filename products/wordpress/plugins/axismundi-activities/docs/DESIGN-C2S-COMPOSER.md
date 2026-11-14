# DESIGN — 관리자 C2S 컴포저와 Outbox

현재의 좁은 관리자 답글/심사 슬라이스는 [ADMIN-WORKFLOWS.md](ADMIN-WORKFLOWS.md)를 따른다.
아래는 더 넓은 계획이며 Like/Follow/활동 로그 UI까지 구현됐다는 뜻이 아니다.

Todo PoC 가 관리자 페이지에서 JMAP 을 증명한 것처럼, ActivityPub C2S 도
관리자 페이지에서 증명한다. 목적은 제품이 아니라 **제출 경로의 증명**이고,
나중에 프론트앱에 작성기를 올릴 때 설계를 다시 하지 않는 것이다.

## 1. 전제 정정 — 엔드포인트는 이미 있다 (실측 2026-11-01)

이 문서는 처음에 "outbox POST 엔드포인트를 만드는 것이 이 슬라이스" 라고 썼다.
**틀렸다.** C2S.md 9 의 "There is no outbox POST endpoint" 를 코드에 대보지 않고
옮긴 결과였다. 실제로는 이렇다.

```txt
c2s-outbox.php:115    POST axismundi/v1/actors/{uuid}/outbox   rest_api_init 등록
인증                  쿠키 + nonce  또는  application password
인가                  can_act_as() · 로컬 · public · handle locked 를
                      서비스 경계에서 재검증 (브라우저 선택을 신뢰하지 않는다)
멱등                  Idempotency-Key + intent 비교 (서버 생성 id·시각 제외)
응답                  201 + Location: <activity uri> + Cache-Control: no-store
수용 타입             Like · Undo(Like) · Create(Note) · bare Note · Delete(local Object)
저장                  c2s-local-object.php 가 Create(Note) 를 로컬 Object 로 저장
감사                  audit-c2s-outbox-http.php · audit-c2s-outbox-mutations.php
```

그러므로 **이 슬라이스는 UI 다.** 백엔드 왕복은 이미 서 있고 감사도 붙어 있다.

### 남은 간극

```txt
UI 없음        컴포저도 Outbox 활동 로그도 없다 — 이 슬라이스의 본체
Follow 미수용  outbox 가 받는 타입에 Follow 가 없다
경로 분기      follow-block.php:278·296 과 like-block.php:76·99 가 자기 REST 경로를
               따로 가진다. Like 는 outbox 로도 되고 블록 경로로도 된다
Update 없음    c2s-local-object.php 는 Create · Delete 만 받는다
Intransitive   미지원 (9 참조)
```

## 2. 저작 경로가 둘이고, 이 슬라이스는 그중 하나다

Lemmy 는 자기 모델을 Post→`Page`, Comment→`Note` 로 매핑한다
([federation docs](https://join-lemmy.org/docs/contributors/05-federation.html#objects)).
수신 시에는 여러 타입을 허용하므로 **타입만으로 게시글·댓글이 결정되지는 않는다.**

우리 매핑은 이렇다.

```txt
경로            저작·저장                                 제출
WP 게시물·CPT   wp_posts 기반 Article · Event · Topic     전용 API → ActivitySubmission
                Image · Video 등
AP C2S          로컬 Object. 기본 Note                    Actor outbox 에 직접 POST
```

**Article 은 C2S 에서 의도적으로 제외한다.** Article 은 wp_posts 에 저장하는
것이 맞고, 그러면 전용 API 와 ActivitySubmission 어댑터가 담당한다. C2S 에
Article 을 넣으면 같은 것을 저장하는 길이 둘이 된다.

C2S Object 는 독립 글로도 답글로도 쓰일 수 있지만, **답글 지원은 이번 범위가
아니다.**

## 3. 소유 경계

```txt
Activities   컴포저 UI · Outbox 활동 로그 UI
             제출 권한 검증 · Activity 처리와 원장 기록
OP           저작된 로컬 Object 의 저장 · 조회 · AS2 표현
```

Object 를 저장하는 곳이 작성 UI 까지 소유할 이유는 없다. 컴포저는 **저장 함수를
직접 부르지 않는다** — 실제 outbox 에 POST 하고, 그 경로가 OP 를 부른다.
그래야 프론트앱이 같은 자리에 들어올 때 UI 만 바뀐다.

### S2S inbound 와 대칭이되, 같지는 않다

```txt
S2S  → inbox   → 원격 Actor 검증      → 원격 Object 반영   → Activity 기록
C2S  → outbox  → 사용자·Actor 발행권 검증 → 로컬 Object 저작  → Activity 기록
```

하위 처리는 공유할 수 있지만 **원격 내용을 관찰하는 것과 로컬 내용을 저작하는
것은 소유권·권한·검증이 다르다.** 같은 저장 경로로 합치지 않는다.

## 4. 정해진 것

```txt
제출 경로     actor 가 광고한 outbox 에 직접 POST. 관리자 전용 우회 경로를 두지
              않는다 — 이 컴포저는 나중에 프론트앱과 외부 클라이언트가 설 자리를
              먼저 만드는 것이다
저작 저장     로컬 Object. wp_posts 를 거치지 않는다
하위 처리     입구와 저작 저장소가 다른 것이지 **검증·원장 기록·전달을 새로 만드는
              것이 아니다.** can_act_as(), Activity 원장, 전달 큐는 기존 경로에
              연결한다
Activity      Create 만. 다른 Activity 는 미지원이며 UI 에서도 그렇게 보인다
Object        Note 만. 선택 UI 에 Article 을 노출하지 않는다
본문          HTML 또는 Markdown 으로 쓰되 저장과 전송은 HTML
Outbox 뷰     콘텐츠 피드가 아니라 **이종 Activity 의 활동 로그**다.
              list-detail 로 보고, 항목마다 AS2 원본을 펼칠 수 있어야 한다
전달 상태     수신처가 여럿이면 결과도 여럿이다. 단일 Delivered 로 뭉개지 않는다
```

### principal 과 acting Actor

```txt
principal     클라이언트가 주장하는 값이 아니라 **인증으로 확정**한다
acting Actor  클라이언트가 고른다
서버          그 principal 이 그 Actor 로 발행할 권한이 있는지 검사한다
```

인증 **수단**과 발행 **권한 검사**는 다른 층이다. 수단이 쿠키든 토큰이든 권한
검사는 같아야 한다.

## 5. 정해지지 않은 것

```txt
인증 수단       관리자 화면은 쿠키 + nonce 로 충분하다.
                외부 클라이언트용으로 Application Password 를 쓸지 Bearer 토큰을
                쓸지 **둘 다 할지는 정해지지 않았다.** 이 슬라이스에서 정할
                필요도 없다 — 같은 엔드포인트에 인증 수단만 더 붙는 구조면 된다

미디어 접근     첨부가 범위 밖이므로 이번에는 정하지 않는다.
                붙일 때 **기존 미디어 접근 정책을 먼저 확인한다.**
                "업로더와 무관하게 전부 사용 가능" 은 피커 요청에서 따라 나오는
                결론이 아니다

예약 발행       백엔드는 이미 있다 — scheduled-publication.php 229 줄,
                wp_ax_activity_submission 테이블. 다만 주석이 명시하듯 그 슬라이스는
                **WordPress Post Create 어댑터/클록만** 제공한다.
                로컬 Object 가 같은 의도 테이블을 쓸지는 어댑터를 하나 더 다는
                문제이고, 이번에 UI 는 내지 않는다
```

## 6. 범위

```txt
안에   컴포저 UI · Outbox 활동 로그 UI          이 슬라이스의 본체
       Follow 를 outbox 수용 타입에 추가        Like 는 이미 된다
밖에   Update · 답글 · Article
       첨부                                     코어 미디어 API 연동이 필요하다
       IntransitiveActivity (Arrive · Travel)   Contacts 뒤 — 9 참조
       예약 발행 UI
       블록 경로 통합                           아래 참조
```

성공 기준: **컴포저에서 Create(Note) 를 제출하면 로컬 Object 로 저장되고
Outbox 에서 보인다.** 그리고 같은 화면에서 Like 와 Follow 를 보낼 수 있다.

### 범용 모듈 + UI 차별화 — 이미 그 모양이다

`axismundi_act_submit_outbox()` 가 타입으로 분기하는 단일 진입점이다. 그러므로
"범용 기능 모듈" 은 새로 만들 것이 아니라 **있는 것에 Follow 를 더하는 일**이다.
UI 가 달라지는 축은 `object` 가 무엇이냐다.

```txt
저작된 object    Create(Note)      본문 작성기.  OP 가 로컬 Object 로 저장
참조된 object    Like · Follow     대상 선택기.  이미 있는 것을 가리킨다
                                   Like 대상은 Object → OP 가 해소
                                   Follow 대상은 Actor → Actors 가 해소
object 없음      Arrive · Travel   장소 선택기 (9)
```

세 번째 줄 때문에 **제출 계약을 Create 전용으로 못 박지 않는다.**

### 블록 경로 분기는 이 슬라이스에서 정리하지 않는다

```txt
follow-block.php:278 · 296    자기 REST 경로
like-block.php:76 · 99        자기 REST 경로 — Like 는 outbox 로도 된다
```

같은 조작에 진입점이 둘이다. 합치는 것이 옳아 보이지만 **블록은 공개 프론트엔드
표면이고 outbox 는 인증된 C2S 표면이라 권한·캐시·응답 모양이 다르다.** 합치려면
그 차이를 먼저 재야 하고, 컴포저가 동작하는 데 필요한 일은 아니다. 별도 슬라이스로
남긴다.

## 7. Update 는 왜 뒤인가 — Note 로 제한하는 진짜 이유

이번에 구현하지 않지만, **지금 적지 않으면 나중에 Note 제한을 자의적 축소로
오해한다.** 제한에는 저장 경계라는 근거가 있다.

### Outbox 에 보이는 것 ≠ C2S 로 편집할 수 있는 것

클라이언트가 수정하려면 먼저 그 Actor 가 발행한 목록을 받아야 하는데, 거기엔
Article 도 Event 도 원격 Object 를 향한 Like 도 섞여 있다.

```txt
대상                                      수정 경로
C2S 로 저작한 로컬 Note                   Object 조회 → C2S Update
WP 게시물에서 투영한 Article · Event 등    원본 WP 편집/API → ActivitySubmission
원격 Object 를 향한 Like · Announce        그 Object 의 수정 권한을 주지 않는다
```

**타입으로 필터링해서 해결하면 안 된다.** 타입만으로는 저작 출처도 수정 가능
여부도 알 수 없다. 서버가 저작 원본과 수정 권한과 지원되는 수정 경로를
판정해야 하고, 그 결과를 클라이언트도 알 수 있어야 한다. 어떤 표면으로 줄지는
후속 설계다. **UI 가 숨기더라도 서버의 거부 검사는 반드시 있어야 한다.**

### 저장 경계를 넘는 변경은 Update 가 아니다

```txt
inReplyTo 추가 · 변경 · 제거 (Note)    같은 로컬 Object 저장소 안이다.  가능
C2S inReplyTo Article → C2S Article    로컬 Object → wp_posts 이전이 필요하다.  불가
로컬 Note → WP Article                 일반 Update 가 아니라 별도의 전환·이관 기능
```

그래서 **C2S 저작을 Note 로 제한한다.** 로컬 Object 원장이 독립 글과 답글을
함께 다루는 유일한 경로이므로, 그 안에서는 `inReplyTo` 를 붙이고 떼는 것이
저장소 이전을 요구하지 않는다. Article·Event 는 wp_posts 저작 경로에 남는다.

단서 둘. **Note 라고 반드시 댓글인 것은 아니고, C2S 표준이 Note 만 허용하는
것도 아니다.** 일관된 저작·수정 모델을 주기 위한 우리 구현의 지원 범위다.

### 부분 수정과 상호운용

처음부터 모든 필드를 덮어쓰는 저장 API 만 만들면 확장이 막힌다. **속성 누락과
명시적 삭제를 구분할 여지를 남긴다.** 다만 JMAP 의 PatchObject 문법을 AP 에
그대로 가져오는 것은 별개 문제다.

그리고 `inReplyTo` 제거는 필드 하나처럼 보이지만 **수신 서버에서는 댓글을
독립 게시물로 재분류하는 일**일 수 있다. 게시글과 댓글을 분리 저장하는 구현에
그 이전이 가능한지는 우리가 보장할 수 없다.

```txt
C2S   inReplyTo: null 로 삭제를 요청하는 의미는 정의돼 있다
S2S   그 변경을 담은 Object 를 받은 상대가 부모 연결 · 스레드 · 목록까지
      갱신하는지는 별도 검증이다
```

Update 를 붙일 때 전부 조사하지 말고, **게시글·댓글 저장 모델이 분리된 구현
하나**부터 확인한다. ① 같은 Object ID 로 답글 생성 ② `inReplyTo` 를 제거한
Update 수신 ③ 재조회 문서뿐 아니라 **부모의 replies · 화면 · 게시물 목록**에서도
반영되는지. 지원하지 않는다고 곧바로 상대 잘못으로 단정하지 않는다 — 프로토콜의
필드 변경과 제품 모델의 구조 변경 사이에는 제약이 있을 수 있다.

**미지원 전환을 조용히 성공 처리하지 않는다.** 이것이 이 절의 유일한 하드 규칙이다.

### 서버가 소유한 속성, 그리고 거부의 모양 (구현 2026-11-14)

위에서 "서버가 판정하고 그 결과를 클라이언트도 알 수 있어야 한다" 고 적어 둔
것의 첫 조각이다. **표면은 광고가 아니라 거부다.**

클라이언트가 쓰기 가능 여부를 아는 길은 셋뿐이고, 배타적이지 않다.

```txt
① 서버가 광고한다      Session 에 싣는다. 아직 없다
② 클라이언트가 외운다   명세를 읽고 구워 넣는다. JMAP 이 쓰는 방식
③ 거부가 이유를 말한다  어느 속성이 왜 거부됐는지 돌려준다
```

**③ 을 먼저 만들었다.** 싸고, 로컬이고, 프로토콜 확장을 약속하지 않으며,
무엇보다 ① 이 정말 필요한지에 대한 증거를 만들어 낸다. 그 전까지 우리는
②  를 강제하면서 ③ 을 주지 않고 있었다 — 컴포저가 server-set 을 직접 벗겨내야
POST 가 통과하는데, 실패했을 때 어느 필드인지 알려주지 않았다.

**분류의 소유는 투영이다.** 무엇이 저장소에서 파생되는지 아는 것은 AS2 어휘가
아니라 transformer 다. `axismundi_op_local_note_server_set()` 이 그 선언이고,
Create 의 strip 과 Update 의 거부가 **같은 출처**를 읽는다. 그 전에는 OP 의
`unset()` 리터럴과 Activities 의 allowlist 리터럴이 서로를 모르는 두 사본이었다.
Image 렌디션 배열처럼 다른 타입의 파생 속성은 그 타입의 transformer 가 자기
목록을 갖는다.

**Create 와 Update 는 같은 분류를 공유하되 규칙이 같지는 않다.** JMAP `/set`
([RFC 8620 §5.3]) 은 생성에서 server-set 을 금지하지만, 패치에서는
*"Any server-set properties MAY be included in the patch if their value is
identical to the current server value"* 로 두고 다를 때만 `invalidProperties`
로 거부한다. 근거가 실용적이다 — 컴포저는 객체를 받아 한 필드만 고치고 통째로
돌려보내므로 `id`·`published` 가 같은 값으로 딸려 오는 것은 정상이다.

**우리는 아직 그 에코를 허용하지 않는다. 그래서 현재 정책에서는 GET 표현을
그대로 다시 제출할 수 없다.**

[ActivityPub §6.3.1] 의 partial 은 *"any key value pair supplied is used to replace
the existing value"* 라는 **의미 규정**이지 "바뀐 것만 보내라" 는 제한이 아니다.
전체 표현을 보내는 것도 그 안에서 말이 된다. 다만 §6.3–6.3.1 이 **변경되지 않은
서버 소유 필드를 서버가 반드시 받아야 한다고 정하지는 않는다** — 적용 방식과
권한 전제를 말할 뿐이다. 그러니 우리 게이트가 명세를 어기는 것은 아니고,
**왕복 제출이라는 흔한 클라이언트 패턴을 막는다**는 것이 정확한 서술이다.

반대쪽도 같이 봐야 한다. §6.3.1 은 **서버가 소유한 속성에 대해 아무 말도 하지
않는다.** 문자 그대로 구현하면 클라이언트가 `attributedTo` 나 `published` 를
덮어쓴다. 그러니 정책은 반드시 있어야 하고, 선택지는 둘뿐이다 — server-set 키면
거부하거나, **값이 같으면 통과**시키거나. JMAP 이 후자를 고른 이유가 정확히 이
왕복이다.

막고 있는 것은 판단이 아니라 순서다. 형식 게이트는 `c2s-local-object.php:50`,
저장본은 `:80` 에서 불러온다. 게이트 시점에 비교할 현재 값이 없다. 게이트를
통째로 뒤로 옮기면 형식 오류(422)와 권한 오류(403)의 선후가 뒤집히므로,
**server-set 키만 로드 뒤 2차 판정으로 미루는** 쪽이 맞다. 그 전까지 컴포저는
server-set 을 직접 벗겨내야 하고, 적어도 이제는 **무엇을 벗겨야 하는지 거부가
알려준다.**

이번 변경 자체는 판정을 바꾸지 않았다 — 거부하던 것을 그대로 거부하고, 무엇을
거부했는지만 말한다.

```txt
properties   경로 → 이유
             object.<field>    required · unsupported · server_set ·
                               not_updatable · unsupported_property
             activity.<field>  not_updatable
reasons      필드가 아닌 사유   object_required · no_editable_properties
```

**경로를 한정한다.** Update 는 문서를 둘 싣는다 — Object 의 `to` 와 Activity 의
`to` 는 서로 다른 입력이다. 이름만 돌려주면 컴포저가 어느 입력에 오류를 붙일지
추론해야 하고, 둘 다 들어오면 한쪽이 다른 쪽을 덮어쓴다.

**인지된 속성은 unknown 이 아니다.** `attributedTo` 는 별도의 귀속 정책이 다루고
Object 의 주소는 생성 시점에 실린다 — 둘 다 오타가 아니라 **이 작업이 받지 않는
실재 속성**이다. 그래서 투영이 자기 어휘를 선언하고
(`axismundi_op_local_note_known_properties()`), 거기 있으면 `not_updatable`,
없으면 `unsupported_property` 다. Create 가 조용히 제거하는 목록을 전체 쓰기
정책으로 취급하지 않는다.

감사 `tests/audit-c2s-update-refusal.php` (14 checks). 마지막 검사가 계약을
지킨다: 형식이 올바른 패치는 이 게이트를 통과해 **뒤에서** 실패해야 한다.
변이 둘로 깨 봤다 — server_set 을 unknown 으로 분류, payload 통째로 제거.

#### 변경이 없는 Update — 하지 않기로 한 것과 그 제약

실질적 변경이 없는 `Update` 를 no-op 으로 처리하는 것은 가능하지만 **이번에
하지 않는다.** 하게 될 때를 위해 제약만 적어 둔다.

**이전 `Update` 의 재시도로 판정하면 안 된다.** 현재 값이 같다는 것은 이번
요청이 객체를 바꾸지 않는다는 뜻이지, 어느 과거 활동을 반복했다는 증거가 아니다.
그래서 c3ab 의 재시도 규칙을 재사용할 수 없고, `200 + 최신 Update 의 Location`
도 틀렸다 — 최신 활동이 이번 요청과 무관할 수 있고, 기존 `Update` 가 아예 없을
수도 있다. 도입한다면 **새 활동을 기록하지 않았다는 사실만** 돌려주는 별도
응답 정책이어야 한다.

**비교 시점이 정해져 있다.** 권한과 필드를 검증한 뒤, 요청을 적용·정규화한
**저장 후보**와 현재 객체 사이에서 비교한다. 없는 필드에 `null` 을 보내면 변화가
없지만 있는 필드에 `null` 을 보내면 삭제이므로, 원본 요청끼리 비교하면 틀린다.

**`updated` 는 실제 변경이 확인된 뒤에만 찍는다.** 지금은 Update 분기 끝에서
무조건 갱신하므로, 그대로 두면 모든 요청이 변경으로 보여 비교 자체가 무의미해진다.
내용이 같은데 수정 시각이 오르는 것은 지금도 틀렸고, no-op 을 도입하지 않더라도
고칠 수 있는 별건이다.

**잃는 것이 있다.** 우리 표현이 안 바뀌었다는 것이 네트워크가 최신이라는 뜻은
아니다 — 원격이 이전 배달을 놓쳤거나, 캐시가 어긋났거나, 새 팔로워가 생겼을 수
있다. `Update` 는 사실상 "이 객체를 다시 가져가라" 는 유일한 손잡이이므로,
no-op 을 조용히 삼키면 수리 경로가 사라진다. **로컬 변경이 없다는 것과 원격
배달을 다시 해야 한다는 것은 다른 판단이다.** 재전송 정책을 같이 정하기 전에는
도입하지 않는다.

#### JMAP 은 여기서 선례가 아니다

런타임에 발견되는 권한은 두 층뿐이다. 계정 단위 `accountCapabilities`, 객체 단위
`myRights` ([RFC 8621] Mailbox). **속성 단위는 없다.** `server-set` 과 `immutable`
은 [RFC 8620 §1.1] 의 타입 시그니처 표기이지 런타임 기제가 아니다. 그러니 ① 을
한다면 이식이 아니라 새로 만드는 것이고, 그 근거는 우리 구현에서 나와야 한다.

JMAP 이 객체 단위에서 멈춘 게 우연이 아닐 수 있다. 필드 단위 권한은 조합이
늘고(타입 × 필드 × 액터 × 크리덴셜), 광고가 커지며, "여기서는 못 쓰지만 다른
경로로는 바뀐다" 는 경우를 불리언으로 표현할 수 없다.

```txt
writable            content · name · summary · sensitive
server-set          id · url · published · updated — 보내면 거부
writable elsewhere  렌디션 URL(원본에서 파생) · alt text(WP meta)
                    "못 쓴다" 가 아니라 "여기서 안 쓴다"
```

세 번째가 빠지면 클라이언트는 "수정 불가" 로 읽고, 실제로는 다른 경로로 바뀌는
값을 영영 건드리지 않는다.

#### 스펙 그대로 받으면 가시성 엣지케이스가 allowlist 문제가 된다

C2S 를 outbox POST 스펙 그대로 구현하면 — 그렇게 하기로 했다 — 아래가 **표현 가능해진다.**
파서가 막는 것이 아니라 **등록 여부로 결정된다.**

```txt
to·cc·audience 없이 bto 만 있는 객체        주소 필드만 보면 무주소 공개 객체와 같다
object 없이 content·attachment 를 든 활동   구조상 메일이다. 그 형태라면 Create 가 아니라
                                            IntransitiveActivity 가 맞는 모델이다
```

**fail-closed allowlist 가 바로 이것들을 막는 장치다.** 등록하지 않으면 통과하지 않고,
그래서 "스펙이 허용하므로 받아야 한다" 가 되지 않는다. 등록 여부는 **그 구성의 가시성을
우리가 판정할 수 있게 된 뒤에** 정한다.

`bto` 전용 객체가 왜 문제인지는 `DESIGN-ADDRESSING.md` §11 에 있다 — 배달 전에 `bto` 가
제거되므로 **수신본은 무주소 공개 객체와 구별되지 않는다.** 그래서 그 구성을 받아들이는
것은 "작성자 의도가 전달되지 않는 발행" 을 허용하는 일이다. 단일 수신자에게 본문을
전하는 것이 목적이라면 그 자리는 메일이다.

`IntransitiveActivity` 는 **구조만 맞고 의미는 비어 있다.** AS2 는 그 타입으로 "메시지
보내기" 라는 뜻이나 수신 UI 를 정의하지 않으므로, 등록해도 상대가 무엇으로 읽을지는
합의가 따로 필요하다. allowlist 한 줄로 끝나는 문제가 아니다.

#### 가시성 하네스 — 안티패턴은 금지 목록이 아니라 회귀 테스트다

`DESIGN-ADDRESSING.md` §11 의 실패 목록은 **막아야 할 입력 목록이 아니다.** 서로 다른
판단을 섞었을 때 **실패해야 하는 회귀 테스트**다. FEP 가 판단 원칙을 설명하고, 하네스는
그 원칙이 **작성부터 수신·표시까지 깨지지 않는지** 확인한다.

**잡아낼 대상이 구체적이다 — 하나의 `visibility` 프리셋이 세 판정을 몰래 대신하는 것.**
지금 `resolve_audience()` 가 단어 넷을 받아 `to`/`cc`/`visibility` 를 한꺼번에 내놓는다
([audience.php:63](../includes/audience.php)). 그 하나가 배달 대상 · 표시 범위 · 독립
조회 권한 셋을 다 결정해 버리면, 어디서 새는지 화면에서는 보이지 않는다.

첫 세 케이스는 이미 나왔다.

```txt
① 팔로워 전용 Create + 공개 이미지
   알림만 좁게 배달된다. 이미지의 독립 조회 정책은 바뀌지 않는다
   실패 조건: 이미지가 팔로워 전용으로 분류되면 실패

② 공개 Note + 무주소 첨부
   첨부마다 to: Public 을 복제하지 않아도 본문 안에서 표시된다
   명시된 첨부 표시 제한은 따로 적용하되 파일 접근 제어와 혼동하지 않는다
   실패 조건: 첨부가 숨겨지거나, 표시 제한이 파일 보호로 집행됐다고 보고되면 실패

③ bto 전용 배달
   와이어에서 bto 가 사라져도 그 수신자는 본문을 읽는다
   주소가 비었다고 제3자에게 캐시를 공개하지 않는다
   실패 조건: 수신자가 못 읽거나, 제3자에게 열리면 실패
```

**JMAP 이후 컴포저로 돌아올 때 화면보다 이 세 케이스가 먼저다.** 돌려 보면 설계가 어디서
새는지 바로 보이고, 반대로 화면부터 만들면 프리셋이 세 판정을 삼킨 것을 나중에 발견한다.

#### ① 이 필요한지 판정하는 기준

JMAP 쪽 todo · Contacts · Calendar 를 구현하면서 **제약을 두 부류로 나눠
기록한다.** 이 구분이 Session 에 필드 정보를 실을지를 정한다.

```txt
타입과 작업만으로 정해지는 제약        명세로 충분하다. 광고할 필요가 없다
객체나 현재 자격 증명에 따라 달라지는 제약  런타임 광고가 필요할 수 있다
```

실제 편집 흐름에서 **어느 쪽이 막혔는지**가 근거다. todo PoC 는 패턴 증명이지
타입 다양성의 증거가 아니다. 그리고 거기서 가져올 것은 `patch()` 라는 기제가
아니라 검증 경험이다 — AP 의 C2S Update 는 부분 객체이지 JSON Pointer 패치가
아니므로, 중첩 경로 문법을 그대로 옮길 이유는 없다.

[ActivityPub §6.3.1]: https://www.w3.org/TR/activitypub/#partial-updates
[RFC 8620 §1.1]: https://www.rfc-editor.org/rfc/rfc8620.html#section-1.1
[RFC 8620 §5.3]: https://www.rfc-editor.org/rfc/rfc8620.html#section-5.3
[RFC 8621]: https://www.rfc-editor.org/rfc/rfc8621.html

## 8. 미디어 — 왜 outbox 가 Create(Image) 를 받지 않나

첨부는 이번 범위 밖이지만, **배제 근거는 지금 적어 둔다.** 나중에 "Note 만
되는 건 덜 만든 것" 으로 읽히면 안 된다.

### 업로드와 발행은 저장 경로가 다르고 참조로 이어진다

```txt
업로드    바이너리 → WP attachment 레코드 + 파일 · 메타데이터 · 렌디션 생성
          → 서버가 정한 미디어 표현을 반환
발행      Create(Note) → 로컬 Object 저장 + Activity 원장 기록
          반환받은 참조를 object.attachment 에 붙인다
```

`wp_posts` 의 attachment 행은 첨부 **페이지 HTML** 이 아니라 미디어를 관리하는
레코드다. 원본 · 썸네일 · 변환본 URL 은 그 레코드에 연결된 별도 표현이다.

**업로드 성공은 발행 성공이 아니다.** SPEC.md 5 가 이미 같은 말을 한다 —
워드프레스 미디어 업로드는 Activity 를 만들지 않는다.

### 입력과 출력은 비대칭이다

업로드는 보낸 것을 그대로 돌려주는 API 가 아니라 **서버가 입력을 처리해 자원으로
만드는 경로**다. 그러므로 응답은 클라이언트가 보낸 파일명 · MIME · URL 을
확정해 주는 것이 아니라, **서버가 검증한** 미디어 타입 · 크기 · 접근 URL 과
attachment 식별자를 알려주는 형태여야 한다. 클라이언트는 그 결과를 붙인다.

### 그래서 Create(Image) 를 받지 않는다

실측한 스펙 상태:

```txt
AP §6.12 Uploading Media     "This section is non-normative."
                             "the precise mechanism is out of scope for this
                             version of ActivityPub"
                             엔드포인트 · content type · 응답 · 참조 방식
                             어느 것도 규정하지 않는다
                             (SocialCG 의 별도 Media Upload report 가 진행 중)

AP §6                        outbox 가 받아야 할 **최소 Activity 타입 집합을
                             규정하지 않는다.** 나열된 의무(object 포함 등)는
                             클라이언트 쪽 의무이고 서버 쪽이 아니다
```

둘을 합치면: **미디어 업로드 경로는 우리가 설계하는 것이고, 지원 타입을 좁히는
것은 위반이 아니다.**

```txt
근거   Create(Image) 가 바이너리 업로드 · 변환 절차를 대신하지 못한다
       outbox 에서 따로 허용하면 미디어 저작 · 저장 경로가 둘로 갈라진다
표현   "표준이 금지한다" 가 아니라 **우리 서버의 C2S 지원 범위가 Create(Note)**
       라는 정책으로 명시한다
집행   미지원 타입은 **저장 전에** 거부한다
```

### SPEC.md 5 와 충돌하지 않는다

구분해 둔다. **원장이 `Create(Image|Video|Document)` 를 담지 못한다는 말이
아니다.** SPEC.md 5 가 말하는, 사용자가 미디어를 피드에 공유하는 명시적 행위는
그대로 기록될 수 있다. 여기서 제한하는 것은 **C2S outbox 로 저작하는 범위**다.
독립 Image 발행이 필요하면 WP 미디어 저작 경로와 ActivitySubmission 이 맡는다.

## 9. IntransitiveActivity — Contacts 뒤다 (결정, 2026-11-01)

컴포저가 언젠가 지원해야 하는 것은 맞다. 다만 Create(Note) 보다 **크고, 증명하는
것은 적다.**

```txt
AS2            IntransitiveActivity 는 object 속성이 "inappropriate" 다
               Travel = origin + target,  Arrive = location + origin
               속성 모양이 달라 UI 도 다르다
Question       이미 여기서 빠져 있다 — C2S.md 12 참조.
               FEP-9967 이 Create 발행을 MUST 로 쓰고 Mastodon 은 Object 타입으로 쓴다
               그러므로 새 작업은 Arrive · Travel 둘뿐이다
```

### 의존 사슬

```txt
Arrive · Travel
  → Contacts 프로필 카드의 addresses + coordinates 저작
      → Contacts 플러그인
          → Todo 가 궤도에 오른 뒤
```

미뤄 두기로 한 플러그인을 먼저 당겨 와야 한다. 그리고 object 가 없으므로
**로컬 Object 층을 하나도 건드리지 않는다** — 컴포저가 존재하는 이유인 그 경로를
증명하지 못한다.

### 그래도 지금 영향을 주는 것 하나

IntransitiveActivity 는 `Create` + object 전제가 깨지는 첫 사례다. 그러므로
Create(Note) 를 구현할 때 **제출 계약을 Create 전용으로 못 박지 않는다.**
object 없는 Activity 가 나중에 들어올 자리를 남긴다. 구현하지 않아도 계약
모양은 지금 정해진다.

### Place 를 어디서 읽나 — Place CPT 를 만들지 않는다

```txt
저장    Contacts card 의 addresses 항목
        coordinates 는 "geo:" URI (RFC 5870),  contexts 로 home/work 라벨
        **JSContact 에 locations 속성은 없다.** RFC 9553 은 좌표를
        Address.coordinates 에 둔다
가시성  contexts 는 "where an address should be used, not who can see it" 이다.
        가시성은 .jscontact 투영 결정이 정한다 — 명시 선택 투영, 원문 금지
투영    Place, id 없음. 임베드된 익명 객체.
        기본은 name 또는 geodata 의 term 축 — London · Busan, Korea
        좌표 · radius 는 사용자가 그 Activity 에 붙이기로 할 때만.
        거칠게 말하는 것은 보호 조치가 아니라 **사람들이 말하는 방식**이다
        geodata 는 coordinates / term / address 3 축이고 taxonomy.php 가
        계층과 ISO 3166-1 상속을 이미 가진다
동기화  컴포저는 카드를 **자동완성으로만** 읽는다. 스냅샷이고 참조가 아니다.
        Activity 는 불변이고 연합되므로, 참조를 넣으면 나중 해소 시점의 카드가
        나간다. 스냅샷은 그 Activity 를 위해 공개하기로 한 것만 담는다
공개범위 보통의 글과 같은 설정이다. 이 타입 전용 안전장치가 아니다 —
        Arrive · Travel 은 사용자가 알리려고 하는 행위다
안 만듦  Place CPT. geodata 가 single-post Location 모델을 만들었다 버렸다
        (geodata/includes/meta.php:157)
```

## 10. View 는 GET 에서 자동 발화하지 않는다

컴포저에 넣을 것이냐는 질문의 답은 **주 사용처가 컴포저가 아니다** 이다.

AS2 실측: `View` 는 "Indicates that the actor has viewed the object" 이고
**대상 타입에 제한이 없다.** Actor 도 Activity 도 AS2 에서는 Object 의 하위
타입이므로, "Actor·Activity 는 제외하고 콘텐츠 Object 만" 은 표준 정의가 아니라
**우리 지원 정책**이다. 그렇게 적는다.

### 일반 Object GET 에서 발화시키지 않는 이유

```txt
조회에 섞여 있는 것    캐시 갱신 · 미리보기 · 프리페치 · 크롤러
확정되지 않는 것       요청한 사람이 **어떤 Account 로** 봤는지
```

GET 하나당 View 를 만들면 사람이 보지 않은 것까지 기록되고, 기록된 actor 가
실제로 본 주체라는 보장도 없다.

### 그래서 명시 제출이다

```txt
클라이언트   실제 표시를 확인한 뒤, 선택된 Actor 의 View(object URI) 를 제출
서버         인증 · 실행 권한 · 대상을 검증하고 중복 정책을 적용
전달         기록과 외부 전달은 별개로 정한다. 봤다고 자동 연합하지 않는다
```

### 그러므로 컴포저 범위 안이다

콘텐츠 뷰어의 View 도 **같은 outbox 를 탄다.** 그러니 "주 사용처가 뷰어" 라는 것은
컴포저가 다룰 일이 아니라는 뜻이 아니라, 컴포저가 그 타입을 지원하면 **뷰어는 같은
엔드포인트의 또 다른 클라이언트**가 된다는 뜻이다. 범용 제출 서비스 + 용도별 UI 의
한 사례이고, 대상 입력 방식도 Like 와 같다 — URI 를 받아 서버가 해소한다.

```txt
컴포저   수동 제출.  계약을 정의하는 자리
뷰어     표시 확인 뒤 자동 제출.  같은 계약의 소비자
공통     인증 · 실행 권한 · 대상 검증 · 중복 정책은 한 곳에서
```

마지막 줄이 중요하다. View 를 기록하는 것과 그것을 남에게 보내는 것은 다른
결정이고, 읽음 정보는 보내지 않는 쪽이 기본값이어야 한다.

## 10.1 AS2 JSON 편집기와 컴포저는 둘 다 필요하다 (2026-11-14)

둘을 택일로 두면 안 된다. **편집기는 문서를 작성·검사하는 도구이고, 컴포저는 그 문서를 특정
Actor 의 outbox 에 제출하는 도구다.**

```txt
AS2 JSON 편집기   서버가 아직 실행하지 못하는 것도 작성할 수 있어야 한다 —
                  Create(Person) · 확장 타입 · 인라인 객체 대 URI 참조 ·
                  to·cc·audience 를 그대로 들여다보기
C2S 컴포저        Actor 선택 · 미디어 업로드 · 주소 프리셋 · 실패한 필드 표시 · 재시도.
                  최종 판정은 서버가 한다
```

선례가 있다 — Contacts 의 JSContact 카드 편집기(빌드 없는 `wp.element`).

계약 넷.

```txt
한 문서 공유     두 화면이 같은 AS2 문서를 본다.  JSON 편집기는 컴포저의 고급 모드다
제출은 명시 동작  유효한 JSON 을 저장하거나 미리 보는 것만으로 원장·배달 큐가 움직이지 않는다
확장 필드 보존    폼이 모르는 필드를 모드 전환 때 지우지 않는다
작성 ≠ 제출 가능  편집기에서 쓸 수 있다는 것이 현재 Actor 로 제출할 수 있다는 뜻이 아니다
```

마지막 줄이 네 단계 계약과 같은 말이다 — **AS2 로 표현할 수 있다 → 이 outbox 가 받는다 →
이 사용자가 할 권한이 있다 → 연합으로 배달한다** 는 자동으로 이어지지 않는다. `Accept(Follow)`
는 정상이고 `Reject(Reject)` 는 우리 outbox 가 원장 기록 전에 거부한다는 것이 그 예다
(`C2S.md` §5 "각 동사는 응답하거나 취소할 수 있는 대상만 받는다").

그래서 하네스가 화면보다 먼저다 — 거부된 요청이 원장과 큐를 건드리지 않고, 재시도가 활동을
늘리지 않고, 배달이 객체의 공개 범위를 몰래 바꾸지 않는다는 것을 확인하는 자리다.

## 11. 순서

```txt
1  기존 outbox 지원 범위 확인       타입별로 실제 왕복 · 조회 · 공개범위를 재 본다
2  공통 처리 연결 + Follow 수용      아래 참조 — "한 줄" 이 아니다
3  컴포저 UI                         Create(Note) · Like · Follow
4  Outbox 활동 로그 UI               이종 Activity · 수신처별 전달 상태
```

엔드포인트가 이미 서 있으므로 UI 가 처음부터 **진짜 경로**를 친다. 프론트앱은
같은 엔드포인트에 인증 수단만 바꿔 들어온다.

### "Follow 분기에 한 줄" 은 틀렸다 (정정)

기존 Follow 처리에 연결하더라도 **진입점 계약은 따로 검증해야 한다.**

```txt
대상 해소        URI 가 Actor 인가
차단 검사        양방향 fail-closed
중복 요청        같은 쌍에 live Follow 가 이미 있는가
승인 상태        pending 으로 남는가 즉시 accepted 인가
Undo 검증        대상과 저자가 맞는가
멱등성           Idempotency-Key 재시도가 두 번 기록하지 않는가
```

백엔드가 있다는 사실만으로 "UI 만 남았다" 고 확정하지 않는다. **타입별로 실제
outbox 왕복과 조회·공개범위를 재 본 뒤** 판단한다.

### 대상은 드롭다운이 아니라 URI 입력이다

Follow 를 고르면 전체 Actor 원장을, Like 를 고르면 전체 Object 레지스트리를
선택지로 내릴 이유가 없다. **클라이언트는 URI 를 입력하고 서버가 해소·검증한다.**

```txt
로컬 또는 유효한 캐시    내부 해소
미캐시 원격 URI          외부 조회 후 검증
무효 · 미지원 대상        구체적인 오류
일시적 조회 실패          대상 무효와 구분해 재시도 가능하게
```

**캐시됨 ≠ 지금 사용 가능.** 삭제·차단·오래된 캐시가 있으므로 최종 검증은 따로
한다. Follow 는 Actor 인지, Like 는 지원하는 Object 인지 각각 본다. 외부 조회는
SSRF 방어·리디렉션 제한·크기·시간 제한을 갖춘 **기존 원격 해소 경로를 재사용**한다.

UI 는 `URI 입력 → 선택적 미리보기 → 제출` 이면 된다. 미리보기가 성공해도 제출
시점에 권한과 대상 상태를 **다시** 검사하고, **미리보기가 Follow·Like 를 생성해서는
안 된다.**

### 지원 범위 조회 API — 하드코딩하지 않기 위해

컴포저가 무엇을 낼 수 있는지 서버에 물어야 한다. 답변의 소유는 나뉜다.

```txt
인증된 사용자 · 선택된 Account 의 Actor · 실행 권한    인증·Account 선택 계층 + Actors
Activity · Object 타입별 속성 정의와 값 형식           OP 어휘 레지스트리
인스턴스가 실제로 수용하는 C2S 동작                    Activities 제출 서비스
현재 주체가 그 Actor 로 실행할 수 있는 범위            Activities 가 권한·정책을 합성
```

**OP 가 아는 어휘와 서버가 구현한 기능은 다르다.** OP 에 `Article` 정의가 있어도
C2S `Create(Article)` 을 허용한다는 뜻이 아니다. 그러므로 클라이언트에 둘을
구분해 준다 — **타입 설명**(속성·필수 여부·자료형·참조 대상)과 **실효 지원 범위**
(지금 문맥에서 제출 가능한 Activity × Object 조합).

내부 이름이 무엇이든 **와이어에서는 `actor`** 다. 인증이 사용자를 확정하고,
acting Account 는 선택 문맥이 정하며, 서버가 그 Actor 로 실행할 권한을 검증한다.

Update 는 한 단계 더 있다 — **타입별 지원 여부만으로 수정 가능 여부를 정할 수
없다.** 대상 URI 를 해소한 뒤 저작 출처·소유권·저장 경로에 따라 그 객체에서
수정 가능한 속성을 답해야 한다 (7 참조).

이 조회 API 는 **UI 구성용 계약**이고, 제출 시 같은 규칙을 다시 집행한다. 거대한
동적 폼 엔진을 먼저 만들 필요는 없지만, 지원 목록을 컴포저 JS 에 하드코딩하지는
않는다.

### 대상의 소유와 행위의 소유는 다르다

Follow 의 대상 식별은 Actors 가, Like 의 대상 Object 해소는 OP 가 맡는다. 그러나
**Follow·Like 행위의 처리와 원장 기록은 Activities 책임**이다. 그리고 블록 UI 가
자기 REST 경로를 가지는 것 자체는 문제가 아니다 — 외부 계약이 달라도 **내부 처리
서비스를 공유**하면 된다. 모든 UI 를 outbox HTTP 호출로 통일하는 것과 처리 로직을
단일화하는 것은 별개다. 다만 **이번 컴포저는 실제 outbox 를 쓰는 클라이언트여야
한다.**
