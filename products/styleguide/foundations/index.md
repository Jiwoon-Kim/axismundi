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

이 구역의 문서는 값을 옮겨 적지 않습니다. 색 스와치도 타입 표본도 이 페이지가
로드한 토큰에서 직접 읽으므로, 테마가 바뀌면 문서가 따라옵니다.
{%- else -%}
아직 문서가 없습니다.
{%- endif %}
