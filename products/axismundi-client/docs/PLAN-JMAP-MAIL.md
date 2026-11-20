# Plan Draft: Mail CLI, Persistence, GUI

CLI 명칭 결정은 `axismundi`이며 Cargo 패키지·실행 파일·현재 사용 예시에 반영했다.
기존 `AXJ_*` 환경변수는 이번 실행 파일명 변경과 분리해 호환 유지한다.
아래 첫 슬라이스의 이름 변경 제외는 당시 범위 기록이지 명칭 재검토 요구가 아니다.

## 제품 UI 착수 기준

Mail·Contacts·ActivityPub 각각의 대표 사용 흐름을 먼저 끝까지 연결한다.
Mail은 읽기·본문·저장·델타, Contacts는 조회·편집 왕복, AP는 Actor 선택·읽기·
C2S 제출을 기준으로 삼는다. 모든 세부 기능의 완성을 기다린다는 뜻은 아니다.
그전 GUI는 최소 검증 화면으로 유지하고 프로토콜 처리는 Rust에 둔다.
Angular Material을 이용한 본격적인 탐색·계정 전환·작성 화면 설계는 세 영역의
실제 동작을 근거로 진행한다. Stalwart의 기본 WebUI는 서버·계정 관리 화면이며
메일·주소록 사용자 클라이언트의 비교 대상으로 가정하지 않는다.

## 2026-11-19 착수 기록

사용자가 별도 Opus 검토 없이 진행을 승인했다. 아래 검토 질문은 미결 판단 기록으로
유지하되 구현 착수를 막는 외부 승인 조건으로 취급하지 않는다.

| 단계 | 현재 결과 | 남은 범위 |
|---|---|---|
| M0 | 프로필 격리 유지·Mail 가산 스키마·coverage/dirty 표현 기록 | 전역 파일·출하 암호화는 미결 |
| M1 | 제한된 읽기·result reference/분할·응답 대응·실서버 왕복 | Thread 전개는 후속 |
| M2 | Mail 전용 테이블·오프라인·부분 병합·Contacts 보존 검증 | 임의 필터·희소 다중 페이지·Blob 캐시 확장 |
| M3 | Email changes·dirty/checkpoint·창 재조회·만료 복구 | Mailbox delta·queryChanges·큰 backlog 우선순위 |
| M4 | 아직 미구현 | 본격 UI는 Mail/Contacts/AP 대표 흐름 이후 |
| M5 | Submission의 양방향 로컬 테스트만 존재 | 제품 작성·Draft·Identity·Blob·발송 등 |

명령은 `mailboxes`, `mail-inbox`, `mail-read`, `mail-sync`, `mail-offline`이다.
근거는 [Mail 왕복](INTEROP-MAIL.md)·[캐시 구현](IMPLEMENTATION-MAIL-CACHE.md)이며,
RFC 8621 전체 메서드의 소유 영역·지원 상태는 [대응표](MATRIX-JMAP-MAIL.md)를 따른다.
이하 단계별 항목은 전체 완료 기준이다. 위 첫 구현 결과로 모든 항목의 완료를 주장하지 않는다.

[Spec](SPEC-JMAP-CLIENT.md) → [설계·검토 질문](DESIGN-JMAP-CLIENT.md)을 먼저 읽는다.
Opus 없이도 아래 구현 순서와 검증을 이어간다. 검토를 기다리며 진행 승인을 다시 묻지 않는다.

## 현재 근거와 범위

Session/batch/네트워크 신뢰·선택적 발견·Contacts E2E는 기존 기반이다.
[네트워크 기록](IMPLEMENTATION-NETWORK-TRUST.md)·[Contacts 기록](INTEROP-CONTACTS.md)을
참고한다. SetOutcome이 있다는 사실은 Mail·부분 캐시·발송·result-reference chain의 증거가 아니다.
Core/Mail 전체 RFC 적합성을 주장하지 않는다.

WordPress Session 조립 점검은 별도 제한된 감사이며 Stalwart 클라이언트 작업의 선행 조건이 아니다.
서버 변경·Contacts 서버 리팩토링·Calendar·push·SMTP/외부 발송은 이번 계획에서 열지 않는다.

