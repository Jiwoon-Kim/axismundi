# v3.6.22 Theme Switcher Explicit Auto State - Phase 1 Report

## Verdict

Phase 1 recommends:

```txt
Route D + Route E

Route D = JS + CSS + Pilot PHP default implementation
Route E = defer the old inline head-script note out of v3.6.22
```

Mirror handling recommendation:

```txt
M2 = edit lab / Pilot sources and generated styleguide mirrors in one reviewed pass
```

Reason:

- The JS and CSS surfaces are small and byte-aligned where expected.
- Explicit auto-state can preserve current behavior while preventing future
  variants from falling through `:not([data-theme="light"])`.
- Pilot PHP can add a Pilot-only root default without deciding a future
  distributable skeleton.
- Inline head script / FOUC mitigation is product-context or BACKLOG #21
  territory and should not block the explicit root-state cleanup.

Phase 1 made no implementation, runtime, CSS, PHP, BACKLOG, generated mirror, or
root meta-doc edits.

## Current Repo State

```txt
HEAD:    de106ab Update handoff docs to v3.6.21
Branch:  main...origin/main = 0/0
Tree:    docs/v3.6.22/ untracked docs only
```

Phase 1 read-only lock:

```txt
implementation files edited: 0
asset files edited:          0
generated mirror edited:     0
BACKLOG.md edited:           0
root meta-docs edited:       0
```

## Source Inputs Read

Cycle / backlog sources:

```txt
BACKLOG.md #22
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-3-VERIFICATION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-2-DECISION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-1-REPORT.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-0-PLAN.md
docs/v3.6.20/PILOT-DISTRIBUTABLE-BOOTSTRAP-PHASE-5-CLOSE.md
NEXT-SESSION.md
CURRENT-STATE.md
```

Memory sources:

```txt
project-axismundi-theme-switcher-separation
project-axismundi-theme-switcher-selector-ownership
project-axismundi-tracked-copies
project-axismundi-pilot-not-distributable
project-axismundi-distributable-skeleton-prerequisites
project-axismundi-phase-workflow
project-axismundi-role-division
feedback-scope-discipline
feedback-mount-staleness
```

Implementation inventory sources:

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

## Executive Findings

F1 - JS auto-state removal is exactly five calls:

| File | `removeAttribute("data-theme")` | `setAttribute("data-theme", ...)` | Storage owner |
|---|---:|---:|---|
| `products/reference-implementations/axismundi-lab/scripts/theme.js` | 1 | 1 | `ax-theme` |
| `products/reference-implementations/axismundi-lab/scripts/style-guide.js` | 1 | 1 | `axismundi.theme` |
| `products/reference-implementations/axismundi-pilot/assets/scripts/pilot-block-bridge.js` | 1 | 1 | `axismundi-pilot-theme` |
| `styleguide/scripts/theme.js` | 1 | 1 | `ax-theme` mirror |
| `styleguide/scripts/style-guide.js` | 1 | 1 | `axismundi.theme` mirror |

Each runtime already validates or derives `auto` as a legal mode. The needed JS
change is not a new state; it is changing root mutation for the existing state:

```txt
current: auto -> remove data-theme
target:  auto -> set data-theme="auto"
```

F2 - CSS implicit auto selectors are exactly 9 executable selectors:

| Surface | File | Executable implicit selectors |
|---|---|---:|
| lab | `tokens.sys.dark.css` | 2 |
| Pilot | `tokens.sys.dark.css` | 2 |
| styleguide | `tokens.sys.dark.css` | 2 |
| lab | `base.css` | 1 |
| Pilot | `base.css` | 1 |
| styleguide | `base.css` | 1 |

`tokens.sys.dark.css` also has one explanatory comment per copy that mentions
`:root:not([data-theme="light"])`, so the search count is 3 per file, but only
2 are executable selectors.

F3 - tracked copies are byte-identical where expected:

| Surface set | Hash status |
|---|---|
| lab `theme.js` / styleguide `theme.js` | identical |
| lab `style-guide.js` / styleguide `style-guide.js` | identical |
| lab / Pilot / styleguide `tokens.sys.dark.css` | identical |
| lab / Pilot / styleguide `base.css` | identical |

