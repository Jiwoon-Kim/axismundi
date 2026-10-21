---
title: Progress indicators
kind: material
order: 23
---

React 프론트앱의 ProgressIndicator. WordPress core 블록 어댑터가 아니다.
검증 표면은 `/stylebook/components/progress-indicators`이다.

## 계약

`value`를 생략하면 불확정, 유한한 값을 전달하면 확정 진행률이다. `max` 기본값은 1이며,
값은 0~max로 clamp한다. 잘못된 값은 불확정으로 처리하고 개발 경고를 낸다.
Linear/Circular 모두 이름 있는 progressbar이며 불확정에는 aria-valuenow가 없다.

Flat/Wavy 네 종류 각각 기본/두꺼운 두께를 제공한다. `wavy`와 `thick`은 boolean prop이다.
Reduced-motion에서는 정지 물결이 아니라 flat 경로를 표시한다. 컨테이너 크기는 유지한다.
모르는 진행량을 정적인 determinate 값으로 위장하지 않는다.

## 표시

기본 두께 4px, Circular 40px. Active는 primary, track은 secondary-container다.
두꺼운 두께는8px이며 flat circular는44px, wavy circular는48/52px이다.
Wavy linear의 높이는10/14px이며 wavelength는 확정형40px, 불확정20px이다.
Figma 표본에 맞춰10/100%에서도 물결을 유지한다. Compose 기본 진폭 함수와 다른
명시적인 표현 정책이며, 모르는 진행량을 확정형으로 바꾸지는 않는다.
Linear determinate는 기본적으로 4px stop indicator를 표시하며 `showStop={false}`로 생략한다.
완료 시 track과 stop을 제거한다. `showTrack={false}`로 버튼 등에서 track을 숨길 수 있다.
Linear만 RTL 반전한다. 주변 간격과 작업의 aria-busy, 완료 알림은 소비자가 소유한다.

불확정 모션은 고정한 AndroidX Material3 commit의 시간축을 CSS로 옮겼다. Linear는 두 구간의
head/tail 네 시간축(1750ms)을 사용한다. Circular는 MDC Android advance의 head/tail과
기본 회전(5400ms)을 사용한다. 이전 retreat 시간축은 제품에서 제거했다.
Easing은 같은 수치를 가진 기존 테마 토큰을 사용한다. 화면 밖/문서 숨김 시 pause하고
unmount 시 observer를 정리한다. geometry와 rasterization까지 pixel-identical하다는 뜻은 아니다.
Reduced-motion에서는 모션 없이 고정된 불확정 segment를 유지하며 실제 진행값은 바꾸지 않는다.

토큰 표는 `_data/progress.yml`, 검증 명령은
`python tools/validators/validate_styleguide_progress.py --self-test`다.
Wave 실험의 한계와 후속 판단은 제품의 `docs/REFERENCE-M3-PROGRESS-IMPLEMENTATION.md`에 기록한다.
