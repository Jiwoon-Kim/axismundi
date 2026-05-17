# Axismundi v3.5.3 — Card #9 Phase 2 Plan

> **Status**: PLAN-ONLY v1.0. Awaiting review/approval before Phase 2 execution.
> **Date**: 2026-05-16
> **Source authority**: `docs/v3.5.3/CARD-PHASE-0-REPORT.md` + Phase 1 audit trio.
> **Reference template**: Button v3.5.1 + Icon button v3.5.2 Phase 2 plans and lab pattern pages.
> **Pre-entry decision**: Card base ripple = NONE; interactive/action Card ripple = CANDIDATE, deferred to Ripple v2 / BACKLOG #25 + #26.

---

## §0 — Plan Scope And Gate

This is a plan-only artifact. It does not create Phase 2 deliverables.

Purpose:

```txt
1. Define exact Phase 2 deliverables.
2. Lock selector policy and non-goals.
3. Define the Card pattern HTML section structure.
4. Map validation and G1-G10 gate readiness.
5. Surface risks before execution.
```

Approval gate:

```txt
Phase 2 execution does not begin until User approves this plan.
```

---

## §1 — Lock Decisions

### §1.1 — Deliverables

Phase 2 creates exactly two deliverable artifacts:

| File | Path | Role |
|---|---|---|
| `lab-card.css` | `products/reference-implementations/axismundi-lab/modules/card/lab-card.css` | Lab-internal demo scaffolding and documentation comments on top of baseline `.card`. |
| `lab-card-pattern.html` | `products/reference-implementations/axismundi-lab/modules/card/lab-card-pattern.html` | Full variant/slot/semantics/disabled pattern page for Card. |

Not created:

```txt
lab-card.js
```

Reason:

```txt
Card Phase 2 has no runtime behavior. Animated ripple is deferred and
static catalog specimens do not need JavaScript.
```

Phase bookkeeping:

```txt
Optional after successful execution:
  CARD-SPEC-AUDIT.md §11 criterion #6 (Phase 2 artifact completeness) -> PASS

Do not update CURRENT-STATE.md unless the user asks for a phase-boundary
snapshot. Do not update NEXT-SESSION.md unless ending or handing off the
session.
```

### §1.2 — Selector Policy

Decision:

```txt
Use .lab-card-demo as the lab page scope marker.
```

Allowed:

```txt
.lab-card-demo layout scaffolding
.lab-card-demo .card[...] visualization-only helpers
module-private --lab-card-* layout variables
comments declaring dependency profile and audit references
```

Forbidden:

```txt
unscoped .card overrides
unscoped .card--filled / .card--elevated / .card--outlined overrides
new .card__body selector
variant color overrides
baseline padding/radius/elevation overrides
ripple wiring
JavaScript dependencies
```

Scoped visualization helpers, if used, must be limited to:

```txt
opacity
state-layer explanation
demo-only layout affordance
```

They must not change baseline component measurements.

### §1.3 — WordPress Bridge Handling

Decision:

```txt
Do not edit blocks.css.
Do not register block styles.
Do include one current-bridge specimen in lab-card-pattern.html:
  <div class="wp-block-group is-style-card-filled">...</div>
```

Purpose:

```txt
Show that the existing core/group card bridge is CURRENT partial.
Make the bridge visible to Phase 3 QA without turning styleguide/ into
source authority.
```

Caption requirement:

```txt
This specimen demonstrates the existing blocks.css bridge only.
It is not the source Card API and it does not cover interactive Card
semantics, media/title/subtitle/actions slots, or disabled behavior.
```

### §1.4 — Static Catalog Caption Rule

Any specimen that looks interactive but has no wired behavior must say so.

Required for:

```txt
action button-card
navigation anchor-card
aria-disabled plugin-managed button-card
```

Caption wording:

```txt
Static catalog specimen — no runtime action is wired here. Production
usage wires behavior at the integrator level (theme JS / block editor /
plugin).
```

Korean caption:

```txt
정적 카탈로그 specimen — 여기서는 runtime action을 배선하지 않는다.
실제 사용 시 통합 레벨(theme JS / block editor / plugin)에서 behavior를
배선한다.
```

### §1.5 — Disabled 3-Way Split

Decision:

```txt
Use the Phase 1 Card split:
  locked non-interactive article
  native disabled button-card
  aria-disabled plugin-managed button-card
```

Each disabled case needs a user-facing caption.

Especially for `aria-disabled`:

```txt
aria-disabled exposes disabled state and receives Pattern B styling, but
does not block activation. The integrator/plugin must guard click and
keyboard behavior.
```

### §1.6 — Body Content Scope

Decision:

```txt
Body text is un-classed direct content inside .card.
Do not create .card__body in CSS or HTML.
```

Allowed:

```html
<article class="card card--filled">
  <h3 class="card__title">...</h3>
  <p>Body content...</p>
</article>
```

Forbidden:

```html
<div class="card__body">...</div>
```

---

## §2 — `lab-card.css` Scope

### §2.1 — Purpose

`lab-card.css` is lab-internal scaffolding for the Card pattern page.

The component styling remains baseline-owned by:

```txt
components.css §5 Card
components.css §0 State-layer foundation
blocks.css §8 core/group card bridge
```

### §2.2 — Allowed Content

```txt
Allowed:
  - dependency header and audit references
  - .lab-card-demo wrapper layout
  - .lab-card-section layout
  - .lab-card-grid / .lab-card-row layout
  - specimen captions and code-snippet styling
  - media placeholder layout for demo images/blocks
  - module-private --lab-card-* layout variables
```

Expected file size:

```txt
120-200 lines
```

Card's pattern page is richer than Button/Icon button because it includes
variant, media, slot, action, navigation, disabled, and WordPress bridge specimens.

### §2.3 — Forbidden Content

```txt
Forbidden:
  - unscoped .card or .card--* overrides
  - new .card__body selector
  - new --md-sys-* tokens
  - color literals for component surfaces
  - baseline padding/radius/elevation changes
  - changes to Button/Icon button controls inside .card__actions
  - data-ax-ripple, <md-ripple>, lab-ripple, or ripple.js
  - JavaScript hooks required for rendering
```

Verification grep:

```powershell
rg -n "^\\.card|^[^.].*\\.card" products\reference-implementations\axismundi-lab\modules\card\lab-card.css
```

Any selector affecting `.card` without `.lab-card-demo` scope is suspect.

---

## §3 — `lab-card-pattern.html` Scope

### §3.1 — Purpose

The pattern page demonstrates the baseline-supported Card contract in one lab-internal page:

```txt
variants
media slot
title/subtitle/un-classed body content
actions slot composition
native semantics decision tree
disabled Pattern B split
state-layer opt-out
current WordPress core/group bridge
code snippets
cross-references
```

### §3.2 — Required Section Structure

```txt
§1  Page header
    - title
    - Experimental lab preview banner
    - links to style-guide.html#components-card and audit docs

§2  Variants — non-interactive articles (3)
    - filled
    - elevated
    - outlined
    Use <article class="card card--...">.

§3  With media slot (3)
    - filled / elevated / outlined cards with .card__media
    - demo media blocks or images
    - no external image dependency required

§4  Title / subtitle / body content
    - .card__title
    - .card__subtitle
    - un-classed body paragraph
    - explicit note: no .card__body selector

§5  Actions slot composition
    - .card__actions with Button
    - .card__actions with Icon button
    - nested controls own their own accessibility
    - Card owns the slot layout only

§6  Interactive variants
    §6a Action card
        <button class="card card--elevated card--interactive has-state-layer"
                type="button">
        Static catalog caption required.

    §6b Navigation card
        <a class="card card--outlined card--interactive has-state-layer"
           href="#...">
        Static catalog caption required.

§7  Disabled Pattern B split
    §7a Locked non-interactive article
        <article class="card ..." aria-disabled="true">
        Caption: no activation exists.

    §7b Native disabled button-card
        <button class="card card--interactive" type="button" disabled>
        Caption: browser/platform blocks activation.

    §7c aria-disabled plugin-managed button-card
        <button class="card card--interactive" type="button" aria-disabled="true">
        Caption: integrator/plugin must guard activation.

§8  has-state-layer opt-out demo
    - one interactive card with has-state-layer
    - one interactive card without has-state-layer
    - explicit caption that missing overlay is intentional

§9  WordPress current bridge specimen
    - one <div class="wp-block-group is-style-card-filled">...</div>
    - caption: existing blocks.css bridge, CURRENT partial, not source API

§10 Code snippets
    - static article card
    - action button-card
    - navigation anchor-card
    - actions slot composition
    - disabled split
    - WordPress core/group bridge

§11 Cross-references
    - Phase 0 report
    - Phase 1 plan
    - SPEC / MEASUREMENT / WP-MAPPING
    - Button / Icon button precedents
```

