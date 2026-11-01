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

## 3. 정해진 것

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

## 4. 정해지지 않은 것

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

## 5. 범위

```txt
안에   actor outbox 의 POST 엔드포인트          공개 C2S 표면. 이것이 기반이다
       Create(Note) 저작 → 로컬 Object 저장
       Actor 전환 · 인증(관리자 경로)
       컴포저 UI · Outbox 뷰                    증명의 본체. 빼면 증명이 없다
밖에   첨부                                     코어 미디어 API 연동이 필요하다
       Article · 답글
       Update · Like · Announce
       예약 발행 UI
       프론트앱 작성기 자체
```

성공 기준은 하나다. **Create(Note) 를 제출하면 로컬 Object 로 저장되고
Outbox 에서 보인다.**

**Todo PoC 의 함정을 여기서는 피한다.** Todo 는 관리자 전용 프록시 경로로
JMAP 을 증명했고, 표준 발견 경로(`.well-known/jmap`)는 비어 있는 채로 남았다.
재어 보고서야 바깥 클라이언트가 우리를 찾을 수 없다는 걸 알았다. 컴포저는
같은 길을 가지 않는다 — **관리자 화면이 공개 outbox 의 첫 번째 클라이언트일
뿐**이고, 편의용 우회 경로를 따로 두지 않는다.

### 첨부를 나중에 붙일 때 — 이미 정해진 것

첨부는 `object.attachment` 에 둔다. Activity 에 두지 않는다. `Create` 는
"누가 무엇을 만들었다" 는 행위의 래퍼이고, 본문과 미디어는 그 행위의 대상인
`Note` 에 속한다. Activity 에 두면 수신 측이 본문과 미디어의 관계를 해석할
근거가 사라진다. C2S.md §13 의 "attachment 은 파일 참조이지 발행물 참조가
아니다" 와도 이어진다.

## 6. 순서

```txt
1  로컬 Object 저작·저장          OP 로컬 Object 층
2  actor outbox POST 엔드포인트    인증 포함. 제출된 의도가 트리거다 (§9 의 빈칸)
                                   검증·원장·전달은 기존 경로에 연결
3  컴포저 UI                       그 엔드포인트의 첫 클라이언트
4  Outbox 뷰                       ledger 투영 + 수신처별 전달 상태
```

3 이 2 보다 뒤인 것이 핵심이다. 엔드포인트가 먼저 서야 컴포저가 **클라이언트**가
되고, 그래야 프론트앱이 같은 자리에 들어올 수 있다.
