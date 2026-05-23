# Loading - WordPress Mapping (v3.6.14 Phase 2)

Component: Loading #30

## Current WordPress Boundary

WordPress core block markup does not expose a general theme-owned Loading block
surface in the current Pilot scope.

Loading remains a lab primitive and plugin/custom-block candidate when async
state needs to be rendered by product code.

## Theme Mapping

No WordPress block bridge is added in v3.6.14.

Allowed future mappings:

```txt
plugin async action -> Loading visual primitive
custom block pending state -> Loading visual primitive
editor control state -> WordPress component / plugin territory
```

## Non-Goals

- Do not add a custom block.
- Do not inject loading indicators into core block markup.
- Do not edit Pilot theme files.
- Do not edit WordPress block bridge CSS.

## Verdict

Loading has no theme-owned WordPress mapping in this cycle.
