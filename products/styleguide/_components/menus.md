---
title: Menus
description: 두 네임스페이스 중 하나만 짓는 이유, 48과 44가 다른 상자인 이유, 그리고 메뉴가 dialog가 아닌 이유
kind: material
order: 60
lang: ko
---

M3의 메뉴는 **임시로 나타나는 동작 묶음**입니다. 화면에 계속 떠 있어야 하는 동작은
toolbar의 몫이고, 메뉴는 트리거가 열었다가 선택이나 dismiss로 사라집니다.

> **Vertical standard 행을 구현했습니다.** 구현과 표본은 플러그인의 스타일북 라우트
> `/social/stylebook/components/menus`에 있습니다 — 이 사이트의 페이지가 아니라 설치된
> 워드프레스가 서비스하는 경로라 여기서 링크하지 않습니다. 값은 전부
> [`_data/menu.yml`]({{ site.repository_url }}/blob/main/products/styleguide/_data/menu.yml)에서
> 오고, 그 파일은 토큰 표
> [`SOURCE-M3-MENUS-RAW.md`]({{ site.repository_url }}/blob/main/products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-MENUS-RAW.md)와
> M3의 산문 네 쪽을 출처로 적습니다. baseline·horizontal·vibrant·gap·submenu·density는
> 이 첫 slice에 넣지 않았고, 각각의 이유는 아래에 기록합니다.

## 그룹은 합성합니다

메뉴는 평평한 `items` 배열이 아니라 `Menu.Group`·`Menu.Item`·`Menu.Separator`를
조합합니다. 그룹은 label, 2dp block padding, 선택 범위를 소유합니다. 따라서 한 메뉴 안의
두 선택 그룹은 서로 다른 radio 또는 checkbox 세트가 될 수 있고, submenu와 추가 슬롯도
행을 데이터 키로 늘리지 않고 그 자리에 조합할 수 있습니다.

현재 스크롤 상자는 elevation 층과 분리된 menu list 전체입니다. 스크롤 메뉴와 submenu가
양립하는지, 양립한다면 어느 그룹이 스크롤을 소유하는지는 아직 런타임 실험 전이라 이
구현으로 결론 내리지 않습니다.

```jsx
<Menu label="Sort feed" trigger={ trigger }>
	<Menu.Group selectionMode="single" defaultValue="recent">
		<Menu.Item value="recent" label="Most recent" />
		<Menu.Item value="popular" label="Most discussed" />
	</Menu.Group>
	<Menu.Separator />
	<Menu.Group>
		<Menu.Item label="Copy link" />
	</Menu.Group>
</Menu>
```

`value`와 `onValueChange`를 주면 선택 상태를 제어할 수 있고, 없으면 Group이 `selected`가
붙은 첫 Item을 초기 상태로 쓴다. `multi` 그룹만 선택 뒤에도 메뉴를 열어 둡니다.

section label도 별도 행입니다. 바깥은 32dp와 좌우 4dp inset이고, 그 안 Content는 좌우
12dp·위 6dp·아래 0dp·요소 사이 8dp입니다. 선택적 20dp leading icon과 label-large 텍스트가 이
Content 층에 들어갑니다. `Menu.Group`의 `labelIcon`이 그 슬롯이고, `labelDivider`는 같은
label 행 안의 optional divider slot입니다. 이는 그룹 사이를 가르는 `Menu.Separator`와
의미가 다릅니다.

## 네임스페이스가 둘입니다

이 컴포넌트에서 가장 먼저 걸리는 것이 이것입니다. `md.comp.menu`와 `md.comp.menus`는
같은 것의 두 철자가 아니라 **서로 다른 두 행**입니다.

| 변형 | 네임스페이스 | 상태 | 비고 |
|---|---|---|---|
{% for v in site.data.menu.variants -%}
| {{ v[1].official_name | default: v[0] }} | `{{ v[1].namespace }}` | `{{ v[1].status }}` | {% if v[1].live_token_count %}살아 있는 토큰 {{ v[1].live_token_count }}개{% elsif v[1].token_count %}토큰 {{ v[1].token_count }}개{% else %}{{ v[1].availability }}{% endif %} |
{% endfor %}

### baseline을 안 짓는 이유 — 레거시라서가 아니라 불완전해서

baseline은 deprecated가 아닙니다. M3는 "still available to use"라고 쓰고, 최신 shape·색
스타일·선택 상태·모션이 없다고만 덧붙입니다. 그런데 **살아 있는 토큰을 세어 보면
{{ site.data.menu.variants.baseline.live_token_count }}개**입니다:

