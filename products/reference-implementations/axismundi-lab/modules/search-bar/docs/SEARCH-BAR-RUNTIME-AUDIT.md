# Search Bar — Runtime Audit (v3.5.8 Phase 5 Close)

> **Status**: v3.5.8 release closed; Phase 3 behavioral QA PASS.  
> **Component**: Search bar #17  
> **Category**: Interaction Runtime side of Component Full-Spec + Interaction  
> **Companions**: `SEARCH-BAR-SPEC-AUDIT.md`, `SEARCH-BAR-MEASUREMENT-AUDIT.md`, `SEARCH-BAR-WP-MAPPING.md`

---

## §0 — Runtime Audit Status

This is the v3.5.8 canonical runtime audit surface for Search bar.

It does not replace or move `search-expansion/`. It records
`search-expansion/` as current runtime evidence and translates that older
v3.3.4 extraction audit into the v3.5.x gate vocabulary.

---

## §1 — Inputs Read

```txt
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
modules/search-expansion/lab-search-expansion.css
modules/search-expansion/lab-search-expansion.js
modules/search-expansion/lab-search-expansion-pattern.html
modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
docs/v3.5.0/PROMOTION-CRITERIA.md
modules/snackbar/docs/SNACKBAR-RUNTIME-AUDIT.md
modules/popover/docs/POPOVER-AUDIT.md
```

---

## §2 — Runtime Category Framing

Search bar is not a pure Interaction Runtime module. It is dual-category:

```txt
Component Full-Spec:
  Search bar shell, variants, slots, measurements, WordPress mapping.

Interaction Runtime:
  expanded suggestions behavior, ARIA combobox/listbox wiring, keyboard
  movement, Escape policy, DOM mutation, forbidden ancestor guard.
```

This is why v3.5.8 uses four docs. The runtime doc owns G11-G16 while the
other three docs own Component Full-Spec coverage.

---

## §3 — Current search-expansion/ Inventory

Current files:

```txt
modules/search-expansion/lab-search-expansion.css
modules/search-expansion/lab-search-expansion.js
modules/search-expansion/lab-search-expansion-pattern.html
modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
```

Runtime JS facts:

```txt
- IIFE module, no public window API.
- Host selector: .search-bar.
- Bails out inside forbidden ancestors.
- Adds .ax-search-suggestions panel per instance.
- Creates stable listbox id per instance.
- Adds combobox/listbox/option ARIA.
- Uses focus, input, keydown, pointerdown, focusout, click listeners.
- Implements Escape clear-then-collapse policy.
- Implements ArrowDown / ArrowUp / Home / End navigation.
```

Runtime CSS facts:

```txt
- Adds .search-bar transition behavior.
- Uses .search-bar:focus-within and .search-bar.is-search-active.
- Adds .ax-search-suggestions popup styling.
- Adds .ax-search-suggestions__item Pattern A state layer.
- Includes prefers-reduced-motion handling.
- Defines module-local --ax-search-suggestions-* custom props.
```

---

## §4 — Contract Surface

Current contract surface:

```txt
Input:
  .search-bar host with .search-bar__input descendant.

Generated DOM:
  .ax-search-suggestions panel.
  .ax-search-suggestions__item buttons.

State:
  .is-search-active on .search-bar.

ARIA:
  role=combobox
  aria-controls
  aria-expanded
  aria-autocomplete=list
  role=listbox
  role=option
  aria-selected

Public JS API:
  none.
```

Phase 1 decision:

```txt
Do not invent window.axSearch in Phase 1.
If Phase 2 needs an API, propose it in Phase 2 plan.
```

---

## §5 — ARIA Combobox/Listbox Contract

Current runtime:

```txt
input.search-bar__input
  role="combobox"
  aria-controls="<generated listbox id>"
  aria-expanded="false|true"
  aria-autocomplete="list"

.ax-search-suggestions
  role="listbox"

.ax-search-suggestions__item
  role="option"
  aria-selected="false"
  tabindex="-1"
```

Phase 2 must verify whether this is sufficient against current ARIA combobox
best practice, especially for:

```txt
- active descendant vs roving focus
- button role=option tradeoff
- Enter behavior on focused option
- form submit behavior when input remains focused
```

Do not silently change the ARIA model without documenting the decision.

---

## §6 — Keyboard Interaction Contract

Current keyboard contract:

```txt
Focus input:
  open suggestions.

Input text:
  keep suggestions open.

ArrowDown from input:
  prevent default, open suggestions, focus first option.

ArrowDown on option:
  move to next option.

ArrowUp on option:
  move to previous option; at first option, return focus to input.

Home / End:
  first / last option.

Click option:
  fill input, focus input, collapse.
```

Phase 2 QA must verify keyboard behavior with Playwright, not only by manual
visual inspection.

---

## §7 — Escape And Collapse Policy

Current Escape policy:

```txt
Escape with non-empty input:
  clear input, dispatch input event, remain open.

Escape with empty input:
  collapse, blur input.
```

This is intentionally different from generic popover single-step Escape.
Search owns query preservation semantics. It should not inherit popover/
Escape behavior blindly.

---

## §8 — DOM Mutation And Idempotence

Current DOM mutation:

```txt
setupSearchBar(bar)
  finds .search-bar__input
  bails out if .ax-search-suggestions already exists
  increments listbox id counter
  appends panel
  wires ARIA
  attaches listeners
```

Idempotence:

```txt
Re-running enableSearchBar() does not duplicate panels because setup bails
out if .ax-search-suggestions exists.
```

Phase 2 migration must preserve idempotence. Dynamic WordPress/HTMX-like
content swaps are expected future use cases.

