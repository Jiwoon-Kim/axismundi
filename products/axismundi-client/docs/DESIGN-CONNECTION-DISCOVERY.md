# Design — 연결과 기능 발견

2026-11-19 정정. 이 문서는 현재 연결 계약이며, 2026-11-17 실측은 역사로 구분한다.
구체적 동작·검증 명령은 [네트워크 구현](IMPLEMENTATION-NETWORK-TRUST.md),
저장 소유 경계는 [JMAP 설계](DESIGN-JMAP-CLIENT.md)를 함께 따른다.

클라이언트가 **어느 서버에, 어떻게 붙고, 무엇이 켜지는지**를 정하는 한 장. 화면 설계가
아니라 그 앞의 경계다.

```txt
정한다      자격증명이 어디에 묶이는지 · 발견 순서와 그 이유 · 비밀의 위치 · IPC 표면
정하지 않는다 AP 서명 위임의 서버 엔드포인트(없다) · OGC 의 제품 역할 · 실행 경계(블로킹 코어)
            화면 구성은 이 경계가 선 뒤에 온다
```

## 세 레인

```txt
A 연결    (인증 프로필, 승인된 서비스 endpoint, credential).  승인된 오리진에 인증한다
         WordPress REST · JMAP · (나중에) OGC API
B 위임    (actor, home server).  **임의의 호스트를 향해** "내가 그 Actor다" 를 증명한다
         AP signed fetch.  **개인키는 홈 서버에 남는다.  클라이언트가 들지 않는다**
0 익명    자격증명을 붙이지 않는다.  공개 AS2 조회가 여기다
```

A 와 B 가 다른 종류인 이유: JMAP 자격증명은 *그 호스트에게* 인증하지만, Actor 서명은
*상대가 누구든* 같은 주장을 한다. 그래서 "Mastodon 의 팔로워 전용 글" 은 세 번째 자격증명이
아니라 **A(우리 사이트 연결) + 위임 파라미터(어느 Actor로)** 의 조합이다.

## 연결 경계는 인증 프로필이며 호스트는 발견 입력이다

과거 실측(2026-11-17, Application Password 하나; 아래 경로는 당시 기록):

```txt
GET /wp-json/wp/v2/users/me                              200
GET /wp-json/axismundi/jmap/session                      200
GET /wp-json/axismundi/activitystreams/local-objects/types  200
GET /.well-known/jmap                                    302 → 위 Session URL
```

하나의 인증 프로필이 여러 서비스에 접근할 수 있다는 증거이지 호스트당 사용자나 캐시가
하나라는 뜻은 아니다. 같은 도메인의 복수 로그인·다른 인증 주체는 별도 프로필로 격리한다.
서비스 endpoint, 인증 주체, JMAP Account, AP Actor, WP User를 동일시하지 않는다.

```txt
발견 입력        호스트 또는 URL
인증 프로필      지속적인 로컬 격리 경계. Session 스냅샷이나 원격 account ID가 아님
서비스 연결      승인된 endpoint·인증 방식과 접근 정보
Session/Account  서버에서 관측한 정보. 프로필별 캐시와 접근 범위로 취급
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

### 익명 발견과 인증된 Session 읽기는 다른 경로다

과거 측정(2026-11-17, 당시 기본 에이전트; 현재 정책의 금지 근거로 사용하지 않음):

```txt
ureq 기본값   RedirectAuthHeaders::Never
             "Never preserve the authorization header on redirect.  This is the default."
.well-known/jmap 에서 시작 + Basic   → 302 를 따라간 뒤 **403**  (헤더가 떨어진다)
해석된 Session URL 에서 시작          → **200**
```

현재는 인증된 well-known URL에서도 시작할 수 있다. 자동 리다이렉트는 끄고 GET의
각 Location을 직접 확인해 같은 오리진일 때만 인증을 유지한다. 다른 호스트·포트·스킴,
다운그레이드·URL 내 자격증명 등은 거부한다. POST 리다이렉트는 따라가지 않는다.
SameHost 옵션은 동일 오리진 검사 대신 사용할 수 없다. 플랫폼 인증서 검증을 유지한다.

#### 구현 세부

익명 발견에서도 자동 추적 대신 Location을 직접 검사하며 비밀은 보내지 않는다.
GUI는 발견한 주소를 사용자에게 보여 주고 승인된 plan과 실제 API 오리진을 검증한다.
인증 GET 추적과 익명 발견의 세부 차이는 네트워크 구현 기록에 있다.

```txt
입력이 기억할 것 둘
  사용자가 친 것     example.com / http://localhost:8884 / 전체 Session URL
  발견이 해석한 것   실제 Session URL.  **자격증명은 이쪽 오리진에 묶인다**
