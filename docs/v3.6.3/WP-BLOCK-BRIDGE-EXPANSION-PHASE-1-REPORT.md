# v3.6.3 — WP Block Bridge Expansion — Phase 1 Report

Date: 2026-05-20

## Verdict

Phase 1 reset patch is complete for `table-footer-contrast`.

The confirmed WordPress core/table footer leak is reset without changing the
broader table bridge contract. No semantic-decision, bridge-parity, custom
block, fixture-expansion, or editor-compatibility work was performed in this
phase.

## Scope

```txt
Phase:
  Phase 1 — Reset Patch

Finding:
  table-footer-contrast

Input:
  v3.6.2 Phase 2 classification:
    table-footer -> reset

  v3.6.2 Phase 3 visual QA:
    tfoot border-top: 3px solid currentColor in light/dark
```

## Changed

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

Patch:

```css
.wp-block-post-content .wp-block-table tfoot {
  border-block-start: 0 !important;
}
```

This is a reset-only patch. It removes the native `tfoot` block-start border
that survived the existing table bridge. It does not choose a new footer
surface treatment.

## Computed Evidence

Source:

```txt
tmp/phase1-specimen-wall/specimen-wall-render-gate.json
```

After patch:

```txt
table-footer:
  tagName:             tfoot
  border-top-width:   0px
  border-top-style:   none
  border-top-color:   rgb(29, 27, 32)
  border-bottom-width: 0px
  border-bottom-style: none
```

Regression spot check:

```txt
table-default figure:
  border-top:    1px solid rgb(202, 196, 208)
  border-bottom: 1px solid rgb(202, 196, 208)

table-stripes figure:
  border-top:    1px solid rgb(202, 196, 208)
  border-bottom: 1px solid rgb(202, 196, 208)
```

The prior 3px currentColor footer rule is gone, while default/stripes wrapper
containment remains on the M3 outline-variant tone.

## Validation

```powershell
wp-env start
  PASS

python tools\generators\build_pilot_specimen_wall.py
  PASS — updated specimen wall page 29

npm run validate:specimen-wall
  PASS

php -l products\reference-implementations\axismundi-pilot\functions.php
  PASS

npm test
  PASS — overall 1.000; Axis E/F/G all 1.000

npm run validate:computed
  PASS

git diff --check
  PASS
```

## Non-Goals Confirmed

```txt
- No custom blocks registered.
- No core/button semantic fix attempted.
- No core/quote or core/pullquote semantic fix attempted.
- No search/code/separator bridge patch attempted.
- No specimen fixture expansion.
- No theme.json edit.
- No lab baseline or published styleguide edit.
```

## Next Route

Phase 2 may begin after review/approval of this Phase 1 patch:

```txt
Bridge lane count: 3
  1. search-styleguide-delta
  2. code-long-line-overflow
  3. separator-variant-visibility
```

Per the Phase 0 review follow-up, separator work must first inspect
`functions.php` to confirm which core/separator style variations are registered
before deciding whether Phase 2 remains CSS-only or needs an explicit
registration scope decision.
