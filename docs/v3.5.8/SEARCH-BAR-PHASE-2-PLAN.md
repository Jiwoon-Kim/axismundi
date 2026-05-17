# Search Bar #17 — Phase 2 Plan (v3.5.8)

> **Status**: Plan-only. Do not execute Phase 2 until this plan is reviewed and approved.  
> **Target component**: Search bar #17  
> **Phase 0 source**: `docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md`  
> **Phase 1 source**: `docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md` + four Search bar audit docs  
> **Category**: Component Full-Spec + Interaction  
> **Core Phase 2 question**: implement Search bar runtime artifacts while preserving `search-expansion/` audit trail and avoiding baseline mutation.

---

## §0 — Executive Summary

Phase 2 should create the Search bar lab implementation surface:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/
  lab-search-bar.css
  lab-search-bar.js
  lab-search-bar-pattern.html
```

This is an Interaction-bearing component cycle, so unlike Button/Card/FAB
Phase 2, JavaScript is required. The runtime behavior is not new from
scratch; it is a v3.5.8 consolidation of the already-audited
`search-expansion/` runtime into the Search bar component lane.

Recommended migration mode:

```txt
Transitional absorption.

Create new search-bar implementation files.
Do not delete or move search-expansion/ in Phase 2.
Leave search-expansion/ as historical v3.3.4 evidence.
Future Phase 5 records search-bar/ as canonical v3.5.8 surface.
```

This keeps the matrix target direction ("search-bar/ absorbs
search-expansion/") without creating a destructive rename during an
implementation phase.

---

## §1 — File Scope

### §1.1 — Deliverable artifacts

Exactly three implementation files:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/
  lab-search-bar.css
  lab-search-bar.js
  lab-search-bar-pattern.html
```

Expected roles:

```txt
lab-search-bar.css:
  Search bar lab scaffolding, expanded suggestions styling, contained/divided
  demos, reduced-motion handling, lab-only helper layout.

lab-search-bar.js:
  v3.5.8 runtime derived from search-expansion/ evidence:
    ARIA combobox/listbox wiring
    Escape clear-then-collapse
    Arrow/Home/End navigation
    idempotent per-instance setup
    forbidden ancestor guard

lab-search-bar-pattern.html:
  static + live specimens:
    rest/focused/filled/expanded
    suggestions/recent/no-results
    trailing icon-button composition
    core/search-inspired form
    forbidden ancestor negative cases
```

### §1.2 — Phase bookkeeping

Allowed after implementation:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/docs/
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-RUNTIME-AUDIT.md
```

Minimal updates only:

```txt
SPEC:
  Pattern/runtime artifacts present -> PASS at Phase 2 level.

MEASUREMENT:
  Playwright Phase 2 evidence summary, if run.

RUNTIME:
  G12 implementation evidence and migration mode note.
```

Do not update `CURRENT-STATE.md` or `NEXT-SESSION.md`.

### §1.3 — Explicitly forbidden in Phase 2

Do not edit:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/ontology-theme-pilot/theme.json
CHANGELOG.md
ROADMAP.md
BACKLOG.md
CURRENT-STATE.md
NEXT-SESSION.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

Do not move, delete, or rewrite:

```txt
products/reference-implementations/axismundi-lab/modules/search-expansion/
```

Do not create:

```txt
public baseline Search bar changes
WordPress block registrations
popover/ integration
remote search/autocomplete code
analytics/federated search code
```

---

## §2 — search-expansion/ Migration Decision

Phase 1 recorded three options. Phase 2 should choose:

```txt
Option C — Transitional absorption.
```

Meaning:

```txt
1. Create search-bar/ implementation files as the new v3.5.8 canonical
   implementation lane.
2. Copy/reimplement the validated behavior shape from search-expansion/ into
   lab-search-bar.js and lab-search-bar.css.
3. Keep search-expansion/ untouched as historical v3.3.4 extraction evidence.
4. Do not delete, rename, or stub search-expansion/ during Phase 2.
5. Phase 5 can record that search-bar/ is the canonical Search bar module
   while search-expansion/ remains legacy evidence until a later cleanup.
```

Why not physical move in Phase 2:

```txt
- Moving files would create noisy history and broken cross-references.
- SEARCH-EXPANSION-AUDIT.md is valuable provenance.
- Phase 2 already has runtime + visual QA load.
- Non-destructive transition mirrors the discipline used for prior historical
  runtime audit disposition cases.