{% for t in site.data.menu.variants.baseline.live_tokens -%}
- `{{ t }}`
{% endfor %}

전부 컨테이너입니다. item이 없습니다 — `md.comp.menu.list-item.*` 일가가 통째로
deprecated이고, 사유가 전부 "use standalone list component tokens instead"입니다. 그러니
baseline 메뉴 아이템을 deprecated 아닌 토큰으로 조립할 길이 **없습니다**. 짓는다면
폐기된 토큰을 소비하거나 List에서 추론해야 합니다.

호환 때문에 남길 이유도 없습니다. Button의 `is-style-outlined`는 **저장된 콘텐츠가 그
클래스를 들고 있어서** 남은 shim인데, 이 프로젝트에는 baseline 메뉴를 든 저장물이
없습니다. 갚을 부채가 없으면 레거시 행을 짓지 않습니다.

다시 열 조건도 적어 둡니다 — {{ site.data.menu.variants.baseline.reopen_when }}

### horizontal은 발행되었는데 아무도 설명하지 않습니다

토큰 표에 `md.comp.menus.horizontal.*`가
{{ site.data.menu.variants.horizontal.token_count }}개 있습니다. 컨테이너 shape가
`corner.full`이고, icon-only 하위 변형과 자기 item 간격·모양까지 있습니다.

그리고 그걸 말하는 산문이 없습니다. overview·specs·guidelines·accessibility 어디에도
없고, Material Web 문서에도, Compose에도 없습니다. 공식 명칭이 "**Vertical** menus"로
축을 명시해 붙은 것 자체가 다른 축의 자리를 비워 둔 모양입니다.

디자인 킷에도 **없습니다.** 커넥터로 Menu 페이지를 열어보면 섹션이 둘뿐이고(Baseline과
expressive), expressive 쪽은 Configurations · Menu(Theme × Groups) · Building blocks입니다.
horizontal 프레임이 어디에도 없습니다.

그러니 이것은 **그림도 산문도 없이 토큰 데이터베이스에만 있는 일가**입니다. 구현하지 않고
`published_without_prose`로 적어 둡니다.

## 48과 44는 충돌이 아니라 다른 상자입니다

아이템 높이 토큰은 **{{ site.data.menu.item.container_height }}**입니다. 그런데 접근성
절은 타깃을 48×48dp 이상으로 요구하고, specs의 Measurements 그림에는 48이 적혀 있습니다.
여기서 "둘 중 뭐가 맞나"로 가면 틀립니다. 둘 다 맞고, **재는 상자가 다릅니다.**

```
{{ site.data.menu.item.slot_height }}  slot          포인터가 닿는 칸. 접근성 타깃.
 -{{ site.data.menu.item.inset_block }}  inset_block
 -{{ site.data.menu.item.inset_block }}  inset_block
---
{{ site.data.menu.item.container_height }}  container     state layer · 모서리 · selected 색이 붙는 상자
```

가로도 같은 모델입니다 — 좌우로 {{ site.data.menu.item.inset_inline }}dp씩 들어옵니다.

출처가 다르다는 것도 같이 적습니다. {{ site.data.menu.item.container_height }}는 토큰이고,
{{ site.data.menu.item.slot_height }}와 inset 둘은 **{{ site.data.menu.item.geometry_source }}**입니다.
specs의 Measurements가 표가 아니라 그림이라 표에는 없습니다.

이게 구현을 바꿉니다. 아이템을 `44`에 margin으로 지으면 타깃이 전부 44가 되고, 화면상
똑같아 보입니다. 슬롯이 48이고 그 안에 44가 inset으로 들어가야 합니다.

검증은 **값 셋이 아니라 산수**에 겁니다:

```
{{ site.data.menu.item.invariant }}
```

어느 하나가 혼자 움직이면 깨집니다. 값 하나를 핀으로 박으면 그 값이 틀렸을 때 결함이
계약으로 굳습니다 — scaffold의 rail에서 한 번 그랬습니다.

### 토큰이 없는 값은 comp 슬롯에 리터럴로 들어갑니다

이 저장소가 이미 쓰는 규칙은 **표의 형태를 따라가는 것**입니다. 표가
`-> md.sys.measurement.space150`이면 comp 슬롯이 `var( --md-sys-measurement-space150 )`을
읽고, 표가 `{"value":16,"unit":"DIPS"}`처럼 리터럴이면 슬롯도 리터럴입니다. 표에 아예
없으면 — Badge의 maximum-character 폭과 inline padding이 그 경우인데 — 역시 리터럴로,
`--md-comp-*` 슬롯에 들어갑니다.

