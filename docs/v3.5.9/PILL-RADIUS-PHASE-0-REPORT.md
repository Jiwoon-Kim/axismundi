# v3.5.9 — BACKLOG #31 — Pill Radius Interpolation Correction Phase 0 Report

> **Status**: Phase 0 complete; awaiting Phase 1 correction audit plan.  
> **Release kind**: Baseline correction / token graph amendment.  
> **Source**: `BACKLOG.md` #31.  
> **Scope**: Documentation-only in Phase 0. No baseline edits.

---

## §0 — Framing

BACKLOG #31 is a token-graph problem, not a component styling preference.

`--md-sys-shape-corner-full: 9999px` works correctly for static pill
contexts. It becomes visually unstable only when a component transitions from
that very large source value to a smaller pressed or selected shape.

Decision boundary:

```txt
Preserve static semantics:
  --md-sys-shape-corner-full stays 9999px.

Correct morphing semantics:
  morphing source radii should use a finite component-height-based value.
```

This release should close the problem before Button group #6 Full-Spec work,
because Button group already has baseline connected-pill and selected+pressed
shape morph rules.

---

## §1 — Inputs Read

```txt
BACKLOG.md #31
docs/v3.5.9/PILL-RADIUS-PHASE-0-PLAN.md
docs/v3.5.0/PROMOTION-CRITERIA.md
products/reference-implementations/axismundi-lab/stylesheets/tokens.css
products/reference-implementations/axismundi-lab/stylesheets/components.css
```

Quick references from baseline:

```txt
tokens.css:654   --md-sys-shape-corner-full: 9999px
tokens.css:818   --comp-button-radius: var(--md-sys-shape-corner-full)
tokens.css:826   --comp-avatar-radius: var(--md-sys-shape-corner-full)
```

---

## §2 — Current Token Graph Inventory

Current shape graph:

| Token | Current value | Phase 0 classification |
|---|---|---|
| `--md-sys-shape-corner-full` | `9999px` | Static logical full pill. KEEP. |
| `--comp-button-radius` | `var(--md-sys-shape-corner-full)` | Morph source for Button. MIGRATE. |
| `--comp-avatar-radius` | `var(--md-sys-shape-corner-full)` | Static circular avatar. KEEP. |
| `--_fab-radius` | `16px` / `20px` / `28px` per size | Already finite; not a `corner-full` consumer. KEEP. |

Key finding:

```txt
FAB was listed as "likely affected" in BACKLOG #31, but Phase 0 inventory
does not confirm that. FAB already uses finite radii:
  56px FAB  -> 16px
  80px FAB  -> 20px
  96px FAB  -> 28px
Extended FAB -> 16px

FAB active state changes state-layer opacity and elevation, not border-radius.
Therefore FAB is not a v3.5.9 migration target.
```

---

## §3 — `corner-full` Consumer Table

This table records direct `corner-full` or `corner-full`-derived consumers
found in `tokens.css` and `components.css`.

Legend:

```txt
STATIC_PILL              no state-driven radius morph
MORPH_SOURCE_CONFIRMED   full pill participates in radius transition/morph
MORPH_SOURCE_POSSIBLE    future or adjacent morph risk; do not patch now
OUT_OF_SCOPE             not part of v3.5.9
```

| Surface | Lines | Use | State / transition evidence | Classification | v3.5.9 action |
|---|---:|---|---|---|---|
| Shape token | tokens.css:654 | `--md-sys-shape-corner-full` | Source token | STATIC_PILL semantic | Do not change |
| Button token | tokens.css:818 | `--comp-button-radius -> corner-full` | Button transitions `border-radius`; `:active` shrinks to corner-small | MORPH_SOURCE_CONFIRMED | Migrate token value |
| Avatar token | tokens.css:826 + components.css:97 | static circular avatar | No radius morph | STATIC_PILL | Leave |
| Button | components.css:139, 152-168 | rest full pill -> active small | `border-radius` transition + `:active` rule | MORPH_SOURCE_CONFIRMED | Migrate |
| Icon button | components.css:256, 261-270 | circular touch target | transition includes radius, but no smaller state found | STATIC_PILL / no morph | Leave |
| Nav rail icon pill | components.css:684 | active indicator pill | background/color transition only | STATIC_PILL | Leave |
| Nav rail expanded item | components.css:755 | expanded item pill | no radius morph found | STATIC_PILL | Leave |
| Search bar | components.css:1500 | 56px rounded search field | background transition only | STATIC_PILL | Leave |
| Sheet handle | components.css:1981 | drag handle pill | no radius morph found | STATIC_PILL | Leave |
| Navigation active indicator | components.css:2542 / 2587 | nav bar/rail indicator pill | opacity/background only | STATIC_PILL | Leave |
| Tabs / progress / slider / switch static tracks | components.css:3057, 3194, 3672, 3966, 3984, 4376 | track or indicator pill | no smaller radius transition found | STATIC_PILL | Leave |
| Button group connected outer edges | components.css:4756-4762, 4787-4793, 4830-4837 | first/last outer pill restoration | Button group has radius transitions and pressed states | MORPH_SOURCE_CONFIRMED | Migrate directional outer full corners |
| Button group selected segment | components.css:4807 + 4811-4824 | selected full pill -> selected+pressed smaller | `border-radius` transition inherited from `.ax-button`; selected+pressed shrinks | MORPH_SOURCE_CONFIRMED | Migrate |
| Toolbar floating container | components.css:4881 | floating toolbar pill shell | no radius morph found | STATIC_PILL | Leave |
| FAB menu close | components.css:5167 | close FAB circle | no radius morph found; opacity/background only | STATIC_PILL | Leave |
| FAB menu item button | components.css:5298 | 56px item pill | background only | STATIC_PILL | Leave |
| Split button outer edges | components.css:5406-5424 | outer full pill restoration during hover | hover morphs child radius; outer full restored | MORPH_SOURCE_POSSIBLE | Defer unless scheduled with Split button |
| Split button selected | components.css:5430 | selected full pill | hover/selected geometry exists | MORPH_SOURCE_POSSIBLE | Defer to Split button cycle |
| Date picker cells/range endpoints | components.css:5573, 5653-5658 | circular date cells / range endpoints | background/selection changes, no radius morph to smaller source in v3.5.9 scope | STATIC_PILL | Leave |

