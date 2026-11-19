# Mail Partial Cache and Delta

2026-11-19. `core/src/mail/cache.rs`의 첫 Mail 영속화 구현 기록이다.
전체 범위는 [메서드 대응표](MATRIX-JMAP-MAIL.md), 단계는 [계획](PLAN-JMAP-MAIL.md)을 따른다.
Mail RFC 전체 구현이나 모든 메일의 offline replica 완료를 뜻하지 않는다.

## 도메인과 프로필

기존 프로필당 파일 사용 방식을 유지하고 Mail 테이블만 가산한다. 전역 단일 파일로의
이관이나 Contacts 행 재작성은 하지 않는다. 한 프로필의 동일 SQLite 파일을 Contacts와
Mail 양쪽에서 열 수 있다. 공통 `metadata`의 profile·service 바인딩을 확인하며,
Mail 버전은 `mail_schema_version=1`, Contacts 버전은 기존 `schema_version`으로 독립 관리한다.
같은 프로필 문자열로 파일을 열더라도 다른 API URL·로그인명으로 인증하면 사용을 거부한다.
비밀번호는 저장하지 않는다. 암호화·키 관리 정책은 여전히 출하 전 미결이다.

| 테이블 | 현재 저장 내용 |
|---|---|
| mail_accounts | account별 로컬 revision. 낡은 네트워크 응답의 쓰기 거부 |
| mail_mailboxes | JSON·role. 권한·계층·서버 count도 받은 JSON 안에 보존 |
| mail_emails | 부분 JSON·속성 coverage·subject/receivedAt 파생 컬럼 |
| mail_email_mailboxes | account별 Email↔Mailbox N:M 파생 관계 |
| mail_sync_state | 타입별 불투명 checkpoint |
| mail_queries | account/mailbox별 고정된 첫 창의 조건·query 응답·stale |
| mail_query_items | 서버가 준 창의 위치와 Email ID. get 반환 순서로 재정렬하지 않음 |
| mail_unavailable | notFound와 destroyed를 미캐시 상태와 구분 |

Thread·Identity·Submission·로컬 Draft 테이블은 각 소유 기능의 구현 전이므로 아직 생성하지
않는다. 빈 범용 records 테이블로 대체하지 않는다. 쿼리의 현재 키는 mailbox당 고정 조건의
첫 창에 한정된다. 사용자 정의 필터·sort·희소 페이지 창 확장 전에 query 식별을 확장해야 한다.

## 읽기와 확보 범위

`mail-sync`는 지정 Mailbox 또는 role=inbox의 첫 창을 조회한다. `collapseThreads=false`,
`min(maxObjectsInGet,50)`이며 다른 메일함은 `--mailbox`로 선택한다. `mail-read --db`는
본문을 필요할 때 조회하고 저장한다. `mail-offline`은 자격증명·네트워크 없이 저장본을 읽는다.

요청 상한이 허용하면 Email/query→Email/get의 `#ids` result reference를 사용한다.
호출 수나 직렬화 byte 상한을 넘으면 두 요청으로 나눈다. Thread 전개는 아직 하지 않는다.
불완전·중복·요청하지 않은 get ID는 거부하고, 누락은 명시적 notFound여야 한다.

서버 JSON은 의미적으로 보존하고 반환된 속성만 병합한다. 누락된 기존 필드는 유지하고
명시적 null은 반영한다. bodyValues는 이번에 받은 집합으로 교체한다. coverage에는 실제
반환된 속성, body fetch 옵션·64 KiB 한도·part별 truncation/encoding 문제·저장 byte 수를
남긴다. body 문자열을 coverage에 중복 저장하지 않는다. JSON에서 MIME 원문을 만들지 않는다.

오프라인 응답은 `document`, `coverage`, `staleProperties`, `available`을 구분한다.
본문 속성이 없거나 coverage에서 빠진 것을 완전한 본문으로 읽으면 안 된다.
부분 get 이후 오래된 본문이 남을 수 있으므로 렌더러는 원본 JSON만 보고 신선도를 판단하지
않아야 한다. 소속 색인도 마지막 fetch의 파생 데이터이며 mailboxIds coverage가 무효화되면
최신 권한·소속의 근거로 쓰지 않는다.

