# v3.6.4 - WP Block Bridge Residual Cleanup - Phase 0 Plan

Date: 2026-05-20

## Verdict

v3.6.4 should begin as a plan-first mechanical cleanup cycle for the narrowed
BACKLOG #41 residual scope left by v3.6.3.

This is not a new semantic-decision cycle. The semantic routes were named in
v3.6.3 and are now enforced through Lock 3 and Lock 4.

## User Request Log

```txt
계속 이어서 하자. 매번 소규모 업데이트때마다 새 세션 열고 맥락 파악하는것도
토큰소모가 많으니
```

Reviewer standby framing:

```txt
Next cycle:  BACKLOG #41 residual cleanup
Input docs:  NEXT-SESSION.md §0 (post-v3.6.3 reading order)
Locks live:  Lock 1/2 (token), Lock 3/4 (semantic route + mismatch routing)
```

Phase 0 review checklist expected by Opus:

```txt
1. Lock 3/4 compliance 명시 — Phase 0 plan이 "Lock 3/4를 enforce하는
   mechanical cleanup cycle"임을 verbatim으로 frame 했는지
2. Scope가 v3.6.3 Phase 5 close 문서의 deferred 목록에 정확히 매핑되는지
   (button mechanical cleanup + quote/pullquote selector narrowing +
   distinct pullquote bridge — 그 외 확장 금지)
3. Non-goals에 "v3.6.3 semantic decision 재논의 안 함" +
   "ripple/editor parity는 별도 cycle" 명시
4. Risk profile: pullquote가 quote 스타일을 silent하게 흡수하지 않도록
   selector narrowing 검증 어떻게 할지 (computed probe? specimen wall extension?)
5. Validator strategy: Axis E/F/G + validate:specimen-wall 유지,
   새 axis 불필요 예상 (mechanical 작업이라)
```

## Cycle Frame

```txt
v3.6.4 is a Lock 3/4 enforcing mechanical cleanup cycle.
```

Meaning:

```txt
Lock 3:
  core/button semantic route was named in v3.6.3. v3.6.4 may clean up visual
  link affordances on .wp-block-button__link without reopening the semantic
  decision.

Lock 4:
  quote/pullquote mismatch was routed in v3.6.3. v3.6.4 may narrow selectors
  and add distinct .wp-block-pullquote bridge CSS, but must prove pullquote is
  not silently swallowed by generic quote styling.
```

## Source Inputs

```txt
NEXT-SESSION.md §0 post-v3.6.3 reading order
BACKLOG.md #41
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
products/reference-implementations/axismundi-lab/stylesheets/blocks.css §3
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
```

## Scope

This cycle maps exactly to the v3.6.3 Phase 5 deferred BACKLOG #41 residual
work that is already semantically routed.

### Lane 1 - Button Mechanical Cleanup

Source:

```txt
v3.6.3 Phase 5 deferred:
  button mechanical cleanup after route:
    text-decoration, user-select, and state styling checks for
    .wp-block-button__link
```

Expected work:

```txt
- Inspect current .wp-block-button__link computed text-decoration and
  user-select behavior on the specimen wall.
- Patch only mechanical link-affordance leakage if reproduced.
- Preserve the v3.6.3 semantic route:
  anchor with href = navigation receiving M3 button visual bridge.
- Do not change markup, block registration, save format, or href behavior.
```

Expected evidence:

```txt
- Before/after computed values for at least one .wp-block-button__link variant.
- Confirm href remains present on specimen anchors.
- Confirm focus-visible outline and hover/pressed state-layer behavior still
  resolve through M3 tokens.
```

### Lane 2 - Quote/Pullquote Distinct Surface Cleanup

Source:

```txt
v3.6.3 Phase 5 deferred:
  quote/pullquote implementation after route:
    selector narrowing and distinct .wp-block-pullquote bridge styling
```

Expected work:

```txt
- Narrow quote bridge selectors so .wp-block-pullquote's inner blockquote is
  not styled as core/quote by accident.
- Add distinct .wp-block-pullquote bridge CSS following the lab blocks.css §3
  route: centered editorial pullquote, top/bottom dividers, larger emphasis
  type, and citation styling.
- Preserve prose/default blockquote behavior where it is intentionally outside
  the block bridge.
```

Expected evidence:

```txt
- Computed probe showing core/quote keeps primary-bordered quote treatment.
- Computed probe showing core/pullquote has top/bottom dividers and does not
  inherit quote inline-start padding/bar styling.
- Computed probe showing pullquote paragraph/citation use distinct pullquote
  typography and spacing.
```

## Out of Scope

```txt
v3.6.3 semantic decision 재논의 안 함.
ripple/editor parity는 별도 cycle.
```

Additional non-goals:

```txt
- Do not implement custom blocks.
- Do not register custom blocks.
- Do not edit theme.json.
- Do not edit functions.php unless a later reviewed plan explicitly expands
  scope.
- Do not implement plugin behavior.
- Do not change core/button semantics, href behavior, or block save markup.
- Do not collapse quote and pullquote into one shared quote style.
- Do not route separator work, Material Symbols font work, or BACKLOG #44
  fixture/editor compatibility into this cycle.
- Do not expand the committed specimen wall fixture for pullquote in this
  cycle; deeper pullquote fixture coverage remains BACKLOG #44.
```

## Files Expected To Change After GO

Implementation:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

Reports:

```txt
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
```

Phase 5 close artifacts, produced when both implementation phases pass review:

```txt
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
BACKLOG.md
```

## Files Not Expected To Change

```txt
theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
tools/validators/*
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/stylesheets/components.css
```

## Risk Profile

### R1 - Pullquote silently absorbs quote styling

Risk:

```txt
The current Pilot bridge uses :where(.wp-block-quote, blockquote). Because
core/pullquote wraps a blockquote inside figure.wp-block-pullquote, broad
blockquote rules can make pullquote a side effect of quote styling.
```

Mitigation:

```txt
- Narrow block bridge quote selectors around .wp-block-quote.
- Add explicit .wp-block-pullquote rules.
- Use a temporary DOM/computed probe for pullquote markup instead of expanding
  the committed specimen fixture in this cycle.
```

### R2 - Button cleanup accidentally changes semantics

Risk:

```txt
Visual cleanup could accidentally treat core/button anchors as action buttons
or hide the href-dependent route named in v3.6.3.
```

Mitigation:

```txt
- Only patch text-decoration/user-select/state visual leakage.
- Verify specimen anchors still have href.
- Do not add role changes, JS behavior, or markup filters.
```

### R3 - Token graph drift

Risk:

```txt
New button or pullquote CSS could introduce literal color values or bypass
md-sys tokens.
```

Mitigation:

```txt
- Use existing md-sys, md-ref, motion, shape, and space tokens only.
- Keep Axis E/F/G at 1.000 PASS.
```

### R4 - Source/asset mirror drift

Risk:

```txt
Pilot bridge source and asset mirror can diverge.
```

Mitigation:

```txt
- Keep source and copied asset bridge byte-identical after edits.
- Diff source vs asset mirror before commit.
```

## Validation Strategy

No new validator axis is expected for v3.6.4. This is mechanical cleanup over
already-routed semantics.

Required validators:

```powershell
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
git diff --check
```

Required validator invariants:

```txt
Axis E md-sys color maps to md-ref:      PASS / 1.000
Axis F bridge downstream-only:           PASS / 1.000
Axis G theme.json custom downstream-only: PASS / 1.000
validate:specimen-wall:                  PASS
validate:computed:                       PASS
```

Additional computed probes:

```txt
Button:
  .wp-block-button__link text-decoration
  .wp-block-button__link user-select
  .wp-block-button__link href presence
  .wp-block-button__link focus-visible outline

Quote:
  .wp-block-quote border-inline-start color/width
  .wp-block-quote padding-inline-start
  .wp-block-quote cite color/font sizing

Pullquote:
  .wp-block-pullquote border-block-start/end
  .wp-block-pullquote text-align
  .wp-block-pullquote blockquote padding-inline-start / border-inline-start
  .wp-block-pullquote p typography
  .wp-block-pullquote cite spacing/color
```

## Phase Partition

```txt
Phase 0:
  Plan and review.

Phase 1:
  Button mechanical cleanup.

Phase 2:
  Quote/pullquote distinct-surface cleanup.

Phase 3:
  Light visual QA for button state interaction and quote/pullquote distinct
  surfaces.

Phase 5:
  Close if both implementation phases pass review.
```

There is no semantic-decision phase in this cycle. v3.6.3 already supplied the
semantic decisions; v3.6.4 enforces them. Phase 3 is reserved for visual QA,
not semantic routing.

Phase 2 entry checkpoint:

```txt
Before writing the Pilot quote/pullquote bridge translation, read
products/reference-implementations/axismundi-lab/stylesheets/blocks.css §3
to inventory the lab quote/pullquote treatment.
```

## Phase 0 Exit Criteria

```txt
- Phase 0 plan exists under docs/v3.6.4/.
- Lock 3/4 enforcement frame is explicit.
- Scope maps exactly to v3.6.3 Phase 5 deferred #41 residuals selected for
  this cycle.
- Non-goals explicitly state:
  v3.6.3 semantic decision 재논의 안 함.
  ripple/editor parity는 별도 cycle.
- Pullquote selector-narrowing verification strategy is named.
- Axis E/F/G and validate:specimen-wall remain required.
- Implementation files are not edited before Phase 0 GO.
```
