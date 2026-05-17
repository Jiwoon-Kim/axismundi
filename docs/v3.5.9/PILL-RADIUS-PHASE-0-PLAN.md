# v3.5.9 — BACKLOG #31 — Pill Radius Interpolation Correction Phase 0 Plan

> **Status**: Plan-only. Awaiting review / approval before Phase 0 report execution.  
> **Release kind**: Baseline correction / token graph amendment.  
> **Source backlog**: `BACKLOG.md` #31 — Pill radius interpolation — morphing-safe corner-full token.  
> **Date**: 2026-05-17.

---

## §0 — Framing

v3.5.9 addresses BACKLOG #31 before Button group #6 enters the Wave 1
mainline.

The problem is not that `corner-full` is wrong. The problem is that
`9999px` is a good static pill sentinel but a poor interpolation source when
CSS transitions from pill to a smaller pressed shape.

```txt
Keep:
  --md-sys-shape-corner-full: 9999px
  Meaning: static fully-rounded pill semantics.

Add / route:
  morphing-safe pill radius
  Meaning: a fully-rounded source value that can transition cleanly.
```

This release is intentionally small, but it touches baseline semantics. It
therefore follows the v3.5.6 infrastructure-style discipline rather than a
Component Full-Spec component cycle.

---

## §1 — Cycle Shape Decision

Recommended cycle shape:

```txt
Phase 0  PILL-RADIUS-PHASE-0-REPORT.md
         Inventory current `corner-full` consumers and choose token strategy.

Phase 1  PILL-RADIUS-CORRECTION-AUDIT.md
         Single correction audit doc: token graph, consumer migration,
         affected closed components, QA criteria, non-goals.

Phase 2  Baseline correction
         tokens.css + components.css only, limited to confirmed morphing
         consumers.

Phase 3  Playwright visual/motion QA
         Button + FAB + Button group candidate traces.

Phase 5  Mechanical close
         CHANGELOG / ROADMAP / BACKLOG #31 close / affected audit notes.
```

Not a 3-doc component trio and not a 4-doc runtime cycle:

```txt
- No SPEC / MEASUREMENT / WP-MAPPING trio.
- No RUNTIME-AUDIT.
- No component module creation.
- No lab pattern implementation except optional QA harness if Phase 2 needs it.
```

Preferred file locations:

```txt
docs/v3.5.9/PILL-RADIUS-PHASE-0-PLAN.md       (this file)
docs/v3.5.9/PILL-RADIUS-PHASE-0-REPORT.md
docs/v3.5.9/PILL-RADIUS-CORRECTION-AUDIT.md
```

---

## §2 — Authoritative Inputs To Read

Phase 0 report must read:

```txt
BACKLOG.md #31
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/fab/docs/FAB-SPEC-AUDIT.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
```

Optional QA references:

```txt
products/reference-implementations/axismundi-lab/modules/button/lab-button-pattern.html
products/reference-implementations/axismundi-lab/modules/fab/lab-fab-pattern.html
```

---

## §3 — Current Evidence From Quick Survey

Phase 0 plan survey found:

```txt
tokens.css:
  --md-sys-shape-corner-full: 9999px
  --comp-button-radius: var(--md-sys-shape-corner-full)
  --comp-avatar-radius: var(--md-sys-shape-corner-full)

components.css Button:
  .ax-button { border-radius: var(--comp-button-radius); }
  .ax-button:active { border-radius: var(--md-sys-shape-corner-small); }
  transition includes border-radius.

components.css FAB:
  .ax-fab uses --_fab-radius.
  Survey required: determine whether --_fab-radius resolves to corner-full
  and whether border-radius itself transitions. Quick excerpt shows FAB
  currently transitions elevation/background, not border-radius, so it may
  be less affected than initially assumed.

components.css Button group:
  connected first/last and selected states use corner-full.
  pressed states shrink inner corners.
  This is a direct reason to close #31 before Button group #6 Full-Spec work.
```

Phase 0 report must replace this quick survey with a full grep-backed
consumer table.

---

## §4 — Token Strategy Options To Evaluate

Phase 0 must compare and lock one strategy.

### Option A — Smaller Static Sentinel

```txt
--md-sys-shape-corner-pill-stable: 999px;
```

Pros:

```txt
- Simple.
- Avoids changing existing corner-full.
- Reduces but does not eliminate interpolation distance.
```

