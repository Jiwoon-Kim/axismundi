# Brief — 메일 텍스트 검색

> **범위**: 코어 + Tauri + GUI(셸 app bar 포함). app bar 검색 버튼 → 입력 dialog → Enter 로 닫고 결과를 기존 메일
> 목록 칸에. `Email/query` 의 `text` filter 와 `SearchSnippet/get` 강조. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md),
> 목록·선택은 [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md), 메서드 범위는 [MATRIX-JMAP-MAIL.md](MATRIX-JMAP-MAIL.md).

2026-11-24 작성·확정. "소유자 결정" 과 "측정" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 멈추고 보고한다.

## 소유자 결정

```txt
조건      text 하나.  from·날짜·첨부 등 조건 검색(→ filter chips·고급검색 dialog)은 이번에 없다
범위      현재 Account 의 전체 메일.  여러 연결·Account 동시 검색은 없다
          결과 행에 메일함 이름을 표시해 출처를 보인다
진입      app bar 의 검색 버튼.  입력은 MatDialog — 좁은 창 전체 화면, 넓은 창 app bar 아래에 붙는 형태
          Enter → dialog 닫고 결과를 기존 메일 화면 목록에.  결과를 dialog 안에 두지 않는다
          (선택·일괄 작업·열고 돌아오기가 목록에 있어서. 결과가 dialog 안인 명령 팔레트형은 내비게이션용)
일괄작업  읽음·별표 그대로.  Move to… 는 결과에서 비활성 — 여러 소속이 보이는 메일에서 어느 소속을 뺄지 결과만으로
          알 수 없다. 보이는 소속을 다 빼는 건 Trash 정책이지 일반 이동의 기본값이 아니다. 일반 이동은 그 메일함에서
          Move to Trash 는 결과에서도 제공 — 기존 정책 그대로: Trash 에 추가할 권한(mayAddItems) 확인, 보이는 소속 중
          뺄 권한(mayRemoveItems)이 있는 것만 제거. 권한 때문에 일반 메일함 소속이 남으면 결과에서 무조건 지우지 않고
          재동기화 결과를 따른다
Trash·Junk  기본 검색에서 Trash·Junk 에만 속한 메일을 뺀다: AND[text, inMailboxOtherThan[trash, junk]]
          (Inbox·사용자 폴더에도 속한 메일은 계속 찾힌다 — inMailboxOtherThan 은 "목록 밖 메일함에 하나 이상").
          role 이 없으면 그 id 만 생략.  Trash·Junk 포함 검색은 나중에 조건 검색(필터)에서
정렬      receivedAt 내림차순 고정.  목록은 메시지 단위(스레드 목록 보류)
```

## 측정 — Stalwart 1.1.0 (2026-11-24, alice Account c, 프로브 메일은 만들고 지움)

```txt
Email/query  text      일치는 토큰 전체만.  접두·형태소 없음:
                         "probe"=O  "prob"=X   "Running"=O "run"=X "meeting"(본문 meetings)=X
                         "회의록"=O "회의는"=O  "회의"=X  "안녕"(본문 안녕하세요)=X  "철수"(김철수)=X
                       from 이름·주소도 걸린다: "김철수" "chulsoo" "chulsoo@example.invalid" "example.invalid" = O
                       text "" → 0건(오류 아님)
             inMailbox 없이 Account 전체 = 동작(7건 vs Inbox 한정 2건).  canCalculateChanges: true
             AND[text, inMailboxOtherThan[trash, junk]] — Trash 로 옮기면 결과에서 빠짐(text 만이면 남음)
Email/queryChanges     text filter 그대로 동작: 새로 맞는 메일은 added, 위 AND 에서 Trash 로 옮긴 메일은 removed
SearchSnippet/get      subject·preview 가 HTML: <mark> 만 태그, 나머지는 이스케이프(&lt;script&gt; &amp;)
                       본문이 안 맞으면 preview: null, 제목이 안 맞으면 subject: null
                       강조는 쿼리보다 넓다 — text "회의" 는 0건인데 snippet 은 "회의록" "회의는" 에 mark
                       text 없는 filter 면 둘 다 null
```

