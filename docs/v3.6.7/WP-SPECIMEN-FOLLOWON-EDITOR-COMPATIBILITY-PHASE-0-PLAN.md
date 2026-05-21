# v3.6.7 - WP Specimen Follow-On Coverage / Editor Compatibility - Phase 0 Plan

Date: 2026-05-21

Phase: 0 - Plan

## User Request Log

User said:

```txt
go
```

Carry-forward from the v3.6.6 close review:

```txt
v3.6.6 cycle closed.
Next cycle remains plan-first.
Priority order is:
  #44 -> Wave 2 -> #21 -> narrowed #41
```

This Phase 0 selects the first agreed next candidate:

```txt
BACKLOG #44 - Specimen wall follow-on coverage + editor compatibility
```

Implementation files must not be edited before Phase 0 review GO.

## Current Baseline

Local status at Phase 0 entry:

```txt
## main...origin/main [ahead 5]
```

No unstaged or untracked work was present before writing this plan.

Repository state:

```txt
v3.6.0 through v3.6.6 are closed.
WordPress core in wp-env is 7.0.
v3.6.x cadence uses Phase 0 / 1 / 2 / 3 / 5.
Phase 4 is intentionally unused.
```

## Selected Candidate

v3.6.7 should be a BACKLOG #44 cycle.

Reason:

```txt
#44 is now the ripest candidate because v3.6.6 routed a concrete editor
compatibility signal into it:

  editor open console errors:           56
  block validation console error count: 56

The original #44 scope already owns editor-valid fixture / editor compatibility,
mark/highlight coverage, long-line coverage coordination, deeper pullquote
coverage, and the Material Symbols font constraint cross-reference.
```

This cycle starts as diagnostic-first because the failure mode behind the 56
editor validation console errors is not yet mapped by block/fixture segment.

## Phase 0 Actually Read

Read according to `NEXT-SESSION.md §0` order:

```txt
AGENTS.md
CURRENT-STATE.md
PROJECT-CONTEXT.md
CHANGELOG.md latest entry
ROADMAP.md current tail
BACKLOG.md #41 / #44 / #21 / #14
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-2-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-5-CLOSE.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-2-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-0-PLAN.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-5-CLOSE.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-SEMANTIC-DECISIONS.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-1-REPORT.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-0-PLAN.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-5-CLOSE.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md §1-2
docs/v3.6.0/PILOT-LESSONS-AND-TOKEN-ARCHITECTURE.md
```

Additional Phase 0 reads:

```txt
CLAUDE.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-0-PLAN.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-1-REPORT.md
docs/v3.5.0/PROMOTION-CRITERIA.md
package.json
tools/generators/build_pilot_specimen_wall.py
tools/validators/validate_pilot_specimen_wall.js
tools/validators/validate_pilot_computed_styles.js
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
products/reference-implementations/axismundi-pilot/functions.php
```

## Governing Prior Decisions

The reverse WordPress block bridge order remains binding:

```txt
Markdown / HTML defaults -> WordPress core block -> core reset -> bridge ->
M3 mapping -> interaction bridge -> computed-style audit
```

The current specimen wall contract from v3.6.2 remains binding:

```txt
The wall is WordPress-rendered from a committed fixture.
The importer is deterministic.
The render gate checks HTTP 200, console/page errors, horizontal overflow,
and stable anchors.
Coverage expansion must not reopen #43's Tier 1 close.
```

The v3.6.3 semantic routes remain binding:

```txt
core/button anchor with href = valid navigation receiving M3 button visuals.
core/quote and core/pullquote = distinct theme-owned surfaces.
Plugin/custom-block need is routed, not implemented in theme cycles.
```

The v3.6.5 editor token lesson remains binding:

```txt
Editor compatibility must distinguish selector/style landing from token
resolution. The editor iframe may transform or inline theme styles differently
from the front end.
```

The v3.6.6 ripple/editor state lesson remains binding:

```txt
Editor behavior must be classified by what the real editor canvas exposes.
Do not fake theme targets for states or surfaces WordPress does not expose.
```

## Active Locks

This cycle preserves all four close-time locks:

```txt
Lock 1 - wp-custom downstream-only

Every settings.custom.axismundi.* entry MUST be defined as:
  var(--comp-*) or var(--md-sys-*) or var(--md-ref-*)

Literal hex / rgb / px / number values are forbidden in this namespace.
Rationale: wp-custom is a downstream projection of M3, never a source.
Validator: tools/validators/validate_theme_pilot.py Axis G.
```

```txt
Lock 2 - md-sys color maps to md-ref

Every --md-sys-color-* entry MUST be defined as:
  var(--md-ref-palette-*)

Literal hex / rgb / hsl values are forbidden in the md-sys color layer.
Rationale: md-sys is the runtime semantic layer; md-ref is the primitive source.
Dark mode swaps sys -> ref mappings only.
Validator: tools/validators/validate_theme_pilot.py Axis E.
```

