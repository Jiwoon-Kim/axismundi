# Brief — 메일 작성·발송 (1단계: 텍스트 메일)

> **범위**: 코어 + Tauri + GUI. 새 메일·답장·전체 답장·전달을 **텍스트 본문**으로 쓰고, 서버 초안으로 저장하고,
> `EmailSubmission/set` 으로 보낸다. 규칙은 [gui/AGENTS.md](../gui/AGENTS.md), 목록·선택은
> [DESIGN-MAIL-GUI.md](DESIGN-MAIL-GUI.md)(From 원칙은 "화면 구조" 절), 메서드 범위는 [MATRIX-JMAP-MAIL.md](MATRIX-JMAP-MAIL.md).

2026-11-25 작성. "소유자 결정" 과 "측정" 은 확정, 나머지는 착수 지시다. 측정이 지시와
어긋나면 멈추고 보고한다.

## 소유자 결정 (이미 기록된 것)

```txt
From      작성 화면에만.  현재 Account 의 Identity/get 그대로, 값은 identityId, 표시는 이름 + 주소.
          주소로 중복 제거하지 않는다.  다른 Account 의 Identity 와 합치지 않는다 (DESIGN-MAIL-GUI)
순서      검색 → 작성·발송 → 스레드 목록.  보낸 사람·받는 사람 필터는 작성·발송 뒤
결과모름  응답을 잃은 쓰기는 재전송하지 않고 되읽어 판단, 모르면 잠금 — 발송에서는 중복 발송 방지가 목적이라
          Bulwark 관찰이 아니라 우리 테스트로 고정한다
참고      Bulwark 1.21.3 (AGPL-3.0) 은 흐름 참고만 — 코드 복사 금지
발송계약  From 은 클라이언트가 고른 Identity 에서 구성한다(2026-11-25).  작성 창의 Identity 목록에서 고르고 보내기 전까지
          바꿀 수 있다.  일반 Identity 는 주소 고정(자유 입력 없음), 와일드카드(*@domain) Identity 는 그 도메인의 주소만.
          Sender 는 이번 단계에서 설정하지 않는다.  Stalwart 강화·이슈 보고는 범위 밖
1단계     텍스트 본문만(text/plain).  HTML 작성·서식, 첨부, 예약 발송·보내기 취소, 서명 편집, Identity/set 은 다음.
          답장·전달 때 원문 인용은 텍스트로
작성 표면 단일 MatDialog — 좁은 창(<840) 전체 화면, 넓은 창 가운데 큰 dialog.  한 번에 하나
초안      서버 초안($draft Email in Drafts)만.  자동 저장 실패 시 작성 내용을 창에 그대로 두고, 저장 안 된 변경이 있는 채
          닫으면 확인을 받는다.  로컬 초안이 없으므로 앱 종료 시 마지막 서버 저장 이후 입력은 복구 못 한다(알고 받아들임)
          로컬 초안(복구 버퍼·오프라인 작성)은 앱이 온라인/오프라인 상태를 감지할 수 있게 된 뒤 구현한다
```

## 측정 — Stalwart 1.1.0 (2026-11-25, alice Account c → admin, 로컬만, 프로브 메일 정리함)

```txt
submission capability  maxDelayedSend 2592000(30일), submissionExtensions FUTURERELEASE·SIZE·DSN·DELIVERYBY·
                       MT-PRIORITY·REQUIRETLS
Identity/get           alice 에 3개: alice@ · alice.work@ · team@ (같은 name, 서명 빈 문자열, mayDelete true)
Email 은 불변          초안 subject 변경 → notUpdated invalidProperties.  초안 저장 = 새 Email 생성 + 이전 것 파괴
                       (keywords·mailboxIds 만 바뀐다 — RFC 8621 §4.6)
Sent 자동 저장 없음    onSuccess 없이 제출하면 초안은 Drafts 에 $draft 그대로 남고 Sent 에 사본도 안 생긴다
onSuccessUpdateEmail   같은 요청에서 Drafts→Sent, keywords/$draft 제거 — 응답에 암묵 Email/set 이 따라온다(동작함)
From 위조 통과          from = someone@elsewhere.example 인 초안을 alice Identity 로 제출 → 생성되고 실제 배달됨
                       (deliveryStatus delivered yes).  서버가 From 과 Identity 를 대조하지 않는다.
                       RFC 8621: 클라이언트는 Identity.email 을 From 으로 MUST 사용(§6), 서버의 거부는 SHOULD(§9.6,
                       SetError forbiddenFrom) — 우리는 클라이언트 MUST 만 지킨다. 서버 SHOULD 는 우리 일이 아님
                       (소유자 2026-11-25, Stalwart 보고 안 함)
보내기 취소            envelope.mailFrom.parameters HOLDFOR=120 → undoStatus pending, sendAt +2분,
                       update undoStatus canceled → canceled (동작함, 1단계 범위 밖)
제출 기록              EmailSubmission/query 에 남는다(l·m·n) — 그런데 onSuccess 없이 보낸 첫 제출(k)은 query·get
                       모두에서 사라졌고, filter emailIds 로도 못 찾았다.  원인 미확인
```