Expected size:

```txt
360-520 lines
```

### §3.3 — Canonical Markup

Static card:

```html
<article class="card card--filled">
  <h3 class="card__title">Card title</h3>
  <p>Un-classed body content.</p>
</article>
```

Action card:

```html
<button class="card card--elevated card--interactive has-state-layer" type="button">
  <span class="card__title">Run action</span>
  <span>Un-classed body content.</span>
</button>
```

Navigation card:

```html
<a class="card card--outlined card--interactive has-state-layer" href="#card-demo">
  <span class="card__title">Open destination</span>
  <span>Un-classed body content.</span>
</a>
```

Rules:

```txt
no <article role="button" tabindex="0">
no clickable div
no .card__body
no nested interactive controls inside button-card
no ripple markup
```

---

## §4 — Dependency Header For CSS

`lab-card.css` should start with:

```css
/* ============================================================
 * lab-card.css
 *
 * Lab-internal pattern page scaffolding on top of baseline:
 *   - components.css §5 Card — CURRENT baseline primitive
 *   - components.css §0 State-layer foundation — CURRENT for
 *     interactive card specimens
 *   - blocks.css §8 core/group card bridge — CURRENT partial
 *
 * Composition dependencies:
 *   - icon-system/ — CURRENT conditional via nested Icon button
 *     inside .card__actions only
 *
 * Candidate / future dependencies (NOT wired at v3.5.3):
 *   - lab/modules/ripple/ — CANDIDATE for action/interactive Card,
 *     deferred to Ripple v2 (BACKLOG #25 + #26)
 *
 * Audit docs:
 *   - docs/CARD-SPEC-AUDIT.md
 *   - docs/CARD-MEASUREMENT-AUDIT.md
 *   - docs/CARD-WP-MAPPING.md
 *
 * Baseline/public files remain unchanged.
 * No .card__body selector is introduced.
 * ============================================================ */
```

---

## §5 — G1-G10 Gate Readiness

| Gate | Phase 2 target | Verification |
|---|---|---|
| G1 validator | preserve PASS | `python tools\validators\validate_theme_pilot.py` |
| G2 baseline untouched | preserve | mtime/readback for components.css, blocks.css, style-guide.html |
| G3 publish clean | no publish change expected | optional, not required for Phase 2 |
| G4 module artifacts | create two files | `lab-card.css` + `lab-card-pattern.html` |
| G5 changelog | later | Phase 5 |
| G6 visual QA | later | Phase 3 |
| G7 real controls | achieve | action cards are buttons; navigation cards are anchors |
| G8 native semantics | achieve | no fake clickable article/div |
| G9 WCAG SC accuracy | already achieved | MEASUREMENT records static vs interactive nuance |
| G10 3-doc audit | already achieved | Phase 1 trio exists |

---

## §6 — Validation Plan

After approved Phase 2 execution:

```powershell
# 1. Create exactly two deliverable artifacts
Test-Path products\reference-implementations\axismundi-lab\modules\card\lab-card.css
Test-Path products\reference-implementations\axismundi-lab\modules\card\lab-card-pattern.html
Test-Path products\reference-implementations\axismundi-lab\modules\card\lab-card.js
# expected: True, True, False

# 2. Marker check
rg -n "\[NEXT SESSION:\]" products\reference-implementations\axismundi-lab\modules\card
# expected: no hits

# 3. Native semantics anti-pattern check
rg -n "role=\"button\"|tabindex=\"0\"|<div[^>]*class=\"[^\"]*card|<article[^>]*role=\"button\"" products\reference-implementations\axismundi-lab\modules\card\lab-card-pattern.html
# expected: no fake interactive card hits

# 4. Action card button type
rg -n "<button(?![^>]*type=)" products\reference-implementations\axismundi-lab\modules\card\lab-card-pattern.html
# expected: no rendered button without type

# 5. No invented body selector
rg -n "card__body" products\reference-implementations\axismundi-lab\modules\card
# expected: hits only in explanatory forbidden text, not live markup/CSS selectors

# 6. No ripple wiring
rg -n "data-ax-ripple|md-ripple|lab-ripple|ripple\\.js" products\reference-implementations\axismundi-lab\modules\card
# expected: no hits except explanatory text if any

# 7. Validator
python tools\validators\validate_theme_pilot.py
# expected: 1.000 / 1.000 / 1.000 / 1.000 PASS
```

