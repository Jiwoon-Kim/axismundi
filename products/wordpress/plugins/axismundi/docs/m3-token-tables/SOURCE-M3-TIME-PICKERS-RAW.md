# M3 Time pickers raw token table

- Specs: https://m3.material.io/components/time-pickers/specs
- Token table: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.39f4b152dd9bd292.json
- Generator: `tools/generators/fetch_m3_component_tokens.py` (complete stdout follows)

````text
component: Time pickers   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.time-input]
  md.comp.time-input.container.color                                        COLOR         -> md.sys.color.surface-container-high
  md.comp.time-input.container.elevation                                    ELEVATION     -> md.sys.elevation.level3
  md.comp.time-input.container.shape                                        SHAPE         -> md.sys.shape.corner.extra-large
  md.comp.time-input.focus.indicator.color                                  COLOR         -> md.sys.color.secondary
  md.comp.time-input.focus.indicator.outline.offset                         LENGTH        -> md.sys.state.focus-indicator.outer-offset
  md.comp.time-input.focus.indicator.thickness                              LENGTH        -> md.sys.state.focus-indicator.thickness
  md.comp.time-input.headline.color                                         COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.headline.font                                          FONT_NAMES    -> md.sys.typescale.label-medium.font
  md.comp.time-input.headline.line-height                                   LINE_HEIGHT   -> md.sys.typescale.label-medium.line-height
  md.comp.time-input.headline.size                                          FONT_SIZE     -> md.sys.typescale.label-medium.size
  md.comp.time-input.headline.tracking                                      FONT_TRACKING  -> md.sys.typescale.label-medium.tracking
  md.comp.time-input.headline.type                                          TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-input.headline.font","fontWeightTokenName":"md.comp.time-input.headline.weight","fontSizeTokenName":"md.comp.time-input.headline.size","fontTrackingTokenName":"md.comp.time-input.headline.tracking","lineHeightTokenName":"md.comp.time-input.headline.line-height"}
  md.comp.time-input.headline.weight                                        FONT_WEIGHT   -> md.sys.typescale.label-medium.weight
  md.comp.time-input.period-selector.container.height                       LENGTH        {"value":72,"unit":"DIPS"}
  md.comp.time-input.period-selector.container.shape                        SHAPE         -> md.sys.shape.corner.small
  md.comp.time-input.period-selector.container.width                        LENGTH        {"value":52,"unit":"DIPS"}
  md.comp.time-input.period-selector.focus.state-layer.opacity              OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.time-input.period-selector.hover.state-layer.opacity              OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.time-input.period-selector.label-text.font                        FONT_NAMES    -> md.sys.typescale.title-medium.font
  md.comp.time-input.period-selector.label-text.line-height                 LINE_HEIGHT   -> md.sys.typescale.title-medium.line-height
  md.comp.time-input.period-selector.label-text.size                        FONT_SIZE     -> md.sys.typescale.title-medium.size
  md.comp.time-input.period-selector.label-text.tracking                    FONT_TRACKING  -> md.sys.typescale.title-medium.tracking
  md.comp.time-input.period-selector.label-text.type                        TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-input.period-selector.label-text.font","fontWeightTokenName":"md.comp.time-input.period-selector.label-text.weight","fontSizeTokenName":"md.comp.time-input.period-selector.label-text.size","fontTrackingTokenName":"md.comp.time-input.period-selector.label-text.tracking","lineHeightTokenName":"md.comp.time-input.period-selector.label-text.line-height"}
  md.comp.time-input.period-selector.label-text.weight                      FONT_WEIGHT   -> md.sys.typescale.title-medium.weight
  md.comp.time-input.period-selector.outline.color                          COLOR         -> md.sys.color.outline
  md.comp.time-input.period-selector.outline.width                          LENGTH        {"value":1,"unit":"DIPS"}
  md.comp.time-input.period-selector.pressed.state-layer.opacity            OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.time-input.period-selector.selected.container.color               COLOR         -> md.sys.color.tertiary-container
  md.comp.time-input.period-selector.selected.focus.label-text.color        COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-input.period-selector.selected.focus.state-layer.color       COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-input.period-selector.selected.hover.label-text.color        COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-input.period-selector.selected.hover.state-layer.color       COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-input.period-selector.selected.label-text.color              COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-input.period-selector.selected.pressed.label-text.color      COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-input.period-selector.selected.pressed.state-layer.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-input.period-selector.unselected.focus.label-text.color      COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.period-selector.unselected.focus.state-layer.color     COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.period-selector.unselected.hover.label-text.color      COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.period-selector.unselected.hover.state-layer.color     COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.period-selector.unselected.label-text.color            COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.period-selector.unselected.pressed.label-text.color    COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.period-selector.unselected.pressed.state-layer.color   COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.time-input-field.container.color                       COLOR         -> md.sys.color.surface-container-highest
  md.comp.time-input.time-input-field.container.height                      LENGTH        {"value":72,"unit":"DIPS"}
  md.comp.time-input.time-input-field.container.shape                       SHAPE         -> md.sys.shape.corner.small
  md.comp.time-input.time-input-field.container.width                       LENGTH        {"value":96,"unit":"DIPS"}
  md.comp.time-input.time-input-field.focus.container.color                 COLOR         -> md.sys.color.primary-container
  md.comp.time-input.time-input-field.focus.label-text.color                COLOR         -> md.sys.color.on-primary-container
  md.comp.time-input.time-input-field.focus.outline.color                   COLOR         -> md.sys.color.primary
  md.comp.time-input.time-input-field.focus.outline.width                   LENGTH        {"value":2,"unit":"DIPS"}
  md.comp.time-input.time-input-field.hover.label-text.color                COLOR         -> md.sys.color.on-surface
  md.comp.time-input.time-input-field.hover.state-layer.color               COLOR         -> md.sys.color.on-surface
  md.comp.time-input.time-input-field.hover.state-layer.opacity             OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.time-input.time-input-field.label-text.color                      COLOR         -> md.sys.color.on-surface
  md.comp.time-input.time-input-field.label-text.font                       FONT_NAMES    -> md.sys.typescale.display-medium.font
  md.comp.time-input.time-input-field.label-text.line-height                LINE_HEIGHT   -> md.sys.typescale.display-medium.line-height
  md.comp.time-input.time-input-field.label-text.size                       FONT_SIZE     -> md.sys.typescale.display-medium.size
  md.comp.time-input.time-input-field.label-text.tracking                   FONT_TRACKING  -> md.sys.typescale.display-medium.tracking
  md.comp.time-input.time-input-field.label-text.type                       TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-input.time-input-field.label-text.font","fontWeightTokenName":"md.comp.time-input.time-input-field.label-text.weight","fontSizeTokenName":"md.comp.time-input.time-input-field.label-text.size","fontTrackingTokenName":"md.comp.time-input.time-input-field.label-text.tracking","lineHeightTokenName":"md.comp.time-input.time-input-field.label-text.line-height"}
  md.comp.time-input.time-input-field.label-text.weight                     FONT_WEIGHT   -> md.sys.typescale.display-medium.weight
  md.comp.time-input.time-input-field.separator.color                       COLOR         -> md.sys.color.on-surface
  md.comp.time-input.time-input-field.separator.font                        FONT_NAMES    -> md.sys.typescale.display-large.font
  md.comp.time-input.time-input-field.separator.line-height                 LINE_HEIGHT   -> md.sys.typescale.display-large.line-height
  md.comp.time-input.time-input-field.separator.size                        FONT_SIZE     -> md.sys.typescale.display-large.size
  md.comp.time-input.time-input-field.separator.tracking                    FONT_TRACKING  -> md.sys.typescale.display-large.tracking
  md.comp.time-input.time-input-field.separator.type                        TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-input.time-input-field.separator.font","fontWeightTokenName":"md.comp.time-input.time-input-field.separator.weight","fontSizeTokenName":"md.comp.time-input.time-input-field.separator.size","fontTrackingTokenName":"md.comp.time-input.time-input-field.separator.tracking","lineHeightTokenName":"md.comp.time-input.time-input-field.separator.line-height"}
  md.comp.time-input.time-input-field.separator.weight                      FONT_WEIGHT   -> md.sys.typescale.display-large.weight
  md.comp.time-input.time-input-field.supporting-text.color                 COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-input.time-input-field.supporting-text.font                  FONT_NAMES    -> md.sys.typescale.body-small.font
  md.comp.time-input.time-input-field.supporting-text.line-height           LINE_HEIGHT   -> md.sys.typescale.body-small.line-height
  md.comp.time-input.time-input-field.supporting-text.size                  FONT_SIZE     -> md.sys.typescale.body-small.size
  md.comp.time-input.time-input-field.supporting-text.tracking              FONT_TRACKING  -> md.sys.typescale.body-small.tracking
  md.comp.time-input.time-input-field.supporting-text.type                  TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-input.time-input-field.supporting-text.font","fontWeightTokenName":"md.comp.time-input.time-input-field.supporting-text.weight","fontSizeTokenName":"md.comp.time-input.time-input-field.supporting-text.size","fontTrackingTokenName":"md.comp.time-input.time-input-field.supporting-text.tracking","lineHeightTokenName":"md.comp.time-input.time-input-field.supporting-text.line-height"}
  md.comp.time-input.time-input-field.supporting-text.weight                FONT_WEIGHT   -> md.sys.typescale.body-small.weight
  md.comp.time-picker.clock-dial.color                                      COLOR         -> md.sys.color.surface-container-highest
  md.comp.time-picker.clock-dial.container.size                             LENGTH        {"value":256,"unit":"DIPS"}
  md.comp.time-picker.clock-dial.label-text.font                            FONT_NAMES    -> md.sys.typescale.body-large.font
  md.comp.time-picker.clock-dial.label-text.line-height                     LINE_HEIGHT   -> md.sys.typescale.body-large.line-height
  md.comp.time-picker.clock-dial.label-text.size                            FONT_SIZE     -> md.sys.typescale.body-large.size
  md.comp.time-picker.clock-dial.label-text.tracking                        FONT_TRACKING  -> md.sys.typescale.body-large.tracking
  md.comp.time-picker.clock-dial.label-text.type                            TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-picker.clock-dial.label-text.font","fontWeightTokenName":"md.comp.time-picker.clock-dial.label-text.weight","fontSizeTokenName":"md.comp.time-picker.clock-dial.label-text.size","fontTrackingTokenName":"md.comp.time-picker.clock-dial.label-text.tracking","lineHeightTokenName":"md.comp.time-picker.clock-dial.label-text.line-height"}
  md.comp.time-picker.clock-dial.label-text.weight                          FONT_WEIGHT   -> md.sys.typescale.body-large.weight
  md.comp.time-picker.clock-dial.selected.label-text.color                  COLOR         -> md.sys.color.on-primary
  md.comp.time-picker.clock-dial.selector.center.container.color            COLOR         -> md.sys.color.primary
  md.comp.time-picker.clock-dial.selector.center.container.shape            SHAPE         -> md.sys.shape.corner.full
  md.comp.time-picker.clock-dial.selector.center.container.size             LENGTH        {"value":8,"unit":"DIPS"}
  md.comp.time-picker.clock-dial.selector.handle.container.color            COLOR         -> md.sys.color.primary
  md.comp.time-picker.clock-dial.selector.handle.container.shape            SHAPE         -> md.sys.shape.corner.full
  md.comp.time-picker.clock-dial.selector.handle.container.size             LENGTH        {"value":48,"unit":"DIPS"}
  md.comp.time-picker.clock-dial.selector.track.container.color             COLOR         -> md.sys.color.primary
  md.comp.time-picker.clock-dial.selector.track.container.width             LENGTH        {"value":2,"unit":"DIPS"}
  md.comp.time-picker.clock-dial.shape                                      SHAPE         -> md.sys.shape.corner.full
  md.comp.time-picker.clock-dial.unselected.label-text.color                COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.container.color                                       COLOR         -> md.sys.color.surface-container-high
  md.comp.time-picker.container.elevation                                   ELEVATION     -> md.sys.elevation.level3
  md.comp.time-picker.container.shape                                       SHAPE         -> md.sys.shape.corner.extra-large
  md.comp.time-picker.headline.color                                        COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.headline.font                                         FONT_NAMES    -> md.sys.typescale.label-medium.font
  md.comp.time-picker.headline.line-height                                  LINE_HEIGHT   -> md.sys.typescale.label-medium.line-height
  md.comp.time-picker.headline.size                                         FONT_SIZE     -> md.sys.typescale.label-medium.size
  md.comp.time-picker.headline.tracking                                     FONT_TRACKING  -> md.sys.typescale.label-medium.tracking
  md.comp.time-picker.headline.type                                         TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-picker.headline.font","fontWeightTokenName":"md.comp.time-picker.headline.weight","fontSizeTokenName":"md.comp.time-picker.headline.size","fontTrackingTokenName":"md.comp.time-picker.headline.tracking","lineHeightTokenName":"md.comp.time-picker.headline.line-height"}
  md.comp.time-picker.headline.weight                                       FONT_WEIGHT   -> md.sys.typescale.label-medium.weight
  md.comp.time-picker.period-selector.container.shape                       SHAPE         -> md.sys.shape.corner.small
  md.comp.time-picker.period-selector.focus.state-layer.opacity             OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.time-picker.period-selector.horizontal.container.height           LENGTH        {"value":38,"unit":"DIPS"}
  md.comp.time-picker.period-selector.horizontal.container.width            LENGTH        {"value":216,"unit":"DIPS"}
  md.comp.time-picker.period-selector.hover.state-layer.opacity             OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.time-picker.period-selector.label-text.font                       FONT_NAMES    -> md.sys.typescale.title-medium.font
  md.comp.time-picker.period-selector.label-text.line-height                LINE_HEIGHT   -> md.sys.typescale.title-medium.line-height
  md.comp.time-picker.period-selector.label-text.size                       FONT_SIZE     -> md.sys.typescale.title-medium.size
  md.comp.time-picker.period-selector.label-text.tracking                   FONT_TRACKING  -> md.sys.typescale.title-medium.tracking
  md.comp.time-picker.period-selector.label-text.type                       TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-picker.period-selector.label-text.font","fontWeightTokenName":"md.comp.time-picker.period-selector.label-text.weight","fontSizeTokenName":"md.comp.time-picker.period-selector.label-text.size","fontTrackingTokenName":"md.comp.time-picker.period-selector.label-text.tracking","lineHeightTokenName":"md.comp.time-picker.period-selector.label-text.line-height"}
  md.comp.time-picker.period-selector.label-text.weight                     FONT_WEIGHT   -> md.sys.typescale.title-medium.weight
  md.comp.time-picker.period-selector.outline.color                         COLOR         -> md.sys.color.outline
  md.comp.time-picker.period-selector.outline.width                         LENGTH        {"value":1,"unit":"DIPS"}
  md.comp.time-picker.period-selector.pressed.state-layer.opacity           OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.time-picker.period-selector.selected.container.color              COLOR         -> md.sys.color.tertiary-container
  md.comp.time-picker.period-selector.selected.focus.label-text.color       COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-picker.period-selector.selected.focus.state-layer.color      COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-picker.period-selector.selected.hover.label-text.color       COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-picker.period-selector.selected.hover.state-layer.color      COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-picker.period-selector.selected.label-text.color             COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-picker.period-selector.selected.pressed.label-text.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-picker.period-selector.selected.pressed.state-layer.color    COLOR         -> md.sys.color.on-tertiary-container
  md.comp.time-picker.period-selector.unselected.focus.label-text.color     COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.period-selector.unselected.focus.state-layer.color    COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.period-selector.unselected.hover.label-text.color     COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.period-selector.unselected.hover.state-layer.color    COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.period-selector.unselected.label-text.color           COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.period-selector.unselected.pressed.label-text.color   COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.period-selector.unselected.pressed.state-layer.color  COLOR         -> md.sys.color.on-surface-variant
  md.comp.time-picker.period-selector.vertical.container.height             LENGTH        {"value":80,"unit":"DIPS"}
  md.comp.time-picker.period-selector.vertical.container.width              LENGTH        {"value":52,"unit":"DIPS"}
  md.comp.time-picker.time-selector.24h-vertical.container.width            LENGTH        {"value":114,"unit":"DIPS"}
  md.comp.time-picker.time-selector.container.height                        LENGTH        {"value":80,"unit":"DIPS"}
  md.comp.time-picker.time-selector.container.shape                         SHAPE         -> md.sys.shape.corner.small
  md.comp.time-picker.time-selector.container.width                         LENGTH        {"value":96,"unit":"DIPS"}
  md.comp.time-picker.time-selector.focus.state-layer.opacity               OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.time-picker.time-selector.hover.state-layer.opacity               OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.time-picker.time-selector.label-text.font                         FONT_NAMES    -> md.sys.typescale.display-large.font
  md.comp.time-picker.time-selector.label-text.line-height                  LINE_HEIGHT   -> md.sys.typescale.display-large.line-height
  md.comp.time-picker.time-selector.label-text.size                         FONT_SIZE     -> md.sys.typescale.display-large.size
  md.comp.time-picker.time-selector.label-text.tracking                     FONT_TRACKING  -> md.sys.typescale.display-large.tracking
  md.comp.time-picker.time-selector.label-text.type                         TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-picker.time-selector.label-text.font","fontWeightTokenName":"md.comp.time-picker.time-selector.label-text.weight","fontSizeTokenName":"md.comp.time-picker.time-selector.label-text.size","fontTrackingTokenName":"md.comp.time-picker.time-selector.label-text.tracking","lineHeightTokenName":"md.comp.time-picker.time-selector.label-text.line-height"}
  md.comp.time-picker.time-selector.label-text.weight                       FONT_WEIGHT   -> md.sys.typescale.display-large.weight
  md.comp.time-picker.time-selector.pressed.state-layer.opacity             OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.time-picker.time-selector.selected.container.color                COLOR         -> md.sys.color.primary-container
  md.comp.time-picker.time-selector.selected.focus.label-text.color         COLOR         -> md.sys.color.on-primary-container
  md.comp.time-picker.time-selector.selected.focus.state-layer.color        COLOR         -> md.sys.color.on-primary-container
  md.comp.time-picker.time-selector.selected.hover.label-text.color         COLOR         -> md.sys.color.on-primary-container
  md.comp.time-picker.time-selector.selected.hover.state-layer.color        COLOR         -> md.sys.color.on-primary-container
  md.comp.time-picker.time-selector.selected.label-text.color               COLOR         -> md.sys.color.on-primary-container
  md.comp.time-picker.time-selector.selected.pressed.label-text.color       COLOR         -> md.sys.color.on-primary-container
  md.comp.time-picker.time-selector.selected.pressed.state-layer.color      COLOR         -> md.sys.color.on-primary-container
  md.comp.time-picker.time-selector.separator.color                         COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.time-selector.separator.font                          FONT_NAMES    -> md.sys.typescale.display-large.font
  md.comp.time-picker.time-selector.separator.line-height                   LINE_HEIGHT   -> md.sys.typescale.display-large.line-height
  md.comp.time-picker.time-selector.separator.size                          FONT_SIZE     -> md.sys.typescale.display-large.size
  md.comp.time-picker.time-selector.separator.tracking                      FONT_TRACKING  -> md.sys.typescale.display-large.tracking
  md.comp.time-picker.time-selector.separator.type                          TYPOGRAPHY    {"fontNameTokenName":"md.comp.time-picker.time-selector.separator.font","fontWeightTokenName":"md.comp.time-picker.time-selector.separator.weight","fontSizeTokenName":"md.comp.time-picker.time-selector.separator.size","fontTrackingTokenName":"md.comp.time-picker.time-selector.separator.tracking","lineHeightTokenName":"md.comp.time-picker.time-selector.separator.line-height"}
  md.comp.time-picker.time-selector.separator.weight                        FONT_WEIGHT   -> md.sys.typescale.display-large.weight
  md.comp.time-picker.time-selector.unselected.container.color              COLOR         -> md.sys.color.surface-container-highest
  md.comp.time-picker.time-selector.unselected.focus.label-text.color       COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.time-selector.unselected.focus.state-layer.color      COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.time-selector.unselected.hover.label-text.color       COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.time-selector.unselected.hover.state-layer.color      COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.time-selector.unselected.label-text.color             COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.time-selector.unselected.pressed.label-text.color     COLOR         -> md.sys.color.on-surface
  md.comp.time-picker.time-selector.unselected.pressed.state-layer.color    COLOR         -> md.sys.color.on-surface
