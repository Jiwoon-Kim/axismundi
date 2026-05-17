# Search Bar #17 — Phase 0 Plan (v3.5.8)

> **Status**: Plan-only. Do not execute Phase 0 report until this plan is reviewed and approved.  
> **Target component**: Search bar #17  
> **Matrix row**: `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #17  
> **Current matrix status**: PARTIAL (`search-expansion/` exists; Full-Spec layer owed)  
> **Category**: Component Full-Spec + Interaction  
> **Primary objective**: Plan the Phase 0 investigation that decides whether Search bar closes as a 3-doc native/CSS-style component like Text field, or as a 4-doc dual-category component because an extracted runtime already exists.

---

## §0 — Executive Summary

v3.5.8 should enter with Search bar #17 because it is the closest sibling to
the just-closed Text field #16 and already has a PARTIAL interaction module:

```txt
Text field #16
  v3.5.7 DONE
  native/CSS interaction
  3-doc trio
  no runtime audit

Search bar #17
  PARTIAL
  search-expansion/ runtime exists
  audit shape must be decided in Phase 0
```

This plan does not create `lab/modules/search-bar/`, does not move
`search-expansion/`, and does not edit baseline files. It only defines what
the Phase 0 report must read, decide, and route.

---

## §1 — Non-Negotiable Scope Locks

Phase 0 planning and Phase 0 report authoring are documentation-only.

Allowed in this planning cycle:

```txt
- Create docs/v3.5.8/SEARCH-BAR-PHASE-0-PLAN.md.
- Later, after approval, create docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md.
- Read baseline §10 Search bar.
- Read style-guide.html #components-search-bar.
- Read lab/modules/search-expansion/ fully.
- Read v3.5.0 framework docs and v3.5.7 Text field precedent.
```

Forbidden in this planning cycle:

```txt
- Create lab/modules/search-bar/.
- Move, rename, or rewrite lab/modules/search-expansion/.
- Edit lab-search-expansion.css/js/pattern.html.
- Edit components.css §10.
- Edit style-guide.html #components-search-bar.
- Edit tokens.css, blocks.css, theme.json.
- Edit CHANGELOG.md, ROADMAP.md, BACKLOG.md.
- Edit CURRENT-STATE.md or NEXT-SESSION.md.
- Implement Search bar Phase 1/2 artifacts.
```

---

## §2 — Why Search Bar Next

Search bar is the best v3.5.8 mainline candidate because:

```txt
1. It directly follows Text field #16 in the Inputs family.
2. It is PARTIAL, so Phase 0 can leverage an existing interaction extraction.
3. It tests the dual-category rule established by Text field:
   native/CSS interaction → 3-doc;
   extracted runtime → likely 4-doc.
4. It advances Wave 1 from 5/9 closed toward 6/9.
5. It clarifies whether search-expansion/ becomes search-bar/ or remains a
   referenced runtime lane.
```

This is also the cleanest moment to prevent a drift where Search bar is
mistakenly treated as "just a Text field variant."

---

## §3 — Authoritative Inputs To Read

### §3.1 — v3.5.0 Framework

Read:

```txt
docs/v3.5.0/33-COMPONENT-INVENTORY.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/COMPONENT-COVERAGE-MAP.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Required extractions:

```txt
- Phase 0B decision #7:
  Search bar is distinct from Text field.

- MATRIX row #17:
  PARTIAL; existing search-expansion/; target search-bar/ absorbs
  search-expansion/.

- PROMOTION §4.3:
  Component Full-Spec + Interaction typically requires both artifact sets:
  3 audit docs + 1 runtime audit doc + CSS + JS + pattern HTML.

- DISTINCT but COUPLED principle:
  Search bar owns search semantics; infrastructure/runtime providers must
  not absorb consumer semantics.
```

### §3.2 — Baseline Search Bar

Read:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css §10
products/reference-implementations/axismundi-lab/style-guide.html #components-search-bar
```

Phase 0 report must inventory:

```txt
- Baseline selector list:
  .search-bar
  .search-bar::before
  .search-bar__leading-icon
  .search-bar__trailing
  .search-bar__input

