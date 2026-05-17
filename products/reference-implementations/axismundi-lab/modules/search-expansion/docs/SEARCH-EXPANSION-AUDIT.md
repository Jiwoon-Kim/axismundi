# Search Expansion Audit — v3.3.4


> **v3.4.8 Deletion Notice (added retrospectively):**
> The benchmark source files referenced throughout this document
> (`scripts/benchmark-interactions.js`, `stylesheets/benchmark-interactions.css`,
> and `style-guide-benchmark.html`) were **removed from the active tree at
> v3.4.8 Benchmark Surface Deletion**. Line ranges and quoted file paths in
> this document are HISTORICAL references — they describe where the source
> code lived during extraction, not where it lives now. Provenance is
> preserved in this audit document, CHANGELOG.md / ROADMAP.md, the
> v3.X.Y zip freezes, and git history.

> Second Beer-CSS-derived module audit under
> `../../../docs/BEER-CSS-INTAKE.md`. Records the extraction-with-refinement
> decisions made when porting the benchmark search-bar focus
> expansion into a stand-alone lab module, the theme-territory vs
> plugin-territory boundary specific to search, and the per-module
> verdict on the five lab promotion criteria.

## Extraction record (v3.3.4)

### Source locations (before extraction)

| Asset | Path | Range / signature |
|---|---|---|
| CSS | `stylesheets/benchmark-interactions.css` | L181–262 (`.search-bar` transition / `:focus-within` + `.is-search-active` elevation / `.ax-search-suggestions` popup + items) |
| JS  | `scripts/benchmark-interactions.js` | L247–288 — `enableSearchBar()` |

### Target locations (after extraction)

| Asset | Path |
|---|---|
| CSS | `stylesheets/lab-search-expansion.css` |
| JS  | `scripts/lab-search-expansion.js` |
| Demo page | `lab-search-expansion-pattern.html` |
| Audit (this file) | `docs/SEARCH-EXPANSION-AUDIT.md` |
| Cross-module contract | `../../../docs/BEER-CSS-INTAKE.md` |

### Audit-trail markers in benchmark

Per intake §9, the original benchmark sections are marked
`EXTRACTED: v3.3.4 → <path>` but not deleted. The benchmark page
continues to load `enableSearchBar()` from its own copy.

## Approach taken — extract-with-refinement

Same as v3.3.3 ripple: the benchmark structure (CSS transition +
state class + popup panel; JS class-toggling state machine) is
sound. The Beer-CSS-isms are concentrated in tokenization gaps,
missing reduced-motion handling, a missing `.prose` bail-out, a
half-finished a11y wiring, and an Escape-key policy that loses
typed query state.

Verdict: **extract with seven specific refinements** (enumerated
below), not a fresh reimplementation.

### Refinements applied

| # | Issue in benchmark | Refinement in lab module | Intake clause |
|---|---|---|---|
| 1 | Hardcoded magic numbers: `gap: 2px`, `padding: 8px 0`, `gap: 12px`, `min-height: 44px`, `padding: 0 16px` | Module-local custom properties declared at `:root` (`--ax-search-suggestions-item-gap`, `--ax-search-suggestions-item-icon-gap`, `--ax-search-suggestions-item-min-height`) with rationale comments; `padding` now uses `var(--space-*)` tokens. `44px` is retained as the WCAG 2.5.5 Target Size minimum, documented as an accessibility floor rather than a design token. | §3 |
| 2 | Suggestion item `:hover` / `:focus-visible` used a hand-rolled `color-mix(... on-surface 8% ...)` | Replaced with a Pattern A state-layer pseudo (`::before` with `currentColor` + `--md-sys-state-hover-state-layer-opacity` / `--md-sys-state-focus-state-layer-opacity`). Matches every other interactive surface in `components.css §0`. | §7 |
| 3 | No `@media (prefers-reduced-motion: reduce)` block | Added explicit block: disables `translateY` lift, removes panel transform animation, sets transition durations to `0s`. Shadow elevation change is retained (shadows are depth, not motion). | §4 |
| 4 | No `.prose` / federation bail-out in JS — a `.search-bar` rendered inside `.prose` (e.g. via a future block pattern) would have rippled all the same | Added `if (bar.closest(FORBIDDEN_ANCESTORS)) return;` at the top of the `enableSearchBar()` per-instance loop, before any DOM mutation. Forbidden ancestors: `.prose, .wp-block-post-content, .entry-content, [contenteditable]`. | §1 |
| 5 | Escape key behavior lost typed query state (Esc with text typed → immediate blur + collapse, query gone) | Two-step Escape: with text present, first Esc clears the input (dispatches `input` event so any future filter-on-type UI stays in sync) and leaves the panel open; second Esc blurs + collapses. Matches macOS / iOS search-field convention. | §1 (semantic risk surfacing) |
| 6 | A11y: popup was `role="listbox"` and items were `role="option"`, but the input was a plain `<input type="search">` with no `aria-controls` / `aria-expanded` / `aria-autocomplete` — the listbox was a free-floating widget from a screen reader's perspective | Input now gets `role="combobox"` + `aria-controls="<panel id>"` + `aria-expanded` (kept in sync with `.is-search-active`) + `aria-autocomplete="list"`. Each suggestion item gets `aria-selected="false"` + `tabindex="-1"` for arrow-key roving focus. | §1 |
| 7 | Keyboard navigation between input and suggestions was unimplemented (panel was open but only mouse / Tab through the items worked, no ArrowDown from input → first option) | Added ArrowDown / ArrowUp / Home / End handlers: ArrowDown from input or any item moves to next; ArrowUp at first item moves back to input; Home/End jump to first/last item. | (a11y polish under §1) |

