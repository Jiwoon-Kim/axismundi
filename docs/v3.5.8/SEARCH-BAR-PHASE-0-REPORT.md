# Search Bar #17 — Phase 0 Report (v3.5.8)

> **Status**: Phase 0 complete — documentation-only investigation.  
> **Target component**: Search bar #17  
> **Matrix row**: `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #17  
> **Current matrix status**: PARTIAL (`search-expansion/` exists; Full-Spec layer owed)  
> **Category**: Component Full-Spec + Interaction  
> **Phase 0 verdict**: Proceed to Phase 1 with a **4-doc audit shape**.

---

## Section 0 — Phase 0 Framing

Search bar is the first v3.5.x component after Text field to test the
dual-category rule:

```txt
Text field #16
  v3.5.7 DONE
  interaction = native form control + CSS state behavior
  Phase 1 audit shape = 3-doc trio
  no runtime audit

Search bar #17
  v3.5.8 candidate
  interaction = existing extracted JS runtime in search-expansion/
  Phase 1 audit shape = 4-doc set
  runtime audit required
```

This Phase 0 report does not implement Search bar, does not create
`lab/modules/search-bar/`, does not move `search-expansion/`, and does not
edit baseline files. It records the ontology decisions that Phase 1 must
use.

Search bar must not drift into Text field. The v3.5.0 Phase 0B decision is
explicit: Search bar is distinct from Text field because it owns
search-specific affordances: leading search icon, trailing search actions,
expanded search state, and suggestions/results surfaces.

---

## Section 1 — Authoritative Inputs Read

### Framework inputs

```txt
docs/v3.5.0/33-COMPONENT-INVENTORY.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Key framework facts:

```txt
Phase 0B decision #7:
  Search bar and Text field stay distinct.

Matrix row #17:
  Search bar = PARTIAL.
  Current module = search-expansion/ (v3.3.4).
  Target module = search-bar/ absorbs search-expansion/.
  Category = Component Full-Spec + Interaction.

Promotion criteria Section 4.3:
  Component Full-Spec + Interaction can require both a Component Full-Spec
  audit surface and an Interaction Runtime audit surface.

Text field precedent:
  Dual category does not automatically imply a fourth doc.
  Native/CSS interaction can stay inside SPEC.
  Extracted JS runtime is the deciding difference.
```

### Local Search bar inputs

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §10
products/reference-implementations/axismundi-lab/style-guide.html #components-search-bar
products/reference-implementations/axismundi-lab/modules/search-expansion/
```

`components.css §10` is the baseline Search bar shell. It explicitly calls
the baseline "rest state only" and routes active/expanded search view to a
later interaction surface.

`style-guide.html #components-search-bar` exposes one public rest-state
specimen: a labelled `.search-bar` with a leading search icon, a native
`input[type="search"]`, a trailing icon button, and an avatar.

`search-expansion/` contains the extracted interaction:

```txt
lab-search-expansion.css
lab-search-expansion.js
lab-search-expansion-pattern.html
docs/SEARCH-EXPANSION-AUDIT.md
```

### External Material inputs

The M3 Search pages are JavaScript-rendered. Standard text fetches return
only the JavaScript requirement shell, so the usable read path is browser
rendering / Playwright text extraction.

Read targets:

```txt
https://m3.material.io/components/search/overview
https://m3.material.io/components/search/specs
https://m3.material.io/components/search/guidelines
https://material-web.dev/components/text-field/
```

Material facts used here:

```txt
- Search bar is not merely a generic text input.
- Search includes a leading search icon, optional trailing icons/avatar,
  search input text, and a suggestions/results container.
- Search may display suggestions before or while the user types.
- Suggested keywords/results are displayed in a list-like surface.
- Search has contained and divided styles.
- Focused search can become docked or full-screen.
- Search results/suggestions are content surfaces; the data source and
  result rendering are application/plugin concerns.
```

Material Web Text field remains a useful contrast point: Text fields behave
like native input/textarea elements. Search bar includes an expanded search
surface and suggestion/result semantics beyond the field shell.

---

## Section 2 — Baseline Inventory

### components.css Section 10

