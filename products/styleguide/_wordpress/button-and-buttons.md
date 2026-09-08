---
title: Button and Buttons
description: M3 Button / Button group을 core/button과 core/buttons에 바인딩하는 방법, 그리고 누가 무엇을 정하는가
order: 10
lang: ko
---

```
M3 Button / connected Buttons / Button group
        ↓
core/button / core/buttons
        ↓
Axismundi theme.json + component CSS
```

세 층을 잇는 규칙은 세 갈래입니다.

> **하나의 button에서는 button이 크기와 스타일을 정한다.**
> **`core/buttons`는 그런 button들을 배치한다.**
> **진짜 M3 Button group에서만 group이 segment의 구성을 정한다.**

마지막 줄의 group 구성원은 독립된 button이 아니라 **segment**입니다. segment가
자기 색을 고르면 그건 group이 아니라 그냥 버튼 몇 개입니다. 하지만
`core/buttons`의 자식은 바로 그 “버튼 몇 개”가 맞습니다.

## `core/buttons`는 Button group이 아닙니다

이 구분이 이 페이지 전체의 축입니다.

| | 무엇인가 | 무엇을 정하는가 |
|---|---|---|
| `core/buttons` | action container | 배치와 `blockGap`. 자식 button이 크기·스타일을 정함 |
| `core/buttons.is-style-connected` | Connected Buttons variation | 연결 모양과 gap. 자식 button의 결정은 유지 |
| M3 Button group | 상태를 가진 별도 컴포넌트 | 모든 segment의 크기·모양·색, 선택 상태 |

WordPress에서 `core/buttons`는 원래 flex 컨테이너입니다. 테마의
`is-style-connected`는 그 컨테이너에 인접한 모서리와 좁은 gap을 주는 **실제
스타일 variation**입니다. 구현해 둔 것은 맞지만, 그것만으로 컨테이너가 M3의
Button group이 되지는 않습니다. `aria-pressed`, 선택 규칙, 공통 상태가 없고,
무엇보다 자식의 색과 크기를 재정의하지 않기 때문입니다.

<div class="sg-demo sg-demo--stack">
  <div class="wp-block-buttons">
    <div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Get tickets</a></div>
    <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Learn more</a></div>
  </div>
  <div class="wp-block-buttons is-style-connected">
    <div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Get tickets</a></div>
    <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Learn more</a></div>
  </div>
</div>

**두 줄의 마크업은 컨테이너 클래스 하나만 다릅니다.** 아래줄은 Button의 색
스타일을 무시하지 않습니다. Filled `Get tickets`와 Outlined `Learn more`가 그대로
남고, 인접한 모서리와 gap만 연결됩니다. card의 actions처럼 서로 다른 목적의
행동을 함께 놓는 것이 `core/buttons`의 본래 역할입니다.

## Connected는 CSS가 어디까지 바꾸는가

테마와 정적 어댑터 모두 container와 인접한 Button의 기하에 다음만 적용합니다.

```css
.wp-block-buttons.is-style-connected {
  flex-wrap: nowrap;
  gap: var(--md-sys-measurement-space25);
}
```

자식에 색·크기 custom property를 쓰지 않습니다. `assets/css/components/button.css`의
순서는 다음 계약을 보존합니다.

```
1 the control     커스텀 프로퍼티에서 읽는 기하
2 the button      크기
3 the button      색 스타일
4 the container   배치만
5 connected buttons  연결 모양만
```

M3 Button group은 Theme Switcher처럼 컴포넌트가 상태와 segment를 직접 소유할 때
구현합니다. `core/buttons`에 시각 스타일 하나를 더했다고 그 의미까지 생기지는
않습니다.

## 크기는 왜 `is-style-*`이 아닌가

M3에서 크기와 색은 **서로 구속하지 않는 두 축**입니다. 그런데 블록 에디터의
Styles 패널은 **단일 선택**입니다. 크기를 `is-style-*`에 태우면
`is-style-medium`과 `is-style-tonal`이 서로 배타적이 되고, M3가 직교로 정의한 두
축이 하나로 접힙니다.

그래서 크기는 평범한 클래스입니다.

```
스타일   is-style-*        Styles 패널에서 고름
크기     is-size-*         Advanced > Additional CSS class
모양     is-shape-square   같음
```

셋 다 에디터에서 지금 바로 도달 가능하고, 서로 조합됩니다. 나중에 플러그인이
`registerBlockVariation`으로 크기 선택 UI를 붙이더라도 클래스 계약은 그대로입니다.

`is-size-small`은 없습니다. Small이 기본값이고, 기본값에 클래스를 요구하면 M3가
"Small (default)"이라고 쓴 것과 어긋납니다.

## 테마가 실제로 쓰는 두 경로

같은 결과를 내는 두 개의 등록 방식이 있고, 테마는 둘 다 씁니다.

| 경로 | 무엇 | 어디 |
|---|---|---|
| `styles.elements.button` | 모든 버튼의 기본 표면 | `theme.json` |
| `styles.blocks.core/button.variations.outline` | core가 이미 등록한 변형에 스타일만 | `theme.json` |
| `styles/blocks/button-*.json` | 변형 등록 + 스타일 | 자동 등록 partial |

`elements.button`이 M3 Small 표면 전체를 소유합니다 — 40dp 높이, 16dp 좌우,
label-large 서체, pill 반지름 20px, 눌린 8px, state layer, focus ring. Small
하나만 있는 이유이자, 다른 크기를 넣으려면 이 element 레벨을 크기별 클래스로
쪼개야 하는 이유입니다.

## 아직 바인딩되지 않은 것

