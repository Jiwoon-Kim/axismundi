# Progress: Flat / Wavy 구현

2026-10-22. 현재 구현은 Linear/Circular × determinate/indeterminate × Flat/Wavy × 기본/두꺼운 두께다.
새 runtime dependency, WAAPI, requestAnimationFrame loop는 없다. SVG는 DOM과 CSS로 표시하며,
React는 진행값, 크기, 문서 가시성, 교차 상태가 변할 때만 갱신한다.

## 공개 계약

`ProgressIndicator`: `value` 생략=불확정, 유한한 value=확정, `max=1`, `label`,
`circular=false`, `wavy=false`, `thick=false`, `showTrack=true`, `showStop=true`, `animated=true`, className/style.
Amplitude, wavelength, geometry callback은 공개 API에 넣지 않았다.
잘못된 max는 1, 잘못된 value는 불확정으로 거부하고 개발 경고한다. 유한한 값은 clamp한다.
이름과 min/max를 제공하며 불확정에서는 aria-valuenow를 생략한다.
진행 대상의 aria-busy, 완료 안내, 노출/숨김 지연은 소비자 책임이다.

## Flat 표시

## Wavy 및 Figma 패턴 (2026-10-22)

Stylebook에0/10/20/50/80/100% × Flat/Flat thick/Wavy/Wavy thick 비교표와
위상을 달리한 불확정 표본 두 줄을 Linear/Circular 각각 제공한다.
두께4/8px, Circular flat40/44px, wave48/52px, Linear wave높이10/14px이다.
Circular는 AndroidX의9꼭짓점 RoundedPolygon circle/star recipe를 기존 graphics-shapes
1.1.0으로 실행해27개의 대응 cubic을 추출한다. pinned Compose의 의존 버전
1.2.0-alpha01과 좌표가 완전히 동일하다고 주장하지 않는다. 출처/라이선스는 컴포넌트 옆에 있다.
Linear는40px(확정)/20px(불확정) 주기의 교대 quadratic 경로를 만든다.
SVG pathLength/dash로 경로 길이 기준 구간을 추출하고 위상을 역이동/역회전하여
시작점이 물결 이동 때문에 함께 이동하지 않게 한다. Circular 위상은9초, Linear는1초다.
여기서는 Figma 패턴처럼10/100%에서도 진폭을 유지한다(NON-STANDARD: Compose 기본
진폭 함수와 다른 표현 정책). 진폭이 변하지 않으므로 현재 범위에는 WAAPI morph가 필요 없다.
CSS와 SVG만 사용하며 reduced-motion에서는 같은 크기의 정적 flat 경로로 대체한다.
이는 네이티브 PathMeasure/rasterization과 pixel-identical하다는 보증은 아니다.
이 문서 아래의 wave probe 및6000ms retreat 기록은 교체 전 실험 이력이다.
검증: Loading 포함 Node19건 통과, Progress validator33건(오류 변이4건 포함) 통과,
ExportProgressKt --check 일치, 제품 빌드 성공(기존 성능 경고2건).
Chrome에서 wave circular48/52px 및 stroke4/8px을 실측했다.390px 모바일 뷰포트에서
스크롤바 제외 clientWidth/scrollWidth는375px로 같고 표 내부만440/880px로 스크롤된다.
Preview에서32개 wave path 모두 computed d가 flat 대체와 일치하고 위상 animation은none이다.
OS reduced-motion 변경 검증은 수행하지 않았다. 실제 움직임의 최종 시각 판정은 사용자 리뷰로 남긴다.

스펙의 4px 두께, 4px gap, Linear stop 4px, Circular 40px을 기준으로 한다.
Linear 폭은 ResizeObserver로 측정하여 SVG 좌표를 CSS pixel과 맞춘다. 따라서 가변 폭에서도
gap과 stop 크기를 비율로 늘리지 않는다. Circular radius는 18이다. 둥근 cap 길이를 arc에서
보정하고, 0과 1에서는 각각 full track/full active를 별도로 처리한다. 작은 구간의 gap은 줄인다.
Stop은 기본 표시하되 생략 가능하며, 완료 시 제거한다. Linear만 RTL 반전한다.