```txt
Lock 3 - core/button semantic route before visual cleanup

Before accepting visual cleanup for core/button link affordances, name the
semantic route. A core/button anchor with href is navigation and may receive an
M3 button visual bridge. A real action, form behavior, AJAX flow, federation
action, or durable custom schema must be routed to plugin/custom-block
territory, not implemented in the theme bridge.
```

```txt
Lock 4 - semantic mismatch handling rule

When a WordPress core block visually maps to M3 but carries divergent markup,
interaction, or accessibility semantics, route the mismatch as either
theme-owned semantic-decision or plugin/custom-block territory before
accepting a visual fix. Do not silently ignore the mismatch and do not collapse
distinct core block structures into one generic CSS patch.
```

Diagnostic-first remains a useful methodology, not Lock 5.

## Scope

### In Scope

1. Identify which specimen fixture block(s) cause the editor block-validation
   console errors.
2. Decide whether the committed specimen fixture should become editor-valid or
   whether the front-end-only fixture method should be documented as
   intentional.
3. If editor-valid fixture repair is selected, patch only the committed
   specimen fixture and any validator/generator checks needed to prove it.
4. Add or route mark/highlight coverage after the authoring path is known.
5. Add or route long-line code coverage after comparing against the existing
   #41 code bridge input and v3.6.3 code-overflow close.
6. Add or route deeper pullquote coverage after confirming whether #41 still
   needs fixture-backed semantic evidence or the current temporary probes are
   enough.
7. Track Material Symbols font constraint as a #14 cross-reference only unless
   Phase 1 proves a real font dependency in the specimen/editor surface.
8. Keep all observations separated by front end vs editor canvas.

### Out Of Scope

```txt
Do not reopen v3.6.2 BACKLOG #43 Tier 1 close.
Do not implement #41 bridge/reset fixes.
Do not decide or reopen core/button semantic boundary.
Do not change Pilot ripple graduation or shared runtime packaging.
Do not implement BACKLOG #21 Interpreter Plugin.
Do not implement custom blocks.
Do not edit theme.json.
Do not edit functions.php unless Phase 1 proves a fixture/editor-valid route
requires a reviewed registration/enqueue decision.
Do not edit lab components.css §0 or styleguide authoring surfaces.
Do not route separator variant visibility through Material Symbols unless new
evidence proves a real font dependency.
```

## Route Buckets

Phase 1 must choose one route before Phase 2 patches:

```txt
A. Editor-valid fixture repair:
   The 56 console errors are caused by invalid committed block fixture markup.
   Patch the fixture to match current WordPress 7.0 save output while
   preserving the front-end specimen anchors and coverage.

B. Front-end-only fixture method:
   The current fixture is intentionally a front-end evidence surface and
   editor validity would require distorting the specimen or losing coverage.
   Document the method, keep the warning routed, and avoid a fake repair.

C. Split fixture strategy:
   Preserve the front-end wall and add a separate editor-valid fixture or
   reduced editor smoke fixture for editor compatibility checks.

D. Validator/probe-only route:
   No fixture content patch yet. Add diagnostics that map validation errors by
   block/selector first, then defer actual repair to a later #44 sub-cycle.

E. Coverage-only route:
   Editor validation errors prove unrelated or already acceptable; use this
   cycle for mark/highlight / long-line / pullquote fixture coverage only.

F. Other, with evidence.
```

Expected Phase 1 outcome:

```txt
Route A/B/C/D/E/F selected with evidence.
No implementation files edited in Phase 1.
```

## Phase Partition

### Phase 0 - Plan

Artifact:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-0-PLAN.md
```

Exit criteria:

```txt
Selected candidate is BACKLOG #44.
Plan-first is preserved.
Phase 1 is diagnostic before implementation.
Editor-valid fixture decision is framed as routes, not assumed.
Locks 1-4 are explicit.
Phase 4 remains unused.
Implementation files are untouched.
```

### Phase 1 - Editor Compatibility / Coverage Inventory

No implementation files may be edited in Phase 1.

Read and probe:

```txt
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
tools/generators/build_pilot_specimen_wall.py
tools/validators/validate_pilot_specimen_wall.js
tools/validators/validate_pilot_computed_styles.js
products/reference-implementations/axismundi-pilot/functions.php
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-1-REPORT.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-2-CLASSIFICATION.md
docs/v3.6.2/WP-CORE-BLOCK-SPECIMEN-WALL-PHASE-3-VISUAL-QA.md
docs/v3.6.3/WP-BLOCK-BRIDGE-EXPANSION-PHASE-2-REPORT.md
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-3-VISUAL-QA.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-3-VISUAL-QA.md
```

Inventory tasks:

```txt
Editor error mapping:
  - Open the specimen wall in the editor.
  - Capture console errors and block-validation errors.
  - Map each error to block name, specimen anchor/variant if possible, and
    expected vs actual content if WordPress exposes it.
  - Record UI invalid-content text count separately from console count.

