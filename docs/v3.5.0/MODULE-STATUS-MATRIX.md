# Module Status Matrix — v3.5.0 Phase 1A

> Phase 1A deliverable. Formal status matrix derived from `33-COMPONENT-INVENTORY.md` (Phase 0A+0B). Three ontology axes — TOC group / Category / Dependency — are recorded explicitly per row.
>
> Companion document: `COMPONENT-COVERAGE-MAP.md` (visual distribution maps).
>
> Phase 1B will derive `PROMOTION-CRITERIA.md` + `PUBLIC-SURFACE-CHARTER.md` from this matrix.

> **v3.5.4 amendment**: Consumer-state vocabulary added after the first
> three Wave 1 cycles (Button v3.5.1, Icon button v3.5.2, Card v3.5.3).
> Row #36 `ripple/` is corrected from a flat inferred consumer list to
> state-aware buckets. This is an explicit amendment, not a silent rewrite
> of the v3.5.0 Phase 1A deliverable.
>
> **v3.5.6 amendment**: Ripple v2 contract landed as the stable public
> contract for animated ripple enhancement. Row #36 now records
> `data-ax-ripple`, bounded/unbounded variants, transitional HOST_SELECTOR
> compatibility, and the v3.5.6 promotion of FAB family + Card action
> surfaces from CANDIDATE to TARGET after Phase 3 visual QA.
>
> **v3.5.9 amendment**: BACKLOG #31 closed as a baseline correction. Static
> `corner-full` remains 9999px, while Button and Button group morph sources
> now use finite pill radii to avoid interpolation flicker. Component status
> counts are unchanged.
>
> **v3.5.10 amendment**: Button group #6 closed (TODO → DONE). Single 3-doc
> trio audit shape. Pattern A radio+label + Pattern B button+aria-pressed
> both supported. Ripple #36 row sub-table promoted Button group from
> CANDIDATE to TARGET bounded per segment. M3 XS/S/M/L/XL size coverage
> remains partial because the Button family ships only default M size;
> tracked as BACKLOG #32 — Button family size variants cycle. Component
> status counts shift from 10 DONE / 18 TODO to 11 DONE / 17 TODO.

> **v3.5.11 amendment**: List #33 closed (TODO → DONE). Single 3-doc
> trio audit shape; no runtime audit. Interactive action/navigation/selectable
> list rows promote from ripple CANDIDATE to TARGET bounded per row, while
> static informational rows remain ripple NONE. `components.css` §26 received
> a small in-cycle List color alignment patch for segmented container surface
> and direct trailing icon color. Component status counts shift from 11 DONE /
> 17 TODO to 12 DONE / 16 TODO.
>
> **v3.5.12 amendment**: Carousel #34 closed (PARTIAL → DONE). 4-doc
> audit shape because the v3.3.2 `carousel/` module owns extracted JS runtime.
> Reduced-motion and Home/End blockers were closed in `lab-carousel.css/js`,
> no-JS fallback was preserved with an `.is-enhanced` runtime marker, and
> Gallery remains DISTINCT but COUPLED with conditional binding only.
> Component status counts shift from 12 DONE / 3 PARTIAL to 13 DONE /
> 2 PARTIAL. Wave 1 is now 9 / 9 complete.
>
> **v3.5.13 amendment**: Wave 1 closure cleanup completed. BACKLOG #32
> Button family size variants and BACKLOG #33 List full-token coverage are
> closed. Baseline-only Records now have record-only audit docs under
> `lab/modules/_records/` while retaining RECORD status. Component status
> counts are unchanged: 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD.

## §1 — Hard rule: row counting

```
Baseline-only Records are component rows with RECORD status,
NOT additional matrix entries.

Infrastructure providers are separate non-component entries
because they are reusable runtime dependencies, not TOC components.
```

```
Baseline-only Record는 별도 컴포넌트가 아니라 RECORD 상태를 가진
component row다.
Infrastructure provider는 재사용 런타임 의존성이므로 component row와
별도 non-component entry로 관리한다.
```

### Canonical entry count

```
34  TOC component rows  (Component Status Matrix §3)
     ├── 3 with RECORD status (Avatar / Divider / Badge — Baseline-only Records)
     ├── 13 with DONE status  (Button / Icon button / FAB / Extended FAB /
     │                         Button group / Card / Text field / Search bar /
     │                         Chip / Snackbar / Tooltip / List / Carousel)
     ├── 2 with PARTIAL status (Date picker / Time picker)
     └── 16 with TODO status

 3  Infrastructure provider rows  (Infrastructure Matrix §4)
     popover/ · ripple/ · icon-system/

══
37  canonical entries total
```

