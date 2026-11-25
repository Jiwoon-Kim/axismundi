# JMAP Mail Implementation Matrix

2026-11-19 작성, **2026-11-24 갱신**(읽음·별표·이동 쓰기, RFC 8887 푸시, 메일함 관리 진행 반영).
[RFC 8621](https://jmap.io/spec/rfc8621/) 전체 메서드의 제품 구현 범위다.
Core가 임의 메서드를 전송할 수 있다는 사실과 각 동작의 제품 지원을 구분한다.
전체 목록이 장기 구현 대상이며 아래 후속 항목을 첫 읽기 DB의 완료로 간주하지 않는다.

## 저장 책임

합의된 도메인 테이블은 `mail_mailboxes`, `mail_emails`, `mail_email_mailboxes`,
`mail_threads`, `mail_identities`, `mail_submissions`, `mail_drafts`다.
앞 세 테이블은 첫 영속화에 구현됐고, 나머지는 소유 기능을 구현할 때 추가한다.
쿼리 창·타입 체크포인트·확보 범위·미확보 상태는 별도 Mail 보조 구조다.
Contacts/Calendar 모델·원본 MIME·로컬 편집·서버 Email을 한 records 테이블로 합치지 않는다.

| 영역 | 소유 모듈·데이터 | 경계 |
|---|---|---|
| Mailbox | Mail mailbox 연산, mail_mailboxes | role·권한·계층·서버 count. 이름으로 정체성을 추론하지 않음 |
| Email | Mail email 연산, mail_emails | 부분 JSON·확보 속성·본문 truncation·서버 Email 상태 |
| 소속 | mail_email_mailboxes | account 안의 Email↔Mailbox N:M. query 이탈과 파괴를 구분 |
| Thread | 후속 thread 연산, mail_threads | 서버 threadId/Email 순서를 사용. subject로 스레드를 발명하지 않음. 읽기 창의 같은 대화 보기는 구현(Thread/get). **접힌 스레드 목록은 보류**(2026-11-24): 선택·읽음·삭제 범위 미정 — 예: Inbox 답장 한 통만 보이는 대화를 휴지통으로 보낼 때 그 한 통인지 Sent 의 나머지까지인지. Thread/changes 는 스레드 소속 로컬 저장 방식에 따라 필요해지는 수단이지 시작 조건이 아님 |
| Identity | 후속 identity 연산, mail_identities | 발신 설정. ContactCard·AP Actor·Session과 별개 |
| Submission | 후속 submission 연산, mail_submissions | 생성·대기·배달 결과·취소. Email과 별도 state/수명주기 |
| 로컬 Draft | 후속 compose 연산, mail_drafts | 미전송 편집과 서버 $draft Email의 분리·명시적 연결 |
| MIME/Blob | `jmap::blob`(코어 구현: RFC 8620 업로드·다운로드, RFC 9404 Blob/get·upload·lookup), GUI 미사용 | JSON으로 원문 바이트를 재구성했다고 간주하지 않음. 디스크 캐시는 DESIGN-MAIL-GUI.md "바이트 원장"(미구현) |
| VacationResponse | 후속 계정별 단일 설정 | Identity·Draft에 섞지 않음. 별도 singleton 저장 형태는 구현 시 결정 |
| SearchSnippet | 메일 텍스트 검색 결과 | 검색 조건에 종속된 메모리 표시. Email 본문이나 영구 정체성으로 취급하지 않음 |

## 메서드별 범위

실서버 증거는 INTEROP-MAIL, 저장 증거는 IMPLEMENTATION-MAIL-CACHE 기록을 따른다.
제품 쓰기는 읽음·별표·메일함 이동까지 구현됐다(DESIGN-MAIL-GUI.md "쓰기"·"이동"). 발송은 아직 후속이며 자동 발송을 켜지 않는다.
푸시: RFC 8887 WebSocket(`jmap::websocket`, 구독 → 따라잡기)이 CLI·GUI 실시간 갱신에 쓰인다 — Stalwart 1.1.0 은 공유받은
Account 의 변경을 그 사용자 소켓에 보내지 않아 그 화면은 새로고침(버튼·F5·당김)에 의존(DESIGN-MAIL-GUI.md "실시간 갱신").

| 메서드 | 처리·저장·실패 계약 | 현재 상태 |
|---|---|---|
| Mailbox/get | role/rights/count와 계층 보존, 불완전 snapshot 거부 | 읽기 CLI·전체 snapshot 캐시 |
| Mailbox/changes | Mailbox 전용 state, count만 바뀌는 경우의 updatedProperties 처리 | **구현**(2026-11-20): 첫 sync·계산 불가 시 스냅샷, 이후 델타. updatedProperties 는 보관 속성과의 교집합만 병합(Stalwart 는 thread 카운트도 보냄). 파괴 시 그 목록 삭제. Stalwart 델타 후 캐시 = 새 스냅샷 |
| Mailbox/query | 조건·정렬·계층별 ID 창. Email query와 별개 | 후속 — 트리는 Mailbox/get 으로 충분해 당장 필요 없음 |
| Mailbox/queryChanges | 해당 queryState의 소속/순서 변경, 불가 시 창 재조회 | 후속 |
| Mailbox/set | 생성·이름/부모 변경·삭제, 권한·항목별 실패·파괴 옵션 | **진행 중**(2026-11-24, BRIEF-MAILBOX-MANAGE): 생성(최상위·하위)·이름 변경·빈 메일함 삭제(`onDestroyRemoveEmails: false`). 생성 응답 유실은 재전송 없이 결과 모름. 부모 변경·sortOrder 는 후속 |
| Thread/get | 서버 threadId와 emailIds 순서, 전개 수가 get 상한을 넘으면 분할 | 접힌 목록은 Thread/get 없이 동작. **전개 구현**(2026-11-20, `mail-thread`): Thread/get 단독 → emailIds를 maxObjectsInGet 청크로 Email/get, 절단 없음, 사이에 사라진 메시지는 notFound. 메시지는 목록 coverage로 캐시, **스레드 소속은 저장 안 함**(Thread/changes 후속) |
| Thread/changes | Thread 전용 checkpoint와 캐시 무효화 | 후속 |
| Email/get | projection·null/누락·unknown·body part/truncation, notFound | 읽기 CLI·부분 캐시·오프라인 |
| Email/changes | Email 전용 state, hasMoreChanges, 파괴/dirty, 만료 복구 | 부분 캐시 구현·mock/실서버 검증. updated 는 가변 속성(keywords·mailboxIds)만 무효화하고 그 둘만 재요청(2026-11-21) |
| Email/query | account/filter/sort/collapseThreads 및 queryState, 서버 순서 | 메일함 조건(`inMailbox`)의 첫 창·result reference·캐시, GUI 무한 스크롤(`mail_more`, 같은 queryState 필수). 메시지·접힌 스레드 목록이 디스크에서 **쿼리별 키**로 공존(스키마 v2). 현재 Account의 text 검색(Trash·Junk에만 속한 메일 제외)은 **메모리 목록**으로 구현. 정렬은 receivedAt 내림차순 고정. from·날짜 등 조건 검색은 후속 |
| Email/queryChanges | 객체 changes와 분리, same-query 적용, 계산 불가 시 창 재조회 | **구현**(2026-11-20): 캐시된 창이 canCalculateChanges=true·같은 인자면 델타 적용, 창이 줄면 그 구간만 position 으로 보충(같은 queryState 여야 함), 커버리지 없는 멤버만 get. 계산 불가·tooManyChanges·불일치는 창 재조회. Stalwart 외부 생성·삭제 후 캐시 = 새 query |
| Email/set | 서버 Draft 생성·keywords/mailboxIds 변경·파괴. 로컬 의도 별도 보존 | **제품 구현(일부, 2026-11-23·24)**: keywords 패치(`$seen`·`$flagged`, 단건·일괄), mailboxIds 이동(Move·OnlyIn — Trash/Junk 는 보이는 소속 전부 빼고 넣기). 결과 판정·분할(maxObjectsInSet·maxSizeRequest)·응답 유실 시 재전송 없이 되읽기·결과 모름 잠금. 서버 Draft 생성·파괴(2026-11-25, BRIEF-MAIL-COMPOSE): 새 초안 생성 확인 뒤에만 이전 초안 파괴(RFC 8620 §5.3 원자 단위가 개별 작업이라 한 요청에 묶지 않음), 텍스트 본문만 |
| Email/copy | source/target account 구별, 부분 성공·후속 원본 파괴 결과 분리 | 후속 |
| Email/import | 업로드한 원문 Blob→서버 Email, 항목별 성공/실패와 생성 ID | 후속 |
| Email/parse | Blob 파싱 결과, 저장된 Email 생성/동기화와 다름 | 후속 |
| SearchSnippet/get | filter·emailIds에 종속된 강조/문맥, 렌더링 시 안전 처리 | 텍스트 검색 첫 결과와 각 페이지에서 사용. `<mark>`만 요소로 렌더하고 다른 내용은 텍스트로 표시 |
| Identity/get | 서버가 허용한 발신 identity와 signature·권한 | **제품 구현**(2026-11-25): 작성 창 From 목록. From 은 고른 Identity 에서 구성(RFC 8621 §6 MUST, 와일드카드는 도메인 검사), Sender·Message-ID 는 보내지 않음 |
| Identity/changes | Identity 전용 state·생성/변경/파괴 반영 | 후속 |
| Identity/set | 서버 허용 범위, mayDelete·항목별 거부·로컬 편집 보존 | 후속 |
| EmailSubmission/get | envelope·deliveryStatus·undoStatus, 완료/실패 구분 | 발송 응답 유실 판정에만 사용(내부). 배달 상태 표시는 후속 |
| EmailSubmission/changes | Submission 전용 state와 결과 관찰 | 후속 |
| EmailSubmission/query | 계정별 제출 기록 query 창, Email 목록과 별개 | 후속 |
| EmailSubmission/queryChanges | 제출 view의 queryState·순서 변경 | 후속 |
| EmailSubmission/set | Identity·Email·수신자 검증, undo/후속 Email 변경, 모호한 응답 재조정 | **제품 구현**(2026-11-25): 초안 저장 확인 → 제출 + onSuccessUpdateEmail(Drafts→Sent, $draft 제거). 응답 유실은 재전송 없이 초안·제출 기록 되읽기, 모르면 잠금. 보내기 취소·예약 발송(HOLDFOR 실측됨)은 후속 |
| VacationResponse/get | account별 singleton과 활성화/기간/본문 | 후속 |
| VacationResponse/set | singleton 변경, 유효성·권한·오류, 자동응답 루프 경계 | 후속 |

## 현재 구현 순서

1. 직접 query와 제한된 result reference, get 응답 완전성 검사.
2. 앞 세 도메인 테이블과 query/checkpoint 보조 구조, 오프라인 목록·본문.
3. Email changes와 창 재조회, 명시적인 stale/coverage. 전체 복제 완료는 주장하지 않음.
4. Mailbox delta·queryChanges·Thread·페이지 창 확장.
5. Identity·로컬 Draft·서버 Draft·Blob/import/parse·수정/copy·Submission.
6. VacationResponse를 포함한 남은 메서드와 실패 경로.

GUI 순서(2026-11-24): **텍스트 검색 구현 → 작성·발송 → 스레드 목록**. 목록은 메시지 단위, 읽기 창은 같은 대화를 함께 보여 준다.

M2/M3의 현재 데이터 구조가 후속 상태를 담을 수 있어도 해당 메서드 구현 증거는 아니다.
Thread/Identity/Submission state는 Email state를 재사용하지 않는다. 모든 메서드의
capability·account 범위·상한·call ID·오류 검사는 Core와 해당 Mail 연산의 책임이다.
