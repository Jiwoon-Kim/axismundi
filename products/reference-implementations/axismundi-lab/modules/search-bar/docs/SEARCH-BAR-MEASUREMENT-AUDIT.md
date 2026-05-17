# Search Bar — Measurement Audit (v3.5.8 Phase 5 Close)

> **Status**: v3.5.8 release closed; Phase 3 visual QA PASS.  
> **Component**: Search bar #17  
> **Companions**: `SEARCH-BAR-SPEC-AUDIT.md`, `SEARCH-BAR-WP-MAPPING.md`, `SEARCH-BAR-RUNTIME-AUDIT.md`

---

## §0 — Audit Status

This document owns Search bar measurement, token, geometry, motion, and WCAG
evidence. It covers both the baseline rest shell and the current
`search-expansion/` runtime evidence, but it does not implement new CSS.

---

## §1 — Inputs Read

```txt
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
components.css §10 Search bar
style-guide.html #components-search-bar
modules/search-expansion/lab-search-expansion.css
modules/search-expansion/lab-search-expansion.js
modules/search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
M3 Search specs/guidelines
```

---

## §2 — Baseline Dimensions

Current baseline shell:

| Measurement | Value | Source |
|---|---:|---|
| Host height | 56px | `.search-bar` |
| Padding inline | `var(--space-md)` | `.search-bar` |
| Corner | `var(--md-sys-shape-corner-full)` | `.search-bar` |
| Elevation | `var(--md-sys-elevation-shadow-level3)` | `.search-bar` |
| Typography | body-large | `.search-bar` |
| Leading icon | `var(--comp-icon-size-md)` | `.search-bar__leading-icon` |
| Input width | `flex: 1 1 auto` | `.search-bar__input` |

The 56px rest shell aligns with Search bar as a high-emphasis input surface,
not a generic Text field clone.

---

## §3 — M3 Search Measurement Comparison

M3 Search measurement concepts to preserve:

```txt
- Search bar container.
- Leading search icon.
- Input text area.
- Trailing icon/avatar affordances.
- Suggestions/results container.
- Focused/expanded layout.
- Contained and divided styles.
```

Axismundi baseline covers the rest shell. `search-expansion/` covers an
expanded suggestions surface, but it predates v3.5.x audit vocabulary.

Phase 2 must decide exact pattern dimensions for:

```txt
contained rest
contained focused/expanded
divided style, if included
docked suggestions panel
full-screen search view, if included or deferred
```

---

## §4 — Search Bar Shell Tokens

Baseline tokens:

```txt
--space-md
--md-sys-shape-corner-full
--md-sys-color-surface-container-high
--md-sys-color-on-surface
--md-sys-color-on-surface-variant
--md-sys-elevation-shadow-level3
--md-sys-typescale-body-large-*
--md-sys-state-hover-state-layer-opacity
--md-sys-motion-curve-fast-effects-*
```

Phase 2 must not introduce new system tokens casually. Lab-only custom props
may be used for search-specific layout details if they are scoped and
documented.

Current `search-expansion/` uses module-local props:

```txt
--ax-search-suggestions-item-gap
--ax-search-suggestions-item-icon-gap
--ax-search-suggestions-item-min-height
```

These are evidence, not final v3.5.8 token decisions.

---

## §5 — Icon And Trailing Action Geometry

Leading icon:

```txt
slot: .search-bar__leading-icon
glyph size: var(--comp-icon-size-md)
semantics: decorative search affordance unless it is a real button
```

Trailing actions:

```txt
slot: .search-bar__trailing
composition: ax-icon-button and ax-avatar
gap: var(--space-sm)
```

Geometry rule:

```txt
Trailing controls must use their own component geometry. Search bar must not
resize or restyle icon-button internals to fake alignment.
```

Phase 2 Playwright should verify:

```txt
- leading icon center aligns with 56px shell
- input does not overlap trailing slot
- trailing icon-button keeps expected hit area
- avatar does not collapse input min-width
```

---

## §6 — Suggestions Popup Geometry

Current `search-expansion/` popup:

```txt
selector: .ax-search-suggestions
position: absolute
inset-block-start: calc(100% + var(--space-xs))
inset-inline: 0
z-index: 30
padding-block: var(--space-sm)
corner: var(--md-sys-shape-corner-large)
background: var(--md-sys-color-surface-container-low)
shadow: var(--md-sys-elevation-shadow-level2)
```

Suggestion item:

```txt
selector: .ax-search-suggestions__item
min-height: 44px
padding-inline: var(--space-md)
gap: --ax-search-suggestions-item-icon-gap
state layer: Pattern A ::before
```

Phase 1 verdict:

```txt
44px is acceptable as an accessibility floor in current evidence.
Phase 2 may move to 48px if M3 density alignment is prioritized.
```

---

## §7 — Focus / Expanded / Disabled Measurements

Focus shell:

```txt
.search-bar:focus-within
  outline: 2px solid var(--md-sys-color-secondary)
  outline-offset: 2px
```