Important correction:

```txt
No mass migration. The presence of `corner-full` alone is not a bug.
Only state-driven full-pill -> smaller-corner interpolation is in scope.
```

---

## §4 — Morph Verification Result

### Confirmed

Button:

```txt
components.css:139  rest border-radius uses --comp-button-radius
components.css:152  transition includes border-radius
components.css:166  .ax-button:active
components.css:168  active border-radius = corner-small

Result:
  Confirmed 9999px -> 8px interpolation path.
```

Button group:

```txt
components.css:4658  .ax-button-group .ax-button
components.css:4661  transition includes border-radius
components.css:4756  connected first outer full pill
components.css:4761  connected last outer full pill
components.css:4807  selected segment = corner-full
components.css:4811  selected+pressed state
components.css:4814  selected+pressed = corner-extra-small

Result:
  Confirmed connected/selected full-pill -> smaller pressed morph path.
  This validates v3.5.9 before Button group #6.
```

### Verified Not Affected For v3.5.9

FAB + Extended FAB:

```txt
components.css:2196  --_fab-radius = corner-large (16px)
components.css:2270  medium FAB radius = corner-large-increased (20px)
components.css:2275  large FAB radius = corner-extra-large (28px)
components.css:2336  Extended FAB radius = corner-large (16px)
components.css:2219  transition: box-shadow/background-color
components.css:2367  transition: box-shadow/background-color
components.css:2248  active/focus-visible changes elevation
components.css:2395  active/focus-visible changes elevation

Result:
  FAB does not use corner-full and does not transition border-radius.
  It should not be migrated in v3.5.9.
```

### Defer

Split button:

```txt
Already has outer full-pill restoration and hover/selected radius logic.
However Split button is not the immediate v3.5.10 route and has not had its
own Full-Spec cycle. Defer to Split button cycle unless user explicitly
expands v3.5.9.
```

---

## §5 — Options A-D Comparison Matrix

| Option | Interpolation safety | Visual accuracy | Complexity | Migration cost | Fallback safety | Verdict |
|---|---:|---:|---:|---:|---:|---|
| A: smaller static sentinel (`999px`) | Low-Med | Low | Low | Low | Med | Reject. Still arbitrary and still interpolates a large number. |
| B: global `50%` token | Med | Med | Low | Med | Med | Reject as primary. Percentage radii are not explicit enough for directional segment corners. |
| C: component height calc only | High | High | Med | Med | High | Acceptable, but lacks a named semantic token for morph-safe use. |
| D: semantic token + component calc | High | High | Med | Med | High | Chosen. Best ontology and implementation fit. |

---

## §6 — Final Decision Lock

Chosen strategy: **Option D — Hybrid Token Alias + Component Calc**.

Token decision:

```css
--md-sys-shape-corner-pill-stable: 50%;
```

Usage rule:

```txt
Use `corner-full` for static fully-rounded surfaces.

Use component-height / segment-height calc for confirmed morph sources,
optionally documented under the `pill-stable` semantic.
```

Expected Phase 2 component-token shape:

```css
--comp-button-radius: calc(var(--comp-button-height) / 2);

/* Button group: exact token names decided in Phase 1 audit. */
--comp-button-group-pill-radius-m:  calc(var(--comp-button-group-height-m) / 2);
--comp-button-group-pill-radius-xs: calc(var(--comp-button-group-height-xs) / 2);
--comp-button-group-pill-radius-l:  calc(var(--comp-button-group-height-l) / 2);
--comp-button-group-pill-radius-xl: calc(var(--comp-button-group-height-xl) / 2);
```

Why not token-only:

```txt
A global token cannot know whether the host is 40px, 48px, 56px, 80px,
or an arbitrary segmented-control height. For morphing surfaces, the exact
source radius should come from the component's own height contract.
```

---

## §7 — Baseline Edit Scope

Phase 2 allowed files:

```txt
tokens.css
components.css
```

Phase 2 expected changes:

```txt
tokens.css:
  - Add --md-sys-shape-corner-pill-stable.
  - Add comments explaining static full vs morphing source.
  - Do not change --md-sys-shape-corner-full.

components.css:
  - Button: change --comp-button-radius to height/2 calc.
  - Button group: replace connected/selected outer full-pill sources with
    finite per-size morph source radii.
```

Phase 2 not expected:

```txt
- FAB migration.
- Avatar/static pill migration.
- Split button migration.
- Date/Time picker migration.
- theme.json edit.
```

---

## §8 — theme.json / Validator Impact

Phase 0 decision:

```txt
Do not edit theme.json in v3.5.9.
```

Reason:

```txt
The new token is an internal design-system implementation detail for
animation safety. It is not a theme customization surface. WordPress users
should not choose a morphing-source radius separately from component shape.
```

Validator expectation:

```txt
Adding one CSS custom property in tokens.css should not require schema edits.
Validator must remain 1.000 / 1.000 / 1.000 / 1.000 PASS.
```

---

## §9 — Playwright QA Plan

Button trace:

```txt
1. Open lab-button-pattern.html.
2. Capture computed border-radius at rest.
3. Press and sample frames during transition.
4. Assert no intermediate 9999px-style computed radius appears.
5. Assert final active radius remains M3 corner-small (8px).
```

Button group trace:

```txt
1. Use baseline/style-guide or a minimal local specimen containing connected
   `.ax-button-group--connected`.
2. Capture first / middle / last segment radii at rest.
3. Capture selected and selected+pressed states.
4. Assert outer edges remain pill-like with finite radii.
5. Assert inner pressed corners still use the M3 pressed values.
```

FAB trace:

```txt
1. Confirm computed border-radius is finite.
2. Confirm active state does not change border-radius.
3. Record as regression guard only, not migration evidence.
```

---

## §10 — Affected Closed Component Alignment Plan

Phase 5 should add terse alignment notes only where the correction changes a
closed component's baseline contract.

Expected:

```txt
Button SPEC:
  Add note that v3.5.9 replaced the rest pill source with a morphing-safe
  height-based value while preserving corner-full static semantics.
```

Not expected:

```txt
FAB SPEC:
  No alignment note unless Phase 2 unexpectedly touches FAB.

Search bar / Text field / Ripple docs:
  No alignment notes.
```

Button group has not closed as a v3.5.x Full-Spec component yet; v3.5.10
Button group audit should inherit this correction as current baseline.

---

## §11 — Risks And Dispositions

| Risk | Disposition |
|---|---|
| New token duplicates `corner-full` without clear meaning | Avoided by usage rule: static full vs morph source |
| Over-migrating every pill | Explicitly forbidden; static pill consumers stay on `corner-full` |
| FAB speculative migration | Rejected; FAB is finite-radius and no border-radius transition found |
| Button group scope creep | Include only baseline correction, not Button group Full-Spec implementation |
| theme.json scope creep | Rejected; internal animation-safety token only |
| Split button risk ignored forever | Recorded as future cycle evidence, not v3.5.9 scope |

---

## §12 — Phase 1 Entry Conditions

Phase 1 can begin when reviewers accept:

```txt
1. Option D as the strategy.
2. Button + Button group as v3.5.9 migration targets.
3. FAB as verified not affected.
4. theme.json as out of scope.
5. Split button as deferred.
```

Phase 1 deliverable:

```txt
docs/v3.5.9/PILL-RADIUS-CORRECTION-AUDIT.md
```

Phase 1 must define:

```txt
- exact token names
- exact components.css line-level patch plan
- Playwright before/after assertions
- Phase 5 alignment note scope
```

---

## §13 — Non-Goals

This release does not:

```txt
- Change --md-sys-shape-corner-full.
- Convert all pill components.
- Edit theme.json.
- Edit style-guide.html by hand.
- Implement Button group #6 Full-Spec.
- Fix Split button.
- Add JavaScript.
- Reopen FAB without evidence.
- Reopen Search bar/Text field/Ripple docs.
```

---

## §14 — Verdict

```txt
Phase 0 PASS.
Phase 5 close: BACKLOG #31 resolved at v3.5.9.

BACKLOG #31 is confirmed as a real baseline correction.
The actual migration scope is narrower and cleaner than the original
backlog suspicion:

  migrate:
    Button
    Button group baseline connected/selected morph sources

  do not migrate:
    FAB / Extended FAB
    static pill surfaces
    Split button in v3.5.9

Chosen strategy:
  Option D — semantic morphing-safe token plus component-height calc for
  confirmed morph sources.

Final:
  Phase 2 implemented the scoped baseline correction.
  Phase 3 visual QA passed.
  Phase 5 closed the backlog item and release bookkeeping.
```
