# 브리프 — JMAP 전송 경계: 거짓 광고 없는 Session 을 낼 수 있는가

2026-10-25. 이 문서는 구현 지시가 아니라 **범위 확정**이다. 출발점은 "라우트 하나를
붙인다" 가 아니라 **Session 이 광고하는 URL 넷을 실제로 서비스할 수 있는가** 다.

## 왜 그 질문이 먼저인가

Session 은 `apiUrl` · `uploadUrl` · `downloadUrl` · `eventSourceUrl` 을 광고한다.
서비스할 수 없는 URL 을 광고하면 **능력을 거짓 주장**하는 것이고, 그것은 이 프로젝트가
`ldp:inbox` 와 `as:sensitive` 에서 각각 거부한 바로 그 형태다.

### 명세에 출구가 없다 (측정, 2026-10-25)

RFC 8620 원문을 §2 · §6 · §7 까지 받아 확인했다.

```txt
§2    성공한 GET 은 "MUST return a JSON-encoded Session object".
      네 URL 중 어느 것도 required/optional 로 표시되지 않는다.
      capability 가 없을 때 무엇을 넣어야 하는지 규정이 없다.
§6.1  uploadUrl    URI Template level 1, "MUST contain a variable called accountId"
§6.2  downloadUrl  "MUST contain variables called accountId, blobId, type, and name"
§7.3  eventSourceUrl "MUST contain variables called types, closeafter, and ping"
§7    푸시 메커니즘은 **둘**이다 -- EventSource(§7.3)와 PushSubscription(§7.2).
      **EventSource 를 서버가 구현해야 한다는 문구는 없다.**
      클라이언트가 "any push service supported by their environment" 를 쓸 수 있다고 한다.
```

마지막 줄이 유일한 여유다 -- 푸시 **메커니즘**은 선택이 있지만, `eventSourceUrl` **필드**가
선택이라는 근거는 찾지 못했다.

### 우리 검증기는 넷을 전부 요구한다

`modules/rfc8620/session.php` `validate_transport()` 는 네 URL 각각에 대해 HTTPS,
자격증명·fragment 금지, 그리고 **정확한 템플릿 변수 집합**을 요구한다
(`array_diff` 양방향이라 누락도 잉여도 거부). 즉 하나라도 비우면 Session 이
만들어지지 않는다. 이것은 설계대로이고, 가짜 URL 로 채우는 길을 막아 둔 것이다.

## URL 별 대조 — 지금 무엇을 낼 수 있는가

```txt
apiUrl          낼 수 있다.  Engine::execute() 가 이미 메서드 배치를 실행한다.
                경계가 할 일은 POST 본문을 그 호출로 넘기는 것.

uploadUrl       **낼 수 있을 것으로 보인다.** 기존 결정과 충돌하지 않는다 --
                "업로드는 WordPress 코어 API 를 쓴다 / JMAP blob 기계를 만들지 않는다"
                (open-items) 는 **별도 blob 저장소를 만들지 않는다**는 뜻이고,
                업로드를 JMAP 으로 노출하지 않는다는 뜻이 아니다.
                POST 를 받아 WP attachment 를 만들고 JMAP 업로드 응답
                (accountId, blobId, type, size)을 돌려주면 된다.
                → blobId 를 무엇으로 할지가 결정 사항 (attachment id? content hash?)

downloadUrl     uploadUrl 과 같은 축. attachment 를 서비스한다.
                → **접근 제어가 실질 작업이다.** 비공개 미디어가 존재하므로
                  (`_ax_media_visibility`) 공개 URL 로 그냥 넘기면 안 된다.

eventSourceUrl  **여기가 막힌다.** SSE 는 연결을 오래 붙잡는데 PHP-FPM 워커 모델과
                공유 호스팅에서 성립하기 어렵다. 스테이징이 WP.com Atomic 이므로
                거기서 되는지가 사실 문제다.
                → 이 브리프의 첫 작업은 **구현이 아니라 측정**이다.
```

## 그래서 첫 작업은 측정 셋이다

