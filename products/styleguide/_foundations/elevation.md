---
title: Elevation
description: elevation은 그림자가 아니고, 다크에서는 그림자를 쓰지도 않는다
order: 70
lang: ko
---

M3에서 elevation은 그림자가 아니라 **표면의 상대적 위계**입니다.

```
이 표면이 다른 표면보다 얼마나 떠 있고,
지금 사용자의 주의에서 얼마나 앞에 있는가
```

M2에 가까운 해석은 "elevation → z축 거리 → 그림자"였습니다. M3는 다릅니다 —
elevation을 **tonal surface 차이**로 먼저 읽고, 그림자는 보조 신호입니다.
Compose가 `shadowElevation`과 `tonalElevation`을 따로 받는 것도 같은 이유입니다.

## 웹으로 옮길 때 생기는 문제

M3 토큰 페이지가 정의하는 것은 `md.sys.elevation.level0…5`와 `0/1/3/6/8/12dp`
뿐이고, 본문은 이렇게 말합니다 — *elevation has no shadow or value of its own by
default.* 즉 level은 **상대적 z축 거리**이지 `box-shadow` 값이 아닙니다.

dp는 CSS에 대응물이 없습니다. `translateY`인지 `z-index`인지 `blur`인지가
모호해서, 테마는 **dp 높이 토큰을 정의하지 않습니다.**

대신 Material Web의 웹 구현이 그 level을 두 겹 그림자로 렌더하고, 테마는 그
공식을 가져왔습니다.

```
level   key shadow (불투명도 .30)   ambient shadow (불투명도 .15)
1       0 1px 2px 0                 0 1px 3px 1px
2       0 1px 2px 0                 0 2px 6px 2px
3       0 1px 3px 0                 0 4px 8px 3px
4       0 2px 3px 0                 0 6px 10px 4px
5       0 4px 4px 0                 0 8px 12px 6px
```

불투명도는 `color-mix`의 `transparent 70%` / `85%`로 표현됩니다.

## 여섯 단계

<ul class="sg-elevations">
  <li><span class="sg-elevation__box" style="--sg-elevation-shadow: var(--md-sys-elevation-shadow-level0)">level0</span><span class="sg-shape__name">none</span></li>
  <li><span class="sg-elevation__box" style="--sg-elevation-shadow: var(--md-sys-elevation-shadow-level1)">level1</span><span class="sg-shape__name">1dp</span></li>
  <li><span class="sg-elevation__box" style="--sg-elevation-shadow: var(--md-sys-elevation-shadow-level2)">level2</span><span class="sg-shape__name">3dp</span></li>
  <li><span class="sg-elevation__box" style="--sg-elevation-shadow: var(--md-sys-elevation-shadow-level3)">level3</span><span class="sg-shape__name">6dp</span></li>
  <li><span class="sg-elevation__box" style="--sg-elevation-shadow: var(--md-sys-elevation-shadow-level4)">level4</span><span class="sg-shape__name">8dp</span></li>
  <li><span class="sg-elevation__box" style="--sg-elevation-shadow: var(--md-sys-elevation-shadow-level5)">level5</span><span class="sg-shape__name">12dp</span></li>
</ul>

**다크 모드로 바꾸면 위 그림자가 전부 사라집니다.** 버그가 아니라 M3입니다 —
어두운 표면에서는 그림자가 읽히지 않으므로, 위계를 tonal surface 차이로 전달합니다.

그 tonal 절반이 색 토큰 쪽에 있습니다.

```
surface-container-lowest / low / (base) / high / highest
```

다크에서 카드가 배경보다 앞에 있어 보이는 것은 그림자가 아니라 저 단계 차이
덕분입니다.

## 두 가지를 혼동하지 말 것

**elevation ≠ z-index.** elevation은 시각적·의미적 위계이고, `z-index`는
브라우저의 실제 겹침과 stacking context 제어입니다. 모달이 화면 위에 뜨려면 둘
다 필요할 수 있지만, 카드의 level 1이 모든 요소보다 높은 `z-index`를 가져야 한다는
뜻은 아닙니다. 스택 스케일이 필요해지면 그건 별도 레인입니다.

**scrim은 elevation level이 아닙니다.** 모달이나 lightbox처럼 뒤의 콘텐츠를
비활성화하고 앞 표면으로 주의를 모으는 overlay입니다.

## shadow와 scrim이 색 파일에 없는 이유

두 역할은 light와 dark에서 **같은 `neutral-0`**입니다. 스킴에 따라 바뀌지 않으므로
색 역할이라기보다 elevation 동작의 일부이고, 그래서 그림자 공식·다크 억제와 함께
`tokens.sys.elevation.css`에 있습니다.

WordPress 쪽 구조와도 맞습니다. `theme.json`이 색은 `settings.color`, 그림자는
`settings.shadow`로 나누고, preset도 `--wp--preset--color--*`와
`--wp--preset--shadow--*`로 갈립니다.

## 이 사이트의 사용처

이 문서의 코드 블록 하나가 `--md-sys-elevation-shadow-level1`을 씁니다. 라이트에서
그림자가 보이고 다크에서 사라지는 것을 스위처로 바로 확인하실 수 있습니다 —
설명이 아니라 지금 읽고 계신 화면에서요.
