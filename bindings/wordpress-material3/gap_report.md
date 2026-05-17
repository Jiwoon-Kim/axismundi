# v2.1a Binding Layer — Gap Report

생성: 2026-05-12  
Baseline: WP ontology v0.2 (114 entities) + M3 token ontology v0.1 (273 entities) + 48 block-component binding rules

## Approach Decision

GPT의 두 번째 분석 (BLOCK-COMPONENT-MAP을 lookup → rule-based로 재구성)을 부분 채택:

**기존 발견**: BLOCK-COMPONENT-MAP은 단순 직관 매핑이 아니라 **이미 6-bucket (A/B/C/D/E/F) 분류 체계**를 갖춤. 이건 ontology-aware한 구조.

**v2.1a 결정**: bucket을 **폐기하지 않고 ontology grammar로 승격**:

| Legacy bucket | v2.1a binding_type | 의미 |
|---|---|---|
| A | Direct.CoreBlockStyle | core block + style class 1:1 |
| B | Compositional.BlockPattern | 다중 블록 composition |
| C | Direct.CustomBlock | 커스텀 블록 1:1 |
| D | Composite.TemplatePart | FSE 템플릿 파트 composite |
| E | OutOfScope.Handoff | 폼/3rd-party plugin 위임 |
| F | RuntimeOnly.ThemeJS | JS 런타임만, ontology entity 없음 |

GPT 두 번째 분석의 Direct/Conditional/Composite/Invalid 4-class와 정합 (A+C=Direct, B=Conditional, D=Composite, E+F=Invalid/Runtime).

---

## Tier 1 — Token Bindings (6)

| WP anchor | M3 anchor | confidence | P4 agreement |
|---|---|---|---|
| **wp:ThemeToken.color** | m3:Family.sys.color | **0.95** | 5/5 |
| **wp:AppearanceTool** | (meta-flag) | **0.90** | 5/5 |
| **wp:ThemeToken.typography** | m3:Family.sys.typescale | **0.85** | 5/5 |
| wp:ThemeToken.shadow | m3:Family.sys.elevation | 0.70 | 4/5 |
| wp:ThemeToken.spacing | (M3 baseline gap) | 0.70 | 5/5 |
| wp:ThemeToken.border | m3:Family.sys.shape | 0.60 | 4/5 |

### Strong bindings (3, confidence ≥ 0.85)

가장 ROI 높은 immediate-implementable bindings:

#### G5.1 — `wp:ThemeToken.color` ↔ `m3:Family.sys.color` (0.95)

- WP `settings.color.palette[]`는 open string-keyed list
- M3 `sys-color`는 fixed 38 roles (primary/secondary/tertiary/error × on-/-container 등)
- Binding pattern: **role_to_slug** — theme.json palette entry가 M3 role slug 사용
- Axismundi 구현: `tokens.css §2` (36 dual-scheme tokens)

**Gap**: WP 사용자는 임의 palette 생성 가능. M3 ↔ WP binding을 강제하려면 theme.json `settings.color.custom=false` 정책 필요.

#### G5.2 — `wp:AppearanceTool` ↔ M3 design-tool meta-flag (0.90)

- appearanceTools=true는 WP가 ontology 안에 갖춘 **유일한 meta-flag**
- M3는 단일 meta-flag 없지만, appearanceTools가 enable하는 12 BlockSupport 묶음이 **M3 Component Inspector에서 노출되는 design-freedom 묶음과 정확히 일치**
- Binding pattern: **meta_flag_to_capability_bundle**

#### G5.3 — `wp:ThemeToken.typography` ↔ `m3:Family.sys.typescale` (0.85)

- WP `fontSizes`는 size 하나만 정의
- M3 `sys-typescale`은 role (display/headline/title/body/label × 3 sizes) × 5 properties (font/size/weight/lineHeight/tracking)
- Binding pattern: **role_to_slug_plus_utility_class** — fontSize slug + base.css `.t-{role}` utility class로 부족한 4 properties 채움

---

## Tier 2 — Component Bindings (32 in-scope, 16 out-of-scope)