{{ site.data.menu.item.inset_block }}와 {{ site.data.menu.item.inset_inline }}도 그쪽입니다.
`var( --md-sys-measurement-space25 )`로 쓰면 **M3가 이 inset을 스페이싱 스케일 위에
올려놨다**는 주장을 하게 되는데, M3는 그런 적이 없습니다. 값이 같은 것은 일치이지 발행된
참조가 아닙니다. 그리고 참조로 쓰면 테마가 스케일을 조정할 때 **그림에서 잰 값이 따라
움직입니다.**

```css
.ax-menu__item {
	/* Measured; no token publishes either. A literal rather than
	 * var( --md-sys-measurement-space25 ): equal values, not a reference. */
	--md-comp-menu-item-slot-height: {{ site.data.menu.item.slot_height }}px;
	--md-comp-menu-item-inset-block: {{ site.data.menu.item.inset_block }}px;
	--md-comp-menu-item-inset-inline: {{ site.data.menu.item.inset_inline }}px;
	/* md.comp.menus.menu-item.height */
	--md-comp-menu-item-container-height: {{ site.data.menu.item.container_height }}px;
}
```

섞이지 않게 `menu.yml`이 어느 것이 토큰 없는 값인지 이름으로 적고
(`item.unpublished`), 검증기가 양방향으로 잡습니다 — 토큰 없는 숫자가 목록 밖에 생기면
실패하고, 토큰이 있는 값을 목록에 넣어도 실패합니다.

### 칠해지는 상자의 이름은 State Layer입니다

디자인 킷의 레이어 트리가 그 읽기를 이름으로 확인해줍니다:

```
Menu-item     h:{{ site.data.menu.item.slot_height }} hug   paddingBlock:{{ site.data.menu.item.inset_block }}   paddingInline:{{ site.data.menu.item.inset_inline }}
  Content     h:hug                 cornerRadius:{{ site.data.menu.item.shape }}
    State Layer  h:{{ site.data.menu.item.container_height }}  paddingBlock:{{ site.data.menu.item.state_layer_padding_block }}  paddingInline:{{ site.data.menu.item.state_layer_padding_inline }}
```

가로가 여기서 닫힙니다:

```
{{ site.data.menu.item.leading_space_composition }}
```

{{ site.data.menu.item.inset_inline }} + {{ site.data.menu.item.state_layer_padding_inline }} =
{{ site.data.menu.item.leading_space }}. 발행된 leading-space는 **슬롯 가장자리에서** 재는
값이고, 칠해지는 상자는 거기서 {{ site.data.menu.item.inset_inline }} 들어와 자기 패딩
{{ site.data.menu.item.state_layer_padding_inline }}를 갖습니다. 측정값과 토큰이 만나는
유일한 자리라 이것도 핀으로 박았습니다.

가로 전체를 끝에서 끝까지 복원하면 하나가 더 결정됩니다:

```
{{ site.data.menu.container.inline_size_floor }}
 -{{ site.data.menu.item.inset_inline }} x2   inset
 -{{ site.data.menu.item.state_layer_padding_inline }} x2  state layer padding
 -{{ site.data.menu.item.leading_icon_size }} -{{ site.data.menu.item.trailing_icon_size }}  leading · trailing icon
 -{{ site.data.menu.item.state_layer_between_space }} x2   between
 ----
 {{ site.data.menu.item.content_inline_size }}   = 킷이 표시한 텍스트 블록 폭
```

요소 사이 간격을 토큰의 12로 두면 112가 나옵니다. 킷은 {{ site.data.menu.item.content_inline_size }}을
표시하므로, **킷의 {{ site.data.menu.item.state_layer_between_space }}는 자기 폭 산수로
뒷받침됩니다.** 이 기록은 처음에 그 자리에서 토큰을 따랐고, 되돌렸습니다 — 세로 패딩에서는
산수가 닫히는 쪽을 따라놓고 가로에서 반대로 한 것이기도 했습니다.

### 발행되었지만 가리키는 곳을 모르는 값

`menu-item.top-space`와 `bottom-space`가 각각 {{ site.data.menu.item.top_space }}로
발행되어 있습니다. 그런데 {{ site.data.menu.item.container_height }} − 8 − 8 = 28이고,
label-large의 줄 상자는 20입니다. 킷은 대신 {{ site.data.menu.item.state_layer_padding_block }}을
재고, {{ site.data.menu.item.container_height }} − 12 − 12 = 20으로 정확히 맞습니다.