### Refinements deliberately NOT applied

- **Per-instance listener pattern** — ripple uses one delegated `document` listener; search uses four per-instance listeners (`focus`, `input`, `keydown`, `focusout`). Per-instance is correct here because each search-bar has its own panel id, its own ARIA state, and its own suggestion data. Delegation would force lookup-by-target on every event. Intake §6 allows this — "one delegating listener per module" is for cases where a global listener can cleanly cover all instances; per-instance is acceptable when state is naturally per-instance. Documented in JS file header.
- **Panel position relative to viewport edges** — the popup uses `inset-inline: 0` to span the full bar width. For a narrow viewport with a wide bar, this could clip if the bar is near a viewport edge, but in practice `.search-bar` is rendered with a `max-inline-size`, not flush to the viewport edge. Repositioning logic (mirroring popper/floating-ui behavior) is out of scope for this module; if a future block pattern places `.search-bar` flush against a screen edge, that's a v3.3.5 popover-style concern.
- **Live filtering of suggestions** — explicitly NOT in scope (see plugin territory below). The static demo list demonstrates the visual + interaction layer only.

## Theme territory vs Plugin territory

Search is the second Beer-CSS-derived module to need an explicit
territory boundary (popover/menu in v3.3.5 will be the third). The
distinction matters because search has a strong pull toward data
features (autocomplete, live results, federated query) that
absolutely do not belong in a theme.

### Theme territory (allowed in this module and its eventual successor in `components.css` / `interactions.js`)

- Visual expansion on focus / active state (elevation lift, transform).
- Suggestions popup visual chrome (background, shadow, corner radius, item layout).
- Clear-button visual affordance (an icon button inside the trailing slot that clears the input).
- Keyboard navigation within the popup (Arrow keys, Home, End).
- ARIA wiring for screen reader announcement of the popup as a combobox listbox.
- The Escape-key policy (clear, then collapse).
- Reduced-motion handling.
- `.prose` / federation bail-out.

### Plugin territory (NOT in this module, ever)

- Live search query against any data source (REST endpoint, local search index, etc.).
- Remote search / external API calls.
- Federated search across ActivityPub feeds — the microblog target federates over AP, and search-across-the-fediverse is a complex plugin-scope feature (privacy, federation policy, query routing all involved).
- Suggestion data source (the demo data in this module is hardcoded).
- Query analytics / telemetry.
- Search result rendering (the suggestion popup is for typing-assist, not for results — results pages are separate templates).
- "Did you mean" / spell-correction.
- Saved searches / search history persistence.

This boundary is enforced by where the code lives: anything that
queries a backend, persists state, or implements federation logic
must live in a plugin package under `products/distributables/plugins/`,
never inside theme runtime.

## Lineage

Visual pattern reference: Beer CSS docs page (frozen in `compare/`).
The reference is a CSS framework's take on search-bar expansion;
Axismundi's interpretation is significantly stricter on a11y wiring
and Escape-key behavior.

`compare/Beer CSS - …html` is frozen reference, not authority
(per `BEER-CSS-INTAKE.md`).

## Promotion criteria — five-criterion check

### 1. Works without JS

When `lab-search-expansion.js` does not load:
- The `.search-bar` still renders normally (its layout is in
  `components.css §10`).
- Focus elevates the bar via the CSS `:focus-within` selector
  (defined in `lab-search-expansion.css`, no JS dependency).
- Input accepts text normally.
- If the bar is wrapped in a `<form>`, Enter submits.
- The suggestions popup does NOT appear (it is built by JS), but
  the input itself remains usable.

