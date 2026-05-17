# Search Bar #17 — Phase 1 Plan (v3.5.8)

> **Status**: Plan-only. Do not execute Phase 1 audit docs until this plan is reviewed and approved.  
> **Target component**: Search bar #17  
> **Phase 0 source**: `docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md`  
> **Category**: Component Full-Spec + Interaction  
> **Core lock**: 4-doc audit shape because `search-expansion/` is an extracted JS runtime.

---

## §0 — Executive Summary

Search bar Phase 1 creates the audit surface that the Phase 0 report locked:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/docs/
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md
  SEARCH-BAR-RUNTIME-AUDIT.md
```

This is the second dual-category data point after Text field:

```txt
Text field #16
  native/CSS interaction
  3-doc trio
  no runtime audit

Search bar #17
  extracted JS runtime in search-expansion/
  4-doc audit set
  runtime audit required
```

Phase 1 remains documentation-only. It may create `lab/modules/search-bar/docs/`
and the four audit docs, but it must not create implementation files, move
`search-expansion/`, or edit baseline/public/state/release files.

---

## §1 — Non-Negotiable Scope Locks

Allowed in Phase 1 execution after this plan is approved:

```txt
- Create products/reference-implementations/axismundi-lab/modules/search-bar/docs/.
- Create SEARCH-BAR-SPEC-AUDIT.md.
- Create SEARCH-BAR-MEASUREMENT-AUDIT.md.
- Create SEARCH-BAR-WP-MAPPING.md.
- Create SEARCH-BAR-RUNTIME-AUDIT.md.
- Read search-expansion/ as current runtime evidence.
- Cross-reference SEARCH-EXPANSION-AUDIT.md as historical extraction record.
- Record Phase 2 migration options without executing them.
```

Forbidden in Phase 1:

```txt
- Create lab-search-bar.css.
- Create lab-search-bar.js.
- Create lab-search-bar-pattern.html.
- Move, rename, copy, or delete search-expansion/.
- Edit lab-search-expansion.css/js/pattern.html.
- Edit components.css §10 Search bar.
- Edit style-guide.html #components-search-bar.
- Edit tokens.css, blocks.css, theme.json.
- Edit CHANGELOG.md, ROADMAP.md, BACKLOG.md.
- Edit CURRENT-STATE.md or NEXT-SESSION.md.
- Couple Search bar to popover/ as TARGET.
- Implement live search, autocomplete data, federated search, analytics, or result rendering.
```

Phase 1 is audit body authoring, not implementation.

---

## §2 — Inputs To Read

### Cycle docs

```txt
docs/v3.5.8/SEARCH-BAR-PHASE-0-PLAN.md
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/33-COMPONENT-INVENTORY.md
```

### Baseline/public sources

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §10
products/reference-implementations/axismundi-lab/style-guide.html #components-search-bar
```

Required class-name inventory:

```txt
.search-bar
.search-bar::before
.search-bar__leading-icon
.search-bar__trailing
.search-bar__input
.search-bar__input::placeholder
.search-bar:focus-within
.search-bar[aria-disabled="true"]
```

Do not invent `.search-bar__container`, `.search-bar__suggestions`, or
`.search-bar__clear` as baseline selectors. If Phase 1 discusses them, mark
them as lab/future target selectors.

### Current runtime evidence

```txt
products/reference-implementations/axismundi-lab/modules/search-expansion/
  lab-search-expansion.css
  lab-search-expansion.js
  lab-search-expansion-pattern.html
  docs/SEARCH-EXPANSION-AUDIT.md
```

Key runtime evidence to extract:

```txt
- role=combobox on input
- aria-controls / aria-expanded / aria-autocomplete
- role=listbox / role=option for suggestions
- per-instance listbox id
- focus/input/pointerdown/focusout listeners
- Escape clear-then-collapse policy
- ArrowDown / ArrowUp / Home / End navigation
- forbidden ancestor guard
- reduced-motion handling
- demo-only suggestion data
```

### Reference precedents

Use this order:

```txt
1. Text field v3.5.7
   Closest Inputs sibling; contrast case: dual category but 3-doc because
   interaction is native/CSS.

2. Snackbar v3.4.10
   Runtime audit precedent; G11-G16 shape; runtime behavior as primary work.

3. Popover v3.4.5
   Runtime precedent and future CANDIDATE provider comparison.

4. Icon button v3.5.2
   Historical runtime-audit disposition precedent.

5. Button / Card / FAB / Ripple v2
   Dependency-state and Wave 1 discipline precedents.

6. Chip v3.4.9
   Original Component Full-Spec audit template.
```

### External references

Use Playwright/browser-rendered text if needed because M3 Search pages are
JavaScript-rendered:

```txt
https://m3.material.io/components/search/overview
https://m3.material.io/components/search/specs
https://m3.material.io/components/search/guidelines
https://material-web.dev/components/text-field/
```

Material Web remains secondary implementation reference only. Do not import
Material Web custom elements or treat them as Axismundi runtime authority.

---

## §3 — Deliverable Shape

### §3.1 — SEARCH-BAR-SPEC-AUDIT.md

Purpose:

```txt
Component Full-Spec ownership for Search bar.
```

Required sections:

```txt
§0  Audit status
§1  Inputs read
§2  Phase 0 verdict carry-forward
§3  Baseline inventory summary
§4  M3 Search alignment
§5  Search bar distinct-from-Text-field contract
§6  Variant and state matrix
§7  Native semantics and Principle 1 / Principle 2
§8  Slot and composition contract
§9  Dependency profile
§10 Search view and suggestions boundary
§11 Class-name precision rules
§12 G1-G10 Component Full-Spec readiness
§13 G11-G16 Interaction readiness cross-link
§14 Risks and deferred items
§15 Verdict
§16 Cross-references
§17 What this audit does NOT do
```

SPEC must explicitly state:

```txt
- Search bar is distinct from Text field.
- Field host ripple = NONE.
- Trailing icon-button ripple is via icon-button consumer.
- popover/ is CANDIDATE future alignment, not TARGET.
- search-expansion/ is current runtime evidence.
- Runtime behavior is owned by SEARCH-BAR-RUNTIME-AUDIT.md.
```

SPEC should not duplicate the full runtime audit. It should summarize and
cross-reference the runtime doc.

### §3.2 — SEARCH-BAR-MEASUREMENT-AUDIT.md

Purpose:

```txt
Measurement, token, geometry, motion, and WCAG evidence.
```

Required sections:

```txt
§0  Audit status
§1  Inputs read
§2  Baseline dimensions
§3  M3 Search measurement comparison
§4  Search bar shell tokens
§5  Icon and trailing action geometry
§6  Suggestions popup geometry
§7  Focus / expanded / disabled state measurements
§8  Motion and reduced-motion measurements
§9  WCAG SC applicability
§10 Playwright Phase 2/3 QA plan
§11 Verdict
§12 Cross-references
```

Measurement must cover:

```txt
- 56px baseline search-bar height.
- corner-full rest shape.
- leading icon size and placement.
- trailing icon-button/avatar composition.
- suggestion item minimum target size from search-expansion/.
- focused/expanded geometry.
- reduced-motion transform suppression.
```

WCAG scope should include:

```txt
- SC 1.4.3 Contrast Minimum
- SC 1.4.11 Non-text Contrast
- SC 2.1.1 Keyboard
- SC 2.4.3 Focus Order
- SC 2.4.7 Focus Visible
- SC 2.5.8 Target Size Minimum
- SC 2.5.5 Target Size Enhanced where applicable
- SC 3.3.2 Labels or Instructions
- SC 4.1.2 Name, Role, Value
- SC 4.1.3 Status Messages where async search is plugin territory
```

Be precise: backend result updates and live result announcements are plugin
territory unless v3.5.8 explicitly implements them, which Phase 1 does not.

### §3.3 — SEARCH-BAR-WP-MAPPING.md

Purpose:

```txt
WordPress block/theme/plugin boundary.
```

Required sections:

```txt
§0  Mapping status
§1  Inputs read
§2  core/search block mapping
§3  Search form markup inventory
§4  Theme-can surface
§5  Plugin-should surface
§6  Suggestions/autocomplete boundary
§7  ActivityPub / social-CMS search boundary
§8  Accessible-name and native form contract
§9  Anti-pattern inventory
§10 Runtime disposition and migration note
§11 Verdict
§12 Cross-references
```

WP-MAPPING must lock:

```txt
Theme can:
  visual shell, static layout, state styling, static suggestions shape,
  template/pattern placement.

Plugin should:
  query backend, autocomplete source, remote/federated search, analytics,
  search history, personalization, live result rendering.
```

Primary mapping:

```txt
core/search
```

Secondary mapping candidates:

```txt
header search pattern
sidebar/search widget pattern
query-loop search form
site search overlay template
custom block / plugin implementation for autocomplete
```

### §3.4 — SEARCH-BAR-RUNTIME-AUDIT.md

Purpose:

```txt
Interaction Runtime audit for Search bar.
```

Required sections:

```txt
§0  Runtime audit status
§1  Inputs read
§2  Runtime category framing
§3  Current search-expansion/ inventory
§4  Contract surface
§5  ARIA combobox/listbox contract
§6  Keyboard interaction contract
§7  Escape and collapse policy
§8  DOM mutation and idempotence policy
§9  Forbidden ancestor / prose guard
§10 Reduced motion
§11 Suggestions data boundary
§12 popover/ candidate alignment
§13 Phase 2 migration options
§14 G11-G16 readiness
§15 Risks
§16 Verdict
§17 Cross-references
§18 What this audit does NOT do
```

Runtime audit must make the Text field distinction explicit:

```txt
Text field:
  G11-G16 coverage inside SPEC because native/CSS state behavior.

Search bar:
  G11-G16 coverage in RUNTIME-AUDIT because extracted JS runtime exists.
```

Runtime audit must not move or rewrite `search-expansion/`.

---

## §4 — search-expansion/ Disposition Lock

Phase 1 records, but does not migrate.

Required phrasing:

```txt
search-expansion/ is current runtime evidence for Search bar v3.5.8.
SEARCH-EXPANSION-AUDIT.md is historical extraction record.
SEARCH-BAR-RUNTIME-AUDIT.md is the v3.5.8 canonical runtime audit surface.
Physical migration is deferred to Phase 2 plan/execution.
```

Phase 1 must enumerate Phase 2 options:

```txt
Option A — Absorb
  Move/rename runtime files into lab/modules/search-bar/.
  Leave search-expansion/ as historical doc only or remove after release.

Option B — Reference
  Keep search-expansion/ as runtime lane and make search-bar/ Full-Spec docs
  cross-reference it.

Option C — Transitional
  Create search-bar/ implementation wrappers and keep search-expansion/ as
  deprecated compatibility shell until a follow-up cleanup.
```

Recommended Phase 1 posture:

```txt
Record all three. Do not choose the physical migration mode yet unless
Phase 1 discovers a hard blocker.
```

---

## §5 — Dependency Profile Lock

Phase 1 must use state-aware dependency wording.

```txt
Search field host:
  components.css §10 Search bar shell = CURRENT
  search-expansion/ runtime = CURRENT-PARTIAL evidence
  icon-system/ = CURRENT conditional / canonical leading icon
  icon-button/ = CURRENT conditional composition for trailing actions
  ripple/ = NONE on field host
  popover/ = CANDIDATE future alignment

Trailing icon-button surface:
  icon-button/ = CURRENT composition
  icon-system/ = CURRENT via icon button glyph
  ripple/ = TARGET via icon-button consumer, not Search bar host

Suggestions/results surface:
  search-expansion/ = CURRENT-PARTIAL runtime evidence
  list-like semantics = local combobox/listbox/option contract
  ripple/ = CANDIDATE future alignment for suggestion items
  popover/ = CANDIDATE future anchored-surface alignment
  List #33 = CANDIDATE visual/semantic reference
```

