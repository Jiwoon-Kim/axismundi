---
title: Token layers
description: ref에서 sys를 거쳐 컴포넌트까지, 그리고 그 방향이 강제되는 이유
order: 10
lang: ko
---

Axismundi의 토큰은 한 방향으로만 흐릅니다.

```
--md-ref-*     리터럴 값이 존재하는 유일한 층
      ↓ var()
--md-sys-*     역할. 리터럴 금지
      ↓ var()
--wp--preset--* / --comp-*    WordPress 브리지
      ↓ var()
컴포넌트
```

거꾸로 흐르거나 층을 건너뛰면 안 됩니다. 이건 스타일 가이드라인이 아니라
**CI가 검사하는 규칙**입니다.

## 검사되는 두 가지

`validate_token_layering.py`가 매 푸시마다 두 축을 봅니다.

**Axis E — 색 층.** 모든 `--md-sys-color-*`는 `var(--md-ref-palette-*)`로
정의되어야 합니다. 리터럴 hex는 실패입니다.

**Axis F — 브리지 층.** 모든 `--wp--preset--color--*`와
`--wp--custom--axismundi--*`는 `var()`여야 하고, 그 `var()`가 가리키는 토큰이
실제로 상류에 존재해야 합니다. 존재하지 않는 토큰을 가리키는 것도 실패입니다.

실패하면 종료 코드가 0이 아닙니다. (한동안은 아니었습니다 — 이전 검증기는 점수와
무관하게 항상 0을 반환해서, 감사가 실패해도 CI는 초록불이었습니다.)

## 왜 강제하나

다크 모드가 이유입니다. 다크는 **ref → sys 매핑만** 바꿉니다.

```
light   --md-sys-color-primary: var(--md-ref-palette-primary-40)
dark    --md-sys-color-primary: var(--md-ref-palette-primary-80)
```

sys에 리터럴이 박혀 있으면 바꿀 매핑이 없습니다. 그때부터 다크는 "값을 하나씩
다시 고르는 일"이 되고, 그건 유지되지 않습니다.

## 층별 위치

이 사이트는 이 파일들을 빌드 때 테마에서 그대로 복사해 씁니다. 여덟 개 전부
바이트 동일합니다.

| 층 | 파일 | 내용 |
|---|---|---|
| ref | `tokens.ref.css` | 팔레트 94톤 |
| ref | `tokens.ref.typeface.css` | 서체 계열·굵기 |
| sys | `tokens.sys.color.light.css` / `.dark.css` | 색 역할 34개 × 2 |
| sys | `tokens.sys.typography.css` | 타입스케일 15역할 |
| sys | `tokens.sys.shape.css` | corner scale |
| sys | `tokens.sys.motion.css` | duration · easing |
| sys | `tokens.sys.elevation.css` | 그림자 공식 · shadow · scrim |
| sys | `tokens.sys.state.css` | state layer opacity · focus ring |

`tokens.ref.typeface.css`와 `tokens.sys.typography.css`만 이 사이트가 직접
씁니다. 나머지는 테마 것입니다.

**서체 ref 층이 이 사이트에만 있는 것은 의도가 아니라 현황입니다.** 배포 테마의
`tokens.ref.css`는 색만 갖고 있고, `axismundi-lab` 쪽 것은 스택에
`'Noto Sans KR'`이 박힌 provider seam 이전 상태입니다. 지금 저장소에서 최신
서체 reference는 여기뿐입니다.

## 두 가지가 sys에 없습니다

**`--wp--preset--font-size--*`.** WordPress가 `theme.json`에서 런타임에
생성하므로 CSS 파일에 존재하지 않습니다. 이 사이트에는 WordPress 런타임이 없어서
타입스케일을 직접 선언합니다 — [Typography]({{ '/foundations/typography/' | relative_url }})
참고.

**dp 단위 elevation level.** M3의 `md.sys.elevation.level0…5`는 표면 사이의
z축 거리(dp)이고, CSS에 직접 대응하는 것이 없습니다. `translateY`인지 `z-index`인지
`blur`인지가 모호해서 정의하지 않았고, 대신 Material Web의 웹 번역인 `box-shadow`
공식을 `--md-sys-elevation-shadow-level*`로 가져왔습니다.

elevation은 그림자가 아닙니다. M3에서는 표면의 상대적 위계이고, 다크에서는
그림자 대신 **tonal surface 차이**로 읽습니다. 그래서 다크에서 물리적 그림자가
`none`이 됩니다.
