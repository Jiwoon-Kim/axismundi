# 결정 기록: Appearance Surfaces — 표시 설정은 누구의 것인가

## 상태

**방향 결정. 2026-10-03.** Admin의 `design` 라우트를 **제거하라는 결정이 아니다.** 지금
있는 `/design/styles`는 그대로 둔다. 이 문서가 막는 것은 앞으로의 IA를 `design`을 Admin의
고정 도메인으로 보고 확장하는 일이다.

## 1. `design`은 Admin의 고정 도메인이 아니다

Admin의 도메인을 `operations / moderation / data-views / design`으로 적은 안이 있었고, 그중
`design`이 틀렸다. 근거는 Misskey의 `/settings/theme`이다 — **그 화면은 운영자가 사이트를
꾸미는 화면이 아니라 사용자가 자기 표시 환경을 고르는 화면이다.** 테마 선택, 라이트/다크,
시스템 동기화가 모두 개인 경험에 속한다.

```text
Social
  settings/appearance/     개인 scheme · 표시 밀도 · PWA 설정        ← 사용자의 것
  feed / collection / search

Admin
  operations/              변경 · 승인 · 일괄 작업
  moderation/              신고 · 검토 · 제재
  data-views/              테이블 · 필터 · 저장된 뷰
  site-appearance/         사이트 기본 theme · 허용 목록 · 브랜드 값   ← 나중에, 실제로
                           저장하는 기능이 생길 때만
```

목표는 **사용자가 wp-admin이나 Admin 앱으로 넘어가지 않고 자기 디바이스에서 프론트앱과
PWA 설정을 바꾸는 것**이다. 그 설정이 Admin에 있으면 그 목표가 성립하지 않는다.

Admin에 사이트 전역 기본값을 **저장하는** 기능이 생기면 그때 `site-appearance` 같은 운영
도메인으로 재정의한다. 이름이 `design`이 아닌 이유는 그 화면이 디자인 작업이 아니라
**사이트 정책**이기 때문이다.

## 2. per-device는 새 요구가 아니다 — 이미 계약이다

이게 이 문서를 쓰면서 확인한 것이다. "자기 디바이스에서"는 추가 요구가 아니라 테마
스위처가 **이미 그렇게 구현된 이유**이고, 편의가 아니라 **캐시 정합성** 때문이다.

```text
products/styleguide/_components/theme-switcher.md §상태 계약
  html[data-theme]   "auto" | "light" | "dark"   항상 존재, 지우지 않음
  저장               localStorage "axismundi_theme"
  부트스트랩          <head> 최상단 블로킹 스크립트, 토큰 CSS보다 먼저
  방송               window "axismundi-theme-scheme-change", detail.mode
```

그 문서가 이유까지 적어 두었다 — **전면 페이지 캐시가 한 방문자의 색 모드를 공유 HTML에
구워버리면 안 된다.** localStorage는 우회가 아니라 그 문제의 해법이다.

따라서 **Social의 appearance 화면은 새 권한이 아니라 같은 controller의 세 번째 소비자**다.
그 문서는 이미 두 컨트롤이 하나의 controller를 공유하고 서로의 존재를 모른 채 window
이벤트로 동기화되는 것을 시연한다. Social 화면도 같은 자리에 선다.

### 교차 표면 계약

이 셋은 이제 **블록 테마·Social·미래 PWA 설정이 공유하는 계약**이다. 이름을 바꾸면 세
표면이 함께 깨진다.

```text
localStorage key   axismundi_theme
속성               html[data-theme]
이벤트             window "axismundi-theme-scheme-change" { detail.mode }
```

**두 스위처, 하나의 선호.** 블록 테마에 이미 스위처가 있으므로, Social이 자기 저장소를
따로 두면 같은 디바이스에서 같은 질문에 두 가지 답이 나온다.

### 계정 동기화를 하게 되면

Misskey는 기기 간 동기화를 **명시적 opt-in**으로 둔다. 우리가 그걸 하게 되면 제약이 하나
따라온다 — **서버가 첫 페인트를 그리게 해선 안 된다.** 그건 §2가 피한 바로 그 캐시 문제다.
계정 값은 **동기화 소스**로서 페인트 전에 localStorage에 써 넣는 것이고, 서버 렌더가 아니다.

## 3. 무엇이 per-device인가

PWA 설정이 이 축을 강화한다. push subscription은 브라우저마다 다른 객체이고, 설치 상태와
알림 권한도 그렇다. **계정의 속성이 아니라 기기의 속성이다.**

| | 성격 | 사는 곳 |
|---|---|---|
| scheme (auto/light/dark) | 기기 | Social settings/appearance |
| 표시 밀도 · reduced motion 존중 | 기기 | 같음 |
| PWA 설치 · 알림 권한 · push subscription | 기기 | 같음 |
| 사이트 기본 theme · 허용 목록 · 브랜드 | 사이트 정책 | Admin site-appearance (나중) |
| 차단 · 뮤트 | 계정 | Social settings (appearance 아님) |

마지막 줄을 함께 적는 이유는 "개인 설정"이 전부 appearance가 아니기 때문이다. appearance는
**이 기기가 어떻게 보이는가**이고, 차단은 계정이 가지고 다니는 것이다.

## 4. Stylebook은 둘 다 아니다

`/social/stylebook/styles/*`는 사용자 appearance도 Admin design도 아니다. **블록 테마가
제공한 런타임 토큰이 Social에서 실제로 어떻게 해소되는지 보는 개발·VQA 표면**이다. 그래서
color · elevation · icons · motion · shape · spacing · typography가 그쪽에 있는 것이 맞다.

같은 이유로 Stylebook에 **scheme 전환 UI를 흉내 내지 않는다.** Motion 페이지에서 한 번
걸렸던 것과 같은 함정이다 — 테마가 product-level `expressive`/`standard` 선택기를 아직
제공하지 않으므로, 페이지가 전환기를 그리면 없는 기능을 있는 것처럼 말하게 된다.

## 5. 미결 — 프로젝트 소유자의 판정

- **`settings/appearance`의 라우트 등록 시점.** Social은 아직 셸·컴포넌트 층이고 설정 화면이
  없다. 이 문서는 자리를 비워 두는 것이고 지금 만들라는 것이 아니다.
- **계정 동기화를 할 것인가.** 하면 §2의 제약(페인트 전 localStorage 주입)이 따라온다.
- **Admin `/design/styles`의 최종 거처.** 지금 라우트는 유지. 사이트 기본값을 저장하는 기능이
  생길 때 `site-appearance`로 재정의하는지, 아니면 그 화면 자체가 다른 것이 되는지.
- **표시 밀도를 발행할 것인가.** M3에 density 축이 있지만 우리 토큰 층에는 아직 없다.

## 관련 기록

- `products/styleguide/_components/theme-switcher.md` — scheme 상태 계약의 원본
- `DECISION-ADMIN-ROUTE-AREAS.md` — Admin 라우트 영역 계약
- `DECISION-FRONTEND-THEME-ASSET-CONTRACT.md` — 테마가 주고 Social이 소비하는 것
- `DECISION-OBJECT-READ-SURFACES.md` — 같은 "표면마다 목적이 다르다" 축의 객체 쪽 결정
- `DECISION-FRONTEND-COMPONENT-CONVENTIONS.md` — `aria-pressed`를 순환 컨트롤에 쓰지 않는 이유
