---
title: Color
description: 94개 reference 톤과 34개 system 역할, 그리고 두 스킴이 뒤바뀌는 방식
order: 20
lang: ko
---

이 페이지의 모든 색은 지금 이 사이트가 로드한 토큰에서 읽습니다. 스크린샷이
아니라 **살아있는 값**이라, 좌측 상단 스위처로 스킴을 바꾸면 아래 색들이 함께
바뀝니다. 그래서 이 문서는 실제 구현과 어긋날 수가 없습니다.

## 두 층

```
--md-ref-palette-*   리터럴 값이 존재하는 유일한 층
        ↓ var()
--md-sys-color-*     역할. 리터럴 금지, 오직 ref를 소비
        ↓ var()
컴포넌트
```

**리터럴 hex가 `--md-sys-color-*`에 들어가면 CI가 실패합니다.** 이건 취향이 아니라
검사되는 규칙입니다 — `validate_token_layering.py`의 Axis E가 그걸 봅니다. 이유는
단순합니다: 다크 모드는 ref → sys 매핑만 바꿉니다. sys에 값이 박혀 있으면 바꿀
매핑이 없습니다.

## Reference palette

M3 reference 톤. 값이 실제로 여기 있는 유일한 곳입니다.

{% for family in site.data.color.ref %}
### {{ family.family }}

<div class="sg-tones">
{%- for tone in family.tones %}
  {%- if tone <= 50 %}{% assign ink = "#fff" %}{% else %}{% assign ink = "#000" %}{% endif %}
  <span class="sg-tone" style="--sg-tone-ink: {{ ink }}; background: var(--md-ref-palette-{{ family.family }}-{{ tone }})">{{ tone }}</span>
{%- endfor %}
</div>
{% endfor %}

`neutral`만 24톤이고 나머지는 14톤입니다. 여분은 surface container 단계를 위한
것으로, M3가 그 자리에 튜닝된 값을 따로 발행하기 때문입니다 — 톤 곡선에서
계산해 낸 값이 아닙니다.

## System roles

각 역할이 어느 ref 톤을 가리키는지가 함께 적혀 있습니다. **두 스킴의 차이가 곧
그 매핑의 차이**이고, 그게 전부입니다.

{% for group in site.data.color.sys %}
### {{ group.group }}

<ul class="sg-roles">
{%- for role in group.roles %}
  <li class="sg-role">
    <span class="sg-role__swatch" style="background: var(--md-sys-color-{{ role.name }})"></span>
    <span class="sg-role__meta">
      <code class="sg-role__name">--md-sys-color-{{ role.name }}</code>
      <span class="sg-role__map">light {{ role.light }} · dark {{ role.dark }}</span>
    </span>
  </li>
{%- endfor %}
</ul>
{% endfor %}

## 다크가 하는 일

`primary`를 보면 `primary-40` → `primary-80`입니다. 어두운 배경에서 같은 색조를
더 밝은 톤으로 올리는 것이지, 다른 색으로 바꾸는 것이 아닙니다. `surface`는
`neutral-98` → `neutral-6`으로 뒤집힙니다.

`shadow`와 `scrim`은 두 스킴에서 같은 `neutral-0`입니다. 그래서 색 파일이 아니라
elevation 파일에 있습니다 — 색 역할이라기보다 **elevation 동작의 일부**로 분류한
구조입니다. 다크에서 물리적 그림자가 `none`이 되는 것도 같은 파일에서 함께
설명됩니다.

## 어디에 있나

```
themes/axismundi/assets/styles/
  tokens.ref.css                 팔레트
  tokens.sys.color.light.css     역할 → ref (light)
  tokens.sys.color.dark.css      역할 → ref (dark)
  tokens.sys.elevation.css       shadow · scrim + 그림자 공식
```

이 사이트는 그 파일들을 빌드 때 그대로 복사해 씁니다. 따로 옮겨 적은 팔레트를
문서화하면 아무도 배포하지 않는 색을 설명하게 되니까요.