초안의 임의적인 단일 segment 이동과 2초 circular sweep는 사용자 리뷰 후 폐기했다.
현재 모션 시간축은 AndroidX commit `2e1a16243ae2c9284445a746f1d487c73af92623`의
`ProgressIndicator.kt`에서 가져온다. Material Web과 Flutter의 두 bar/head-tail 모델도
대조했지만 서로 다른 세대의 곡선과 시간을 섞지 않고 Compose profile 하나를 선택했다.

- Linear: 1750ms cycle, head1 delay0/duration1000, tail1 250/1000,
  head2 650/850, tail2 900/850. 각각 독립적인 CSS number property로 움직인다.
- Circular: 6000ms cycle, progress .1~.87~.1, 기본 회전 0~1080도,
  추가 회전 0~360도. 추가 회전은 300ms 이동/1200ms hold를 네 번 수행한다.
- MotionTokens의 accelerate (.3,0,.8,.15), decelerate (.05,.7,.1,1),
  standard (.2,0,0,1)은 현재 테마의 같은 easing 토큰을 소비한다.
- active와 mask가 root의 동일한 property를 상속하므로 cap/gap이 같은 clock을 따른다.
  React/JS가 매 frame geometry를 갱신하지 않는다. 등록된 CSS property 보간을 사용한다.

타이밍 수치와 곡선을 이식했지만 SVG masking의 cap/gap과 Android Canvas의 rasterization까지
동일하다고 주장하지 않는다. Determinate 값 변경은 즉시 반영한다. 출처와 Apache 라이선스는
component의 LICENSE.md 및 LICENSE-APACHE-2.0.txt에 보존하고 readme에서 안내한다.
모션 축소 시 불확정 의미를 유지한 정적 segment로 표시하고, value를 만들어내지 않는다.
화면 밖/숨김 상태에서는 CSS animation을 pause하고 unmount 때 observer/listener를 정리한다.

참고한 upstream (2026-10-21 열람):
- https://m3.material.io/components/progress-indicators/specs
- https://m3.material.io/components/progress-indicators/accessibility
- https://github.com/material-components/material-web/blob/main/progress/internal/_linear-progress.scss
- https://github.com/material-components/material-web/blob/main/progress/internal/_circular-progress.scss
- https://github.com/flutter/flutter/blob/master/packages/flutter/lib/src/material/progress_indicator.dart
- https://github.com/material-components/material-components-android/blob/master/lib/java/com/google/android/material/progressindicator/LinearDrawingDelegate.java
- https://github.com/material-components/material-components-android/blob/master/lib/java/com/google/android/material/progressindicator/CircularDrawingDelegate.java

## Wave 검증과 경계

후속 원본 drawing cache 대조는 `REFERENCE-COMPOSE-PROGRESS-DRAWING.md`를 기준으로 한다.
공식 CircularShapes가 RoundedPolygon/Morph를 사용하고 progress가 경로 길이 비율이라는 점을
확인했다. 아래 sine 프로브의 호 길이/각도 차이를 탈락 근거로 읽지 않는다.

Stylebook의 `wave-probe`만 Linear sine path와 Circular radial sine path를 만든다.
Geometry는 module 초기화 때 한 번 계산하며 CSS transform으로 움직인다. 이것은 공식 Android
wave 알고리즘의 이식이 아니라 구현 비용을 확인하는 진단용 대조군이다.

- Linear: 움직이는 path를 고정 폭으로 자르면 끝점이 사각으로 잘리고 파형 위상이 바뀔 때
  끝점의 y가 바뀐다. rounded cap과 실제 진행 위치를 동시에 맞추는 추가 작업이 필요하다.
