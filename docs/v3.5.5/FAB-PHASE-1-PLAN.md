# Axismundi v3.5.5 — FAB Family Phase 1 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 1 execution.  
> **Date**: 2026-05-16  
> **Preceded by**: `docs/v3.5.5/FAB-PHASE-0-REPORT.md`  
> **Scope**: Plan the Phase 1 audit-doc authoring pass for FAB #3 + Extended FAB #4.  
> **Non-scope**: No audit docs are created by this plan; no FAB module files, baseline files, state files, release files, or handoff files are edited.

---

## §0 — Goal

Phase 1 will convert the Phase 0 FAB family findings into the standard Component Full-Spec audit body.

The planned deliverables after approval are:

```txt
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-WP-MAPPING.md
```

Phase 1 is documentation authoring only. It does not create:

```txt
lab-fab.css
lab-fab-pattern.html
lab-fab.js
```

---

## §1 — Phase 1 Lock Decisions

This plan locks the FAB-specific decisions that should not be rediscovered during audit authoring.

### §1.1 — Family-Merge Audit Trio Shape

Decision:

```txt
Create one FAB-family audit trio.
```

The docs are named after the module family:

```txt
FAB-SPEC-AUDIT.md
FAB-MEASUREMENT-AUDIT.md
FAB-WP-MAPPING.md
```

They cover both public anchors:

```txt
#components-fab
#components-fab-extended
```

Execution implication:

```txt
Create modules/fab/docs/ only after this plan is approved.
Do not create separate extended-fab docs.
Do not create modules/extended-fab/.
```

Reason:

```txt
FAB #3 and Extended FAB #4 are separate TOC rows but one matrix module:
  row #3 target module: fab/
  row #4 target module: folds into fab/
```

### §1.2 — Variant / Family Matrix Scope

Decision:

```txt
Phase 1 documents the current baseline family:
  FAB:          default 56px, medium 80px, large 96px
  Extended FAB: static 56px height / 80px min-width
```

Surface/color scope:

```txt
tonal primary default
tonal secondary
tonal tertiary
primary
secondary
tertiary
```

Do not invent:

```txt
extra Extended FAB sizes
lowered / flat FAB variants
animated collapse states
FAB menu variants
toolbar-attached variants
fixed/sticky placement utilities
```

Phase 1 should record M3 differences honestly, especially the naming mismatch between current baseline comments and Material naming if found:

```txt
baseline "default" = 56px
baseline ".is-medium" = 80px
baseline ".is-large" = 96px
```

### §1.3 — Disabled Split

Decision:

```txt
FAB family uses Pattern A control-level disabled treatment.
```

Phase 1 must split disabled documentation into two cases:

```txt
§5  Disabled — native disabled
    <button class="ax-fab" type="button" disabled>
    <button class="ax-fab-extended" type="button" disabled>

§5a Disabled — aria-disabled plugin-managed
    <button class="ax-fab" type="button" aria-disabled="true">
    <button class="ax-fab-extended" type="button" aria-disabled="true">
```

Native disabled:

```txt
platform blocks activation
removed from normal activation flow
```

aria-disabled:

```txt
communicates disabled state
does not block activation by itself
theme/plugin/editor code must guard events
```

Card's Pattern B three-way split does not apply to FAB. FAB is button-like, not a container card.

### §1.4 — Static Catalog Caption Discipline

Decision:

```txt
All behavior-looking specimens must be captioned as static catalog specimens.
```

Required caption pattern for Extended FAB or hover/active/state examples:

```txt
Static catalog specimen — state values are fixed; no JS transition handler.
Production usage wires actual behavior at the theme, block editor, or plugin
level.

정적 카탈로그 specimen — 상태 값은 고정되어 있으며 JS 전환 핸들러는 없다.
실제 사용 시 theme / block editor / plugin 레벨에서 동작을 배선한다.
```

Reason:

```txt
Extended FAB collapse/expand and scroll behavior are out of v3.5.5 scope.
The pattern page must not make static specimens feel like broken controls.
```

### §1.5 — Native Semantics Decision Tree

Decision:

```txt
FAB and Extended FAB are native button controls.
```

Canonical examples for Phase 1 docs:

```html
<button class="ax-fab" type="button" aria-label="Compose">
  <span class="material-symbols-rounded notranslate ax-fab-icon"
        translate="no"
        aria-hidden="true"
        draggable="false">edit</span>
</button>

<button class="ax-fab-extended" type="button">
  <span class="material-symbols-rounded notranslate ax-fab-extended__icon"
        translate="no"
        aria-hidden="true"
        draggable="false">edit</span>
  <span class="ax-fab-extended__label">Compose</span>
</button>
```

