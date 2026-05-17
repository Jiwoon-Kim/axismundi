# Axismundi Text Field — 구현 매핑

본 청크는 M3 text-field의 Axismundi 구현을 다룬다. M3 spec 사양(측정값 /
anatomy / configurations)은 별도 청크 `text-fields-spec.md` 참조.

## 위치

- **파일**: `Axismundi/stylesheets/components.css`
- **섹션**: §9 Text field — M3-COMPONENT-SPECS §32
- **라인 범위**: 930–1468 (Chunk C-1: Input primitives)
- **인라인 문서 블록**: lines 929–1023 (~95줄, 마크업 / a11y 주의사항 / DOM
  order 제약 / floating label 패턴 / prefix-suffix 가시성 / validation /
  active indicator / outlined notch / 인터랙티브 아이콘 합성)

## Markup 패턴

```html
<div class="text-field text-field--filled">
  <div class="text-field__container">          <!-- div, NOT label -->
    <input class="text-field__input" id="tf-id"
           placeholder=" "
           required pattern="..." maxlength="..." />
    <label class="text-field__label" for="tf-id">Email</label>
    <span class="text-field__leading-icon">…</span>     <!-- optional -->
    <span class="text-field__prefix">₩</span>           <!-- optional -->
    <span class="text-field__suffix">KRW</span>         <!-- optional -->
    <button class="ax-icon-button is-standard has-state-layer
                   text-field__trailing-icon">…</button><!-- optional -->
    <span class="text-field__error-icon">…</span>       <!-- auto -->
  </div>
  <div class="text-field__bottom">                       <!-- optional -->
    <span class="text-field__supporting">Helper</span>
    <span class="text-field__counter">0 / 280</span>
  </div>
</div>
```

### 두 가지 markup 제약

1. **Container는 `<div>` (NOT `<label>`)** — HTML5는 labelable 요소
   (`<button>`, `<a>` 등)를 `<label>` 자손으로 두면 안 됨. trailing-icon이
   `<button>`인 경우 label-click → input-focus가 button 클릭 핸들러와
   충돌해서 clear/voice/dropdown 액션이 깨짐. 라벨은 별도 `<label>` 요소로,
   `for`/`id` 페어링 명시. (v0.3.5 fix)

2. **Input이 DOM 첫 위치** — 모든 슬롯 spans/labels은 input의 후행
   sibling이어야 함. `:placeholder-shown ~ .__prefix` 같은 selector가
   forward-only sibling combinator(`~`)를 쓰기 때문. 시각적 column 배치는
   각 슬롯의 `grid-column`로, 모든 슬롯에 `grid-row: 1` 강제 → DOM 순서와
   무관하게 한 행 유지.

## 5-column grid

`.text-field__container`는 `grid-template-columns: auto auto 1fr auto auto`:

| Column | Slot | grid-column |
|---|---|---|
| 1 | leading-icon | 1 |
| 2 | prefix | 2 |
| 3 | input | 3 |
| 4 | suffix | 4 |
| 5 | trailing-icon / error-icon (mutually exclusive) | 5 |

`column-gap: var(--space-sm)`, `padding-inline: var(--_tf-px)`.

## 커스텀 프로퍼티 + 토큰

```css
.text-field {
  --_tf-h: 56px;                                    /* M3 56dp */
  --_tf-px: var(--space-md);                        /* = 16px */
  --_tf-label-rest-size:  var(--md-sys-typescale-body-large-size);
  --_tf-label-rest-lh:    var(--md-sys-typescale-body-large-line-height);
  --_tf-label-float-size: var(--md-sys-typescale-body-small-size);
  --_tf-label-float-lh:   var(--md-sys-typescale-body-small-line-height);
}
```

토큰 사용 컨벤션 준수: `var(--md-sys-color-*)` 직접 사용 (raw-triple 패턴
없음). 색상은 surface/on-surface/on-surface-variant/primary/error 계열,
typescale은 body-large(rest input/label) + body-small(floated label/
supporting/counter), motion은 fast-spatial(label float) + fast-effects
(color/box-shadow transitions).

