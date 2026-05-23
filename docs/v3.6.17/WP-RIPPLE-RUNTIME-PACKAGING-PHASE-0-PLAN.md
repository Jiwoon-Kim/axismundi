# v3.6.17 - WP Ripple Runtime Packaging Decision - Phase 0 Plan

Date: 2026-05-23
Status: Phase 0 plan
Primary candidate: BACKLOG #41 shared WordPress ripple runtime packaging decision
Priority / route: P1 / GO candidate for Phase 1 diagnostic only

## User Request Log

User requested:

```txt
Continue from the post-v3.6.16 handoff.
Read NEXT-SESSION.md section 0 reading order.
Confirm current HEAD / working tree / push state.
Start the v3.6.17 Phase 0 plan.

Priority:
P1 / GO - BACKLOG #41 shared WordPress ripple runtime packaging decision

Scope:
Only shared WordPress ripple runtime packaging decision.
Must evaluate provider/runtime/Pilot/WP boundaries and runtime mirror-surface
impact.
Do not mix in remaining no-inline-styles / compat-api/css / Edge Tools /
webhint warnings.

Non-goals required:
#46 disabled ripple host hygiene
#47 popover provider hygiene
#21 Interpreter Plugin
#44 specimen coverage
Pilot revision

Phase 4:
Intentionally unused unless Phase 1 discovers a deeper architecture audit need.
```

Operational constraints:

```txt
Codex: execution / documentation / implementation after gates
Opus: review only; no file edits
Verdict format: P1/P2/P3 + GO/NO-GO/APPROVE WITH NOTES
Lock 5 remains active
Phase 1 diagnostic before implementation
Phase 2 forbidden before Opus Phase 1 verdict + user execution GO
Local git status is authoritative for mount-staleness cases
New/modified files should remain LF
```

## Current Repo State

Local status at Phase 0 entry:

```txt
HEAD:   0142dbb Close v3.6.16 lab a11y diagnostics fix
Branch: main == origin/main after git fetch origin --prune
Push:   no local commits ahead of origin/main
```

Working tree:

```txt
Modified before v3.6.17 Phase 0:
  products/reference-implementations/axismundi-lab/modules/button/lab-button.css
  products/reference-implementations/axismundi-lab/modules/carousel/lab-carousel.css
```

Those pre-existing CSS changes are unrelated to BACKLOG #41 and are outside
this cycle's expected write scope. v3.6.17 must not revert or normalize them.

Current matrix snapshot remains:

```txt
DONE       31
PARTIAL     0
TODO        0
RECORD      3
```

## Source Inputs

Phase 0 orientation followed the NEXT-SESSION.md section 0 route, with emphasis
on the current-state and #41 lineage:

```txt
AGENTS.md
CURRENT-STATE.md
PROJECT-CONTEXT.md
CHANGELOG.md latest entry
ROADMAP.md current tail
BACKLOG.md #21 / #41 / #44 / #46 / #47 / #14
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-5-CLOSE.md
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-1-REPORT.md
docs/v3.6.16/LAB-A11Y-DIAGNOSTICS-FIX-PHASE-0-PLAN.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-0-PLAN.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-1-REPORT.md
docs/v3.6.6/WP-BLOCK-BRIDGE-RIPPLE-EDITOR-STATE-PARITY-PHASE-5-CLOSE.md
tools/generators/build_pilot_assets.py
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/functions.php
```

Phase 1 should complete any remaining deep reads from the handoff order only
where they affect the packaging decision. Older Wave component docs are close
evidence and should not be reopened unless Phase 1 finds a direct boundary
dependency.

## #41 Narrowed Baseline

BACKLOG #41 originally covered WordPress block bridge state and ripple
enhancement. v3.6.3 through v3.6.6 already closed the reset, bridge, semantic,
editor token, and current editor state parity slices.

Remaining #41 scope after v3.6.6:

```txt
shared WordPress ripple runtime packaging decision:
  decide whether a future v3.7.x WordPress binding / plugin-custom track
  packages the Ripple v2 provider for WordPress surfaces.

Sub-decisions:
  1. post-content front-end anchors
  2. editor-owned content surfaces
  3. forbidden ancestor policy
  4. attach/detach lifecycle
  5. shared token alias location
```

The current Pilot front-end runtime remains Pilot-only:

```txt
data-ax-ripple marker: yes
.ax-ripple insertion: yes
aria-hidden ripple node: yes
window.axRipple API: no
attach/detach/refresh: no
.ax-ripple-host classes: no
unbounded mode: no
forbidden ancestor policy: no
--md-ripple-* aliases: no
--ax-ripple-* aliases: no
```

Phase 0 does not assume graduation. Phase 1 must diagnose and recommend one
packaging route.

## Boundary Model

### Provider / Runtime Boundary

Existing lab provider:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/docs/RIPPLE-V2-AUDIT.md
```

Current provider contract:

```txt
[data-ax-ripple]
[data-ax-ripple="bounded"]
[data-ax-ripple="unbounded"]
.ax-ripple-host
.ax-ripple-host--bounded
.ax-ripple-host--unbounded
window.axRipple.attach(control, options?)
window.axRipple.detach(control)
window.axRipple.refresh(root?)
forbidden ancestors: .prose, .wp-block-post-content, .entry-content, [contenteditable]
```

Phase 1 must decide whether this provider is:

```txt
A. lab-only provider, not packaged for WordPress;
B. theme-packaged provider for front-end WordPress only;
C. plugin/custom-binding packaged provider for WordPress surfaces;
D. split provider with CSS/theme state in theme and JS ripple in plugin;
E. deferred because the boundary still needs deeper architecture review.
```

### Pilot Boundary

Current Pilot bridge source:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
```

Current copied Pilot assets:

```txt
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

At Phase 0 entry, source and copied Pilot assets are byte-equivalent by SHA256:

```txt
pilot-block-bridge.css:
  5D52091467E6DE2C13FF6E03D27181EB8349A5FD7736E6CBB47B709EFC716552

pilot-block-bridge.js:
  D2506A65D38AA265D999FD4A6D43DEE45BA1B83E9AF538672F7545A0091DBA16
```

### WordPress Boundary

`products/reference-implementations/axismundi-pilot/functions.php` currently:

```txt
adds editor styles for copied CSS assets, including pilot-block-bridge.css
enqueues front-end copied CSS assets
enqueues assets/scripts/pilot-block-bridge.js on the front end only
registers core block styles
does not register custom blocks
does not enqueue Pilot runtime JS inside the editor canvas
```

This boundary is central: editor styles receive CSS, but editor content does not
receive the Pilot front-end ripple runtime.

## Runtime Mirror Surfaces

For this decision, "runtime mirror surfaces" means the shared runtime decision
may touch or constrain three simultaneously tracked surfaces:

```txt
1. Lab provider source:
   products/reference-implementations/axismundi-lab/modules/ripple/

2. Pilot bridge source:
   products/reference-implementations/axismundi-pilot/bridge/

3. Pilot copied runtime assets:
   products/reference-implementations/axismundi-pilot/assets/
```

Phase 1 must explicitly answer:

```txt
1. Which surface is authoritative if a shared WordPress ripple runtime exists?
2. Does build_pilot_assets.py copy the right source, or would it need a new
   packaging source?
