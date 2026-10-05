# M3 token table: Bottom sheets

Official specs URL: https://m3.material.io/components/bottom-sheets/specs
TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.75e530a1014802b3.json

```text
component: Bottom sheets   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.sheet]
  md.comp.sheet.bottom.docked.container.color               COLOR         -> md.sys.color.surface-container-low
  md.comp.sheet.bottom.docked.container.shape               SHAPE         -> md.sys.shape.corner.extra-large.top
  md.comp.sheet.bottom.docked.drag-handle.color             COLOR         -> md.sys.color.on-surface-variant
  md.comp.sheet.bottom.docked.drag-handle.height            LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.sheet.bottom.docked.drag-handle.width             LENGTH        {"value":32,"unit":"DIPS"}
  md.comp.sheet.bottom.docked.minimized.container.shape     SHAPE         -> md.sys.shape.corner.none
  md.comp.sheet.bottom.docked.modal.container.elevation     ELEVATION     -> md.sys.elevation.level1
  md.comp.sheet.bottom.docked.standard.container.elevation  ELEVATION     -> md.sys.elevation.level1
  md.comp.sheet.bottom.focus.indicator.color                COLOR         -> md.sys.color.secondary
  md.comp.sheet.bottom.focus.indicator.outline.offset       LENGTH        -> md.sys.state.focus-indicator.outer-offset
  md.comp.sheet.bottom.focus.indicator.thickness            LENGTH        -> md.sys.state.focus-indicator.thickness
[md.ref.palette]
  md.ref.palette.neutral-variant0                           COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant100                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant20                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant30                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant80                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral-variant90                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral10                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral96                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary20                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                                COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary95                                COLOR         (per scheme; the theme owns the value)
[md.sys.color]
  md.sys.color.on-surface-variant                           COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary                                      COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface-container-low                        COLOR         (per scheme; the theme owns the value)
[md.sys.elevation]
  md.sys.elevation.level1                                   ELEVATION     {"value":1,"unit":"DIPS"}
[md.sys.shape]
  md.sys.shape.corner.extra-large.top                       SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","topLeft":{"value":28,"unit":"DIPS"},"topRight":{"value":28,"unit":"DIPS"},"defaultSize":{"unit":"DIPS"}}
  md.sys.shape.corner.none                                  SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"unit":"DIPS"}}
[md.sys.state]
  md.sys.state.focus-indicator.outer-offset                 LENGTH        {"value":2,"unit":"DIPS"}
  md.sys.state.focus-indicator.thickness                    LENGTH        {"value":3,"unit":"DIPS"}

included: 40
skipped (deprecated): 3
  - md.comp.sheet.bottom.docked.container.surface-tint-layer.color
      Deprecated as part of the update from opacity based surfaces to tonal surfaces. Surfaces no longer use surface-tint layers for tinting, please use the desired surface role directly as the container color.
  - md.comp.sheet.bottom.docked.drag-handle.opacity
      Deprecated per b/278783477
  - md.sys.color.surface-tint
      This token should no longer be used and its function/usage has been replaced by the tonal surface colors
```