Do not classify Search bar field host as ripple-dependent.

Do not classify Search bar as a `popover/` consumer in v3.5.8 Phase 1. Use
CANDIDATE language unless Phase 2 explicitly changes the runtime architecture.

---

## §6 — Runtime Contract Lock

`SEARCH-BAR-RUNTIME-AUDIT.md` must cover the current runtime contract from
`search-expansion/`:

```txt
Host selector:
  .search-bar

Forbidden ancestors:
  .prose
  .wp-block-post-content
  .entry-content
  [contenteditable]

Runtime-created surface:
  .ax-search-suggestions
  .ax-search-suggestions__item

ARIA:
  role=combobox
  aria-controls
  aria-expanded
  aria-autocomplete=list
  role=listbox
  role=option
  aria-selected

Keyboard:
  focus opens
  input opens
  ArrowDown moves from input to first option
  ArrowDown / ArrowUp move among options
  ArrowUp at first option returns to input
  Home / End jump
  Escape with text clears and keeps open
  Escape empty collapses

Data:
  demo suggestions are static
  live data belongs to plugin territory

Motion:
  prefers-reduced-motion disables transform animation
```

Phase 1 must not invent a public `window.axSearch` API. If Phase 2 might need
one, record it as a future option, not a Phase 1 contract.

---

## §7 — M3 Search Coverage Lock

Phase 1 must cover these M3 Search concepts:

```txt
- Search bar as distinct component.
- Search bar anatomy: container, leading icon, input text, trailing icon/avatar.
- Suggestions/results container.
- Contained and divided search styles.
- Focused/expanded search.
- Docked and full-screen search views.
- Recent searches / autocomplete / no-results specimens.
- Search suggestions/results as list-like content.
```

Scope recommendation:

```txt
v3.5.8 Phase 1:
  audit all concepts

v3.5.8 Phase 2:
  implement contained rest/focused/expanded search bar and static suggestions
  with current runtime evidence

Future candidate:
  full-screen search view, remote results, and popover-backed positioning
```

Do not let M3 focused/full-screen search force an oversized Phase 2. Phase 1
can route the larger search-view layouts if needed.

---

## §8 — WordPress Boundary Lock

WP-MAPPING must use `core/search` as the primary surface, but must not pretend
WordPress core provides autocomplete semantics.

Required boundary:

```txt
core/search:
  primary markup/style mapping

Theme:
  Search bar shell and static suggestion/result shape

Plugin:
  search query, results source, autocomplete, federated search, analytics,
  query history, personalization, live announcements
```

ActivityPub / social CMS note:

```txt
Federated search is policy-heavy and must stay plugin territory. The theme
may render a search surface, but it must not decide federation query routing,
privacy policy, instance search behavior, or remote API semantics.
```

---

## §9 — Playwright And QA Plan

Phase 1 must prepare Phase 2/3 Playwright checks.

Minimum QA targets:

```txt
1. Baseline rest shell dimensions.
2. Leading icon alignment.
3. Trailing icon-button/avatar slot geometry.
4. Focus-within outline and state-layer behavior.
5. Expanded suggestions panel geometry.
6. Arrow navigation focus movement.
7. Escape clear-then-collapse policy.
8. Forbidden ancestor non-attachment.
9. Reduced motion transform suppression.
10. core/search-inspired markup specimen, if Phase 2 includes it.
```

Phase 1 does not run these as a gate, but each audit doc must say what Phase
2/3 will verify.

---

## §10 — Risks

| Risk | Severity | Disposition |
|---|---:|---|
| 3-doc/4-doc drift | High | 4-doc locked by Phase 0; restated in all docs |
| search-expansion ownership ambiguity | High | Record current evidence; defer physical migration |
| popover/ premature coupling | Medium | CANDIDATE only in Phase 1 |
| Search bar becomes Text field variant | High | Re-cite Phase 0B #7 in SPEC |
| Suggestion data leaks into theme | High | WP-MAPPING plugin boundary |
| Runtime API over-invention | Medium | No `window.axSearch` in Phase 1 |
| Class-name invention | Medium | Baseline §10 grep required |
| Full-screen search scope creep | Medium | Audit yes; Phase 2 scope decision later |
| Accessibility citation gaps | Medium | MEASUREMENT + RUNTIME split SC coverage |
| Legacy search-expansion audit staleness | Medium | New RUNTIME-AUDIT becomes canonical v3.5.8 surface |