Anything that appears in §5 Baseline-only Records summary is a *cross-reference* to its component row, not a new entry.

## §2 — Column definitions

The 12 columns recorded for each row:

```
1. #              row number (canonical within section)
2. Component      component name (or infrastructure module name in §4)
3. TOC Group      Foundation / Actions / Containers / Navigation /
                  Inputs / Selection / Feedback / Display
                  (or "Cross-cutting Infrastructure" in §4)
4. Category       4-category framework:
                    Component Full-Spec Module / Interaction Runtime Module /
                    Baseline-only Record / Plugin-territory Mapping
                  (or "Interaction Runtime Infrastructure" in §4)
5. Status         DONE / PARTIAL / TODO / RECORD
6. Existing       Current lab/modules/<name>/ directory if any; — if none
7. Target         Planned lab/modules/<name>/ directory; "(records-only)"
                  for RECORD status; same as Existing for Infrastructure
8. Dep type       Consumer / Provider / Independent
9. Provider       Infrastructure provider (when Dep type = Consumer);
                  — when Independent or Provider
10. Consumers     List of consumer modules (when Dep type = Provider);
                  — when Consumer or Independent
11. Wave          1 / 2 / 3 / record / done / infra
12. Notes         DISTINCT but COUPLED markers; release version (for DONE);
                  carry-over BACKLOG refs; brief risk note
```

### Consumer-state vocabulary (v3.5.4 amendment)

Infrastructure provider edges use these states:

```txt
CURRENT:
  Provider is presently wired into this consumer surface.

TARGET:
  Provider is a designed dependency for this consumer, but not wired in
  the current baseline/module surface.

CANDIDATE:
  Provider is a plausible or previously inferred dependency, but the
  design decision or implementation evidence is insufficient.

NONE:
  No provider dependency for this consumer surface.

CURRENT conditional:
  Provider is wired only when a slot/composition path uses it.

CURRENT conditional via composition:
  Provider is not a direct dependency of the host component, but becomes
  current through nested composed components.
```

`TARGET` does **not** mean baseline-wired. For `ripple/`, CURRENT is
presently `none`; TARGET means the current ripple module contract or
allowlist treats the consumer as a designed target, while the main
style-guide baseline still does not load ripple.

## §3 — Component Status Matrix (34 TOC component rows)

### Actions (8 rows)

| # | Component | TOC Group | Category | Status | Existing | Target | Dep type | Provider | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|---|:---:|---|
| 1 | Button | Actions | Component Full-Spec | **DONE** (v3.5.1) | `button/` | `button/` | Consumer | `ripple/`, `icon-system/` (conditional) | — | done | Wave 1 #1 closed. State-layer CURRENT; ripple TARGET; icon-system CURRENT conditional. v3.5.9 baseline correction: Button pill radius now uses finite height/2 source for stable active morph. v3.5.13 adds opt-in XS/S/M/L/XL size hooks; default no-size Button remains 40px |
| 2 | Icon button | Actions | Component Full-Spec | **DONE** (v3.5.2) | `icon-button/` | `icon-button/` | Consumer | `ripple/`, `icon-system/` | — | done | Wave 1 #2 closed. icon-system CURRENT unconditional; ripple TARGET. v3.5.13 adds opt-in XS/S/M/L/XL size hooks and closes the finite-radius tail from BACKLOG #31 for composed Icon buttons |
| 3 | FAB | Actions | Component Full-Spec | **DONE** (v3.5.5) | `fab/` | `fab/` | Consumer | `ripple/`, `icon-system/` | — | done | Wave 1 #4 closed as FAB family. icon-system CURRENT unconditional; ripple TARGET after v3.5.6 Ripple v2 (unbounded) |
| 4 | Extended FAB | Actions | Component Full-Spec | **DONE** (v3.5.5) | (folds into `fab/`) | (folds into `fab/`) | Consumer | `ripple/`, `icon-system/` | — | done | Closed with #3 as static Extended FAB. Collapse/expand behavior deferred to BACKLOG #30; ripple TARGET after v3.5.6 |
| 5 | FAB menu | Actions | Component Full-Spec + Interaction | TODO | — | `fab-menu/` | Consumer | `popover/`, `ripple/`, `icon-system/` | — | 2 | Depends on FAB family + Menu module; DISTINCT but COUPLED with popover/ |
| 6 | Button group | Actions | Component Full-Spec | **DONE** (v3.5.10) | `button-group/` | `button-group/` | Consumer | `ripple/` | — | done | Wave 1 #7 closed. 3-doc trio (no RUNTIME). Pattern A radio+label single-select + Pattern B button+aria-pressed multi-toggle. Ripple TARGET bounded per segment; group container has no ripple. icon-system CURRENT conditional. v3.5.9 finite pill morph contract inherited. v3.5.13 closes BACKLOG #32: XS/S/M/L/XL size hooks now functional; default no-size Button group remains unchanged |
| 7 | Split button | Actions | Component Full-Spec + Interaction | TODO | — | `split-button/` | Consumer | `popover/`, `ripple/` | — | 2 | Primary action + chevron dropdown; DISTINCT but COUPLED with popover/ |
| 8 | Toolbar | Actions | Component Full-Spec | TODO | — | `toolbar/` | Consumer | `ripple/` | — | 2 | Floating-with-FAB deferred (v1.5+) |

