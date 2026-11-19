# Spec Draft: JMAP Client

2026-11-19. **제품 계약과 단계별 완료 기준. 전체 구현 완료 문서는 아니다.**
사용자가 Opus 사전 검토 없이 구현 진행을 승인했다. Opus 검토는 후속 검토이며
착수의 선행 조건이 아니다. 미구현 요구사항은 완료 기준이지 현재 테스트가 이미
강제한다는 뜻이 아니다. DB의 미결 선택까지 확정된 것으로 보지는 않는다.

## 1. 제품 범위

- JMAP Core·Mail을 **우리 제품의 기본 지원 범위**로 잡는다. 모든 JMAP 클라이언트가
  Mail을 구현해야 한다는 프로토콜 규범을 주장하지 않는다.
- Contacts·Calendar는 별도 모듈이다. ActivityPub·선택적 WordPress 통합은 유지한다.
- Stalwart로 클라이언트를 독립 검증한다. WordPress Mail 서버 구현은 보류한다.
- 우리 JMAP Session 조립 점검은 별도 과제이며 Stalwart Mail 읽기의 선행 조건이 아니다.
- 순서: 문서·저장 설계 검토 → CLI 왕복 → SQLite 영속화·동기화 → GUI → 쓰기·발송.

| 계층 | 책임 |
|---|---|
| Rust Core | Session·인증 경계·capability·상한·batch·result reference·오류 |
| Rust Mail | Mailbox/Email/Thread 의미·부분 속성·쿼리·Mail 동기화 |
| SQLite | 서비스별 테이블·파생 색인·원자적 저장 |
| CLI | 인자·관찰 가능한 결과. HTTP나 동기화 로직을 중복 구현하지 않음 |
| GUI | 같은 코어의 이름 붙은 동작. SQL IPC·범용 인증 fetch는 열지 않음 |

## 2. 근거와 우선순위

