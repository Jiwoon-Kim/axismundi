# Axismundi v3.5.2 — Icon button #2 Phase 0 Report

> **Status**: Phase 0 inventory and ontology framing.
> **Date**: 2026-05-16
> **Scope**: Documentation-only entry report for Wave 1 Icon button #2.
> **Non-scope**: No baseline edits, no `lab/modules/icon-button/` creation, no ripple v2 work, no publish-surface change.

---

## §0 — Phase 0 Framing

v3.5.2 enters Wave 1 with **Icon button #2** as the second Component Full-Spec item after v3.5.1 Button #1.

The component is not a simple replay of Button #1:

```txt
Button #1
  icon-system/ = CURRENT conditional
  Reason: only icon-slot specimens need the icon runtime.

Icon button #2
  icon-system/ = CURRENT unconditional
  Reason: the icon is the component body.
```

This distinction is the main ontology test for Phase 0. Button's "icon slot" becomes Icon button's "icon container". The component remains distinct from `icon-system/`, but it is more tightly coupled to that infrastructure than Button was.

Phase 0 also records an ownership issue surfaced by the previous runtime work:

```txt
Current file:
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md

Question:
  Should Icon button's prior runtime audit remain owned by icon-system/,
  or move/integrate into a future icon-button/ docs directory?
```

This report recommends moving or integrating that audit under Icon button ownership in a later approved phase, while preserving cross-references to `icon-system/`.

---

## §1 — Authoritative Inputs

Phase 0 read these inputs as canonical or reference material:

```txt
v3.5.0 framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md
  docs/v3.5.0/COMPONENT-COVERAGE-MAP.md

Baseline and public specimens:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §3
  products/reference-implementations/axismundi-lab/style-guide.html #components-icon-button

Existing icon-system references:
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-SYSTEM-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-FONT-POLICY.md
  products/reference-implementations/axismundi-lab/modules/icon-system/docs/INLINE-SVG-INVENTORY.md

Sibling Component Full-Spec precedents:
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/button/docs/BUTTON-WP-MAPPING.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-SPEC-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-MEASUREMENT-AUDIT.md
  products/reference-implementations/axismundi-lab/modules/chip/docs/CHIP-WP-MAPPING.md
```

Canonical framework classification from v3.5.0:

```txt
Component:        Icon button #2
TOC group:        Actions
Category:         Component Full-Spec
Current status:   PARTIAL
Existing coverage: partially covered by icon-system/
Target module:    icon-button/
Dependency type:  Consumer
Known deps:       ripple/, icon-system/
Wave:             Wave 1
```

---

## §2 — Baseline Inventory

### §2.1 — `components.css §3 Icon button`

Current baseline owns the visual and interaction baseline for `.ax-icon-button`:

```txt
Selector family:
  .ax-icon-button
  .ax-icon-button > svg
  .ax-icon-button > .ax-icon
  .ax-icon-button.is-filled
  .ax-icon-button.is-tonal
  .ax-icon-button.is-outlined
  .ax-icon-button.is-standard
  .ax-icon-button[aria-pressed="true"]
  .ax-icon-button:disabled
  .ax-icon-button[aria-disabled="true"]
```

Observed baseline shape:

```txt
Container:
  - inline-grid
  - 40px nominal width/height via --comp-button-height
  - 48px min touch target via --comp-touch-target
  - full corner radius
  - borderless default
  - state-layer host via components.css §0

Variants:
  - filled
  - tonal
  - outlined
  - standard

Toggle:
  - aria-pressed="true" states for filled/tonal/outlined/standard

Disabled:
  - native :disabled
  - aria-disabled="true"
  - standard variant has transparent background exception
```

Baseline appears sufficient as the source visual contract for Phase 1 audit and Phase 2 lab specimens. Phase 0 does not authorize baseline mutation.

### §2.2 — `style-guide.html #components-icon-button`

The public specimen section contains:

```txt
Variants:
  4 buttons
  - filled
  - tonal
  - outlined
  - standard

Toggle:
  4 buttons
  - filled / tonal / outlined / standard
  - aria-pressed="false"
  - class sg-toggle

Disabled:
  4 buttons
  - native disabled
  - filled / tonal / outlined / standard
```