Baseline Search bar selectors:

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

Baseline geometry and tokens:

```txt
height: 56px
padding-inline: var(--space-md)
border-radius: var(--md-sys-shape-corner-full)
background: var(--md-sys-color-surface-container-high)
box-shadow: var(--md-sys-elevation-shadow-level3)
typography: body-large
state layer: Pattern A via .search-bar::before
focus ring: standard outer +2px outline on :focus-within
```

Baseline class precision:

```txt
Search bar host:        .search-bar
Leading icon slot:      .search-bar__leading-icon
Trailing actions slot:  .search-bar__trailing
Input element:          .search-bar__input
```

No `.search-bar__container`, `.search-bar__suggestions`, or
`.search-bar__clear` baseline selectors exist in `components.css §10`.
Phase 1 and Phase 2 must not invent baseline selectors without documenting
them as lab-only or future target selectors.

### Baseline scope conclusion

Baseline currently owns:

```txt
- Rest shell
- Native search input placement
- Leading icon slot
- Trailing action/avatar slot
- Hover state layer
- Focus ring
- Disabled rest-state styling
```

Baseline does not own:

```txt
- Suggestions popup
- Expanded search view
- Combobox/listbox ARIA
- Escape clear/collapse policy
- Arrow-key suggestion navigation
- Data source or result rendering
```

This split matches the current matrix state: Search bar is PARTIAL because
the rest shell exists and interaction extraction exists, but the Full-Spec
Search bar module is still owed.

---

## Section 3 — Public Specimen Inventory

`style-guide.html #components-search-bar` exposes one public specimen:

```html
<label class="search-bar" style="max-width: 600px;">
  <span class="search-bar__leading-icon">...</span>
  <input class="search-bar__input" name="q" type="search" placeholder="..." />
  <span class="search-bar__trailing">
    <button class="ax-icon-button is-standard has-state-layer" ...>...</button>
    <span class="ax-avatar is-size-xs">...</span>
  </span>
</label>
```

Important public-surface observations:

```txt
1. The host is a label, not a div role=searchbox.
2. The real text entry is a native input[type="search"].
3. The trailing voice action is a real icon button.
4. The avatar is composed as a trailing visual affordance.
5. The specimen is rest-state only.
6. No search-expansion JS is loaded by the main style guide.
7. No suggestions/result surface is shown on the public baseline page.
```

This specimen is correct as a baseline rest shell. It is incomplete as a
Full-Spec Search bar, because M3 search includes focused/expanded surfaces
and suggestions/results behavior.

---

## Section 4 — Lock Decision 1: Search Bar Distinct From Text Field

**Decision**: Search bar remains a distinct component from Text field.

This reaffirms v3.5.0 Phase 0B decision #7.

Reasoning:

```txt
Text field:
  - Generic input shell.
  - Filled/outlined variants.
  - Labels, supporting text, counter, validation, prefix/suffix.
  - Native/CSS interaction.

Search bar:
  - Search-specific shell.
  - Leading search affordance.
  - Trailing search actions such as clear, mic, filter, avatar.
  - Focused/expanded search view.
  - Suggestions/recent searches/results surface.
  - Existing extracted JS interaction in search-expansion/.
```

M3 also treats Search as its own component. Search bar can share input-shell
lessons with Text field, but it cannot be reduced to a Text field variant
without losing expanded search semantics.

Phase 1 implication:

```txt
Search bar audit docs may reference Text field heavily, but must not copy
Text field's 3-doc native-only interaction shape by default.
```

---

## Section 5 — Lock Decision 2: Audit Shape

**Decision**: v3.5.8 Search bar uses a **4-doc audit shape**.

Required Phase 1 deliverables:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/docs/
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md
  SEARCH-BAR-RUNTIME-AUDIT.md
```

This differs from Text field v3.5.7:

```txt
Text field:
  native/CSS state behavior
  no extracted runtime
  G11-G16 handled inside SPEC
  3-doc trio

Search bar:
  existing extracted JS runtime
  ARIA combobox/listbox wiring
  per-instance DOM mutation
  keyboard navigation state machine
  Escape policy
  forbidden ancestor guard
  4-doc set