### Containers (2 rows)

| # | Component | TOC Group | Category | Status | Existing | Target | Dep type | Provider | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|---|:---:|---|
| 9 | Card | Containers | Component Full-Spec | **DONE** (v3.5.3) | `card/` | `card/` | Consumer | `ripple/` (action TARGET), `icon-system/` (composition-only) | — | done | Wave 1 #3 closed. Base Card ripple NONE; action Card ripple TARGET after v3.5.6 Ripple v2; core/group bridge CURRENT partial |
| 10 | Divider | Containers | Baseline-only Record | RECORD | — | (records-only) | Independent | — | — | record | Simple visual primitive; maps to `core/separator`. v3.5.13 record-only audit exists under `lab/modules/_records/` |

### Navigation (5 rows)

| # | Component | TOC Group | Category | Status | Existing | Target | Dep type | Provider | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|---|:---:|---|
| 11 | App bar | Navigation | Component Full-Spec | TODO | — | `app-bar/` | Consumer | `ripple/` (action slots), `icon-system/` | — | 2 | Anchored to viewport; scroll behavior; navigation slots |
| 12 | Nav bar | Navigation | Component Full-Spec | TODO | — | `nav-bar/` | Consumer | `ripple/`, `icon-system/` | — | 2 | Mobile bottom-nav; sister to Nav rail |
| 13 | Nav rail | Navigation | Component Full-Spec | TODO | — | `nav-rail/` | Consumer | `ripple/`, `icon-system/` | — | 2 | Single module covers collapsed + expanded variants |
| 14 | Tabs | Navigation | Component Full-Spec + Interaction | TODO | — | `tabs/` | Independent | — | — | 2 | Primary + secondary variants; indicator animation + arrow-key nav (Interaction); same dual pattern as Search bar |
| 15 | Menu | Navigation | Component Full-Spec + Interaction dependency | TODO | (uses `popover/` runtime) | `menu/` | Consumer | `popover/`, `icon-system/` | — | 2 | **DISTINCT but COUPLED with popover/.** Menu owns role=menu/menuitem/density/icon/shortcut/selected/disabled/divider/submenu; popover/ owns anchor/position/dismiss/outside-click/Escape/focus restore/viewport collision |

### Inputs (8 rows)

