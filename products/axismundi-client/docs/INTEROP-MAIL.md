# Mail Interoperability

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
cargo test -p axismundi-jmap-core --test mail_stalwart -- --ignored --nocapture
cargo test -p axismundi-jmap-core --test contacts_stalwart -- --ignored --nocapture
cargo run -p axj -- mailboxes --session-url $env:AXJ_SESSION_URL
cargo run -p axj -- mail-inbox --session-url $env:AXJ_SESSION_URL
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
