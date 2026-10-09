# 계획: Multi-aspect ratio carousel의 morph

## 상태

**설계만. 배포 이후로 미룸. 2026-10-10.** 지금 multi-aspect는 morph하지 않는다 —
항목은 자기 폭으로 흐르고 컨테이너 경계에서 잘린다. 그 상태로 **충분히 쓸 수 있고**,
소유자가 배포 뒤로 미루기로 했다. 이 문서는 나중에 구현할 사람이 같은 함정에 두 번
빠지지 않도록, 왜 기존 morph를 못 켜는지와 무엇을 만들어야 하는지를 적는다.

## 1. 기존 morph를 켜면 안 되는 이유

AndroidX 전략은 **단일 `itemWidth`와 동일 stride**를 전제한다. keyline은 그 하나의 폭에서
생성되므로, keyline의 offset은 "모든 stride가 같을 때 항목이 앉는 자리"다.

그걸 가변폭에 그대로 적용했을 때 실제로 난 일 둘:

```txt
항목을 keyline offset으로 옮김   →  서로 미끄러져 겹치고 8dp gap이 사라짐
가운데 정렬 클립으로 마스크      →  272 박스 안에 84.9만 보이고 양옆에 빈 박스
                                   캐러셀이 8을 선언한 자리에서 101.5dp가 측정됨
```

둘 다 측정으로 잡혔고 되돌렸다. **"일단 켜 보고 조정한다"로 접근하면 같은 자리로 돌아온다.**

## 2. 불변식 — 무엇을 깨뜨리면 안 되는가

multi-aspect의 전체 폭은 계산 가능해야 한다. 그것이 이 레이아웃에 ratio를 필수로 만든 이유다.

```txt
전체 폭 = leading padding
        + Σ (컨테이너 높이 × ratioᵢ)
        + gap × (itemCount - 1)
        + trailing padding
```

morph가 이 합을 바꾸면 **snap 위치와 scrollWidth가 매 프레임 흔들린다.** 따라서:

- **항목 박스는 움직이지도 커지지도 않는다.** morph는 확정된 박스 위의 마스크다.
- 착지점은 지금처럼 **항목 자기 leading edge**다. 이미 구현돼 있고 바뀔 이유가 없다.
- 항목 사이 gap은 어떤 스크롤 위치에서도 8dp다. 이것이 회귀 판정의 1차 기준이다.

## 3. 만들 것 — 누적폭 기반 edge zone

keyline을 단일 폭에서 생성하지 말고, **양 끝에 edge zone 두 개**를 두고 가운데는 자연
흐름으로 둔다.

```txt
|<-- E -->|            중앙: 마스크 없음            |<-- E -->|
 leading                                              trailing
```

- 박스가 중앙에 온전히 들어오면 마스크 없음.
- edge zone에 걸친 항목은 **그 zone 안으로 들어온 만큼**만 보이고, 크기는 자기 폭에서
  anchor 크기까지 보간된다.
- 보간은 항목의 **중심이 zone을 지나는 비율**로 한다. 폭이 제각각이므로 index가 아니라
  위치가 기준이어야 한다.

### 마스크는 가장자리에 붙인다, 가운데가 아니다

균일 캐러셀에서 마스크를 가운데 정렬하는 이유는 **항목도 keyline으로 함께 옮겨져** 창과
박스의 중심이 같아지기 때문이다. 여기서는 항목이 안 움직이므로 그 전제가 없다.

```txt
leading zone   클립을 박스의 왼쪽에 붙인다  (inset은 오른쪽에만)
trailing zone  클립을 박스의 오른쪽에 붙인다
```

가운데 정렬로 두면 101.5dp 간극이 그대로 재현된다.

### outline도 같이 비대칭이 된다

outline은 이미 "보이는 창"을 따라가도록 `::after`로 그린다. 창이 비대칭이 되면
`inset-inline`도 비대칭이어야 한다. 지금은 양쪽에 같은 값을 넣고 있으므로 여기서 같이
고쳐야 한다.

## 3-1. 먼저 고칠 것 — stride와 box가 어댑터 없이 섞여 있다

