# 계획: Carousel 엔진 재구성

## 상태

**진행 중. 2026-10-09.** 현재 Carousel은 Material 3 Design Kit에서 측정한 정적
profile을 runtime 알고리즘의 입력으로 사용한다. 이 계획은 그 방향을 철회하고,
AndroidX Material 3 Carousel의 공개 API와 내부 responsibility split을 웹 구현의
기준으로 삼는다.

Figma의 `412px`·`600px` profile은 버리지 않는다. 알고리즘을 만드는 source가 아니라,
알고리즘이 같은 시각 결과를 내는지 확인하는 regression fixture로만 사용한다.

## 1. 확인된 upstream 구조

AndroidX는 variant를 하나의 자유 조합형 prop으로 공개하지 않는다.

| Variant | 공개 입력 | 기본 fling |
| --- | --- | --- |
| `HorizontalUncontainedCarousel` | exact `itemWidth` | no-snap |
| `HorizontalMultiBrowseCarousel` | target `preferredItemWidth`, small min/max | single-advance snap |
| `HorizontalCenteredHeroCarousel` | optional target `preferredItemWidth`, small min/max | single-advance snap |

세 API는 내부 `Carousel`에 서로 다른 `KeylineList` factory를 넘긴다. 내부 엔진은
`CarouselState`와 Pager를 사용하고, 모든 item을 strategy의 large item size로 배치한 뒤
scroll position에 따라 mask와 translation을 적용한다.

출처:

- `https://developer.android.com/develop/ui/compose/components/carousel`
- `https://github.com/androidx/androidx/blob/a0c645de83b353f2aa79c3b33a11e8ba853ca1e4/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/carousel/Carousel.kt`
- `https://github.com/androidx/androidx/blob/a0c645de83b353f2aa79c3b33a11e8ba853ca1e4/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/carousel/Keylines.kt`
- `https://github.com/androidx/androidx/blob/a0c645de83b353f2aa79c3b33a11e8ba853ca1e4/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/carousel/Strategy.kt`

직접 port하는 코드가 생기면 Apache-2.0 attribution과 원본 commit을 파일 머리에 남긴다.

## 2. 철회하는 현재 전제

다음 값은 Figma fixture의 측정 결과이지 runtime strategy의 입력이 아니다.

```text
184px large reference
120px medium
56px small
600px container profile switch
[316, 56], [188, 120, 56], [56, 252, 56]
```

따라서 현재 `keylineProfile()`과 그 profile에서 사후 조립한 start/default/end 전환은
교체한다. `carousel.yml`의 측정 사실은 유지하되 `implemented` 표시는 새 엔진 검증 뒤에만
다시 부여한다.

## 3. 웹 공개 API

variant contract를 이름과 prop으로 분리한다.

```js
<UncontainedCarousel itemWidth={ 280 } />
<MultiBrowseCarousel preferredItemWidth={ 186 } />
<CenteredHeroCarousel preferredItemWidth={ 316 } />
```

공통 접근성 prop과 `CarouselItem` contract는 유지한다. 기존 자유 조합형
`<Carousel layout="...">`은 stylebook migration이 끝날 때까지 compatibility wrapper로만
남기고, 제품 route의 새 사용을 금지한다.

`multiAspect`는 AndroidX 공개 Carousel API가 아니다. 다양한 aspect ratio를 가진 fixed-width
item collection이라는 M3/Figma configuration으로 유지하되, AndroidX parity를 주장하지 않는
별도 Uncontained composition으로 둔다.

## 4. 내부 계층

```text
variant component
  -> pure keyline strategy(input size, count, spacing, width policy)
  -> carousel state(current item, offset, item count)
  -> DOM renderer(scroll offset -> interpolated keyline -> mask/translation)
  -> CarouselItem content
```

필수 불변식:

- strategy는 `itemCount`를 입력으로 받아 부족한 item에 phantom keyline을 요구하지 않는다.
- underlying item stride는 strategy의 large item size + spacing이다.
- 보이는 small/medium 크기는 flex-basis가 아니라 mask 결과다.
- start/end는 별도 임의 profile이 아니라 default keylines를 focal range가 edge에 오도록
  단계적으로 이동한 strategy다.
- snap target은 DOM item의 현재 masked left edge가 아니라 state의 item stride와 keyline snap
  position에서 계산한다.
- reduced motion에서는 위치 기반 morph와 parallax를 제거하고 균일 geometry로 내린다.
- RTL은 state offset과 translation에서 한 번만 반전한다.

## 5. 구현 순서

```text
1  pure keyline data types와 strategy contract
2  Uncontained strategy + state + renderer
3  Multi-browse arrangement search와 strategy
4  Centered Hero strategy
5  pointer/touch/keyboard를 state에 연결
6  stylebook을 variant API로 migration
7  Figma 412/600 profile을 output regression으로 측정
8  carousel.yml과 validator의 implementation 상태 갱신
```

Uncontained을 먼저 만들지만 단순 overflow strip으로 취급하지 않는다. AndroidX의
Uncontained도 공통 keyline engine을 사용하며 edge item을 mask한다. 이 경로가 이후 advanced
layout renderer와 reduced-motion fallback의 기반이다.

## 6. 보존 범위

다음은 엔진과 독립적이므로 유지한다.

- `CarouselItem`, `CarouselItemMedia`, `CarouselItemText`
- container/item labeling과 keyboard exit contract
- Show all 대체 경로
- Picsum 기반 10-item stylebook data
- 별도 Figma static profile section
- page-level horizontal overflow containment

## 7. 검증

순수 strategy에는 table-driven unit test를 둔다. 최소 검증은 다음과 같다.

- item count 0·1·2·5·10
- container 412·600과 그 사이 임의 폭
- small size가 40–56 범위를 벗어나지 않음
- keyline arrangement가 available space를 채움
- 첫 item과 마지막 item이 focal position에 도달함
- forward/reverse drag 뒤 current item과 offset이 일치함
- reduced motion에서 모든 item geometry가 균일함
- document horizontal overflow가 생기지 않음

브라우저 fixture는 시작·중간·끝에서 mask width, translation, current item, scroll offset을 함께
출력한다. 정적 Figma profile은 같은 section에 섞지 않는다.