**Status: PASS by construction.**

### 2. M3 state-layer compliance

Suggestion items use Pattern A state-layer via `::before` pseudo
with `--md-sys-state-hover-state-layer-opacity` (0.08) and
`--md-sys-state-focus-state-layer-opacity` (0.10). Matches
`components.css §0` exactly. The `.search-bar` host's own state
layer is unchanged (defined in `components.css §10`, untouched
by this module).

**Status: PASS.**

### 3. `prefers-reduced-motion: reduce` honored

Explicit `@media (prefers-reduced-motion: reduce)` block in
`lab-search-expansion.css`:
- `transform: none` on `.search-bar:focus-within`,
  `.search-bar.is-search-active`, and the suggestions panel in
  both states.
- `transition: 0s` on the elevation/transform properties.
- Shadow elevation change is retained (depth, not motion).
- State-layer transitions on suggestion items disabled.

**Status: PASS.**

### 4. Keyboard-operable

Full keyboard model: Tab to focus → expand; ArrowDown from input
→ first option; Arrow keys navigate options; Home/End jump;
ArrowUp at first option returns to input; Enter selects; Escape
clears or collapses per the two-step policy. Focus is never
trapped (Tab-out works at any time).

**Status: PASS.**

### 5. No leak into `.prose` / `post_content` / federated surfaces

JS has explicit `if (bar.closest(FORBIDDEN_ANCESTORS)) return;`
before any DOM mutation. The pattern page includes two negative
test cases (`.prose` container with a `.search-bar` inside, and a
`[contenteditable]` container with another) so the isolation is
visually confirmable. CSS selectors do not match inside `.prose`
either (the popup panel only renders when JS adds it).

**Status: PASS by explicit JS bail-out.**

### Verdict summary

| Criterion | Status | Blocker? |
|---|---|---|
| 1. Works without JS | PASS by construction | No |
| 2. M3 state-layer compliance | PASS | No |
| 3. `prefers-reduced-motion: reduce` honored | PASS | No |
| 4. Keyboard-operable | PASS | No |
| 5. No `.prose` / federation leak | PASS by explicit bail-out | No |

**Verdict: PASS on all five criteria.** Manual visual QA on
`lab-search-expansion-pattern.html` is the next step before
promotion is considered. Promotion target: `components.css §10
Search bar` for the CSS (the elevation transition + state class +
popup chrome can compose with the existing structure), and either
`theme.js` or a new `interactions.js` for the JS.

## Module file inventory

```
products/reference-implementations/axismundi-lab/
├─ stylesheets/
│  └─ lab-search-expansion.css        ← module CSS
├─ scripts/
│  └─ lab-search-expansion.js         ← module JS (per-instance setup,
│                                       not delegated)
├─ lab-search-expansion-pattern.html  ← demo + negative test cases
└─ docs/
   ├─ BEER-CSS-INTAKE.md              ← cross-module contract (v3.3.3)
   └─ SEARCH-EXPANSION-AUDIT.md       ← this file
```

`publish_styleguide.py` is not modified. `lab-search-expansion.css`
will appear in `/styleguide/stylesheets/` as an orphan publish
artifact (same asymmetry as v3.3.2 carousel and v3.3.3 ripple); JS
and HTML are not copied. Addressed in v3.4.x Lab Module
Restructure.

## Module-comparison notes (v3.3.3 ripple vs v3.3.4 search)

Useful for future module authors to reference:

| Aspect | Ripple (v3.3.3) | Search expansion (v3.3.4) |
|---|---|---|
| Listener pattern | One delegated `document` listener | Four per-instance listeners |
| Why | Ripple is stateless and global; one handler can match any allowlisted target | Search is per-instance stateful (each bar has its own panel id + ARIA state) |
| Reduced motion | `animation: none` + opacity transition | `transform: none` + transitions to `0s` |
| Why | Ripple's animation is the entire effect; opacity transition replaces the keyframe | Search expansion is multi-property; suppressing transform is enough, shadow elevation can stay |
| ARIA work | None needed (decorative span, `aria-hidden`) | Full combobox/listbox/option wiring on real interactive elements |
| Escape policy | N/A (no input) | Two-step (clear, then collapse) |

These comparison rows are a sample template — future module
audits can append their own row to this table when relevant.

## Future audit cycles

Subsequent audit revisions needed when:
- Manual visual QA against `lab-search-expansion-pattern.html` returns findings.
- A live-suggestions / autocomplete feature is requested (would have to be a separate plugin module, NOT this one).
- The intake contract changes a constraint that affects this module.
- Promotion to `components.css §10` is approved.

Each revision appends a section to this file (not a new file).
