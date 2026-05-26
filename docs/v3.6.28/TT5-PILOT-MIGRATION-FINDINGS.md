# TT5 → Pilot 변환 기록 — v3.6.28

> **목적**: TT5 1.5를 MD3 Pilot 테마로 이식하는 과정에서 관찰된 사실, 결정 근거, 충돌 케이스의 증거 기록.  
> **retention class**: keep (변환 데이터셋 — 다음 템플릿 개발의 베이스라인)  
> **commits**: `59ce22c`, `0e48845`, `c767335`, `92affe7`, `b78adc4`

---

## 1. 세션 목적

- MD3 적용 TT5 베이스라인 Pilot 생성 (변환 데이터 수집)
- 새 템플릿 개발을 위한 베이스라인 설정
- 웹디자인 결정 온톨로지(`@layout-ontology v0.1`) 실전 적용

---

## 2. 작업 범위

### 2-A. TT5 Lab 레이아웃 추출 (선행 세션 완료)

`axismundi-lab/layouts/tt5-*.html` 13개 파일, `@tt5-source` + `@layout-ontology v0.1` 어노테이션 포함.

| 유형 | 파일 수 |
|---|---|
| templates (index/home/404/search/archive/page/page-no-title/single) | 8 |
| parts (header/footer/sidebar/header-large-title/vertical-header) | 5 |

소스 권위: `twentytwentyfive.1.5/twentytwentyfive/` (공식 TT5 1.5 릴리즈)

### 2-B. Pilot 템플릿/패턴 교체

| 항목 | 결과 |
|---|---|
| patterns/ | 기존 6개 → 12개 (QA-only 5개 삭제, TT5-derived 11개 신규/재작성) |
| templates/ | 8개 전부 TT5 블록 구조로 교체 |
| parts/ | 2개 → 3개 (sidebar 추가) |
| ax-theme-switcher | header.php에서 dev-controls.php로 분리 |
| QA 패턴 (button-actions/card-list/hero/prose-sample/search-section) | 삭제 |

---

## 3. 충돌 케이스 및 결정

### 3-1. Spacing slug 불일치

**관찰**: 이식된 패턴들이 `var:preset|spacing|60` 등 TT5 숫자 slug를 참조하는데, Pilot `theme.json`에는 xs/sm/md/lg/xl/xxl 문자 slug만 있었음. 모든 padding/margin/gap 무효화.

**결정**: TT5 숫자 slug(10–80)를 theme.json에 추가, 값은 MD3 `--space-*` 토큰으로 매핑. 새 MD3 토큰 추가 금지.

**매핑**:
```
slug 10 → var(--space-xs)  = 4px
slug 20 → var(--space-sm)  = 8px
slug 30 → var(--space-md)  = 16px
slug 40 → var(--space-lg)  = 24px
slug 50 → var(--space-xl)  = 32px
slug 60 → calc(var(--space-xl) + var(--space-sm))
slug 70 → calc(var(--space-xl) + var(--space-md))
slug 80 → calc(var(--space-xl) * 2)
```

`60–80`은 새 토큰을 만들지 않고 기존 MD3 spacing token 조합으로만 표현했다. 이 값들은 TT5 clamp scale 복제가 아니라 Pilot MD3 token vocabulary 안에서의 compatibility alias다.

### 3-2. FontSize slug 불일치

**관찰**: 패턴의 `"fontSize":"x-large"` 등이 Pilot에서 미매핑. TT5: small/medium/large/x-large/xx-large. Pilot: MD3 typescale slug만 존재.

**결정**: TT5 font alias 5개를 MD3 `--md-sys-typescale-*-size` CSS var로 추가. 기존 MD3 slug 유지.

```
small    → var(--md-sys-typescale-body-small-size)
medium   → var(--md-sys-typescale-body-medium-size)
large    → var(--md-sys-typescale-title-large-size)
x-large  → var(--md-sys-typescale-headline-medium-size)
xx-large → var(--md-sys-typescale-headline-large-size)
```

### 3-3. `.alignfull` shim 충돌 (핵심 버그)