```

The deciding evidence is `lab-search-expansion.js`. It is not a small CSS
state shim. It creates a suggestions panel, assigns a stable listbox id,
wires input ARIA attributes, handles focus/input/Escape/Arrow/Home/End
events, manages active state, and enforces forbidden-ancestor bail-out.

This is enough runtime surface to deserve a runtime audit.

### Why not a 3-doc trio?

A 3-doc trio would blur the Text field rule:

```txt
native/CSS interaction -> inside SPEC
extracted JS runtime   -> runtime audit
```

Search bar is the first follow-up data point after Text field. Preserving the
distinction now makes future dual-category cycles easier:

```txt
Tabs:
  likely 4-doc if indicator animation + arrow-key nav are extracted runtime

Carousel:
  likely 4-doc because carousel/ interaction already exists

Date/Time:
  likely 4-doc because date-time/ runtime already exists

Slider:
  no runtime audit because native input range + CSS handles it
```

### Runtime audit ownership

The runtime audit should live under the future Search bar module:

```txt
lab/modules/search-bar/docs/SEARCH-BAR-RUNTIME-AUDIT.md
```

`search-expansion/docs/SEARCH-EXPANSION-AUDIT.md` remains the historical
runtime extraction record. Phase 1 should cross-reference it, not delete it.

---

## Section 6 — Lock Decision 3: search-expansion/ Disposition

**Decision**: Phase 0 records disposition only. No movement or rewrite in
Phase 0.

Recommended staged disposition:

```txt
Phase 1:
  Create search-bar/docs/ and write the 4 audit docs.
  Treat search-expansion/ as current runtime evidence.
  Do not move files yet.

Phase 2 plan:
  Decide the exact migration mode:
    - absorb search-expansion files into search-bar/
    - keep search-expansion/ as a referenced runtime lane
    - create transitional wrappers / cross-reference stubs

Phase 2 execution:
  Implement the approved migration mode.

Phase 5:
  Close matrix row #17 and record migration in CHANGELOG/ROADMAP.
```

`search-expansion/` is not a general infrastructure module. It does not serve
multiple components. It is Search bar-specific:

```txt
Selector allowlist: .search-bar only
Runtime purpose: Search suggestions/expanded state only
Public API: none
Consumer semantics: search-specific
```

Therefore it should not be promoted to cross-cutting infrastructure. The
target matrix wording "`search-bar/` absorbs `search-expansion/`" remains
the most likely final state.

Open Phase 2 design question:

```txt
Should search-expansion/ physically disappear after absorption, or remain as
a historical compatibility shell with a deprecation note?
```

That decision is not needed in Phase 0.

---

## Section 7 — Lock Decision 4: Suggestions Popup And popover/

**Decision**: Suggestions popup remains Search bar-owned in v3.5.8 Phase 0,
with `popover/` recorded as a future alignment candidate.

Reasoning:

```txt
Search bar owns:
  - search input semantics
  - combobox/listbox relationship
  - suggestion item semantics
  - Escape clear-then-collapse policy
  - query/suggestion visual state

popover/ owns:
  - generic anchor positioning
  - dismiss/outside-click
  - Escape/focus restore for anchored surfaces
  - viewport collision
```

Current `search-expansion/` is self-contained and not coupled to
`popover/`. Moving directly to popover coupling in Phase 1 would add a second
architectural problem before the Search bar Full-Spec layer is established.

Therefore:

```txt
v3.5.8:
  Search bar runtime audit owns suggestions popup behavior.
  popover/ remains CANDIDATE future alignment, not TARGET.

Future v3.5.x:
  If Search bar suggestions need shared anchored-surface behavior, introduce
  a DISTINCT but COUPLED contract:
    Search bar owns search semantics.
    popover/ owns generic positioning/dismiss behavior.
```

This is consistent with Menu/Popover framing, but it avoids forcing a
provider relationship before local Search bar runtime boundaries are clear.

---

## Section 8 — Lock Decision 5: Dependency Profile

Search bar has three surfaces, and each surface has a different dependency
profile.

### Surface A — Search field host

```txt
components.css §10 Search bar shell:
  CURRENT