```txt
M1  Atomic(스테이징)에서 장시간 열린 HTTP 응답이 유지되는가.
    text/event-stream 으로 ping 을 내보내며 N 초 버티는지 실측한다.
    안 되면 eventSourceUrl 을 "진짜로" 서비스할 수 없다는 뜻이고 M3 로 간다.
M2  비공개 attachment 를 요청자별로 인가해 서비스할 수 있는가.
    `_ax_media_visibility` 가 이미 있으므로 그 판정을 downloadUrl 에서 재사용한다.
M3  eventSourceUrl 을 서비스할 수 없을 때의 선택지를 명세·배포 구현으로 확인한다.
    후보: (ㄱ) 연결 즉시 ping 하나 보내고 닫는 최소 구현 -- 거짓은 아니지만 쓸모가 적다
          (ㄴ) PushSubscription(§7.2)만 지원하고 eventSourceUrl 은 즉시 오류를 내는 URL
          (ㄷ) 필드를 비우고 비표준으로 표시 -- 검증기를 고쳐야 하고 클라이언트가 깨질 수 있다
    **추측으로 고르지 않는다.** Stalwart·Apache James 가 푸시 없이도 Session 을
    어떻게 내는지 확인한다. 언어 무관하게, 모호한 전송 계약은 배포 구현이 정리한다.
```

## 경계가 포함하는 것 — 라우트 하나가 아니다

```txt
인증        쿠키 세션과 Application Passwords 둘 다. 코어에 맡긴다.
            (공식 앱 측정: 자체 호스팅은 Application Passwords 또는 XML-RPC -- S10 절 참조)
CSRF        쿠키로 인증된 요청에는 nonce 가 필요하다. 토큰 인증 요청에는 아니다.
            둘을 같은 규칙으로 다루면 한쪽이 깨진다.
입력 검증   I-JSON 와이어 검증. Engine 주석이 "belong to the future transport boundary"
            로 비워 둔 바로 그 자리다.
오류        HTTP 오류와 메서드별 오류를 구별한다. RFC 8620 의 problem details 형태.
요청 크기   Engine 이 maxSizeRequest 를 이미 들고 있다. 경계가 그 값을 쓴다.
CORS        임의 출처에 인증을 열지 않는다. 같은 출처 PWA 가 1 차 소비자다.
```

## 검증 순서

```txt
1  M1·M2 측정 → eventSourceUrl 범위 확정
2  apiUrl + Session GET (인증·CSRF·오류·크기 포함)
3  TS 클라이언트로 end-to-end: Session 읽기 → 명시적 accountId → AddressBook 호출
   후보는 jmap-jam(독립 검증) 또는 jmap-client-ts.
   **주의**: jmap-client-ts 는 `accountId:null` 을 primary Account 로 자동 대체한다고
   문서에 적혀 있다. 우리 규율은 **명시 선택이고 폴백 금지**이므로, 그 편의 기능이
   우리 계약을 우회해 통과시키지 않는지 확인해야 한다.
4  uploadUrl·downloadUrl (M2 의 인가 판정 포함)
```

## 이 브리프가 대신하지 않는 것

```txt
B·C1·C2 의 브라우저 검증     선택 화면과 편집기의 다중 탭·쿠키 동작은 JMAP 과 무관하다.
                             TS 클라이언트 검증이 그것을 대신하지 않는다.
C3 예약 발행 Account 영속화   별개. Account 전환 완료선의 마지막 조각이다.
Contacts AddressBook 호환    GroupOffice 는 `Contact/get` 같은 자체 모델을 쓴다.
                             라우팅 구조는 참고하되, 그것이 RFC 9610 호환을 증명하지 않는다.
```

## 참고로만 읽을 구현체

조사는 멈춘다. 전송 계약이 모호해질 때만 언어 무관하게 다시 본다.

```txt
GroupOffice (PHP)   엔드포인트 하나에 메서드 분기 -- GET=Session, POST=Router, OPTIONS=CORS.
                    `www/api/jmap.php` 가 그 전부다(실제로 읽음). 우리는 WP REST route 로
                    같은 모양을 만들되 직접 진입 방식은 쓰지 않는다.
jmap-kit (TS)       capability 확장을 플러그인으로 다룬다. 우리 Registry 와 같은 문제이므로
                    경계가 생긴 뒤 우리 capability 가 클라이언트에 어떻게 보이는지 대조한다.
jmap-jam (TS)       fetch 기반, 명시적 accountId. 독립 검증에 쓴다.
zend-jmap (PHP)     저장소가 폐기를 명시한다. 의존 후보에서 제외.
```
