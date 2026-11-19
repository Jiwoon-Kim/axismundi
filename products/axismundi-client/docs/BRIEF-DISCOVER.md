# Brief — `axismundi discover`: 자격증명 없이 접속 주소를 확인한다

> **범위**: 코어에 발견 단계를 만들고 CLI 로 검증한다. GUI·IPC·키체인은 이 슬라이스가 아니다.
> 설계 근거는 [DESIGN-CONNECTION-DISCOVERY.md](DESIGN-CONNECTION-DISCOVERY.md).

2026-11-19 정정. 발견 기능은 이미 구현됐다. 이 문서는 첫 구현의 범위와 현재 계약을
구분해 남긴다. 현재 세부 동작은 [네트워크 구현 기록](IMPLEMENTATION-NETWORK-TRUST.md)을 따른다.

## 왜 CLI 가 먼저인가

화면부터 만들면 발견 로직이 GUI 안에 갇혀 다른 서버로 못 던진다. `Core/echo` 때와 같은
이유다 — **측정 도구가 제품보다 앞선다.** 셋에 던져 탐침표가 실제로 맞는지 재는 것까지가
이 슬라이스다.

```txt
Axismundi   셋 다 켜진다
Stalwart    JMAP 만
평범한 WP    wp/v2 만 (아직 그런 호스트가 없으면 그 칸은 비워 둔다)
```

## 최초 착수 당시 없던 것 — 현재는 구현됨

최초 착수 당시 코어는 Session URL만 받았다. 현재는 호스트/URL 입력에서 익명 발견하는
discover_selected와 CLI·GUI 연결 경로가 있다. 아래 표는 착수 전 기록이다.

```txt
있다   Client::session( session_url )      — 이미 해석된 주소를 받는다
없다   호스트/URL 에서 출발해 무엇이 있는지 알아내는 단계
```

## 탐침 셋

```txt
① GET 홈페이지 → 광고된 REST 링크  WordPress 인덱스의 name·namespaces를 읽는다
② GET {base}/.well-known/jmap    리다이렉트의 **Location 을 읽는다** → Session URL
③ GET {base}/.well-known/nodeinfo  links[].href 를 따라가 protocols 를 읽는다
```

**자격증명을 붙이지 않는다.** 발견은 익명이고, 그래야 ③단계에서 "이 주소로 비밀을 보냅니다"
를 보여줄 수 있다.
선택한 서비스만 탐색하며 REST 광고가 없으면 /wp-json/을 추측하지 않는다.
CLI 무옵션은 전체 탐침을 유지하고 GUI 기본 선택은 JMAP이다.

### 리다이렉트를 자동으로 따라가지 않는다

과거 측정(2026-11-17, 당시 기본 에이전트; 현재 인증 정책과 구분):

```txt
ureq 기본값   RedirectAuthHeaders::Never
.well-known/jmap 에 Basic 을 붙이면 → 302 를 따라간 뒤 403 (헤더가 떨어진다)
해석된 Session URL 에 붙이면        → 200
```

이 측정은 well-known 인증이 불가능하다는 뜻이 아니다. 현재 인증된 GET은 각 Location을
수동 검사해 동일 오리진에서 인증을 유지한다. 익명 발견은 별도 경로이며 비밀을 보내지 않는다.

```txt
해야 함   자동 추적은 끄고 Location과 최종 오리진을 직접 검사한다
인증 GET  동일 오리진에서만 인증을 유지한다. POST 리다이렉트는 따라가지 않는다
금지     SameHost를 동일 오리진 검사 대신 쓰거나 다른 오리진으로 자격증명을 전달함
```

## 최초 산출물 제안 — 현재 타입의 복제본 아님

아래 Option/notes 형태는 최초 제안이다. 현재는 discovery.rs의 Probe 상태와 선택 옵션을
기준으로 한다. 이 표를 근거로 실제 타입을 예전 형태로 되돌리지 않는다.

```txt
Discovery {
  input          사용자가 친 것 (그대로 보존)
  base           정규화한 오리진
  wordpress      Option<{ name, namespaces: Vec<String> }>
  jmap           Option<{ session_url }>        ← Location 에서 온 값
  activitypub    Option<{ protocols: Vec<String>, software: Option<String> }>
  notes          Vec<String>                    ← 아래 참조
}
```

**`notes` 가 필요한 이유**: "없다" 와 "확인하지 못했다" 는 다르다. 404 와 연결 실패와
타임아웃은 각각 다른 사실이고, 사용자에게 "JMAP 미지원" 으로 뭉뚱그리면 거짓말이 된다.

## 상태는 셋이고 둘로 뭉개지 않는다

```txt
미지원          그 탐침이 404 를 주었다
로그인 필요      JMAP 이 있고 Session 이 인증을 요구한다 (**미지원이 아니다**)
확인 못 함       연결 실패·타임아웃·예상 못 한 상태코드
```

빈 Account 목록은 로그인 뒤의 관측이며 정상일 수 있다. 목록만으로 인증 실패/성공을
단정하지 않는다. 현재 Probe는 found·login_required·not_found·unexpected·failed·
not_requested를 구분한다. 아래 출력은 형식 예시이지 설치마다 성립하는 기대값은 아니다.

## 출력

사람이 읽을 수 있게, 그리고 **무엇을 보고 그렇게 판단했는지** 함께.

```txt
base          http://localhost:8884
wordpress     yes   name="Axismundi Workspace"  namespaces=16
jmap          yes   session=http://localhost:8884/wp-json/axismundi/v1/users/me/session
activitypub   yes   protocols=[activitypub]  software=axismundi 0.1.0-alpha0.1
```

## 수용 기준

```txt
세 서버       Axismundi · Stalwart · (가능하면) 평범한 WordPress 에 각각 던진다
             **기대값을 미리 적고** 그것과 대조한다
Stalwart      jmap=yes, wordpress=no, activitypub=no 여야 한다.
             하나라도 yes 로 나오면 탐침이 헐겁다는 뜻이다
오리진 변화    발견이 다른 오리진으로 해석되면 **그 사실을 출력한다**
단위 테스트    Location 해석: 상대·절대 Location 둘 다.
             404 / 연결 실패 / 예상 못 한 상태코드가 **서로 다른 결과**가 된다
변이         max_redirects 를 기본값으로 되돌리면 Session URL 을 못 읽어 테스트가 실패해야 한다
```

마지막 줄이 완료 판정이다. 통과만 보면 Location 을 정말 읽는지 알 수 없다.

## 바꾸지 말 것

```txt
자격증명      발견은 익명이다.  Credentials 를 이 경로에 넣지 않는다
Session 규칙  apiUrl 은 Session 이 광고한 것을 쓴다.  발견이 만든 추측으로 대체하지 않는다
코어 가드     apiUrl 의 자격증명 거부 · HTTPS→HTTP 다운그레이드 거부.  발견에도 같은 선이 걸린다
core ∥ cli    코어는 출력하지 않고 cli 는 HTTP 를 말하지 않는다
```

## 현재 후속 범위

```txt
구현됨  이름 붙은 IPC·spawn_blocking 실행 경계·Angular 연결/문서 읽기 화면
보관    현재 Rust managed Connection 메모리. 입력 비밀번호는 GUI에서 전달 후 입력란 비움
후속    키체인·재시작 후 자동 연결은 별도 설계. GUI나 Mail 읽기의 선행 조건이 아님
다음    Mail 계획의 남은 검증·영속화·동기화
```
