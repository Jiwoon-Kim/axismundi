# Raw M3 token table: loading-indicator

Official specs URL: https://m3.material.io/components/loading-indicator/specs

Observed TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.68895be451a51c31.json

```text
component: Loading indicator   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.loading-indicator]
  md.comp.loading-indicator.active-indicator.color            COLOR         -> md.sys.color.primary
  md.comp.loading-indicator.active-indicator.size             LENGTH        {"value":38,"unit":"DIPS"}
  md.comp.loading-indicator.contained.active-indicator.color  COLOR         -> md.sys.color.on-primary-container
  md.comp.loading-indicator.contained.container.color         COLOR         -> md.sys.color.primary-container
  md.comp.loading-indicator.container.height                  LENGTH        {"value":48,"unit":"DIPS"}
  md.comp.loading-indicator.container.shape                   SHAPE         -> md.sys.shape.corner.full
  md.comp.loading-indicator.container.width                   LENGTH        {"value":48,"unit":"DIPS"}
[md.ref.palette]
  md.ref.palette.primary0                                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary100                                   COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary60                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                    COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary60                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                                  COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                                  COLOR         (per scheme; the theme owns the value)
[md.sys.color]
  md.sys.color.on-primary-container                           COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary                                        COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary-container                              COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary-container                            COLOR         (per scheme; the theme owns the value)
[md.sys.shape]
  md.sys.shape.corner.full                                    SHAPE         {"family":"SHAPE_FAMILY_CIRCULAR"}

included: 26
skipped (deprecated): 1
  - md.comp.loading-indicator.container.color
      Deprecated in favor of a distinct variant which uses a different color mapping with, and without container.
```
