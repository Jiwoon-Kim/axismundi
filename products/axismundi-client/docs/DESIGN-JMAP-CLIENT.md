# Design Draft: JMAP Client and Mail Storage

2026-11-19. **설계 제안이며 최종 DDL·마이그레이션 지시가 아니다.**
[Spec](SPEC-JMAP-CLIENT.md) → 이 설계 → [계획](PLAN-JMAP-MAIL.md) 순으로 검토한다.

## 1. 합의와 미결

합의된 방향은 Core/Mail 기본 지원, CLI 우선 증명, 단일 SQLite 계층의 서비스별 테이블 분리다.
DB 설계도 CLI 전에 검토한다. 프로토콜이 같다는 이유로 저장 모델까지 합치지 않는다.

**첫 구현 제안:** 현재처럼 지속적 인증 프로필을 격리 경계로 두고, 프로필의 DB 파일 안에서
서비스별 스키마를 공유한다. 사용자의 단일 SQLite 방향이 전체 로그인들을 한 파일에 넣는다는
뜻인지는 아직 확정하지 않았다. 전역 파일을 선택한다면 모든 데이터·관계·쿼리·체크포인트·
작업 키와 복합 외래 키에 profile 범위가 필요하다. 이 결정 전 물리 마이그레이션은 하지 않는다.

2026-11-19 구현: 기존 프로필당 파일에 Mail 테이블만 가산하는 방식으로 시작했다.
전역 파일로 이관하거나 Contacts 데이터를 옮기지 않는다. 세 도메인 테이블과 별도 query/
checkpoint 보조 구조의 실제 계약은 [캐시 구현 기록](IMPLEMENTATION-MAIL-CACHE.md),
Thread·Identity·Submission·Draft를 포함한 전체 범위는 [대응표](MATRIX-JMAP-MAIL.md)에 있다.

## 2. 식별과 접근

현재 Contacts는 profile ID를 API URL·로그인명에 바인딩한다. 오사용 방지이지 범용 영구 사용자
식별자가 아니다. endpoint/사용자명 변경은 명시적 재바인딩 정책이 필요하며, 비밀번호·토큰
교체만으로 다른 사용자가 되지는 않는다. Session 스냅샷은 교체 가능한 관측값이지 소유 키가 아니다.

레코드 범위는 `(profile, service binding, accountId, type, id)`다. 프로필당 단일 JMAP 서비스라면
로컬에서 중복되는 service 키를 생략할 수 있지만, 다른 프로필의 같은 Account 캐시를 합치지 않는다.
공유 원격 Account임을 아는 것과 캐시 공유는 별개다. `mayCreateAddressBook` 같은 값도 있으므로
accountCapabilities 전체를 사용자와 무관한 전역 account 상수로 저장하지 않는다.

권한 철회 시 추가 사용을 중단한다. 기존 offline 데이터 처리는 별도 보안·제품 결정이다.
로그아웃·자격증명 제거·캐시 삭제·미전송 편집 폐기는 구분한다. 현재 개발 캐시는 비암호화이며,
개인 데이터 암호화와 키 보관은 출하 전 미결 사항이다. 자격증명은 fixture·문서·DB에 넣지 않는다.

## 3. 논리 저장 영역 후보

아래는 책임 분리이지 모두 지금 생성할 테이블 목록이 아니다. 위 인증 범위가 전 영역에 적용된다.

| 영역 | 후보 | 목적 |
|---|---|---|
| 연결·접근 | 프로필 메타데이터, Session/account-access 스냅샷 | 서버 권한 원장을 복제하지 않고 관측값 보존 |
| Mailbox | `mail_mailboxes` | JSON, role, parent, rights, subscription, counts |
| Email | `mail_emails` | 확보 속성·신선도와 목록 색인 |
| 소속 | `mail_email_mailboxes` | account 범위의 mailboxIds 관계 |
| Thread | `mail_threads`, 필요시 순서 관계 | 서버 Thread ID/순서. 제목으로 자체 생성하지 않음 |
| 쿼리 | `mail_queries`, `mail_query_items` | 조건·정렬·queryState·희소한 순서 창 |
| 본문·Blob | 캐시 메타데이터와 파일 | MIME 원문·본문 값·첨부 바이트 구분 |
| 체크포인트 | 타입별 state·무효화 작업 | 객체 동기화 진도. 쿼리 최신 여부와 별개 |
| 나중 쓰기 | drafts, Mail mutations, submissions | 서버 기준본과 로컬 의도 분리 |

Contacts 기존 행·pending edits는 별도 이관 승인 전 유지한다. `records`에 Mail을 넣지 않는다.
Calendar 빈 테이블을 미리 만들지 않는다. Contacts의 AddressBook 소속도 복수이며, Calendar의
소속·반복은 해당 규격으로 확인한다. 셋을 억지로 범용 collection 테이블에 맞추지 않는다.
WP User/AP Person/ContactCard/Mail Identity도 단일 persons로 합치지 않는다.

## 4. 부분 객체와 쿼리 창

Email 행은 **확보한 데이터**다. get으로 전달된 속성만 병합하고 누락된 기존 필드를 지우지 않는다.
명시적 null은 누락이 아니다. 모르는 확장도 보존한다. 확보 범위에는 property뿐 아니라 요청한
본문 part ID·fetch 옵션·truncation·header 선택이 필요하다. 단일 `complete=true`는 부족하다.
정확한 coverage 표현은 M0/M2 결정 항목이다. 미조회·notFound·파괴·dirty·쿼리 이탈도 구분한다.

