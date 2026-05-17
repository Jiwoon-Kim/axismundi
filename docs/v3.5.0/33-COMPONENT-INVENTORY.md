# 33-Component Ontology Inventory — v3.5.0 Phase 0A + 0B

> **Phase 0A**: Thin inventory of 34 baseline sections with 4-category candidate.
> **Phase 0B**: TOC taxonomy adopted as canonical order; 7 medium-confidence reconciliation items resolved.
>
> Source authority (light scan only — NO CSS body inspection):
> - `style-guide.html` sidebar `<nav>` TOC structure (canonical order, this Phase 0B)
> - `components.css` section TOC (`§1-§34`)
> - `style-guide.html` `#components-*` anchor IDs
> - `lab/modules/*` directory listing

## §1 — Canonical order = TOC taxonomy (Phase 0B decision)

The `components.css` section ordering (§1-§34) is an implementation artifact — sections were added in accumulation order over the build history. The `style-guide.html` sidebar `<nav>` groups components by their **public-surface taxonomy**, which is what users actually navigate:

```
Public-surface canonical order:
  Foundation (non-components: Color, Typography)
  Actions       (8 components)
  Containers    (2 components)
  Navigation    (5 components + 1 variant anchor)
  Inputs        (8 components)
  Selection     (2 components)
  Feedback      (6 components)
  Display       (3 components)
```

For all v3.5.0 documents and matrix work, **canonical component order = TOC group order**, with `components.css §N` and style-guide anchors as secondary references only.

### Why TOC and not CSS §

```
components.css §  =  implementation accumulation order
                     (when the section was authored, what bucket the
                      author was in at the time, build-history scars)

TOC nav order      =  public-facing ontology
                     (Foundation / Actions / Containers / Navigation /
                      Inputs / Selection / Feedback / Display)
                     ≈ users navigate by function, not by spec §
```

## §2 — 4-category framework (confirmed for v3.5.0)

```
1. Component Full-Spec Module    — like chip (v3.4.9)
   baseline primitive exists + 3-doc audit (SPEC + MEASUREMENT + WP-MAPPING)
   typical for: visually complex variants, measurement-sensitive specs

2. Interaction Runtime Module    — like snackbar (v3.4.10), popover, tooltip, date-time
   baseline visual chrome exists + runtime layer module added
   typical for: behavior-driven surfaces (queue, focus, anchored, transient)

3. Baseline-only Module Record   — NEW v3.5.0 category (used)
   baseline is sufficient; no separate lab/modules/<name>/ implementation
   audit-only record kept (1-2 page minimal audit doc)

4. Plugin-territory Mapping      — NEW v3.5.0 category (declared, 0 cases inside baseline)
   not theme/module territory; lives in plugin layer
   only mapping documentation kept under bindings/ or plugin proposals
```

**Note**: TOC Group and Category are **orthogonal axes**. A `Containers` TOC item can be a `Baseline-only Record` (Divider) or a `Component Full-Spec Module` (Card). A `Selection` TOC item can be either too (Badge / Chip). Both classifications matter and are recorded independently.

## §3 — Component count reconciliation

```
TOC anchors (sidebar nav)                        35
  └── minus 1 variant anchor (Nav rail expanded)  -1
TOC distinct components                          34

components.css §1-§34                            34
  (§0 State-layer foundation is cross-cutting, NOT a component)

M3 spec components (Phase 1B "33/33")            33

  Difference TOC 34 vs M3 33 = 1.
  Likely source: one M3 family is split into two baseline sections
  (resolved in Phase 0B §7 reconciliation).
```

### Hard rule for v3.5.0 (Phase 0B)

```
CSS section count ≠ Component count

components.css sections = implementation sections
TOC components          = ontology / public-surface components

Use TOC for canonical component count.
Use components.css § as implementation reference only.
```

## §4 — The inventory matrix (canonical TOC order)

Legend:
- **TOC Group**: Foundation / Actions / Containers / Navigation / Inputs / Selection / Feedback / Display
- **CSS §**: `components.css` section number (implementation reference)
- **SG**: `style-guide.html` `#components-*` anchor
- **Module**: existing `lab/modules/<name>/` directory; — if none
- **Status**: DONE / partial / —
- **Category**: candidate from §2 framework
- **Conf**: high / medium / low
- **Wave**: 1 / 2 / 3 / record-only

### Actions (8)

