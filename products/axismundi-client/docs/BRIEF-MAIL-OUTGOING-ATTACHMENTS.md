# Brief — 보내는 첨부 (작성 2단계 ①, 평문 작성 창)

> **범위**: 코어 + Tauri + GUI. 지금의 평문 작성 창(BRIEF-MAIL-COMPOSE)에 일반 첨부를 더한다 — 파일 선택·끌어다 놓기,
> 업로드 진행·실패·재시도, 첨부가 든 초안 저장·다시 열기·발송. 리치 텍스트·인라인 이미지는 다음 단계
> ([DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md) "작성기 — 리치 텍스트 방향"). 규칙은 [gui/AGENTS.md](../gui/AGENTS.md).

2026-11-26 작성. "소유자 결정" 과 "측정" 은 확정, 나머지는 착수 지시다. 측정이 지시와 어긋나면 멈추고 보고한다.

## 소유자 결정 (이미 기록된 것)

```txt
순서        받은 첨부 → 보내는 첨부(이번) → 에디터 비교 → 리치 텍스트
업로드 시점  파일을 고르면 바로 uploadUrl 로 올리고 받은 blobId 를 작성 창 항목에 둔다.  이후 초안 저장은 그 blobId 를
            참조(재업로드 없음) — Bulwark 와 같은 흐름.  JMAP 이 정한 건 "초안이 참조하기 전에 blob 이 있어야 한다" 뿐
상태 구분    업로드 성공 ≠ 첨부가 든 초안 저장 성공.  둘을 따로 표시.  업로드 중·실패 첨부를 빼고 조용히 발송 금지
자동 저장    첨부만 추가해도(제목·본문 비어도) 초안 저장 대상
미참조 blob  버린 첨부의 blob 은 지우지 않는다 — Stalwart 가 만료·미참조 blob 을 정리한다(Data Cleanup 스케줄)
업로드 영역  JMAP uploadUrl 만.  WP Media·AP uploadMedia 와 섞지 않는다
첨부 표시    본문 밖 카드(이름·크기·유형 아이콘/썸네일·상태·제거).  받은 첨부 카드와 같은 부품
```

## 측정 — Stalwart 1.1.0 (2026-11-26, alice)

```txt
core        maxSizeUpload 50000000, maxConcurrentUpload 4, maxSizeRequest 10000000
mail(c)     maxSizeAttachmentsPerEmail 50000000
submission  submissionExtensions.SIZE 값 없음([])
blob 정리   업로드 만료 시간은 측정 전(참고용 — 이번 작업 중 한 번 재 볼 것 — 올리고 참조 없이 둔 blob 이 언제 notFound 가 되나)
```

## 이미 있는 것 — 읽고 시작

```txt
코어   jmap::blob::upload(session, account, type, bytes) — maxSizeUpload 검사, 업로드 오리진 확인
       mail::compose — 초안 저장(생성 확인 뒤 이전 초안 파괴), 발송(저장 → 제출), source(초안 다시 열기)
       compose.rs:135 — **첨부가 있는 초안은 지금 다시 열기를 거부한다**(평문 작성 창 전제) → 이번에 바꾼다
       blob_ledger — 받은 첨부 원장(이번에 업로드한 바이트도 넣을지는 지시 6)
Tauri  mail_compose_save / mail_compose_source, 첨부 카드(mail-body.component)
GUI    compose-dialog.component.ts (229줄) — From·To·Cc·Bcc·제목·본문 textarea
설정   tauri.conf.json windows[0] — dragDropEnabled 미지정(기본 true: Tauri 가 창 단위로 파일 드롭을 가로챈다)
```

## 지시

1. **초안 모델**: 작성 창 상태에 첨부 목록 — 항목마다 `{localId, name, type, size, state: uploading|uploaded|failed,
   blobId?}`. 초안 저장의 Email 생성은 `bodyStructure`(multipart/mixed: text/plain 본문 + 첨부 파트들 `blobId`·`type`·
   `name`·`disposition: attachment`)로. 첨부가 없으면 지금 모양 그대로.
2. **업로드(Rust) — 파일 핸들**: 웹뷰가 바이트도 **경로도** 들고 있지 않게. Rust 는 **OS 파일 선택 대화상자나 네이티브
   드롭에서 받은 파일만** 등록하고 불투명한 핸들을 돌려준다. 웹뷰는 경로를 넘기지 않고 그 핸들로 업로드·취소·재시도를
   요청한다(웹뷰가 준 임의 경로를 Rust 가 읽는 명령은 만들지 않는다). 핸들은 작성 창 수명 동안만 유효. 동시 업로드는
   `maxConcurrentUpload` 이하. 크기는 고르는 순간 `maxSizeUpload` 와 메일당 합계 `maxSizeAttachmentsPerEmail` 로 막고
   이유를 표시. 취소(항목 제거)되면 진행 중 업로드를 끊는다.