---

## §9 — Forbidden Ancestor / Prose Guard

Current guard:

```txt
.prose
.wp-block-post-content
.entry-content
[contenteditable]
```

Policy:

```txt
Search bars inside long-form content or editor surfaces remain plain inputs.
No DOM mutation.
No suggestions popup.
No expansion runtime attachment.
```

This is G14-applicable and must remain a hard rule in Phase 2.

---

## §10 — Reduced Motion

Current reduced-motion behavior:

```txt
@media (prefers-reduced-motion: reduce)
  .search-bar transitions collapse
  active transform disabled
  suggestions transform disabled
  visibility/opacity snap
```

Depth/shadow may still change because shadow is state/depth, not spatial
motion. Transform animation is the thing to suppress.

---

## §11 — Suggestions Data Boundary

Current suggestions are static demo data. This is intentional.

Theme/runtime may own:

```txt
- suggestions surface
- keyboard movement
- static lab suggestions
- clear/collapse policy
```

Plugin territory:

```txt
- actual suggestion source
- remote autocomplete
- WP_Query
- federated search
- personalized history
- analytics
- result ranking/rendering
```

The runtime audit must keep this boundary sharp.

---

## §12 — popover/ Candidate Alignment

Current state:

```txt
search-expansion/ self-contains the popup.
popover/ is not used.
```

Decision:

```txt
popover/ = CANDIDATE future alignment, not v3.5.8 TARGET.
```

Reason:

```txt
Search bar owns search semantics and the Escape clear policy.
popover/ owns generic anchored-surface mechanics.
Coupling them before Search bar Full-Spec closure would mix two decisions.
```

Future alignment path:

```txt
Search bar:
  search semantics, combobox/listbox, query policy boundary.

popover/:
  anchor positioning, outside click, viewport collision, focus restore.
```

---

## §13 — Phase 2 Migration Options

Phase 1 records three options:

```txt
Option A — Absorb
  Move/rename search-expansion files into search-bar/.
  SEARCH-BAR-RUNTIME-AUDIT remains canonical.

Option B — Reference
  Keep search-expansion/ as runtime lane.
  search-bar/ owns Full-Spec docs and cross-references runtime lane.

Option C — Transitional
  Create search-bar wrappers / aliases.
  Keep search-expansion/ as deprecated compatibility shell.
```

Recommendation for Phase 2 planning:

```txt
Prefer A or C. The matrix target says search-bar/ absorbs search-expansion/.
Option B is safer short-term but leaves ownership split.
```

No migration occurs in Phase 1.

---

## §14 — G11-G16 Readiness

| Gate | Phase 1 status |
|---|---|
| G11 hard rules locked | PASS — keyboard/Escape/ARIA/prose/reduced-motion rules recorded |
| G12 hard rules verified in code | PASS — Phase 2 implementation + Phase 3 QA |
| G13 inventory accuracy | PASS — current runtime inventory recorded |
| G14 forbidden ancestor | PASS — guard recorded |
| G15 reduced motion | PASS — CSS evidence recorded |
| G16 runtime audit doc | PASS — this document exists |

Phase 5 runtime verdict:

```txt
G11-G16 PASS.
Phase 2 created `lab-search-bar.js` using transitional absorption:
new Search bar runtime files exist, while `search-expansion/` remains
untouched as historical evidence.
```

Phase 3 runtime findings:

```txt
1. Suggestion option activation now uses `data-search-value`.
   This separates the selected query value from decorative Material
   Symbols ligature text in the option button.

2. Escape behavior verified:
     first Escape with text    -> clears the input and remains expanded
     second Escape when empty  -> collapses the suggestions surface

3. Arrow navigation verified:
     ArrowDown moves from the input to the first suggestion option.

4. Forbidden ancestor and reduced-motion paths remain covered by the
   v3.5.8 runtime implementation.

5. Native browser search clear pseudo-elements are suppressed in CSS
   so the composed trailing clear icon-button is the only visible clear
   affordance.
```

---

## §15 — Risks

| Risk | Disposition |
|---|---|
| Roving focus vs aria-activedescendant mismatch | Phase 2 QA/design check |
| Button role=option tradeoff | Phase 2 QA/design check |
| Escape policy differs from popover | Intentional Search-specific rule |
| Static demo data mistaken for product data | Plugin boundary recorded |
| search-expansion file ownership split | Phase 2 migration decision |
| `:root` module-local custom props | Phase 2 token-scope review |
| Full-screen search scope creep | SPEC/WP-MAPPING route |
| popover coupling too early | CANDIDATE only |

---

## §16 — Verdict

Runtime audit Phase 1 verdict:

```txt
PASS at audit level.

Search bar needs a runtime audit because current search-expansion/ is an
extracted JS runtime with ARIA, keyboard, DOM mutation, Escape policy,
idempotence, forbidden-ancestor guard, and reduced-motion handling.
```

Implementation verdict is PASS. Phase 3 verified the runtime visually and
behaviorally with Playwright plus user QA.

---

## §17 — Cross-References

```txt
SEARCH-BAR-SPEC-AUDIT.md
SEARCH-BAR-MEASUREMENT-AUDIT.md
SEARCH-BAR-WP-MAPPING.md
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
../search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
../snackbar/docs/SNACKBAR-RUNTIME-AUDIT.md
../popover/docs/POPOVER-AUDIT.md
```

---

## §18 — What This Audit Does NOT Do

This runtime audit does not:

```txt
- move or rewrite search-expansion/
- implement lab-search-bar.js
- create window.axSearch
- implement live autocomplete
- implement backend search
- implement federated search
- couple to popover/
- edit baseline or public style guide
```
