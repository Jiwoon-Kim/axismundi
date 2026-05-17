# M3 Text Field — 사양 (Spec)

M3 text-field는 사용자 텍스트 입력의 1차 primitive 컴포넌트다. 두 변형(variants):
**filled** 와 **outlined**. 본 청크는 spec 측정값 + anatomy + 상태 cascade를
다룬다. Axismundi 구현(마크업 / 토큰 / grid)은 별도 청크 `text-fields-impl.md`
참조.

## 공통 anatomy — 10 요소

Filled / Outlined는 시각적으로 다르지만 anatomy는 거의 동일하다 (동일 10요소
다이어그램 공유):

1. container · 2. leading-icon · 3. label (rest 위치) · 4. floated-label
· 5. trailing-icon · 6. active-indicator · 7. caret · 8. supporting (helper)
· 9. supporting-rest · 10. baseline

### Filled / Outlined — 시각적 차이 3가지

| 요소 | Filled | Outlined |
|---|---|---|
| Container 배경 | surface-container-highest | transparent |
| Active indicator | bottom border만 | 4-side outline |
| Floated label 처리 | top 8dp로 단순 이동 | top outline 위에 얹힘 + 4dp gap으로 outline 끊음 (notch) |

## 측정값 (M3 spec 17 reference images 직접 측정)

| Metric | Filled | Outlined |
|---|---|---|
| Container height (single-line) | 56dp | 56dp |
| H-padding (no icon) | 16 / 16 dp | 16 / 16 dp |
| H-padding (leading icon) | 12dp + 24dp(icon) + 16dp gap | same |
| H-padding (trailing icon) | 16dp gap + 24dp(icon) + 12dp | same |
| V-padding | 8dp top / 8dp bottom | 8dp top (rest) / 24dp inner content (focused) ※ |
| Active indicator (rest → focus) | 1dp → 2dp bottom, primary | 1dp → 2dp full outline, primary |
| Floated label 위치 | top 8dp, label-small typescale | top: -50% (sits ON outline border), label-small, 4dp gap each side로 outline 끊음 |
| Floated label `inset-inline-start` (no icon) | 16dp | 16dp (= container padding) |
| Floated label `inset-inline-start` (leading icon) | 16dp + icon + gap (icon 뒤로 shift) | 16dp (leading icon 무시 — notch는 container 시작에 고정) |
| Supporting text gap from container | 4dp | 4dp |
| Supporting text padding | 16dp L / 16dp R | same |
| Counter | supporting row 우측, 16dp gap from supporting, 16dp right pad | same |

※ outlined V-padding의 "24dp inner content (focused)" 값은 vqa2-spec-audit.md
원본에 그대로 기록된 표현. 해석상 모호한 부분이 있어 **검증 필요** 표시 —
구현 시 `text-fields-impl.md` 의 `padding-block: var(--space-md) 0` 값과
대조.

## 7 Configurations (Filled / Outlined 각 7가지 모두 검증됨)

1. With supporting text
2. With trailing icon (clear)
3. With leading icon
4. Leading + trailing icons
5. **Prefix** (예: `$1.43`) — input value 좌측, **별도 grid 셀**
6. **Suffix** (예: `25 lbs`) — input value는 좌정렬 유지, suffix는 별도
   grid column에서 우정렬. **시각적으로 합쳐 보이지만 별도 셀.** Input을
   우정렬하는 패턴이 아님.
7. Multi-line (textarea variant) — anatomy 본질적으로 동일, container만
   block 방향으로 content 따라 확장. Label은 여전히 floats to top, supporting
   text도 여전히 container 아래.

### Prefix / Suffix 가시성 규칙

Rest 상태(input 비어있고 focus 없음)에서는 **숨겨진다**. 이유: rest 상태의
label과 같은 baseline 충돌 방지. Focus 또는 populated(값 있음)에서만 노출.

### Counter 배치

Counter(예: `5 / 20`)는 container **밖**, supporting-text-row 영역에 우정렬.
Container 안이 아님 — supporting과 같은 row.

## Error 상태 — 5요소 cascade

Error는 단일 색 변경이 아니라 **cascade**:

- label color → `error`
- input value color → `error`
- supporting text color → `error`
- caret color → `error`
- active indicator color → `error`

추가로 trailing icon 슬롯에 `!` error icon 노출. **Trailing icon ↔ error
icon은 같은 슬롯을 공유 (mutually exclusive)** — 둘 다 동시 노출 불가.

Hover 또는 focus 진입 후에도 error 컬러는 유지된다.

## 검증 상태 (verification)

- 위 모든 수치/구조는 `./source_raw/reports/vqa2-spec-audit.md` (2026-05-08,
  M3 spec 17 reference images 직접 측정) 기반.
- m3.material.io 사이트는 SPA + JS 렌더링 → 자동 fetch 검증 불가능.
  URL은 사람의 수동 확인용 anchor 역할.
- spec 변경 가능성 있음 — 본 청크 사실은 **2026-05-08 시점 기준**.
- "outlined V-padding 24dp inner content" 표현 1건은 해석 모호 → 검증 필요.

## 참조

- **Primary (verified)**: `./source_raw/reports/vqa2-spec-audit.md`
  - 인용 (anatomy): "Same 10-element anatomy diagram (1 container + 2
    leading-icon + 3 label + 4 floated-label + 5 trailing-icon + 6
    active-indicator + 7 caret + 8 supporting + 9 supporting-rest +
    10 baseline)."
  - 인용 (filled/outlined 차이): "Differences are: (a) container
    background (filled = surface-container-highest, outlined = transparent),
    (b) active indicator location/shape (filled = bottom border, outlined =
    4-side outline), (c) floated label position when notched (outlined
    inserts label INTO the top outline edge with 4dp gap each side; filled
    simply moves up to top: 8dp)."
  - 인용 (suffix 셀): "input value `25` stays left-aligned, suffix `lbs`
    right-aligned in its own column. They are NOT visually merged."
  - 인용 (error cascade): "Label color, input value color, supporting text
    color, caret color, AND active indicator color all become error.
    Trailing icon area shows `!` error icon (mutually exclusive with normal
    trailing icon). Hovering / focusing in error state preserves error
    coloring."
- **Spec anchor (수동 확인용, 자동 검증 불가)**:
  https://m3.material.io/components/text-fields/specs
- **Axismundi 구현 매핑**: `./knowledge/material/text-fields-impl.md`
  (작성 예정 — 마크업 / 5-column grid / 토큰 / floating label trick /
  validation / 자주 틀리는 부분)
- **코드 위치**: `Axismundi/stylesheets/components.css` §9 (lines
  930–1468, Chunk C-1: Input primitives)