Evidence hashes:

```txt
theme.js              61161F9E2235C7E4EE0EFA8CBC3FC2BD15611F3E71C58DBBE076B56822DFC273
style-guide.js        C9FF362FF5FBDD7163951FCB344723F9B066F283536705FDEC7F40432EF74E1E
tokens.sys.dark.css   A25060748DDAF174A1EB06A267E09C57246C3DAD6FC4A22F27A5345734ECE65E
base.css              DD3E57D0B5863D54255168B8F2E7A8DD8CE94A04B74F5E49C2A91AD8B39DBA66
```

F4 - Pilot `functions.php` has no current root attribute filter:

```txt
language_attributes matches: 0
data-theme matches:         0
```

The file currently sets up theme support, editor styles, front-end asset
enqueue, and font library registration. Adding a small `language_attributes`
filter would be a new Pilot-only root default, not a modification to existing
theme identity, template files, or distributable skeleton.

F5 - v3.6.21 contracts remain valid:

```txt
.sg-theme          = lab / styleguide / module selector contract
.ax-theme-switcher = Pilot / future product-facing selector contract
data-theme-button  = styleguide-local runtime
data-theme-set     = production / module / Pilot runtime
storage keys       = owner-specific
visitor preference != editor author preview
```

The v3.6.22 implementation can change root `data-theme` values without
collapsing selector, attribute, or storage ownership.

## Q1 - Current JS Mutation Inventory

Answer:

All five planned JS surfaces currently remove `data-theme` for `auto` and set it
for `light` / `dark`.

Current source pattern in `theme.js`:

```js
function apply(mode) {
  if (mode === "auto") {
    ROOT.removeAttribute("data-theme");
  } else {
    ROOT.setAttribute("data-theme", mode);
  }
}
```

Current source pattern in `style-guide.js`:

```js
function apply(mode) {
  if (mode === "auto") {
    html.removeAttribute("data-theme");
  } else {
    html.setAttribute("data-theme", mode);
  }
}
```

Current source pattern in Pilot bridge:

```js
function apply(mode) {
  if (mode === "auto") {
    ROOT.removeAttribute("data-theme");
  } else {
    ROOT.setAttribute("data-theme", mode);
  }
}
```

The selected state logic already compares buttons against the stored mode, so
it does not require selector / attribute ownership changes.

## Q2 - Current CSS Cascade Inventory

Answer:

The implicit dark-auto cascade appears in 6 files, with 9 executable selectors.

Current token dark-mode shape:

```css
@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) {
    ...
  }
}
```

There are two executable token blocks per `tokens.sys.dark.css` copy:

```txt
§2.3 Auto mode color tokens
§8.2 Dark mode shadow suppression
```

Current base shape:

```css
@media (prefers-color-scheme: dark) {
  html:not([data-theme="light"]) {
    color-scheme: dark;
  }
}
```

There is one executable base selector per `base.css` copy.

## Q3 - Explicit Auto Equivalence

Answer:

Yes, explicit auto can preserve current behavior if CSS keeps both absent and
auto selectors.

Recommended token selector:

```css
@media (prefers-color-scheme: dark) {
  :root:not([data-theme]),
  :root[data-theme="auto"] {
    ...
  }
}
```

Recommended base selector:

```css
@media (prefers-color-scheme: dark) {
  html:not([data-theme]),
  html[data-theme="auto"] {
    color-scheme: dark;
  }
}
```

Equivalence matrix:

| Root state | Current behavior | Proposed behavior | Equivalent |
|---|---|---|---|
| no attribute | OS dark applies | OS dark applies | yes |
| `data-theme="auto"` | OS dark applies | OS dark applies | yes |
| `data-theme="light"` | OS dark suppressed | OS dark suppressed | yes |
| `data-theme="dark"` | explicit dark applies | explicit dark applies | yes |

Keeping the absent selector is important for compatibility with cached pages,
older mirrors, or temporary pre-JS states.

## Q4 - Future Variant Safety

Answer:

Current selectors absorb any value except `light` into OS-dark auto blocks:

```css
:root:not([data-theme="light"])
html:not([data-theme="light"])
```

