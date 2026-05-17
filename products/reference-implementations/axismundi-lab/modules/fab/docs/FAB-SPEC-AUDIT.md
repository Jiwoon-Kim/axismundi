# FAB Family — Spec Audit (v3.5.5 Phase 1)

> **Status**: Phase 1 audit body authored. Implementation not started.  
> **Component family**: FAB #3 + Extended FAB #4  
> **Category**: Component Full-Spec  
> **Primary Phase 0 source**: `docs/v3.5.5/FAB-PHASE-0-REPORT.md`  
> **Execution plan**: `docs/v3.5.5/FAB-PHASE-1-PLAN.md`  
> **Companions**: `FAB-MEASUREMENT-AUDIT.md`, `FAB-WP-MAPPING.md`

---

## §0 — Audit Status

This audit converts the v3.5.5 Phase 0 FAB family findings into the standard Component Full-Spec audit lane.

Phase 1 is documentation-only:

```txt
No lab-fab.css.
No lab-fab-pattern.html.
No lab-fab.js.
No baseline or public-surface edits.
```

Phase 0 settled the main family decision:

```txt
FAB #3 + Extended FAB #4 = one `fab/` module family.
```

---

## §1 — Scope And Source Inventory

Authoritative inputs:

```txt
docs/v3.5.5/FAB-PHASE-0-PLAN.md
docs/v3.5.5/FAB-PHASE-0-REPORT.md
docs/v3.5.5/FAB-PHASE-1-PLAN.md
docs/v3.5.0/MODULE-STATUS-MATRIX.md
docs/v3.5.0/PROMOTION-CRITERIA.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Baseline/public sources:

```txt
components.css §0  state-layer foundation
components.css §15 FAB
components.css §16 Extended FAB
style-guide.html #components-fab
style-guide.html #components-fab-extended
style-guide.html #components-fab-menu only as boundary evidence
```

Sibling precedents:

```txt
Icon button v3.5.2  icon-system CURRENT unconditional
Button v3.5.1       Pattern A disabled split and native button semantics
Card v3.5.3         behavior deferral and WP mapping discipline
Chip v3.4.9         original Component Full-Spec template
```

---

## §2 — Family-Merge Decision

Decision:

```txt
Create one FAB-family audit trio under modules/fab/docs/.
```

This SPEC covers both:

```txt
FAB primitive:
  icon-only action surface
  public anchor #components-fab

Extended FAB primitive:
  leading icon + visible label action surface
  public anchor #components-fab-extended
```

Rejected:

```txt
separate modules/fab/ and modules/extended-fab/
separate FAB-SPEC and EXTENDED-FAB-SPEC docs
```

Rationale:

```txt
The canonical matrix folds Extended FAB #4 into `fab/`.
Both members share native button semantics, elevation, color roles,
state-layer foundation, disabled model, icon dependency, and ripple
candidate status.

v3.5.6 update: Ripple v2 promotes FAB family from CANDIDATE to TARGET with
the unbounded variant. The v3.5.5 CANDIDATE wording below remains the
historical release decision; it is superseded by the later v3.5.6
infrastructure contract.
```

---

## §3 — Variant Matrix

### §3a — FAB Sizes

Baseline-present FAB sizes:

| Variant | Class | Container | Icon | Shape |
|---|---|---:|---:|---|
| Default | `.ax-fab` | 56px | 24px | large / 16px |
| Medium | `.ax-fab.is-medium` | 80px | 28px | large-increased / 20px |
| Large | `.ax-fab.is-large` | 96px | 36px | extra-large / 28px |

Phase 1 does not invent a 40px FAB rule. If M3 naming differs from Axismundi's current baseline naming, MEASUREMENT records the deviation; SPEC does not change implementation.

### §3b — FAB Surfaces

Baseline-present surface/color styles:

```txt
tonal primary default
tonal secondary
tonal tertiary
primary
secondary
tertiary
```

Each surface remains the same component primitive:

```txt
native <button>
icon-only accessible name required
state-layer Pattern A
elevation level3 rest / level4 hover
```

### §3c — Extended FAB Static Label-Bearing Variant

Baseline-present Extended FAB:

```txt
height: 56px
min-width: 80px
leading icon: 24px
visible label: title-medium
gap: 8px
padding: 16px inline
```

Extended FAB is static in v3.5.5:

```txt
No collapse/expand behavior.
No scroll-responsive label transition.
No toolbar choreography.
```

---

## §4 — Native Semantics And Accessible-Name Contract

This section applies Principle 1 and Principle 2 to the FAB family:

```txt
Principle 1:
  visible controls must map to real runtime controls.

