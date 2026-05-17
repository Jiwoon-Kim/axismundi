# Axismundi 백업/아카이브 정리 보고

## Source: GPT session
> Preserved verbatim for archival value. The structural recommendation in this report informs workstation-level file organization (outside the monorepo); the monorepo itself implements it via `products/_archive/` (v3.3.0).

---

# 1. TIMELINE

## Phase 0 — Pre-monorepo refinement series

```
refine.zip
refine_phase1.zip → refine_phase5.zip
refine_v1.0.zip
refine_v2.0_pilot.zip
refine_v2.0_pilot_p2.zip
refine_v2.0_pilot_p3.zip
```

의미: WordPress / M3 / handbook / markdown repair / refinement pipeline을 실험하던 시기.
상태: current monorepo의 corpus / atlas / core / tools로 대부분 흡수됨.
보존 방식: `pre-monorepo-archive/refine-series/`

## Phase 1 — Ontology core emergence

```
ontology_core_v0_2.zip
ontology_v2_1a_p0.zip
```

의미: WordPress ontology, Material Design 3 token ontology, WP ↔ M3 binding, core block mapping, pilot validation.

이 시기부터 단순 styleguide가 아니라 `corpus → atlas → ontology → binding → pilot` 구조가 형성됨.

상태: current monorepo의 core/, bindings/, products/reference-implementations/ontology-theme-pilot/로 흡수.
보존 방식: `pre-monorepo-archive/ontology-series/`

## Phase 2 — Axismundi static prototype / RC series

```
axismundi-v3.1.0.zip
기존 Axismundi static prototype / RC 계열
```

의미: 고해상도 정적 시각 프로토타입, style-guide.html, style-guide-blocks.html, style-guide-prose.html, 16 templates, CSS component catalog, social CMS / ActivityPub frame 혼입.

상태: 더 이상 authority 아님, legacy visual reference로 격하 예정.

결론:
- `products/reference-implementations/axismundi-prototype/` → `products/_archive/axismundi-prototype/`
- (실제 v3.3.0에서 수행됨)

## Phase 3 — Monorepo normalization (v3.1.0)

핵심 구조: corpus/, atlas/, core/, bindings/, products/, tools/
보존: `freezes/axismundi-v3.1.0-freeze.zip`

## Phase 4–7 — License / Font / Icon foundation (v3.2.0–v3.2.3)

v3.2.3: Roboto Flex / Serif / Mono full no-subset WOFF2, Noto Sans KR = Korean fallback layer, Future CJK = WordPress Font Library / Google Fonts user-managed expansion, Material Symbols = icon layer.

현재 가장 중요한 stable freeze. 보존: `freezes/axismundi-v3.2.3-freeze.zip`

## Phase 8 — Next structural shift (v3.3.0)

prototype legacy/archive, assets relocation, lab active visual QA surface 승격, root styleguide = lab mirror, publish_styleguide.py source 변경.

---

# 2. MANIFEST — 권장 루트 관리 구조

```
Axismundi_Work/
├─ current/
│  └─ axismundi/                         ← 현재 작업본
│
├─ freezes/
│  ├─ axismundi-v3.1.0-freeze.zip
│  ├─ axismundi-v3.2.3-freeze.zip
│  └─ _old/
│     ├─ axismundi-v3.2.0.zip
│     ├─ axismundi-v3.2.1.zip
│     └─ axismundi-v3.2.2.zip
│
├─ pre-monorepo-archive/
│  ├─ README.md
│  ├─ TIMELINE.md
│  ├─ MANIFEST.md
│  ├─ refine-series/
│  ├─ ontology-series/
│  ├─ prototype-series/
│  └─ _discard-candidates/
│
├─ external-assets/
│  ├─ google-fonts-original/
│  └─ material-symbols-original/
│
├─ scratch/
└─ _LATEST.md
```

## Current monorepo (`current/axismundi/`)

역할: 현재 작업 표면, 최신 monorepo, 앞으로 v3.3.0 작업 대상.

주의:
- pre-monorepo zip 넣지 않기
- 원본 Google Fonts zip 넣지 않기
- 중복 freeze zip 넣지 않기

## Freezes (`freezes/`)

역할: milestone snapshot만 보존.

KEEP:
- axismundi-v3.1.0-freeze.zip
- axismundi-v3.2.3-freeze.zip
- axismundi-v3.3.0-freeze.zip (생성 후)

