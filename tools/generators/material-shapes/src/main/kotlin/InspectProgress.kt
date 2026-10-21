/*
 * Copyright 2024 The Android Open Source Project
 * SPDX-License-Identifier: Apache-2.0
 * CircularShapes recipe adapted from AndroidX CircularWavyProgressModifiers.kt
 * at 2e1a16243ae2c9284445a746f1d487c73af92623.
 * Changes: JVM-only inspection; no Compose renderer, path extraction or UI.
 * License: ../../../LICENSE-APACHE-2.0.txt.
 */
import androidx.graphics.shapes.CornerRounding
import androidx.graphics.shapes.Morph
import androidx.graphics.shapes.RoundedPolygon
import androidx.graphics.shapes.circle
import androidx.graphics.shapes.star
import kotlin.math.PI
import kotlin.math.abs
import kotlin.math.max
import kotlin.math.roundToInt

/** Executes the upstream shape recipe, without generating product assets. */
fun main() {
    for (size in listOf(24f, 48f, 240f)) {
        val radius = size / 2 - 4f / 2
        val vertices = max(5, (2 * PI * radius / 15f).roundToInt())
        val track = RoundedPolygon.circle(numVertices = vertices).normalized()
        val active = RoundedPolygon.star(
            numVerticesPerRadius = vertices,
            innerRadius = .75f,
            rounding = CornerRounding(radius = .35f, smoothing = .4f),
            innerRounding = CornerRounding(radius = .5f),
        ).normalized()
        val morph = Morph(track, active)
        val counts = mutableSetOf<Int>()
        for (amplitude in listOf(0f, .25f, .5f, .75f, 1f)) {
            val curves = morph.asCubics(amplitude)
            check(curves.isNotEmpty())
            counts.add(curves.size)
            for (i in curves.indices) {
                val c = curves[i]
                val next = curves[(i + 1) % curves.size]
                val coordinates = listOf(c.anchor0X, c.anchor0Y, c.control0X,
                    c.control0Y, c.control1X, c.control1Y, c.anchor1X, c.anchor1Y)
                check(coordinates.all { it.isFinite() })
                check(abs(c.anchor1X - next.anchor0X) < .00001f)
                check(abs(c.anchor1Y - next.anchor0Y) < .00001f)
            }
        }
        check(counts.size == 1) { "Morph topology changed with amplitude" }
        println("circular wave inspection: size=$size stroke=4 wavelength=15 vertices=$vertices matchedCubics=${counts.first()}; five amplitudes finite and closed")
    }
}