Expanded runtime:

```txt
.search-bar:focus-within,
.search-bar.is-search-active
  box-shadow: var(--md-sys-elevation-shadow-level2)
  transform: translateY(-1px)
```

Disabled:

```txt
.search-bar[aria-disabled="true"]
  cursor: not-allowed
  pointer-events: none
  muted background/color
  level0 elevation
```

Phase 2 must decide whether disabled Search bar uses:

```txt
native disabled input
aria-disabled host
both with a documented pattern
```

Search bar likely needs a Pattern A split:

```txt
native disabled input for actual form inactivity
aria-disabled host only when plugin/runtime manages composite inactivity
```

---

## §8 — Motion And Reduced Motion

Current runtime motion:

```txt
Search bar elevation/transform transition on focus/active.
Suggestions panel opacity + transform + visibility transition.
```

Reduced motion evidence:

```txt
@media (prefers-reduced-motion: reduce) in lab-search-expansion.css:
  transform disabled
  transitions collapse to 0s
  shadow depth retained
```

Phase 2 must preserve:

```txt
- No transform animation under reduced motion.
- No layout jump that obscures focus.
- No motion dependence for understanding suggestions state.
```

---

## §9 — WCAG SC Applicability

| SC | Applicability |
|---|---|
| 1.4.3 Contrast Minimum | Text and placeholder contrast must be checked |
| 1.4.11 Non-text Contrast | Focus outline, container, state layer, popup boundaries |
| 2.1.1 Keyboard | Runtime suggestions must be keyboard operable |
| 2.4.3 Focus Order | Input -> suggestions -> next content order |
| 2.4.7 Focus Visible | Input and suggestion option focus |
| 2.5.8 Target Size Minimum | Trailing buttons and suggestion items |
| 2.5.5 Target Size Enhanced | Suggestion 44px evidence; icon-button composition may meet separately |
| 3.3.2 Labels or Instructions | Search input must have accessible name/label |
| 4.1.2 Name, Role, Value | combobox/listbox/option ARIA |
| 4.1.3 Status Messages | Live result updates are plugin territory unless implemented |

Important nuance:

```txt
Search field host itself is a text input surface, not an action target.
Trailing icon buttons and suggestion items are action targets.
```

---

## §10 — Playwright Phase 2/3 QA Plan

Minimum Playwright checks:

```txt
1. Rest shell height = 56px.
2. Leading icon computed font-size/box aligns to comp-icon-size-md.
3. Trailing icon-button geometry remains icon-button-owned.
4. Focus-within outline appears without clipping.
5. Expanded suggestions panel appears below host.
6. Suggestion item min-height >= 44px.
7. ArrowDown moves focus from input to first option.
8. Escape with text clears, keeps expanded.
9. Escape empty collapses.
10. Forbidden ancestor specimens do not attach suggestions.
11. Reduced-motion disables transform animation.
```

Screenshot artifacts should stay ignored by `.gitignore`.

---

## §11 — Verdict

Phase 5 measurement verdict:

| Criterion | Status |
|---|---|
| Baseline dimension inventory | PASS |
| M3 measurement coverage | PASS |
| Runtime geometry inventory | PASS |
| WCAG SC citation coverage | PASS |
| Playwright QA plan | PASS |
| Implementation measurement verdict | PASS |

Phase 2 pre-check scope:

```txt
lab-search-bar.css created.
lab-search-bar.js created.
lab-search-bar-pattern.html created.
Rest shell, expansion, suggestions, trailing icon-button composition,
forbidden-ancestor cases, and reduced-motion hooks are now available for
Playwright/user visual QA.
```

Phase 3 measurement findings:

```txt
1. Mobile overflow was found at 390px viewport width. Root cause was the
   lab card context: `width: 100%` plus card padding caused the Search bar
   shell to exceed the available inline size by 83px.

   Resolution was lab-scoped only:
     .lab-search-bar-card .search-bar {
       box-sizing: border-box;
       max-inline-size: 100%;
       min-inline-size: 0;
     }

   Baseline components.css §10 remains untouched.

2. Leading search icon vertical center alignment stayed at delta 0px.

3. Trailing icon-button geometry stayed 48px × 48px and remains the only
   Ripple v2 consumer route inside Search bar.

4. Suggestion item height remained approximately 44px. This is documented
   as a Search suggestion surface measurement, not a field-host target.
```

---

## §12 — Cross-References

```txt
SEARCH-BAR-SPEC-AUDIT.md
SEARCH-BAR-WP-MAPPING.md
SEARCH-BAR-RUNTIME-AUDIT.md
docs/v3.5.8/SEARCH-BAR-PHASE-0-REPORT.md
docs/v3.5.8/SEARCH-BAR-PHASE-1-PLAN.md
../search-expansion/docs/SEARCH-EXPANSION-AUDIT.md
../text-field/docs/TEXT-FIELD-MEASUREMENT-AUDIT.md
../icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
```
