# v3.6.21 Theme Switcher Contract - Phase 5 Close

## Verdict

v3.6.21 is closed as an A-only no-code Theme Switcher Contract cycle.

Closed decision:

```txt
.sg-theme           = lab / styleguide / module selector contract
.ax-theme-switcher  = Pilot / future product-facing selector contract
data-theme-button   = styleguide-local attribute vocabulary
data-theme-set      = production / module / Pilot runtime attribute vocabulary
storage keys        = owner-specific, not globally unified
BACKLOG #22         = open, narrowed to explicit root-state implementation
BACKLOG #21         = plugin territory for HCT / editor UI / Global Styles sync
```

No implementation files changed in v3.6.21.

## Documents

Cycle docs:

```txt
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-0-PLAN.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-1-REPORT.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-2-DECISION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-3-VERIFICATION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
```

Cross-cutting docs:

```txt
None added.
```

Root meta-docs:

```txt
NEXT-SESSION.md
CURRENT-STATE.md
CHANGELOG.md
ROADMAP.md
```

were intentionally not updated in this strict no-code close. Their staleness is
now a four-cycle maintenance issue from v3.6.18 through v3.6.21 and remains
routed forward as a separate maintenance commit.

## Closed Decisions

1. `.sg-theme` and `.ax-theme-switcher` are not competing names for one
   contract. They are two selector contracts with different owners.
2. `.sg-theme` is valid and active for lab/styleguide/module surfaces.
3. `.ax-theme-switcher` is valid and active for Pilot / future product-facing
   surfaces.
4. Current runtime already tolerates both selectors where shared runtime is used;
   the drift was contract wording, not a click-path defect.
5. `data-theme-button` remains styleguide-local.
6. `data-theme-set` remains production/module/Pilot runtime vocabulary.
7. Storage keys remain owner-specific:
   - `ax-theme` = lab module/prototype runtime;
   - `axismundi.theme` = lab/styleguide catalog-local runtime;
   - `axismundi-pilot-theme` = Pilot front-end runtime.
8. A future distributable must choose its own visitor storage key during the
   skeleton / product-context cycle.
9. Visitor preference and editor preview remain separated.
10. BACKLOG #22 remains open and narrowed to explicit `data-theme="auto"`
    root-state implementation.
11. BACKLOG #21 remains the owner for HCT, Material Color Utilities,
    PluginSidebar / editor preview UI, Global Styles sync, and custom color
    regeneration.

## Not Closed

v3.6.21 did not:

- edit `theme.js`;
- edit `style-guide.js`;
- edit Pilot bridge JS;
- edit CSS;
- edit `theme.json`;
- edit `functions.php`;
- edit templates / parts / patterns;
- edit generated `styleguide/`;
- edit archive files;
- implement BACKLOG #22;
- implement BACKLOG #21;
- create a distributable skeleton;
- generate release-seal derivatives;
- update root meta-docs.

## Selector Contract

Formal selector ownership:

| Selector | Owner | Current surfaces | Contract |
|---|---|---|---|
| `.sg-theme` | lab / styleguide / module surfaces | `style-guide.html`, generated `styleguide/index.html`, lab module pattern pages, archive style-guide | specimen, catalog, and module-runtime controls |
| `.ax-theme-switcher` | Pilot / future product-facing surfaces | Pilot `parts/header.html`, archive front-page prototype | product-facing front-end controls |

Historical lineage:

```txt
.sg-theme
  -> prototype-era styleguide selector
  -> current lab/styleguide/module selector

.ax-theme-switcher
  -> archive front-page selector
  -> v3.6.0 Pilot header carry-over
  -> v3.6.20 / v3.6.21 active Pilot product-facing surface classification
```

## Attribute / Storage Contract

Formal attribute ownership:

| Attribute | Owner | Meaning |
|---|---|---|
| `data-theme-button` | styleguide-local runtime | catalog switcher handled by `style-guide.js` |
| `data-theme-set` | production/module/Pilot runtime | module, prototype, and product-facing switcher handled by `theme.js` / Pilot bridge |

