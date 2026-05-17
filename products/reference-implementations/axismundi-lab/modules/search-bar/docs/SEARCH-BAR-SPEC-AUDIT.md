# Search Bar — Spec Audit (v3.5.8 Phase 5 Close)

> **Status**: v3.5.8 release closed; Phase 3 visual QA PASS.  
> **Component**: Search bar #17  
> **Category**: Component Full-Spec + Interaction  
> **Primary Phase 0 source**: `docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md`  
> **Execution plan**: `docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md`  
> **Companions**: `SEARCH-BAR-MEASUREMENT-AUDIT.md`, `SEARCH-BAR-WP-MAPPING.md`, `SEARCH-BAR-RUNTIME-AUDIT.md`

---

## §0 — Audit Status

This audit owns the Component Full-Spec side of Search bar v3.5.8. Search bar
is dual-category, but Phase 0 explicitly chose a 4-doc audit shape because
`search-expansion/` is an extracted JS runtime.

```txt
Component Full-Spec coverage:
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md

Interaction Runtime coverage:
  SEARCH-BAR-RUNTIME-AUDIT.md
```

Text field v3.5.7 is the contrast case: Text field uses a 3-doc trio because
its interaction is native/CSS state behavior. Search bar uses 4 docs because
its existing expansion behavior is DOM-mutating JS with ARIA and keyboard
state.

Phase 1 remains documentation-only:

```txt
No lab-search-bar.css.
No lab-search-bar.js.
No lab-search-bar-pattern.html.
No movement of search-expansion/.
No baseline or public-surface edits.
```

---

## §1 — Inputs Read

Cycle docs:

```txt
docs/v3.5.8/SEARCH-BAR-PHASE-0-PLAN.md
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/33-COMPONENT-INVENTORY.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Baseline/public sources:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §10
products/reference-implementations/axismundi-lab/style-guide.html #components-search-bar
```

Runtime evidence:

```txt
products/reference-implementations/axismundi-lab/modules/search-expansion/
  lab-search-expansion.css
  lab-search-expansion.js
  lab-search-expansion-pattern.html
  docs/SEARCH-EXPANSION-AUDIT.md
```

External references:

```txt
M3 Search overview:
  https://m3.material.io/components/search/overview

M3 Search specs:
  https://m3.material.io/components/search/specs

M3 Search guidelines:
  https://m3.material.io/components/search/guidelines

Material Web Text field:
  https://material-web.dev/components/text-field/
```

M3 Search pages are JavaScript-rendered; use Playwright/browser-rendered text
for future exact citation checks.

---

## §2 — Phase 0 Verdict Carry-Forward

Phase 0 locked seven decisions:

| Lock | Decision |
|---|---|
| Search bar vs Text field | Distinct components; Search bar is not a Text field variant |
| Audit shape | 4 docs, including `SEARCH-BAR-RUNTIME-AUDIT.md` |
| `search-expansion/` disposition | Record current evidence in Phase 1; physical migration deferred |
| Suggestions popup | Search bar-owned in v3.5.8; `popover/` CANDIDATE future alignment |
| Dependency profile | Field host ripple NONE; trailing icon-button via consumer |
| Variants/states | Rest, focus, filled, expanded, suggestions/no-results to audit |
| WordPress mapping | `core/search` primary; search data/result behavior plugin territory |

This SPEC adopts all seven locks without reopening them.

---

## §3 — Baseline Inventory Summary

`components.css §10` defines the rest-state Search bar shell.

Baseline selectors:

```txt
.search-bar
.search-bar::before
.search-bar__leading-icon
.search-bar__leading-icon > svg
.search-bar__leading-icon > .ax-icon
.search-bar__leading-icon > .material-symbols-rounded
.search-bar__trailing
.search-bar__trailing > svg
.search-bar__trailing > .ax-icon
.search-bar__input
.search-bar__input::placeholder
.search-bar:focus-within
.search-bar[aria-disabled="true"]
```

Baseline geometry:

```txt
height: 56px
padding-inline: var(--space-md)
border-radius: var(--md-sys-shape-corner-full)
background: var(--md-sys-color-surface-container-high)
box-shadow: var(--md-sys-elevation-shadow-level3)
typography: body-large
state layer: Pattern A via .search-bar::before
focus ring: outer +2px outline on :focus-within
```

