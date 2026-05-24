# v3.6.21 Theme Switcher Contract - Phase 2 Decision

## Verdict

v3.6.21 adopts an A-only no-code decision:

```txt
Route A = contract decision report
Route B = not executed
Route C = not executed
Route D = deferred
Route E = already bounded by BACKLOG #21
```

No implementation, runtime, template, generated mirror, D-layer, archive,
asset, or root meta-doc files change in Phase 2.

## Decision Summary

1. `.sg-theme` is the lab / styleguide / module selector contract.
2. `.ax-theme-switcher` is the Pilot / product-facing selector contract.
3. Both selectors remain accepted by the shared lab/styleguide `theme.js`
   runtime; no selector runtime bug is currently present.
4. `data-theme-button` remains styleguide-local.
5. `data-theme-set` remains the production/module/Pilot runtime control
   attribute.
6. Storage keys remain owner-specific; do not unify them in this cycle.
7. Visitor preference and editor preview remain separated.
8. BACKLOG #22 remains open, narrowed to explicit root-state implementation.
9. BACKLOG #21 continues to own HCT, editor preview UI, PluginSidebar, Global
   Styles synchronization, and custom color regeneration.
10. `style-guide-blocks.html` / `style-guide-prose.html` switcher shell
    consistency is routed to the future Core Block Catalog split cycle.

## Selector Contract

Formal selector ownership:

| Selector | Owner | Current surfaces | Contract |
|---|---|---|---|
| `.sg-theme` | lab / styleguide / module surfaces | `style-guide.html`, generated `styleguide/index.html`, lab module pattern pages, archive style-guide | specimen, catalog, and module-runtime theme controls |
| `.ax-theme-switcher` | Pilot / future product-facing surfaces | Pilot `parts/header.html`, archive front-page prototype | product-facing front-end theme control |

Rationale:

- `.sg-theme` is active in lab/styleguide/module surfaces.
- `.ax-theme-switcher` is active in the Pilot header.
- The current shared `theme.js` already accepts both selectors:

```js
document.querySelectorAll(".sg-theme, .ax-theme-switcher")
```

Therefore the correct contract is dual ownership, not unification.

## Selector Lineage

The Phase 1 inventory shows the selectors belong to different architectural
eras and surfaces:

```txt
.sg-theme
  = prototype-era styleguide selector
  = current lab/styleguide/module selector

.ax-theme-switcher
  = archive front-page selector
  = current Pilot product-facing selector
```

The old `theme.js` wording that marks `.ax-theme-switcher` as archive-only is
now stale as contract language, but the runtime behavior is already tolerant.

## Attribute Vocabulary

Formal attribute ownership:

| Attribute | Owner | Meaning |
|---|---|---|
| `data-theme-button` | styleguide-local runtime | catalog switcher handled by `style-guide.js` |
| `data-theme-set` | production/module/Pilot runtime | module, prototype, and product-facing switcher handled by `theme.js` / Pilot bridge |

Rationale:

The two vocabularies prevent styleguide-local state from being clobbered by the
production/module runtime. This is not drift to collapse in v3.6.21.

## Storage Key Contract

Current keys:

| Key | Owner | Current use |
|---|---|---|
| `ax-theme` | lab `theme.js` | production/module prototype preference |
| `axismundi.theme` | lab/styleguide `style-guide.js` | catalog-local preference |
| `axismundi-pilot-theme` | Pilot bridge | Pilot front-end visitor preference |

Decision:

```txt
Do not unify storage keys in v3.6.21.
```

Future distributable warning:

The existing memory guardrail used `axismundi.theme` as an example visitor key,
but Phase 1 showed that `axismundi.theme` is already occupied by the lab
styleguide catalog. A future distributable must choose a non-conflicting visitor
key in its own skeleton / product-context cycle.

Candidate future visitor keys:

```txt
axismundi-theme
axismundi.visitor.theme
<slug>.theme
```

No key is selected in v3.6.21.

## Visitor / Editor Separation

Decision:

```txt
Front-end visitor preference != editor preview mode.
```

Allowed in theme-only mode:

- static `light` / `dark` / `auto`;
- visitor localStorage preference;
- CSS sys-layer dark-mode swap;
- explicit selector and root-state contract.

Not allowed in this cycle:

- syncing visitor localStorage with editor preview state;
- PluginSidebar / InspectorControls;
- HCT or Material Color Utilities;
- Global Styles synchronization;
- custom palette regeneration.

These remain BACKLOG #21 / Interpreter Plugin territory.

## BACKLOG #22 Narrowing

BACKLOG #22 remains open.

It is narrowed by v3.6.21 to:

```txt
explicit data-theme="auto" root-state implementation
```

Required scope to close #22 later:

1. JS attribute mutation across current switcher runtimes:
   - lab `scripts/theme.js`;
   - lab `scripts/style-guide.js`;
   - Pilot `assets/scripts/pilot-block-bridge.js`;
   - generated `styleguide/scripts/theme.js`;
   - generated `styleguide/scripts/style-guide.js`.
2. CSS selector cascade review:
   - `tokens.sys.dark.css`;
   - `base.css`;
   - future variant safety for values beyond `light` / `dark` / `auto`.
3. PHP root default:
   - likely `language_attributes` filter in the eventual product theme.
4. Verification:
   - front-end visitor click path;
   - styleguide catalog click path;
   - Pilot front-end click path;
   - editor canvas behavior if the implementation reaches WordPress theme
     runtime;
   - generated mirror restore / publish tooling posture.

This can be a separate narrow implementation cycle, but it is not a one-line JS
cleanup.

## Route B Status

Route B was available as narrow docs/comment hygiene, but the user selected:

```txt
v3.6.21 Phase 2 GO + A only
```

Therefore:

- no `theme.js` comment edits;
- no generated mirror comment edits;
- no publish tooling run;
- no implementation file edits.

If Route B is reopened later, it must choose one of:

| Option | Meaning | Tradeoff |
|---|---|---|
| B1 | edit lab source comments only; mirror updates during next publish cycle | preserves generated-mirror policy most strictly |
| B2 | edit lab source + generated mirror comments in one reviewed pass | immediate sync, but treats generated mirror as an explicit tracked copy |

v3.6.21 makes no choice because Route B is not executed.

## `style-guide-blocks.html` / `style-guide-prose.html`

Phase 1 found no active theme-switcher markup in:

```txt
products/reference-implementations/axismundi-lab/style-guide-blocks.html
products/reference-implementations/axismundi-lab/style-guide-prose.html
```

Forward route:

```txt
Future Core Block Catalog split cycle should decide whether these pages get
styleguide shell parity and, if so, which selector surface they use.
```

Default expectation:

- if treated as catalog/styleguide surfaces, use `.sg-theme`;
- if later converted into product-facing surfaces, re-evaluate under the
  Pilot/distributable product contract.

## Lock Compliance

Lock 1:

- Preserved. No `settings.custom.axismundi.*` entries were changed.
- No `wp-custom` route was reopened.

Lock 2:

- Preserved. No `--md-sys-color-*` mapping was changed.
- Explicit auto-state remains a future root-state implementation, not a token
  source reversal.

Lock 3:

- Preserved. Core/button semantic route is not reopened.

Lock 4:

- Preserved. Selector and root-state mismatch are routed as contract policy,
  not opportunistic file edits.

Lock 5:

- Preserved. Phase 1 diagnostic preceded Phase 2 decision.
- v3.6.21 remains a no-code decision variant unless a later phase explicitly
  changes route after review and user GO.

Close-time count if no implementation is added later:

```txt
overall Lock 5 self-application: 11th
implementation-cycle count:     6th, unchanged from v3.6.20
```

## Non-Goals Confirmed

v3.6.21 Phase 2 does not:

- implement BACKLOG #21;
- implement BACKLOG #22;
- edit `theme.js`;
- edit `style-guide.js`;
- edit Pilot bridge JS;
- edit CSS;
- edit `theme.json`;
- edit `functions.php`;
- edit templates / parts / patterns;
- edit generated `styleguide/`;
- edit archive files;
- create a distributable skeleton;
- generate release-seal derivatives;
- update root meta-docs;
- touch D-layer binding files.

## Memory Candidate Notes

Candidate M4:

```txt
theme-switcher attribute vocabulary and storage keys are owner-separated:
data-theme-set = production/module/Pilot;
data-theme-button = styleguide-local;
storage keys are not globally unified.
```

Status: WATCH. Promote after Phase 5 if this contract remains stable.

Candidate M5:

```txt
theme-switcher selector dual ownership:
.sg-theme = lab/styleguide/module;
.ax-theme-switcher = Pilot/product-facing.
```

Status: PROMOTE candidate after Phase 5, because Phase 2 formalizes it.

M3:

```txt
Boundary context != product context.
```

Status: still WATCH. v3.6.21 uses the Pilot/product boundary, but does not
repeat the boundary/product-context distinction strongly enough to promote.

## Phase 3 Recommendation

Phase 3 should be light verification:

```txt
git status --short --branch
git diff --check
```

Full runtime validation is not required for A-only no-code Phase 2, because no
implementation files changed. If Phase 3 chooses evidence-shape parity with
v3.6.17-v3.6.20, it may run the full suite, but generated artifact churn must be
restored before close.

## Review Request

```txt
P1: Any blocker to A-only no-code Phase 2 close?
P2: Is dual selector ownership now sufficiently formalized?
P3: Is BACKLOG #22 narrowing specific enough for a future implementation cycle?
```