All section specimens are real native `<button>` elements with `type="button"`, `aria-label`, `.has-state-layer`, and a hidden glyph span:

```html
<span class="material-symbols-rounded notranslate"
      translate="no"
      aria-hidden="true"
      draggable="false">add</span>
```

This is already consistent with the icon-font policy at runtime.

### §2.3 — Baseline/Public Mismatches To Audit

Phase 0 found two stale documentation or selector-shape gaps:

```txt
Mismatch A — snippet text:
  style-guide.html snippet still shows <svg ...></svg> for Icon button.
  Actual specimens use Material Symbols spans.

Mismatch B — toggle helper wording:
  helper text still describes authors updating SVG fill/d on toggle.
  Actual specimens use Material Symbols spans and aria-pressed state.
```

There is also a selector-shape point that Phase 1 must settle:

```txt
components.css glyph size selector:
  .ax-icon-button > svg,
  .ax-icon-button > .ax-icon

style-guide.html actual glyph:
  .ax-icon-button > .material-symbols-rounded.notranslate

icons.css may already provide Material Symbols base sizing/hardening.
Phase 1 must decide whether this is:
  - fully covered by icon-system/icons.css,
  - a documentation-only mismatch,
  - or a future baseline amendment candidate.
```

Phase 0 does not change any of these. It routes them to Phase 1 audit.

---

## §3 — Existing Partial Coverage

`ICON-BUTTON-RUNTIME-AUDIT.md` is valuable but not sufficient for Component Full-Spec closure.

What it already covers:

```txt
Release:     v3.4.3
Location:    modules/icon-system/docs/
Scope:       40 ax-icon-button instances in style-guide.html
Main work:   inline SVG -> Material Symbols glyph spans
CSS touch:   icon hardening rules in icons.css §1
Non-work:    no canonical ax-icon-button CSS changes
```

Important findings preserved from that audit:

```txt
- Parent button keeps class list, type, and aria-label.
- Glyph becomes aria-hidden.
- Glyph hardening ensures pointer events pass through to parent button.
- State-layer machinery remains owned by the parent .ax-icon-button.
- Runtime conversion does not make icon-system/ the component owner.
```

Why this is only partial:

```txt
It is a runtime/conversion audit, not a full Component Full-Spec audit.
It does not produce:
  - ICON-BUTTON-SPEC-AUDIT.md
  - ICON-BUTTON-MEASUREMENT-AUDIT.md
  - ICON-BUTTON-WP-MAPPING.md
  - lab-icon-button.css
  - lab-icon-button-pattern.html
  - release-level G1-G10 closeout for Icon button #2
```

Phase 1 must treat the existing runtime audit as a reference, not as a replacement for the three-doc audit pattern.

---

## §4 — Dependency Profile

### §4.1 — Consumer-State Table

```txt
Dependency                                           State
--------------------------------------------------   ----------------------
components.css §0 state-layer foundation             CURRENT
components.css §3 Icon button baseline               CURRENT
lab/modules/icon-system/                             CURRENT unconditional
lab/modules/ripple/                                  TARGET, deferred
```

### §4.2 — State-Layer Foundation

Icon button already uses the static CSS state-layer foundation:

```txt
.ax-icon-button.has-state-layer
  -> components.css §0 pseudo-element state layer
  -> hover/focus/pressed static tint
```

This is CURRENT and baseline-owned.

### §4.3 — Icon-System Dependency

Icon button is the first Wave 1 component where `icon-system/` is not optional:

```txt
Button:
  icon-system/ is needed only when an icon slot is present.

Icon button:
  icon-system/ is always needed because the visual content is the icon.
```

Ownership split:

```txt
icon-button/ owns:
  - host button semantics
  - variant states
  - selected/unselected state
  - disabled treatment
  - touch target/container geometry
  - WordPress block mapping for icon-only controls

icon-system/ owns:
  - Material Symbols loading/policy
  - SVG fallback policy
  - glyph hardening
  - glyph naming and picker guidance
  - infrastructure rules shared by Button, FAB, Menu, App bar, Nav bar, etc.
```

This is `DISTINCT but COUPLED` with a stronger coupling than Button.

### §4.4 — Ripple Dependency

