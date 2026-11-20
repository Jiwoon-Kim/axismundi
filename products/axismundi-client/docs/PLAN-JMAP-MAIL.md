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

**2026-11-20 결정 — Mail GUI 를 먼저 붙인다.** 위 기준을 바꾼다. Rust 코어에 JMAP 을 구현하고
CLI 로 검증한 뒤 Mail 부터 GUI 를 붙인다. 근거(소유자): Mail 화면은 나중에 ActivityPub inbox/outbox
GUI 에 그대로 도움이 되고, 클라이언트 blob 업로드가 성공하면 Contacts 쪽 할 일이 준다. 원래 기준이
막으려던 위험 — 앱 전체 틀(탐색·계정 전환)이 메일 하나에 맞춰 굳는 것 — 은 **Mail 을 앱 셸이 아닌
도메인 화면 하나로** 만들어서 피한다.

클라이언트가 구현할 RFC 범위. Mail 호환 기준은 Stalwart 이고, 2026-11-20 Session 에서 광고를 측정했다:

```txt
범위 안    8620 core · 8621 mail/submission/vacation · 8887 websocket · 9404 blob
          9425 quota · 9661 sieve · 9670 principals
제외       9007 MDN · 9219 S/MIME 검증    Stalwart 미광고 → 실서버로 검증할 수 없음
범위 밖    9749 webpush-vapid             데스크톱 앱에는 Web Push 를 받을 푸시 서비스가 없다.
                                         axismundi 플러그인 프런트앱의 PWA·알림 때 보고,
                                         JMAP core 플러그인의 후속 확장 후보. 데스크톱 실시간은 8887
```

GUI 착수 전에 필요한 것: 8621 의 남은 읽기 델타(Mailbox/changes·queryChanges 완료 → 페이지 창),
8887 실시간(푸시 완료), 9404 첨부. 9425 는 표시만이라 작다. 9661·9670 은 GUI 설정 화면과 함께 넣는다.

**9749 에 붙은 외부 근거 (2026-11-20).** Stalwart Labs 가 2026-11-18 Mastodon 에 "Push notifications
over JMAP are coming to an Apple Mail client" 라고 알렸고, Apple 개발자들이 Stalwart 개발 서버로 테스트
했다고 한다. Apple Mail 이 JMAP 자체를 지원하느냐는 질문에는 "Not yet ... It's just JMAP push for now".
인용된 X 글(미검증)은 iOS 27.3 beta 2, OAuth·IMAP JMAPACCESS·JMAP EmailPush·VAPID 를 요구한다고 적는다.
Stalwart 는 자체 호스팅에서 APNS 인증서 없이 iOS 푸시가 된다고 답했다. 로컬 Stalwart 가 광고하는
`urn:ietf:params:jmap:emailpush`·`webpush-vapid` 가 바로 그것이다. 그래서 9749 는 우리 PWA 만의 일이 아니라
**WordPress JMAP 플러그인이 iOS Mail 푸시를 받게 하는 경로**이기도 하다. 클라이언트 범위 밖이라는 결정은
그대로이고(데스크톱은 8887), 플러그인 후속 확장의 근거로 기록한다.
출처: https://mastodon.social/@stalwartlabs/117636625047483146

## 2026-11-19 착수 기록

사용자가 별도 Opus 검토 없이 진행을 승인했다. 아래 검토 질문은 미결 판단 기록으로
유지하되 구현 착수를 막는 외부 승인 조건으로 취급하지 않는다.

| 단계 | 현재 결과 | 남은 범위 |
|---|---|---|
| M0 | 프로필 격리 유지·Mail 가산 스키마·coverage/dirty 표현 기록 | 전역 파일·출하 암호화는 미결 |
| M1 | 제한된 읽기·result reference/분할·응답 대응·실서버 왕복·Thread 전개 | 스레드 소속 오프라인 보관(Thread/changes) |
| M2 | Mail 전용 테이블·오프라인·부분 병합·Contacts 보존 검증 | 임의 필터·희소 다중 페이지·Blob 캐시 확장 |
| M3 | Email changes·dirty/checkpoint·창 재조회·만료 복구·Mailbox delta·Email queryChanges | 큰 backlog 우선순위·페이지 창 |
| M4 | 아직 미구현 | 본격 UI는 Mail/Contacts/AP 대표 흐름 이후 |
| M5 | Submission의 양방향 로컬 테스트만 존재 | 제품 작성·Draft·Identity·Blob·발송 등 |