3. Are Pilot bridge source and copied assets expected to stay byte-identical?
4. Would lab provider files remain lab-only, become shared source, or be split?
5. Where would token aliases live if shared across lab + WordPress?
6. What validation detects drift across all three surfaces?
```

Phase 0 expectation:

```txt
No Phase 2 implementation patch is presumed.
If Phase 1 recommends any 3-copy mutation, stop for Opus verdict and user GO.
```

Note:

```txt
The canonical 3-tracked-copy pattern for tokens.sys.light.css across lab,
Pilot, and styleguide is not this cycle's expected write scope. This section
only covers the ripple runtime's mirror surfaces.
```

## Route Candidates

### Route A - Decision-Only Packaging Report

```txt
Produce a Phase 1 diagnostic and Phase 2 no-code decision report.
Record whether shared WordPress ripple runtime should be theme, plugin/custom,
split, or deferred.
Do not patch runtime files in v3.6.17.
```

Pros:

```txt
matches the narrowed #41 decision scope
keeps provider/runtime/Pilot/WP boundaries visible
avoids accidental plugin or Pilot revision work
can close or re-route #41 without risky packaging churn
```

Cons:

```txt
does not produce a runtime package
may leave a future implementation item if the decision selects a packaging path
```

Default:

```txt
Use Route A if Phase 1 surfaces no evidence requiring an implementation route.
```

### Route B - Theme-Packaged Front-End Runtime Proposal

```txt
Diagnose whether lab Ripple v2 can be packaged into the Pilot/theme front end
for post-content anchors while staying out of the editor canvas.
```

Phase 1 must treat this as a proposal only. It cannot enter Phase 2 without
Opus verdict and user execution GO because it may require packaging source and
forbidden-ancestor policy changes.

Primary risk:

```txt
Ripple v2 currently forbids .wp-block-post-content and .entry-content, while
the current Pilot target is .wp-block-button__link inside post content.
```

### Route C - Plugin / Custom Binding Runtime Proposal

```txt
Classify shared WordPress ripple runtime as plugin/custom-binding territory.
The theme keeps CSS state-layer parity; animated pointer ripple for WordPress
content ships later through a plugin/custom runtime package.
```

Pros:

```txt
respects editor-owned surfaces and [contenteditable] boundaries
aligns with the existing "custom blocks/plugin territory" discipline
keeps the Pilot from becoming the shared runtime source
```

Cons:

```txt
must avoid accidentally expanding into BACKLOG #21 Interpreter Plugin
needs a crisp future handoff shape if selected
```

### Route D - Split CSS State / JS Ripple Policy

```txt
Keep theme CSS state-layer mappings in the Pilot/theme bridge, but classify
animated JS ripple packaging as a separate plugin/custom-binding runtime.
```

Evaluate this candidate against Q3-Q5 evidence: post-content anchors,
editor-owned surfaces, and attach/detach lifecycle.

### Route E - Deeper Architecture Audit Trigger

```txt
Use only if Phase 1 discovers the decision cannot be made without a broader
architecture audit across plugin packaging, editor extension APIs, or future
distribution structure.
```

If Route E is selected, Phase 4 may become active. Otherwise Phase 4 remains
intentionally unused.

## Phase 1 Diagnostic Questions

Phase 1 is read-only except for its report document.

Required questions:

```txt
Q1. Current Runtime Inventory
    Which ripple/runtime files are loaded on Pilot front end, editor parent,
    and editor iframe?

Q2. Provider Contract Compatibility
    Which Ripple v2 provider features are mandatory for a shared WordPress
    runtime, and which are lab-only?

Q3. Post-Content Anchors
    Can a runtime attach to .wp-block-button__link inside
    .wp-block-post-content without violating the current forbidden-ancestor
    policy?

Q4. Editor-Owned Surfaces
    Is animated ripple allowed in the editor canvas, or should editor parity
    remain CSS state-layer only?

Q5. Attach / Detach Lifecycle
    Does WordPress front-end navigation, block rendering, or editor mutation
    require window.axRipple.refresh/detach semantics, MutationObserver, or a
    WordPress-specific bootstrap?

Q6. Token Alias Location
    If --md-ripple-* / --ax-ripple-* aliases become shared, do they belong in
    lab ripple CSS, Pilot bridge CSS, token bridge CSS, or a future plugin
    stylesheet?

Q7. Runtime Mirror Drift
    How would lab provider source, Pilot bridge source, and copied Pilot assets
    remain synchronized if any shared runtime path is selected?

Q8. WordPress Packaging Surface
    Is the correct owner the Pilot theme, a future WordPress binding package,
    a plugin/custom-block track, or a split?

