---
title: FABs
description: 색이 아니라 크기가 변형인 이유, 표가 없는 컴포넌트를 짓지 않는 이유, 그리고 웹의 FAB menu가 메뉴인 이유
kind: material
order: 70
lang: ko
---

M3는 FAB를 **세 컴포넌트로 나눠 발행합니다** — FAB, extended FAB, FAB menu. 이 페이지는
셋을 한 레코드에 담습니다. 축이 같기 때문입니다: 크기
{{ site.data.fab.sizes.fab.container_height }}·{{ site.data.fab.sizes.medium.container_height }}·{{ site.data.fab.sizes.large.container_height }}dp가
셋을 관통하고, extended FAB는 FAB와 서로 변형되며, FAB menu는 FAB에서 열립니다.

> **아직 짓지 않았습니다.** 이 페이지는 구현 기록이 아니라 구현 전 기록입니다. 값은 전부
> [`_data/fab.yml`]({{ site.repository_url }}/blob/main/products/styleguide/_data/fab.yml)에서
> 오고, 그 파일은 토큰 표
> [`SOURCE-M3-FLOATING-ACTION-BUTTON-RAW.md`]({{ site.repository_url }}/blob/main/products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-FLOATING-ACTION-BUTTON-RAW.md)·[`SOURCE-M3-FAB-MENU-RAW.md`]({{ site.repository_url }}/blob/main/products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-FAB-MENU-RAW.md)와
> M3 산문 열두 쪽을 출처로 적습니다. 이번 slice는 {{ site.data.fab.project_policy.slice }}

## 색은 변형이 아닙니다

M3의 문장이 분류를 정합니다 — "{{ site.data.fab.prose_sources.variants_are_sizes.statement }}"
그래서 토큰표가 두 쪽으로 갈립니다. **크기 세트는 색 토큰을 들지 않고, 색 세트는 크기
토큰을 들지 않습니다.**

| 크기 | container | icon | corner |
|---|---|---|---|
{% assign size_names = "fab,medium,large,small" | split: "," -%}
{% for name in size_names -%}
{%- assign s = site.data.fab.sizes[name] -%}
| {{ name }} | {{ s.container_height }} | {{ s.icon_size }} | `{{ s.container_shape }}` |
{% endfor %}

`small`은 폐기된 게 아니라 **권장되지 않습니다** — 토큰이 살아 있어서 40dp FAB는 읽히는
M3입니다. 그걸 기록해 두지 않으면 나중에 실수로 보입니다.

패딩은 표에 없습니다. M3는 측정 이미지로만 발행하고 이 리포에는 그 이미지가 없습니다.
그래서 레코드는 값을 베끼지 않고 **산수를 적습니다** —
`{{ site.data.fab.sizes.padding_is_derived.expression }}`. 정사각 컨테이너에 아이콘을
가운데 두면 같은 수가 나오고, 피그마 측정이 이와 어긋나면 측정이 이기고 이 항목이 불일치가
됩니다.

## 한 표 안에 두 세대가 있습니다

살아있는 `md.comp.fab` 토큰은 {{ site.data.fab.token_families.live_total }}개이고,
폐기된 것이 {{ site.data.fab.token_families.deprecated_total }}개입니다. 살아있는 쪽이
둘로 갈리는데, **상태 이름이 세대를 그대로 드러냅니다.**

| 세트 | 토큰 수 | 상태 이름 | 자기 크기 토큰 | `lowered` |
|---|---|---|---|---|
| 색 세트 6개 | {{ site.data.fab.token_families.expressive_colour_sets.token_count_each }}씩 | {{ site.data.fab.token_families.expressive_colour_sets.state_names | join: " · " }} | {{ site.data.fab.token_families.expressive_colour_sets.carries_size_tokens }} | {{ site.data.fab.token_families.expressive_colour_sets.carries_lowered }} |
{% for pair in site.data.fab.token_families.baseline_shaped_colour_sets.sets -%}
| `{{ pair[0] }}` | {{ pair[1] }} | {{ site.data.fab.token_families.baseline_shaped_colour_sets.state_names | join: " · " }} | {{ site.data.fab.token_families.baseline_shaped_colour_sets.carries_size_tokens }} | {{ site.data.fab.token_families.baseline_shaped_colour_sets.carries_lowered }} |
{% endfor %}