오리진이 바뀌었으면 그 사실을 말하고 승인을 다시 받는다
```

## 탐침 셋 — 다른 질문, 다른 층

"이 도메인이 WordPress 인가 · AP 를 하는가 · JMAP 을 하는가" 는 **서로 다른 세 질문**이고
표준도 셋이다. 현재는 선택한 서비스만 탐색한다. CLI 옵션이 없으면 기존 전체 탐침을
유지하고 GUI 기본 선택은 JMAP이다. 아래는 현재 발견 방식이며 응답의 존재를 보장하지 않는다.

```txt
① WordPress?    홈페이지의 https://api.w.org/ REST 링크를 따라간다
                → 광고된 REST 인덱스의 name·namespaces
                링크가 없으면 /wp-json/을 추측해 요청하지 않는다
                답하는 것: 어떤 **표면**이 있는가

② JMAP?         GET /.well-known/jmap          (RFC 8620 §2.2)
                → 리다이렉트를 검사해 광고된 Session 주소를 해석
                답하는 것: JMAP 이 있는가 + **Session 이 어디인가**
                그 뒤 Session 의 accountCapabilities 가 **이 계정**이 무엇을 쓸 수 있는지 답한다

③ ActivityPub?  GET /.well-known/nodeinfo → .../nodeinfo/2.1
                → protocols: ["activitypub"], software: axismundi 0.1.0-alpha0.1
                답하는 것: 이 **서버**가 AP 를 하는가
                WebFinger 는 별개로 **특정 Actor** 를 해석한다 (resource 없이 부르면 404 다)
```

**탐침이 셋이라는 것은 구현 비용이지 구조적 장벽이 아니다.** 메일 클라이언트도 계정 추가에서
autodiscover·SRV·well-known 을 차례로 때린다. 호스트는 어떤 조합이든 될 수 있고 — WordPress
인데 AP 가 없을 수도, AP 는 하는데 JMAP 이 없을 수도(Mastodon), JMAP 만 할 수도(Stalwart) —
결과는 **capability 집합**이다.

### 못 만드는 것은 범용 클라이언트가 아니라 **프로토콜 이름을 단 클라이언트**다

```txt
"JMAP 클라이언트"   Mastodon 글을 못 읽는다
"AP 클라이언트"     Todo 를 동기화 못 한다
제품은 둘 다 필요하다 → 이름은 **프로토콜이 아니라 사용자가 하는 일**로 붙인다
```

### 진짜 비대칭 — 답하는 **층**이 다르다

```txt
WordPress 인덱스   표면 단위   namespaces
JMAP Session      **계정 단위** accountCapabilities
NodeInfo          **서버 단위** protocols
```

NodeInfo와 REST 인덱스만으로 사용자의 Actor 권한을 알 수는 없다. 현재 Axismundi의
인증된 AP Session이 행위 가능한 Actor id 목록을 공급한다. 이것은 일반 AP 서버가
동일 endpoint를 제공한다는 뜻은 아니다.

```txt
AP 에 있는 것     NodeInfo (서버 수준, 그나마 W3C 가 아니라 fediverse 관행)
                 WebFinger (Actor 해석)
우리 AP Session   자격증명으로 행위할 수 있는 Actor id 참조. 문서 상세와 분리
```

AP Session의 참조 id를 익명 AS2 GET하고, outbox는 그 Actor 문서에서 얻는다.
name·type·outboxSubmitUrl 사본을 Session 필수 항목으로 복원하지 않는다.

## 라우트 존재는 권한이 아니다

위 탐침표가 "무엇이 있는가" 를 답한다. **"내가 그것을 쓸 수 있는가" 는 다른 질문**이다.
우리 AP Session과 계정별 권한 응답을 확인한다. NodeInfo만으로 권한을 추론하지 않는다.

Actor 의 `outbox` 가 보인다고 해서 (a) 거기에 무슨 자격증명으로 POST 할 수 있는지, (b)
로그인한 사용자가 그 Actor 로 행위할 수 있는지를 알 수 없다. outbox 는 **컬렉션 주소**다
(AP §5.1). NodeInfo 가 `protocols: ["activitypub"]` 를 광고해도 그것은 **서버**가 AP 를
한다는 말이지 **이 사용자**가 제출할 수 있다는 말이 아니다.

```txt
금지   공개 Actor 문서나 NodeInfo 만 보고 AP C2S 제출이나 서명 위임을 **켜지 않는다**
기록   이 구멍이 Session Pre-FEP 의 가장 구체적인 사례다 —
      "발견은 했는데 권한은 알 수 없다" 를 돌아가는 클라이언트가 못 하는 일로 보일 수 있다