토큰 일치·한국어 부분 일치 없음은 **서버 특성**이다. 클라이언트에서 접두 와일드카드·로컬 필터로 보정하지 않는다
(서버마다 다르게 틀린 결과가 된다). 업스트림 보고 여부는 소유자 판단.

## 이미 있는 것 — 읽고 시작

```txt
코어   mail.rs  query_args / query_key — filter 는 {inMailbox} 고정, key = "v1|inMailbox=…|sort=…|collapseThreads=…"
                 (key 는 직렬화하지 않고 고정 순서로 조립 — 주석 이유 그대로)
       cache.rs sync / more — 디스크 Store(SQLite, 프로필 파일) 의 listing 에 창·queryState
                move_emails(.., from, to, exclusive) — exclusive 면 from 을 쓰지 않는다(OnlyIn)
       query_changes — filter·sort·collapseThreads 를 그대로 실어 보낸다
Tauri  mail_sync / mail_more → ListView;  mail_move 는 from == to 를 거부(exclusive 에도 from 필수)
셸     app.component.html — mat-toolbar.app-bar(제목·spacer·Accounts 버튼), 화면별 슬롯 없음
GUI    mail-page: 목록 머리, 무한 스크롤(mail-paging.ts), 새로고침·푸시 reload, 행 선택·일괄 작업,
       행 템플릿 row.subject / row.preview(mail-page.component.html:221-222), row.mailboxIds 이미 있음
```

## 지시

1. **셸 검색 계약**: app bar 의 검색 버튼은 셸에 두고, 무엇을 찾을지는 지금 화면이 등록한다(작은 서비스:
   placeholder, 실행, 종료). 등록한 화면이 없으면 버튼을 두지 않는다. 지금 등록하는 건 Mail 뿐 — Contacts 등은 나중에
   같은 자리(Contacts 는 ContactCard 검색). 단축키는 이번에 없다.
2. **입력 dialog**: `MatDialog`. 좁은 창(기존 compact 기준과 같게) 전체 화면 — 뒤로 버튼 + 입력칸 + 지우기; 넓은 창은
   app bar 아래에 붙여(위치 지정, 백드롭 투명 또는 옅게) 같은 내용. 열 때 입력칸 포커스, 진행 중 검색어가 있으면 채워서.
   Enter → 공백뿐이면 무시, 아니면 닫고 실행. Esc·뒤로 → 닫기만(진행 중 결과는 그대로). chips 자리는 비워 둔다.
3. **검색어는 디스크에 쓰지 않는다.** 검색 listing(검색어가 든 key·ids·queryState)은 프로필 Store 에 저장하지 않는다
   — 메모리에만 두고 검색 종료·연결·Account 전환 시 버린다. 결과 Email 레코드는 메일함 목록과 같은 데이터라 Store 에
   들어가도 된다. 방식(메모리 listing 맵, 휘발 Store 분리 등)은 구현 판단 — 고른 이유를 보고. 검색어는 URL 에도 넣지 않는다.
4. **코어 쿼리**: filter = `AND[{text}, {inMailboxOtherThan: [trash, junk]}]`(role 있는 것만; 둘 다 없으면 `{text}`).
   첫 창 + `SearchSnippet/get` 을 한 요청에서(`#emailIds` back-reference). 이어 받기는 메일함 목록과 같은 규칙 — 같은
   queryState 필수, 다르면 다시 묻고 두 번째도 다르면 오류. 이어 받은 페이지의 snippet 도 같이.
   `unsupportedFilter` 등 method error 는 거부 문구로(원격 description 은 웹뷰에 넘기지 않는 기존 규칙).
