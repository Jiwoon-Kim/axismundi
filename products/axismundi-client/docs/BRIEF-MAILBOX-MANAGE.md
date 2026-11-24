# Brief — 메일함 만들기 · 이름 변경 · 삭제

> **범위**: JMAP `Mailbox/set`(RFC 8621 §2.5)으로 메일함을 만들고(최상위·하위), 이름을 바꾸고, 빈 메일함을 지운다.
> 코어 → Tauri 명령 → GUI(메일함 트리). 규칙은 [gui/AGENTS.md](../gui/AGENTS.md), 쓰기 결과 규칙은
> [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md) "쓰기"·"일괄"·"이동", 트리는 같은 문서 "메일함 트리".

2026-11-24 작성. 실시간 갱신(BRIEF-LIVE-SYNC) 커밋 뒤에 시작한다 — 같은 Tauri 연결·메일 화면을 건드린다.
"소유자 결정" 과 "실측" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 지시를 고치지 말고 멈추고 보고한다.

## 소유자 결정

```txt
범위      만들기(최상위·하위) · 이름 변경 · 빈 메일함 삭제.
          부모 바꾸기(이동), sortOrder 편집, 공유 ACL, 구독, 내용째 삭제, Settings 화면은 다음 단계
진입점    만들기는 메일함 목록 위 머리줄("Mailboxes" + 보이는 `+`) — 처음 쓰는 사람이 찾아야 하는 동작이라
          우클릭에만 두지 않는다(Gmail 도 "+ 새 라벨" 을 보이게 둔다), Tab 으로도 닿는다.  지름길로 트리의 빈 공간
          우클릭 → "New mailbox".  부모는 다이얼로그에서 고른다.  메일함마다 "하위 만들기" 를
          두지 않는다(모든 행에 붙으면 트리가 지저분해진다 — 행 우클릭 메뉴에도 넣지 않는다).  이름 변경·삭제는 트리 행의 우클릭 메뉴(소유자
          결정, 데스크톱 앱).  키보드는 ContextMenu 키·Shift+F10, 터치는 길게 누르기로 같은 메뉴.  보이는 `⋮` 는
          두지 않는다 — 행마다도, 목록 머리에도.
          상단 FAB 는 메일 작성 전용.  Settings → Mail 은 나중(여러 메일함 일괄 관리가 필요해질 때)
역할      기본 None(role null).  고급 옵션으로 special-use 역할 선택 가능.  `shared`(Stalwart 전용)는 선택지에서
          뺀다 — 서버가 준 shared 메일함은 지금처럼 표시만
판단      권한·한도는 버튼 표시와 입력 검사에만 쓰고, 될지 안 될지는 서버가 정한다.  이미 쓰이는 역할도 클라이언트가
          막지 않는다 — 공유 Account 는 모든 메일함이 보이지 않고, 목록을 읽은 뒤 바뀔 수도 있다.  "사용 중" 표시만
삭제      onDestroyRemoveEmails: false 로만.  메일이 남았거나 하위 메일함이 있으면 서버가 거부 → 이유를 보여 준다.
          메일 영구 삭제·하위 연쇄 삭제는 이 단계에 없다
```

## 실측 (로컬 Stalwart 1.1.0, alice 세션) — 읽고 시작

```txt
Account mail capability   세 Account(c 본인, d team, b admin 공유) 모두
                          mayCreateTopLevelMailbox: true · maxMailboxDepth: 10 · maxSizeMailboxName: 255
                          b 는 alice 에게 Inbox 하나만 보이는데도 true — 광고값을 "된다" 로 읽지 말 것.
                          b 에서 최상위 생성을 실제로 시도해 무엇이 오는지 재고 기록한다
역할 거부                 Stalwart 관리 화면: "A mailbox with role 'inbox' already exists."  같은 Account 에 같은 role
                          둘은 RFC 8621 MUST NOT.  JMAP 응답의 SetError type·properties 를 재서 기록한다
Stalwart 가 받는 role      SpecialUse enum 11개: inbox trash junk drafts archive sent shared important memos scheduled
                          snoozed (crates/types/src/special_use.rs).  all · flagged 는 거부될 것으로 보이나 미측정 — 잰다
role 변경 보호            Inbox·Trash·Junk 의 role 은 바꿀 수 없다 (crates/email/src/mailbox/role.rs)
```

## 지시

### 1. 코어 (`jmap::mail` 아래, 예: `jmap/mail/mailbox.rs`)

- `create(parent, name, role)` · `rename(id, name)` · `destroy(id)` — 각각 `Mailbox/set` 하나.
  - `myRights` 등 서버가 정하는 속성은 보내지 않는다. `parentId` 는 최상위면 null.
  - 삭제는 `onDestroyRemoveEmails: false` 고정.