쿼리 식별에는 account 범위·filter·sort·collapseThreads 등 의미를 바꾸는 인자가 들어간다.
position/limit은 그 쿼리의 창이다. 서버 순서의 ID와 위치·알려진 구간/빈틈을 저장하며,
행 수를 전체 결과로 해석하거나 로컬 SQL 정렬이 서버 collation과 같다고 가정하지 않는다.
한 Email은 여러 독립된 목록에 나타날 수 있고, 한 목록의 제거가 본문 삭제를 뜻하지 않는다.

구현(스키마 v2, 2026-11-20): `mail_queries`·`mail_query_items` 의 키는 `(account, query_key)`.
v1 은 `(account, mailbox_id)` 로 키잉해 같은 메일함의 메시지 목록과 스레드 목록이 서로 덮어썼다 —
위 문단과 어긋난 드리프트였다. `query_key` 는 **맵을 직렬화하지 않고 원시값을 고정 순서로**
조립한다. CLI 와 GUI 가 같은 프로필 파일을 열고, GUI 빌드는 Tauri 의존성과 serde_json 기능을
통합한다. 그쪽 어디서든 `preserve_order` 가 켜지면 직렬화된 맵은 두 빌드에서 다른 키가 되어 서로의
행을 못 찾는다(2026-11-20 측정: 현재는 꺼져 있음). v1 의 두 테이블은 쿼리 결과의 캐시일 뿐 사용자
의도가 없어 번역하지 않고 버린 뒤 다음 sync 가 다시 채운다.

동일 batch의 get들도 변하는 상태를 볼 수 있다. call ID·응답명으로 대응시키며, 요청 generation/
scope 검사를 두어 view 전환·로그아웃·경쟁 refresh 이후의 낡은 응답이 새 캐시를 덮지 않게 한다.
불투명 state 토큰의 대소 비교로 최신 응답을 결정하지 않는다.

## 5. Result Reference와 상한

직접 query의 limit은 get 상한 안으로 제한한다. 하지만 Thread 전개 뒤 Email 수는 화면 행보다
훨씬 커질 수 있다. 크기를 보장하지 못하면 ID를 먼저 받고 deduplicate/chunk get으로 나눈다.
result reference 결과를 조용히 절단하지 않는다. maxCallsInRequest·UTF-8로 직렬화한 요청
byte 수·object 수·동시 요청 수를 광고된 상한과 더 낮은 로컬 상한 안으로 제한한다.

가이드의 4-call chain이 상한을 넘으면 의존 경계에서 나눈다. 메서드·참조 오류, 중복 ID,
정상적인 추가 메서드 응답과 부분 성공을 보존하며 HTTP 200 전체를 한 성공으로 읽지 않는다.

## 6. 부분 캐시 동기화

`/changes`는 캐시 객체를 삭제/무효화하고 `/queryChanges`는 목록 소속·순서를 갱신한다.
동일 조건·정렬·옵션·oldQueryState에만 delta를 적용한다. canCalculateChanges가 false이거나
쿼리 delta를 계산할 수 없으면 필요한 query 창을 다시 읽는다. 메일 전체를 버리지는 않는다.
객체 hasMoreChanges를 처리하고 Email에 Mailbox/Contacts state를 쓰지 않는다.

체크포인트 전진 조건: 관련 변경을 적용했거나 **필요한 재조회 작업과 dirty 표시를 영속화**했다.
dirty 객체를 최신으로 표시하지 않는다. 부분 캐시의 state 전진은 Account 전체 복제 완료가 아니다.
미캐시 생성은 당장 get하지 않을 수 있지만 view는 별도 갱신한다. 파괴된 객체의 캐시·소속과
영향받는 view를 정리한다. 전체 replica는 더 강한 coverage 보장이 필요한 별도 모드다.

네트워크는 SQLite 쓰기 트랜잭션 밖에서 수행한다. 기준본·색인·소속·dirty 작업·타입 체크포인트를
한 트랜잭션으로 확정하고 이전 로컬 revision을 다시 검사한다. 쿼리 결과와 queryState도 함께
확정한다. 독립된 query/type 상태를 서버 전체의 한 스냅샷인 것처럼 묶지 않는다.
실패·crash 후에는 이전 유효 상태 또는 명시적 dirty 상태가 남는다. 로컬 편집은 재동기화로 지우지 않는다.

## 7. 바이트·쓰기·미결 정책

JMAP Email JSON에서 `.eml`을 받았다고 간주하지 않는다. 원문은 광고된 download endpoint로
필요할 때 받는다. 큰 파일은 디스크 캐시로 두는 안이며, DB 참조·크기/무결성·원자적 게시·
crash 잔재·eviction·draft pin을 검증해야 한다. blob ID나 URL만으로 인증 범위를 넘어 공유하지 않는다.

첫 화면은 텍스트로 시작한다. rich HTML·원격 이미지 정책은 별도다. 로컬 작성본, `$draft` Email,
pending operation, EmailSubmission은 수명주기가 다르다. 제출 결과가 불명확하면 재전송 전에
조정해야 하며, 범용 blind retry queue를 먼저 만들지 않는다.

## 8. Opus 검토 질문 — 한 번에 답할 항목

1. 프로필 소유 단일 파일이 첫 구현에 적절한가? 전역 파일이 지금 필요한 구체적 요구가 있는가?
   어떤 격리 조건이 빠졌는가?
2. 속성 coverage와 durable dirty 작업으로 부분 캐시 진도·전체 복제·crash/낡은 응답을 제대로
   구분할 수 있는가? 명확한 반례가 있는가?
3. Thread fan-out·서버 상한에 따라 result-reference chain을 나누는 기준이 충분한가?
4. CLI 읽기 전에 빠진 선행 조건 또는 지금 빼야 할 추상화/테이블은 무엇인가?

답을 미리 가정하지 않는다. 넓은 기능 제안보다 구현 전에 고쳐야 할 구체적 결함을 우선한다.