유력한 가설은 토큰의 8이 **supporting text가 붙어 커진 아이템**의 것이라는 쪽입니다 —
20 + 16 + 8 + 8 = 52도 똑같이 닫히니까요. 킷의 텍스트 블록이 그 두 줄을 실제로 hug합니다:

```
{{ site.data.menu.item.content_inline_size }} × {{ site.data.menu.item.content_block_size_one_line }}   라벨만
{{ site.data.menu.item.content_inline_size }} × {{ site.data.menu.item.content_block_size_with_supporting_text }}   supporting text 포함 ({{ site.data.menu.item.content_block_size_one_line }} + 16)
```

그런데 **킷에서는 52가 나올 수 없습니다.** supporting text는 숨은 레이어이고, 두 줄짜리
variant가 없습니다 — `Menu item/Standard`의 상태 심볼 11개가 전부 48입니다. 칠해지는 상자의
세로 패딩 12는 오토레이아웃 고정 속성이라 내용이 36이 되어도 그대로이고, 그러면 상자 60에
아이템 64입니다.

그래서 가설은 **그것을 그렸을 유일한 소스가 지지하지 않습니다.** 토큰의 8은 킷에도 산문에도
자리가 없는 채로 남습니다. 한 줄 아이템은 잰 값 12로 짓습니다.

이 기록은 한 번 "상자가 44로 고정이라 두 줄을 안 그린다"고 적었습니다. 고정이 아니라 **두
줄 variant가 없는 것**이고, 그때 근거는 옆 행에서 읽은 크기 배지였습니다.

## 구조는 리스트, 값은 메뉴

M3가 메뉴의 내장 item 토큰을 걷어낸 사유가 "use standalone **list** component tokens
instead"이고, Material Web은 메뉴가 `md-list`를 조립하며 `md-menu-item`이
`md-list-item`을 상속합니다. 그러니 메뉴 컨테이너는 역할상 목록입니다.

그런데 값은 List 것이 아닙니다. 셋을 재봤고 셋 다 다릅니다:

| 속성 | Menu | List |
|---|---|---|
{% for d in site.data.menu.structure.divergence -%}
| {{ d.property }} | {{ d.menu }} | {{ d.list }} |
{% endfor %}

그래서 Menu는 [`list.yml`]({{ site.repository_url }}/blob/main/products/styleguide/_data/list.yml)을
소비하지 않고 자기 item slot을 발행합니다. `md.comp.menus.menu-item.*`는 deprecated가
아닙니다 — 폐기된 것은 **옛 내장 일가**이고, expressive 일가가 그 자리를 대신했습니다.

## 호스트는 dialog가 아닙니다

[Surface]({{ '/components/surface/' | relative_url }})는 `<dialog>` 호스트를 소유하고,
그 modal 축이 scrim·스크롤 락·inert를 끌고 옵니다. 메뉴는 anchored·non-modal·light-dismiss
이고, non-modal `dialog.show()`에는 light dismiss가 없습니다. 그러니 Surface를 재사용하지
않는 것이 생략이 아니라 **다른 계약**입니다.

선례는 저장소 안에 있습니다 —
[`tooltip.js`]({{ site.repository_url }}/blob/main/products/wordpress/plugins/axismundi-dialogs/assets/tooltip.js):

{% for n in site.data.menu.host.precedent_notes -%}
- {{ n }}
{% endfor %}

세 번째가 메뉴에 그대로 걸립니다. **top layer는 join한 순서로 쌓이므로**, dialog 안에서
여는 메뉴는 그 dialog보다 뒤에 join해야 합니다.

위치는 {{ site.data.menu.host.positioning }}

## 그룹 — 토큰은 있고 플랫폼은 아닙니다

`md.comp.menus.gap`이 {{ site.data.menu.grouping.gap.gap }}dp로, group padding과 group
shape까지 함께 발행되어 있습니다. 그리고 guidelines가 이렇게 씁니다:

> {{ site.data.menu.prose_sources.groups_on_web.statement }}

그래서 gap은 기록하고 짓지 않습니다. **발행된 토큰이 발행된 플랫폼은 아닙니다.** web의
그룹 구분은 divider이고, divider는 Divider 컴포넌트가 소유합니다 — 이 네임스페이스의
중첩 divider 토큰은 전부 독립 `md.comp.divider.*`로 가라고 deprecated되었습니다.
(`_data/divider.yml`은 있고 스타일북 페이지는 아직 없습니다.)

