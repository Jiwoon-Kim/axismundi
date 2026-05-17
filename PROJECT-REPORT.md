# Axismundi — Project Report (Consolidated)

> Generated: 2026-05-13 (current frontier: v3.3.0)
>
> This document consolidates fragmentation across multiple sessions: Phase 1A through Phase 2B δ-2 (static prototype era, multiple ClaudeChat threads), the Phase 8 doctrinal era (GPT framing development), the refine series (corpus repair pipeline), the monorepo normalization era (v3.x.x, current), and this session's work (v3.2.0 through v3.3.0).
>
> Intended as a permanent project archeology record. Lives in repo root.

---

## I. One-line synthesis

Axismundi began as "a Material 3 WordPress block theme with ActivityPub microblog" and has evolved into an **ontology-driven WordPress + design system architecture** with three coordinated artifacts: a knowledge corpus (WP + M3 documentation refined), a token-based design system (33 components, Korean-first typography), and a layered monorepo (`corpus / atlas / core / bindings / products / tools`) that treats the design system as just one product layer rather than the whole project.

The journey has not been linear. The static prototype reached completion (Phase 1B → Phase 2B δ-2) before the monorepo formalized; the doctrinal grammar and ontology framing developed in parallel; the monorepo era (v3.0.0+) absorbed both into a unified structure.

**This is a personal project**. KIM Ji-woon (designbusan.ai.kr) is the primary author; GPT and Claude were leveraged as amplification tools. All architectural decisions are the author's. See `AUTHORSHIP.md` for decision territory and the universal-vs-Axismundi-specific signature mapping.

---

## II. Full Timeline

### Pre-monorepo era (April–May 2026)

#### Phase 1A — Token foundation (~late April 2026)
- 3-tier token system established (ref → sys → comp)
- `tokens.css`, `base.css`, 14 component baseline
- M3 ref → sys → comp structure
- Hex literal zero policy
- Light/dark scheme baseline

#### Phase 1B — 33-component expansion (2026-05-02 to 2026-05-03)
- **Tier 1 (E1–E4)**: 7 microblog core components — App bar, Card, Chip, Button, Icon button, Avatar, FAB
- **Tier 2 (F1–F2)**: Form 4 components — Text field, Switch, Checkbox, Radio. Text field 5-slot grid refactor. **Caveat 9.10**: `<label>` wrapper → `<div>` for HTML5 labelable element compliance
- **Tier 3 (G1–G3)**: List, Progress, Button group, Toolbar, Carousel
- **Tier 4 (H1, H234)**: FAB menu, Split button, Date picker, Time picker
- Closed 2026-05-03 as **v0.4.0 / v1.0.0-rc1** with all 33 components
- Backups: `Axismundi-chunk-E1..H234.zip`, `Axismundi-phase-1b-final.zip`

#### Phase 2A α — Foundation (early May 2026)
- 5-stylesheet architecture (`tokens / base / components / prose / blocks`)
- `style.css` theme root entry + `readme.txt` (wp.org §11)
- `theme.js` skeleton (5 IIFE)
- LICENSE GPL-3.0-or-later

#### Phase 2B β — Stylesheet split (2026-05-07)
- 3 style guide pages (`style-guide / style-guide-blocks / style-guide-prose`)
- Visual QA round 1 — prose, table variants, text-field disabled/error/suffix
- Backup: `Axismundi-phase-2B-beta-fix2b.zip`

#### Phase 2B γ-1, γ-2, γ-3 — Ultrareview (2026-05-08)
- 6-axis ultrareview methodology developed (token consistency / logical properties / a11y / cross-page sync / theme.js wiring / wp.org compliance)
- Specific findings are now superseded — see `products/_archive/_pre-monorepo-reports/SUPERSEDED-ULTRAREVIEW.md`. The methodology is preserved for re-audit triggers (lab → pilot promotion, distributable construction)
- 4 wp.org §11 files complete during this era; LICENSE added → §11 5/5
- 5 page templates: `home`, `front-page`, `single`, `index`, `404`
- 3 template parts: `header`, `footer`, `sidebar`
- Total static prototype size at γ-3 close: ~17,100 LOC, 24 files (all now archived)
- Backup: `Axismundi-phase-2B-gamma-3-final.zip`