## 변경과 원자성

처음에는 빈 Email/get으로 객체 state를 확보한 다음 창을 읽는다. 이후 Email/changes의
hasMoreChanges를 따라가며 각 페이지의 oldState를 확인한다. 최대 32페이지 후에도 남으면
진도를 보존한 채 재실행을 요구한다. state 문자열을 정렬하거나 queryState와 비교하지 않는다.

변경된 캐시 객체는 coverage를 비워 dirty를 영속화한다. 미캐시 생성 객체를 전부 내려받지는
않고, 쿼리는 별도로 새로 읽는다. 파괴는 객체와 파생 소속을 제거하고 이유를 기록한다.
쿼리 이탈만으로 Email을 삭제하지 않는다. cannotCalculateChanges는 새 state를 얻고 기존
객체·쿼리를 stale로 만든 뒤 필요한 창만 다시 읽는다. 캐시 전체를 먼저 지우지 않는다.

데이터·query 순서·queryState의 확정과 dirty/checkpoint의 확정은 각각 SQLite 트랜잭션이다.
네트워크는 쓰기 트랜잭션 밖에서 수행하고 account revision을 재검사한다. get 실패 시에는
이전 창과 데이터 또는 이미 영속화된 dirty/checkpoint가 남는다. 유효한 query 응답 이후에도
get에서 notFound나 다른 mailbox 소속이 보이면 해당 창을 stale로 표시한다.
독립된 type/query/get 응답을 하나의 고정된 서버 스냅샷으로 주장하지 않는다.

Mailbox는 매 sync마다 전체 get으로 갱신하고 Mailbox state도 따로 저장한다.
Mailbox/changes의 updatedProperties 처리와 Email/queryChanges는 아직 미구현이며,
현재는 창 재조회 fallback을 의도적으로 쓴다. `partialCache=true`와 `queryStale`를 노출한다.

## 검증

이번 실행 결과: workspace 기본 테스트 35개(Core 34·CLI 1) 통과. 별도로 ignored
실서버 테스트 4개(Contacts·Mail 읽기·Mail 캐시·양방향 Submission)를 명시 실행해 통과했다.
최종 CLI 온라인→본문 저장→자격증명 없는 offline 조회도 exit 0으로 통과했다.
문서 상대 링크와 git diff --check를 확인했다. GUI 변경·빌드·화면 검증은 이번 범위가 아니다.

`cargo test --workspace`의 Mail 테스트가 다음을 다룬다.

- Contacts schema/행/pending edit 보존, Mail 추가 후 양쪽 reopen, 프로필·서비스·Account 격리.
- 알 수 없는 Mail schema version 거부, 부분 병합·null·unknown 보존·body coverage.
- 트랜잭션 rollback, 오래된 revision 거부, 파괴의 소속 제거.
- 작은 상한(2), result reference와 call ID 대응, 호출 수 1에서 요청 분리.
- 다중 changes 페이지, 서로 다른 get/type state, query 순서, query 이탈과 파괴 구분.
- cannotCalculateChanges, 불완전 get 이후 dirty/checkpoint와 이전 창 보존.

실서버 opt-in:

```powershell
# Supply AXJ_SESSION_URL, AXJ_USER, AXJ_SECRET for the local development server.
cargo test -p axismundi-jmap-core --test mail_cache_stalwart -- --ignored --nocapture
```

Stalwart에서 격리된 Mailbox/Email을 만들고 snapshot·본문 저장·offline reopen·외부 keywords
변경·메일함 이동·실제 파괴·프로필 격리를 검증한 뒤 자기 fixture만 삭제했다.
CLI도 임시 DB에 mail-sync→mail-read를 실행한 후 자격증명을 제거하고 mail-offline으로
재조회한다. GUI·HTML 렌더·외부 송신 검증이 아니며 사용자 메일은 수정하지 않는다.
