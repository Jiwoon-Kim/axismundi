# SOURCE M3 FLOATING-ACTION-BUTTON RAW

Official specs URL: https://m3.material.io/components/floating-action-button/specs

TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.41587918e51cca98.json

Generator output (verbatim):
```text
component: Floating action buttons (FABs)   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.fab]
  md.comp.fab.branded.container.color                          COLOR         -> md.sys.color.surface-container-high
  md.comp.fab.branded.container.elevation                      ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.branded.container.height                         LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab.branded.container.shadow-color                   COLOR         -> md.sys.color.shadow
  md.comp.fab.branded.container.shape                          SHAPE         -> md.sys.shape.corner.large
  md.comp.fab.branded.container.width                          LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab.branded.focus.container.elevation                ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.branded.focus.indicator.color                    COLOR         -> md.sys.color.secondary
  md.comp.fab.branded.focus.indicator.thickness                LENGTH        -> md.sys.state.focus-indicator.thickness
  md.comp.fab.branded.focus.state-layer.color                  COLOR         -> md.sys.color.primary
  md.comp.fab.branded.focus.state-layer.opacity                OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.branded.hover.container.elevation                ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.branded.hover.state-layer.color                  COLOR         -> md.sys.color.primary
  md.comp.fab.branded.hover.state-layer.opacity                OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.branded.icon.size                                LENGTH        {"value":36,"unit":"DIPS"}
  md.comp.fab.branded.indicator.outline.offset                 LENGTH        -> md.sys.state.focus-indicator.outer-offset
  md.comp.fab.branded.lowered.container.color                  COLOR         -> md.sys.color.surface-container-low
  md.comp.fab.branded.lowered.container.elevation              ELEVATION     -> md.sys.elevation.level1
  md.comp.fab.branded.lowered.focus.container.elevation        ELEVATION     -> md.sys.elevation.level1
  md.comp.fab.branded.lowered.hover.container.elevation        ELEVATION     -> md.sys.elevation.level2
  md.comp.fab.branded.lowered.pressed.container.elevation      ELEVATION     -> md.sys.elevation.level1
  md.comp.fab.branded.pressed.container.elevation              ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.branded.pressed.state-layer.color                COLOR         -> md.sys.color.primary
  md.comp.fab.branded.pressed.state-layer.opacity              OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab.container.height                                 LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab.container.shape                                  SHAPE         -> md.sys.shape.corner.large
  md.comp.fab.container.width                                  LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab.icon.size                                        LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.fab.large.container.height                           LENGTH        {"value":96,"unit":"DIPS"}
  md.comp.fab.large.container.shape                            SHAPE         -> md.sys.shape.corner.extra-large
  md.comp.fab.large.container.width                            LENGTH        {"value":96,"unit":"DIPS"}
  md.comp.fab.large.icon.size                                  LENGTH        {"value":36,"unit":"DIPS"}
  md.comp.fab.medium.container.height                          LENGTH        {"value":80,"unit":"DIPS"}
  md.comp.fab.medium.container.shape                           SHAPE         -> md.sys.shape.corner.large-increased
  md.comp.fab.medium.container.width                           LENGTH        {"value":80,"unit":"DIPS"}
  md.comp.fab.medium.icon.size                                 LENGTH        {"value":28,"unit":"DIPS"}
  md.comp.fab.primary-container.container.color                COLOR         -> md.sys.color.primary-container
  md.comp.fab.primary-container.container.elevation            ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.primary-container.container.shadow-color         COLOR         -> md.sys.color.shadow
  md.comp.fab.primary-container.focused.container.elevation    ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.primary-container.focused.icon.color             COLOR         -> md.sys.color.on-primary-container
  md.comp.fab.primary-container.focused.state-layer.color      COLOR         -> md.sys.color.on-primary-container
  md.comp.fab.primary-container.focused.state-layer.opacity    OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.primary-container.hovered.container.elevation    ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.primary-container.hovered.icon.color             COLOR         -> md.sys.color.on-primary-container
  md.comp.fab.primary-container.hovered.state-layer.color      COLOR         -> md.sys.color.on-primary-container
  md.comp.fab.primary-container.hovered.state-layer.opacity    OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.primary-container.icon.color                     COLOR         -> md.sys.color.on-primary-container
  md.comp.fab.primary-container.pressed.container.elevation    ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.primary-container.pressed.icon.color             COLOR         -> md.sys.color.on-primary-container
  md.comp.fab.primary-container.pressed.state-layer.color      COLOR         -> md.sys.color.on-primary-container
  md.comp.fab.primary-container.pressed.state-layer.opacity    OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab.primary.container.color                          COLOR         -> md.sys.color.primary
  md.comp.fab.primary.container.elevation                      ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.primary.container.shadow-color                   COLOR         -> md.sys.color.shadow
  md.comp.fab.primary.focused.container.elevation              ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.primary.focused.icon.color                       COLOR         -> md.sys.color.on-primary
  md.comp.fab.primary.focused.state-layer.color                COLOR         -> md.sys.color.on-primary
  md.comp.fab.primary.focused.state-layer.opacity              OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.primary.hovered.container.elevation              ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.primary.hovered.icon.color                       COLOR         -> md.sys.color.on-primary
  md.comp.fab.primary.hovered.state-layer.color                COLOR         -> md.sys.color.on-primary
  md.comp.fab.primary.hovered.state-layer.opacity              OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.primary.icon.color                               COLOR         -> md.sys.color.on-primary
  md.comp.fab.primary.pressed.container.elevation              ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.primary.pressed.icon.color                       COLOR         -> md.sys.color.on-primary
  md.comp.fab.primary.pressed.state-layer.color                COLOR         -> md.sys.color.on-primary
  md.comp.fab.primary.pressed.state-layer.opacity              OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab.secondary-container.container.color              COLOR         -> md.sys.color.secondary-container
  md.comp.fab.secondary-container.container.elevation          ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.secondary-container.container.shadow-color       COLOR         -> md.sys.color.shadow
  md.comp.fab.secondary-container.focused.container.elevation  ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.secondary-container.focused.icon.color           COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab.secondary-container.focused.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab.secondary-container.focused.state-layer.opacity  OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.secondary-container.hovered.container.elevation  ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.secondary-container.hovered.icon.color           COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab.secondary-container.hovered.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab.secondary-container.hovered.state-layer.opacity  OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.secondary-container.icon.color                   COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab.secondary-container.pressed.container.elevation  ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.secondary-container.pressed.icon.color           COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab.secondary-container.pressed.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab.secondary-container.pressed.state-layer.opacity  OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab.secondary.container.color                        COLOR         -> md.sys.color.secondary
  md.comp.fab.secondary.container.elevation                    ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.secondary.container.shadow-color                 COLOR         -> md.sys.color.shadow
  md.comp.fab.secondary.focused.container.elevation            ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.secondary.focused.icon.color                     COLOR         -> md.sys.color.on-secondary
  md.comp.fab.secondary.focused.state-layer.color              COLOR         -> md.sys.color.on-secondary
  md.comp.fab.secondary.focused.state-layer.opacity            OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.secondary.hovered.container.elevation            ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.secondary.hovered.icon.color                     COLOR         -> md.sys.color.on-secondary
  md.comp.fab.secondary.hovered.state-layer.color              COLOR         -> md.sys.color.on-secondary
  md.comp.fab.secondary.hovered.state-layer.opacity            OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.secondary.icon.color                             COLOR         -> md.sys.color.on-secondary
  md.comp.fab.secondary.pressed.container.elevation            ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.secondary.pressed.icon.color                     COLOR         -> md.sys.color.on-secondary
  md.comp.fab.secondary.pressed.state-layer.color              COLOR         -> md.sys.color.on-secondary
  md.comp.fab.secondary.pressed.state-layer.opacity            OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab.small.container.height                           LENGTH        {"value":40,"unit":"DIPS"}
  md.comp.fab.small.container.shape                            SHAPE         -> md.sys.shape.corner.medium
  md.comp.fab.small.container.width                            LENGTH        {"value":40,"unit":"DIPS"}
  md.comp.fab.small.icon.size                                  LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.fab.surface.container.color                          COLOR         -> md.sys.color.surface-container-high
  md.comp.fab.surface.container.elevation                      ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.surface.container.height                         LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab.surface.container.shadow-color                   COLOR         -> md.sys.color.shadow
  md.comp.fab.surface.container.shape                          SHAPE         -> md.sys.shape.corner.large
  md.comp.fab.surface.container.width                          LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab.surface.focus.container.elevation                ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.surface.focus.icon.color                         COLOR         -> md.sys.color.primary
  md.comp.fab.surface.focus.indicator.color                    COLOR         -> md.sys.color.secondary
  md.comp.fab.surface.focus.indicator.outline.offset           LENGTH        -> md.sys.state.focus-indicator.outer-offset
  md.comp.fab.surface.focus.indicator.thickness                LENGTH        -> md.sys.state.focus-indicator.thickness
  md.comp.fab.surface.focus.state-layer.color                  COLOR         -> md.sys.color.primary
  md.comp.fab.surface.focus.state-layer.opacity                OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.surface.hover.container.elevation                ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.surface.hover.icon.color                         COLOR         -> md.sys.color.primary
  md.comp.fab.surface.hover.state-layer.color                  COLOR         -> md.sys.color.primary
  md.comp.fab.surface.hover.state-layer.opacity                OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.surface.icon.color                               COLOR         -> md.sys.color.primary
  md.comp.fab.surface.icon.size                                LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.fab.surface.lowered.container.color                  COLOR         -> md.sys.color.surface-container-low
  md.comp.fab.surface.lowered.container.elevation              ELEVATION     -> md.sys.elevation.level1
  md.comp.fab.surface.lowered.focus.container.elevation        ELEVATION     -> md.sys.elevation.level1
  md.comp.fab.surface.lowered.hover.container.elevation        ELEVATION     -> md.sys.elevation.level2
  md.comp.fab.surface.lowered.pressed.container.elevation      ELEVATION     -> md.sys.elevation.level1
  md.comp.fab.surface.pressed.container.elevation              ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.surface.pressed.icon.color                       COLOR         -> md.sys.color.primary
  md.comp.fab.surface.pressed.state-layer.color                COLOR         -> md.sys.color.primary
  md.comp.fab.surface.pressed.state-layer.opacity              OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab.tertiary-container.container.color               COLOR         -> md.sys.color.tertiary-container
  md.comp.fab.tertiary-container.container.elevation           ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.tertiary-container.container.shadow-color        COLOR         -> md.sys.color.shadow
  md.comp.fab.tertiary-container.focused.container.elevation   ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.tertiary-container.focused.icon.color            COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab.tertiary-container.focused.state-layer.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab.tertiary-container.focused.state-layer.opacity   OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.tertiary-container.hovered.container.elevation   ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.tertiary-container.hovered.icon.color            COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab.tertiary-container.hovered.state-layer.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab.tertiary-container.hovered.state-layer.opacity   OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.tertiary-container.icon.color                    COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab.tertiary-container.pressed.container.elevation   ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.tertiary-container.pressed.icon.color            COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab.tertiary-container.pressed.state-layer.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab.tertiary-container.pressed.state-layer.opacity   OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab.tertiary.container.color                         COLOR         -> md.sys.color.tertiary
  md.comp.fab.tertiary.container.elevation                     ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.tertiary.container.shadow-color                  COLOR         -> md.sys.color.shadow
  md.comp.fab.tertiary.focused.container.elevation             ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.tertiary.focused.icon.color                      COLOR         -> md.sys.color.on-tertiary
  md.comp.fab.tertiary.focused.state-layer.color               COLOR         -> md.sys.color.on-tertiary
  md.comp.fab.tertiary.focused.state-layer.opacity             OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab.tertiary.hovered.container.elevation             ELEVATION     -> md.sys.elevation.level4
  md.comp.fab.tertiary.hovered.icon.color                      COLOR         -> md.sys.color.on-tertiary
  md.comp.fab.tertiary.hovered.state-layer.color               COLOR         -> md.sys.color.on-tertiary
  md.comp.fab.tertiary.hovered.state-layer.opacity             OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab.tertiary.icon.color                              COLOR         -> md.sys.color.on-tertiary
  md.comp.fab.tertiary.pressed.container.elevation             ELEVATION     -> md.sys.elevation.level3
  md.comp.fab.tertiary.pressed.icon.color                      COLOR         -> md.sys.color.on-tertiary
  md.comp.fab.tertiary.pressed.state-layer.color               COLOR         -> md.sys.color.on-tertiary
  md.comp.fab.tertiary.pressed.state-layer.opacity             OPACITY       -> md.sys.state.pressed.state-layer-opacity
[md.ref.palette]
  md.ref.palette.neutral0                                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral10                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral17                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral92                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral96                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary0                                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary10                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary100                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary60                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary0                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary10                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary100                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary20                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary60                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary95                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary0                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary10                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary100                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary20                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary30                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary40                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary60                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary80                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary90                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary95                                    COLOR         (per scheme; the theme owns the value)
[md.sys.color]
  md.sys.color.on-primary                                      COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-primary-container                            COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-secondary                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-secondary-container                          COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-tertiary                                     COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-tertiary-container                           COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary                                         COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary-container                               COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary                                       COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary-container                             COLOR         (per scheme; the theme owns the value)
  md.sys.color.shadow                                          COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface-container-high                          COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface-container-low                           COLOR         (per scheme; the theme owns the value)
  md.sys.color.tertiary                                        COLOR         (per scheme; the theme owns the value)
  md.sys.color.tertiary-container                              COLOR         (per scheme; the theme owns the value)
[md.sys.elevation]
  md.sys.elevation.level1                                      ELEVATION     {"value":1,"unit":"DIPS"}
  md.sys.elevation.level2                                      ELEVATION     {"value":3,"unit":"DIPS"}
  md.sys.elevation.level3                                      ELEVATION     {"value":6,"unit":"DIPS"}
  md.sys.elevation.level4                                      ELEVATION     {"value":8,"unit":"DIPS"}
[md.sys.shape]
  md.sys.shape.corner.extra-large                              SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"value":28,"unit":"DIPS"}}
  md.sys.shape.corner.large                                    SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"value":16,"unit":"DIPS"}}
  md.sys.shape.corner.large-increased                          SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"value":20,"unit":"DIPS"}}
  md.sys.shape.corner.medium                                   SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"value":12,"unit":"DIPS"}}
[md.sys.state]
  md.sys.state.focus-indicator.outer-offset                    LENGTH        {"value":2,"unit":"DIPS"}
  md.sys.state.focus-indicator.thickness                       LENGTH        {"value":3,"unit":"DIPS"}
  md.sys.state.focus.state-layer-opacity                       OPACITY       0.1
  md.sys.state.hover.state-layer-opacity                       OPACITY       0.08
  md.sys.state.pressed.state-layer-opacity                     OPACITY       0.1

included: 227
skipped (deprecated): 306
  - md.comp.fab.branded.container.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.comp.fab.branded.large.container.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.container.height
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.container.shadow-color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.container.shape
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.container.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.comp.fab.branded.large.container.width
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.focus.indicator.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.icon.color
      Bug fix. The branded FAB is using a four-colored icon.
  - md.comp.fab.branded.large.icon.size
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.lowered.container.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.branded.large.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and branded fab `md.comp.fab.branded` token sets instead.
  - md.comp.fab.primary.container.height
      Token is deprecated.
  - md.comp.fab.primary.container.shape
      Token is deprecated.
  - md.comp.fab.primary.container.width
      Token is deprecated.
  - md.comp.fab.primary.focus.container.elevation
      Token is deprecated.
  - md.comp.fab.primary.focus.icon.color
      Token is deprecated.
  - md.comp.fab.primary.focus.indicator.color
      Token is deprecated.
  - md.comp.fab.primary.focus.indicator.outline.offset
      Token is deprecated.
  - md.comp.fab.primary.focus.indicator.thickness
      Token is deprecated.
  - md.comp.fab.primary.focus.state-layer.color
      Token is deprecated.
  - md.comp.fab.primary.focus.state-layer.opacity
      Token is deprecated.
  - md.comp.fab.primary.hover.container.elevation
      Token is deprecated.
  - md.comp.fab.primary.hover.icon.color
      Token is deprecated.
  - md.comp.fab.primary.hover.state-layer.color
      Token is deprecated.
  - md.comp.fab.primary.hover.state-layer.opacity
      Token is deprecated.
  - md.comp.fab.primary.icon.size
      Token is deprecated.
  - md.comp.fab.primary.large.container.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.container.height
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.container.shadow-color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.container.shape
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.container.width
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.focus.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.focus.indicator.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.hover.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.icon.size
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.pressed.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.large.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.lowered.container.elevation
      Token is deprecated.
  - md.comp.fab.primary.lowered.focus.container.elevation
      Token is deprecated.
  - md.comp.fab.primary.lowered.hover.container.elevation
      Token is deprecated.
  - md.comp.fab.primary.lowered.pressed.container.elevation
      Token is deprecated.
  - md.comp.fab.primary.small.container.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.container.height
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.container.shadow-color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.container.shape
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.container.width
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.focus.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.focus.indicator.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.hover.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.icon.size
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.pressed.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.primary.small.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal primary container fab `md.comp.fab.primary-container` token sets instead.
  - md.comp.fab.secondary.container.height
      Token is deprecated.
  - md.comp.fab.secondary.container.shape
      Token is deprecated.
  - md.comp.fab.secondary.container.width
      Token is deprecated.
  - md.comp.fab.secondary.focus.container.elevation
      Token is deprecated.
  - md.comp.fab.secondary.focus.icon.color
      Token is deprecated.
  - md.comp.fab.secondary.focus.indicator.color
      Token is deprecated.
  - md.comp.fab.secondary.focus.indicator.outline.offset
      Token is deprecated.
  - md.comp.fab.secondary.focus.indicator.thickness
      Token is deprecated.
  - md.comp.fab.secondary.focus.state-layer.color
      Token is deprecated.
  - md.comp.fab.secondary.focus.state-layer.opacity
      Token is deprecated.
  - md.comp.fab.secondary.hover.container.elevation
      Token is deprecated.
  - md.comp.fab.secondary.hover.icon.color
      Token is deprecated.
  - md.comp.fab.secondary.hover.state-layer.color
      Token is deprecated.
  - md.comp.fab.secondary.hover.state-layer.opacity
      Token is deprecated.
  - md.comp.fab.secondary.icon.size
      Token is deprecated.
  - md.comp.fab.secondary.large.container.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.container.height
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.container.shadow-color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.container.shape
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.container.width
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.focus.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.focus.indicator.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.hover.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.icon.size
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.pressed.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.large.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.lowered.container.elevation
      Token is deprecated.
  - md.comp.fab.secondary.lowered.focus.container.elevation
      Token is deprecated.
  - md.comp.fab.secondary.lowered.hover.container.elevation
      Token is deprecated.
  - md.comp.fab.secondary.lowered.pressed.container.elevation
      Token is deprecated.
  - md.comp.fab.secondary.small.container.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.container.height
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.container.shadow-color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.container.shape
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.container.width
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.focus.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.focus.indicator.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.hover.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.icon.size
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.pressed.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.secondary.small.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal secondary container fab `md.comp.fab.secondary-container` token sets instead.
  - md.comp.fab.surface.container.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.comp.fab.surface.large.container.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.container.height
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.container.shadow-color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.container.shape
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.container.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.comp.fab.surface.large.container.width
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.focus.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.focus.indicator.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.hover.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.icon.size
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.lowered.container.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.pressed.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.surface.large.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and surface fab `md.comp.fab.surface` token sets instead.
  - md.comp.fab.tertiary.container.height
      Token is deprecated.
  - md.comp.fab.tertiary.container.shape
      Token is deprecated.
  - md.comp.fab.tertiary.container.width
      Token is deprecated.
  - md.comp.fab.tertiary.focus.container.elevation
      Token is deprecated.
  - md.comp.fab.tertiary.focus.icon.color
      Token is deprecated.
  - md.comp.fab.tertiary.focus.indicator.color
      Token is deprecated.
  - md.comp.fab.tertiary.focus.indicator.outline.offset
      Token is deprecated.
  - md.comp.fab.tertiary.focus.indicator.thickness
      Token is deprecated.
  - md.comp.fab.tertiary.focus.state-layer.color
      Token is deprecated.
  - md.comp.fab.tertiary.focus.state-layer.opacity
      Token is deprecated.
  - md.comp.fab.tertiary.hover.container.elevation
      Token is deprecated.
  - md.comp.fab.tertiary.hover.icon.color
      Token is deprecated.
  - md.comp.fab.tertiary.hover.state-layer.color
      Token is deprecated.
  - md.comp.fab.tertiary.hover.state-layer.opacity
      Token is deprecated.
  - md.comp.fab.tertiary.icon.size
      Token is deprecated.
  - md.comp.fab.tertiary.large.container.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.container.height
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.container.shadow-color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.container.shape
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.container.width
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.focus.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.focus.indicator.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.hover.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.icon.size
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.pressed.icon.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.large.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the large fab size `md.comp.fab.large` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.lowered.container.elevation
      Token is deprecated.
  - md.comp.fab.tertiary.lowered.focus.container.elevation
      Token is deprecated.
  - md.comp.fab.tertiary.lowered.hover.container.elevation
      Token is deprecated.
  - md.comp.fab.tertiary.lowered.pressed.container.elevation
      Token is deprecated.
  - md.comp.fab.tertiary.small.container.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.container.height
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.container.shadow-color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.container.shape
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.container.width
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.focus.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.focus.indicator.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.hover.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.icon.size
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.pressed.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.comp.fab.tertiary.small.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and tonal tertiary container fab `md.comp.fab.tertiary-container` token sets instead.
  - md.fab.surface.small.container.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.container.height
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.container.shadow-color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.container.shape
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.container.surface-tint-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.container.width
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.focus.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.focus.indicator.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.focus.indicator.outline.offset
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.focus.indicator.thickness
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.focus.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.focus.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.hover.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.hover.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.hover.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.icon.size
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.lowered.container.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.lowered.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.lowered.focus.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.lowered.hover.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.lowered.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.pressed.container.elevation
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.pressed.icon.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.pressed.state-layer.color
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.fab.surface.small.pressed.state-layer.opacity
      This token is deprecated. Use the corresponding token from the expressive small fab `md.comp.fab` or baseline small fab `md.comp.fab.small` and surface fab `md.comp.fab.surface` token sets instead.
  - md.sys.color.surface-tint
      This token should no longer be used and its function/usage has been replaced by the tonal surface colors
```