- Baseline role:
  rest-state search field shell;
  56px pill container;
  level3 elevation;
  leading search icon;
  trailing affordance slot;
  native <input type="search">.

- Current limitation:
  components.css explicitly states rest state only;
  active/expanded search view lives outside baseline.
```

### §3.3 — Existing Interaction Module

Read fully:

```txt
products/reference-implementations/axismundi-lab/modules/search-expansion/
  lab-search-expansion.css
  lab-search-expansion.js
  lab-search-expansion-pattern.html
  docs/SEARCH-EXPANSION-AUDIT.md
```

Known initial facts to verify in Phase 0 report:

```txt
- Existing module is v3.3.4 Beer-CSS-derived extraction.
- It implements per-instance JS setup.
- It adds suggestion popup DOM.
- It wires role=combobox, aria-controls, aria-expanded,
  aria-autocomplete=list.
- It implements Arrow navigation, Home/End, Escape clear-then-collapse.
- It has forbidden-ancestor bail-out for .prose, .wp-block-post-content,
  .entry-content, [contenteditable].
- It treats live search, remote search, autocomplete data, federated search,
  analytics, and result rendering as plugin territory.
```

### §3.4 — Wave 1 Precedents

Reference order:

```txt
1. Text field v3.5.7
   Closest Inputs sibling; dual category resolved as 3-doc because
   interaction is native/CSS state behavior.

2. Icon button v3.5.2
   Prior runtime-audit disposition problem; useful for deciding whether
   existing runtime docs stay in place or migrate.

3. Ripple v3.5.6
   Infrastructure/runtime contract and closed consumer alignment precedent.

4. Card v3.5.3
   Composition boundary and action surface split.

5. Button v3.5.1
   Action/control disabled split and visible control principle.

6. Chip v3.4.9
   Original 3-doc audit template.
```

### §3.5 — External Material References

Phase 0 report must verify current M3 search guidance before finalizing
decisions:

```txt
Official Material Design:
  https://m3.material.io/components/search/overview
  https://m3.material.io/components/search/specs
  https://m3.material.io/components/search/guidelines

Official Android/Compose Material3 API as a secondary source:
  SearchBar / DockedSearchBar / ExpandedFullScreenSearchBar
```

Note:

```txt
The M3 web pages require JavaScript in simple text fetches. Phase 0 should
use browser/Playwright/manual source review if needed, and cite only stable
findings rather than over-quoting inaccessible page text.
```

---

## §4 — Phase 0 Report Shape

Expected report:

```txt
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
```

Required sections:

```txt
§0  Phase 0 framing
    - Search bar #17, Wave 1, Inputs family, PARTIAL row.
    - Distinct from Text field re-citation.

§1  Authoritative inputs read
    - v3.5.0 framework docs
    - baseline §10
    - style-guide #components-search-bar
    - search-expansion/ module
    - Text field v3.5.7 precedent
    - M3 Search references

§2  Baseline §10 inventory
    - selector inventory
    - visual anatomy
    - current public specimens
    - rest-state-only limitation

§3  search-expansion/ inventory
    - CSS/JS/pattern/audit docs
    - runtime behavior
    - ARIA combobox/listbox/option wiring
    - keyboard model
    - forbidden ancestors
    - plugin-territory boundaries

§4  Search bar vs Text field boundary
    - Phase 0B decision #7
    - shared input-shell ideas
    - search-specific affordances
    - explicit "not a Text field variant" verdict

§5  Audit doc shape decision
    - 3-doc vs 4-doc analysis
    - Text field native/CSS precedent
    - search-expansion extracted runtime precedent
    - Phase 0 verdict for Phase 1 deliverables

§6  search-expansion/ disposition
    - absorb, reference runtime, or hybrid
    - migration timing
    - cross-reference preservation
    - whether existing SEARCH-EXPANSION-AUDIT.md becomes historical,
      migrated, or cited