명령은 `mailboxes`, `mail-inbox`, `mail-read`, `mail-thread`, `mail-sync`, `mail-offline`이다.
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
          **조용히 절단하지 않는다.**  구현됨 (mail-thread, 2026-11-20)
```

전개 구현 기록: Thread/get 은 단독 호출이다. Thread/get→Email/get 참조 사슬은 상한보다 작은
스레드에서만 왕복 하나를 아끼고, 정작 큰 스레드에선 requestTooLarge 로 실패하므로 쓰지 않는다.
emailIds 중복은 서버 응답 결함으로 거부한다(한 스레드 안이라 dedupe 할 대상이 아님). 결과 순서는
Email/get 응답 순서가 아니라 Thread/get 의 순서다. 증거:

- mock(maxObjectsInGet=2, 5통): Email/get 3회 `[1,2] [3,4] [5]`, 순서 복원, 중간 삭제분 notFound.
  변이 셋(청크 무시·순서 정렬 제거·첫 청크만)이 모두 실패한다. 마지막 변이는 처음에 실패가 아니라
  **멈춤**이었다 — mock 이 오지 않는 요청을 무한 대기. accept 기한을 넣어 "request 2 of 4 never
  came" 실패로 바꿨다.
- Stalwart: inbox 목록에서 2통인 스레드가 전개하면 4통(나머지는 다른 메일함). 전개는 메일함
  경계를 넘는다. 4통 모두 같은 파일에서 오프라인으로 읽힘. 없는 스레드는 `found:false`, Email/get 없음.

남은 것: 스레드 소속의 오프라인 보관은 Thread/changes 와 함께. 실서버 maxObjectsInGet 이 커서
청크 분할 자체는 mock 으로만 증명됐다.

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

**Mailbox delta 구현 기록 (2026-11-20).** 첫 sync 와 cannotCalculateChanges 는 전체 스냅샷, 이후는
Mailbox/changes → 생성·변경분만 Mailbox/get. `updatedProperties` 가 오면(카운트만 바뀜, RFC 8621 §2.2)
이미 캐시된 메일함은 **캐시가 보관하는 속성과의 교집합만** 받아 병합한다. 측정: Stalwart 는 메일
하나의 `$seen` 변경에 `updatedProperties: [totalEmails, unreadEmails, totalThreads, unreadThreads]` 를
보낸다 — thread 카운트까지 병합하면 델타로 갱신된 메일함이 스냅샷본과 모양이 달라진다. 파괴된 메일함은
그 목록(mail_queries)을 함께 지우고 메시지는 남긴다(이동·삭제 여부는 Email/changes 몫).
changes 와 get 사이에 사라진 메일함은 파괴로 본다. 페이지마다 따로 커밋해 실패 시 마지막 커밋 state 가
남는다.

증거: mock 테스트 하나(2페이지·카운트 병합·생성·파괴+목록 정리·사이 소멸·스냅샷 폴백·중간 실패 보존),
변이 7종 전부 실패. Stalwart: 스냅샷 sync → 외부에서 `$seen` 복원·메일함 삭제 → 델타 sync 후 캐시된
메일함 6개가 같은 속성의 새 Mailbox/get 과 **완전히 일치**, state 는 Stalwart 가 그 구간에 대해
돌려주는 newState 와 같다. fixture(메일함·`$seen`)는 원상복구했다.

이 과정에서 기존 mock 이 Windows 에서 간헐 실패하던 원인을 찾았다(커밋된 코드에서 10회 중 2회):
accept 된 소켓이 리스너의 non-blocking 을 물려받아 요청 바이트보다 먼저 읽으면 WouldBlock. 수정 후 20회 0실패.

남은 것: 메일함 수가 maxObjectsInGet 를 넘으면 `ids:null` 스냅샷이 거부될 수 있다(RFC 8620 §5.1).
그때는 Mailbox/query + 청크 get 이 필요하다. 미구현.

**Email/queryChanges 구현 기록 (2026-11-20).** 목록의 소속·순서는 쿼리 델타가, 메시지 내용은 앞서 돈
Email/changes 가 맡는다(DESIGN §6). 그래서 델타 뒤에는 목록 속성 커버리지가 없는 멤버만 get 한다.

```txt
조건   캐시된 창이 있고, 저장된 인자 == 지금 인자, canCalculateChanges=true
적용   removed 와 added 의 id 를 빼고 → added 를 index 오름차순으로 삽입 → 창 크기로 자름
경계   보유한 길이를 넘는 index 는 빈틈 뒤라 그것과 그 뒤를 버린다. 위가 지워져 아래에서 올라온 행은
      델타가 말하지 않으므로 창이 줄면 position=len 으로 그 구간만 다시 묻는다 — 그 응답의 queryState 가
      델타의 newQueryState 와 다르면 창 전체 재조회
