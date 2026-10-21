# Progress animation provenance

The indeterminate CSS timelines adapt the animation specifications in AndroidX
Material3 `ProgressIndicator.kt`:

- Copyright 2022 The Android Open Source Project
- License: Apache-2.0, included in `LICENSE-APACHE-2.0.txt`
- Source commit: `2e1a16243ae2c9284445a746f1d487c73af92623`
- Source: https://github.com/androidx/androidx/blob/2e1a16243ae2c9284445a746f1d487c73af92623/compose/material3/material3/src/commonMain/kotlin/androidx/compose/material3/ProgressIndicator.kt
- Easing: `MotionTokens.kt` at the same commit, resolved through existing theme tokens.

Changes: translate Kotlin infinite keyframes into registered CSS number
properties; use SVG active shapes and synchronized cutout masks; implement web
visibility, reduced-motion and accessibility behavior. No AndroidX runtime is
bundled. Material Web and Flutter were comparison references, not the selected
timing profile for linear. Circular now adapts MDC Android's advance animator:

- Copyright 2024 The Android Open Source Project; Apache-2.0.
- Source commit: `66b0eeb53afeb6025ffbbd37734b08e5bce197ed`
- Source: https://github.com/material-components/material-components-android/blob/66b0eeb53afeb6025ffbbd37734b08e5bce197ed/lib/java/com/google/android/material/progressindicator/CircularIndeterminateAdvanceAnimatorDelegate.java
- Changes: single-color repeating head/tail timelines expressed as CSS numbers;
  existing SVG track mask follows the same arc. Completion/color cycling are not ported.

Wavy geometry adapts `internal/LinearWavyProgressModifiers.kt` (quadratic recipe)
and `internal/CircularWavyProgressModifiers.kt` (normalized circle/star recipe)
at the AndroidX commit above, Copyright 2024 The Android Open Source Project.
`assets/circular-wave.json` is exported with graphics-shapes-desktop 1.1.0;
the pinned Compose source declares 1.2.0-alpha01, so coordinate identity with
that Compose build is not claimed. SVG dash extraction, countertranslation and
counterrotation replace Compose PathMeasure calls. Figma's constant amplitude
is used instead of the default Compose endpoint amplitude function.
The former sine-wave diagnostic has been removed. No Android runtime is bundled.

No NOTICE was identified alongside the source files used here. Recheck the
upstream distribution and notices when updating the adaptation.