That means future values such as `sepia`, `dim`, or `high-contrast` would match
the OS-dark auto branch. The proposed absent + auto selectors stop that
absorption:

```css
:root:not([data-theme]),
:root[data-theme="auto"]

html:not([data-theme]),
html[data-theme="auto"]
```

Future variant safety is therefore the main reason to prefer Route C/D over
Route B.

## Q5 - Pilot PHP Root Default

Answer:

Pilot `functions.php` currently has no `language_attributes` filter. Adding a
small Pilot-only filter is available, but it must be framed carefully.

Recommended constraint:

```txt
Pilot may default its own root output to data-theme="auto".
Future distributables do not inherit that decision automatically.
```

Q5.1 - Would a future distributable inherit/copy the same route?

Not automatically. v3.6.20 and the promoted Pilot/distributable memory say
Pilot is a probe, not a distributable. If a future skeleton copies the Pilot
route, that must be an explicit skeleton/product-context decision.

Q5.2 - If inherit/copy is required, is it a skeleton prerequisite?

Yes. The future distributable root default belongs to the distributable
skeleton prerequisites. v3.6.22 can provide Pilot evidence without locking the
future product.

Q5.3 - WordPress compatibility / editor iframe impact?

The filter must be validated. Phase 1 found no current root-attribute hook, so
Phase 2 should use a minimal additive filter and Phase 3 should check:

```txt
front-end root includes data-theme="auto"
editor canvas remains valid / no block validation regression
php -l passes
computed validation remains 1.000
```

Q5.4 - Can PHP route out while JS + CSS proceed?

Yes. If review rejects PHP in Phase 2, Route C remains safe and useful. It would
leave PHP default routed to a future skeleton/product-context cycle.

Phase 1 recommendation:

```txt
Route D is acceptable if Phase 2 explicitly labels the PHP filter Pilot-only.
```

## Q6 - Inline Head-Script Note

Answer:

Defer.

BACKLOG #22's older scope mentions a small inline head script to avoid FOUC.
That is not required to make the root state explicit. It also introduces
product-policy questions:

```txt
inline script policy
FOUC budget
CSP / WordPress.org posture
future distributable first-paint contract
BACKLOG #21 theme-mode runtime boundary
```

Phase 1 recommends Route E:

```txt
Do not implement inline head script in v3.6.22.
Record it as product-context / BACKLOG #21-adjacent follow-on if still desired.
```

## Q7 - Generated Mirror Route

Answer:

Recommend M2: edit source + mirror in one reviewed pass.

Reason:

- lab `theme.js` and `styleguide/scripts/theme.js` are byte-identical.
- lab `style-guide.js` and `styleguide/scripts/style-guide.js` are
  byte-identical.
- lab / Pilot / styleguide CSS copies are byte-identical.
- The expected changes are small and mechanical.
- Running publish tooling may create broader generated churn that is not needed
  for this specific 5 JS + 6 CSS + 1 PHP change.

Q7.1 - If M1 source-only edit is selected, when should publish tooling run?

It would need a later publish or mirror-sync cycle. Phase 1 does not recommend
M1 because it would intentionally leave root-state semantics stale in the public
styleguide mirror.

Q7.2 - If M2 selected, are mirrors byte-identical?

Yes. Hash evidence:

```txt
theme.js source/mirror        identical
style-guide.js source/mirror  identical
tokens.sys.dark.css 3-copy    identical
base.css 3-copy               identical
```

Q7.3 - If M3 publish run selected, what churn is expected?

Unknown without running the generator. Given recent cycles, validator-generated
reports can churn after `npm test`, and publish tooling could touch unrelated
styleguide mirror files. M3 is not necessary for the bounded implementation.

Q7.4 - If M4 selected, what proves mirror unaffected?

Nothing. The mirror includes the same root-state JS and CSS; it is affected. M4
is therefore not evidence-backed for v3.6.22.

## Q8 - Verification Route

Required if Phase 2 implements Route D:

```txt
php -l products\reference-implementations\axismundi-pilot\functions.php
npm test
python tools\generators\build_pilot_specimen_wall.py
npm run validate:specimen-wall
npm run validate:computed
git diff --check
```

Browser / runtime verification should include:

| Path | Expected evidence |
|---|---|
| lab styleguide catalog | `auto` click leaves `<html data-theme="auto">`; light/dark explicit; selected state correct |
| lab module pattern | `[data-theme-set]` auto/light/dark root attribute and `aria-checked` correct |
| generated styleguide | same as lab styleguide, because mirror is edited under M2 |
| Pilot front-end | initial root has `data-theme="auto"` if PHP route selected; clicks preserve auto/light/dark |
| editor canvas | no block validation or computed validation regression; root behavior observed if accessible |

No editor preview UI is needed or allowed.

## Q9 - Lock Impact

Lock 1:

- Preserved. No `wp-custom`, `settings.custom.axismundi.*`, or theme.json route
  changes are needed.

Lock 2:

- Preserved. CSS changes selectors only; token values remain md-sys mappings to
  md-ref values.

Lock 3:

- Preserved. Core/button semantic route is not touched.

Lock 4:

- Preserved. The root-state mismatch is routed through theme-switcher contract
  and #22 implementation, not opportunistic edits.

Lock 5:

- Preserved so far. Phase 1 diagnostic preceded any implementation.

Pilot/distributable boundary:

- Preserved if PHP filter is explicitly documented as Pilot-only evidence.
- Future distributable root default remains a skeleton/product-context decision.

## Q10 - BACKLOG #22 Close Criteria

If Phase 2 implements Route D and Phase 3 verification passes, BACKLOG #22 can
close for current Axismundi surfaces, with a follow-on note for inline
head-script / product first-paint policy.

Close criteria:

```txt
1. JS writes explicit data-theme="auto" in all 5 current runtime surfaces.
2. CSS dark-auto branch matches absent + explicit auto only across 3-copy
   tokens/base surfaces.
3. Pilot root default emits data-theme="auto" without deciding future
   distributable behavior.
4. Full validation passes.
5. Browser/runtime verification confirms auto/light/dark click paths and Pilot
   initial root default.
6. BACKLOG.md records #22 closed or closed-current-surface with inline script
   routed separately.
```

If PHP is rejected, Route C can still narrow #22 but should not fully close it.

## Phase 2 Route Recommendation

Recommended route:

```txt
Route D + Route E + M2
```

Meaning:

```txt
Route D:
  JS + CSS + Pilot PHP default.

Route E:
  Defer inline head script out of this cycle.

M2:
  Edit source + generated styleguide mirror in one reviewed pass.
```

Expected Phase 2 implementation surface:

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

Route D caveat:

```txt
The PHP filter must be Pilot-only and must not claim future distributable
authority.
```

## M6 / Memory Candidate Status

M6 - BACKLOG #22 explicit auto-state prerequisites:

```txt
status: active candidate
```

Phase 1 used the v3.6.21 M6 prerequisite shape:

```txt
JS mutation + CSS cascade review + PHP root default + multi-path verification
```

Promotion should wait until Phase 5. If Route D closes cleanly, M6 becomes a
strong promotion candidate. If Phase 2 narrows instead of closes, keep M6 as
watch.

M7 - Tracked copy mirror handling framework:

```txt
status: watch
```

The M1-M4 mirror framework was useful in Phase 1 and selected M2. Consider
promotion only if Phase 2/3 proves it reusable and future cycles need the same
decision shape.

M8 - Maintenance commit vs cycle commit separation:

```txt
status: watch
```

No new evidence beyond the already-clean `de106ab` maintenance separation.

## Phase 1 Validation

Commands / evidence:

```txt
git status --short --branch
rg removeAttribute / setAttribute / data-theme / localStorage / language_attributes
rg :not([data-theme="light"]) / data-theme="dark" / data-theme="light"
Get-FileHash tracked JS/CSS copies
git diff --check
```

Results:

```txt
implementation changes: 0
generated mirror changes: 0
BACKLOG changes: 0
git diff --check: PASS
```

## Review Request

Opus review should answer:

```txt
P1: Any blocker to Route D + E + M2?
P2: Is Pilot PHP language_attributes safe to treat as Pilot-only evidence?
P3: Is BACKLOG #22 close criteria sufficient if Phase 2/3 pass?
```

Phase 2 must wait for Opus Phase 1 verdict and explicit user Phase 2 execution
GO.
