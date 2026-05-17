# Axismundi v3.5.5 — FAB Family Phase 0 Report

> **Status**: Phase 0 inventory and ontology framing.  
> **Date**: 2026-05-16  
> **Component family**: FAB #3 + Extended FAB #4  
> **Category**: Component Full-Spec  
> **Scope**: Documentation-only. No baseline, public, module, state, release, or handoff edits.

---

## §0 — Phase 0 Framing

FAB family is the fourth Wave 1 Component Full-Spec cycle after:

```txt
v3.5.1 Button #1       DONE
v3.5.2 Icon button #2  DONE
v3.5.3 Card #9         DONE
v3.5.4 Matrix amendment DONE
v3.5.5 FAB family      ENTERING
```

This is the first Wave 1 cycle to exercise a **family-merge** row in the canonical matrix:

```txt
FAB #3           = canonical TOC row
Extended FAB #4  = canonical TOC row
Target module    = fab/
Audit shape      = one FAB-family audit trio
```

The Phase 0 task is not to implement FAB. It is to decide how this merged family should enter the v3.5.x audit pipeline without fragmenting the module model.

Central distinctions:

```txt
FAB primitive:
  Icon-only action surface. Native button semantics. Icon-system dependency
  is unconditional.

Extended FAB primitive:
  Leading icon + text label action surface. Same family, wider shape.
  Static primitive only in v3.5.5.

FAB menu:
  Separate interaction component (#5). Out of scope.

Toolbar floating-with-FAB:
  Separate composition/behavior concern. Out of scope.
```

---

## §1 — Authoritative Inputs

Framework:

```txt
CONSTITUTION.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Phase plan:

```txt
docs/v3.5.5/FAB-PHASE-0-PLAN.md
```

Baseline/public:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
  §0 state-layer foundation
  §15 FAB
  §16 Extended FAB

products/reference-implementations/axismundi-lab/style-guide.html
  #components-fab
  #components-fab-extended
  #components-fab-menu only as boundary evidence

products/reference-implementations/axismundi-lab/stylesheets/tokens.css
  elevation / shape / type / icon-size token sources
```

Closed Wave 1 precedents:

```txt
Button v3.5.1:
  Action surface, variant family handled inside one audit trio.

Icon button v3.5.2:
  icon-system/ CURRENT unconditional pattern.

Card v3.5.3:
  composition boundary discipline and behavior-pattern deferral.

Chip v3.4.9:
  original 3-doc Component Full-Spec template.
```

Framework classification:

```txt
TOC rows:       #3 FAB, #4 Extended FAB
TOC group:      Actions
CSS sections:   components.css §15, §16
Public anchors: #components-fab, #components-fab-extended
Category:       Component Full-Spec
Current status: TODO
Target module:  fab/
Dependency:     Consumer
Provider deps:  ripple/, icon-system/
Wave priority:  1
```

---

## §2 — Baseline Inventory

### §2.1 Shared Chunk Framing

`components.css` groups FAB and Extended FAB together:

```txt
Chunk E1 — FAB + Extended FAB
§15 FAB           — M3-COMPONENT-SPECS §7
§16 Extended FAB  — M3-COMPONENT-SPECS §8
```

The baseline comment states both are bucket D:

```txt
template-level placement, but the component itself is just an inline-block
element; page-level positioning is the author's job.
```

Phase 1 must preserve this split:

```txt
Component primitive:
  inline-flex action surface

Page/application placement:
  fixed/sticky/floating layout, bottom-end placement, toolbar choreography
```

### §2.2 FAB §15

Observed baseline selectors and behavior:

```txt
.ax-fab
.ax-fab::before
.ax-fab:hover::before
.ax-fab:focus-visible::before
.ax-fab:active::before
.ax-fab:hover
.ax-fab:active
.ax-fab:focus-visible
.ax-fab > svg
.ax-fab > .ax-icon
.ax-fab.is-medium
.ax-fab.is-large
.ax-fab.is-tonal-secondary
.ax-fab.is-tonal-tertiary
.ax-fab.is-primary
.ax-fab.is-secondary
.ax-fab.is-tertiary
.ax-fab:disabled
.ax-fab[aria-disabled="true"]
```

Key measurements/tokens from baseline:

```txt
Default size:  56px
Medium size:   80px
Large size:    96px
Default icon:  var(--comp-icon-size-md) / 24px
Medium icon:   28px
Large icon:    36px
Default shape: var(--md-sys-shape-corner-large) / 16px
Medium shape:  var(--md-sys-shape-corner-large-increased) / 20px
Large shape:   var(--md-sys-shape-corner-extra-large) / 28px
Rest elevation:  var(--md-sys-elevation-shadow-level3)
Hover elevation: var(--md-sys-elevation-shadow-level4)
Focus outline: 2px solid secondary, 2px offset
```