#### Phase 2B γ-3 vqa1, vqa1.1, vqa1.2 — Visual QA passes (2026-05-08)
- 17 M3 text-field reference images measured manually
- Outlined focus thickness: 3px → 2px decision
- Suffix deferred item closure (input 좌측정렬 + suffix 우측 별도 셀)
- Slider color root cause re-corrected (token error, not gradient direction)
- vqa2-spec-audit.md as first verified M3 spec artifact
- Backup: `Axismundi-phase-2B-gamma-3-vqa1.1.zip`

#### Phase 2B δ — Page expansion (2026-05-08)
- 4 new pages: `search`, `archive`, `attachment`, `single-axismundi_profile`
- Backup: `Axismundi-phase-2B-delta.zip`

#### Phase 2B δ-2 — v1 absorption (2026-05-09)
- v1 React SPA prototype 시각 자산 흡수
- Initially overwrote `front-page.html` → corrected to **separate variation** (`front-page-microblog.html`)
- Original front-page preserved (3 Korean posts)
- New variation: 7 bilingual posts, tabs, composer, theme switcher
- `theme.js §8` Theme switcher added
- `base.css §6.8` visually-hidden util added
- Backup: `Axismundi-phase-2B-delta-2.zip`

#### Doctrinal Era — Phase 7 + Phase 8 (parallel KB build track, Cowork sessions)

Independently of static prototype work, a parallel knowledge-base construction track ran in Cowork sessions. This track produced what became the monorepo's A/B/C layers.

**Phase 7 — KB construction 1차 (~85 chunks)**
- block-authoring substrate (registration → block.json → supports → edit/save → markup → deprecation → wrapper → dynamic → inner-blocks → variations → transforms)
- theme-config (appearanceTools, settings, styles, patterns, templateParts, customTemplates, residual-governance)
- style-engine (4 chunks)
- data-layer, interactivity, plugin-dev
- block.bindings (paradigm bridge)
- First DSL spec

**Phase 7.5–7.8 — Constitutional formalization**
- task #93: `structural-patterns.md` — KB Constitution v1
- task #97: Authority Mediation Surface candidate
- task #103: Paired Operational Architecture
- task #108: Resolution Surface KB-wide audit
- editor-customization + admin-ui + site-building chunks