§7  Suggestions popup ontology
    - Search bar internal expanded surface vs popover/ consumer
    - DISTINCT but COUPLED analysis
    - current search-expansion/ self-contained behavior
    - future popover alignment candidate if appropriate

§8  Dependency profile / consumer-state
    - components.css §10 = CURRENT baseline shell
    - search-expansion/ = CURRENT/PARTIAL runtime
    - popover/ = TARGET or CANDIDATE for suggestions surface
    - ripple/ = NONE for field host; TARGET/CANDIDATE for suggestion items
    - icon-system/ = CURRENT conditional or near-unconditional for leading
      search icon
    - icon-button composition for trailing clear/filter/mic controls

§9  Variants, states, and interaction surface
    - rest / focused / filled / expanded
    - suggestions / recent searches / no results
    - leading icon / trailing icons
    - clear affordance
    - Escape and Arrow key policy

§10 WordPress mapping stub
    - core/search primary mapping
    - query loop / sidebar / template context
    - plugin territory for search data, autocomplete, result rendering,
      federation, analytics

§11 Playwright + class-name precision QA plan
    - baseline class grep
    - dimension trace
    - expanded surface geometry
    - keyboard path
    - forbidden ancestor negative tests

§12 Risks + dispositions
    - 5-8 risks surfaced honestly

§13 Phase 1 entry conditions
    - deliverables list depends on §5 audit-shape verdict
    - non-goals
    - validation commands

§14 G1-G16 applicability
    - G1-G10 Component Full-Spec
    - G11-G16 Interaction
    - if 4-doc chosen, runtime audit owns extracted runtime gates

§15 Non-goals
    - no data search
    - no remote/federated search
    - no result page rendering
    - no baseline mutation
    - no module migration in Phase 0

§16 Verdict
    - Phase 0 PASS / not PASS
    - Phase 1 allowed deliverable shape
```

---

## §5 — Lock Decision 1: Search Bar Distinct From Text Field

Phase 0 report must re-cite v3.5.0 Phase 0B decision #7:

```txt
Search bar is distinct from Text field because it owns search-specific
affordances: leading search icon, expanded state, suggestions, search query
semantics, and search result/data boundary.
```

Why this matters:

```txt
Text field #16 owns generic input shell.
Search bar #17 owns search task entry.

The two share form-control ideas, but Search bar is not a variant of Text
field and must not be folded into Text field audit docs.
```

Expected Phase 0 report verdict:

```txt
Search bar = distinct component.
Text field precedent is sibling/reference, not parent.
```

---

## §6 — Lock Decision 2: Audit Shape (3-Doc vs 4-Doc)

This is the largest ontology question in v3.5.8.

Text field precedent:

```txt
Text field #16
  Component Full-Spec + Interaction
  native/CSS state behavior
  no reusable JS runtime
  3-doc trio
```

Search bar current evidence:

```txt
Search bar #17
  Component Full-Spec + Interaction
  search-expansion/ exists
  CSS + JS + pattern + audit
  runtime behavior: expansion, suggestions, ARIA, keyboard, Escape policy
```

Therefore Phase 0 must decide:

```txt
Option A — 3-doc trio
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md

  Use only if Phase 0 concludes search-expansion/ remains historical or is
  not the canonical Search bar runtime.

Option B — 4-doc shape
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md
  SEARCH-BAR-RUNTIME-AUDIT.md

  Use if Phase 0 concludes search-expansion/ is the current extracted
  runtime side of Search bar #17.
```

Recommendation before report:

```txt
Lean Option B (4-doc) unless inventory proves search-expansion/ is too stale
or too narrow to be canonical.
```

Reason:

```txt
PROMOTION-CRITERIA §4.3 says Component Full-Spec + Interaction requires both
artifact sets in practice. Search bar already has an extracted JS runtime,
unlike Text field.
```

Phase 0 report must not rubber-stamp this. It must read `search-expansion/`
and decide from evidence.

---

## §7 — Lock Decision 3: search-expansion/ Disposition

Current state:

```txt
Existing: lab/modules/search-expansion/
Target:   lab/modules/search-bar/ (matrix says absorbs search-expansion/)
```

Phase 0 should evaluate three options:

```txt
Option A — Absorb now
  Move/rename search-expansion/ into search-bar/.

  Pro: single ownership.
  Con: too much for Phase 0; breaks history/cross-refs if rushed.