Baseline explicitly says:

```txt
Rest state only.
Active/expanded "search view" is a dialog surface and lives in Chunk D.
```

That comment is important: the baseline shell is not pretending to close the
full Search component. v3.5.8 adds the Full-Spec + runtime audit lane around
that existing shell.

---

## §4 — M3 Search Alignment

M3 Search treats Search as its own component. Search contains:

```txt
- search bar container
- leading search icon or navigation affordance
- input text
- optional trailing icons or avatar
- suggestions/results container
- focused/expanded search states
- contained or divided styles
- docked or full-screen focused layouts
```

Axismundi status:

```txt
Baseline §10:
  covers rest container, leading icon, input, trailing actions/avatar.

search-expansion/:
  covers focused/expanded suggestions interaction for lab runtime evidence.

v3.5.8 Search bar:
  formalizes both surfaces into a 4-doc audit set.
```

Phase 2 should be conservative:

```txt
Implement contained Search bar + expanded suggestions first.
Audit divided/full-screen search view, but route oversized layout behavior if
it would force a larger release.
```

---

## §5 — Distinct From Text Field

Search bar must remain distinct from Text field.

```txt
Text field:
  generic input shell
  filled / outlined variants
  labels, supporting text, counter, validation, prefix/suffix
  native/CSS state behavior

Search bar:
  search-specific input shell
  leading search affordance
  trailing clear/filter/mic/avatar actions
  focused/expanded search state
  suggestions/recent searches/results surface
  extracted runtime in search-expansion/
```

Phase 1 cross-reference:

```txt
Text field v3.5.7:
  3-doc dual-category case, no runtime audit.

Search bar v3.5.8:
  4-doc dual-category case, runtime audit required.
```

This distinction becomes a future rule of thumb:

```txt
native/CSS interaction -> SPEC owns G11-G16 coverage
extracted JS runtime   -> RUNTIME-AUDIT owns G11-G16 coverage
```

---

## §6 — Variant And State Matrix

Phase 1 expected coverage:

| Group | Variants / states |
|---|---|
| Rest shell | rest, hover, focus-within, disabled |
| Query state | empty, filled query, cleared query |
| Expansion | focused/active, suggestions visible, suggestions hidden |
| Suggestions | recent searches, autocomplete suggestions, no-results placeholder |
| Styles | contained, divided |
| Layout | docked search view, full-screen search view as audit scope |
| Slots | leading search icon, trailing clear, trailing mic, trailing filter, avatar |
| Runtime | Escape, ArrowDown/Up, Home/End, focusout collapse |

Phase 2 scope should be decided by the Phase 2 plan, but Phase 1 must make
the full M3 matrix visible so deferrals are explicit rather than accidental.

---

## §7 — Native Semantics And Principles

Principle 1: visible control = real runtime.

```txt
- Search text entry is a real native input[type="search"].
- Trailing actions are real button / icon-button controls.
- Suggestion items in current runtime are real button elements with role=option.
- No div role=button substitutes.
```

Principle 2: native semantics first.

```txt
- Use <input type="search"> for search entry.
- Use <button type="button"> for trailing clear/mic/filter actions.
- Use form semantics where Search bar is a submit surface.
- Use ARIA combobox/listbox only to express the popup relationship that native
  input alone does not provide.
```

Forbidden anti-patterns:

```txt
- <div role="searchbox"> instead of native input.
- Clickable trailing icon glyph without button semantics.
- Search suggestions rendered as plain divs with click handlers.
- Search bar field host treated as a button/ripple target.
```

---

## §8 — Slot And Composition Contract

Search bar slots:

```txt
.search-bar                      host
.search-bar__leading-icon        canonical search icon slot
.search-bar__input               native search input
.search-bar__trailing            composed trailing controls/avatar
```

Trailing slot composition:

```txt
Voice search:
  ax-icon-button consumer

Clear query:
  ax-icon-button consumer; runtime behavior belongs to Search bar

Filter:
  ax-icon-button or future filter-chip/menu composition

Avatar:
  ax-avatar record composition
```

The leading search icon is canonical for Search bar. It uses `icon-system/`
but does not make Search bar an icon-system module.