```

OGC API 는 나중에 더해질 때 **OpenAPI 문서가 security scheme 을 선언**하므로 그쪽은 자동
발견이 된다. 레인 A 라 이 모델을 바꾸지 않는다.

### 발견 결과와 인증 후 접근 정보를 구분한다

```txt
발견             found / login_required / not_found / unexpected / failed / not_requested
인증 후 빈 계정    정상 접근 범위일 수 있음. 빈 목록만으로 인증 실패/성공을 증명하지 않음
```

셋째가 실재한다. 실측(2026-11-17): Actor 가 없는 WordPress 사용자는 Session 을 받되
`accounts` 가 비어 있고, 투영기가 던지지 않는다.

```txt
accounts: 0        primaryAccounts: {}        오류가 아니다
```

순진한 클라이언트는 셋을 전부 "뭔가 잘못됨" 으로 뭉갠다. 특히 셋째를 인증 실패로 보여주면
사용자는 비밀번호를 다시 친다.

```txt
안내   서버 응답의 사실과 인증 여부의 불확실성을 구분한다
       Actor가 없는 Axismundi 사용자라는 사실을 확인한 경우에만 해당 활성화 안내를 한다
금지   빈 Stalwart 계정에도 Actor 생성을 요구하거나 클라이언트가 임의로 정체성을 발급함
```

그래서 WordPress 인증까지는 Actor 없이 끝나고(`wp/v2` 는 바로 열린다), 나머지는 "기능이
꺼져 있다" 가 아니라 **"한 걸음 더"** 로 안내된다.

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
입력·전달   GUI에서 입력한 비밀번호를 이름 붙은 connect IPC로 Rust에 전달하고 입력란을 비움
세션 보관   현재 Rust managed Connection의 메모리. 응답으로 비밀을 웹뷰에 돌려주지 않음
영구 보관   현재 미구현. OS 키체인·재연결 정책은 후속 설계이며 GUI 착수 조건이 아님
로그        Credentials Debug 비밀 가림 테스트가 있음. 평문 파일·DB에 비밀을 저장하지 않음
인증 방식   현재 Basic. Bearer·cookie+nonce·API key 확장은 미구현이며 전역 모드로 추론하지 않음
```

```txt
invoke 에 여는 것      connect · discover · list_accounts · get_todos 처럼 화면이 필요한 동작
invoke 에 열지 않는 것  URL·헤더를 받는 **범용 요청**.  자격증명을 든 앱이 화면이 시키는 곳으로
                     요청하면 confused deputy 다
익명 레인             별도 명령.  **자격증명을 붙이지 않는다**는 것이 그 층의 불변식이고,
                     인증 연결의 쿠키나 헤더를 재사용하지 않는다
```

URI를 받는 이름 붙은 익명 Actor/Object 읽기와 URL·헤더를 받아 인증하는 범용 프록시는
다르다. API 오리진 승인과 익명/인증 읽기 구분을 IPC에서도 유지한다.

## 다음

```txt
현재   발견·연결 GUI, 공개 Actor/Object GET, Contacts 캐시와 실서버 검증, Mail 읽기 CLI
다음   Mail 계획의 남은 읽기 검증·DB·동기화. 모든 Core 기능이 완성됐다는 뜻은 아님
나중   레인 B 의 선행 작업: "이 Actor 의 키로 이 URI 를 서명 GET" 하는 **우리 쪽 엔드포인트**.
      Mastodon 이 그 요청을 존중한다는 것은 소스로 확인했고, 우리 엔드포인트는 **없다**
보류   OGC API.  레인 A 라 나중에 더해도 이 모델이 바뀌지 않는다.  제품 역할이 미정이다
```

### 미결: 범용 AP 승인과 작업공간 진입

현재 GUI는 문서에서 읽은 outbox도 우리 REST actors/{uuid}/outbox 형태만 승인하고,
첫 페이지는 page=1로 요청한다. 범용 AP endpoint 승인과 Collection first/next 처리는
별도 설계가 필요하다. 기존 자격증명 보호 검사를 임의로 없애지 않는다.
현재 연결은 WP/JMAP/AP 중 하나라도 결과가 있으면 유지한다. AP 중심이라는 과거 설명과
Stalwart 독립 지원을 연결 성공·서비스 준비·작업공간 선택으로 어떻게 구분할지는 미결이다.
이 문서는 AP Session 필수나 모든 outbox 허용 중 어느 쪽도 새로 결정하지 않는다.
