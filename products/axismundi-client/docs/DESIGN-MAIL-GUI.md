# Design — Mail GUI 조립안

2026-11-21. 상태: **설계안**. 구현 전. 아래 "소유자 결정"만 확정이고 나머지는 제안이다.

## 소유자 결정 (2026-11-21)

```txt
클라이언트 성격   axismundi 플러그인(React)은 컴포넌트를 스펙대로 재발명하고 디자인에 투자한다.
                 클라이언트(Angular)는 디자인 투자를 최소로 한다.  Material·CDK 부품을 그대로 쓰고,
                 정말 없는 부품만 만든다.  그때만 플러그인 쪽 M3 측정값을 참고한다.
토큰            mat.theme() 의 --mat-sys-* 그대로.  플러그인의 --md-ref/--md-sys 를 이식하거나
                 연결층을 두지 않는다
도메인 전환      왼쪽 레일: ActivityPub → Mail → Contacts → Calendar, 하단 Settings
Mail 레이아웃    iCloud Mail 처럼 메일함 트리 · 메일 목록 · 메일 상세의 list-detail
```

## 레퍼런스에서 본 것 (2026-11-21 스크린샷)

| | 도메인 전환 | 메일함 | 계정 | 목록 | 상세 |
|---|---|---|---|---|---|
| Outlook(새 데스크톱) | 맨 왼쪽 앱 레일 | 즐겨찾기 + 계정별 트리 섹션 | **여러 계정을 한 트리에 동시에** 펼침 | 날짜 그룹(어제·이번 달), 2줄 행 | 오른쪽 읽기 창 |
| iCloud Mail(현재 웹) | 없음 | 시스템 메일함 → Folders | 단일 | 제목 위 "Inbox / 44 Messages", 검색은 목록 위, 3줄 행(보낸이·제목·미리보기) | 오른쪽 |
| Gmail | 앱 전환은 별도 레일 | 시스템 라벨 → 라벨 | URL 단위(`/u/0`) 한 번에 하나 | 카테고리 탭, 1줄 밀도 행 | 목록을 대체 |

가져오는 것: Outlook 의 **레일**, iCloud 의 **세 칸과 목록 머리(메일함 이름 + 개수) · 3줄 행**.
가져오지 않는 것: Outlook 의 리본(명령은 상세 툴바와 행 메뉴로), Gmail 의 카테고리 탭.

## 화면 구조

```txt
┌ app bar ───────────────────────────────────────────────── (avatar ▾) ┐
├rail┬ mailboxes ─────┬ list ───────────┬ detail ─────────────────────┤
│ AP │ Inbox      12  │ Inbox · 44      │ toolbar  reply · move…      │
│ M  │ Drafts      1  │ [search]        │ subject                     │
│ Ct │ Sent           │ ┌ sender  time ┐│ thread: message 1           │
│ Ca │ …              │ │ subject      ││         message 2 …         │
│    │ Folders        │ │ preview      ││                             │
│ ⚙  │                │ └──────────────┘│                             │
└────┴────────────────┴─────────────────┴─────────────────────────────┘
avatar 팝오버:  ActivityPub Actors (선택 1)  |  JMAP Accounts (선택 1)  — 서로 독립
```

- **셸은 상단 앱 바와 왼쪽 레일로 구성한다.** 레일은 ActivityPub, Mail, Contacts, Calendar
  순서이고 Settings는 하단에 고정한다. Inbox와 Outbox는 ActivityPub 내부의 하위 경로다.
  앱 바 오른쪽의 아바타 버튼은 Account 전환 팝오버를 연다. 내용 배치는 각 화면이 맡는다.
- **항목 노출 제안:** JMAP은 Session의 서버 capability와 해당 capability를 제공하는 Account가
  모두 있고 앱에 해당 화면이 구현된 경우에만 항목을 연다. Mail에서 선택한 Account가 Calendar를
  지원하지 않아도 같은 인증 프로필의 다른 Account가 지원한다면 Calendar를 숨기지 않는다.
  ActivityPub Inbox와 Outbox는 JMAP과 독립적으로, AP Session의 Actor와 각 컬렉션의 접근 가능 여부를
  기준으로 판단한다. 로딩·오프라인·재인증을 미지원으로 간주하지 않는다. Settings와 연결 관리는
  세션이 없어도 열 수 있게 한다.
- **인증은 공유할 수 있지만 세션과 선택은 별개다.** 공통 팝오버에서 ActivityPub Actor와
  JMAP Account를 별도 그룹으로 보여 주고, 각 선택을 독립적으로 유지한다. Actor 선택은
  ActivityPub에만, JMAP Account 선택은 Mail·Contacts·Calendar에 적용한다.
  이름이나 이메일로 연결하거나 한쪽 선택으로 다른 쪽을 전환하지 않는다.
  JMAP Session의 커스텀 `actorUri`는 제거했다(2026-11-21). GUI는 Account와 Actor를 따로 보여 준다.
  Mail Account가 바뀌면 메일함·목록·상세가 함께 바뀐다.