Optional phase bookkeeping after successful execution:

```txt
Update CARD-SPEC-AUDIT.md §11 criterion #6 from TBD to PASS.

Do not update CURRENT-STATE.md unless the user wants a phase-boundary
snapshot. Do not update NEXT-SESSION.md unless ending or handing off the
session.
```

---

## §7 — Explicit Non-Goals

Phase 2 does not:

```txt
- edit components.css
- edit blocks.css
- edit style-guide.html
- edit tokens.css
- edit theme.json
- edit BACKLOG.md
- edit CHANGELOG.md
- edit ROADMAP.md
- edit CURRENT-STATE.md unless explicitly requested as phase-boundary bookkeeping
- edit NEXT-SESSION.md
- create lab-card.js
- create .card__body
- wire current ripple
- implement Ripple v2
- implement Lab Preview Routes
- register WordPress block styles
- correct blocks.css comment drift
- rename .card to .ax-card
- implement custom Card block runtime
- implement editor UI
- implement drag/drop, expandable cards, or sorting
- implement ActivityPub/social actions
- create fixed Card size variants or grids
```

---

## §8 — Risks

### Risk A — Shadow Baseline

If `lab-card.css` styles `.card` globally, it becomes a second baseline.

Mitigation:

```txt
Only demo scaffolding and scoped helpers under .lab-card-demo.
No unscoped .card or .card--* overrides.
```

### Risk B — Fake Interactive Card Regression

Card is especially vulnerable to clickable article/div patterns.

Mitigation:

```txt
Pattern HTML includes only native button for action and anchor for
navigation. Anti-patterns appear only as escaped snippets or explanatory
text, not live specimens.
```

### Risk C — Nested Interactive Controls Inside Button-Card

A button-card cannot contain nested buttons or links.

Mitigation:

```txt
Nested Button/Icon button composition appears only in static article cards
with .card__actions, never inside button-card specimens.
```

### Risk D — `aria-disabled` Misread As Native Disabled

`aria-disabled` does not block activation.

Mitigation:

```txt
Separate §7b native disabled from §7c plugin-managed aria-disabled.
Add bilingual warning captions.
```

### Risk E — WordPress Bridge Confusion

The `.wp-block-group.is-style-card-*` specimen may be mistaken for source authority.

Mitigation:

```txt
Caption says it is an existing blocks.css bridge, CURRENT partial, not
the source Card API.
```

### Risk F — `.card__body` API Creep

Pattern authors may invent a body slot because many card libraries have one.

Mitigation:

```txt
Use un-classed body paragraphs. Include `.card__body` only in forbidden
text/snippet warnings.
```

### Risk G — Ripple Drift

Interactive Card looks like a ripple candidate, so implementers may wire current ripple.

Mitigation:

```txt
No lab-card.js.
No data-ax-ripple.
No lab-ripple dependency.
Card action ripple remains CANDIDATE for Ripple v2.
```

---

## §9 — Approval Gate

Phase 2 execution is blocked until this plan is approved.

Approved execution means:

```txt
Create lab-card.css.
Create lab-card-pattern.html.
Do not create JS.
Do not edit baseline/public/state/handoff files.
Run validation.
Report changed files and risks.
```

If review finds gaps:

```txt
Revise this plan to v1.1.
Re-run validator.
Wait for approval again.
```

---

## §10 — One-Line Summary

```txt
Card Phase 2 should add exactly two lab-internal artifacts
(lab-card.css + lab-card-pattern.html), demonstrate three Card variants,
media/title/subtitle/un-classed body content, actions slot composition,
native action and navigation card semantics, disabled Pattern B three-way
split, state-layer opt-out, and one current core/group bridge specimen,
while preserving baseline/public files, not inventing .card__body, and
keeping interactive Card ripple as CANDIDATE for Ripple v2.
```
