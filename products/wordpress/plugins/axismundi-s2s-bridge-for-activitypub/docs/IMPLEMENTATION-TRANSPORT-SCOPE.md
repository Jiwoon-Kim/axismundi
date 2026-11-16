# S3a Transport 바인딩 및 Account 차단

후속 S3b 구현은 [Actor별 서명 키](../../axismundi-actors/docs/IMPLEMENTATION-SIGNING-KEYS.md)에 별도로 기록한다.
아래 키 미구현 설명은 S3a 시점의 범위다.

2026-10-24. S3의 첫 구현 단위다. Actor별 키 생성·저장·명시 회전(S3b)은 아직 구현하지 않았다.
S3 전체 완료 또는 S4 UNIQUE 해제 조건 충족을 주장하지 않는다.

## Transport provenance

Actors DB 35.0은 기존 Actor 행에 `transport_kind`, `transport_user_id`를 추가한다.
`upstream_user`는 공식 플러그인의 User 키, `upstream_application`은 기존 Application 키를 참조한다.
공개키·비밀키 선택과 공식 per-user Inbox의 수신 대상 해소는 이 필드만 사용한다.
Bridge는 더 이상 이 경로에서 local_user_id를 읽지 않는다.

마이그레이션은 미초기화된 로컬 행만 기존 바인딩에서 backfill한다. 이후 소유권 호환 컬럼이
바뀌어도 재실행이 transport를 따라 바꾸지 않는다. 키 재료·Actor URI·key ID는 바꾸지 않는다.
부분 실패는 버전을 완료로 올리지 않는다. MySQL DDL의 트랜잭션 롤백을 주장하지 않는다.
누락·알 수 없는 바인딩은 Application 키로 폴백하지 않고 키를 제공하지 않는다.
구 per-user 라우트에서 둘 이상의 Actor가 같은 바인딩으로 발견되면 임의 선택하지 않는다.

이번에는 신규 Actor도 기존 provider 선택을 명시적으로 저장한다. 독립 키를 받는 신규 Actor
정책은 S3b에서 구현해야 한다. 현재 Application 키 공유가 해소됐다고 보아서는 안 된다.

## 수신 차단

공식 Moderation의 사이트·기존 User 차단은 보존하고, 수신 Account → 발신 Actor의 활성 Block을
추가 검사한다. User 차단의 호환 문맥도 ownership이 아니라 transport 바인딩으로 얻는다.
Group의 운영자 개인 차단을 Group 차단으로 자동 상속하지 않는다.

Actor UUID POST와 기존 per-user/shared handoff 모두 공통 판정을 통과한다.
공유 delivery는 수신자별로 검사해 차단하지 않은 Account의 receipt만 기록한다.
모든 명시 수신자가 차단했으면 기록·Actor Update 적용 전에 거부한다.
Block 저장소 미준비·SQL 실패를 차단 없음으로 해석하지 않는다.
공유 Activity가 다른 Account에 기록되더라도 차단한 Account의 Follow 상태를 바꾸거나
자동 Accept를 유발하지 않도록 관계 처리에서도 해당 로컬 Account의 Block을 확인한다.
기존 공식 엔드포인트의 HTTP 응답 정책 자체는 이번에 소유하지 않는다.

## 검증

`tests/audit-actor-inbox.php`는 기존 수신·서명 감사에 transport/provenance 및 차단 검사를 추가한다.
기존 User 연결을 제거해도 공개·비밀키와 구 Inbox 대상이 유지되는지, replay 및 실패 복구,
Group 키 불변, 차단/Undo, 공유 수신자 격리, 사이트 차단, Block SQL 실패를 검사한다.
`tests/audit-outbound-delivery.php`와 Actors ownership 감사로 기존 발신 및 접근도 회귀 검사한다.

로컬 감사: Actor Inbox 57건, outbound delivery 40건, legacy Inbox handoff 28건,
ownership 29건, JMAP Session 47건 통과.
Activities 관계 21건·로컬 소셜 25건도 통과했다.
키 선택을 local_user_id로 되돌린 변이는 기존 키 유지 검사에서 실패했다.
Account Block을 무시한 변이는 차단 수신 검사에서 실패했고,
Block 조회를 생략한 변이는 SQL 실패 거부 검사에서 실패했다. 모두 정상 코드로 복원했다.
Follow 처리의 Block 보호를 제거한 변이는 다른 Account의 receipt가 차단된 Group의
Follow 관계를 생성하는 검사에서 실패했다.

## 다음 단위

S3b는 비밀키 저장·접근·유출 방지, 신규 Actor 독립 키, 기존 키를 보존하는 명시적 회전과
감사 기록, 공개/비밀키 일치, 전달 큐의 키 해소를 함께 다룬다. 운영 중인 키는 이번에 회전하지 않았다.
Application은 Actor 타입이지 인스턴스별 단수 제약이 아니다. sharedInbox와 인스턴스 서명자 역할도
별개다. 그 소유권 및 관리 UI는 S9에서 결정하며 이번에는 기존 공유 URL을 유지한다.