- **From 은 작성 화면에만 있다.** 현재 Account 의 `Identity/get` 그대로. 값은 `identityId`, 표시는
  이름 + 주소. 주소로 중복 제거하지 않는다(RFC 8621 은 email 중복을 막지 않고, fixture 에서
  `team@` 이 두 Account 에 다 있다). 다른 Account 의 Identity 와 합치지 않는다.
  메일함 트리 아래에 Account 의 주소(Identity)를 보여 주는 안은 작성·발송(EmailSubmission) GUI 때 본다
  (2026-11-22). Identity 는 메일을 담지 않으므로 트리에 두더라도 메일함이 아니라 "이 주소로 쓰기" 또는
  `to`/`from` 검색이다. 앱 바 메뉴의 JMAP 항목은 Account 다 — Stalwart 는 Account 이름에 주소를 넣어서
  Identity 처럼 보일 뿐이다.

## 창 폭별 접힘

M3 window size class 경계(compact <600 · medium <840 · expanded <1200 · large ≥1200)를 그대로 쓴다.
CDK `Breakpoints` 는 M2 구간(600/960/1280/1920)이라 쓰지 않고, 같은 수치를 `BreakpointObserver`
쿼리로 직접 준다. 수치 출처는 M3 원문(플러그인 `docs/SOURCE-M3-ADAPTIVE-LAYOUT-RAW.md`)이다.

```txt
large ≥1200      rail · mailboxes · list · detail 전부
expanded 840+    mailboxes 를 drawer(mat-sidenav mode="over")로 접는다 → rail · list · detail
medium 600+      list 와 detail 중 하나만.  detail 에서 뒤로 = list
compact <600     medium 과 같고 rail 도 drawer 안으로
```

**구현 (2026-11-21)**: 칸 개수는 위 window size class 그대로(`BreakpointObserver` 에 M3 쿼리 직접),
메일함은 `mat-sidenav` — large 에서 `side`, 그 아래 `over` + 목록 머리의 메뉴 버튼. 한 칸 모드의
상세에는 뒤로 버튼(목록 URL 로 이동). compact 에서 레일을 서랍에 넣는 것은 아직 안 했다(셸 몫).

**칸 안은 브레이크포인트 없이** — 목록 행 배치는 목록 칸 자신의 폭을 보는 container query 다(Gmail
의 "받은편지함 오른쪽" 배치가 목록이 좁아지면 1줄 행을 3줄로 바꾸는 것과 같은 축). 목록이 600px 이상이면
`보낸이 | 제목 — 미리보기 | 날짜` 한 줄, 그 미만이면 세 줄. 한 칸 모드에서 목록이 창 전체를 차지하면
저절로 한 줄이 된다. 창 폭이 아니라 칸 폭이 기준이라 칸 개수 규칙과 서로 묶이지 않는다.

각 칸의 최소 폭은 숫자를 먼저 박지 않는다. 목록 행에서 보낸이·시간·제목이 한 줄씩 읽히는 폭,
본문이 읽히는 폭을 실제 fixture 로 재서 정한다. 경계를 옮기는 근거도 그 측정이다.

## 부품 대응 (Angular Material / CDK 22.2.7, 설치본 확인)

| 자리 | 부품 | 직접 만드는 것 |
|---|---|---|
| 전체 배치 | CSS Grid + `BreakpointObserver` | 그리드 열 정의 하나. 범용 pane 라이브러리로 만들지 않는다 |
| 레일 | `mat-icon-button` + `routerLinkActive` + `matTooltip` | 세로 배치뿐. Material 에 nav rail 이 없다. 모자라면 그때 플러그인 `navigation_rail` 측정값 |
| 메일함 트리 | `mat-tree`(childrenAccessor) — `Mailbox.parentId` 로 구성, CDK tree 키보드 탐색 | role 순서(inbox·drafts·sent·junk·trash·archive 먼저)와 미읽음 수 |
| 접힌 메일함 | `mat-sidenav` | — |
| 앱 바와 Account 전환 | `mat-toolbar` + 아바타 버튼 + `mat-menu` | 우측에 배치, 아바타 없으면 이니셜 또는 계정 아이콘 |
| 목록 | `cdk-virtual-scroll-viewport` + 행 선택은 `cdk/listbox` 또는 `mat-selection-list` (가상 스크롤과 같이 되는지 먼저 확인) | 3줄 행 템플릿, 미읽음 굵기 |
| 검색 | `mat-form-field` + `matInput` | — |
| 상세 | `mat-toolbar` + `mat-menu` + 스레드는 `mat-expansion-panel` 후보 | 텍스트 본문 렌더(HTML 정책 전까지 plain text) |
| 작성 | `mat-dialog` 또는 라우트, From 은 `mat-select` | — |
| 상태 | `mat-progress-bar`, `mat-snack-bar` | 빈·로딩·오류·미캐시·오프라인 구분 문구(`gui/AGENTS.md`) |

