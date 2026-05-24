# v3.6.22 Theme Switcher Explicit Auto State - Phase 0 Plan

## User Request Log

User selected T3 after v3.6.21 close and the post-close maintenance commit:

```txt
T3 Go
```

Interpreted route:

```txt
v3.6.22 primary candidate:
  BACKLOG #22 explicit data-theme="auto" root-state implementation
```

This cycle follows v3.6.21 Theme Switcher Contract. v3.6.21 made the contract
decision; v3.6.22 may implement the explicit root-state model if Phase 1
confirms the write surface is still bounded.

## Current Repo State

```txt
HEAD:    de106ab Update handoff docs to v3.6.21
Branch:  main...origin/main = 0/0
Tree:    clean at Phase 0 entry
```

Lineage:

```txt
de106ab  Update handoff docs to v3.6.21                (maintenance)
0b629d9  Close v3.6.21 theme switcher contract         (cycle close)
aefb384  Close v3.6.20 pilot vs distributable bootstrap
663b62c  Close v3.6.19 asset surface audit + index
b4ab619  Close v3.6.18 core block mapping audit
```

`de106ab` is a root meta-doc maintenance commit, not a cycle and not part of
the Lock 5 count chain.

## Cycle Frame

This is a narrow implementation candidate, not a theme redesign and not an
Interpreter Plugin cycle.

The problem is the current implicit auto state:

```txt
auto mode in JS        -> removes [data-theme]
auto mode in CSS       -> relies on :not([data-theme="light"])
debuggable DOM state   -> absent
future variant safety  -> weak, because anything not light can fall into dark
```

The target is explicit root-state semantics:

```txt
data-theme="auto"   -> follow OS preference
data-theme="light"  -> force light
data-theme="dark"   -> force dark
```

Phase 0 does not authorize implementation. Phase 1 must diagnose the exact
surface before any file edit.

## Source Inputs

Primary:

```txt
BACKLOG.md #22
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-3-VERIFICATION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-2-DECISION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-1-REPORT.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-0-PLAN.md
NEXT-SESSION.md
CURRENT-STATE.md
```

Memory guardrails:

```txt
project-axismundi-theme-switcher-separation
project-axismundi-theme-switcher-selector-ownership
project-axismundi-tracked-copies
project-axismundi-phase-workflow
project-axismundi-role-division
feedback-scope-discipline
feedback-mount-staleness
project-axismundi-pilot-not-distributable
project-axismundi-distributable-skeleton-prerequisites
```

Implementation inventory targets for Phase 1 read-only review:

```txt
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js

products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.dark.css
products/reference-implementations/axismundi-lab/stylesheets/base.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/base.css
styleguide/stylesheets/tokens.sys.dark.css
styleguide/stylesheets/base.css

products/reference-implementations/axismundi-pilot/functions.php
```

Optional archaeology / comparison inputs:

```txt
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
docs/v3.5.0/PUBLIC-SURFACE-CHARTER.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-1-PLAN.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
```

## In Scope

Phase 1 diagnostic:

1. Inventory all current auto/light/dark JS mutation paths.
2. Inventory all `:not([data-theme="light"])` CSS cascade paths across lab,
   Pilot, and generated styleguide surfaces.
3. Determine whether `data-theme="auto"` can be written without changing
   storage keys, selector ownership, or visitor/editor separation.
4. Determine whether CSS can replace implicit `:not([data-theme="light"])`
   auto matching with explicit auto / absent selectors while preserving current
   visual behavior.
5. Determine whether Pilot `functions.php` should set root
   `data-theme="auto"` by default via `language_attributes` in this cycle.
6. Determine whether BACKLOG.md's older inline head-script note remains in
   scope, should be rejected, or should route to a future product/distributable
   cycle.
7. Determine generated mirror handling:
   - source-only + publish regeneration later;
   - direct source + mirror edit in one reviewed pass;
   - publish tool run;
   - or no mirror change if Phase 1 finds mirror is out of implementation
     scope.
8. Produce a Phase 2 route recommendation.

Potential Phase 2 implementation, only after review + user GO:

```txt
JS:  set explicit data-theme="auto" instead of removing the attribute.
CSS: replace current :not([data-theme="light"]) dark-auto selectors with
     explicit absent + auto selectors, if Phase 1 proves equivalence.
PHP: add Pilot root default data-theme="auto" only if Phase 1 proves it is
     safe for WordPress and not a distributable/skeleton decision.
Docs: update BACKLOG #22 close / narrow status only if implementation verifies.
```

## Non-Goals

This cycle does not:

- implement BACKLOG #21 Interpreter Plugin;
- implement HCT / Material Color Utilities;
- add PluginSidebar, InspectorControls, or editor preview UI;
- sync visitor localStorage with editor preview state;
- change Global Styles or custom color regeneration;
- add high-contrast, sepia, dim, or other future variants;
- change selector ownership between `.sg-theme` and `.ax-theme-switcher`;
- unify `data-theme-button` and `data-theme-set`;
- unify storage keys;
- choose the future distributable visitor storage key;
- create a distributable skeleton;
- generate release-seal derivatives;
- change Pilot product identity, slug, namespace, text domain, or templates;
- edit `theme.json`;
- relocate `wp-custom` or reverse Lock 1;
- change `md-sys` / `md-ref` source routing or reverse Lock 2;
- edit D-layer binding files;
- implement Core Block Catalog split;
- add switcher markup to `style-guide-blocks.html` or `style-guide-prose.html`;
- implement Theme Switcher Route B comment hygiene unless Phase 2 explicitly
  selects a comment/doc sidecar route;
- update root meta-docs opportunistically;
- edit archive files.

## Standing Contracts

Selector ownership from v3.6.21:

```txt
.sg-theme          = lab / styleguide / module selector contract
.ax-theme-switcher = Pilot / future product-facing selector contract
```

Attribute ownership from v3.6.21:

```txt
data-theme-button = styleguide-local runtime
data-theme-set    = production / module / Pilot runtime
```

Storage ownership:

```txt
ax-theme              = lab theme.js
axismundi.theme       = lab/styleguide style-guide.js
axismundi-pilot-theme = Pilot bridge
```

This cycle may change what value is written to the root `data-theme` attribute.
It must not collapse selector, attribute, or storage ownership.

Visitor/editor separation:

```txt
front-end visitor preference != editor author preview
```

Editor behavior can be verified if Pilot root defaults reach editor canvas, but
editor preview UI remains out of scope.

## Tracked Copy / Mirror Policy

The explicit auto-state work touches tracked surfaces:

```txt
lab source
Pilot copy
generated styleguide mirror
```

Phase 1 must decide the mirror handling before Phase 2 implementation:

| Option | Meaning | Tradeoff |
|---|---|---|
| M1 | edit lab + Pilot sources only; mirror updates in later publish cycle | preserves generated-mirror posture, but leaves temporary visible drift |
| M2 | edit lab + Pilot sources and generated styleguide mirror in one reviewed pass | immediate sync, but treats mirror as explicit tracked copy |
| M3 | run publish tooling to regenerate mirror after source edits | most faithful if tooling is stable, but may create broader generated churn |
| M4 | implementation does not touch mirror because Phase 1 proves mirror path is non-authoritative for this change | narrowest, but must be evidence-backed |

Phase 0 does not choose M1-M4. Phase 1 must recommend one.

## Diagnostic Questions

Q1. Current JS mutation inventory:

- Which files remove `data-theme` for `auto`?
- Which files set `data-theme` for light/dark only?
- Which files sync checked/pressed state based on stored `auto`?

Q2. Current CSS cascade inventory:

- How many `:root:not([data-theme="light"])` selectors exist?
- How many `html:not([data-theme="light"])` selectors exist?
- Are lab, Pilot, and styleguide copies byte-identical or only logically
  aligned?

Q3. Explicit auto equivalence:

- Does replacing implicit `:not([data-theme="light"])` with absent + auto
  selectors preserve current behavior for:
  - no attribute;
  - `data-theme="auto"`;
  - `data-theme="light"`;
  - `data-theme="dark"`?

Q4. Future variant safety:

- Which selectors currently absorb hypothetical `sepia`, `dim`, or
  `high-contrast` values into the dark branch?
- Does the proposed explicit selector shape stop that absorption?

Q5. Pilot PHP root default:

- Does Pilot currently set root attributes in `functions.php`?
- Is adding `data-theme="auto"` via `language_attributes` safe for front-end and
  editor contexts?
- Would this be Pilot-only, or would it accidentally decide future
  distributable behavior?
- Q5.1: If Pilot `functions.php` gets a `language_attributes` filter, would a
  future distributable inherit or copy the same route?
- Q5.2: If inherit/copy is required, does that make the PHP default a
  distributable skeleton prerequisite rather than a v3.6.22 Pilot decision?
- Q5.3: Does the filter remain compatible with current WordPress
  `language_attributes` output and editor iframe behavior?
- Q5.4: Can Phase 2 safely route PHP out while still implementing JS + CSS
  explicit auto-state?

Q6. Inline head-script note:

- BACKLOG #22 mentions a small inline head script for localStorage override to
  avoid FOUC. Is this still required after v3.6.21?
- If required, is it theme-only and bounded, or does it belong to BACKLOG #21 /
  future product skeleton territory?

Q7. Generated mirror route:

- Which generated styleguide files would need the same source changes?
- Is publish tooling stable enough to regenerate them in Phase 2?
- If not, should mirror files be directly edited in lockstep?
- Q7.1: If M1 source-only edit is selected, when should publish tooling run?
- Q7.2: If M2 source + mirror edit is selected, are mirror files currently
  byte-identical or otherwise aligned with their lab sources?
- Q7.3: If M3 publish run is selected, what generated churn is expected beyond
  known validator reports?
- Q7.4: If M4 mirror non-authoritative is selected, what proves the mirror is
  unaffected by the #22 root-state change?

Q8. Verification route:

- What browser / validator evidence is required for:
  - lab styleguide catalog click path;
  - lab module pattern click path;
  - Pilot front-end click path;
  - WordPress editor canvas root attribute behavior, if affected?

Q9. Lock impact:

- Does the CSS rewrite preserve Lock 1 (`wp-custom` downstream-only)?
- Does the CSS rewrite preserve Lock 2 (`md-sys` -> `md-ref`)?
- Does the PHP root default touch any Pilot/distributable boundary from M1/M2
  memory?

Q10. BACKLOG #22 close criteria:

- If Phase 2 succeeds, can BACKLOG #22 close?
- Or should it narrow further if inline head-script, editor canvas, or
  distributable default remains unresolved?

## Phase Routes

Route A - No-code decision only:

Use if Phase 1 finds the implementation surface is wider than expected, or if
PHP / inline script / mirror handling needs a deeper architecture decision.

Route B - JS-only explicit root-state:

Set `data-theme="auto"` in JS paths, keep CSS and PHP unchanged. Likely
insufficient to close #22 because CSS would still absorb future variants.

Route C - JS + CSS explicit 3-state:

Set explicit auto in JS and replace implicit CSS selectors across lab, Pilot,
and styleguide tracked surfaces. Candidate narrow implementation if Phase 1
proves current behavior remains equivalent.

Route D - JS + CSS + Pilot PHP default:

Route C plus `functions.php` root default via `language_attributes`. Candidate
for closing #22 if Phase 1 proves WordPress front-end/editor behavior is safe.

Route E - Defer inline head script:

If Phase 1 determines the inline head-script note is FOUC/product-context
territory, explicitly defer it while still closing or narrowing #22 around root
state and cascade semantics.

Route F - Full theme-mode runtime:

Rejected for v3.6.22. This belongs to BACKLOG #21 / Interpreter Plugin.

Route G - Distributable skeleton / product context:

Rejected for v3.6.22. This belongs to a future user slug / product GO cycle.

## Phase 2 Route Decision Tree

Phase 1 should collapse the route choice using evidence:

```txt
if Q5 PHP language_attributes = Pilot-safe and distributable-neutral:
  Route D is available (JS + CSS + Pilot PHP)
  BACKLOG #22 close is plausible if verification also passes

elif Q5 PHP language_attributes = distributable-boundary risk:
  Route C is preferred (JS + CSS)
  PHP default routes to distributable skeleton / product-context cycle
  BACKLOG #22 narrows further rather than fully closing

elif Q3 CSS equivalence = uncertain:
  Route B is the maximum safe implementation (JS-only explicit auto)
  CSS cascade remains follow-on
  BACKLOG #22 narrows further

elif Phase 1 finds the combined JS/CSS/PHP/mirror surface too broad:
  Route A is preferred (no-code decision / split plan)

else:
  Route C is the default narrow implementation candidate
```

Route E can combine with C or D if the old inline head-script note is deferred
to product-context or BACKLOG #21 territory.

## Expected Write Scope

Phase 0:

```txt
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-0-PLAN.md
```

Phase 1:

```txt
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-1-REPORT.md
```

Phase 2, only after review + user GO, may touch a subset of:

```txt
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js

products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.dark.css
products/reference-implementations/axismundi-lab/stylesheets/base.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/base.css
styleguide/stylesheets/tokens.sys.dark.css
styleguide/stylesheets/base.css

products/reference-implementations/axismundi-pilot/functions.php
BACKLOG.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-2-IMPLEMENTATION.md
```

Phase 3 / 5 docs:

```txt
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-3-VERIFICATION.md
docs/v3.6.22/THEME-SWITCHER-AUTO-STATE-PHASE-5-CLOSE.md
```

