---
title: WordPress Bindings
description: core 블록과 Material 컴포넌트의 대응 관계
section: wordpress
lang: ko
---

{% assign docs = site.wordpress | sort: "order" -%}
{% if docs.size > 0 -%}
<ul>
{%- for doc in docs %}
  <li><a href="{{ doc.url | relative_url }}">{{ doc.title }}</a>{% if doc.description %} — {{ doc.description }}{% endif %}</li>
{%- endfor %}
</ul>
{%- else -%}
아직 문서가 없습니다.

이 구역은 블록 하나에 문서 하나입니다. `core/navigation`처럼 대응이 까다로운
것부터 씁니다. 각 문서는 core가 실제로 내보내는 마크업, Material 쪽 대응
컴포넌트, 그리고 맞출 수 없는 지점을 함께 적습니다.

마지막 항목이 이 구역의 존재 이유입니다. 시각적으로만 비슷하게 만들고 의미가
어긋난 채로 두면, 그 차이는 나중에 접근성이나 에디터 동작에서 되돌아옵니다.
{%- endif %}