| # | Component | TOC Group | CSS § | SG anchor | Module | Status | Category | Conf | Wave | Notes |
|---:|---|---|---:|---|---|:---:|---|:---:|:---:|---|
| 1 | **Button** | Actions | §2 | `#components-button` | — | — | Component Full-Spec | high | 1 | Highest-frequency surface; 5 variants in baseline. Wave 1 priority. |
| 2 | **Icon button** | Actions | §3 | `#components-icon-button` | (covered by `icon-system/`) | partial | Component Full-Spec | high | 1 | `icon-system/ICON-BUTTON-RUNTIME-AUDIT.md` exists — partial coverage; full-spec module owed. |
| 3 | **FAB** | Actions | §15 | `#components-fab` | — | — | Component Full-Spec | high | 1 | **Phase 0B: MERGE with #4 Extended FAB into a single FAB family module** (resolves TOC 34 vs M3 33). |
| 4 | **Extended FAB** | Actions | §16 | `#components-fab-extended` | — | — | (folds into FAB family) | high | 1 | Variant of FAB §15. Single Full-Spec module covers both. |
| 5 | **FAB menu** | Actions | §31 | `#components-fab-menu` | — | — | Component Full-Spec + Interaction | med | 2 | Expandable FAB + menu items; depends on FAB family + Menu module. Wave 2. |
| 6 | **Button group** | Actions | §28 | `#components-button-group` | — | — | Component Full-Spec | high | 1 | Connected button row. Wave 1 (Button family extension). |
| 7 | **Split button** | Actions | §32 | `#components-split-button` | — | — | Component Full-Spec | med | 2 | Primary action + chevron dropdown. Wave 2 (Button family). |
| 8 | **Toolbar** | Actions | §29 | `#components-toolbar` | — | — | Component Full-Spec | med | 2 | Page-level placement; floating-with-FAB deferred (v1.5+). Wave 2. |

### Containers (2)

| # | Component | TOC Group | CSS § | SG anchor | Module | Status | Category | Conf | Wave | Notes |
|---:|---|---|---:|---|---|:---:|---|:---:|:---:|---|
| 9 | **Card** | Containers | §5 | `#components-card` | — | — | Component Full-Spec | high | 1 | Multiple variants (elevated/filled/outlined); content slots; high WP block mapping (`core/group`). Wave 1. |
| 10 | **Divider** | Containers | §4 | `#components-divider` | — | — | **Baseline-only Record** | high | record | Simple visual primitive; maps to `core/separator`. Record-only audit sufficient. First confirmed use of `Baseline-only Record` category. |

### Navigation (5 + 1 variant)

| # | Component | TOC Group | CSS § | SG anchor | Module | Status | Category | Conf | Wave | Notes |
|---:|---|---|---:|---|---|:---:|---|:---:|:---:|---|
| 11 | **App bar** | Navigation | §6 | `#components-app-bar` | — | — | Component Full-Spec | high | 2 | Anchored to viewport; scroll behavior; navigation slots. Wave 2. |
| 12 | **Nav bar** | Navigation | §17 | `#components-nav-bar` | — | — | Component Full-Spec | high | 2 | Mobile bottom-nav. Wave 2. |
| 13 | **Nav rail** | Navigation | §7 | `#components-nav-rail` (+ `-expanded` variant anchor) | — | — | Component Full-Spec | high | 2 | Collapsed + expanded variants (single module covers both). Wave 2. |
| 14 | **Tabs** | Navigation | §8 | `#components-tabs` | — | — | Component Full-Spec + Interaction | med→**high** | 2 | **Phase 0B: BOTH Full-Spec + Interaction Runtime confirmed.** Primary + secondary variants (Full-Spec); indicator animation + arrow-key nav (Interaction). Sibling pattern to Search bar §10. |
| 15 | **Menu** | Navigation | §19 | `#components-menu` | (uses `popover/` as runtime dependency) | partial | Component Full-Spec + Popover runtime dependency | med→**high** | 2 | **Phase 0B: DISTINCT but COUPLED with `popover/`.** Menu owns menu semantics (role=menu, role=menuitem, item density, leading icon, shortcut text, selected/checkmark, disabled item, divider/group, submenu candidate). `popover/` owns anchored-surface runtime (anchor + position + dismiss + outside-click + Escape + focus restore + viewport collision). Menu module reuses Popover runtime rather than reimplementing positioning/dismissal. |

### Inputs (8)