## 스크롤과 양립하지 않는 것

세 소스가 독립적으로 같은 제약을 가리킵니다. 늦게 발견하면 비싼 종류라 여기 적습니다.

{% for m in site.data.menu.mutual_exclusions -%}
- **{{ m.subject }}** — {{ m.statement }}
{% endfor %}

세 번째가 특히 쓸모 있습니다. 우리 테마가 코어 마크업 위에서 같은 결론에 **따로**
도달했고, `blocks.navigation-submenu.css`가 그 이유를 주석으로 들고 있습니다. 중첩
팝오버는 이 저장소에서 이미 돌아가는 모양이라, 서브메뉴를 지을 때 참조할 자리가 있습니다.

서브메뉴 자체는 web에 있습니다(`md-sub-menu`). Compose에는 없고, M3가 직접 그렇게
씁니다. 이 구현은 native popover top layer를 써서 스크롤 목록 안의 submenu도 클리핑되지
않음을 VQA로 확인했습니다. 따라서 in-flow 구현의 스크롤 제약은 여기에는 적용되지 않습니다.

### 어디에 여는가 — 가로는 합의, 세로는 유도

M3는 숫자를 발행하지 않습니다. 가로는 네 소스가 같은 말을 합니다:

{% for a in site.data.menu.submenu.placement.inline.agreed_by -%}
- **{{ a.source }}** — {{ a.says }}
{% endfor %}

그래서 간격 {{ site.data.menu.submenu.placement.inline.offset }}, 부모 메뉴의
{{ site.data.menu.submenu.placement.inline.anchor }}에 붙입니다.

세로는 **아직 확정이 아닙니다.** 가로와 달리 합의가 없고, 구현 중인 우리 쪽 정책입니다 —
`{{ site.data.menu.submenu.placement.block.status }}`.

규칙은 {{ site.data.menu.submenu.placement.block.rule }}이고, 그러면:

```
부모 painted top  = slot top + inset_block
서브 painted top  = container top + group_padding_block + inset_block
→ {{ site.data.menu.submenu.placement.block.derivation }}
```

**열린 질문이 하나 있어서 확정하지 않습니다.** Material Web의 `anchor-corner: start-end`가
무엇을 앵커로 삼는지입니다 — 부모 **컨테이너**면 위 유도가 설 수 있지만, 부모 **item**이면
(그리고 `md-sub-menu`가 트리거를 `item` 슬롯으로 받는 걸 보면 그쪽이 유력합니다) 서브메뉴
컨테이너의 top이 item의 top에 맞고 오프셋은 `-2`가 아니라 **0**입니다.

코어의 `top: -1px`는 **이 질문의 근거가 아닙니다.** 주석이 `// Border width`라고 말하는 그대로
자기 border 보정이고, 우리에게 없는 가장자리입니다. 구현들이 컨테이너의 선행 가장자리를
보정한다는 것만 보여줄 뿐 group padding에 대해서는 아무 말도 하지 않습니다.

확정은 어댑터가 생긴 뒤입니다 — 부모 item의 칠해진 state layer와 서브메뉴 첫 state layer의
`top`을 재서 차이를 봅니다. **우리 기하가 우리 자신에 대해 답하는 것**이고, 위 두 소스는
그걸 할 수 없습니다.

**hover bridge는 적어만 뒀습니다.** 코어는 서브메뉴에 폭 0.5em의 투명한 `::before`를 붙여,
포인터가 행 사이를 대각선으로 지날 때 메뉴를 벗어나 닫히지 않게 합니다. M3는 언급하지
않지만, 맞닿은 서브메뉴여도 대각선에서는 포인터를 놓칩니다. 우리 스택이 이미 쓰는
기법이라 출처와 함께 남깁니다.

작은 화면에서는 코어가 nested submenu를 아코디언으로 접습니다. M3도 "서브메뉴는 큰 화면용"
이라고 쓰고 adaptive guidance를 가리킵니다 — **따로 도달한 같은 결론**입니다.

## 키보드

| 키 | 동작 |
|---|---|
{% for k in site.data.menu.keyboard -%}
| `{{ k.keys }}` | {{ k.action }} |
{% endfor %}

여는 순간의 포커스는 **첫 메뉴 아이템**입니다. 비활성 아이템은 포커스를 받되 선택되지
않고, divider와 gap은 포커스를 받지 않습니다.

## 지금 안 만든 것

추측으로 채우지 말라고 남깁니다.