- Circular: pathLength=100 dash는 파형의 호 길이 비율이다. 각도 비율과 같지 않다.
  720 sample/8 wave/radius20/amplitude1.6 프로브에서 최대 차이는 약 0.330 percentage point다.
  수치 자체가 사용 불가능하다는 뜻은 아니다. 단순 전체 회전은 determinate의 시작·끝 각도까지
  돌려버리므로, 파형 위상과 진행 구간을 분리해야 한다는 것이 더 중요한 실패다.
- Reduced-motion preview와 OS media query 모두 wave DOM 대신 실제 flat 컴포넌트를 표시한다.
  멈춘 물결로 대체하지 않는다. Preview는 Stylebook 전용이며 공개 컴포넌트 prop이 아니다.

현재 flat은 제품 지원이며 wave는 공개 variant가 아니다. 이 저비용 sine probe의 결함만으로
공식 wave 이식이 비싸거나 불가능하다고 판단했던 결론은 근거가 부족하다. 정확한 파형 끝점·
트랙 gap·경로 길이 기반 진행량을 공식 구현과 대조한 뒤 성능을 재야 한다. 프로브가 CSS로 움직인다는 사실만으로 성능이 좋다거나,
반대로 wave 전체에 per-frame JS가 반드시 필요하다고 단정하지 않는다. 다른 기하 전략도 가능하다.
현재 프로브를 M3 wave 완료본으로 사용하지 않는다. Flat API의 변경은 이 실험 때문에 필요하지 않다.

Android의 두 DrawingDelegate도 파형의 cached path와 표시 구간을 분리한다. 실제 표시 때
phase에 따라 path 구간을 옮기고 endpoint를 계산한 뒤 translation/rotation을 적용한다.
따라서 단순 SVG 전체 이동/회전만으로 동등하게 만들 수 있다고 가정하지 않았다. 이 계산을
웹에 옮기거나 SVG mask 기반의 다른 전략을 검증하는 작업이 wave 승격의 남은 범위다.
추가 대조 원본은 같은 AndroidX commit의 `WavyProgressIndicator.kt`다. 공식 reference HTML은
도구의 본문 크기 제한을 넘어서 raw source를 읽었다. Wave API의 amplitude/wavelength/waveSpeed와
flat의 진행 시간축 재사용을 확인했으며, 위 sine 프로브를 그 소스의 구현이라고 부르지 않는다.

## 검증 명령

```text
node --test products/wordpress/plugins/axismundi/src/apps/frontend/components/progress-indicators/__tests__/*.test.cjs
python tools/validators/validate_styleguide_progress.py --self-test
python products/styleguide/bin/build.py --verify
docker exec wp-env-axismundi-66f7e116-cli-1 wp eval-file wp-content/plugins/axismundi/tests/audit-public-routes.php
```

브라우저 검증은 `/stylebook/components/progress-indicators`에서 수행한다. OS 설정을 실제 변경한
시험과 Stylebook reduced-motion preview를 구분하여 보고한다. 모션 timing은 geometry 정확성
검사와 별도이며, 이 검증들이 다른 플랫폼의 모션과 pixel-identical함을 보증하지 않는다.

초기 검증: Progress 테스트 6건, 기존 Loading 포함 13건 통과. 토큰/정책 validator는
오류 변이 3종 포함 25건 통과, public route 감사는 40건 통과했다. 실제 Chrome에서
Linear 높이 4px, Circular 40x40px, Linear RTL scaleX(-1), range 입력 후 aria-valuenow=100을
확인했다. 390px viewport의 scrollWidth도 390px이다. Preview에서 두 wave SVG가 display:none,
flat이 display:block이며, active 모션의 computed animation-name이 none임을 확인했다.
OS reduced-motion 설정을 변경하지 않았다. 빌드는 성공했으며 기존 webpack 성능 경고 2건은 남아 있다.