| # | Component | TOC Group | Category | Status | Existing | Target | Dep type | Provider | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|---|:---:|---|
| 16 | Text field | Inputs | Component Full-Spec + Interaction | **DONE** (v3.5.7) | `text-field/` | `text-field/` | Independent | — | — | done | Wave 1 #5 closed. First Inputs family entry and first dual-category closure; 3-doc trio with interaction coverage embedded in SPEC; no runtime audit or JS module |
| 17 | Search bar | Inputs | Component Full-Spec + Interaction | **DONE** (v3.5.8) | `search-bar/` (v3.5.8) + `search-expansion/` preserved as historical runtime evidence | `search-bar/` | Independent | — | — | done | Wave 1 #6 closed. DISTINCT from Text field per Phase 0B; first 4-doc dual-category closure. Field host ripple NONE; trailing icon-button consumes ripple via own route. `search-expansion/` remains untouched as transitional evidence |
| 18 | Checkbox | Inputs | Component Full-Spec | TODO | — | `checkbox/` | Independent | — | — | 2 | Native `<input type="checkbox">` + custom visual; indeterminate state |
| 19 | Radio | Inputs | Component Full-Spec | TODO | — | `radio/` | Independent | — | — | 2 | Native input + radiogroup |
| 20 | Switch | Inputs | Component Full-Spec | TODO | — | `switch/` | Independent | — | — | 2 | Native checkbox + custom visual |
| 21 | Slider | Inputs | Component Full-Spec | TODO | — | `slider/` | Independent | — | — | 3 | Phase 0B: NO separate Interaction module; native `<input type="range">` + CSS |
| 22 | Date picker | Inputs | Component Full-Spec + Interaction | PARTIAL | `date-time/` (v3.4.7) | `date-time/` (Full-Spec layer added) | Consumer | `popover/` | — | 2 | Module merges Date + Time picker (Phase 0B decision); WAI-ARIA grid in BACKLOG #19 |
| 23 | Time picker | Inputs | Component Full-Spec + Interaction | PARTIAL | `date-time/` (v3.4.7) | (folds into `date-time/`) | Consumer | `popover/` | — | 2 | Single module covers both; module-level merge with #22 |

### Selection (2 rows)

| # | Component | TOC Group | Category | Status | Existing | Target | Dep type | Provider | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|---|:---:|---|
| 24 | Chip | Selection | Component Full-Spec | **DONE** | `chip/` (v3.4.9) | `chip/` | Consumer | `ripple/`, `icon-system/` (leading/trailing) | — | done | First Component Full-Spec Module. Elevated variants → BACKLOG #23 |
| 25 | Badge | Selection | Baseline-only Record | RECORD | — | (records-only) | Independent | — | — | record | Small visual primitive; numeric/dot variants; attaches to other components. v3.5.13 record-only audit exists under `lab/modules/_records/` |

### Feedback (6 rows)

| # | Component | TOC Group | Category | Status | Existing | Target | Dep type | Provider | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|---|:---:|---|
| 26 | Dialog | Feedback | Interaction Runtime | TODO | — | `dialog/` | Independent | — | — | 2 | Basic + full-screen; focus trap; backdrop click; ESC dismiss |
| 27 | Sheet | Feedback | Interaction Runtime | TODO | — | `sheet/` | Independent | — | — | 2 | Bottom-modal + side-modal; drag-to-dismiss; often paired with Dialog |
| 28 | Snackbar | Feedback | Interaction Runtime | **DONE** | `snackbar/` (v3.4.10) | `snackbar/` | Independent | — | — | done | Naming inconsistency → BACKLOG #18 (v3.5.0 sweep deferred to Phase 1B) |
| 29 | Tooltip | Feedback | Interaction Runtime | **DONE** | `tooltip/` (v3.4.6) | `tooltip/` | Independent | — | — | done | Touch long-press + rich tooltip → BACKLOG #16 |
| 30 | Loading | Feedback | Component Full-Spec | TODO | — | `loading/` | Independent | — | — | 3 | Spinner family; lower complexity |
| 31 | Progress | Feedback | Component Full-Spec | TODO | — | `progress/` | Independent | — | — | 3 | Linear + circular; determinate + indeterminate; wavy variant deferred (v1.5+) |

### Display (3 rows)

| # | Component | TOC Group | Category | Status | Existing | Target | Dep type | Provider | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|---|:---:|---|
| 32 | Avatar | Display | Baseline-only Record | RECORD | — | (records-only) | Independent | — | — | record | Standalone (not folded into List); uses outside List (chat, profile). v3.5.13 record-only audit exists under `lab/modules/_records/` |
| 33 | List | Display | Component Full-Spec | **DONE** (v3.5.11) | `list/` | `list/` | Consumer | `ripple/` (interactive rows), `icon-system/` (conditional leading/trailing icons), Avatar #32 composition | — | done | Wave 1 #8 closed. 3-doc trio; no RUNTIME. 1/2/3-line rows, standard + segmented styles, state matrix, static/action/navigation semantics. Interactive rows ripple TARGET bounded per item; static informational rows ripple NONE. Avatar remains Baseline-only Record composition, not folded into List. v3.5.13 closes BACKLOG #33: focus indicator, selected-disabled, segmented wrapper/item token split, expand trailing icon container, and trailing supporting time are aligned. `core/list` is partial static mapping only |
| 34 | Carousel | Display | Component Full-Spec + Interaction | **DONE** (v3.5.12) | `carousel/` (v3.3.2, v3.5.12 Full-Spec close) | `carousel/` | Independent | — | — | done | Wave 1 #9 closed. 4-doc audit shape because lab-carousel.js owns extracted runtime. Reduced-motion + Home/End blockers closed; no-JS fallback preserved via `.is-enhanced`. Gallery is not Carousel; conditional binding only. Full-screen remains deferred/integration scope |

