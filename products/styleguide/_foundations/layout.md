---
title: Layout
description: Scaffold, breakpoint, grid, pane — 그리고 WordPress의 content width가 별개의 축인 이유
order: 45
lang: ko
---

M3의 layout은 **scaffold**를 전제로 합니다. 화면을 안전 영역, bar, pane, rail로
나누고, 그 배치가 창 크기에 따라 바뀝니다.

## 용어부터

M3 용어집이 구분해두었고, 그 구분이 실제로 일을 합니다.

| 용어 | 뜻 |
|---|---|
| **Scaffold** | 화면 구성요소를 조립하는 기본 구조 |
| **Pane** | 컴포넌트를 담는 레이아웃 컨테이너. fixed · flexible · floating · semi-permanent |
| **Rail** | pane을 둘러싼 **주변 공간**. navigation rail·toolbar·pane control이 여기 앉음 |
| **Bar** | 페이지를 감싸 탐색을 돕는 것. app bar, bottom navigation bar |
| **Margin** | 화면 가장자리와 그 안의 요소 사이 |
| **Gap** | 컨테이너 안의 컴포넌트·요소 사이 |
| **Column** | pane 안의 세로 콘텐츠 블록 |
| **Safety region** | 상태 표시줄·제스처 바 같은 시스템 UI가 쓰는, 앱 바깥의 영역 |
| **Fold / Spacer** | 접히는 기기의 화면 경계와 두 pane 사이 공간 |
| **Drag handle** | pane 크기를 바꾸는 컴포넌트 |
| **Rulers** | 레이아웃 요소를 정렬하는 전역 정렬선 |

**rail은 navigation rail이 아닙니다.** rail은 자리이고 navigation rail은 거기
앉는 것입니다. 이 둘을 한 토큰으로 합치면 나중에 list-detail이나 supporting-pane을
서술할 수 없게 됩니다.

## Breakpoints

M3가 이름을 바꿨습니다 — 예전 **window size classes**가 지금은 **breakpoints**
입니다. 옛 이름이 남은 자료를 만나면 같은 것입니다.

| Breakpoint | 폭 (dp) | Panes | Navigation | 흔한 기기 |
|---|---|---|---|---|
{% for c in site.data.layout.breakpoints.classes -%}
| **{{ c.name }}** | {% if c.max %}{{ c.min }}–{{ c.max }}{% else %}{{ c.min }}+{% endif %} | {{ c.panes }} | {{ c.navigation }} | {{ c.devices }} |
{% endfor %}

**이 페이지가 지금 어느 구간인지**는 창을 좁혔다 넓혀보면 아래 그리드가 답합니다.

## 미디어쿼리는 토큰을 읽지 못합니다

레이아웃 토큰이 왜 생성되는지의 이유입니다. 짐작이 아니라 측정했습니다.

```
뷰포트 900px
@media (min-width: 600px)         적용됨
@media (min-width: var(--bp))     적용 안 됨
matchMedia('(min-width: var(--bp))').matches → false
```

게다가 `CSS.supports('(min-width: var(--bp))')`는 **true를 반환합니다.** 지원
여부를 그것으로 판단하면 안 됩니다.

그래서 브레이크포인트 값은 빌드 때 CSS 텍스트에 리터럴로 박히고, **같은 데이터에서
커스텀 프로퍼티로도 한 번 더** 나옵니다. 위 표와 실제 미디어쿼리가 한 소스에서
나오므로 갈라질 수 없고, 검증기가 둘이 여전히 같은지 확인합니다.

## Grid

<div class="sg-gridprobe ax-grid">
{%- for i in (1..12) %}
  <span class="sg-gridprobe__col"><span>{{ i }}</span></span>
{%- endfor %}
</div>

창을 좁히면 컬럼이 줄어듭니다. 12개를 모두 그려두었으므로 **compact에서는 4개씩
세 줄, medium에서는 8개와 4개**로 접힙니다.

| Breakpoint | Columns | Margin | Gap |
|---|---|---|---|
{% assign g = site.data.layout.grid -%}
{% for c in site.data.layout.breakpoints.classes -%}
| **{{ c.name }}** | {{ g.columns[c.name] }} | space{{ g.margin[c.name] }} | space{{ g.gap[c.name] }} |
{% endfor %}

컬럼 수와 그 주변 여백은 **M3가 발행하는 값이 아닙니다.** breakpoint 표에도
grid 문서에도 없어서, 이건 이 프로젝트의 선택이고 토큰도 `--ax-sys-*`입니다.
4/8/12는 오래된 Material 그리드이고, large 이상에서는 컬럼을 더 늘리는 대신
margin을 넓힙니다.

여백 값은 새로 만들지 않고 [Spacing]({{ '/foundations/spacing/' | relative_url }})
스케일에서 가져옵니다.

## Panes

M3가 발행하는 pane 폭은 하나입니다.

```
supporting pane   expanded 이상에서 고정 360dp
                  compact·medium에서는 focus pane 아래, 폭은 유연
```

그래서 `--md-sys-layout-pane-supporting`만 `--md-sys-*`이고, 나머지 pane 폭은
정해야 할 정책입니다.

canonical layout 셋이 이 구조 위에 있습니다.

**Feed** — 그리드 조합. compact에서 카드가 세로로 쌓이고, expanded 이상에서 컬럼이
늘어납니다.

**List-detail** — 두 pane. compact 1개, medium 1개(권장) 또는 2개, expanded 이상
2개. 단일 pane일 때만 상세 화면에 뒤로 가기가 있고, 두 pane일 때만 목록에 선택
상태가 있습니다.

**Supporting pane** — focus pane과 보조 pane. 부모-자식 관계라면 이게 아니라
list-detail입니다.

## Content width는 다른 축입니다

WordPress의 `contentSize`/`wideSize`는 breakpoint와 **다른 질문에 답합니다.**

```
breakpoint         rail·pane·grid를 어떻게 배치할 것인가
content / wide     글과 블록이 어느 폭까지 넓어질 것인가
```

테마의 값은 이렇습니다.

```
contentSize   {{ site.data.layout.content_widths.content }}px
wideSize      {{ site.data.layout.content_widths.wide }}px
```

**`wideSize`가 1280px이고 large breakpoint가 1200px입니다.** 가깝지만 같지
않습니다. 하나로 묶으면 이 우연이 규칙으로 굳어버립니다.

그래서 `.ax-content`와 `.ax-wide`는 미디어쿼리가 아니라 최대 폭에 답합니다.

```css
.ax-content {
  inline-size: min(
    calc(100% - 2 * var(--ax-sys-layout-margin)),
    var(--ax-sys-layout-content-max)
  );
  margin-inline: auto;
}
```

뺄셈이 하는 일이 있습니다 — 최대 폭에 못 미치는 좁은 화면에서는 요소가 가장자리에
붙는 대신 **margin을 돌려줍니다.**

## 아직 안 한 것

이 문서 사이트의 shell은 아직 scaffold로 재단장되지 않았습니다. 지금 내비게이션
레일은 고정 `15rem`이고 본문은 `68ch`인데, 둘 다 이 페이지가 서술하는 토큰이
아닙니다.

토큰과 primitive가 먼저 있어야 그 위에서 갈아끼울 수 있어서 순서를 이렇게
잡았습니다. 재단장하면 이 사이트 자체가 자기 문서의 첫 구현체가 됩니다.