The attribute split is intentional. It prevents styleguide-local state from
being clobbered by production/module runtime.

Storage keys remain owner-specific:

| Key | Owner | Current use |
|---|---|---|
| `ax-theme` | lab `theme.js` | production/module prototype preference |
| `axismundi.theme` | lab/styleguide `style-guide.js` | catalog-local preference |
| `axismundi-pilot-theme` | Pilot bridge | Pilot front-end visitor preference |

Future distributable note:

```txt
Do not reuse axismundi.theme as the future distributable visitor key.
It is already occupied by the lab/styleguide catalog runtime.
```

Candidate future keys remain undecided:

```txt
axismundi-theme
axismundi.visitor.theme
<slug>.theme
```

## BACKLOG #22 Narrowing

BACKLOG #22 remains open.

v3.6.21 narrows it to:

```txt
explicit data-theme="auto" root-state implementation
```

Required scope for a future close:

1. JS attribute mutation:
   - lab `scripts/theme.js`;
   - lab `scripts/style-guide.js`;
   - Pilot `assets/scripts/pilot-block-bridge.js`;
   - generated `styleguide/scripts/theme.js`;
   - generated `styleguide/scripts/style-guide.js`.
2. CSS selector cascade review:
   - `tokens.sys.dark.css`;
   - `base.css`;
   - future variant safety beyond `light` / `dark` / `auto`.
3. PHP root default:
   - likely `language_attributes` filter in the eventual product theme.
4. Verification:
   - front-end visitor click path;
   - styleguide catalog click path;
   - Pilot front-end click path;
   - editor canvas behavior if the implementation reaches WordPress runtime;
   - generated mirror restore / publish tooling posture.

This is not a one-line JS cleanup.

## Validation

Full 6-suite validation ran in Phase 3 for no-code evidence-shape parity:

| Command | Result |
|---|---|
| `php -l products\reference-implementations\axismundi-pilot\functions.php` | PASS |
| `npm test` | PASS - Overall 1.000, Axis A-G all 1.000 |
| `python tools\generators\build_pilot_specimen_wall.py` | PASS - page 13 / 14 updated |
| `npm run validate:specimen-wall` | PASS |
| `npm run validate:computed` | PASS |
| `git diff --check` | PASS |

Generated artifact restore drill executed:

```txt
bindings/wordpress-material3/binding_legitimacy_audit.json
bindings/wordpress-material3/pilot_validation_report.md
```

Both were restored after `npm test`.

## Lock Compliance

Lock 1:

- Preserved. No `settings.custom.axismundi.*` or `wp-custom` source-route change.

Lock 2:

- Preserved. No `--md-sys-color-*` or `md-ref` mapping change.

Lock 3:

- Preserved. Core/button semantic route not reopened.

Lock 4:

- Preserved. Selector / state mismatch routed through contract decision, not
  opportunistic file edits.

Lock 5:

- Preserved. Phase 1 diagnostic preceded Phase 2 A-only decision, then Phase 3
  verification.

Count chain:

| Cycle | Overall Lock 5 self-application | Implementation-cycle count | Variant |
|---|---:|---:|---|
| v3.6.17 | 7th | 5th | no-code packaging decision |
| v3.6.18 | 8th | 5th | no-code mapping audit decision |
| v3.6.19 | 9th | 6th | narrow docs hygiene |
| v3.6.20 | 10th | 6th | no-code boundary decision |
| v3.6.21 | 11th | 6th | no-code contract decision |

## Phase 4

Phase 4 intentionally unused.

Reason:

```txt
Phase 1 diagnostic and Phase 2 A-only decision found no runtime defect, no
implementation regression, and no deeper architecture audit need.
```

Phase 4 remains a deep-architecture-audit trigger, not a required close step.

Recent intentionally-unused chain:

```txt
v3.6.5
v3.6.6
v3.6.9
v3.6.14
v3.6.16
v3.6.17
v3.6.18
v3.6.19
v3.6.20
v3.6.21
```

## Memory Promotion Notes

M5 - promote candidate:

```txt
theme-switcher selector dual ownership:
.sg-theme = lab/styleguide/module;
.ax-theme-switcher = Pilot/product-facing.
```

Recommendation:

```txt
PROMOTE after v3.6.21 close + push if user triggers "기억해".
```

M4 - promote candidate:

```txt
theme-switcher attribute vocabulary and storage owner separation:
data-theme-set = production/module/Pilot;
data-theme-button = styleguide-local;
storage keys are owner-specific.
```

Recommendation:

```txt
PROMOTE or fold into the existing theme-switcher-separation memory after close.
```

Existing `project-axismundi-theme-switcher-separation` wording correction:

```txt
Do not hard-code axismundi.theme as the generic visitor key.
It is already the lab/styleguide catalog-local key.
Future distributable visitor key must be chosen in the skeleton/product cycle.
```

M6 - watch:

```txt
BACKLOG #22 explicit auto-state close prerequisites:
JS mutation + CSS cascade review + PHP root default + multi-path verification.
```

M3 - watch:

```txt
Boundary context != product context.
```

Keep watch until skeleton bootstrap or release-seal cycle reuses it.

## Routed Forward

Future cycle entry points:

1. Theme Switcher Route B comment hygiene:
   - smallest scope;
   - optional refinement;
   - must decide generated mirror handling B1 vs B2 if executed.
2. BACKLOG #22 explicit auto-state implementation:
   - use v3.6.21 narrowing as source input;
   - use M6 as prerequisite checklist if promoted later.
3. Core Block Catalog 6-category split:
   - `style-guide-blocks.html` / `style-guide-prose.html` currently have no
     active switcher markup;
   - if catalog surfaces gain a switcher, default selector input is `.sg-theme`;
   - if product-facing, re-evaluate under `.ax-theme-switcher`.
4. Distributable skeleton bootstrap:
   - requires user slug / product GO;
   - must decide future visitor storage key;
   - use M1/M2 and v3.6.20 prerequisites.
5. Release-seal derivative generation:
   - still blocked until product context exists.
6. Distributable build-copy pipeline:
   - manual copy vs build-time copy;
   - product-local binary policy;
   - future `docs/ASSET-SURFACE-INDEX.md` row.
7. Webdesign-craftsman workflow ontology.
8. Media catalog implementation.
9. Pixabay video isolation.
10. `ontology-theme-pilot/assets/` modernization or freeze.
11. BACKLOG #21 Interpreter Plugin strategy.
12. BACKLOG #44 specimen coverage.
13. BACKLOG #46 disabled ripple host hygiene.
14. BACKLOG #47 popover provider hygiene.
15. v3.6.15-v3.6.17 diagnostics policy follow-ons:
    - VS Code workspace diagnostics;
    - Edge Tools / webhint policy;
    - no-inline-styles policy;
    - broad compat-api/css policy;
    - button-group `inline-size: fit-content` warning.
16. Root meta-doc maintenance:
    - `NEXT-SESSION.md`;
    - `CURRENT-STATE.md`;
    - `CHANGELOG.md`;
    - `ROADMAP.md`.

Suggested priority order for immediate switcher-related follow-ons:

```txt
1. Route B comment hygiene
2. BACKLOG #22 explicit auto-state implementation
3. Core Block Catalog split shell consistency
4. Distributable skeleton storage key decision
```

## Close Status

v3.6.21 is close-ready pending Opus Phase 5 review and user commit/push GO.

Expected close commit scope:

```txt
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-0-PLAN.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-1-REPORT.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-2-DECISION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-3-VERIFICATION.md
docs/v3.6.21/THEME-SWITCHER-CONTRACT-PHASE-5-CLOSE.md
```

Recommended commit message:

```txt
Close v3.6.21 theme switcher contract
```

