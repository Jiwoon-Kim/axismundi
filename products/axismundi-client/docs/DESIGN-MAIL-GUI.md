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
  JMAP Session의 커스텀 `actorUri`는 제거 대상이며 GUI는 이 필드에 의존하지 않는다.
  Mail Account가 바뀌면 메일함·목록·상세가 함께 바뀐다.
- **From 은 작성 화면에만 있다.** 현재 Account 의 `Identity/get` 그대로. 값은 `identityId`, 표시는
  이름 + 주소. 주소로 중복 제거하지 않는다(RFC 8621 은 email 중복을 막지 않고, fixture 에서
  `team@` 이 두 Account 에 다 있다). 다른 Account 의 Identity 와 합치지 않는다.

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
- `actorUri` 제거 — 소비자가 셋이다: WP `axismundi-jmap` Session 투영(`session.php`, 감사 2개,
  `IMPLEMENTATION-SESSION.md`), 코어 `lib.rs` 역직렬화, 현재 GUI 의 Account↔Actor 짝짓기
  (`app.component.ts`). 서버에서 떼기 전에 GUI 짝짓기부터 없앤다(역순이면 연결 확인 화면이 조용히 짝을 잃는다).
  별도 작업으로 한다(소유자, 2026-11-21).

## 검증 서버

클라이언트 JMAP(Mail·Contacts·Calendar)은 **Stalwart 로 검증한다**. WordPress `axismundi-jmap` 은 이
GUI 의 검증 대상이 아니다 — ActivityPub 클라이언트를 붙일 때 같이 본다(소유자, 2026-11-21).
- 목록 선택 부품 — `cdk/listbox` 와 가상 스크롤의 조합을 실측해서 고른다.
- 세션 확인 전·오프라인 상태에서 레일 항목을 유지할지와, 설명이 필요한 미지원 상태의 표시 방식.
