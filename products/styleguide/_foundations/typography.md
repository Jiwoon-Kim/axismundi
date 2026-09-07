---
title: Typography
description: M3 타입스케일 15역할, emphasized 굵기, 그리고 문서 언어를 따르는 행간
order: 30
lang: ko
---

색과 마찬가지로, 아래 표본은 자기가 설명하는 토큰으로 조판됩니다. 크기·굵기·자간·
행간 어느 것도 여기 옮겨 적지 않았습니다.

{% assign divisor = site.data.typography.meta.rem_divisor -%}

## Baseline type scale

{% assign groups = "display,headline,title,body,label" | split: "," -%}
{% for group in groups %}
### {{ group | capitalize }}

<ul class="sg-specimens">
{%- for entry in site.data.typography.roles %}
{%- assign role = entry[0] %}{% assign spec = entry[1] %}
{%- if role contains group %}
  <li class="sg-specimen">
    <span class="sg-specimen__sample" style="font-family: var(--md-sys-typescale-{{ role }}-font); font-size: var(--md-sys-typescale-{{ role }}-size); font-weight: var(--md-sys-typescale-{{ role }}-weight); letter-spacing: var(--md-sys-typescale-{{ role }}-tracking); line-height: var(--md-sys-typescale-{{ role }}-line-height)">한글과 Latin이 한 줄에 섞이는 문장 — {{ role }}</span>
    <span class="sg-specimen__meta">
      <code class="sg-specimen__token">--md-sys-typescale-{{ role }}</code>
      <span>{{ spec.size }}pt</span>
      <span>weight {{ spec.weight }}</span>
      <span>tracking {{ spec.tracking }}pt</span>
      <span>emphasized {{ spec.emphasized_weight }}</span>
    </span>
  </li>
{%- endif %}
{%- endfor %}
</ul>
{% endfor %}

`display`는 이 사이트가 쓰지 않습니다. 57px는 히어로 크기이지 레퍼런스 문서의
제목이 아니라서요. 토큰은 있고, 쓰는 곳이 없을 뿐입니다.

## 단위

스펙은 pt로 발행하고, 같은 문서가 웹 대응을 `1sp = {{ 1.0 | divided_by: divisor }}rem`으로
줍니다. 그래서 생성기가 발행값을 {{ divisor }}로 나눕니다. **자간도 같은 나눗셈**을
쓰는데, 그러면 요소 크기에 비례하지 않는 절대 오프셋이 됩니다 — 스펙이 그렇게
정의합니다.

숫자는 전부 계산된 것입니다. `_data/typography.yml`이 발행값을 갖고,
생성기가 CSS를 만들고, 검증기가 그 CSS를 다시 읽어 pt로 되돌려 대조합니다.

## Emphasized

emphasized 스타일은 baseline과 **굵기만** 다릅니다. 그래서 토큰도 굵기 하나만
있고 크기·자간·행간은 공유합니다.

스펙에 불일치가 다섯 곳 있고, 지우지 않고 기록했습니다.

<ul class="sg-specimens">
{%- for entry in site.data.typography.roles %}
{%- assign role = entry[0] %}{% assign spec = entry[1] %}
{%- if spec.emphasized_weight_static %}
  <li class="sg-specimen">
    <span class="sg-specimen__sample" style="font-family: var(--md-sys-typescale-{{ role }}-font); font-size: var(--md-sys-typescale-{{ role }}-size); font-weight: var(--md-sys-typescale-{{ role }}-emphasized-weight); letter-spacing: var(--md-sys-typescale-{{ role }}-tracking); line-height: var(--md-sys-typescale-{{ role }}-line-height)">{{ role }} emphasized</span>
    <span class="sg-specimen__meta">
      <span>축 <code class="sg-specimen__token">'wght' {{ spec.emphasized_weight }}</code> 사용</span>
      <span>정적 Roboto라면 {{ spec.emphasized_weight_static }}</span>
    </span>
  </li>
{%- endif %}
{%- endfor %}
</ul>

이 다섯은 `weight 700` 옆에 `'wght' 600`이 적혀 있습니다. 같은 문서의
Fonts & weights 표는 semi bold를 600, bold를 700으로 정의하고, 굵기가 500인
역할들은 축과 일치합니다. 이 사이트는 가변폰트 Roboto Flex로 렌더하므로
**실제로 적용되는 것은 축 값**이라 600을 씁니다.

## 행간은 문서 언어를 따릅니다

M3는 세로 공간이 더 필요한 문자 체계를 위해 행간 세트를 네 개 발행합니다.
레이아웃이 `lang`을 보고 `data-language-height`를 정하며, 한국어·일본어·중국어는
`medium`, 나머지는 baseline입니다.

**이 페이지는 지금 `medium`으로 렌더되고 있습니다.** `lang="ko"`니까요.

| Role | small | medium | large | extra-large |
|---|---|---|---|---|
{% for entry in site.data.typography.roles -%}
{%- assign role = entry[0] %}{% assign lh = entry[1].line_height -%}
| `{{ role }}` | {{ lh.small }} | {{ lh.medium }} | {{ lh.large }} | {{ lh["extra-large"] }} |
{% endfor %}

본문(`body-large`)이 24pt 대신 27pt가 되는 게 그 결과입니다. 한국어는 같은
행간에서 영문보다 답답하게 보이므로, 이건 취향이 아니라 문자 체계의 문제입니다.

## 광학 크기

어떤 역할도 `font-variation-settings`를 내보내지 않습니다. 스펙은 각 역할에
자기 크기와 같은 `opsz`를 주는데, 가변폰트에서 `font-optical-sizing: auto`가
정확히 그 일을 합니다. 스펙이 나열하는 나머지 축(`GRAD 0`, `wdth 100`,
`ROND 0`, `CRSV 0`, `slnt 0`, `FILL 0`, `HEXP 0`)은 역할마다 같고 이미
`@font-face` 기본값입니다.

## 이 사이트의 위계

역할과 용도는 다릅니다. 아래는 이 문서 사이트의 선택이지 M3의 규정이 아닙니다.

```
h1     headline-large      본문   body-large
h2     headline-small      캡션   body-small
h3     title-large         내비   label-large
```

## `--wp--preset--font-size--*`가 여기 없는 이유

WordPress는 `theme.json`의 typography preset에서 **런타임에** CSS 변수를
만듭니다. 테마의 15개 fontSize는 위 스펙과 같은 값이지만, 그 변수는 CSS 파일에
존재하지 않으므로 가져올 것이 없습니다.

그래서 이 사이트는 타입스케일을 직접 선언합니다. 색과 반대 방향인데, 이유가
다릅니다 — 색은 테마에 최신 CSS가 있어서 가져오고, 타입스케일은 애초에 가져올
파일이 없습니다.

자세한 것은 [Token layers]({{ '/foundations/token-layers/' | relative_url }}).