`{{ site.data.fab.token_families.arithmetic.expression }}` =
{{ site.data.fab.token_families.arithmetic.equals }}. 산수가 닫히기 때문에 검증기는 토큰
목록을 얼려 두지 않고 **합**을 봅니다 — 세트가 개명되면 조용히 줄어드는 대신 실패합니다.

여기서 나오는 결론이 하나 있습니다. **`lowered`는 권장되지 않는 두 세트에만 남았습니다.**
권장되는 색으로 lowered FAB를 만들면 발행된 elevation 세트가 없습니다. 그래서 navigation
rail이 적어 둔 "resting elevation 0"은 토큰 읽기가 아니라 **프로젝트 결정**입니다.

## 상태는 고도를 바꿉니다

enabled가 `{{ site.data.fab.states.enabled.container_elevation }}`,
hover가 `{{ site.data.fab.states.hovered.container_elevation }}`,
focus와 pressed가 다시 level3으로 돌아옵니다. 비기본 색을 쓰면
**state layer 색은 컨테이너가 아니라 아이콘 색을 따릅니다** — 표가 뒷받침합니다:
`{{ site.data.fab.colour_styles.state_layer_follows_the_icon.token_evidence }}`.

고도 소유권은 Menu와 같습니다. 이 테마는 `--md-sys-elevation-level*`을 **의도적으로 정의하지
않습니다.** 따라서 FAB도 프론트앱의 `<Elevation level>` 프리미티브를 쓰고,
`--md-sys-elevation-shadow-level*`에 손을 대지 않습니다. hover에서 level3이 level4가 되는 건
그림자를 하나 더 얹는 게 아니라 프리미티브의 레벨을 바꾸는 일입니다.

**disabled는 발행되지 않습니다.** M3의 문장은
"{{ site.data.fab.prose_sources.do_not_disable.statement }}"이고, 그래서 구현은
`disabled` prop을 받지 않습니다 — 동작이 불가능하면 FAB가 사라집니다. 흐릿한 FAB는
기본값으로라도 생기면 안 됩니다.

웹에서는 tooltip이 컴포넌트의 일부입니다. 라벨이 보이지 않는 버튼이라 hover와 focus에서
아이콘의 텍스트 라벨을 띄우라고 지시받습니다. 호출하는 쪽이 기억해서 붙이는 것이 아니라
FAB가 들고 있어야 하는 부분입니다. 반대로 extended FAB는
tooltip이 필요 없습니다 — 라벨이 이미 보입니다.

## Extended FAB: 표가 없다는 사실이 가장 중요합니다

`{{ site.data.fab.meta.namespace_without_a_table }}`는 이 리포 어디에도 정의되지 않습니다.
수집 기록이 실패로 남아 있고, M3 자신의 리소스 표도 Web: Expressive를
**{{ site.data.fab.variants.extended_fab.web_expressive_status }}**로 적습니다. 산문이 주는
것은 높이 세 개뿐입니다 —
small {{ site.data.fab.extended_fab.sizes_m3_expressive.small }},
medium {{ site.data.fab.extended_fab.sizes_m3_expressive.medium }},
large {{ site.data.fab.extended_fab.sizes_m3_expressive.large }}dp. corner·padding·icon
size·medium과 large의 타입 스케일은 발행되지 않았습니다.

막힌 건 피그마가 아니라 **다른 생성 소스**로 풀렸습니다. M3가 extended FAB의 Expressive를
Available로 적은 곳이 Compose이고, 그 토큰 파일은 같은 디자인 시스템 DB에서 생성됩니다
(`{{ site.data.fab.extended_fab.measured_from_compose.why_this_counts.header }}`,
`{{ site.data.fab.extended_fab.measured_from_compose.why_this_counts.version }}`).

