# Axismundi v3.5.10 — Button Group #6 Phase 2 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 2 execution.  
> **Date**: 2026-05-17  
> **Component**: Button group #6  
> **Source authority**: `docs/v3.5.10/BUTTON-GROUP-PHASE-0-REPORT.md` + Button group Phase 1 audit trio.  
> **Pre-entry decision**: 3-doc Component Full-Spec cycle; no runtime audit; ripple bounded TARGET per segment.

---

## §0 — Plan Scope And Gate

This is a plan-only artifact. It does not create Phase 2 deliverables.

Purpose:

```txt
1. Lock the exact Phase 2 file scope.
2. Lock v3.5.9 pill-radius inheritance and token non-promotion.
3. Define Pattern A / Pattern B specimen coverage.
4. Define bounded ripple protocol per segment.
5. Define Playwright pre-check and Phase 3 entry criteria.
```

Approval gate:

```txt
Phase 2 execution does not begin until User approves this plan.
```

---

## §1 — Lock Decisions

### §1.1 — Deliverables

Phase 2 creates exactly three deliverable artifacts:

| File | Path | Role |
|---|---|---|
| `lab-button-group.css` | `products/reference-implementations/axismundi-lab/modules/button-group/lab-button-group.css` | Lab-scoped demo layout, captions, QA hooks, and visualization-only wrappers around baseline `.ax-button-group`. |
| `lab-button-group.js` | `products/reference-implementations/axismundi-lab/modules/button-group/lab-button-group.js` | Small Pattern B demo controller for `button[aria-pressed]` toggles. Pattern A remains native radio and does not use JS. |
| `lab-button-group-pattern.html` | `products/reference-implementations/axismundi-lab/modules/button-group/lab-button-group-pattern.html` | Pattern page covering Pattern A/B, connected geometry, content variants, states, ripple, disabled cases, and snippets. |

Not created:

```txt
BUTTON-GROUP-RUNTIME-AUDIT.md
additional baseline CSS files
WordPress block registration files
```

Reason for `lab-button-group.js`:

```txt
Pattern A is native: input[type=radio] + label.
Pattern B is button-based: button[type=button][aria-pressed].

Pattern B needs a small demo controller to flip aria-pressed for interactive
catalog evidence. This does not promote Button group into Interaction Runtime:
the JS is local to the lab pattern page and has no reusable provider API.
```

Phase bookkeeping:

```txt
After successful execution:
  BUTTON-GROUP-SPEC-AUDIT.md §12 criterion #3 (Pattern HTML completeness)
  -> PASS at Phase 2 level.

Do not update CURRENT-STATE.md unless the user asks for a phase-boundary
snapshot. Do not update NEXT-SESSION.md unless ending or handing off the
session.
```

### §1.2 — Token Decision

Decision:

```txt
Keep v3.5.9 local variable through Phase 2.
Do not promote public Button group tokens in v3.5.10 Phase 2.
```

Current baseline stays:

```css
.ax-button-group {
  --_button-group-pill-radius: calc(var(--comp-button-height) / 2);
}
```

Not edited:

```txt
tokens.css
components.css
theme.json
```

Rationale:

```txt
Phase 1 MEASUREMENT recommends not promoting public --comp-button-group-*
tokens until actual size-variant need is proven. Phase 2 is a lab artifact
cycle, not a baseline token graph cycle.

v3.5.9 already fixed the morphing flicker with a local finite radius. Phase 2
should validate that current baseline, not redesign it.
```

Future venue:

```txt
If Phase 3 finds that XS/S/L/XL connected Button group variants require public
component tokens, route that as a follow-up baseline/token amendment rather
than expanding Phase 2.
```

### §1.3 — Selector Policy

Decision:

```txt
Use .lab-button-group-demo as the lab page scope marker.
```

Allowed:

```txt
.lab-button-group-demo layout scaffolding
.lab-button-group-demo .ax-button-group[...] visualization-only wrappers
module-private --lab-button-group-* layout variables
data-qa-* attributes for Playwright
comments declaring dependency profile and audit references
```

Forbidden:

```txt
unscoped .ax-button-group overrides
unscoped .ax-button-group__input overrides
unscoped .ax-button overrides
unscoped [data-ax-ripple] overrides
baseline token overrides
public --comp-button-group-* token creation
```

Scoped visualization helpers must not change baseline component measurements.

### §1.4 — Baseline Edit Lock

