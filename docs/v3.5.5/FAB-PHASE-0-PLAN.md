# Axismundi v3.5.5 — FAB Family Phase 0 Plan

Status: PLAN-ONLY v1.0  
Target release: v3.5.5 — Wave 1 — FAB family  
Scope: FAB #3 + Extended FAB #4 Phase 0 planning only  
Date: 2026-05-16

---

## §0. Goal

Prepare the Phase 0 inventory/report pass for the FAB family after the v3.5.4 matrix consumer-state amendment and the v3.5.5 ontology grounding pass.

This plan does not write the Phase 0 report yet. It defines what the Phase 0 report must inspect, classify, and decide before Phase 1 audit docs can begin.

Expected Phase 0 execution deliverable after approval:

```txt
docs/v3.5.5/FAB-PHASE-0-REPORT.md
```

No FAB module files are created during this plan or Phase 0 report work.

---

## §1. Scope Locks

### §1.1 Family-Merge Module Structure

FAB is the first real test of the v3.5.0 Phase 0B family-merge decision:

```txt
Matrix row #3  FAB
Matrix row #4  Extended FAB

Single module:
  products/reference-implementations/axismundi-lab/modules/fab/

Two public TOC anchors:
  #components-fab
  #components-fab-extended
```

Phase 0 must settle the audit shape:

```txt
Option (a): Single audit trio for the FAB family
            FAB + Extended FAB handled as family variants within one module.

Option (b): Paired audit docs
            FAB and Extended FAB each get separate SPEC / MEASUREMENT /
            WP-MAPPING docs.
```

Recommended decision: **Option (a)**.

Rationale:

```txt
- Matrix already folds Extended FAB into fab/.
- Shared action semantics, elevation model, icon dependency, color roles,
  state-layer foundation, and ripple candidate status make this one
  family-level module.
- Button #1 handled five Button variants inside one audit trio; FAB can
  similarly handle FAB / Extended FAB as related family members.
- Paired docs would duplicate most dependency and WordPress mapping analysis.
```

Expected future audit trio if Option (a) is approved:

```txt
products/reference-implementations/axismundi-lab/modules/fab/docs/
  FAB-SPEC-AUDIT.md
  FAB-MEASUREMENT-AUDIT.md
  FAB-WP-MAPPING.md
```

### §1.2 Baseline Inventory Targets

Phase 0 must inspect and report:

```txt
components.css §15 — FAB
components.css §16 — Extended FAB
style-guide.html #components-fab
style-guide.html #components-fab-extended
```

Required inventory details:

```txt
- Rule block count and selector structure.
- FAB size variants:
    .ax-fab
    .ax-fab.is-medium
    .ax-fab.is-large
- Extended FAB size scope:
    current baseline appears small/default only; Phase 0 must confirm.
- Surface/color variants:
    default tonal surface
    secondary
    tertiary
    primary
    plus any baseline-specific tonal aliases.
- Elevation tokens:
    expected level3 rest / level4 hover relationship.
- Icon slot:
    FAB icon body.
    Extended FAB leading icon.
- Label slot:
    Extended FAB label only.
- Disabled specimens.
- style-guide specimen count and whether specimens use icon-system
  conventions or legacy inline SVG.
```

Phase 0 must also distinguish:

```txt
FAB family       = row #3 + row #4
FAB menu         = row #5, separate future module/phase
Toolbar + FAB    = toolbar family concern, not v3.5.5 FAB family scope
```

### §1.3 Dependency Profile Hypothesis

Phase 0 starts from this dependency hypothesis and must confirm or amend it:

```txt
components.css §0 state-layer foundation
  State: CURRENT
  Reason: FAB is an action surface and uses the shared static state-layer
          baseline.

icon-system/
  State: CURRENT unconditional
  Reason: FAB is definitionally an icon-bearing action component.
          Extended FAB also has a required leading icon slot in the current
          Axismundi family model.

ripple/
  State: CANDIDATE
  Reason: v3.5.4 matrix amendment classifies FAB and Extended FAB as
          CANDIDATE ripple consumers. Ripple v2 (#25) is the venue for
          consumer promotion.

elevation tokens
  State: CURRENT baseline token graph
  Reason: FAB uses elevation levels, but elevation is not a separate
          infrastructure module like icon-system/.
```

Important nuance:

```txt
icon-system/ may be CURRENT unconditional as the target dependency contract
while current public specimens still contain legacy inline SVG snippets.
Phase 0 must honestly inventory that gap if present.
```

### §1.4 Ripple Consumer-State Decision

Recommended Phase 0 decision:

```txt
ripple/ = CANDIDATE for FAB family.
```

Do not promote FAB or Extended FAB to TARGET during v3.5.5 unless a separate architecture decision explicitly moves ripple consumer alignment into this release.

