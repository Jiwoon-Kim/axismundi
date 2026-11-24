# Brief — Mail 실시간 목록 갱신 (RFC 8887 푸시를 GUI 에)

> **범위**: 코어 `jmap::websocket` 의 푸시를 Tauri 와 GUI 에 연결해, 사용자가 아무것도 하지 않아도 목록·미읽음
> badge·읽기 창이 서버 변경을 따라가게 한다. OS 수신 알림은 이 위의 별도 작업 — 이번엔 하지 않는다.
> 규칙은 [gui/AGENTS.md](../gui/AGENTS.md), 푸시 설계·증거는 [PLAN-JMAP-MAIL.md](PLAN-JMAP-MAIL.md) "RFC 8887 푸시".

2026-11-24 작성. "이미 있는 것" 과 "불변식" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 지시를 고치지 말고
멈추고 보고한다.

## 이미 있는 것 — 읽고 시작

```txt
코어   core/src/jmap/websocket.rs
         Client::push_channel(session, data_types, push_state, idle) → PushChannel
         PushChannel::next() → Event::StateChange{changed, push_state} | Event::Idle ;  close()
         endpoint() 는 Session 의 websocket capability 에서만, apiUrl 과 같은 origin 만 (인증이 업그레이드에 실림)
CLI    cli/src/main.rs  watch() · watch_loop()  — 루프가 지금 CLI 에만 있다
         순서: 구독(연결) → 따라잡기 sync.  처음과 매 재연결 모두.  Transport 오류만 1→60초 백오프 재시도,
         업그레이드 거부·서브프로토콜 불일치·RequestError 는 종료.  이 순서를 고정하는 테스트가 있다
         (2026-11-21 Codex 리뷰로 "sync 먼저" 에서 바뀜 — 사이에 온 변경이 사라지던 결함)
Tauri  connection.rs  Active{ mail: Option<Mail> } — 로그인(연결) 하나에 Mail 캐시 하나
       mail.rs        mail_sync / mail_view / mail_read ... — GUI 가 부르는 동기화는 이미 delta 기반
GUI    MailActions.changed — 확인된 쓰기 뒤 올라가는 신호.  목록(view.reload)과 읽기 창(detail/thread reload)이
       이걸 보고 다시 읽는다.  reload 는 화면의 행을 유지한다
       Tauri 이벤트 수신(@tauri-apps/api/event listen)은 아직 어디에도 없다
```

## 불변식 (어기면 멈추고 보고)

```txt
순서     구독 → 따라잡기.  재연결마다도.  GUI 쪽 따라잡기 = 보고 있는 뷰 reload.  "sync 먼저" 로 되돌리지 않는다
한 곳    루프(연결·백오프·종료 판정·따라잡기 순서)는 코어 하나에만.  CLI watch_loop 를 코어로 옮겨
         CLI·Tauri 가 같이 쓴다.  옮길 때 기존 순서 테스트도 같이 옮기고, 옛 순서로 되돌리면 깨지는지 다시 확인
푸시 = 깨우기  StateChange 는 "무엇이 바뀌었나" 뿐.  화면은 기존 mail_sync(delta) 로만 바뀐다.  푸시 본문을 화면 값으로 쓰지 않는다
범위     연결(로그인) 단위.  StateChange 의 changed 는 Account 별 — 지금 보는 Account 가 바뀌었을 때만 reload.
         로그아웃·연결 전환·앱 종료에서 루프가 멈춘다(늦게 온 이벤트가 새 연결 화면을 건드리지 않게 — 이벤트에
         connectionId 를 싣고 GUI 가 현재 연결과 비교)
보존     reload 는 열어 둔 메일·선택·결과 모름 잠금·트리 펼침·키보드 포커스를 유지한다 (지금 changed 경로가
         이미 그렇다 — 같은 경로를 쓴다).  사라진 행은 기존 effect 가 선택에서 뺀다
자격증명  푸시 연결은 Active 가 이미 가진 Client 를 쓴다.  새 자격증명·새 인증 경로를 만들지 않는다
```