Option B — Reference runtime
  Keep search-expansion/ as separate runtime and create search-bar/ as
  Full-Spec layer referencing it.

  Pro: minimal churn.
  Con: ownership remains split.

Option C — Hybrid / staged absorption
  Phase 0 records disposition only.
  Phase 1 plans docs.
  Phase 2 or a follow-up performs actual migration if approved.

  Pro: mirrors disciplined patterns from prior cycles.
  Con: requires careful cross-reference hygiene.
```

Recommendation:

```txt
Option C.

Do not move files in Phase 0.
Let Phase 0 decide target ownership and Phase 1/2 plan the actual migration.
```

Phase 0 report should explicitly decide whether:

```txt
- SEARCH-EXPANSION-AUDIT.md remains historical reference only.
- SEARCH-BAR-RUNTIME-AUDIT.md incorporates it.
- search-expansion/ is later renamed/migrated to search-bar/.
- search-expansion/ stays as an internal runtime provider for search-bar/.
```

---

## §8 — Lock Decision 4: Suggestions Popup Ontology

Open question:

```txt
Are suggestions a Search bar internal expanded state, or a popover/ consumer?
```

Evidence for internal:

```txt
- Existing search-expansion/ self-contains the popup.
- It appends `.ax-search-suggestions` inside `.search-bar`.
- It does not use popover/.
- It owns combobox/listbox/option wiring.
```

Evidence for popover/ alignment:

```txt
- Suggestions are an anchored expanded surface.
- v3.5.0 DISTINCT but COUPLED principle routes anchor/position/dismiss
  semantics to infrastructure when reusable.
- Menu / Split button / FAB menu / Date+Time picker already use or target
  popover/ for anchored surfaces.
```

Recommendation for Phase 0:

```txt
Do not force popover/ migration in v3.5.8 Phase 0.

Classify:
  search-expansion/ = CURRENT/PARTIAL self-contained runtime.
  popover/ = CANDIDATE or TARGET-FUTURE for suggestions surface alignment,
             depending on inventory.
```

If Phase 0 chooses popover/ TARGET, it must explain:

```txt
- why Search bar suggestion geometry should be owned by popover/;
- how combobox/listbox semantics stay owned by Search bar;
- whether Phase 2 migration is in scope or deferred.
```

Conservative likely verdict:

```txt
Search bar owns suggestions semantics.
search-expansion/ currently owns the self-contained expanded surface.
popover/ is a future alignment candidate, not a v3.5.8 blocker.
```

---

## §9 — Lock Decision 5: Dependency Profile

Phase 0 report must produce a state-aware dependency table.

Initial expected profile:

| Provider / layer | Expected consumer-state | Notes |
|---|---|---|
| `components.css §10` | CURRENT | Baseline rest-state Search bar shell |
| `search-expansion/` | CURRENT/PARTIAL | Existing extracted interaction runtime; exact disposition TBD |
| `icon-system/` | CURRENT conditional / near-unconditional | Leading search glyph is search-defining, trailing glyphs vary |
| `icon-button/` | CURRENT conditional composition | Clear/filter/mic trailing controls should be real controls |
| `ripple/` | NONE for field host | Search field host is input shell, not ripple target |
| `ripple/` | TARGET/CANDIDATE for suggestion items | Depends whether suggestion items behave like list/menu items |
| `popover/` | CANDIDATE or TARGET-FUTURE | Suggestions surface may align later; Phase 0 must decide |

Important distinction:

```txt
Field host:
  no data-ax-ripple.

Trailing icon-button:
  ripple through icon-button consumer, not through Search bar host.