search-expansion/ runtime:
  CURRENT-PARTIAL evidence

icon-system/:
  CURRENT conditional / effectively required for canonical leading search icon

icon-button/:
  CURRENT conditional for trailing actions

ripple/:
  NONE on the field host

popover/:
  CANDIDATE future alignment only
```

The field host is not a button. It accepts text and hosts an input. Animated
ripple is not part of the Search bar shell. Hover/focus state-layer behavior
is already handled by baseline Pattern A.

### Surface B — Trailing icon buttons

```txt
icon-button/:
  CURRENT composition dependency

icon-system/:
  CURRENT via icon button glyph

ripple/:
  TARGET through icon-button consumer state, not through Search bar host
```

Examples:

```txt
Voice search
Clear query
Filter
Back button in focused/full-screen search
```

These are icon button composition surfaces. Search bar does not reimplement
icon button shape, ripple, or accessible-name policy.

### Surface C — Suggestions / results items

```txt
search-expansion/:
  CURRENT-PARTIAL runtime evidence

list semantics:
  CURRENT local listbox/option semantics in search-expansion/

ripple/:
  CANDIDATE for future suggestion item action feedback

popover/:
  CANDIDATE for future anchored-surface behavior

List component:
  CANDIDATE visual/semantic reference; M3 search suggestions/results are
  list-like but Search bar owns query-specific behavior
```

The suggestion item surface is interactive, but current v3.3.4 implementation
uses state-layer hover/focus, not Ripple v2. Phase 1 should record this
honestly rather than force ripple wiring.

---

## Section 9 — Lock Decision 6: Variants And States

Phase 1 should audit the following Search bar variants and states.

### Baseline shell states

```txt
rest
hover
focus-within
disabled
filled query
```

### Search runtime states

```txt
focused / active
expanded suggestions visible
typed query with suggestions
typed query with no results
Escape with text -> clear, stay open
Escape empty -> collapse
ArrowDown from input -> first suggestion
ArrowUp at first suggestion -> input
Home/End in suggestion list
```

### M3 Search styles

```txt
contained
divided
```

The baseline currently resembles a contained rest bar. Divided style and
search view layout should be audited, but Phase 2 may choose static specimens
or a runtime pattern depending on the approved Phase 1 verdict.

### Layout modes

```txt
docked focused search
full-screen focused search
```

These are Search bar / Search view concerns, not Text field concerns. Phase 1
should decide whether v3.5.8 implements both or routes one to a future
behavior/layout release.

### Slots

```txt
leading search icon
trailing clear button
trailing voice/mic button
trailing filter button
avatar
suggestion list item leading icon
suggestion category label
suggestion/result list content
```

The leading search icon is canonical. Trailing actions are optional and
composed.

---

## Section 10 — Lock Decision 7: WordPress Mapping

**Decision**: `core/search` is the primary WordPress mapping surface.

Theme territory:

```txt
- Search shell visual styling.
- Search input rest/focus/expanded visual states.
- Suggestions/results container shape.
- Static pattern composition.
- `core/search` style support where WordPress markup allows it.
- Template-level placement of search bars.
```

Plugin territory:

```txt
- Actual search backend.
- WP_Query customization.
- Autocomplete data.
- Live search API.
- Remote/federated search.
- ActivityPub search policy.
- Query analytics.
- Search history/persistence.
- Result rendering rules.
- Privacy-sensitive personalization.
```

Phase 1 WP-MAPPING must include:

```txt
1. core/search block inventory.
2. Search form markup constraints.
3. Theme-can / plugin-should boundary.
4. Autocomplete/suggestions plugin boundary.
5. Anti-pattern inventory.
6. ActivityPub / social-CMS search cautions.
```

Anti-pattern candidates:

```txt
- Treating Search bar as a Text field variant.
- Styling arbitrary input[type=search] globally.
- Rendering suggestions without combobox/listbox relationship.
- Running remote autocomplete from theme JS.
- Persisting search history in theme runtime.
- Rendering federated search in theme layer.
- Using div role=searchbox instead of native input.
- Making trailing icon glyphs clickable without real buttons.
- Hiding labels/accessible names.
```

---

## Section 11 — M3 Search Alignment Summary

Material Search alignment for v3.5.8:

```txt
M3 Search fact:
  Search bar includes leading search icon, optional trailing icons/avatar,
  input text, and suggestions/results surface.