Phase 2 does not edit:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/theme.json
```

Interpretation:

```txt
The Phase 2 module demonstrates Button group Full-Spec patterns on top of the
current baseline. It does not migrate §28 into a different public token model.
```

---

## §2 — Pattern A / Pattern B Coverage

### §2.1 — Pattern A: Native Radio Group

Pattern A demonstrates single-select / selection-required cases:

```html
<fieldset class="ax-button-group ax-button-group--connected"
          data-qa="pattern-a-radio">
  <legend class="u-vh">Density</legend>
  <input type="radio" name="density" id="density-compact"
         class="ax-button-group__input">
  <label for="density-compact"
         class="ax-button is-tonal has-state-layer"
         data-ax-ripple="bounded">Compact</label>
  ...
</fieldset>
```

Rules:

```txt
- Keep the input in the accessibility tree.
- Do not use display:none on the radio input.
- Use label as the visible segment.
- Use native radio keyboard behavior.
- Document the M3 web guidance vs native radio tension in captions.
```

Ripple protocol:

```txt
Primary test: labels receive data-ax-ripple="bounded".
If Playwright/runtime verification shows label hosts are unsuitable ripple
hosts, stop and report before changing architecture. Do not silently move
Pattern A away from native radio semantics.
```

### §2.2 — Pattern B: Button Toggle Group

Pattern B demonstrates multi-toggle / toolbar-like cases:

```html
<div class="ax-button-group ax-button-group--connected"
     role="toolbar"
     aria-label="Text formatting"
     data-qa="pattern-b-toolbar">
  <button type="button"
          class="ax-button is-tonal has-state-layer"
          aria-pressed="true"
          data-ax-ripple="bounded">Bold</button>
  ...
</div>
```

Rules:

```txt
- Each segment is a real button.
- aria-pressed reflects the selected state.
- lab-button-group.js toggles aria-pressed only in the lab page.
- Do not create reusable runtime provider API.
- Do not use Pattern B for mutually exclusive form values unless a plugin
  owns the behavior intentionally.
```

Keyboard:

```txt
Tab reaches each button according to normal button flow.
Space / Enter activates the focused button.
```

### §2.3 — Caption Discipline

Pattern page captions must explicitly say:

```txt
Pattern A:
  Native radio pattern. Arrow-key behavior comes from the browser.

Pattern B:
  Button toggle pattern. This demo JS flips aria-pressed for catalog evidence.

M3 tension:
  Official M3 web guidance describes button-item Tab / Space / Enter behavior.
  Native radio groups use radio keyboard behavior. Axismundi keeps both
  patterns because they serve different semantics.
```

---

## §3 — Pattern HTML Structure

`lab-button-group-pattern.html` should use this structure:

```txt
§1 Status / dependencies
§2 Standard Button group
§3 Connected Button group
§4 Pattern A — radio + label single-select
§5 Pattern B — button aria-pressed multi-toggle
§6 Segment count matrix: 2 / 3 / 4 / 5
§7 Content matrix: label-only / icon+label / icon-only
§8 Size specimen: default M + representative XS/L/XL
§9 Disabled states
§10 Bounded ripple specimens
§11 v3.5.9 pill radius verification specimen
§12 WordPress core/buttons approximation specimen
§13 Code snippets
§14 Cross-references
```

Avoid full cartesian explosion:

```txt
Show representative specimens that prove each axis:
  structure
  segment count
  selection mode
  content type
  size
  disabled state
  ripple
```

Minimum live specimens:

| Section | Minimum specimens |
|---|---:|
| Standard group | 2 |
| Connected group | 3 |
| Pattern A radio | 2 |
| Pattern B toggle | 2 |
| Segment count | 4 |
| Icon content | 3 |
| Disabled | 3 |
| Ripple | 2 |
| WordPress approximation | 1 |

---

## §4 — CSS Plan

`lab-button-group.css` is lab-scoped only.

Allowed responsibilities:

```txt
page shell
demo grids
specimen captions
responsive wrapping tests
QA outlines / data-qa labels
static state explanation blocks
```

Forbidden responsibilities:

```txt
changing .ax-button-group geometry
changing .ax-button-group--connected gap
changing .ax-button border radius
changing selected color tokens
changing v3.5.9 finite pill variable
adding baseline size variants
```

Representative selectors:

```css
.lab-button-group-demo { ... }
.lab-button-group-demo__grid { ... }
.lab-button-group-demo__specimen { ... }
.lab-button-group-demo [data-qa="overflow-check"] { ... }
```

Do not write:

```css
.ax-button-group { ... }
.ax-button-group--connected { ... }
.ax-button-group .ax-button { ... }
```

Exception:

```txt
Scoped QA wrappers may style a parent container, never the baseline component
selector itself. If a baseline selector seems necessary, stop and report.
```

---

## §5 — JS Plan

`lab-button-group.js` is a local demo script.

Required behavior:

```txt
1. Find Pattern B specimens with data-button-group-toggle-demo.
2. On click, toggle aria-pressed for the clicked button.
3. Dispatch no custom events.
4. Store no persistent state.
5. Avoid touching Pattern A radio groups.
```

Suggested minimal shape:

```js
const groups = document.querySelectorAll('[data-button-group-toggle-demo]');