Do not add `.search-bar__container`, `.search-bar__suggestions`, or
`.search-bar__clear` as baseline selectors in Phase 1. If Phase 2 introduces
lab selectors, they must be documented as lab-only or future target names.

---

## §9 — Dependency Profile

| Surface | Provider | State | Notes |
|---|---|---|---|
| Search field host | `components.css §10` | CURRENT | Rest shell |
| Search field host | `search-expansion/` | CURRENT-PARTIAL | Existing runtime evidence |
| Search field host | `ripple/` | NONE | Search field is not an action button |
| Search field host | `icon-system/` | CURRENT conditional | Leading search icon |
| Trailing actions | `icon-button/` | CURRENT conditional | Clear/mic/filter actions |
| Trailing actions | `ripple/` | TARGET via icon-button | Not via Search bar host |
| Suggestions | `search-expansion/` | CURRENT-PARTIAL | Local combobox/listbox runtime |
| Suggestions | `popover/` | CANDIDATE | Future anchored-surface alignment |
| Suggestions | `List #33` | CANDIDATE | Visual/semantic reference |

Required dependency sentence:

```txt
Search bar field host is NOT ripple-dependent. Ripple applies only through
composed trailing icon-button consumers or future suggestion-item decisions.
```

---

## §10 — Search View And Suggestions Boundary

Search view has three layers:

```txt
Layer 1 — Search shell:
  .search-bar rest/focus visual shell

Layer 2 — Suggestions surface:
  current search-expansion/ popup + listbox/option runtime

Layer 3 — Search data/result behavior:
  plugin territory
```

v3.5.8 Phase 1 owns Layer 1 and Layer 2 audit coverage. It must not absorb
Layer 3.

`popover/` boundary:

```txt
Current v3.5.8:
  self-contained search runtime

Future candidate:
  Search bar owns search semantics.
  popover/ owns generic positioning/dismiss/focus-restore if needed.
```

Do not classify Search bar as `popover/` TARGET until an approved plan
actually rewires the runtime.

---

## §11 — Class-Name Precision

Baseline class names are narrow. Phase 2 must grep before authoring pattern
HTML.

Canonical current selectors:

```txt
.search-bar
.search-bar__leading-icon
.search-bar__trailing
.search-bar__input
```

Current runtime-created selectors:

```txt
.ax-search-suggestions
.ax-search-suggestions__item
.search-bar.is-search-active
```

Forbidden as baseline assumptions:

```txt
.search-bar__container
.search-bar__suggestions
.search-bar__clear
.search-bar__results
```

These may be discussed as future/lab selectors only if Phase 2 plan explicitly
authorizes them.

---

## §12 — G1-G10 Component Readiness

| Gate | Phase 1 status |
|---|---|
| G1 validator | Must remain 1.000 PASS |
| G2 baseline untouched | PASS expectation; Phase 1 docs only |
| G3 publish clean | Phase 5 venue |
| G4 module artifacts | Phase 2 venue |
| G5 changelog | Phase 5 venue |
| G6 visual QA | Phase 3 venue |
| G7 Principle 1 | Covered in §7; final after Phase 2 |
| G8 Principle 2 | Covered in §7; final after Phase 2 |
| G9 WCAG SC citations | Owned by MEASUREMENT + RUNTIME |
| G10 audit pattern | Modified PASS at Phase 1: 3 component docs + 1 runtime doc |

Phase 1 verdict:

```txt
G10 audit shape is satisfied at Phase 1.
G1/G2 should pass after documentation-only creation.
G4/G6/G5 remain later phase gates.
```

---

## §13 — G11-G16 Interaction Cross-Link

`SEARCH-BAR-RUNTIME-AUDIT.md` owns G11-G16:

| Gate | Owner |
|---|---|
| G11 hard interaction rules | RUNTIME §5-§7 |
| G12 hard rules verified in code | RUNTIME §3 + Phase 2/3 |
| G13 inventory accuracy | Phase 0 + RUNTIME §3 |
| G14 forbidden ancestor | RUNTIME §9 |
| G15 reduced motion | RUNTIME §10 |
| G16 runtime audit doc | RUNTIME doc itself |

