# Icon button — Spec Audit (v3.5.2 Phase 1)

> **Status**: v3.5.2 release closed. Phase 2 artifacts authored; Phase 3 visual QA PASS; Phase 5 mechanical close DONE.
> **Component**: Icon button #2
> **Category**: Component Full-Spec
> **Primary Phase 0 source**: `docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md`
> **Execution plan**: `docs/v3.5.2/ICON-BUTTON-PHASE-1-PLAN.md`

---

## §0 — Status / Scope

This audit promotes Icon button from v3.5.0 `PARTIAL` status into a Component Full-Spec audit lane.

The component is distinct from Button #1 because its visual body is always an icon:

```txt
Button #1:
  icon-system/ = CURRENT conditional
  reason: icons are optional slots

Icon button #2:
  icon-system/ = CURRENT unconditional
  reason: the icon is the component body
```

This document is documentation-only. It creates no CSS, HTML pattern, JavaScript, public specimen update, or runtime migration.

---

## §1 — Authoritative Inputs

```txt
Phase reports:
  docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md
  docs/v3.5.2/ICON-BUTTON-PHASE-1-PLAN.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md
  docs/v3.5.0/33-COMPONENT-INVENTORY.md

Baseline/public:
  products/reference-implementations/axismundi-lab/stylesheets/components.css §0 + §3
  products/reference-implementations/axismundi-lab/stylesheets/icons.css §1 + §5
  products/reference-implementations/axismundi-lab/style-guide.html #components-icon-button

Existing icon-system references:
  ../icon-system/docs/ICON-SYSTEM-AUDIT.md
  ../icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
  ../icon-system/docs/ICON-FONT-POLICY.md
  ../icon-system/docs/INLINE-SVG-INVENTORY.md

Sibling precedents:
  ../button/docs/BUTTON-SPEC-AUDIT.md
  ../button/docs/BUTTON-MEASUREMENT-AUDIT.md
  ../button/docs/BUTTON-WP-MAPPING.md
  ../chip/docs/CHIP-SPEC-AUDIT.md
  ../chip/docs/CHIP-MEASUREMENT-AUDIT.md
  ../chip/docs/CHIP-WP-MAPPING.md
```

---

## §2 — Baseline Inventory

### §2.1 — `components.css §3`

Baseline selector family:

```txt
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

Current baseline contract:

```txt
container:
  width/height:        --comp-button-height (40px)
  min-width/min-height --comp-touch-target (48px)
  display:             inline-grid
  radius:              full
  user-select:         none

state layer:
  .has-state-layer + components.css §0

variants:
  filled
  tonal
  outlined
  standard

toggle:
  aria-pressed="true" selected-state selectors

disabled:
  native :disabled
  aria-disabled="true"
  standard variant transparent background exception
```

### §2.2 — `icons.css §1 + §5`

Phase 0 flagged a possible selector gap because `components.css §3` sizes `> svg` and `> .ax-icon`, while the public specimens use direct Material Symbols spans.

Phase 1 resolves this as covered:

```txt
icons.css §1:
  .material-symbols-rounded {
    font-size: 24px;
    user-select: none;
    -webkit-user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
  }

icons.css §5:
  .ax-icon-button > .material-symbols-rounded {
    font-size: 24px;
    line-height: 1;
  }
```

Conclusion:

```txt
No baseline amendment is required for direct Material Symbols spans.
The glyph selector shape is owned by icon-system/ integration.
```

### §2.3 — Public Specimens

`style-guide.html #components-icon-button` contains:

```txt
4 variant specimens:
  filled / tonal / outlined / standard

4 toggle specimens:
  same variants with aria-pressed="false"

4 native disabled specimens:
  same variants with disabled
```

All visible specimens use native `<button type="button">`, an `aria-label`, `.has-state-layer`, and an aria-hidden Material Symbols glyph span.

Known public specimen mismatch:

```txt
The code snippet/helper wording still contains SVG-era language.
Candidate routing: BACKLOG #28 — Icon button public specimen SVG wording cleanup.
```

