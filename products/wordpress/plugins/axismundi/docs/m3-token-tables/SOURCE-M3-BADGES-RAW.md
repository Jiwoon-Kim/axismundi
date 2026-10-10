# Raw M3 token table: badges

Official specs URL: https://m3.material.io/components/badges/specs

Observed TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.59a1b3a9a90ca8b3.json

Earlier failure record, kept because it says what went wrong: no matching
TOKEN_TABLE*.json was observed after a 5-second CSR wait, so the generator was
never run. The wait was the whole problem -- the asset appears on this page
later than that. Observed on 2026-10-11 with a 12-second wait, from the same
2026-09-23_06-10-05 snapshot the other tables in this directory came from.

```text
component: Badges   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.badge]
  md.comp.badge.color                         COLOR         -> md.sys.color.error
  md.comp.badge.large.color                   COLOR         -> md.sys.color.error
  md.comp.badge.large.label-text.color        COLOR         -> md.sys.color.on-error
  md.comp.badge.large.label-text.font         FONT_NAMES    -> md.sys.typescale.label-small.font
  md.comp.badge.large.label-text.line-height  LINE_HEIGHT   -> md.sys.typescale.label-small.line-height
  md.comp.badge.large.label-text.size         FONT_SIZE     -> md.sys.typescale.label-small.size
  md.comp.badge.large.label-text.tracking     FONT_TRACKING  -> md.sys.typescale.label-small.tracking
  md.comp.badge.large.label-text.type         TYPOGRAPHY    {"fontNameTokenName":"md.comp.badge.large.label-text.font","fontWeightTokenName":"md.comp.badge.large.label-text.weight","fontSizeTokenName":"md.comp.badge.large.label-text.size","fontTrackingTokenName":"md.comp.badge.large.label-text.tracking","lineHeightTokenName":"md.comp.badge.large.label-text.line-height"}
  md.comp.badge.large.label-text.weight       FONT_WEIGHT   -> md.sys.typescale.label-small.weight
  md.comp.badge.large.shape                   SHAPE         -> md.sys.shape.corner.full
  md.comp.badge.large.size                    LENGTH        {"value":16,"unit":"DIPS"}
  md.comp.badge.shape                         SHAPE         -> md.sys.shape.corner.full
  md.comp.badge.size                          LENGTH        {"value":6,"unit":"DIPS"}
[md.ref.palette]
  md.ref.palette.error0                       COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error10                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error100                     COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error20                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error30                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error40                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error80                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error90                      COLOR         (per scheme; the theme owns the value)
  md.ref.palette.error95                      COLOR         (per scheme; the theme owns the value)
[md.ref.typeface]
  md.ref.typeface.plain                       FONT_NAMES    {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Roboto Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/4fa3c9edf61f5705","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20","fontNames":{"values":["Google Sans Flex"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4","fontNames":{"values":["Roboto"]}} | {"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"2","fontNames":{"values":["System"]}} | {"values":["Google Sans Text"]}
  md.ref.typeface.weight-medium               FONT_WEIGHT   {"fontWeight":600,"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/60f04c068cdc9df1/tags/2632f6c814786d5e","designSystems/20543ce18892f7d9/contextTagGroups/09bf0256851f709d/tags/0d7ad1717e7d7c59"],"specificityScore":"130"} | 500
[md.sys.color]
  md.sys.color.error                          COLOR         (per scheme; the theme owns the value)
  md.sys.color.on-error                       COLOR         (per scheme; the theme owns the value)
[md.sys.shape]
  md.sys.shape.corner.full                    SHAPE         {"family":"SHAPE_FAMILY_CIRCULAR"}
[md.sys.typescale]
  md.sys.typescale.label-small.font           FONT_NAMES    -> md.ref.typeface.plain
  md.sys.typescale.label-small.line-height    LINE_HEIGHT   {"value":16,"unit":"POINTS"}
  md.sys.typescale.label-small.size           FONT_SIZE     {"value":11,"unit":"POINTS"}
  md.sys.typescale.label-small.tracking       FONT_TRACKING  {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499","designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"20"} | {"fontTracking":{"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/3397b1a67a15bfaf/tags/706d14a5e60ed83c"],"specificityScore":"16"} | {"fontTracking":{"value":0.5,"unit":"POINTS"},"contextTags":["designSystems/20543ce18892f7d9/contextTagGroups/38657353fa5c2ab9/tags/0b5c40268cdb2499"],"specificityScore":"4"} | {"value":0.1,"unit":"POINTS"}
  md.sys.typescale.label-small.weight         FONT_WEIGHT   -> md.ref.typeface.weight-medium

included: 32
skipped (deprecated): 0
```