3. **입력 경로 — 측정 먼저**:
   (a) 클립 버튼 → OS 파일 선택 대화상자(경로) → 지시 2.
   (b) 끌어다 놓기 — Tauri 기본(창 단위 가로채기, 경로가 옴)과 HTML5 `DataTransfer`(웹뷰 안 위치별, File 객체) 중
       WebView2 에서 실제로 무엇이 오는지 재 보고 고른다. 이번 범위는 "작성 창 아무 데나 놓으면 일반 첨부" 하나라
       위치 구분이 필요 없다 — 경로가 오는 Tauri 방식이면 지시 2 를 그대로 쓴다. 다음 단계(인라인 이미지)에서 본문
       위치가 필요해지므로 두 방식의 차이(좌표·파일 객체·큰 파일 메모리)를 보고에 적을 것.
   붙여넣기(클립보드 파일)는 이번에 없다 — 인라인 이미지 단계에서.
4. **저장·발송 규칙**:
   - **작성 창당 저장은 한 번에 하나만**(직렬화). 저장 중에 내용·첨부가 바뀌면 끝난 뒤 최신 상태로 한 번 더 저장한다.
     "새 초안 생성 확인 뒤 이전 초안 파괴" 구조라 요청이 겹치면 중복 초안이 남거나 최신 첨부가 빠진다.
   - 발송은 진행 중인 저장을 기다린 뒤 **최신 상태를 반영한 저장 결과(초안 id)** 로 제출한다.
   - 자동 저장은 업로드가 **끝난** 첨부만 참조한다. 업로드 중인 첨부가 있으면 저장은 하되 "첨부 N개 업로드 중" 을
     표시하고, 완료되면 한 번 더 저장.
   - 발송 버튼: 업로드 중이거나 실패한 첨부가 있으면 비활성 + 이유(실패 항목은 재시도 또는 제거해야 발송 가능).
   - 발송 직전 저장도 지금처럼 "새 초안 확인 뒤 이전 초안 파괴" — 첨부 파트가 바뀌어도 같은 규칙.
5. **업로드됨·초안 미저장 상태**: 업로드는 성공했지만 그 첨부가 든 초안 저장이 아직 성공하지 않은 상태를 따로 둔다.
   - 저장이 실패하면 "저장됨" 으로 표시하지 않는다. 이 상태로 창을 닫으려 하면 확인을 받는다("첨부가 초안에 저장되지
     않았습니다").
   - 다시 저장할 때 서버가 그 `blobId` 를 거부하면(만료 등 — notCreated 의 blobNotFound 류) 그 항목을 실패로 바꾸고, 원본
     파일(핸들이 살아 있으면)로 다시 올리거나 제거할 수 있게 한다.
   - blob 정리 만료 측정은 참고용 — 완료 조건이 아니다.
6. **초안 다시 열기**: `compose.rs:135` 의 거부를 바꾼다 — `disposition: attachment`(또는 cid 없는 파트) 첨부는
   uploaded 상태 항목으로 복원(blobId·이름·타입·크기). 인라인 파트(cid 참조)나 text/html 본문이 있으면 지금처럼 거부
   (리치 텍스트 단계 몫)하고 이유를 표시.
7. **원장**: 올린 바이트를 받은 첨부 원장에 넣지 않는다(이번엔) — 발송된 메일을 Sent 에서 열면 받은 첨부 경로로 다시
   받는다. 넣을지는 리치 텍스트 단계에서 인라인 이미지와 같이 판단.
8. **카드**: 받은 첨부 카드 부품을 재사용(아이콘·이름·크기) + 상태(진행률 또는 진행 중 표시, 실패 + 재시도, 제거).

## 하지 않는 것

인라인 이미지·클립보드 붙여넣기, 리치 텍스트, 첨부 이름 바꾸기, Files 에서 첨부, 링크 공유, 업로드 바이트 원장 저장,
보내기 취소, 여러 파일 zip.

## 검증

```txt
cargo test   저장 직렬화(겹친 요청 없음, 저장 중 변경 → 완료 뒤 재저장, 발송이 최신 저장 결과 사용), 거부된 blobId → 실패 항목,
             초안 bodyStructure(첨부 있음/없음), 다시 열기(attachment 복원 / 인라인·html 거부), 크기 상한 거부,
             업로드 중 첨부는 저장에 포함 안 됨
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib    IPC fixture
node --test "gui/src/**/*.spec.ts"   첨부 상태 전이(uploading→uploaded/failed→retry), 발송 가능 판정
npm --prefix gui run build
E2E (ignored, Stalwart, alice→admin 만): 첨부 2개 초안 저장 → 다시 열기 → 발송 → admin Inbox 에 첨부 2개(크기·이름 일치)
```

창에서(원격 디버깅 CDP), 좁은 창·넓은 창:

1. 클립 → 파일 2개 선택 → 진행 표시 → 업로드 완료 → 자동 저장 → Drafts 에 첨부 2개 초안.
2. 창 닫고 Drafts 에서 다시 열기 → 첨부 카드 2개 복원.
3. 큰 파일(상한 초과) 선택 → 업로드 시작 없이 거부 이유.
4. 자동 저장 중에 첨부 추가·제거 후 바로 발송 → 수신 메일의 첨부가 마지막 상태와 같고 Drafts 에 초안이 남지 않음.
5. 업로드 성공 + 저장 실패(Caddy 중지) 상태에서 닫기 → 확인창, "저장됨" 표시 없음.
6. 업로드 중 발송 버튼 비활성, 실패 유도(Caddy 중지) → 재시도 → 발송.
7. 끌어다 놓기 — 지시 3(b) 측정 결과와 함께.

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 커밋은 사용자 승인 뒤.
