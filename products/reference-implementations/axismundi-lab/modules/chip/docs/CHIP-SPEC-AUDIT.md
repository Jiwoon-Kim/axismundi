# Chip Spec Audit — v3.4.9

> Bucket: E (Component module — full-spec audit)
> Charter: see `lab/docs/ARCHITECTURE-BOUNDARIES.md` §1 (four layers), §3 (bucket E), §4 (theme can / plugin should)
> Module taxonomy: see `lab/modules/README.md §Module taxonomy`
>
> Phase 1 — skeleton. Phase 2 (implementation) and Phase 3 (verdict)
> sections to be completed in subsequent phases.

## §1 — Critical framing

```
Chip is the first Axismundi Component Full-Spec Module.

Unlike interaction modules, the chip module does not extract runtime
behavior from a benchmark source. It expands an existing baseline
primitive into full-spec, measurement, variant, native semantics, and
WordPress mapping surfaces.
```

한글 요약:

```
Chip은 Axismundi의 첫 Component Full-Spec Module이다.

Interaction module처럼 benchmark runtime을 추출하는 작업이 아니라,
이미 존재하는 baseline primitive를 full spec, measurement, variant,
native semantics, WordPress mapping 표면으로 확장하는 작업이다.
```

This framing matters for three reasons:

1. **Taxonomy precedent**: v3.4.9 establishes the template that subsequent Component modules (future text-field, FAB full-spec, etc.) follow. The audit doc structure, the three-doc audit pattern (`-SPEC`, `-MEASUREMENT`, `-WP-MAPPING`), and the Phase 1/2/3 split applied here become the reference for Component modules going forward.
2. **Scope discipline**: v3.4.9 does NOT expand chip into every Material 3 optional variant. Elevated chip variants (assist/filter/suggestion) are routed to **BACKLOG #23**. Chip is NOT a chance to redesign or upgrade the baseline.
3. **Origin distinction**: v3.4.6 tooltip closed the Beer-CSS-derived interaction-module family. v3.4.7 date-time was the first interaction module outside that lineage (GPT Codex). v3.4.9 chip is the first module that is not an interaction module at all. The lineage chain shifts: it is no longer "where did the runtime come from?" but "which baseline primitive does this expand?".

### Baseline primitive — single source of truth

The baseline primitive at `components.css §11 Chip` (L1626–L1743, 118 lines, 7 rule blocks) remains UNCHANGED at v3.4.9. This module does NOT modify the baseline. The relationship is:

```
components.css §11 Chip
  = baseline primitive (visual specimen + base behavior)

style-guide.html#components-chip
  = representative specimens for catalog viewing (L1782–L1855, 74 lines)

lab/modules/chip/
  = full-spec / measurement / WordPress-mapping expansion
  = lab-internal; lab-chip.css extends with additional documented variants
    but does NOT replace baseline classes
```

## §2 — Baseline / module split

```
BASELINE  styleguide layer
          components.css §11 Chip (UNCHANGED at v3.4.9)
          style-guide.html#components-chip (UNCHANGED at v3.4.9)
          .chip + 4 variants + 2 icon slots + selected + disabled

LAB MODULE  component full-spec layer (this module)
          lab/modules/chip/
          ├── lab-chip.css            (extends; documents native form mapping +
          │                           input chip close affordance pattern)
          ├── lab-chip-pattern.html   (full variant matrix demo with Principle 2
          │                           native form controls)
          └── docs/
              ├── CHIP-SPEC-AUDIT.md           (this file)
              ├── CHIP-MEASUREMENT-AUDIT.md    (closes BACKLOG #4)
              └── CHIP-WP-MAPPING.md           (first WordPress mapping audit)

PLUGIN    federation / data binding layer (NOT touched at v3.4.9)
          - WordPress block editor sidebar controls
          - Custom form block chip rendering
          - Post meta facet chips
          - ActivityPub tag chips
```

Charter §4 application: theme renders chip surfaces; plugin emits chip data (filter facets, taxonomy terms, applied filters). Chip is squarely on the theme side; what *fills* the chip is plugin territory.

## §3 — Inventory

### Baseline (untouched at v3.4.9)

| File | Range | Lines | Notes |
|---|---|---:|---|
| `components.css §11 Chip` | L1626 → L1743 | 118 | 7 rule blocks |
| `style-guide.html#components-chip` | L1782 → L1855 | 74 | 4 sub-sections, 12 chip instances |

### Baseline rule block inventory (7 rules)