Q9. Validation
    What commands and browser probes are required before any implementation
    route can safely proceed?

Q10. Phase 4 Trigger
    Did Phase 1 discover a deeper architecture audit need?
```

Expected artifact:

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-1-REPORT.md
```

## Phase Cadence

```txt
Phase 0: plan and route candidates
Phase 1: read-only diagnostic and route recommendation
Phase 2: no-code decision report or implementation only after Opus verdict +
         user execution GO
Phase 3: verification appropriate to the Phase 2 route
Phase 4: intentionally unused unless Phase 1 discovers deeper architecture
         audit need
Phase 5: close / release metadata
```

Phase 2 is explicitly blocked until:

```txt
1. Phase 1 report is complete.
2. Opus returns Phase 1 verdict in P1/P2/P3 + GO/NO-GO/APPROVE WITH NOTES form.
3. User gives execution GO.
```

## Expected Write Scope

Phase 0:

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-0-PLAN.md
```

Phase 1:

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-1-REPORT.md
```

Phase 2, only after required gates:

```txt
docs/v3.6.17/WP-RIPPLE-RUNTIME-PACKAGING-PHASE-2-DECISION.md
```

Possible implementation files only if Phase 1 + review + user GO explicitly
select an implementation route:

```txt
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.css
products/reference-implementations/axismundi-lab/modules/ripple/lab-ripple.js
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
products/reference-implementations/axismundi-pilot/functions.php
tools/generators/build_pilot_assets.py
```

The possible implementation list is not a permission grant for Phase 2. It is
the boundary surface Phase 1 must evaluate.

## Fences

Expected to remain unchanged in v3.6.17 unless Phase 1 produces a reviewed
scope expansion:

```txt
products/reference-implementations/axismundi-lab/stylesheets/components.css
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/modules/popover/
products/reference-implementations/axismundi-lab/modules/nav-bar/
products/reference-implementations/axismundi-lab/modules/menu/
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/fixtures/
products/reference-implementations/axismundi-pilot/patterns/
products/reference-implementations/axismundi-pilot/templates/
styleguide/
```

Policy/diagnostics warnings remain routed outside #41:

```txt
no-inline-styles policy
compat-api/css broad warnings
Microsoft Edge Tools / webhint normative policy
VS Code workspace diagnostics config policy
button-group inline-size: fit-content compatibility warning
```

## Non-Goals

Required non-goals for this cycle:

```txt
BACKLOG #46 disabled ripple host authoring hygiene: not included.
BACKLOG #47 popover provider menu-item-class extraction hygiene: not included.
BACKLOG #21 Interpreter Plugin: not implemented or designed beyond naming
  possible plugin/custom-binding ownership as a route outcome.
BACKLOG #44 specimen coverage / validator hardening: not included.
Pilot revision: not included.
```

Additional non-goals:

```txt
No custom block registration.
No Gutenberg core modification.
No broad editor compatibility repair.
No #44 editor fixture expansion.
No theme.json token/value rewrite.
No components.css baseline rewrite.
No lab component reopen.
No direct styleguide mirror edits.
No TT5-derived visual or token implementation.
```

## Applicable Gates

Universal / infrastructure gates:

```txt
G1. Validator / npm test remains PASS before close.
G2. Baseline authoring surfaces remain fenced unless explicitly approved.
G5. CHANGELOG entry at Phase 5 if the cycle closes.
G22. Multi-consumer provider requirement preserved or graduation rejected.
G23. Semantic neutrality preserved: ripple does not become button-only meaning.
G24. Boundary rules respected: static state-layer foundation remains separate
     from animated pointer ripple.
G25. Independent audit / decision evidence recorded.
G26. Public dependency contract documented if any contract changes.
```

WordPress / token gates:

```txt
Axis E - md-sys color maps to md-ref
Axis F - bridge downstream-only
Axis G - wp-custom downstream-only
validate:specimen-wall
validate:computed
```

Lock impact:

```txt
Lock 1: no wp-custom literal regression allowed.
Lock 2: no md-sys literal color regression allowed.
Lock 3: v3.6.3 core/button anchor route is not reopened.
Lock 4: mismatched editor/content semantics must be routed, not patched over.
Lock 5: Phase 1 diagnostic is mandatory before implementation.
```

## Validation Plan

Phase 1 read-only support:

```powershell
git status --short --branch
git branch -vv
Get-FileHash products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.css,
  products\reference-implementations\axismundi-pilot\assets\styles\pilot-block-bridge.css,
  products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.js,
  products\reference-implementations\axismundi-pilot\assets\scripts\pilot-block-bridge.js
```

If Phase 2 remains no-code:

```powershell
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

If Phase 2 edits runtime or packaging files, add:

```powershell
node --check products\reference-implementations\axismundi-lab\modules\ripple\lab-ripple.js
node --check products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.js
python tools\generators\build_pilot_assets.py
git diff --exit-code -- products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.css products\reference-implementations\axismundi-pilot\assets\styles\pilot-block-bridge.css
git diff --exit-code -- products\reference-implementations\axismundi-pilot\bridge\pilot-block-bridge.js products\reference-implementations\axismundi-pilot\assets\scripts\pilot-block-bridge.js
```

Browser probes, only if runtime behavior changes:

```txt
Pilot front end:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Editor smoke:
  editor-valid smoke fixture if #44-owned fixture remains available

Required evidence:
  script/style load counts
  typeof window.axRipple or selected package API
  post-content anchor ripple attach / non-attach decision
  editor iframe runtime absence or approved presence
  console/page errors 0
  focus-visible static state remains visible
  disabled / aria-disabled no unsafe activation
```

## Risks

### R1 - Accidental Scope Expansion Into Plugin Work

Risk:

```txt
The packaging decision may start designing BACKLOG #21 Interpreter Plugin.
```

Mitigation:

```txt
Phase 1 may name plugin/custom-binding ownership as a route, but must not
design or implement the Interpreter Plugin.
```

### R2 - Forbidden Ancestor Policy Conflict

Risk:

```txt
Shared ripple wants WordPress post-content anchors, but Ripple v2 currently
forbids .wp-block-post-content and .entry-content.
```

Mitigation:

```txt
Phase 1 must decide whether this is a hard no, a WordPress-specific exception,
a split package, or plugin/custom territory.
```

### R3 - Editor-Owned Surface Overreach

Risk:

```txt
Animated ripple could be pushed into [contenteditable] / editor iframe content
where Gutenberg owns pointer interaction.
```

Mitigation:

```txt
Editor CSS state-layer parity remains the default. Animated editor ripple
requires explicit reviewed approval.
```

### R4 - 3-Copy Drift

Risk:

```txt
Lab provider, Pilot bridge source, and Pilot copied assets diverge.
```

Mitigation:

```txt
Phase 1 must define authority and drift checks before any implementation.
build_pilot_assets.py and hash/diff checks become mandatory for any copied
asset route.
```

### R5 - Token Alias Backflow

Risk:

```txt
Ripple token aliases could be introduced in the wrong layer or with literals.
```

Mitigation:

```txt
Axis E/F/G validation remains mandatory. Alias location is one of the five
preserved #41 sub-decisions.
```

## Review Gate

Phase 0 review should answer:

```txt
P1:
  Any blockers to selecting BACKLOG #41 as v3.6.17 primary candidate?

P2:
  Any missing Phase 1 diagnostic questions for provider/runtime/Pilot/WP
  boundaries or runtime mirror-surface impact?

P3:
  Any non-blocking notes on route wording, validation, or non-goals?

Verdict:
  GO / NO-GO / APPROVE WITH NOTES for Phase 1 diagnostic.
```

Recommended Phase 0 verdict request:

```txt
P1 / GO for Phase 1 diagnostic only.
No Phase 2 implementation authorization is implied.
```

## Next

Submit this Phase 0 plan for Opus review. Do not edit implementation files and
do not enter Phase 1 until Phase 0 receives GO.