- 결과는 메일 쓰기와 같은 판정 틀: 확인 / 거부(SetError type + properties) / 실패 / 결과 모름.
  응답을 잃으면 **다시 보내지 않는다**:
  - 이름 변경: `Mailbox/get` 으로 그 id 를 읽어 이름이 바뀌었으면 확인, 아니면 그대로, 못 읽으면 모름.
  - 삭제: 그 id 가 notFound 면 확인(없어졌다는 결과는 같다), 있으면 그대로.
  - **생성: 확인할 수 없다.** 같은 이름의 메일함이 보여도 이번 요청이 만든 것인지 알 수 없다(다른 기기·사용자).
    결과 모름으로 남기고, 목록을 다시 읽게 한다. 자동 재시도 금지 — 같은 이름이 둘 생길 수 있다.
- 쓰기 뒤 메일함 캐시는 기존 Mailbox delta 동기화로 맞춘다(요청 본문을 캐시에 쓰지 않는다).
- Stalwart E2E(ignored, 자기 메일함만 만들고 지움): 최상위·하위 생성, 이름 변경, 빈 메일함 삭제, 메일 남은 메일함 삭제
  거부(`mailboxHasEmail`), 하위 있는 메일함 삭제 거부(`mailboxHasChild`), 쓰이는 role 생성 거부, `all`·`flagged`
  생성 결과, 공유 Account b 최상위 생성 결과, role 메일함 삭제 — **Inbox·Trash·Junk 는 destroy 하지 않는다**(fixture 의
  실제 Inbox 가 지워질 수 있다): 이 셋은 `Mailbox/get` 으로 `myRights.mayDelete` 만 읽어 기록하고, destroy 는 테스트가
  빈 role(예: `archive`)로 직접 만든 메일함에만 시도. 단위 테스트는 기존 scripted 서버로 판정 틀과 "생성은 재전송 안 함".

### 2. Tauri

- 명령 셋(`mail_create_mailbox`, `mail_rename_mailbox`, `mail_destroy_mailbox` 또는 하나로 — 판단).
- 리스트 응답에 Account 의 `mayCreateTopLevelMailbox`·`maxMailboxDepth`·`maxSizeMailboxName` 과 메일함별
  `mayCreateChild`·`mayRename`·`mayDelete` 를 더한다(지금은 mayAddItems·mayRemoveItems·maySetSeen 만). fixture 재생성.
- **거부 사유는 우리 문구로.** 기존 규칙대로 원격 응답 텍스트는 webview 에 넘기지 않는다(`keyword_results` 테스트가
  고정). SetError `description` 도 넘기지 않고 type·properties 로 문구를 고른다:
  `invalidProperties` + `role` → "이 역할은 이미 쓰이거나 서버가 허용하지 않습니다", `+ name` → 이름 문제,
  `mailboxHasEmail` → "메일을 먼저 옮기세요", `mailboxHasChild` → "하위 메일함을 먼저 지우세요", `forbidden` → 권한 없음,
  그 밖 → type 을 붙인 일반 문구. 다이얼로그가 해당 필드에 붙일 수 있게 properties 도 넘긴다.

### 3. GUI

- **만들기**: 메일함 트리 위에 작은 머리줄 — "Mailboxes" 라벨(M3 navigation drawer 의 섹션 헤드라인) + 끝에
  `+` 아이콘 버튼(툴팁 "New mailbox"). 사이드 패널과 좁은 창의 서랍 모두 같은 자리. Account 의
  `mayCreateTopLevelMailbox` 와 모든 메일함의 `mayCreateChild` 가 false 로 명시돼 만들 곳이 하나도 없을 때만 숨긴다.
  - 지름길: 트리의 빈 공간(행이 아닌 곳) 우클릭 → "New mailbox" 하나짜리 메뉴 → 같은 다이얼로그. 행 위 우클릭은
    아래 이름 변경·삭제 메뉴. 브라우저 기본 메뉴는 메일함 패널 안에서만 막는다.
- **새 메일함 다이얼로그**(Material dialog, Signal Forms):
  - 이름(필수).
  - 상위 메일함: 기본 "없음(최상위)". 최상위가 안 되면(`mayCreateTopLevelMailbox` false) 기본값 없이 선택 필수.
    선택지는 트리 순서, 경로로 표시(`Axismundi Probe / test`) — 같은 이름도 구분된다. `mayCreateChild` 가 false 로
    명시된 메일함은 비활성. 지금 열린 메일함을 기본으로 삼지 않는다(의도하지 않은 곳에 생긴다).
  - 고급 ▸ 역할: 접혀 있음. 기본 None. 아래 "역할 목록". 이 Account 에서 쓰이는 역할은 "사용 중" 표시만.
  - 보내기 전 검사: 빈 이름, `maxSizeMailboxName`(UTF-8 바이트), 부모 깊이 + 1 > `maxMailboxDepth`.
  - 거부되면 **다이얼로그를 닫지 않고** 해당 필드에 이유. 결과 모름이면 닫고 알림 + 목록 다시 읽기.
  - 만든 뒤 그 메일함을 연다(부모 펼침). 트리 포커스가 BODY 로 빠지지 않게(DESIGN-MAIL-GUI.md "메일함 트리" 함정).
