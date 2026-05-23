# Progress - WordPress Mapping (v3.6.14 Phase 2)

Component: Progress #31

## Current WordPress Boundary

WordPress core block markup does not expose a general theme-owned Progress
block surface in the current Pilot scope.

Progress remains a lab primitive and plugin/custom-block candidate when async
or completion state needs to be rendered by product code.

## Theme Mapping

No WordPress block bridge is added in v3.6.14.

Allowed future mappings:

```txt
plugin async task -> Progress visual primitive
custom block progress state -> Progress visual primitive
editor async state -> WordPress component / plugin territory
```

## Non-Goals

- Do not add a custom block.
- Do not inject progress indicators into core block markup.
- Do not edit Pilot theme files.
- Do not edit WordPress block bridge CSS.

## Verdict

Progress has no theme-owned WordPress mapping in this cycle.
