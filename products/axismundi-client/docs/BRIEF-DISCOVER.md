# Brief — `axj discover`: 자격증명 없이 접속 주소를 확인한다

> **범위**: 코어에 발견 단계를 만들고 CLI 로 검증한다. GUI·IPC·키체인은 이 슬라이스가 아니다.
> 설계 근거는 [DESIGN-CONNECTION-DISCOVERY.md](DESIGN-CONNECTION-DISCOVERY.md).

## 왜 CLI 가 먼저인가

화면부터 만들면 발견 로직이 GUI 안에 갇혀 다른 서버로 못 던진다. `Core/echo` 때와 같은
이유다 — **측정 도구가 제품보다 앞선다.** 셋에 던져 탐침표가 실제로 맞는지 재는 것까지가
이 슬라이스다.

```txt
Axismundi   셋 다 켜진다
Stalwart    JMAP 만
평범한 WP    wp/v2 만 (아직 그런 호스트가 없으면 그 칸은 비워 둔다)
```

## 지금 없는 것

코어는 **Session URL 을 직접 받는다.** 호스트에서 시작하는 경로가 없다.

```txt
있다   Client::session( session_url )      — 이미 해석된 주소를 받는다
없다   호스트/URL 에서 출발해 무엇이 있는지 알아내는 단계
```

## 탐침 셋

```txt
① GET {base}/wp-json/            200 + JSON 이면 WordPress.  name 과 namespaces 를 읽는다
② GET {base}/.well-known/jmap    리다이렉트의 **Location 을 읽는다** → Session URL
③ GET {base}/.well-known/nodeinfo  links[].href 를 따라가 protocols 를 읽는다
```

**자격증명을 붙이지 않는다.** 발견은 익명이고, 그래야 ③단계에서 "이 주소로 비밀을 보냅니다"
를 보여줄 수 있다.

### 리다이렉트를 자동으로 따라가지 않는다

이것이 이 슬라이스의 핵심 기전이다. 측정(2026-11-17):

```txt
ureq 기본값   RedirectAuthHeaders::Never
.well-known/jmap 에 Basic 을 붙이면 → 302 를 따라간 뒤 403 (헤더가 떨어진다)
해석된 Session URL 에 붙이면        → 200
```

그러니 `.well-known/jmap` **에** 인증할 수 없다. 그리고 ureq 이 리다이렉트를 자동으로
따라가면 중간 `Location` 을 잃는다.

```txt
해야 함   발견용 에이전트는 `max_redirects(0)` 으로 두고 Location 을 직접 읽는다
         (또는 save_redirect_history 를 켠다 — 어느 쪽이든 **최종 오리진을 알 수 있어야** 한다)
금지     RedirectAuthHeaders 를 SameHost 로 바꾸는 것.  기본값이 자산이다
```

## 산출물

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

세 번째 상태(인증됐으나 Account 없음)는 **로그인 뒤**의 일이라 이 슬라이스 밖이다.

## 출력

사람이 읽을 수 있게, 그리고 **무엇을 보고 그렇게 판단했는지** 함께.

```txt
base          http://localhost:8884
wordpress     yes   name="Axismundi Workspace"  namespaces=16
jmap          yes   session=http://localhost:8884/wp-json/axismundi/jmap/session
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

## 다음

```txt
2차  IPC 셋(discover · connect · list_accounts) + OS 키체인 + 실행 경계
    코어가 블로킹이므로 Tauri 에서 UI 를 막지 않게 둔다 —
    async 커맨드에서 blocking 작업으로 넘기는 쪽이 기본값과 무관하게 안전하다
3차  Angular 화면.  1·2차가 있으면 얇다
```