{% for n in site.data.menu.not_published -%}
- **{{ n[1].what }}**{% if n[1].note %} — {{ n[1].note }}{% endif %}
{% endfor %}

density만 사정이 다릅니다. 킷이 값을 하나 갖고 있는데 — 한 단계당 아이템마다
{{ site.data.menu.design_kit.density.step }}dp, building block에서 73·65·57, 12개짜리
baseline 메뉴에서 688·592·496 — **baseline 행에만** 있습니다. expressive 섹션에는 density
변형이 아예 없습니다. 우리가 짓지 않는 행의 값이라 그렇게 적어 두었습니다. 다른 행에
가져다 쓰지 않기 위해서입니다.

## 아무도 발행하지 않아 우리가 정한 것

폭과 최대 높이입니다. M3는 둘 다 발행하지 않고, 랩의 감사가 min/max 폭의 권위로 지목한
것은 랩 자신의 스타일시트입니다.

**최대 높이는 트리거가 남긴 뷰포트 공간에 묶습니다.** M3가 실제로 쓴 제약은 그것뿐입니다
— placement 절은 창 가장자리에 관한 것이고, 잘리느니 자리를 옮기라고 합니다. 발행된
어디에도 "몇 개까지"는 없습니다.

아이템 갯수 상한은 **계약만 적어두고 켜지 않았습니다**. 숫자를 고르면 근거 없는 상수가
하나 생기고, 그걸 핀으로 박으면 결함이 계약으로 굳습니다. 뷰포트 상한 아래에서는 갯수가
상수가 아니라 결과입니다. 필요해지면 높이 공식으로 켭니다:

```
{{ site.data.menu.container.block_size_formula }}
```

행은 아이템(48)이거나 section label(32)입니다. 킷의 **여섯 인스턴스**에서 확인했습니다 —
196(4개), 228(라벨+4개), 292(6개), 324(라벨+6개), 394(두 그룹), 592(세 그룹). 검증기가 전부
재구성해서 대조하므로, 슬롯 높이든 라벨 높이든 그룹 패딩이든 움직이면 깨집니다.

**폭은 {{ site.data.menu.container.inline_size_floor }}을 바닥으로** 쓰고 내용에 따라
자라되 **창에 물립니다.** 한 킷의 한 인스턴스는 바닥의 증거이지 고정폭의 증거가 아니고,
가장 긴 라벨이 안 들어가는 메뉴가 넓은 메뉴보다 나쁩니다. 창 clamp는 우리 네비게이션
서브메뉴에서 가져왔습니다 — 높이를 창에 묶어놓고 폭을 안 묶으면 여전히 넘칩니다.

형제 구현이 다른 답을 들고 있다는 것도 적어 뒀습니다. 블록 쪽은
`min {{ site.data.menu.project_policy.inline_size.sibling_precedent.minimum }}` /
`max {{ site.data.menu.project_policy.inline_size.sibling_precedent.maximum }}`입니다. 맞추지
않았습니다 — 블록의 수치는 짧은 네비게이션 라벨을 보고 고른 것이고 208은 메뉴를 그린
값입니다. 다만 **누가 정한 차이**로 남겨서, 나중에 공통 답을 낼 때 두 숫자가 다 보이게
했습니다.

판단이 남은 것 둘:

- **compact에서 bottom sheet로 바꿀지.** M3는 "Consider"라고 씁니다. Dialog의 full-screen
  전환은 M3가 의무로 적어서 의무였는데 여기는 아닙니다. 자동 전환인지 호출자의 선택인지는
  읽기가 아니라 결정입니다.
- **vibrant를 낼지.** tertiary 기반이고 "should be used sparingly"입니다. 토큰은 발행되어
  있고 경로는 가산적이라, 요구가 생길 때 엽니다.

## 랩은 증거이지 제품이 아닙니다

[`modules/menu`]({{ site.repository_url }}/tree/main/products/reference-implementations/axismundi-lab/modules/menu)가
이미 같은 경계를 그어 두었습니다 — Menu는 item 의미와 저작 구조를 소유하고, popover
제공자가 anchoring·개폐·바깥 dismiss·Escape·포커스 복원·배치를 소유합니다. 지금
이 기록이 지키는 경계가 그것입니다.

다만 랩의 측정 감사는 메뉴 min/max 폭의 권위를 자기 baseline primitive라고 적습니다.
그 권위는 랩의 `components.css`이지 M3가 아닙니다. 그래서 폭은 위의 "안 만든 것"에
남아 있습니다.
