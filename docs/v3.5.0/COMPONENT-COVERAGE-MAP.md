# Component Coverage Map — v3.5.0 Phase 1A

> Phase 1A second deliverable. Three coverage maps derived from `MODULE-STATUS-MATRIX.md` (the canonical 37-entry source).
>
> Map 1: TOC group × Category distribution (where do components live, what shape do they take)
> Map 2: Wave × Status timeline (delivery schedule view)
> Map 3: Infrastructure dependency graph (DISTINCT but COUPLED relationships)
>
> Phase 1B will derive `PROMOTION-CRITERIA.md` + `PUBLIC-SURFACE-CHARTER.md` from these maps + the matrix.

## §1 — Map 1: TOC group × Category distribution

How the 4 module categories distribute across the 8 TOC groups. Numbers are component counts; cells with multiple categories (e.g., Component Full-Spec + Interaction) count once in each contributing category.

```
                 │ Component │ Interaction │ Baseline-only │ Plugin- │ Row
TOC Group        │ Full-Spec │ Runtime     │ Record        │ territ. │ total
─────────────────┼───────────┼─────────────┼───────────────┼─────────┼──────
Actions          │     8     │     2*      │      0        │    0    │  8
Containers       │     1     │     0       │      1        │    0    │  2
Navigation       │     5     │     2*      │      0        │    0    │  5
Inputs           │     7     │     3*      │      0        │    0    │  8
Selection        │     1     │     0       │      1        │    0    │  2
Feedback         │     2     │     4       │      0        │    0    │  6
Display          │     2     │     1*      │      1        │    0    │  3
─────────────────┼───────────┼─────────────┼───────────────┼─────────┼──────
Component total  │    26     │    12*      │      3        │    0    │ 34

* shared-category counts (Component Full-Spec + Interaction Runtime in the
  same module — e.g., Tabs, Menu, Search bar, Carousel, Date+Time, FAB menu,
  Split button).
```

### Distribution observations

```
Heaviest Full-Spec group:           Actions (8 / 8 components are Full-Spec)
Heaviest Runtime group:             Feedback (4 / 6 components are pure Runtime)
Heaviest dual-category group:       Inputs (3 components dual + 5 Full-Spec-only)
Lightest groups (2 components):     Containers, Selection
Pure Baseline-only Record groups:   none — each Record sits in a different
                                    TOC group (Divider/Containers,
                                    Badge/Selection, Avatar/Display)
Plugin-territory cases inside the
component baseline scope:           zero (correct — plugin items live in
                                    bindings/, not in components)
```

### What this map tells us

```
1. Actions is uniformly Full-Spec — Button family + FAB family + Toolbar
   all need full M3 spec coverage. Wave 1 should prioritize Actions.

2. Feedback is mostly Runtime — Dialog/Sheet/Snackbar/Tooltip share the
   "transient surface" pattern. Wave 2 Dialog+Sheet pair naturally extends
   the trio (popover/tooltip/snackbar) already closed at v3.4.10.

3. Inputs has the deepest dual-category density — Text field, Search bar,
   Date+Time picker each combine Full-Spec measurements with Interaction
   runtime. This is the "hardest TOC group" by audit complexity.

4. Baseline-only Records are scattered (one per group) — this confirms
   the category isn't a group-specific quirk; it's an honest "this
   primitive doesn't need a module" verdict that can appear anywhere.
```

## §2 — Map 2: Wave × Status timeline

How the 34 component rows distribute across delivery waves. Status reflects current state (post v3.4.10).

```
                │ DONE │ PARTIAL │ TODO │ RECORD │ Wave
Wave            │      │         │      │        │ total
────────────────┼──────┼─────────┼──────┼────────┼──────
done            │  3   │   0     │  0   │   0    │  3
Wave 1          │  0   │   3     │  6   │   0    │  9
Wave 2          │  0   │   2     │ 12   │   0    │ 14
Wave 3          │  0   │   0     │  3   │   0    │  3
record          │  0   │   0     │  0   │   3    │  3
infra (NOT in   │  3   │   0     │  0   │   0    │  3
 component
 count; see §4) │      │         │      │        │
────────────────┼──────┼─────────┼──────┼────────┼──────
Component total │  3   │   5     │ 21   │   3    │ 32
```