폴백   cannotCalculateChanges · tooManyChanges · 인자 변경 · 보충 불일치 → 기존 창 재조회
```

증거: mock 2개 — 시나리오(새 메일 → 그 하나만 get, 위 삭제 → 보충 query 만·get 없음, 보충 state 불일치
→ 재조회, 계산 불가 → 재조회)와 순수 적용 함수(빈틈·이동·중복). 변이 8종 전부 실패(그중 "added 를 먼저
빼지 않음"은 처음에 살아남아 테스트를 추가했다 — 규격을 지키는 서버는 보내지 않는 경우라 비어 있었다).
테스트 데이터도 바로잡았다: mock 이 id·subject 만 돌려주면 커버리지가 그 둘뿐이라 모든 멤버를 다시 받는다.
실서버는 요청한 속성을 전부(없으면 null) 돌려준다.

Stalwart: Email/query 가 `canCalculateChanges: true`. 외부에서 inbox 에 메일 생성 → sync → 캐시 =
새 query(5개, 같은 queryState), 서버 델타 `added:[{id,index:0}]`. 삭제 → sync → 캐시 = 새 query(4개),
델타 `removed:[id]`. 이때 창이 줄어 보충 경로를 탔다 — 같은 요청(position 4)에 Stalwart 는
`position 4 · 같은 queryState · ids []` 로 답해 폴백 없이 맞물린다. 관찰: position 이 total 을 넘으면
Stalwart 는 `position: 0` 을 돌려준다. 보유 prefix 는 total 을 넘을 수 없어 이 경로엔 영향 없음.

완료: 외부 테스트 클라이언트의 변경 반영. 실패 시 이전 체크포인트 또는 durable dirty 작업
보존. 부분 데이터로 서버 전체 count를 영구 재계산하지 않는다. full replica 완료는 주장하지 않는다.

## RFC 8887 푸시 (2026-11-20)

`websocket` 모듈과 CLI `mail-watch`. 소켓은 "무엇이 바뀌었나"만 알리고 내용은 나르지 않는다.
StateChange 를 받으면 기존 `/changes`·`/queryChanges` 흐름(sync)을 돌린다 — 푸시는 그 흐름을 일찍 깨울 뿐
대체하지 않는다(M5 의 원칙). 이번 범위는 푸시만이고 **요청은 계속 HTTP** 로 보낸다. WebSocket 위의 요청은
후속.

```txt
URL      Session 의 websocket capability 에서만. supportsPush=true 여야 함
인증     Authorization 은 업그레이드 요청에 실린다 → apiUrl 과 같은 origin(https↔wss, http↔ws)에만.
         다르면 거부 — 다른 origin 으로의 인증 리다이렉트를 거부하는 것과 같은 규칙