Rationale:

```txt
- v3.5.4 intentionally classified FAB + Extended FAB as CANDIDATE.
- Ripple v2 (#25) and data-ax-ripple opt-in (#27) are already the
  dedicated venue for the animated state-layer contract.
- Promoting FAB alone would fragment the Ripple v2 consumer migration.
- FAB Phase 0 should increase clarity, not create a new hidden ripple scope.
```

### §1.5 Toolbar Floating-With-FAB Boundary

Toolbar floating-with-FAB patterns are out of v3.5.5 scope.

Phase 0 must record:

```txt
v3.5.5 FAB family scope:
  standalone FAB
  standalone Extended FAB

Deferred:
  FAB menu row #5
  Toolbar row #8 integration
  floating-with-FAB toolbar choreography
  responsive toolbar/FAB behavior patterns
```

Recommended disposition:

```txt
Record as deferred v3.5.x / v1.5+ behavior-composition work.
Revisit when Toolbar #8 or FAB menu #5 enters its own cycle.
```

### §1.6 WordPress Block Mapping Stub

Phase 0 should only enumerate WordPress mapping candidates. Phase 1 WP-MAPPING will formalize the decision.

Initial hypothesis:

```txt
Natural core/* block: weak / none.

Possible paths:
  1. core/button + is-style-fab
     Weak candidate because FAB is positioned and action-specific, not an
     ordinary inline button style.

  2. Pattern composition
     More plausible for theme-level CTA surfaces or floating action
     composition, but positioning must remain carefully scoped.

  3. Custom block / plugin territory
     Plausible when FAB action is app-like, editor-driven, or behavior-heavy.
```

Phase 0 must not register block styles or edit WordPress integration files.

### §1.7 Extended FAB Static-Only Scope

Extended FAB may involve behavior in broader Material usage:

```txt
- extended-to-collapsed transition
- expansion/collapse on scroll
- label visibility changes
- FAB-to-toolbar choreography
```

Recommended v3.5.5 decision:

```txt
Static Extended FAB only.
```

Behavior patterns should be deferred similarly to Card #29:

```txt
Primitive first.
Behavior-heavy patterns later.
```

Phase 0 should surface this as a risk/disposition item so Phase 1 and Phase 2 do not accidentally inherit behavior work.

---

## §2. Inputs To Read During Phase 0 Execution

Canonical framework:

```txt
CONSTITUTION.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Closed Wave 1 precedents:

```txt
products/reference-implementations/axismundi-lab/modules/button/docs/
  BUTTON-SPEC-AUDIT.md
  BUTTON-MEASUREMENT-AUDIT.md
  BUTTON-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/icon-button/docs/
  ICON-BUTTON-SPEC-AUDIT.md
  ICON-BUTTON-MEASUREMENT-AUDIT.md
  ICON-BUTTON-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/card/docs/
  CARD-SPEC-AUDIT.md
  CARD-MEASUREMENT-AUDIT.md
  CARD-WP-MAPPING.md

products/reference-implementations/axismundi-lab/modules/chip/docs/
  CHIP-SPEC-AUDIT.md
  CHIP-MEASUREMENT-AUDIT.md
  CHIP-WP-MAPPING.md
```

Baseline/public surface inventory:

```txt
products/reference-implementations/axismundi-lab/assets/css/components.css
  §0 state-layer foundation
  §15 FAB
  §16 Extended FAB

products/reference-implementations/axismundi-lab/style-guide.html
  #components-fab
  #components-fab-extended
  #components-fab-menu only as out-of-scope boundary evidence

products/reference-implementations/axismundi-lab/assets/css/tokens.css
  elevation tokens
  FAB-related component tokens if present