```

Phase 5 may decide whether to open a cleanup BACKLOG entry for eventual
`search-expansion/` archival/removal.

---

## §3 — lab-search-bar.css Plan

CSS should be lab-scoped and implementation-focused.

Allowed selectors:

```txt
.lab-search-bar-demo
.lab-search-bar-demo ...
.search-bar.is-search-active
.ax-search-suggestions
.ax-search-suggestions__item
```

Selector policy:

```txt
Baseline .search-bar layout is defined in components.css §10.
Phase 2 may add runtime modifier behavior for .search-bar.is-search-active
inside lab-search-bar.css.
Phase 2 must not redefine the entire .search-bar baseline.
```

Suggested CSS sections:

```txt
§0 Header and scope notes
§1 Lab page scaffolding
§2 Search bar runtime transition hooks
§3 Suggestions panel
§4 Suggestion item state layer
§5 Contained vs divided demo modifiers
§6 Disabled / readonly / inactive specimens
§7 Forbidden ancestor visual notes
§8 Reduced motion
```

Token rules:

```txt
- Reuse existing M3 tokens.
- Keep search-specific props module-local.
- Avoid :root pollution if possible; prefer .lab-search-bar-demo scoped custom
  props unless a runtime selector truly requires wider scope.
- Do not edit tokens.css.
```

Runtime classes:

```txt
.search-bar.is-search-active
  allowed as runtime state modifier.

.ax-search-suggestions
.ax-search-suggestions__item
  allowed as runtime-created surface selectors.
```

Do not introduce baseline-looking selectors such as `.search-bar__container`
unless they are clearly lab-only and justified. Prefer preserving the current
baseline slot names.

---

## §4 — lab-search-bar.js Plan

JS should be a Search bar runtime module derived from `search-expansion/`, not
a blind copy.

Required behavior:

```txt
1. onReady bootstrap.
2. Query `.search-bar` hosts.
3. Bail out inside forbidden ancestors:
   .prose, .wp-block-post-content, .entry-content, [contenteditable]
4. Find `.search-bar__input`; bail if missing.
5. Idempotent setup: do not duplicate panels/listeners.
6. Create suggestions panel with stable per-instance id.
7. Wire input ARIA:
   role=combobox
   aria-controls
   aria-expanded
   aria-autocomplete=list
8. Build static lab suggestions.
9. Toggle `.is-search-active`.
10. Implement Escape clear-then-collapse.
11. Implement ArrowDown / ArrowUp / Home / End navigation.
12. Click suggestion -> fill input, focus input, collapse.
13. Focusout delay collapse.
14. Console debug optional and low-noise.
```

Design locks:

```txt
No public window.axSearch API in v3.5.8 Phase 2.
No remote fetch.
No analytics.
No localStorage search history.
No plugin data integration.
No popover/ dependency.
```

Potential improvement over v3.3.4:

```txt
Use a per-host data marker such as data-ax-search-ready="true" for
idempotence instead of only checking for an existing panel. This makes
readback and Playwright assertions easier.
```

ARIA caution:

```txt
Current search-expansion/ uses button role=option with roving focus. Phase 2
may preserve it as existing evidence, but should leave a comment for future
aria-activedescendant review rather than pretending the question is closed
forever.
```

---

## §5 — lab-search-bar-pattern.html Plan

Pattern HTML should be the formal QA surface.

Required sections:

```txt
§0 Header / status / scope
§1 Rest shell baseline specimen
§2 Focused + filled specimen
§3 Expanded suggestions specimen
§4 Contained style specimen
§5 Divided style specimen
§6 Trailing actions specimen
   - clear
   - mic
   - filter
   - avatar
§7 core/search-inspired form specimen
§8 No-results / empty suggestions specimen
§9 Disabled / inactive specimens
§10 Forbidden ancestor negative cases
§11 Reduced motion note
§12 Code snippets
§13 Cross-references
```

Static caption discipline:

```txt
Any specimen with static suggestions must say the suggestion data is demo-only.
Any clear/filter/mic action without real product behavior must say behavior is
visual/runtime demo only.
```

Native semantics:

```txt
Use real input[type="search"].
Use real button[type="button"] for trailing actions.
Use form role/search semantics in core/search-inspired specimen.
Do not use div role=button or clickable spans.
```

Trailing icon-button composition:

```txt
Use .ax-icon-button for clear/mic/filter.
If ripple is demonstrated, it must be via icon-button consumer path:
  data-ax-ripple on the icon button
not on .search-bar field host.
```

---

## §6 — Ripple / Icon Button / Popover Locks

### Ripple

```txt
Search field host:
  ripple = NONE

Trailing icon buttons:
  ripple = TARGET via icon-button consumer

Suggestion items:
  ripple = CANDIDATE future alignment
```

Phase 2 must not add `data-ax-ripple` to `.search-bar`.

Allowed:

```html
<button class="ax-icon-button ..." data-ax-ripple>
  ...
</button>
```

Forbidden:

```html
<label class="search-bar" data-ax-ripple>
  ...