TLS      HTTP 와 같은 신뢰: rustls + ring + rustls-platform-verifier(ureq 와 같은 버전 공유), 검증 끄지 않음
서브프로토콜  "jmap" 요청, 서버가 수락하지 않으면 Shape 오류(재시도 대상 아님)
재연결   Transport 오류만 1→60초 백오프로 재시도. 재연결 전 sync 한 번, 마지막 pushState 로 재개 요청.
         업그레이드 거부(401 등)·서브프로토콜 불일치·RequestError 는 같은 답이 반복되므로 종료
idle     읽기 타임아웃 30초마다 Idle 이벤트 — 연결은 유지, 호출자가 멈출지 판단
```

증거:

- mock 5개: origin 규칙(호스트·포트·평문·자격증명·비 소켓 스킴), 업그레이드 요청의 Authorization·
  서브프로토콜, push enable 본문(dataTypes·pushState), ping 과 모르는 `@type` 은 건너뛰고 StateChange 만
  통과, idle, close, 서브프로토콜 미수락, 401 업그레이드 거부, RequestError. 변이 4종 전부 실패.
- Stalwart: `mail-watch --events 2` 를 띄우고 외부에서 inbox 에 메일 생성·삭제 → push 두 번, 목록 4→5→4.
  Caddy 를 재시작해 연결을 끊으면 `closed: 1001` → resync → 재연결 → 다음 push 수신.
- **실측으로 찾은 결함:** 처음엔 해석된 주소 중 첫 번째만 시도했다. `localhost` 는 `::1` 이 먼저 나오고
  Docker 는 포트를 IPv4 에만 열어 연결이 거부됐다(HTTP 는 ureq 가 모든 주소를 시도해 정상). 모든 주소를
  순서대로 시도하도록 고쳤다.

남은 것: pushState 재개가 실제로 끊긴 동안의 변경을 다시 보내는지는 Stalwart 로 확인하지 않았다(재연결
전 sync 가 있어 변경 유실은 막는다). WebSocket 위의 JMAP 요청, 앱 종료 시 상태 저장.

## Blob: RFC 8620 업로드·다운로드 + RFC 9404 (2026-11-20)

`blob` 모듈과 CLI `blob-upload`·`blob-download`·`blob-get`·`blob-compose`·`blob-lookup`. Mail 첨부가
첫 용도지만 모듈은 Mail 에 묶지 않았다 — Contacts 사진(ContactCard)도 같은 경로를 쓴다.

```txt
엔드포인트   uploadUrl·downloadUrl 은 Session 템플릿에서만. RFC 6570 level 1 로 확장해 '/' 까지 인코딩
            (type "text/plain" 이 경로 조각을 늘리지 않게). 필수 변수 누락·모르는 변수·짝 안 맞는 괄호는 거부
인증 범위   로그인이 실리므로 apiUrl 과 같은 origin 에만. 다운로드 리다이렉트도 같은 origin 만 따라감
업로드      maxSizeUpload 를 보내기 전에 검사. 응답의 accountId·size 가 보낸 것과 달라야 오류
다운로드    max_bytes 로 메모리 상한. 넘으면 오류이지 잘라서 주지 않음
Blob/upload maxDataSources·maxSizeBlobSet(인라인 분량) 사전 검사. 생성 id 마다 created 또는 notCreated
            중 정확히 하나여야 함. 한 blob 실패가 나머지를 실패시키지 않음
Blob/get    모든 id 가 list·notFound 에 한 번씩. isEncodingProblem·isTruncated 는 그대로 전달
Blob/lookup supportedTypeNames 밖의 타입은 보내지 않음. 타입마다 그 capability 를 using 에 추가.
            묻지 않은 타입의 답·id 중복·누락은 거부