Axismundi status:
  Baseline shell covers leading icon/input/trailing slot at rest.
  search-expansion/ covers focused/expanded suggestions interaction.

M3 Search fact:
  Search can show suggestions or results while focused/typing.

Axismundi status:
  search-expansion/ creates static demo suggestions and keyboard navigation.
  Data source and result rendering remain plugin territory.

M3 Search fact:
  Focused search can be docked or full-screen.

Axismundi status:
  Baseline comments already route active/expanded search view out of the
  rest shell. Phase 1 must decide v3.5.8 layout scope.

M3 Search fact:
  Search suggestions/results are list-like.

Axismundi status:
  search-expansion/ uses role=listbox / role=option. Future List component
  visual alignment is a candidate, not a Phase 0 blocker.
```

Phase 1 should cite M3 Search pages directly, but should avoid overfitting to
Material Web custom elements. Axismundi should align to Material's contract
and semantics while using native HTML, CSS, and selective JS compatible with
WordPress/theme constraints.

---

## Section 12 — Risks And Dispositions

### Risk 1 — Search bar drifts into Text field

```txt
Risk:
  Search bar is mistakenly treated as a Text field variant because it shares
  native input shell mechanics.

Disposition:
  CLOSED in Phase 0. Search bar remains distinct per Phase 0B #7 and M3
  Search semantics.
```

### Risk 2 — Audit shape over- or under-specified

```txt
Risk:
  A 3-doc trio hides extracted runtime complexity.
  A 4-doc set could over-shape a native-only component.

Disposition:
  CLOSED in Phase 0. Search bar uses 4-doc shape because search-expansion/
  is an extracted JS runtime with ARIA, keyboard, DOM mutation, and state.
```

### Risk 3 — search-expansion/ ownership ambiguity

```txt
Risk:
  Search bar docs, search-expansion docs, and future search-bar module could
  disagree about ownership.

Disposition:
  ROUTED. Phase 0 records disposition only. Phase 1 cross-references current
  runtime evidence. Phase 2 plan decides physical migration / absorption.
```

### Risk 4 — popover/ premature coupling

```txt
Risk:
  Suggestions popup becomes a popover consumer before Search bar runtime
  semantics are settled.

Disposition:
  ROUTED. popover/ is CANDIDATE future alignment, not v3.5.8 TARGET.
```

### Risk 5 — Suggestion items semantics vs List component

```txt
Risk:
  M3 describes suggestions/results as list-like, but Axismundi List #33 is
  separately queued.

Disposition:
  ROUTED. Search bar owns query-specific suggestion semantics in v3.5.8.
  List visual alignment is a future candidate, not a blocker.
```

### Risk 6 — WordPress search data scope

```txt
Risk:
  Search bar runtime crosses into backend search, autocomplete, analytics,
  federated query, or result rendering.

Disposition:
  CLOSED in Phase 0. These are plugin territory. Phase 1 WP-MAPPING must
  make the boundary explicit.
```

### Risk 7 — Class-name precision drift

```txt
Risk:
  Phase 2 pattern HTML invents non-baseline selectors such as
  .search-bar__container or .search-bar__suggestions without marking them
  lab-only.

Disposition:
  ROUTED. Phase 1 and Phase 2 must grep baseline §10 before authoring.
```

### Risk 8 — Runtime audit staleness

```txt
Risk:
  SEARCH-EXPANSION-AUDIT.md is a v3.3.4 extraction audit and predates the
  v3.5.x consumer-state vocabulary and Text field dual-category precedent.

Disposition:
  ROUTED. Phase 1 writes SEARCH-BAR-RUNTIME-AUDIT.md and cross-references the
  older audit as historical evidence.
```

---

## Section 13 — Phase 1 Entry Conditions

Phase 1 may begin after this report is approved.

Phase 1 deliverables:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/docs/
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md
  SEARCH-BAR-RUNTIME-AUDIT.md
```