This audit records the mismatch but does not edit `style-guide.html`.

---

## §3 — Variant And State Coverage

| Area | Current coverage | Phase 1 verdict |
|---|---|---|
| Filled icon button | Baseline + specimen | PASS |
| Tonal icon button | Baseline + specimen | PASS |
| Outlined icon button | Baseline + specimen | PASS |
| Standard icon button | Baseline + specimen | PASS |
| Toggle selected/unselected | `aria-pressed` selectors + specimens | PASS |
| Native disabled | `:disabled` selector + specimens | PASS |
| `aria-disabled` | selector exists, no separate public specimen | Document separately in Phase 2 plan |
| Static state-layer | `has-state-layer` + §0 | PASS |
| Animated ripple | TARGET only | Deferred |

Coverage note:

```txt
Icon button's baseline is smaller than Button's variant space:
  4 variants, no label, no elevated/text/bare variants.
```

---

## §4 — Icon-System Dependency Contract

Icon button uses `icon-system/` as a CURRENT unconditional dependency.

Ownership split:

```txt
icon-button/ owns:
  - host control semantics
  - icon button variants
  - selected/unselected state
  - disabled state policy
  - touch target/container geometry
  - WordPress icon-only control mapping

icon-system/ owns:
  - Material Symbols loading and policy
  - SVG fallback policy
  - glyph hardening
  - glyph picker/naming guidance
  - shared glyph rules consumed by Button, FAB, Menu, App bar, Nav bar, etc.
```

This is the strongest `DISTINCT but COUPLED` case in Wave 1 so far.

Dependency profile:

> **v3.5.4 matrix amendment note**: this section is now aligned with
> the canonical consumer-state vocabulary introduced in
> `docs/v3.5.0/MODULE-STATUS-MATRIX.md`: `icon-system/` = CURRENT
> unconditional and `ripple/` = TARGET.
>
> **v3.5.6 Ripple v2 alignment note**: Icon button remains a ripple TARGET
> consumer with the unbounded variant. The stable animated ripple contract is
> `data-ax-ripple` + `window.axRipple`; it remains a progressive enhancement
> above `components.css §0` and does not change the Icon button baseline.

```txt
components.css §0 state-layer foundation   CURRENT
components.css §3 icon-button baseline     CURRENT
icon-system/                               CURRENT unconditional
ripple/                                    TARGET, deferred to Ripple v2
```

---

## §5 — Runtime Audit Migration Disposition

Current historical audit:

```txt
products/reference-implementations/axismundi-lab/modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
```

Phase 1 disposition:

```txt
The runtime audit is canonical historical evidence, but not the owner of
the Component Full-Spec audit lane.

Icon button owns the component audit trio from v3.5.2 onward.
```

Preferred future migration, pending explicit approval:

```txt
Move or copy:
  modules/icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
    -> modules/icon-button/docs/ICON-BUTTON-RUNTIME-AUDIT.md

Then leave either:
  - a stub in icon-system/docs/, or
  - updated cross-references from icon-system docs to icon-button/docs/
```

Phase 1 does **not** move, copy, or stub the file.

---

## §6 — Ripple TARGET Deferral

Icon button is a valid target consumer of `ripple/`, but current ripple wiring remains deferred.

```txt
State:
  ripple/ = TARGET enhancement, not baseline-wired

Decision inherited from Button v3.5.1:
  Option (b): do not bind Wave 1 components to the current Beer-CSS-derived
  ripple. Defer animated ripple to Ripple v2 / Material Web alignment.

Related scheduled work:
  BACKLOG #25 — Ripple v2 contract
  BACKLOG #27 — data-ax-ripple opt-in introduction
```

Do not remove Icon button from the ripple consumer graph. Do not classify it as `NONE`.

---

## §7 — Exceptions And Deferred Items