Principle 2:
  native semantics beat recreated ARIA controls.
```

Canonical FAB:

```html
<button class="ax-fab" type="button" aria-label="Compose">
  <span class="material-symbols-rounded notranslate ax-fab-icon"
        translate="no"
        aria-hidden="true"
        draggable="false">edit</span>
</button>
```

Canonical Extended FAB:

```html
<button class="ax-fab-extended" type="button">
  <span class="material-symbols-rounded notranslate ax-fab-extended__icon"
        translate="no"
        aria-hidden="true"
        draggable="false">edit</span>
  <span class="ax-fab-extended__label">Compose</span>
</button>
```

Required:

```txt
FAB icon-only controls need aria-label or equivalent accessible name.
Extended FAB needs a visible label.
Icon glyphs are decorative and aria-hidden.
```

Forbidden:

```txt
<div role="button" class="ax-fab">
<span class="ax-fab">
icon-only FAB without accessible name
Extended FAB without visible label
```

Navigation links styled as FAB are not included in this static primitive unless WP-MAPPING later carves a narrow navigation exception.

---

## §5 — Disabled: Native Disabled

Native disabled specimens:

```html
<button class="ax-fab" type="button" aria-label="Compose" disabled>
  ...
</button>

<button class="ax-fab-extended" type="button" disabled>
  ...
</button>
```

Native disabled contract:

```txt
platform blocks activation
visual disabled style applies
state-layer pseudo-element hidden
elevation level0
```

This follows Button/Icon button Pattern A, not Card Pattern B.

---

## §5a — Disabled: Aria-Disabled Plugin-Managed

Plugin-managed disabled specimens:

```html
<button class="ax-fab" type="button" aria-label="Compose" aria-disabled="true">
  ...
