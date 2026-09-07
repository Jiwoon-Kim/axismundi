---
title: Axismundi Style Guide
description: Material 3를 WordPress 블록 테마에 바인딩하는 방식과, 그렇게 정한 이유
lang: ko
---

Axismundi는 Material Design 3를 WordPress 블록 테마에 바인딩하는 프로젝트입니다.
이 사이트는 그 결과물의 **설계 문서**입니다. 무엇을 만들었는지보다, 왜 그렇게
만들었고 언제 그렇게 써야 하는지를 적습니다.

## 범위

배포되는 제품은 저장소의 `products/wordpress/` 아래에 있습니다.

| | |
|---|---|
| Themes | `axismundi` (부모), `omphalos` (레이아웃 실험용 자식) |
| Plugins | 정체성·연합·달력·미디어·타이포그래피 등 23개 |

테마는 표현을 소유하고, 플러그인은 데이터와 동작을 소유합니다. 이 경계는 취향이
아니라 배포 단위의 문제입니다 — 테마를 바꿔도 데이터가 남아야 하니까요.

## 원칙 — Material을 복제하지 않고 바인딩한다

블록 테마 작업은 **역방향**입니다. Material 컴포넌트를 먼저 그리고 WordPress에
끼워 넣는 게 아니라, WordPress core 블록이 실제로 뱉는 마크업에서 출발해 그것을
Material의 의미에 대응시킵니다.

그래서 이 문서의 컴포넌트 항목은 대체로 세 층을 함께 답니다.

```
Material 사양        무엇을 요구하는가
core 블록 출력       WordPress가 실제로 무엇을 만드는가
대응과 그 한계       어디까지 맞출 수 있고, 어디부터는 다른 것인가
```

세 번째가 가장 중요합니다. 시각적으로 비슷해 보여도 마크업·상호작용·접근성
의미가 다르면 그건 대응이 아니라 **덮어쓰기**입니다. 그런 경우는 CSS로 덮지 않고
경계를 다시 긋습니다.

## 세 곳의 역할이 다릅니다

같은 컴포넌트가 저장소의 세 곳에 나타납니다. 헷갈리기 쉬우니 먼저 구분해 둡니다.

**테마 토큰** — `products/wordpress/themes/axismundi/assets/styles/`
: 실제로 배포되는 값. `--md-ref-*`가 원시 팔레트이고, `--md-sys-*`는 그것을
  `var()`로만 소비합니다. 이 층에 리터럴 색을 쓰는 것은 금지이며, CI가 검사합니다.

**Lab** — `products/reference-implementations/axismundi-lab/`
: 작업대. 컴포넌트가 실제로 어떻게 동작하고 측정됐는지, core 블록의 어떤 기본값이
  새는지를 원시 HTML·CSS·감사 문서로 보관합니다. 구현 전에 여기를 읽으면 시행착오가
  줄어듭니다. 실험이 섞여 있으므로 여기 있다는 것이 곧 확정을 뜻하지는 않습니다.

**이 사이트** — `products/styleguide/`
: Lab의 specimen을 복제하지 않습니다. 토큰과 원칙, 대표 컴포넌트, 테마·블록 적용
  사례를 정리하고 근거는 Lab으로 링크합니다.

## 문서 언어

본문은 한국어이고, 경계는 영어로 고정합니다.

```
URL / 파일명 / 슬러그          English
토큰명 / CSS 변수 / 블록명      English
컴포넌트명 / 표준 용어          English
코드 예시와 API                English
설명 / 결정 이유 / 사용 기준     Korean
```

`Actor`, `outbox`, `core/navigation`, `--md-sys-color-primary` 같은 것은 영어
단어가 아니라 **식별자**라 번역하면 오히려 의미가 흐려집니다. 반대로 "왜 좌표만으로
병합하지 않는가" 같은 설명은 한국어가 훨씬 빨리 읽힙니다.

각 문서에 `lang` 메타데이터를 두었으므로, 외부 독자가 생기면 필요한 페이지부터
영어판을 붙일 수 있습니다.

## 현재 상태

이 사이트는 방금 시작했습니다. 아래 구역은 아직 비어 있고, 순서대로 채웁니다.

- **Foundations** — 토큰 층, 색, 타이포그래피, 모양, 상태
- **Components** — 컴포넌트별 사양과 구현 기준
- **WordPress Bindings** — core 블록과 Material 컴포넌트의 대응 관계

그동안 실제 구현 근거가 필요하면
[axismundi-lab]({{ site.repository_url }}/tree/main/products/reference-implementations/axismundi-lab)을
직접 보시면 됩니다.
