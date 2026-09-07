---
title: Components
description: 컴포넌트별 Material 사양과 구현 기준
section: components
lang: ko
---

두 종류가 있고, 그 차이가 이 구역의 구조입니다.

{% assign material = site.components | where: "kind", "material" | sort: "order" -%}
{% assign controls = site.components | where: "kind", "axismundi" | sort: "order" -%}

## Material bindings

Material 컴포넌트를 WordPress core 블록에 바인딩한 것들입니다. 측정된 기준선은
[axismundi-lab]({{ site.repository_url }}/tree/main/products/reference-implementations/axismundi-lab)에
있고, 이 문서들은 그 결과와 대응의 한계를 설명합니다.

{% if material.size > 0 -%}
<ul>
{%- for doc in material %}
  <li><a href="{{ doc.url | relative_url }}">{{ doc.title }}</a>{% if doc.description %} — {{ doc.description }}{% endif %}</li>
{%- endfor %}
</ul>
{%- else -%}
아직 문서가 없습니다. Lab에서 측정이 끝나고 실제 테마나 플러그인에 쓰이고 있는
컴포넌트만 여기로 옵니다 — Lab에 있다는 것만으로는 오지 않습니다.
{%- endif %}

## Axismundi controls

Axismundi 고유의 복합 컨트롤입니다. Material 컴포넌트를 재료로 쓰지만, 상태와
저장과 의미는 이 프로젝트가 소유하고 어떤 M3 사양도 이들을 서술하지 않습니다.

{% if controls.size > 0 -%}
<ul>
{%- for doc in controls %}
  <li><a href="{{ doc.url | relative_url }}">{{ doc.title }}</a>{% if doc.description %} — {{ doc.description }}{% endif %}</li>
{%- endfor %}
</ul>
{%- else -%}
아직 문서가 없습니다.
{%- endif %}
