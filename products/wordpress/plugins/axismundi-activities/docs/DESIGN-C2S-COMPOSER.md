# DESIGN — 관리자 C2S 컴포저와 Outbox

Todo PoC 가 관리자 페이지에서 JMAP 을 증명한 것처럼, ActivityPub C2S 도
관리자 페이지에서 증명한다. 목적은 제품이 아니라 **제출 경로의 증명**이고,
나중에 프론트앱에 작성기를 올릴 때 설계를 다시 하지 않는 것이다.

## 1. 왜 이 슬라이스가 의미 있나 — C2S.md §9 의 빈칸을 친다

현재 제출 경로의 실측 상태:

```txt
있음   axismundi_act_submit_c2s()      principal · actor identity · expected version
                                       can_act_as() 와 소스 권한과 버전을 검증
                                       Create · Delete 만
있음   유일한 어댑터 = 블록/클래식 에디터.  WP_Post 를 넘긴다
없음   outbox POST 엔드포인트
없음   Update
```

그리고 C2S.md §9 가 스스로 적어 둔 결함:

> 트리거가 아직 OP 의 `axismundi_op_object_publish_candidate` 훅이라, 발행이
> **제출된 의도가 아니라 저장을 관찰한 투영**에서 시작된다.

컴포저는 **두 번째 어댑터이자 첫 번째 진짜 의도**다. 사람이 "이걸 이 Actor 로
발행한다" 고 누른 것이 제출이 되는 경로를 처음으로 만든다. 그래서 이 슬라이스는
UI 작업처럼 보이지만 실제로는 §9 의 가장 큰 빈칸을 친다.

## 2. 정해진 것

```txt
제출 경로         actor 의 outbox 에 직접 POST 한다. 관리자 전용 우회 경로를
                  두지 않는다 — 이 컴포저는 나중에 클라이언트와 프론트앱이 설
                  자리를 먼저 만드는 것이다
저장              저작된 Object 는 **로컬 Object** 로 저장한다. wp_posts 를
                  거치지 않으므로 기존 activitySubmission 어댑터(WP_Post 를
                  받는 쪽)와 다른 경로다
Activity 선택     Create 부터. 드롭다운은 두되 지금은 Create 만 활성
Object 선택       Note · Article
본문 입력         HTML 또는 Markdown 으로 쓰되 **저장과 전송은 HTML**
첨부              object.attachment 에 넣는다. Activity 에 넣지 않는다
첨부 소스         미디어 라이브러리 피커. 업로더와 무관하게 전부 사용 가능
                  (사이트 공유자원 정책, 코어 동작 그대로)
alt text          첨부의 name 에 저장한다
Actor 전환        principal 과 acting Actor 를 **둘 다 명시**한다. 추론하지 않는다
Outbox 뷰         내가 발행한 Activity 역순 목록 + 전달 상태
                  = 페이스북 프로필의 활동 로그에 해당하는 것
```

### 첨부가 Activity 가 아니라 Object 에 붙는 이유

`Create` 는 "누가 무엇을 만들었다" 는 행위의 래퍼다. 본문과 미디어는 그 행위의
대상인 `Note` 에 속한다. Activity 에 `attachment` 를 두는 것이 문법적으로
불가능하지는 않지만, 수신 측이 본문과 미디어의 관계를 해석할 근거가 사라진다.

C2S.md §13 이 이미 정한 것과도 이어진다 — **attachment 은 파일 참조이지
발행물 참조가 아니다.** 미디어 라이브러리 항목을 붙인다는 것은 그 파일을
가리키는 것이지 그 첨부 페이지를 발행물로 가리키는 것이 아니다.

```json
{ "type": "Create",
  "actor": "https://example.com/@handle",
  "object": {
    "type": "Note",
    "attributedTo": "https://example.com/@handle",
    "content": "<p>…</p>",
    "attachment": [ { "type": "Image", "mediaType": "image/jpeg",
                      "url": "…", "name": "alt text" } ] } }
```

## 3. 정해야 할 것

### ① 예약 발행 — 백엔드는 이미 있다

프로토타입 UI 에서 빼는 것은 맞다. 다만 **"크론·큐가 없어서 뺀다" 는 틀린
이유다.** 이미 있다.

```txt
includes/scheduled-publication.php      229 줄
wp_ax_activity_submission 테이블        "Private scheduling intent,
                                         never an Activity or an attribution authority"
docs/BRIEF-C3-SCHEDULED-PUBLICATION.md
```

주석이 명시하듯 그 슬라이스는 **WordPress Post Create 어댑터/클록만** 제공한다.
컴포저가 저작한 로컬 Object 도 같은 제출 의도 테이블을 쓸 수 있는지는 어댑터를
하나 더 다는 문제이고, 이 슬라이스에서는 UI 를 내지 않는다.

### ② 인증 — 공개 엔드포인트이므로 이 슬라이스의 문제다

outbox 는 공개 표면이므로 인증을 미룰 수 없다.

```txt
관리자 페이지     쿠키 + nonce 로 같은 엔드포인트를 친다
외부 클라이언트   Application Passwords 또는 Bearer 토큰
공통              principal 과 acting Actor 를 둘 다 명시하고,
                  서버가 can_act_as() 로 다시 검증한다. 추론하지 않는다
```

최소선은 **관리자 화면이 쓰는 경로와 외부 클라이언트가 쓸 경로가 같은
엔드포인트**라는 것이다. 인증 수단만 다르다. 경로가 갈라지면 프론트앱이 쓸 때
다시 만들게 된다.

## 4. 범위 — 그리고 범위 밖이 남긴 위험

```txt
안에   actor outbox 의 POST 엔드포인트           공개 C2S 표면. 이것이 기반이다
       Create(Note) 저작 → 로컬 Object 저장
       미디어 첨부 · Actor 전환 · 인증
       Outbox 목록 + AS2 원본 보기 + 전달 상태
밖에   Update · Like · Announce
       예약 발행 UI
       프론트앱 작성기 자체
```

**Todo PoC 의 함정을 여기서는 피한다.** Todo 는 관리자 전용 프록시 경로로
JMAP 을 증명했고, 표준 발견 경로(`.well-known/jmap`)는 비어 있는 채로 남았다.
재어 보고서야 바깥 클라이언트가 우리를 찾을 수 없다는 걸 알았다. 컴포저는
같은 길을 가지 않는다 — **관리자 화면이 공개 outbox 의 첫 번째 클라이언트일
뿐**이고, 편의용 우회 경로를 따로 두지 않는다.

## 5. 순서

```txt
1  로컬 Object 저작·저장          OP 로컬 Object 층. wp_posts 를 거치지 않는다
2  actor outbox POST 엔드포인트    인증 포함. 제출된 의도가 트리거다 (§9 의 빈칸)
3  컴포저 UI                       그 엔드포인트의 첫 클라이언트
4  Outbox 뷰                       ledger 투영 + 전달 상태
```

3 이 2 보다 뒤인 것이 핵심이다. 엔드포인트가 먼저 서야 컴포저가 **클라이언트**가
되고, 그래야 프론트앱이 같은 자리에 들어올 수 있다.