수정 후에는 `motion.test.cjs`가 pinned Compose의 시간(밀리초)·진행량·회전 milestone을
실제 CSS keyframes와 대조하고 시간/최대 sweep/총 회전 오류 변이를 거부한다. Stylebook의
Inspect frame은 새 timeline을 paused 상태로 시작하여 지연값으로 정확한 시점을 선택한다.
실시간 재생과 별도로 두 head/tail의 실제 길이 변화와 active/mask 동기화를 관찰할 수 있다.

수정 후 Chrome의 Inspect frame 실측:

| 시간 | h1 | t1 | h2 | t2 |
|---|---|---|---|---|
| 500ms | .153998 | .0353427 | 0 | 0 |
| 1100ms | 1 | .573561 | .175120 | .0312091 |

폭 758px에서 1100ms의 두 active는 각각 323.241px, 109.084px이다. 각 cutout은 active보다
8px 넓고 x는 4px 앞서므로 같은 clock에서 양쪽 4px 간격이 유지된다. Circular 3000ms는
sweep=.87, 기본회전540도, 추가회전180도이며 active/mask dash 값이 일치한다.
## Material Web 비교 표면 (2026-10-22)

### Circular advance 채택

사용자가 linear의 두 구간 동시 노출과 circular의 끊겨 보이는 전환을 구분해 보고했다.
이는 프레임 드롭으로 확정된 진단이 아니다. 기존 circular retreat 대신 MDC Android
`CircularIndeterminateAdvanceAnimatorDelegate.java`의 5400ms advance를 채택한다.
원본 commit은 `66b0eeb53afeb6025ffbbd37734b08e5bce197ed`다.
기본 회전1520도, 네 번의 head/tail 전진 각250도, tail 초기 오프셋-20도를 사용한다.
각 head는667ms 동안 전진하고 tail이 다음667ms 동안 따라오며 FastOutSlowIn을 사용한다.
한 주기의 총 전진은2520도(7회전)라 반복 경계의 위치가 이어진다.
이전6000ms retreat keyframe은 삭제했으며 legacy prop을 추가하지 않는다.
Chrome 500ms 고정 프레임에서 head=239.807, tail=0, sweep=.721686을 확인했다.
active와 mask의 dash가 모두81.6208px로 일치한다. Reduced-motion preview에서는
animation=none, head=70, tail=0, sweep=.25로 보이는 정적 상태를 유지했다.
이는 보간 적용 확인이며, 사용자의 부드러움에 대한 시각적 승인을 대신하지 않는다.
아래6000ms 실측은 교체 전 구현의 이력이다. Linear 두 구간은 여전히 pinned Compose
disjoint 패턴이며, 사용자 요청을 임의의 단일 막대로 해석해 삭제하지 않는다.

Stylebook 상단에 240px linear / 40px circular 제품 표본과 선택적으로 로드하는 공식
Material Web stories iframe을 추가했다. 외부 요청은 체크박스로 켠 뒤에만 시작하며,
제품에 Material Web 런타임 의존성을 번들하지 않는다. 외부 데모의 값, 크기, 시계는 별도로
제어된다. 동기화된 프레임 비교나 동일 크기 검증이 완료되었다는 뜻은 아니다.

Material Web `progress/internal/_linear-progress.scss`의 불확정 모션은 2000ms이며
두 bar의 translate/scale 조합이다. 현재 pinned Compose 기반의 1750ms head/tail과 다르다.
따라서 Web 데모와의 시각 차이만으로 현재 구현의 결함을 판정하지 않는다.
비교 구현은 제품 스펙이나 legacy/year별 공개 prop으로 승격하지 않는다.

초기 브라우저 측정에서 keyframe 내부 easing만으로는 선형 보간이 관측되어 root animation에도
각 theme easing을 명시했다. 소스 존재만 확인하지 않고 위 실제 보간값으로 재검증했다.