5. **결과 화면**(기존 mail-page 목록 칸):
   - 목록 머리: `"{검색어}" · N results`(total 없으면 개수 생략) + 지우기 버튼. 트리에서는 선택된 메일함 없음.
   - 지우기 또는 메일함 열기 → 검색 종료, 직전 메일함 목록으로(캐시된 것).
   - 행: snippet 있으면 강조된 subject/preview, 없으면 평문. 메일함 이름 라벨 — `row.mailboxIds` 를 현재 메일함 목록
     이름으로, role 순서로 정렬, 공유 Account 에서는 보이는 것만(모르는 id 는 생략). 여러 개면 전부(넘치면 말줄임).
   - 0건: "No messages match" 한 줄(서버 특성 설명은 넣지 않음).
   - 결과 행을 열고 돌아와도 검색어·결과·스크롤 유지.
   - 첫 검색 표시는 첫 로드 spinner(가운데), 재실행·새로고침은 상단 spinner 규칙 재사용.
6. **Snippet 렌더링**: `innerHTML` 금지. 문자열을 `<mark>`/`</mark>` 로만 나누고 나머지는 엔티티 디코드한 텍스트로,
   템플릿에서 `<mark>` 요소로 그린다. `<mark>` 외 태그가 보이면 그 필드는 평문으로. 순수 함수로 빼서 테스트
   (이스케이프·짝 안 맞는 태그·중첩·빈 문자열).
7. **일괄 작업**: 읽음·별표·Move to Trash 활성(권한 판단·결과 반영은 소유자 결정 그대로 — 확정 이동도 `complete: false`
   로 기록하고 숨김은 재동기화가 정한다). `Move to…`(행·일괄·상세 툴바) 는 비활성 + 이유 툴팁
   ("Open the message's mailbox to move it" 류 — 기존 "숨기지 않고 비활성+이유" 규칙). Tauri `mail_move` 의
   from 필수 검사는 exclusive 일 때 출발 메일함 없이 부를 수 있게 바꾼다(비-exclusive 는 지금 그대로 from 필수).
8. **갱신**: 검색 중 푸시·새로고침 → 검색 listing 갱신(queryChanges 동작 측정됨, 메모리 listing 이면 첫 창부터 다시
   물어도 된다 — 이어 받은 길이 유지는 메일함 목록과 같게).

## 하지 않는 것

조건 검색·filter chips·고급검색 dialog, 여러 Account 검색, 결과에서 일반 이동, 정렬 선택, 검색 기록·추천·저장된 검색,
입력 중 자동 검색, 단축키, 로컬(오프라인) 검색, 접두/와일드카드 보정, 스레드로 접힌 결과.

## 검증

```txt
cargo test  코어: query key 에 text, snippet back-reference, 이어 받기 queryState; Tauri: exclusive 이동 from 없이
node --test "gui/src/**/*.spec.ts"   snippet 파서, 검색 상태 전이, 메일함 라벨
npm --prefix gui run build
E2E (ignored, Stalwart): text 검색 → 결과·snippet, 이어 받기, 검색 중 외부 생성 → 갱신
```

창에서(원격 디버깅 CDP), 좁은 창·넓은 창 둘 다:

1. 검색 버튼 → dialog(좁은 창 전체 화면 / 넓은 창 app bar 아래) → Enter → dialog 닫힘, 목록에 결과·강조·메일함 라벨.
   제목에 `<b>` 같은 문자열이 든 메일은 태그가 아니라 글자로 보인다.
2. 결과 열기 → 돌아오기 → 검색어·결과·스크롤 유지. 지우기 → 직전 메일함 목록.
3. Move to… 비활성·이유 표시. 읽음·별표 동작. Trash → 결과에서 빠진다(뺄 권한 없는 소속이 남는 경우는 재동기화대로 남는다).
4. 검색 중 외부 생성(맞는 메일) → 결과에 붙는다.
5. 검색 후 프로필 Store 파일에서 검색어 문자열을 찾는다 → 없어야 한다(known-positive: 일부러 저장한 문자열로 grep
   판정식을 먼저 확인).

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.