| L# | Selector | Purpose |
|---:|---|---|
| 1626 | `.chip` | Base shape, size, typography, motion |
| 1669 | `.chip__leading-icon, .chip__trailing-icon` | Slot layout |
| 1679 | `svg.chip__leading-icon, svg.chip__trailing-icon, .chip__leading-icon > svg, .chip__leading-icon > .ax-icon, .chip__trailing-icon > svg, .chip__trailing-icon > .ax-icon` | Icon sizing (18px) with avatar exception |
| 1690 | `.chip--assist, .chip--filter, .chip--input` | Outlined rest container |
| 1697 | `.chip--suggestion` | Flat rest container |
| 1709 | `.chip--filter[aria-pressed="true"], .chip--filter[aria-checked="true"], .chip--filter.is-selected` | Selected state (filter only, 3-marker pattern) |
| 1718 | `.chip--input .chip__trailing-icon` | Cursor pointer (remove affordance hint) |
| 1723 | `.chip:disabled, .chip[aria-disabled="true"]` | Disabled Pattern A (10% surface + 38% text) |
| 1739 | `.chip--suggestion:disabled, .chip--suggestion[aria-disabled="true"]` | Suggestion disabled exception (transparent bg) |

### Style-guide.html specimen inventory (12 instances)

| Subsection | Specimens | Notes |
|---|---|---|
| Variants — 4 types (L1789) | assist + filter + input + suggestion | One each, baseline variant catalog |
| Filter — selected (L1812) | 2 toggles | Uses `sg-toggle` class (style-guide.js handles toggle) |
| Input — with avatar leading (L1825) | 1 input chip | Avatar 24px exception in action |
| Disabled (L1837) | 4 disabled variants | One per variant |

## §4 — Variant matrix

### §4.1 Assist chip — HIGH coverage

| Aspect | Baseline | Notes |
|---|:---:|---|
| 32dp height | ✓ | `--_chip-h: 32px` L1627 |
| Corner-small radius | ✓ | token L1642 |
| Outlined rest | ✓ | L1690-L1696 |
| Leading icon 18px | ✓ | L1685-L1686 |
| Label-large typography | ✓ | L1647-L1651 |
| Disabled (Pattern A) | ✓ | L1723-L1738 |
| **Elevated variant** | ✗ | BACKLOG #23 — deferred |
| **Hover/focus state-layer override** | ⚠ | Inherits `has-state-layer` mechanism, no chip-specific override |

**Module work (v3.4.9 lab-chip.css)**: documentation only. No CSS additions for assist chip. Elevated variant routed to BACKLOG #23.

### §4.2 Filter chip — MEDIUM-HIGH coverage

| Aspect | Baseline | Notes |
|---|:---:|---|
| Base + outlined + typography | ✓ | (inherits from base + L1690-L1696) |
| Selected state — 3-marker pattern | ✓ | L1702-L1715: `aria-pressed`, `aria-checked`, `.is-selected` |
| Selected: outline removed + secondary-container | ✓ | L1712-L1714 |
| Disabled (Pattern A) | ✓ | L1723-L1738 |
| **Native form wrapping (input checkbox/radio)** | ✗ | Principle 2 application — module work item |
| **Leading check icon when selected** | ⚠ | Possible by author; no automatic pattern |
| **Trailing dropdown chevron (filter-with-menu)** | ✗ | M3 §14.3 option |
| **Elevated variant** | ✗ | BACKLOG #23 |
| **Selected + leading icon color inheritance** | ⚠ | Works via CSS inheritance, not documented |

**Module work (v3.4.9 lab-chip.css)**:

1. Add `.chip--filter:checked` selector for native form input wrapping (mirrors the 3-marker selected styling)
2. Document `<input type="checkbox" class="chip chip--filter">` + `<label>` pattern in pattern HTML §filter-native
3. Demonstrate filter-group as `<fieldset>` with multiple checkbox-chips

**Open question for Phase 2**: how to attach `chip--filter` class to the `<input>` itself (CSS-only) vs wrapping `<label>` (markup convention). Phase 1 records both options.

### §4.3 Input chip — MEDIUM coverage (most gaps)

| Aspect | Baseline | Notes |
|---|:---:|---|
| Base + outlined | ✓ | (inherits) |
| **Avatar 24dp exception** | ✓ | L1631 + L1676-L1678 — most explicit exception in §11 |
| Trailing icon cursor: pointer | ✓ | L1718-L1720 |
| Disabled (Pattern A) | ✓ | L1723-L1738 |
| **Close affordance target size (interactive)** | ⚠ | 18px visible icon — not enough to prove an adequate interactive target |
| **Close button semantics** | ✗ | Baseline specimen uses `<span>` — Principle 1 risk |
| **Keyboard dismiss (Backspace / Delete on focused chip)** | ✗ | M3 §14.5 — module work item |
| **Tab order (chip + close = 2 stops)** | ✗ | Module work item |