## 구현 지시

1. **코어:** `watch_loop` 와 `Push` trait 을 `jmap::websocket` 으로 옮긴다(이름은 판단). 연결·sync·대기·종료 신호를
   주입받는 형태 유지. 멈춤 신호(예: `AtomicBool` 또는 채널)를 받아 다음 이벤트·대기 사이에 확인. CLI `mail-watch` 는
   옮긴 것을 쓰고 동작이 같아야 한다.
2. **Tauri:** Mail 이 있는 연결이 성립하면 백그라운드 스레드에서 루프를 돈다. sync 콜백은 캐시를 직접 sync 하지 말고
   GUI 에 이벤트를 보낸다(`mail-changed {connectionId, accountIds, reason: "catch-up"|"push"}`) — 동기화는 GUI 의
   mail_sync 가 하던 대로. 연결이 바뀌거나 로그아웃하면 멈춤 신호. `Active` 가 사라질 때 스레드가 남지 않게.
   푸시를 광고하지 않는 서버(`websocket` capability 없음·supportsPush false)는 조용히 생략 — 오류 알림 금지.
3. **GUI:** `listen("mail-changed")` 를 MailActions(또는 연결 서비스)에 한 번 둔다. 현재 연결·Account 와 맞을 때만
   기존 갱신 신호를 올린다. 연속 이벤트는 합친다 — 진행 중 reload 가 있으면 하나만 예약. 사용자가 일으킨 쓰기의
   `changed` 와 같은 경로라 화면 보존 규칙이 그대로 적용되는지 확인.
4. **문서:** DESIGN-MAIL-GUI.md 에 "실시간 갱신" 절. PLAN-JMAP-MAIL.md 푸시 절에 "루프가 코어로 옮겨짐" 한 줄.

## 하지 않는 것

OS 알림, 새 메일 소리·배지 숫자 외 표시, WebSocket 위의 JMAP 요청, EventSource(RFC 8620 §7.3) 대체 경로,
pushState 영속(앱 재시작 후 재개), 오프라인 큐.

## 검증

```txt
cargo test --workspace --lib            옮긴 루프 테스트 포함. 순서를 되돌리면 실패하는지 변이 확인
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib
node --test "gui/src/**/*.spec.ts"      이벤트 합치기·연결 비교를 순수 함수로 뺐다면 spec
npm --prefix gui run build
```

Tauri 창에서 (로컬 Stalwart, alice 또는 admin). 창 내부는 computer-use 로 devtools 를 못 보므로
`WEBVIEW2_ADDITIONAL_BROWSER_ARGUMENTS=--remote-debugging-port=9222` 로 tauri dev 를 띄우고 CDP 로 DOM 을 읽는다
(DESIGN-MAIL-GUI.md "메일함 트리" 의 측정 경로):

1. 창을 Inbox 에 둔 채 외부에서 메일 생성(기존 `stalwart-fixture-send.ps1` 또는 JMAP Email/set) → 손대지 않아도 행과
   badge 가 바뀐다. 외부에서 삭제 → 빠진다.
2. 메일을 열고 선택 모드로 몇 개 고른 상태에서 1 을 반복 → 읽기 창·선택·포커스 유지.
3. Caddy(`axismundi-stalwart-https`) 를 멈춘 사이 Stalwart 에 직접(18880) 메일 생성 → Caddy 재시작 → 재연결 직후
   push 없이도 반영(따라잡기).
4. 로그아웃 또는 연결 전환 → 이전 연결의 스레드가 멈춘다(로그·CDP 로 확인), 새 화면에 이전 이벤트가 닿지 않는다.

끝나면 바꾼 파일, 창에서 본 것, 못 본 것을 보고한다. 커밋은 사용자 승인 뒤.
