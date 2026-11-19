# Audit: Client Decision Drift

2026-11-19. 이 대화의 사용자 결정과 클라이언트 문서·코드·테스트를 정적으로 대조했다.
후속 사용자의 결정이 이전 제안보다 우선한다. 런타임 장애 재현이나 RFC 적합성 전수 감사는
아니다. 최초 감사는 기록만 추가했으며, 아래 후속 정정 상태와 구분한다.

## 후속 정정

2026-11-19 사용자 승인으로 D2~D6의 원문을 수정했다. Spec의 진행 승인·현재 Mail 구현,
연결 설계/발견 브리프의 인증 리다이렉트·광고 기반 선택 탐침·프로필 경계·메모리 보관을
현재 계약으로 정정했다. 과거 측정과 최초 타입 제안은 역사임을 표시했다.
Q1·Q2는 연결 설계의 미결 절에 남겼으며 코드의 보안 검사·성공 조건은 바꾸지 않았다.
아래 항목별 설명과 원래 줄 번호는 최초 감사 당시 증거다. 정정 후에는 줄 번호가 달라진다.

## 결과

최초 감사 결과는 확정 드리프트 6건이었다. CLI 이름 1건은 직전 작업에서 수정됐고,
나머지 문서 충돌 5건은 위 후속 정정으로 처리했다.
원인은 전부 '아무 문서도 없음'은 아니다. 결정이 새 구현 문서에는 있으나 구 설계의 명령형
문장을 폐기하지 않아, 읽는 문서에 따라 서로 반대의 작업을 지시하는 경우가 더 많다.
두 설계 질문은 합의 위반으로 확정하지 않고 별도 분류한다.

## 확정 항목

### D1. CLI 이름 — 수정됨

사용자가 합의한 이름은 axismundi였지만 cli/Cargo.toml과 도움말·실행 예시는 axj였다.
직전 변경에서 패키지·실행 파일·현재 명령 예시를 axismundi로 변경했다.
역사적 실측의 axj 표기는 명칭 변경 안내와 함께 보존했다. AXJ_* 환경변수 호환은 별도다.
README에 현재 이름과 호환 경계를 명시했다. 아직 미커밋 상태다.

### D2. P1: 철회된 검토 게이트와 구현 전 상태가 Spec에 남음

[SPEC-JMAP-CLIENT.md](SPEC-JMAP-CLIENT.md):5는 '문서 작성만 승인, 검토 전 구현 금지'이고
:79·:88은 Mail 워크플로·새 명령·테스트가 없다고 쓴다. 사용자는 검토 없이 진행을 승인했고
Mail CLI와 실서버 테스트가 이미 있다. [PLAN-JMAP-MAIL.md](PLAN-JMAP-MAIL.md)는 후속
승인을 기록했지만 Spec 첫머리는 고치지 않았다. Spec부터 읽는 다음 작업자가 구현을
중단하거나 이미 있는 기능을 다시 만들게 된다. 이는 이번 작업에서 발생한 새 드리프트다.

조치: 요구사항과 당시 상태를 분리하고, 승인 범위·현재 구현·남은 DB/발송을 일치시킨다.

### D3. P1: 인증 리다이렉트의 구 결론이 여전히 금지 규칙임

[DESIGN-CONNECTION-DISCOVERY.md](DESIGN-CONNECTION-DISCOVERY.md):76과
[BRIEF-DISCOVER.md](BRIEF-DISCOVER.md):48은 well-known에 인증할 수 없다고 단정한다.
최신 [네트워크 구현 기록](IMPLEMENTATION-NETWORK-TRUST.md):17은 동일 오리진 GET의
수동 리다이렉트에서 인증을 유지한다고 정한다. core/src/lib.rs의
same_origin_redirect_keeps_authorization 테스트도 후자를 검증한다.
구 문장을 따르면 Stalwart에서 고친 인증 유실을 다시 도입할 수 있다.

조치: 익명 발견과 인증된 Session 읽기를 분리하고, 수동 동일 오리진 추적 정책으로 통일한다.
SameHost 완화나 오리진 간 자격증명 전달을 허용하자는 뜻은 아니다.

### D4. P1: 철회된 WordPress 경로 추측이 탐침 설계에 남음

[BRIEF-DISCOVER.md](BRIEF-DISCOVER.md):30과
[DESIGN-CONNECTION-DISCOVERY.md](DESIGN-CONNECTION-DISCOVERY.md):101은 /wp-json/
직접 GET을 기본 탐침으로 적는다. 최신 결정·구현은 선택한 서비스만 발견하고 WordPress는
홈페이지가 광고한 REST 링크를 따른다. core/src/discovery.rs의
jmap_only_never_probes_other_services와 wordpress_requires_a_homepage_advertisement가
현 정책을 검증한다. 구 설계대로 복원하면 불필요한 경로 탐색과 Stalwart 차단 문제를
재발시킬 수 있다.

조치: 현재 발견 정책으로 바꾸고 구 URL·탐침표는 당시 실측임을 표시한다.

### D5. P2: 연결 경계를 호스트로 설명한 구 모델이 우선 문서에 남음