groups.forEach((group) => {
  group.addEventListener('click', (event) => {
    const button = event.target.closest('button[aria-pressed]');
    if (!button || !group.contains(button) || button.disabled) return;
    const next = button.getAttribute('aria-pressed') !== 'true';
    button.setAttribute('aria-pressed', String(next));
  });
});
```

Constraints:

```txt
No dependency on ripple internals.
No dependency on WordPress.
No reusable window.axButtonGroup API.
No keyboard override for native button activation.
No radio-group JS.
```

Reason:

```txt
The component remains Component Full-Spec, not Interaction Runtime. The JS is
catalog scaffolding so Pattern B can show state changes in Phase 3 QA.
```

---

## §6 — Ripple Protocol

Button group is promoted to ripple TARGET for v3.5.10.

Protocol:

```txt
Attach data-ax-ripple="bounded" to each visible segment.
Do not attach ripple to .ax-button-group container.
Use bounded variant only.
Do not use unbounded.
Do not rewrite lab-ripple.
```

Pattern A:

```html
<label class="ax-button is-tonal has-state-layer"
       data-ax-ripple="bounded">Week</label>
```

Pattern B:

```html
<button type="button"
        class="ax-button is-tonal has-state-layer"
        aria-pressed="true"
        data-ax-ripple="bounded">Bold</button>
