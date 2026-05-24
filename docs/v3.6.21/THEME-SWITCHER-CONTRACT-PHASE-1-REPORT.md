# v3.6.21 Theme Switcher Contract - Phase 1 Report

## Verdict

Phase 1 diagnostic is complete.

Recommended Phase 2 route:

```txt
Layer 1: Route A - no-code contract decision
Layer 2: Route B - narrow docs/comment hygiene, if user wants implementation
Layer 3: Route D - explicit auto-state remains a follow-on implementation route
```

Do not enter Route D in Phase 2 unless the user explicitly accepts a tracked
multi-surface implementation. Current CSS already treats `data-theme="auto"` and
an absent `data-theme` attribute equivalently under OS-dark media queries, but
closing BACKLOG #22 fully requires a larger theme/runtime validation pass than a
selector contract cycle.

## Read-Only Status

Phase 1 preserved the read-only lock:

```txt
implementation files: 0 edits
asset files:          0 edits
template files:       0 edits
theme.json:           0 edits
D-layer files:        0 edits
generated mirror:     0 edits
archive files:        0 edits
```

Local git ground truth at start:

```txt
HEAD:   aefb384 Close v3.6.20 pilot vs distributable bootstrap
Branch: main...origin/main = 0/0
Status: docs/v3.6.21/ untracked only
```

## Source Inputs Read

Primary:

```txt
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-0-PLAN.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-5-CLOSE.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-2-DECISION.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-1-PLAN.md
docs/v3.6.0/ONTOLOGY-THEME-PILOT-PHASE-3-REPORT.md
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-5-CLOSE.md
docs/v3.6.1/TOKEN-ARCHITECTURE-REFACTOR-PHASE-3-VISUAL-QA.md
bindings/wordpress-material3/FEEDBACK-AND-STRATEGY.md
BACKLOG.md #21
BACKLOG.md #22
BACKLOG.md closed #8 / #9 / #12 theme-switcher cohort fixes
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-lab/style-guide.html
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
products/reference-implementations/axismundi-pilot/parts/header.html
products/reference-implementations/axismundi-pilot/functions.php
products/reference-implementations/axismundi-pilot/theme.json
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.dark.css
products/reference-implementations/axismundi-pilot/assets/styles/base.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-preset.bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/wp-custom.bridge.css
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
```

Read-only comparison:

```txt
styleguide/index.html
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js
styleguide/stylesheets/base.css
styleguide/stylesheets/tokens.sys.dark.css
products/_archive/axismundi-prototype/
```

## Key Findings

F1 - `.sg-theme` is an active lab/styleguide contract.

Evidence:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
  .sg-theme count: 16
  data-theme-button count: 9

styleguide/index.html
  .sg-theme count: 16
  data-theme-button count: 9

lab module pattern pages:
  carousel, chip, date-time, icon-system, popover, search-expansion,
  snackbar, tooltip use .sg-theme with data-theme-set.
```

Conclusion:

`theme.js` is not wrong to call `.sg-theme` canonical for lab/module surfaces.
The contract is real.

F2 - `.ax-theme-switcher` is no longer archive-only in practice.

Evidence:

```txt
products/reference-implementations/axismundi-pilot/parts/header.html
  .ax-theme-switcher count: 1
  data-theme-set count: 3

products/_archive/axismundi-prototype/front-page-microblog.html
  .ax-theme-switcher count: 5
  data-theme-set count: 3
```

Conclusion:

The `theme.js` comment that says `.ax-theme-switcher` is archive-only is stale
after v3.6.0 Pilot creation and v3.6.20 Pilot/distributable boundary work.

F3 - Runtime already accepts both selectors.

Evidence:

```js
document.querySelectorAll(".sg-theme, .ax-theme-switcher")
```

in:

```txt
products/reference-implementations/axismundi-lab/scripts/theme.js
styleguide/scripts/theme.js
```

Conclusion:

No selector runtime bug was found. The drift is contract/documentation
classification, not a failing click path.

F4 - There are two theme-button vocabularies by design.

Evidence:

```txt
style-guide.js:
  storage key = "axismundi.theme"
  selector    = [data-theme-button]

theme.js:
  storage key = "ax-theme"
  selector    = [data-theme-set]

Pilot bridge:
  storage key = "axismundi-pilot-theme"
  selector    = [data-theme-set]
```

Conclusion:

`data-theme-button` is styleguide-local. `data-theme-set` is production/module
runtime. This separation prevents `theme.js` from clobbering styleguide-local
state and should be preserved.

F5 - Auto-state is currently implicit in JS, but explicit in CSS comments.

Evidence:

```js
if (mode === "auto") {
  ROOT.removeAttribute("data-theme");
}
```

appears in:

```txt
products/reference-implementations/axismundi-lab/scripts/theme.js
products/reference-implementations/axismundi-lab/scripts/style-guide.js
products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js
styleguide/scripts/theme.js
styleguide/scripts/style-guide.js
```

CSS comments say `data-theme="auto"` or absent both follow OS dark:

```txt
tokens.sys.dark.css:
  data-theme="auto" or absent -> media query takes effect