Cons:

```txt
- Still arbitrary.
- Still linearly interpolates from a large number.
- Can fail for extremely large pills.
```

### Option B — Percentage Radius

```txt
--md-sys-shape-corner-pill-stable: 50%;
```

Pros:

```txt
- Predictable interpolation.
- Common CSS pill idiom.
- Does not require per-component height tokens.
```

Cons:

```txt
- Percentage border-radius has axis-specific behavior.
- Can produce unexpected curves for non-button shapes.
- Less explicit about M3 dp/height relationship.
```

### Option C — Component Height Calc

```txt
--comp-button-radius: calc(var(--comp-button-height) / 2);
--_fab-radius: calc(var(--_fab-size) / 2);
```

Pros:

```txt
- Geometrically exact for each component.
- Clean interpolation source value: 20px for 40px Button, 28px for 56px FAB.
- No ambiguity about very wide components.
```

Cons:

```txt
- Requires each component to own the radius calculation.
- A global pill-stable token cannot directly know component height.
```

### Option D — Hybrid Token Alias + Component Calc

```txt
Global semantic token:
  --md-sys-shape-corner-pill-stable: 50%;

Component tokens for morphing sources:
  --comp-button-radius: calc(var(--comp-button-height) / 2);
  --_fab-radius: calc(var(--_fab-size) / 2);
```

Pros:

```txt
- Keeps a named token for ontology / documentation.
- Uses component-height calc where exact geometry matters.
- Leaves static corner-full unchanged.
- Most defensive for Button group and future morphing components.
```

Cons:

```txt
- Slightly more complex token graph.
- Phase 1 audit must define when to use pill-stable vs component calc.
```

Plan recommendation:

```txt
Prefer Option D unless Phase 0 discovers validator/theme-token constraints
that make it too costly.
```

---

## §5 — Consumer Scope And Migration Rules

Phase 0 must produce a table of all `corner-full` consumers and classify them:

```txt
STATIC_PILL
  Safe to leave on --md-sys-shape-corner-full.

MORPH_SOURCE_CONFIRMED
  Rest/selected state uses corner-full and transitions to smaller corner.
  Candidate for v3.5.9 migration.

MORPH_SOURCE_POSSIBLE
  Looks suspicious but requires Playwright or style trace.

OUT_OF_SCOPE
  Future component, non-Wave-1 surface, or not touched in this release.
```

Initial likely classifications:

```txt
Button #1:
  MORPH_SOURCE_CONFIRMED.

Button group #6 baseline:
  MORPH_SOURCE_CONFIRMED or MORPH_SOURCE_POSSIBLE.
  Must be decided in Phase 0 because Button group #6 is the next planned
  Wave 1 component.

FAB #3 + Extended FAB #4:
  Verify. Existing quick excerpt suggests active state changes elevation,
  not border-radius, but the radius source may still need auditing.

Avatar / Badge / Chip static pills:
  STATIC_PILL unless transition evidence exists.

Date picker cells / Slider thumb / Switch track:
  Verify before touching; do not migrate by assumption.
```

Migration rule:

```txt
Only confirmed morphing sources migrate in v3.5.9.
Static pill surfaces continue using corner-full.
No speculative mass sweep.
```

---

## §6 — Baseline Edit Scope To Lock

Allowed in Phase 2 only after Phase 0 + Phase 1 approval:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
  - Add morphing-safe pill token / comments.
  - Keep --md-sys-shape-corner-full unchanged.

products/reference-implementations/axismundi-lab/stylesheets/components.css
  - Update only confirmed morphing consumers.
  - Expected minimum: Button.
  - Possible: Button group baseline; FAB only if confirmed.
```

Needs Phase 0 decision:

```txt
products/reference-implementations/ontology-theme-pilot/theme.json
  - Probably NOT touched.
  - Only needed if the new token must be exported to WordPress settings.
  - Default recommendation: do not register unless a real consumer requires
    theme-facing customization.
