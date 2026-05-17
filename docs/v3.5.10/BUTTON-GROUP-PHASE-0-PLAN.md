# v3.5.10 — Wave 1 — Button Group #6 Phase 0 Plan

> **Status**: Plan-only.  
> **Target component**: Button group #6.  
> **Matrix row**: `docs/v3.5.0/MODULE-STATUS-MATRIX.md` row #6.  
> **Previous release dependency**: v3.5.9 BACKLOG #31 finite pill radius
> correction is current baseline, not work to repeat.

---

## §0 — Plan Verdict

```txt
Phase 0 plan is ready for review.

This plan proposes:
  - Category: Component Full-Spec.
  - Audit shape: 3-doc trio, not RUNTIME-AUDIT.
  - Scope: Button group only.
  - Explicitly not scope: Split button, Toolbar, standalone toggle button.
  - Current baseline: components.css §28 + style-guide.html
    #components-button-group.
  - v3.5.9 finite pill correction is inherited as current baseline.
```

No implementation occurs in this plan.

---

## §1 — Purpose

Button group is the next Wave 1 component after the v3.5.9 baseline
correction.

Phase 0 must determine whether the existing baseline Button group is a
straight Component Full-Spec cycle or whether its interaction semantics require
a separate runtime audit. It must also prevent drift into adjacent but distinct
components:

```txt
Button group     = grouped buttons / segmented-like controls.
Split button     = primary action + dropdown trigger; separate row #7.
Toolbar          = container that may host button groups; separate row #8.
Standalone toggle button = Button / Icon button variant, not Button group.
```

---

## §2 — Required Inputs

Phase 0 report must read these sources:

```txt
Root context:
  AGENTS.md
  CURRENT-STATE.md
  NEXT-SESSION.md
  PROJECT-CONTEXT.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
  docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md

Current baseline:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §28
  products/reference-implementations/axismundi-lab/style-guide.html #components-button-group
  products/reference-implementations/axismundi-lab/stylesheets/tokens.css
  products/reference-implementations/axismundi-lab/stylesheets/blocks.css

Precedents:
  products/reference-implementations/axismundi-lab/modules/button/docs/*
  products/reference-implementations/axismundi-lab/modules/icon-button/docs/*
  products/reference-implementations/axismundi-lab/modules/fab/docs/*
  docs/v3.5.9/PILL-RADIUS-CORRECTION-AUDIT.md
```

Optional external spec read:

```txt
M3 Button group / Segmented button guidance, if current public docs expose it.
Use local baseline and v3.5.0 ontology as authority when external naming drifts.
```

---

## §3 — Baseline Facts Already Known

Phase 0 report must verify, not assume, these known facts:

```txt
components.css §28:
  - Root: .ax-button-group
  - Connected variant: .ax-button-group--connected
  - Hidden native radio input: .ax-button-group__input
  - Pattern A: radio + label, CSS-only, single-select
  - Pattern B: button + aria-pressed, JS-driven, multi-toggle toolbar use
  - Standard selected button widens via flex-grow: 1.15
  - Connected variant uses 2px gap and small inner corners
  - Connected selected segment becomes finite pill after v3.5.9
  - Connected selected+pressed inner corners shrink to smaller values

style-guide.html #components-button-group:
  - Standard single-select demo
  - Connected single-select demo
  - Toolbar multi-toggle demo
  - Inline snippet still describes theme JS toggling aria-pressed
```

v3.5.9 current baseline:

```txt
tokens.css:
  --md-sys-shape-corner-pill-stable: 50%
  --comp-button-radius: calc(var(--comp-button-height) / 2)

components.css §28:
  --_button-group-pill-radius: calc(var(--comp-button-height) / 2)

Playwright result:
  Button group selected segment: 20px -> 4px active morph.
  Button group outer corners: 20px preserved.
```

---

## §4 — Lock Decision 1: Category

Recommended decision:

```txt
Button group = Component Full-Spec.
Applicable gates: G1-G10.
Not Interaction Runtime.
Not Infrastructure.
Not Baseline-only Record.
```

Rationale:

```txt
The current baseline already uses native HTML / ARIA patterns:
  Pattern A: input[type=radio] + label
  Pattern B: button[type=button][aria-pressed]

There is no standalone extracted runtime module equivalent to search-expansion/
or snackbar. Existing style-guide inline JS is a tiny demo pattern, not a
reusable runtime provider.
```

Phase 0 must verify whether a real runtime extraction need exists. Default is
no.

---

## §5 — Lock Decision 2: Audit Shape

Recommended decision:

```txt
3-doc trio:
  BUTTON-GROUP-SPEC-AUDIT.md
  BUTTON-GROUP-MEASUREMENT-AUDIT.md
  BUTTON-GROUP-WP-MAPPING.md

No BUTTON-GROUP-RUNTIME-AUDIT.md in v3.5.10.
```

Rationale:

```txt
Text field v3.5.7:
  native/CSS interaction -> 3-doc trio.

Search bar v3.5.8:
  extracted JS runtime -> 4-doc shape.

Button group resembles Text field more than Search bar:
  native radio semantics + native buttons + small integrator toggle JS
  do not justify a separate runtime doc.
```

Phase 0 must still record a decision, not silently inherit the default.

---

## §6 — Lock Decision 3: Terminology

Phase 0 must settle naming language:

```txt
Canonical repo name:
  Button group

M3 / UI vocabulary to map:
  Button group
  Segmented button
  Segmented control
```

Recommended framing:

```txt
Use "Button group" as the Axismundi component name because row #6 and
baseline §28 already use it.

Use "segmented-like" or "segmented button semantics" only as mapping language,
not as a new component name.
```

This prevents accidental creation of `segmented-button/` or a duplicate matrix
row.

---

## §7 — Lock Decision 4: Split Button Boundary

Hard boundary:

```txt
Split button #7 is out of scope.
```

Reasons:

```txt
Split button has dropdown semantics and popover/ dependency.
Matrix row #7 is Component Full-Spec + Interaction.
Button group row #6 is Component Full-Spec.
v3.5.9 left Split button pill sources intentionally deferred.
```

Phase 0 report should cross-reference Split button only as a non-goal and as a
future consumer of the pill-stable lesson.

---

## §8 — Lock Decision 5: Semantics Matrix

Phase 0 must inventory and choose the semantic matrix:

```txt
Pattern A — single-select:
  <fieldset class="ax-button-group">
    <legend>...</legend>
    <input type="radio" class="ax-button-group__input">
    <label class="ax-button ...">...</label>
  </fieldset>

Pattern B — multi-toggle:
  <div class="ax-button-group" role="toolbar" aria-label="...">
    <button type="button" class="ax-button ..." aria-pressed="true|false">
  </div>
```

Expected Phase 0 decision:

```txt
Both patterns are valid and in scope.

Pattern A owns single-select value choice.
Pattern B owns independent toggle toolbar use.

Do not force role="radiogroup" when native radio + fieldset already provides
the grouping semantics.
Do not use radio semantics for independent formatting toggles.
```

Phase 1 SPEC should formalize the decision tree.

---

## §9 — Lock Decision 6: Dependency Profile

Expected state-aware dependency profile:

```txt
components.css §0 state-layer foundation:
  CURRENT

ripple/:
  TARGET or CANDIDATE to be decided in Phase 0.
  Recommendation: TARGET, bounded, per segment via data-ax-ripple.
  Reason: v3.5.6 Ripple v2 lists Button group #6 as CANDIDATE awaiting
  component cycle. This is the component cycle.

icon-system/:
  CURRENT conditional.
  Reason: icon-only and icon+label segments are valid variants, but label-only
  segments do not require icons.

pill-stable token:
  CURRENT baseline correction inherited from v3.5.9.
  Do not redesign token value in v3.5.10.
```

Phase 0 must decide whether Button group moves from ripple CANDIDATE to TARGET
now, or waits until Phase 2 implementation evidence. Recommended: record
TARGET as the intended contract, with actual `data-ax-ripple` wiring in Phase 2.

---

## §10 — Lock Decision 7: Variant Scope

Phase 0 report must inventory:

```txt
Structure:
  - Standard group
  - Connected group

Semantic patterns:
  - Single-select radio + label
  - Multi-toggle button + aria-pressed

Content variants:
  - Label-only segment
  - Icon + label segment
  - Icon-only segment

Visual variants:
  - Filled / Tonal / Outlined / Text where supported through nested .ax-button
  - Selected / unselected
  - Pressed / focus-visible / disabled

Size variants:
  - Baseline currently changes gap / corner values, not full button height.
  - Phase 0 must verify XS/S/L/XL semantics and whether Phase 1/2 should
    demonstrate all or defer some.
```

Expected conservative scope:

```txt
Phase 1 audits the full baseline inventory.
Phase 2 pattern page demonstrates representative variants, not every
cartesian combination.
```

---

## §11 — Lock Decision 8: Disabled State

Recommended decision:

```txt
Pattern A disabled:
  native input:disabled + label state.

Pattern B disabled:
  native button:disabled for real disabled controls.
  aria-disabled only when an integrator must keep focusability or custom
  activation policy.
```

Phase 0 must decide whether Button group follows:

```txt
Button family Pattern A:
  §5 native disabled
  §5a aria-disabled plugin-managed
```

Recommendation:

```txt
Use Button family Pattern A.
Do not use Card Pattern B 3-way disabled split; Button group is a control
surface, not a static content container.
```

---

## §12 — Lock Decision 9: WordPress Mapping Stub

Phase 0 should only stub likely WordPress mappings:

```txt
Likely theme territory:
  - Pattern composition for view-mode / filter-mode selectors.
  - Block toolbar/editor-adjacent visual patterns.

Likely plugin territory:
  - Actual data filtering.
  - Persisted preference storage.
  - Editor toolbar behavior.
  - Formatting command execution.

Weak core mapping:
  - There is no obvious direct core/* block equivalent.
```