Fixture validity:
  - Compare committed fixture block comments/attributes with rendered editor
    expectations.
  - Identify whether errors come from handcrafted HTML inside valid block
    comments, from missing serialized attributes, from custom data attributes,
    or from style variation registration drift.

Coverage follow-on:
  - mark/highlight: identify an editor-valid authoring path or route as
    pending.
  - long-line code: decide whether the existing v3.6.3 temporary DOM evidence
    is enough, or whether the specimen should gain a committed long-line case.
  - pullquote: decide whether committed fixture coverage is needed after
    v3.6.4 temporary DOM probes and v3.6.5 editor token parity.
  - Material Symbols: keep as #14 cross-reference unless the specimen/editor
    diagnostics prove a real font/layout failure.
```

Route selection evidence:

```txt
For each route bucket A-F:
  selected or rejected
  reason
  implementation implications
```

Expected artifact:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
```

Exit criteria:

```txt
Editor validation error count recorded.
Block/fixture source of errors mapped as far as available tooling allows.
Route A/B/C/D/E/F selected.
Coverage items classified: mark/highlight, long-line code, pullquote,
Material Symbols.
Implementation files edited: no.
```

### Phase 2 - Selected Fixture / Validator / Routing Patch

Patch only the Phase 1 selected route.

Likely files if Route A is selected:

```txt
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
tools/validators/validate_pilot_specimen_wall.js
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
```

Possible files if Route C or D is selected:

```txt
products/reference-implementations/axismundi-pilot/fixtures/<new-editor-fixture>.html
tools/generators/build_pilot_specimen_wall.py
tools/validators/<new-or-extended-validator>.js
package.json
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
```

Possible no-code outcome if Route B is selected:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
BACKLOG.md update deferred to Phase 5
```

Patch constraints:

```txt
Stable data-ax-specimen-id and data-ax-specimen-variant anchors must remain.
Front-end specimen URL must remain:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall
No #41 bridge/reset implementation.
No token literal regression.
No custom blocks.
No plugin behavior.
No theme.json change unless Phase 1 review explicitly expands scope.
```

Exit criteria:

```txt
Selected route is implemented or documented.
Front-end specimen wall render gate still PASS.
Editor compatibility evidence improves or is explicitly routed.
Coverage additions, if any, are editor-valid or intentionally front-end-only
with a documented reason.
```

### Phase 3 - Visual / Editor QA

Surfaces:

```txt
Front end:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Editor:
  http://localhost:8888/wp-admin/post.php?post=29&action=edit
  iframe[name="editor-canvas"]
```

Required evidence:

```txt
Front end:
  HTTP 200
  no console/page errors
  no horizontal overflow at 390px
  Tier 1 anchors preserved
  any added coverage anchors visible and targetable

Editor:
  console/page error count
  block validation console error count
  UI invalid-content text count
  affected block(s) identified or explicitly not exposed by tooling
  editor canvas token smoke remains intact
```

If Route A or C changes fixture content, Phase 3 must compare:

```txt
before count from v3.6.6:
  editor open console errors:           56
  block validation console error count: 56

after count:
  exact observed values
```

Expected artifact:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
```

Exit criteria:

```txt
Front-end specimen wall remains usable.
Editor compatibility result is recorded honestly.
No #41 bridge/reset fix is claimed.
Coverage and routing decisions are evidence-backed.
```

### Phase 5 - Close

Expected close artifacts:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

`AGENTS.md` / `CLAUDE.md` should update only if a new operating rule is
promoted. No new lock is expected.

Exit criteria:

```txt
BACKLOG #44 records what closed and what remains.
If editor validity is repaired, before/after counts are recorded.
If editor validity remains intentionally open, the reason and next route are
recorded.
mark/highlight, long-line, pullquote, and Material Symbols items are each
closed, narrowed, or forwarded.
CHANGELOG includes v3.6.7.
NEXT-SESSION.md reading order includes v3.6.7 docs.
```

## Applicable G1-G26 Gates

Universal gates:

```txt
G1. validate_theme_pilot.py / npm test PASS.
G2. Baseline surfaces untouched unless explicitly authorized.
G4. Phase reports and any new fixture/validator artifacts exist.
G5. CHANGELOG entry at Phase 5.
G6. Visual QA covers front end and editor where applicable.
G10. Findings recorded in docs, not chat memory.
```

WordPress / binding gates by analogy:

```txt
G15. WordPress mapping claims require actual WP-rendered evidence.
G20. Existing regression gates must remain PASS.
G21. Theme-can / plugin-should boundary remains explicit if a mismatch routes
     to plugin/custom-block territory.
```

