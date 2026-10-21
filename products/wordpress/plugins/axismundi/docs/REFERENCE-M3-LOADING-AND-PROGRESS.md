# M3 Loading, Progress, Skeleton: 착수 전 대조

2026-10-21. 사용자가 지정한 M3 페이지 12개를 Chrome에서 렌더링하여 본문을 읽었다.
현재 upstream과 로컬 자료의 차이를 기록한 참고다. 아래 조사 기록의 "미구현"은 당시 상태이며,
후속 구현 현황은 바로 다음 절을 기준으로 읽는다. 과거 lab 자료는 수정하지 않았다.

## 후속 구현: Loading geometry와 Web 표시

2026-10-21. `tools/generators/material-shapes/`에 재생성 가능한 Kotlin/Maven 추출기를 만들었다.
MDC commit `66b0eeb53afeb6025ffbbd37734b08e5bce197ed`의 7개 factory를 JVM point transform으로
adapt하고, 실제 AndroidX graphics-shapes-desktop 1.1.0으로 rounding과 Morph 매칭을 실행한다.
브라우저에 Kotlin/Android runtime은 넣지 않는다. generator에 사용한 factory adapter는 원본
copyright와 수정 내역을 보존한다. 라이선스는 기존 자산 관리 선례에 따라 generator와 plugin
자산 옆에 보존하며 readme의 Third-party assets 절에서 안내한다.

생성물: `components/loading-indicators/assets/`의 JSON과 7개 static SVG. 7개 인접 전환은
마지막 Oval에서 첫 SoftBurst로 돌아오는 쌍을 포함한다. geometry는 테마 토큰이 아니다.
generator `--check`는 JSON/SVG를 메모리에서 재생성해 비교하며, Kotlin에서 내부 0.25/0.5/0.75
프레임을 실제 Morph 결과와 대조한다. 별도 Python 검증기는 contour, closure, bounds, 원본과
matched endpoint의 외형 일치 및 오류 변이를 검사한다.

Web `LoadingIndicator`는 이름 있는 불확정 progressbar다. reduced-motion에서는 morph와 회전을
멈추고 첫 SoftBurst를 표시한다. 이름과 indeterminate 의미는 유지하며 가짜 진행률을 만들지
않는다. 명시적 정적 표시, 장식적 표시, contained, 24~240px을 지원한다. 화면 밖/숨김/unmount
시 재생을 취소하고 listener/observer를 정리한다. 미지원 path animation에서는 정적 표시한다.

이 Web profile은 테마의 slow-spatial 곡선+duration을 사용하고, MDC의 쌍당 추가 회전 90도와
constant 회전 50도를 각각 morph와 linear 회전에 연결한다. 원래 Android stiffness/damping을
정확히 적분한 동작이라고 주장하지 않는다. 각 쌍의 SVG `d`는 command topology가 맞으므로
WAAPI로 보간한다. React는 매 frame마다 다시 렌더하지 않는다. shape 데이터는 해당 Stylebook
페이지를 열 때만 별도 chunk로 내려온다.

검증 표면: `/stylebook/components/loading-indicators`. 기존 motion 토큰과 renderer를 소비한다.
Progress의 후속 구현은 `REFERENCE-M3-PROGRESS-IMPLEMENTATION.md`를 기준으로 한다.
Skeleton은 아직 구현하지 않았다. 컴포넌트 상태 테스트는 OS reduced-motion의 최초
상태와 런타임 변경을 mock media query로 검사한다. 실제 Chrome에서는 morph/정적 대체와
48px/38px 치수 및 모바일 가로 overflow를 확인한다. OS 설정을 실제 변경한 시험은 아니다.

명령은 generator README와 `tools/validators/validate_loading_geometry.py`를 기준으로 한다.

## 로컬 자료

- `m3-token-tables/SOURCE-M3-LOADING-INDICATOR-RAW.md`: 있다. 보존된 표는 dsdb 38.2.9,
  table revision 2026-05-27, 관측 데이터 URL은 2026-09-23이다. 포함 26개 중 컴포넌트 토큰은
  7개다. 48dp 외곽, 38dp active shape, contained/default 색 구분이 있다.
- `m3-token-tables/SOURCE-M3-PROGRESS-INDICATORS-RAW.md`: 있다. 같은 데이터 버전이다.
  포함 54개 중 컴포넌트 토큰은 23개다. flat/wavy, 4dp 두께, circular 40dp/48dp,
  gap, stop indicator, amplitude/wavelength가 있다. 예전 분리 토큰 33개는 deprecated다.