[DESIGN-CONNECTION-DISCOVERY.md](DESIGN-CONNECTION-DISCOVERY.md):26·:41은
'호스트당 연결'과 Connection=(host,credential,scheme)을 앞세운다. 같은 문서에
오리진 승인 주의는 있으므로 무조건 비밀을 공유하라는 문서는 아니다. 하지만 이후 합의는
서비스 endpoint·인증 프로필·인증 주체·Account·Actor를 구별하고, 같은 도메인의 복수 로그인도
격리하는 것이다. [DESIGN-JMAP-CLIENT.md](DESIGN-JMAP-CLIENT.md):18과
[Contacts 구현 기록](INTEROP-CONTACTS.md):7에는 새 경계가 있다.
한 호스트를 사용자/캐시 소유 키로 읽으면 이전에 논의한 계정 충돌을 되살리게 된다.

조치: 호스트는 발견 입력이지 소유 키가 아님을 구 설계 첫머리에서도 명확히 한다.
프로필별 파일과 전역 파일 중 아직 미결인 선택까지 확정된 것으로 쓰지는 않는다.

### D6. P2: 키체인을 GUI 선행 조건으로 둔 문장이 철회되지 않음

[DESIGN-CONNECTION-DISCOVERY.md](DESIGN-CONNECTION-DISCOVERY.md):214와
[BRIEF-DISCOVER.md](BRIEF-DISCOVER.md):121에는 키체인 보관과 GUI 전 단계가 남아 있다.
실제 합의는 세션 동안 Rust 메모리에 보관하고, 재시작 후 자격증명 보존은 별도 작업으로
미뤄도 된다는 것이었다. gui/src-tauri/src/connection.rs:12의 managed Connection은
메모리 상태를 쓰며 현재 keychain 플러그인을 구성하지 않는다.
또 'GUI는 연결 id와 라벨만 본다'는 문장은 비밀번호 입력/IPC 전달조차 없는 것처럼 읽힌다.
Angular는 입력한 비밀번호를 Rust에 전달한 뒤 입력란을 비운다. 지속 보관하지 않는 것과
입력 순간에도 다루지 않는 것은 다르다.

조치: 입력·전달·세션 보관·영구 보관 경계를 구분하고 키체인을 출시 전/후속 정책으로 명시한다.

## 별도 설계 질문 — 확정 건수에 포함하지 않음

### Q1. 문서에서 얻은 outbox와 Axismundi 전용 전송 승인

GUI는 Actor 정규 id를 익명 GET하고 outbox를 해당 문서에서 읽는다. 하지만
gui/src-tauri/src/connection.rs:326의 validated_outbox는 우리 REST actors/{uuid}/outbox
형태만 승인한다. :67은 첫 페이지를 page=1로 조립한다. 읽은 문서의 주소를 사용하는 것과
임의의 AP 서버/컬렉션을 지원하는 것은 현재 같지 않다.
이 검사는 전송 자격증명을 보호하는 의도적 테스트를 갖고 있으므로 무조건 지워서는 안 된다.
범용 endpoint 승인과 Collection의 first/next 해석을 구현할지, 현 GUI를 Axismundi AP
어댑터로 명시할지 결정하고 기록해야 한다.

### Q2. AP 중심 제품과 여러 서비스의 연결 성공 기준

과거 대화는 AP Session을 작업공간 진입 기준이라고 설명했다. 이후 사용자는 Stalwart
Mail·Contacts를 독립 지원하는 통합 클라이언트로 범위를 넓혔다.
현재 connection.rs:461은 WP/JMAP/AP 중 하나라도 결과가 있으면 연결을 유지하며,
GUI는 JMAP 발견을 기본 선택한다. 이는 현재 Stalwart 지원과 양립하므로 버그로 단정하지 않는다.
연결 성공, 서비스별 준비, 선택한 작업공간을 분리해 제품 정책으로 기록할 필요가 있다.

## 드리프트로 세지 않은 것

- Mail DB·오프라인·delta·제품 Draft/Submission: 계획에 남은 미구현.
- 로컬/서버 Draft·JSContact·AP Actor·WP User의 분리: 설계에 명시됨.
- 프로필별 파일 대 전역 파일·암호화·런타임 번역 라이브러리: 명시적 미결.
- Actor/Object 정규 URI 익명 GET·컬렉션 fallback 없음: README·코드·테스트에 반영됨.
- i18n 방향·초기 Roboto Flex/Symbols·본격 UI를 미루는 순서: 최근 문서에 기록됨.
- 서버의 로컬 AS2 저장 키 순서·Actor 활성화·scope 제거 등: 클라이언트 감사 범위 밖.

## 후속 정리 원칙

새 기억 파일을 늘리는 것만으로는 반대되는 기존 설계를 무력화하지 못한다.
현재 계약 문서를 지정하고, 철회된 규칙은 그 자리에서 수정하거나 역사로 표시해야 한다.
개별 구현 기록에만 최신 결정을 덧붙이는 방식은 반복하지 않는다.
최초 감사에서는 보고만 추가했다. 후속 정정은 문서만 변경했으며 런타임 구현이나
Mail DB를 추가하지 않았다. 검증은 상대 링크와 diff 검사이며 테스트 재실행은 하지 않았다.