Forbidden:

```txt
<div role="button" class="ax-fab">
<span class="ax-fab">
<a class="ax-fab"> for action behavior
icon-only FAB without accessible name
Extended FAB without visible label
```

Navigation links styled as FAB are not part of the v3.5.5 primitive unless Phase 1 WP-MAPPING explicitly carves a navigation exception.

### §1.6 — Icon Slot Canonical Pattern

Decision:

```txt
icon-system/ = CURRENT unconditional.
```

Phase 1 must use the icon-system-forward specimen pattern:

```html
<span class="material-symbols-rounded notranslate ax-fab-icon"
      translate="no"
      aria-hidden="true"
      draggable="false">add</span>
```

For Extended FAB:

```html
<span class="material-symbols-rounded notranslate ax-fab-extended__icon"
      translate="no"
      aria-hidden="true"
      draggable="false">add</span>
```

Phase 1 must also record the current public gap:

```txt
Current style-guide specimens use inline SVG.
Baseline CSS supports both direct svg children and .ax-icon style hooks.
The target audit pattern should align with icon-system policy.
```

Do not edit `style-guide.html` during Phase 1.

### §1.7 — Ripple Consumer-State

Decision:

```txt
ripple/ = CANDIDATE
```

Phase 1 must not promote FAB to TARGET.

Required wording:

```txt
FAB and Extended FAB are plausible animated-ripple consumers, but v3.5.4
places them in the CANDIDATE bucket. Ripple v2 (#25) and data-ax-ripple
opt-in (#27) are the intended venue for promotion.
```

Phase 1 docs may reference:

```txt
Button #1 ripple TARGET precedent
Icon button #2 ripple TARGET precedent
Card #9 action surface ripple CANDIDATE precedent
```

### §1.8 — WordPress Mapping Direction

Decision:

```txt
No natural core/* block maps cleanly to FAB.
```

Phase 1 WP-MAPPING must compare three paths:

```txt
1. Pattern composition
   CTA / quick-action composition controlled by theme templates.

2. Theme template part / pattern
   Theme owns placement and static styling, but action behavior remains
   limited.

3. Custom block / plugin territory
   Preferred for app-like floating actions, editor-integrated behavior,
   or scroll/route-aware FABs.
```

Core button style bridge is weak:

```txt
core/button + is-style-fab is not equivalent to a real FAB because FAB is
placement-sensitive and action-context-sensitive, not merely an inline
button variant.
```

Phase 1 must not register block styles.

### §1.9 — Behavior Pattern Deferral

Decision:

```txt
v3.5.5 = static FAB family primitive.
```

Behavior-heavy patterns are deferred:

```txt
Extended FAB collapse/expand
auto-hide on scroll
transitioning FAB to FAB menu
morphing into modal/sheet
toolbar floating-with-FAB choreography
```

Disposition:

```txt
Record as future v3.5.x BACKLOG candidate if Phase 1 finds enough evidence.
Do not edit BACKLOG.md during Phase 1 unless a later explicit approval
expands scope.
```

---

## §2 — Phase 1 Deliverables

### §2.1 `FAB-SPEC-AUDIT.md`

Required structure:

```txt
§0  Audit status
§1  Scope and source inventory
§2  Family-merge decision
§3  Variant matrix
    §3a FAB sizes: 56 / 80 / 96
    §3b FAB surfaces: tonal primary/secondary/tertiary + primary/secondary/tertiary
    §3c Extended FAB static label-bearing variant
§4  Native semantics and accessible-name contract
§5  Disabled — native disabled
§5a Disabled — aria-disabled plugin-managed
§6  State-layer and ripple consumer-state
§7  Icon-system dependency contract
§8  Elevation and shape semantics
§9  Extended FAB behavior deferral
§10 WordPress/M3 boundary summary
§11 Phase 1 verdict criteria
§12 G1-G10 applicability
§13 References
§14 What this audit does NOT do
```

Required findings:

```txt
- Single FAB-family audit trio.
- Native button only.
- FAB icon-only requires accessible name.
- Extended FAB requires visible label.
- icon-system/ is CURRENT unconditional.
- ripple/ remains CANDIDATE.
- static state-layer is CURRENT.
- Extended FAB behavior is deferred.
- FAB menu and Toolbar integration are out of scope.
```

### §2.2 `FAB-MEASUREMENT-AUDIT.md`

Required structure:

```txt
§0  Measurement status
§1  Source inventory and measurement method
§2  FAB container sizes
§3  Extended FAB container metrics
§4  Icon metrics and slot sizing
§5  Elevation / shape / state-layer metrics
§6  Disabled metrics
§7  WCAG SC accuracy
§8  Token coverage
§9  Deviations / deferrals
§10 Measurement verdict
```

Required measurements:

```txt
FAB:
  default: 56px
  medium:  80px
  large:   96px

Extended FAB:
  height:    56px
  min-width: 80px
  padding:   16px L/R
  gap:       8px

Icon:
  default FAB icon: 24px
  medium FAB icon:  28px
  large FAB icon:   36px
  Extended FAB icon: 24px

Elevation:
  rest:  level3
  hover: level4
  focus/active: level3
  disabled: level0
```

WCAG SC accuracy:

```txt
SC 2.5.8 Target Size (Minimum) AA:
  FAB 56 / 80 / 96: met
  Extended FAB 56 high / 80 min-width: met

SC 2.5.5 Target Size (Enhanced) AAA:
  FAB 56 / 80 / 96: met
  Extended FAB 56 high / 80 min-width: met
```

Important note:

```txt
If Phase 1 compares M3 "small FAB = 40dp" against Axismundi baseline, record
that Axismundi baseline currently exposes 56/80/96, not a 40px FAB. Do not
invent a 40px implementation.
```

### §2.3 `FAB-WP-MAPPING.md`

Required structure:

```txt
§0  Mapping status
§1  WordPress context inventory
§2  No natural core/* block mapping
§3  Pattern composition path
§4  Theme template part path
§5  Custom block / plugin path
§6  Placement and positioning boundary
§7  Accessible name and action contract
§8  Anti-pattern inventory
§9  Behavior pattern deferrals
§10 Mapping verdict
§11 References
```

Core/block contexts to evaluate:

```txt
core/button
core/buttons
core/navigation
core/social-links
template parts
patterns
custom dynamic blocks
```

Expected conclusion:

```txt
core/button can share native button semantics but does not naturally own
FAB placement, floating behavior, or app-level action context.
```

Anti-pattern inventory must include:

```txt
- inline ordinary button restyled as FAB without placement contract
- non-positioned FAB in prose flow
- icon-less FAB
- icon-only FAB without aria-label/equivalent accessible name
- hardcoded SVG bypassing icon-system policy
- <div role="button" class="ax-fab">
- aria-disabled without event guard
- FAB menu behavior folded into FAB primitive
- scroll auto-hide behavior treated as static theme CSS
- custom block used for pure static styling when pattern composition suffices
```

---

## §3 — Inputs To Read During Phase 1 Execution

Required:

```txt
docs/v3.5.5/FAB-PHASE-0-REPORT.md
docs/v3.5.5/FAB-PHASE-0-PLAN.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
```

Baseline/public:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/style-guide.html
```

Reference templates, in order:

```txt
1. Icon button v3.5.2 audit trio
   Closest icon-system unconditional precedent.

2. Button v3.5.1 audit trio
   Variant matrix, Pattern A disabled split, native semantics.

3. Card v3.5.3 audit trio
   Composition boundary, behavior deferral, WP mapping discipline.

4. Chip v3.4.9 audit trio
   Original Component Full-Spec template.
