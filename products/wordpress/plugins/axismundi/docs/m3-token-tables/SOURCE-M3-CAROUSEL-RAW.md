# M3 token table: Carousel

Official specs URL: https://m3.material.io/components/carousel/specs
TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.43a124d2adc8a2ef.json

```text
component: Carousel   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.carousel-item]
  md.comp.carousel-item.container.color                        COLOR         -> md.sys.color.surface
  md.comp.carousel-item.container.elevation                    ELEVATION     -> md.sys.elevation.level0
  md.comp.carousel-item.container.shadow-color                 COLOR         -> md.sys.color.shadow
  md.comp.carousel-item.container.shape                        SHAPE         -> md.sys.shape.corner.extra-large
  md.comp.carousel-item.disabled.container.color               COLOR         -> md.sys.color.surface
  md.comp.carousel-item.disabled.container.elevation           ELEVATION     -> md.sys.elevation.level0
  md.comp.carousel-item.disabled.container.opacity             OPACITY       0.38
  md.comp.carousel-item.focus.container.elevation              ELEVATION     -> md.sys.elevation.level0
  md.comp.carousel-item.focus.indicator.color                  COLOR         -> md.sys.color.secondary
  md.comp.carousel-item.focus.indicator.outline.offset         LENGTH        -> md.sys.state.focus-indicator.outer-offset
  md.comp.carousel-item.focus.indicator.thickness              LENGTH        -> md.sys.state.focus-indicator.thickness
  md.comp.carousel-item.focus.state-layer.color                COLOR         -> md.sys.color.on-surface
  md.comp.carousel-item.focus.state-layer.opacity              OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.carousel-item.hover.container.elevation              ELEVATION     -> md.sys.elevation.level1
  md.comp.carousel-item.hover.state-layer.color                COLOR         -> md.sys.color.on-surface
  md.comp.carousel-item.hover.state-layer.opacity              OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.carousel-item.pressed.container.elevation            ELEVATION     -> md.sys.elevation.level0
  md.comp.carousel-item.pressed.state-layer.color              COLOR         -> md.sys.color.on-surface
  md.comp.carousel-item.pressed.state-layer.opacity            OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.carousel-item.with-outline.disabled.outline.color    COLOR         -> md.sys.color.outline
  md.comp.carousel-item.with-outline.disabled.outline.opacity  OPACITY       0.12
  md.comp.carousel-item.with-outline.focus.outline.color       COLOR         -> md.sys.color.on-surface
  md.comp.carousel-item.with-outline.hover.outline.color       COLOR         -> md.sys.color.outline
  md.comp.carousel-item.with-outline.outline.color             COLOR         -> md.sys.color.outline
  md.comp.carousel-item.with-outline.outline.width             ELEVATION     {"value":1,"unit":"DIPS"}
  md.comp.carousel-item.with-outline.pressed.outline.color     COLOR         -> md.sys.color.outline
[md.ref.palette]
  md.ref.palette.neutral-variant20                             COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant30                             COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant50                             COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant60                             COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant70                             COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant95                             COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral0                                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral10                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral100                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral6                                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral90                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral98                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary20                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary95                                   COLOR         (per scheme; the theme owns the value)
[md.sys.color]
  md.sys.color.on-surface                                      COLOR         (per scheme; the theme owns the value)
  md.sys.color.outline                                         COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary                                         COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary                                       COLOR         (per scheme; the theme owns the value)
  md.sys.color.shadow                                          COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface                                         COLOR         (per scheme; the theme owns the value)
[md.sys.elevation]
  md.sys.elevation.level0                                      ELEVATION     {"unit":"DIPS"}
  md.sys.elevation.level1                                      ELEVATION     {"value":1,"unit":"DIPS"}
[md.sys.shape]
  md.sys.shape.corner.extra-large                              SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"value":28,"unit":"DIPS"}}
[md.sys.state]
  md.sys.state.focus-indicator.outer-offset                    LENGTH        {"value":2,"unit":"DIPS"}
  md.sys.state.focus-indicator.thickness                       LENGTH        {"value":3,"unit":"DIPS"}
  md.sys.state.focus.state-layer-opacity                       OPACITY       0.1
  md.sys.state.hover.state-layer-opacity                       OPACITY       0.08
  md.sys.state.pressed.state-layer-opacity                     OPACITY       0.1

included: 64
skipped (deprecated): 2
  - md.comp.carousel-item.container.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.sys.color.surface-tint
      This token should no longer be used and its function/usage has been replaced by the tonal surface colors
```
