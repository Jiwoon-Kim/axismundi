# v3.6.5 - WP Block Bridge Editor Token Parity - Phase 5 Close

Date: 2026-05-21

Phase: 5 - Close

## Verdict

v3.6.5 is closed as a BACKLOG #41 editor token enqueue parity slice.

The cycle fixed the editor canvas md-sys light token gap surfaced by v3.6.4:

```txt
Before:
  editor iframe --md-sys-color-on-surface:        empty
  editor iframe --md-sys-color-outline-variant:   empty
  editor pullquote divider:                       missing

After:
  editor iframe --md-sys-color-on-surface:        #1D1B20
  editor iframe --md-sys-color-outline-variant:   #CAC4D0
  editor pullquote divider:                       1px solid rgb(202, 196, 208)
```

No custom blocks, plugin behavior, `theme.json`, `functions.php`, fixture
repair, TT5-derived implementation, ripple runtime change, or broader editor
state parity implementation was introduced.

## Commits

```txt
5a317b7  Add v3.6.5 editor token parity phase 0 plan
ee021bf  Amend v3.6.5 editor token parity plan
91a34e9  Document v3.6.5 editor token root cause
e2ead24  Add TT5 reference note to v3.6.5 inventory
d23050b  Fix editor light sys token stylesheet
27bbbb2  Record v3.6.5 editor token visual QA
```

## Documents

```txt
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-0-PLAN.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-1-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-2-REPORT.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-3-VISUAL-QA.md
docs/v3.6.5/WP-BLOCK-BRIDGE-EDITOR-TOKEN-PARITY-PHASE-5-CLOSE.md
```

## Closed

BACKLOG #41 item closed by v3.6.5:

```txt
editor token enqueue parity:
  v3.6.4 Phase 3 found that the editor iframe did not expose
  --md-sys-color-on-surface or --md-sys-color-outline-variant.

  v3.6.5 diagnosed this as a malformed trailing comment in
  tokens.sys.light.css that WordPress 7.0's editor-style transform turned into
  an empty inline style.

  v3.6.5 repaired the malformed comment across the lab, Pilot, and styleguide
  tracked copies, restoring editor md-sys light token resolution.
```

## Root Cause

Phase 1 selected root cause bucket:

```txt
E. other, with evidence
```

The malformed token file ending was:

```css
}

/* ------------------------------------------------------------
```

WordPress editor iframe evidence before the fix:

```txt
tokens.ref:       length 6708, cssRules > 0
tokens.sys.light: length 0,    cssRules 0
tokens.comp:      length 23539, cssRules > 0
tokens.sys.dark:  length 8631, cssRules > 0
```

The front end tolerated the malformed trailing comment and preserved the
completed `:root` rule. WordPress 7.0's editor-style transform did not, so the
editor iframe lost md-sys light defaults.

## Patch

The Phase 2 patch closed the trailing comment:

```css
}

/* ------------------------------------------------------------ */
```

Patched files:

```txt
products/reference-implementations/axismundi-lab/stylesheets/tokens.sys.light.css
products/reference-implementations/axismundi-pilot/assets/styles/tokens.sys.light.css
styleguide/stylesheets/tokens.sys.light.css
```

Three-copy lockstep after the fix:

```txt
SHA256:
  7F837F7C7F7104C19ABC21563A8BFE2D05DB227858791B3CD41D25E2DCC26E98

Comment count:
  /* = 47
  */ = 47

EOF:
  " */\n"
```

## Visual QA

Editor canvas:

```txt
--md-sys-color-on-surface:         #1D1B20
--md-sys-color-outline-variant:    #CAC4D0
--md-sys-color-on-surface-variant: #49454F

pullquote border: 1px solid rgb(202, 196, 208)
pullquote color:  rgb(29, 27, 32)
pullquote cite:   rgb(73, 69, 79)
```

Front-end light mode:

```txt
--md-sys-color-on-surface:         #1D1B20
--md-sys-color-outline-variant:    #CAC4D0
--md-sys-color-on-surface-variant: #49454F

pullquote border: 1px solid rgb(202, 196, 208)
pullquote color:  rgb(29, 27, 32)
pullquote cite:   rgb(73, 69, 79)
```

Front-end dark mode:

```txt
--md-sys-color-on-surface:         #E6E0E9
--md-sys-color-outline-variant:    #49454F
--md-sys-color-on-surface-variant: #CAC4D0

pullquote border: 1px solid rgb(73, 69, 79)
pullquote color:  rgb(230, 224, 233)
pullquote cite:   rgb(202, 196, 208)
```

Button regression smoke:

```txt
editor href:                 #button-fill
editor text-decoration-line: none
editor user-select:          none

front-end href:                 #button-fill
front-end text-decoration-line: none
front-end user-select:          none
```

## Lock Compliance

```txt
Lock 1 - wp-custom downstream-only:
  preserved; no wp-custom source values changed.

Lock 2 - md-sys color maps to md-ref:
  preserved; the fix restored existing md-sys -> md-ref declarations without
  adding literal color values.

Lock 3 - core/button semantic route before visual cleanup:
  preserved; button work was regression smoke only.

Lock 4 - semantic mismatch handling rule:
  preserved; no semantic decisions reopened.
```

## Routed Forward

BACKLOG #41 remains open, but its residual scope is narrower:

```txt
Still open under #41:
  ripple bridge graduation
  broader editor-canvas state parity for hover/focus/pressed/disabled/selected
```

BACKLOG #44 remains open for editor compatibility:

```txt
editor-invalid-content / editor-valid fixture work:
  Phase 3 still observed the pre-existing
  "Block contains unexpected or invalid content" warning.

  invalidContentWarningCount: 57
```

TT5 reference remains future structural guidance only:

```txt
TT5 may inform future core block selector/theme.json coverage.
TT5 was not copied or used as a v3.6.5 implementation source.
```

## Methodology Finding

v3.6.5 demonstrates a diagnostic-first cycle shape:

```txt
When the failure mode is unknown, Phase 1 classifies root cause and selects an
implementation route before any patch.
```

Do not promote this to a new lock yet. Keep it as a methodology finding for
now. If future ripple/editor-state cycles reuse the same pattern, reconsider
whether it deserves lock status.

## Phase Cadence Note

v3.6.x uses:

```txt
Phase 0: plan
Phase 1: first implementation / inventory
Phase 2: patch / bridge
Phase 3: visual QA or semantic QA
Phase 5: close
```

Phase 4 is intentionally unused in this cadence.

## Validation

Final close validation:

```txt
wp-env run cli wp core version: 7.0
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS
npm run validate:computed: PASS
git diff --check: PASS
```