Color styles:

```txt
tonal primary (default)
tonal secondary
tonal tertiary
primary
secondary
tertiary
```

State model:

```txt
Pattern A static state-layer:
  ::before overlay
  currentColor
  M3 hover/focus/pressed opacity tokens

Animated ripple:
  not baseline-wired
  remains CANDIDATE
```

Disabled model:

```txt
native disabled and aria-disabled selectors both styled.
container/fill fades through color-mix.
box-shadow drops to level0.
pointer-events none.
```

Phase 1 note:

```txt
FAB is a native-action Pattern A component. It should follow Button/Icon
button disabled split discipline: native disabled specimens and
aria-disabled plugin-managed specimens must be documented separately.
```

### §2.3 Extended FAB §16

Observed baseline structure:

```txt
.ax-fab-extended
.ax-fab-extended::before
.ax-fab-extended:hover::before
.ax-fab-extended:focus-visible::before
.ax-fab-extended:active::before
.ax-fab-extended:hover
.ax-fab-extended:active
.ax-fab-extended:focus-visible
.ax-fab-extended__icon
.ax-fab-extended__label
.ax-fab-extended.is-tonal-secondary
.ax-fab-extended.is-tonal-tertiary
.ax-fab-extended.is-primary
.ax-fab-extended.is-secondary
.ax-fab-extended.is-tertiary
.ax-fab-extended:disabled
.ax-fab-extended[aria-disabled="true"]
```

Key measurements/tokens from baseline:

```txt
Height:       56px
Min width:    80px
Padding:      0 var(--space-md) / 16px L-R
Gap:          var(--space-sm) / 8px
Icon:         var(--comp-icon-size-md) / 24px
Shape:        var(--md-sys-shape-corner-large) / 16px
Typography:   title-medium
Rest elevation:  level3
Hover elevation: level4
```

Important scope fact:

```txt
The baseline explicitly says only "small" size is present for Extended FAB.
Phase 1 should not infer medium/large Extended FAB variants unless the
baseline or M3 comparison justifies a future candidate.
```

---

## §3 — Public Specimen Inventory

### §3.1 FAB Anchor

`style-guide.html #components-fab` currently exposes:

```txt
Sizes:
  .ax-fab
  .ax-fab.is-medium
  .ax-fab.is-large

Color styles:
  .ax-fab
  .ax-fab.is-tonal-secondary
  .ax-fab.is-tonal-tertiary
  .ax-fab.is-primary
  .ax-fab.is-secondary
  .ax-fab.is-tertiary

Disabled:
  .ax-fab[disabled]
  .ax-fab.is-medium[disabled]
```

Specimen count:

```txt
FAB visible button specimens: 11
  sizes: 3
  color styles: 6
  disabled: 2
```

Accessibility:

```txt
FAB specimens use native <button>.
Icon-only FAB specimens include aria-label.
SVG children are aria-hidden="true".
```

Icon implementation:

```txt
Current public specimens use inline SVG.
This is compatible with the baseline selector `.ax-fab > svg`, but it is
not the Material Symbols / icon-system-forward public specimen direction.
```

### §3.2 Extended FAB Anchor

`style-guide.html #components-fab-extended` currently exposes:

```txt
Default:
  two .ax-fab-extended specimens
  English and Korean labels

Color styles:
  .ax-fab-extended
  .ax-fab-extended.is-tonal-secondary
  .ax-fab-extended.is-primary
  .ax-fab-extended.is-tertiary

Disabled:
  one .ax-fab-extended[disabled] specimen
```

Specimen count:

```txt
Extended FAB visible button specimens: 7
  default: 2
  color styles: 4
  disabled: 1
```

Accessibility:

```txt
Extended FAB specimens use native <button>.
Visible label text is present.
SVG icon children are aria-hidden="true".
```

Icon implementation:

```txt
Current public specimens use inline SVG with .ax-fab-extended__icon.
Phase 1 should decide whether the audit target pattern uses Material
Symbols / .ax-icon conventions while recording the current SVG-era
public specimen state.
```

### §3.3 FAB Menu Boundary Evidence

`style-guide.html #components-fab-menu` follows immediately after Extended FAB and uses:

```txt
.ax-fab-menu
.ax-fab-menu__close
.ax-fab-menu__list
.ax-fab-menu__item-button
data-fab-menu-toggle
aria-expanded / aria-controls
```

Phase 0 disposition:

```txt
FAB menu is not part of v3.5.5 FAB family.
It is row #5, Component Full-Spec + Interaction.
It depends on FAB family but owns a separate interaction surface.
```

---

## §4 — Family-Merge Module Structure Decision

Decision:

```txt
Use one `fab/` module and one FAB-family audit trio.
```

Future Phase 1/2 paths:

```txt
products/reference-implementations/axismundi-lab/modules/fab/docs/
  FAB-SPEC-AUDIT.md
  FAB-MEASUREMENT-AUDIT.md
  FAB-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/fab/
  lab-fab.css
  lab-fab-pattern.html
  no lab-fab.js unless a later behavior decision explicitly authorizes it
```

Rejected path:

```txt
Separate FAB-SPEC and EXTENDED-FAB-SPEC docs.
```

Rationale:

```txt
- Matrix row #4 folds into `fab/`.
- Both components share action semantics, elevation, state-layer,
  color roles, icon dependency, and disabled model.
- Extended FAB is a labeled family member, not a separate runtime.
- A single audit trio avoids duplicating WordPress mapping and measurement
  tables across two nearly identical action surfaces.
```

Phase 1 implication:

```txt
Audit docs must still preserve the two public anchors:
  #components-fab
  #components-fab-extended

The family merge does not erase the fact that the public TOC exposes both.
```

---

## §5 — Dependency Profile / Consumer-State

### §5.1 State-Layer Foundation

```txt
components.css §0 state-layer foundation = CURRENT
```

Reason:

```txt
FAB and Extended FAB are native action surfaces and already use Pattern A
static state-layer pseudo-elements with hover/focus/pressed opacity tokens.
```

### §5.2 Icon-System

```txt
icon-system/ = CURRENT unconditional
```

Reason:

```txt
FAB is definitionally an icon-bearing action component.
Extended FAB has a required leading icon slot in the current Axismundi
family model.
```

Nuance:

```txt
Current public specimens still use inline SVG.
This does not make icon-system optional; it creates an audit gap between
the dependency contract and current public snippet style.
```

Phase 1 requirement:

```txt
FAB-SPEC-AUDIT.md must record:
  current selector compatibility with SVG and .ax-icon
  target public specimen direction for icon-system alignment
  whether SVG cleanup becomes a future BACKLOG candidate
```

### §5.3 Ripple

```txt
ripple/ = CANDIDATE
```

Reason:

```txt
v3.5.4 matrix amendment places FAB #3 + Extended FAB #4 in the CANDIDATE
ripple bucket, not TARGET.
```

Disposition:

```txt
Keep CANDIDATE for v3.5.5.
Do not promote FAB to TARGET during this cycle.
Route animated ripple decisions through:
  BACKLOG #25 Ripple v2 contract
  BACKLOG #27 data-ax-ripple opt-in
```

### §5.4 Elevation Tokens

```txt
elevation tokens = CURRENT baseline token graph dependency
```

Reason:

```txt
FAB uses level3 rest elevation and level4 hover elevation.
Elevation is not a separate provider module in the v3.5.0 matrix.
It is part of the M3 token graph.
```

Phase 1 MEASUREMENT requirement:

```txt
Verify level3/level4 usage and document whether focus/active return to
level3 as baseline currently states.
```

---

## §6 — Extended FAB Scope Decision

Decision:

```txt
v3.5.5 handles static Extended FAB only.
```

Out of scope:

```txt
extended-to-collapsed transition
scroll-responsive collapse
label visibility choreography
toolbar/FAB integration
FAB menu expansion
```

Rationale:

```txt
The baseline Extended FAB is static: 56px height, min-width 80px, leading
icon, visible label, title-medium typography, Pattern A state-layer.
Behavior-heavy patterns would require runtime and responsive policy that
belongs in a separate behavior release.
```

Precedent:

```txt
Card #29 routed behavior-heavy card patterns away from the static Card
primitive. Extended FAB collapse/expand should follow the same primitive
first, behavior later discipline.
```

---

## §7 — Risks And Dispositions

| Risk | Finding | Disposition |
|---|---|---|
| R1 Family merge | FAB and Extended FAB are two TOC rows but one target module. | Single `fab/` module and single audit trio. |
| R2 Icon contract gap | Dependency contract is icon-system CURRENT unconditional, but public specimens use inline SVG. | Record honestly in Phase 1; do not edit public surface in Phase 0. Future cleanup candidate if needed. |
| R3 Ripple ambiguity | FAB is action surface but v3.5.4 says CANDIDATE, not TARGET. | Keep CANDIDATE; defer to Ripple v2/data-ax-ripple release. |
| R4 Extended behavior creep | Collapse/expand/scroll behavior could enter accidentally. | Static-only in v3.5.5; behavior release later. |
| R5 WordPress mapping weak fit | No natural `core/*` equivalent. | Phase 1 WP-MAPPING enumerates pattern/custom-block possibilities. |
| R6 Hit target classification | FAB likely meets AA/AAA target-size thresholds, but needs formal measurement. | Phase 1 MEASUREMENT verifies SC 2.5.8 and SC 2.5.5. |
| R7 FAB menu / Toolbar creep | Adjacent public sections can blur scope. | FAB menu #5 and Toolbar #8 explicitly out of scope. |