이 구역이 존재하는 이유입니다. 비슷하게 만들어 두고 어긋난 채로 두면 그 차이는
나중에 접근성이나 에디터 동작으로 돌아옵니다.

| M3 | 상태 | 막고 있는 것 |
|---|---|---|
| 색 스타일 5종 | 바인딩됨 | — |
| Small (40dp) | 바인딩됨 | — |
| Round 모양 + 눌림 morph | 바인딩됨 | — |
| XS · M · L · XL | 없음 | `elements.button`이 크기를 하나로 못박고 있음 |
| Square 모양 | 없음 | 위와 같음 |
| M3 Button group | 없음 | `core/buttons`는 action container이며 선택 상태를 저장하지 않음 |
| Toggle button | 없음 | 선택 상태를 저장할 곳이 core/button에 없음 |
| Disabled | 없음 | `theme.json` element 모델에 `:disabled`가 없음 |
| Icon slot | 없음 | core/button은 라벨만 가짐 |
| Target area 48dp | 없음 | XS·S에서 시각 크기와 타깃 크기가 갈라짐 |

**Toggle이 가장 깊은 항목입니다.** 나머지는 CSS 문제이지만 토글은 상태 문제입니다.
`core/button`은 눌린 상태라는 개념이 없고, `aria-pressed`를 저장할 속성도 없습니다.
지금 이 사이트에서 토글로 동작하는 것은
[Theme Switcher]({{ '/components/theme-switcher/' | relative_url }})뿐이고, 그건
core 블록이 아니라 플러그인 블록입니다. 그 경계가 우연이 아닙니다.

**Disabled는 이 사이트가 테마보다 앞서 있는 유일한 지점입니다.** 정적 어댑터는
`:disabled`와 `[aria-disabled]`를 구현하지만 테마는 못 합니다. 문서가 제품을
앞지르는 건 좋지 않으므로 여기에 적어 둡니다.

## 두 개의 Outlined

`core/button`에는 **outline 계열 스타일이 두 개 등록되어 있습니다.**

```
is-style-outline    core 등록 · theme.json styles.blocks.core/button.variations.outline
is-style-outlined   테마 등록 · styles/blocks/button-outlined.json
```

`is-style-outline`가 core/button의 canonical variation입니다. 반면
`is-style-outlined`는 Dialog·Sheet를 포함한 multi-block partial의 slug이고, 이미
저장된 콘텐츠에도 존재할 수 있는 legacy class입니다. 두 이름을 같은 것으로
취급하거나 무심코 하나를 지우면 안 됩니다.

`button-outlined.json`이 그래도 지워지지 않은 이유는 `blockTypes`에 있습니다.

```json
"blockTypes": [ "core/button", "axismundi/dialog", "axismundi/sheet" ]
```

core의 `outline`은 `core/button`에만 등록되므로, dialog와 sheet의 열기 버튼에
Outlined를 주는 곳은 이 partial뿐입니다. 즉 **`core/button` 쪽만 중복**입니다.

partial의 `styles.elements.button`은 프런트와 에디터에서 잘못된 **후손 선택자**로
나옵니다.

```
.is-style-X .wp-block-button__link .wp-element-button
```

실제 마크업은 `<a class="wp-block-button__link wp-element-button">` 하나이므로 이
선택자는 아무것도 잡지 못하고 기본 Filled가 남습니다. 테마는 tonal·elevated·text에
이어 `is-style-outlined`에도 공개 클래스 규칙을 둡니다. 따라서 legacy class는
프런트와 에디터 모두에서 Outlined 표면으로 렌더됩니다.

장기적으로는 core/button에서 `is-style-outlined`를 노출하지 않고
`is-style-outline`로 저장 콘텐츠를 마이그레이션하는 편이 낫습니다. 다만 Dialog와
Sheet는 같은 partial을 계속 쓰므로, migration 전에 이 shim을 지우면 안 됩니다.

```
A  outlined shim 추가        3줄. 에디터 미리보기만 고침, 중복은 남음
B  blockTypes에서 core/button 제거   중복 제거, dialog·sheet 유지. 여전히 A 필요
C  partial 삭제 + dialog·sheet를 다른 방식으로   가장 깨끗하지만 범위가 큼
```

## 마크업

블록 에디터가 쓰는 것과 같습니다. 이 페이지의 버튼들이 실제로 그 마크업입니다.

```html
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"tagName":"button"} -->
<div class="wp-block-button"><button type="button" class="wp-block-button__link wp-element-button">Filled</button></div>
<!-- /wp:button -->

<!-- wp:button {"tagName":"button","className":"is-style-tonal"} -->
<div class="wp-block-button is-style-tonal"><button type="button" class="wp-block-button__link wp-element-button">Tonal</button></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
```

`tagName`이 `button`인지 `a`인지는 시각적 선택이 아닙니다. `href`가 있는 앵커는
탐색이고, 동작을 실행하는 것은 `<button>`입니다. 둘을 같은 CSS로 칠하는 것은
맞지만 **같은 것으로 취급하면 안 됩니다** — 키보드 동작도 스크린 리더가 읽는
역할도 다릅니다.

## 드리프트 방지

정적 어댑터는 `theme.json`의 두 번째 사본입니다. 이 저장소에서 두 번째 사본은
검증기를 함께 가져야 합니다.

```
python tools/validators/validate_styleguide_button.py
```

높이, 좌우 여백, pill 반지름, 눌린 반지름, 다섯 스타일의 container·content 역할,
state layer 불투명도, focus ring, Connected Buttons variation의 gap·모서리 —
`theme.json`과 partial이 발행하는 값을 어댑터 CSS에서 다시 읽어 비교합니다. 한쪽만
고치면 빌드가 실패합니다.