SPEC should not duplicate the runtime details. It should keep the conceptual
boundary clear:

```txt
SPEC owns the component.
RUNTIME owns the extracted behavior.
WP-MAPPING owns integration boundary.
MEASUREMENT owns geometry/token/WCAG evidence.
```

---

## §14 — Risks And Deferred Items

| Risk | Status |
|---|---|
| Search bar treated as Text field variant | Closed by Phase 0; restated here |
| 3-doc shape hides runtime complexity | Closed by 4-doc decision |
| `search-expansion/` ownership ambiguous | Deferred to Phase 2 migration decision |
| `popover/` premature coupling | Deferred; CANDIDATE only |
| Suggestion item ripple/list alignment | Deferred; CANDIDATE |
| Full-screen search view scope creep | Audit now; implementation scope later |
| Live search data leaks into theme | Forbidden; plugin territory |
| Class-name drift | Guarded by §11 |

Potential future BACKLOG candidates:

```txt
- Search bar full-screen search view if not implemented in v3.5.8.
- popover-backed Search suggestions alignment.
- List #33 visual alignment for suggestion/result items.
- Search bar physical migration cleanup after search-expansion absorption.
```

Do not open these BACKLOG entries in Phase 1 unless explicitly requested.

---

## §15 — Verdict

Phase 5 SPEC verdict:

| Criterion | Status |
|---|---|
| M3 Search spec coverage | PASS |
| Token-driven implementation | PASS |
| Pattern HTML completeness | PASS |
| Audit doc completeness | PASS |
| Dependency declarations | PASS at Phase 1 |
| Runtime audit split | PASS at Phase 1 |

Phase 3 / Phase 5 notes:

```txt
Visual QA PASS after three in-cycle Search-specific corrections:

1. Suggestion selection now reads `data-search-value` rather than
   button textContent. This prevents Material Symbols ligature text
   (for example `history`) from leaking into the selected query value.

2. Native browser `input[type="search"]` clear affordances are hidden
   because Search bar owns a composed trailing clear icon-button.
   Covered pseudo-elements:
     ::-webkit-search-cancel-button
     ::-webkit-search-decoration
     ::-webkit-search-results-button
     ::-webkit-search-results-decoration
     ::-ms-clear
     ::-ms-reveal

   Playwright cannot reliably inspect these pseudo-elements directly,
   so Phase 3 verified the CSS rule presence and the custom clear-button
   behavior instead.

3. Mobile overflow was fixed in lab scope only; see
   SEARCH-BAR-MEASUREMENT-AUDIT.md for the measurement note.
```

Summary:

```txt
Search bar v3.5.8 correctly uses a 4-doc audit shape. The component is
distinct from Text field, runtime coverage is split into RUNTIME-AUDIT, and
Phase 2 created the approved lab implementation artifacts:

  lab-search-bar.css
  lab-search-bar.js
  lab-search-bar-pattern.html

search-expansion/ remains untouched as historical v3.3.4 evidence. The
Search field host remains non-ripple; only composed trailing icon buttons
consume Ripple v2 via their own consumer route.

v3.5.8 closes Search bar #17 and moves MODULE-STATUS-MATRIX row #17 from
PARTIAL to DONE.
```

---

## §16 — Cross-References

Companion docs:

```txt
SEARCH-BAR-MEASUREMENT-AUDIT.md
SEARCH-BAR-WP-MAPPING.md
SEARCH-BAR-RUNTIME-AUDIT.md
```

Phase docs:

```txt
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
```

Reference docs:

```txt
../search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
../text-field/docs/TEXT-FIELD-SPEC-AUDIT.md
../icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
../ripple/docs/RIPPLE-V2-AUDIT.md
```

External references:

```txt
https://m3.material.io/components/search/overview
https://m3.material.io/components/search/specs
https://m3.material.io/components/search/guidelines
```

---

## §17 — What This Audit Does NOT Do

This audit does not:

```txt
- implement Search bar CSS/JS/pattern HTML
- move or rename search-expansion/
- deprecate search-expansion/
- create a public window.axSearch API
- couple Search bar to popover/
- classify the Search field host as ripple-dependent
- implement autocomplete or live results
- implement ActivityPub/federated search
- edit baseline/public/release/state files
```
