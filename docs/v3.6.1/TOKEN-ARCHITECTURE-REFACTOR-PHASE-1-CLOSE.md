# v3.6.1 — Token Architecture Refactor — Phase 1 Close

Date: 2026-05-19

## Verdict

Phase 1 is implementation-complete and ready for full close review.

## Implemented Chain

```txt
md-ref
  -> md-sys light/dark mappings
  -> wp-preset / wp-custom bridge files
  -> theme.json palette + settings.custom.axismundi.*
  -> Pilot runtime + editor-facing contract
```

Strict M3 mode remains one-way downstream. WordPress is a projection target,
not the source of the M3 token graph.

## Phase Results

```txt
Phase 1A: token layer extraction
  tokens.ref.css / tokens.sys.light.css / tokens.sys.dark.css / tokens.comp.css
  tokens.css retained as empty compatibility shim.

Phase 1B: loading and asset bridge update
  Required order implemented:
    tokens.ref.css
    tokens.sys.light.css
    tokens.comp.css
    tokens.sys.dark.css
    wp-preset.bridge.css
    wp-custom.bridge.css
    tokens.css

Phase 1C: WordPress bridge layers
  wp-preset.bridge.css and wp-custom.bridge.css added.
  Axis F validates single-var downstream bridge values and broken refs.

Phase 1D: theme.json settings.custom.axismundi.*
  26 downstream-only custom leaves added.
  Axis G validates allowed var shape and upstream token existence.

Phase 1E: dark mode infrastructure
  Pilot Light / Dark / Auto switcher wired.
  Computed validator now checks forced light/dark matrix plus real click path.

Phase 1F: policy and backlog close
  FEEDBACK-AND-STRATEGY.md §1 refined.
  BACKLOG #20 closed.
```

## Validation

```txt
php -l products/reference-implementations/axismundi-pilot/functions.php
  PASS

npm test
  PASS
  Axis E tokens:  1.000
  Axis F bridge:  1.000
  Axis G custom:  1.000
  Overall:        1.000 PASS

npm run validate:computed
  PASS
  Matrix:
    front-light
    front-dark
    styleguide-blocks-light
    styleguide-blocks-dark
  Real Pilot click path:
    dark  -> data-theme="dark"  -> #141218 body background
    light -> data-theme="light" -> #FEF7FF body background

git diff --check
  PASS
```

## BACKLOG #20 Close Evidence

```txt
settings.color.custom=false remains confirmed:
  products/reference-implementations/axismundi-pilot/theme.json

wp-preset/wp-custom bridge files exist:
  products/reference-implementations/axismundi-lab/stylesheets/
  products/reference-implementations/axismundi-pilot/assets/styles/
  styleguide/stylesheets/

Dark mode sys-layer swap validates in Pilot:
  tools/validators/validate_pilot_computed_styles.js
  tmp/phase3-computed-audit/computed-style-audit.json

theme.json settings.custom.axismundi.* obeys downstream-only lock:
  tools/validators/validate_theme_pilot.py Axis G
```

## Non-Blocking Future Candidates

```txt
Axis H candidate:
  Validate wp-custom.bridge.css <-> theme.json settings.custom.axismundi.*
  correspondence after camelCase/kebab-case normalization.

Auto-mode matrix candidate:
  Extend computed validation with emulateMedia({ colorScheme }) for
  data-theme auto / absent attribute behavior.
```