Suggestion item:
  may be ripple consumer if item is action/selectable surface.
```

---

## §10 — Lock Decision 6: Variants And States

Phase 0 should inventory M3 and baseline states before Phase 1:

```txt
Search bar shell:
  rest
  hover
  focused
  filled / query entered
  disabled

Expanded/search view:
  active / expanded
  suggestions visible
  recent searches
  autocomplete suggestions
  no results
  result preview (likely plugin territory)

Slots:
  leading search icon
  trailing clear
  trailing filter
  trailing voice/mic
  optional avatar/account? (M3/Android APIs may expose this; verify)

Runtime:
  ArrowDown into suggestions
  ArrowUp back to input
  Home/End within list
  Escape clear-then-collapse
  Enter / selection
  focusout collapse
```

Phase 0 report must decide which of these are:

```txt
CURRENT in baseline
CURRENT in search-expansion/
TARGET for v3.5.8 Full-Spec
CANDIDATE for future behavior release
PLUGIN territory
```

---

## §11 — Lock Decision 7: WordPress Mapping

Primary mapping:

```txt
core/search
```

Phase 0 WP mapping stub should enumerate:

```txt
- core/search block
- search form in templates
- sidebar/header search patterns
- query loop/search results templates
- block variations or patterns
- plugin territory for autocomplete data, remote search, federated search,
  analytics, saved searches, and live results
```

Theme-can:

```txt
- Search bar visual shell
- Search block style variation / pattern shape
- static suggestion surface preview if not data-backed
- focus/expanded visual affordance if runtime approved
```

Plugin-should:

```txt
- live suggestions
- async data source
- search result rendering beyond core search page
- federated search
- query analytics
- personalization/history
```

---

## §12 — Class-Name Precision And Playwright QA

v3.5.6 Tab/Nav QA taught that baseline class-name precision must be explicit.

Phase 0 report must require baseline grep before any Phase 2 markup:

```txt
rg -n "search-bar|search-expansion|ax-search-suggestions" \
  products/reference-implementations/axismundi-lab/stylesheets/components.css \
  products/reference-implementations/axismundi-lab/style-guide.html \
  products/reference-implementations/axismundi-lab/modules/search-expansion
```

Known baseline classes:

```txt
.search-bar
.search-bar__leading-icon
.search-bar__trailing
.search-bar__input
.ax-search-suggestions
.ax-search-suggestions__item
.is-search-active
```

Do not assume additional classes until grep proves them.

Playwright must enter Phase 2/3 as a formal gate:

```txt
- Rest bar dimension trace: height 56px, max width specimen.
- Leading icon size/position.
- Trailing clear/filter/mic geometry.
- Focus outline and active elevation.
- Expanded suggestions geometry.
- Arrow key path from input to suggestions and back.
- Escape policy: text clears first, empty collapses.
- Forbidden ancestor negative tests.
- No search-expansion/ runtime attachment inside .prose/[contenteditable].
- No data fetch / plugin behavior in theme runtime.
```

Screenshots should remain gitignored by existing `docs/**/*-qa.png` policy.

---

## §13 — Expected Risks To Surface

### R1 — Search bar collapses into Text field

Risk:

```txt
Search bar may be treated as a Text field variant because it contains an
input shell.
```

Disposition:

```txt
Re-cite Phase 0B #7 and classify it as distinct.
```

### R2 — Audit shape drift

Risk:

```txt
Text field's 3-doc success may be over-applied to Search bar even though an
extracted runtime exists.
```

Disposition:

```txt
Phase 0 must explicitly decide 3-doc vs 4-doc from search-expansion evidence.
```

### R3 — search-expansion ownership ambiguity

Risk:

```txt
Keeping search-expansion/ separate while creating search-bar/ may split
authority.
```

Disposition:

```txt
Recommended staged absorption decision. No file movement in Phase 0.
```

### R4 — Suggestions popup absorbs data/search semantics

Risk:

```txt
Visual suggestion list may drift into live search, autocomplete data, remote
search, or federated search.
```

Disposition:

```txt
Theme owns surface and keyboard shell; plugin owns data and results.
```

### R5 — popover/ over-promotion

Risk:

```txt
Phase 0 may force popover/ integration because suggestions are anchored.
```

Disposition:

```txt
Decide from current runtime and M3 evidence. Conservative default:
popover/ future alignment candidate, not Phase 0 blocker.
```

### R6 — ripple over-promotion

Risk:

```txt
Search field host may be incorrectly treated as ripple consumer.
```

Disposition:

```txt
Field host = NONE. Suggestion items and composed icon-buttons are separate
consumer surfaces.
```

### R7 — WordPress core/search boundary drift

Risk:

```txt
Theme may accidentally own search query behavior.
```

Disposition:

```txt
core/search visual mapping belongs in theme; data/search/result behavior
belongs in WordPress core or plugins.
```

### R8 — Legacy Beer-CSS provenance/staleness

Risk:

```txt
search-expansion/ is Beer-CSS-derived v3.3.4; its audit predates v3.5.x
consumer-state vocabulary and Playwright QA discipline.
```

Disposition:

```txt
Phase 0 must treat it as current evidence but not unquestioned authority.
Phase 1/2 may require runtime audit refresh.
```

---

## §14 — Phase 1 Entry Conditions

Phase 1 may start only after Phase 0 report settles:

```txt
1. Search bar distinct from Text field.
2. Audit doc shape: 3-doc or 4-doc.
3. search-expansion/ disposition.
4. Suggestions popup ontology.
5. Dependency profile.
6. WordPress mapping stub.
7. Phase 1 deliverable file list.
```

If Phase 0 chooses 4-doc shape, expected Phase 1 deliverables:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/docs/
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md
  SEARCH-BAR-RUNTIME-AUDIT.md
```

