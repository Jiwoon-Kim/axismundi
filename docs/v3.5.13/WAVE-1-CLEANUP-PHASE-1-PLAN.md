# v3.5.13 Wave 1 Closure Cleanup — Phase 1 Plan

> **Status**: Phase 5 closed.  
> **Release**: v3.5.13.  
> **Input**: `WAVE-1-CLEANUP-PHASE-0-REPORT.md` approved.  
> **Rule**: plan-only. Do not create `_records/`, do not edit audit docs, and do not edit baseline CSS/tokens in this phase.

---

## §0 — Phase 1 Goal

Phase 1 converts the approved 3-lane cleanup framing into concrete audit
deliverables.

Execution order remains:

```txt
Lane C — Records sweep
Lane B — List token coverage
Lane A — Button family size variants
```

Phase 1 is documentation and audit authoring only. Baseline edits are deferred
to Phase 2 plan + Phase 2 execution.

---

## §1 — Lane C Deliverables: Records Sweep

Create the `_records/` namespace during Phase 1 execution:

```txt
products/reference-implementations/axismundi-lab/modules/_records/
```

Create three record-only audit docs:

```txt
AVATAR-RECORD-AUDIT.md
DIVIDER-RECORD-AUDIT.md
BADGE-RECORD-AUDIT.md
```

Expected length:

```txt
Avatar: 180-260 lines
Divider: 120-180 lines
Badge: 180-260 lines
```

Record doc shape:

```txt
§0 Status
§1 Baseline inventory
§2 M3 / WordPress mapping
§3 Dependency / composition notes
§4 Accessibility / reduced-motion applicability
§5 Why no lab module is needed
§6 Record verdict
§7 Cross-references
§8 Non-goals
```

Lane C locks:

```txt
- RECORD status stays RECORD.
- No lab CSS / JS / pattern HTML artifacts.
- No baseline edits.
- No style-guide.html edits.
- Each doc should explicitly say "Baseline-only Record".
```

Record-specific content:

```txt
Avatar:
  - inventory `.ax-avatar`, `.is-size-xs`, `.is-size-sm`, default, `.is-size-lg`
  - initials / image content modes
  - List leading-slot composition dependency
  - BACKLOG #2 cross-reference only; do not close

Divider:
  - inventory `.ax-divider`, `.is-style-inset`, `.is-style-middle-inset`
  - WordPress `core/separator` mapping
  - no state-layer / ripple / runtime

Badge:
  - inventory `.ax-badge`, `:empty`, `.is-large`, `.is-inline`, `.has-badge`
  - host-positioning contract
  - numeric / dot variants
  - attachment to Icon button / App bar / Nav / Tabs as future consumers
```

---

## §2 — Lane B Deliverables: List Token Coverage

Use in-place extension of the existing List audit docs. Do not create a new
List token doc unless execution discovers the extension is too large to keep
readable.

Edit during Phase 1 execution:

```txt
products/reference-implementations/axismundi-lab/modules/list/docs/
  LIST-SPEC-AUDIT.md
  LIST-MEASUREMENT-AUDIT.md
```

Expected additions:

```txt
LIST-SPEC-AUDIT.md:         +80-140 lines
LIST-MEASUREMENT-AUDIT.md:  +100-180 lines
```

SPEC additions:

```txt
- Full M3 List token coverage extension section.
- Token row classification:
    already covered by §26
    covered by generic §0 state-layer foundation
    composition-owned by Avatar / icon-system
    behavior-deferred
    candidate baseline mismatch
- Explicit note that v3.5.11 release closure remains closed.
- Explicit note that #33 is cleanup/audit-extension work, not a release reopen.
```

MEASUREMENT additions:

```txt
- Token comparison table for disabled / selected-disabled / hover / focus /
  pressed / dragged / spacing / shape / size / typography.
- Focus indicator candidate mismatch:
    baseline 2px secondary / -3px offset
    M3 token row says 3dp thickness / -3dp inner offset
    Phase 2 decision required.
- Dragged rows:
    §0 has generic [data-dragging] state-layer opacity path
    List drag/reorder runtime remains deferred.
- Video slot:
    explicit deferred state; no baseline patch in v3.5.13 unless user
    reprioritizes.
```

Lane B locks:

```txt
- Audit-extension first.
- No CSS edit in Phase 1.
- Phase 2 may patch only `components.css §26`.
- No §0 state-layer rewrite.
- No drag/reorder runtime.
- Avatar stays composition-owned and record-owned; it is not folded into List.
```

---

## §3 — Lane A Deliverables: Button Family Size Variants

Use one central v3.5.13 audit for the cross-cutting size contract, plus
in-place measurement/spec notes in the three closed component docs.

Create during Phase 1 execution:

```txt
docs/v3.5.13/BUTTON-FAMILY-SIZE-AUDIT.md
```

Edit during Phase 1 execution:

```txt
products/reference-implementations/axismundi-lab/modules/button/docs/
  BUTTON-SPEC-AUDIT.md
  BUTTON-MEASUREMENT-AUDIT.md

products/reference-implementations/axismundi-lab/modules/icon-button/docs/
  ICON-BUTTON-SPEC-AUDIT.md
  ICON-BUTTON-MEASUREMENT-AUDIT.md

products/reference-implementations/axismundi-lab/modules/button-group/docs/
  BUTTON-GROUP-SPEC-AUDIT.md
  BUTTON-GROUP-MEASUREMENT-AUDIT.md
```

Expected additions:

```txt
BUTTON-FAMILY-SIZE-AUDIT.md:       300-450 lines
Each SPEC alignment note:           20-40 lines
Each MEASUREMENT size section:      40-80 lines
```

Central audit required sections:

```txt
§0 Status / scope
§1 Inputs read
§2 Current baseline inventory
§3 M3 size matrices to extract / cite
§4 Token surface options
§5 Recommended token contract
§6 Per-component mapping: Button
§7 Per-component mapping: Icon button
§8 Per-component mapping: Button group
§9 WCAG target-size implications
§10 Phase 2 patch surface
§11 Playwright QA matrix
§12 Risks / fallback triggers
§13 Verdict
§14 Non-goals
```

Token contract lock to settle in Phase 1:

```txt
Do not defer token-surface choice to Phase 2.

Phase 1 must choose one:
  Option A — public per-size component tokens for Button family
  Option B — local variables only
  Option C — hybrid public size tokens + local connected geometry mapping

Recommended: Option C.
```

Recommended Option C shape:

```txt
Public tokens in tokens.css (Phase 2 candidate):
  --comp-button-height-xs
  --comp-button-height-s
  --comp-button-height-m
  --comp-button-height-l
  --comp-button-height-xl

Possibly public or local after Phase 1 decision:
  --comp-button-padding-inline-*
  --comp-button-icon-size-*
  --comp-button-label-size-*

Local mappings in components.css:
  .ax-button.is-size-*
  .ax-icon-button.is-size-*
  .ax-button-group.is-size-* .ax-button
```

Lane A locks:

```txt
- No baseline edit in Phase 1.
- No style-guide.html edit.
- No Wave 1 closure verdict reopen.
- Alignment notes are additive and mechanical.
- Phase 2 must be plan-first before tokens.css/components.css edits.
```

---

## §4 — Lane Dependencies / Parallelism

```txt
Lane C can run first and independently.
Lane B can run after or parallel with C.
Lane A can begin audit authoring after C/B, but must be the last lane to
approve for Phase 2 because it has the highest baseline edit risk.
```

Cross-lane interactions:

```txt
Avatar appears in Lane B as a List composition row and Lane C as its own
record. Lane C owns Avatar. Lane B should cross-reference it.

Button family size does not affect List row heights or List token classification.

Badge record may mention future Button/Icon/App bar hosts but must not alter
Button family size scope.
```

---

## §5 — Phase 1 Non-Goals

```txt
- No tokens.css edits.
- No components.css edits.
- No style-guide.html edits.
- No blocks.css edits.
- No theme.json edits.
- No lab CSS / JS / pattern HTML generation.
- No Playwright required except optional M3 table extraction.
- No CHANGELOG / ROADMAP / MATRIX / CURRENT-STATE / NEXT-SESSION edits.
- No repo rename or publish prep.
```

---

## §6 — Phase 1 Verification

After Phase 1 execution:

```txt
1. Validator remains 1.000 / 1.000 / 1.000 / 1.000.
2. Baseline mtimes unchanged:
   - tokens.css
   - components.css
   - style-guide.html
   - blocks.css
   - theme.json / ontology-theme-pilot theme.json
3. `_records/` exists with exactly 3 record docs.
4. List SPEC + MEASUREMENT contain #33 extension sections.
5. BUTTON-FAMILY-SIZE-AUDIT.md exists and locks token contract.
6. Button / Icon button / Button group SPEC + MEASUREMENT docs contain
   additive v3.5.13 alignment notes.
```

---

## §7 — Phase 2 Entry Criteria

Phase 2 plan may begin only after:

```txt
- Lane C record docs approved.
- Lane B token coverage extension approved and candidate §26 patches identified.
- Lane A size audit approved and token-surface contract locked.
```

Phase 2 plan must decide:

```txt
- Whether Lane B patches `components.css §26`.
- Exact Lane A tokens.css diff.
- Exact Lane A components.css §2/§3/§28 diff.
- Whether any lane is documentation-only at close.
```

---

## §8 — Self-Check

```txt
self-check:
  Lane C deliverables: 3 record docs
  Lane B deliverables: LIST-SPEC + LIST-MEASUREMENT in-place extension
  Lane A deliverables: central size audit + 6 component doc alignment edits
  Phase 1 baseline edits: none
  Phase 2 plan-first: required
  `_records/` creation: Phase 1 execution, not plan
```
