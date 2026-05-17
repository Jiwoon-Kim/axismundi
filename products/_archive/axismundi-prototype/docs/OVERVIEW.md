# Axismundi Design System — Overview

> **버전:** v1.0.0-rc1 (Phase 1B 완료)
> **마지막 업데이트:** 2026-05-03
> **상태:** 33/33 spec 컴포넌트 빌드 완료 — Phase 2 prototype 진입 대기
> **문서 역할:** 시스템 reference + caveat catalog + roadmap

---

## 0. About this document

OVERVIEW.md는 **빌드된 시스템의 정확한 reference + 결정 rationale + 향후 작업 계획**을 한 곳에 모은 문서. Claude Design / 협업자 / 미래의 본인이 *현재 시스템이 어떻게 작동하는가*를 빠르게 파악할 수 있도록 작성.

### Related documents

| 문서 | 역할 |
|---|---|
| `prompt-v2.md` | 마스터 spec (변경 안 함) |
| `M3-COMPONENT-SPECS.md` | M3 컴포넌트 reference (33개 spec) |
| `M3-COLOR-TOKEN.md` | M3 baseline 색상 raw 값 |
| `audit-en.md` | Phase-1 audit, 결정 rationale |
| `BRIEF.md` | 제품 컨텍스트 |
| **`OVERVIEW.md`** ← 이 문서 | 빌드 결과 + caveat + roadmap |
| `CONTEXT.md` | 작업 phase + 다음 단계 |
| `CHANGELOG.md` | 버전 history |

### Built artifacts

| 파일 | 역할 |
|---|---|
| `tokens.css` | 모든 토큰 (ref + sys + comp + alias + layout) |
| `base.css` | Reset + typography 매핑 + KR-first 처리 |
| `components.css` | 14 컴포넌트 구현 |
| `style-guide.html` | 시각 카탈로그 (검증 surface) |
| `theme.json` | 토큰 단일 source-of-truth (WP 호환 manifest) |

---

## 1. Token system

### 1.1 Three-tier architecture

토큰은 3개 레이어로 추상화되어 있고, 각 레이어는 *위 레이어를 참조*합니다.

```
Reference (raw values)
   ↓
System (semantic roles)
   ↓
Component (component-specific tokens)
   ↓
Alias (use-case shortcuts) ← optional convenience layer
```

**Layer 별 역할:**

| Layer | Prefix | 예 | 누가 정의하는가 |
|---|---|---|---|
| Reference | `--md-ref-*` | `--md-ref-palette-primary-40: #6750A4;` | M3 baseline (raw hex 값, 톤별 팔레트) |
| System | `--md-sys-*` | `--md-sys-color-primary: var(--md-ref-palette-primary-40);` | M3 spec (의미론적 역할) |
| Component | `--comp-*` | `--comp-card-padding: var(--space-md);` | 본인 시스템 (컴포넌트별) |
| Alias | `--site-bg`, `--card-bg` | `--card-bg: var(--md-sys-color-surface-container);` | 본인 시스템 (use-case 명명) |

### 1.2 Reference layer (`--md-ref-*`)

- 6개 tonal palette: primary, secondary, tertiary, error, neutral, neutral-variant
- 88개 tone (palette별 13–24 tone)
- M3 Expressive: neutral palette 24 tone (다크 surface granularity 위해 4/6/12/17/22/24 추가)
- 모든 값은 hex (`#RRGGBB`)
- Source: `M3-COLOR-TOKEN.md` §1

```css
--md-ref-palette-primary-40: #6750A4;       /* primary container 30 → 90 */
--md-ref-palette-neutral-6:  #141218;       /* dark surface base */
--md-ref-palette-neutral-22: #36343B;       /* dark surface-container-highest */
```

### 1.3 System layer (`--md-sys-*`)

- 38개 sys-color 토큰 (light + dark scheme)
- 75개 typescale 토큰 (15 role × 5 property: family/size/line-height/weight/tracking)
- Shape, motion, state, elevation 토큰

**Sys color 카테고리:**

| 카테고리 | 토큰 수 | 예 |
|---|---|---|
| Brand | 12 | `primary`, `on-primary`, `primary-container`, `on-primary-container` (× 3 brand colors) |
| Status | 4 | `error`, `on-error`, `error-container`, `on-error-container` |
| Surface | 13 | `surface`, `on-surface`, `surface-variant`, `on-surface-variant`, `background`, `on-background`, `surface-bright`, `surface-dim`, `surface-container-{lowest, low, base, high, highest}` |
| Inverse | 3 | `inverse-surface`, `inverse-on-surface`, `inverse-primary` |
| Outline | 2 | `outline`, `outline-variant` |
| Other | 2 | `shadow`, `scrim` |
| Total | **38** | |

**Sys typescale 매핑 — Family rule:**

```css
/* display, headline, title-large → brand */
--md-sys-typescale-display-large-font: var(--md-ref-typeface-brand);

/* title-medium 이하 + body + label → plain */
--md-sys-typescale-body-large-font: var(--md-ref-typeface-plain);
```

### 1.4 Component layer (`--comp-*`)

컴포넌트별 자주 쓰이는 사이즈/포지션을 토큰화. 글로벌하지 않은 *컴포넌트 spec*에 가까움.

```css
/* Card */
--comp-card-padding: var(--space-md);
--comp-card-radius:  var(--md-sys-shape-corner-medium);

/* Button */
--comp-button-height: 40px;
--comp-button-radius: var(--md-sys-shape-corner-full);

/* Avatar */
--comp-avatar-size:   40px;
--comp-avatar-radius: var(--md-sys-shape-corner-full);

/* Icon sizing */
--comp-icon-size-sm: 20px;
--comp-icon-size-md: 24px;
--comp-icon-size-lg: 28px;
--comp-icon-size-xl: 32px;
--comp-touch-target: 48px;

/* Layout aliases */
--comp-rail-width:    96px;
--comp-rail-narrow:   80px;
--comp-rail-expanded: 220px;
--comp-feed-gap:      var(--space-sm);
--comp-feed-max:      640px;
```

**Component-specific literals (M3 §0.10 원칙):**

특정 컴포넌트에서만 쓰이는 사이즈는 *토큰화하지 않음* — 인라인 코멘트로 출처 명시.

| 컴포넌트 | 리터럴 | 출처 |
|---|---|---|
| Chip | 18px (icon) | M3 §14.2 — chip-only |
| App bar small | 64px (height) | M3 §1 |
| App bar medium-flexible | 112-136px | M3 §1.3 |
| App bar large-flexible | 120-152px | M3 §1.3 |
| Tab | 48px (height) | M3 §31 |
| Tab with icon | 64px | M3 §31 |
| Snackbar | 48px / 68px | M3 §28 (1-line / 2-line) |
| Sheet bottom-modal | 32×4px (drag handle) | M3 §26 |
| Sheet side-modal | 256px (width) | M3 §26 |
| Scrim | 0.32 opacity | M3 §0.12 |

이런 리터럴은 *재사용 의도가 없음을 코멘트로 명시*하여 향후 잘못된 토큰 promotion 방지.

### 1.5 Semantic alias layer

`var(--md-sys-color-*)`을 *use case*로 한 번 더 추상화. 컴포넌트 CSS에서 더 읽기 쉬움.

```css
/* Surface roles — by use, not by M3 name */
--site-bg:           var(--md-sys-color-background);
--feed-bg:           var(--md-sys-color-surface-container-lowest);
--card-bg:           var(--md-sys-color-surface-container);
--card-bg-hover:     var(--md-sys-color-surface-container-high);
--modal-bg:          var(--md-sys-color-surface-container-high);

/* Navigation roles */
--nav-rail-bg:       var(--md-sys-color-surface);              /* collapsed standalone */
--nav-rail-bg-modal: var(--md-sys-color-surface-container);    /* expanded modal */
--nav-bar-bg:        var(--md-sys-color-surface-container);
--nav-item-active:   var(--md-sys-color-secondary-container);
--nav-on-item-active: var(--md-sys-color-on-secondary-container);

/* Action roles */
--action-primary-bg:    var(--md-sys-color-primary);
--action-primary-fg:    var(--md-sys-color-on-primary);
--action-secondary-bg:  var(--md-sys-color-secondary-container);
--action-secondary-fg:  var(--md-sys-color-on-secondary-container);
```

> **언제 alias를 만들지:** *컴포넌트 CSS가 sys 토큰 이름을 그대로 쓰면 의미가 흐려질 때.* 예를 들어 `card-bg`는 의도가 명확하지만 `surface-container`는 일반적. 매번 카드를 정의할 때 sys 토큰 의미를 따져야 하면 alias 추가.

### 1.6 Layout tokens

페이지 레벨 레이아웃 제약. 컴포넌트 사이즈가 아닌 *컨테이너 max-width*.