## §4 — Infrastructure Provider Matrix (3 rows)

Cross-cutting non-component entries. These modules are reusable runtime/foundation dependencies consumed by component modules. They do NOT appear in TOC navigation as components.

| # | Provider | TOC Group | Category | Status | Existing | Target | Dep type | Consumers | Wave | Notes |
|---:|---|---|---|:---:|---|---|:---:|---|:---:|---|
| 35 | `popover/` | Cross-cutting Infrastructure | Interaction Runtime Infrastructure | DONE (v3.4.5) | `popover/` | `popover/` | Provider | Menu #15, Split button #7, FAB menu #5, Date+Time picker #22+#23, (future) Select | infra | Anchor + position + dismiss + outside-click + Escape + focus restore + viewport collision. MUST NOT absorb consumer semantics |
| 36 | `ripple/` | Cross-cutting Infrastructure | Interaction Runtime Infrastructure | DONE (v3.5.6) | `ripple/` | `ripple/` | Provider | See state-aware sub-table below | infra | v3.5.6 amendment: Ripple v2 contract landed with `data-ax-ripple`, bounded/unbounded modes, `--md-ripple-*` bridge tokens, `window.axRipple.attach/detach/refresh`, and transitional HOST_SELECTOR compatibility. State-layer Pattern A foundation remains baseline CSS, distinct from animated ripple |
| 37 | `icon-system/` | Cross-cutting Infrastructure | Foundation | DONE (Material Symbols migration complete) | `icon-system/` | `icon-system/` | Provider | Icon button #2, FAB #3+#4, FAB menu #5, Menu #15, App bar #11, Nav bar #12, Nav rail #13, Chip #24 (leading/trailing), List #33 (leading icons), etc. | infra | Material Symbols + SVG track + icon font policy. Multiple internal audit docs already |

### Row #36 `ripple/` consumer-state buckets (v3.5.4 + v3.5.6 + v3.5.10 + v3.5.11 amendments)

| Consumer state | Consumers | Notes |
|---|---|---|
| CURRENT | none | `lab-ripple` is loaded by its lab pattern page, not by the main baseline style guide. No component is currently baseline-wired to animated ripple. |
| TARGET | Button #1 (bounded), Icon button #2 (unbounded), Button group #6 (bounded per segment, v3.5.10), Chip #24 (bounded), Menu #15 (bounded), Nav bar #12 (bounded), Nav rail #13 (bounded), Tabs #14 (bounded), FAB #3 + Extended FAB #4 (unbounded), Card #9 action/interactive surface (bounded), List #33 interactive/action/navigation rows (bounded, v3.5.11) | v3.5.6 Ripple v2 stable contract: `data-ax-ripple` opt-in plus transitional HOST_SELECTOR compatibility. Nav bar/Nav rail were verified as bounded during Phase 3 visual QA. v3.5.10 promoted Button group from CANDIDATE to TARGET bounded after Phase 3 Playwright confirmed segment-only consumer route (group container ripple count 0). v3.5.11 promoted List interactive rows to TARGET bounded; list container and static informational rows remain NONE. |
| CANDIDATE | FAB menu #5, Split button #7, Toolbar #8, App bar #11 action slots | Plausible consumers requiring their own component cycle or interaction decision before TARGET promotion. |
| NONE | Card #9 base visual card; non-interactive components and baseline-only records unless separately promoted | Card has split state: base visual Card = NONE; action/interactive Card = TARGET after v3.5.6. |

## §5 — Baseline-only Records summary

> NOT separate matrix entries. These are cross-references to component rows already in §3 with RECORD status. Listed here only as a navigational summary of the Baseline-only Record category.

