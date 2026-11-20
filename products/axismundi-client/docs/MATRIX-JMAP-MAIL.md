# JMAP Mail Implementation Matrix

2026-11-19. [RFC 8621](https://jmap.io/spec/rfc8621/) 전체 메서드의 제품 구현 범위다.
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
| Thread | 후속 thread 연산, mail_threads | 서버 threadId/Email 순서를 사용. subject로 스레드를 발명하지 않음 |
| Identity | 후속 identity 연산, mail_identities | 발신 설정. ContactCard·AP Actor·Session과 별개 |
| Submission | 후속 submission 연산, mail_submissions | 생성·대기·배달 결과·취소. Email과 별도 state/수명주기 |
| 로컬 Draft | 후속 compose 연산, mail_drafts | 미전송 편집과 서버 $draft Email의 분리·명시적 연결 |
| MIME/Blob | 후속 blob 연산, 파일과 메타데이터 | JSON으로 원문 바이트를 재구성했다고 간주하지 않음 |
| VacationResponse | 후속 계정별 단일 설정 | Identity·Draft에 섞지 않음. 별도 singleton 저장 형태는 구현 시 결정 |
| SearchSnippet | 후속 검색 view 결과 | 검색 조건에 종속. Email 본문이나 영구 정체성으로 취급하지 않음 |

## 메서드별 범위

실서버 증거는 INTEROP-MAIL, 저장 증거는 IMPLEMENTATION-MAIL-CACHE 기록을 따른다.
제품 쓰기는 아직 후속이며 자동 발송을 켜지 않는다.

| 메서드 | 처리·저장·실패 계약 | 현재 상태 |
|---|---|---|
| Mailbox/get | role/rights/count와 계층 보존, 불완전 snapshot 거부 | 읽기 CLI·전체 snapshot 캐시 |
| Mailbox/changes | Mailbox 전용 state, count만 바뀌는 경우의 updatedProperties 처리 | **구현**(2026-11-20): 첫 sync·계산 불가 시 스냅샷, 이후 델타. updatedProperties 는 보관 속성과의 교집합만 병합(Stalwart 는 thread 카운트도 보냄). 파괴 시 그 목록 삭제. Stalwart 델타 후 캐시 = 새 스냅샷 |
| Mailbox/query | 조건·정렬·계층별 ID 창. Email query와 별개 | 후속 |
| Mailbox/queryChanges | 해당 queryState의 소속/순서 변경, 불가 시 창 재조회 | 후속 |
| Mailbox/set | 생성·이름/부모 변경·삭제, 권한·항목별 실패·파괴 옵션 | 후속 |
| Thread/get | 서버 threadId와 emailIds 순서, 전개 수가 get 상한을 넘으면 분할 | 접힌 목록은 Thread/get 없이 동작. **전개 구현**(2026-11-20, `mail-thread`): Thread/get 단독 → emailIds를 maxObjectsInGet 청크로 Email/get, 절단 없음, 사이에 사라진 메시지는 notFound. 메시지는 목록 coverage로 캐시, **스레드 소속은 저장 안 함**(Thread/changes 후속) |
| Thread/changes | Thread 전용 checkpoint와 캐시 무효화 | 후속 |
| Email/get | projection·null/누락·unknown·body part/truncation, notFound | 읽기 CLI·부분 캐시·오프라인 |
| Email/changes | Email 전용 state, hasMoreChanges, 파괴/dirty, 만료 복구 | 부분 캐시 구현·mock/실서버 검증 |
| Email/query | account/filter/sort/collapseThreads 및 queryState, 서버 순서 | 고정 조건의 첫 창·result reference·캐시. 메시지·접힌 스레드 목록이 **쿼리별 키**로 공존(스키마 v2) |
| Email/queryChanges | 객체 changes와 분리, same-query 적용, 계산 불가 시 창 재조회 | 후속, 현재는 창 재조회 |
| Email/set | 서버 Draft 생성·keywords/mailboxIds 변경·파괴. 로컬 의도 별도 보존 | fixture에서만 사용, 제품 후속 |
| Email/copy | source/target account 구별, 부분 성공·후속 원본 파괴 결과 분리 | 후속 |
| Email/import | 업로드한 원문 Blob→서버 Email, 항목별 성공/실패와 생성 ID | 후속 |
| Email/parse | Blob 파싱 결과, 저장된 Email 생성/동기화와 다름 | 후속 |
| SearchSnippet/get | filter·emailIds에 종속된 강조/문맥, 렌더링 시 안전 처리 | 후속 |
| Identity/get | 서버가 허용한 발신 identity와 signature·권한 | Submission 테스트에서만 사용 |
| Identity/changes | Identity 전용 state·생성/변경/파괴 반영 | 후속 |
| Identity/set | 서버 허용 범위, mayDelete·항목별 거부·로컬 편집 보존 | 후속 |
| EmailSubmission/get | envelope·deliveryStatus·undoStatus, 완료/실패 구분 | Submission 테스트에서만 사용 |
| EmailSubmission/changes | Submission 전용 state와 결과 관찰 | 후속 |
| EmailSubmission/query | 계정별 제출 기록 query 창, Email 목록과 별개 | 후속 |
| EmailSubmission/queryChanges | 제출 view의 queryState·순서 변경 | 후속 |
| EmailSubmission/set | Identity·Email·수신자 검증, undo/후속 Email 변경, 모호한 응답 재조정 | 로컬 양방향 테스트만, 제품 후속 |
| VacationResponse/get | account별 singleton과 활성화/기간/본문 | 후속 |
| VacationResponse/set | singleton 변경, 유효성·권한·오류, 자동응답 루프 경계 | 후속 |

## 현재 구현 순서

1. 직접 query와 제한된 result reference, get 응답 완전성 검사.
2. 앞 세 도메인 테이블과 query/checkpoint 보조 구조, 오프라인 목록·본문.
3. Email changes와 창 재조회, 명시적인 stale/coverage. 전체 복제 완료는 주장하지 않음.
4. Mailbox delta·queryChanges·Thread·페이지 창 확장.
5. Identity·로컬 Draft·서버 Draft·Blob/import/parse·수정/copy·Submission.
6. SearchSnippet·VacationResponse를 포함한 남은 메서드와 실패 경로.

M2/M3의 현재 데이터 구조가 후속 상태를 담을 수 있어도 해당 메서드 구현 증거는 아니다.
Thread/Identity/Submission state는 Email state를 재사용하지 않는다. 모든 메서드의
capability·account 범위·상한·call ID·오류 검사는 Core와 해당 Mail 연산의 책임이다.
