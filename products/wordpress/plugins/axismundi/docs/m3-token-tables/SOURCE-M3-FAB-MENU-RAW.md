# SOURCE M3 FAB-MENU RAW

Official specs URL: https://m3.material.io/components/fab-menu/specs

TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.1a076cec8bc202c2.json

Generator output (verbatim):
```text
component: FAB menu   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.fab-menu]
  md.comp.fab-menu.close-button.between-space                                 LENGTH        -> md.sys.measurement.space100
  md.comp.fab-menu.close-button.container.elevation                           ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.close-button.container.height                              LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab-menu.close-button.container.shape                               SHAPE         -> md.sys.shape.corner.full
  md.comp.fab-menu.close-button.container.width                               LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab-menu.close-button.icon.size                                     LENGTH        {"value":20,"unit":"DIPS"}
  md.comp.fab-menu.menu-item.between-space                                    LENGTH        -> md.sys.measurement.space50
  md.comp.fab-menu.menu-item.container.elevation                              ELEVATION     -> md.sys.elevation.level0
  md.comp.fab-menu.menu-item.container.height                                 LENGTH        {"value":56,"unit":"DIPS"}
  md.comp.fab-menu.menu-item.container.shape                                  SHAPE         -> md.sys.shape.corner.full
  md.comp.fab-menu.menu-item.icon-label-space                                 LENGTH        -> md.sys.measurement.space100
  md.comp.fab-menu.menu-item.icon.size                                        LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.fab-menu.menu-item.label-text                                       TYPOGRAPHY    -> md.sys.typescale.title-medium
  md.comp.fab-menu.menu-item.leading-space                                    LENGTH        -> md.sys.measurement.space300
  md.comp.fab-menu.menu-item.trailing-space                                   LENGTH        -> md.sys.measurement.space300
  md.comp.fab-menu.primary-container.list-item.container.color                COLOR         -> md.sys.color.primary-container
  md.comp.fab-menu.primary-container.list-item.container.shadow-color         COLOR         -> md.sys.color.shadow
  md.comp.fab-menu.primary-container.list-item.focused.container.elevation    ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.primary-container.list-item.focused.icon.color             COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.focused.label-text.color       COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.focused.state-layer.color      COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.focused.state-layer.opacity    OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab-menu.primary-container.list-item.hovered.container.elevation    ELEVATION     -> md.sys.elevation.level4
  md.comp.fab-menu.primary-container.list-item.hovered.icon.color             COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.hovered.label-text.color       COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.hovered.state-layer.color      COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.hovered.state-layer.opacity    OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab-menu.primary-container.list-item.icon.color                     COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.label-text.color               COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.pressed.container.elevation    ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.primary-container.list-item.pressed.icon.color             COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.pressed.label-text.color       COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.pressed.state-layer.color      COLOR         -> md.sys.color.on-primary-container
  md.comp.fab-menu.primary-container.list-item.pressed.state-layer.opacity    OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab-menu.primary.close-button.container.color                       COLOR         -> md.sys.color.primary
  md.comp.fab-menu.primary.close-button.container.shadow-color                COLOR         -> md.sys.color.shadow
  md.comp.fab-menu.primary.close-button.focused.container.elevation           ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.primary.close-button.focused.icon.color                    COLOR         -> md.sys.color.on-primary
  md.comp.fab-menu.primary.close-button.focused.state-layer.color             COLOR         -> md.sys.color.on-primary
  md.comp.fab-menu.primary.close-button.focused.state-layer.opacity           OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab-menu.primary.close-button.hovered.container.elevation           ELEVATION     -> md.sys.elevation.level4
  md.comp.fab-menu.primary.close-button.hovered.icon.color                    COLOR         -> md.sys.color.on-primary
  md.comp.fab-menu.primary.close-button.hovered.state-layer.color             COLOR         -> md.sys.color.on-primary
  md.comp.fab-menu.primary.close-button.hovered.state-layer.opacity           OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab-menu.primary.close-button.icon.color                            COLOR         -> md.sys.color.on-primary
  md.comp.fab-menu.primary.close-button.pressed.container.elevation           ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.primary.close-button.pressed.icon.color                    COLOR         -> md.sys.color.on-primary
  md.comp.fab-menu.primary.close-button.pressed.state-layer.color             COLOR         -> md.sys.color.on-primary
  md.comp.fab-menu.primary.close-button.pressed.state-layer.opacity           OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab-menu.secondary-container.list-item.container.color              COLOR         -> md.sys.color.secondary-container
  md.comp.fab-menu.secondary-container.list-item.container.shadow-color       COLOR         -> md.sys.color.shadow
  md.comp.fab-menu.secondary-container.list-item.focused.container.elevation  ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.secondary-container.list-item.focused.icon.color           COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.focused.label-text.color     COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.focused.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.focused.state-layer.opacity  OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab-menu.secondary-container.list-item.hovered.container.elevation  ELEVATION     -> md.sys.elevation.level4
  md.comp.fab-menu.secondary-container.list-item.hovered.icon.color           COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.hovered.label-text.color     COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.hovered.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.hovered.state-layer.opacity  OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab-menu.secondary-container.list-item.icon.color                   COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.label-text.color             COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.pressed.container.elevation  ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.secondary-container.list-item.pressed.icon.color           COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.pressed.label-text.color     COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.pressed.state-layer.color    COLOR         -> md.sys.color.on-secondary-container
  md.comp.fab-menu.secondary-container.list-item.pressed.state-layer.opacity  OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab-menu.secondary.close-button.container.color                     COLOR         -> md.sys.color.secondary
  md.comp.fab-menu.secondary.close-button.container.shadow-color              COLOR         -> md.sys.color.shadow
  md.comp.fab-menu.secondary.close-button.focused.container.elevation         ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.secondary.close-button.focused.icon.color                  COLOR         -> md.sys.color.on-secondary
  md.comp.fab-menu.secondary.close-button.focused.state-layer.color           COLOR         -> md.sys.color.on-secondary
  md.comp.fab-menu.secondary.close-button.focused.state-layer.opacity         OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab-menu.secondary.close-button.hovered.container.elevation         ELEVATION     -> md.sys.elevation.level4
  md.comp.fab-menu.secondary.close-button.hovered.icon.color                  COLOR         -> md.sys.color.on-secondary
  md.comp.fab-menu.secondary.close-button.hovered.state-layer.color           COLOR         -> md.sys.color.on-secondary
  md.comp.fab-menu.secondary.close-button.hovered.state-layer.opacity         OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab-menu.secondary.close-button.icon.color                          COLOR         -> md.sys.color.on-secondary
  md.comp.fab-menu.secondary.close-button.pressed.container.elevation         ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.secondary.close-button.pressed.icon.color                  COLOR         -> md.sys.color.on-secondary
  md.comp.fab-menu.secondary.close-button.pressed.state-layer.color           COLOR         -> md.sys.color.on-secondary
  md.comp.fab-menu.secondary.close-button.pressed.state-layer.opacity         OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab-menu.tertiary-container.list-item.container.color               COLOR         -> md.sys.color.tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.container.shadow-color        COLOR         -> md.sys.color.shadow
  md.comp.fab-menu.tertiary-container.list-item.focused.container.elevation   ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.tertiary-container.list-item.focused.icon.color            COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.focused.label-text.color      COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.focused.state-layer.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.focused.state-layer.opacity   OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab-menu.tertiary-container.list-item.hovered.container.elevation   ELEVATION     -> md.sys.elevation.level4
  md.comp.fab-menu.tertiary-container.list-item.hovered.icon.color            COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.hovered.label-text.color      COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.hovered.state-layer.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.hovered.state-layer.opacity   OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab-menu.tertiary-container.list-item.icon.color                    COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.label-text.color              COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.pressed.container.elevation   ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.tertiary-container.list-item.pressed.icon.color            COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.pressed.label-text.color      COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.pressed.state-layer.color     COLOR         -> md.sys.color.on-tertiary-container
  md.comp.fab-menu.tertiary-container.list-item.pressed.state-layer.opacity   OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.fab-menu.tertiary.close-button.container.color                      COLOR         -> md.sys.color.tertiary
  md.comp.fab-menu.tertiary.close-button.container.shadow-color               COLOR         -> md.sys.color.shadow
  md.comp.fab-menu.tertiary.close-button.focused.container.elevation          ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.tertiary.close-button.focused.icon.color                   COLOR         -> md.sys.color.on-tertiary
  md.comp.fab-menu.tertiary.close-button.focused.state-layer.color            COLOR         -> md.sys.color.on-tertiary
  md.comp.fab-menu.tertiary.close-button.focused.state-layer.opacity          OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.fab-menu.tertiary.close-button.hovered.container.elevation          ELEVATION     -> md.sys.elevation.level4
  md.comp.fab-menu.tertiary.close-button.hovered.icon.color                   COLOR         -> md.sys.color.on-tertiary
  md.comp.fab-menu.tertiary.close-button.hovered.state-layer.color            COLOR         -> md.sys.color.on-tertiary
  md.comp.fab-menu.tertiary.close-button.hovered.state-layer.opacity          OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.fab-menu.tertiary.close-button.icon.color                           COLOR         -> md.sys.color.on-tertiary
  md.comp.fab-menu.tertiary.close-button.pressed.container.elevation          ELEVATION     -> md.sys.elevation.level3
  md.comp.fab-menu.tertiary.close-button.pressed.icon.color                   COLOR         -> md.sys.color.on-tertiary
  md.comp.fab-menu.tertiary.close-button.pressed.state-layer.color            COLOR         -> md.sys.color.on-tertiary
  md.comp.fab-menu.tertiary.close-button.pressed.state-layer.opacity          OPACITY       -> md.sys.state.pressed.state-layer-opacity
[md.ref.palette]
  md.ref.palette.neutral0                                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary0                                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary10                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary100                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary60                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary0                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary10                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary100                                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary20                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary60                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary95                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary0                                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary10                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary100                                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary20                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary30                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary40                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary60                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary80                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary90                                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary95                                                   COLOR         (per scheme; the theme owns the value)
[md.ref.typeface]
  md.ref.typeface.brand                                                       FONT_NAMES    {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Roboto Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/4fa3c9edf61f5705","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Google Sans Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4","fontNames":{"values":["Roboto"]}} | {"values":["Google Sans"]}
  md.ref.typeface.plain                                                       FONT_NAMES    {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Roboto Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/4fa3c9edf61f5705","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Google Sans Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4","fontNames":{"values":["Roboto"]}} | {"values":["Google Sans Text"]}
  md.ref.typeface.weight-bold                                                 FONT_WEIGHT   {"fontWeight":800,"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/60f04c068cdc9df1/tags/2632f6c814786d5e","designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"130"} | 700
  md.ref.typeface.weight-medium                                               FONT_WEIGHT   {"fontWeight":600,"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/60f04c068cdc9df1/tags/2632f6c814786d5e","designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"130"} | 500
[md.sys.color]
  md.sys.color.on-primary                                                     COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-primary-container                                           COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-secondary                                                   COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-secondary-container                                         COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-tertiary                                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-tertiary-container                                          COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary                                                        COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary-container                                              COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary                                                      COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary-container                                            COLOR         (per scheme; the theme owns the value)
  md.sys.color.shadow                                                         COLOR         (per scheme; the theme owns the value)
  md.sys.color.tertiary                                                       COLOR         (per scheme; the theme owns the value)
  md.sys.color.tertiary-container                                             COLOR         (per scheme; the theme owns the value)
[md.sys.elevation]
  md.sys.elevation.level0                                                     ELEVATION     {"unit":"DIPS"}
  md.sys.elevation.level3                                                     ELEVATION     {"value":6,"unit":"DIPS"}
  md.sys.elevation.level4                                                     ELEVATION     {"value":8,"unit":"DIPS"}
[md.sys.measurement]
  md.sys.measurement.space100                                                 LENGTH        {"value":8,"unit":"DIPS"}
  md.sys.measurement.space300                                                 LENGTH        {"value":24,"unit":"DIPS"}
  md.sys.measurement.space50                                                  LENGTH        {"value":4,"unit":"DIPS"}
[md.sys.shape]
  md.sys.shape.corner.full                                                    SHAPE         {"family":"SHAPE_FAMILY_CIRCULAR"}
[md.sys.state]
  md.sys.state.focus.state-layer-opacity                                      OPACITY       0.1
  md.sys.state.hover.state-layer-opacity                                      OPACITY       0.08
  md.sys.state.pressed.state-layer-opacity                                    OPACITY       0.1
[md.sys.typescale]
  md.sys.typescale.title-medium                                               TYPOGRAPHY    {"type":{"fontNameTokenName":"md.sys.typescale.title-medium.font","fontWeightTokenName":"md.sys.typescale.title-medium.weight","fontSizeTokenName":"md.sys.typescale.title-medium.size","fontTrackingTokenName":"md.sys.typescale.title-medium.tracking","lineHeightTokenName":"md.sys.typescale.title-medium.line-height","variationAxes":{"wdth":{"axisValueTokenName":"md.sys.typescale.title-medium.wdth"},"wght":{"axisValueTokenName":"md.sys.typescale.title-medium.wght"},"slnt":{"axisValueTokenName":"md.sys.typescale.title-medium.slnt"},"opsz":{"axisValueTokenName":"md.sys.typescale.title-medium.opsz"},"GRAD":{"axisValueTokenName":"md.sys.typescale.title-medium.grad"},"ROND":{"axisValueTokenName":"md.sys.typescale.title-medium.rond"},"CRSV":{"axisValueTokenName":"md.sys.typescale.title-medium.crsv"},"HEXP":{"axisValueTokenName":"md.sys.typescale.title-medium.hexp"},"FILL":{"axisValueTokenName":"md.sys.typescale.title-medium.fill"}}},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"type":{"fontNameTokenName":"md.sys.typescale.title-medium.font","fontWeightTokenName":"md.sys.typescale.title-medium.weight","fontSizeTokenName":"md.sys.typescale.title-medium.size","fontTrackingTokenName":"md.sys.typescale.title-medium.tracking","lineHeightTokenName":"md.sys.typescale.title-medium.line-height","variationAxes":{"wght":{"axisValueTokenName":"md.sys.typescale.title-medium.wght"},"GRAD":{"axisValueTokenName":"md.sys.typescale.title-medium.grad"},"wdth":{"axisValueTokenName":"md.sys.typescale.title-medium.wdth"},"ROND":{"axisValueTokenName":"md.sys.typescale.title-medium.rond"},"opsz":{"axisValueTokenName":"md.sys.typescale.title-medium.opsz"},"CRSV":{"axisValueTokenName":"md.sys.typescale.title-medium.crsv"},"slnt":{"axisValueTokenName":"md.sys.typescale.title-medium.slnt"},"FILL":{"axisValueTokenName":"md.sys.typescale.title-medium.fill"},"HEXP":{"axisValueTokenName":"md.sys.typescale.title-medium.hexp"}}},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"} | {"fontNameTokenName":"md.sys.typescale.title-medium.font","fontWeightTokenName":"md.sys.typescale.title-medium.weight","fontSizeTokenName":"md.sys.typescale.title-medium.size","fontTrackingTokenName":"md.sys.typescale.title-medium.tracking","lineHeightTokenName":"md.sys.typescale.title-medium.line-height"}
  md.sys.typescale.title-medium.crsv                                          AXIS_VALUE    {"axisValue":{"tag":"CRSV"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.fill                                          AXIS_VALUE    {"axisValue":{"tag":"FILL"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.font                                          FONT_NAMES    -> md.ref.typeface.brand | -> md.ref.typeface.plain
  md.sys.typescale.title-medium.grad                                          AXIS_VALUE    {"axisValue":{"tag":"GRAD"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.hexp                                          AXIS_VALUE    {"axisValue":{"tag":"HEXP"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.line-height                                   LINE_HEIGHT   {"value":24,"unit":"POINTS"}
  md.sys.typescale.title-medium.opsz                                          AXIS_VALUE    {"axisValue":{"tag":"opsz","value":16},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.rond                                          AXIS_VALUE    {"axisValue":{"tag":"ROND"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.size                                          FONT_SIZE     {"value":16,"unit":"POINTS"}
  md.sys.typescale.title-medium.slnt                                          AXIS_VALUE    {"axisValue":{"tag":"slnt"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.tracking                                      FONT_TRACKING  {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"unit":"POINTS"} | {"fontTracking":{"value":0.15,"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4"}
  md.sys.typescale.title-medium.wdth                                          AXIS_VALUE    {"axisValue":{"tag":"wdth","value":100},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}
  md.sys.typescale.title-medium.weight                                        FONT_WEIGHT   -> md.ref.typeface.weight-medium | -> md.ref.typeface.weight-bold
  md.sys.typescale.title-medium.wght                                          AXIS_VALUE    {"axisValue":{"tag":"wght","value":500},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"}

included: 190
skipped (deprecated): 0
```
