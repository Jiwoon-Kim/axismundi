# Material loading shapes

The generated polygon and morph data, and the static SVGs beside this file,
derive from The Android Open Source Project's MaterialShapes definitions.
They retain Apache-2.0 attribution. Full license: `LICENSE-APACHE-2.0.txt`.

- Copyright (C) 2024 The Android Open Source Project (MaterialShapes).
- Copyright 2023 The Android Open Source Project (graphics-shapes).
- Factories: Material Components Android commit `66b0eeb53afeb6025ffbbd37734b08e5bce197ed`,
  `lib/java/com/google/android/material/shape/MaterialShapes.java`.
  https://github.com/material-components/material-components-android/blob/66b0eeb53afeb6025ffbbd37734b08e5bce197ed/lib/java/com/google/android/material/shape/MaterialShapes.java
- Geometry and matching: `androidx.graphics:graphics-shapes-desktop:1.1.0`.
  https://developer.android.com/jetpack/androidx/releases/graphics
- Generator: `tools/generators/material-shapes/`. See its README for reproduction.

Changes: seven factories adapted to JVM point transforms instead of Android
Matrix/PointF/RectF; radial normalization to a unit viewBox; seven-decimal
coordinate serialization; adjacent `Morph` endpoints exported as matched cubic
arrays. SVGs contain only a viewBox and path, with no Figma layers or metadata.
Runtime colour and sizing belong to the consumer. Neither the Kotlin library
nor an Android runtime is included in the browser bundle.

The generator's adapted factory file retains the original copyright notice.
The upstream Maven graphics-shapes distribution provides an Apache license in
its POM. No upstream NOTICE was identified in the source directories used here;
recheck notices when updating the pinned inputs.