| Item | Status | Disposition |
|---|---|---|
| XS / S / M / L / XL size expansion | Not in current baseline | Deferred; default-size-only for v3.5.2 |
| Animated ripple | Current ripple is not Wave 1 contract | Deferred to Ripple v2 |
| `aria-disabled` specimen | Selector exists; no public specimen | Phase 2 pattern should add separate plugin-managed specimen |
| SVG-era public snippet | Real mismatch | Candidate BACKLOG #28; no Phase 1 edit |
| Runtime audit migration | Ownership decision made | Future approved move/copy/stub |

---

## §8 — M3 Spec Coverage And Icon-Button Semantics

Icon button's current baseline covers the core M3 shape needed for Wave 1:

```txt
4 variants:
  filled
  tonal
  outlined
  standard

toggle state:
  selected/unselected via aria-pressed

icon-only semantics:
  visible glyph + programmatic accessible name

state layer:
  static CSS state-layer foundation via .has-state-layer
```

The audit intentionally keeps WordPress mapping details in `ICON-BUTTON-WP-MAPPING.md`. This section covers component/spec semantics only.

---

## §9 — Phase 2 Readiness Checklist

Phase 2 can plan implementation after Phase 1 review if:

```txt
✓ SPEC, MEASUREMENT, and WP-MAPPING docs exist
✓ icon-system/ is declared CURRENT unconditional
✓ ripple/ is declared TARGET/deferred
✓ default-size-only scope is explicit
✓ native disabled and aria-disabled are split
✓ runtime audit migration disposition is recorded
✓ BACKLOG #28 is recorded
✓ no baseline/public files were edited
```

---

## §10 — SPEC Verdict Criteria (Phase 5 close, ALL PASS)

| # | Criterion | Phase 5 verdict | Notes |
|---:|---|:---:|---|
| 1 | M3 icon button coverage | PASS | 4 variants + selected/unselected state + native disabled + aria-disabled plugin-managed contract + state-layer recorded |
| 2 | Token-driven implementation | PASS | Container, touch target, glyph size, state layer, and variant colors consume existing baseline/icon-system tokens; no new system tokens |
| 3 | Pattern HTML completeness | PASS | `lab-icon-button-pattern.html` authored with 8 sections, 21 native buttons, 0 missing `type=`, 0 missing accessible names |
| 4 | Phase 2 artifact completeness | PASS | `lab-icon-button.css` + `lab-icon-button-pattern.html` authored; no JS created |
| 5 | Dependency declarations | PASS | `icon-system/` CURRENT unconditional; `ripple/` TARGET; runtime audit migration disposition recorded |
| 6 | Static Visual QA | PASS | User-verified after Phase 2; 0 blocking visual issues reported |

Phase 5 release close:

```txt
v3.5.2 Wave 1 — Icon button #2 closes as DONE.
Baseline/public files remain unchanged.
Runtime audit migration remains future bookkeeping.
BACKLOG #28 records stale public SVG snippet cleanup.
```

---

## §10a — Visible Control Principle And Native Semantics

Principle 1 — visible control:

```txt
Icon button is visually present through its glyph, but its accessible name
does not come from visible label text. Therefore aria-label, aria-labelledby,
or an equivalent naming mechanism is mandatory.
```

Failure examples:

```txt
<button class="ax-icon-button"><span class="material-symbols-rounded">search</span></button>
```

Correct pattern:

```html
<button class="ax-icon-button is-standard has-state-layer" type="button" aria-label="Search">
  <span class="material-symbols-rounded notranslate" translate="no" aria-hidden="true" draggable="false">search</span>
</button>
```

Principle 2 — native semantics:

```txt
Actions use <button type="button">.
Navigation may use <a>, but only when the control is truly a link.
Do not use <div role="button"> or <span class="ax-icon-button">.
```

---

## §11 — G1-G10 Gate Applicability