```

Infrastructure references:

```txt
products/reference-implementations/axismundi-lab/modules/icon-system/docs/
products/reference-implementations/axismundi-lab/modules/ripple/
```

---

## §4 — Execution Steps After Approval

1. Create docs directory:

```txt
products/reference-implementations/axismundi-lab/modules/fab/docs/
```

2. Create:

```txt
FAB-SPEC-AUDIT.md
FAB-MEASUREMENT-AUDIT.md
FAB-WP-MAPPING.md
```

3. Use single family framing throughout:

```txt
FAB family
FAB primitive
Extended FAB primitive
```

4. Preserve public anchor distinction:

```txt
#components-fab
#components-fab-extended
```

5. Record current public specimen SVG state without editing public surface.

6. Mark dependencies:

```txt
state-layer foundation = CURRENT
icon-system/           = CURRENT unconditional
ripple/                = CANDIDATE
elevation tokens       = CURRENT token graph
```

7. Run validator.

8. Report changed files, validation, assumptions, and remaining risks.

---

## §5 — Test Plan

Plan validation:

```txt
- FAB-PHASE-1-PLAN.md exists.
- No modules/fab/docs/ directory created yet.
- No FAB audit docs created yet.
- No lab-fab.css/html/js created.
- Baseline/public/state/handoff files untouched.
- Validator remains 1.000 PASS.
```

Phase 1 execution validation after approval:

```txt
- 3 audit docs created under modules/fab/docs/.
- No lab-fab.css/html/js created.
- SPEC includes family-merge decision.
- SPEC includes native semantics decision tree.
- SPEC includes Pattern A disabled split.
- SPEC includes icon-system CURRENT unconditional.
- SPEC includes ripple CANDIDATE.
- MEASUREMENT includes 56/80/96 FAB sizes.
- MEASUREMENT includes Extended FAB 56px / 80px min-width.
- MEASUREMENT cites SC 2.5.8 AA and SC 2.5.5 AAA accurately.
- WP-MAPPING includes no-natural-core-block finding.
- WP-MAPPING includes pattern/template/custom-block paths.
- WP-MAPPING includes anti-pattern inventory.
- FAB menu and Toolbar are explicitly out of scope.
- Validator remains 1.000 PASS.
```

---

## §6 — Explicit Non-Goals

This plan does not authorize:

```txt
- creating FAB-SPEC-AUDIT.md
- creating FAB-MEASUREMENT-AUDIT.md
- creating FAB-WP-MAPPING.md
- creating lab-fab.css
- creating lab-fab-pattern.html
- creating lab-fab.js
- editing components.css
- editing style-guide.html
- editing tokens.css
- editing blocks.css
- editing theme.json
- replacing public inline SVG specimens
- implementing icon-system cleanup
- implementing Ripple v2
- adding data-ax-ripple
- promoting ripple/ to TARGET
- implementing Extended FAB collapse/expand behavior
- implementing FAB menu
- implementing Toolbar integration
- registering WordPress block styles
- editing BACKLOG.md
- editing CHANGELOG.md
- editing ROADMAP.md
- editing CURRENT-STATE.md
- editing NEXT-SESSION.md
```

---

## §7 — Risks

### Risk A — Family Merge Becomes Two Components Again

FAB and Extended FAB are separate public anchors, so Phase 1 could accidentally split them into separate modules.

Mitigation:

```txt
Use one module path and one audit trio. Preserve anchors as sub-sections.
```

### Risk B — Icon-System Contract Hidden By Existing SVG Specimens

The current public surface uses inline SVG snippets, but the consumer-state model says icon-system is CURRENT unconditional.

Mitigation:

```txt
Record the current specimen state as an audit gap.
Use icon-system-forward snippets in target audit examples.
Do not edit public surface in Phase 1.
```

### Risk C — Ripple Candidate Promoted Too Early

FAB is an action surface, so a writer might infer ripple TARGET.

Mitigation:

```txt
Follow v3.5.4 matrix exactly: FAB + Extended FAB = CANDIDATE.
Defer promotion to Ripple v2 / data-ax-ripple release.
```

### Risk D — Extended FAB Behavior Scope Creep

Collapse/expand behavior could sneak into SPEC, WP mapping, or Phase 2 expectations.

Mitigation:

```txt
Static primitive only. Behavior-heavy patterns are future candidates.
```

### Risk E — WordPress Mapping Overclaims Core/Button Fit

`core/button` shares button semantics but not FAB placement or app action context.

Mitigation:

```txt
WP-MAPPING must classify core/button as weak/partial, not natural.
Pattern composition, theme template part, and custom block/plugin paths
must be separated.
```

### Risk F — M3 Size Naming Drift

The user brief mentions 40dp Small / 56dp Default / 96dp Large, while current Axismundi baseline exposes 56 / 80 / 96.

Mitigation:

```txt
MEASUREMENT must report current baseline truth first.
Any M3 naming mismatch becomes a deviation/deferral note, not an
implementation task.
```

---

## §8 — Approval Gate

Phase 1 execution is blocked until this plan is approved.

Approval should confirm:

```txt
- Single FAB-family audit trio.
- FAB and Extended FAB are sub-sections, not separate modules.
- icon-system/ remains CURRENT unconditional.
- ripple/ remains CANDIDATE.
- Extended FAB behavior remains static-only.
- Pattern A disabled split is correct.
- WP-MAPPING should treat core/button as weak/partial, not natural.
- Phase 1 remains documentation-only.
```

After approval:

```txt
Codex may create modules/fab/docs/ and author the 3 audit docs.
```

---

## §9 — One-Line Summary

Phase 1 will create one FAB-family audit trio under `modules/fab/docs/`, covering both FAB and Extended FAB as a merged action family, locking icon-system as CURRENT unconditional, preserving ripple as CANDIDATE, documenting Pattern A disabled semantics, and keeping behavior/runtime/public-surface changes out of scope.