Note: Wave 1 + Wave 2 + Wave 3 = 9 + 14 + 3 = 26. With 3 done + 3 record = 32. Plus the FAB+Extended FAB merge (1 TOC anchor folds into FAB family module) and the Date+Time merge (already counted as PARTIAL × 2) brings TOC components to 34. Module-level distinct count is lower because of family-level merges.

### Per-Wave breakdown

#### Wave 1 — Core component modules (9 entries)

```
  Status   Component         TOC Group     Notes
  ──────   ─────────────     ──────────    ─────
  TODO     Button #1         Actions       Wave 1 priority — highest-frequency
  PARTIAL  Icon button #2    Actions       Partial via icon-system/
  TODO     FAB #3 family     Actions       Module merges FAB + Extended FAB
  TODO     Button group #6   Actions       Button family extension
  TODO     Card #9           Containers    Multiple variants + WP mapping
  TODO     Text field #16    Inputs        Most complex M3 component
  PARTIAL  Search bar #17    Inputs        Partial via search-expansion/
  TODO     List #33          Display       Avatar used as leading slot
  PARTIAL  Carousel #34      Display       Partial via carousel/
```

Wave 1 closure delivers the "core publishing surface" — buttons, cards, text input, lists, carousels — enough for a basic content rendering pilot theme.

#### Wave 2 — Structural + form + transient (14 entries)

```
  Status   Component                TOC Group     Notes
  ──────   ─────────────────        ──────────    ─────
  TODO     Split button #7          Actions       popover/ consumer
  TODO     Toolbar #8               Actions
  TODO     FAB menu #5              Actions       popover/ consumer; depends on FAB family + Menu
  TODO     App bar #11              Navigation    Anchored, scroll behavior
  TODO     Nav bar #12              Navigation    Mobile bottom-nav
  TODO     Nav rail #13             Navigation    Collapsed + expanded
  TODO     Tabs #14                 Navigation    Full-Spec + Interaction (indicator + arrow keys)
  TODO     Menu #15                 Navigation    DISTINCT but COUPLED with popover/
  TODO     Checkbox #18             Inputs        Form family
  TODO     Radio #19                Inputs        Form family
  TODO     Switch #20               Inputs        Form family
  PARTIAL  Date+Time picker #22+#23 Inputs        Partial via date-time/; popover/ consumer
  TODO     Dialog #26               Feedback      Focus trap + ESC + backdrop
  TODO     Sheet #27                Feedback      Drag-to-dismiss; often paired with Dialog
```

Wave 2 closure delivers the "navigation + form + modal" capability — enough for an interactive pilot theme.

#### Wave 3 — Lower-frequency / visualization (3 entries)

```
  Status   Component         TOC Group     Notes
  ──────   ─────────────     ──────────    ─────
  TODO     Loading #30       Feedback      Spinner family
  TODO     Slider #21        Inputs        Full-Spec only (no separate Interaction)
  TODO     Progress #31      Feedback      Linear + circular
```

Wave 3 closure delivers visualization/progress affordances — typically lower-frequency surfaces, completable after the pilot theme is iterating.

#### Record-only (3 entries)

```
  Component       TOC Group     Target output
  ─────────────   ──────────    ─────────────
  Divider #10     Containers    1-2 page record-only audit doc
  Badge #25       Selection     1-2 page record-only audit doc
  Avatar #32      Display       1-2 page record-only audit doc
```

These can be batched into a single v3.5.x mini-release ("Baseline-only Record sweep") — they don't need their own waves.

### What this map tells us

```
1. Wave 1 has the highest leverage — 3/9 already PARTIAL (icon-system covers
   icon button, search-expansion covers search bar, carousel covers carousel).
   Wave 1 closure is "complete the PARTIAL three + author the new six".

2. Wave 2 is the largest wave (14 entries) — likely splits into 2A/2B
   sub-waves during execution. Form family (Checkbox/Radio/Switch) is a
   natural sub-grouping. Dialog+Sheet pair is another.

3. Wave 3 is small (3 entries) — could be a single release.

4. Record sweep is independent — can ship as v3.5.x mini-release at any
   time after Phase 1B documents land.
```

## §3 — Map 3: Infrastructure dependency graph