## M0. 검토와 결정

- 사전 Opus 검토 게이트는 사용자 승인으로 해제됐다. 네 질문은 후속 검토 입력으로 유지한다.
- 파일/프로필 경계, 부분 속성 coverage와 dirty 작업 표현을 기록한다.
- 구현 메서드별 RFC 8620/8621 근거를 확인해 가이드의 오래된 예시를 교정한다.
- 쓰기 전에 테스트 전용 Mail fixture 생성·식별·정리 절차를 정한다.

완료: 첫 읽기 흐름·저장 경계·남은 미결의 명시. 24개 테이블 생성·기존 DB 이관·바이너리 이름
변경·서버 재작성은 하지 않는다.

## M1. CLI 프로토콜 증명 — Mail DB 없이

첫 CLI 동작은 Mailbox 목록, 제한된 Email/query 창, Email 하나 읽기다.
HTTP·프로토콜·동기화 로직은 Rust Core/Mail에 둔다.

| 단계 | 양성 검사 | 실패·경계 검사 |
|---|---|---|
| Session/account | Mail 광고와 검증된 account 선택 | 빈 접근·여러 후보·Mail 미지원·읽기 전용을 구분 |
| Mailbox | role=inbox·계층·권한 보존 | 지역화 이름으로 Inbox 추론 금지, role 없을 때 명시적 결과 |
| Query/get | result reference로 ID/순서/목록 속성 확보 | 동일 메서드 여러 호출·참조/메서드 오류·비정상 응답 |
| 상세 | 열 때 추가 속성·본문 조회 | 누락·truncation을 완전한 본문으로 읽지 않음 |
| 상한 | query/get 크기와 call 의존 관계 제한 | 작은 모의 상한·Thread fan-out에서 실제 분할, 절단 금지 |

먼저 `collapseThreads=false` 메시지 행으로 시작한다. 선택한 조건·정렬·projection·call ID를
관찰 가능하게 하되 비밀·개인 본문을 로그에 남기지 않는다.

**Thread 는 두 단계로 나뉜다 (2026-11-20 정정).** 원래 이 절은 "Thread 옵션은 전개 상한 검사와
함께" 라고 적었다. 그 문장이 막으려던 위험은 팬아웃이고, 팬아웃은 **전개**에서만 생긴다.

```txt
접힌 목록   collapseThreads=true 의 Email/query.  스레드당 대표 id 하나라 결과는 limit 이하,
          limit 은 maxObjectsInGet 이하 — 메시지 목록과 같은 상한 안에 든다.  구현됨
          (--rows threads).  목록 키가 collapseThreads 를 포함해 메시지 목록과 공존한다
전개       Thread/get → 그 스레드의 emailIds 전부 → Email/get.  스레드 하나가 메일 수천 개일
          수 있어 상한이 없다.  ID 를 먼저 받고 dedupe·maxObjectsInGet 청크로 나눈다.
          **조용히 절단하지 않는다.**  미구현 — 이 단계가 상한 검사와 함께 들어간다
```

여러 스레드의 emailIds 를 result reference 로 한 번에 Email/get 에 잇는 사슬은 쓰지 않는다.
상한을 넘길 수 있는 유일한 경로이고, 스레드 하나씩 전개하면 필요하지 않다.

완료: **비어 있지 않은 결정적 메시지**로 Stalwart 읽기 왕복을 증명한다. 빈 Inbox 성공만으로
닫지 않는다. 현재 합성 fixture는 Email/set으로 넣고, 별도 두 계정 테스트에서 로컬 Submission을
검증했다. upload + Email/import 원문 경로는 후속이다. 실제 외부 SMTP 배달은 하지 않는다.

## M2. SQLite Mail 영속화

M1에 필요한 테이블·색인만 Mail 소유 스키마로 만든다. SQLite runtime을 재사용하되 Contacts
데이터 모델을 재사용하지 않는다. Contacts 기존 행·pending edits는 보존한다.