**관찰**: footer title이 TT5에서 x=158(wide column 안)인데 Pilot에서는 x=0(viewport 끝)으로 붙음.

**원인**: Pilot `blocks.css`의 `.alignfull { inline-size: 100vw; margin-inline: calc(50% - 50vw) }` shim이 WP 7.0 core의 root-padding-aware alignment를 무시하고 100vw로 강제 돌파.

**확인 경로**:
1. TT5 테마로 전환 → footer title x=158, width=1340
2. Pilot(shim 존재) → footer title x=0, width=1656
3. Pilot(shim 제거 후) → footer title x=158 일치

**결정**: `useRootPaddingAwareAlignments: true` + `styles.spacing.padding` 설정 테마에서 `.alignwide`/`.alignfull` geometry는 blocks.css에서 건드리지 않음. `.alignleft`/`.alignright`/`.aligncenter` float 처리만 CSS에서 담당.

**추가 설정**: `theme.json styles.spacing` 에 TT5와 동일한 root padding 추가:
```json
"spacing": {
  "blockGap": "var(--space-md)",
  "padding": { "left": "var:preset|spacing|50", "right": "var:preset|spacing|50" }
}
```

### 3-4. TT5 accent-6 토큰 매핑

**관찰**: TT5 패턴들이 `--wp--preset--color--accent-6`(`color-mix(in srgb, currentColor 20%, transparent)`)을 border 색상으로 사용. Pilot 팔레트에 accent-6 없음.

**결정**: `accent-6` → `outline-variant` 매핑. 시각적 의도(낮은 대비 border) 동일.

### 3-5. 404 이미지 placeholder

**관찰**: TT5 hidden-404 패턴의 이미지 컬럼은 실제 미디어 참조. DB 없는 wp-env 환경에서 비어 보임.

**결정**: `has-surface-variant-background-color` Cover 블록으로 대체. MD3 surface-variant가 시각적으로 유사한 역할.

---

## 4. TT5 vs Pilot 구조적 차이 (베이스라인 기록)

| 항목 | TT5 1.5 | Pilot v3.6.28 |
|---|---|---|
| spacing 슬러그 체계 | 숫자(20–80), clamp() 반응형 | xs/sm/md/lg/xl + 숫자(10–80), MD3 token + calc 조합 |
| fontSize 체계 | small/medium/large/x-large/xx-large | MD3 typescale 15개 + TT5 alias 5개 |
| root blockGap | 1.2rem | var(--space-md) = 16px |
| root padding | spacing\|50 (clamp 30–50px) | spacing\|50 = var(--space-xl) = 32px |
| color palette | accent-1~6 (8개) | MD3 sys colors (24개) |
| alignfull 처리 | core 위임 | core 위임 (shim 제거됨) |
| footer 로고 | site-logo 블록 | site-logo 블록 (DB 로고 없으면 빈 상태) |
| theme toggle | 없음 | dev-controls.php 별도 패턴 |

---

## 5. DB 상태 관련 관찰

- `site-logo`: DB에 로고 설정 없으면 footer에서 빈 상태. WP 동작 — 테마 버그 아님.
- `site-tagline`: blogdescription 비어 있으면 출력 없음. 동일.
- wp-env destroy 후 기본 콘텐츠: Hello world! + Sample Page + Privacy Policy. TT5 템플릿 검증에 충분.
- wp-env `.wp-env.json` core pin: `WordPress/WordPress#7.0` (commit `c767335`). 오프라인 모드 캐시 이슈로 `.wp-env` 캐시에 `latestWordPressVersion: 7.0` 수동 설정 필요했음.

---

## 6. 미해결 / 다음 세션 후보

- `tt5-single.html`: `lab-text-field.css` 링크 있으나 미사용 (minor cleanup)
- header-large-title / vertical-header part: Pilot에 미이식 (해당 템플릿 없으면 미사용)
- QA 패턴 재작성: 새 TT5 구조 기반으로 QA 페이지 별도 생성 예정
- MD3 spacing 스케일 확장 필요 여부: slug 60–80이 기존 token 조합으로 표현됨 — 향후 `--space-2xl` 등 추가 여부는 사용자 결정 필요