#### Target-size gap (precise WCAG framing)

The "close affordance target size" question requires careful WCAG citation:

```
WCAG 2.2 SC 2.5.8 Target Size (Minimum) — Level AA
  Pointer input targets at least 24 × 24 CSS pixels.

WCAG 2.2 SC 2.5.5 Target Size (Enhanced) — Level AAA
  Pointer input targets at least 44 × 44 CSS pixels.

Material Design touch-friendly convention
  Recommends a larger effective hit area than the visible icon, often
  cited as ~48dp. Not a WCAG requirement but a UX convention.
```

The baseline `.chip--input .chip__trailing-icon` has visible size 18px. Whether the effective interactive target reaches 24×24 (AA) depends on:

- Whether the icon's parent (`.chip__trailing-icon` slot) expands to include padding
- Whether the icon element is itself the click target (`<span>`) vs a wrapping `<button>`
- Whether the chip body has its own click handler that catches clicks anywhere on the chip surface

**18px visible icon is not enough to prove an adequate interactive target.** v3.4.9 Phase 1 records this gap. Phase 2 chooses an implementation:

```
Option A — pseudo-element target expansion
  .chip__trailing-icon::after { content: ""; position: absolute;
    inset: -<padding>; min-width: 24px; min-height: 24px; }
  Pros: no markup change. Cons: requires positioning, affects layout if
        not careful, hit area invisible.

Option B — <button> wrapping with min-size
  <button class="chip__close" type="button" aria-label="Remove">
    <span class="chip__trailing-icon ax-icon">close</span>
  </button>
  Pros: explicit semantics (Principle 1), keyboard activation, focus
        ring works. Cons: markup change from baseline specimens.

Option C — chip itself as removable surface (Backspace dismiss)
  Tab once: chip focused → Backspace/Delete dismisses.
  Pros: native semantics-like, fewer tab stops. Cons: surface-wide
        click handler conflicts with chip body action.
```

Phase 2 decision is recorded explicitly in the audit doc when made; v3.4.9 Phase 1 does not pre-decide.

**Phase 2 decision (recorded 2026-05-15)**: **Option B (primary) + Option A-lite (boost)**.

- The close affordance is a real `<button class="chip__close" type="button" aria-label="Remove <chip-label>">`.
- The button container is 24×24 CSS pixels — meets WCAG SC 2.5.8 AA on all pointer types.
- A `::before` pseudo-element expands the hit area to ~44×44 on coarse pointer (`@media (pointer: coarse)`) — meets WCAG SC 2.5.5 AAA and Material touch convention.
- The visible close glyph stays 18px to match `--_chip-icon` baseline.
- Option C (Backspace/Delete keyboard dismiss) remains deferred — requires JS and is recorded as a BACKLOG follow-up.

Implementation lives in `lab-chip.css §2`. Pattern demo in `lab-chip-pattern.html §4`.

### §4.4 Suggestion chip — HIGH coverage

| Aspect | Baseline | Notes |
|---|:---:|---|
| Flat rest (no outline) | ✓ | L1697-L1700 |
| **Disabled exception (transparent bg, no Pattern A fill)** | ✓ | L1739-L1742 — explicit exception |
| Base + typography | ✓ | (inherits) |
| **Elevated variant** | ✗ | BACKLOG #23 |

**Module work (v3.4.9 lab-chip.css)**: documentation only. Suggestion chip is the most complete relative to M3 spec.

## §5 — Explicit exceptions (recorded in baseline code)

Six exceptions are already documented in `components.css §11 Chip`:

| # | Exception | Location | Affects |
|---:|---|---|---|
| 1 | **Icon 18px vs Avatar 24px** — avatar leading slot keeps its own size via selector skip | L1631 comment + L1676-L1678 selector pattern (`.chip__leading-icon.ax-avatar` doesn't match generic icon-size selectors) | Input chip primarily |
| 2 | **Selected state 3-marker pattern** — `aria-pressed`, `aria-checked`, `.is-selected` all resolve to same selected styling, rationale per marker documented | L1702-L1708 comment + L1709-L1715 selector | Filter chip |
| 3 | **Selected outline removal** — filled selected state removes outline to prevent visual conflict | L1712 | Filter chip selected |
| 4 | **Suggestion disabled transparent bg** — does not apply Pattern A 10% surface fill | L1739-L1742 | Suggestion chip disabled |
| 5 | **Disabled `cursor: not-allowed` + `pointer-events: none`** — applied to all variants via common rule | L1725-L1726 | All variants disabled |
| 6 | **18px chip icon NOT tokenized** — design decision recorded inline: only chip uses 18px, so adding a global token would tempt incorrect reuse | L1628-L1631 comment block | All variants |

## §6 — Missing exceptions / module work items

Five exceptions are NOT in baseline code and constitute module work items:

| # | Missing exception | Affected variant | Resolution strategy at v3.4.9 |
|---:|---|---|---|
| 1 | **Input chip close target size adequacy** (WCAG SC 2.5.8 AA / SC 2.5.5 AAA / Material touch convention — none currently proven) | Input | Phase 2 chooses among options A/B/C in §4.3; `lab-chip-pattern.html` demonstrates correct pattern; `lab-chip.css` adds supporting selectors |
| 2 | **Input chip close as `<button>` with `aria-label`** | Input | Pattern HTML uses `<button>` element with `aria-label="Remove <chip-label>"`; baseline specimen `<span>` left as-is (Principle 1 violation but baseline UNCHANGED) |
| 3 | **Filter chip native form mapping (`<input type="checkbox">` + `<label>`)** — selected state must wire to `:checked` | Filter | `lab-chip.css` adds `.chip--filter:checked` or `<input>:checked + <label>.chip` selector; `lab-chip-pattern.html` demonstrates Principle 2 |
| 4 | **`has-state-layer` + disabled state-layer suppression** | All | Documentation in audit; if implementation needed, `lab-chip.css` adds `.chip:disabled.has-state-layer, .chip[aria-disabled="true"].has-state-layer { ... state-layer suppress ... }` |
| 5 | **Selected filter + leading icon color inheritance** | Filter selected | Documentation only — works via CSS inheritance, audit records as known pattern |

**Phase 2 decision** for item 1 is the largest open question. Items 2, 3, 5 are documentation + minor CSS. Item 4 needs cross-reference with the existing `has-state-layer` mechanism (which lives outside §11 Chip).

## §7 — Visible control principle (applied to chip variants)

Per `lab/modules/README.md §Design principles`:

```
Visible control must map to real runtime behavior.
```

Variant-by-variant application:

| Variant | Real runtime behavior must be | If unavailable in demo |
|---|---|---|
| **Assist** | `<button>` triggering a real action | Mark as `aria-hidden="true"` + label "visual specimen" |
| **Filter** | `<input type="checkbox">` or `<input type="radio">` with visible `:checked` state | Pure visual specimen acceptable IF labeled as such |
| **Input** | `<button>` close affordance with real dismiss action; chip itself with optional keyboard focus | Static pre-filled list is fine; close button must not pretend |
| **Suggestion** | `<button>` or `<a>` with real action | Static visual is fine if no actionable claim |

The forbidden pattern across all variants: a `<button>` or clickable-styled element with no action, no state, and no visible disabled reason. The pattern HTML demonstrates the correct shape for each variant; the audit doc records why each shape is correct.

### Where baseline specimens deviate from Principle 1

The baseline `style-guide.html#components-chip` specimens at L1795 (`<button aria-pressed="false">` filter chip without selected toggle wiring) would violate Principle 1 if not for the `sg-toggle` class which `style-guide.js` handles. The deviation is documented but baseline is NOT modified at v3.4.9.

`lab-chip-pattern.html` demonstrates the **Principle 2-compliant** alternative: `<input type="checkbox">` + `<label>` filter chips where the selected state is genuinely `:checked` with no JS required.

## §8 — Five-criterion verdict

| # | Criterion | Status |
|---:|---|:---:|
| 1 | **JS-off fallback** — baseline specimens visible without JS; filter chips use native `<input type="checkbox/radio">` + `<label>` (clicks toggle natively); input chip close button uses `onclick` but baseline degrade is a visible static chip with no remove action (acceptable degradation) | ✓ PASS |
| 2 | **M3 / state-layer compatibility** — baseline `.chip*` UNCHANGED; module CSS uses M3 sys tokens only (`--md-sys-color-secondary-container`, `--md-sys-color-outline-variant`, `--md-sys-color-on-surface`, `--md-sys-shape-corner-small`, `--md-sys-typescale-label-large-*`, `--md-sys-motion-curve-fast-effects-*`); `has-state-layer` mechanism extended cleanly with disabled suppression | ✓ PASS |
| 3 | **Reduced motion** — module CSS adds only the `chip__close` button background-color transition using `--md-sys-motion-curve-fast-effects-*` tokens (which are reduced-motion gated at token level); no new motion patterns introduced | ✓ PASS |
| 4 | **Keyboard / a11y** — native form semantics fully applied (filter chips use real checkbox/radio with arrow-key support in radio groups; `<fieldset><legend>` group containment); input chip close affordance is a real `<button>` with `aria-label="Remove <chip-label>"` and `:focus-visible` outline ring; WCAG SC 2.5.8 AA target size (24×24) met on all pointer types via button container; WCAG SC 2.5.5 AAA (44×44) met on coarse pointer via `::before` expansion; Backspace/Delete dismiss honestly deferred since v3.4.9 is no-JS | ✓ PASS (with explicit deferred) |
| 5 | **Prose / federation isolation** — chip is a leaf component; state lives in `<input>` form value (filter) or button click handler (close), not DOM-position-dependent JS; no forbidden-ancestor logic required at module level; lab-chip.css uses no global side effects | ✓ PASS |

### Verdict

```
PASS as the first Component Full-Spec Module.

v3.4.9 establishes the audit pattern for Component modules: three docs
(-SPEC, -MEASUREMENT, -WP-MAPPING), a four-variant matrix, explicit
exception inventory, WordPress mapping, and visible-control principle
application per variant.

Phase 2 delivers native form mapping for filter chips, real button close
affordances for input chips with WCAG SC 2.5.8 AA and SC 2.5.5 AAA
target-size references, and disabled state-layer suppression.

The baseline chip primitive remains UNCHANGED.
Elevated variants are routed to BACKLOG #23.
Backspace/Delete dismiss behavior remains deferred because v3.4.9 is a
no-JS Component Full-Spec Module.
```

한글 요약:

```
첫 Component Full-Spec Module로 PASS.

v3.4.9는 interaction 추출이 아니라 Component Full-Spec Module의
기준을 세운다. filter chip은 native input + label로 실제 selected
state를 제공하고, input chip close는 실제 button + aria-label +
확장된 hit area를 제공한다. baseline chip primitive는 변경하지
않았고, elevated variant와 Backspace/Delete dismiss는 후속으로
넘긴다.
```

### Internal contract checks (for traceability)

- **Charter §1 / Bucket E**: confirmed — component module lives in lab tier; baseline UNCHANGED at v3.4.9. Same posture as past lab modules.
- **Component vs Interaction taxonomy**: confirmed — first Component Full-Spec Module. Module taxonomy section in `lab/modules/README.md` defines this category. Sets the template for future Component modules (text-field, future FAB-full-spec, etc.).
- **Principle 1 application**: confirmed — `<button class="chip__close">` with `aria-label`, filter chip toggle via real `:checked`, disabled chips actually disable, state-layer suppressed on disabled surfaces. Baseline `style-guide.html` deviation at L1795 documented but NOT modified (out of scope).
- **Principle 2 application**: confirmed — filter chip selected state uses `<input type="checkbox/radio">` + sibling `<label>` (no JS toggle); arrow keys within radio group work natively; close button uses native `<button>` semantics.
- **WCAG citation accuracy**: confirmed — SC 2.5.8 (AA, 24×24) and SC 2.5.5 (AAA, 44×44) cited correctly throughout audit; Material touch-friendly convention noted as separate design convention, not WCAG requirement.
- **Static Visual QA Gate**: PASS — 8 input/label pairs 100% matched, 4 input chips have 4 close buttons with `aria-label`, 16 CSS classes all defined in lab+baseline, no benchmark/out-of-tree references. Browser-side manual verification recommended but not blocking.

## §9 — What this module does NOT do

- Does not modify `components.css §11 Chip` baseline primitive.
- Does not modify `style-guide.html#components-chip` baseline specimens (74 lines untouched).
- Does not promote any new chip variant to baseline (separate Charter §1 decision).
- Does not add elevated variants (BACKLOG #23).
- Does not decide between Options A/B/C for input chip target-size (Phase 2).
- Does not add Gutenberg block editor integration (Charter §4 plugin territory).
- Does not implement filter chip JS toggle (Principle 2 — use native `<input type="checkbox">` instead).
- Does not address `.snackbar` naming inconsistency (BACKLOG #18) or `.chip` naming review.
- Does not generate M3 tonal palette colors for chip selected state (BACKLOG #21 Interpreter Plugin scope).
