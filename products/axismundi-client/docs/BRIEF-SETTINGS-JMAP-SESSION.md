# Brief — 연결 상세: JMAP Session 탐색 화면

> **범위**: GUI(설정 화면). Settings → Connections 에서 연결 하나를 열어, 그 연결이 받은 JMAP Session 을 구조화해
> 보여 주는 읽기 전용 상세 화면. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md), 연결 모델은
> [DESIGN-CONNECTION-DISCOVERY.md](DESIGN-CONNECTION-DISCOVERY.md)("연결 관리 UI").

2026-11-27 작성. "소유자 결정"·"근거"는 확정, "지시"는 착수 지시다. 측정이 지시와 어긋나면 멈추고 보고한다.

## 소유자 결정

```txt
위치      연결마다 상세 화면 /settings/connections/:id.  연결 목록 행에 "Details".  상세를 열어도 앱의 활성 연결·
          선택한 Account 는 바뀌지 않는다(그 연결의 Connected 를 읽기만)
형태      탭 4개: Overview(엔드포인트 포함) · Accounts · Capabilities · Session.  capability 는 Account 단위라
          Accounts 탭을 둔다(개인·공유·읽기 전용·primary 는 Account 의 성격) — 시안의 Endpoints 탭은 Overview 로 합침
데이터    Session 한 번 받은 것만으로 그린다 — 새 IPC·탭별 네트워크 요청 없음
참고      소유자 시안 axismundi-jmap-ui.html(정적 프로토타입) + 설계 대화.  정보 구조는 따르되 탭은 4개로 줄이고, 데이터·시각 스타일(자체 색·폰트·이모지 아이콘)은 따르지 않는다
```

## 근거 — Session 에 있는 것과 없는 것

`ConnectionService.connections()` 의 각 `Connected` 가 이미 `jmap_document`(Session JSON 원문)와 Account 요약을 들고
있다(`gui/src-tauri/src/connection.rs` `connect`). 시안 항목을 Session 필드에 대조한 결과:

```txt
Session 에서      username · apiUrl 오리진 · uploadUrl · downloadUrl · eventSourceUrl · state · capabilities(서버) ·
                  accounts[id].{name,isPersonal,isReadOnly,accountCapabilities} · primaryAccounts ·
                  WebSocket url·supportsPush(…:websocket) · webpush-vapid 키 · Principal ID(…:principals:owner
                  principalId / …:principals currentUserPrincipalId) · 서비스별 세부값(정렬 옵션·첨부 한도·
                  maxDelayedSend·submissionExtensions·FileNode 금지 문자·Sieve 확장·Metadata dataTypes 등)
클라이언트가 앎    연결 상태(로드됨) · 앱 구현 여부(Client support) · URN 표시 이름·묶음·서비스→데이터 타입
요청이 필요 — 제외 저장 용량·사용량(Quota/get) · Principal 이름·상세(Principal/get)
시안의 잘못       "Stalwart v1.0.0" 은 서버 이름·버전이 아니라 urn:ietf:params:jmap:sieve 의 implementation
                  문자열 — 머리에 서버명으로 쓰지 말고 Sieve 항목 안에 그 이름 그대로.  "18 / 20" 같은 고정 숫자,
                  하드코딩한 capability 집합은 옮기지 않는다
```

## 지시

1. **라우트·진입**: `/settings/connections/:id`(id = 실행 중 연결 id; 없으면 목록으로). 탭은 쿼리(`?tab=`)로 —
   Overview 의 서비스 카드에서 Capabilities 의 해당 묶음으로 바로 간다(`?tab=capabilities&group=mail`). 연결 목록 행에
   `Details` 버튼. 머리: 사용자명 · 서버 오리진 · 연결 상태. JMAP 이 없는 연결은 기존 WordPress/AP 원문 패널만.
2. **순수 모델**(`settings/jmap-session-view.ts` + spec, Angular 없이 `node --test`): Session JSON → 개요·교차표·
   제한·엔드포인트·Account 상세. fixture 는 **실제 Stalwart Session** 둘 — admin(Account 하나)과 alice(여러 Account, 공유
   Account 포함). 읽기 전용 Account 가 실측에 없으면 그 한 경우만 합성.
3. **URN 레지스트리 — 표시 전용**(`settings/jmap-capabilities.ts`): URN → 표시 이름 · 묶음(Mail · Calendars ·
   Contacts · Files & storage · Principals · Metadata · Core & transport · Vendor extensions) · 알려진 필드의 표시
   형식. **활성화 정책은 넣지 않는다**(어떤 앱을 켤지는 지금처럼 연결·Account 의 capability 로 정한다 — 섞으면 표시와
   권한 판단이 한 표에 묶인다). 등록 안 된 URN 은 원래 URN 과 값을 범용 렌더러로.
4. **값 표시 형식**(읽기 전용이 보이게): 불리언 = 아이콘+문구(스위치·체크박스 금지), 바이트 = 10진 단위(MB),
   ISO 기간(P52W1D) = 사람이 읽는 기간, 문자열 배열 = 칩(많으면 접기), 객체 = 중첩 속성 목록, 빈 객체 = "Advertised",
   `null` = "Not provided", URI·템플릿 = 고정폭 + 복사. 긴 URN 은 줄바꿈되게.