## Floating label — no-JS 트릭

```css
.text-field__input { padding-block: var(--space-md) 0; }  /* reserve top space */
.text-field__input::placeholder { color: transparent; }    /* hide placeholder char */

.text-field__label { /* rest 위치: vertically centered */
  position: absolute;
  inset-inline-start: var(--_tf-px);
  inset-block-start: 50%;
  transform: translateY(-50%);
}
.text-field__container:focus-within .text-field__label,
.text-field__input:not(:placeholder-shown) ~ .text-field__label {
  top: 8px;                                          /* float to top 8dp */
  transform: translateY(0);
  font-size: var(--_tf-label-float-size);
}
```

핵심: `placeholder=" "` (스페이스 한 칸) + `:placeholder-shown` 페어로 "input
비어있음"을 CSS만으로 검출. 비어있고 focus 없음 → label rest. focus 또는
populated → label float.

## Prefix / Suffix 가시성 (rest 시 숨김)

```css
.text-field__container:not(:focus-within)
  .text-field__input:placeholder-shown ~ .text-field__prefix,
.text-field__container:not(:focus-within)
  .text-field__input:placeholder-shown ~ .text-field__suffix {
  visibility: hidden;
}
```

rest 상태에서 label과 prefix가 같은 baseline 충돌하기 때문. focus 또는
populated에서 노출. 또한 `pointer-events: none` + `user-select: none`로
실수 인터랙션 차단.

## Outlined notch — surface backdrop 트릭

```css
.text-field--outlined .text-field__container {
  box-shadow: inset 0 0 0 1px var(--md-sys-color-outline);
}
.text-field--outlined .text-field__container:focus-within {
  box-shadow: inset 0 0 0 2px var(--md-sys-color-primary);
}
.text-field--outlined .text-field__container:focus-within .text-field__label,
.text-field--outlined .text-field__input:not(:placeholder-shown)
  ~ .text-field__label {
  background-color: var(--md-sys-color-surface);    /* outline 끊는 backdrop */
  padding-inline: var(--space-xs);                  /* 양쪽 4dp gap */
  top: calc(var(--_tf-label-float-lh) * -0.5);      /* outline 위로 올림 */
}
```

**한계**: 부모 surface가 `surface` 토큰이라고 가정. 다른 surface 컨테이너
위에 올라가면 backdrop 색이 안 맞음. `OVERVIEW.md` caveat 9.2 (v1.5+
tokenization 계획) 참조.

### Outlined + leading icon → label 위치 cancel

Leading icon이 있는 경우 rest label은 icon 뒤로 shift. 그러나 outlined
variant에서 floated 시점에는 notch가 outline 좌측 모서리에 고정 (icon 무시):

```css
.text-field--with-leading .text-field__label {
  inset-inline-start: calc(var(--_tf-px) + var(--comp-icon-size-md) + var(--space-sm));
}
.text-field--outlined.text-field--with-leading
  .text-field__container:focus-within .text-field__label {
  inset-inline-start: calc(var(--_tf-px) - var(--space-xs));   /* cancel */
}
```

## Validation — 수동 + 네이티브

| 경로 | 트리거 | Selector |
|---|---|---|
| 수동 | 저자가 `.is-error` 추가 | `.text-field.is-error` |
| 네이티브 | HTML5 (`required`/`pattern`/`maxlength`/`type=email`) → 사용자 인터랙션 후 | `.text-field:has(.text-field__input:user-invalid)` |

두 경로 동일 스타일 공유. `:user-invalid` 사용 이유: `:invalid`는 페이지
로드 시점에 required-but-empty 필드에 즉시 발화되어 UX 나쁨. `:user-invalid`
는 사용자 인터랙션 후에만 발화 → 폼 UX 컨벤션과 맞음.

## Error 상태 cascade — 코드 매핑