48 binding rules from BLOCK-COMPONENT-MAP 분석:

| binding_type | count | confidence |
|---|---|---|
| Direct.CoreBlockStyle | 14 | 0.9 |
| Direct.CustomBlock | 11 | 0.85 |
| Composite.TemplatePart | 7 | 0.75 |
| Compositional.BlockPattern | 1 | 0.7 |
| OutOfScope.Handoff | 9 | (out) |
| RuntimeOnly.ThemeJS | 6 | (out) |

### G5.4 — Bucket B (Compositional) 단 1건

`List item (with leading/trailing)` 가 유일한 Block Pattern binding. 

**해석**: Axismundi는 composition 패턴을 Block Pattern으로 풀기보다 **core block + Block Style** 또는 **custom block**으로 가는 방향 선택. 이는 보수적 결정 — Block Pattern은 inserter UX가 약하기 때문. GPT 두 번째 분석에서 우려한 "core/group → Card 직선 매핑"이 실제로는 **bucket A로 정확히 잡혀 있음** (`core/group + is-style-card-elevated`), 잘못된 직선 매핑 아님.

### G5.5 — Tier 3 deferred 0건 (실측)

`deferred_count: 0` — 1차 검토에서 deferred 표시된 component 없음. 다만 M3-COMPONENT-SPECS Tier 1만 처리한 거라 (Button/Card/List/Divider 등 일상 컴포넌트), v2.1a-P1에서 Tier 2 (Chips/Tabs/Dialogs/Menus/Sheets)로 가면 다수가 deferred로 분류될 가능성.

---

## v2.1a 산출물 Atlas Coverage

이번엔 atlas 직접 link하지 않았지만 (M3 source 자체가 atlas와 별개 authority — Axismundi project authority), Tier 1 token binding이 atlas의 `theme-config.json-settings-{color,typography,spacing}`과 정확히 정합 — **이는 v0.2 ontology가 이미 정한 binding anchor를 v2.1a binding map이 동일하게 사용함을 의미**.

## v2.1a-P1 예정 작업

GPT 두 번째 분석에서 명시한 Tier 1 우선 ingestion:

1. **tokens.css + M3-COLOR-TOKEN** → m3_token_ontology.jsonld ✓ (이번 P0 완료)
2. **BLOCK-COMPONENT-MAP** → rule-based binding rules ✓ (이번 P0 완료)
3. **M3-COMPONENT-SPECS Tier 1** (Button, Card, List, Divider) — 다음 P1
4. **base.css semantic policies** (§3 heading mapping, §6.5 .t-{role}, §11 code) — 다음 P1
5. **Axismundi block theme self-validation** — 실제 테마가 이 binding 구현하는지 — 다음 P2


---

## v2.1a-P0.5 — Binding Legitimacy Audit (2026-05-12)

### Audit method

Instead of abstract binding audit, **execution was via pilot block theme generation**:
1. Auto-generate `theme.json` from M3 token ontology + binding map (script: `v2_1_build_theme_json.py`)
2. Hand-write `functions.php` + `block-styles.css` per Tier 2 binding rules
3. Run validation script (`v2_1_validate_pilot.py`) — 4-axis audit (Schema / Theme / CSS / Runtime)

This converts the "is the binding map legitimate?" question into the "does the binding map auto-generate a working block theme?" question — and discovers issues at the code-realization stage, not on paper.

### Verdict — 1.000 / 1.000 PASS

| Axis | Score | Details |
|---|---|---|
| A schema | 1.000 | color 36/36 + typography 15/15 + shadow 6/6 perfect role-slug agreement |
| B theme | 1.000 | appearanceTools=true + 4/4 lock-down flags correct |
| C css | 1.000 | tokens.css + base.css + block-styles.css all present, M3 var() referenced (no hard-coded hex) |
| D runtime | 1.000 | 12/12 block style registrations match binding rules |

### Discovery — Token ontology bug found and fixed

During Axis A audit, **typography fontSizes A2 score initially 0.5**:
- Cause: `v2_1_build_token_ontology.py` parsing `line-height` (hyphenated CSS property) as `line` (role suffix) + `height` (property), creating 30 false typescale_roles instead of 15.
- Fix: Recognize `line-height` as 2-segment property in `classify_token()`.
- Re-emit: ontology → theme.json → re-validate → 1.000.