- **이름 변경·삭제**: 트리 행 우클릭 → 그 행 대상의 메뉴: 이름 변경(`mayRename`) · 삭제(`mayDelete`).
  - 권한이 false 로 명시된 항목은 **숨기지 않고 비활성** — 메뉴 모양이 행마다 달라지지 않고, 왜 안 되는지 알 수 있다
    (M3·Apple HIG: 이 대상에서만 안 되는 동작은 비활성, 이 화면에서 성립하지 않는 동작만 숨김). 이유는 항목의 **둘째
    줄**(비활성 메뉴 항목엔 툴팁이 뜨지 않는다): "No permission to rename" / "No permission to delete".
  - null 은 활성(서버가 판단). 둘 다 비활성이어도 메뉴는 연다 — 반응이 없는 것보다 이유가 보이는 게 낫다.
  - role 만 보고 미리 막지 않는다(Inbox·Trash·Junk 라도 `mayDelete` 가 true 면 활성). 서버가 거부하면 우리 문구로.
  키보드는 포커스된 treeitem 에서 ContextMenu 키·Shift+F10, 터치는 길게 누르기.
  메뉴는 `mat-menu`(우클릭 위치에 띄우기 — cdk `cdkContextMenuTriggerFor` 가 설치본에 있으면 그것). 메뉴를 닫으면 포커스는
  그 treeitem 으로 돌아온다. 트리 행에는 버튼을 더하지 않는다 — 탭 정지는 treeitem 하나 그대로. 브라우저 기본 우클릭
  메뉴는 트리 행에서만 막는다.
  - 이름 변경: 이름 칸 하나인 다이얼로그(새 메일함 다이얼로그와 같은 검사).
  - 삭제: 확인 대화상자(메일함 이름, "비어 있어야 지울 수 있음"). 지운 메일함이 열려 있었으면 Inbox 로.
- 이 UI 는 Opus 가 창에서 다시 판단한다 — 배치·간격은 스크린샷으로 보고.

### 역할 목록 (고급 옵션)

IANA "IMAP Mailbox Name Attributes" 에는 메일함 용도(special-use)가 아닌 LIST 속성이 섞여 있다 — `HasChildren`,
`HasNoChildren`, `Noselect`, `NoInferiors`, `NonExistent`, `Marked`, `Unmarked`, `Remote`, `Subscribed` 는 넣지 않는다.
선택지: `inbox` `archive` `drafts` `sent` `junk` `trash` `important` `memos` `scheduled` `snoozed` `all` `flagged`
(소문자, RFC 8621 §2). 등록돼 있다는 것은 이름이 표준이라는 뜻일 뿐 서버가 만들어 준다는 뜻이 아니다 — 화면 문구도
그렇게. `flagged` 역할 메일함을 만든다고 $flagged 메일이 모이는 게 아니다(별표 보기는 별개 기능).

## 하지 않는 것

부모 변경, sortOrder, 공유 ACL·초대, 구독(isSubscribed), 내용째 삭제(onDestroyRemoveEmails: true), Settings → Mail
화면, `shared` 역할 생성, 다른 Account 로 옮기기.

## 검증

```txt
cargo test --workspace --lib        판정 틀 · 생성 재전송 금지 · 사유 매핑. 가드는 되돌려 깨지는지 확인
cargo test -p axismundi-client-core --test mail_cache_stalwart -- --ignored   (또는 새 E2E 파일)
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib     fixture: 권한 필드, 원격 문구 비노출
node --test "gui/src/**/*.spec.ts"  권한→메뉴 활성, 이름 바이트 길이·깊이 검사, 역할 목록(LIST 속성·shared 없음)
npm --prefix gui run build
```

창에서(원격 디버깅 CDP, DESIGN-MAIL-GUI.md "메일함 트리" 측정 경로): `+` 로 최상위 생성 → 트리에 나타나고 열림;
`+` 에서 상위 선택해 하위 생성 → 부모 펼침; 빈 공간 우클릭 → New mailbox 로 같은 다이얼로그; 우클릭 → 이름 변경 → 트리 갱신·포커스 유지; 메일 있는 메일함 삭제 → 거부 문구, 다이얼로그 유지;
빈 메일함 삭제 → 사라지고 열려 있었으면 Inbox; 쓰이는 역할로 생성 → 역할 필드에 거부; 트리 우클릭·Shift+F10 으로 같은 메뉴, 닫으면 포커스가 그 행으로.
만든 메일함은 끝에 지운다. 끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.