---

## §8 — Phase 1 Entry Conditions

Phase 1 may begin when this report is approved.

Required Phase 1 deliverables:

```txt
products/reference-implementations/axismundi-lab/modules/fab/docs/
  FAB-SPEC-AUDIT.md
  FAB-MEASUREMENT-AUDIT.md
  FAB-WP-MAPPING.md
```

Phase 1 locks:

```txt
1. Single FAB-family audit trio.
2. Preserve both public anchors in analysis:
     #components-fab
     #components-fab-extended
3. icon-system/ = CURRENT unconditional.
4. ripple/ = CANDIDATE.
5. Extended FAB = static-only.
6. FAB menu and Toolbar integration = out of scope.
7. WordPress mapping = candidate analysis, not implementation.
8. Inline SVG public specimen gap = audit finding, not Phase 1 public edit.
```

Expected Phase 1 special sections:

```txt
SPEC:
  family merge decision
  native button semantics
  icon contract
  Extended FAB static scope
  non-goals

MEASUREMENT:
  56 / 80 / 96 FAB sizes
  Extended FAB 56px / min-width 80px
  SC 2.5.8 AA and SC 2.5.5 AAA target-size classification
  elevation level3/level4

WP-MAPPING:
  no natural core block assumption
  core/button style bridge evaluation
  pattern composition vs custom block/plugin territory
  placement responsibility boundary
```

---

## §9 — G1-G10 Applicability

| Gate | Phase 0 applicability | Notes |
|---|---|---|
| G1 Validator 1.000 | Applicable | Must remain PASS after documentation-only work. |
| G2 Baseline untouched | Applicable | `components.css`, `style-guide.html`, `tokens.css`, `blocks.css` must remain untouched. |
| G3 Publish runs cleanly | Not required in Phase 0 | No publish surface changes. |
| G4 Module artifacts exist | Future | Phase 2 creates `lab-fab.css` and `lab-fab-pattern.html` if approved. |
| G5 CHANGELOG | Future | Phase 5 only. |
| G6 Static Visual QA | Future | Phase 3 after Phase 2 implementation. |
| G7 Principle 1 | Future audit | Native button semantics and visible/accessible action controls. |
| G8 Principle 2 | Future audit | Use `<button type="button">` in lab patterns. |
| G9 WCAG SC accuracy | Future audit | Phase 1 MEASUREMENT must verify target-size claims. |
| G10 3-doc audit pattern | Future audit | Phase 1 creates the trio. |

---

## §10 — Non-Goals

This Phase 0 report does not:

```txt
- create products/reference-implementations/axismundi-lab/modules/fab/
- create lab-fab.css
- create lab-fab-pattern.html
- create lab-fab.js
- edit components.css
- edit style-guide.html
- edit tokens.css
- edit blocks.css
- edit theme.json
- replace inline SVG specimens
- implement icon-system cleanup
- implement Ripple v2
- add data-ax-ripple
- promote ripple/ from CANDIDATE to TARGET
- implement Extended FAB collapse/expand behavior
- implement FAB menu
- implement Toolbar floating-with-FAB behavior
- register WordPress block styles
- edit BACKLOG.md
- edit CURRENT-STATE.md
- edit NEXT-SESSION.md
```

---

## §11 — Verdict

Phase 0 verdict:

```txt
PASS — FAB family is ready for Phase 1 planning.
```

Settled:

```txt
- FAB #3 + Extended FAB #4 use one `fab/` module.
- Phase 1 should create one FAB-family audit trio.
- icon-system/ is CURRENT unconditional.
- ripple/ remains CANDIDATE.
- Extended FAB is static-only in v3.5.5.
- FAB menu #5 and Toolbar #8 integration are out of scope.
- WordPress mapping is weak/native-none and must be formalized in Phase 1.
```

One-line summary:

```txt
v3.5.5 FAB Phase 0 validates the first family-merge Component Full-Spec
entry: one FAB-family module, unconditional icon contract, CANDIDATE ripple,
static Extended FAB, and no behavior/runtime/public-surface changes before
Phase 1 audit planning.
```
