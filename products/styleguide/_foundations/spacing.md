---
title: Spacing
description: M3 measurement 스케일 — 그리고 테마가 이미 그걸 쓰고 있었다는 것
order: 35
lang: ko
---

M3의 `md.sys.measurement.space*`는 8dp를 기본 단위로 하는 선형 스케일입니다.
8의 배수가 아닌 값들 — 2, 4, 6, 10, 14 — 은 **nested unit**이라 부르고, M3는
그중 권장되는 것만 발행합니다.

## 이 스케일은 이미 배포되고 있었습니다

문서를 쓰기 전에 테마의 `theme.json`과 대조해봤습니다.

```
M3 발행    18개  (space0 … space900)
theme.json 17개  spacingSizes

값 불일치      없음
M3에만 있음    space0
테마에만 있음  없음
```

**슬러그도 값도 정확히 일치합니다.** `space0`만 빠졌는데 그건 0이라 WordPress
preset으로 쓸 일이 없습니다.

그러니 이건 M3를 닮은 Axismundi 정책이 아니라 **M3 발행 스케일 그 자체**이고,
테마가 이미 구현해두었으며, 아무 데도 적혀 있지 않았을 뿐입니다. 토큰 접두어가
`--ax-sys-*`가 아니라 `--md-sys-*`인 근거가 그것입니다.

두 곳이 갈라지면 검증기가 멈춥니다 — 이 사이트의 스케일과 테마의 `spacingSizes`를
매번 대조합니다.

## 스케일

{% assign nested = site.data.measurement.meta.nested -%}
<ul class="sg-spacings">
{%- for entry in site.data.measurement.scale %}
  <li class="sg-spacing">
    <span class="sg-spacing__bar" style="inline-size: var(--md-sys-measurement-space{{ entry[0] }})"></span>
    <span class="sg-spacing__meta">
      <code class="sg-specimen__token">space{{ entry[0] }}</code>
      <span>{{ entry[1] }}dp</span>
      {%- if nested contains entry[0] %}<span>nested</span>{% endif %}
    </span>
  </li>
{%- endfor %}
</ul>

막대 길이는 자기가 설명하는 토큰입니다.

## 왜 CSS 파일이 여기 있나

색과 다르고 타이포그래피와 같습니다. WordPress는 `theme.json`의
`settings.spacing`에서 **런타임에** `--wp--preset--spacing--*`를 만듭니다. 그래서
테마에는 가져올 spacing 스타일시트가 없고, 이 사이트가 직접 선언합니다.

```
_data/measurement.yml          발행 스케일          ← 여기를 고침
   ↓ generate_styleguide_layout.py
tokens.sys.measurement.css     생성물, 커밋
   ↑ validate_styleguide_layout.py  + theme.json 교차 확인
```

`defaultSpacingSizes: false`도 같은 이야기입니다 — 테마가 WordPress 기본 스케일을
끄고 M3 것으로 갈아끼웠다는 뜻입니다.

## 컴포넌트가 쓰는 이름

M3는 컴포넌트 spacing 속성의 이름 규칙을 바꿨습니다.

```
지금부터   padding · margin · gap
           + 위치어 horizontal · vertical · leading · trailing · top · bottom
           예) "Medium button: leading padding"

이전       space 하나로 전부 서술
           leading-space · trailing-space · top-space · bottom-space · between-space
           예) "Medium button: leading space"
```

옛 이름을 쓰는 컴포넌트 토큰을 만나면 그건 폐기가 아니라 **이전 명명**입니다.

밀도나 적응형 로직은 스케일이 아니라 **컴포넌트 속성 쪽에** 걸어야 합니다.
스케일 자체는 고정된 선형 값입니다.

## margin과 gap은 다릅니다

M3 용어집이 나눠놓았고, [Layout]({{ '/foundations/layout/' | relative_url }})에서
그 구분을 씁니다.

```
margin   화면 가장자리와 그 안의 요소 사이
gap      컨테이너 안의 컴포넌트·요소 사이
```

둘 다 이 스케일에서 값을 가져오지, 새 숫자를 만들지 않습니다.