MOVE TO _old:
- axismundi-v3.2.0.zip
- axismundi-v3.2.1.zip
- axismundi-v3.2.2.zip

DELETE LATER:
- 같은 의미의 중복 zip
- 파일명만 다른 복사본
- 다음 milestone에 완전히 흡수된 중간본

## Pre-monorepo archive (`pre-monorepo-archive/`)

역할: 과정 보존, 의사결정 기록, 고고학적 archive.

넣을 것: refine series, ontology core series, binding pilot series, 초기 Axismundi prototype / RC zip, paste markdown / repair notes 중 의미 있는 것.

넣지 말 것: 현재 작업본, 최신 fonts/icons runtime, GitHub 공개용 repo 자료.

## External assets (`external-assets/`)

역할: 외부 원본 다운로드 보존.

넣을 것: Google Fonts 원본 zip (Roboto Flex/Serif/Mono, Noto Sans/Serif KR), Material Symbols 원본, LICENSE / OFL 원본.

current monorepo에는: 실제 사용하는 WOFF2, OFL.txt, LICENSE.txt, source.txt만 남기는 게 좋음.

---

# 3. Milestone policy

## Milestone A — Pre-monorepo final
보존 이유: 어떻게 여기까지 왔는지 증명하는 archaeology

## Milestone B — v3.1.0
보존 이유: monorepo 구조가 처음 성립한 기준점

## Milestone C — v3.2.3
보존 이유: license/font/icon/lab 정리가 끝난 안정 지점

## Milestone D — v3.3.0
생성 조건: prototype archive 이동, core/design-systems/material3/assets/ 이동, lab active visual QA surface 확정, root styleguide lab mirror 전환.
(2026-05-13 v3.3.0 freeze로 달성됨)

---

# 4. 삭제 / 이동 기준

## 즉시 삭제하지 말고 `_old/`로 이동

- v3.2.0.zip, v3.2.1.zip, v3.2.2.zip

이유: v3.2.3에 흡수됨. 하지만 바로 삭제하면 불안하므로 _old에서 며칠 보관.

## discard 후보

- 같은 날짜의 중복 zip
- 파일명만 다른 동일 백업
- 다음 phase에서 완전히 대체된 임시본
- 깨진 실험본 중 기록 가치 없는 것

위치: `pre-monorepo-archive/_discard-candidates/`, `freezes/_old/`

---

# 5. `_LATEST.md` 템플릿

```md
# Axismundi Latest

## Current working copy
Path: Axismundi_Work/current/axismundi
Current state: v3.3.0
Status: stable working copy

## Latest freeze
File: freezes/axismundi-v3.3.0-freeze.zip
Meaning:
- prototype → _archive
- assets → core/design-systems/material3/
- lab = active visual authority
- /styleguide/ mirrors lab

## Permanent freezes
- freezes/axismundi-v3.1.0-freeze.zip
- freezes/axismundi-v3.2.3-freeze.zip
- freezes/axismundi-v3.3.0-freeze.zip

## Old / safe-to-archive freezes
- freezes/_old/axismundi-v3.2.0.zip
- freezes/_old/axismundi-v3.2.1.zip
- freezes/_old/axismundi-v3.2.2.zip

## Pre-monorepo archive
Path: Axismundi_Work/pre-monorepo-archive
Contains: refine-series, ontology-series, prototype-series, raw notes

## External assets
Path: Axismundi_Work/external-assets
Contains: original Google Fonts downloads, original Material Symbols downloads

## Next milestone
v3.3.1 — Pilot Block Theme Probe
Planned: minimal axismundi-pilot/, core block mapping validation, gallery as carousel fallback
```

---

# 6. 정리 결론

현재 보존 전략은 이렇게 고정하면 됨:

- 모든 zip을 보존하지 않는다.
- 모든 과정을 버리지도 않는다.
- 중요 milestone은 zip으로 보존한다.
- 과정은 TIMELINE / MANIFEST로 압축 보존한다.
- 현재 작업은 current 하나만 유지한다.
- pre-monorepo는 current 바깥 archive로 격리한다.

## 한 줄 결론

**v3.1.0과 v3.2.3은 milestone freeze로 보존하고, 그 이전 과정은 pre-monorepo archive에 TIMELINE/MANIFEST와 함께 정리. v3.3.0부터는 lab 승격과 asset relocation을 새 milestone으로 잡으면 된다.**
