# M3 token table: Switch

Official specs URL: https://m3.material.io/components/switch/specs
TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.33b1b2925d9ff561.json

```text
component: Switch   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.switch]
  md.comp.switch.disabled.selected.handle.color           COLOR         -> md.sys.color.surface
  md.comp.switch.disabled.selected.handle.opacity         OPACITY       1
  md.comp.switch.disabled.selected.icon.color             COLOR         -> md.sys.color.on-surface
  md.comp.switch.disabled.selected.icon.opacity           OPACITY       0.38
  md.comp.switch.disabled.selected.track.color            COLOR         -> md.sys.color.on-surface
  md.comp.switch.disabled.track.opacity                   OPACITY       0.12
  md.comp.switch.disabled.unselected.handle.color         COLOR         -> md.sys.color.on-surface
  md.comp.switch.disabled.unselected.handle.opacity       OPACITY       0.38
  md.comp.switch.disabled.unselected.icon.color           COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.disabled.unselected.icon.opacity         OPACITY       0.38
  md.comp.switch.disabled.unselected.track.color          COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.disabled.unselected.track.outline.color  COLOR         -> md.sys.color.on-surface
  md.comp.switch.focus.indicator.color                    COLOR         -> md.sys.color.secondary
  md.comp.switch.focus.indicator.offset                   LENGTH        -> md.sys.state.focus-indicator.outer-offset
  md.comp.switch.focus.indicator.thickness                LENGTH        -> md.sys.state.focus-indicator.thickness
  md.comp.switch.handle.shape                             SHAPE         -> md.sys.shape.corner.full
  md.comp.switch.pressed.handle.height                    LENGTH        {"value":28,"unit":"DIPS"}
  md.comp.switch.pressed.handle.width                     LENGTH        {"value":28,"unit":"DIPS"}
  md.comp.switch.selected.focus.handle.color              COLOR         -> md.sys.color.primary-container
  md.comp.switch.selected.focus.icon.color                COLOR         -> md.sys.color.primary
  md.comp.switch.selected.focus.state-layer.color         COLOR         -> md.sys.color.primary
  md.comp.switch.selected.focus.state-layer.opacity       OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.switch.selected.focus.track.color               COLOR         -> md.sys.color.primary
  md.comp.switch.selected.handle.color                    COLOR         -> md.sys.color.on-primary
  md.comp.switch.selected.handle.height                   LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.switch.selected.handle.width                    LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.switch.selected.hover.handle.color              COLOR         -> md.sys.color.primary-container
  md.comp.switch.selected.hover.icon.color                COLOR         -> md.sys.color.primary
  md.comp.switch.selected.hover.state-layer.color         COLOR         -> md.sys.color.primary
  md.comp.switch.selected.hover.state-layer.opacity       OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.switch.selected.hover.track.color               COLOR         -> md.sys.color.primary
  md.comp.switch.selected.icon.color                      COLOR         -> md.sys.color.primary
  md.comp.switch.selected.icon.size                       LENGTH        {"value":16,"unit":"DIPS"}
  md.comp.switch.selected.pressed.handle.color            COLOR         -> md.sys.color.primary-container
  md.comp.switch.selected.pressed.icon.color              COLOR         -> md.sys.color.primary
  md.comp.switch.selected.pressed.state-layer.color       COLOR         -> md.sys.color.primary
  md.comp.switch.selected.pressed.state-layer.opacity     OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.switch.selected.pressed.track.color             COLOR         -> md.sys.color.primary
  md.comp.switch.selected.track.color                     COLOR         -> md.ref.palette.primary60 | -> md.ref.palette.primary50 | -> md.sys.color.primary
  md.comp.switch.state-layer.shape                        SHAPE         -> md.sys.shape.corner.full
  md.comp.switch.state-layer.size                         LENGTH        {"value":40,"unit":"DIPS"}
  md.comp.switch.track.height                             LENGTH        {"value":32,"unit":"DIPS"}
  md.comp.switch.track.outline.width                      LENGTH        {"value":2,"unit":"DIPS"}
  md.comp.switch.track.shape                              SHAPE         -> md.sys.shape.corner.full
  md.comp.switch.track.width                              LENGTH        {"value":52,"unit":"DIPS"}
  md.comp.switch.unselected.focus.handle.color            COLOR         -> md.sys.color.on-surface-variant
  md.comp.switch.unselected.focus.icon.color              COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.focus.state-layer.color       COLOR         -> md.sys.color.on-surface
  md.comp.switch.unselected.focus.state-layer.opacity     OPACITY       -> md.sys.state.focus.state-layer-opacity
  md.comp.switch.unselected.focus.track.color             COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.focus.track.outline.color     COLOR         -> md.sys.color.outline
  md.comp.switch.unselected.handle.color                  COLOR         -> md.sys.color.outline
  md.comp.switch.unselected.handle.height                 LENGTH        {"value":16,"unit":"DIPS"}
  md.comp.switch.unselected.handle.width                  LENGTH        {"value":16,"unit":"DIPS"}
  md.comp.switch.unselected.hover.handle.color            COLOR         -> md.sys.color.on-surface-variant
  md.comp.switch.unselected.hover.icon.color              COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.hover.state-layer.color       COLOR         -> md.sys.color.on-surface
  md.comp.switch.unselected.hover.state-layer.opacity     OPACITY       -> md.sys.state.hover.state-layer-opacity
  md.comp.switch.unselected.hover.track.color             COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.hover.track.outline.color     COLOR         -> md.sys.color.outline
  md.comp.switch.unselected.icon.color                    COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.icon.size                     LENGTH        {"value":16,"unit":"DIPS"}
  md.comp.switch.unselected.pressed.handle.color          COLOR         -> md.sys.color.on-surface-variant
  md.comp.switch.unselected.pressed.icon.color            COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.pressed.state-layer.color     COLOR         -> md.sys.color.on-surface
  md.comp.switch.unselected.pressed.state-layer.opacity   OPACITY       -> md.sys.state.pressed.state-layer-opacity
  md.comp.switch.unselected.pressed.track.color           COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.pressed.track.outline.color   COLOR         -> md.sys.color.outline
  md.comp.switch.unselected.track.color                   COLOR         -> md.sys.color.surface-container-highest
  md.comp.switch.unselected.track.outline.color           COLOR         -> md.sys.color.outline
  md.comp.switch.with-icon.handle.height                  LENGTH        {"value":24,"unit":"DIPS"}
  md.comp.switch.with-icon.handle.width                   LENGTH        {"value":24,"unit":"DIPS"}
[md.ref.palette]
  md.ref.palette.neutral-variant0                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant100                       COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant20                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant30                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant50                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant60                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant70                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant80                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant90                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant95                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral0                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral10                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral100                               COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral22                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral6                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral90                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral98                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary0                                 COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary10                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary100                               COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary50                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary60                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary20                              COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                              COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                              COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                              COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                              COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary95                              COLOR         (per scheme; the theme owns the value)
[md.sys.color]
  md.sys.color.on-primary                                 COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-surface                                 COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-surface-variant                         COLOR         (per scheme; the theme owns the value)
  md.sys.color.outline                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary-container                          COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary                                  COLOR         (per scheme; the theme owns the value)
  md.sys.color.shadow                                     COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface-container-highest                  COLOR         (per scheme; the theme owns the value)
[md.sys.elevation]
  md.sys.elevation.level0                                 ELEVATION     {"unit":"DIPS"}
  md.sys.elevation.level1                                 ELEVATION     {"value":1,"unit":"DIPS"}
[md.sys.shape]
  md.sys.shape.corner.full                                SHAPE         {"family":"SHAPE_FAMILY_CIRCULAR"}
[md.sys.state]
  md.sys.state.focus-indicator.outer-offset               LENGTH        {"value":2,"unit":"DIPS"}
  md.sys.state.focus-indicator.thickness                  LENGTH        {"value":3,"unit":"DIPS"}
  md.sys.state.focus.state-layer-opacity                  OPACITY       0.1
  md.sys.state.hover.state-layer-opacity                  OPACITY       0.08
  md.sys.state.pressed.state-layer-opacity                OPACITY       0.1

included: 124
skipped (deprecated): 6
  - md.comp.switch.disabled.handle.elevation
      The Material Switch component has been updated to a new design. Deprecated tokens are no longer required, new tokens have been added and token values updated. The new tokens only correspond to the new design
  - md.comp.switch.disabled.handle.opacity
      The Material Switch component has been updated to a new design. Deprecated tokens are no longer required, new tokens have been added and token values updated. The new tokens only correspond to the new design
  - md.comp.switch.handle.elevation
      The Material Switch component has been updated to a new design. Deprecated tokens are no longer required, new tokens have been added and token values updated. The new tokens only correspond to the new design
  - md.comp.switch.handle.height
      The Material Switch component has been updated to a new design. Deprecated tokens are no longer required, new tokens have been added and token values updated. The new tokens only correspond to the new design
  - md.comp.switch.handle.shadow-color
      The Material Switch component has been updated to a new design. Deprecated tokens are no longer required, new tokens have been added and token values updated. The new tokens only correspond to the new design
  - md.comp.switch.handle.width
      The Material Switch component has been updated to a new design. Deprecated tokens are no longer required, new tokens have been added and token values updated. The new tokens only correspond to the new design
```
