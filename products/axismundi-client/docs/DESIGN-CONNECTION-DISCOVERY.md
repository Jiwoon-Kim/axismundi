# Design — 연결과 기능 발견

클라이언트가 **어느 서버에, 어떻게 붙고, 무엇이 켜지는지**를 정하는 한 장. 화면 설계가
아니라 그 앞의 경계다.

```txt
정한다      자격증명이 어디에 묶이는지 · 발견 순서와 그 이유 · 비밀의 위치 · IPC 표면
정하지 않는다 AP 서명 위임의 서버 엔드포인트(없다) · OGC 의 제품 역할 · 실행 경계(블로킹 코어)
            화면 구성은 이 경계가 선 뒤에 온다
```

## 세 레인

```txt
A 연결    (host, credential).  그 호스트에게 인증한다
         WordPress REST · JMAP · (나중에) OGC API
B 위임    (actor, home server).  **임의의 호스트를 향해** "내가 그 Actor다" 를 증명한다
         AP signed fetch.  **개인키는 홈 서버에 남는다.  클라이언트가 들지 않는다**
0 익명    자격증명을 붙이지 않는다.  공개 AS2 조회가 여기다
```

A 와 B 가 다른 종류인 이유: JMAP 자격증명은 *그 호스트에게* 인증하지만, Actor 서명은
*상대가 누구든* 같은 주장을 한다. 그래서 "Mastodon 의 팔로워 전용 글" 은 세 번째 자격증명이
아니라 **A(우리 사이트 연결) + 위임 파라미터(어느 Actor로)** 의 조합이다.

## 연결은 프로토콜당이 아니라 **호스트당**이다

실측(2026-11-17, Application Password 하나):

```txt
GET /wp-json/wp/v2/users/me                              200
GET /wp-json/axismundi/jmap/session                      200
GET /wp-json/axismundi/activitystreams/local-objects/types  200
GET /.well-known/jmap                                    302 → 위 Session URL
```

그러니 로그인은 프로토콜마다 하나가 아니라 **호스트마다 하나**이고, 그 호스트에서 쓸 수 있는
프로토콜은 **발견의 결과**다.

```txt
Connection = (host, credential, scheme)
             그 위에서 가능한 것은 접속 뒤에 발견한다
```

**단, 같은 호스트라면 비밀을 공유해도 된다는 규칙이 아니다.** Axismundi 가 하나의 Application
Password 를 여러 API 에서 받는 것은 **그 서버가 지원하는 연결 방식**이지 클라이언트가 가정할
일반 규칙이 아니다. 자격증명은 **사용자가 승인한 오리진에만** 보낸다.

## 순서 — 서버 선택 → 발견 → 로그인 → 계정·Actor

이름은 "로그인" 이 아니라 **"서버 연결"** 이다. 데스크톱은 WordPress 프론트와 달리 어느
서버에 붙을지가 처음부터 정해져 있지 않다.

```txt
① 서버 선택   저장된 연결을 고르거나 주소를 입력한다.
             **도메인과 전체 URL 둘 다 받는다** — http://localhost:8884, 하위 경로 설치, 그리고
             이미 아는 Session URL 직접 입력까지
② 발견       **자격증명 없이** 먼저 한다.  결과를 사용자에게 보여준다
             JMAP Session 이 인증을 요구하면 "**로그인 필요**" 다 — "JMAP 미지원" 과 다르다
③ 로그인     **실제로 비밀을 보낼 주소와 인증 방식을 보여준 뒤** 받는다
④ 계정·Actor  서버가 내주는 JMAP Account 와 사용자가 행위할 수 있는 Actor 를 **각각** 보여준다.
             로그인 사용자명으로 둘을 추정하지 않는다
```

### ②가 ③보다 앞서는 것은 **기계적 강제**다

선호가 아니다. 측정(2026-11-17):

```txt
ureq 기본값   RedirectAuthHeaders::Never
             "Never preserve the authorization header on redirect.  This is the default."
.well-known/jmap 에서 시작 + Basic   → 302 를 따라간 뒤 **403**  (헤더가 떨어진다)
해석된 Session URL 에서 시작          → **200**
```

`.well-known/jmap` **에** 인증할 수가 없다. 먼저 인증 없이 해석하고, **해석된 URL 에**
인증한다. 그리고 이 기본값은 **지켜야 할 자산**이다 — 발견이 다른 오리진으로 튀어도
자격증명이 따라가지 않는다. 바꾸지 않는다.

우리 서버가 미인증 발견에 302 를 주고 그 뒤 403 이 나오는 것은 정상이다. 결함이 아니다.

#### 구현 세부