**이건 multi-aspect만의 문제가 아니라 균일 uncontained에서 이미 나 있다.** morph가 켜진
uncontained의 *보이는* 간격을 재면 선언값 8dp가 아니다.

```txt
item 0·1   offset 0        mask 280     visible gap 8      ✓
item 2     offset -107.13  mask 73.74   visible gap 4
item 3     offset -345.05  mask 14.18   visible gap 6.12
item 4     offset -620.33  mask 10      visible gap 0.63
```

snap은 원인이 아니다 — 정지 상태에서도 그렇다. 원인은 모델이 둘이라는 것이다.

```txt
AndroidX   keyline의 size에 itemSpacing이 접혀 있다 (size ≒ itemWidth + spacing)
웹 구현     content box 280 + 별도 CSS gap 8
```

렌더러는 전략의 `size`를 **그대로** mask 폭으로 쓰고, 동시에 항목을 keyline 중심으로
옮긴다. 두 모델 사이의 변환이 어디에도 없다.

**단순히 모든 mask에서 gap을 빼는 것으로는 안 된다.** 그 가정으로 계산하면 2–3번 쌍이
14.1dp가 나온다 — 앵커 구간의 keyline은 edge-to-edge로 묶여 있지 않기 때문이다.

필요한 것은 **한 군데의 어댑터**다. 전략의 stride 좌표계를 웹의 `box + gap` 좌표계로
바꾸는 함수 하나를 두고, mask 폭과 translate를 둘 다 그것을 거쳐 계산한다. 검증은
래퍼 gap이 아니라 **보이는 창 사이의 간격**으로, 시작·중간·끝·드래그 중 각각 8dp여야 한다.
래퍼만 재면 지금처럼 놓친다.

이 어댑터가 서기 전에는 가변폭 morph를 시작할 수 없다. 같은 좌표계 혼동을 폭이 제각각인
항목들에 얹는 일이 되기 때문이다.

## 4. 발행되지 않은 값 — 우리가 정하는 것

M3는 이 경우의 식을 발행하지 않는다. 다음은 전부 **프로젝트 정책**이고, 그렇게 기록해야
한다.

```txt
E (edge zone 폭)   후보: small item 범위(40–56) 또는 AndroidX anchor(10)
보간 곡선           AndroidX는 keyline 사이 선형 보간
최소 가시 폭        anchor 크기 아래로 내려갈 이유가 없음
```

발행값처럼 보이는 이름(`md.comp.*`)을 붙이지 않는다. 이 저장소가 이미 한 번 당한 자리다.

## 5. 하지 않는 것

- **per-item keyline 리스트를 전략에 추가하지 않는다.** `carousel-strategy.js`는 핀된
  AndroidX revision의 포팅이고 upstream 벡터로 테스트된다. 가변폭 로직은 거기 넣지 말고
  렌더러 쪽 별도 경로로 둔다. 전략을 바꾸면 26개 벡터의 의미가 흐려진다.
- **reduced motion에서는 morph하지 않는다.** 이미 그렇고, multi-aspect는 "모든 항목이 같은
  크기"가 정의상 불가능하므로 자기 폭을 유지한 채 마스크만 꺼진다.

## 6. 검증 기준

```txt
항목 사이 gap          모든 스크롤 위치에서 8dp          ← 1차 회귀 기준
겹침                  0
scrollWidth           morph 전후 동일
snap 착지점            항목 자기 leading edge, 변화 없음
outline               보이는 창과 일치 (비대칭 포함)
reduced motion        마스크 없음, 폭 유지
```

앞의 두 줄이 이 작업에서 실제로 깨졌던 것이므로, 구현을 시작하기 전에 먼저 측정 스크립트를
만들어 두는 편이 낫다.

## 관련 기록

- `PLAN-FRONTEND-CAROUSEL-ENGINE.md` — AndroidX 포팅의 책임 분리.
- `_data/carousel.yml` — "keylines describe one stride, so varying widths take only the
  layout"에 이때 난 두 결함의 측정값이 있다.
