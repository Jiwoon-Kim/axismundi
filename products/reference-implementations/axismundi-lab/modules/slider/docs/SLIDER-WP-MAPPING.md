# Slider - WordPress Mapping (v3.6.14 Phase 2)

Component: Slider #21

## Current WordPress Boundary

WordPress core does not provide a general-purpose `core/slider` block in the
theme surface covered by Axismundi Pilot v0.

Slider remains a lab module and future plugin/custom-block candidate when an
author-facing range input is required.

## Theme Mapping

No WordPress block bridge is added in v3.6.14.

Allowed future mappings:

```txt
custom form block -> Slider visual primitive
plugin settings control -> Slider visual primitive
editor inspector control -> WordPress component / plugin territory
```

## Non-Goals

- Do not add a custom block.
- Do not map arbitrary `<input type="range">` content in post bodies.
- Do not edit Pilot theme files.
- Do not edit WordPress block bridge CSS.

## Verdict

Slider has no theme-owned WordPress mapping in this cycle. It is documented as
a lab primitive for future plugin or custom-block territory.