```css
--layout-content-max:       640px;   /* 메인 콘텐츠 최대 너비 */
--layout-rail-width:        96px;
--layout-rail-narrow:       80px;
--layout-rail-expanded:     220px;
--layout-rail-expanded-max: 360px;
--layout-feed-max:          640px;
--layout-aside-max:         320px;
--layout-modal-max:         560px;
--layout-bottom-sheet-max:  640px;
```

### 1.7 Token format — hex + `color-mix()`

**모든 색상 값은 hex (`#RRGGBB`).** RGB channels (`106 84 141`) 형식 사용 안 함.

**Alpha 합성은 `color-mix(in srgb, ..., transparent N%)` 패턴:**

```css
/* State layer — Pattern A (currentColor + opacity 토큰) */
.button:hover::before {
  background-color: color-mix(
    in srgb,
    currentColor calc(var(--md-sys-state-hover-state-layer-opacity) * 100%),
    transparent
  );
}

/* Soft surface tint — Pattern B (named token + transparent) */
background-color: color-mix(
  in srgb,
  var(--md-sys-color-primary),
  transparent 92%
);
```

> **Why hex?** `color-mix()`는 valid CSS color를 입력으로 받습니다. RGB channels (`rgb()` 안에서만 작동)보다 hex가 모든 CSS color 함수와 호환. 단일 format으로 통일.

---

## 2. Color system

### 2.1 Source — M3 baseline (not seed-derived)

**M3 official baseline** 사용. Material Theme Builder의 seed-derived 토큰은 사용 안 함.

**왜 baseline:**
- Material Theme Builder는 HCT 알고리즘으로 seed에서 생성하지만, M3 공식 spec과 *drift* 발생
- Builder 결과에 deprecated 토큰 (`surface-tint`) 포함
- Baseline은 hand-curated, 보수적, 권위적

**Custom seed (Plum, Teal 등) 채택은 v1.5+로 defer.** 현재는 baseline 그대로.

### 2.2 Light + dark scheme

각 sys color 토큰은 light/dark에서 *다른 ref tone* 참조.

| Sys role | Light | Dark |
|---|---|---|
| `primary` | primary 40 (`#6750A4`) | primary 80 (`#D0BCFF`) |
| `on-primary` | primary 100 (`#FFFFFF`) | primary 20 (`#381E72`) |
| `primary-container` | primary 90 (`#EADDFF`) | primary 30 (`#4F378B`) |
| `surface` | neutral 98 (`#FEF7FF`) | neutral 6 (`#141218`) |
| `surface-container` | neutral 94 (`#F3EDF7`) | neutral 12 (`#211F26`) |
| `outline` | neutral-variant 50 (`#79747E`) | neutral-variant 60 (`#938F99`) |

전체 매핑은 `M3-COLOR-TOKEN.md` §2 참조.

### 2.3 Auto + manual override (selector strategy)

3가지 mode 지원: `light` / `dark` / `auto` (system follows).

```css
/* 1. Default = light */
:root { --md-sys-color-surface: #FEF7FF; /* ...etc */ }

/* 2. Auto — system dark, unless user explicitly picked light */
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    --md-sys-color-surface: #141218;
    /* ...dark overrides */
  }
}

/* 3. Manual — explicit data-theme override */
:root[data-theme="dark"] {
  --md-sys-color-surface: #141218;
  /* ...dark overrides */
}
```

**Selector 핵심:**
- `:root:not([data-theme="light"])` — auto 모드에서 사용자가 *명시적으로 light 선택*한 경우 system dark 무시
- `:root[data-theme="dark"]` — 사용자가 *explicit dark* 선택한 경우 (system이 light라도)
- `data-theme` 없으면 default (auto)

JS에서 `localStorage` 읽고 `<html>`에 `data-theme` 적용. 자세한 구현은 `style-guide.html`의 theme switcher 참조.

### 2.4 Surface containers (5 levels)

Light에서 가장 *밝은* → 어두운 순:

```
surface-container-lowest    #FFFFFF   (가장 밝음)
surface-container-low       #F7F2FA
surface-container           #F3EDF7
surface-container-high      #ECE6F0
surface-container-highest   #E6E0E9   (가장 어두움)
```

Dark에서 가장 *어두운* → 밝은 순:

```
surface-container-lowest    #0F0D13   (가장 어두움)
surface-container-low       #1D1B20
surface-container           #211F26
surface-container-high      #2B2930
surface-container-highest   #36343B   (가장 밝음)
```

**Use case:**
- `lowest`: 피드 배경 (콘텐츠 강조)
- `low`: 보조 surface (sidebar background)
- (base) `container`: card 기본 background
- `high`: card hover, modal bg
- `highest`: text-field filled bg, snackbar inverse 대안

### 2.5 Inverse colors

다크 모드에서도 *밝은 surface*가 필요할 때 (예: snackbar). 또는 light 모드에서 *어두운 강조* 필요할 때.

```
inverse-surface       (dark mode에서 light surface)
inverse-on-surface    (위의 텍스트 색)
inverse-primary       (toggle된 outlined icon button selected 등)
```

Snackbar가 대표 use case — light mode에서도 *어두운 배경의 토스트*. `inverse-surface` + `inverse-on-surface`로 구현.

### 2.6 Static palettes (deferred)

M3 baseline은 *static palettes* 11개 (Black/White, Blue, Yellow, Red, Purple, Cyan, Grey, Green, Orange, Pink + Blue variant + Grey variant)를 추가로 제공. *seed-independent*.

**현재 상태:** v0.2.0에 미사용. `M3-STATIC-PALETTES.md`에 raw 값 보관.

**향후 use case:**
- ActivityPub 인스턴스별 색상 태그 (각 인스턴스에 다른 static palette 매핑)
- 태그 chip 색상 변형 (해시 기반)
- Status indicators (online/away/offline)

v1.5+에서 도입 시 별도 sys 토큰 namespace (`--md-sys-color-instance-blue-bg` 등)로 분리. core sys 레이어를 오염시키지 않음.

---

## 3. Typography

### 3.1 Typescale — 15 roles (M3 spec exact)

| Role | Family | Size (px) | Line (px) | Weight | Tracking (px) |
|---|---|---|---|---|---|
| display-large | brand | 57 | 64 | 400 | -0.25 |
| display-medium | brand | 45 | 52 | 400 | 0 |
| display-small | brand | 36 | 44 | 400 | 0 |
| headline-large | brand | 32 | 40 | 400 | 0 |
| headline-medium | brand | 28 | 36 | 400 | 0 |
| headline-small | brand | 24 | 32 | 400 | 0 |
| title-large | brand | 22 | 28 | 400 | 0 |
| title-medium | plain | 16 | 24 | 500 | 0.15 |
| title-small | plain | 14 | 20 | 500 | 0.1 |
| body-large | plain | 16 | 24 | 400 | 0.5 |
| body-medium | plain | 14 | 20 | 400 | 0.25 |
| body-small | plain | 12 | 16 | 400 | 0.4 |
| label-large | plain | 14 | 20 | 500 | 0.1 |
| label-medium | plain | 12 | 16 | 500 | 0.5 |
| label-small | plain | 11 | 16 | 500 | 0.5 |

각 role마다 5개 sys 토큰 (`-font`, `-size`, `-line-height`, `-weight`, `-tracking`) = 75 토큰.

### 3.2 Family rule — brand vs plain

| Family | 적용 | 의도 |
|---|---|---|
| `brand` | display, headline, title-large | 시각 강조 (display face 후보) |
| `plain` | title-medium 이하 + body + label | 가독성 (body face 필수) |

**현재 baseline 상태:** brand = plain = `Roboto Flex, Noto Sans KR, system-ui, sans-serif`. 같은 폰트.

**향후 swap 시나리오:** 만약 brand만 다른 face로 (예: Geist, Inter)로 바꾸려면 ref 토큰 *한 줄*만 수정:

```css
/* 변경 전 */
--md-ref-typeface-brand: 'Roboto Flex', 'Noto Sans KR', system-ui, sans-serif;

/* 변경 후 */
--md-ref-typeface-brand: 'Geist', 'Noto Sans KR', system-ui, sans-serif;
```

15개 typescale 중 7개 (display × 3 + headline × 3 + title-large)에 자동 적용. body는 영향 없음.

### 3.3 Korean-first stack

모든 typeface stack에 **Noto Sans KR (또는 Noto Serif KR for serif)이 fallback 직전 위치**:

```css
--md-ref-typeface-brand:  'Roboto Flex', 'Noto Sans KR', system-ui, sans-serif;
--md-ref-typeface-plain:  'Roboto Flex', 'Noto Sans KR', system-ui, sans-serif;
--md-ref-typeface-serif:  'Source Serif', 'Noto Serif KR', Georgia, serif;
--md-ref-typeface-mono:   'JetBrains Mono', 'Noto Sans Mono', ui-monospace, monospace;
```

**Body level 추가 처리 (`base.css`):**