**Phase 8.x — Constitutional precision (~tasks #93–109)**
- Phase 8.5: **Doctrine 6 formalization** (Authority Access Mediation)
- Phase 8.10: HARD/SOFT mode + 6 sub-variants
- Phase 8.13: Bridge Pattern audit
- Phase 8.14: **Law 3b formalization + KB Constitution v2 declared**
- Phase 8.18: **Section X analytical tier** (4 Civilization Archetypes, non-constitutional)
- Phase 8.20–22: Predictive utility pilots
- Phase 8.24–26: Disciplined non-promotion 5/5 (forward authoring readiness)

**Phase 8.27+ — Forward authoring under composure doctrine (35 chunks across Phase 8.27 → 8.48)**

Doctrine declared at 8.27: *"Reference when clarifying. Omit when unnecessary. Deploy naturally."*

Terrain coverage in Phase 8.27+:
- site-building, build-tooling, block-authoring contracts, data-layer
- i18n, editor-customization, interactivity, admin-ui
- style-engine, plugin-dev, multisite
- rest-api, template-tags, theme-config (Global Styles user persistence)

Three audits at the end:
- **M1 — Structural Audit** (breadth): 23 chunks / 4 emergent toolkits
- **M2 — Grammar Audit** (precision): Law 4 fit criterion specified
- **M3 — Topology Audit** (governance geometry): Federation 5-variant family, Doctrine 5 bifurcated, 5-level analytical granularity
- **P1 — Frontier Mapping** (forward strategic options: 3 paths)

**Constitutional summary at Phase 8 closure (KB Constitution v2)**

6 KB-Wide Laws:
1. **Declaration ≠ Exposure** (Law 1)
2. **HTML Primacy** (Law 2)
3. **Authority Continuity** (Law 3) + **3b: Cross-Runtime Bridge** sub-pattern
4. **Arbitration Compiler** (Law 4) — fit criterion specified
5. **Entity → Relationship Pivot** (Law 5)
6. **Compiler ↔ Runtime Split** (Law 6)

6 Doctrines:
- Doctrine 1–5 (Phase 7-era)
- **Doctrine 6: Authority Access Mediation** — fit criterion (Mediates + Decides + Terminates + Binds) + jurisdictional dimension

Section X (non-constitutional analytical tier):
- 4 Civilization Archetypes — consistently refused as constitutional inflation

5 Emergent prose-level toolkits:
- ◆ **Anti-Law-4** (10 anti + 5 positive)
- ● **Existence-vs-operation** (28 contributions; foundational at 8.29)
- △ **Anti-Law-3b** (5 inventory members; anchored at 8.38 ServerSideRender)
- ※ **Federation variants** (5 variants; progression 8.36→8.48)
- ■ **Doctrine 6 fit** (7 anti + 3 positive + 4-criterion + jurisdictional)

Result: ontology evolved from "style guide + classification" to *interpretive grammar* — false-analogy rejection, scale stress testing, audit synthesis. **LLM-readable constitutional infrastructure**.

**Phase 8 closure → Phase 9 transition**

Doctrine shift at Phase 9 entry: *"Build the smallest thing that ships. Reference KB only when stuck."*

KB role changes:
- **Frozen reference** — no more forward chunks
- **Engine-room manual** — Phase 9 build lookup
- **Methodology archive** — 3 audits + frontier map record *how* the KB grew

Phase 9 anti-patterns (explicit):
- New KB chunk addition impulse → reject
- "Phase 8.49" mental impulse → reject
- Doctrine refinement before build → reject
- Tier B scope creep → reject

Phase 8 closure stats: ~103 forward chunks, 23 meta documents, 14 bounded contexts, 6 Laws + 3b, 6 Doctrines, 5 toolkits, 3 audits, 1 frontier map, Q8/Q9/Q10 zero-pressure streak: 35 chunks in Phase 8.27+ alone.

**KB operating rules preserved as meta-doctrine**

The procedural rules under which the KB was authored are preserved at `products/_archive/_pre-monorepo-reports/cowork-kb-operating-rules/`. Notable items:

- 11 numbered chunk-authoring rules (schema atomization, spike-then-batch, substrate-first, operational-density-over-completeness, observe-then-codify, etc.)
- 6-slot DSL specification (WHEN / SHAPE / REQUIRES / INVARIANTS / ANTIPATTERNS / RELATED) — never extended despite covering capability-heavy areas
- English-only chunk language policy (token economy)
- Generic-vs-project layer separation rule — scaled from `./knowledge/` subfolders into the monorepo's full 6-layer architecture

These rules are *still live* for future KB extension (ActivityPub KB, additional design systems, alternate platforms). They are not retired with the v3.3.0 freeze; they are reusable operating doctrine. See `products/_archive/_pre-monorepo-reports/cowork-kb-operating-rules/README.md` for the rule-to-monorepo mapping.

#### Refine series (2026-05-12)
- Markdown repair / lang_fix / dev-handbook refinement pipeline
- ~38 patches on WordPress developer handbook source
- 5/5 validation pass
- Backups: `refine.zip`, `refine_phase5.zip`, `refine_v1.0.1.zip`

This series produced what would become `corpus/refined/dev-handbook-clean/` in the monorepo.

---

### Monorepo era (May 2026 — present)

#### v3.0.0 — Monorepo Normalization (early monorepo)
Six-layer architecture established:
```
corpus/        — refined external knowledge mirror
atlas/         — captured rules from external + internal sources
core/          — Axismundi ontology
bindings/      — contracts (WP ↔ M3 binding maps)
products/      — reference-implementations + distributables
tools/         — generators + validators
```
- CONSTITUTION.md established with 10 articles
- Material 3 isolated as a replaceable design system

#### v3.0.1 — Structural Hardening
- DESIGN-DOCTRINE.md
- bindings/_spec/binding-schema.md
- products 2-track split: `reference-implementations/` + `distributables/`
- Constitution Article 11 (Design Doctrine delegation)

#### v3.1.0 — RC Integration + Publishing Surface
- Real Axismundi v1.0.0-rc1 imported as `axismundi-v1.0.0-rc1/` (33 components)
- `compare/` benchmark → `axismundi-benchmark/`
- `atlas/material/` multi-domain (text-fields-spec/impl)
- `theme-pilot` → `ontology-theme-pilot` rename
- `/index.html` + `/styleguide/` publish surfaces
- Constitution Article 12 (publishing surfaces are mirrors)
- `publish_styleguide.py` generator
- Validation: pilot 1.000 PASS

#### v3.2.0 — License / Font / Icon Foundation (2026-05-13, current session)
- LICENSE-MATRIX.md, NOTICE.md (per-asset license declarations)
- 5 self-hosted variable fonts converted (Roboto Flex/Serif/Mono + Noto Sans/Serif KR)
- Material Symbols × 3 (Outlined/Rounded/Sharp) icon fonts
- `atlas/material/icon-font-scope-policy.md` — chrome-only scope rule
- `axismundi-v1.0.0-rc1/` → `axismundi-prototype/` rename

#### v3.2.1 — Font Runtime Integration (current session)
- `fonts.css` — @font-face for 4 fonts with unicode-range strategy
- `icons.css` — Material Symbols base + GRAD sync + Expressive FILL state
- `tokens.css §1.4` — `--md-grade` shared variable
- `prose.css §12` — icon scope enforcement (CSS-level federation guard)
- `patterns/icon-button-search.html` — first static icon button pattern

#### v3.2.2 — Interaction Lab Audit (current session)
- **`axismundi-benchmark/` → `axismundi-lab/`** rename — reflects actual lab role
- INTERACTION-AUDIT.md — 5 components promoted to prototype, 4 held in lab
- 5-point promotion criteria formalized
- Slider color refined: `on-secondary-container` → `secondary-container` (M3 spec correction)
- Korean typography audit — no changes, body-level inheritance confirmed correct
- `.nojekyll` (GitHub Pages optimization)
- `typography-axis.html` — variable font axis explorer (13 axes drag UI)

#### v3.2.3 — Font Coverage Fix (current session)
- Roboto Flex/Serif/Mono re-converted **full no-subset** WOFF2
- `-latin` filename suffix dropped (no longer subset)
- ₩, ←→, ≈≠≤≥, Greek, Cyrillic now all present
- New strategy formalized:
  - **Roboto** = non-CJK base layer (full coverage)
  - **Noto Sans KR** = Korean fallback (via unicode-range)
  - **Future CJK** = user-managed via WordPress Font Library (WP 6.5+) / Google Fonts integration
- Material Symbols = icon layer (unchanged)

#### v3.3.0 — Lab Promotion + Legacy Split (current session, current frontier)
- Authority migration: prototype → lab
- `products/_archive/axismundi-prototype/` (legacy preserved)
- `core/design-systems/material3/assets/` (fonts + icons shared)
- `publish_styleguide.py` source: prototype → lab
- root `/styleguide/` now mirrors lab
- CONSTITUTION Article 12 Rule 5: publish surface authority migration policy
- Total: 996 files, 33MB monorepo, 21MB zipped

---

## III. Current Manifest (v3.3.0)

### Layer-organized

```
axismundi/
├── corpus/                                 (664 files, 6.2M)
│   ├── source/                             — frozen WP/Gutenberg references
│   └── refined/dev-handbook-clean/         — patched markdown (38 patches)
│
├── atlas/                                  (140 files, 3.8M)
│   ├── wordpress/                          — captured WP rules
│   ├── material/                           — captured M3 rules
│   │   ├── text-fields-spec.md
│   │   ├── text-fields-impl.md
│   │   └── icon-font-scope-policy.md
│   └── _meta/                              — audit + ledger
│
├── core/                                   (55 files, 19M ← assets moved here in v3.3.0)
│   ├── design-systems/
│   │   └── material3/
│   │       ├── runtime/                    (Axismundi-authored CSS)
│   │       ├── specs/                      (M3 spec mirror)
│   │       ├── assets/                     (fonts + icons, shared)
│   │       │   ├── fonts/                  (5 font families, OFL 1.1)
│   │       │   └── icons/                  (Material Symbols Rounded, Apache 2.0)
│   │       ├── DESIGN-DOCTRINE.md          (6 doctrines + 8 locked decisions)
│   │       └── token_ontology.jsonld
│   ├── wordpress-ontology/                 (114 entities)
│   └── ontology-bridges/
│
├── bindings/                               (13 files, 152K)
│   ├── _spec/binding-schema.md             (D-layer grammar)
│   └── wordpress-material3/
│       ├── binding_map.json                (40 bindings: 6 token + 32 component)
│       ├── binding_legitimacy_audit.json   (1.000 PASS)
│       └── pilot_validation_report.md
│
├── products/                               (96 files, 21M)
│   ├── _archive/                           ← NEW in v3.3.0
│   │   └── axismundi-prototype/            (legacy, social-CMS-frame contaminated)
│   │       └── _LEGACY.md                  (demotion notice)
│   ├── reference-implementations/          (3 dirs, active)
│   │   ├── axismundi-lab/                  ← ACTIVE VISUAL AUTHORITY
│   │   │   ├── style-guide.html
│   │   │   ├── style-guide-blocks.html
│   │   │   ├── style-guide-prose.html
│   │   │   ├── style-guide-benchmark.html
│   │   │   ├── typography-axis.html        (variable font axis explorer)
│   │   │   ├── stylesheets/                (8 css)
│   │   │   ├── scripts/
│   │   │   └── docs/INTERACTION-AUDIT.md
│   │   └── ontology-theme-pilot/           (ontology validation target)
│   └── distributables/                     (empty; future home of distributable theme + plugins)
│
├── tools/                                  (34 files, 672K)
│   ├── generators/publish_styleguide.py    (lab → /styleguide/ mirror, path rewriting)
│   ├── validators/validate_theme_pilot.py  (1.000 PASS)
│   ├── builders/
│   └── compilers/
│
└── styleguide/                             (13 files, 628K)  ← publish surface
    ├── index.html / blocks.html / prose.html  (lab mirror)
    ├── stylesheets/                        (8 css, paths rewritten)
    ├── scripts/style-guide.js
    └── README.md
```

### Root governance files

- `CONSTITUTION.md` (12 articles)
- `ROADMAP.md`
- `CHANGELOG.md` (v3.0.0 → v3.3.0 history)
- `README.md`
- `LICENSE-MATRIX.md` (per-asset declarations)
- `NOTICE.md` (Apache 2.0 + OFL 1.1 attributions)
- `.nojekyll` (GitHub Pages)
- `PROJECT-REPORT.md` ← this document

### Key statistics

| Item | Value |
|---|---|
| Total files | ~1003 |
| Total size | 33 MB |
| Zipped size | 21 MB |
| KB chunks (Phase 7–8) | ~103 across 14 bounded contexts |
| KB meta documents | 23 |
| KB-Wide Laws | 6 (Law 1–6 + Law 3b sub-pattern) |
| Doctrines | 6 (Doctrine 1–6) |
| Section X archetypes (non-constitutional) | 4 Civilization Archetypes |
| Emergent prose-level toolkits | 5 (Anti-Law-4, Existence-vs-operation, Anti-Law-3b, Federation variants, Doctrine 6 fit) |
| Audits performed | 3 (M1 Structural / M2 Grammar / M3 Topology) |
| Frontier maps | 1 (P1) |
| Ontology entities (C layer) | 273 (M3) + 114 (WP) = 387 |
| Bindings (D layer) | 40 (6 token + 32 component + 2 cross) |
| Validation score | **1.000 / 1.000 PASS** |
| Stylesheets (in lab) | 8 |
| Style guide pages | 3 canonical + 2 experimental |
| Fonts | 5 families, all OFL 1.1 |
| Icons | Material Symbols Rounded (Apache 2.0) |
| Hex literal violations | 0 |
| WCAG AA violations | 0 |

### A–F Layer mapping (canonical)

The monorepo's six top-level folders correspond to GPT's 6-layer ontology architecture, articulated during Phase 8:

```
A. Corpus      → corpus/       (근거층 — source mirror, refined documents, provenance)
B. Atlas       → atlas/        (판단층 — rule-based knowledge, WHEN/THEN, bounded contexts)
C. Core        → core/         (구조층 — formal ontology, JSON-LD entities, type system)
D. Bindings    → bindings/     (번역층 — WP × M3 confidence-scored mapping)
E. Products    → products/     (산출층 — prototypes, themes, plugins)
F. Tools       → tools/        (자동화층 — generators, validators, compilers)
```

Each layer answers a different question:

| Layer | Question | Failure if removed |
|---|---|---|
| A Corpus | "무엇을 근거로 삼았나?" | Lose ability to verify sources |
| B Atlas | "이 지식을 어떻게 판단/적용하나?" | Lose rule-discoverability |
| C Core | "기계가 이해할 수 있는 구조는?" | Lose formal type system |
| D Bindings | "WP와 M3는 어떻게 연결되나?" | Lose translation layer |
| E Products | "실제 작동하는 것은?" | Lose runnable artifacts |
| F Tools | "어떻게 재현/검증하나?" | Lose reproducibility |

Constitution Article 1 (since v3.3.0 amendment) is the authoritative codification of this 6-layer architecture.

---

## IV. Milestone ladder

### ✅ Completed (per ClaudeChat + GPT consolidated)

| ID | Milestone | Era |
|---|---|---|
| M1 | Token system established (3-tier ref/sys/comp) | Phase 1A |
| M2 | Tier 1 — 21 components | Phase 1B (v0.2.5) |
| M3 | Tier 2 + Text field refactor | Phase 1B (v0.3.0) |
| M4 | Tier 3 — 30 components | Phase 1B |
| M5 | Tier 4 — 33 / 33 components | Phase 1B (v0.4.0) |
| M6 | Phase 1B closed → v1.0.0-rc1 | 2026-05-03 |
| M7 | Phase 2A α — wp.org metadata + foundation | 2026-05-04 |
| M8 | Phase 2B β — 3 style guides + theme.js skeleton | 2026-05-07 |
| M9 | Phase 2B γ — pages + ultrareview | 2026-05-08 |
| M10 | Phase 2B vqa1.x — 17 visual QA fixes | 2026-05-08 |
| M11 | Phase 2B δ — 4 new pages | 2026-05-08 |
| M12 | Phase 2B δ-2 — v1 absorption | 2026-05-09 |
| M13 | Refine series — corpus repair pipeline | 2026-05-12 |
| M14 | Doctrinal Era (Phase 8) — ontology grammar | parallel |
| M15 | Monorepo normalization (v3.0.0–v3.1.0) | ~2026-05-13 |
| M16 | License/Font/Icon foundation (v3.2.0–v3.2.3) | 2026-05-13 |
| M17 | Lab Promotion + Legacy Split (v3.3.0) | 2026-05-13 |

### 🔵 Current frontier

| ID | Item | Status |
|---|---|---|
| M18 | Pilot block theme probe (`axismundi-pilot/`) | v3.3.1 planned |
| M19 | Lab visual QA cycle | ongoing (date/time picker, icon swap, etc.) |
| M20 | Promoted CSS/JS merge from lab to components.css | v3.2.4 optional |

### 🟡 Future

| ID | Item | Triggered by |
|---|---|---|
| M21 | Distributable theme construction | M18 + M19 stable |
| M22 | HCT plugin (`axismundi-hct-color-panel/`) | M21 |
| M23 | ActivityPub plugin (`axismundi-activitypub/`) | M21 + external Service scope decision |
| M24 | v1.0.0 release | M21 + M22 + M23 + wp.org submission |

---

## V. What the evolution actually means

### The transformation underneath

| Era | Headline question | Actual answer |
|---|---|---|
| Phase 1 | "예쁜 테마 만들 수 있나?" | Yes — 33-component design system |
| Phase 2 | "WordPress에 넣을 수 있나?" | Yes — static prototype reaches wp.org compliance |
| Phase 8 | "분류표를 넘어 grammar가 되나?" | Yes — false-analogy resistance + scale stress |
| Refine + Monorepo | "여러 자산을 어떻게 통합하나?" | Six-layer architecture (corpus/atlas/core/bindings/products/tools) |
| v3.x.x | "production-ready 자산 ownership?" | Single source per asset, layered consumers |
| **Now** | "lab QA → pilot construction" | About to test in v3.3.1 |

### The four ontology layers (per GPT framing analysis)

The Axismundi ontology is not one ontology — it is four coordinated ontologies, each occupying a different conceptual space:

**A. Design ontology** — M3 tokens, component grammars, state layers, motion specs

**B. WordPress mechanism ontology** — block lifecycle, theme.json, template hierarchy, hooks, REST, multisite, federation portability

**C. Interpretive doctrine grammar** — 6 KB-Wide Laws (Law 1–6 + Law 3b) + 6 Doctrines + Section X analytical tier + 5 emergent toolkits

**D. Product compiler substrate** — spec-emittable + retrieval-capable + prompt-orchestrable (D-layer bindings make this operational)

The Axismundi monorepo at v3.3.0 contains all four. The 6-layer monorepo architecture (A–F = corpus/atlas/core/bindings/products/tools) is the *physical incarnation* of these four conceptual ontologies; bindings (D) is where ontology A and B meet, products (E) is where compiler substrate (D as ontology) becomes executable.

### Phase 9 = Monorepo era

The transition from Phase 8 (KB construction) to Phase 9 (Axismundi product build) corresponds to the monorepo era. The principal doctrine of Phase 9 — *"Build the smallest thing that ships. Reference KB only when stuck"* — directly motivates the monorepo's product layer (E) reorganization across v3.0.0+:

- v3.0.0–v3.0.1: monorepo normalization absorbs Phase 8 KB into corpus/atlas/core/bindings
- v3.1.0: RC integration — static prototype joins products (E) layer
- v3.2.0–v3.2.3: license/font/icon foundation
- v3.2.2: lab established (separates experimental from authoritative)
- v3.3.0: lab promoted to active visual authority, prototype demoted to archive — *the product layer (E) becomes correctly stratified*
- v3.3.1+ (planned): minimal pilot block theme — the first executable compression of A+B+C+D into a shippable E artifact

---

## VI. Current frontier

After v3.3.0:

```
prototype  →  archived (legacy reference, social-CMS-frame contaminated)
lab        →  active visual authority + visual QA surface
assets     →  shared design system layer (core/design-systems/material3/assets/)
publish    →  /styleguide/ mirrors lab
distributable  →  not yet authored; future home of clean rebuild
```

**Immediate frontier (v3.3.1)**: **Lab Cleanup**. Post-v3.3.0 freeze, user identified that the lab styleguide inherited pre-lab-era contamination from the prototype era (sections authored before the prototype/lab separation was introduced in v3.2.2). The publish surface (`/styleguide/`) propagates this contamination because it mirrors lab. Resolution: rollback to local clean backup + targeted lab fixes + re-run publish. See `KNOWN-ISSUES.md #1` for full tracking.

**Pilot probe deferred to v3.3.2** (was v3.3.1). The pilot block theme is built FROM lab; constructing it before lab cleanup would either carry contamination forward or require rework. Substrate-first principle applies.

**After lab cleanup stabilizes (v3.3.1) → pilot probe (v3.3.2)**: `axismundi-pilot/` block theme probe. Minimal block theme constructed from clean lab styleguide + ontology to verify core block mapping extension viability. Not a distributable — a probe.

**After pilot probe succeeds (v3.4.0+)**: distributable theme construction at `products/distributables/themes/axismundi/`. This is the clean rebuild that the prototype was demoted for — it draws from lab QA findings + pilot probe + selective prototype archeology.

### Phase 9 anti-patterns (carried forward from Phase 8 closure)

Explicit refusals that protect the build phase:

- **"New KB chunk addition" impulse** → reject. KB is frozen; engine-room manual only.
- **"Phase 8.49" mental impulse** → reject. There is no Phase 8.49; doctrinal era closed.
- **Doctrine refinement before build** → reject. Refinement happens when build encounters a *positive control* gap, not as preventive work.
- **Tier B scope creep** → reject. Pilot is minimal-verification, not feature-complete.

These anti-patterns are sustained guardrails. They prevent the principal failure mode (Analysis Overhang) that GPT consultation flagged as the highest risk to v1.0.0 shipping.

---

## VII. Backup / archive recommendation

Per GPT consultation, the following workstation-level structure is recommended:

```
Axismundi_Work/
├── current/
│   └── axismundi/                          ← current monorepo working copy
│
├── freezes/                                ← milestone snapshots
│   ├── axismundi-v3.1.0-freeze.zip          (monorepo baseline)
│   ├── axismundi-v3.2.3-freeze.zip          (font/icon/lab stable point)
│   ├── axismundi-v3.3.0-freeze.zip          (lab promotion + asset relocation)
│   └── _old/                                (superseded intermediates)
│       ├── axismundi-v3.2.0.zip
│       ├── axismundi-v3.2.1.zip
│       └── axismundi-v3.2.2.zip
│
├── pre-monorepo-archive/                   ← project archaeology
│   ├── README.md
│   ├── TIMELINE.md
│   ├── MANIFEST.md
│   ├── refine-series/                       (refine.zip → refine_v1.0.1.zip)
│   ├── ontology-series/                     (ontology_core_v0_2 → ontology_v2_1a_p0)
│   ├── prototype-series/                    (Axismundi-chunk-* + phase-2B-*.zip)
│   └── _discard-candidates/
│
├── external-assets/                        ← original third-party downloads
│   ├── google-fonts-original/
│   └── material-symbols-original/
│
└── _LATEST.md                              ← authority pointer
```

The monorepo (`current/axismundi/`) should not contain pre-monorepo zips, original font zips, or duplicate freeze zips. Those belong in their respective archive locations alongside (not inside) the monorepo.

---

## VIII. Risk register (carried forward from GPT γ-3 ultrareview)

| Risk level | Items |
|---|---|
| **LOW** | Token drift, ARIA duplication, CSS consistency |
| **MEDIUM** | theme.json migration complexity, block template mapping, PHP integration regressions |
| **HIGH** | Scope explosion, over-engineering before WP-native conformity, custom architecture vs wp.org conventions tension |

The most insidious risk remains **analysis overhang** — ontology continues to expand, KB continues to grow, structure remains correct, but no shippable product exists. v3.3.1 (pilot probe) is the antidote: a deliberate compression of accumulated knowledge into the smallest verifiable WordPress block theme.

---

## IX. One-line executive assessment

> **Axismundi has crossed from experimental design system into production-grade static architecture with an LLM-readable governance substrate. The frontier is no longer "can this work" but "how cleanly can lab-validated styleguide + ontology compress into a shippable distributable WordPress block theme."**

---

## Document history

| Date | Event |
|---|---|
| 2026-05-13 | First consolidated report — synthesizes Phase 1A through v3.3.0 across multiple sessions + GPT analyses |
| 2026-05-13 (later) | Supplemented with Phase 8 KB closure detail (~103 chunks, 14 bounded contexts, 6 Laws + 3b, 6 Doctrines, 5 toolkits, 3 audits, 1 frontier). A–F 6-layer mapping made canonical (Constitution Article 1 amended). Phase 9 anti-patterns articulated. cowork-phase8-kb-build-closure.md added to archive. |

This document is intended to be updated, not replaced, as subsequent milestones complete. Each major release (v3.4.0, v4.0.0, etc.) should add a row to the timeline + a milestone row + update the current frontier section.