Phase 1 must:

```txt
1. Preserve the 4-doc decision.
2. Keep Search bar distinct from Text field.
3. Treat search-expansion/ as current runtime evidence.
4. Avoid physical file migration.
5. Decide Phase 2 migration options only as plan/preparation.
6. Include M3 Search external citations.
7. Include class-name precision inventory from baseline §10.
8. Include WordPress core/search mapping.
9. Include plugin territory boundary.
10. Include Playwright Phase 2/3 QA plan.
```

Phase 1 must not:

```txt
- Create lab-search-bar.css/js/pattern.html.
- Move search-expansion/ files.
- Edit components.css §10.
- Edit style-guide.html #components-search-bar.
- Edit CURRENT-STATE.md or NEXT-SESSION.md.
- Edit CHANGELOG.md / ROADMAP.md / BACKLOG.md.
```

---

## Section 14 — G1-G16 Applicability

Search bar is Component Full-Spec + Interaction.

Universal + Component Full-Spec gates:

```txt
G1  Validator 1.000 PASS                         Applicable
G2  Baseline untouched unless authorized          Applicable
G3  Publish runs cleanly                          Applicable at close
G4  Module artifacts present                      Applicable after Phase 2
G5  CHANGELOG entry                               Applicable at Phase 5
G6  Static Visual QA                              Applicable after Phase 3
G7  Principle 1: visible control = real runtime   Applicable
G8  Principle 2: native semantics                 Applicable
G9  WCAG SC citation accuracy                     Applicable
G10 3-doc audit pattern                           Modified: 4-doc shape
```

Interaction Runtime gates:

```txt
G11 Hard interaction rules locked                 Applicable
G12 Hard rules verified in code                   Applicable
G13 Phase 0 inventory accuracy                    Applicable
G14 Forbidden ancestor / prose guard              Applicable
G15 Reduced motion                                Applicable
G16 Runtime audit document                        Applicable
```

G16 is the key difference from Text field. Text field carried interaction
coverage inside SPEC because it had no extracted JS runtime. Search bar has
`search-expansion/`, so a runtime audit is required.

Infrastructure gates:

```txt
G22-G26                                           Not applicable
```

Search bar is not infrastructure. `search-expansion/` is Search-specific and
does not serve multiple consumers.

---

## Section 15 — Non-Goals

v3.5.8 Phase 0 does not:

```txt
- Implement Search bar.
- Create lab/modules/search-bar/.
- Move or rename search-expansion/.
- Rewrite lab-search-expansion.css.
- Rewrite lab-search-expansion.js.
- Rewrite lab-search-expansion-pattern.html.
- Promote search-expansion to infrastructure.
- Couple Search bar to popover/ in this phase.
- Wire Ripple v2 to the Search bar host.
- Implement live search.
- Implement autocomplete data source.
- Implement federated search.
- Implement search analytics.
- Implement search result rendering.
- Add WordPress block styles.
- Edit components.css §10.
- Edit style-guide.html.
- Edit tokens.css / blocks.css / theme.json.
- Edit release bookkeeping docs.
- Touch CURRENT-STATE.md or NEXT-SESSION.md.
```

---

## Section 16 — Verdict

Phase 0 passes.

```txt
Search bar #17:
  Category:
    Component Full-Spec + Interaction

  Current status:
    PARTIAL

  Distinct from Text field:
    YES

  Existing runtime:
    search-expansion/ v3.3.4

  Phase 1 audit shape:
    4 docs

  search-expansion/ disposition:
    record only in Phase 0
    cross-reference in Phase 1
    migration decision in Phase 2 plan

  popover/:
    CANDIDATE future alignment
    not v3.5.8 TARGET

  ripple/:
    field host = NONE
    trailing icon buttons = via icon-button consumer
    suggestion items = CANDIDATE future alignment

  WordPress:
    core/search primary mapping
    backend data/search behavior = plugin territory
```

Next step:

```txt
v3.5.8 Phase 1 plan:
  Create SEARCH-BAR-PHASE-1-PLAN.md.
  Lock the 4-doc execution structure.
  Prepare Search bar audit docs without moving runtime files yet.
```