```

Stalwart 실측:

- 바이너리 311,200B(비 UTF-8 포함) 업로드 → 다운로드 바이트 동일. 이름에 공백·한글(`probe 한.bin`).
- **범위를 주면 digest 도 그 범위의 digest 다.** `--length 300` 의 `digest:sha-256` 이 로컬 전체 해시와
  달라 보였는데, 앞 300바이트의 해시와 정확히 같았다. 범위 없이는 sha-256·sha-1 모두 로컬과 일치.
  RFC 9404 의 규정이지만 오해하기 쉬워 doc comment 에 적었다.
- 범위 읽기 `offset 1000 length 10` = 로컬 같은 구간. 바이너리를 asText 로 읽으면 `isEncodingProblem: true`.
- 조합: `"PREFIX-"` + 텍스트 blob 의 [7..16) → `"PREFIX-axismundi"` 16B. 없는 blobId 를 소스로 주면 그
  항목만 `notCreated(invalidProperties)`, 요청은 성공.
- lookup: inbox 메일의 blobId → `Email [cmaaaaat]`, `Thread [t]`. 아무도 참조 안 하는 업로드 blob →
  `Email []`(notFound 가 아니라 빈 목록). `Mailbox` 는 미지원 타입이라 클라이언트가 보내기 전에 거부.

mock 7개, 변이 6종 전부 실패. 그중 "다운로드가 아무 리다이렉트나 따라감"은 처음에 살아남았다 — 테스트의
리다이렉트 대상이 해석 안 되는 호스트라 규칙이 없어도 실패했기 때문이다. 대상을 실제로 응답하는 다른
포트의 서버로 바꿔 규칙 때문에 실패하게 했다.

남은 것: 업로드한 blob 을 Email 첨부로 붙이는 작성 흐름(M5 쓰기 슬라이스), 디스크 blob 캐시(DESIGN §7),
Contacts 사진 연결.

## GUI 직전 두 가지 (2026-11-21)

**1. 플래그 변경은 플래그만.** Email/changes 의 updated 는 무엇이 바뀌었는지 말하지 않는다(Mailbox/changes
와 달리 updatedProperties 가 없다). 대신 RFC 8621 §4 가 Email 은 `keywords`·`mailboxIds` 말고는 불변이라고
정한다. 그래서 updated 와 cannotCalculateChanges 는 그 둘의 coverage 만 지우고, 델타 경로는 그 둘만 빠진
메시지를 `["id","keywords","mailboxIds"]` 로, 그 밖은 목록 속성 전체로 따로 받는다. 이전엔 읽음 표시 하나에
목록 속성 전체를 다시 받고 이미 읽은 본문까지 stale 로 표시했다. 그 옛 동작을 고정하던 테스트 3개
(mock 2·Stalwart 1)를 새 규칙으로 바꿨다 — 의도된 동작 변경이다.

**2. 페이지 이어 받기.** `cache::more` / `mail-more`.

```txt
요청     캐시된 목록의 인자 + position=보유 길이, limit=창 크기, calculateTotal
state    응답 queryState 가 목록의 것과 다르면 그 사이 목록이 바뀐 것 → 델타 sync 한 번 후 재시도.
         또 다르면 오류(다른 state 의 행을 이어 붙이면 순서가 틀리거나 중복이 생긴다)
델타     advance 가 창 크기가 아니라 max(창 크기, 보유 길이) 를 유지 — 스크롤한 페이지를 잘라내지 않음
total    query·queryChanges 모두 calculateTotal. view 에 total·hasMore, 서버가 안 주면 null(추측 안 함)
상한     sync 의 하드코딩 50 제거(창은 limit 로, 델타는 길이로 이미 제한됨)
```

창 전체 재조회 폴백(계산 불가 등)은 첫 창만 다시 읽는다 — 그때는 스크롤한 페이지가 사라지고 다시
이어 받아야 한다. 의도된 단순화.

증거: mock 2개(플래그만 재요청·신규는 전체 / 페이지·델타 유지·state 불일치 재동기화·끝), 변이 6종 전부
실패. Stalwart 실측: probe 메일함에 메일 55통을 만들어(기존 1통 포함 56) 창 50 → `mail-more` 로 56,
`hasMore=false`, 캐시 순서 = 새 Email/query. 둘째 페이지(52번째)를 외부에서 `$seen` → sync 후 56행 유지,
그 메일만 keywords 갱신·제목 유지·stale 없음. 3통 삭제 → 53행, 새 query 와 일치. fixture 55통 전부 삭제.

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