마지막 줄 때문에 **응답 유실 판정을 제출 기록에만 기대면 안 된다**(지시 5).

## 이미 있는 것 — 읽고 시작

```txt
코어   core/tests/mail_submission_stalwart.rs — 초안 생성 → EmailSubmission/set → 수신 확인 → Sent 로 직접 이동
         (상호운용 테스트일 뿐, 제품 경로 아님).  mail.rs update(Change) · 결과 판정 · 분할 · 되읽기 패턴
       jmap::blob — 업로드는 있음(이번엔 안 씀)
Tauri  mail_* 명령, 거부 문구 규칙(원격 description 은 웹뷰로 안 넘김)
GUI    MailWriter(결과 모름 잠금), 상세 툴바, MatDialog 사용례(메일함 관리·검색 뷰)
```

## 지시

1. **Identity 읽기**: 코어 `identities(account)` — `Identity/get`, 결과를 Store 에 캐시(`mail_identities`, Identity 전용
   state; Email state 재사용 금지). 이번엔 Identity/changes 대신 작성 창을 열 때 다시 읽어도 된다. Tauri
   `mail_identities`. Account 에 submission capability 가 없으면 작성 버튼을 이유와 함께 비활성.
2. **작성 창**:
   - 진입: 목록 머리의 새 메일(FAB 또는 아이콘 — M3 기준으로 판단해 보고), 상세 툴바의 답장·전체 답장·전달.
   - 필드: From(`mat-select`, Identity 가 하나면 표시만), To·Cc·Bcc(주소 입력 칩 — 콤마·Enter 로 확정, 잘못된 주소 표시),
     제목, 본문(textarea). Cc·Bcc 는 접어 둔다.
   - 답장: To = 원문 Reply-To 없으면 From; 전체 답장 = 거기에 원문 To·Cc 중 **고른 Identity 주소를 뺀** 나머지;
     제목 `Re: `(이미 있으면 덧붙이지 않음); `inReplyTo` = 원문 Message-ID, `references` = 원문 references + Message-ID.
     전달: 제목 `Fwd: `, 수신자 비움, 원문 헤더 요약 + 본문 인용. 기본 From = 원문을 받은 주소와 같은 Identity, 없으면 첫 Identity.
   - 닫기: 변경 없음 → 그냥 닫힘. 변경 있음 → 저장 후 닫기. 저장 실패·결과 모름이면 닫지 않고 "저장되지 않은 변경이 있습니다 —
     그래도 닫기 / 계속 쓰기" 확인. 자동 저장 실패는 창 안에 표시하고 내용은 그대로 둔다. 초안 삭제는 별도 버튼(확인).
3. **초안 저장**: 서버 초안만. Email 은 불변이므로 저장 = 두 단계. (가) `Email/set create`(새 초안, Drafts, `$draft`
   `$seen`). (나) **새 초안 id 를 확인(created)한 뒤에만** 이전 초안 `destroy`. 한 요청에 묶지 않는다 — RFC 8620 §5.3 의
   원자 단위는 개별 객체 작업이라 생성 notCreated + 삭제 성공 조합이 가능하고, 그러면 기존 초안을 잃는다(Codex 지적,
   2026-11-25). 삭제만 실패하면 초안이 둘 남을 수 있다 — 내용 유실보다 낫다; 남은 이전 id 는 다음 저장 때 다시 지운다. 자동 저장은 입력이 멈추고 몇 초 뒤(debounce, 값은 실측해 보고) + 창 닫을 때.
   저장이 겹치지 않게 직렬화 — 진행 중이면 끝난 뒤 최신 내용으로 한 번 더.
   - 생성 응답 유실: 재전송 금지, 이전 초안은 건드리지 않는다. 새 초안의 id 를 모르므로 Drafts 를 되읽어 판단할 수 없다 → 그 작성 창을 "저장 결과
     모름" 으로 표시하고 다음 저장은 새 초안 생성(이전 id 파괴는 하지 않음 — 중복 초안 하나가 남을 수 있음, 데이터 유실보다
     낫다). 보고에 이 선택의 대안을 적을 것.
   - 기존 초안 열기: Drafts 의 메일을 열면 작성 창으로(읽기 창 아님).
   - Message-ID 는 클라이언트가 넣지 않는다 — 서버가 생성(RFC 8621 §4.6 MUST). 필요하면 저장 응답 뒤 Email/get 으로 읽는다.