</button>
```

Contract:

```txt
aria-disabled communicates state.
aria-disabled does not block activation.
theme, editor, or plugin code must guard click/keyboard behavior.
```

Phase 2 pattern captions must state this distinction in user-facing text.

---

## §6 — State-Layer And Ripple Consumer-State

Static state-layer:

```txt
components.css §0 / Pattern A = CURRENT
```

Current FAB baseline implements:

```txt
::before overlay
currentColor
hover / focus / pressed opacity tokens
disabled pseudo-element hidden
```

Animated ripple:

```txt
ripple/ = CANDIDATE
```

Do not classify FAB as ripple TARGET in v3.5.5.

v3.5.6 Ripple v2 alignment note: this v3.5.5 constraint is superseded by Ripple v2.
FAB and Extended FAB are now ripple TARGET consumers using the unbounded
variant via the stable `data-ax-ripple` + `window.axRipple` contract.

Rationale:

```txt
v3.5.4 matrix amendment deliberately placed FAB #3 + Extended FAB #4
in the CANDIDATE bucket. Ripple v2 (#25) and data-ax-ripple opt-in (#27)
are the correct venue for promotion.
```

---

## §7 — Icon-System Dependency Contract

Dependency state:

```txt
icon-system/ = CURRENT unconditional
```

Reason:

```txt
FAB is an icon-bearing action surface by definition.
Extended FAB has a leading icon slot in the current Axismundi family model.
```

Current public gap:

```txt
style-guide.html specimens currently use inline SVG.
components.css supports both direct svg and .ax-icon style hooks.
```

Target audit pattern:

```txt
Use Material Symbols / icon-system-forward snippets in FAB audit examples.
Record public SVG specimen cleanup as a potential future cleanup, not a
Phase 1 edit.
```

---

## §8 — Elevation And Shape Semantics

FAB baseline elevation:

```txt
rest:  level3
hover: level4
focus: level3
active: level3
disabled: level0
```

Shape:

```txt
default FAB: 16px
medium FAB: 20px
large FAB: 28px
Extended FAB: 16px
```

Elevation tokens are part of the baseline token graph. They are not a separate infrastructure provider module.

---

## §9 — Extended FAB Behavior Deferral

Out of scope:

```txt
Extended FAB collapse/expand
auto-hide on scroll
FAB-to-FAB-menu transition
toolbar floating-with-FAB choreography
modal/sheet morph behavior
```

Disposition:

```txt
v3.5.5 closes the static primitive first.
Behavior-heavy patterns should be routed like Card #29 if they become a
future release candidate.
```

Static catalog caption requirement:

```txt
State or behavior-looking specimens are fixed examples, not live toggles.
Production behavior belongs at theme, editor, or plugin integration level.
```

---

## §10 — WordPress / M3 Boundary Summary

FAB has native button semantics, but WordPress mapping is not equivalent to ordinary `core/button`.

Boundary:

```txt
core/button:
  shares button semantics, weak visual/source match.

pattern composition:
  plausible for theme-owned CTA surfaces.

template part:
  plausible for theme placement.

custom block/plugin:
  preferred for app-like floating action behavior.
```

No WordPress block style, PHP, or plugin code is changed by this audit.

---

## §11 — Release Verdict Criteria

| # | Criterion | Status | Notes |
|---:|---|:---:|---|
| 1 | M3/FAB family spec coverage | PASS (Phase 5 closed) | Phase 2 artifacts cover FAB sizes, surface variants, static Extended FAB, disabled Pattern A, state-layer opt-out, and code snippets. Phase 3 visual QA passed. |
| 2 | Token-driven implementation | PASS (Phase 5 closed) | `lab-fab.css` consumes existing baseline/system tokens and only adds lab-scoped visualization rules, including the Material Symbols glyph-size bridge found during visual QA. |
| 3 | Pattern HTML completeness | PASS (Phase 2 closed) | `lab-fab-pattern.html` created with 8 sections: FAB sizes, surface variants, Extended FAB static, native disabled, aria-disabled plugin-managed, state-layer/ripple boundary, code snippets, and cross-references. |
| 4 | Audit doc completeness | PASS | SPEC + MEASUREMENT + WP-MAPPING bodies authored in Phase 1. |
| 5 | Dependency declarations | PASS | state-layer CURRENT, icon-system CURRENT unconditional, ripple CANDIDATE at v3.5.5 and TARGET after v3.5.6 Ripple v2, elevation token graph recorded. |

Release close:

```txt
Phase 2 implementation: PASS
Phase 3 Static Visual QA: PASS (user-verified)
Phase 5 mechanical close: PASS
v3.5.5 closes FAB #3 + Extended FAB #4 as one static FAB family primitive.
Extended FAB behavior patterns remain deferred to BACKLOG #30.
```

---

## §12 — G1-G10 Applicability

| Gate | Status | FAB Phase 1 note |
|---|---|---|
| G1 Validator 1.000 | PASS | Final validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS. |
| G2 Baseline untouched | PASS | No baseline/public CSS or style-guide edits. |
| G3 Publish runs cleanly | N/A for lab-only artifact | v3.5.5 does not require publish-surface mutation; publish tooling remains available after v3.5.x fixes. |
| G4 Module artifacts present | PASS | `lab-fab.css` + `lab-fab-pattern.html` created. No `lab-fab.js`. |
| G5 CHANGELOG | PASS | v3.5.5 entry added during Phase 5. |
| G6 Static Visual QA | PASS | User-verified after the lab-scoped Material Symbols size bridge fix. |
| G7 Principle 1 | PASS | Pattern HTML uses real visible controls, not fake role-based controls. |
| G8 Principle 2 | PASS | FAB specimens use native `<button type="button">` semantics. |
| G9 WCAG SC accuracy | PASS in MEASUREMENT | Current dimensions documented. |
| G10 3-doc audit pattern | PASS | This trio completes Phase 1 audit body. |

---

## §13 — References

Companions:

```txt
FAB-MEASUREMENT-AUDIT.md
FAB-WP-MAPPING.md
```

Phase docs:

```txt
../../../../../docs/v3.5.5/FAB-PHASE-0-REPORT.md
../../../../../docs/v3.5.5/FAB-PHASE-1-PLAN.md
../../../../../docs/v3.5.5/PRE-ENTRY-ONTOLOGY-GROUNDING.md
```

Precedents:

```txt
../../icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
../../button/docs/BUTTON-SPEC-AUDIT.md
../../card/docs/CARD-SPEC-AUDIT.md
../../chip/docs/CHIP-SPEC-AUDIT.md
```

---

## §14 — What This Audit Does NOT Do

This audit does not:

```txt
create lab-fab.js
edit components.css
edit style-guide.html
edit tokens.css
replace public inline SVG specimens
implement icon-system cleanup
promote ripple to TARGET
implement Ripple v2
add data-ax-ripple
implement Extended FAB collapse/expand
implement FAB menu
implement Toolbar integration
register WordPress block styles
edit CURRENT-STATE.md
edit NEXT-SESSION.md
```