```css
body {
  word-break: keep-all;          /* 한글 단어 단위 줄바꿈 */
  overflow-wrap: anywhere;       /* 영문 긴 단어 강제 줄바꿈 (keep-all과 짝) */
  line-height: 1.6;              /* 혼합 텍스트 안전 비율 (Latin 1.5 + Hangul 마진) */
}
```

**Why `keep-all` + `anywhere` together:**
- `keep-all` 만 = 영문 긴 단어가 줄 안 바뀌어 가로 스크롤 위험
- `anywhere` 만 = 한글이 글자 단위로 깨져 단어 가독성 ↓
- 둘 합쳐야 한글은 단어 단위 + 영문은 강제 줄바꿈

### 3.4 Utility classes (`.t-{role}`)

`base.css`에 15개 typography 유틸리티 클래스. `<style-guide.html>`에서 사용 + prototype에서 헬퍼로 활용.

```css
.t-display-large {
  font-family: var(--md-sys-typescale-display-large-font);
  font-size: var(--md-sys-typescale-display-large-size);
  line-height: var(--md-sys-typescale-display-large-line-height);
  font-weight: var(--md-sys-typescale-display-large-weight);
  letter-spacing: var(--md-sys-typescale-display-large-tracking);
}
/* ...15 classes total */

/* Emphasis modifier */
.t-label-large.is-prominent { font-weight: 700; }
.t-label-medium.is-prominent { font-weight: 700; }
```

**Heading 매핑 (`base.css`):**

| HTML | Typescale role |
|---|---|
| `h1` | headline-large |
| `h2` | headline-medium |
| `h3` | headline-small |
| `h4` | title-large |
| `h5` | title-medium |
| `h6` | title-small |

`display-*` (57/45/36px)는 *어떤 element에도 자동 매핑 안 함*. Article hero 등 특수 surface에서 `.t-display-*` 클래스로만 사용.

---

## 4. Spacing & shape

### 4.1 Spacing scale

```css
--space-xs: 4px;
--space-sm: 8px;
--space-md: 16px;
--space-lg: 24px;
--space-xl: 32px;
```

5 tier로 충분. 더 큰 spacing이 필요하면 *use case별*로 component / layout 토큰에 정의 (예: page padding은 `--space-xl` 사용).

> **`--space-2xl` 미정의 사례:** v0.2.0 visual QA에서 `style-guide.html`이 `--space-2xl` 사용했으나 토큰 미정의 → fallback 0px → 패딩 깨짐. Fix: `--space-xl` 사용. 시스템 spacing scale은 보존.

### 4.2 Shape scale — 10 tiers + directional

```css
--md-sys-shape-corner-none:                  0px;
--md-sys-shape-corner-extra-small:           4px;
--md-sys-shape-corner-small:                 8px;
--md-sys-shape-corner-medium:                12px;
--md-sys-shape-corner-large:                 16px;
--md-sys-shape-corner-large-increased:       20px;
--md-sys-shape-corner-extra-large:           28px;
--md-sys-shape-corner-extra-large-increased: 32px;
--md-sys-shape-corner-extra-extra-large:     48px;
--md-sys-shape-corner-full:                  9999px;

/* Directional modifiers */
--md-sys-shape-corner-extra-large-top:  28px 28px 0 0;
--md-sys-shape-corner-extra-small-top:   4px  4px 0 0;
--md-sys-shape-corner-large-start:      16px 0 0 16px; /* RTL: logical */
```

### 4.3 Component-level shape decisions

| 컴포넌트 | Shape | 출처 |
|---|---|---|
| Card | `corner-medium` (12px) | M3 §11 |
| Button (S size) | `corner-full` rest, `corner-small` pressed (shape morph) | M3 §4.3 |
| Icon button | `corner-full` (round) | M3 §6 |
| Chip | `corner-small` (8px) | M3 §14 |
| Dialog | `corner-extra-large` (28px) | M3 §17 |
| Bottom sheet | `corner-extra-large-top` | M3 §26 (top edges only) |
| Side sheet | `corner-large-start` | M3 §26 (RTL-safe logical) |
| Search bar | `corner-full` | M3 §29 |
| Snackbar | `corner-extra-small` (4px) | M3 §28 |
| Text field filled | `corner-extra-small-top` (4px top only) | M3 §32 |
| Text field outlined | `corner-extra-small` (4px all) | M3 §32 |

---

## 5. State & motion

### 5.1 State layer — Pattern A (`currentColor` + opacity)

**원칙 (M3 §0.7):** state-layer color = *컴포넌트 variant의 foreground (label/icon) 색*. `currentColor`로 자동 적용.

**Implementation — `.has-state-layer` mixin (components.css §0):**

```css
.has-state-layer {
  position: relative;
  isolation: isolate;
}
.has-state-layer::before {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background-color: transparent;
  transition: background-color
    var(--md-sys-motion-curve-fast-effects-duration)
    var(--md-sys-motion-curve-fast-effects);
  pointer-events: none;
  z-index: -1;
}
.has-state-layer:hover::before {
  background-color: color-mix(
    in srgb,
    currentColor calc(var(--md-sys-state-hover-state-layer-opacity) * 100%),
    transparent
  );
}
.has-state-layer:focus-visible::before {
  background-color: color-mix(
    in srgb,
    currentColor calc(var(--md-sys-state-focus-state-layer-opacity) * 100%),
    transparent
  );
}
.has-state-layer:active::before {
  background-color: color-mix(
    in srgb,
    currentColor calc(var(--md-sys-state-pressed-state-layer-opacity) * 100%),
    transparent
  );
}
```

**컴포넌트 적용:**
```html
<button class="ax-button ax-button--filled has-state-layer">Save</button>
```

State-layer color는 자동으로 `var(--md-sys-color-on-primary)` (filled의 foreground). 다른 variant도 자동 — `currentColor` 덕분.

### 5.2 State opacity values

```css
--md-sys-state-hover-state-layer-opacity:   0.08;
--md-sys-state-focus-state-layer-opacity:   0.10;
--md-sys-state-pressed-state-layer-opacity: 0.10;
--md-sys-state-dragged-state-layer-opacity: 0.16;
```

**Why these values (M3 Expressive correct):**
- M3 1.x: 0.08/0.12/0.12 — older spec
- M3 Expressive (현재): 0.08/0.10/0.10/0.16 — *focus/pressed가 더 부드러움*, dragged 추가
- Phase-1 v1.0이 0.12 사용했으나 audit에서 잘못 발견 → v2에서 정정

### 5.3 Disabled patterns (A / B / C / D)

M3-COMPONENT-SPECS §0.8에 4가지 패턴. 컴포넌트 타입에 따라 선택.

| Pattern | 적용 | 구현 |
|---|---|---|
| **A** (Container fade) | Button (모든 variant), Chip, Icon button | `bg: on-surface 10%`, `label: on-surface 38%`, `outline: on-surface 12%` |
| **B** (Whole container fade) | Card | `opacity: 0.38` (모든 자식 포함) |
| **C** (FG only fade) | (사용 안 함, M3에서 더 쓰지 않음) | — |
| **D** (Mixed for input) | Text field | `bg: on-surface 4%`, `outline: on-surface 12%`, `label/icons: on-surface 38%` |

### 5.4 Motion physics — spring + cubic-bezier

**M3 Expressive spring system.** 두 형태로 제공:

**Raw spring tokens (native API용 — Compose, SwiftUI):**
```css
--md-sys-motion-spring-fast-spatial-damping:     0.8;
--md-sys-motion-spring-fast-spatial-stiffness:   1400;
--md-sys-motion-spring-fast-effects-damping:     1.0;
--md-sys-motion-spring-fast-effects-stiffness:   3800;
/* ...12 tokens total */
```

**CSS cubic-bezier (transition-timing-function용):**
```css
--md-sys-motion-curve-fast-spatial:           cubic-bezier(0.42, 1.67, 0.21, 0.9);
--md-sys-motion-curve-fast-spatial-duration:  350ms;
--md-sys-motion-curve-fast-effects:           cubic-bezier(0.31, 0.94, 0.34, 1);
--md-sys-motion-curve-fast-effects-duration:  150ms;
/* ...6 curves × 2 properties = 12 tokens */
```

**Speed 선택 가이드 (M3 §0.9):**

| Curve | When |
|---|---|
| `fast-effects` | 색상 변화, opacity, 짧은 visual feedback (state layer) |
| `fast-spatial` | 작은 위치/크기 변화 (button shape morph) |
| `default-effects` | scrim opacity, dialog enter, 부드러운 visual |
| `default-spatial` | dialog/sheet enter motion, 큰 위치 변화 |
| `slow-effects` | 거의 사용 안 함 (긴 animation) |
| `slow-spatial` | 거의 사용 안 함 |

**컴포넌트 motion 매핑:**