규범은 [RFC 8620](https://www.rfc-editor.org/rfc/rfc8620.html)과
[RFC 8621](https://www.rfc-editor.org/rfc/rfc8621.html)이다. 각 메서드 구현 시 해당 절을 확인한다.
[Crash Course](https://jmap.io/crash-course/)·[Client Guide](https://jmap.io/client/)는
사용 흐름의 참고이며 의무적인 DB 스키마가 아니다. Stalwart 응답은 상호운용 증거다.

예시를 그대로 복사하지 않는다. Crash Course 후반의 필터·응답 배열 접근이 서로 맞지 않고,
Client Guide의 full-copy 예시에는 `/changes`의 `/added` 참조와 오래된 filter/sort 형식이 있다.
`/changes`는 `created/updated/destroyed`, `hasMoreChanges`, `cannotCalculateChanges` 기준으로
구현한다. `/queryChanges`의 오류 처리를 모든 `/changes`에 적용하지 않는다.

## 3. Core 계약

- endpoint·계정·기능·상한은 Session에서 얻는다. 명시 account 또는 검증된 primary/
  유일한 지원 account를 선택하며, WP User나 AP Actor에서 account ID를 추론하지 않는다.
- 서비스·인증 프로필·인증 사용자·Account·Actor는 별개다. 같은 도메인·이름으로 합치지 않는다.
- Session·capability의 모르는 필드를 보존한다. capability 지원과 객체별 허가는 다르다.
  Mailbox `myRights`와 서버의 최종 판정을 존중한다.
- [네트워크 신뢰 구현](IMPLEMENTATION-NETWORK-TRUST.md)의 플랫폼 인증서 검증·승인 오리진·
  선택적 발견을 유지한다. 무차별 서비스 탐침을 복원하지 않는다.
- batch와 result reference를 지원하되 call/object/byte/concurrency 상한을 지킨다.
  batch는 트랜잭션도 고정된 서버 스냅샷도 아니다.
- transport/TLS·HTTP problem·메서드 오류·항목별 SetError를 구분한다.
  응답은 call ID와 기대한 메서드명으로 대응시킨다. 배열 위치·첫 메서드 응답으로 고르지 않는다.
- Session `state`/응답 `sessionState`, 타입별 객체 `state`, 쿼리별 `queryState`를 구분한다.
  토큰은 불투명하며 다른 타입·계정·프로필에 재사용하지 않는다.
- 응답 `sessionState`가 달라지면 Session 재조회와 접근 정보 재검증을 예약한다.
  이를 객체 체크포인트로 쓰지 않는다. 빈 계정 목록을 인증 실패로 단정하지 않는다.

## 4. 첫 Mail 계약

Mailbox 조회 → `role=inbox` 선택 → 제한된 Email/query + 목록 속성 get → 선택한 Email의
추가 속성·본문 조회 → 확보한 부분의 영속화·오프라인 조회 → 목록·객체 변경 동기화.
메일함 이름은 지역화될 수 있다. Thread 집약 목록은 명시적 옵션이지 Mailbox와 동의어가 아니다.
여러 메일함에 나타나는 같은 Email은 같은 인증 범위 안에서만 기준본을 공유한다.
쿼리에서 빠졌다는 사실은 Email 파괴가 아니다. 첫 단계에 전체 `.eml` 복제를 요구하지 않는다.

## 5. 저장·렌더링 계약

하나의 embedded SQLite 계층 안에서 Mail/Contacts/Calendar 테이블을 분리한다.
Contacts `records`를 범용 Mail 저장소로 확장하지 않는다. 프로필별 파일인지 전체 프로필을
담는 파일인지는 [설계의 검토 질문](DESIGN-JMAP-CLIENT.md)에 남긴다.

받은 JSON 값과 확장 필드는 의미적으로 보존한다. 원래 JSON 바이트 순서는 약속하지 않는다.
요청·확보한 속성을 기록하며, 누락과 명시적 null을 구분한다. JMAP Email JSON, RFC 5322/MIME
원문 바이트, 로컬 작성본, 서버 Draft, EmailSubmission은 서로 다른 데이터·수명주기다.
RFC 5321은 SMTP 전송 규약이지 원문 형식이 아니다.

첫 상세 화면은 안전한 텍스트여도 된다. HTML 렌더링 전 정화·원격 이미지·첨부 정책을 정한다.
본문의 임의 리소스 URL에 계정 자격증명을 보내지 않는다.

## 6. 현재 상태와 제외 범위

현재 Core에 Session/batch/오류/SetOutcome·네트워크 검사가 있고 Contacts에 별도 SQLite·
Stalwart E2E가 있다. Mail 읽기 CLI는 메일함·제한된 받은편지함·요청 시 본문을 조회한다.
`Batch::response`는 call ID와 응답명으로 대응한다. 제품 Mail DB·delta·작성·발송 UI는 아직 없다.
result reference는 JSON으로 전송할 수 있지만 Mail의 해당 워크플로는 아직 검증하지 않았다.
별도 실서버 테스트에서는 두 로컬 계정의 EmailSubmission 송수신도 검증했다. 이는 제품
발송 기능의 구현이 아니다. 근거: [Mail](INTEROP-MAIL.md),
[Contacts](INTEROP-CONTACTS.md), [Core](INTEROP-CORE-ECHO.md).

첫 범위에서 제외: SMTP/IMAP 클라이언트, WordPress Mail, 실제 외부 발송, OAuth 공급자 통합,
Calendar 반복 전개, 범용 persons 원장, AP 델타 규약, push, 전체 offline replica, 발송 UI,
24개 테이블 일괄 생성. Core/Mail 전체 RFC 적합성을 이미 충족한다고 표현하지 않는다.

완료 검사는 [계획](PLAN-JMAP-MAIL.md)에 정의한다. Contacts/Core 회귀 테스트와
Mail 전용 검증을 구분한다. 연결의 현재 계약은 [연결 설계](DESIGN-CONNECTION-DISCOVERY.md),
구체적 네트워크 동작과 검증은 [구현 기록](IMPLEMENTATION-NETWORK-TRUST.md)을 따른다.