| Gate | Applies? | Phase 5 state |
|---|:---:|---|
| G1 validator passes | yes | PASS — 1.000 / 1.000 / 1.000 / 1.000 |
| G2 baseline untouched | yes | PASS — components.css / icons.css / style-guide.html unchanged |
| G3 publish runs cleanly | yes | N/A for Phase 5 close — no publish-surface mutation in v3.5.2 |
| G4 module artifacts present | yes | PASS — `lab-icon-button.css` + `lab-icon-button-pattern.html` |
| G5 changelog entry | yes | PASS — v3.5.2 entry added |
| G6 static visual QA | yes | PASS — user-verified |
| G7 real controls | yes | PASS — 21 native `<button>` specimens; no div/span role-button controls |
| G8 native semantics | yes | PASS — all rendered buttons have explicit `type="button"` and accessible names |
| G9 WCAG SC accuracy | yes | PASS — MEASUREMENT cites SC 2.5.8 AA + SC 2.5.5 AAA, both met by 48px target |
| G10 3-doc audit pattern | yes | PASS — SPEC + MEASUREMENT + WP-MAPPING complete |

G11-G26 do not apply directly: Icon button is a Component Full-Spec consumer, not an Interaction Runtime, Record, Plugin, or Infrastructure provider.

---

## §12 — References

```txt
Companion docs:
  ./ICON-BUTTON-MEASUREMENT-AUDIT.md
  ./ICON-BUTTON-WP-MAPPING.md

Phase docs:
  docs/v3.5.2/ICON-BUTTON-PHASE-0-REPORT.md
  docs/v3.5.2/ICON-BUTTON-PHASE-1-PLAN.md

Framework:
  docs/v3.5.0/MODULE-STATUS-MATRIX.md
  docs/v3.5.0/PROMOTION-CRITERIA.md

Icon-system:
  ../icon-system/docs/ICON-SYSTEM-AUDIT.md
  ../icon-system/docs/ICON-BUTTON-RUNTIME-AUDIT.md
  ../icon-system/docs/ICON-FONT-POLICY.md
  ../icon-system/docs/INLINE-SVG-INVENTORY.md

Precedents:
  ../button/docs/BUTTON-SPEC-AUDIT.md
  ../button/docs/BUTTON-MEASUREMENT-AUDIT.md
  ../button/docs/BUTTON-WP-MAPPING.md
  ../chip/docs/CHIP-SPEC-AUDIT.md
  ../chip/docs/CHIP-MEASUREMENT-AUDIT.md
  ../chip/docs/CHIP-WP-MAPPING.md
```

---

## §13 — What This Audit Does NOT Do

- Does not edit `components.css §0` or `components.css §3`.
- Does not edit `icons.css`.
- Does not edit `style-guide.html #components-icon-button`.
- Does not edit `theme.json`.
- Does not close `BACKLOG.md` #28; it remains an open v3.5.x cleanup item.
- Does not move, copy, or stub `ICON-BUTTON-RUNTIME-AUDIT.md`.
- Does not create `lab-icon-button.css`.
- Does not create `lab-icon-button-pattern.html`.
- Does not create `lab-icon-button.js`.
- Does not wire current `ripple/`.
- Does not implement Ripple v2.
- Does not implement Lab Preview Routes.
- Does not migrate the historical runtime audit; migration remains future bookkeeping.

---

## §14 — v3.5.13 Size-Variant Alignment Note

This additive note links Icon button #2 to BACKLOG #32. It does not reopen the
v3.5.2 release verdict.

Current state:

```txt
Icon button has a 40px visual container, 48px minimum target, 24px glyph, and
no `.is-size-*` matrix.
```

v3.5.13 size contract:

```txt
BUTTON-FAMILY-SIZE-AUDIT.md locks Option C:
  shared Button-family size tokens with local Icon button mappings.
```

Expected Phase 2 impact:

```txt
Icon button may receive `.ax-icon-button.is-size-xs/s/m/l/xl` mappings for
visual box and glyph size while preserving accessible hit target rules.
```

Non-goal:

```txt
This note does not implement size variants, change icon-system policy, or alter
the v3.5.2 Component Full-Spec verdict.
```
