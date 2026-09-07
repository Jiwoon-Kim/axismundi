---
title: Components
description: 컴포넌트별 Material 사양과 구현 기준
section: components
lang: ko
---

{% assign docs = site.components | sort: "order" -%}
{% if docs.size > 0 -%}
<ul>
{%- for doc in docs %}
  <li><a href="{{ doc.url | relative_url }}">{{ doc.title }}</a>{% if doc.description %} — {{ doc.description }}{% endif %}</li>
{%- endfor %}
</ul>
{%- else -%}
아직 문서가 없습니다.

여기에 들어올 항목은 Lab에서 측정이 끝나고 실제 테마나 플러그인에 쓰이고 있는
컴포넌트입니다. Lab에 있다는 것만으로는 오지 않습니다 — 그쪽에는 실험이 섞여
있으니까요.
{%- endif %}
