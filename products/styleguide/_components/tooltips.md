---
title: Tooltips
description: 토큰이 네 개뿐인 컴포넌트, 그리고 같은 질문에 세 번 다르게 답한 리포를 한 번에 닫는 기록
kind: material
order: 80
lang: ko
---

툴팁은 작은 컴포넌트입니다. 그런데 이 리포에는 **이미 세 번 구현되어 있고 셋이 서로
다릅니다.** 그래서 이 레코드의 첫 일은 값을 적는 게 아니라 **어느 답을 택했는지와 그
이유**를 적는 것입니다.

> **아직 프리미티브는 없습니다.** 값은 전부
> [`_data/tooltip.yml`]({{ site.repository_url }}/blob/main/products/styleguide/_data/tooltip.yml)에서
> 오고, 그 파일은 토큰 표
> [`SOURCE-M3-TOOLTIPS-RAW.md`]({{ site.repository_url }}/blob/main/products/wordpress/plugins/axismundi/docs/m3-token-tables/SOURCE-M3-TOOLTIPS-RAW.md)와
> M3 산문 네 쪽, 그리고 리포 안의 선례 셋을 출처로 적습니다. 이번 slice는
> {{ site.data.tooltip.project_policy.slice }}

## 표에는 네 개뿐입니다

살아있는 토큰이 {{ site.data.tooltip.meta.live_token_total }}개인데, **plain tooltip 몫은
네 개입니다** — 컨테이너 색 `{{ site.data.tooltip.plain.container_colour }}`, 모서리
`{{ site.data.tooltip.plain.container_shape }}`, 글자 색
`{{ site.data.tooltip.plain.text_colour }}`, 타입 `body-small`. 높이도 패딩도 최대 너비도
**토큰이 없습니다.**

그 수치는 산문에 있습니다 — specs 쪽 속성 표가
"{{ site.data.tooltip.prose_sources.plain_measurements.statement }}"라고 적습니다. 그래서
레코드는 `--md-comp-plain-tooltip-height` 같은 것을 만들지 않고 **두 수치를 인용과 함께**
적습니다. 없는 토큰을 생성하는 순간 이 파일이 아무도 발행하지 않은 값의 출처가 됩니다.

세로 패딩은 산수로 나옵니다: body-small의 줄 높이 16에 위아래 4씩이면
{{ site.data.tooltip.plain.container_height }}. 검증기가 숫자 셋이 아니라
`{{ site.data.tooltip.plain.invariant }}`를 들고 있어서, 하나가 혼자 움직이면 실패합니다.

rich는 패딩이 비대칭입니다 — 위 {{ site.data.tooltip.rich.padding_block_start }}, 아래
{{ site.data.tooltip.rich.padding_block_end }}, 좌우
{{ site.data.tooltip.rich.padding_inline }}. 이번에 짓지는 않습니다.
{{ site.data.tooltip.variants.rich.why }}

## 리포가 자기 자신과 어긋나 있습니다

| | 메커니즘 | ARIA | 지연 |
|---|---|---|---|
| 배포 중인 플러그인 | top layer popover | `aria-hidden`, role 없음 | {{ site.data.tooltip.precedents_in_this_repository.shipped_plugin.show_delay }}ms |
| 랩 모듈 | 테마 런타임 | `role="tooltip"` + `aria-describedby` | 없음 |
| FAB 안의 span | z-index 2 | `aria-hidden` | 없음 |

메커니즘은 증거가 고릅니다. 배포 중인 플러그인이 **이유까지 적어** 뒀습니다 —
{{ site.data.tooltip.precedents_in_this_repository.shipped_plugin.why_the_top_layer }}

FAB 안의 span은 **{{ site.data.tooltip.precedents_in_this_repository.fab_inline.status }}**로
표시했습니다. {{ site.data.tooltip.precedents_in_this_repository.fab_inline.why_it_must_change }}

## ARIA는 둘 중 하나가 아니라 경우입니다

M3는 "{{ site.data.tooltip.prose_sources.role.statement }}"라고 합니다. 랩은 그대로
따랐고, 배포 중인 플러그인은 일부러 반대로 갔습니다. **둘 다 자기 경우에 대해 옳습니다.**

{{ site.data.tooltip.project_policy.aria.rule }}

이유는 중복입니다 — {{ site.data.tooltip.project_policy.aria.why_the_exception }}

FAB은 어느 쪽인지 먼저 적어 둡니다:
{{ site.data.tooltip.project_policy.aria.consequence_for_the_fab }}

## 위치와 동작

plain은 기본이 위, **경계가 있으면 {{ site.data.tooltip.placement.plain.distance_from_a_visual_boundary }},
없으면 {{ site.data.tooltip.placement.plain.distance_without_a_boundary }}**입니다 — 버튼처럼
보이는 상자가 있느냐 글자 베이스라인이냐의 차이입니다. app bar 안에서는 아래로 뒤집힙니다.

배치는 Menu의 placer 축을 그대로 씁니다.
{{ site.data.tooltip.placement.this_app.why }}

동작에서 발행된 수는 **사라지는 쪽**입니다 —
{{ site.data.tooltip.behaviour.hide_after_leaving }}ms. 나타나는 지연은 M3가 적지 않아서
배포 중인 플러그인의 {{ site.data.tooltip.project_policy.delay.show }}ms를 가져옵니다.
{{ site.data.tooltip.project_policy.delay.note }}

그리고 한 번에 하나입니다. 새 툴팁이 뜨면 열려 있던 것이 즉시 닫힙니다. 포커스는
움직이지 않습니다 — {{ site.data.tooltip.behaviour.focus_is_never_moved }} 이것이 popover와
툴팁을 가르는 선이고, 랩의 audit이 "popover 런타임을 복사해 쓸 수 없다"고 결론 낸 이유도
같습니다.

## 기다리고 있는 소비자

{% for consumer in site.data.tooltip.project_policy.consumers_waiting -%}
- {{ consumer }}
{% endfor %}

extended FAB은 소비자가 **아닙니다**: {{ site.data.tooltip.project_policy.not_a_consumer }}