---

## §11 — G1-G16 Readiness Mapping

Phase 1 must prepare this final gate model:

```txt
G1  Validator 1.000 PASS
G2  Baseline untouched
G3  Publish clean
G4  Module artifacts present after Phase 2
G5  CHANGELOG at Phase 5
G6  Static / behavioral visual QA
G7  Principle 1: visible control = real runtime
G8  Principle 2: native semantics
G9  WCAG SC citation accuracy
G10 Component audit pattern
     Modified: 3 Component docs + 1 Runtime doc

G11 Hard interaction rules locked
G12 Hard rules verified in code
G13 Phase 0 inventory accuracy
G14 Forbidden ancestor guard
G15 Reduced motion
G16 Runtime audit document
```

`SEARCH-BAR-RUNTIME-AUDIT.md` owns G11-G16 detail. SPEC should reference it
without copying it wholesale.

---

## §12 — Phase 1 Execution Checklist

When this plan is approved, Phase 1 execution should:

```txt
1. Create lab/modules/search-bar/docs/.
2. Draft SEARCH-BAR-SPEC-AUDIT.md.
3. Draft SEARCH-BAR-MEASUREMENT-AUDIT.md.
4. Draft SEARCH-BAR-WP-MAPPING.md.
5. Draft SEARCH-BAR-RUNTIME-AUDIT.md.
6. Include cross-references among all four docs.
7. Include cross-reference to SEARCH-EXPANSION-AUDIT.md.
8. Include cross-reference to Text field v3.5.7 contrast.
9. Include M3 Search links.
10. Include Playwright Phase 2/3 QA plan.
11. Run validator.
12. Confirm no baseline/state/release/runtime implementation files changed.
```

Expected line-count scale:

```txt
SPEC:        650-850 lines
MEASUREMENT: 400-550 lines
WP-MAPPING:  450-600 lines
RUNTIME:     500-700 lines
```

Search bar Phase 1 will likely be heavy. The fourth doc is justified by the
runtime evidence, not by doc-count symmetry.

---

## §13 — Non-Goals

Phase 1 does not:

```txt
- implement Search bar CSS/JS/pattern HTML
- move search-expansion/
- rename search-expansion/
- deprecate search-expansion/
- rewrite search-expansion runtime
- create a public Search API
- implement autocomplete data
- implement remote/federated search
- implement search result rendering
- couple to popover/
- wire ripple to the field host
- update matrix status
- close BACKLOG items
- update changelog/roadmap
- update current-state/next-session
- modify baseline/public/pilot files
```

---

## §14 — Validation Plan

After creating this plan:

```txt
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

Phase 1 execution validation after approval:

```txt
- validator PASS
- search-bar/docs/ exists with exactly 4 audit docs
- no lab-search-bar.css/js/pattern.html
- search-expansion/ unchanged
- components.css/style-guide.html/tokens.css/blocks.css/theme.json unchanged
- CURRENT-STATE.md and NEXT-SESSION.md unchanged
```

---

## §15 — Self-Verification Summary

```txt
self-check:
  4-doc / four audit docs: 11 mentions
  search-expansion: 31 mentions
  RUNTIME-AUDIT: 13 mentions
  popover CANDIDATE / future alignment: 7 mentions
  field host ripple NONE: 3 mentions
  class-name precision / baseline §10: 6 mentions
  Playwright / QA: 7 mentions
  CURRENT-STATE / NEXT-SESSION untouched: 3 mentions
  lab/modules/search-bar/ implementation files forbidden: explicit
```

Approval gate:

```txt
If approved, proceed to Phase 1 execution:
  create search-bar/docs/ and author the four audit docs.
```