| | small | medium | large |
|---|---|---|---|
{% assign c = site.data.fab.extended_fab.measured_from_compose -%}
| height · min width | {{ c.small.container_height }} | {{ c.medium.container_height }} | {{ c.large.container_height }} |
| icon | {{ c.small.icon_size }} | {{ c.medium.icon_size }} | {{ c.large.icon_size }} |
| 좌우 | {{ c.small.leading_space }} | {{ c.medium.leading_space }} | {{ c.large.leading_space }} |
| 상하 | {{ c.small.leading_space }} | {{ c.medium.block_space }} | {{ c.large.block_space }} |
| 아이콘–라벨 | {{ c.small.icon_label_space }} | {{ c.medium.icon_label_space }} | {{ c.large.icon_label_space }} |
| (토큰이 말하는 값) | — | {{ c.medium.icon_label_space_token_says }} | {{ c.large.icon_label_space_token_says }} |
| 타입 | title-medium | title-large | headline-small |

아이콘–라벨 줄이 어긋나는 건 오타가 아닙니다. Compose가 **자기 토큰 둘을 틀렸다고 주석에
적고** 다른 값으로 그리는데, **디자인 키트도 같은 값을 그립니다.** 둘 대 하나라서 레코드는
그린 값을 택하고 토큰 값을 옆에 남깁니다.

large에서 상하와 좌우가 다른 것도 같은 이유로 남깁니다. 30은 36dp 아이콘을 96dp 상자
가운데 두는 수이고, 28은 토큰이 발행한 leading space입니다. small과 medium이 한 수로
보이는 건 가운데 두는 산수가 우연히 같은 값에 떨어지기 때문입니다.

산문의 "padding이 줄었다"도 같이 풀립니다. 같은 파일에 baseline의 text padding이 20dp로
남아 있고 expressive small의 좌우는 16입니다 — **20에서 16으로** 준 것이지, baseline의
16dp 아래로 내려간 게 아니었습니다.

최소 너비도 다릅니다. baseline은 80dp 고정이었지만 expressive는
**{{ c.minimum_width_is_the_height }}**

large 아이콘은 한 번 틀리게 적었다가 키트를 읽고 고쳤습니다. Compose의 토큰 파일은
{{ c.large.icon_size_compose_token_says }}라고 하는데 키트와 `md.comp.fab.large.icon.size`는
둘 다 {{ c.large.icon_size }}입니다. 그래서 접히고 펴질 때 바뀌는 건 모양과 너비이고,
**아이콘 크기는 그대로입니다.** 한 소스만 보고 적었던 결론이라 레코드에 정정 사실까지
남겨 뒀습니다.

랩이 이미 들고 있는 것도 baseline 행입니다 —
{{ site.data.fab.extended_fab.no_token_table.what_the_lab_already_holds.which_row_this_is }}
medium과 large는 {{ site.data.fab.extended_fab.no_token_table.what_the_lab_already_holds.medium_and_large }}

측정 가능한 숫자는 baseline 행에만 있습니다 — 높이
{{ site.data.fab.extended_fab.baseline.container_height }},
너비 {{ site.data.fab.extended_fab.baseline.container_width }},
corner {{ site.data.fab.extended_fab.baseline.container_shape }},
icon {{ site.data.fab.extended_fab.baseline.icon_size }},
padding {{ site.data.fab.extended_fab.baseline.padding }}. 그리고 그 행은 M3가 쓰지 말라고
하는 행입니다. 확인 가능해서 적었을 뿐, 목표가 아닙니다.

라벨 규칙에는 비대칭이 하나 있습니다. **아이콘만 있는 extended FAB는 금지되고, 라벨만 있는
것은 허용됩니다.** RTL에서는 아이콘이 라벨 뒤로 갑니다.

## 웹의 FAB menu는 메뉴입니다

이건 토큰표를 먼저 읽으면 **반대로 결론 납니다.** `md.comp.fab-menu`는 높이
{{ site.data.fab.fab_menu.android_shape.menu_item.container_height }}dp,
corner `{{ site.data.fab.fab_menu.android_shape.menu_item.container_shape }}`,
`{{ site.data.fab.fab_menu.android_shape.menu_item.label_text }}` 라벨, 항목마다 자기
elevation을 발행합니다. 리스트가 아니라 떠 있는 버튼들입니다. 그런데 그건 Android와
Expressive의 모양이고, **웹은 세 쪽에서 세 번 다른 말을 듣습니다**:

{% for citation in site.data.fab.fab_menu.on_web_it_is_a_fab_and_a_menu.evidence -%}
{%- assign key = citation | remove: "prose_sources." -%}
{%- assign entry = site.data.fab.prose_sources[key] %}
- "{{ entry.statement }}" — [{{ entry.source | split: "/" | last }}]({{ entry.source }})
{% endfor %}

그래서 웹에서 FAB menu를 짓는다는 것은 FAB가 우리가 이미 가진 메뉴를 여는 일이고, 권장
간격은 {{ site.data.fab.fab_menu.on_web_it_is_a_fab_and_a_menu.gap_between_fab_and_menu }}dp입니다.
다만 M3가 가리키는 것은 **baseline** 메뉴 컴포넌트이고 우리가 구현한 것은 vertical 행입니다 —
우리가 열 메뉴는 M3가 이름 부르는 메뉴가 아니라 우리가 가진 메뉴입니다.

플랫폼과 무관하게 발행된 동작은 남깁니다: 항목
{{ site.data.fab.fab_menu.behaviour_published_for_every_platform.item_count.min }}–{{ site.data.fab.fab_menu.behaviour_published_for_every_platform.item_count.max }}개,
FAB에서만 열림, close button이 FAB 자리를 차지하고 초기 포커스가 거기 머무름, 항목은 close
button **뒤로** 스크롤.

rail과의 충돌은 **창 크기에서 풀립니다.** M3가 금지하는 것은 "rail과 짝인 FAB에서 FAB
menu 열기"인데, compact 창에는 rail이 없습니다 — nav bar가 있고 FAB는 그것과 따로
움직입니다. 측정이 뒷받침합니다:
{{ site.data.fab.fab_menu.our_shell_conflict.measured }}

그래서 표현 축을 **{{ site.data.fab.fab_menu.presentation_axis.gate }}**로 잡습니다 —
compact는 {{ site.data.fab.fab_menu.presentation_axis.compact }},
expanded는 {{ site.data.fab.fab_menu.presentation_axis.expanded }}. PWA의 display mode는
게이트가 아닙니다: 같은 폰의 브라우저 탭과 설치본이 이유 없이 달라집니다.

compact에 expressive stack을 쓰는 것은 **{{ site.data.fab.fab_menu.deviation_on_compact.status }}**로
적습니다. M3의 웹 지침에서도, 관측된 제품에서도 벗어납니다 —
{{ site.data.fab.fab_menu.deviation_on_compact.also_against }} 벗어나는 이유는
M3의 웹 조언이 "데스크탑 앱에는 메뉴가 있다"는 근거 위에 서 있고, 그 근거가 폰에서는
성립하지 않기 때문입니다. 틀렸을 때의 비용도 같이 적어 둡니다 —
{{ site.data.fab.fab_menu.deviation_on_compact.cost_if_wrong }}

랩에 구현이 이미 있습니다. 측정이 토큰과 맞고(close 56/아이콘 20, 행 56/아이콘 24, 간격
8과 4, 좌우 24), 가져올 때 걸리는 건 하나입니다 —
{{ site.data.fab.fab_menu.lab_implementation.porting_consequence }}

## 배치와 첫 소비자

compact·medium은 {{ site.data.fab.placement.compact }}, expanded는
{{ site.data.fab.placement.expanded }}. 여백은 {{ site.data.fab.placement.margin }}dp이고
large·extra-large 창에서 {{ site.data.fab.placement.margin_large_windows }}dp로 커집니다.
한 화면에 하나이고, 카드 같은 개별 컴포넌트는 자기 FAB를 갖지 않습니다 — FAB는 화면의
속성이지 목록 행의 속성이 아닙니다.

첫 소비자는 이미 있습니다. `{{ site.data.fab.placement.existing_consumer.slot }}` 슬롯이
navigation rail에 들어 있고, 컴포넌트 자체를 미룬 채 "resting elevation 0"까지 적어둔
상태입니다. 즉 FAB는 **자기 토큰이 발행한 고도 없이도 쓸 수 있어야** 합니다.

## 스타일북 라우트는 다섯 군데입니다

{{ site.data.fab.project_policy.stylebook_route.note }}

검증기는 이걸 조건으로 묶습니다 — 슬러그가 **한 군데라도** 나타나면 다섯 군데 모두에
있어야 합니다. 없을 때는 조용하고, 반쯤 들어오면 실패합니다.