```

Conclusion:

Explicit `data-theme="auto"` is compatible with current CSS semantics, but the
current JS/storage behavior still removes the attribute for auto.

F6 - BACKLOG #22 is not closed by the current state.

Evidence:

BACKLOG #22 requested:

```txt
data-theme="auto"   -> follow OS preference
data-theme="light"  -> force light
data-theme="dark"   -> force dark
```

and scope included:

```txt
language_attributes filter default data-theme="auto"
small inline head script for localStorage override
frontend and site editor verification
```

Conclusion:

Theme-side JS can make auto explicit, but full BACKLOG #22 close is bigger than
the selector contract alone.

F7 - No Lock 1 / Lock 2 violation is required for a selector contract.

Evidence:

v3.6.1 locks:

```txt
Lock 1: settings.custom.axismundi.* entries are downstream-only var() leaves.
Lock 2: every --md-sys-color-* maps to --md-ref-palette-*.
```

FEEDBACK-AND-STRATEGY keeps strict M3 mode direction:

```txt
md-ref -> md-sys -> wp-preset/wp-custom -> theme.json projections
```

Conclusion:

Routes A/B/C can avoid token edits entirely. Route D touches root state and dark
mode selectors but still does not require `--wp--preset` or `wp-custom` source
reversal.

F8 - Visitor preference and editor preview remain separate.

Evidence:

FEEDBACK-AND-STRATEGY places PluginSidebar / editor preview work in Interpreter
Plugin territory. The current theme runtime stores visitor/front-end state in
localStorage keys, not editor state:

```txt
ax-theme
axismundi.theme
axismundi-pilot-theme
```

Conclusion:

No Phase 2 route should synchronize editor preview state with front-end visitor
preference.

## Markup Inventory

| Surface | Active class | Attribute | Storage owner | Status |
|---|---|---|---|---|
| lab `style-guide.html` | `.sg-theme` | `data-theme-button` | `style-guide.js` / `axismundi.theme` | active catalog-local |
| generated `styleguide/index.html` | `.sg-theme` | `data-theme-button` | `style-guide.js` / `axismundi.theme` | generated mirror |
| lab module pages | `.sg-theme` | `data-theme-set` | `theme.js` / `ax-theme` | active module runtime |
| Pilot header | `.ax-theme-switcher` | `data-theme-set` | Pilot bridge / `axismundi-pilot-theme` | active Pilot runtime |
| archive prototype | `.ax-theme-switcher` | `data-theme-set` | archive `theme.js` / `ax-theme` | historical reference |

Count snapshot:

| File | `.sg-theme` | `.ax-theme-switcher` | `data-theme-set` | `data-theme-button` |
|---|---:|---:|---:|---:|
| `products/reference-implementations/axismundi-lab/style-guide.html` | 16 | 0 | 0 | 9 |
| `styleguide/index.html` | 16 | 0 | 0 | 9 |
| `products/reference-implementations/axismundi-pilot/parts/header.html` | 0 | 1 | 3 | 0 |
| `products/reference-implementations/axismundi-lab/scripts/theme.js` | 3 | 4 | 8 | 1 |
| `products/reference-implementations/axismundi-lab/scripts/style-guide.js` | 0 | 0 | 0 | 1 |
| `products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js` | 0 | 0 | 4 | 0 |
| `products/_archive/axismundi-prototype/front-page-microblog.html` | 0 | 5 | 3 | 0 |

## Q1 - Where Does Markup Exist?

Answer:

```txt
lab catalog:        .sg-theme + data-theme-button
lab module pages:  .sg-theme + data-theme-set
Pilot header:      .ax-theme-switcher + data-theme-set
archive:           .ax-theme-switcher + data-theme-set
generated mirror:  mirrors lab catalog / scripts
```

`style-guide-blocks.html` and `style-guide-prose.html` did not surface active
theme-switcher markup in the Phase 1 grep. Their future shell consistency work
remains separate from this contract decision.

## Q1.1 - Where Is Active `.sg-theme`?

Active `.sg-theme` exists in:

```txt
products/reference-implementations/axismundi-lab/style-guide.html
styleguide/index.html
products/reference-implementations/axismundi-lab/modules/*/*pattern.html
products/_archive/axismundi-prototype/style-guide.html
```

It does not appear to be merely stale. The stale part is narrower: the comment
that reserves `.ax-theme-switcher` for archive-only use.

## Q2 - Canonical Selector

Recommended answer:

```txt
Formalize both selectors as different surface contracts.
```

Contract:

| Selector | Owner | Meaning |
|---|---|---|
| `.sg-theme` | lab / styleguide / module pattern surfaces | specimen, catalog, and local module runtime theme controls |
| `.ax-theme-switcher` | Pilot / future product theme surfaces | product-facing front-end theme control |

Do not introduce a new selector in this cycle. It would create churn without
solving a runtime failure.

## Q2.1 - Legacy Classification Hypotheses

Hypothesis A:

```txt
The comment is outdated; .ax-theme-switcher is active Pilot / future product
surface.
```

Verdict: PARTIAL. It is active in Pilot, but "future product" still requires a
future product/skeleton cycle per M1/M2.

Hypothesis B:

```txt
The comment is valid; Pilot currently uses the wrong selector.
```

Verdict: NO. v3.6.20 explicitly kept Pilot as probe but also recorded the active
Pilot header markup as the Theme Switcher follow-on input. Treating Pilot as
"wrong" would erase real evidence.

Hypothesis C:

```txt
Both selectors are active contracts with different owners.
```

Verdict: YES. This is the strongest contract decision.

## Q3 - `data-theme-set` Contract

`data-theme-set` is stable for:

```txt
lab module pages
Pilot header
archive production-style front page
theme.js / Pilot bridge delegated click handlers
```

Allowed values:

```txt
light
dark
auto
```

`data-theme-button` remains styleguide-local and should not be renamed inside
this cycle.

## Q4 - Root `[data-theme]` Contract

Current behavior:

```txt
light -> <html data-theme="light">
dark  -> <html data-theme="dark">
auto  -> <html> with no data-theme attribute
```

Current CSS:

```css
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) { ... }
}
```

and:

```css
@media (prefers-color-scheme: dark) {
  html:not([data-theme="light"]) { color-scheme: dark; }
}
```

Impact of explicit `data-theme="auto"`:

- In current CSS, it still matches `:root:not([data-theme="light"])`.
- It also still matches `html:not([data-theme="light"])`.
- No token value changes are required for semantic equivalence today.
- However, BACKLOG #22 wants explicit selectors to prevent future variants from
  being silently absorbed by `:not([data-theme="light"])`.

Route implication:

```txt
Phase 2 can decide the contract.
Phase 2 should not claim #22 closed unless it implements JS + CSS selector
rewrite + PHP root default + verification.
```

## Q5 - Storage Contract

Current storage keys:

| Surface | Key | Purpose |
|---|---|---|
| lab `theme.js` | `ax-theme` | production/module prototype preference |
| lab `style-guide.js` | `axismundi.theme` | catalog-local preference |
| Pilot bridge | `axismundi-pilot-theme` | Pilot front-end visitor preference |
| archive `theme.js` | `ax-theme` | historical prototype preference |

Recommendation:

Do not unify keys in v3.6.21. The separation reflects owner boundaries.

## Q6 - Theme-Only vs Interpreter Plugin

Theme-only allowed:

- static `light` / `dark` / `auto`;
- localStorage visitor preference;
- CSS sys-layer swap;
- selector contract and root-state clarity.

Interpreter Plugin territory:

- HCT / Material Color Utilities;
- user-driven color regeneration;
- PluginSidebar / InspectorControls;
- editor preview controls;
- Global Styles synchronization;
- visitor/editor state synchronization.

## Q7 - Lock 1 / Lock 2 Implications

Routes A/B/C:

```txt
No Lock 1 / Lock 2 impact.
```

Route D:

```txt
Possible CSS/JS/PHP implementation, but still no reason to reverse
md-sys/wp-preset/wp-custom direction.
```

Required guard if Route D is chosen later:

```txt
Do not bind --md-sys-color-* to --wp--preset--*.
Do not put literal values into settings.custom.axismundi.*.
```

## Q8 - Implementation or Decision Cycle?

Recommendation:

```txt
Decision-first.
```

Phase 2 should at minimum create a contract decision document. If the user wants
a small implementation, limit it to comment/docs hygiene:

- update `theme.js` / mirrored `styleguide/scripts/theme.js` comments to say
  `.sg-theme` is lab/styleguide and `.ax-theme-switcher` is Pilot/product;
- do not alter runtime behavior yet.

## Q9 - Tracked-Copy Impact

Tracked surfaces:

| Surface | Current role |
|---|---|
| lab `scripts/theme.js` | source copy |
| generated `styleguide/scripts/theme.js` | mirror copy |
| Pilot `assets/scripts/pilot-block-bridge.js` | product/probe runtime |
| lab `stylesheets/base.css` / `tokens.sys.dark.css` | source CSS |
| Pilot `assets/styles/base.css` / `tokens.sys.dark.css` | product/probe copy |
| generated `styleguide/stylesheets/*` | mirror copy |

Impact:

- Selector comment hygiene affects lab source and generated mirror.
- Selector runtime changes affect lab and generated mirror immediately, Pilot
  only if Pilot bridge is changed separately.
- Explicit auto-state affects at least lab JS, styleguide JS, Pilot bridge,
  base.css, tokens.sys.dark.css, and possibly `functions.php` for
  `language_attributes`.

Therefore Route D is not a "one-line JS cleanup" if the goal is #22 close.

## Q10 - BACKLOG #22 Status Mapping

| Phase 2 route | #22 outcome |
|---|---|
| Route A no-code decision | keep open |
| Route B comment/docs hygiene | keep open, narrowed by contract |
| Route C selector/markup alignment | keep open unless auto-state implemented |
| Route D explicit auto-state implementation | candidate close only if JS + CSS + PHP default + verification all land |
| Route E plugin boundary decision | keep open or route to #21 runtime module depending detail |

Recommended v3.6.21 outcome:

```txt
Keep #22 open, but narrow it:
  selector contract separated from explicit auto-state implementation.
```

## Route Evaluation

Route A - no-code contract decision:

```txt
Verdict: GO / recommended baseline.
```

Rationale:

- Current runtime is not failing.
- The main defect is contract language drift.
- This preserves v3.6.20 no-skeleton boundary.

Route B - narrow docs/comment hygiene:

```txt
Verdict: GO if user wants a tiny implementation after Phase 2 review.
```

Allowed edits:

```txt
products/reference-implementations/axismundi-lab/scripts/theme.js
styleguide/scripts/theme.js
docs/v3.6.21/...
```

Only if Phase 2 explicitly chooses it:

- update comments to formalize `.sg-theme` and `.ax-theme-switcher` owners;
- no runtime selector behavior change;
- no root auto-state behavior change.

Route C - selector/markup alignment:

```txt
Verdict: NO-GO for v3.6.21 unless Phase 2 rejects dual-contract.
```

Rationale:

No evidence requires changing Pilot markup from `.ax-theme-switcher`; formalized
dual ownership is cleaner.

Route D - explicit auto-state implementation:

```txt
Verdict: DEFER / follow-on candidate.
```

Rationale:

Implementation is feasible, but #22 close needs more than setting
`data-theme="auto"` in one JS file. It should be its own narrow implementation
cycle or a Phase 2 route only with explicit user GO for tracked multi-surface
changes.

Route E - plugin boundary decision:

```txt
Verdict: PARTIAL / already decided.
```

Rationale:

Theme-only vs plugin boundary is clear enough: visitor switcher is theme; editor
preview, HCT, and Global Styles synchronization are BACKLOG #21.

Route F - full Interpreter Plugin:

```txt
Verdict: REJECTED.
```

Route G - distributable/release-seal work:

```txt
Verdict: REJECTED.
```

M1/M2 block it.

## Recommended Phase 2 Content

Phase 2 should write:

```txt
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-2-DECISION.md
```

Expected decision:

1. Formalize dual selector ownership:
   - `.sg-theme` = lab/styleguide/module surface.
   - `.ax-theme-switcher` = Pilot/product-facing surface.
2. Preserve `data-theme-button` as styleguide-local.
3. Preserve `data-theme-set` as production/module/Pilot runtime.
4. Keep visitor preference and editor preview separate.
5. Keep HCT / editor sidebar / Global Styles sync in BACKLOG #21.
6. Keep BACKLOG #22 open but narrowed to explicit root-state implementation.
7. Document that Route D requires tracked multi-surface implementation.

Optional after Opus Phase 2 review and user GO:

```txt
Route B docs/comment hygiene only.
```

## Validation Evidence

Commands run:

```powershell
git status --short --branch
rg -n "sg-theme|ax-theme-switcher|data-theme-set|data-theme-button|localStorage|prefers-color-scheme|data-theme" ...
rg --count "sg-theme|ax-theme-switcher|data-theme-set|data-theme-button" ...
Select-String ... BACKLOG.md / v3.6.0 / v3.6.1 / v3.6.20 docs / FEEDBACK-AND-STRATEGY.md
git diff --check
```

Final validation is recorded after this file is written.

## Review Request

```txt
P1: Any blockers to Phase 2 Route A + optional Route B?
P2: Is dual selector ownership (.sg-theme lab/styleguide, .ax-theme-switcher
    Pilot/product) sufficiently supported by evidence?
P3: Should explicit data-theme="auto" remain a follow-on Route D, or should
    Phase 2 be allowed to implement it?
```

