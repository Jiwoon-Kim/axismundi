# Persistent Mail Probe for GUI E2E

2026-11-19. 로컬 Stalwart의 admin과 Alice로 GUI 검증에 쓸 고정 데이터를 준비했다.
Mail GUI는 아직 없으므로 현재 증거는 실제 서버 배달과 JMAP 조회다. 프론트엔드 Thread
화면·작성 기능 구현이 끝났다는 뜻은 아니다.

## 화면에서 확인할 데이터

모든 제목은 `[Axismundi probe v1]`로 찾는다.

| 데이터 | 기대 화면·상태 |
|---|---|
| Conversation | admin→Alice→admin→Alice→admin의 4-message 왕복. 계정마다 Sent 2개·Inbox 2개 |
| Independent message | Alice→admin의 별도 메일. Conversation과 다른 threadId |
| Server draft - not sent | 계정마다 Draft 1개. 서버 $draft Email이며 클라이언트 로컬 작성본은 아님 |
| Axismundi Probe 메일함 | Conversation의 첫 메일을 원래 소속과 함께 표시하는 N:M 사례 |

Subject만 맞춘 것이 아니라 Message-ID, In-Reply-To, References를 가진 실제 답장을
Email/set과 EmailSubmission/set으로 배달했다. Thread/get에서 네 메일의 소속을 확인했고,
collapseThreads=true인 Conversation 쿼리는 한 행을 반환했다. 계정별 Thread ID는 서로
다른 값일 수 있으며, 상대 계정의 Email/Thread ID를 같은 것으로 가정하면 안 된다.

다음 GUI 구현에서의 관찰 기준:

1. Inbox와 Sent에서 같은 Conversation이 보이고, 펼치면 서버가 묶은 네 메일을 읽는다.
2. Independent message가 그 대화에 섞이지 않는다.
3. Probe 메일함의 첫 메일과 원래 메일함의 첫 메일이 같은 Email임을 유지한다.
4. 서버 Draft를 받은 편지나 이미 발송된 메일로 표시하지 않는다.
5. 사용자 조작 전까지 JMAP 조회만으로 읽음 처리를 하지 않는다.

fixture는 테스트 종료 후 삭제하지 않고 보존했다. 기존 송수신 smoke-test 메일도 별도로
있을 수 있으므로 계정 전체 메일 수를 위 fixture 수와 같다고 가정하지 않는다.
현재 제품 CLI는 메시지 행 목록이며 Thread 전개는 아직 구현하지 않았다.

실행 결과: 양쪽 계정 각각 Thread/get의 멤버 4개, collapsed query 한 행,
다른 independent threadId, 미발송 Draft 상태를 확인했다. 재실행에서도 같은 네 Email ID를
유지하고 새 발송 없이 exit 0으로 끝났다. workspace all-targets 기본 테스트 35개도 통과했다.

## 재현과 중복 방지

환경에서 두 계정의 자격증명과 로컬 Session URL을 공급하고 명시적으로 실행한다.

```powershell
# AXJ_SESSION_URL, AXJ_USER, AXJ_SECRET, AXJ_ALICE_USER, AXJ_ALICE_SECRET
$env:AXJ_SEED_MAIL_PROBE = '1'
cargo run -p axismundi-jmap-core --example seed_mail_probe
```

실행 파일은 loopback Session·동일 오리진 API·별도 인증 계정·예상된 로컬 발신 Identity를
확인한다. 수신자는 admin@example.org와 alice@example.org 두 계정뿐이다.
생성된 이메일/제출 ID를 보고하며, 고정 Message-ID를 재조회해 이미 양쪽에 있는 메일을
재발송하지 않는다. 한쪽만 있으면 모호한 제출로 간주해 멈춘다. 실패 후 무조건 새 메일을
발송하거나 계정 전체를 지우는 정리 명령은 없다.

이 설치에서는 Message-ID 헤더 필터 검색이 기대한 메일을 찾지 못했지만, 제목 검색 후
Email/get의 messageId 속성에서는 같은 값을 확인했다. 따라서 fixture 식별은 제한된 제목
검색과 정확한 messageId 비교를 사용한다. 이를 서버 전체의 헤더 검색 지원 여부로 일반화하지
않는다. 재실행은 메일을 추가 발송하지 않는지 별도로 확인한다.

수동으로 fixture 일부를 지우거나 Draft를 발송/편집했다면 해당 상태를 먼저 확인한다.
이 도구는 임의의 사용자 편집을 되돌리는 fixture 복구 엔진이 아니다.
비밀번호는 저장소에 넣지 않는다. 계정·Email·Thread·Mailbox의 설치별 숫자/문자 ID도
프로토콜 상수로 하드코딩하지 않고 실행 결과에서 읽는다.