4. **From 고정**(RFC 8621 §6 MUST): 보낼 Email 의 `from` 은 고른 Identity 의 `{name, email}` 로 코어가 덮어쓴다 — GUI 가
   준 값을 믿지 않는다. 예외는 `email` 이 `*@domain` 와일드카드인 Identity 뿐(그 도메인의 주소를 사용자가 입력, 코어가
   도메인 일치 검사). Identity 에는 `sender` 속성이 없다 — Sender 헤더는 쓰지 않는다. `replyTo`·`bcc` 는 Identity 값이
   있으면 따른다. envelope 를 생략하면 서버가 Sender 또는 From 에서 mailFrom 을 만든다(§7). 서버가 위조를 막지 않는다는 측정을 코어 테스트로 남긴다
   (위조 시도 → 코어가 거부, 서버에 안 감).
5. **발송**: 두 요청으로 나눈다. (가) 최신 내용을 지시 3 의 초안 저장으로 저장하고 응답으로 새 초안 id 를 받는다 — 이 응답을
   잃으면 발송은 시작도 안 한 것이니 저장 결과 모름 처리만. (나) 받은 id 로 `EmailSubmission/set create {emailId, identityId}` +
   `onSuccessUpdateEmail {#s: Drafts 소속 null, Sent true, keywords/$draft null}`. envelope 는 생략해 서버가 헤더에서
   만들게 한다(Bcc 포함 여부를 실측해 보고). Sent role 이 없으면 Sent 이동만 생략.
   - **응답 유실 — 재전송 금지**(중복 발송). 되읽기 순서: (1) 그 초안 id 로 Email/get — Sent 에 있고 `$draft` 없음 →
     보냄 확정. (발송 요청 때는 초안 id 를 항상 안다 — (가)·(나) 분리 덕분.) (2) 아직 Drafts/`$draft` → `EmailSubmission/query filter {emailIds:[id]}` 로 찾으면 그 상태를 따른다.
     (3) 둘 다 판정 불가 → **결과 모름**: 작성 창을 잠그고 "보냈는지 확인할 수 없음 — Sent 와 받는 사람 쪽을 확인" 안내,
     재발송 버튼 없음(사용자가 초안에서 새로 보내기만 가능). 초안이 파괴됐는데 Sent 에도 없으면 역시 결과 모름.
   - 위 규칙은 코어 단위 테스트(모의 전송: 응답 유실·부분 응답)로 고정한다 — 정상 발송 관찰로 대신하지 않는다.
   - 성공: 창 닫기, "Sent" 스낵바(보내기 취소 버튼 없음 — 1단계), 목록·트리 갱신은 기존 푸시/새로고침 경로.
   - 실패(notCreated): 거부 문구 규칙대로, 초안·창 유지. `forbiddenFrom`·`forbiddenToSend`·`tooManyRecipients`·
     `noRecipients`·`invalidRecipients` 는 알려진 type 으로 문구 매핑.
6. **결과 모름 잠금 재사용**: 쓰기 잠금 키에 "submission:<초안 id>" 를 더한다 — 같은 초안으로 두 번 보내기 버튼이 눌리지
   않게. 보내는 중 버튼 비활성은 기본.

## 하지 않는 것

HTML 작성·서식 툴바, 첨부(blob 업로드), 인라인 이미지, 예약 발송·보내기 취소(HOLDFOR — 측정만 해 둠), 서명 편집,
Identity/set, 로컬 오프라인 초안(mail_drafts), 여러 작성 창 동시, 주소록 자동완성, 다른 Account 의 Identity 로 보내기,
Account 간 이동(Email/copy — Bulwark 비교 메모 참고), 읽음 확인 요청(MDN).