```

QA requirements:

```txt
- Ripple stays within each segment.
- Ripple follows selected segment radius.
- Ripple does not bleed into neighboring segment.
- Ripple does not appear on the group container.
- Label host behavior is verified before Phase 3 PASS.
```

Fallback rule:

```txt
If Pattern A label ripple host fails, do not replace Pattern A semantics.
Document the finding and route a Ripple v2 label-host amendment or use Pattern
B specimens for animated ripple evidence only after review.
```

---

## §7 — Disabled State Coverage

Use Button family Pattern A disabled split.

Native disabled:

```txt
Pattern A: input[type=radio][disabled] + label
Pattern B: button[disabled]
```

Aria-disabled plugin-managed:

```txt
button[aria-disabled="true"]
```

Specimens:

```txt
§9a segment-level native disabled
§9b whole-group native disabled, represented by disabling every segment
§9c aria-disabled plugin-managed specimen with caption
```

Caption requirement:

```txt
aria-disabled does not block activation by itself. Integrators must suppress
click/key behavior and preserve accessible explanation.
```

---

## §8 — WordPress Mapping Specimen

Phase 2 includes one lab specimen showing the visual approximation:

```txt
core/buttons-like row -> Button group visual pattern
```

Boundary:

```txt
This is a lab specimen only.
Do not register a core/buttons style.
Do not edit functions.php.
Do not edit blocks.css.
Do not claim core/buttons is a full semantic mapping.
```

Caption:

```txt
core/buttons can approximate an inline row visually, but connected selection
state and radio/pressed semantics require theme pattern markup or plugin
ownership.
```

---

## §9 — Playwright Pre-Check

Phase 2 execution should run Playwright before handing to manual Phase 3 QA.

Required checks:

```txt
1. 2/3/4/5-segment groups render without overflow at 390 / 768 / 1280 px.
2. Connected default M outer radius remains 20px.
3. Connected default M inner rest radius is 8px.
4. Connected default M pressed inner radius is 4px.
5. Selected segment rest radius is 20px.
6. Selected+pressed inner radius is 4px, first/last outer radius remains 20px.
7. Pattern A arrow-key behavior changes checked radio.
8. Pattern B Tab + Space/Enter toggles aria-pressed.
9. Focus ring is visible on selected and unselected segments.
10. Bounded ripple stays inside each segment.
11. Reduced motion mode does not break layout or state visibility.
12. Icon-only segment remains at least 48px target in live rendering.
```

Suggested viewport matrix:

```txt
390x844
768x1024
1280x900
```

Evidence:

```txt
Screenshots may be written under docs/v3.5.10/*-qa.png if needed.
Those artifacts are ignored by repo policy and should not be release-critical.
```

---

## §10 — Wave 1 Regression Smoke

Phase 2 must not disturb closed Wave 1 components.

Smoke list:

```txt
Button family:
  v3.5.9 :active finite pill morph still stable.

Icon button:
  no geometry or ripple regression.

FAB:
  no geometry regression.

Text field:
  no pattern or input-shell regression.

Search bar:
  no field-host ripple regression and no native clear-button regression.
```

Expected because:

```txt
Phase 2 edits only lab/modules/button-group/* plus SPEC bookkeeping.
No baseline files are edited.
```

---

## §11 — Validation Plan

Before execution:

```txt
python .\tools\validators\validate_theme_pilot.py
```

After file creation:

```txt
python .\tools\validators\validate_theme_pilot.py
```

Readback checks:

```txt
rg -n "data-ax-ripple=\"bounded\"" products/reference-implementations/axismundi-lab/modules/button-group
rg -n "aria-pressed" products/reference-implementations/axismundi-lab/modules/button-group
rg -n "input type=\"radio\"" products/reference-implementations/axismundi-lab/modules/button-group
rg -n "^\\.ax-button-group|^\\.ax-button " products/reference-implementations/axismundi-lab/modules/button-group/lab-button-group.css
```

Expected:

```txt
validator remains 1.000 / 1.000 / 1.000 / 1.000 PASS
lab-button-group.css has no unscoped baseline selectors
Pattern A and Pattern B both present
data-ax-ripple appears only on segments
```

---

## §12 — Edit Protocol

Use:

```txt
apply_patch
readback
abort on mismatch
```

Do not:

```txt
fresh Write as automatic fallback
mv existing files aside unless user approves or file is actually corrupt
rewrite CURRENT-STATE.md
rewrite NEXT-SESSION.md
```

If readback mismatch appears:

```txt
1. Stop.
2. Compare PowerShell view and direct file read.
3. Report mismatch to user.
4. Do not continue patching until resolved.
```

---

## §13 — Phase 3 Entry Criteria

Phase 2 can enter Phase 3 only when:

```txt
1. lab-button-group.css exists.
2. lab-button-group.js exists.
3. lab-button-group-pattern.html exists.
4. BUTTON-GROUP-SPEC-AUDIT.md §12 criterion #3 is marked PASS at Phase 2.
5. Validator is 1.000 PASS.
6. Playwright pre-check passes.
7. Wave 1 regression smoke has no blocker.
8. Baseline files are untouched.
```

Blockers:

```txt
Pattern A radio inaccessible
Pattern B aria-pressed toggle broken
ripple bleeding across connected segment boundaries
mobile overflow for representative 5-segment group
v3.5.9 pill morph regression
```

---

## §14 — Non-Goals

Phase 2 does not:

```txt
- edit tokens.css
- edit components.css
- edit blocks.css
- edit style-guide.html
- edit theme.json
- register WordPress block styles
- implement Split button
- implement Toolbar
- implement standalone toggle button
- create RUNTIME-AUDIT
- create reusable JS runtime API
- alter Ripple v2
- alter v3.5.9 pill-stable token value
- alter Button / Icon button / FAB / Text field / Search bar modules
- update CHANGELOG.md
- update ROADMAP.md
- update BACKLOG.md
- update CURRENT-STATE.md
- update NEXT-SESSION.md
```

---

## §15 — Risks

| Risk | Severity | Mitigation |
|---|---:|---|
| Pattern A label is not a reliable ripple host | Medium | Verify in Playwright; stop and report before fallback |
| Pattern A/B keyboard semantics confused | Medium | Captions and separate sections; do not merge behaviors |
| 5-segment group overflows mobile | Medium | Playwright viewport matrix before Phase 3 |
| JS scope creeps into runtime provider | Low | `lab-button-group.js` remains local; no public API |
| Token promotion sneaks into Phase 2 | Low | tokens.css/components.css locked out |
| core/buttons visual specimen overclaims semantics | Low | Caption as partial approximation only |

---

## §16 — Self-Check

```txt
self-check:
  exact deliverables / 3 files             present
  lab-button-group.js decision             locked (Pattern B demo only)
  local token retained                     locked
  public token promotion                   deferred
  Pattern A radio+label                    covered
  Pattern B aria-pressed                   covered
  ripple bounded per segment               covered
  label ripple host verification           covered
  baseline untouched                       locked
  Playwright pre-check                     defined
  Phase 3 entry criteria                   defined
  CURRENT-STATE/NEXT-SESSION untouched     locked
```

---

## §17 — Plan Verdict

```txt
READY FOR REVIEW.

If approved:
  Phase 2 execution creates exactly three lab artifacts and performs limited
  SPEC bookkeeping.

If revised:
  update this plan only; do not start implementation.
```