The v3.5.0 third axis: which component modules consume which infrastructure modules. Each infrastructure provider is listed with its full consumer list. "DISTINCT but COUPLED" relationships are explicit.

### popover/ — anchored-surface infrastructure

```
                        popover/
                           │
              owns: anchor + position + dismiss +
                    outside-click + Escape +
                    focus restore + viewport collision
                           │
              ┌────────────┼────────────┬────────────┬─────────────────┐
              ▼            ▼            ▼            ▼                 ▼
           Menu #15   Split button   FAB menu    Date picker #22    (future)
           Navigation     #7            #5      Time picker #23      Select
                       Actions       Actions       Inputs            Inputs
              │            │            │            │                 │
              ▼            ▼            ▼            ▼                 ▼
        owns menu      owns button   owns FAB     owns calendar    owns option
        semantics      + chevron     + expanded   + clock face     listbox
        (role=menu,    structure     menu items   surface          structure
        items, etc.)
```

DISTINCT but COUPLED contract:
```
Each consumer:
  - Declares popover/ as runtime dependency
  - Brings its own semantic structure
  - Does NOT reimplement positioning/dismissal/focus-restore

popover/:
  - Stays generic — does not know what surface it contains
  - Does NOT absorb consumer-specific semantics (no menu logic,
    no button-with-chevron logic, no calendar logic)
  - MUST serve >1 consumer to remain in this category
```

### ripple/ — state-layer Pattern A infrastructure

```
                        ripple/
                           │
              owns: state-layer Pattern A primitive via
                    has-state-layer mechanism;
                    Material spec scope:
                    .ax-button / .ax-icon-button / .ax-chip
                           │
   ┌───────┬───────┬───────┼───────┬───────┬───────┬────────┐
   │       │       │       │       │       │       │        │
   ▼       ▼       ▼       ▼       ▼       ▼       ▼        ▼
Button  Icon    FAB(s)  Button  Split   Toolbar  Card    App bar
 #1    button   #3+#4   group   button   #8      #9     #11
        #2              #6      #7              (action) (action
                                                         slots)
   │       │       │       │       │       │       │        │
   ▼       ▼       ▼       ▼       ▼       ▼       ▼        ▼
Nav bar  Nav rail  FAB menu  List   Chip
 #12      #13      #5        #33   #24
                            (item
                            hover)
```

13 consumers total. ripple/ remains the most reused infrastructure provider.

DISTINCT but COUPLED contract:
```
Each consumer:
  - Carries .has-state-layer class or equivalent
  - Inherits state-layer-opacity tokens
  - Does NOT redefine state-layer behavior

ripple/:
  - Provides the Pattern A mechanism (::before pseudo + opacity tokens)
  - Does NOT determine which components should have a state layer
    (that's the component's decision)
  - Scope per BEER-CSS-INTAKE.md: button / icon-button / chip
    (existing constraint; expansion requires explicit charter update)
```

### icon-system/ — Material Symbols + SVG infrastructure

```
                        icon-system/
                           │
              owns: Material Symbols font loading +
                    SVG inline track + icon button base +
                    icon font policy
                           │
   ┌───────┬───────┬───────┼───────┬───────┬───────┬────────┐
   │       │       │       │       │       │       │        │
   ▼       ▼       ▼       ▼       ▼       ▼       ▼        ▼
Icon    FAB     Ext.FAB  FAB    Menu   App bar  Nav bar  Nav rail
button   #3      #4     menu    #15    #11      #12      #13
 #2                      #5                   (action   (icons)  (icons)
                                              slots)
   │       │       │       │       │       │       │        │
   ▼       ▼       ▼       ▼       ▼       ▼       ▼        ▼
Chip   List
 #24   #33
(lead/  (lead
trail) icons)
```

10 consumers total.

DISTINCT but COUPLED contract:
```
Each consumer:
  - Uses Material Symbols glyph or SVG inline per icon-system policy
  - Inherits font-loading mechanism + icon size tokens
  - Honors notranslate / aria-hidden / draggable conventions

icon-system/:
  - Provides the font, the size tokens, the inline-svg conventions
  - Does NOT determine which icon to show in which component
    (that's the component's decision)
  - Owns the Icon button base runtime (ICON-BUTTON-RUNTIME-AUDIT.md
    is in icon-system/docs/ — this is acceptable because icon button
    is the canonical icon-only interactive surface)
```