## 검증

```txt
cargo test  코어: From 덮어쓰기·위조 거부, 답장 헤더(inReplyTo·references), 전체 답장 수신자(자기 Identity 제외),
            초안 저장 생성 확인 뒤에만 삭제(생성 실패 시 이전 초안 유지), 발송 요청 모양(onSuccessUpdateEmail), 응답 유실 3분기(모의)
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib    IPC 형식 fixture
node --test "gui/src/**/*.spec.ts"   수신자 칩 파싱, 작성 상태 전이(깨끗/변경/저장 중/결과 모름)
npm --prefix gui run build
E2E (ignored, Stalwart, alice→admin 만): 새 메일 발송 → admin Inbox 수신, alice Sent 에 1통·Drafts 에 0통;
            답장 → 수신 메일의 In-Reply-To = 원문 Message-ID; 초안 저장 두 번 → Drafts 에 최신 1통
```

창에서(원격 디버깅 CDP — gui/AGENTS.md), 좁은 창·넓은 창:

1. 새 메일 → From 세 개 중 선택 → 저장 없이 닫기(내용 없음) / 내용 입력 후 닫기 → Drafts 에 1통.
2. Drafts 의 초안 열기 → 작성 창 → 발송 → Sent 에 있고 Drafts 에 없음, admin 에 도착.
3. 답장·전체 답장·전달의 수신자·제목·인용.
4. 발송 중 Caddy 중지(응답 유실 근사) → 결과 모름 잠금, 재발송 버튼 없음. 재시작 후 Sent 확인으로 풀림.

끝나면 바꾼 파일, 본 것, 못 본 것을 보고. 측정에서 원인 미확인으로 남긴 "사라진 제출 기록(k)" 도 한 번 재현해 볼 것.
커밋은 사용자 승인 뒤.

## 2단계 후보 — 관찰 메모 (2026-11-26, Bulwark 1.21.3·Gmail·Outlook·Stalwart, 결정 아님)

```txt
첨부 업로드   JMAP 은 "초안이 참조하기 전에 blob 이 있어야 한다" 는 순서만 정한다.  Bulwark = 파일을 고르면 바로
              uploadUrl 로 올리고 받은 blobId 를 작성 창 항목에 기록, 이후 초안 저장은 그 blobId 를 참조(재업로드 없음).
              업로드 성공 ≠ 초안에 첨부 저장 성공 — 따로 표시.  업로드 중·실패 첨부를 빼고 조용히 발송 금지
자동 저장     Bulwark 는 첨부 추가·인라인 이미지 붙여넣기도 초안 변경으로 보고 약 2초 뒤 저장 — 임시보관에 메일이 생기는
              건 업로드 효과가 아니라 이어진 초안 저장.  제목·본문이 비어도 첨부만 있으면 저장 대상
미참조 blob   Stalwart 는 임시 업로드 만료 + 참조 없음이면 blob 정리 작업(기본 매일, Data Cleanup 스케줄)에서 지운다
              — 버린 첨부를 클라이언트가 지울 필요 없음.  로컬 서버의 만료 시간은 측정 전
인라인 이미지 Bulwark 는 삽입 버튼 없이 클립보드 붙여넣기만.  Gmail 은 Aa 로 서식 툴바를 접고 펼친다(접혀도 평문 모드 아님).
              후보: 붙여넣기 = 커서 위치에 삽입(작성 중엔 로컬 미리보기, 저장 때 blobId 인라인 파트 + 본문 cid:),
              Aa = 서식 도구만 토글, 클립 = 일반 첨부(본문에 안 넣음)
보내기 취소   Bulwark 는 서버 보류: 짧은 hold 로 제출(UNDO_WINDOW_MS 120s, undoStatus pending) → 취소 = canceled.
              우리 측정(HOLDFOR 120 → canceled) 과 같은 방식.  창 동안 Sent·Scheduled 수와 답장 표시를 보내기 전 값으로 둔다
Files·링크    Outlook 첨부 메뉴: 이 컴퓨터 / OneDrive / 업로드 및 공유(클라우드 링크) / 링크.  링크 공유는 일반 첨부와 다른
              기능 — JMAP Files(FileNode) 이후
```