spec의 5요소 cascade가 코드에 그대로 매핑:

| Spec 요소 | 구현 selector |
|---|---|
| label color | `.is-error .text-field__label`, `:focus-within .__label` 포함 (specificity 우선) |
| input value color | `.is-error .text-field__input { color, caret-color, -webkit-text-fill-color: error }` |
| supporting / counter | `.is-error .text-field__supporting`, `.__counter` |
| caret color | `caret-color: error` (위 input 룰에 포함) |
| active indicator | filled `box-shadow: inset 0 -2px 0 error`, outlined `inset 0 0 0 2px error` |

추가: trailing-icon ↔ error-icon `display` 토글로 같은 grid col 5에서 상호
배타 노출.

`-webkit-text-fill-color`는 Safari/iOS UA가 input value 색을 plain `color`
보다 우선시하는 케이스 대응. password type의 마스킹 글리프(•••)는
`currentColor` 사용 → `color: error` 필요.

## Disabled — Pattern D (§0.8)

5요소 individual opacity 적용 (Pattern A의 wrapper-level 0.38이 아님):

| 요소 | 처리 |
|---|---|
| filled container fill | on-surface @ 4% |
| filled active indicator | on-surface @ 38% |
| outlined outline | on-surface @ 12% |
| label / icons / prefix / suffix / supporting / counter | on-surface @ 38% |
| input value | on-surface @ 38% (Webkit `text-fill-color` override 포함 — UA `GrayText` 덮음) |

`color-mix(in srgb, var(--md-sys-color-on-surface) {n}%, transparent)` 패턴.
selector는 `:has(.text-field__input:disabled)` 또는 `[aria-disabled="true"]`
양쪽 지원.

## 자주 틀리는 부분 (γ-3 ultrareview / vqa1+vqa2 lessons)

1. **Suffix를 input과 같은 셀로 합치지 말 것.** input은 좌정렬 유지, suffix는
   별도 grid column 4 우정렬. (vqa2 deferred item closure)
2. **Outlined floated label은 leading icon 무시.** notch는 항상 container
   시작 모서리. line 1213–1221 selector로 cancel.
3. **Filled active indicator는 bottom-only.** full container border 아님.
4. **Outlined focus는 2px**, 인라인 주석 line 996의 "3px"는 stale (실제
   코드 + spec 모두 2px). → `./source_raw/FOLLOW-UPS.md` FU-001 참조.
5. **Error focus 시 label color cascade는 specificity 충돌 주의.** variant
   별 `:focus-within .__label { color: primary }`가 specificity 더 높을 수
   있어, `.is-error .__container:focus-within .__label` 형태로 명시 필요.
   (line 1324–1334)
6. **DOM order: input 먼저.** sibling combinator `~` forward-only.
7. **Outlined surface backdrop은 부모 surface 토큰 가정** (caveat 9.2).
8. **Native validation은 `:user-invalid` 사용** (`:invalid` 아님).

## References

- **코드**: `Axismundi/stylesheets/components.css` §9 (lines 930–1468)
  - 인라인 문서 블록: 929–1023
  - 변수/컨테이너: 1024–1068
  - 슬롯/그리드: 1070–1144
  - input/label: 1146–1221
  - bottom row: 1223–1245
  - variants (filled/outlined): 1247–1301
  - error state: 1303–1363
  - disabled: 1365–1407+
- **Spec 매핑 청크**: `./knowledge/material/text-fields-spec.md`
- **검증 자료**: `./source_raw/reports/vqa2-spec-audit.md` (특히 "Codebase
  audit results" 표 — 각 spec 요구사항 ↔ 우리 구현 매핑)
- **알려진 한계**: `Axismundi/docs/OVERVIEW.md` caveat 9.2 (outlined notch
  backdrop의 nested-surface 한계, v1.5+ tokenization 계획)
- **Follow-up**: `./source_raw/FOLLOW-UPS.md` FU-001 (line 996 stale 주석)