리다이렉트를 자동으로 따라가면 중간의 `Location` 을 잃는다. 발견 단계에서는
**`max_redirects(0)` 로 두고 `Location` 을 직접 읽거나** `save_redirect_history` 를 켠다.
그래야 "사용자가 승인할 최종 오리진" 을 ③에서 보여줄 수 있다.

```txt
입력이 기억할 것 둘
  사용자가 친 것     example.com / http://localhost:8884 / 전체 Session URL
  발견이 해석한 것   실제 Session URL.  **자격증명은 이쪽 오리진에 묶인다**
오리진이 바뀌었으면 그 사실을 말하고 승인을 다시 받는다
```

## 발견 — 프로토콜마다 다르고, **라우트 존재는 권한이 아니다**

```txt
WordPress REST  GET /wp-json/ 의 namespace 목록.  존재가 단서다
JMAP            .well-known/jmap → Session.  Session 의 accountCapabilities 가
                **계정별로 무엇이 열려 있는지** 답한다
AP              WebFinger / Actor 문서.  **capability 광고가 없다**
OGC API         OpenAPI 문서가 security scheme 을 선언한다 → 자동 발견 가능
```

**AP 가 유일하게 답하지 못한다.** Actor 의 `outbox` 가 보인다고 해서 (a) 거기에 무슨
자격증명으로 POST 할 수 있는지, (b) 로그인한 사용자가 그 Actor 로 행위할 수 있는지를 알 수
없다. outbox 는 컬렉션 주소다(AP §5.1).

```txt
금지   공개 Actor 문서만 보고 AP C2S 제출이나 서명 위임을 **켜지 않는다**
기록   이 구멍이 Session Pre-FEP 의 가장 구체적인 사례다 —
      "발견은 했는데 권한은 알 수 없다" 를 돌아가는 클라이언트가 못 하는 일로 보일 수 있다
```

## 가정하지 않고 발견하고, 없으면 끈다

"교집합이 Axismundi 뿐" 은 **지금 사실의 서술**이지 코드의 전제가 아니다.

```txt
평범한 WordPress   wp/v2 만 켜진다
Stalwart          JMAP 만 켜진다
Axismundi         wp/v2 · JMAP · AS2 가 켜진다 (자격증명 하나로, 위 실측)
검증              같은 연결 화면에서 세 서버에 붙어 각각 무엇이 켜지는지 본다.
                 Stalwart 는 Core/echo 까지, Axismundi 는 Todo 조회·저장까지면 충분하다
```

그리고 **권한은 발견과 또 다른 층**이다. 화면은 발견된 기능을 보여주되, 편집·공유·Actor
선택은 **서버가 알려 준 계정별 권한**으로 판단한다. 기능이 있다는 것과 이 사용자가 쓸 수
있다는 것은 다르다.

## 비밀의 위치와 IPC 표면

```txt
보관        OS 키체인.  웹뷰에도, 평문 설정 파일에도 두지 않는다
GUI 가 보는 것  **연결 id 와 라벨뿐.**  비밀은 Rust 밖으로 나가지 않는다
            (코어의 Credentials 가 Debug 에서 비밀을 가리고, 그 테스트가 있다)
scheme      Basic · Bearer · cookie+nonce · API key 를 **열거형**으로.
            "전역 인증 모드" 하나로 두지 않는다
```

```txt
invoke 에 여는 것      connect · discover · list_accounts · get_todos 처럼 화면이 필요한 동작
invoke 에 열지 않는 것  URL·헤더를 받는 **범용 요청**.  자격증명을 든 앱이 화면이 시키는 곳으로
                     요청하면 confused deputy 다
익명 레인             별도 명령.  **자격증명을 붙이지 않는다**는 것이 그 층의 불변식이고,
                     인증 연결의 쿠키나 헤더를 재사용하지 않는다
```

코어에 이미 있는 가드(`apiUrl` 에 박힌 자격증명 거부, HTTPS→HTTP 다운그레이드 거부)는
**IPC 에서 우회되면 무의미하다.** IPC 가 URL 을 받지 않는 것이 그 가드를 지키는 방법이다.

## 다음

```txt
먼저   Todo/set — 항목 수준 오류와 ifInState.  코어의 마지막 구조적 공백
그다음  이 문서를 화면으로 — 서버 연결 → 발견 결과 → 계정·Actor
나중   레인 B 의 선행 작업: "이 Actor 의 키로 이 URI 를 서명 GET" 하는 **우리 쪽 엔드포인트**.
      Mastodon 이 그 요청을 존중한다는 것은 소스로 확인했고, 우리 엔드포인트는 **없다**
보류   OGC API.  레인 A 라 나중에 더해도 이 모델이 바뀌지 않는다.  제품 역할이 미정이다
```