[md.ref.palette]
  md.ref.palette.neutral-variant0                                           COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant100                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant20                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant30                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant50                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant60                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant70                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant80                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant90                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant95                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral0                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral10                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral100                                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral17                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral22                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral90                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral92                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary0                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary10                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary100                                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary60                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary20                                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary95                                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary0                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary100                                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary30                                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary40                                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary60                                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary80                                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary90                                                 COLOR         (per scheme; the theme owns the value)
[md.ref.typeface]
  md.ref.typeface.brand                                                     FONT_NAMES    {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Roboto Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/4fa3c9edf61f5705","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Google Sans Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4","fontNames":{"values":["Roboto"]}} | {"values":["Google Sans"]}
  md.ref.typeface.plain                                                     FONT_NAMES    {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Roboto Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/4fa3c9edf61f5705","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Google Sans Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4","fontNames":{"values":["Roboto"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"2"} | {"values":["Google Sans Text"]}
  md.ref.typeface.weight-bold                                               FONT_WEIGHT   {"fontWeight":800,"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/60f04c068cdc9df1/tags/2632f6c814786d5e","designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"130"} | 700
  md.ref.typeface.weight-medium                                             FONT_WEIGHT   {"fontWeight":600,"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/60f04c068cdc9df1/tags/2632f6c814786d5e","designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"130"} | 500
  md.ref.typeface.weight-regular                                            FONT_WEIGHT   {"fontWeight":500,"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/60f04c068cdc9df1/tags/2632f6c814786d5e","designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"130"} | 400
[md.sys.color]
  md.sys.color.on-primary                                                   COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-primary-container                                         COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-surface                                                   COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-surface-variant                                           COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-tertiary-container                                        COLOR         (per scheme; the theme owns the value)
  md.sys.color.outline                                                      COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary                                                      COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary-container                                            COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary                                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface-container-high                                       COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface-container-highest                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.tertiary-container                                           COLOR         (per scheme; the theme owns the value)
[md.sys.elevation]
  md.sys.elevation.level3                                                   ELEVATION     {"value":6,"unit":"DIPS"}
[md.sys.shape]
  md.sys.shape.corner.extra-large                                           SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"value":28,"unit":"DIPS"}}
  md.sys.shape.corner.full                                                  SHAPE         {"family":"SHAPE_FAMILY_CIRCULAR"}
  md.sys.shape.corner.small                                                 SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"value":8,"unit":"DIPS"}}
[md.sys.state]
  md.sys.state.focus-indicator.outer-offset                                 LENGTH        {"value":2,"unit":"DIPS"}
  md.sys.state.focus-indicator.thickness                                    LENGTH        {"value":3,"unit":"DIPS"}
  md.sys.state.focus.state-layer-opacity                                    OPACITY       0.1
  md.sys.state.hover.state-layer-opacity                                    OPACITY       0.08
  md.sys.state.pressed.state-layer-opacity                                  OPACITY       0.1
[md.sys.typescale]
  md.sys.typescale.body-large.font                                          FONT_NAMES    -> md.ref.typeface.plain
  md.sys.typescale.body-large.line-height                                   LINE_HEIGHT   {"value":24,"unit":"POINTS"}
  md.sys.typescale.body-large.size                                          FONT_SIZE     {"value":16,"unit":"POINTS"}
  md.sys.typescale.body-large.tracking                                     FONT_TRACKING  {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"} | {"fontTracking":{"value":0.5,"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4"} | {"unit":"POINTS"}
  md.sys.typescale.body-large.weight                                        FONT_WEIGHT   -> md.ref.typeface.weight-regular
  md.sys.typescale.body-small.font                                          FONT_NAMES    -> md.ref.typeface.plain
  md.sys.typescale.body-small.line-height                                   LINE_HEIGHT   {"value":16,"unit":"POINTS"}
  md.sys.typescale.body-small.size                                          FONT_SIZE     {"value":12,"unit":"POINTS"}
  md.sys.typescale.body-small.tracking                                     FONT_TRACKING  {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"} | {"fontTracking":{"value":0.4,"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4"} | {"value":0.1,"unit":"POINTS"}
  md.sys.typescale.body-small.weight                                        FONT_WEIGHT   -> md.ref.typeface.weight-regular
  md.sys.typescale.display-large.font                                       FONT_NAMES    -> md.ref.typeface.brand
  md.sys.typescale.display-large.line-height                                LINE_HEIGHT   {"value":64,"unit":"POINTS"}
  md.sys.typescale.display-large.size                                       FONT_SIZE     {"value":57,"unit":"POINTS"}
  md.sys.typescale.display-large.tracking                                   FONT_TRACKING  {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"} | {"fontTracking":{"value":-0.25,"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4"} | {"unit":"POINTS"}
  md.sys.typescale.display-large.weight                                     FONT_WEIGHT   -> md.ref.typeface.weight-regular | -> md.ref.typeface.weight-medium
  md.sys.typescale.display-medium.font                                      FONT_NAMES    -> md.ref.typeface.brand
  md.sys.typescale.display-medium.line-height                               LINE_HEIGHT   {"value":52,"unit":"POINTS"}
  md.sys.typescale.display-medium.size                                      FONT_SIZE     {"value":45,"unit":"POINTS"}
  md.sys.typescale.display-medium.tracking                                  FONT_TRACKING  {"unit":"POINTS"}
  md.sys.typescale.display-medium.weight                                    FONT_WEIGHT   -> md.ref.typeface.weight-regular | -> md.ref.typeface.weight-medium
  md.sys.typescale.label-medium.font                                        FONT_NAMES    -> md.ref.typeface.plain
  md.sys.typescale.label-medium.line-height                                 LINE_HEIGHT   {"value":16,"unit":"POINTS"}
  md.sys.typescale.label-medium.size                                        FONT_SIZE     {"value":12,"unit":"POINTS"}
  md.sys.typescale.label-medium.tracking                                    FONT_TRACKING  {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"} | {"fontTracking":{"value":0.5,"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4"} | {"value":0.1,"unit":"POINTS"}
  md.sys.typescale.label-medium.weight                                      FONT_WEIGHT   -> md.ref.typeface.weight-medium
  md.sys.typescale.title-medium.font                                        FONT_NAMES    -> md.ref.typeface.brand | -> md.ref.typeface.plain
  md.sys.typescale.title-medium.line-height                                 LINE_HEIGHT   {"value":24,"unit":"POINTS"}
  md.sys.typescale.title-medium.size                                        FONT_SIZE     {"value":16,"unit":"POINTS"}
  md.sys.typescale.title-medium.tracking                                    FONT_TRACKING  {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"unit":"POINTS"} | {"fontTracking":{"value":0.15,"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4"}
  md.sys.typescale.title-medium.weight                                      FONT_WEIGHT   -> md.ref.typeface.weight-medium | -> md.ref.typeface.weight-bold

included: 266
skipped (deprecated): 5
  - md.comp.time-input.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.comp.time-picker.clock-dial.color.ignore
      Deprecating token due to typo in name. Please use md.comp.time-picker.clock-dial.color instead"
  - md.comp.time-picker.clock-dial.shape.ignore
      Deprecating token due to typo in name. Please use md.comp.time-picker.clock-dial.shape instead
  - md.comp.time-picker.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.sys.color.surface-tint
      This token should no longer be used and its function/usage has been replaced by the tonal surface colors
````