```

Infrastructure docs:

```txt
products/reference-implementations/axismundi-lab/modules/icon-system/docs/
products/reference-implementations/axismundi-lab/modules/ripple/
```

---

## §3. Required Phase 0 Report Shape

Recommended report:

```txt
docs/v3.5.5/FAB-PHASE-0-REPORT.md
```

Required sections:

```txt
§0 Executive summary
§1 Scope and non-goals
§2 Matrix placement: FAB #3 + Extended FAB #4 family merge
§3 Baseline inventory: components.css §15 + §16
§4 Public specimen inventory: style-guide.html anchors
§5 Dependency profile: state-layer, icon-system, ripple, elevation tokens
§6 Family audit-shape decision: single trio vs paired docs
§7 Extended FAB behavior scope: static primitive vs behavior deferral
§8 WordPress mapping stub
§9 Risks and dispositions
§10 Phase 1 entry constraints
§11 Validation notes
§12 One-line verdict
```

Phase 0 report must make these decisions explicit:

```txt
- Single family audit trio or paired docs.
- ripple/ remains CANDIDATE unless explicitly escalated.
- Extended FAB behavior remains out of scope unless explicitly escalated.
- FAB menu and Toolbar integration are deferred.
- WordPress mapping remains a Phase 1 formalization item.
```

---

## §4. Expected Risks

### Risk 1 — Family-Merge Module Structure

FAB #3 and Extended FAB #4 are separate TOC entries but one module family.

Expected disposition:

```txt
Single module and single audit trio, with FAB and Extended FAB handled as
family members/variants.
```

### Risk 2 — Icon-System Contract vs Inline SVG Specimens

FAB has an unconditional icon-system dependency in the v3.5.4 consumer-state model. Current public specimens may still use inline SVG.

Expected disposition:

```txt
Inventory honestly.
Do not edit style-guide.html in Phase 0.
Route cleanup as candidate future public-surface cleanup if needed.
```

### Risk 3 — Ripple CANDIDATE vs TARGET

Material FABs are action surfaces, but Axismundi ripple consumer-state is release-controlled.

Expected disposition:

```txt
Keep ripple/ as CANDIDATE.
Reference BACKLOG #25 and #27 as the Ripple v2/data-ax-ripple venue.
```

### Risk 4 — Extended FAB Expand/Collapse Behavior

Extended FAB can participate in label-collapse and scroll-responsive behavior.

Expected disposition:

```txt
Static-only in v3.5.5.
Behavior pattern deferred, following the Card #29 precedent.
```

### Risk 5 — WordPress Mapping Weakness

FAB has no obvious native core block equivalent.

Expected disposition:

```txt
Phase 0 enumerates mapping candidates only.
Phase 1 WP-MAPPING decides whether this is theme pattern composition,
custom block/plugin territory, or a limited block-style bridge.
```

### Risk 6 — Hit Target and WCAG Classification

FAB size likely exceeds SC 2.5.8 AA and SC 2.5.5 AAA thresholds, but Phase 1 must verify with actual baseline measurements.

Expected disposition:

```txt
Phase 0 records measurement requirement.
Phase 1 MEASUREMENT verifies exact dimensions, visible target, and
touch target treatment.
```

### Risk 7 — FAB Menu / Toolbar Boundary Creep

FAB menu #5 and Toolbar #8 can look adjacent to FAB family work.

Expected disposition:

```txt
Explicitly out of scope.
Only boundary references allowed in Phase 0.
```

---

## §5. Constraints

Documentation-only constraints:

```txt
- Do not create products/reference-implementations/axismundi-lab/modules/fab/.
- Do not create lab-fab.css.
- Do not create lab-fab-pattern.html.
- Do not create lab-fab.js.
- Do not edit components.css.
- Do not edit style-guide.html.
- Do not edit tokens.css.
- Do not edit blocks.css.
- Do not edit theme.json.
- Do not edit BACKLOG.md in Phase 0 plan/report.
- Do not edit CURRENT-STATE.md.
- Do not edit NEXT-SESSION.md.
- Do not implement Ripple v2.
- Do not replace inline SVG specimens during Phase 0.
- Do not register WordPress block styles.
```

Process constraints:

```txt
Plan → review → approval → Phase 0 report.
Phase 0 report → review → approval → Phase 1 plan.
```

---

## §6. Validation Plan

After creating this plan:

```txt
1. Confirm this file exists.
2. Confirm no lab/modules/fab/ directory was created.
3. Confirm baseline/public files were not modified.
4. Confirm CURRENT-STATE.md and NEXT-SESSION.md were not modified.
5. Run validator:
     python .\tools\validators\validate_theme_pilot.py
6. Confirm:
     1.000 / 1.000 / 1.000 / 1.000 PASS
```

Note:

```txt
The validator may refresh generated audit/report files under
bindings/wordpress-material3/. That is validator output, not FAB scope.
```

---

## §7. Approval Gate

This plan requires review before Phase 0 report execution.

Approval should confirm:

```txt
- Family-merge audit direction is acceptable.
- Ripple remains CANDIDATE.
- Extended FAB behavior remains static-only.
- Toolbar/FAB menu boundaries are clear.
- WordPress mapping remains a Phase 1 decision.
- Phase 0 stays documentation-only.
```

If approved, next Codex task:

```txt
Create docs/v3.5.5/FAB-PHASE-0-REPORT.md following this plan.
Do not create lab/modules/fab/ yet.
```

---

## §8. One-Line Summary

v3.5.5 Phase 0 should treat FAB + Extended FAB as the first family-merge Component Full-Spec cycle, keep ripple as CANDIDATE, keep Extended FAB behavior static-only, and prepare a single FAB-family audit path without touching baseline or creating module artifacts.
