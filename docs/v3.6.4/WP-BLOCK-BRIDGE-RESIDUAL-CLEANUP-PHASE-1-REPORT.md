# v3.6.4 - WP Block Bridge Residual Cleanup - Phase 1 Report

Date: 2026-05-20

Phase: 1 - Button Mechanical Cleanup

## Verdict

Phase 1 is complete.

The button semantic route from v3.6.3 remains unchanged: `core/button` anchors
with `href` are navigation links receiving an M3 button visual bridge. This
phase only removes mechanical link-affordance leakage from
`.wp-block-button__link`.

## Changed Files

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
docs/v3.6.4/WP-BLOCK-BRIDGE-RESIDUAL-CLEANUP-PHASE-1-REPORT.md
```

## Patch

Added a post-content scoped button-link cleanup before the existing button
state bridge:

```css
.wp-block-post-content .wp-block-button__link {
  text-decoration: none;
  user-select: none;
  -webkit-user-select: none;
}
```

Reason:

```txt
The post-content link bridge sets ordinary links to underline. core/button
anchors need to remain anchors semantically, but they should not inherit prose
link underline or selectable text behavior when rendered as M3 button surfaces.
```

## Before Evidence

Computed probe on the specimen wall:

```txt
URL:
  http://localhost:8888/?pagename=axismundi-core-block-specimen-wall

Variants checked:
  button-fill
  button-outline
  button-tonal
  button-elevated
  button-text
```

Before:

```txt
All five variants:
  href:                 present
  text-decoration-line: underline
  text-decoration-style: solid
  text-decoration-thickness: 1px
  user-select:          auto
  cursor:               pointer
  ::before opacity:     0

Focused filled variant:
  outline-width:  2px
  outline-style:  solid
  outline-color:  rgb(103, 80, 164)
  outline-offset: 2px
```

## After Evidence

After patch:

```txt
All five variants:
  href:                 present
  text-decoration-line: none
  user-select:          none
  cursor:               pointer
  ::before opacity:     0

Focused filled variant:
  outline-width:  2px
  outline-style:  solid
  outline-color:  rgb(103, 80, 164)
  outline-offset: 2px
```

## Lock 3 Check

```txt
Semantic route reopened: no
Markup changed:          no
href behavior changed:   no
role added/changed:      no
JS behavior added:       no
Custom block work:       no
```

The cleanup is allowed by Lock 3 because the semantic route was named in
v3.6.3 before this visual affordance patch.

## Source / Asset Mirror

The same CSS was applied to:

```txt
products/reference-implementations/axismundi-pilot/bridge/pilot-block-bridge.css
products/reference-implementations/axismundi-pilot/assets/styles/pilot-block-bridge.css
```

The files remain byte-identical for this bridge.

## Validation

```txt
diff source vs asset mirror: PASS
python tools\generators\build_pilot_specimen_wall.py: PASS
npm run validate:specimen-wall: PASS
php -l products/reference-implementations/axismundi-pilot/functions.php: PASS
npm test: PASS (Axis A-G all 1.000)
npm run validate:computed: PASS
git diff --check: PASS
```

## Next

Proceed to Phase 1 review, then Phase 2 quote/pullquote distinct-surface
cleanup after GO.
