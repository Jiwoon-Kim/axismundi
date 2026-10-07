# 계획: Social Home 조합 순서

## 상태

**제안됨. 2026-10-07.** Material 3 Design Kit의 `Examples/Home-Mobile`과
`Examples/Home-Web` 구조를 component inventory에 대조한 구현 순서다. Figma 예제는 조합과
의존성을 확인하는 VQA source이고, 수치나 component contract의 권위는 각 M3 source와
`products/styleguide/_data/*.yml`이다.

## 1. 화면 구조에서 확인된 것

Home에는 Carousel이 여섯 번이 아니라 **네 번** 있다.

| 영역 | 구조 | Carousel 의존 |
| --- | --- | --- |
| Section 1 | Title header + visual Carousel | 있음 |
| Section 2 | Title header + Carousel + 별도 text/actions | 있음 |
| Section 3 | Cards 01–08 | 없음 — Card collection |
| Section 4 | Title header + Carousel of cards | 있음 |
| Section 5 | Swipeable area + column of List items | 없음 — List contract |
| Section 6 | Artist header + small Carousel + supporting text | 있음 |

Mobile의 status/gesture bar와 Web의 browser chrome은 example frame의 장치 chrome이며 Social
component가 아니다. Navigation bar, Navigation rail, App bar와 content pane은 이미 Scaffold
topology가 소유한다.

## 2. 구현 순서

```text
1  carousel.yml + validator
2  Carousel · CarouselItem DOM, keyboard, label contract
3  Uncontained
3' Section 3: existing non-actionable Card + grid        (3과 병행 가능)
4  Sections 1 · 2 · 4 · 6
5  Multi-browse keylines · masking · dynamic width
6  Hero · full-screen
7  Section 5: ListItem
8  ActionableCard: whole-card target이 측정된 뒤에만
```

Uncontained이 첫 구현인 이유는 단순성만이 아니다. M3 reduced-motion contract가 parallax와
위치 기반 확장을 제거하고 **모든 item을 같은 크기로** 만들기 때문에, 모든 advanced layout이
균일한 Uncontained geometry로 내려올 수 있어야 한다. 첫 구현이 이후 layout의 필수 fallback이
되어 버려지는 작업이 없다.

Section 3은 Carousel을 기다리지 않는다. 기존 `Card`는 nested actions를 허용하는
non-actionable container이므로 먼저 grid를 구성한다. 전체 Card가 하나의 target이어야 한다는
것이 실제 interaction에서 확인되기 전에는 `ActionableCard`를 만들지 않는다. 이 fixture가 그
필요성을 추정이 아니라 측정으로 바꾸는 자리다.

## 3. Carousel과 Home header의 계약

`Title header`는 현재 Home composition이다. 그러나 header의 arrow action은 장식이 아니라 M3가
vertically scrolling page에 요구하는 **비수평 Show all 경로**다.

- header가 있으면 같은 행의 48dp arrow icon button이 전체 목록으로 이동한다.
- 전체 목록 화면은 같은 header를 다시 표시한다.
- header가 없으면 Carousel 아래에 padding 4dp인 Show all button을 둔다.
- full-screen Carousel만 이 대체 경로 요구의 예외다.
- Carousel container 안이나 바로 옆에는 이전/다음 control을 추가하지 않는다.

`DECISION-FRONTEND-SCROLL-OWNERSHIP.md`가 Social의 기본을 document scroll로 채택했으므로,
표준 Social route의 non-full-screen Carousel은 위 조건을 **항상** 만족해야 한다. 호출자가
선택적으로 켜는 enhancement가 아니다.

## 4. 컴포넌트 경계

- `Carousel`은 layout, scrolling, focus movement와 labeling을 소유하고 item content를 모른다.
- `CarouselItem`은 직접 actionable한 item contract를 소유한다. `Card`를 상속하지 않는다.
- `Card` collection은 Carousel이 아니다. Section 3의 grid는 독립적으로 구현한다.
- `ListItem`과 swipeable list는 Carousel이 아니다. Section 5에서 별도 contract로 만든다.
- Section 2의 tonal IconButton은 Carousel 밖의 text composition에 있다. 직접 actionable item
  안에 nested action을 넣는 근거가 되지 않는다.

## 관련 기록

- `products/styleguide/_data/carousel.yml` — layout, 금지 조합, item state와 accessibility source.
- `DECISION-FRONTEND-SCROLL-OWNERSHIP.md` — document scroll과 Show all 조건의 상시 성립.
- `DECISION-FRONTEND-LAYOUT-NAMING.md` — Scaffold와 Pane의 공개 어휘.
- `products/reference-implementations/axismundi-lab/modules/carousel/` — progressive-enhancement
  참고 구현. 현행 contract의 권위는 아니다.