</label>
```

### Icon button

Trailing actions should compose existing icon button primitives. Do not create
new Search-specific fake icon buttons.

### Popover

`popover/` remains CANDIDATE. Phase 2 does not import or depend on it.

Reason:

```txt
Search owns combobox/listbox and Escape clear policy.
popover/ owns generic anchored-surface behavior.
The coupling question is future alignment, not v3.5.8 Phase 2 scope.
```

---

## §7 — WordPress / core-search Scope

Phase 2 should include a lab pattern specimen inspired by `core/search`, but
must not edit WordPress integration files.

Allowed:

```txt
- form role=search specimen
- input name=s or q, documented as demo mapping
- static template-like Search bar layout
- comments/captions identifying plugin territory
```

Forbidden:

```txt
- register_block_style()
- functions.php edits
- block.json edits
- theme.json edits
- real WP_Query code
- autocomplete endpoint code
```

Plugin territory remains:

```txt
backend query
autocomplete source
remote/federated search
analytics
history/persistence
live result rendering
```

---

## §8 — Playwright QA Plan

Phase 2 implementation should run a local Playwright pre-check before asking
for Phase 3 visual QA.

Recommended checks:

```txt
1. Open lab-search-bar-pattern.html via file://.
2. Measure rest shell height = 56px.
3. Verify leading icon vertical center.
4. Verify trailing icon-button geometry stays inside slot.
5. Focus input; assert .is-search-active and aria-expanded=true.
6. Assert .ax-search-suggestions visible.
7. Type text; Escape once clears and remains active.
8. Escape again collapses and aria-expanded=false.
9. ArrowDown moves focus to first option.
10. Home/End operate within option list.
11. Forbidden ancestor specimen gets no suggestions panel.
12. Emulate reduced motion and verify transform animation suppressed.
13. Screenshot rest and expanded states.
```

Artifacts:

```txt
docs/**/*-qa.png and docs/**/qa-*.png are gitignored.
Do not commit screenshots unless explicitly requested.
```

Phase 3 user visual QA remains final.

---

## §9 — Phase Bookkeeping

After Phase 2 implementation, update only:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/docs/
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-RUNTIME-AUDIT.md
```

Suggested updates:

```txt
SPEC:
  Pattern/runtime artifacts created; pattern completeness PASS at Phase 2.

MEASUREMENT:
  Playwright pre-check evidence summary.

RUNTIME:
  G12 implementation evidence; migration mode = transitional absorption.
```

Do not update:

```txt
CURRENT-STATE.md
NEXT-SESSION.md
CHANGELOG.md
ROADMAP.md
BACKLOG.md
MODULE-STATUS-MATRIX.md
```

Those are Phase 5 / session-boundary surfaces.

---

## §10 — Risks

| Risk | Severity | Mitigation |
|---|---:|---|
| Physical migration breaks history | High | Use transitional absorption; leave search-expansion/ untouched |
| Runtime JS grows into product search | High | Static suggestions only; no data source |
| Search field host gets ripple | Medium | Explicit forbidden selector rule |
| popover/ coupling sneaks in | Medium | CANDIDATE only; no dependency |
| Baseline selector drift | Medium | Use current `.search-bar__*`; lab-only selectors documented |
| ARIA pattern uncertainty | Medium | Preserve current evidence; note future review |
| Reduced-motion regression | Medium | Playwright + CSS media check |
| core/search overreach | Medium | Pattern specimen only; no WP registration |
| Full-screen search scope creep | Medium | Defer if it expands beyond lab pattern |

---

## §11 — Validation Plan

After Phase 2 execution:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

File checks:

```txt
search-bar/
  lab-search-bar.css exists
  lab-search-bar.js exists
  lab-search-bar-pattern.html exists
  docs/ four audit docs still exist

search-expansion/
  unchanged mtimes/content

baseline:
  components.css unchanged
  style-guide.html unchanged
  tokens.css unchanged
  blocks.css unchanged
  theme.json unchanged

state/session:
  CURRENT-STATE.md unchanged
  NEXT-SESSION.md unchanged
```

Playwright checks:

```txt
Run local file visual/behavior checks before Phase 3 user QA.
```

---

## §12 — Self-Verification Summary

```txt
self-check:
  three deliverables: 8 mentions
  search-expansion transitional/untouched: 15 mentions
  ripple field host NONE: 5 mentions
  trailing icon-button consumer route: 5 mentions
  popover CANDIDATE/no dependency: 7 mentions
  core/search/WP mapping: 6 mentions
  Playwright QA: 9 mentions
  CURRENT-STATE/NEXT-SESSION untouched: 4 mentions
  baseline forbidden: explicit
```

Approval gate:

```txt
If approved, execute Phase 2:
  create lab-search-bar.css
  create lab-search-bar.js
  create lab-search-bar-pattern.html
  apply minimal audit bookkeeping
  run validator and Playwright pre-check
```

