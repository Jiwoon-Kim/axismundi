---
title: Foundations
description: 토큰 층, 색, 타이포그래피, 모양, 상태
section: foundations
lang: ko
---

{% assign docs = site.foundations | sort: "order" -%}
{% if docs.size > 0 -%}
<ul>
{%- for doc in docs %}
  <li><a href="{{ doc.url | relative_url }}">{{ doc.title }}</a>{% if doc.description %} — {{ doc.description }}{% endif %}</li>
{%- endfor %}
</ul>
{%- else -%}
아직 문서가 없습니다.

먼저 들어올 것은 토큰 층입니다. `--md-ref-*`(원시 팔레트)와 `--md-sys-*`(역할)의
관계, `--md-sys-*`가 리터럴 값을 가질 수 없는 이유, 그리고 그 규칙을 CI에서
검사하는 방식을 다룹니다.
{%- endif %}
