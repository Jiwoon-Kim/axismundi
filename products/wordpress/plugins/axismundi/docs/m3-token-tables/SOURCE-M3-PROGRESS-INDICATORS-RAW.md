# Raw M3 token table: progress-indicators

Official specs URL: https://m3.material.io/components/progress-indicators/specs

Observed TOKEN_TABLE URL: https://m3.material.io/_dsm/data/dsdb-m3/2026-09-23_06-10-05/TOKEN_TABLE.6f51f7ab7775a392.json

```text
component: Progress indicators   dsdb 38.2.9
table revision: 2026-05-27T22:44:15.009106Z

[md.comp.progress-indicator]
  md.comp.progress-indicator.active-indicator.color                                 COLOR         -> md.sys.color.primary
  md.comp.progress-indicator.active-indicator.shape                                 SHAPE         -> md.sys.shape.corner.full
  md.comp.progress-indicator.circular.active-indicator.thickness                    LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.progress-indicator.circular.active-indicator.wave.amplitude               LENGTH        {"value":1.6,"unit":"DIPS"}
  md.comp.progress-indicator.circular.active-indicator.wave.wavelength              LENGTH        {"value":15,"unit":"DIPS"}
  md.comp.progress-indicator.circular.size                                          LENGTH        {"value":40,"unit":"DIPS"}
  md.comp.progress-indicator.circular.track-active-indicator-space                  LENGTH        -> md.sys.measurement.space50
  md.comp.progress-indicator.circular.track.thickness                               LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.progress-indicator.circular.with-wave.size                                LENGTH        {"value":48,"unit":"DIPS"}
  md.comp.progress-indicator.linear.active-indicator.thickness                      LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.progress-indicator.linear.active-indicator.wave.amplitude                 LENGTH        {"value":3,"unit":"DIPS"}
  md.comp.progress-indicator.linear.active-indicator.wave.wavelength                LENGTH        {"value":40,"unit":"DIPS"}
  md.comp.progress-indicator.linear.height                                          LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.progress-indicator.linear.indeterminate.active-indicator.wave.wavelength  LENGTH        {"value":20,"unit":"DIPS"}
  md.comp.progress-indicator.linear.stop-indicator.size                             LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.progress-indicator.linear.stop-indicator.trailing-space                   LENGTH        -> md.sys.measurement.space0
  md.comp.progress-indicator.linear.track-active-indicator-space                    LENGTH        -> md.sys.measurement.space50
  md.comp.progress-indicator.linear.track.thickness                                 LENGTH        {"value":4,"unit":"DIPS"}
  md.comp.progress-indicator.linear.with-wave.height                                LENGTH        {"value":10,"unit":"DIPS"}
  md.comp.progress-indicator.stop-indicator.color                                   COLOR         -> md.sys.color.primary
  md.comp.progress-indicator.stop-indicator.shape                                   SHAPE         -> md.sys.shape.corner.full
  md.comp.progress-indicator.track.color                                            COLOR         -> md.sys.color.secondary-container
  md.comp.progress-indicator.track.shape                                            SHAPE         -> md.sys.shape.corner.full
[md.ref.palette]
  md.ref.palette.neutral22                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.neutral90                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary20                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary30                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary40                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary60                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary80                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary90                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.primary95                                                          COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary30                                                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary40                                                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary60                                                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary80                                                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.secondary90                                                        COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary20                                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary30                                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary40                                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary60                                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary80                                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary90                                                         COLOR         (per scheme; the theme owns the value)
  md.ref.palette.tertiary95                                                         COLOR         (per scheme; the theme owns the value)
[md.sys.color]
  md.sys.color.primary                                                              COLOR         (per scheme; the theme owns the value)
  md.sys.color.primary-container                                                    COLOR         (per scheme; the theme owns the value)
  md.sys.color.secondary-container                                                  COLOR         (per scheme; the theme owns the value)
  md.sys.color.surface-container-highest                                            COLOR         (per scheme; the theme owns the value)
  md.sys.color.tertiary                                                             COLOR         (per scheme; the theme owns the value)
  md.sys.color.tertiary-container                                                   COLOR         (per scheme; the theme owns the value)
[md.sys.measurement]
  md.sys.measurement.space0                                                         LENGTH        {"unit":"DIPS"}
  md.sys.measurement.space50                                                        LENGTH        {"value":4,"unit":"DIPS"}
[md.sys.shape]
  md.sys.shape.corner.full                                                          SHAPE         {"family":"SHAPE_FAMILY_CIRCULAR"}
  md.sys.shape.corner.none                                                          SHAPE         {"family":"SHAPE_FAMILY_ROUNDED_CORNERS","defaultSize":{"unit":"DIPS"}}

included: 54
skipped (deprecated): 33
  - md.comp.circular-progress-indicator.active-indicator.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.circular-progress-indicator.active-indicator.shape
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.circular-progress-indicator.active-indicator.width
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.circular-progress-indicator.four-color.active-indicator.four.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.circular-progress-indicator.four-color.active-indicator.one.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.circular-progress-indicator.four-color.active-indicator.three.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.circular-progress-indicator.four-color.active-indicator.two.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.circular-progress-indicator.size
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.active-indicator.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.active-indicator.height
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.active-indicator.shape
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.four-color.active-indicator.four.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.four-color.active-indicator.one.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.four-color.active-indicator.three.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.four-color.active-indicator.two.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.track.color
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.track.height
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.linear-progress-indicator.track.shape
      Token set deprecated in favour of a merged token set which combines the circular and linear progress indicator. Please use "md.com.progress-indicator" tokens instead.
  - md.comp.progress-indicator.active-indicator-track-space
      Token is deprecated.
  - md.comp.progress-indicator.active-indicator.thickness
      Token is deprecated.
  - md.comp.progress-indicator.circular.thick.active-indicator.thickness
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.circular.thick.size
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.circular.thick.track-active-indicator-space
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.circular.thick.track.thickness
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.linear.thick.active-indicator.thickness
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.linear.thick.height
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.linear.thick.stop-indicator.size
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.linear.thick.stop-indicator.trailing-space
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.linear.thick.track-active-indicator-space
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.linear.thick.track.thickness
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.linear.thick.with-wave.height
      No longer tokenized as a variant, but rather a sample configuration in code
  - md.comp.progress-indicator.stop-indicator.size
      Token is deprecated.
  - md.comp.progress-indicator.track.thickness
      Token is deprecated.
```