If Phase 0 chooses 3-doc shape, expected Phase 1 deliverables:

```txt
products/reference-implementations/axismundi-lab/modules/search-bar/docs/
  SEARCH-BAR-SPEC-AUDIT.md
  SEARCH-BAR-MEASUREMENT-AUDIT.md
  SEARCH-BAR-WP-MAPPING.md
```

In both cases, Phase 1 must not implement runtime or move
`search-expansion/` unless a later approved plan explicitly authorizes it.

---

## §15 — Validation Plan

After creating this plan:

```txt
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
=== Overall: 1.000 (PASS) ===
  A schema:  1.000
  B theme:   1.000
  C css:     1.000
  D runtime: 1.000
```

Integrity checks:

```txt
- Confirm lab/modules/search-bar/ not created.
- Confirm lab/modules/search-expansion/ untouched.
- Confirm baseline mtimes preserved.
- Confirm CURRENT-STATE.md and NEXT-SESSION.md untouched.
- Confirm no BACKLOG/CHANGELOG/ROADMAP edits.
```

---

## §16 — Non-Goals

This plan does not:

```txt
- Write SEARCH-BAR-PHASE-0-REPORT.md.
- Create search-bar audit docs.
- Create lab/modules/search-bar/.
- Rename search-expansion/.
- Move SEARCH-EXPANSION-AUDIT.md.
- Implement search-bar CSS.
- Implement search-bar JS.
- Implement live suggestions.
- Implement autocomplete data.
- Implement remote search.
- Implement federated search.
- Implement search result rendering.
- Add popover/ integration.
- Add ripple to the search field host.
- Edit baseline §10 Search bar.
- Edit style-guide.html.
- Edit tokens.css.
- Edit blocks.css.
- Edit theme.json.
- Edit CURRENT-STATE.md.
- Edit NEXT-SESSION.md.
```

---

## §17 — Plan Verdict

```txt
PASS as plan.

Search bar #17 should enter Phase 0 with one primary ontology question:

  Does the existing extracted search-expansion/ runtime make v3.5.8 a
  4-doc dual-category closure, unlike Text field's native/CSS 3-doc closure?

Phase 0 report must answer this from local evidence before Phase 1 starts.
```
