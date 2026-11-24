# Mail Interoperability

## 2026-11-20 계정별 메일함과 관리자의 계정 횡단 읽기

웹 UI 의 Mailbox 화면(`/account/Account/Mailbox`)은 로그인한 계정의 메일함이다. 시스템 메일함은
계정마다 따로 있다. 두 계정에서 각자 Mailbox/get:

```txt
            account  Inbox  Sent   probe 메일함
admin       b        4/4    3/1    k (1/0)
alice       c        3/3    4/1    h (1/1)
```

id `a`~`e` 가 두 계정에서 같지만 카운트가 다르다 — id 는 계정 범위라 같은 문자열의 별개 객체다.
저장소 덤프로 확인한 이유: id 는 계정별 문서번호의 인코딩이다([저장소 구조](INTEROP-STALWART-STORAGE.md) §4).
캐시 키가 `(account, id)` 인 이유(DESIGN §2). `shareWith` 는 모두 `{}`. role 이 같은 이름으로 반복되는
건 서버가 계정마다 기본 세트를 만들기 때문이다(RFC 8621 role 은 계정 안에서만 유일).

**관리자는 Session 에 없는 계정을 읽을 수 있다.** admin 의 Session 은 `b` 만 광고하지만
`accountId:"c"` 로 Mailbox/get·Email/query 를 보내면 alice 의 데이터가 성공으로 온다. alice 가
`b` 를 요청하면 `accountNotFound`. Stalwart 관리자 권한의 성질이며 공유가 아니다. 클라이언트는
`choose_account` 가 Session 광고 계정만 허용하므로 이 경로를 쓰지 않는다. 관리자 자격증명으로
클라이언트를 테스트할 때 "읽혔다"를 "광고됐다"로 해석하지 않는다.

## 2026-11-19 두 계정의 로컬 Submission

별도 User 역할의 `alice@example.org`를 기존 로컬 도메인에 추가했다.
관리 계정 `admin@example.org`와 서로 다른 JMAP account에서 다음을 검증했다.

- Identity/get으로 발신 identity를 얻고 Draft Email을 생성.
- EmailSubmission/set으로 해당 로컬 상대에게 제출.
- 상대 Inbox를 조회해 고유한 fixture 제목과 본문이 실제 도착했는지 확인.
- 두 방향 모두 deliveryStatus의 delivered=yes 및 250 응답 확인.
- 발신 Email은 Sent 역할 메일함으로 이동하고 $draft를 제거.

서버 내부 계정 간 배달이므로 인터넷 MX·외부 SMTP 포트 공개가 필요하지 않았다.
이 결과는 Stalwart의 로컬 배달·표준 JMAP Submission 왕복 검증이며,
제품의 Mail 작성 UI·오프라인 발송 큐·범용 Submission 구현 완료를 뜻하지 않는다.

명시적으로 실행하는 테스트:

```powershell
# Supply AXJ_SESSION_URL, AXJ_USER, AXJ_SECRET,
# AXJ_ALICE_USER and AXJ_ALICE_SECRET in the current process.
cargo test -p axismundi-client-core --test mail_submission_stalwart -- --ignored --nocapture
```

테스트는 loopback 및 동일 오리진 API를 요구하고 admin@example.org와 alice@example.org
사이만 수신자로 지정한다. 기본 성공 경로에서는 생성한 Submission과 양쪽 Email ID만
삭제한다. 실패·강제 종료 시 fixture가 남을 수 있으므로 무조건 재제출하지 않는다.
`AXJ_KEEP_MAIL_FIXTURES=1`은 GUI 확인을 위해 성공한 fixture를 보존하는 명시적 옵션이다.
최초 검증에서는 양방향 각 1건을 보존했다. 이는 제품의 자동 재시도 정책이 아니다.
비밀번호·토큰은 문서나 코드에 저장하지 않는다.

## 2026-11-19 실서버 검증

Stalwart `axismundi-jmap-interop`와 Caddy `axismundi-stalwart-https`를 실행하고,
`https://localhost:18881/.well-known/jmap`에서 시작해 플랫폼 TLS 검증을 유지한 채
리다이렉트·인증·Mail 읽기를 확인했다. 현재 설치의 account는 `b`였으며 테스트는
Session에서 계정을 선택한다. 해당 문자열이나 메일함 ID를 제품 코드에 고정하지 않는다.

- CLI `mailboxes`: role과 myRights를 포함한 메일함 5개 조회.
- 실서버 테스트: 합성 Email을 inbox에 생성하고, 비어 있지 않은 목록·제목·본문·
  없는 ID의 notFound를 확인한 뒤 생성한 Email ID만 삭제.
- EmailSubmission·SMTP는 사용하지 않았고 외부 발송은 하지 않았다.
- CLI `mail-inbox`: fixture 정리 후 빈 목록을 정상 반환.
- 기존 Contacts 실서버 회귀도 통과: snapshot·오프라인 재조회·외부 delta·
  queued update·충돌 편집 보존·삭제.

실행 명령은 클라이언트 루트 기준이다. 자격증명은 환경에서 제공하고 커밋하지 않는다.

```powershell
$env:AXJ_SESSION_URL = 'https://localhost:18881/.well-known/jmap'
# Supply AXJ_USER and AXJ_SECRET securely in the current process.
cargo test -p axismundi-client-core --test mail_stalwart -- --ignored --nocapture
cargo test -p axismundi-client-core --test contacts_stalwart -- --ignored --nocapture
cargo run -p axismundi -- mailboxes --session-url $env:AXJ_SESSION_URL
cargo run -p axismundi -- mail-inbox --session-url $env:AXJ_SESSION_URL
```

`mail_stalwart`는 명시적 opt-in 테스트이며 loopback Session과 동일 오리진 apiUrl만
허용한다. 일시적 합성 메일 생성·삭제 권한이 필요하다. 테스트 중 프로세스를 강제로
종료하면 fixture가 남을 수 있다. 자동 재실행이 임의의 기존 메일을 삭제하지는 않는다.
본문 확인 실패 시에도 생성 ID 정리를 시도하지만 서버 장애 시 정리는 실패할 수 있다.

이번 결과는 읽기 초기 경로의 상호운용성 증거다. result reference·Thread·Mail DB·
queryChanges·첨부 다운로드·발송이나 RFC 전체 구현 완료를 뜻하지 않는다.

## 컨테이너 정리

현재 config/data bind mount를 공유하던 중지 컨테이너
`axismundi-jmap-interop-before-localhost-20261119`와
`axismundi-jmap-interop-before-https-20261119`만 제거했다.
외부 데이터 디렉터리·볼륨·이미지·WordPress 환경은 삭제하지 않았다.
현재 Stalwart와 HTTPS 프록시는 검증 후 실행 상태로 유지했다.