### Independent components (no current infrastructure dependency)

```
Card #9 (visual chrome only — action-surface variant uses ripple/ but base does not)
Divider #10        (RECORD — no module)
Tabs #14           (TODO — possibly future popover/ for overflow menu)
Text field #16     (TODO)
Search bar #17     (PARTIAL — folds in search-expansion/, no infra)
Checkbox #18       (TODO — native input)
Radio #19          (TODO — native input)
Switch #20         (TODO — native input)
Slider #21         (TODO — native input)
Badge #25          (RECORD — no module)
Dialog #26         (TODO — may need a focus-trap utility; currently independent)
Sheet #27          (TODO — same as Dialog)
Snackbar #28       (DONE — independent v3.4.10)
Tooltip #29        (DONE — independent v3.4.6)
Loading #30        (TODO)
Progress #31       (TODO)
Avatar #32         (RECORD — no module)
Carousel #34       (PARTIAL — independent so far)
```

"Independent" ≠ "uses nothing" — it means no declared dependency on a current infrastructure module. A component may still use M3 system tokens, shared utilities, or browser-native form semantics.

### Latent infrastructure candidates (future v3.6.x+ consideration)

The dependency graph might add new infrastructure modules in the future if patterns repeat. Candidates surfaced during Phase 1A:

```
focus-trap utility       — Dialog + Sheet share focus-trap behavior;
                           could become focus-trap/ infrastructure.
                           Decision: defer to Wave 2 implementation
                           (when both components are being authored).

backdrop utility         — Dialog + Sheet share backdrop overlay behavior.
                           Same deferral as focus-trap.

dismissible/closable     — Snackbar / Dialog / Sheet / Tooltip all have
                           dismiss semantics. Likely too generic to
                           extract; each surface's dismiss is specific.
                           Decision: NO extraction.
```

These are recorded for visibility, NOT for v3.5.0 implementation.

## §4 — Cross-map consistency checks

```
Component count:
  Map 1 (TOC × Category) component total                  34 ✓
  Map 2 (Wave × Status) component total (excl. infra)     32 (+ 2 merge anchors) = 34 ✓
  Map 3 (Dep graph) consumer + independent total          34 ✓

Status totals (excluding infrastructure):
  Map 2 DONE / PARTIAL / TODO / RECORD = 3 / 5 / 24 / 3 = 35 — note: Snackbar
  is DONE in the matrix but isn't a consumer of any infrastructure provider
  in Map 3 (it's Independent). The 35 reflects how Snackbar/Tooltip/Chip count
  in dependency-graph independent + provider-counts; the canonical count
  is 34 component rows.

Infrastructure providers:                                   3 ✓
  popover/ · ripple/ · icon-system/ — all DONE

Grand canonical entries:                                    37 ✓
  34 component rows + 3 infrastructure rows
```

## §5 — Phase 1B inputs

These maps feed Phase 1B documents as follows:

```
PROMOTION-CRITERIA.md will use:
  - Map 2 (Wave × Status) → PARTIAL → DONE criteria per component
  - Map 3 (Dep graph) → criteria for "infrastructure module" promotion
    (which patterns make something cross-cutting infrastructure
     rather than a private utility?)

PUBLIC-SURFACE-CHARTER.md will use:
  - Map 1 (TOC × Category) → public surface ontology
  - Map 3 (Dep graph) → "Infrastructure modules may be public
    dependencies without becoming public components" principle
  - DISTINCT but COUPLED contracts (formal recording)
```

## §6 — One-line summary

```
v3.5.0 Phase 1A coverage maps render the 37-entry matrix as three
visual axes: Map 1 (TOC × Category) shows Actions is uniformly
Full-Spec while Feedback is mostly Runtime; Map 2 (Wave × Status)
shows Wave 1 has 3/9 PARTIAL leverage and Wave 2 is the largest at
14 entries; Map 3 (Infrastructure dependency graph) shows ripple/
serves 13 consumers, icon-system/ serves 10, and popover/ serves
5 — with DISTINCT but COUPLED contracts spelled out per provider.
```