```

Forbidden:

```txt
- Changing --md-sys-shape-corner-full globally.
- Editing style-guide.html directly.
- Editing lab module docs/artifacts except optional QA notes.
- Opening a naming sweep.
- Reopening Button/FAB/Search bar cycles beyond terse Phase 5 alignment notes.
```

---

## §7 — Validator And Tooling Questions

Phase 0 must answer:

```txt
1. Does adding --md-sys-shape-corner-pill-stable require validator schema changes?
2. Does theme.json need a matching setting/token export?
3. Does publish_styleguide.py require any change? Expected: no.
4. Are Playwright artifacts ignored? Expected: yes, screenshot artifacts stay out.
```

Validation commands for later phases:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Expected result:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

---

## §8 — Playwright QA Plan

Phase 0 should define the Phase 3 Playwright evidence set.

Minimum traces:

```txt
Button:
  - Record computed border-radius before press.
  - Record frame samples during :active transition.
  - Confirm no 9999px -> 8px interpolation flicker.
  - Confirm final pressed radius still matches M3 §4.3.

Button group:
  - Record connected first/last/middle segment radii.
  - Record selected + pressed segment radii.
  - Confirm outer pill edges remain pill-like while inner corners morph.

FAB:
  - Verify whether border-radius changes at all.
  - If not morphing, record as not affected.
```

QA artifact policy:

```txt
Screenshots/traces are diagnostic only and should stay gitignored.
Audit docs record textual evidence, not large image artifacts.
```

---

## §9 — Phase 0 Report Shape

Expected report:

```txt
docs/v3.5.9/PILL-RADIUS-PHASE-0-REPORT.md
```

Required sections:

```txt
§0  Framing and source BACKLOG #31
§1  Inputs read
§2  Current token graph inventory
§3  corner-full consumer table
§4  Morphing source classification
§5  Token strategy comparison (Options A-D)
§6  Decision lock: chosen strategy
§7  Baseline edit scope
§8  theme.json / validator impact
§9  Playwright QA plan
§10 Affected closed component alignment plan
§11 Risks and dispositions
§12 Phase 1 entry conditions
§13 Non-goals
§14 Verdict
```

---

## §10 — Applicable Gates

Applicable:

```txt
G1  Validator remains 1.000 PASS.
G2  Public/baseline edits are explicit and scoped.
G3  Publish should remain clean.
G5  CHANGELOG records release.
G13 Inventory accuracy spirit applies: all corner-full consumers must be
    honestly classified before baseline edits.
```

Infrastructure-style gates by analogy:

```txt
G22-G26 partially apply as token graph / baseline correction discipline:
  - stable public dependency contract
  - reusable across multiple consumers
  - no consumer semantics absorbed
```

Not applicable:

```txt
G6-G10 component full-spec gates as a component release.
G11-G16 runtime gates.
G17-G21 plugin gates.
```

---

## §11 — Risks

| Risk | Severity | Phase 0 disposition |
|---|---:|---|
| New token becomes a vague duplicate of corner-full | High | Define exact usage: static full vs morphing source |
| Button group already has connected pill morphs | High | Classify in Phase 0 before v3.5.10 |
| FAB assumed affected but not actually morphing | Medium | Verify from CSS and Playwright before migration |
| theme.json scope creep | Medium | Default to no theme.json edit unless validator/schema demands |
| Static pill regressions | Medium | Leave static corner-full consumers untouched |
| Closed audit doc churn | Low | Phase 5 only, terse alignment notes if needed |

---

## §12 — Non-Goals

This v3.5.9 cycle must not:

```txt
- Change --md-sys-shape-corner-full.
- Convert all corner-full consumers.
- Reopen Button/FAB/Button group component audits as new component cycles.
- Implement Button group #6 Full-Spec.
- Add JavaScript to solve CSS interpolation.
- Edit style-guide.html by hand.
- Perform Lab Preview Routes.
- Perform Records sweep.
- Touch Search bar v3.5.8 artifacts.
```

---

## §13 — Self-Verification Summary

```txt
self-check:
  BACKLOG #31 read                         yes
  corner-full quick grep                   yes
  Button confirmed morph source            yes
  Button group baseline risk identified    yes
  FAB requires verification                 yes
  corner-full immutable lock                yes
  token options A-D included                yes
  Playwright QA plan included               yes
  Phase 0 plan-only                         yes
```

---

## §14 — Plan Verdict

```txt
Plan v1.0 is ready for review.

Recommended route:
  approve Phase 0 execution -> write PILL-RADIUS-PHASE-0-REPORT.md
  -> review token strategy lock
  -> Phase 1 correction audit
  -> Phase 2 baseline patch
  -> Phase 3 Playwright morph QA
  -> Phase 5 close BACKLOG #31.
```