| Property | Curve |
|---|---|
| Color, background, box-shadow, opacity | `fast-effects` |
| Border-radius (shape morph), small position | `fast-spatial` |
| Modal scrim | `default-effects` |
| Modal enter (translate, scale) | `default-spatial` |
| Tab indicator slide | `default-spatial` |

### 5.5 Reduced-motion handling

`base.css`에 글로벌 처리:

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
  html { scroll-behavior: auto; }
}
```

A11y 필수. 모든 motion 사실상 비활성화. 컴포넌트별 추가 처리 불필요.

---

## 6. Elevation

### 6.1 Levels (0–5) with dp values

```css
--md-sys-elevation-level0: 0;
--md-sys-elevation-level1: 1px;
--md-sys-elevation-level2: 3px;
--md-sys-elevation-level3: 6px;
--md-sys-elevation-level4: 8px;
--md-sys-elevation-level5: 12px;
```

dp 값은 *논리적 elevation* (z-axis 거리). 시각적 표현은 §6.2에서 결정.

### 6.2 Light/dark policy (shadow vs none)

**Light mode** — 그림자로 elevation 표현:

```css
--md-sys-elevation-shadow-level1:
  0 1px 2px color-mix(in srgb, var(--md-sys-color-shadow), transparent 70%),
  0 1px 3px 1px color-mix(in srgb, var(--md-sys-color-shadow), transparent 85%);