- `m3-token-tables/SOURCE-M3-TOOLBARS-RAW.md`: 있다. Toolbars 자료가 없다는 앞선 추정은 틀렸다.
- Chips: 제품 옆 raw token table과 styleguide의 전용 component/data 파일은 찾지 못했다.
  lab의 `modules/chip/`에는 패턴과 감사 기록이 있다. 현재 Chips 토큰 추출은 후속 확인이다.
- lab `modules/loading/`과 `modules/progress/`에는 spec/measurement/WP mapping 기록이 있다.
  제품 Stylebook의 컴포넌트나 현재 규격 검증으로 간주하지 않는다.

현재 specs 본문의 Loading 48/38dp와 Progress 기본 4dp, flat/wavy 구성은 보존 자료와 맞는다.
이번에는 최신 원시 TOKEN_TABLE JSON 전체를 재추출하거나 모든 토큰을 재측정하지 않았다.
보존된 표의 존재와 현재 본문의 주요 수치 일치를 전체 최신성 보증으로 확대하지 않는다.

## 사용 기준과 상태 전환

[Loading guidelines](https://m3.material.io/components/loading-indicator/guidelines),
[Progress guidelines](https://m3.material.io/components/progress-indicators/guidelines)를 기준으로:

- 예상 대기가 200ms 미만이면 별도 indicator 없이 결과를 보여준다. 200ms~5s의 짧고
  불확정인 작업에는 Loading, 5s를 넘는 작업에는 Progress를 권한다.
- 이 구분은 예상 작업의 성격을 고르는 기준이다. 5초 타이머로 Loading을 Progress로
  자동 교체하라는 뜻이 아니다. Loading에서 Progress로의 전환은 피하라고 명시한다.
- 처음 불확정이었다가 실제 진행량을 알 수 있게 되는 작업은 처음부터 Progress를 사용하고
  indeterminate에서 determinate로 전환한다. 진행량은 실제 측정값이어야 한다.
- linear는 주로 컨테이너 가장자리, circular는 중심에 놓는다. 추가 목록 로딩은 기존 콘텐츠를
  가리지 않고 새 항목이 들어올 공간에 둔다. 함께 로딩되는 그룹에는 하나를 쓰는 것이 기본이다.
- 이번에 읽은 페이지에서 별도의 최소 표시 지속시간이나 정량적인 완료 fade 시간을 확인하지
  못했다. 200ms를 최소 표시시간으로 해석하거나 임의 값을 upstream 규칙처럼 쓰지 않는다.

## Loading과 lab의 차이

[Overview](https://m3.material.io/components/loading-indicator/overview),
[Specs](https://m3.material.io/components/loading-indicator/specs),
[Accessibility](https://m3.material.io/components/loading-indicator/accessibility):

- 현재 Loading은 7개 Material 도형을 연결하는 반복 morph다. lab의 원형 SVG stroke spinner는
  이것과 다르다. lab의 contained 치수 기록도 현재 48dp 외곽/38dp shape와 다르다.
- 기본 active는 primary, contained는 on-primary-container와 primary-container를 사용한다.
  다른 콘텐츠 위에서는 contained를 사용해 대비를 확보한다.
- 크기는 24~240dp 범위에서 조절하며 외곽과 active shape 비율을 유지한다.
- 현재 접근성 산문은 progress bar 역할과 작업 대상을 설명하는 이름을 요구한다.
  lab의 standalone `role="status"`를 그대로 승격하지 않는다. Web의 값 속성, aria-busy,
  별도 live announcement 구성은 구현 시 접근성 계약으로 검증할 항목이다.
- active와 배경의 대비는 3:1 이상이다. pull-to-refresh에는 제스처 외 새로고침 수단이 필요하다.
  Android/Compose 전용 동작 설명을 Web의 이미 지원되는 기능으로 쓰지 않는다.

## Progress에서 보존할 조건

[Overview](https://m3.material.io/components/progress-indicators/overview),
[Specs](https://m3.material.io/components/progress-indicators/specs),
[Accessibility](https://m3.material.io/components/progress-indicators/accessibility):

- linear/circular 각각 determinate/indeterminate, flat/wavy 구성을 구분한다.
  lab은 wavy를 제외했으므로 이 범위 결정을 현재 제품으로 자동 상속하지 않는다.
- linear determinate의 끝점은 4dp stop indicator다. track과 주변 배경의 대비가 3:1 미만이면
  필요하며, circular와 indeterminate에는 쓰지 않는다.
- 버튼 안의 circular는 label/icon 색을 사용하고 track을 제거하는 지침이 있다.
- linear는 RTL에서 반전하고 circular는 반전할 필요가 없다. 작은 버튼에는 flat을 권한다.
- 기본 두께보다 굵은 예시는 고정된 별도 variant 토큰이 아니라 사용 사례별 조절 예시다.

## Skeleton, 전환, shape morph

[Transition patterns](https://m3.material.io/styles/motion/transitions/transition-patterns),
[Applying transitions](https://m3.material.io/styles/motion/transitions/applying-transitions):

- Skeleton은 독립적인 컴포넌트 토큰 집합이라기보다 M3가 명시한 전환 패턴이다. 콘텐츠가
  나타날 위치를 먼저 예약하여 레이아웃 이동을 줄이고, 로딩 중 완만하게 pulse한 뒤
  콘텐츠가 그 위로 빠르게 나타나는 형태다. SVG가 필수라는 설명은 없다.
- 제품 관점의 판단: 런타임에서 카드/목록의 자리와 크기를 예약하는 작업은 PWA 여부보다
  예상 레이아웃을 아는지의 문제다. 브라우저/OS의 앱 시작 화면을 제어하는 일과 구분한다.
  읽기 화면의 실제 형태가 정해진 뒤 필요한 Skeleton 조합을 고르는 편이 맞다.
- reduced motion에서는 강한 이동/확대 대신 작은 fade를 사용하고 장식적 parallax/morph를
  끄라는 지침이 있다. Loading 자체의 구체적인 정지 대체 모습은 별도 제품 판정으로 남긴다.
- 이 transitions 문서는 아직 legacy easing/duration 체계를 사용한다고 명시한다.
  shape morph의 expressive motion 기본값과 모든 화면 전환의 motion 계약을 혼동하지 않는다.

[Shape overview](https://m3.material.io/styles/shape/overview-principles),
[Shape morph](https://m3.material.io/styles/shape/shape-morph):

- shape library는 35개 도형이며, 도형 하나에 고정된 기능적 의미를 부여하지 않는다.
- 공식 shape morph API는 플랫폼별이며 현재 Web 구현은 제공되지 않는다고 명시한다.
  이것은 Web/PWA에서 구현 불가능하다는 뜻이 아니라 공식 API를 그대로 가져올 수 없다는 뜻이다.
- Loading을 정확히 재현하려면 7개 도형의 geometry뿐 아니라 연결 순서, 보간과 회전/타이밍도
  확인해야 한다. 정적인 SVG 한 장만으로 해결됐다고 보지 않는다. 에셋 요청 전에 공식 구현과
  기존 로컬 geometry의 재사용 가능성을 확인한다. 이번에는 SVG 제공을 요청할 단계까지 가지 않았다.

공식 구현의 후속 출발점:
[AndroidX LoadingIndicator.kt, 관측한 commit snapshot](https://android.googlesource.com/platform/frameworks/support/+/72aa0200196dcc2517b3fb340a7614bcd9147515/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/LoadingIndicator.kt).
이 snapshot의 기본 불확정 순서는 SoftBurst, Cookie9Sided, Pentagon, Pill, Sunny,
Cookie4Sided, Oval이다. Web으로 이식하거나 morph 수치를 대조한 것은 아직 아니다.
플랫폼 API가 제공하는 다른 mode까지 M3 Web 문서의 권장 사용법으로 확대하지 않는다.

## Geometry 추출 방향 검토

2026-10-21 추가. 사용자가 제공한 Material Web, Flutter, Android 구현을 대조했다.
아래는 제안이며 추출기나 Web 애니메이션을 구현/검증한 상태가 아니다.

Container shape 토큰은 내부 active shape의 곡선 좌표를 제공하지 않는다. Figma SVG export를
전제로 삼지 않고 공식 polygon 정의에서 재생성하는 방향이 적합하다. 역할은 셋으로 나뉜다.

1. `MaterialShapes`: 7개 도형의 꼭짓점, rounding, 방향, 정규화 정의.
2. `RoundedPolygon.cubics`: 렌더링에 사용할 cubic Bezier geometry.
3. `Morph(start, end).asCubics(t)`: 두 도형의 feature를 매칭하고 곡선을 분할하여 대응시킨
   보간 결과. 독립 SVG 7장의 `d`가 처음부터 같은 command/point 개수를 가진다고 가정하지 않는다.

따라서 정적 미리보기 SVG와 애니메이션 데이터를 구분해서 생성할 수 있다. 제안하는
애니메이션 데이터는 각 인접 쌍의 `Morph.asCubics(0)` / `Morph.asCubics(1)` 좌표다.
마지막 Oval에서 첫 SoftBurst로 돌아오는 쌍도 포함한다. Web 런타임은 현재 쌍의 정렬된
좌표를 보간해 SVG path를 그리고 회전을 적용한다. 일반 polygon 매칭 알고리즘을 JS로
재작성하거나 Android 런타임을 브라우저에 싣는 대신 빌드 시점에 복잡한 매칭을 끝내는 안이다.
쌍별 command 수, endpoint의 원본 geometry 일치, 정규화 bounds, loop 접합은 검증이 필요하다.

출처와 대조한 구현:

- [MDC MaterialShapes.java](https://github.com/material-components/material-components-android/blob/master/lib/java/com/google/android/material/shape/MaterialShapes.java)
- [AndroidX RoundedPolygon.kt](https://github.com/androidx/androidx/blob/androidx-main/graphics/graphics-shapes/src/commonMain/kotlin/androidx/graphics/shapes/RoundedPolygon.kt)
- [AndroidX Morph.kt](https://github.com/androidx/androidx/blob/androidx-main/graphics/graphics-shapes/src/commonMain/kotlin/androidx/graphics/shapes/Morph.kt)
- [MDC DrawingDelegate](https://github.com/material-components/material-components-android/blob/master/lib/java/com/google/android/material/loadingindicator/LoadingIndicatorDrawingDelegate.java)
  는 7개 polygon의 정규화, 인접 Morph 생성, morphFraction의 쌍 선택과 path 생성을 분리한다.
- [MDC AnimatorDelegate](https://github.com/material-components/material-components-android/blob/master/lib/java/com/google/android/material/loadingindicator/LoadingIndicatorAnimatorDelegate.java)
  에는 도형당 650ms, 기본 회전 50도와 spring 구동 추가 회전 90도, stiffness 200,
  damping ratio 0.6이 있다. 이 수치는 관측한 Android 구현값이지 Web에 채택한 계약이 아니다.
  SVG geometry만 export하면 이 시간/회전 동작은 포함되지 않는다.

생성기를 만든다면 upstream commit과 라이브러리 버전, 좌표 정밀도, 정규화 규칙, 출처와
라이선스를 함께 보존하고 `--check`로 재생성 일치를 검사한다. 현재는 생성된 에셋이 없다.

### 기존 motion 기반은 이미 연결돼 있다

테마 `assets/styles/tokens/tokens.sys.motion.css`가 spring의 Web 변환 곡선과 대응 duration을
제공하고 foundation loader가 프론트앱에 전달한다. Button/FAB의 shape 변화와 Dialog 등은
이미 이 값을 소비한다. 별도 spring 토큰 체계나 범용 motion engine을 선행 작업으로 만들지 않는다.

실제 Admin iframe 안의 `/stylebook/components/buttons`에서 computed 값을 확인했다.

| Spatial | Curve | Duration |
|---|---|---|
| fast | cubic-bezier(0.42, 1.67, 0.21, 0.9) | 350ms |
| default | cubic-bezier(0.38, 1.21, 0.22, 1) | 500ms |
| slow | cubic-bezier(0.39, 1.29, 0.35, 0.98) | 650ms |

따라서 남은 것은 polygon 좌표/쌍 매칭과 Loading의 반복 phase/회전을 기존 motion 값에
연결하는 일이다. corner-radius morph가 이미 된다는 사실은 arbitrary SVG path의 대응점
매칭까지 구현됐다는 뜻은 아니다. 기존 곡선은 stiffness/damping을 직접 적분하는 spring
runtime이 아니라 cubic-bezier + duration 표현이다. Android의 도형당 650ms와 slow spatial의
650ms가 같다는 이유만으로 두 애니메이션이 동등하다고 판단하지 않는다.

### 단순 spinner의 위치

[Material Web progress](https://material-web.dev/components/progress/)는 linear/circular의
determinate/indeterminate 구현 선례다. 따라서 단순 원형 spinner 자체가 금지된 것은 아니다.
다만 이것은 circular indeterminate progress이며 7-shape Expressive Loading과 동일하지 않다.
lab spinner를 우선 사용한다면 임시 Loading 구현을 중복 생성하기보다 CircularProgress의
사용 사례로 두고, shape-morph Loading의 구현 여부를 별도로 표시하는 편이 낫다.

[Material Web linear tokens](https://github.com/material-components/material-web/blob/main/tokens/_md-comp-linear-progress.scss)
는 `versions/v0_192/md-comp-linear-progress-indicator`를 참조한다. 현재 제품 raw 표의 통합
`md.comp.progress-indicator` 토큰과 같은 세대라고 가정해 복사하지 않는다.
[Flutter circular](https://api.flutter.dev/flutter/material/CircularProgressIndicator-class.html)와
[linear](https://api.flutter.dev/flutter/material/LinearProgressIndicator-class.html)도 Progress의
선례다. `year2023` 등 외형 전환 옵션을 확인해야 하며,
[`useMaterial3`](https://api.flutter.dev/flutter/material/ThemeData/useMaterial3.html)가 true라는
사실만으로 최신 Expressive Loading geometry를 제공한다고 판단하지 않는다.

[MDC LoadingIndicator.java](https://github.com/material-components/material-components-android/blob/master/lib/java/com/google/android/material/loadingindicator/LoadingIndicator.java)
에는 showDelay와 minHideDelay, minHideDelay 상한 1000ms가 있다. 이것은 앞 절에서 확인하지
못했던 산문상의 보편적 최소 표시시간을 새로 발견했다는 뜻이 아니라 플랫폼 구현의 옵션이다.

## Material Web와 Flutter의 실제 애니메이션 경계

2026-10-21 소스 확인. Material Web Progress가 WAAPI를 사용할 것이라는 추정은 맞지 않았다.
확인한 `progress/internal` 구현은 다음과 같다.

- [`progress.ts`](https://github.com/material-components/material-web/blob/main/progress/internal/progress.ts):
  value/max/indeterminate와 progressbar ARIA 속성을 담당한다. animation engine은 없다.
- [`circular-progress.ts`](https://github.com/material-components/material-web/blob/main/progress/internal/circular-progress.ts):
  determinate는 SVG circle + stroke-dashoffset, indeterminate는 반원으로 clip한 bordered div
  두 개를 사용한다. SVG 대비 성능 이유를 적었지만 Chrome 111에서의 과거 측정이며 현재
  브라우저에서도 같은 성능 배수가 나온다고 주장하지 않는다.
- [`_circular-progress.scss`](https://github.com/material-components/material-web/blob/main/progress/internal/_circular-progress.scss):
  CSS keyframes로 arc 확대/축소, arc 회전, 전체 회전을 조합한다.
- [`linear-progress.ts`](https://github.com/material-components/material-web/blob/main/progress/internal/linear-progress.ts)와
  [`_linear-progress.scss`](https://github.com/material-components/material-web/blob/main/progress/internal/_linear-progress.scss):
  값은 scaleX, determinate 변화는 CSS transition, indeterminate의 두 bar는 CSS keyframes로
  구동한다. 보이지 않는 buffering dots는 animation도 돌리지 않도록 한다.

따라서 CSS를 쓰는 upstream을 WAAPI로 재작성하는 것을 구현의 전제 조건으로 삼지 않는다.
Web용 markup/CSS/ARIA 분리는 Material Web을 출발점으로 쓸 수 있지만, 현행 M3의 통합 토큰,
gap/stop/wavy 요구까지 이미 구현됐다고 가정해서 통째로 복사하지 않는다.

[`Flutter progress_indicator.dart`](https://github.com/flutter/flutter/blob/master/packages/flutter/lib/src/material/progress_indicator.dart)는
AnimationController의 시간값을 Interval/CurveTween 등으로 변환하고 CustomPainter가 그린다.
linear는 두 선의 head/tail, circular는 head/tail/offset/rotation을 분리한다. 불확정이면
controller를 반복하고 확정 값이 있으면 내부 반복을 멈추며 dispose 시 해제한다.
외부/theme controller를 통한 동기화도 지원한다. 최신 외형 분기에는 track gap, rounded cap,
linear stop indicator가 있다. Flutter의 Canvas runtime을 가져오기보다 계산/상태 분리와
geometry의 대조 대상으로 삼는다. 이 파일의 Progress를 7-shape Loading 구현으로 부르지 않는다.

구현 방향 제안은 Web의 CSS/SVG 구조, Flutter의 최신 geometry 대조, AndroidX/MDC의 Loading
morph 원본을 조합하는 것이다. 기존 테마 motion 토큰은 유지한다. Loading의 path 보간과
수명주기에 WAAPI가 유리한지는 실제 추출 데이터와 브라우저 재생을 검증한 뒤 결정한다.
