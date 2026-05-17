# v3.5.13 Wave 1 Closure Cleanup — Phase 0 Report

> **Status**: Phase 5 closed.  
> **Release**: v3.5.13.  
> **Target**: post-Wave-1 cleanup release.  
> **Scope**: BACKLOG #32 + BACKLOG #33 + Baseline-only Records sweep.  
> **Boundary**: documentation / planning only. No baseline edits in Phase 0.

---

## §0 — Framing

Wave 1 is complete:

```txt
Wave 1: 9 / 9 DONE
Matrix: 13 DONE / 2 PARTIAL / 16 TODO / 3 RECORD
```

v3.5.13 should now close the cleanup debt created or surfaced during Wave 1
before v3.5.14 publish prep:

```txt
Lane A — BACKLOG #32 Button family size variants
Lane B — BACKLOG #33 List M3 full token coverage extension
Lane C — Records sweep (Avatar / Divider / Badge)
```

This release is a cleanup release, not a new component cycle.

---

## §1 — Inputs Read

Local canonical inputs:

```txt
docs/v3.5.13/WAVE-1-CLEANUP-PHASE-0-PLAN.md
BACKLOG.md #32 + #33
docs/v3.5.0/MODULE-STATUS-MATRIX.md rows #1, #2, #6, #10, #25, #32, #33
products/reference-implementations/axismundi-lab/stylesheets/tokens.css §9
products/reference-implementations/axismundi-lab/stylesheets/components.css §1
products/reference-implementations/axismundi-lab/stylesheets/components.css §2
products/reference-implementations/axismundi-lab/stylesheets/components.css §3
products/reference-implementations/axismundi-lab/stylesheets/components.css §4
products/reference-implementations/axismundi-lab/stylesheets/components.css §18
products/reference-implementations/axismundi-lab/stylesheets/components.css §26
products/reference-implementations/axismundi-lab/stylesheets/components.css §28
```

Official M3 spec URLs checked:

```txt
https://m3.material.io/components/buttons/specs
https://m3.material.io/components/icon-buttons/specs
https://m3.material.io/components/button-groups/specs
https://m3.material.io/components/lists/specs
```

Observation:

```txt
The official M3 pages return "This website requires JavaScript" to text fetch.
Use Playwright extraction as the standard path in Phase 1 / Phase 2 whenever
fresh M3 tables are needed.
```

---

## §2 — Single Release vs Split Release Decision

Decision:

```txt
v3.5.13 remains a single cleanup release with three lanes.
```

Why:

```txt
- All three lanes are direct Wave 1 cleanup.
- The lanes are independent enough to keep scoped internally.
- A single release gives a clean v3.5.14 publish-prep entry point:
  Wave 1 closed, Wave 1 cleanup closed, publish prep begins.
```

Rejected alternatives:

```txt
Split each lane into separate releases:
  Rejected for bookkeeping churn unless Lane A or Lane B expands beyond the
  narrow surfaces identified below.

Records-only release:
  Rejected because #32 and #33 are user-surfaced technical debt and should not
  be carried into publish prep without at least Phase 0/1 classification.
```

---

## §3 — Per-Lane Phase Shape

Decision:

```txt
Use one outer v3.5.13 phase sequence, with lane-scoped inner deliverables.
```

Shape:

```txt
Phase 0:
  One report (this file).

Phase 1:
  Lane A: BUTTON-FAMILY-SIZE-AUDIT.md
  Lane B: LIST-TOKEN-COVERAGE-AUDIT.md or extension sections in existing
          List audit docs.
  Lane C: AVATAR-RECORD-AUDIT.md / DIVIDER-RECORD-AUDIT.md /
          BADGE-RECORD-AUDIT.md.

Phase 2:
  Lane A baseline patch if confirmed.
  Lane B baseline patch only for narrow §26 mismatches if confirmed.
  Lane C no baseline patch expected.

Phase 3:
  Unified QA pass, but lane-specific checks.

Phase 5:
  One integrated close, with lane-by-lane sub-verdicts and an overall
  v3.5.13 wrap-up.
```

Reason:

```txt
Lane C can be completed mostly independently. Lane B is List-local. Lane A is
cross-cutting and riskier, so it should not block record authoring but should
control the final release close if it touches baseline tokens.
```

---

## §4 — Lane Execution Order

Decision:

```txt
Execute in low-risk-to-high-risk order:

1. Lane C — Records sweep
2. Lane B — List token coverage
3. Lane A — Button family size variants
```

Why:

```txt
- Records sweep is audit-only and can establish the _records namespace first.
- List token coverage is mostly audit-extension first; baseline edit is optional.
- Button family size variants are the only lane expected to require coordinated
  tokens.css + components.css edits.
```

Parallelism:

```txt
Lane C and Lane B may be drafted in parallel if needed.
Lane A should wait until its token surface is settled in Phase 1 before any
Phase 2 patch is attempted.
```

---

## §5 — Cross-Lane Interaction

Decision:

```txt
No hard dependency exists between Lane A and Lane B.
```

Rationale:

```txt
Lane A affects Button / Icon button / Button group sizes.
Lane B affects List token coverage and possibly List §26.

List row heights (56 / 72 / 88), slot spacing, typography, selected state,
disabled state, and state-layer mapping do not depend on Button height tokens.
```

Composition notes:

```txt
Avatar appears in Lane B as a List composition-owned leading slot and in Lane C
as a Baseline-only Record. The record audit should own Avatar itself; the List
token audit should only cross-reference Avatar as a composed leading slot.

Badge may appear on future app bar/nav/icon-button surfaces, but no Wave 1
cleanup lane depends on changing badge geometry.
```

---

## §6 — Lane A Inventory: Button Family Size Variants

Current token state:

```txt
tokens.css §9:
  --comp-button-height: 40px
  --comp-button-radius: calc(var(--comp-button-height) / 2)
  --comp-icon-size-sm: 20px
  --comp-icon-size-md: 24px
  --comp-icon-size-lg: 28px
  --comp-icon-size-xl: 32px
  --comp-touch-target: 48px
```

Current Button baseline:

```txt
components.css §2:
  .ax-button height = var(--comp-button-height) (40px)
  padding-inline = var(--space-md) (16px)
  label-large typography is constant
  active morph = corner-small
  icon size = --comp-icon-size-sm (20px)
```

Current Icon button baseline:

```txt
components.css §3:
  .ax-icon-button width/height = var(--comp-button-height) (40px)
  min-width/min-height = --comp-touch-target (48px)
  glyph size = --comp-icon-size-md (24px)
  no is-size-* matrix exists in baseline
```

Current Button group baseline:

```txt
components.css §28:
  sizes inherit from Button; default M = 40px.
  is-size-xs / is-size-s adjust group gap.
  connected is-size-xs / l / xl adjust corner radius.
  no size hook changes height / font-size / padding.
```

Phase 0 decision:

```txt
Lane A remains in v3.5.13, but Phase 1 must settle the token surface before
Phase 2. Do not patch heights ad hoc.
```

Recommended Phase 1 lock:

```txt
Use a coordinated 3-component size audit:
  Button
  Icon button
  Button group

Likely token direction:
  public per-size component tokens for height / label typography / horizontal
  padding / icon size, with local mapping where Button group needs connected
  geometry.
```

Fallback:

```txt
If Button and Icon button M3 size matrices are incompatible enough that one
token model cannot serve both, split Lane A into its own future release and let
v3.5.13 continue with Lane B + Lane C.
```

---

## §7 — Lane B Inventory: List M3 Full Token Coverage

Current List baseline:

```txt
components.css §26:
  .ax-list background = surface
  segmented background = surface
  item min-heights = 56 / 72 / 88
  item padding = 10px 16px
  content gap = 12px
  segmented gap = 2px
  leading icon = 24px
  leading image = 56px
  trailing icon = 24px
  selected container = secondary-container
  selected content = on-secondary-container
  disabled = on-surface 10% state/container + 38% text/icon
  focus outline = 2px secondary with -3px offset
```

Token-row classification:

```txt
Already covered by List §26:
  - enabled container / segmented container surface
  - label / supporting / overline colors
  - leading/trailing icon base colors
  - selected container + selected content colors
  - 56/72/88 row heights
  - leading/trailing 16px, top/bottom 10px, between 12px
  - segment gap 2px
  - icon 24px, image 56px

Covered by generic §0 state-layer foundation:
  - hover state-layer opacity 0.08
  - focus state-layer opacity 0.10
  - pressed state-layer opacity 0.10

Partially covered / needs Phase 1 comparison:
  - disabled-selected rows
  - focus indicator thickness: baseline uses 2px, M3 token row says 3dp
  - dragged rows: baseline §0 has [data-dragging] state-layer, but List
    drag/reorder behavior is deferred
  - video slot sizes: explicitly deferred in §26
  - expressive shape table: §26 implements expressive radius states, but Phase 1
    should compare row-by-row before claiming full coverage

Composition-owned:
  - leading avatar color / label color / 40dp size -> Avatar record
  - icon glyph source -> icon-system where Material Symbols are used
```

Phase 0 decision:

```txt
Lane B remains audit-extension first.
```

Allowed Phase 2 patch:

```txt
Only narrow List §26 patches that Phase 1 proves are real List-specific
mismatches. Focus indicator 2px -> 3px is a candidate for measurement review,
not an automatic patch.
```

Forbidden in v3.5.13 Lane B:

```txt
- Adding drag/reorder runtime.
- Broadly rewriting §0 state-layer foundation.
- Folding Avatar into List.
- Claiming full Expressive shape coverage without row-level comparison.
```

---

## §8 — Lane C Records Path + Status Semantics

Decision:

```txt
Record docs live under:
  products/reference-implementations/axismundi-lab/modules/_records/
```

Files:

```txt
AVATAR-RECORD-AUDIT.md
DIVIDER-RECORD-AUDIT.md
BADGE-RECORD-AUDIT.md
```

Why `_records/`:

```txt
- It keeps Baseline-only Records under the lab module audit surface.
- It avoids pretending Avatar / Divider / Badge have full component module
  folders with CSS/JS/pattern artifacts.
- It keeps record-only artifacts discoverable next to other module audits.
```

Record doc shape:

```txt
Single audit file per record:
  §0 status
  §1 baseline inventory
  §2 M3 / WordPress mapping
  §3 dependency / composition notes
  §4 accessibility / reduced-motion applicability
  §5 why no module is needed
  §6 verdict
```

Matrix status decision:

```txt
Keep Status = RECORD.
```

Reason:

```txt
RECORD is not an incomplete state. It is the honest final classification for
baseline-only primitives that do not need lab modules. Phase 5 should update
notes to say record audit exists, but should not convert RECORD -> DONE unless
the v3.5.0 matrix vocabulary is changed globally.
```

Record-specific locks:

```txt
Avatar:
  - standalone; not folded into List.
  - baseline supports xs/sm/default/lg.
  - record should cross-reference BACKLOG #2 avatar size consistency if still
    open, without closing it unless explicitly in scope.

Divider:
  - maps to WordPress core/separator.
  - no runtime, no state-layer, no ripple.

Badge:
  - attaches to host components; record must document host-positioning
    constraints.
  - no standalone interaction; no ripple.
```

---

## §9 — Baseline Edit Risk Table

| Lane | Expected edit type | Potential files | Risk | Phase 0 disposition |
|---|---|---|---|---|
| A #32 | Baseline token + CSS patch | `tokens.css`, `components.css §2/§3/§28` | Medium | Keep in v3.5.13; Phase 1 must settle token model |
| B #33 | Audit extension; optional List patch | `components.css §26` | Low-Medium | Audit first; patch only proven narrow mismatches |
| C Records | Record docs only | `modules/_records/*.md` | Low | No baseline edit expected |

Non-target files:

```txt
style-guide.html
blocks.css
theme.json
lab module JS
publish_styleguide.py
GitHub / Pages files
```

Abort triggers:

```txt
- Lane A requires style-guide.html markup edits to be meaningful.
- Lane A token model becomes incompatible across Button / Icon button /
  Button group.
- Lane B requires §0 state-layer framework rewrite.
- Lane B requires drag/reorder runtime.
- Lane C discovers a real module need for Avatar / Divider / Badge.
```

---

## §10 — Phase 1 Deliverables

Recommended Phase 1 files:

```txt
docs/v3.5.13/WAVE-1-CLEANUP-PHASE-1-PLAN.md

Lane A:
  docs/v3.5.13/BUTTON-FAMILY-SIZE-AUDIT.md

Lane B:
  docs/v3.5.13/LIST-TOKEN-COVERAGE-AUDIT.md
  or targeted additions to:
    products/reference-implementations/axismundi-lab/modules/list/docs/
      LIST-SPEC-AUDIT.md
      LIST-MEASUREMENT-AUDIT.md

Lane C:
  products/reference-implementations/axismundi-lab/modules/_records/
    AVATAR-RECORD-AUDIT.md
    DIVIDER-RECORD-AUDIT.md
    BADGE-RECORD-AUDIT.md
```

Recommended lock:

```txt
Use a central `BUTTON-FAMILY-SIZE-AUDIT.md` and `LIST-TOKEN-COVERAGE-AUDIT.md`
for v3.5.13. Add small alignment notes to closed component docs only at Phase 5.
```

---

## §11 — Phase 2 Expected Patch Surface

Phase 2 should be plan-first again because Lane A and possibly Lane B may edit
baseline.

Expected:

```txt
Lane A:
  tokens.css
  components.css §2 Button
  components.css §3 Icon button
  components.css §28 Button group

Lane B:
  components.css §26 List only, if any patch is approved.

Lane C:
  no CSS patch.
```

Phase 2 constraints:

```txt
- Edit-first / readback / abort-on-mismatch.
- No automatic fresh Write fallback.
- Validator before and after any baseline patch.
- Playwright measurement before and after Lane A size patch.
```

---

## §12 — Playwright QA Plan

Lane A:

```txt
Measure Button:
  XS / S / M / L / XL height
  font size
  padding
  icon size
  active morph radius stability

Measure Icon button:
  XS / S / M / L / XL container
  glyph size
  touch target
  selected/unselected geometry

Measure Button group:
  XS / S / M / L / XL segment height
  connected outer/inner radius
  selected + pressed morph
  Pattern A and Pattern B keyboard smoke
```

Lane B:

```txt
Measure List:
  disabled and selected-disabled colors
  hover/focus/pressed state-layer opacity
  focus indicator thickness / offset
  row heights 56 / 72 / 88
  spacing 16 / 10 / 12 / 2
  typography rows
```

Lane C:

```txt
Smoke check only if record specimens are added:
  Avatar sizes and image clipping
  Divider variants
  Badge dot/numeric positioning
```

---

## §13 — Non-Goals

```txt
- v3.5.14 publish prep.
- README / README.ko.md.
- GitHub Actions.
- GitHub repository creation.
- GitHub Pages.
- Directory rename.
- `docs/releases/` reorganization, unless Phase 1 explicitly re-scopes it.
- Reopening Wave 1 verdicts.
- Changing `dev/` workspace semantics.
- Moving the repo anywhere except future `C:\Users\thaum\dev\axismundi\`
  when v3.5.14 path prep begins.
```

---

## §14 — Verdict

Phase 0 verdict:

```txt
PASS.
```

Locks:

```txt
1. v3.5.13 remains a single cleanup release.
2. Use three lanes: C Records, B List tokens, A Button family size.
3. Execution order: C -> B -> A unless Phase 1 finds a reason to reorder.
4. Lane C uses `lab/modules/_records/`.
5. RECORD status remains RECORD after record docs exist.
6. Lane B is audit-extension first; baseline §26 patch only if narrowly proven.
7. Lane A remains in v3.5.13 but must settle token surface in Phase 1.
8. Phase 2 must be plan-first because baseline edits are likely.
9. Phase 5 close is integrated with lane-by-lane sub-verdicts.
10. v3.5.14 publish prep remains out of scope.
```

Ready for:

```txt
Release closed in Phase 5.
```