/* ...level2~5 */
```

**Dark mode** — 모든 그림자 `none`. 대신 *surface container 단계*로 elevation 시각화:

```css
:root[data-theme="dark"] {
  --md-sys-elevation-shadow-level1: none;
  --md-sys-elevation-shadow-level2: none;
  /* ...all levels: none */
}
```

다크 모드에서 그림자는 시각적 가치가 적고 (배경이 어두워 그림자 안 보임), 대신 `surface-container-low → high` 단계 변화가 elevation 신호로 작동.

**컴포넌트 매핑:**

| 컴포넌트 | Light shadow | Dark surface |
|---|---|---|
| Card filled | level0 | surface-container-highest |
| Card elevated | level1, hover level2 | surface-container-low + level0 |
| Card outlined | level0 + 1px outline | surface + 1px outline |
| App bar (rest) | level0 | surface |
| App bar (scrolled) | level2 | surface-container |
| Bottom sheet | level1 | surface-container-low + level1 |
| Search bar | level3 | surface-container-high |
| Dialog basic | level3 | surface-container-high |
| Snackbar | level3 | inverse-surface (역색이라 그림자 무관) |

### 6.3 Surface tint deprecated

M3 *Initial* spec에는 `--md-sys-color-surface-tint` 토큰이 있어 elevation 시 surface에 primary 색을 살짝 섞었으나, **M3 Expressive에서 deprecated**.

**v0.2.0:** tokens.css에 `surface-tint` 토큰 *없음*. 대신 surface container 단계 (`-lowest` → `-highest`)로 elevation 표현.

---

## 7. Component reference

### 7.1 Component status table (33 spec → 33 built / 0 planned) ✅

| # | M3 §  | Component | Bucket | Status | Milestone |
|---|---|---|---|---|---|
| 1 | §1 | App Bar | D | ✅ Built | v0.2.0 |
| 2 | §2 | Badge | C | ✅ Built | v0.2.5 |
| 3 | — | Avatar (extracted) | C | ✅ Built | v0.2.0 |
| 4 | §4 | Button | A | ✅ Built | v0.2.0 |
| 5 | §5 | Button group | C | ✅ Built | v0.4.0 |
| 6 | §6 | Icon button | C | ✅ Built | v0.2.0 |
| 7 | §7 | FAB | D | ✅ Built | v0.2.5 |
| 8 | §8 | Extended FAB | D | ✅ Built | v0.2.5 |
| 9 | §9 | FAB menu | E | ✅ Built | v0.4.0 |
| 10 | §10 | Split button | C | ✅ Built | v0.4.0 |
| 11 | §11 | Card | A | ✅ Built | v0.2.0 |
| 12 | §12 | Carousel | C | ✅ Built | v0.4.0 |
| 13 | §13 | Checkbox | E | ✅ Built | v0.3.0 |
| 14 | §14 | Chip | C | ✅ Built | v0.2.0 |
| 15 | §15 | Date picker | C | ✅ Built | v0.4.0 |
| 16 | §16 | Time picker | C | ✅ Built | v0.4.0 |
| 17 | §17 | Dialog | C | ✅ Built | v0.2.0 |
| 18 | §18 | Divider | A | ✅ Built | v0.2.0 |
| 19 | §19 | List | A | ✅ Built | v0.4.0 |
| 20 | §20 | Loading indicator | F | ✅ Built | v0.2.5 |
| 21 | §21 | Progress indicator | C | ✅ Built | v0.4.0 |
| 22 | §22 | Menu | C | ✅ Built | v0.2.5 |
| 23 | §23 | Navigation bar | D | ✅ Built | v0.2.5 |
| 24 | §24 | Navigation rail | D | ✅ Built | v0.2.0 (collapsed + expanded) |
| 25 | §25 | Radio button | E | ✅ Built | v0.3.0 |
| 26 | §26 | Sheet | C | ✅ Built | v0.2.0 (bottom-modal + side-modal) |
| 27 | §27 | Slider | E | ✅ Built | v0.3.0 |
| 28 | §28 | Snackbar | F | ✅ Built | v0.2.0 |
| 29 | §29 | Search | C | ✅ Built | v0.2.0 (rest state) |
| 30 | §30 | Switch | E | ✅ Built | v0.3.0 |
| 31 | §31 | Tabs | C | ✅ Built | v0.2.0 |
| 32 | §32 | Text field | E | ✅ Built | v0.2.0 |
| 33 | §33 | Toolbar | D | ✅ Built | v0.4.0 |
| 34 | §34 | Tooltip | F | ✅ Built | v0.2.5 |

> **참고:** Avatar는 M3 spec에 별도 § 없음 (List §19.7 leading-slot의 일부). Axismundi에선 *별도 컴포넌트*로 추출.

### 7.2 Built components (v0.3.0)

각 컴포넌트의 variant + state + 사용 예. 자세한 구현은 `components.css` 참조.

> *Tier 1 (§15–§21)부터는 components.css 마지막 부분에 추가됨. v0.2.0 14개는 §1–§14에 위치.*

#### Avatar (`.ax-avatar`)
- **Sizes:** xs (24px, chip leading) / sm (32px) / default (40px) / lg (56px)
- **Variants:** Image (`<img>`) + Initials (text)
- **Shape:** corner-full
- **Colors:** primary-container bg + on-primary-container text

#### Button (`.ax-button`)
- **Variants:** filled / tonal / elevated / outlined / text
- **Size:** S (40px height) — XS/M/L/XL은 v1.5+
- **States:** rest / hover / focus / pressed (shape morph corner-full → corner-small) / disabled (Pattern A)
- **Typography:** label-large
- **Icons:** leading icon 24px supported

#### Icon button (`.ax-icon-button`)
- **Variants:** filled / tonal / outlined / standard
- **Size:** 40×40 default, 48×48 touch target
- **Icon:** 24px (`--comp-icon-size-md`)
- **Toggle mode:** outlined-selected uses `inverse-surface` + `inverse-on-surface`
- **Glyph swap rule (§6.4):** outlined unselected → filled selected (HTML 처리는 author 책임)

#### Divider (`.ax-divider`)
- **Variants:** full-width / inset (16px L margin) / middle-inset (16px L+R)
- **Color:** outline-variant
- **Thickness:** 1px

#### Card (`.card`)
- **Variants:** filled / elevated / outlined
- **Common:** corner-medium, padding md, body-large
- **Interactive:** `.card--interactive` + `.has-state-layer` (hover: level1 → level2 for elevated)
- **Disabled:** Pattern B (whole-container 0.38)

#### App bar (`.app-bar`)
- **Variants:** small (64px) / medium-flexible (112-136px) / large-flexible (120-152px)
- **Title typography:** title-large / headline-medium / display-small
- **Scrolled state:** `[data-scrolled="true"]` → surface-container bg + level2 elevation
- **Slots:** `.app-bar__leading`, `.app-bar__title`, `.app-bar__trailing`, `.app-bar__subtitle` (medium/large only)

#### Nav rail (`.nav-rail`)
- **Modes:**
  - Collapsed (default): 96px width (default) / 80px (narrow)
  - Expanded (`.is-expanded`): 220-360px, row layout, label-large
- **Item:** 64px height collapsed / 56px height expanded
- **Active indicator (§0.13):** secondary-container fill + on-secondary-container glyph
  - Collapsed: 56×32 pill around icon
  - Expanded: full-width pill, corner-full
- **Modal mode:** `.nav-rail.is-expanded`을 `.sheet--side-modal` 안에 wrap (composition pattern)

#### Tabs (`.tabs`)
- **Variants:** primary / secondary
- **Active indicator:**
  - Primary: 3px, top-rounded (corner-extra-small), color primary
  - Secondary: 2px, flat, color on-surface
- **Implementation:** `::after` pseudo-element + `.is-active` (CSS-only)
- **Slide transition:** v1.5+ JS

#### Text field (`.text-field`)
- **Variants:** filled / outlined
- **Floating label:** `:placeholder-shown` 활용 (no JS), body-large (rest) → body-small (floated)
- **Active indicator:**
  - Filled: 1px → 2px primary on focus (inset 0 -1px → -2px)
  - Outlined: 1px → 3px primary on focus
  - Error: error color, filled gets 2px at rest
- **Disabled:** Pattern D
- **Textarea:** `:has(textarea.text-field__input)` — align-items start, label anchors top

#### Search bar (`.search-bar`)
- **State:** rest only (active "search view"는 dialog로 위임)
- **Container:** surface-container-high, level3, corner-full, height 56px
- **Slots:** `.search-bar__leading-icon`, `.search-bar__input`, `.search-bar__trailing` (button + avatar)

#### Chip (`.chip`)
- **Variants:** assist / filter / input / suggestion
- **Sizes:** 32px height fixed (M3 §14)
- **Icon size:** 18px (`--_chip-icon` local var, M3 §14.2 chip-only literal)
- **Filter selected:** `[aria-pressed="true"]` + `.is-selected` → secondary-container
- **Input chip:** trailing icon for remove (close X)
- **Disabled:** Pattern A

#### Dialog (`.dialog`)
- **Variants:** basic / full-screen
- **Element:** `<dialog>` or `<div>` with `.is-open`
- **Basic:** corner-extra-large (28px), surface-container-high, level3, max-width 560px
- **Full-screen:** inset:0 (no width/height — viewport units 회피), surface bg, level0
- **Scrim:** `.modal-scrim` (shared with sheet) — `<dialog>::backdrop` transparent으로 무효화
- **Headline center-align:** `:has(.dialog__icon)` triggers center

#### Sheet (`.sheet`)
- **Variants:** bottom-modal / side-modal
- **Bottom modal:** corner-extra-large.top, surface-container-low, level1, drag handle 32×4px
  - Top margin 72px (mobile) / 56px (>640px)
  - Width: full / max 640px
- **Side modal:** corner-large.start (RTL-safe via logical), 256px width, 100% height
- **Scrim:** shared `.modal-scrim`
- **Standard / detached variants:** v1.5+

#### Snackbar (`.snackbar`)
- **Container:** inverse-surface bg, inverse-on-surface text, level3, corner-extra-small
- **Heights:** 48px (single line) / 68px (two lines)
- **Action button:** inverse-primary color, label-large, state layer Pattern A
- **Provider/container:** prototype JS 영역 (imperative `m3.snackbar(msg)`)

#### FAB (`.ax-fab`)
- **Sizes:** default (56) / `.is-medium` (80) / `.is-large` (96)
- **Color styles:** tonal primary (default) / `.is-tonal-secondary` / `.is-tonal-tertiary` / `.is-primary` / `.is-secondary` / `.is-tertiary`
- **States:** rest / hover (level4) / pressed (level3) / disabled (Pattern A, level0)
- **Icon size:** 24 / 28 (medium) / 36 (large)
- **Use:** Composer trigger (우측하단 floating)

#### Extended FAB (`.ax-fab-extended`)
- **Size:** 56px height, min-width 80px
- **Layout:** leading icon (24px) + title-medium label
- **Color styles:** same 6 as FAB
- **Use:** FAB with discoverable label ("새 글 쓰기")

#### Nav bar (`.nav-bar`)
- **Height:** 64px, full viewport width, surface-container + level2
- **Item:** vertical layout (icon + label below), `.nav-bar__icon` wrapper hosts active indicator pill
- **Active indicator:** 56×32 pill, secondary-container fill (§0.13), state hooks: `.is-active` / `[aria-current="page"]` / `[aria-selected="true"]`
- **Focus:** inner -3px (dense layout)
- **A11y:** iOS safe-area-inset-bottom respected
- **Use:** mobile bottom nav (768px and below)

#### Badge (`.ax-badge`)
- **Variants:** small (6×6 dot, 3dp radius — `.ax-badge:empty`) + large (16×16+ numeric, 8dp radius — `.is-large`)
- **Position:** absolute, anchored to icon top-right corner via `transform: translate(50%, -50%)` (width-aware)
- **Inline variant:** `.is-inline` for manual placement (text flow)
- **Host helper:** `.has-badge` adds `position: relative` + `overflow: visible`
- **Important:** for label-bearing hosts (nav-bar item), apply `.has-badge` to icon wrapper, not outer button (50% reference must center on icon)

#### Menu (`.ax-menu`)
- **Orientation:** vertical only in v0.2.5 (Standard color)
- **Container:** surface-container-low, level2, corner-large, 8px padding, 2px gap between items
- **Item:** 44px min-height, 16px L/R, 12px gap, label-large, corner-extra-small base
- **Slots:** `.ax-menu__item-{leading,label,supporting,trailing,trailing-text}`, `.ax-menu__divider`, `.ax-menu__section-label`
- **Selected state:** tertiary-container fill + corner-medium morph (§22.7)
- **Show/hide:** `.is-open` (visibility + opacity transition)
- **Deferred to v1.5+:** horizontal orientation, Vibrant color mode, position-aware item corners (§22.5 patterns 2+3), submenu

#### Loading indicator (`.ax-loading`)
- **Variants:** default 48px (uncontained, primary) / `.is-contained` 38×48 (primary-container alveus) / `.is-small` 24px (inline)
- **Implementation:** SVG circle with stroke-dashoffset morph + rotation animation
- **Reduced motion:** rotation → opacity pulse fallback
- **Deferred to v1.5+:** true M3 Expressive morph animation (current is simpler legacy spinner)

#### Tooltip (`.ax-tooltip`)
- **Variants:** `.is-plain` (inverse-surface, 24px height, body-small, max 240px, single line) / `.is-rich` (surface-container + level2, corner-medium)
- **Rich slots:** `.ax-tooltip__{subhead,supporting,actions,action}`
- **Action button:** 32px height, primary color, label-large, state layer Pattern A
- **Show/hide:** `.is-open` (visibility + opacity transition)
- **z-index:** 1100 (above modals + snackbar)
- **Position:** author's responsibility (typical pattern: data attribute trigger + JS)

#### Checkbox (`.ax-checkbox`)
- **Container:** 18×18 box, 2px corner, 2px outline (on-surface-variant)
- **State layer:** 40×40 circle, currentColor + opacity
- **Selected:** primary fill + on-primary check icon
- **Indeterminate:** horizontal 10×2px on-primary bar
- **Cross-color pressed quirk** (M3 §13.3): unselected pressed = primary, selected pressed = on-surface
- **Error variant:** `.is-error` modifier — border/container/state-layer all → error color
- **Disabled (Pattern C):** outline 38%, label 38%
- **Markup:** hide native input + custom `.ax-checkbox__visual` span. Native input handles a11y + form submission.

#### Radio button (`.ax-radio`)
- **Outer ring:** 20×20, 2px on-surface-variant border (rest)
- **Inner dot:** 10×10 primary, scales 0→1 on `:checked` via spring transition
- **State layer:** 40×40 circle (same as checkbox)
- **Cross-color pressed quirk** (M3 §25.3): same as checkbox
- **Disabled (Pattern C):** ring 38%, dot 38%
- **Markup:** hide native input + custom `.ax-radio__visual` span

#### Switch (`.ax-switch`)
- **Track:** 52×32 pill, `box-sizing: border-box` + 2px outline. Selected = primary fill, unselected = surface-container-highest
- **Handle:** size morph (M3 §0.9 pattern 4) — 16×16 (unselected) / 24×24 (selected) / 28×28 (pressed)
- **Geometry:** abspos containing block = padding edge (CSS spec). Final values document inline.
  - Unselected rest leading: 6px (rest center 14)
  - Unselected pressed leading: 0 (28 grows around 14, center - 14)
  - Selected rest leading: 22px (48 - 24 - 2)
  - Selected pressed leading: 20px (rest center 34 - 14)
  - State layer 40×40 leading: -6 (unselected) / 14 (selected)
- **Track `overflow: visible`:** pressed 28×28 handle extends outside per M3 pattern
- **Disabled (Pattern C):** track 12%, handle 38% (unselected) / surface (selected, full opacity per §30.6)
- **Markup:** native `<input type="checkbox" role="switch">` + `.ax-switch__track` span (handle/state-layer via `::before`/`::after`)
- **v0.2.5 scope:** track + handle + state layer. Optional icon-on-handle deferred to v1.5+.

#### Slider (`.ax-slider`)
- **Scope:** XS standard horizontal only (v0.2.5)
- **Track:** 16px height, secondary-container background, corner-large
- **Active fill:** linear-gradient via `--_value` custom property, JS sets percentage on input event (5-line inline script in style guide)
- **Handle:** 4×44px primary, morphs to 2×44 on press (M3 §27.3)
- **Cross-browser:** `::-webkit-slider-{runnable-track,thumb}` + `::-moz-range-{track,thumb,progress}` (Firefox uses native progress pseudo)
- **Focus:** outline on thumb pseudo only, not on the 44px-tall input box
- **Disabled (Pattern C):** track 12%, handle 38%. `background` shorthand (not `-color`) prevents gradient bleed. Firefox `::-moz-range-progress { background: transparent }`.
- **Deferred to v1.5+:** S/M/L/XL sizes, vertical, centered, range, stops, value indicator

### 7.3 Built components — Tier 3 (v0.4.0) ✅

시스템 완전성 5 컴포넌트. CHANGELOG v0.4.0 entry에 상세.

| # | M3 § | Component | Key behavior |
|---|---|---|---|
| 19 | §19 | List | 56/72/88 line variants, expressive shape state machine (§19.6), segmented variant |
| 21 | §21 | Progress indicator | Linear (4px) + circular (40px), determinate/indeterminate, reduced-motion fallback |
| 5 | §5 | Button group | Standard (15% widen + secondary-container fill) + connected (per-size inner corners, 50% pill on selected) |
| 33 | §33 | Toolbar | Docked + floating variants × Standard + Vibrant colors |
| 12 | §12 | Carousel | Multi-browse + hero + uncontained, custom property layout, scroll-snap |

### 7.4 Built components — Tier 4 (v0.4.0) ✅

Deferred 컴포넌트. CHANGELOG v0.4.0 entry에 상세.

| # | M3 § | Component | Key behavior |
|---|---|---|---|
| 9 | §9 | FAB menu | 56×56 close + stacked items, 3 color sets, `.is-open` toggle |
| 10 | §10 | Split button | 2-button composition, 50% pill on `aria-expanded="true"` |
| 15 | §15 | Date picker | Visual structure only — calendar grid + date cell states. Logic = author/library |
| 16 | §16 | Time picker | Visual structure only — input/dial variants + `.is-24h` modifier. Drag = JS |

---

## 8. Cross-cutting patterns

### 8.1 BEM naming convention

```
.component                  ← block
.component--variant         ← modifier
.component__slot            ← element
.component.is-state         ← state (mutually exclusive)
.component[aria-pressed]    ← state (ARIA-driven)
```

**예:**
```html
<button class="ax-button ax-button--filled has-state-layer is-loading">
  <span class="ax-button__icon">...</span>
  <span class="ax-button__label">Save</span>