Phase 1 WP-MAPPING should formalize theme-can / plugin-should boundaries.

---

## §13 — Required Phase 0 Report Shape

Codex should write:

```txt
docs/v3.5.10/BUTTON-GROUP-PHASE-0-REPORT.md
```

Required sections:

```txt
§0  Phase 0 framing
§1  Authoritative inputs
§2  Baseline inventory: components.css §28
§3  Public specimen inventory: style-guide.html #components-button-group
§4  Category decision: Component Full-Spec
§5  Audit shape decision: 3-doc trio vs runtime audit
§6  Terminology: Button group vs segmented button/control
§7  Native semantics decision tree
§8  Dependency profile / consumer-state
§9  v3.5.9 pill radius baseline inheritance
§10 Variant / size / content matrix
§11 Disabled-state split
§12 WordPress mapping stub
§13 Risks + dispositions
§14 Phase 1 entry conditions
§15 G1-G10 applicability
§16 Non-goals
§17 Verdict
```

---

## §14 — Expected Risks

Phase 0 should surface these risks:

```txt
Risk 1 — Runtime audit over-shaping
  Button group has interactive state, but likely no extracted runtime.
  Disposition: 3-doc trio unless Phase 0 discovers real runtime need.

Risk 2 — Terminology drift
  "Segmented button" could create duplicate ontology.
  Disposition: Button group remains canonical; segmented is mapping language.

Risk 3 — Split button bleed
  Split button shares connected-pill geometry but owns dropdown/popover semantics.
  Disposition: defer to row #7.

Risk 4 — Ripple promotion ambiguity
  Matrix currently lists Button group as ripple CANDIDATE.
  Disposition: Phase 0 should recommend TARGET bounded for segments, with
  Phase 2 implementation evidence.

Risk 5 — Icon-system overstatement
  Icon-only segments are valid, but not every segment has an icon.
  Disposition: CURRENT conditional.

Risk 6 — v3.5.9 token re-litigation
  Button group may tempt re-solving pill-stable.
  Disposition: inherit v3.5.9 as current baseline; do not alter token value.

Risk 7 — Disabled semantics ambiguity
  Native disabled vs aria-disabled can blur.
  Disposition: Button family Pattern A.
```

---

## §15 — G1-G10 Applicability

Expected gate set:

```txt
G1  validator PASS                         applicable
G2  baseline untouched during module work   applicable
G3  publish runs cleanly                    Phase 5 / mechanical
G4  module artifacts present                Phase 2+
G5  CHANGELOG entry                         Phase 5
G6  Static Visual QA                        Phase 3
G7  Principle 1                             applicable
G8  Principle 2                             applicable
G9  WCAG SC citations accurate              applicable
G10 3-doc audit pattern complete            applicable
```

G11-G16 are not expected unless Phase 0 discovers an extracted runtime
requirement.

---

## §16 — Non-Goals

Do not do these in v3.5.10 Phase 0:

```txt
- Create lab/modules/button-group/.
- Create BUTTON-GROUP-* audit docs.
- Edit components.css.
- Edit tokens.css.
- Change --md-sys-shape-corner-pill-stable.
- Reopen BACKLOG #31.
- Implement Button group Full-Spec.
- Implement Split button.
- Implement Toolbar.
- Add JavaScript.
- Edit theme.js.
- Edit style-guide.html.
- Edit CHANGELOG / ROADMAP / BACKLOG / CURRENT-STATE / NEXT-SESSION.
- Promote Button group ripple wiring before Phase 2.
```

---

## §17 — Validation Plan

For this plan-only step:

```powershell
python .\tools\validators\validate_theme_pilot.py
```

Expected:

```txt
1.000 / 1.000 / 1.000 / 1.000 PASS
```

Also verify:

```txt
Created:
  docs/v3.5.10/BUTTON-GROUP-PHASE-0-PLAN.md

Not created:
  lab/modules/button-group/
  BUTTON-GROUP-SPEC-AUDIT.md
  BUTTON-GROUP-MEASUREMENT-AUDIT.md
  BUTTON-GROUP-WP-MAPPING.md

Untouched:
  components.css
  tokens.css
  style-guide.html
  theme.json
  CHANGELOG.md
  ROADMAP.md
  BACKLOG.md
  CURRENT-STATE.md
  NEXT-SESSION.md
```

---

## §18 — Self-Check Summary

```txt
self-check:
  Component Full-Spec category           7 mentions
  3-doc trio / no RUNTIME-AUDIT          8 mentions
  Split button out-of-scope              8 mentions
  Button group vs segmented terminology  7 mentions
  v3.5.9 finite pill inheritance         10 mentions
  radio + label Pattern A                8 mentions
  button + aria-pressed Pattern B        6 mentions
  ripple TARGET/CANDIDATE decision       6 mentions
  icon-system conditional                3 mentions
  CURRENT-STATE / NEXT-SESSION untouched 2 mentions
```