| Cross-ref | Component | TOC Group | Brief record scope (target audit doc) |
|---|---|---|---|
| → row #10 | **Divider** | Containers | Baseline visual is sufficient; record-only audit doc to capture: variant inventory (inset / full-bleed / vertical?), WP mapping (`core/separator`), reduced-motion N/A, no module needed |
| → row #25 | **Badge** | Selection | Baseline visual is sufficient; record-only audit doc to capture: variant inventory (numeric / dot / small), attachment patterns to other components (typically Icon button + App bar action), no module needed |
| → row #32 | **Avatar** | Display | Baseline visual is sufficient; record-only audit doc to capture: size variants (xs/sm/md/lg), shape (circle / rounded-square), content slots (initials / image / icon), used in List (leading) + chat (sender) + profile (header), no module needed |

The Baseline-only Record category exists precisely to keep the 33-component audit promise honest without forcing trivial primitives into the `lab/modules/<name>/` shape. Each record-only audit doc is expected to be roughly 1-2 pages (small fraction of a Full-Spec module audit doc).

## §6 — Status distribution snapshot

| Status | Count | Components |
|---|---:|---|
| **DONE** | 13 | Button #1, Icon button #2, FAB #3, Extended FAB #4, Button group #6, Card #9, Text field #16, Search bar #17, Chip #24, Snackbar #28, Tooltip #29, List #33, Carousel #34 |
| **PARTIAL** | 2 | Date picker #22 + Time picker #23 (date-time) |
| **TODO** | 16 | All remaining Wave 2/3 modules to be authored |
| **RECORD** | 3 | Avatar #32, Divider #10, Badge #25 |
| Component total | **34** | — |
| Infrastructure (DONE) | 3 | popover/ #35, ripple/ #36, icon-system/ #37 |
| Grand total | **37** | canonical entries |

## §7 — Dependency snapshot (informal, formalized in COVERAGE-MAP §3)

```
popover/ consumers (5):     Menu #15, Split button #7, FAB menu #5,
                            Date picker #22, Time picker #23
                            (+ future Select)

ripple/ consumers (state-aware, v3.5.4 + v3.5.6 + v3.5.10 + v3.5.11):
  CURRENT:    none
  TARGET:     Button #1, Icon button #2, Button group #6 (v3.5.10),
              Chip #24, Menu #15, Nav bar #12, Nav rail #13, Tabs #14,
              FAB #3 + Extended FAB #4, Card #9 action/interactive,
              List #33 interactive rows (v3.5.11)
  CANDIDATE:  FAB menu #5, Split button #7,
              Toolbar #8, App bar #11 action slots
  NONE:       Card #9 base visual card; non-interactive components and
              baseline-only records unless separately promoted

icon-system/ consumers (10): Icon button #2, FAB #3, Extended FAB #4,
                             FAB menu #5, Menu #15, App bar #11, Nav bar #12,
                             Nav rail #13, Chip #24, List #33

Independent components (no infrastructure dependency):
  Card (visual chrome only without action), Divider, Tabs, Text field,
  Search bar, Checkbox, Radio, Switch, Slider, Badge, Dialog, Sheet,
  Snackbar, Tooltip, Loading, Progress, Avatar, Carousel
```

Note: Independent ≠ "uses nothing"; it means no declared dependency on a current infrastructure module. A component may still use M3 system tokens, shared utilities, etc.

## §8 — Phase 1B carry-forward items

The following are recorded here but resolved by Phase 1B:

```
1. Promotion criteria — when does PARTIAL → DONE? When does a TODO row
   become a Wave-N release candidate? (PROMOTION-CRITERIA.md)

2. Public charter — formal architecture of public / lab / baseline /
   plugin tiers; infrastructure-vs-component principle; rename sweep
   schedule (PUBLIC-SURFACE-CHARTER.md)

3. .snackbar → .ax-snackbar rename sweep (BACKLOG #18) — execution
   deferred to Phase 1B; matrix records it as a Notes cell only

4. data-theme="auto" 3-state model (BACKLOG #22) — design in Phase 1B;
   does not affect any component row in this matrix

5. Theme-only color customization policy (BACKLOG #20) — Phase 1B
   charter content; not a per-component concern
```

## §9 — One-line summary

```
v3.5.12 updates the module status matrix: 34 TOC component rows
(13 DONE + 2 PARTIAL + 16 TODO + 3 RECORD) plus 3 infrastructure provider
rows (popover/ ripple/ icon-system/), for 37 canonical entries with
explicit Dependency Type / Provider / Consumers axis on every row —
"DISTINCT but COUPLED" relationships traced from Menu/Split button/
FAB menu/Date+Time picker through popover/, state-aware consumer buckets
through ripple/, and component/icon composition through icon-system/. Wave 1 is
now 9 / 9 complete.
```
