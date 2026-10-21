# Compose Progress: 실제 그리기 경로 대조

2026-10-21. 영상 역설계 대신 아래 고정 commit의 public API에서 internal drawing cache까지
읽었다. 이 문서는 조사 결과와 웹 이식 경계를 기록하며 wave UI 구현 완료를 뜻하지 않는다.

## 원본

AndroidX commit: `2e1a16243ae2c9284445a746f1d487c73af92623`

- [ProgressIndicator.kt](https://github.com/androidx/androidx/blob/2e1a16243ae2c9284445a746f1d487c73af92623/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/ProgressIndicator.kt)
- [WavyProgressIndicator.kt](https://github.com/androidx/androidx/blob/2e1a16243ae2c9284445a746f1d487c73af92623/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/WavyProgressIndicator.kt)
- [LinearWavyProgressModifiers.kt](https://github.com/androidx/androidx/blob/2e1a16243ae2c9284445a746f1d487c73af92623/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/internal/LinearWavyProgressModifiers.kt)
- [CircularWavyProgressModifiers.kt](https://github.com/androidx/androidx/blob/2e1a16243ae2c9284445a746f1d487c73af92623/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/internal/CircularWavyProgressModifiers.kt)

## 서로 다른 세 가지 시간축

1. 진행량: determinate는 외부 progress를 읽는다. 이 값 사이의 보간은 기본적으로 하지 않는다.
2. 불확정 구간: Linear head/tail 네 animation spec, Circular global/additional rotation 및 sweep.
   Wavy도 flat의 spec을 재사용한다. wave이기 때문에 진행 시간축을 새로 만들지 않는다.
3. 파형 위상: waveOffset은 별도로 반복한다. 따라서 determinate 값이 멈춰도 파형은 움직인다.

Linear offset 주기는 `wavelength / waveSpeed * 1000`ms다. 기본값은 한 파장/초이며 최소 50ms다.
Circular offset은 원 둘레 전체에 대한 비율이므로 여기에 vertex count를 곱한다. 9개 파형이면
기본 위상 한 바퀴가 9초다. 이것은 불확정 global rotation 6초와 다른 애니메이션이다.

## Linear: sine sampling이 아니라 quadratic path와 segment

호출 경로: public LinearWavyProgressIndicator → internal modifier →
determinate/indeterminate node → LinearProgressDrawingCache.updatePaths →
updateFullPaths / updateDrawPaths → drawPath.

`updateFullPaths`:
- 반파장마다 quadraticTo를 연결한다. control y는 부호를 번갈아 바꾼다.
- 최대 진폭의 기준 경로를 만들고 표시 시 y-scale로 amplitude를 적용한다.
- 경로는 표시 폭보다 두 파장 더 길게 만들어 위상 이동을 수용한다.
- PathMeasure의 전체 길이 / 실제 path bounds 폭을 progressPathScale로 캐시한다.

`updateDrawPaths`:
- progress fraction 쌍은 tail/head다. 확정은 한 쌍, 불확정은 두 쌍이다.
- cap을 위해 head/tail 좌표를 양끝 반두께 안으로 clamp한다.
- waveShift = waveOffset * wavelength를 더한 위치에서 getSegment한다.
- 추출한 경로를 -waveShift로 이동시켜 파형만 흐르게 한다.
- track은 별도의 평평한 경로이며 cap 너비와 초기 구간을 고려해 gap을 계산한다.

따라서 rectangular clip으로 active 끝을 자르는 기존 프로브는 동등한 구현이 아니다.
공식 구현은 추출된 stroke 자체의 round cap을 보존한다. 초기 점도 이 cap 처리와 연결된다.

## Circular: RoundedPolygon + Morph

호출 경로: public CircularWavyProgressIndicator → internal modifier/node →
CircularShapes / CircularProgressDrawingCache → updateFullPaths / updateDrawPaths → drawPath.

`CircularShapes.update`:
- r = min(size)/2 - strokeWidth/2.
- vertex count = max(5, round(2*pi*r/wavelength)).
- 같은 vertex count의 RoundedPolygon.circle과 RoundedPolygon.star를 normalized한다.
- star는 innerRadius .75, rounding radius .35/smoothing .4, innerRounding radius .5다.
- 중간 amplitude가 필요할 때 원→별 Morph를 만들고 amplitude로 보간한다.

이 geometry는 정현파가 아니다. Loading에서 사용한 AndroidX graphics-shapes와 같은 계열이므로
기존 JVM 추출 도구를 재사용할 수 있다. 다만 Loading의 7개 shape를 재사용한다는 뜻은 아니다.

`CircularProgressDrawingCache`:
- active와 track의 full path 및 PathMeasure를 따로 캐시한다.
- motion이 켜지면 active path를 반복해 위상 경계를 넘어도 구간을 추출할 수 있게 한다.
- pStart/startProgress와 pStop/endProgress는 active 경로 길이의 비율이다.
- waveOffset * active 한 바퀴 길이만큼 추출 구간을 이동한다.
- 추출 뒤 -waveOffset*360도만큼 역회전하여 위상 이동과 진행 구간의 회전을 분리한다.
- track은 자신의 경로 길이와 cap/gap spacing으로 별도 추출한다.
- 불확정 모드는 이 결과에 global/additional 회전을 더한다. Shape 시작 방향 보정도 별도로 있다.

**정정:** 이전 sine 프로브에서 측정한 호 길이 비율과 각도 비율의 .330 percentage point 차이는
규격 위반의 증거가 아니다. 공식 구현도 progress fraction을 경로 길이에 적용한다.
그 수치로 wave를 탈락시키거나 각도 기반 API가 필수라고 주장하지 않는다.

## Amplitude도 상태다

확정 모드의 기본 amplitude 함수는 progress <= .1 또는 >= .95일 때 0, 사이에서는 1이다.
진폭 변화에는 별도 animation을 적용한다. 이 commit의 DurationLong2는 500ms다.
증가에는 standard, 감소에는 emphasized-accelerate easing을 사용한다.
불확정 모드 기본 amplitude는 1이다. 진행값, 진폭, 위상을 하나로 묶으면 이 동작을 잃는다.

## 웹 이식 방향

공통 공개 API에 wave geometry callback을 추가하지 않는다. 먼저 다음 내부 renderer를 검증한다.

- Linear: 공식 quadratic geometry를 SVG path로 표현한다. pathLength 정규화와 dash 구간으로
  PathMeasure의 segment 추출에 대응하고, 위상만큼 반대 이동한다. round cap은 stroke로 둔다.
- Circular: 기존 JVM 도구로 원/별 및 Morph 대응 cubics를 추출한다. 반복 경로의 dash 구간과
  위상 반대 회전을 조합한다. track과 불확정 시간축은 별도로 유지한다.
- 진폭 변경에는 matched path 보간을 검토한다. 진폭/크기 변경 때의 경로 길이 정규화와 gap,
  위상 loop 경계, 작은 progress의 점, RTL은 별도 검증 항목이다.
- SVG의 pathLength/dash가 공식 getSegment와 동등하게 작동하는지는 아직 검증하지 않았다.
  dash로 불가능한 보정이 드러나면 기존 path slicing 라이브러리를 검토한다.
- reduced-motion에서는 실제 flat renderer를 사용한다. 파형 위상만 멈추는 대체가 아니다.

이 방향은 browser native path 처리를 우선하는 이식 후보이며 성능 통과나 exact port 선언이 아니다.
공식 알고리즘을 확보했으므로 이제 영상은 이식 뒤 시각 검증에 사용한다.

## 실행 가능한 geometry 확인

`tools/generators/material-shapes/src/main/kotlin/InspectProgress.kt`는 원본의 CircularShapes
recipe를 실제 graphics-shapes-desktop 1.1.0에서 실행한다. 24/48/240 크기, 두께4/파장15를
고정하고 다섯 amplitude의 cubics가 유한하고 닫혀 있으며 topology가 같은지 확인한다.
제품 파일을 생성하지 않으며 segment 추출·위상·렌더링 검증을 대신하지 않는다.
실행 명령은 generator README를 따른다. 크기를 바꾸면서 파장을 고정하면 vertex 수가 달라진다.
비례 확대와 고정 파장을 혼동하지 않는다.

실행 결과: 24px=5 vertices/35 matched cubics, 48px=9/27, 240px=49/147.
각 크기의 amplitude 0/.25/.5/.75/1 검사가 통과했다. 기존 Loading exporter의 --check도 통과했다.

버전 한계: 이 Compose commit의 `libraryversions.toml`은 GRAPHICS_SHAPES=1.2.0-alpha01이다.
현재 JVM 확인 도구는 기존 1.1.0을 그대로 사용했다. 따라서 recipe/API 재사용이 가능하다는
검증이지 해당 Compose 빌드와 모든 좌표가 동일하다는 검증은 아니다. 실제 wave 산출물 생성 전
upstream shapes 버전을 별도로 고정·대조해야 하며, 기존 Loading 의존성을 무단 승격하지 않는다.