검증할 것:
- 다른 서버의 동일 ID, 같은 공유 Account에 접근하는 두 사용자의 다른 가시성 격리.
- 목록 get → 상세 get의 병합, 확장 보존, null/누락 구분, 부분 본문 coverage.
- query 순서·창·빈틈의 reopen 보존. JSON만으로 원문 다운로드를 주장하지 않음.
- offline 목록/상세에서 확보 데이터와 미확보 상세를 구분.
- 실패·crash 시 데이터/state 일치. 경쟁/늦은 응답이 새 generation·다른 프로필을 덮지 않음.
- 신규 스키마·업그레이드·재실행·실패 복구. 개인 데이터 암호화/키 정책은 계속 명시적 미결.

완료: 실제 파일의 재시작·offline 증거와 격리된 migration 검사. CREATE 성공은 migration
검사도 렌더링 검사도 아니다.

## M3. 부분 캐시 동기화

Mailbox/Email changes, queryChanges 또는 필요한 query 창 재조회를 붙인다.
Thread changes는 Thread view를 쓸 때 검증한다. 설계의 dirty 작업·원자성 계약을 적용하며,
Contacts 전체 캐시 가정이나 모든 토큰의 일치 조건을 그대로 복사하지 않는다.

fixture: 새 메일, `$seen`/keyword 변경, 메일함 이동·복수 소속, 객체 파괴 없는 query 이탈,
실제 파괴, 순서 변경, 만료 state, canCalculateChanges=false, 중간 객체 state, 누락 get,
동시 변경·crash/reopen. 큰 backlog가 화면의 최신 창 확보를 막지 않게 한다.

완료: 외부 테스트 클라이언트의 변경 반영. 실패 시 이전 체크포인트 또는 durable dirty 작업
보존. 부분 데이터로 서버 전체 count를 영구 재계산하지 않는다. full replica 완료는 주장하지 않는다.

## M4. 같은 코어 위의 GUI

Mailbox·query 창·선택 Email·refresh를 이름 붙은 IPC로 제공한다. SQL·범용 인증 fetch를
노출하지 않는다. blocking network/DB 작업은 UI thread 밖에 둔다. 빈 목록·loading·오류·
미캐시·offline 상태를 구분한다. 텍스트 상세부터 시작한다.

실행 중인 Tauri 바이너리에서 메일함 이동·스크롤·상세 확보·재시작을 실제 조작과 화면으로
검증한다. CLI/core 통과만으로 GUI를 완료 처리하지 않는다.

## M5. 후속

로컬 작성본, 서버 Draft, Identity, upload/import, 변경 작업, 낙관적 UI, EmailSubmission은
별도 쓰기 슬라이스다. 권한·항목별 오류·충돌 보존·모호한 제출 결과 조정이 자동 재시도보다 먼저다.
push는 기존 changes/query 흐름을 깨울 뿐 대체하지 않는다. Contacts/Calendar 확장은
각 서비스 계약을 확인한 뒤 검증된 기반만 재사용한다.

## 검증 규율

현재 실행 가능한 회귀 명령이다. **이 초안 작성 중 새로 실행했다는 뜻은 아니다.**

```powershell
cargo test --workspace
cargo test --manifest-path gui/src-tauri/Cargo.toml --lib
npm --prefix gui run build
```

실행 위치는 `products/axismundi-client`다. Contacts opt-in E2E는 INTEROP-CONTACTS.md를 따른다.
Mail 구현과 함께 이름 붙은 테스트를 추가한다. ignored 검사를 실행으로 세지 않는다.
실서버의 작은 데이터로 안 걸리는 분기는 낮은 상한의 mock으로 증명한다. Stalwart는 독립
상호운용 증거이고 Axismundi 단독 검사는 우리 스택끼리의 일치 증거다.

실행 시 이미지/version·공개 URL·capability·fixture account를 다시 확인한다. `b`나 bootstrap
계정을 규약으로 하드코딩하지 않는다. 현재 로컬 HTTPS는 환경 증거이지 이식성 보장이 아니다.
자격증명은 source/docs에 넣지 않는다. cleanup 실패 시 반환된 fixture ID를 보고하고 무관한
메일을 지우지 않는다. commit/push는 명시적으로 요청받을 때만 한다.