| # | Component | TOC Group | CSS § | SG anchor | Module | Status | Category | Conf | Wave | Notes |
|---:|---|---|---:|---|---|:---:|---|:---:|:---:|---|
| 16 | **Text field** | Inputs | §9 | `#components-text-field` | — | — | Component Full-Spec + Interaction | high | 1 | Filled + outlined; label transition; error/help states. Possibly most complex M3 component. Wave 1 priority. |
| 17 | **Search bar** | Inputs | §10 | `#components-search-bar` | `search-expansion/` (v3.3.4) | partial | Component Full-Spec + Interaction | med→**high** | 1 | **Phase 0B: KEEP DISTINCT from Text field.** Search bar has its own affordances (leading search icon, trailing actions, expanded-state interaction). `search-expansion/` is the interaction; Full-Spec module still owed. |
| 18 | **Checkbox** | Inputs | §22 | `#components-checkbox` | — | — | Component Full-Spec | high | 2 | Form control; indeterminate state; native `<input type="checkbox">`. Wave 2 (form family). |
| 19 | **Radio** | Inputs | §23 | `#components-radio` | — | — | Component Full-Spec | high | 2 | Form control; native input + radiogroup. Wave 2 (form family). |
| 20 | **Switch** | Inputs | §24 | `#components-switch` | — | — | Component Full-Spec | high | 2 | Form control; native checkbox + custom visual. Wave 2 (form family). |
| 21 | **Slider** | Inputs | §25 | `#components-slider` | — | — | Component Full-Spec | med→**high** | 3 | **Phase 0B: NO separate Interaction module.** Native `<input type="range">` + custom track/thumb CSS handles it. Keyboard arrows are native input behavior. Wave 3. |
| 22 | **Date picker** | Inputs | §33 | `#components-date-picker` | `date-time/` (v3.4.7) | partial (interaction only) | Component Full-Spec + Interaction | med→**high** | 2 | **Phase 0B: MERGE with #23 Time picker — `date-time/` already covers both.** Single Full-Spec module covers both pickers (matches existing module structure). WAI-ARIA grid in BACKLOG #19. |
| 23 | **Time picker** | Inputs | §34 | `#components-time-picker` | `date-time/` (v3.4.7) | partial (interaction only) | (folds into Date+Time family) | high | 2 | Folds into Date+Time Full-Spec module (#22). |

### Selection (2)

| # | Component | TOC Group | CSS § | SG anchor | Module | Status | Category | Conf | Wave | Notes |
|---:|---|---|---:|---|---|:---:|---|:---:|:---:|---|
| 24 | **Chip** | Selection | §11 | `#components-chip` | `chip/` (v3.4.9) | **DONE** | Component Full-Spec | — | done | v3.4.9 DONE. First Component Full-Spec module. Elevated variants → BACKLOG #23. |
| 25 | **Badge** | Selection | §18 | `#components-badge` | — | — | **Baseline-only Record** | high | record | Small visual primitive; numeric/dot variants; typically attaches to other components. Second confirmed use of `Baseline-only Record`. |

### Feedback (6)

| # | Component | TOC Group | CSS § | SG anchor | Module | Status | Category | Conf | Wave | Notes |
|---:|---|---|---:|---|---|:---:|---|:---:|:---:|---|
| 26 | **Dialog** | Feedback | §12 | `#components-dialog` | — | — | Interaction Runtime | high | 2 | Basic + full-screen; focus trap; backdrop click; ESC dismiss. Pure interaction module. Wave 2. |
| 27 | **Sheet** | Feedback | §13 | `#components-sheet` | — | — | Interaction Runtime | high | 2 | Bottom-modal + side-modal; drag-to-dismiss; backdrop. Often paired with Dialog. Wave 2. |
| 28 | **Snackbar** | Feedback | §14 | `#components-snackbar` | `snackbar/` (v3.4.10) | **DONE** | Interaction Runtime | — | done | v3.4.10 DONE. Naming inconsistency carried to v3.5.0 sweep (BACKLOG #18). |
| 29 | **Tooltip** | Feedback | §21 | `#components-tooltip` | `tooltip/` (v3.4.6) | **DONE** | Interaction Runtime | — | done | v3.4.6 DONE. Touch long-press + rich tooltip in BACKLOG #16. |
| 30 | **Loading** | Feedback | §20 | `#components-loading` | — | — | Component Full-Spec | high | 3 | Spinner family. Lower complexity. Wave 3. |
| 31 | **Progress** | Feedback | §27 | `#components-progress` | — | — | Component Full-Spec | high | 3 | Linear + circular; determinate + indeterminate. Wavy variant deferred (v1.5+). Wave 3. |

### Display (3)

| # | Component | TOC Group | CSS § | SG anchor | Module | Status | Category | Conf | Wave | Notes |
|---:|---|---|---:|---|---|:---:|---|:---:|:---:|---|
| 32 | **Avatar** | Display | §1 | `#components-avatar` | — | — | **Baseline-only Record** | med→**high** | record | **Phase 0B: KEEP STANDALONE as Baseline-only Record.** Despite M3 §19.7 listing avatar as a List leading-slot, the baseline + style-guide promote it to standalone TOC entry, so v3.5.0 inventory respects that. Avatar is used outside List too (chat, profile). Third confirmed use of `Baseline-only Record`. |
| 33 | **List** | Display | §26 | `#components-list` | — | — | Component Full-Spec | high | 1 | High-frequency surface; sub-types (1/2/3 line, leading slots use Avatar). Wave 1. |
| 34 | **Carousel** | Display | §30 | `#components-carousel` | `carousel/` (v3.3.2) | partial (interaction only) | Component Full-Spec + Interaction | med→**high** | 1 | `carousel/` is the interaction extracted v3.3.2; Full-Spec module still owed. Wave 1. |

## §5 — Foundation TOC group (non-components)

| TOC anchor | Surface | Bucket |
|---|---|---|
| `#color` | Color tokens + palette | Foundation (cross-cutting) |
| `#typography` | Typescale tokens | Foundation (cross-cutting) |

These are NOT counted in the 33/34 components matrix. They sit under the Foundation TOC group as cross-cutting design-system primitives.

## §6 — Cross-cutting modules (NOT in TOC component groups)

These lab modules are cross-cutting interaction or foundation primitives — they are NOT tied to one TOC component group:

| Module | Bucket | Scope | Component coverage |
|---|---|---|---|
| `icon-system/` | foundation | Material Symbols + SVG track policy | Powers Icon button #2; used by chip, button, etc. |
| `ripple/` | foundation (state-layer family) | state-layer Pattern A primitives | Cross-cutting; used by chip, button, action chip, etc. |
| `popover/` | foundation (anchored-surface family) | anchor + position + dismiss + outside-click + Escape + focus restore + viewport collision | **Multi-consumer infrastructure.** Used (or to be used) by: Menu #15, Split button #7, FAB menu #5, Date+Time picker #22+#23 (calendar/clock popover surface), and any future Select component. Popover owns the surface behavior; consumers own their own semantic structure. |

These three remain as cross-cutting infrastructure; they do not appear as TOC components.

## §7 — Phase 0B reconciliation decisions (the 7 items)

The 7 medium-confidence items from Phase 0A are resolved as follows:

| # | Reconciliation question | Phase 0B decision | Effect on TOC canonical count |
|---:|---|---|---|
| 1 | Avatar #32 — standalone vs fold into List #33? | **STANDALONE** under Display TOC group. Avatar has uses outside List (chat, profile, comment thread). Mark as `Baseline-only Record`. | Avatar stays as separate row. Count unaffected. |
| 2 | FAB #3 ↔ Extended FAB #4 — merge? | **MERGE** into single FAB-family Full-Spec module. Extended FAB is a variant. | Resolves TOC 34 vs M3 33 — Extended FAB folds into FAB family at module level, but stays as separate TOC anchor (users navigate to it). |
| 3 | Date picker #22 ↔ Time picker #23 — merge? | **MERGE** into single Date+Time Full-Spec module. `lab/modules/date-time/` already structures it this way. | TOC keeps both anchors; module level merges them. Consistent with existing pattern. |
| 4 | Menu #15 ↔ `popover/` — overlap? | **DISTINCT but COUPLED.** Popover owns anchored-surface runtime (anchor, position, dismiss, outside-click, Escape, focus restore, viewport collision). Menu owns menu semantics (role=menu, role=menuitem, item density, leading icon, shortcut text, selected/checkmark, disabled, divider/group, submenu). Menu module declares Popover as a runtime dependency rather than reimplementing positioning/dismissal. Same coupling pattern applies to Split button, FAB menu, and Date+Time picker — see §11 module dependency graph. | No count change. Both stay; explicit dependency recorded. |
| 5 | Tabs #14 — Full-Spec only, or also Interaction? | **BOTH.** Tabs needs Full-Spec (primary/secondary variants) AND Interaction Runtime (indicator animation + keyboard arrow nav). Same dual pattern as Search bar #17. | No count change. Category = "Component Full-Spec + Interaction". |
| 6 | Slider #21 — Interaction Runtime needed? | **NO — Full-Spec only.** Native `<input type="range">` + custom CSS handles track/thumb; keyboard arrow-step is native input behavior. No separate Interaction module. | No count change. Removes Slider from Interaction list. |
| 7 | Search bar #17 ↔ Text field #16 — share family? | **DISTINCT components, distinct modules.** Search bar has search-specific affordances (leading icon, expanded-state, suggestions). `search-expansion/` is the interaction; both Full-Spec + Interaction at module level. | No count change. Both stay separate. |

### Net effect on counts

```
After Phase 0B reconciliation:
  TOC canonical components            34 anchors
  M3 spec parity                       33 (Extended FAB folds into FAB family)
  Lab module structure (target)        17 distinct modules:
    - 13 Component Full-Spec modules
    - 4 Interaction Runtime modules (or 4 dual-category modules)
    - Avatar / Divider / Badge are Baseline-only Records (3 light docs, no /modules/)
    - FAB family (covers FAB + Extended FAB)
    - Date+Time family (covers Date picker + Time picker)
```

## §8 — Summary by category (Phase 0B post-reconciliation)

| Category | Count | Members |
|---|---:|---|
| **Component Full-Spec Module** | ~13 modules | Button, Icon button, Card, Text field, Search bar*, FAB family (+Extended), List, Button group, Carousel*, Tabs*, Menu*, Date+Time*, App bar, Nav rail, Nav bar, Loading, Progress, Slider, Checkbox, Radio, Switch, Toolbar, FAB menu*, Split button |
| **Interaction Runtime Module** | 4 pure + N shared | Dialog, Sheet, Snackbar ✓, Tooltip ✓ (pure); Tabs, Menu, Search bar, Carousel, Date+Time, FAB menu (shared with Full-Spec) |
| **Baseline-only Module Record** | **3** | Avatar, Divider, Badge |
| **Plugin-territory Mapping** | 0 (within baseline) | (none at component level; plugin items live in `bindings/`) |
| **DONE (v3.4.x)** | 3 full + 2 partial | Chip ✓, Snackbar ✓, Tooltip ✓ (full); Carousel (interaction only), Date-Time (interaction only) |

*Asterisk: component appears in multiple rows because it spans two categories (typically Component Full-Spec + Interaction Runtime).

## §9 — Wave grouping (Phase 0B confirmed)

```
Wave 1 — Core component modules (9 modules, high-frequency)
  1. Button                  — Actions
  2. Icon button             — Actions  (folds in icon-system/)
  3. Card                    — Containers
  4. Text field              — Inputs
  5. Search bar              — Inputs  (folds in search-expansion/)
  6. FAB family              — Actions  (covers FAB + Extended FAB)
  7. List                    — Display  (Avatar referenced as leading slot)
  8. Button group            — Actions
  9. Carousel                — Display  (folds in carousel/)

Wave 2 — Structural + form + transient (12 modules)
  10. App bar                — Navigation
  11. Nav rail               — Navigation
  12. Nav bar                — Navigation
  13. Tabs                   — Navigation
  14. Menu                   — Navigation  (depends on popover/)
  15. Dialog                 — Feedback
  16. Sheet                  — Feedback
  17. Checkbox               — Inputs  (form family)
  18. Radio                  — Inputs  (form family)
  19. Switch                 — Inputs  (form family)
  20. Toolbar                — Actions
  21. FAB menu               — Actions
  22. Split button           — Actions
  23. Date+Time family       — Inputs  (folds in date-time/)

Wave 3 — Lower-frequency / visualization (3 modules)
  24. Loading                — Feedback
  25. Slider                 — Inputs
  26. Progress               — Feedback

Baseline-only Record (3 records, no /modules/)
  - Avatar                   — Display
  - Divider                  — Containers
  - Badge                    — Selection

DONE
  - Chip                     ✓ v3.4.9   (Selection)
  - Tooltip                  ✓ v3.4.6   (Feedback)
  - Snackbar                 ✓ v3.4.10  (Feedback)
```

Total: 9 + 12 + 3 + 3 records + 3 done = 30 module / record / done items covering 34 TOC anchors (FAB+Extended share one module; Date+Time share one module).

## §10 — What this inventory does NOT do

```
NOT in Phase 0A/0B:
- Deep CSS body inspection per component
- Line-range measurement per component
- Rule-block counting per component (Phase 0 correction lesson from snackbar v3.4.10)
- M3 spec deviation analysis per component
- WordPress block mapping detail per component
- audit-doc skeleton generation per component
- module implementation
- naming sweep execution (.snackbar → .ax-snackbar)
- data-theme="auto" 3-state design

All of the above happens in:
- Phase 1 (MODULE-STATUS-MATRIX.md + COMPONENT-COVERAGE-MAP.md +
  PROMOTION-CRITERIA.md + PUBLIC-SURFACE-CHARTER.md skeleton)
- Phase 2 (formal documents + naming sweep + data-theme model)
- v3.5.1+ (Wave N module implementations, per audit per component)
```

## §11 — Module dependency graph (informal)

Phase 0B surfaced an ontology pattern that needs explicit recording: certain lab modules are **infrastructure modules**, and several component modules **depend on** them rather than reimplementing the same behavior. The clearest case is `popover/` as the anchored-surface infrastructure, with multiple consumers across TOC groups.

### DISTINCT but COUPLED — the core relationship

```
Module type A — Infrastructure (cross-cutting, multi-consumer)
  popover/        anchored-surface runtime
  ripple/         state-layer Pattern A primitive
  icon-system/    Material Symbols + SVG track + icon button base

Module type B — Component (TOC-anchored)
  Menu, Split button, FAB menu, Date+Time picker — depend on popover/
  Button, Chip, Icon button — depend on ripple/ via has-state-layer
  Icon button — depend on icon-system/

Coupling rule:
  Component modules MAY declare a runtime dependency on an
  infrastructure module. They MUST NOT reimplement that
  infrastructure's behavior.
```

This is the same posture as "Menu uses Popover runtime; Popover does not equal Menu." Distinct ontology, coupled implementation.

### Anchored-surface consumers (`popover/` dependents)

```
Popover (anchored-surface infrastructure)
  ├── owns: anchor + position + dismiss + outside-click + Escape +
  │         focus restore + viewport collision
  └── consumers:
        - Menu                   (semantic list of items)
        - Split button           (button + chevron anchored menu)
        - FAB menu               (FAB + expanded menu items)
        - Date+Time picker       (calendar/clock anchored surface)
        - (future) Select        (form input + anchored option list)
```

Each consumer brings its own semantic structure and reuses popover's positioning/dismissal behavior. Popover stays generic — it does not know whether the surface inside is a menu, a calendar, or a select listbox.

### Boundary discipline

```
DO:
  Menu module reuses popover/ runtime via dependency declaration
  Split button module reuses popover/ runtime
  FAB menu module reuses popover/ runtime
  Date+Time picker reuses popover/ for the picker surface

DO NOT:
  Absorb Menu semantics into popover/ (popover serves >1 consumer)
  Hardcode menu-only behavior into popover/ (would leak to other consumers)
  Reimplement popover's positioning logic inside Menu (duplicates infrastructure)
```

This dependency graph is **informal** at Phase 0B — Phase 1 `MODULE-STATUS-MATRIX.md` will record it formally as a column or sub-table. The point of recording it here is to lock the "DISTINCT but COUPLED" framing before Phase 1.

### Other infrastructure ↔ consumer relationships (preliminary)

```
ripple/ consumers (Pattern A state-layer):
  Button, Icon button, Chip, Card (hover), List item (hover), etc.

icon-system/ consumers:
  Icon button (primary), Chip (leading/trailing icon), Button (icon variant),
  FAB (icon contents), etc.
```

These are recorded for visibility; Phase 1 will validate the exact consumer list per infrastructure module.

## §12 — One-line summary

```
v3.5.0 Phase 0A+0B locks the ontology in TOC taxonomy order:
34 baseline sections map to 34 TOC anchors covered by 17 target
modules (13 Full-Spec + 4 pure Interaction Runtime + N shared)
plus 3 Baseline-only Records (Avatar / Divider / Badge), with
FAB+Extended FAB and Date+Time picker merged at the module level
to align with the M3 spec's 33-component count, and an explicit
infrastructure-vs-consumer dependency graph (popover/, ripple/,
icon-system/ as multi-consumer infrastructure modules) recording
the DISTINCT but COUPLED relationship Menu has with popover/ —
the same coupling pattern applying to Split button, FAB menu,
and Date+Time picker.
```
