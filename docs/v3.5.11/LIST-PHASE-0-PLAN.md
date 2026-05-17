# Axismundi v3.5.11 — Wave 1 — List #33 Phase 0 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 0 report.  
> **Date**: 2026-05-17  
> **Target component**: List #33  
> **Matrix row**: `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #33.  
> **Wave 1 position**: 8th Wave 1 closure candidate; remaining Wave 1 after List is Carousel #34.

---

## §0 — Plan Verdict

```txt
Phase 0 plan is ready for review.

This plan proposes:
  - Category: Component Full-Spec.
  - Audit shape: Phase 0 must decide 3-doc vs 4-doc.
  - Default recommendation: 3-doc trio, unless Phase 0 discovers extracted
    runtime behavior beyond native button/link interaction.
  - Scope: List #33 only.
  - Explicitly not scope: Avatar #32 record closure, Menu #15, Tabs #14,
    Navigation rail/bar, custom listbox runtime, drag/reorder behavior.
  - Current baseline: components.css §26 + style-guide.html #components-list.
  - Dependencies to classify: ripple/, icon-system/, Avatar record.
```

No implementation occurs in this plan.

---

## §1 — Purpose

List #33 is the last Wave 1 TODO component. It is a high-frequency Display
surface and a core publishing-surface primitive:

```txt
List item rows
navigation-like rows
profile / notification rows
settings rows
content summaries
```

Phase 0 must prevent List from absorbing adjacent ontology:

```txt
List            = row/container primitive with 1/2/3-line layout and slots.
Avatar #32      = Baseline-only Record; used in List leading slot but not folded.
Menu #15        = action menu/list of commands; separate component.
Tabs #14        = panel switcher; separate component.
Nav bar/rail    = app navigation; separate components.
Listbox runtime = ARIA selection widget; only in scope if Phase 0 proves
                  extracted runtime is required.
Drag/reorder    = behavior-heavy pattern; defer unless baseline already owns it.
```

---

## §2 — Required Inputs

Phase 0 report must read:

```txt
Root context:
  AGENTS.md
  CURRENT-STATE.md
  NEXT-SESSION.md
  PROJECT-CONTEXT.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md
  docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md

Current baseline:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §26
  products/reference-implementations/axismundi-lab/style-guide.html #components-list
  products/reference-implementations/axismundi-lab/stylesheets/tokens.css
  products/reference-implementations/axismundi-lab/stylesheets/blocks.css

Records / dependencies:
  components.css §1 Avatar
  style-guide.html #components-avatar
  docs/v3.5.0/MODULE-STATUS-MATRIX.md row #32 Avatar
  ripple v2 docs and lab module
  icon-system docs and lab module

Precedents:
  Button v3.5.1
  Icon button v3.5.2
  Card v3.5.3
  FAB v3.5.5
  Ripple v3.5.6
  Text field v3.5.7
  Search bar v3.5.8
  Button group v3.5.10
