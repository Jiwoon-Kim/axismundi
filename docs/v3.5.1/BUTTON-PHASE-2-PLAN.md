# Button — Phase 2 Plan (v3.5.1)

> **Bucket**: E (Component module — phase planning document)
> **Status**: C5 Phase 2 plan-only document. Awaiting User approval before Phase 2 execution.
> **Source authority**: `docs/v3.5.1/BUTTON-PHASE-0-REPORT.md` + Phase 1 audit body trio (`BUTTON-SPEC-AUDIT.md`, `BUTTON-MEASUREMENT-AUDIT.md`, `BUTTON-WP-MAPPING.md`)
> **Reference template**: `lab/modules/chip/lab-chip.css` + `lab-chip-pattern.html` (v3.4.9 — first Component Full-Spec implementation)
> **Companions**: `BUTTON-SPEC-AUDIT.md`, `BUTTON-MEASUREMENT-AUDIT.md`, `BUTTON-WP-MAPPING.md`
> **Pre-entry decision**: ✓ SETTLED 2026-05-16 — Option (b) defer animated ripple to Ripple v2 release (BACKLOG #25). Consensus: User + GPT + Claude Opus.
> **Revisions**: 2026-05-16 — Plan v1.1 incorporating 4 findings (deliverable/bookkeeping split, selector policy clarification, disabled specimen split, NEXT-SESSION discipline).

## §0 — Plan scope and gate

This document is a **plan-only artifact**. Its purpose:

1. Specify the exact scope of Phase 2 deliverables.
2. Lock in non-goals so Phase 2 execution stays bounded.
3. Map applicable G1–G10 gates to Phase 2 work.
4. Specify validation commands.
5. Surface risks for User review.

**Approval gate**: Phase 2 execution (writing `lab-button.css` and `lab-button-pattern.html`) does NOT begin until User approves this plan. GPT strategic review optional but recommended for ripple-decision implications.

This plan is consistent with:

- `BUTTON-SPEC-AUDIT.md §6` "Missing exceptions / module work items"
- `BUTTON-SPEC-AUDIT.md §12` G1–G10 gate applicability
- Phase 2 pre-entry decision (Option b) recorded in `CURRENT-STATE.md`

## §1 — Phase 2 deliverables

**Deliverable artifacts**: exactly two files. No more, no less.

| File | Path | Role |
|---|---|---|
| `lab-button.css` | `products/reference-implementations/axismundi-lab/modules/button/lab-button.css` | Pattern variations on top of baseline `.ax-button.is-*`. No ripple wiring per Option (b). |
| `lab-button-pattern.html` | `products/reference-implementations/axismundi-lab/modules/button/lab-button-pattern.html` | Full variant matrix + state-layer demo + icon slot + opt-out specimen. Lab-internal demo page. |

**Phase bookkeeping** (NOT counted as deliverable artifacts; see §6 steps 7–8): updates to `BUTTON-SPEC-AUDIT.md §11` verdict criterion #3 and to `CURRENT-STATE.md` happen after the two deliverable artifacts are in place. These are state-tracking updates, not new module artifacts, and follow phase-close conventions. The "two files only" rule applies to the deliverable artifacts produced by Phase 2; it does NOT prohibit phase bookkeeping.

**NOT created at Phase 2**:

- `lab-button.js` — Option (b) excludes ripple wiring; baseline Button needs no JS.
- Any baseline file (`components.css`, `style-guide.html`, `theme.json`, `tokens.css`).
- Any block style registration in `functions.php` (WP-MAPPING audit §10 records declaratively only).

## §2 — `lab-button.css` scope

### §2.1 Purpose

Document via CSS the variant / state combinations that the baseline already supports, plus any pattern-level conventions worth recording in code (not just docs). The lab CSS is consumed by the lab pattern HTML page; it is **lab-internal** and NOT promoted to baseline at v3.5.1.

### §2.2 What `lab-button.css` MAY contain

```
ALLOWED additions (none alter the baseline contract):

  1. Pattern-level layout helpers for the lab demo page
     (e.g., .lab-button-row, .lab-button-grid).
     These are demo-page scaffolding, NOT button styling.

  2. Documentation comments referencing audit doc sections
     (e.g., /* See BUTTON-SPEC-AUDIT.md §4.1 — Filled variant */).

  3. Demo-only state-visualization selectors, STRICTLY SCOPED under
     .lab-button-demo ancestor. The visualization must be:
        - Limited to opacity, state-layer overlay, or token-value swap
          (e.g., showing what the pressed corner-small radius looks
          like statically).
        - Never modify color tokens (filled / tonal / elevated /
          outlined / text), variant fill, or border-radius rest value.
        - Never modify size, padding, gap, font, or any baseline
          measurement.
        - Never propagate outside .lab-button-demo (no global ::root
          or :where(.ax-button) leakage).
     Example (allowed):
       .lab-button-demo .ax-button[data-demo-state="pressed"] {
         border-radius: var(--md-sys-shape-corner-small);
       }
     Example (NOT allowed — see §2.3 #1):
       .ax-button[data-demo-state="pressed"] { ... }   /* unscoped */

  4. Module-private CSS variables for the demo layout
     (--lab-button-row-gap, etc.) — NOT M3 system tokens, NOT
     button-affecting.
```

### §2.3 What `lab-button.css` MUST NOT contain

```
FORBIDDEN:

  1. UNSCOPED selectors that override baseline `.ax-button` or
     `.ax-button.is-*`. Any rule whose selector is `.ax-button` or
     `.ax-button.is-filled` (etc.) without a `.lab-button-demo`
     ancestor scope is FORBIDDEN — it would shadow the baseline
     contract.

     Scoped demo-only selectors (e.g., `.lab-button-demo .ax-button
     [data-demo-state="pressed"]`) are allowed per §2.2 #3, subject
     to its visualization-only constraints. The rule of thumb:
        - Bare `.ax-button` override        → FORBIDDEN
        - `.lab-button-demo .ax-button …`   → ALLOWED, visualization
                                              only (see §2.2 #3)

  2. Ripple wiring of any form:
       - No data-ax-ripple opt-in attribute (BACKLOG #27 — v3.5.x)
       - No <md-ripple> reference
       - No script linkage to lab/modules/ripple/ for Button
     Option (b) defers all animated ripple to Ripple v2 release.

  3. New M3 system tokens (e.g., --md-sys-*) — only consume existing
     tokens.

  4. Icon-system overrides — icon slot styling is consumed from icon-
     system module via .ax-button-icon (per baseline L171–L178).

  5. JS dependencies — file is CSS-only.

  6. Color literals — only via existing color-scheme tokens.
```

### §2.4 Expected size

Estimated 60–120 lines of CSS, mostly demo-page scaffolding + comment references to audit doc sections. The component itself is fully covered by baseline `components.css §2` (113 lines, 11 rule blocks); `lab-button.css` adds essentially no new component styling.

### §2.5 Reference template

`lab/modules/chip/lab-chip.css` (v3.4.9) — first Component Full-Spec module. Chip's lab CSS added:

- A `.chip__close` button affordance (which baseline did NOT have) — Phase 2 actual work
- Hit-area expansion for coarse pointer
- Native form mapping for filter chips (`<input type="checkbox">:checked` styling)

Button's case is different: baseline `.ax-button` is feature-complete for what v3.5.1 targets. Most of Button's `lab-button.css` is demo scaffolding + documentation. This is acceptable — module CSS can be light when baseline already covers the spec.

## §3 — `lab-button-pattern.html` scope

### §3.1 Purpose

Demonstrate every Phase 1-scoped variant / state / slot combination in a single browsable page. Serves as:

- Visual regression baseline for Phase 3 QA gate
- Reference for plugin / theme integration authors
- Confirmation that the audit docs match observable rendering

### §3.2 Section structure (proposed)

```
<!doctype html>
... <head> with same stylesheet chain as style-guide.html ...
... <body>

§1  Page header — Title + link back to style-guide.html#components-button

§2  Variants — label only (5)
    Filled / Tonal / Elevated / Outlined / Text — all with has-state-layer

§3  With leading icon (5)
    Filled / Tonal / Elevated / Outlined / Text — canonical icon slot per
    BUTTON-SPEC-AUDIT.md §8 (material-symbols-rounded + ax-button-icon
    + aria-hidden + translate=no + draggable=false)

§4  has-state-layer opt-out demo (1 + 1)
    1× Filled WITH state-layer + 1× Filled WITHOUT state-layer
    Clearly labeled so the visual difference is obvious.

§5  Disabled — Pattern A §0.8 (5) — native attribute
    All 5 variants with native `disabled` attribute.
    Demonstrates Pattern A: 10% bg + 38% text + level0 box-shadow;
    text variant shows transparent bg exception (baseline L231–L234).
    Caption (bilingual): "Native disabled — browser blocks activation +
    AT announces 'disabled' + Pattern A visual rendering applied."
    Caption (KO): "Native disabled — 브라우저가 활성화 차단 +
                   AT가 'disabled' 발화 + Pattern A 시각 렌더링 적용."

§5a Disabled — aria-disabled (plugin-managed) — single specimen
    1× Filled with `aria-disabled="true"` (no native disabled).
    Caption (bilingual) — MUST be present so this specimen is NOT read
    as a canonical pattern:
      "aria-disabled visually mirrors Pattern A via baseline L213
      ([aria-disabled='true']) but does NOT block click activation by
      itself. Use this form ONLY when the disabled state is plugin-
      managed (e.g., a form plugin toggles aria-disabled while the
      submit handler is in flight) and the plugin's own JS prevents
      activation. For static / theme-side disabled, prefer native
      `disabled`."
    Caption (KO):
      "aria-disabled는 baseline L213 선택자로 Pattern A 외관을
      흉내내지만, 단독으로는 click activation을 막지 않는다.
      plugin이 disabled 상태를 동적으로 관리하면서 (e.g., form
      plugin이 submit handler 진행 중 aria-disabled를 토글),
      plugin JS가 activation을 직접 차단할 때만 이 형태를 사용.
      정적 / theme-side disabled는 native `disabled`가 정답."

§6  Bare button (no .is-* class) (1)
    Demonstrates the rare-use base shape (per SPEC §4.6).

§7  Code snippets (collapsible <details> blocks)
    One snippet per variant section showing the canonical markup.

§8  Cross-references — links to:
      docs/v3.5.1/BUTTON-PHASE-0-REPORT.md
      BUTTON-SPEC-AUDIT.md
      BUTTON-MEASUREMENT-AUDIT.md
      BUTTON-WP-MAPPING.md
      docs/v3.5.0/MODULE-STATUS-MATRIX.md row #1
```

### §3.3 Toggle deferral

Phase 1 SPEC §6 item 5 deferred toggle pattern. The lab pattern HTML does NOT include `aria-pressed` toggle specimens. Toggle visual feedback styling is style-guide-JS-driven currently (per baseline `style-guide.html` L671). When toggle is settled (FAB / icon-toggle Wave 1 item or v3.5.x toggle clarification), the pattern HTML gets a §3a Toggle section added.

### §3.4 Expected size

Estimated 200–280 lines of HTML, including code snippets and Korean / English bilingual section labels (matching style-guide.html convention).

### §3.5 Reference template

`lab/modules/chip/lab-chip-pattern.html` (v3.4.9). Same section-structure approach; bilingual section labels; collapsible code snippets.

## §4 — Dependency declaration in lab module CSS

Button is a CURRENT consumer of components.css §0 State-layer foundation and CURRENT-conditional consumer of icon-system/. The lab CSS comment header SHOULD declare this (matching the convention in chip's `lab-chip.css`):

```css
/* ============================================================
 * lab-button.css
 *
 * Lab-internal pattern variations on top of baseline:
 *   - components.css §2 Button (L122–L234) — baseline primitive
 *   - components.css §0 State-layer foundation (L22–L79) — CURRENT
 *
 * Conditional infrastructure dependencies:
 *   - icon-system/ — CURRENT (when icon slot is used)
 *
 * Target / future dependencies (NOT wired at v3.5.1):
 *   - lab/modules/ripple/ — TARGET (Phase 2 explicitly defers per
 *     Option (b); animated ripple will land via Ripple v2 release,
 *     BACKLOG #25)
 *
 * Audit docs:
 *   - lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
 *   - lab/modules/button/docs/BUTTON-MEASUREMENT-AUDIT.md
 *   - lab/modules/button/docs/BUTTON-WP-MAPPING.md
 *
 * Baseline §2 + §0 UNCHANGED at v3.5.1.
 * ============================================================ */
```

## §5 — G1–G10 gate readiness mapping (Phase 2 target)

Per `BUTTON-SPEC-AUDIT.md §12`. Phase 2 must achieve / preserve:

| Gate | Description | Phase 2 target | Verification command |
|---:|---|:---:|---|
| G1 | `validate_theme_pilot.py` 1.000 PASS | ✓ preserve | `python3 tools/validators/validate_theme_pilot.py` |
| G2 | Baseline §0 + §2 + `#components-button` anchor UNCHANGED | ✓ preserve | `stat` mtime check + `md5sum` byte check on baseline files |
| G3 | `publish_styleguide.py` runs cleanly (in environments with correct ROOT path) | ⚠ deferred | (script L35 hardcoded path issue documented; Phase 5 may run from Windows-side) |
| G4 | Module artifacts present at agreed paths | ✓ create | `ls lab/modules/button/` — expect `lab-button.css` + `lab-button-pattern.html` + `docs/` |
| G5 | CHANGELOG entry added | (Phase 5) | n/a at Phase 2 |
| G6 | Static Visual QA Gate PASS (0 actual issues) | (Phase 3) | n/a at Phase 2 |
| G7 | Principle 1 — all controls real `<button>` | ✓ achieve | grep pattern HTML for `<button` vs `<div role="button">` |
| G8 | Principle 2 — `<button type="button">` standard | ✓ achieve | grep pattern HTML for explicit `type="button"` / `type="submit"` |
| G9 | WCAG SC citations accurate | ✓ already | (Phase 1 — MEASUREMENT §4 cites SC 2.5.8 AA + SC 2.5.5 AAA correctly) |
| G10 | 3-doc audit pattern complete | ✓ already | (Phase 1 — SPEC + MEASUREMENT + WP-MAPPING bodies authored) |

G3 caveat: the `publish_styleguide.py` hardcoded path issue (L35 `Path("/home/claude/axismundi")`) is environmental, NOT a Phase 2 regression. If Phase 5 runs from a Windows-side environment, the script may need patching to use `Path(__file__).resolve().parent.parent.parent`. This is recorded as a Phase 5 / tooling-cleanup concern, NOT a Phase 2 blocker.

## §6 — Validation plan (Phase 2 execution)

When Phase 2 executes (after this plan is approved), the following sequence runs:

```
1. Pre-edit baseline mtime snapshot
   for f in components.css style-guide.html theme.json tokens.css ; do
     stat --format='%y' [path-to-]$f
   done
   # Expected: all at 2026-05-16 05:40

2. Create lab-button.css (per §2 scope above)
   wc -l lab/modules/button/lab-button.css
   # Expected: 60–120 lines

3. Create lab-button-pattern.html (per §3 scope above)
   wc -l lab/modules/button/lab-button-pattern.html
   # Expected: 200–280 lines

4. Validator gate
   python3 tools/validators/validate_theme_pilot.py
   # Expected: 1.000 / 1.000 / 1.000 / 1.000 PASS

5. Post-edit baseline mtime check (must match pre-edit)
   for f in components.css style-guide.html theme.json tokens.css ; do
     stat --format='%y' [path-to-]$f
   done

6. Principle 1/2 verification on pattern HTML
   grep -c '<button.*type=' lab/modules/button/lab-button-pattern.html
   grep -c '<div role="button"' lab/modules/button/lab-button-pattern.html
   # Expected: many of the first, 0 of the second

# --- Phase bookkeeping (NOT counted as §1 deliverable artifacts) ---

7. Update BUTTON-SPEC-AUDIT.md §11 verdict criterion #3 (Pattern HTML
   completeness) from TBD to PASS.

8. Update CURRENT-STATE.md (mandatory): set Phase 2 status to DONE; set
   Phase 3 to NEXT.
   Update NEXT-SESSION.md (CONDITIONAL): only if a true session boundary
   is at hand — new chat session, end-of-day handoff, or a big phase
   transition (e.g., entering Phase 3 means switching to QA-gate workflow).
   Otherwise, leave NEXT-SESSION.md as-is. CURRENT-STATE.md + this plan
   doc + the audit doc verdict update are sufficient state-tracking for
   intra-session continuity.
```

## §7 — Non-goals (Phase 2 explicit exclusions)

```
Phase 2 does NOT:

  - Modify baseline components.css §0 (state-layer foundation)
  - Modify baseline components.css §2 (Button)
  - Modify baseline style-guide.html (including #components-button L624–L693)
  - Modify baseline tokens.css
  - Modify theme.json
  - Create lab-button.js
  - Add ripple wiring (animated or otherwise) — Option (b) defers all to
    Ripple v2 release (BACKLOG #25)
  - Add toggle (aria-pressed) styling (deferred per SPEC §6 item 5)
  - Add XS / M / L / XL size variants (deferred per Phase 0 constraint 2;
    SPEC §6 item 4, MEASUREMENT §6)
  - Execute matrix amendment (BACKLOG #24 / #26 — separate v3.5.x release)
  - Execute Ripple v2 contract design (BACKLOG #25 — separate release)
  - Execute data-ax-ripple opt-in introduction (BACKLOG #27 — separate)
  - Execute .snackbar → .ax-snackbar naming sweep (BACKLOG #18 — separate)
  - Execute data-theme=auto policy (BACKLOG #20 / #22 — separate)
  - Implement form submission behavior (plugin territory per CHARTER §3.4)
  - Add ActivityPub federation button surfaces (future work)
  - Register WP block styles in functions.php (WP-MAPPING audit records
    declaratively only)
  - Add admin-side button surfaces
  - Implement plugin / editor integration runtime
  - Generate M3 tonal palette colors (BACKLOG #21 Interpreter Plugin)
  - Implement Icon button (Wave 1 #2 — separate release)
  - Implement FAB or Extended FAB (separate Wave 1+ items)
  - Refactor publish_styleguide.py (informational tooling concern;
    separate cleanup)
```

## §8 — Risks (Phase 2-specific)

### Risk A — Visual divergence from baseline style-guide

```
Description: lab-button-pattern.html will use the same baseline classes as
             style-guide.html #components-button. If the lab pattern page
             loads a different stylesheet chain or order, visual rendering
             may differ subtly.
Mitigation:  Mirror the exact <link> chain used by style-guide.html. Reference
             template lab-chip-pattern.html demonstrates the correct chain.
Severity:    LOW — easy to verify visually side-by-side.
```

### Risk B — Pattern HTML drift from canonical icon slot

```
Description: SPEC §8 records the canonical icon slot pattern:
             <span class="material-symbols-rounded notranslate ax-button-icon"
                   translate="no" aria-hidden="true" draggable="false">…</span>
             Pattern HTML must use this exact form; any drift makes the §8
             "canonical pattern" claim inaccurate.
Mitigation:  Copy-paste from style-guide.html L646–L666 for the with-icon
             section.
Severity:    LOW — mechanical.
```

### Risk C — Opt-out specimen confusion

```
Description: The "has-state-layer opt-out demo" (§3.2 §4) shows one button
             WITHOUT has-state-layer. Without clear labeling, this may look
             like a bug (broken hover) rather than an intentional demo.
Mitigation:  Add a visible caption "← has-state-layer class omitted —
             expected to lack hover/focus opacity overlay" next to the
             opt-out specimen. Bilingual EN + KO.
Severity:    LOW — handled by labeling.
```

### Risk D — Disabled state styling on .is-text variant

```
Description: Baseline §2 L231–L234 has explicit text-variant disabled
             exception (transparent bg, no Pattern A 10% fill). Pattern HTML
             must demonstrate this exception or risk inconsistency claim
             in Phase 3 QA gate.
Mitigation:  §5 disabled section MUST include text-variant specimen and
             visually show transparent bg (versus other variants' 10% fill).
Severity:    LOW — covered by section structure.
```

### Risk E — Phase 3 QA gate finds undocumented issue

```
Description: Phase 3 visual QA may surface issues not predicted at Phase 2.
             E.g., browser-specific rendering quirks, font-rendering edge
             cases, color-scheme dark-mode interactions.
Mitigation:  Phase 3 is the proper venue. If issues are found, they route
             to either:
               (a) BACKLOG entries (if non-blocking for Phase 5 release)
               (b) Phase 2 amendment (if blocking)
             Plan does NOT pre-decide.
Severity:    MEDIUM — by definition unpredictable; Phase 3 is the gate.
```

### Risk F — Lab CSS becomes a "shadow baseline"

```
Description: If lab-button.css contains any UNSCOPED selectors that affect
             button rendering, it becomes a shadow baseline — a separate
             source of truth that drifts from components.css §2. The
             system loses single-source-of-truth integrity.
Mitigation:  Strict adherence to §2.2 #3 (allowed scoped patterns) and
             §2.3 #1 (forbidden unscoped patterns). Any selector mentioning
             .ax-button must be either:
               - scoped under .lab-button-demo ancestor AND limited to
                 visualization-only properties (opacity / state-layer
                 overlay / token-swap), OR
               - rejected as a baseline override.
             Phase 2 reviewer (User / GPT / Claude reviewer) verifies
             with:
               grep -nE '^\.ax-button|^[^.].*\.ax-button' lab-button.css
               # Any match that lacks .lab-button-demo prefix is a
               # forbidden unscoped override.
Severity:    HIGH if violated — but easy to verify via the grep above.
```

## §9 — Approval gate

```
Plan author:    Claude Opus in cowork lane (per current routing).
Plan reviewer:  User (Ji-woon) — primary authority.
Optional:       GPT strategic review.

Before Phase 2 execution begins:
  - User reviews this plan in full.
  - User signals approval explicitly (e.g., "approved", "execute", "Phase 2 go").
  - Phase 2 execution then runs the §6 validation plan sequence.

If User has revisions:
  - Plan is amended (this file is updated).
  - Re-approval required.
  - Then Phase 2 executes.

Phase 2 execution does NOT proceed without explicit approval.
```

## §10 — Cross-references

```
Phase 0:    docs/v3.5.1/BUTTON-PHASE-0-REPORT.md         (canonical Phase 0)
Phase 1:    lab/modules/button/docs/BUTTON-SPEC-AUDIT.md
            lab/modules/button/docs/BUTTON-MEASUREMENT-AUDIT.md
            lab/modules/button/docs/BUTTON-WP-MAPPING.md

Pre-entry decision: CURRENT-STATE.md "Phase 2 pre-entry decision" section
                    (Option b, decided 2026-05-16)

Framework:  docs/v3.5.0/MODULE-STATUS-MATRIX.md row #1   (Button)
            docs/v3.5.0/PROMOTION-CRITERIA.md §4.1       (Component Full-Spec criteria)
            docs/v3.5.0/PROMOTION-CRITERIA.md §7         (G1–G10 universal gates)
            docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §3     (4-tier architecture)
            docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md §4     (DISTINCT but COUPLED)

Baseline:   products/reference-implementations/axismundi-lab/stylesheets/components.css
              §0 L22–L79   State-layer foundation
              §2 L122–L234 Button
            products/reference-implementations/axismundi-lab/style-guide.html
              L624–L693    #components-button anchor
            products/reference-implementations/axismundi-lab/stylesheets/tokens.css
              L817 / L818 / L834  button-related tokens

Template:   lab/modules/chip/lab-chip.css                (v3.4.9 reference)
            lab/modules/chip/lab-chip-pattern.html       (v3.4.9 reference)

BACKLOG:    #18  Snackbar naming sweep (scheduled v3.5.x)
            #24  Matrix consumer-state column (Phase 0 Risk 1 — v3.5.x)
            #25  Ripple v2 contract (Phase 0 §3.7 — v3.5.x; ← where animated
                  ripple wiring for Button + other consumers will land)
            #26  Matrix row #36 allowlist correction (Phase 0 §3.5.4 — v3.5.x)
            #27  data-ax-ripple opt-in (Phase 0 §3.8 — v3.5.x)
```

## §11 — One-line plan summary

```
Phase 2 creates exactly two deliverable artifacts — lab-button.css (pattern
variations + demo scaffolding; bare .ax-button overrides FORBIDDEN, scoped
.lab-button-demo .ax-button visualization ALLOWED for opacity/state-layer/
token-swap only; NO ripple wiring; NO lab-button.js) and lab-button-
pattern.html (5 variants × label-only / with-icon / disabled-native / bare
+ has-state-layer opt-out demo + aria-disabled plugin-managed single
specimen with explicit caption + bilingual code snippets), then phase
bookkeeping updates BUTTON-SPEC-AUDIT.md §11 verdict criterion #3 and
CURRENT-STATE.md (NEXT-SESSION.md only at true session boundary), preserves
baseline §0 + §2 + #components-button anchor + tokens.css unchanged, targets
G1 / G2 / G4 / G7 / G8 readiness (G3 deferred to environment, G5 / G6 to
Phase 5 / Phase 3, G9 / G10 already done), and routes animated ripple,
toggle, XS/M/L/XL sizes, matrix amendments, naming sweeps, theme policies,
plugin work, and federation actions to their own future releases.
```