This is **exactly the audit value GPT predicted** — without code-realization, the bug stays hidden in JSON-LD where typescale_role={body-large, body-large-line, body-medium, ...} looks superficially plausible but breaks downstream.

### Self-validation limitations

Score 1.000 is **partially tautological** — `theme.json` is auto-emitted FROM the binding map, so naturally agrees with it. Real binding legitimacy will be probed by:
- **v2.1a-P1**: Hand-write component ontology nodes for M3-COMPONENT-SPECS Tier 1 — do they cleanly extend the binding map?
- **v2.1a-P2**: Real Axismundi block theme (not auto-generated) — do humans naturally write code that matches the binding ontology?

For v0.1 pilot purposes, 1.000 means **binding map is self-consistent and can drive code generation**. Sufficient to proceed to P1.

### v0.5 outputs

```
_meta/v2_1/
├─ binding_legitimacy_audit.json     ← 4-axis score (1.000)
└─ pilot_validation_report.md        ← human-readable report

/home/claude/axismundi-theme-pilot-v0.1/   ← generated pilot block theme
├─ style.css
├─ theme.json (auto-emitted)
├─ functions.php (12 block style registrations)
├─ README.md (binding-to-code traceability)
├─ assets/css/{tokens,base,block-styles}.css
├─ templates/{index,single}.html
└─ parts/{header,footer}.html
```


---

## v2.1a-P0.5 — Architectural decision (post-pilot)

After v2/v3 iterations explored hex-literal-in-theme.json to fix inspector GUI swatches, **architectural decision finalized**:

**`tokens.css` is the Single Source of Truth**. `theme.json` is a minimal WP integration layer with `var()` references throughout. WordPress inspector GUI limits (`#000` swatch, empty typography inputs) are **accepted as intentional** — they will be replaced by a future Axismundi plugin providing:

- HCT-based color picker (M3 native color space, not hex)
- Dynamic palette generation from seed
- Theme switcher with light/dark/scheme variants
- Typography role inspector showing M3 hierarchy (display/headline/title/body/label × small/medium/large)

### Why this matters

If theme.json had been hex-literal (v2/v3 approach), the M3 ref→sys 2-tier token chain would have been compromised:
- Palette changes require theme.json regeneration
- Dynamic theming (M3 Material You pattern) impossible without bespoke build step
- Dark mode requires style variation duplication of full palette
- `color-mix()` / `light-dark()` / `oklch()` usage in block-styles.css becomes inconsistent with theme.json hex layer

### What this preserves

- `tokens.css` ref→sys architecture: full M3 spec
- `block-styles.css` modern CSS: color-mix(), light-dark(), oklch() all available
- `[data-theme="dark"]` selector handles dark mode: no style variation needed
- Token chain throughout: `var(--md-sys-color-primary)` resolves consistently in all contexts

### v2.1a-P0.5 verdict — re-affirmed PASS

Validation script still scores 1.000 / 1.000 (4-axis). The audit was correct — binding ontology IS self-consistent and operationalizable. The inspector GUI discovery was a real implementation gap, but its resolution is **plugin layer, not binding ontology**.

### v0.5 final state

- `theme.json` (v4): minimal, var() throughout, lockdown flags (`custom: false`, `customFontSize: false`)
- `styles/m3-dark.json`: removed (tokens.css handles dark mode)
- README: documents the architectural decision + plugin roadmap
- Binding map: unchanged (still v2.1a-P0 baseline)
- Validation script: still passes (binding-level audit, not GUI-level)

### v2.1a-P0.5 → next

Pilot is now stable. Two paths:

- **P1**: M3-COMPONENT-SPECS Tier 1 component ontology (Button/Card/List/Divider structure + supports profile + style class spec). Build component-level ontology that the pilot's 12 block style registrations can be evaluated against.
- **Plugin track**: Begin Axismundi HCT color picker plugin. This is parallel to P1 and doesn't block ontology work.