```

External spec read:

```txt
https://m3.material.io/components/lists/overview
https://m3.material.io/components/lists/specs
https://m3.material.io/components/lists/guidelines
https://m3.material.io/components/lists/accessibility
```

If the M3 pages are JavaScript-rendered, use Playwright extraction as in
Button group Phase 0.

---

## §3 — Required Phase 0 Report Shape

`docs/v3.5.11/LIST-PHASE-0-REPORT.md` should use this shape:

```txt
§0  Phase 0 framing
§1  Authoritative inputs
§2  Baseline §26 inventory
§3  Public specimen inventory (#components-list)
§4  M3 spec digest
§5  Category + audit-shape decision (3-doc vs 4-doc)
§6  Native semantics decision tree
§7  Variant / slot matrix
§8  Dependency profile / consumer-state
§9  Avatar #32 boundary
§10 Ripple TARGET decision
§11 WordPress mapping stub
§12 Playwright + class-name precision QA plan
§13 Risks + dispositions
§14 Phase 1 entry conditions
§15 G1-G10 applicability
§16 Non-goals
§17 Verdict
```

---

## §4 — Lock Questions Phase 0 Must Settle

### §4.1 — Audit Shape: 3-Doc vs 4-Doc

Decision required:

```txt
Does List need only the Component Full-Spec trio:
  LIST-SPEC-AUDIT.md
  LIST-MEASUREMENT-AUDIT.md
  LIST-WP-MAPPING.md

or does it need a 4th runtime audit:
  LIST-RUNTIME-AUDIT.md
```

Recommendation:

```txt
Default to 3-doc trio.
```

Rationale:

```txt
Baseline §26 currently uses native button/link surfaces and CSS state
behavior. There is no extracted list runtime module like search-expansion/.
Selection is represented by static .is-selected / aria-selected="true".
```

Escalate to 4-doc only if Phase 0 discovers:

```txt
keyboard-managed listbox behavior
multi-select roving tabindex
drag/reorder runtime
virtualized list runtime
async loading / infinite list behavior owned by theme JS
```

### §4.2 — List vs Avatar Boundary

Decision required:

```txt
Avatar #32 remains Baseline-only Record and is not folded into List #33.
```

Phase 0 must cite v3.5.0 Phase 0B:

```txt
Avatar stays standalone because it appears outside List (chat, profile,
comment thread), even though List uses it as a leading slot.
```

List audit should treat Avatar as:

```txt
composition dependency / leading-slot consumer
not a subcomponent owned by List
```

### §4.3 — Variant Scope

Phase 0 must inventory:

```txt
1-line item
2-line item
3-line item
standard list
segmented list
leading icon
leading Avatar
leading image
trailing icon
trailing text
divider
selected state
disabled state
```

Phase 0 must decide whether all are v3.5.11 Phase 2 scope or whether some are
deferred.

Recommendation:

```txt
All baseline-owned variants above are in scope.
Video slot, drag handle, reorder, and multi-select differentiated visuals are
deferred because baseline comments already defer them.
```

### §4.4 — Native Semantics Decision Tree

Phase 0 must lock a decision tree:

```txt
Static content list:
  <ul>/<ol>/<li> or <div role="list"> + non-interactive rows

Action row:
  <button type="button" class="ax-list__item">

Navigation row:
  <a class="ax-list__item" href="...">

Selected navigation/value row:
  aria-selected="true" only when the owning pattern defines selection context

Forbidden:
  <div role="button" class="ax-list__item">
  click-only non-focusable rows
  using List to implement Tabs / Menu / Nav rail
```

### §4.5 — Ripple Consumer-State

Matrix v3.5.10 currently records:

```txt
List #33 item hover/action surface = CANDIDATE
```

Phase 0 must decide whether List promotes to TARGET:

```txt
Option A — promote to TARGET bounded per interactive item
Option B — remain CANDIDATE until Phase 2 evidence
```

Recommendation:

```txt
Promote to intended TARGET for interactive list items, bounded per item.
Base non-interactive list rows remain NONE.
```

Rationale:

```txt
List item is a row-sized interactive surface with hover/focus/pressed state
layer. Ripple v2 already supports bounded row surfaces. This mirrors Card's
"base NONE / action surface TARGET" split.
```

### §4.6 — icon-system / Avatar / ripple Dependency Profile

Phase 0 must classify:

```txt
icon-system/:
  CURRENT conditional (leading/trailing icon slots)

Avatar #32:
  RECORD composition dependency for leading slot

ripple/:
  TARGET bounded for interactive items if §4.5 Option A is adopted
  NONE for static/non-interactive list rows

components.css §0 state-layer:
  CURRENT
```

### §4.7 — WordPress Mapping Stub

Phase 0 should enumerate likely WP surfaces:

```txt
core/list
core/query-loop / post-template rows
core/navigation
core/latest-posts
core/categories
core/comments
plugin-owned activity feeds / notifications / search results
```

Expected boundary:

```txt
Theme can style/pattern row surfaces and slots.
Plugin owns data source, pagination, selection behavior, async loading,
and personalized feeds.
```

---

## §5 — Baseline Inventory Requirements

Phase 0 report must record exact baseline class names:

```txt
.ax-list
.ax-list--segmented
.ax-list__item
.ax-list__item--two-line
.ax-list__item--three-line
.ax-list__leading
.ax-list__content
.ax-list__overline
.ax-list__label
.ax-list__supporting
.ax-list__trailing
.ax-list__trailing-text
.ax-list__divider
.ax-list__leading-image
```

Class-name precision rule:

```txt
Do not invent .list, .list-item, .md-list, .ax-list-item, or .ax-list__body.
Use baseline §26 class names exactly.
```

---

## §6 — Playwright / QA Plan Seed

Phase 0 report should define Phase 2/3 QA needs:

```txt
1. 1/2/3-line row heights: 56 / 72 / 88
2. leading icon 24px
3. leading Avatar 40px
4. leading image 56px
5. trailing icon 24px
6. segmented gap 2px
7. state morph: rest extra-small, hover medium, focus/pressed/selected large
8. selected row color and child text/icon color
9. disabled row opacity/color
10. mobile long-label overflow / supporting text clamp
11. bounded ripple stays inside item
12. static list rows do not receive ripple
```

---

## §7 — Expected Risks

| Risk | Why It Matters | Expected Disposition |
|---|---|---|
| R1 Audit shape drift | List can be mistaken for listbox runtime. | Default 3-doc; 4-doc only if extracted runtime exists. |
| R2 Avatar folding | Avatar appears in List leading slot. | Keep Avatar standalone RECORD; List composes it. |
| R3 Ripple over-application | Static rows should not animate. | Split interactive TARGET / static NONE. |
| R4 Menu/Tabs/Nav drift | List visuals resemble other row systems. | Native semantics decision tree. |
| R5 Role misuse | `button role=listitem` may be questionable. | Phase 0/1 must verify semantic pattern. |
| R6 WP mapping overclaim | core/list is content list, not interactive row system. | WP-MAPPING separates theme pattern vs plugin behavior. |
| R7 Long content overflow | List is content-heavy. | Phase 2 Playwright mobile text checks. |

---

## §8 — Non-Goals

Phase 0 does not:

```txt
- create lab/modules/list/
- create LIST-SPEC-AUDIT.md
- create lab-list.css
- create lab-list-pattern.html
- edit components.css
- edit style-guide.html
- edit tokens.css
- edit blocks.css
- close Avatar #32 record
- implement Menu / Tabs / Nav components
- implement drag/reorder behavior
- implement virtualized/infinite lists
- update CHANGELOG / ROADMAP / CURRENT-STATE / NEXT-SESSION
```

---

## §9 — Validation

Plan-only validation:

```txt
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS
baseline mtimes unchanged
no lab/modules/list/ directory
```

---

## §10 — Self-Check

```txt
self-check:
  List #33 row referenced                     yes
  Wave 1 sequence                             yes (List -> Carousel -> #32)
  3-doc vs 4-doc decision required            yes
  default 3-doc recommendation                yes
  Avatar boundary                             yes
  ripple TARGET split                         yes
  icon-system conditional                     yes
  class-name precision                        yes
  M3 external Playwright fallback             yes
  WP mapping stub                             yes
  Playwright QA seed                          yes
  non-goals / no baseline edit                yes
```

---

## §11 — Plan Verdict

```txt
READY FOR REVIEW.

If approved:
  Codex writes LIST-PHASE-0-REPORT.md.

If revised:
  update this plan only; do not start Phase 0 report.
```