5. **탭별 내용**
   - **Overview**: 연결 요약, 이 연결로 쓸 수 있는 앱(앱에서 열림 / 서버만 광고 / 미지원 — 레일과 겹치지 않게 "왜"를
     보이는 쪽), 전송(WebSocket push · EventSource · Web Push), core 제한(호출 수·동시 요청·동시 업로드·Get/Set 개수·
     요청·업로드 크기), 엔드포인트(API · Upload · Download · EventSource · WebSocket — 템플릿 변수 그대로, 복사 버튼,
     링크로 열지 않음).
   - **Accounts**: Account 목록(개인·공유·읽기 전용, 이 Account 가 primary 인 capability) → 하나 고르면 속성(ID·
     Principal ID)과 그 Account 의 accountCapabilities 세부값(묶음별). 이 선택은 화면 안의 보기 선택이지 앱의 Account 선택이 아니다. 아래에 primaryAccounts 표.
     `isReadOnly: false` 를 "모든 객체 쓰기 가능"으로 쓰지 않는다(개별 권한은 객체의 myRights).
   - **Capabilities**: Accounts 와 같은 데이터를 URN 쪽에서 본다("Calendars 는 어느 Account 에 있나"). 검색(URN·표시
     이름) + 묶음별 접기. 열 = Server(capabilities) · Account(위에서 고른 Account 의
     accountCapabilities) · Primary(primaryAccounts 값) · **Client**(이 앱 구현 여부). 행을 펼치면 그 URN 의 서버값과
     Account 값을 위 형식으로.
   - **Session**: 원문 JSON 읽기 전용 + 복사. `state` 를 보이고, 다시 받기는 다음 범위(지금은 연결 시점의 값이라고
     한 줄).
6. **Client support 목록**: 앱이 실제로 쓰는 capability 를 코드에서 확인해 상수 하나로(코어 요청의 `using` 과 GUI
   기능에서 grep 으로 확정 — 추측 금지). 서버 광고는 됐지만 앱에서 아직 못 여는 것(예: Calendars)은 "Not in this app".
7. **부품**: Angular Material — `mat-tab-nav-bar` 또는 `mat-tab-group`, `mat-expansion-panel`, `mat-table`, chips,
   카드. 색·글자는 `--mat-sys-*`. 좁은 창에서 탭이 스크롤되고 긴 URN·URL 이 가로 스크롤을 만들지 않게.

## 다음 — Capabilities 기본 보기와 Compare accounts 분리 (소유자 결정 2026-11-28, 미구현)

지금(2026-11-28 커밋)은 Account 를 최대 4개 열로 나란히 놓는 비교표가 기본이다. Account 가 늘면(공유받은 소유자마다
Account 가 붙는다) 열 비교가 무의미해지므로 나눈다. 모델 `compareCapability`·`creationPermissions` 와 테스트는 재사용.

```txt
기본 보기   Account 하나: 기능 | Server | (고른 Account) | Accounts 수 | Default account | App.  고른 Account 는 쿼리
            account(Accounts 탭과 공유) — 처음엔 personal 우선, 없으면 첫 Account.  행을 펼치면 광고하는 Account 목록과 값
서버 수준   서버에만 있고 어떤 Account·primaryAccounts 에도 없는 행(Core, VAPID)은 Account 칸·수·Default 를
            Not applicable — URN 목록이 아니라 데이터로 판정(Stalwart 는 websocket 을 Account 에도 넣는다)
Compare     "Compare accounts" 로 열 때만: 지금 보던 Account 가 첫 선택, 2~3개.  2개 전에는 비교표·Differences only 를
            숨기고 "하나 더 고르세요" 안내.  차이 0행이면 "선택한 Account 간 차이 없음".  Advertised creation permissions
            표도 이 안으로(기본 보기에선 Accounts 탭의 Account 상세에 그 Account 하나의 생성 권한).  선택은 쿼리 compare=b,d
다듬기      Default account 칸은 한 줄 + 말줄임(ID 는 툴팁), 서비스 카드의 no-account / not-in-app 아이콘 구분,
            Principal owner 처럼 Account 마다 다를 수밖에 없는 식별자는 Differences only 에서 빼는 것 검토
불변        어디서 고르든 앱의 활성 Account·서버 primaryAccounts 는 바뀌지 않는다
```

## 하지 않는 것

Quota/get·Principal/get, Session 다시 받기, capability 기반 앱 활성화 정책 변경, 새 앱(Calendar·Files) 열기, 설정 편집,
WordPress·AP 상세 화면 구조화(원문 패널 유지), 서비스별 전용 렌더러(Mail 정렬·FileNode·Metadata 매트릭스·Sieve 확장은
첫 버전에 범용 렌더러로 — 보기 나쁘면 그때 전용으로).

## 검증

```txt
node --test "gui/src/**/*.spec.ts"   모델: 교차표(서버만·Account 만·둘 다), 모르는 URN, 공유·읽기 전용, 형식 변환
npm --prefix gui run build
```

창에서(CDP): Stalwart 연결 → 목록의 Details → 네 탭 → Overview 서비스 카드에서 Capabilities 묶음으로 이동 →
Account 바꿔 값 비교 → 엔드포인트 복사 → 상세를 연 뒤에도 앱의 활성 연결·Account 가 그대로인지. 1280 과 400.
끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.