Infrastructure/provider gates where relevant:

```txt
G22-G26 remain preservation checks only.
No infrastructure provider contract is expected to change.
```

v3.6.x hard gates:

```txt
Axis E - md-sys color maps to md-ref: PASS
Axis F - bridge downstream-only: PASS
Axis G - wp-custom downstream-only: PASS
validate:specimen-wall: PASS
validate:computed: PASS
```

## Validation Strategy

Standard validation:

```powershell
wp-env run cli wp core version
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
npm run validate:computed
git diff --check
```

Additional Phase 1 / Phase 3 probes:

```txt
Editor console capture:
  console/page error count
  block validation error count
  UI invalid-content text count
  per-block source mapping where possible

Fixture comparison:
  committed fixture block comments and attributes
  rendered front-end anchors
  editor save/validation expectations

Coverage probes:
  mark/highlight target if added
  long-line code overflow if added
  pullquote distinct surface if added
  Material Symbols loading/layout only if evidence triggers it
```

## Risks

### R1 - Fixture repair changes the evidence surface

Risk:

```txt
Making the fixture editor-valid could accidentally remove the WordPress output
shape that made the front-end specimen useful.
```

Mitigation:

- Preserve stable specimen anchors.
- Compare front-end render gate before/after.
- Do not remove coverage to silence the editor.

### R2 - False editor-valid claim

Risk:

```txt
Console counts can change with editor load timing, extensions, or WordPress
noise, creating a false sense of fixture repair.
```

Mitigation:

- Record console count, block-validation count, and UI invalid-content text
  count separately.
- Prefer block/source mapping over count-only evidence.
- Use extension-free Playwright context for close evidence.

### R3 - #41 scope bleed

Risk:

```txt
Long-line code or pullquote fixture coverage may tempt bridge/reset patches.
```

Mitigation:

- This cycle may add or route fixture coverage only.
- Any bridge/reset defect goes to #41 or already-closed #41 evidence, not a
  #44 implementation patch.

### R4 - Semantic mismatch hidden by fixture edits

Risk:

```txt
Changing fixture markup could hide a real core block semantic mismatch.
```

Mitigation:

- Lock 4 applies.
- If editor-valid markup changes semantic meaning, route it instead of
  silently normalizing it.

### R5 - Material Symbols misrouting

Risk:

```txt
The specimen cycle could incorrectly blame separator or block visibility on
Material Symbols font constraints.
```

Mitigation:

- #14 remains the icon-font/layout-shift owner.
- #44 cross-references #14 only when actual font evidence exists.
- Separator visibility stays a bridge/style-variation matter unless proven
  otherwise.

### R6 - Validator artifact churn

Risk:

```txt
Running validators writes tmp artifacts or generated reports that should not
be committed in a plan/report-only phase.
```

Mitigation:

- Commit only planned source/report artifacts.
- Restore generated validator artifacts if they are not part of the reviewed
  scope.

## Files Expected To Change After GO

Phase 1:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-1-REPORT.md
```

Phase 2, depending on selected route:

```txt
products/reference-implementations/axismundi-pilot/fixtures/core-block-specimen-wall.html
tools/validators/validate_pilot_specimen_wall.js
tools/generators/build_pilot_specimen_wall.py
package.json
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-2-REPORT.md
```

Only the files proven necessary by Phase 1 should change.

Phase 3:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-3-VISUAL-QA.md
```

Phase 5:

```txt
docs/v3.6.7/WP-SPECIMEN-FOLLOWON-EDITOR-COMPATIBILITY-PHASE-5-CLOSE.md
BACKLOG.md
CHANGELOG.md
ROADMAP.md
CURRENT-STATE.md
NEXT-SESSION.md
```

## Files Not Expected To Change

```txt
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/stylesheets/blocks.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/ripple/*
styleguide/*
```

If Phase 1 proves one of these files must change, stop and request review
approval before expanding scope.

## Opus Review Checklist

Phase 0 review should verify:

1. #44 is the correct next candidate after v3.6.6.
2. The cycle is diagnostic-first and does not assume fixture repair.
3. Route buckets A-F cover editor-valid, front-end-only, split-fixture,
   diagnostic-only, and coverage-only outcomes.
4. #41 bridge/reset implementation is explicitly out of scope.
5. Locks 1-4 are preserved.
6. #14 Material Symbols cross-reference is constrained and not used to reroute
   separator visibility without evidence.
7. Validation includes `validate:specimen-wall`, `validate:computed`, Axis E/F/G,
   and explicit editor console/block-validation counts.
8. Phase 4 remains intentionally unused.

## Next

Submit this Phase 0 plan for Opus review. Do not edit implementation files
until Phase 0 receives GO.