## 라우트와 키

```txt
/activitypub                               레일의 ActivityPub 진입
/activitypub/inbox                          ActivityPub Inbox
/activitypub/outbox                        ActivityPub Outbox 진입
/mail                                     Mail 진입
/contacts                                 Contacts 진입
/calendar                                 Calendar 진입
/settings                                 Settings 진입

/mail/:profile/:account/:mailbox            목록
/mail/:profile/:account/:mailbox/:email     상세 (thread 는 email 에서 Thread/get)
```

- `:profile` 은 인증 프로필. 캐시 경계가 (인증 프로필, 서비스, accountId)라서 URL 도 같은 키를 든다.
  `Account.name` 은 라벨이라 URL 에 쓰지 않는다.
- 한 칸 모드의 뒤로 가기는 라우터 history 로 detail → list. 선택 상태를 URL 밖에 두지 않는다.

## 먼저 필요한 것 — Tauri 명령

현재 `src-tauri` 명령은 discover/connect/actor/outbox 뿐이고 Mail 은 하나도 없다. GUI 는 JMAP 을
직접 만들지 않으므로(`gui/AGENTS.md`), 코어에 이미 있는 Mail 기능을 이름 있는 명령으로 노출하는 것이
첫 작업이다: Session 의 Account 목록, 메일함(+changes), 목록 페이지(+queryChanges), Thread·본문,
키워드 변경, Identity, 제출, 실시간 구독. 명령 이름과 반환 형태는 구현 때 코어 API 에서 정한다.

## 미결

- 계정 안 A(동시 트리)·통합 받은편지함 — 한 번에 하나(앱 바 팝오버)를 써 본 뒤.

## 원격 바이트 — 언제 가져오고, 남기는가 (2026-11-21)

가져오는 시점과 기기에 남기는 것은 별개 설정이다(Gmail 도 외부 이미지 표시와 오프라인 저장을 따로 둔다).

```txt
JMAP 첨부 blob        동기화는 이름·크기·blobId 만.  바이트는 열기·저장 때 Rust 가 연결된 메일
                      서버에서 받는다.  디스크 캐시는 옵션 — (인증 프로필, 서비스, Account) 단위,
                      용량 한도와 비우기.  "다른 이름으로 저장" 한 파일은 캐시가 아니다
                      바이트를 통째로 IPC 로 넘기지 않는다.  큰 파일은 Rust 가 파일로 직접 쓴다
메일 외부 이미지      ┐ 같은 성격, 같은 설정 하나 ("항상 표시 / 표시 전에 확인").
AP Note attachment   ┘ 그 글을 열 때만 보는 내용물이라 서버가 대신 받아 둘 이유가 없다
                      (플러그인 Media Library FEDERATED-MEDIA.md "타입별 캐시 정책": Note 의
                      attachment 는 바이너리 캐시 안 함)
AP Actor icon/image   정체성 자산이라 다르다.  사용자의 Axismundi 서버가 축소본을 캐시해 두면
                      그것을 쓴다(Actors REMOTE-ASSET-CACHE.md).  없을 때만 원격에서 받는다
```

- WebView 에 원격 URL 을 그대로 넣지 않는다 — 화면을 그리기만 해도 제3자에게 요청이 간다. 받는 것은
  Rust 이고, 원본 AS2 문서의 URL 은 그대로 보존한다.
- Rust 가 받아도 원격 호스트는 사용자 IP 를 본다. 빠지는 것은 쿠키·referrer·자동 로드뿐이다.
  원격에 사용자를 드러내지 않는 경로는 자기 서버의 사본(Actor icon)뿐이다.
- 캐시는 추적 문제의 해결책이 아니다 — 첫 요청은 캐시를 켜도 발생한다.
- AP 미디어 요청에 JMAP·WordPress 자격증명을 붙이지 않는다. 제한 공개 첨부의 사본을 연결·권한
  경계를 넘어 재사용하지 않는다.

## 검증 서버

클라이언트 JMAP(Mail·Contacts·Calendar)은 **Stalwart 로 검증한다**. WordPress `axismundi-jmap` 은 이
GUI 의 검증 대상이 아니다 — ActivityPub 클라이언트를 붙일 때 같이 본다(소유자, 2026-11-21).
- 목록 선택 부품 — `cdk/listbox` 와 가상 스크롤의 조합을 실측해서 고른다.
- 세션 확인 전·오프라인 상태에서 레일 항목을 유지할지와, 설명이 필요한 미지원 상태의 표시 방식.