</button>
```

**Phase 2 WP block style 매핑:**
```
core/button + is-style-filled       ← BEM (.ax-button--filled)
core/group + is-style-card-elevated ← BEM (.card--elevated)
```

WP의 `is-style-{name}`은 *블록별 namespace*에서 작동 (collision 가능). BEM의 `--variant`는 *컴포넌트 namespace*. 둘은 다른 시스템 — Phase 2에서 별도 매핑 필요.

### 8.2 State layer mixin (`.has-state-layer`)

§5.1 참조. 모든 인터랙티브 컴포넌트가 *opt-in*으로 적용:

```html
<button class="ax-button has-state-layer">…</button>
<a class="card card--interactive has-state-layer">…</a>
<button class="chip chip--filter has-state-layer" aria-pressed="false">…</button>
```

**예외 (직접 구현):**
- Text field — own active indicator (focus ring 대신)
- Search bar — own state-layer (different scope)
- Snackbar action — Pattern A inline

### 8.3 Modal scrim (shared dialog + sheet)

**Single class:** `.modal-scrim` (M3 §0.12).

```css
.modal-scrim {
  position: fixed;
  inset: 0;
  background-color: var(--md-sys-color-scrim);
  opacity: 0;
  pointer-events: none;
  z-index: 1000;
  transition: opacity var(--md-sys-motion-curve-default-effects-duration)
              var(--md-sys-motion-curve-default-effects);
}
.modal-scrim[data-open="true"],
.modal-scrim.is-open {
  opacity: 0.32;
  pointer-events: auto;
}
```

**Dialog + sheet 둘 다 사용.** `<dialog>::backdrop`은 *transparent으로 무효화* (이중 darkening 방지):

```css
.dialog::backdrop {
  background: transparent;
  opacity: 0;
}
```

### 8.4 Z-index layering

```
0       — base content
1       — elevated content (cards on hover)
1000    — modal-scrim
1001    — dialog, sheet
1100    — snackbar (suggested, above modals)
```

**Stacking context isolation 필요한 곳:**
- `.sg-frame--sheet`, `.sg-frame--dialog` — `isolation: isolate` (style guide demo가 modal-scrim 위로 떠오르는 것 방지)

### 8.5 Body scroll lock (modal)

Modal이 열릴 때 body 스크롤 잠금:

```css
html.has-modal-open {
  overflow: hidden;
}
```

JS에서 modal open/close 시 클래스 toggle:

```js
function open(key) {
  // ...modal logic
  document.documentElement.classList.add("has-modal-open");
}
function close() {
  // ...
  document.documentElement.classList.remove("has-modal-open");
}
```

`<dialog>::showModal()` 사용 시엔 native가 처리하지만, 본 시스템은 `<dialog>` element를 *manual scrim*으로 통일 사용. 따라서 명시적 lock 필요.

### 8.6 Focus indicator strategy

**M3 §0.11:** focus indicator는 outer +2px 또는 inner -3px 둘 중 하나.

**Default — Outer +2px (글로벌, base.css):**

```css
*:focus-visible {
  outline: 2px solid var(--md-sys-color-secondary);
  outline-offset: 2px;
}
```

**Inner -3px — 밀집 layout 컴포넌트:**

```css
.tabs__item:focus-visible,
.search-bar:focus-visible {
  outline-offset: -3px;
}
```

**Own indicator (focus ring 대체):**
- Text field uses active indicator (box-shadow), not outline

### 8.7 Active indicator (navigation, tabs, chips)

M3 §0.13 패턴. *secondary-container fill + on-secondary-container glyph*.

| 컴포넌트 | 형태 | 위치 |
|---|---|---|
| Nav rail collapsed | 56×32 pill | icon 주위 |
| Nav rail expanded | 56×full-width pill | item 전체 |
| Nav bar | 56×32 pill | icon 주위 |
| Tabs primary | 3px line, top-rounded | 하단 (label 아래) |
| Tabs secondary | 2px line, flat | 하단 |
| Chip filter (selected) | secondary-container fill | chip 전체 |

---

## 9. Known caveats

발견된 시스템적 한계 + 우회 방법.

### 9.1 `:has()` browser support

**사용처:**
- Text field disabled state (`.text-field:has(:disabled)`)
- Text field textarea variant (`:has(textarea.text-field__input)`)
- Dialog headline center-align (`.dialog:has(.dialog__icon)`)

**Browser support:**
- Safari 15.4+
- Chrome 105+ (2022년 8월)
- Firefox 121+ (2023년 12월)

**Older browser fallback:** v1.5+에서 author opt-in 클래스 제공 가능 (`.text-field.is-disabled`, `.text-field.has-textarea`). 현재 v0.2.0은 modern browser 가정.

### 9.2 Outlined text-field notch backdrop assumption

**문제:** Outlined text field가 floated 라벨일 때 outline을 *break*하는 효과 — label 뒤에 surface 색을 깔아 outline이 끊긴 것처럼 보이게.

```css
.text-field--outlined .text-field__label {
  background-color: var(--md-sys-color-surface);  /* notch backdrop */
  padding-inline: var(--space-xs);
}
```

**한계:** Text field가 *`surface` 위에 있다고 가정*. `surface-container-highest` 위에선 backdrop 색이 mismatch.

**v1.5+ fix 후보:**
```css
.text-field--outlined {
  --text-field-notch-bg: var(--md-sys-color-surface);  /* 외부 override 가능 */
}
.text-field--outlined .text-field__label {
  background-color: var(--text-field-notch-bg);
}
```

### 9.3 Search bar disabled — Pattern A (not D)

Text field는 Pattern D (input field 전용 mixed fade), 그러나 Search bar는 *form input owner*가 아님 — 더 가까운 유사체는 *disabled tonal button*.

**Search bar 적용:** Pattern A (10% bg, 38% label).

Text field와 다른 패턴 사용은 *의도된 결정*. 인라인 코멘트로 명시.

### 9.4 Chip icon selector mismatch (resolved)

**과거 issue:** `.chip__leading-icon > svg` selector가 `<svg class="chip__leading-icon">` 형태에 매칭 안 됨 (자식이 아닌 SVG 자체). SVG 사이즈 미적용 → 기본 사이즈로 폭주.

**Fix:** selector 확장:
```css
svg.chip__leading-icon,
svg.chip__trailing-icon,
.chip__leading-icon  > svg,
.chip__leading-icon  > .ax-icon,
.chip__trailing-icon > svg,
.chip__trailing-icon > .ax-icon {
  width: var(--_chip-icon);
  height: var(--_chip-icon);
}
```

**Avatar leading은 영향 없음:** `.chip__leading-icon.ax-avatar` (자식 selector 아님)이라 매칭 안 됨, 24px (`is-size-xs` 토큰)로 정상 작동.

### 9.5 Viewport units `100vw`/`100vh` (resolved)

**과거 issue:** `.dialog--full-screen { width: 100vw; height: 100vh; }` — 세로 스크롤바 있을 때 *스크롤바 너비만큼 더 넓어져 가로 스크롤* 발생.

**Fix:** `width`/`height` 제거, `inset: 0`만 사용. `position: fixed; inset: 0;`이 자체로 viewport 가득 채움.

### 9.6 Z-index stacking context (resolved)

**과거 issue:** style-guide.html에서 `.sg-frame--sheet .sheet { position: absolute }` 로 override했으나 components.css의 `z-index: 1001`이 root context에서 평가됨 → modal-scrim (1000) 위로 떠오름.

**Fix:** Frame 부모에 `isolation: isolate` 추가:
```css
.sg-frame--sheet,
.sg-frame--dialog {
  isolation: isolate;
}
```

**일반화된 패턴:** *demo / preview 컨테이너* 안에 *fixed/high-z-index* 컴포넌트를 *absolute*로 inline override할 때는 항상 `isolation: isolate` 필요.

### 9.7 Avatar `is-size-xs` = 24px (chip leading slot)

M3 §14.5 — Input chip의 leading slot이 *24px avatar*. 32px (`is-size-sm`)는 너무 큼.

**Fix:** components.css §1 Avatar에 4번째 size 추가:
```css
.ax-avatar.is-size-xs { --_ax-avatar-size: 24px; }
```

Chip icon 18px과 *다른 사이즈*는 의도된 spec — avatar는 사람/identity, icon은 액션.

### 9.8 Type utility classes added retroactively to base.css

`prompt-v2.md`에서 typescale system token 명시했지만 *유틸리티 클래스* 명시는 없었음. style-guide.html에서 사용 시 발견 → base.css §6.5에 retroactively 추가 (15 클래스).

새 토큰 도입 안 됨 — 기존 sys-typescale 토큰 사용. Future system이 같은 함정에 안 빠지도록 prompt-v2 §2.4에 명시 추가 권장 (v3 사이클).

### 9.9 Token naming — diverges from Material Web (intentional)

Material Web (`material-web.dev`) uses `--md-{component}-{prop}-color` flat naming (e.g. `--md-radio-icon-color`, `--md-checkbox-selected-icon-color`). This is *consumer-friendly* — each component exposes its own token surface for instance-level theming.

Axismundi follows the **M3 sys-tier convention** instead — components reference `var(--md-sys-color-*)` directly. Per-component overrides use scoped `--_local` vars or `--comp-*` only when truly needed.

**Why the divergence:**
- Material Web is a web-component library; consumer overrides are a primary use case.
- Axismundi is a system-builder context — theming happens at the sys layer (single token swap → all components follow). Adding ~330 component-flat tokens (33 components × ~10 props) duplicates sys layer for marginal flexibility.
- M3 spec docs themselves describe components as referencing sys roles, not exposing per-component tokens.

**When to revisit:**
- If Axismundi grows into a distributable theme + consumers want instance-level tweaks (e.g. "make this one checkbox green"), introduce `--comp-checkbox-*` or scoped vars selectively.
- *Don't* port Material Web's full token surface wholesale — too many tokens for too little gain in this context.

### 9.10 Text field markup — A11y/HTML standards refactor (v0.3.0 → v0.3.5)

Initial v0.3.0 refactor used `<label class="text-field__container">` as the visual wrapper. This violates HTML5: a `<label>` cannot contain labelable elements (`<button>`, `<a>`) other than the input it labels. Putting `<button class="ax-icon-button">` (clear, voice, dropdown) inside that `<label>` causes label-click → input-focus to fight the button's own click handler — broken interactions for users + screen readers.

**Fix (v0.3.5):**
- Container `<label>` → `<div class="text-field__container">`
- Floating label `<span class="text-field__label">` → `<label class="text-field__label" for="tf-id">`
- Each input gets a unique `id`, label uses explicit `for`/`id` pairing.
- Floating label keeps `pointer-events: none` so its position-absolute ghost doesn't block clicks on input/buttons. Input column 3 is full-width, so click-to-focus on the input itself works naturally — no JS hook needed for the small "container empty area" edge case.

**Grid auto-placement gotcha:**
DOM order = input first (required for `:placeholder-shown ~ .__prefix` selector). All slots have explicit `grid-column` placement, BUT auto-placement won't move backwards in a row — an item with `grid-column: 1` placed AFTER an item in column 3 spawns row 2. Fix: every slot now also includes `grid-row: 1` to stay locked on a single row regardless of DOM order.

---

## 10. WordPress mapping (Phase 2 preview)

> 이 섹션은 *Phase 2 진입 전 preview*. 자세한 매핑은 `BLOCK-COMPONENT-MAP.md` 참조.

### 10.1 theme.json bridge (settings + styles)

**Settings 매핑:**
```json
{
  "settings": {
    "color": {
      "palette": [
        { "slug": "primary",   "name": "Primary",   "color": "#6750A4" },
        { "slug": "secondary", "name": "Secondary", "color": "#625B71" }
      ]
    },
    "typography": {
      "fontSizes": [
        { "slug": "body-large", "name": "Body Large", "size": "16px" }
      ]
    },
    "spacing": {
      "spacingSizes": [
        { "slug": "xs", "name": "Extra Small", "size": "4px" }
      ]
    }
  }
}
```

**Block style hooks (settings.appearanceTools에서 활성):**
- Border radius → shape tokens
- Box shadow → elevation tokens
- Spacing → spacing tokens

### 10.2 Block bucket (A/C/D/E/F)

`BLOCK-COMPONENT-MAP.md` §0.1 참조.

| Bucket | 의미 | 예 |
|---|---|---|
| A | Core block + Block Style | Button → `core/button` + `is-style-filled` |
| B | Block Pattern (composition) | Post Card |
| C | Custom block (plugin) | Chip, Tabs |
| D | Template-part / FSE | App bar, Nav rail |
| E | Form plugin styling | Text field, Checkbox |
| F | Theme JS only | Snackbar, Tooltip |

### 10.3 Naming — three-layer separation

`BLOCK-COMPONENT-MAP.md` §0.2.

| Layer | 명명 | Namespace |
|---|---|---|
| Theme | `axismundi` | (테마 slug) |
| Generic blocks plugin | `m3-blocks` | `m3/*` |
| Domain blocks plugin | `axismundi-blocks` | `axismundi/*` |

**Generic vs Domain 결정:** 다른 테마에서도 쓸 수 있으면 `m3/*`, Axismundi 전용이면 `axismundi/*`.

### 10.4 Phase 2 work order

1. theme.json 완성 (현 starter → 풀 매핑)
2. Block Styles 등록 (`core/button` + 5 styles 등)
3. Block Patterns 작성 (Post Card, Profile Head, Composer 등)
4. Template parts (`header.html`, `footer.html`, `sidebar.html`)
5. Templates (`front-page.html`, `single.html`, `archive.html` 등)
6. Theme JS (snackbar, tooltip, dialog, sheet, ripple enhancement)
7. `m3-blocks` 플러그인 (generic M3 컴포넌트)
8. `axismundi-blocks` 플러그인 (SNS 도메인 composites)

---

## 11. Roadmap

### 11.1 v0.2.0 — 14 components built

✅ Phase 1A 완료. 시스템 기반 + 핵심 컴포넌트.

### 11.2 v0.2.5 — Tier 1 (마이크로블로그 핵심 7개) ✅

✅ Tier 1 완료. 마이크로블로그 prototype 핵심 7 컴포넌트 추가.

- FAB (§7) + Extended FAB (§8)
- Navigation bar (§23) — mobile bottom nav
- Badge (§2)
- Menu (§22)
- Tooltip (§34)
- Loading indicator (§20)

**Chunks (실제 진행):**
- E1: FAB + Extended FAB ✅
- E2: Nav bar + Badge ✅
- E3: Menu ✅
- E4: Tooltip + Loading ✅

### 11.3 v0.3.0 — Tier 2 (form 4개) + Text field 리팩토링 ✅

✅ Tier 2 완료. Form 컴포넌트 4개 + Text field 구조 리팩토링.

- Checkbox (§13), Radio (§25), Switch (§30), Slider (§27 XS standard horizontal만)
- Text field structural refactor (slot 5개 grid + supporting outside container + native validation + leading/trailing icons + clear button auto-hide)

### 11.4 v0.4.0 — Tier 3 + Tier 4 (시스템 완성 9개) ✅

✅ 33/33 spec 컴포넌트 빌드 완료. Tier 3 + Tier 4 묶어서 한 push.

**Tier 3 (5):** List (§19), Progress (§21), Button group (§5), Toolbar (§33), Carousel (§12)
**Tier 4 (4):** FAB menu (§9), Split button (§10), Date picker (§15), Time picker (§16)

**Chunks (실제 진행):** G1 (List+Progress) → G2 (Button group+Toolbar) → G3 (Carousel) → H1 (FAB menu) → H234 (Split+Date+Time)

**Phase 1B 마무리:**
- A11y refactor — text field markup `<label>` container → `<div>`, real `<label for>` (caveat 9.10)
- Accumulated visual fixes (BEM convention, button group selected state, carousel dark mode legibility, badge alignment, search bar form field, transition flicker)
- JS extraction — 5 inline `<script>` blocks → `scripts/style-guide.js` (348 lines)

### 11.5 v1.0.0-rc1 (current) — Phase 1B 종료 ✅

✅ 33 컴포넌트 카탈로그 완성. Phase 2 prototype 진입 대기.

### 11.6 v1.0.0 — Phase 2 진입

마이크로블로그 prototype 빌드 후:
- Feed prototype (Feed + Profile + Article views)
- WordPress block theme (`axismundi`)
- Phase 2 첫 milestone

### 11.7 Future — v1.5+

- Custom seed colors (Plum, Teal 등 swap 시스템)
- Static palettes 활용 (instance/tag color)
- `:has()` fallback (older browser)
- `--text-field-notch-bg` 토큰 (nested surface 처리)
- Sheet drag-to-dismiss JS
- Tab indicator slide animation
- Standard / detached sheet variants

---

## 12. Decision log

주요 결정 + rationale 요약. 자세한 history는 `audit-en.md`, `CHANGELOG.md` 참조.

### 12.1 Plum seed → M3 baseline (Phase-1 → v2)

**결정:** Material Theme Builder의 Plum seed 결과 폐기. M3 official baseline raw 값 사용.

**Rationale:**
- Material Theme Builder는 HCT 알고리즘으로 *seed에서 derive*. M3 공식 spec과 *drift* 발생
- Builder 결과에 deprecated 토큰 (`surface-tint`) 포함
- Baseline은 hand-curated, 권위적
- 본인이 *Plum*을 *임의로 채택*했다는 사실 (Claude Design 추측) 발견

**Trade-off:** brand color 부재. v1.5+에서 custom seed 도입 시 *명시적 결정*으로 진행.

### 12.2 RGB channels → hex format

**결정:** `--md-sys-color-primary: 106 84 141` → `--md-sys-color-primary: #6750A4`.

**Rationale:**
- `color-mix()` 함수가 valid CSS color를 받음 (RGB channels는 `rgb()` 안에서만 작동)
- Hex가 `color-mix()`, `rgb()`, 모든 CSS color 함수와 호환
- Single format으로 시스템 통일

### 12.3 5-tier shape → 10-tier

**결정:** `xs/sm/md/lg/xl` → M3 Expressive 풀 10 tier + 3 directional.

**Rationale:**
- M3 Expressive는 `large-increased`, `extra-large-increased`, `extra-extra-large` 추가
- Component-specific shape 결정에 정확한 tier 선택 가능
- 본인 시스템이 M3 spec compliance 유지하려면 풀 tier 필수

### 12.4 State opacity 0.12 → M3 Expressive correct

**결정:** `0.08/0.12/0.12` → `0.08/0.10/0.10/0.16`.

**Rationale:**
- M3 1.x: 0.08/0.12/0.12 — older spec
- M3 Expressive: 더 부드러운 focus/pressed (0.10) + dragged 추가 (0.16)
- Phase-1이 1.x 값 사용 → audit에서 잘못 발견 → v2 정정

### 12.5 Inverse colors added

**결정:** `inverse-surface`, `inverse-on-surface`, `inverse-primary` 토큰 추가.

**Rationale:**
- Snackbar (light mode에서 어두운 토스트) 같은 use case에 필수
- M3 Expressive 표준
- Phase-1에 누락 → audit에서 발견

### 12.6 Surface tint dropped (deprecated)

**결정:** `--md-sys-color-surface-tint` 토큰 제거.

**Rationale:**
- M3 Expressive에서 deprecated
- Elevation은 surface container 단계 + shadow (light mode)로 표현
- Surface tint는 *primary 색을 surface에 섞는* 시각 효과였으나 가독성 ↓로 폐기

### 12.7 Motion duration aliases → spring physics

**결정:** `--motion-duration-fast: 150ms` 같은 단순 alias → spring physics + cubic-bezier 24 토큰.

**Rationale:**
- M3 Expressive의 정확한 motion 표현
- Native API (Compose, SwiftUI) 호환 (raw spring tokens)
- CSS는 cubic-bezier로 spring 근사 — 12 curves
- 단순 duration alias로는 *어떤 spring처럼 부드러움*을 표현 못 함

### 12.8 Form 컴포넌트 — 시스템 외형 정의 + Phase 2 플러그인 styling

**결정:** Checkbox / Radio / Switch / Slider / Text field — *외형 정의는 시스템*, *form 처리는 외부 플러그인*.

**Rationale:**
- Theme이 form input 직접 처리하면 다른 form 플러그인과 충돌
- 외형은 시스템이 정의해서 Phase 2에서 *플러그인 output에 같은 클래스* 적용
- 디자인 시스템의 *시각 truth-source* 보존 + *form 처리는 플러그인 ecosystem과 협력*

### 12.9 Style guide scope = M3 풀스펙 (33 components)

**결정:** Style guide에 *모든 33 spec 컴포넌트* 포함. Tier 분류는 *빌드 순서*지 *포함/제외*가 아님.

**Rationale:**
- 디자인 시스템의 *완전성* — 시각 카탈로그가 시스템 truth-source
- Bucket 분류 (A/C/D/E/F)는 Phase 2 *구현 경로*. Style guide는 *모든 컴포넌트 외형*을 보여줘야 함
- Form 컴포넌트 (E bucket)도 외형은 시스템이 정의 → style guide에 표시 필수
- 초기 결정 (BRIEF priority 7개만)이 작업 진행 중 *시스템 완전성*과 충돌 → 수정

---

## Appendix A — Quick reference

### Token lookup

| 찾는 것 | 위치 |
|---|---|
| 색상 hex 값 | `M3-COLOR-TOKEN.md` |
| Typescale 값 | `OVERVIEW.md` §3.1 |
| Spacing | §4.1 |
| Shape | §4.2 |
| Motion curve | §5.4 |
| State opacity | §5.2 |

### Component class lookup

| 컴포넌트 | Root class | Variants |
|---|---|---|
| Avatar | `.ax-avatar` | `.is-size-xs/-sm/-lg` |
| Button | `.ax-button` | `.ax-button--filled/-tonal/-elevated/-outlined/-text` |
| Icon button | `.ax-icon-button` | `.is-filled/.is-tonal/.is-outlined/.is-standard` |
| Card | `.card` | `.card--filled/-elevated/-outlined`, `.card--interactive` |
| App bar | `.app-bar` | `.app-bar--small/-medium-flexible/-large-flexible` |
| Nav rail | `.nav-rail` | `.is-narrow`, `.is-expanded` |
| Tabs | `.tabs` | `.tabs--primary/-secondary` |
| Text field | `.text-field` | `.text-field--filled/-outlined`, `.is-error` |
| Search bar | `.search-bar` | (single variant) |
| Chip | `.chip` | `.chip--assist/-filter/-input/-suggestion` |
| Dialog | `.dialog` | `.dialog--basic/-full-screen` |
| Sheet | `.sheet` | `.sheet--bottom-modal/-side-modal` |
| Snackbar | `.snackbar` | (variants via slot composition) |
| Divider | `.ax-divider` | `.is-style-inset/-middle-inset` |

### Cross-cutting class lookup

| Pattern | Class |
|---|---|
| State layer | `.has-state-layer` |
| Modal scrim | `.modal-scrim` |
| Body scroll lock | `html.has-modal-open` |
| Active state | `.is-active` |
| Selected state | `[aria-pressed="true"]` or `.is-selected` |
| Error state | `.is-error` |
| Disabled | `:disabled` or `[aria-disabled="true"]` |
| Typescale utility | `.t-{role}` (e.g. `.t-display-large`) |
| Emphasis modifier | `.t-label-large.is-prominent` |

### Theme switcher (JS)

```js
// Read
const theme = localStorage.getItem("axismundi.theme") ?? "auto";

// Apply
if (theme === "auto") {
  document.documentElement.removeAttribute("data-theme");
} else {
  document.documentElement.setAttribute("data-theme", theme);
}
```