Icon button is a valid TARGET consumer of `ripple/`.

Button v3.5.1 settled Option (b):

```txt
Do not wire current Beer-CSS-derived ripple into Wave 1 components.
Defer animated ripple wiring to a future Ripple v2 / Material Web alignment release.
```

That decision applies here unless explicitly reopened.

Phase 1 should declare:

```txt
ripple/ = TARGET enhancement, not baseline-wired, deferred to BACKLOG #25.
Do not remove Icon button from ripple/ consumer graph.
Do not classify Icon button as NOT ripple-dependent.
```

---

## §5 — Button #1 vs Icon Button #2

Button #1 established the Wave 1 Component Full-Spec pattern:

```txt
Phase 0     inventory + dependency classification
Phase 1     SPEC / MEASUREMENT / WP-MAPPING audit docs
Phase 2     lab CSS + pattern HTML
Phase 3     static visual QA
Phase 5     release close
```

Icon button should reuse that shape with one ontology correction:

```txt
Button #1:
  primary content = text label
  icon = optional slot
  icon-system/ = CURRENT conditional

Icon button #2:
  primary content = icon
  label = accessible name, not visible label
  icon-system/ = CURRENT unconditional
```

This changes the audit focus:

```txt
Button audit asks:
  Does the component handle optional icon slots correctly?

Icon button audit asks:
  Does the component remain a semantic native button while delegating
  glyph rendering to icon-system/?
```

---

## §6 — Risks And Open Questions

### Risk 1 — Unconditional Icon-System Dependency

Icon button must not be absorbed into `icon-system/`.

```txt
Failure mode:
  icon-system/ becomes the component owner because it already has
  ICON-BUTTON-RUNTIME-AUDIT.md.

Correct framing:
  icon-system/ is infrastructure.
  Icon button is the consumer component.
```

Disposition: Phase 1 dependency section must mark `icon-system/` as CURRENT unconditional while preserving component ownership.

### Risk 2 — Existing Runtime Audit Is Partial

The runtime audit is real work but not a Component Full-Spec substitute.

Disposition: Phase 1 should cross-reference it as historical/conversion evidence and then create the standard 3-doc audit set.

### Risk 3 — Glyph Selector Shape

Baseline `components.css §3` targets `> svg` and `> .ax-icon`; current specimens use direct `.material-symbols-rounded`.

Disposition: Phase 1 must inspect `icons.css` and decide whether direct Material Symbols sizing/hardening is fully covered or whether a future baseline amendment is needed.

### Risk 4 — Stale SVG Snippet/Wording

The Icon button public specimen still includes SVG-era snippet/helper language.

Disposition: Phase 1 must record this as a public-specimen documentation mismatch. Any actual `style-guide.html` correction requires explicit later approval.

### Risk 5 — Runtime Audit Ownership Location

Current runtime audit location:

```txt
lab/modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
```

Phase 0 options:

```txt
(a) Move or integrate it under lab/modules/icon-button/docs/
    Recommended. Icon button owns its component audits.

(b) Keep it under icon-system/ and cross-reference from icon-button docs.
    Preserves history but keeps awkward ownership.

(c) Keep runtime audit in icon-system/ and create full-spec docs under icon-button/.
    Clean for new work, but splits component evidence across two modules.
```

Recommendation: **(a), after explicit approval**. Phase 0 does not move files. Phase 1 should decide whether to copy/integrate the runtime audit into `icon-button/docs/` or reference it while scheduling migration.

### Risk 6 — Size Variant Scope

Baseline appears to expose a default-size Icon button. Phase 1 must compare against M3 icon button sizing expectations and decide whether XS/S/M/L/XL variants are in scope or deferred.

Disposition: follow Button #1's conservative pattern unless the baseline already has explicit multi-size support.

### Risk 7 — Disabled Semantics Split

Baseline supports both native `:disabled` and `[aria-disabled="true"]`.

Disposition: Phase 1 and Phase 2 should separate:

```txt
Native disabled:
  <button disabled>

Plugin-managed inert:
  <button aria-disabled="true"> with JS/event policy caveat
```

This follows the Button v3.5.1 plan refinement.

### Risk 8 — Ripple Deferral

