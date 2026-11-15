# Brief — 독립 JMAP 서버에서 인증된 `Core/echo`

목표는 한 문장이다. **"Axismundi 가 아닌 서버에서도 Session 발견과 JMAP Core 메서드 호출이
동작한다"** 를 같은 `axj` 바이너리로 보인다.

이것이 증명하는 것과 증명하지 않는 것을 먼저 고정한다. 섞으면 주장이 무너진다.

```txt
증명한다      Session 발견, 인증, Core 메서드 왕복이 **구현에 독립적**이다
증명하지 않는다 Todo 동기화, Contacts, blob.  양쪽이 실제로 공유하는 기능으로 따로 재야 한다
무관하다      localhost 와 DesignBusan.  둘은 같은 서버 구현이라 **배포 환경** 증거다
```

## 제3자 서비스에 가입하지 않는다

증명해야 하는 것은 **구현 독립성**이고 네트워크 거리가 아니다. 독립 구현을 로컬에서 돌리고
거기에 계정을 만들면 같은 증거가 되며, 소유자 데이터가 외부로 나가지 않는다.

```txt
쓸 것    Stalwart.  Rust 로 쓰인 무관한 코드베이스, Docker 로 로컬 실행, 로컬 계정
쓰지 말 것 Fastmail.  실계정·유료이고 소유자 개인 데이터가 제3자로 간다.  첫 증거에 과하다
보류     Cyrus.  자체 호스팅은 되지만 설정이 무겁다
```

**먼저 확인하고 결론을 내린다**: 그 서버가 Session 에서 `urn:ietf:params:jmap:core` 를
광고하는지, `Core/echo` 를 구현하는지. 구현하지 않으면 그것은 **그 서버에 대한 사실**이고
우리 클라이언트의 결함이 아니다. 그 경우 다른 독립 구현을 찾되, "안 되더라" 를 우리 결함으로
적지 말 것.

## 작업

### ① `axj echo` — 계정을 요구하지 않는다

`Core/echo` 는 `accountId` 를 받지 않는다. 지금 코드는 `todo`·`changes` 가 capability 로
계정을 고르는데, **echo 는 그 경로를 타면 안 된다** — 계정이 없거나 Todo 를 광고하지 않는
서버에서 호출 전에 죽는다. 그것은 서버 문제가 아니라 우리 CLI 의 결함이 된다.

```txt
using      ["urn:ietf:params:jmap:core"] 만.  Todo capability 를 넣지 않는다
arguments  임의 JSON.  중첩 객체·배열·유니코드 문자열·정수·null 을 섞는다
callId     고유값.  응답에서 그대로 돌아오는지 본다
```

### ② 판정은 상태코드가 아니다

`200` 만으로 통과시키지 않는다. 세 가지를 **모두** 단정한다.

```txt
메서드명   응답 invocation 의 name 이 "Core/echo" 다.  "error" 가 아니다
호출 ID    보낸 callId 와 같다
내용       arguments 가 보낸 JSON 과 **같다**.  부분 일치나 키 개수가 아니라 값 동일성
```

`Core/echo` 는 RFC 8620 §4 에서 보낸 인자를 그대로 돌려주는 메서드다. 그래서 내용 동일성이
이 테스트의 전부이고, 거기를 느슨하게 재면 아무것도 재지 않은 것이 된다.

### ③ TLS 를 우회하는 플래그를 만들지 않는다

Stalwart 를 로컬에 띄우면 자체 서명 인증서를 쓰게 되기 쉽고, 그때 "검증 생략" 옵션을 넣고 싶어
진다. **넣지 않는다.** 한 번 들어가면 남고, 검증을 끌 수 있는 클라이언트는 그 자체가 책임이다.

```txt
대신     localhost 에서는 평문 HTTP 로 듣게 설정한다
또는     로컬 CA 를 신뢰 저장소에 넣는다 (기기를 바꾸는 일이므로 소유자 판단)
금지     --insecure, danger_accept_invalid_certs, 검증 비활성 빌드 피처
```

### ④ 두 결과를 따로 기록한다

`docs/` 에 측정 기록을 남기되 라벨을 섞지 않는다.

```txt
배포 환경      localhost:8884, DesignBusan.  같은 구현
구현 정합성    Todo.  capability 가 urn:com.example:jmap:todo — RFC 8620 의 **예제 문자열**이라
              독립 서버가 구현할 일이 없다.  상호운용 증거가 될 수 없다
프로토콜 상호운용  독립 구현에서 Session + 인증 + Core/echo
```

## 측정 전에 확인할 것

```txt
우리 서버가 Core/echo 를 구현한다    audit-http-transport 가 "Core/echo dispatch" 를 단정한다
                                   → 같은 호출을 양쪽에 보내 비교할 수 있다
Basic 인증                          우리 클라이언트는 HTTP Basic 을 보낸다.  상대 서버가
                                   Bearer 만 받으면 그것은 **다음 슬라이스**(토큰 지원)이고
                                   이번 결론을 바꾸지 않는다 — 그 사실을 기록만 한다
```

## 바꾸지 말 것

```txt
Session 이 URL 의 유일한 출처        경로를 붙이거나 조립하는 코드를 넣지 않는다.
                                   이 규칙이 깨지면 증명 자체가 성립하지 않는다
오류 네 분류                        Transport / Problem / Status / Shape.  합치지 않는다
자격증명 로깅 금지                   Credentials 의 Debug 가 비밀을 가린다.  테스트가 걸려 있다
core 와 cli 의 경계                 core 는 출력하지 않고 cli 는 HTTP 를 말하지 않는다
```