## Files Not Expected To Change

```txt
theme.json
bindings/
assets/
core/design-systems/material3/assets/
products/distributables/
products/reference-implementations/axismundi-pilot/templates/
products/reference-implementations/axismundi-pilot/parts/header.html
products/reference-implementations/axismundi-pilot/patterns/
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/_archive/
root meta-docs, unless Phase 5 close explicitly chooses maintenance
```

## Validation Plan

Phase 1 read-only:

```txt
git status --short --branch
rg -n "removeAttribute\\(\"data-theme\"\\)|setAttribute\\(\"data-theme\"|:not\\(\\[data-theme=\"light\"\\]|data-theme=\"auto\""
git diff --check
```

Phase 2 / 3 if implementation occurs:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

Browser / WordPress verification expected if implementation occurs:

```txt
lab styleguide click path: auto/light/dark root attribute + selected state
lab module pattern click path: data-theme-set auto/light/dark
Pilot front-end click path: data-theme-set auto/light/dark
Pilot front-end initial root default: data-theme="auto", if PHP route selected
editor canvas root behavior: only if PHP route affects editor runtime
console/page errors: 0
```

Generated artifacts:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

If `npm test` rewrites these files, restore them before close unless the diff is
intentional and reviewed.

## Risks

R1 - State semantics collapse:

Mitigation: preserve `auto` / `light` / `dark` as three explicit values, not a
binary dark/light toggle.

R2 - Selector contract regression:

Mitigation: do not change `.sg-theme` / `.ax-theme-switcher` ownership.

R3 - Attribute vocabulary collapse:

Mitigation: keep `data-theme-button` and `data-theme-set` owner separation.

R4 - Storage key regression:

Mitigation: do not rename storage keys or choose a future distributable key.

R5 - CSS cascade regression:

Mitigation: Phase 1 must prove explicit selectors preserve current light/dark
behavior and improve future variant safety.

R6 - Pilot PHP boundary creep:

Mitigation: Phase 1 must decide whether `language_attributes` is Pilot-safe and
not a distributable skeleton decision.

R7 - Inline head-script scope creep:

Mitigation: treat BACKLOG's old inline script note as a diagnostic question,
not an implementation assumption.

R8 - Generated mirror drift:

Mitigation: Phase 1 must choose mirror handling before Phase 2.

R9 - Lock 1 / Lock 2 reversal:

Mitigation: no `wp-custom`, `theme.json`, `md-ref`, or token-source route
changes.

R10 - BACKLOG #21 creep:

Mitigation: no HCT, editor UI, PluginSidebar, Global Styles sync, or full
theme-mode runtime.

## Lock Compliance Plan

Lock 1:

- Preserve `wp-custom` downstream-only.
- Do not introduce `settings.custom.axismundi.*` source changes.

Lock 2:

- Preserve `md-sys` -> `md-ref` color routing.
- CSS selector changes must not change token values.

Lock 3:

- Do not reopen core/button semantic route.

Lock 4:

- Treat root-state mismatch as routed theme-switcher policy, not opportunistic
  edits.

Lock 5:

- Phase 1 diagnostic must precede implementation.
- Phase 2 implementation requires Opus verdict + user execution GO.

Expected count if implementation closes:

```txt
v3.6.22 = 12th clean Lock 5 self-application overall
implementation-cycle count = 7th
variant = narrow implementation
```

If Phase 2 becomes no-code:

```txt
v3.6.22 = 12th clean Lock 5 self-application overall
implementation-cycle count = 6th unchanged
variant = no-code decision
```

## Phase Cadence

```txt
Phase 0 - plan
Phase 1 - read-only diagnostic
          implementation files: 0
          generated mirror edits: 0
          BACKLOG edits: 0
Phase 2 - implementation or decision report only after Opus verdict and user GO
Phase 3 - verification
Phase 4 - intentionally unused unless Phase 1/2 discovers deeper architecture audit need
Phase 5 - close
```

Phase 4 is expected to remain unused if Phase 1/2 can bound the auto-state
surface. Because #22 crosses JS, CSS, PHP, and generated mirror surfaces, Phase
4 remains available if Phase 1 finds deeper architecture risk.

## Review Request

Opus review should answer:

```txt
P1: Is BACKLOG #22 the correct v3.6.22 primary candidate after v3.6.21?
P2: Are the Phase 1 diagnostic questions sufficient before implementation?
P3: Are expected write scope, mirror handling, PHP scope, and Non-Goals tight enough?
```

Phase 1 must not start until this Phase 0 plan receives review GO and the user
gives explicit Phase 1 execution GO.