Icon button is in ripple's current target graph/allowlist but should not wire current ripple during v3.5.2.

Disposition: declare TARGET dependency, defer implementation to Ripple v2 / BACKLOG #25.

---

## §7 — Phase 1 Entry

Phase 1 should create the standard Component Full-Spec audit docs under a future Icon button docs directory:

```txt
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-SPEC-AUDIT.md
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-MEASUREMENT-AUDIT.md
products/reference-implementations/axismundi-lab/modules/icon-button/docs/ICON-BUTTON-WP-MAPPING.md
```

Phase 1 may create the `icon-button/docs/` directory if the user approves Phase 1 authoring. Phase 0 does not create it.

Required Phase 1 content:

```txt
SPEC:
  - M3 icon button variants
  - selected/unselected state
  - disabled split
  - accessible name / visible icon contract
  - dependency profile with icon-system/ CURRENT unconditional

MEASUREMENT:
  - container size
  - touch target
  - icon size
  - border/stroke for outlined
  - selected/unselected tokens
  - disabled opacity/token behavior

WP-MAPPING:
  - core/button icon-only mapping
  - block toolbar icon button mapping
  - social/action icon button mapping
  - icon picker and accessible label requirements
  - plugin-managed aria-disabled caveat
```

Phase 1 must also settle what to do with `ICON-BUTTON-RUNTIME-AUDIT.md`:

```txt
Preferred outcome:
  Integrate or migrate the runtime audit into icon-button/docs/
  while preserving a cross-reference from icon-system/ docs.
```

---

## §8 — G1-G10 Applicability

Icon button remains a Component Full-Spec item, so the same G1-G10 gates apply.

```txt
G1 validator passes                 applicable
G2 baseline untouched               applicable
G3 publish runs cleanly             applicable
G4 module artifacts present         applicable after Phase 2
G5 CHANGELOG entry                  applicable at Phase 5
G6 Static Visual QA                 applicable after Phase 2
G7 real controls                    applicable, must be native <button>
G8 native semantics                 applicable, must preserve type/aria-label
G9 WCAG SC accuracy                 applicable
G10 3-doc audit pattern             applicable at Phase 1
```

Additional Icon button-specific gate emphasis:

```txt
G7/G8:
  Icon-only controls must still expose accessible names via aria-label or
  equivalent visible/hidden labeling. The glyph itself remains aria-hidden.

G10:
  ICON-BUTTON-RUNTIME-AUDIT.md is not enough. The 3-doc pattern is still required.
```

---

## §9 — Explicit Non-Goals

Phase 0 does not:

```txt
- edit components.css
- edit style-guide.html
- edit icons.css
- edit theme.json
- edit CHANGELOG.md
- edit ROADMAP.md
- edit CURRENT-STATE.md
- edit NEXT-SESSION.md
- create lab/modules/icon-button/
- move ICON-BUTTON-RUNTIME-AUDIT.md
- create lab-icon-button.css
- create lab-icon-button-pattern.html
- implement ripple v2
- wire current lab/modules/ripple/ into Icon button
- perform public SVG snippet cleanup
- perform icon-system refactor
- perform Lab Preview Routes work
```

---

## §10 — Phase 0 Verdict

```txt
Icon button #2 is ready for v3.5.2 Phase 1 planning.

Classification:
  Component Full-Spec

Current status:
  PARTIAL, because icon-system/ contains a prior runtime conversion audit.

Dependency profile:
  components.css §0 state-layer foundation   CURRENT
  components.css §3 icon-button baseline     CURRENT
  icon-system/                               CURRENT unconditional
  ripple/                                    TARGET, deferred to Ripple v2

Main Phase 1 question:
  Move/integrate ICON-BUTTON-RUNTIME-AUDIT.md under icon-button/docs/
  or keep it as a cross-reference from icon-system/.

Recommendation:
  Use option (a): Icon button owns its component audits; icon-system/
  remains the glyph/runtime infrastructure provider.
```

One-line summary:

```txt
v3.5.2 Icon button Phase 0 validates the component as the next Wave 1
Component Full-Spec item, with icon-system/ upgraded from Button's
conditional dependency to Icon button's unconditional dependency and
runtime-audit ownership recorded as the key Phase 1 ontology decision.
```
